<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMEC_Activator {

	public static function activate(): void {
		self::create_tables();
		self::set_defaults();
		wp_schedule_event( time(), 'hourly', 'smec_process_queue' );
		wp_schedule_event( time(), 'daily',  'smec_clean_logs' );
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'smec_process_queue' );
		wp_clear_scheduled_hook( 'smec_clean_logs' );
		flush_rewrite_rules();
	}

	public static function upgrade(): void {
		self::create_tables();
		update_option( 'smec_db_version', SMEC_VERSION );
	}

	public static function create_tables(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$logs = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}smec_logs (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			type       VARCHAR(50)     NOT NULL DEFAULT 'general',
			level      VARCHAR(20)     NOT NULL DEFAULT 'info',
			message    TEXT            NOT NULL,
			context    LONGTEXT        NULL,
			created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY type       (type),
			KEY level      (level),
			KEY created_at (created_at)
		) $charset;";

		$queue = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}smec_queue (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			mapping_id    VARCHAR(100)    NOT NULL,
			contact_data  LONGTEXT        NOT NULL,
			attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
			max_attempts  TINYINT UNSIGNED NOT NULL DEFAULT 3,
			next_retry    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			status        VARCHAR(20)     NOT NULL DEFAULT 'pending',
			error_message TEXT            NULL,
			created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY status     (status),
			KEY next_retry (next_retry)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $logs );
		dbDelta( $queue );
	}

	private static function set_defaults(): void {
		if ( ! get_option( 'smec_api_settings' ) ) {
			add_option( 'smec_api_settings', [
				'username' => '',
				'api_key'  => '',
				'base_url' => 'https://app.smartemailing.cz/api/v3/',
				'enabled'  => 0,
			] );
		}

		if ( ! get_option( 'smec_webtracking_settings' ) ) {
			add_option( 'smec_webtracking_settings', [
				'enabled'        => 0,
				'guid'           => '',
				'position'       => 'footer',
				'exclude_admins' => 1,
				'exclude_roles'  => [],
				'excluded_pages' => [],
				'custom_code'    => '',
			] );
		}

		if ( ! get_option( 'smec_general_settings' ) ) {
			add_option( 'smec_general_settings', [
				'debug'               => 0,
				'delete_on_uninstall' => 0,
				'log_retention_days'  => 30,
				'modules'             => [
					'webtracking'  => 1,
					'forms'        => 1,
					'woocommerce'  => 1,
					'reading_time' => 1,
				],
			] );
		}

		if ( ! get_option( 'smec_reading_time_settings' ) ) {
			add_option( 'smec_reading_time_settings', [
				'wpm'             => 200,
				'label'           => 'Doba čtení:',
				'suffix'          => 'min',
				'show_icon'       => 1,
				'icon'            => 'clock',
				'auto_insert'     => 0,
				'auto_position'   => 'before',
				'post_types'      => [ 'post' ],
				'wrapper_tag'     => 'span',
				'css_class'       => '',
				'display'         => 'inline',
				'color'           => '',
				'icon_color'      => '',
				'font_size'       => '',
				'font_weight'     => '',
				'padding'         => '',
				'border_radius'   => '',
				'background'      => '',
				'text_align'      => '',
				'icon_size'       => '',
				'custom_css'      => '',
			] );
		}

		add_option( 'smec_db_version', SMEC_VERSION );
	}
}
