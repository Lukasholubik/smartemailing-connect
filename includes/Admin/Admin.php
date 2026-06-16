<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMEC_Admin {

	private SMEC_Settings     $settings;
	private SMEC_ApiService   $api;
	private SMEC_FormManager  $form_manager;
	private SMEC_Queue        $queue;
	private SMEC_Logger       $logger;
	private SMEC_Diagnostics  $diagnostics;
	private SMEC_Notifier     $notifier;

	private const CAPABILITY   = 'manage_options';
	private const MENU_SLUG    = 'smartemailing-connect';
	private const NONCE_ACTION = 'smec_admin_nonce';

	private const DEACTIVATABLE_PLUGINS = [
		'smartemailing/smartemailing.php',
	];

	public function __construct(
		SMEC_Settings $settings,
		SMEC_ApiService $api,
		SMEC_FormManager $form_manager,
		SMEC_Queue $queue,
		SMEC_Logger $logger,
		SMEC_Diagnostics $diagnostics,
		SMEC_Notifier $notifier
	) {
		$this->settings     = $settings;
		$this->api          = $api;
		$this->form_manager = $form_manager;
		$this->queue        = $queue;
		$this->logger       = $logger;
		$this->diagnostics  = $diagnostics;
		$this->notifier     = $notifier;
	}

	public function register(): void {
		add_action( 'admin_menu',            [ $this, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_notices',         [ $this, 'render_admin_notices' ] );
		$this->register_ajax_handlers();
	}

	// -------------------------------------------------------------------------
	// Admin notices – zobrazit na každé stránce WP adminu
	// -------------------------------------------------------------------------
	public function render_admin_notices(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) return;

		// Přeskočit stránky SMEC přehledu – tam jsou detaily přímo v obsahu
		$screen = get_current_screen();
		if ( $screen && $screen->id === 'toplevel_page_' . self::MENU_SLUG ) return;

		$issues = $this->diagnostics->run();
		$counts = $this->diagnostics->count_by_level( $issues );

		// Zobrazit jen pokud jsou critical nebo warning
		$show = array_filter( $issues, fn( $i ) => in_array( $i['level'], [ 'critical', 'warning' ], true ) );
		if ( empty( $show ) ) return;

		$type = $counts['critical'] > 0 ? 'error' : 'warning';

		echo '<div class="notice notice-' . esc_attr( $type ) . ' smec-admin-notice is-dismissible" id="smec-global-notice">';
		echo '<div class="smec-notice-header">';
		echo '<span class="smec-notice-icon">' . ( $counts['critical'] > 0 ? '🔴' : '⚠️' ) . '</span>';
		echo '<strong>SmartEmailing Connect</strong> – ';

		$parts = [];
		if ( $counts['critical'] ) $parts[] = $counts['critical'] . ' kritický problém' . ( $counts['critical'] > 1 ? 'y' : '' );
		if ( $counts['warning'] )  $parts[] = $counts['warning']  . ' varování';
		echo esc_html( implode( ', ', $parts ) );
		echo '</div>';

		echo '<ul class="smec-notice-list">';
		foreach ( array_slice( $show, 0, 3 ) as $issue ) {
			$icon = $issue['level'] === 'critical' ? '🔴' : '⚠️';
			echo '<li>';
			echo '<span class="smec-ni-icon">' . $icon . '</span> ';
			echo '<strong>' . esc_html( $issue['title'] ) . '</strong>';
			echo '</li>';
		}
		if ( count( $show ) > 3 ) {
			echo '<li class="smec-muted">… a další</li>';
		}
		echo '</ul>';

		echo '<p>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) . '" class="button button-small">Zobrazit přehled a řešení →</a>';
		echo '</p>';
		echo '</div>';
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------
	public function register_menus(): void {
		add_menu_page(
			'SmartEmailing Connect',
			'SmartEmailing',
			self::CAPABILITY,
			self::MENU_SLUG,
			[ $this, 'page_overview' ],
			'dashicons-email-alt2',
			31
		);

		// Zařadit do sekce Grou.cz v admin menu (priority 999 – po registraci menu)
		add_action( 'admin_menu', static function () {
			grou_register_admin_menu_group( 31 );
		}, 999 );

		add_action( 'admin_head', static function () {
			grou_output_admin_group_css();
		} );
		$modules = $this->settings->get_general()['modules'] ?? [];

		// Vždy zobrazit
		add_submenu_page( self::MENU_SLUG, 'Přehled',        'Přehled',        self::CAPABILITY, self::MENU_SLUG,   [ $this, 'page_overview' ] );
		add_submenu_page( self::MENU_SLUG, 'API připojení',  'API připojení',  self::CAPABILITY, 'smec-api',        [ $this, 'page_api' ] );

		// Záložky závislé na modulech
		if ( ! empty( $modules['webtracking'] ) ) {
			add_submenu_page( self::MENU_SLUG, 'Webtracking',    'Webtracking',    self::CAPABILITY, 'smec-webtracking',  [ $this, 'page_webtracking' ] );
		}
		add_submenu_page( self::MENU_SLUG, 'Seznamy & Pole', 'Seznamy & Pole', self::CAPABILITY, 'smec-lists',      [ $this, 'page_lists' ] );
		if ( ! empty( $modules['forms'] ) ) {
			add_submenu_page( self::MENU_SLUG, 'Formuláře',      'Formuláře',      self::CAPABILITY, 'smec-forms',        [ $this, 'page_forms' ] );
		}
		if ( ! empty( $modules['woocommerce'] ) ) {
			add_submenu_page( self::MENU_SLUG, 'WooCommerce',    'WooCommerce',    self::CAPABILITY, 'smec-woocommerce',  [ $this, 'page_woocommerce' ] );
		}
		if ( ! empty( $modules['reading_time'] ) ) {
			add_submenu_page( self::MENU_SLUG, 'Doba čtení',     'Doba čtení',     self::CAPABILITY, 'smec-reading-time', [ $this, 'page_reading_time' ] );
		}
		if ( ! empty( $modules['gtm'] ) ) {
			add_submenu_page( self::MENU_SLUG, 'Google Tag Manager', 'GTM',         self::CAPABILITY, 'smec-gtm',          [ $this, 'page_gtm' ] );
		}

		// Vždy zobrazit
		add_submenu_page( self::MENU_SLUG, 'Logy',           'Logy',           self::CAPABILITY, 'smec-logs',         [ $this, 'page_logs' ] );
		add_submenu_page( self::MENU_SLUG, 'Notifikace',     'Notifikace',     self::CAPABILITY, 'smec-notifications',[ $this, 'page_notifications' ] );
		add_submenu_page( self::MENU_SLUG, 'Nastavení',      'Nastavení',      self::CAPABILITY, 'smec-settings',     [ $this, 'page_settings' ] );
	}

	// -------------------------------------------------------------------------
	// Page callbacks
	// -------------------------------------------------------------------------
	private function load_template( string $name, array $data = [] ): void {
		if ( ! current_user_can( self::CAPABILITY ) ) wp_die( 'Přístup odepřen.' );

		// Bezpečnostní ochrana: pouze alfanumerické znaky a pomlčky (zamezit path traversal)
		if ( ! preg_match( '/^[a-z0-9\-]+$/', $name ) ) {
			wp_die( 'Neplatný název šablony.' );
		}

		$file = SMEC_PLUGIN_DIR . 'templates/admin/' . $name . '.php';

		// Ověřit, že soubor existuje UVNITŘ pluginu (defense in depth)
		$real_file   = realpath( $file );
		$real_base   = realpath( SMEC_PLUGIN_DIR . 'templates/admin/' );
		if ( ! $real_file || ! $real_base || ! str_starts_with( $real_file, $real_base ) ) {
			wp_die( 'Šablona nenalezena.' );
		}

		// Bezpečné předání proměnných – bez extract(), bez možnosti přepsání $file
		$settings    = $data['settings']    ?? null;
		$logger      = $data['logger']      ?? null;
		$queue       = $data['queue']       ?? null;
		$diagnostics = $data['diagnostics'] ?? null;
		$scanner     = $data['scanner']     ?? null;
		$notifier    = $data['notifier']    ?? null;

		include $real_file;
	}

	public function page_overview():     void {
		$scanner = new SMEC_FormScanner( $this->settings );
		$this->load_template( 'page-overview', [
			'settings'    => $this->settings,
			'logger'      => $this->logger,
			'queue'       => $this->queue,
			'diagnostics' => $this->diagnostics,
			'scanner'     => $scanner,
		] );
	}
	public function page_api():          void { $this->load_template( 'page-api',           [ 'settings' => $this->settings ] ); }
	public function page_webtracking():  void { $this->load_template( 'page-webtracking',   [ 'settings' => $this->settings ] ); }
	public function page_lists():        void { $this->load_template( 'page-lists',         [ 'settings' => $this->settings ] ); }
	public function page_forms():        void { $this->load_template( 'page-forms',         [ 'settings' => $this->settings ] ); }
	public function page_woocommerce():  void { $this->load_template( 'page-woocommerce',   [ 'settings' => $this->settings ] ); }
	public function page_reading_time(): void { $this->load_template( 'page-reading-time',  [ 'settings' => $this->settings ] ); }
	public function page_gtm():          void { $this->load_template( 'page-gtm',           [ 'settings' => $this->settings ] ); }
	public function page_logs():         void { $this->load_template( 'page-logs',          [ 'logger' => $this->logger, 'queue' => $this->queue ] ); }
	public function page_notifications(): void { $this->load_template( 'page-notifications',  [ 'settings' => $this->settings, 'notifier' => $this->notifier ] ); }
	public function page_settings():     void { $this->load_template( 'page-settings',      [ 'settings' => $this->settings ] ); }

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------
	public function enqueue_assets( string $hook ): void {
		$smec_pages = [
			'toplevel_page_smartemailing-connect',
			'smartemailing_page_smec-api',
			'smartemailing_page_smec-webtracking',
			'smartemailing_page_smec-gtm',
			'smartemailing_page_smec-lists',
			'smartemailing_page_smec-forms',
			'smartemailing_page_smec-woocommerce',
			'smartemailing_page_smec-reading-time',
			'smartemailing_page_smec-logs',
			'smartemailing_page_smec-notifications',
			'smartemailing_page_smec-settings',
		];

		if ( ! in_array( $hook, $smec_pages, true ) ) return;

		// Chart.js jen na přehledové stránce
		if ( $hook === 'toplevel_page_smartemailing-connect' ) {
			wp_enqueue_script(
				'chartjs',
				'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
				[],
				'4.4.0',
				true
			);
		}

		wp_enqueue_style(
			'smec-admin',
			SMEC_PLUGIN_URL . 'assets/admin/css/admin.css',
			[],
			SMEC_VERSION
		);
		wp_enqueue_script(
			'smec-admin',
			SMEC_PLUGIN_URL . 'assets/admin/js/admin.js',
			[ 'jquery' ],
			SMEC_VERSION,
			true
		);
		wp_localize_script( 'smec-admin', 'smecAdmin', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			'strings' => [
				'saved'    => 'Uloženo.',
				'error'    => 'Chyba.',
				'confirm_delete' => 'Opravdu smazat?',
				'testing'  => 'Testování...',
				'loading'  => 'Načítám...',
				'no_mappings' => 'Žádná propojení formulářů.',
			],
		] );
	}

	// -------------------------------------------------------------------------
	// AJAX handlers registration
	// -------------------------------------------------------------------------
	private function register_ajax_handlers(): void {
		$actions = [
			'smec_test_api',
			'smec_save_api',
			'smec_save_webtracking',
			'smec_fetch_lists',
			'smec_fetch_customfields',
			'smec_create_list',
			'smec_get_mappings',
			'smec_save_mapping',
			'smec_delete_mapping',
			'smec_duplicate_mapping',
			'smec_toggle_mapping',
			'smec_test_contact',
			'smec_get_logs',
			'smec_clear_logs',
			'smec_save_general_settings',
			'smec_save_reading_time',
			'smec_get_rt_presets',
			'smec_save_rt_preset',
			'smec_delete_rt_preset',
			'smec_duplicate_rt_preset',
			'smec_save_woocommerce',
			'smec_export_settings',
			'smec_import_settings',
			'smec_process_queue_now',
			'smec_clear_queue',
			'smec_retry_failed_queue',
			'smec_refresh_cache',
			'smec_run_diagnostics',
			'smec_deactivate_plugin',
			'smec_dismiss_notice',
			'smec_scan_forms',
			'smec_ignore_form',
			'smec_unignore_form',
			'smec_save_notifications',
			'smec_test_notification',
			'smec_reset_notifier_state',
			'smec_test_webtracking',
			'smec_create_customfield',
			'smec_save_gtm',
			'smec_test_gtm',
			'smec_get_chart_data',
			'smec_get_form_monitor_stats',
			'smec_get_form_monthly',
			'smec_health_check_now',
			'smec_get_health_status',
		];

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, [ $this, 'handle_ajax' ] );
		}

		// Log export – separate handler (file download, not JSON response)
		add_action( 'wp_ajax_smec_export_logs', [ $this, 'handle_export_logs' ] );
	}

	// Akce s rate limitem (max N volání za okno)
	private const RATE_LIMITED_ACTIONS = [
		'smec_get_chart_data'          => [ 'limit' => 30, 'window' => 60 ],
		'smec_get_form_monitor_stats'  => [ 'limit' => 30, 'window' => 60 ],
		'smec_get_form_monthly'        => [ 'limit' => 30, 'window' => 60 ],
		'smec_deactivate_plugin' => [ 'limit' => 3,  'window' => 300  ],  // 3× za 5 minut
		'smec_import_settings'   => [ 'limit' => 5,  'window' => 60   ],  // 5× za minutu
		'smec_clear_logs'        => [ 'limit' => 10, 'window' => 60   ],
		'smec_test_notification' => [ 'limit' => 5,  'window' => 60   ],
		'smec_process_queue_now' => [ 'limit' => 5,  'window' => 60   ],
		'smec_scan_forms'        => [ 'limit' => 10, 'window' => 60   ],
	];

	public function handle_ajax(): void {
		$action = sanitize_key( $_POST['action'] ?? $_GET['action'] ?? '' );

		// 1. Capability check (před nonce – nonce check je dražší)
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => 'Nedostatecna opravneni.' ], 403 );
		}

		// 2. Nonce verification
		$nonce = sanitize_text_field( $_POST['nonce'] ?? $_GET['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_send_json_error( [ 'message' => 'Neplatny bezpecnostni token.' ], 403 );
		}

		// 3. Rate limiting pro citlivé akce
		if ( isset( self::RATE_LIMITED_ACTIONS[ $action ] ) ) {
			$rl     = self::RATE_LIMITED_ACTIONS[ $action ];
			$key    = 'smec_rl_' . $action . '_' . get_current_user_id();
			$hits   = (int) get_transient( $key );
			if ( $hits >= $rl['limit'] ) {
				wp_send_json_error( [ 'message' => 'Prilis mnoho pozadavku. Zkuste to znovu za chvili.' ], 429 );
			}
			set_transient( $key, $hits + 1, $rl['window'] );
		}

		$method = 'ajax_' . str_replace( 'smec_', '', $action );
		if ( method_exists( $this, $method ) ) {
			$this->$method();
		} else {
			wp_send_json_error( [ 'message' => 'Neznama akce.' ], 400 );
		}
	}

	// -------------------------------------------------------------------------
	// AJAX: API
	// -------------------------------------------------------------------------
	private function ajax_test_api(): void {
		$result = $this->api->ping();
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	private function ajax_save_api(): void {
		$data = $_POST['data'] ?? [];
		if ( is_string( $data ) ) $data = json_decode( wp_unslash( $data ), true ) ?? [];
		$this->settings->save_api( (array) $data );
		wp_send_json_success( [ 'message' => 'API nastavení uloženo.' ] );
	}

	// -------------------------------------------------------------------------
	// AJAX: Webtracking
	// -------------------------------------------------------------------------
	private function ajax_save_webtracking(): void {
		$data = $_POST['data'] ?? [];
		if ( is_string( $data ) ) $data = json_decode( wp_unslash( $data ), true ) ?? [];
		$this->settings->save_webtracking( (array) $data );
		wp_send_json_success( [ 'message' => 'Webtracking nastavení uloženo.' ] );
	}

	// -------------------------------------------------------------------------
	// AJAX: GTM
	// -------------------------------------------------------------------------
	private function ajax_save_gtm(): void {
		$data = $_POST['data'] ?? [];
		if ( is_string( $data ) ) $data = json_decode( wp_unslash( $data ), true ) ?? [];
		$this->settings->save_gtm( (array) $data );
		$cfg = $this->settings->get_gtm();
		wp_send_json_success( [
			'message'      => 'GTM nastavení uloženo.',
			'container_id' => $cfg['container_id'],
		] );
	}

	private function ajax_test_gtm(): void {
		$cfg = $this->settings->get_gtm();

		if ( empty( $cfg['enabled'] ) ) {
			wp_send_json_success( [ 'status' => 'disabled', 'message' => 'GTM není aktivní. Zapněte jej v nastavení.', 'checks' => [] ] );
			return;
		}

		$container_id = $cfg['container_id'];
		if ( ! $container_id || ! preg_match( '/^GTM-[A-Z0-9]+$/i', $container_id ) ) {
			wp_send_json_success( [ 'status' => 'error', 'message' => 'Container ID není platné. Zadejte ve formátu GTM-XXXXXXX.', 'checks' => [] ] );
			return;
		}

		$checks = [
			[ 'label' => 'GTM je aktivní', 'ok' => true ],
			[ 'label' => 'Container ID: ' . esc_html( $container_id ), 'ok' => true ],
		];

		$response = wp_remote_get( home_url( '/' ), [
			'timeout'    => 10,
			'user-agent' => 'SmartEmailing Connect GTM Test',
			'sslverify'  => false,
		] );

		if ( is_wp_error( $response ) ) {
			wp_send_json_success( [ 'status' => 'error', 'message' => 'Nepodařilo se načíst homepage: ' . esc_html( $response->get_error_message() ), 'checks' => $checks ] );
			return;
		}

		$body          = wp_remote_retrieve_body( $response );
		$script_found  = str_contains( $body, 'googletagmanager.com/gtm.js' );
		$id_found      = str_contains( $body, $container_id );
		$noscript_found = str_contains( $body, 'googletagmanager.com/ns.html' );

		$checks[] = [ 'label' => 'GTM script nalezen v <head>', 'ok' => $script_found ];
		$checks[] = [ 'label' => 'Container ID odpovídá v kódu', 'ok' => $id_found ];
		$checks[] = [ 'label' => 'GTM noscript nalezen v <body>', 'ok' => $noscript_found ];

		if ( $script_found && $id_found ) {
			wp_send_json_success( [ 'status' => 'ok', 'message' => 'GTM funguje správně – kód je aktivní na homepage.', 'checks' => $checks ] );
		} else {
			$hints = [];
			if ( ! $script_found ) $hints[] = 'GTM script nebyl nalezen. Ověřte, že je modul aktivní a container ID správné.';
			if ( ! $noscript_found ) $hints[] = 'Noscript tag nebyl nalezen – téma pravděpodobně nepodporuje wp_body_open(), bude umístěn do patičky.';
			wp_send_json_success( [ 'status' => 'warning', 'message' => 'GTM je nastaven, ale kód nebyl nalezen v homepage.', 'checks' => $checks, 'hints' => $hints ] );
		}
	}

	private function ajax_test_webtracking(): void {
		$cfg  = $this->settings->get_webtracking();
		$checks = [];

		// 1. Je webtracking povolen?
		if ( empty( $cfg['enabled'] ) ) {
			wp_send_json_success( [
				'status'  => 'disabled',
				'message' => 'Webtracking není povolen. Zapněte jej v nastavení výše.',
				'checks'  => [],
			] );
			return;
		}
		$checks[] = [ 'label' => 'Webtracking je povolen', 'ok' => true ];

		// 2. Je GUID nastaven?
		$guid = sanitize_text_field( $cfg['guid'] ?? '' );
		if ( ! $guid ) {
			wp_send_json_success( [
				'status'  => 'error',
				'message' => 'GUID není nastaven. Vyplňte jej v poli výše.',
				'checks'  => $checks,
			] );
			return;
		}
		$checks[] = [ 'label' => 'GUID je nastaven (' . esc_html( substr( $guid, 0, 8 ) ) . '…)', 'ok' => true ];

		// 3. Stáhnout homepage a hledat tracking skript
		$response = wp_remote_get( home_url( '/' ), [
			'timeout'    => 10,
			'user-agent' => 'SmartEmailing Connect Test Bot',
			'sslverify'  => false,
		] );

		if ( is_wp_error( $response ) ) {
			wp_send_json_success( [
				'status'  => 'error',
				'message' => 'Nepodařilo se načíst homepage: ' . esc_html( $response->get_error_message() ),
				'checks'  => $checks,
			] );
			return;
		}

		$body = wp_remote_retrieve_body( $response );

		$script_found = str_contains( $body, 'smartemailing.cz/webtracking/script.js' );
		$guid_found   = $guid && str_contains( $body, $guid );

		$checks[] = [
			'label' => 'Tracking skript nalezen ve zdrojovém kódu homepage',
			'ok'    => $script_found,
		];
		$checks[] = [
			'label' => 'GUID odpovídá hodnotě ve skriptu',
			'ok'    => $guid_found,
		];

		if ( $script_found && $guid_found ) {
			wp_send_json_success( [
				'status'  => 'ok',
				'message' => 'Webtracking funguje správně – skript je aktivní na homepage.',
				'checks'  => $checks,
			] );
			return;
		}

		// Skript nenalezen – sestavit nápovědu
		$hints = [];
		if ( ! empty( $cfg['exclude_admins'] ) ) {
			$hints[] = 'Máte zapnuté "Vyloučit administrátory" – test načítá stránku jako nepřihlášený, ale lokální WP cache může vidět jiný výstup.';
		}
		$hints[] = 'Zkontrolujte, zda caching plugin nepodává stránku bez tracking skriptu.';
		if ( ! $script_found ) {
			$hints[] = 'Skript nebyl nalezen. Ověřte, zda je webtracking skutečně aktivní a GUID správně zadán.';
		}

		wp_send_json_success( [
			'status'  => 'warning',
			'message' => 'Webtracking je nastaven, ale skript nebyl nalezen ve zdrojovém kódu homepage.',
			'checks'  => $checks,
			'hints'   => $hints,
		] );
	}

	// -------------------------------------------------------------------------
	// AJAX: Lists & Custom Fields
	// -------------------------------------------------------------------------
	private function ajax_fetch_lists(): void {
		$this->settings->clear_lists_cache();
		$result = $this->api->get_contact_lists();
		if ( $result['success'] ) {
			$this->settings->set_cached_lists( $result['data'] );
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	private function ajax_fetch_customfields(): void {
		$this->settings->clear_customfields_cache();
		$result = $this->api->get_custom_fields( 200 );
		if ( $result['success'] ) {
			$this->settings->set_cached_customfields( $result['data'] );
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	private function ajax_create_list(): void {
		$name = sanitize_text_field( $_POST['name'] ?? '' );
		if ( ! $name ) {
			wp_send_json_error( [ 'message' => 'Zadejte název seznamu.' ] );
		}
		$result = $this->api->create_contact_list( $name );
		if ( $result['success'] ) {
			$this->settings->clear_lists_cache();
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	private function ajax_create_customfield(): void {
		$allowed_types = [ 'text', 'date', 'number', 'checkbox', 'radio' ];

		$name = sanitize_text_field( $_POST['name'] ?? '' );
		$type = sanitize_key( $_POST['type'] ?? 'text' );

		if ( ! $name ) {
			wp_send_json_error( [ 'message' => 'Zadejte název pole.' ] );
		}
		if ( ! in_array( $type, $allowed_types, true ) ) {
			$type = 'text';
		}

		$result = $this->api->create_custom_field( [ 'name' => $name, 'type' => $type ] );

		if ( $result['success'] ) {
			$this->settings->clear_customfields_cache();
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	private function ajax_refresh_cache(): void {
		$this->settings->clear_lists_cache();
		$this->settings->clear_customfields_cache();
		wp_send_json_success( [ 'message' => 'Cache vymazána.' ] );
	}

	// -------------------------------------------------------------------------
	// AJAX: Diagnostika
	// -------------------------------------------------------------------------

	private function ajax_run_diagnostics(): void {
		$issues = $this->diagnostics->run();
		$counts = $this->diagnostics->count_by_level( $issues );
		wp_send_json_success( [ 'issues' => $issues, 'counts' => $counts ] );
	}

	private function ajax_get_form_monitor_stats(): void {
		$period = sanitize_key( $_POST['period'] ?? '30d' );
		$days   = $period === '12m' ? 365 : 30;
		$stats  = $this->logger->get_form_monitor_stats( $days );
		wp_send_json_success( [ 'stats' => $stats, 'period' => $period ] );
	}

	private function ajax_get_form_monthly(): void {
		$form_type = sanitize_key( $_POST['form_type'] ?? '' );
		$form_id   = sanitize_text_field( $_POST['form_id'] ?? '' );
		if ( ! $form_type || ! $form_id ) {
			wp_send_json_error( [ 'message' => 'Chybí parametry.' ] );
		}
		$monthly = $this->logger->get_form_monthly_stats( $form_type, $form_id );
		wp_send_json_success( [ 'monthly' => $monthly, 'form_type' => $form_type, 'form_id' => $form_id ] );
	}

	private function ajax_get_chart_data(): void {
		$period = sanitize_key( $_POST['period'] ?? '30d' );

		if ( $period === '12m' ) {
			$rows    = $this->logger->get_activity_by_month( 12 );
			$labels  = array_column( $rows, 'month' );
		} else {
			$rows    = $this->logger->get_activity_by_day( 30 );
			$labels  = array_map( static fn( $r ) => substr( $r['day'], 5 ), $rows ); // MM-DD
		}

		$days_for_forms = $period === '12m' ? 365 : 30;

		wp_send_json_success( [
			'labels'    => $labels,
			'api_calls' => array_column( $rows, 'api_calls' ),
			'imports'   => array_column( $rows, 'imports' ),
			'errors'    => array_column( $rows, 'errors' ),
			'by_form'   => $this->logger->get_imports_by_form( $days_for_forms ),
		] );
	}

	private function ajax_deactivate_plugin(): void {
		if ( ! current_user_can( 'deactivate_plugins' ) ) {
			wp_send_json_error( [ 'message' => 'Nemáte oprávnění deaktivovat pluginy.' ], 403 );
		}

		$plugin = sanitize_text_field( $_POST['plugin'] ?? '' );

		// Whitelist – povolíme jen konkrétní pluginy
		if ( ! in_array( $plugin, self::DEACTIVATABLE_PLUGINS, true ) ) {
			wp_send_json_error( [ 'message' => 'Deaktivace tohoto pluginu není povolena.' ], 403 );
		}

		if ( ! is_plugin_active( $plugin ) ) {
			wp_send_json_error( [ 'message' => 'Plugin není aktivní.' ] );
		}

		deactivate_plugins( $plugin );

		$this->logger->info( 'Plugin deaktivován přes diagnostiku', [ 'plugin' => $plugin ], 'general' );

		wp_send_json_success( [ 'message' => 'Plugin byl deaktivován.' ] );
	}

	private function ajax_scan_forms(): void {
		$scanner = new SMEC_FormScanner( $this->settings );
		$forms   = $scanner->scan( true );
		wp_send_json_success( [ 'forms' => $forms, 'stats' => $scanner->stats( $forms ) ] );
	}

	private function ajax_ignore_form(): void {
		$type    = sanitize_key( $_POST['form_type'] ?? '' );
		$form_id = sanitize_text_field( $_POST['form_id'] ?? '' );
		$reason  = sanitize_text_field( $_POST['reason'] ?? '' );

		if ( ! $type || ! $form_id ) {
			wp_send_json_error( [ 'message' => 'Chybi typ nebo ID formulare.' ] );
		}

		$this->settings->ignore_form( $type, $form_id, $reason );

		$scanner = new SMEC_FormScanner( $this->settings );
		$scanner->clear_cache();

		wp_send_json_success( [ 'message' => 'Formulár byl přidán do seznamu ignorovaných.' ] );
	}

	// ── Notifikace ───────────────────────────────────────────────────────────

	private function ajax_save_notifications(): void {
		$data = $_POST['data'] ?? [];
		if ( is_string( $data ) ) $data = json_decode( wp_unslash( $data ), true ) ?? [];
		$this->settings->save_notification_settings( (array) $data );
		wp_send_json_success( [ 'message' => 'Nastaveni notifikaci ulozeno.' ] );
	}

	private function ajax_test_notification(): void {
		// Přijmout aktuální data z formuláře (ne jen z DB – aby test fungoval i před uložením)
		$raw = $_POST['cfg'] ?? '';
		if ( $raw ) {
			$form_cfg = json_decode( wp_unslash( $raw ), true ) ?? [];
			$cfg      = $this->settings->save_notification_settings( $form_cfg ) ?: $this->settings->get_notification_settings();
			// Použij čerstvě uložená data
			$cfg = $this->settings->get_notification_settings();
		} else {
			$cfg = $this->settings->get_notification_settings();
		}

		$diag   = $this->notifier->get_mail_diagnostics();
		$result = $this->notifier->send_test( $cfg );
		$result['diagnostics'] = $diag;

		wp_send_json_success( $result );
	}

	private function ajax_reset_notifier_state(): void {
		delete_option( 'smec_notifier_state' );
		wp_send_json_success( [ 'message' => 'Stav notifikatoru byl vynulovan.' ] );
	}

	private function ajax_unignore_form(): void {
		$type    = sanitize_key( $_POST['form_type'] ?? '' );
		$form_id = sanitize_text_field( $_POST['form_id'] ?? '' );

		if ( ! $type || ! $form_id ) {
			wp_send_json_error( [ 'message' => 'Chybi typ nebo ID formulare.' ] );
		}

		$this->settings->unignore_form( $type, $form_id );

		$scanner = new SMEC_FormScanner( $this->settings );
		$scanner->clear_cache();

		wp_send_json_success( [ 'message' => 'Formulár byl odstraněn ze seznamu ignorovaných.' ] );
	}

	private function ajax_dismiss_notice(): void {
		$notice_id = sanitize_key( $_POST['notice_id'] ?? '' );
		if ( $notice_id ) {
			$user_id   = get_current_user_id();
			$dismissed = get_user_meta( $user_id, 'smec_dismissed_notices', true ) ?: [];
			$dismissed[ $notice_id ] = time();
			update_user_meta( $user_id, 'smec_dismissed_notices', $dismissed );
		}
		wp_send_json_success();
	}

	// -------------------------------------------------------------------------
	// AJAX: Form Mappings
	// -------------------------------------------------------------------------
	private function ajax_get_mappings(): void {
		wp_send_json_success( [ 'mappings' => $this->settings->get_form_mappings() ] );
	}

	private function ajax_save_mapping(): void {
		$data = $_POST['mapping'] ?? '';
		if ( is_string( $data ) ) $data = json_decode( wp_unslash( $data ), true ) ?? [];
		if ( empty( $data ) ) {
			wp_send_json_error( [ 'message' => 'Chybí data mapování.' ] );
		}

		$clean = $this->sanitize_mapping( (array) $data );
		$id    = $this->settings->upsert_mapping( $clean );
		wp_send_json_success( [ 'message' => 'Mapování uloženo.', 'id' => $id ] );
	}

	private function ajax_delete_mapping(): void {
		$id = sanitize_key( $_POST['id'] ?? '' );
		if ( ! $id ) wp_send_json_error( [ 'message' => 'Chybí ID.' ] );
		$this->settings->delete_mapping( $id );
		wp_send_json_success( [ 'message' => 'Mapování smazáno.' ] );
	}

	private function ajax_duplicate_mapping(): void {
		$id      = sanitize_key( $_POST['id'] ?? '' );
		$mapping = $this->settings->get_mapping_by_id( $id );
		if ( ! $mapping ) wp_send_json_error( [ 'message' => 'Mapování nenalezeno.' ] );

		$mapping['id']         = '';
		$mapping['name']       = ( $mapping['name'] ?? 'Kopie' ) . ' (kopie)';
		$mapping['enabled']    = 0;
		$mapping['created_at'] = '';
		$new_id                = $this->settings->upsert_mapping( $mapping );
		wp_send_json_success( [ 'message' => 'Mapování duplikováno.', 'id' => $new_id ] );
	}

	private function ajax_toggle_mapping(): void {
		$id      = sanitize_key( $_POST['id'] ?? '' );
		$mapping = $this->settings->get_mapping_by_id( $id );
		if ( ! $mapping ) wp_send_json_error( [ 'message' => 'Mapování nenalezeno.' ] );

		$mapping['enabled'] = empty( $mapping['enabled'] ) ? 1 : 0;
		$this->settings->upsert_mapping( $mapping );
		wp_send_json_success( [ 'enabled' => $mapping['enabled'] ] );
	}

	private function ajax_test_contact(): void {
		$mapping_id = sanitize_key( $_POST['mapping_id'] ?? '' );
		$email      = sanitize_email( $_POST['email'] ?? '' );
		$result     = $this->form_manager->send_test_contact( $mapping_id, $email );
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	// -------------------------------------------------------------------------
	// AJAX: Logs
	// -------------------------------------------------------------------------
	private function ajax_get_logs(): void {
		$logs = $this->logger->get_logs( [
			'type'   => sanitize_key( $_POST['type']   ?? '' ),
			'level'  => sanitize_key( $_POST['level']  ?? '' ),
			'limit'  => (int) ( $_POST['limit']  ?? 50 ),
			'offset' => (int) ( $_POST['offset'] ?? 0 ),
		] );
		$total = $this->logger->count_logs( [
			'type'  => sanitize_key( $_POST['type']  ?? '' ),
			'level' => sanitize_key( $_POST['level'] ?? '' ),
		] );
		wp_send_json_success( [ 'logs' => $logs, 'total' => $total ] );
	}

	private function ajax_clear_logs(): void {
		$days_raw = $_POST['older_than_days'] ?? '';
		$days     = $days_raw !== '' ? max( 1, min( 365, (int) $days_raw ) ) : null;
		$count    = $this->logger->clear_logs( $days );
		wp_send_json_success( [ 'message' => "Smazáno {$count} záznamů." ] );
	}

	// -------------------------------------------------------------------------
	// AJAX: Queue
	// -------------------------------------------------------------------------
	private function ajax_process_queue_now(): void {
		$this->queue->process();
		$pending = $this->queue->count_items( 'pending' );
		wp_send_json_success( [ 'message' => 'Fronta zpracována.', 'pending' => $pending ] );
	}

	private function ajax_clear_queue(): void {
		$status = sanitize_key( $_POST['status'] ?? 'done' );
		$count  = $this->queue->clear_items( $status );
		wp_send_json_success( [ 'message' => "Smazáno {$count} položek." ] );
	}

	private function ajax_retry_failed_queue(): void {
		$count = $this->queue->retry_failed();
		wp_send_json_success( [ 'message' => "Označeno k opakování: {$count} položek." ] );
	}

	// -------------------------------------------------------------------------
	// AJAX: Settings
	// -------------------------------------------------------------------------
	private function ajax_save_general_settings(): void {
		$data = $_POST['data'] ?? [];
		if ( is_string( $data ) ) $data = json_decode( wp_unslash( $data ), true ) ?? [];
		$this->settings->save_general( (array) $data );
		wp_send_json_success( [ 'message' => 'Nastavení uloženo.' ] );
	}

	private function ajax_save_reading_time(): void {
		$data = $_POST['data'] ?? [];
		if ( is_string( $data ) ) $data = json_decode( wp_unslash( $data ), true ) ?? [];
		$this->settings->save_reading_time( (array) $data );
		wp_send_json_success( [ 'message' => 'Nastavení doby čtení uloženo.' ] );
	}

	// ── Reading Time Presety ──────────────────────────────────────────────────

	private function ajax_get_rt_presets(): void {
		wp_send_json_success( [ 'presets' => $this->settings->get_rt_presets() ] );
	}

	private function ajax_save_rt_preset(): void {
		$data = $_POST['preset'] ?? '';
		if ( is_string( $data ) ) $data = json_decode( wp_unslash( $data ), true ) ?? [];
		if ( empty( $data ) ) wp_send_json_error( [ 'message' => 'Chybí data presetu.' ] );

		$clean = $this->settings->sanitize_rt_preset( (array) $data );
		$id    = $this->settings->upsert_rt_preset( $clean );
		wp_send_json_success( [ 'message' => 'Preset uložen.', 'id' => $id ] );
	}

	private function ajax_delete_rt_preset(): void {
		$id = sanitize_key( $_POST['id'] ?? '' );
		if ( ! $id ) wp_send_json_error( [ 'message' => 'Chybí ID.' ] );
		// Nelze smazat jediný preset
		if ( count( $this->settings->get_rt_presets() ) <= 1 ) {
			wp_send_json_error( [ 'message' => 'Nelze smazat poslední preset.' ] );
		}
		$this->settings->delete_rt_preset( $id );
		wp_send_json_success( [ 'message' => 'Preset smazán.' ] );
	}

	private function ajax_duplicate_rt_preset(): void {
		$id     = sanitize_key( $_POST['id'] ?? '' );
		$preset = null;
		foreach ( $this->settings->get_rt_presets() as $p ) {
			if ( ( $p['id'] ?? '' ) === $id ) { $preset = $p; break; }
		}
		if ( ! $preset ) wp_send_json_error( [ 'message' => 'Preset nenalezen.' ] );

		$preset['id']   = '';
		$preset['name'] = ( $preset['name'] ?? 'Kopie' ) . ' (kopie)';
		$preset['slug'] = $preset['slug'] . '-copy';
		$new_id         = $this->settings->upsert_rt_preset( $preset );
		wp_send_json_success( [ 'message' => 'Preset duplikován.', 'id' => $new_id ] );
	}

	private function ajax_save_woocommerce(): void {
		$data = $_POST['data'] ?? [];
		if ( is_string( $data ) ) $data = json_decode( wp_unslash( $data ), true ) ?? [];
		$this->settings->save_woocommerce( (array) $data );
		wp_send_json_success( [ 'message' => 'WooCommerce nastavení uloženo.' ] );
	}

	// -------------------------------------------------------------------------
	// AJAX: Export / Import
	// -------------------------------------------------------------------------
	private function ajax_export_settings(): void {
		$include_api_key = ! empty( $_POST['include_api_key'] );
		$api             = $this->settings->get_api();
		if ( ! $include_api_key ) unset( $api['api_key'] );

		$export = [
			'plugin_version'  => SMEC_VERSION,
			'exported_at'     => current_time( 'c' ),
			'api'             => $api,
			'webtracking'     => $this->settings->get_webtracking(),
			'general'         => $this->settings->get_general(),
			'form_mappings'   => $this->settings->get_form_mappings(),
			'reading_time'    => $this->settings->get_reading_time(),
			'woocommerce'     => $this->settings->get_woocommerce(),
		];
		// Always remove secret fields from webtracking custom_code
		wp_send_json_success( [ 'json' => wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ] );
	}

	private function ajax_import_settings(): void {
		$json = wp_unslash( $_POST['json'] ?? '' );

		// Velikostní limit – zamezit resource exhaustion
		if ( strlen( $json ) > 500_000 ) {
			wp_send_json_error( [ 'message' => 'Import je prilis velky.' ] );
		}

		$data = json_decode( $json, true );

		if ( ! is_array( $data ) || empty( $data['plugin_version'] ) ) {
			wp_send_json_error( [ 'message' => 'Neplatny format importu.' ] );
		}

		// Validace verze – pouze string, ne pole/objekt
		if ( ! is_string( $data['plugin_version'] ) ) {
			wp_send_json_error( [ 'message' => 'Neplatna verze pluginu.' ] );
		}

		// Každá sekce projde přes standardní sanitizery – žádná sekce nemůže obejít validaci
		if ( ! empty( $data['webtracking'] ) && is_array( $data['webtracking'] ) ) {
			$this->settings->save_webtracking( $data['webtracking'] );
		}
		if ( ! empty( $data['general'] ) && is_array( $data['general'] ) ) {
			$this->settings->save_general( $data['general'] );
		}
		if ( ! empty( $data['form_mappings'] ) && is_array( $data['form_mappings'] ) ) {
			// Každé mapování projde sanitizací
			$clean_mappings = [];
			foreach ( $data['form_mappings'] as $m ) {
				if ( is_array( $m ) ) {
					$clean_mappings[] = $this->sanitize_mapping( $m );
				}
			}
			$this->settings->save_form_mappings( $clean_mappings );
		}
		if ( ! empty( $data['reading_time'] ) && is_array( $data['reading_time'] ) ) {
			$this->settings->save_reading_time( $data['reading_time'] );
		}
		if ( ! empty( $data['woocommerce'] ) && is_array( $data['woocommerce'] ) ) {
			$this->settings->save_woocommerce( $data['woocommerce'] );
		}
		// API: import nikdy nepřepisuje klíč pokud nebyl explicitně zahrnut v exportu
		if ( ! empty( $data['api'] ) && is_array( $data['api'] ) ) {
			$this->settings->save_api( $data['api'] );
		}
		if ( ! empty( $data['notification_settings'] ) && is_array( $data['notification_settings'] ) ) {
			$this->settings->save_notification_settings( $data['notification_settings'] );
		}

		wp_send_json_success( [ 'message' => 'Nastaveni importovano.' ] );
	}

	// -------------------------------------------------------------------------
	// Mapping sanitization
	// -------------------------------------------------------------------------
	private function sanitize_mapping( array $data ): array {
		$allowed_form_types = [ 'elementor', 'cf7', 'fluent', 'wpforms', 'gravity', 'registration', 'woocommerce', 'custom' ];

		return [
			'id'                   => sanitize_key( $data['id'] ?? '' ),
			'name'                 => sanitize_text_field( $data['name'] ?? 'Nové mapování' ),
			'form_type'            => in_array( $data['form_type'] ?? '', $allowed_form_types, true ) ? $data['form_type'] : 'elementor',
			'form_id'              => sanitize_text_field( $data['form_id'] ?? '' ),
			'enabled'              => ! empty( $data['enabled'] ) ? 1 : 0,
			'list_id'              => (int) ( $data['list_id'] ?? 0 ),
			'contact_status'       => in_array( $data['contact_status'] ?? '', [ 'confirmed', 'unconfirmed' ], true ) ? $data['contact_status'] : 'confirmed',
			'consent_field'        => sanitize_key( $data['consent_field'] ?? '' ),
			'system_fields'        => $this->settings->sanitize_field_mapping( $data['system_fields'] ?? [] ),
			'custom_field_mapping' => $this->settings->sanitize_custom_field_mapping( $data['custom_field_mapping'] ?? [] ),
			'tags'                 => $this->settings->sanitize_tags( $data['tags'] ?? [] ),
			'conditions'           => $this->sanitize_conditions( $data['conditions'] ?? [] ),
			'created_at'           => sanitize_text_field( $data['created_at'] ?? '' ),
		];
	}

	private function sanitize_conditions( array $conditions ): array {
		$clean = [];
		$allowed_ops = [ '==', '!=', 'contains', 'not_empty', 'empty' ];
		foreach ( $conditions as $c ) {
			$clean[] = [
				'field'    => sanitize_key( $c['field']    ?? '' ),
				'operator' => in_array( $c['operator'] ?? '', $allowed_ops, true ) ? $c['operator'] : '==',
				'value'    => sanitize_text_field( $c['value'] ?? '' ),
			];
		}
		return $clean;
	}

	// -------------------------------------------------------------------------
	// AJAX: Health check
	// -------------------------------------------------------------------------

	private function ajax_health_check_now(): void {
		do_action( 'smec_health_check' );
		$result = get_option( 'smec_health_last_check', [] );
		wp_send_json_success( [
			'message' => 'Kontrola dokončena.',
			'result'  => $result,
		] );
	}

	private function ajax_get_health_status(): void {
		$result    = get_option( 'smec_health_last_check', null );
		$next      = (int) wp_next_scheduled( 'smec_health_check' );
		wp_send_json_success( [
			'result'        => is_array( $result ) ? $result : null,
			'next_scheduled'=> $next,
		] );
	}

	// -------------------------------------------------------------------------
	// Log export – file download (not a standard JSON AJAX response)
	// -------------------------------------------------------------------------

	public function handle_export_logs(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'Přístup odepřen.', 403 );
		}

		$nonce = sanitize_text_field( $_GET['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_die( 'Neplatný bezpečnostní token.', 403 );
		}

		$cfg = $this->settings->get_api();

		$logs = $this->logger->get_logs( [ 'limit' => 1000, 'offset' => 0 ] );
		$logs_parsed = array_map( static function ( array $log ): array {
			$ctx = null;
			if ( ! empty( $log['context'] ) ) {
				$ctx = json_decode( $log['context'], true );
			}
			return [
				'id'         => (int) $log['id'],
				'created_at' => $log['created_at'],
				'type'       => $log['type'],
				'level'      => $log['level'],
				'message'    => $log['message'],
				'context'    => $ctx,
			];
		}, $logs );

		global $wp_version;

		$export = [
			'_meta' => [
				'generated_at'   => current_time( 'c' ),
				'plugin_version' => SMEC_VERSION,
				'wp_version'     => $wp_version,
				'php_version'    => PHP_VERSION,
				'site_url'       => get_site_url(),
				'api_username'   => $cfg['username'] ?? '(not set)',
				'api_base_url'   => $cfg['base_url'] ?? '',
			],
			'health_check' => get_option( 'smec_health_last_check', null ),
			'diagnostics'  => $this->diagnostics->run(),
			'logs'         => $logs_parsed,
		];

		$filename = 'smec-diagnostic-' . gmdate( 'Y-m-d-His' ) . '.json';

		// Prevent any output buffering interference
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}
}
