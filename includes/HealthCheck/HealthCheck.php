<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMEC_HealthCheck {

	private const CRON_HOOK       = 'smec_health_check';
	private const RESULT_OPTION   = 'smec_health_last_check';
	private const STATE_OPTION    = 'smec_health_check_state';
	private const NOTIFY_COOLDOWN = 86400; // 24h between repeat alerts

	private SMEC_Settings    $settings;
	private SMEC_Logger      $logger;
	private SMEC_Diagnostics $diagnostics;
	private SMEC_ApiService  $api;
	private SMEC_Notifier    $notifier;

	public function __construct(
		SMEC_Settings    $settings,
		SMEC_Logger      $logger,
		SMEC_Diagnostics $diagnostics,
		SMEC_ApiService  $api,
		SMEC_Notifier    $notifier
	) {
		$this->settings    = $settings;
		$this->logger      = $logger;
		$this->diagnostics = $diagnostics;
		$this->api         = $api;
		$this->notifier    = $notifier;
	}

	// -------------------------------------------------------------------------
	// Schedule
	// -------------------------------------------------------------------------

	public function schedule(): void {
		add_action( self::CRON_HOOK, [ $this, 'run' ] );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 3600, 'daily', self::CRON_HOOK );
		}
	}

	public function unschedule(): void {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
		}
	}

	// -------------------------------------------------------------------------
	// Main check – called by cron or manually
	// -------------------------------------------------------------------------

	public function run(): void {
		$checks   = [];
		$failures = [];

		// 1. Live API ping
		$ping = $this->api->ping();
		$checks[] = [
			'name'   => 'API připojení SmartEmailing',
			'ok'     => $ping['success'],
			'detail' => $ping['success'] ? 'OK' : $ping['message'],
		];
		if ( ! $ping['success'] ) {
			$failures[] = [
				'check'  => 'API připojení SmartEmailing',
				'detail' => $ping['message'],
			];
		}

		// 2. All diagnostics – flag critical ones as failures
		$issues = $this->diagnostics->run();
		foreach ( $issues as $issue ) {
			$is_crit = $issue['level'] === 'critical';
			$checks[] = [
				'name'   => $issue['title'],
				'ok'     => ! $is_crit,
				'detail' => $is_crit ? $issue['message'] : 'OK',
				'level'  => $issue['level'],
			];
			if ( $is_crit ) {
				$failures[] = [
					'check'  => $issue['title'],
					'detail' => $issue['message'],
				];
			}
		}

		// 3. WP-Cron activity check (queue processor should run at least every 12h if there is work)
		$notifier_state = $this->notifier->get_state_public();
		$last_cron      = (int) ( $notifier_state['cron_last_run'] ?? 0 );
		if ( $last_cron > 0 && ( time() - $last_cron ) > 12 * HOUR_IN_SECONDS ) {
			$hours    = round( ( time() - $last_cron ) / HOUR_IN_SECONDS );
			$checks[] = [
				'name'   => 'WP-Cron (fronta kontaktů)',
				'ok'     => false,
				'detail' => "WP-Cron se nespustil {$hours} hodin.",
			];
			$failures[] = [
				'check'  => 'WP-Cron (fronta kontaktů)',
				'detail' => "WP-Cron se nespustil {$hours} hodin.",
			];
		}

		$status = empty( $failures ) ? 'ok' : 'failed';

		update_option( self::RESULT_OPTION, [
			'timestamp'      => time(),
			'status'         => $status,
			'failures_count' => count( $failures ),
			'checks'         => $checks,
			'failures'       => $failures,
		] );

		$this->logger->info(
			'Health check: ' . $status . ( $failures ? ' (' . count( $failures ) . ' problémů)' : '' ),
			[ 'failures' => count( $failures ) ],
			'system'
		);

		// Notify only when there are failures and we are not in cooldown
		if ( ! empty( $failures ) && ! $this->is_on_cooldown() ) {
			$cfg = $this->settings->get_notification_settings();
			if ( ! empty( $cfg['enabled'] ) ) {
				$this->notifier->send_health_alert( $failures, $cfg );
				$this->mark_notified();
			}
		}
	}

	// -------------------------------------------------------------------------
	// Public getters
	// -------------------------------------------------------------------------

	public function get_last_result(): array {
		$result = get_option( self::RESULT_OPTION, null );
		if ( ! is_array( $result ) ) {
			return [
				'timestamp'      => null,
				'status'         => 'unknown',
				'failures_count' => 0,
				'checks'         => [],
				'failures'       => [],
			];
		}
		return $result;
	}

	public function get_next_scheduled(): int {
		return (int) wp_next_scheduled( self::CRON_HOOK );
	}

	// -------------------------------------------------------------------------
	// Cooldown helpers
	// -------------------------------------------------------------------------

	private function is_on_cooldown(): bool {
		$state = get_option( self::STATE_OPTION, [] );
		$last  = (int) ( $state['last_notified'] ?? 0 );
		return $last > 0 && ( time() - $last ) < self::NOTIFY_COOLDOWN;
	}

	private function mark_notified(): void {
		update_option( self::STATE_OPTION, [ 'last_notified' => time() ] );
	}
}
