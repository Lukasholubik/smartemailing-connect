<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMEC_Queue {

	private SMEC_ApiService $api;
	private SMEC_Settings   $settings;
	private SMEC_Logger     $logger;

	private const MAX_ATTEMPTS = 3;
	private const RETRY_DELAYS = [ 0, 300, 1800 ]; // seconds: immediate, 5 min, 30 min

	public function __construct( SMEC_ApiService $api, SMEC_Settings $settings, SMEC_Logger $logger ) {
		$this->api      = $api;
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	public function register_cron(): void {
		add_action( 'smec_process_queue', [ $this, 'process' ] );
		add_action( 'smec_clean_logs',    [ $this->logger, 'auto_cleanup' ] );

		if ( ! wp_next_scheduled( 'smec_process_queue' ) ) {
			wp_schedule_event( time(), 'hourly', 'smec_process_queue' );
		}
		if ( ! wp_next_scheduled( 'smec_clean_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'smec_clean_logs' );
		}
	}

	// -------------------------------------------------------------------------
	// Add item to queue
	// -------------------------------------------------------------------------
	public function add( string $mapping_id, array $contact_data ): int {
		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'smec_queue',
			[
				'mapping_id'   => sanitize_key( $mapping_id ),
				'contact_data' => wp_json_encode( $contact_data ),
				'attempts'     => 0,
				'max_attempts' => self::MAX_ATTEMPTS,
				'next_retry'   => current_time( 'mysql', true ),
				'status'       => 'pending',
				'created_at'   => current_time( 'mysql', true ),
				'updated_at'   => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : 0;
	}

	// -------------------------------------------------------------------------
	// Process pending items (cron)
	// -------------------------------------------------------------------------
	public function process(): void {
		// Zaznamenat že cron běží (pro detekci zastavení)
		$state = (array) get_option( 'smec_notifier_state', [] );
		$state['cron_last_run'] = time();
		update_option( 'smec_notifier_state', $state );

		global $wpdb;
		$table = $wpdb->prefix . 'smec_queue';

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'pending' AND next_retry <= %s ORDER BY next_retry ASC LIMIT 20",
				current_time( 'mysql', true )
			),
			ARRAY_A
		);

		foreach ( $items as $item ) {
			$this->process_item( $item );
		}
	}

	private function process_item( array $item ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'smec_queue';

		$contact_data = json_decode( $item['contact_data'], true );
		if ( ! $contact_data ) {
			$this->mark_failed( (int) $item['id'], 'Neplatná data kontaktu v queue.' );
			return;
		}

		$result = $this->api->import_contacts( [ $contact_data ] );
		$attempts = (int) $item['attempts'] + 1;

		if ( $result['success'] ) {
			$wpdb->update(
				$table,
				[ 'status' => 'done', 'attempts' => $attempts, 'updated_at' => current_time( 'mysql', true ) ],
				[ 'id' => $item['id'] ],
				[ '%s', '%d', '%s' ],
				[ '%d' ]
			);
			$this->logger->info( 'Queue item processed', [ 'id' => $item['id'], 'mapping_id' => $item['mapping_id'] ], 'queue' );
			return;
		}

		$retryable = $result['retryable'] ?? false;
		if ( ! $retryable || $attempts >= self::MAX_ATTEMPTS ) {
			$this->mark_failed( (int) $item['id'], $result['message'] ?? 'Unknown error', $attempts );
			return;
		}

		$delay      = self::RETRY_DELAYS[ min( $attempts, count( self::RETRY_DELAYS ) - 1 ) ];
		$next_retry = gmdate( 'Y-m-d H:i:s', time() + $delay );

		$wpdb->update(
			$table,
			[
				'attempts'      => $attempts,
				'next_retry'    => $next_retry,
				'error_message' => mb_substr( $result['message'] ?? '', 0, 500 ),
				'updated_at'    => current_time( 'mysql', true ),
			],
			[ 'id' => $item['id'] ],
			[ '%d', '%s', '%s', '%s' ],
			[ '%d' ]
		);
	}

	private function mark_failed( int $id, string $error, int $attempts = 0 ): void {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'smec_queue',
			[
				'status'        => 'failed',
				'attempts'      => $attempts,
				'error_message' => mb_substr( $error, 0, 500 ),
				'updated_at'    => current_time( 'mysql', true ),
			],
			[ 'id' => $id ],
			[ '%s', '%d', '%s', '%s' ],
			[ '%d' ]
		);
		$this->logger->error( 'Queue item failed permanently', [ 'id' => $id, 'error' => $error ], 'queue' );
	}

	// -------------------------------------------------------------------------
	// Admin: get items
	// -------------------------------------------------------------------------
	public function get_items( string $status = '', int $limit = 50, int $offset = 0 ): array {
		global $wpdb;
		$table  = $wpdb->prefix . 'smec_queue';
		$values = [];

		if ( $status ) {
			$sql      = $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d", $status, $limit, $offset );
		} else {
			$sql      = $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $limit, $offset );
		}

		return $wpdb->get_results( $sql, ARRAY_A ) ?: [];
	}

	public function count_items( string $status = '' ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'smec_queue';
		if ( $status ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) );
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	public function clear_items( string $status = 'done' ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'smec_queue';
		if ( $status ) {
			return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE status = %s", $status ) );
		}
		return (int) $wpdb->query( "DELETE FROM {$table}" );
	}

	public function retry_failed(): int {
		global $wpdb;
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}smec_queue SET status = 'pending', attempts = 0, next_retry = %s, updated_at = %s WHERE status = 'failed'",
				current_time( 'mysql', true ),
				current_time( 'mysql', true )
			)
		);
	}
}
