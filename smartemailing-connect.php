<?php
/**
 * Plugin Name: SmartEmailing Connect
 * Plugin URI:  https://reboost.cz
 * Description: Universal WordPress connector for SmartEmailing – API integration, webtracking, form connections, WooCommerce support, and reading time module.
 * Version:     1.4.0
 * Author:      Lukáš Holubík - Grou.cz
 * Text Domain: smartemailing-connect
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SMEC_VERSION',     '1.4.0' );
define( 'SMEC_PLUGIN_FILE', __FILE__ );
define( 'SMEC_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'SMEC_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'SMEC_PLUGIN_SLUG', 'smartemailing-connect' );

// ── Auto-updater via GitHub ───────────────────────────────────────────────
$smec_updater_file = SMEC_PLUGIN_DIR . 'vendor/plugin-update-checker/load-v5p5.php';
if ( file_exists( $smec_updater_file ) ) {
	require_once $smec_updater_file;

	$smec_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/Lukasholubik/smartemailing-connect/',
		SMEC_PLUGIN_FILE,
		'smartemailing-connect'
	);

	// Prodloužení timeout pro GitHub API – výchozí 3 s nestačí
	add_filter( 'http_request_timeout', static function ( int $timeout, string $url ): int {
		if ( str_contains( $url, 'github.com' ) || str_contains( $url, 'api.github.com' ) ) {
			return 15;
		}
		return $timeout;
	}, 10, 2 );
}

if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
	add_action( 'admin_notices', static function () {
		echo '<div class="notice notice-error"><p><strong>SmartEmailing Connect:</strong> Vyžaduje PHP 8.0 nebo vyšší. Aktuální verze: ' . esc_html( PHP_VERSION ) . '.</p></div>';
	} );
	return;
}

$smec_files = [
	'includes/grou-admin-group.php',
	'includes/Settings.php',
	'includes/Diagnostics/Diagnostics.php',
	'includes/Notifications/Notifier.php',
	'includes/Logging/Logger.php',
	'includes/Database/Database.php',
	'includes/Api/ApiService.php',
	'includes/Queue/Queue.php',
	'includes/Webtracking/Webtracking.php',
	'includes/Forms/MappingProcessor.php',
	'includes/Forms/FormManager.php',
	'includes/Forms/FormScanner.php',
	'includes/Forms/Integrations/ElementorForms.php',
	'includes/Forms/Integrations/ContactForm7.php',
	'includes/Forms/Integrations/FluentForms.php',
	'includes/Forms/Integrations/WPForms.php',
	'includes/Forms/Integrations/NativeRegistration.php',
	'includes/WooCommerce/WooCommerceIntegration.php',
	'includes/ReadingTime/ReadingTime.php',
	'includes/GTM/GTM.php',
	'includes/HealthCheck/HealthCheck.php',
	'includes/Admin/Admin.php',
	'includes/Activator.php',
	'includes/Plugin.php',
];

foreach ( $smec_files as $file ) {
	require_once SMEC_PLUGIN_DIR . $file;
}

register_activation_hook( __FILE__, [ 'SMEC_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'SMEC_Activator', 'deactivate' ] );

add_action( 'plugins_loaded', static function () {
	if ( get_option( 'smec_db_version' ) !== SMEC_VERSION ) {
		SMEC_Activator::upgrade();
	}
	( new SMEC_Plugin() )->run();
}, 5 );
