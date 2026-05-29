<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMEC_ApiService {

	private const TIMEOUT      = 15;
	private const RETRY_CODES  = [ 429, 500, 502, 503, 504 ];

	private SMEC_Settings  $settings;
	private SMEC_Logger    $logger;
	private ?SMEC_Notifier $notifier = null;

	public function __construct( SMEC_Settings $settings, SMEC_Logger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	public function set_notifier( SMEC_Notifier $notifier ): void {
		$this->notifier = $notifier;
	}

	// -------------------------------------------------------------------------
	// Public API methods
	// -------------------------------------------------------------------------

	public function ping(): array {
		$result = $this->request( 'GET', 'ping' );
		if ( $result['success'] ) {
			return [ 'success' => true, 'message' => 'Připojení úspěšné.' ];
		}
		return [ 'success' => false, 'message' => $result['error'] ?? 'Připojení selhalo.' ];
	}

	public function get_contact_lists(): array {
		$result = $this->request( 'GET', 'contact-lists', [], [ 'limit' => 100, 'offset' => 0 ] );
		if ( ! $result['success'] ) {
			return [ 'success' => false, 'message' => $result['error'], 'data' => [] ];
		}
		$data = $result['body']['data'] ?? $result['body'] ?? [];
		return [ 'success' => true, 'data' => $data ];
	}

	public function create_contact_list( string $name ): array {
		$result = $this->request( 'POST', 'contact-lists', [
			'name'             => $name,
			'sendername'       => '',
			'senderemail'      => '',
			'replyto'          => '',
			'publicname'       => $name,
			'publicdescription'=> '',
		] );
		if ( ! $result['success'] ) {
			return [ 'success' => false, 'message' => $result['error'] ];
		}
		return [ 'success' => true, 'data' => $result['body']['data'] ?? [] ];
	}

	public function get_custom_fields( int $limit = 100, int $offset = 0 ): array {
		$result = $this->request( 'GET', 'customfields', [], [ 'limit' => $limit, 'offset' => $offset ] );
		if ( ! $result['success'] ) {
			return [ 'success' => false, 'message' => $result['error'], 'data' => [] ];
		}
		$data = $result['body']['data'] ?? $result['body'] ?? [];
		return [ 'success' => true, 'data' => $data ];
	}

	public function create_custom_field( array $field_data ): array {
		$result = $this->request( 'POST', 'customfields', $field_data );
		if ( ! $result['success'] ) {
			return [ 'success' => false, 'message' => $result['error'] ];
		}
		return [ 'success' => true, 'data' => $result['body']['data'] ?? [] ];
	}

	/**
	 * Import one or more contacts.
	 *
	 * @param array $contacts  Array of contact objects matching SE API /import format.
	 * @param array $settings  import settings (update_existing_contacts, skip_invalid_orders, etc.)
	 */
	public function import_contacts( array $contacts, array $import_settings = [] ): array {
		$body = [
			'settings' => array_merge( [
				'update_existing_contacts' => true,
				'skip_invalid_orders'      => true,
			], $import_settings ),
			'data' => $contacts,
		];

		$result = $this->request( 'POST', 'import', $body );
		if ( ! $result['success'] ) {
			return [ 'success' => false, 'message' => $result['error'], 'retryable' => $result['retryable'] ?? false ];
		}
		return [ 'success' => true, 'data' => $result['body'] ];
	}

	// -------------------------------------------------------------------------
	// Core HTTP layer
	// -------------------------------------------------------------------------

	private function request( string $method, string $endpoint, array $body = [], array $query = [] ): array {
		$cfg = $this->settings->get_api();

		if ( empty( $cfg['username'] ) || empty( $cfg['api_key'] ) ) {
			return [ 'success' => false, 'error' => 'API přihlašovací údaje nejsou nakonfigurované.' ];
		}

		// Validace base URL – zamezit SSRF
		$base_raw = $cfg['base_url'] ?? 'https://app.smartemailing.cz/api/v3/';
		$base_raw = rtrim( $base_raw, '/' );
		if ( ! SMEC_Settings::validate_webhook_url( $base_raw . '/ping' ) ) {
			// Fallback na bezpečnou výchozí URL
			$base_raw = 'https://app.smartemailing.cz/api/v3';
			$this->logger->error( 'Neplatna API base URL, pouzivam vychozi.', [], 'api' );
		}
		$base = $base_raw;
		$url  = $base . '/' . ltrim( $endpoint, '/' );

		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		// Sestavit auth bez logování klíče
		$auth = 'Basic ' . base64_encode( $cfg['username'] . ':' . $cfg['api_key'] );

		$args = [
			'method'  => strtoupper( $method ),
			'headers' => [
				'Authorization' => $auth,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			],
			'timeout' => self::TIMEOUT,
		];

		if ( ! empty( $body ) && in_array( strtoupper( $method ), [ 'POST', 'PUT', 'PATCH' ], true ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response  = wp_remote_request( $url, $args );
		$retryable = false;

		if ( is_wp_error( $response ) ) {
			$error = $response->get_error_message();
			$this->logger->error( 'API request failed', [
				'endpoint' => $endpoint,
				'method'   => $method,
				'error'    => $error,
			], 'api' );
			return [ 'success' => false, 'error' => $error, 'retryable' => true ];
		}

		$code        = wp_remote_retrieve_response_code( $response );
		$body_string = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body_string, true );

		if ( in_array( (int) $code, self::RETRY_CODES, true ) ) {
			$retryable = true;
		}

		if ( $this->settings->is_debug() ) {
			$this->logger->debug( 'API response', [
				'endpoint' => $endpoint,
				'method'   => $method,
				'code'     => $code,
				'response' => mb_substr( $body_string, 0, 2000 ),
			], 'api' );
		}

		if ( (int) $code >= 200 && (int) $code < 300 ) {
			// Reset error streak při úspěchu
			if ( $this->notifier ) {
				$this->notifier->reset_api_error_streak();
			}
			return [ 'success' => true, 'code' => $code, 'body' => $decoded ?? [] ];
		}

		$api_message = $decoded['message'] ?? $decoded['error'] ?? "HTTP {$code}";
		$this->logger->error( 'API error response', [
			'endpoint' => $endpoint,
			'code'     => $code,
			'response' => mb_substr( $body_string, 0, 1000 ),
		], 'api' );

		// Zaznamenat error streak
		if ( $this->notifier ) {
			$this->notifier->record_api_error();
		}

		return [
			'success'   => false,
			'error'     => $api_message,
			'code'      => $code,
			'retryable' => $retryable,
		];
	}
}
