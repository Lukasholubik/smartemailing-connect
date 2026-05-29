<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Thin wrapper – table name helpers used across classes.
class SMEC_Database {

	public static function table_logs(): string {
		global $wpdb;
		return $wpdb->prefix . 'smec_logs';
	}

	public static function table_queue(): string {
		global $wpdb;
		return $wpdb->prefix . 'smec_queue';
	}

	public static function drop_tables(): void {
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}smec_logs" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}smec_queue" );
	}

	public static function delete_all_options(): void {
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE 'smec_%'"
		);
		delete_transient( 'smec_lists_cache' );
		delete_transient( 'smec_customfields_cache' );
	}
}
