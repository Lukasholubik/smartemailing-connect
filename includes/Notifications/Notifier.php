<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMEC_Notifier {

	private SMEC_Settings $settings;
	private SMEC_Logger   $logger;
	private SMEC_Queue    $queue;

	// Typy událostí
	public const EVENT_API_STREAK      = 'api_error_streak';
	public const EVENT_IMPORT_FAILURES = 'import_failures';
	public const EVENT_CRON_STOPPED    = 'cron_stopped';
	public const EVENT_IMPORT_SILENCE  = 'import_silence';
	public const EVENT_WEBTRACK_SILENCE = 'webtrack_silence';

	private const STATE_OPTION = 'smec_notifier_state';

	public function __construct( SMEC_Settings $settings, SMEC_Logger $logger, SMEC_Queue $queue ) {
		$this->settings = $settings;
		$this->logger   = $logger;
		$this->queue    = $queue;
	}

	public function register(): void {
		add_action( 'smec_check_notifications', [ $this, 'run_checks' ] );

		if ( ! wp_next_scheduled( 'smec_check_notifications' ) ) {
			wp_schedule_event( time(), 'hourly', 'smec_check_notifications' );
		}
	}

	// -------------------------------------------------------------------------
	// Hlavní vstupní bod – spouštěno cronem každou hodinu
	// -------------------------------------------------------------------------
	public function run_checks(): void {
		$cfg = $this->settings->get_notification_settings();
		if ( empty( $cfg['enabled'] ) ) return;

		$this->check_api_error_streak( $cfg );
		$this->check_import_failures( $cfg );
		$this->check_cron_stopped( $cfg );
		$this->check_import_silence( $cfg );
		$this->check_webtracking_silence( $cfg );

		// Aktualizovat timestamp posledního běhu
		$this->update_state( [ 'last_check' => time() ] );
	}

	// -------------------------------------------------------------------------
	// Check: Sériové API chyby
	// -------------------------------------------------------------------------
	private function check_api_error_streak( array $cfg ): void {
		if ( empty( $cfg['events'][ self::EVENT_API_STREAK ] ) ) return;

		$threshold = (int) ( $cfg['thresholds'][ self::EVENT_API_STREAK ] ?? 3 );
		$state     = $this->get_state();
		$streak    = (int) ( $state['api_error_count'] ?? 0 );

		if ( $streak < $threshold ) return;
		if ( $this->is_on_cooldown( self::EVENT_API_STREAK, $cfg ) ) return;

		// Jen pokud API bylo dříve funkční
		if ( ! $this->api_ever_worked() ) return;

		$last_error = $this->logger->get_last_error( 'api' );
		$error_msg  = $last_error ? $last_error['message'] : 'Neznama chyba.';

		$this->dispatch( self::EVENT_API_STREAK, $cfg,
			'VYPADEK: API pripojeni selhalo ' . $streak . 'x za sebou',
			'API pripojeni SmartEmailing selhalo ' . $streak . 'x za sebou.' . "\n\n"
			. 'Posledni chyba: ' . $error_msg . "\n\n"
			. 'Doporucene reseni: Zkontrolujte platnost API klice v SmartEmailingu (Nastaveni -> Pristupy a API -> API) a dostupnost serveru app.smartemailing.cz.'
		);
	}

	// -------------------------------------------------------------------------
	// Check: Selhání importů ve frontě
	// -------------------------------------------------------------------------
	private function check_import_failures( array $cfg ): void {
		if ( empty( $cfg['events'][ self::EVENT_IMPORT_FAILURES ] ) ) return;

		$threshold = (int) ( $cfg['thresholds'][ self::EVENT_IMPORT_FAILURES ] ?? 5 );
		$failed    = $this->queue->count_items( 'failed' );

		if ( $failed < $threshold ) return;
		if ( $this->is_on_cooldown( self::EVENT_IMPORT_FAILURES, $cfg ) ) return;

		$this->dispatch( self::EVENT_IMPORT_FAILURES, $cfg,
			'VYPADEK: ' . $failed . ' kontaktu se nepodarilo odeslat',
			'Ve fronte pro opakovani je ' . $failed . ' kontaktu, ktere se nepodarilo odeslat do SmartEmailingu ani po vsech pokusech.' . "\n\n"
			. 'Doporucene reseni: Prejdete do sekce Logy -> Fronta, zkontrolujte chyby a kliknete na Opakovat chybne.'
		);
	}

	// -------------------------------------------------------------------------
	// Check: WP-Cron se nespustil
	// -------------------------------------------------------------------------
	private function check_cron_stopped( array $cfg ): void {
		if ( empty( $cfg['events'][ self::EVENT_CRON_STOPPED ] ) ) return;

		$threshold_hours = (int) ( $cfg['thresholds'][ self::EVENT_CRON_STOPPED ] ?? 6 );
		$state           = $this->get_state();
		$last_cron       = (int) ( $state['cron_last_run'] ?? 0 );

		// Fronta musí mít pending položky – jinak to není problém
		if ( $this->queue->count_items( 'pending' ) === 0 ) return;

		if ( $last_cron === 0 ) {
			// Cron ještě nikdy neběžel – nezpozornil instalaci
			return;
		}

		$hours_since = ( time() - $last_cron ) / 3600;
		if ( $hours_since < $threshold_hours ) return;
		if ( $this->is_on_cooldown( self::EVENT_CRON_STOPPED, $cfg ) ) return;

		$pending = $this->queue->count_items( 'pending' );

		$this->dispatch( self::EVENT_CRON_STOPPED, $cfg,
			'VYPADEK: WP-Cron se nespustil uz ' . round( $hours_since ) . ' hodin',
			'WP-Cron (planovac uloh) se na webu nespustil uz ' . round( $hours_since ) . ' hodin. Ve fronte ceka ' . $pending . ' kontaktu na odeslani do SmartEmailingu.' . "\n\n"
			. 'Doporucene reseni: Zkontrolujte nastaveni hostingu – nekteri hostingovi poskytovatele blokovji HTTP callback pro WP-Cron. Pripadne nastavte externi cron (napr. z cpanel nebo server cronem volejte wp-cron.php).'
		);
	}

	// -------------------------------------------------------------------------
	// Check: Ticho – žádné importy X hodin
	// -------------------------------------------------------------------------
	private function check_import_silence( array $cfg ): void {
		if ( empty( $cfg['events'][ self::EVENT_IMPORT_SILENCE ] ) ) return;

		$threshold_hours = (int) ( $cfg['thresholds'][ self::EVENT_IMPORT_SILENCE ] ?? 48 );

		// Jen pokud jsou aktivní mapování formulářů
		$active_maps = array_filter(
			$this->settings->get_form_mappings(),
			fn( $m ) => ! empty( $m['enabled'] ) && ! empty( $m['list_id'] )
		);
		if ( empty( $active_maps ) ) return;

		// Jen pokud někdy probíhaly importy
		if ( ! $this->import_ever_worked() ) return;

		$last_import = $this->logger->get_last_success( 'import' );
		if ( ! $last_import ) return;

		$last_time    = strtotime( $last_import['created_at'] );
		$hours_since  = ( time() - $last_time ) / 3600;

		if ( $hours_since < $threshold_hours ) return;
		if ( $this->is_on_cooldown( self::EVENT_IMPORT_SILENCE, $cfg ) ) return;

		$this->dispatch( self::EVENT_IMPORT_SILENCE, $cfg,
			'Upozorneni: Posledni import kontaktu byl pred ' . round( $hours_since ) . ' hodinami',
			'Posledni uspesny import kontaktu do SmartEmailingu probehl pred ' . round( $hours_since ) . ' hodinami (' . $last_import['created_at'] . ').' . "\n\n"
			. 'Pokud to neni zamer (nizky provoz, prazdniny), mohlo dojit k problemu s napojenim formularu nebo API.' . "\n\n"
			. 'Doporucene reseni: Zkontrolujte funkci formularu na webu, stav API a logy.'
		);
	}

	// -------------------------------------------------------------------------
	// Check: Webtracking ticho
	// -------------------------------------------------------------------------
	private function check_webtracking_silence( array $cfg ): void {
		if ( empty( $cfg['events'][ self::EVENT_WEBTRACK_SILENCE ] ) ) return;

		$wt = $this->settings->get_webtracking();
		if ( empty( $wt['enabled'] ) || empty( $wt['guid'] ) ) return;

		$threshold_hours = (int) ( $cfg['thresholds'][ self::EVENT_WEBTRACK_SILENCE ] ?? 72 );
		$state           = $this->get_state();
		$last_inject     = (int) ( $state['webtrack_last_inject'] ?? 0 );

		// Nikdy neinjektován – nové nastavení, neupozorňujeme
		if ( $last_inject === 0 ) return;

		$hours_since = ( time() - $last_inject ) / 3600;
		if ( $hours_since < $threshold_hours ) return;
		if ( $this->is_on_cooldown( self::EVENT_WEBTRACK_SILENCE, $cfg ) ) return;

		$this->dispatch( self::EVENT_WEBTRACK_SILENCE, $cfg,
			'Upozorneni: Webtracking se nezobrazil ' . round( $hours_since ) . ' hodin',
			'Webtracking SmartEmailing nebyl vlozen na zadnou stranku uz ' . round( $hours_since ) . ' hodin. Webtracking je zapnuty a ma nastaveny GUID, ale stranky ho nezobrazuji.' . "\n\n"
			. 'Mozne priciny: cache plugin blockuje skript, plugin byl deaktivovan a reaktivovan, nebo nastala jina zmena sablony.' . "\n\n"
			. 'Doporucene reseni: Navstivte web v anonymnim okne prohlizece a v F12 konzoli zkontrolujte, zda se nacita smartemailing tracking skript.'
		);
	}

	// -------------------------------------------------------------------------
	// Odeslání notifikace – vrací pole výsledků per-kanál
	// -------------------------------------------------------------------------
	private function dispatch( string $event, array $cfg, string $subject, string $body ): array {
		$site_name = get_bloginfo( 'name' );
		$site_url  = get_site_url();
		$overview  = admin_url( 'admin.php?page=smartemailing-connect' );

		$full_subject = '[SmartEmailing Connect | ' . $site_name . '] ' . $subject;
		$full_body    = $body . "\n\n"
			. '---' . "\n"
			. 'Web: ' . $site_url . "\n"
			. 'Prehled: ' . $overview . "\n"
			. 'Cas: ' . current_time( 'Y-m-d H:i:s' );

		$results = [];
		$any_sent = false;

		// ── Email ──────────────────────────────────────────────
		$emails = array_values( array_filter( array_map( 'sanitize_email', (array) ( $cfg['email'] ?? [] ) ) ) );
		if ( $emails ) {
			$mail_error = null;

			// Zachytit chybu wp_mail
			$capture_error = static function ( \WP_Error $err ) use ( &$mail_error ) {
				$mail_error = $err->get_error_message();
			};
			add_action( 'wp_mail_failed', $capture_error );

			$html = $this->build_email_html( $full_subject, $body, $overview, $site_name, $site_url );
			add_filter( 'wp_mail_content_type', static fn() => 'text/html' );
			$ok = wp_mail( $emails, $full_subject, $html );
			remove_filter( 'wp_mail_content_type', static fn() => 'text/html' );

			remove_action( 'wp_mail_failed', $capture_error );

			if ( $ok ) {
				$results['email'] = [ 'ok' => true,  'to' => implode( ', ', $emails ) ];
				$any_sent = true;
			} else {
				$results['email'] = [
					'ok'    => false,
					'to'    => implode( ', ', $emails ),
					'error' => $mail_error ?? 'wp_mail() vratil false. Zkontrolujte SMTP konfiguraci (plugin WP Mail SMTP).',
				];
			}
		} else {
			$results['email'] = [ 'ok' => false, 'error' => 'Zadna e-mailova adresa neni nastavena.' ];
		}

		// ── Slack ──────────────────────────────────────────────
		if ( ! empty( $cfg['slack_webhook'] ) ) {
			$response = $this->send_slack( $cfg['slack_webhook'], $subject, $body, $overview, $site_name );
			$code     = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
			if ( $code === 200 ) {
				$results['slack'] = [ 'ok' => true ];
				$any_sent = true;
			} else {
				$results['slack'] = [
					'ok'    => false,
					'error' => is_wp_error( $response ) ? $response->get_error_message() : "HTTP {$code}",
				];
			}
		}

		// ── Generic webhook ────────────────────────────────────
		if ( ! empty( $cfg['webhook_url'] ) ) {
			$response = $this->send_webhook( $cfg['webhook_url'], $event, $subject, $body, $site_url, $overview );
			$code     = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
			if ( $code >= 200 && $code < 300 ) {
				$results['webhook'] = [ 'ok' => true ];
				$any_sent = true;
			} else {
				$results['webhook'] = [
					'ok'    => false,
					'error' => is_wp_error( $response ) ? $response->get_error_message() : "HTTP {$code}",
				];
			}
		}

		if ( $any_sent ) {
			$this->mark_sent( $event );
			$this->logger->info( 'Notifikace odeslana: ' . $event, [ 'subject' => $subject ], 'notifications' );
		}

		return $results;
	}

	// -------------------------------------------------------------------------
	// Email HTML šablona
	// -------------------------------------------------------------------------
	private function build_email_html( string $subject, string $body, string $url, string $site, string $site_url ): string {
		$body_html = nl2br( esc_html( $body ) );
		return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;background:#f5f5f5;padding:20px;">'
			. '<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:6px;overflow:hidden;border:1px solid #e0e0e0;">'
			. '<div style="background:#c9372c;padding:16px 24px;">'
			. '<span style="color:#fff;font-size:1rem;font-weight:700;">&#9888; SmartEmailing Connect</span>'
			. '</div>'
			. '<div style="padding:24px;">'
			. '<h2 style="margin-top:0;color:#1d2327;">' . esc_html( $subject ) . '</h2>'
			. '<p style="color:#50575e;line-height:1.6;">' . $body_html . '</p>'
			. '<div style="margin-top:24px;padding-top:16px;border-top:1px solid #e0e0e0;">'
			. '<a href="' . esc_url( $url ) . '" style="background:#2271b1;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;font-weight:600;">Otevrit prehled</a>'
			. '</div>'
			. '</div>'
			. '<div style="background:#f6f7f7;padding:12px 24px;font-size:0.8rem;color:#72777c;">'
			. esc_html( $site ) . ' | ' . esc_url( $site_url )
			. '</div>'
			. '</div></body></html>';
	}

	// -------------------------------------------------------------------------
	// Slack
	// -------------------------------------------------------------------------
	private function send_slack( string $webhook, string $subject, string $body, string $url, string $site ) {
		$icon = str_contains( strtolower( $subject ), 'vypadek' ) ? ':red_circle:' : ':warning:';
		$text = $icon . ' *[' . $site . '] ' . $subject . "*\n"
			. str_replace( "\n\n", "\n", $body ) . "\n"
			. '<' . $url . '|Otevrit prehled>';

		return wp_remote_post( $webhook, [
			'timeout' => 10,
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( [ 'text' => $text ] ),
		] );
	}

	private function send_webhook( string $url, string $event, string $subject, string $body, string $site_url, string $dashboard_url ) {
		return wp_remote_post( $url, [
			'timeout' => 10,
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( [
				'plugin'        => 'smartemailing-connect',
				'event'         => $event,
				'subject'       => $subject,
				'message'       => $body,
				'site_url'      => $site_url,
				'dashboard_url' => $dashboard_url,
				'timestamp'     => current_time( 'c' ),
			] ),
		] );
	}

	// -------------------------------------------------------------------------
	// Cooldown
	// -------------------------------------------------------------------------
	private function is_on_cooldown( string $event, array $cfg ): bool {
		$cooldown_hours = (int) ( $cfg['cooldown_hours'] ?? 24 );
		$state          = $this->get_state();
		$last_sent      = (int) ( $state['last_sent'][ $event ] ?? 0 );

		return $last_sent > 0 && ( time() - $last_sent ) < ( $cooldown_hours * 3600 );
	}

	private function mark_sent( string $event ): void {
		// Atomic update přes WordPress option locking (update_option je transactional v InnoDB)
		global $wpdb;
		$option = self::STATE_OPTION;
		$now    = time();

		// Přečíst aktuální stav přímo z DB (obejít object cache)
		$raw   = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option ) );
		$state = $raw ? (array) maybe_unserialize( $raw ) : [];
		$state['last_sent'][ $event ] = $now;

		update_option( $option, $state );
	}

	// -------------------------------------------------------------------------
	// API error streak tracking (voláno z ApiService)
	// -------------------------------------------------------------------------
	public function record_api_error(): void {
		$state = $this->get_state();
		$state['api_error_count'] = (int) ( $state['api_error_count'] ?? 0 ) + 1;
		update_option( self::STATE_OPTION, $state );
	}

	public function reset_api_error_streak(): void {
		$state = $this->get_state();
		$state['api_error_count'] = 0;
		update_option( self::STATE_OPTION, $state );
	}

	// -------------------------------------------------------------------------
	// Webtracking injection tracking (voláno z Webtracking)
	// -------------------------------------------------------------------------
	public function record_webtrack_inject(): void {
		$state = $this->get_state();
		$state['webtrack_last_inject'] = time();
		update_option( self::STATE_OPTION, $state );
	}

	// -------------------------------------------------------------------------
	// Cron run tracking (voláno z Queue)
	// -------------------------------------------------------------------------
	public function record_cron_run(): void {
		$state = $this->get_state();
		$state['cron_last_run'] = time();
		update_option( self::STATE_OPTION, $state );
	}

	// -------------------------------------------------------------------------
	// Stav
	// -------------------------------------------------------------------------
	private function get_state(): array {
		return (array) get_option( self::STATE_OPTION, [] );
	}

	private function update_state( array $updates ): void {
		$state = array_merge( $this->get_state(), $updates );
		update_option( self::STATE_OPTION, $state );
	}

	public function get_state_public(): array {
		return $this->get_state();
	}

	// -------------------------------------------------------------------------
	// Pomocné: bylo to někdy funkční?
	// -------------------------------------------------------------------------
	private function api_ever_worked(): bool {
		// API fungovalo, pokud existuje alespoň 1 úspěšný import nebo ping
		$last = $this->logger->get_last_success( 'api' );
		if ( $last ) return true;
		$last = $this->logger->get_last_success( 'import' );
		return (bool) $last;
	}

	private function import_ever_worked(): bool {
		return (bool) $this->logger->get_last_success( 'import' );
	}

	// -------------------------------------------------------------------------
	// Manuální test – přijímá aktuální data z formuláře (ne jen z DB)
	// -------------------------------------------------------------------------
	public function send_test( array $cfg ): array {
		$subject = 'Test notifikace SmartEmailing Connect';
		$body    = 'Toto je testovaci zprava z webu ' . get_bloginfo( 'name' ) . '.'
			. "\n\n" . 'Pokud tuto zpravu vidite, notifikace funguje spravne.';

		$results = $this->dispatch( 'test', $cfg, $subject, $body );

		// Sestavit lidsky čitelný výsledek
		$messages  = [];
		$any_ok    = false;
		$any_fail  = false;

		if ( isset( $results['email'] ) ) {
			if ( $results['email']['ok'] ) {
				$messages[] = '✓ E-mail odeslan na: ' . ( $results['email']['to'] ?? '' );
				$any_ok = true;
			} else {
				$messages[] = '✗ E-mail selhal: ' . ( $results['email']['error'] ?? 'neznama chyba' );
				$any_fail = true;
			}
		}

		if ( isset( $results['slack'] ) ) {
			if ( $results['slack']['ok'] ) {
				$messages[] = '✓ Slack zprava odeslana.';
				$any_ok = true;
			} else {
				$messages[] = '✗ Slack selhal: ' . ( $results['slack']['error'] ?? 'neznama chyba' );
				$any_fail = true;
			}
		}

		if ( isset( $results['webhook'] ) ) {
			if ( $results['webhook']['ok'] ) {
				$messages[] = '✓ Webhook odeslan.';
				$any_ok = true;
			} else {
				$messages[] = '✗ Webhook selhal: ' . ( $results['webhook']['error'] ?? 'neznama chyba' );
				$any_fail = true;
			}
		}

		if ( empty( $messages ) ) {
			return [
				'success' => false,
				'message' => 'Zadny kanal neni nastaven. Zadejte e-mail, Slack webhook nebo generic webhook URL.',
				'results' => $results,
			];
		}

		return [
			'success'  => $any_ok,
			'message'  => implode( "\n", $messages ),
			'results'  => $results,
			'has_fail' => $any_fail,
		];
	}

	// -------------------------------------------------------------------------
	// Health check alert (voláno z SMEC_HealthCheck)
	// -------------------------------------------------------------------------
	public function send_health_alert( array $failures, array $cfg ): array {
		$count  = count( $failures );
		$suffix = $count > 1 ? 'y' : '';
		$lines  = [];
		foreach ( $failures as $f ) {
			$lines[] = '• ' . $f['check'] . ': ' . $f['detail'];
		}

		$subject = 'HEALTH CHECK: ' . $count . ' problém' . $suffix . ' nalezen' . $suffix;
		$body    = 'Automatická kontrola SmartEmailing Connect (každých 24h) nalezla ' . $count . ' kritický problém' . $suffix . ":\n\n"
			. implode( "\n", $lines ) . "\n\n"
			. 'Přejděte do administrace a zkontrolujte Přehled nebo Logy → Diagnostika.';

		return $this->dispatch( 'health_check', $cfg, $subject, $body );
	}

	// -------------------------------------------------------------------------
	// SMTP diagnostika
	// -------------------------------------------------------------------------
	public function get_mail_diagnostics(): array {
		$diag = [];

		// Detekce SMTP pluginů
		$smtp_plugins = [
			'wp-mail-smtp/wp_mail_smtp.php'      => 'WP Mail SMTP',
			'easy-wp-smtp/easy-wp-smtp.php'      => 'Easy WP SMTP',
			'post-smtp/postman-smtp.php'          => 'Post SMTP',
			'smtp-mailer/main.php'               => 'SMTP Mailer',
			'fluent-smtp/fluent-smtp.php'        => 'FluentSMTP',
		];

		$active_smtp = null;
		foreach ( $smtp_plugins as $slug => $name ) {
			if ( is_plugin_active( $slug ) ) {
				$active_smtp = $name;
				break;
			}
		}

		$diag['smtp_plugin']   = $active_smtp;
		$diag['is_localhost']  = in_array( $_SERVER['SERVER_NAME'] ?? '', [ 'localhost', '127.0.0.1', '::1' ], true )
			|| str_ends_with( $_SERVER['SERVER_NAME'] ?? '', '.local' );
		$diag['wp_mail_from']  = get_option( 'wp_mail_from', '' );
		$diag['admin_email']   = get_option( 'admin_email', '' );

		return $diag;
	}
}
