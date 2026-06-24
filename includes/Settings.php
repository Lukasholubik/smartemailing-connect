<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMEC_Settings {

	// -------------------------------------------------------------------------
	// Bezpečnostní helper: validace webhook URL (ochrana před SSRF)
	// -------------------------------------------------------------------------
	public static function validate_webhook_url( string $url ): bool {
		if ( $url === '' ) return true; // prázdné je OK (nepovinné)

		// Musí být validní URL
		if ( ! wp_http_validate_url( $url ) ) return false;

		// Povoleno jen HTTPS (ne http, file, ftp, data…)
		$scheme = strtolower( parse_url( $url, PHP_URL_SCHEME ) ?? '' );
		if ( $scheme !== 'https' ) return false;

		$host = strtolower( parse_url( $url, PHP_URL_HOST ) ?? '' );
		if ( $host === '' ) return false;

		// Zakázané hosty (localhost a interní sítě)
		$blocked_hosts = [ 'localhost', '127.0.0.1', '::1', '0.0.0.0', 'metadata.google.internal' ];
		if ( in_array( $host, $blocked_hosts, true ) ) return false;

		// Zakázaná interní IP rozsahy (SSRF protection)
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$ip = $host;
			// 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, 169.254.0.0/16, 100.64.0.0/10
			$private = filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
			if ( ! $private ) return false;
		}

		// DNS lookup – zkontrolovat IP i po resolvu hostname (ochrana před DNS rebinding)
		$resolved_ip = gethostbyname( $host );
		if ( $resolved_ip !== $host ) { // hostname se přeložil na IP
			$private = filter_var( $resolved_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
			if ( ! $private ) return false;
		}

		return true;
	}

	// -------------------------------------------------------------------------
	// Bezpečnostní helper: SVG sanitizace (ochrana před SVG/XSS injection)
	// -------------------------------------------------------------------------
	public static function sanitize_svg_safe( string $svg ): string {
		if ( $svg === '' ) return '';

		// Povolené SVG tagy a atributy (whitelist)
		$allowed = [
			'svg'      => [ 'xmlns' => true, 'viewbox' => true, 'width' => true, 'height' => true,
			                'fill' => true, 'stroke' => true, 'stroke-width' => true,
			                'stroke-linecap' => true, 'stroke-linejoin' => true,
			                'aria-hidden' => true, 'class' => true, 'style' => true,
			                'version' => true, 'xml:space' => true ],
			'path'     => [ 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true,
			                'fill-rule' => true, 'clip-rule' => true, 'class' => true ],
			'circle'   => [ 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true,
			                'stroke-width' => true, 'class' => true ],
			'rect'     => [ 'x' => true, 'y' => true, 'width' => true, 'height' => true,
			                'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'class' => true ],
			'line'     => [ 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true,
			                'stroke' => true, 'stroke-width' => true ],
			'polyline' => [ 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ],
			'polygon'  => [ 'points' => true, 'fill' => true, 'stroke' => true ],
			'ellipse'  => [ 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true ],
			'g'        => [ 'fill' => true, 'stroke' => true, 'transform' => true, 'class' => true ],
			'title'    => [],
			'desc'     => [],
		];

		$clean = wp_kses( $svg, $allowed );

		// Extra: odstranit zbylé JS a event handlery (defense in depth)
		$clean = preg_replace( '/\bon\w+\s*=/i', 'data-removed=', $clean );
		$clean = preg_replace( '/\bjavascript\s*:/i', '', $clean );
		$clean = preg_replace( '/<\s*script/i', '<!-- script removed', $clean );

		return trim( $clean );
	}

	// -------------------------------------------------------------------------
	// API
	// -------------------------------------------------------------------------
	public function get_api(): array {
		$defaults = [
			'username' => '',
			'api_key'  => '',
			'base_url' => 'https://app.smartemailing.cz/api/v3/',
			'enabled'  => 0,
		];
		return wp_parse_args( (array) get_option( 'smec_api_settings', [] ), $defaults );
	}

	public function save_api( array $data ): void {
		$saved = $this->get_api();

		// Never overwrite api_key with the masked placeholder
		if ( isset( $data['api_key'] ) && str_contains( (string) $data['api_key'], '***' ) ) {
			$data['api_key'] = $saved['api_key'];
		}

		$clean = [
			'username' => sanitize_text_field( $data['username'] ?? '' ),
			'api_key'  => sanitize_text_field( $data['api_key']  ?? $saved['api_key'] ),
			'base_url' => esc_url_raw( rtrim( $data['base_url'] ?? 'https://app.smartemailing.cz/api/v3/', '/' ) . '/' ),
			'enabled'  => ! empty( $data['enabled'] ) ? 1 : 0,
		];

		update_option( 'smec_api_settings', $clean );
	}

	// -------------------------------------------------------------------------
	// Webtracking
	// -------------------------------------------------------------------------
	public function get_webtracking(): array {
		$defaults = [
			'enabled'        => 0,
			'guid'           => '',
			'position'       => 'footer',
			'exclude_admins' => 1,
			'exclude_roles'  => [],
			'excluded_pages' => [],
			'custom_code'    => '',
		];
		return wp_parse_args( (array) get_option( 'smec_webtracking_settings', [] ), $defaults );
	}

	public function save_webtracking( array $data ): void {
		$clean = [
			'enabled'        => ! empty( $data['enabled'] ) ? 1 : 0,
			'guid'           => sanitize_text_field( $data['guid'] ?? '' ),
			'position'       => in_array( $data['position'] ?? '', [ 'head', 'footer' ], true ) ? $data['position'] : 'footer',
			'exclude_admins' => ! empty( $data['exclude_admins'] ) ? 1 : 0,
			'exclude_roles'  => array_map( 'sanitize_key', (array) ( $data['exclude_roles'] ?? [] ) ),
			'excluded_pages' => array_map( 'absint', (array) ( $data['excluded_pages'] ?? [] ) ),
			// Vlastní tracking kód: jen admini s unfiltered_html mohou vkládat libovolný kód
			// Bez unfiltered_html se kód odmítne – nepovolujeme ořezání (ořezaný JS je nebezpečný)
			'custom_code'    => current_user_can( 'unfiltered_html' ) ? ( $data['custom_code'] ?? '' ) : '',
		];
		update_option( 'smec_webtracking_settings', $clean );
	}

	// -------------------------------------------------------------------------
	// General / Modules
	// -------------------------------------------------------------------------
	public function get_general(): array {
		$defaults = [
			'debug'               => 0,
			'delete_on_uninstall' => 0,
			'log_retention_days'  => 30,
			'modules'             => [
				'webtracking'  => 1,
				'forms'        => 1,
				'woocommerce'  => 1,
				'reading_time' => 1,
				'gtm'          => 1,
			],
		];
		$saved = (array) get_option( 'smec_general_settings', [] );
		$result = wp_parse_args( $saved, $defaults );
		// Merge modules separately so nested defaults work
		$result['modules'] = wp_parse_args( $saved['modules'] ?? [], $defaults['modules'] );
		return $result;
	}

	public function save_general( array $data ): void {
		$allowed_modules = [ 'webtracking', 'forms', 'woocommerce', 'reading_time', 'gtm' ];
		$modules = [];
		foreach ( $allowed_modules as $m ) {
			$modules[ $m ] = ! empty( $data['modules'][ $m ] ) ? 1 : 0;
		}

		$clean = [
			'debug'               => ! empty( $data['debug'] ) ? 1 : 0,
			'delete_on_uninstall' => ! empty( $data['delete_on_uninstall'] ) ? 1 : 0,
			'log_retention_days'  => max( 1, min( 365, (int) ( $data['log_retention_days'] ?? 30 ) ) ),
			'modules'             => $modules,
		];
		update_option( 'smec_general_settings', $clean );
	}

	public function is_debug(): bool {
		return (bool) ( $this->get_general()['debug'] ?? 0 );
	}

	// -------------------------------------------------------------------------
	// Form Mappings
	// -------------------------------------------------------------------------
	public function get_form_mappings(): array {
		return (array) get_option( 'smec_form_mappings', [] );
	}

	public function save_form_mappings( array $mappings ): void {
		update_option( 'smec_form_mappings', $mappings );
	}

	public function get_mapping_by_id( string $id ): ?array {
		foreach ( $this->get_form_mappings() as $m ) {
			if ( ( $m['id'] ?? '' ) === $id ) return $m;
		}
		return null;
	}

	public function upsert_mapping( array $mapping ): string {
		$mappings = $this->get_form_mappings();

		if ( empty( $mapping['id'] ) ) {
			$mapping['id']         = 'smec_map_' . substr( md5( uniqid( '', true ) ), 0, 10 );
			$mapping['created_at'] = current_time( 'c' );
		}
		$mapping['updated_at'] = current_time( 'c' );

		$found = false;
		foreach ( $mappings as &$m ) {
			if ( ( $m['id'] ?? '' ) === $mapping['id'] ) {
				$m     = $mapping;
				$found = true;
				break;
			}
		}
		unset( $m );

		if ( ! $found ) {
			$mappings[] = $mapping;
		}

		$this->save_form_mappings( $mappings );
		return $mapping['id'];
	}

	public function delete_mapping( string $id ): bool {
		$mappings = $this->get_form_mappings();
		$filtered = array_values( array_filter( $mappings, fn( $m ) => ( $m['id'] ?? '' ) !== $id ) );
		if ( count( $filtered ) === count( $mappings ) ) return false;
		$this->save_form_mappings( $filtered );
		return true;
	}

	// -------------------------------------------------------------------------
	// Reading Time – presety (více vizuálních konfigurací)
	// -------------------------------------------------------------------------

	public function get_rt_presets(): array {
		$saved = get_option( 'smec_rt_presets', null );

		// Migrace ze starého jednoho nastavení na preset systém
		if ( $saved === null ) {
			$old = (array) get_option( 'smec_reading_time_settings', [] );
			$default = $this->make_rt_preset_defaults( 'default', 'Výchozí' );
			if ( $old ) {
				$default = array_merge( $default, array_intersect_key( $old, $default ) );
				$default['slug'] = 'default';
				$default['name'] = 'Výchozí';
			}
			return [ $default ];
		}

		return is_array( $saved ) ? $saved : [];
	}

	public function save_rt_presets( array $presets ): void {
		update_option( 'smec_rt_presets', $presets );
	}

	public function get_rt_preset_by_slug( string $slug ): ?array {
		foreach ( $this->get_rt_presets() as $p ) {
			if ( ( $p['slug'] ?? '' ) === $slug ) return $p;
		}
		// Fallback: first preset if 'default' not found
		$all = $this->get_rt_presets();
		return $all[0] ?? null;
	}

	public function upsert_rt_preset( array $preset ): string {
		$presets = $this->get_rt_presets();

		if ( empty( $preset['id'] ) ) {
			$preset['id']         = 'smec_rt_' . substr( md5( uniqid( '', true ) ), 0, 8 );
			$preset['created_at'] = current_time( 'c' );
		}
		$preset['updated_at'] = current_time( 'c' );

		$found = false;
		foreach ( $presets as &$p ) {
			if ( ( $p['id'] ?? '' ) === $preset['id'] ) {
				$p     = $preset;
				$found = true;
				break;
			}
		}
		unset( $p );
		if ( ! $found ) $presets[] = $preset;

		$this->save_rt_presets( $presets );
		return $preset['id'];
	}

	public function delete_rt_preset( string $id ): bool {
		$presets  = $this->get_rt_presets();
		$filtered = array_values( array_filter( $presets, fn( $p ) => ( $p['id'] ?? '' ) !== $id ) );
		if ( count( $filtered ) === count( $presets ) ) return false;
		$this->save_rt_presets( $filtered );
		return true;
	}

	public function make_rt_preset_defaults( string $slug = 'default', string $name = 'Výchozí' ): array {
		return [
			'id'            => '',
			'name'          => $name,
			'slug'          => $slug,
			'wpm'           => 200,
			'label'         => 'Doba čtení:',
			'suffix'        => 'min',
			'show_icon'     => 1,
			'icon'          => 'clock',      // 'clock' | 'custom'
			'custom_svg'    => '',
			'auto_insert'   => 0,
			'auto_position' => 'before',
			'post_types'    => [ 'post' ],
			'wrapper_tag'   => 'span',
			'css_class'     => '',
			'display'       => 'inline',
			'color'         => '',
			'icon_color'    => '',
			'font_size'     => '',
			'font_weight'   => '',
			'padding'       => '',
			'border_radius' => '',
			'background'    => '',
			'text_align'    => '',
			'icon_size'     => '',
			'custom_css'    => '',
			'created_at'    => '',
			'updated_at'    => '',
		];
	}

	public function sanitize_rt_preset( array $data ): array {
		$allowed_post_types = get_post_types( [ 'public' => true ] );
		$post_types = array_values( array_filter(
			(array) ( $data['post_types'] ?? [ 'post' ] ),
			fn( $pt ) => isset( $allowed_post_types[ $pt ] )
		) );

		// Slug: only alphanumeric + hyphens
		$slug = preg_replace( '/[^a-z0-9\-]/', '', strtolower( sanitize_title( $data['slug'] ?? 'preset' ) ) );
		if ( ! $slug ) $slug = 'preset';

		return [
			'id'            => sanitize_key( $data['id'] ?? '' ),
			'name'          => sanitize_text_field( $data['name'] ?? 'Preset' ),
			'slug'          => $slug,
			'wpm'           => max( 50, min( 1000, (int) ( $data['wpm'] ?? 200 ) ) ),
			'label'         => sanitize_text_field( $data['label']   ?? 'Doba čtení:' ),
			'suffix'        => sanitize_text_field( $data['suffix']  ?? 'min' ),
			'show_icon'     => ! empty( $data['show_icon'] ) ? 1 : 0,
			'icon'          => in_array( $data['icon'] ?? 'clock', [ 'clock', 'custom' ], true ) ? $data['icon'] : 'clock',
			'custom_svg'    => $this->sanitize_svg( $data['custom_svg'] ?? '' ),
			'auto_insert'   => ! empty( $data['auto_insert'] ) ? 1 : 0,
			'auto_position' => in_array( $data['auto_position'] ?? '', [ 'before', 'after' ], true ) ? $data['auto_position'] : 'before',
			'post_types'    => $post_types,
			'wrapper_tag'   => in_array( $data['wrapper_tag'] ?? '', [ 'span', 'div', 'p', 'strong' ], true ) ? $data['wrapper_tag'] : 'span',
			'css_class'     => sanitize_html_class( $data['css_class'] ?? '' ),
			'display'       => in_array( $data['display'] ?? '', [ 'inline', 'block' ], true ) ? $data['display'] : 'inline',
			'color'         => sanitize_hex_color( $data['color']        ?? '' ) ?? '',
			'icon_color'    => sanitize_hex_color( $data['icon_color']   ?? '' ) ?? '',
			'font_size'     => sanitize_text_field( $data['font_size']   ?? '' ),
			'font_weight'   => sanitize_text_field( $data['font_weight'] ?? '' ),
			'padding'       => sanitize_text_field( $data['padding']     ?? '' ),
			'border_radius' => sanitize_text_field( $data['border_radius'] ?? '' ),
			'background'    => sanitize_hex_color( $data['background']  ?? '' ) ?? '',
			'text_align'    => in_array( $data['text_align'] ?? '', [ '', 'left', 'center', 'right' ], true ) ? ( $data['text_align'] ?? '' ) : '',
			'icon_size'     => sanitize_text_field( $data['icon_size']   ?? '' ),
			'custom_css'    => wp_strip_all_tags( $data['custom_css']   ?? '' ),
			'created_at'    => sanitize_text_field( $data['created_at'] ?? '' ),
			'updated_at'    => '',
		];
	}

	private function sanitize_svg( string $svg ): string {
		return self::sanitize_svg_safe( $svg );
	}

	// Zpětná kompatibilita – starý kód který volá get_reading_time() dostane výchozí preset
	public function get_reading_time(): array {
		return $this->get_rt_preset_by_slug( 'default' ) ?? $this->make_rt_preset_defaults();
	}

	public function save_reading_time( array $data ): void {
		$preset = $this->sanitize_rt_preset( array_merge( [ 'slug' => 'default', 'name' => 'Výchozí' ], $data ) );
		$existing = $this->get_rt_preset_by_slug( 'default' );
		if ( $existing ) $preset['id'] = $existing['id'];
		$this->upsert_rt_preset( $preset );
	}

	// -------------------------------------------------------------------------
	// API cache: lists + custom fields
	// -------------------------------------------------------------------------
	public function get_cached_lists(): ?array {
		$cache = get_transient( 'smec_lists_cache' );
		return $cache !== false ? $cache : null;
	}

	public function set_cached_lists( array $lists ): void {
		set_transient( 'smec_lists_cache', $lists, HOUR_IN_SECONDS );
	}

	public function clear_lists_cache(): void {
		delete_transient( 'smec_lists_cache' );
	}

	public function get_cached_customfields(): ?array {
		$cache = get_transient( 'smec_customfields_cache' );
		return $cache !== false ? $cache : null;
	}

	public function set_cached_customfields( array $fields ): void {
		set_transient( 'smec_customfields_cache', $fields, 10 * MINUTE_IN_SECONDS );
	}

	public function clear_customfields_cache(): void {
		delete_transient( 'smec_customfields_cache' );
	}

	// -------------------------------------------------------------------------
	// WooCommerce settings
	// -------------------------------------------------------------------------
	public function get_woocommerce(): array {
		$defaults = [
			'enabled'              => 0,
			'list_id'              => '',
			'require_optin'        => 1,
			'optin_label'          => 'Přihlásit se k odběru newsletteru',
			'import_on_status'     => [ 'processing', 'completed' ],
			'status'               => 'confirmed',
			'field_mapping'        => [],
			'custom_field_mapping' => [],
			'tags'                 => [],
		];
		return wp_parse_args( (array) get_option( 'smec_woocommerce_settings', [] ), $defaults );
	}

	public function save_woocommerce( array $data ): void {
		$allowed_statuses = array_keys( wc_get_order_statuses() );
		$import_statuses = array_filter(
			(array) ( $data['import_on_status'] ?? [ 'completed' ] ),
			fn( $s ) => in_array( 'wc-' . $s, $allowed_statuses, true ) || in_array( $s, $allowed_statuses, true )
		);

		$clean = [
			'enabled'              => ! empty( $data['enabled'] ) ? 1 : 0,
			'list_id'              => (int) ( $data['list_id'] ?? 0 ),
			'require_optin'        => ! empty( $data['require_optin'] ) ? 1 : 0,
			'optin_label'          => sanitize_text_field( $data['optin_label'] ?? 'Přihlásit se k odběru newsletteru' ),
			'import_on_status'     => array_values( $import_statuses ),
			'status'               => in_array( $data['status'] ?? '', [ 'confirmed', 'unconfirmed' ], true ) ? $data['status'] : 'confirmed',
			'field_mapping'        => $this->sanitize_field_mapping( $data['field_mapping'] ?? [] ),
			'custom_field_mapping' => $this->sanitize_custom_field_mapping( $data['custom_field_mapping'] ?? [] ),
			'tags'                 => array_map( 'sanitize_text_field', (array) ( $data['tags'] ?? [] ) ),
		];
		update_option( 'smec_woocommerce_settings', $clean );
	}

	// -------------------------------------------------------------------------
	// GTM
	// -------------------------------------------------------------------------
	public function get_gtm(): array {
		$defaults = [
			'enabled'        => 0,
			'container_id'   => '',
			'exclude_admins' => 1,
			'exclude_roles'  => [],
		];
		$saved = (array) get_option( 'smec_gtm_settings', [] );
		return wp_parse_args( $saved, $defaults );
	}

	public function save_gtm( array $data ): void {
		$container_id = sanitize_text_field( $data['container_id'] ?? '' );

		// Pokud uživatel vložil celý GTM snippet, extrahujeme container ID
		if ( ! empty( $container_id ) && ! preg_match( '/^GTM-[A-Z0-9]+$/i', $container_id ) ) {
			$container_id = SMEC_GTM::extract_container_id( $container_id );
		} else {
			$container_id = strtoupper( $container_id );
		}

		$clean = [
			'enabled'        => ! empty( $data['enabled'] ) ? 1 : 0,
			'container_id'   => $container_id,
			'exclude_admins' => ! empty( $data['exclude_admins'] ) ? 1 : 0,
			'exclude_roles'  => array_map( 'sanitize_key', (array) ( $data['exclude_roles'] ?? [] ) ),
		];
		update_option( 'smec_gtm_settings', $clean );
	}

	// -------------------------------------------------------------------------
	// Sanitization helpers
	// -------------------------------------------------------------------------
	public function sanitize_field_mapping( array $mapping ): array {
		$allowed_system = [ 'emailaddress', 'name', 'surname', 'cellphone', 'birthday', 'nameday', 'salution' ];
		$clean = [];
		foreach ( $mapping as $system_field => $config ) {
			if ( ! in_array( $system_field, $allowed_system, true ) ) continue;
			$clean[ $system_field ] = [
				'source' => in_array( $config['source'] ?? '', [ 'form_field', 'static', 'placeholder' ], true ) ? $config['source'] : 'form_field',
				'value'  => sanitize_text_field( $config['value'] ?? '' ),
			];
		}
		return $clean;
	}

	public function sanitize_custom_field_mapping( array $mapping ): array {
		$clean = [];
		foreach ( $mapping as $row ) {
			if ( empty( $row['field_id'] ) ) continue;
			$clean[] = [
				'field_id' => (int) $row['field_id'],
				'source'   => in_array( $row['source'] ?? '', [ 'form_field', 'static', 'placeholder' ], true ) ? $row['source'] : 'static',
				'value'    => sanitize_text_field( $row['value'] ?? '' ),
			];
		}
		return $clean;
	}

	public function sanitize_tags( array $tags ): array {
		$clean = [];
		foreach ( $tags as $tag ) {
			$clean[] = [
				'source' => in_array( $tag['source'] ?? '', [ 'static', 'placeholder', 'form_field' ], true ) ? $tag['source'] : 'static',
				'value'  => sanitize_text_field( $tag['value'] ?? '' ),
			];
		}
		return $clean;
	}

	// -------------------------------------------------------------------------
	// Notification settings
	// -------------------------------------------------------------------------
	public function get_notification_settings(): array {
		$defaults = [
			'enabled'       => 0,
			'email'         => [ get_option( 'admin_email', '' ) ],
			'slack_webhook' => '',
			'webhook_url'   => '',
			'cooldown_hours'=> 24,
			'events'        => [
				SMEC_Notifier::EVENT_API_STREAK       => 1,
				SMEC_Notifier::EVENT_IMPORT_FAILURES  => 1,
				SMEC_Notifier::EVENT_CRON_STOPPED     => 1,
				SMEC_Notifier::EVENT_IMPORT_SILENCE   => 1,
				SMEC_Notifier::EVENT_WEBTRACK_SILENCE => 0,
			],
			'thresholds'    => [
				SMEC_Notifier::EVENT_API_STREAK       => 3,
				SMEC_Notifier::EVENT_IMPORT_FAILURES  => 5,
				SMEC_Notifier::EVENT_CRON_STOPPED     => 6,
				SMEC_Notifier::EVENT_IMPORT_SILENCE   => 48,
				SMEC_Notifier::EVENT_WEBTRACK_SILENCE => 72,
			],
		];
		$saved = (array) get_option( 'smec_notification_settings', [] );
		$result = wp_parse_args( $saved, $defaults );
		$result['events']     = wp_parse_args( $saved['events']     ?? [], $defaults['events'] );
		$result['thresholds'] = wp_parse_args( $saved['thresholds'] ?? [], $defaults['thresholds'] );
		return $result;
	}

	public function save_notification_settings( array $data ): void {
		$allowed_events = [
			SMEC_Notifier::EVENT_API_STREAK,
			SMEC_Notifier::EVENT_IMPORT_FAILURES,
			SMEC_Notifier::EVENT_CRON_STOPPED,
			SMEC_Notifier::EVENT_IMPORT_SILENCE,
			SMEC_Notifier::EVENT_WEBTRACK_SILENCE,
		];

		$events     = [];
		$thresholds = [];
		foreach ( $allowed_events as $e ) {
			$events[ $e ]     = ! empty( $data['events'][ $e ] ) ? 1 : 0;
			$thresholds[ $e ] = max( 1, (int) ( $data['thresholds'][ $e ] ?? 3 ) );
		}

		// Emaily – rozdělit řádkové nebo čárkami oddělené
		$raw_emails = $data['email'] ?? '';
		if ( is_string( $raw_emails ) ) {
			$raw_emails = preg_split( '/[\n,;]+/', $raw_emails );
		}
		$emails = array_values( array_filter( array_map( 'sanitize_email', (array) $raw_emails ) ) );

		$slack_url   = esc_url_raw( $data['slack_webhook'] ?? '' );
		$webhook_url = esc_url_raw( $data['webhook_url']   ?? '' );

		// Bezpečnostní validace URL – zamezit SSRF útokům
		if ( $slack_url && ! self::validate_webhook_url( $slack_url ) ) {
			$slack_url = ''; // Odmítnout nebezpečnou URL
		}
		if ( $webhook_url && ! self::validate_webhook_url( $webhook_url ) ) {
			$webhook_url = '';
		}

		$clean = [
			'enabled'       => ! empty( $data['enabled'] ) ? 1 : 0,
			'email'         => $emails,
			'slack_webhook' => $slack_url,
			'webhook_url'   => $webhook_url,
			'cooldown_hours'=> max( 1, min( 168, (int) ( $data['cooldown_hours'] ?? 24 ) ) ),
			'events'        => $events,
			'thresholds'    => $thresholds,
		];

		update_option( 'smec_notification_settings', $clean );
	}

	// -------------------------------------------------------------------------
	// Ignore list pro monitor formulářů
	// Klíč: "{type}|{form_id}", hodnota: ['reason' => '...', 'ignored_at' => '...']
	// -------------------------------------------------------------------------
	public function get_ignored_forms(): array {
		return (array) get_option( 'smec_ignored_forms', [] );
	}

	public function ignore_form( string $type, string $form_id, string $reason = '' ): void {
		$key  = sanitize_key( $type ) . '|' . sanitize_text_field( $form_id );
		$list = $this->get_ignored_forms();
		$list[ $key ] = [
			'type'       => sanitize_key( $type ),
			'form_id'    => sanitize_text_field( $form_id ),
			'reason'     => sanitize_text_field( $reason ),
			'ignored_at' => current_time( 'c' ),
		];
		update_option( 'smec_ignored_forms', $list );
	}

	public function unignore_form( string $type, string $form_id ): void {
		$key  = sanitize_key( $type ) . '|' . sanitize_text_field( $form_id );
		$list = $this->get_ignored_forms();
		unset( $list[ $key ] );
		update_option( 'smec_ignored_forms', $list );
	}

	// ── Content Publisher ─────────────────────────────────────────────────────

	public function get_content_publisher(): array {
		$defaults = [
			'enabled'          => 0,
			'post_types'       => [ 'post' ],
			'categories'       => [],
			'event_name'       => 'new_article_published',
			'trigger_email'    => '',
			'contact_list_id'  => 0,
			'delay_days'       => 0,
			'delay_hours'      => 0,
			'delay_minutes'    => 0,
			'send_title'       => 1,
			'send_url'         => 1,
			'send_excerpt'     => 1,
			'send_thumbnail'   => 1,
			'send_author'      => 0,
			'send_category'    => 0,
		];
		$saved = (array) get_option( 'smec_content_publisher', [] );
		return wp_parse_args( $saved, $defaults );
	}

	/** Vrátí celkové zpoždění v minutách (dny + hodiny + minuty). */
	public function get_content_publisher_delay_minutes(): int {
		$cfg = $this->get_content_publisher();
		return (int) $cfg['delay_days'] * 1440
			+ (int) $cfg['delay_hours'] * 60
			+ (int) $cfg['delay_minutes'];
	}

	public function save_content_publisher( array $data ): void {
		$post_types = array_values( array_filter( array_map( 'sanitize_key', (array) ( $data['post_types'] ?? [] ) ) ) );
		$categories = array_values( array_filter( array_map( 'absint', (array) ( $data['categories'] ?? [] ) ) ) );

		update_option( 'smec_content_publisher', [
			'enabled'         => ! empty( $data['enabled'] ) ? 1 : 0,
			'post_types'      => $post_types ?: [ 'post' ],
			'categories'      => $categories,
			'event_name'      => sanitize_key( $data['event_name'] ?? 'new_article_published' ),
			'trigger_email'   => sanitize_email( $data['trigger_email'] ?? '' ),
			'contact_list_id' => absint( $data['contact_list_id'] ?? 0 ),
			'delay_days'      => max( 0, absint( $data['delay_days']    ?? 0 ) ),
			'delay_hours'     => max( 0, min( 23, absint( $data['delay_hours']   ?? 0 ) ) ),
			'delay_minutes'   => max( 0, min( 59, absint( $data['delay_minutes'] ?? 0 ) ) ),
			'send_title'      => ! empty( $data['send_title'] )     ? 1 : 0,
			'send_url'        => ! empty( $data['send_url'] )       ? 1 : 0,
			'send_excerpt'    => ! empty( $data['send_excerpt'] )   ? 1 : 0,
			'send_thumbnail'  => ! empty( $data['send_thumbnail'] ) ? 1 : 0,
			'send_author'     => ! empty( $data['send_author'] )    ? 1 : 0,
			'send_category'   => ! empty( $data['send_category'] )  ? 1 : 0,
		] );
	}
}
