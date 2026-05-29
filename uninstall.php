<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Only delete data if user explicitly enabled this option
$general = get_option( 'smec_general_settings', [] );
if ( empty( $general['delete_on_uninstall'] ) ) {
	return;
}

// Remove DB tables
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}smec_logs" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}smec_queue" );

// Remove all options
$wpdb->query( "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE 'smec_%'" );

// Remove transients
delete_transient( 'smec_lists_cache' );
delete_transient( 'smec_customfields_cache' );

// Clear cron events
wp_clear_scheduled_hook( 'smec_process_queue' );
wp_clear_scheduled_hook( 'smec_clean_logs' );
