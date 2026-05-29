<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Spouští diagnostické kontroly a vrací seznam nalezených problémů.
 *
 * Každý problém je pole:
 *  id        – unikátní klíč (pro dismiss)
 *  level     – 'critical' | 'warning' | 'info'
 *  title     – krátký název problému
 *  message   – popis co se děje a proč je to problém
 *  solution  – konkrétní doporučený postup jak opravit
 *  link      – URL pro odkaz (volitelné)
 *  link_text – text odkazu (volitelné)
 *  action    – pole pro speciální akci (volitelné): type, plugin, label
 */
class SMEC_Diagnostics {

	private SMEC_Settings $settings;
	private SMEC_Logger   $logger;
	private SMEC_Queue    $queue;

	public function __construct( SMEC_Settings $settings, SMEC_Logger $logger, SMEC_Queue $queue ) {
		$this->settings = $settings;
		$this->logger   = $logger;
		$this->queue    = $queue;
	}

	// -------------------------------------------------------------------------
	// Spustit všechny kontroly
	// -------------------------------------------------------------------------
	public function run(): array {
		$issues = [];

		$issues = array_merge( $issues, $this->check_db_tables() );
		$issues = array_merge( $issues, $this->check_api() );
		$issues = array_merge( $issues, $this->check_webtracking() );
		$issues = array_merge( $issues, $this->check_plugin_conflicts() );
		$issues = array_merge( $issues, $this->check_form_mappings() );
		$issues = array_merge( $issues, $this->check_queue() );
		$issues = array_merge( $issues, $this->check_recent_errors() );

		// Seřadit: critical → warning → info
		usort( $issues, static function ( $a, $b ) {
			$order = [ 'critical' => 0, 'warning' => 1, 'info' => 2 ];
			return ( $order[ $a['level'] ] ?? 9 ) <=> ( $order[ $b['level'] ] ?? 9 );
		} );

		return $issues;
	}

	// -------------------------------------------------------------------------
	// Počty podle závažnosti
	// -------------------------------------------------------------------------
	public function count_by_level( array $issues ): array {
		$counts = [ 'critical' => 0, 'warning' => 0, 'info' => 0 ];
		foreach ( $issues as $issue ) {
			$counts[ $issue['level'] ] = ( $counts[ $issue['level'] ] ?? 0 ) + 1;
		}
		return $counts;
	}

