<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMEC_Plugin {

	private SMEC_Settings            $settings;
	private SMEC_Logger              $logger;
	private SMEC_ApiService          $api;
	private SMEC_Queue               $queue;
	private SMEC_Webtracking         $webtracking;
	private SMEC_FormManager         $form_manager;
	private SMEC_MappingProcessor    $processor;
	private SMEC_ReadingTime         $reading_time;
	private SMEC_Diagnostics         $diagnostics;
	private SMEC_Notifier            $notifier;
	private SMEC_Admin               $admin;

	public function run(): void {
		$this->settings     = new SMEC_Settings();
		$this->logger       = new SMEC_Logger( $this->settings );
		$this->api          = new SMEC_ApiService( $this->settings, $this->logger );
		$this->queue        = new SMEC_Queue( $this->api, $this->settings, $this->logger );
		$this->processor    = new SMEC_MappingProcessor( $this->logger );
		$this->form_manager = new SMEC_FormManager( $this->settings, $this->api, $this->processor, $this->queue, $this->logger );
		$this->webtracking  = new SMEC_Webtracking( $this->settings, $this->logger );
		$this->reading_time = new SMEC_ReadingTime( $this->settings );
		$this->diagnostics  = new SMEC_Diagnostics( $this->settings, $this->logger, $this->queue );
		$this->notifier     = new SMEC_Notifier( $this->settings, $this->logger, $this->queue );
		$this->admin        = new SMEC_Admin( $this->settings, $this->api, $this->form_manager, $this->queue, $this->logger, $this->diagnostics, $this->notifier );

		$modules = $this->settings->get_general()['modules'] ?? [];

		// Always load admin
		$this->admin->register();

		// Module: webtracking
		if ( ! empty( $modules['webtracking'] ) ) {
			$this->webtracking->register();
		}

		// Module: forms
		if ( ! empty( $modules['forms'] ) ) {
			$this->form_manager->register();
			$this->load_form_integrations();
		}

		// Module: WooCommerce (only if active)
		if ( ! empty( $modules['woocommerce'] ) && $this->is_woocommerce_active() ) {
			$woo = new SMEC_WooCommerceIntegration( $this->settings, $this->api, $this->logger );
			$woo->register();
		}

		// Module: reading time
		if ( ! empty( $modules['reading_time'] ) ) {
			$this->reading_time->register();
		}

		// Cron: queue processing
		$this->queue->register_cron();

		// Notifier: cron + předat referenci do API a webtrackingu
		$this->notifier->register();
		$this->api->set_notifier( $this->notifier );
		$this->webtracking->set_notifier( $this->notifier );

		// Admin notice: conflict with official SmartEmailing plugin
		if ( is_admin() ) {
			add_action( 'admin_notices', [ $this, 'check_official_plugin_conflict' ] );
		}
	}

	private function load_form_integrations(): void {
		// Elementor Pro Forms
		if ( did_action( 'elementor/loaded' ) || class_exists( '\ElementorPro\Plugin' ) || defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			( new SMEC_ElementorForms( $this->form_manager ) )->register();
		} else {
			// Register on init in case Elementor loads later
			add_action( 'elementor_pro/init', static function () use ( &$_this ) {}, 10 );
			add_action( 'init', function () {
				if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
					( new SMEC_ElementorForms( $this->form_manager ) )->register();
				}
			}, 20 );
		}

		// Contact Form 7
		if ( defined( 'WPCF7_VERSION' ) || class_exists( 'WPCF7' ) ) {
			( new SMEC_ContactForm7( $this->form_manager ) )->register();
		} else {
			add_action( 'plugins_loaded', function () {
				if ( defined( 'WPCF7_VERSION' ) ) {
					( new SMEC_ContactForm7( $this->form_manager ) )->register();
				}
			}, 20 );
		}

		// Fluent Forms
		if ( defined( 'FLUENTFORM_VERSION' ) || class_exists( 'FluentForm\App\Modules\Form\Form' ) ) {
			( new SMEC_FluentForms( $this->form_manager ) )->register();
		} else {
			add_action( 'fluentform/loaded', function () {
				( new SMEC_FluentForms( $this->form_manager ) )->register();
			} );
		}

		// WPForms
		if ( defined( 'WPFORMS_VERSION' ) || function_exists( 'wpforms' ) ) {
			( new SMEC_WPForms( $this->form_manager ) )->register();
		} else {
			add_action( 'wpforms_loaded', function () {
				( new SMEC_WPForms( $this->form_manager ) )->register();
			} );
		}

		// Native WP registration
		( new SMEC_NativeRegistration( $this->form_manager ) )->register();
	}

	private function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' ) || defined( 'WC_VERSION' );
	}

	public function check_official_plugin_conflict(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;
		if ( is_plugin_active( 'smartemailing/smartemailing.php' ) ) {
			echo '<div class="notice notice-warning is-dismissible">';
			echo '<p><strong>SmartEmailing Connect:</strong> ';
			echo esc_html__( 'Je detekován oficiální plugin SmartEmailing. Hrozí duplicitní webtracking nebo dvojité odesílání kontaktů. Doporučujeme deaktivovat oficiální plugin, pokud SmartEmailing Connect plně nahrazuje jeho funkce.', 'smartemailing-connect' );
			echo '</p></div>';
		}
	}
}