	// -------------------------------------------------------------------------
	// DB tabulky
	// -------------------------------------------------------------------------
	private function check_db_tables(): array {
		global $wpdb;
		$issues = [];

		$logs_exists  = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}smec_logs'" );
		$queue_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}smec_queue'" );

		if ( ! $logs_exists || ! $queue_exists ) {
			$issues[] = [
				'id'       => 'missing_db_tables',
				'level'    => 'critical',
				'title'    => 'Chybí databázové tabulky',
				'message'  => 'Tabulky ' . $wpdb->prefix . 'smec_logs a/nebo ' . $wpdb->prefix . 'smec_queue neexistují. Plugin nebude správně fungovat – logy ani fronta opakování nefungují.',
				'solution' => 'Deaktivujte a znovu aktivujte plugin SmartEmailing Connect. Tím se tabulky vytvoří.',
				'link'     => admin_url( 'plugins.php' ),
				'link_text'=> 'Přejít na pluginy',
				'action'   => null,
			];
		}

		return $issues;
	}

	// -------------------------------------------------------------------------
	// API
	// -------------------------------------------------------------------------
	private function check_api(): array {
		$issues = [];
		$cfg    = $this->settings->get_api();

		if ( empty( $cfg['username'] ) || empty( $cfg['api_key'] ) ) {
			$issues[] = [
				'id'       => 'api_not_configured',
				'level'    => 'critical',
				'title'    => 'API připojení není nakonfigurováno',
				'message'  => 'Nejsou zadány přihlašovací údaje SmartEmailing (username nebo API klíč). Bez nich nelze odesílat kontakty ani načítat seznamy.',
				'solution' => 'Přejděte do Nastavení → API připojení, zadejte přihlašovací e-mail a API klíč z vašeho SmartEmailing účtu (Nastavení → Přístupy a API → API).',
				'link'     => admin_url( 'admin.php?page=smec-api' ),
				'link_text'=> 'Nastavit API připojení',
				'action'   => null,
			];
			return $issues; // Další API kontroly nemají smysl bez credentials
		}

		if ( empty( $cfg['enabled'] ) ) {
			// API je nakonfigurováno ale vypnuto – jen info pokud jsou aktivní mapování
			$mappings = array_filter( $this->settings->get_form_mappings(), fn( $m ) => ! empty( $m['enabled'] ) );
			if ( ! empty( $mappings ) ) {
				$issues[] = [
					'id'       => 'api_disabled_with_mappings',
					'level'    => 'warning',
					'title'    => 'API je vypnuto, ale existují aktivní propojení formulářů',
					'message'  => 'API připojení je deaktivováno, ale máte ' . count( $mappings ) . ' aktivních propojení formulářů. Kontakty se nebudou odesílat do SmartEmailingu.',
					'solution' => 'Přejděte do Nastavení → API připojení a zapněte „Aktivovat API".',
					'link'     => admin_url( 'admin.php?page=smec-api' ),
					'link_text'=> 'Zapnout API',
					'action'   => null,
				];
			}
		}

		// Zkontrolovat poslední API chybu (poslední hodinu)
		$last_api_error = $this->logger->get_last_error( 'api' );
		if ( $last_api_error ) {
			$error_time = strtotime( $last_api_error['created_at'] );
			if ( $error_time && ( time() - $error_time ) < 3600 ) {
				$issues[] = [
					'id'       => 'recent_api_error',
					'level'    => 'warning',
					'title'    => 'Nedávná chyba API připojení',
					'message'  => 'V poslední hodině selhalo volání SmartEmailing API: ' . esc_html( mb_substr( $last_api_error['message'], 0, 200 ) ),
					'solution' => 'Zkontrolujte platnost API klíče a dostupnost SmartEmailing serveru. Přejděte do sekce Logy pro více informací.',
					'link'     => admin_url( 'admin.php?page=smec-api' ),
					'link_text'=> 'Otestovat API',
					'action'   => null,
				];
			}
		}

		return $issues;
	}

	// -------------------------------------------------------------------------
	// Webtracking
	// -------------------------------------------------------------------------
	private function check_webtracking(): array {
		$issues = [];
		$cfg    = $this->settings->get_webtracking();

		if ( ! empty( $cfg['enabled'] ) && empty( $cfg['guid'] ) ) {
			$issues[] = [
				'id'       => 'webtracking_no_guid',
				'level'    => 'critical',
				'title'    => 'Webtracking je zapnutý, ale chybí GUID',
				'message'  => 'Webtracking je aktivován, ale není zadán GUID identifikátor. Tracking skript se na webu nevloží a sledování návštěv nebude fungovat.',
				'solution' => 'Přejděte do Webtracking, zadejte GUID. Najdete ho v SmartEmailingu pod Nastavení → Zdroje dat → Webtracking.',
				'link'     => admin_url( 'admin.php?page=smec-webtracking' ),
				'link_text'=> 'Nastavit webtracking',
				'action'   => null,
			];
		}

		return $issues;
	}

	// -------------------------------------------------------------------------
	// Kolize s jinými pluginy
	// -------------------------------------------------------------------------
	private function check_plugin_conflicts(): array {
		$issues = [];

		// Oficiální SmartEmailing plugin
		if ( is_plugin_active( 'smartemailing/smartemailing.php' ) ) {
			$wt_cfg     = $this->settings->get_webtracking();
			$both_track = ! empty( $wt_cfg['enabled'] ) && ! empty( $wt_cfg['guid'] );

			$level   = $both_track ? 'critical' : 'warning';
			$message = 'Aktivní officiální plugin SmartEmailing (wordpress.org/plugins/smartemailing) může způsobovat konflikty:';
			if ( $both_track ) {
				$message .= ' Oba pluginy mají zapnutý webtracking – na webu se vloží tracking skript dvakrát, což zkresí statistiky v SmartEmailingu.';
			} else {
				$message .= ' Hrozí duplicitní odesílání kontaktů nebo kolize option klíčů.';
			}

			$issues[] = [
				'id'       => 'conflict_official_smartemailing',
				'level'    => $level,
				'title'    => 'Kolize: aktivní officiální plugin SmartEmailing',
				'message'  => $message,
				'solution' => 'Pokud SmartEmailing Connect plně nahrazuje officiální plugin, deaktivujte officiální plugin. Vaše nastavení v SmartEmailing Connect zůstane nedotčeno.',
				'link'     => admin_url( 'plugins.php' ),
				'link_text'=> 'Správa pluginů',
				'action'   => [
					'type'   => 'deactivate_plugin',
					'plugin' => 'smartemailing/smartemailing.php',
					'label'  => 'Deaktivovat SmartEmailing plugin',
				],
			];
		}

		// Duplicitní SMEC instance (nemělo by nastat, ale pro jistotu)
		// Další konflikty lze přidat v budoucnu

		return $issues;
	}

	// -------------------------------------------------------------------------
	// Propojení formulářů
	// -------------------------------------------------------------------------
	private function check_form_mappings(): array {
		$issues    = [];
		$mappings  = $this->settings->get_form_mappings();
		$active    = array_filter( $mappings, fn( $m ) => ! empty( $m['enabled'] ) );

		if ( empty( $mappings ) ) {
			$issues[] = [
				'id'       => 'no_form_mappings',
				'level'    => 'info',
				'title'    => 'Žádná propojení formulářů',
				'message'  => 'Zatím nejsou nastavena žádná propojení formulářů se SmartEmailingem. Kontakty z formulářů se do SmartEmailingu neodesílají.',
				'solution' => 'Přejděte do sekce Formuláře a přidejte první propojení.',
				'link'     => admin_url( 'admin.php?page=smec-forms' ),
				'link_text'=> 'Přidat propojení',
				'action'   => null,
			];
			return $issues;
		}

		// Zkontrolovat mapování s neaktivním formulářovým pluginem
		$plugin_checks = [
			'elementor'   => [ 'check' => fn() => defined( 'ELEMENTOR_PRO_VERSION' ), 'name' => 'Elementor Pro' ],
			'cf7'         => [ 'check' => fn() => defined( 'WPCF7_VERSION' ),          'name' => 'Contact Form 7' ],
			'fluent'      => [ 'check' => fn() => defined( 'FLUENTFORM_VERSION' ),     'name' => 'Fluent Forms' ],
			'wpforms'     => [ 'check' => fn() => defined( 'WPFORMS_VERSION' ) || function_exists( 'wpforms' ), 'name' => 'WPForms' ],
		];

		$inactive_found = [];
		foreach ( $active as $m ) {
			$type = $m['form_type'] ?? '';
			if ( isset( $plugin_checks[ $type ] ) && ! ( $plugin_checks[ $type ]['check'] )() ) {
				$inactive_found[ $type ] = $plugin_checks[ $type ]['name'];
			}
		}

		foreach ( $inactive_found as $type => $plugin_name ) {
			$count   = count( array_filter( $active, fn( $m ) => ( $m['form_type'] ?? '' ) === $type ) );
			$issues[] = [
				'id'       => 'inactive_form_plugin_' . $type,
				'level'    => 'warning',
				'title'    => 'Propojeni formularu: plugin ' . $plugin_name . ' neni aktivni',
				'message'  => 'Mate ' . $count . ' aktivni propojeni pro typ "' . $plugin_name . '", ale tento plugin neni na webu nainstalovan nebo aktivovan. Kontakty z techto formularu se neodesilaji.',
				'solution' => 'Aktivujte plugin ' . $plugin_name . ', nebo zmente typ formulare v propojeni na jiny aktivni plugin.',
				'link'     => admin_url( 'admin.php?page=smec-forms' ),
				'link_text'=> 'Spravovat propojení',
				'action'   => null,
			];
		}

		// Propojení bez nastaveného cílového seznamu
		$no_list = array_filter( $active, fn( $m ) => empty( $m['list_id'] ) );
		if ( ! empty( $no_list ) ) {
			$issues[] = [
				'id'       => 'mappings_missing_list',
				'level'    => 'warning',
				'title'    => 'Propojení formulářů: chybí cílový seznam (' . count( $no_list ) . '×)',
				'message'  => count( $no_list ) . ' aktivní propojení nemají nastaven cílový seznam SmartEmailing. Kontakty se odešlou bez zařazení do seznamu.',
				'solution' => 'Přejděte do propojení formulářů a nastavte cílový seznam kontaktů.',
				'link'     => admin_url( 'admin.php?page=smec-forms' ),
				'link_text'=> 'Opravit propojení',
				'action'   => null,
			];
		}

		return $issues;
	}

	// -------------------------------------------------------------------------
	// Fronta opakování
	// -------------------------------------------------------------------------
	private function check_queue(): array {
		$issues = [];
		$failed = $this->queue->count_items( 'failed' );

		if ( $failed > 0 ) {
			$issues[] = [
				'id'       => 'queue_failed_items',
				'level'    => 'warning',
				'title'    => 'Fronta: ' . $failed . ' nepodarených odeslání',
				'message'  => $failed . ' kontaktů se nepodařilo odeslat do SmartEmailingu ani po opakovaných pokusech. Kontakty čekají ve frontě se stavem "failed".',
				'solution' => 'Přejděte do sekce Logy → Fronta, zkontrolujte chyby a klikněte na „Opakovat chybné" pro nový pokus o odeslání.',
				'link'     => admin_url( 'admin.php?page=smec-logs' ),
				'link_text'=> 'Zobrazit frontu',
				'action'   => [
					'type'  => 'retry_queue',
					'label' => 'Opakovat chybné odeslání',
				],
			];
		}

		$pending = $this->queue->count_items( 'pending' );
		if ( $pending > 50 ) {
			$issues[] = [
				'id'       => 'queue_large_backlog',
				'level'    => 'warning',
				'title'    => "Fronta: {$pending} čekajících odeslání",
				'message'  => "Ve frontě čeká neobvykle velký počet kontaktů ({$pending}). Může to znamenat problém s WP-Cron nebo API.",
				'solution' => 'Zkontrolujte dostupnost SmartEmailing API a zda funguje WP-Cron na vašem webu. Frontu lze zpracovat ručně v sekci Logy.',
				'link'     => admin_url( 'admin.php?page=smec-logs' ),
				'link_text'=> 'Zpracovat frontu',
				'action'   => [
					'type'  => 'process_queue',
					'label' => 'Zpracovat frontu nyní',
				],
			];
		}

		return $issues;
	}

	// -------------------------------------------------------------------------
	// Nedávné chyby
	// -------------------------------------------------------------------------
	private function check_recent_errors(): array {
		$issues = [];

		$error_types = [
			'import'      => 'Import kontaktů',
			'woocommerce' => 'WooCommerce integrace',
			'forms'       => 'Propojení formulářů',
		];

		foreach ( $error_types as $type => $label ) {
			$last = $this->logger->get_last_error( $type );
			if ( ! $last ) continue;

			$error_time = strtotime( $last['created_at'] );
			if ( ! $error_time || ( time() - $error_time ) > 86400 ) continue; // starší než 24h ignorovat

			$issues[] = [
				'id'       => 'recent_error_' . $type,
				'level'    => 'warning',
				'title'    => "Nedávná chyba: {$label}",
				'message'  => 'V posledních 24 hodinách nastala chyba v modulu ' . $label . ': ' . esc_html( mb_substr( $last['message'], 0, 300 ) ),
				'solution' => 'Přejděte do sekce Logy pro zobrazení detailů. Zapněte Debug režim v Nastavení pro podrobnější informace.',
				'link'     => admin_url( 'admin.php?page=smec-logs' ),
				'link_text'=> 'Zobrazit logy',
				'action'   => null,
			];
		}

		return $issues;
	}
}
