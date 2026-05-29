<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMEC_FormScanner {

	private SMEC_Settings $settings;

	private const CACHE_KEY    = 'smec_form_scan';
	private const CACHE_TTL    = 5 * MINUTE_IN_SECONDS;
	private const MAX_EL_POSTS = 150; // limit pro Elementor scan

	public function __construct( SMEC_Settings $settings ) {
		$this->settings = $settings;
	}

	// -------------------------------------------------------------------------
	// Scan (s cache)
	// -------------------------------------------------------------------------
	public function scan( bool $force = false ): array {
		if ( ! $force ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( $cached !== false ) return $cached;
		}

		$forms    = $this->detect_all_forms();
		$ignored  = $this->settings->get_ignored_forms();
		$mappings = $this->settings->get_form_mappings();

		foreach ( $forms as &$form ) {
			$form_key = $form['type'] . '|' . $form['id'];

			// Ignorován?
			if ( isset( $ignored[ $form_key ] ) ) {
				$form['connected']    = 'ignored';
				$form['mapping_name'] = null;
				$form['mapping_id']   = null;
				$form['ignore_reason']= $ignored[ $form_key ]['reason'] ?? '';
				continue;
			}

			$form['connected']    = false;
			$form['mapping_name'] = null;
			$form['mapping_id']   = null;
			$form['ignore_reason']= null;

			foreach ( $mappings as $m ) {
				if ( empty( $m['enabled'] ) ) continue;
				if ( ( $m['form_type'] ?? '' ) !== $form['type'] ) continue;
				if ( ( $m['form_id'] ?? '' ) === $form['id'] || ( $m['form_id'] ?? '' ) === '*' ) {
					$form['connected']    = true;
					$form['mapping_name'] = $m['name'] ?? 'Propojeni';
					$form['mapping_id']   = $m['id'] ?? null;
					break;
				}
			}
		}
		unset( $form );

		set_transient( self::CACHE_KEY, $forms, self::CACHE_TTL );
		return $forms;
	}

	public function clear_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	// -------------------------------------------------------------------------
	// Detekce všech formulářů
	// -------------------------------------------------------------------------
	private function detect_all_forms(): array {
		$forms = [];
		$forms = array_merge( $forms, $this->scan_elementor() );
		$forms = array_merge( $forms, $this->scan_cf7() );
		$forms = array_merge( $forms, $this->scan_wpforms() );
		$forms = array_merge( $forms, $this->scan_fluent() );
		$forms = array_merge( $forms, $this->scan_gravity() );
		$forms = array_merge( $forms, $this->scan_registration() );
		return $forms;
	}

	// -------------------------------------------------------------------------
	// Elementor Pro Forms – procházíme _elementor_data každé stránky
	// -------------------------------------------------------------------------
	private function scan_elementor(): array {
		if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) && ! defined( 'ELEMENTOR_VERSION' ) ) {
			return [];
		}

		global $wpdb;

		$post_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT pm.post_id
			 FROM {$wpdb->prefix}postmeta pm
			 INNER JOIN {$wpdb->prefix}posts p ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_elementor_data'
			   AND p.post_status = 'publish'
			 LIMIT %d",
			self::MAX_EL_POSTS
		) );

		if ( empty( $post_ids ) ) return [];

		// Sbíráme: form_name → seznam stránek
		$found_forms = []; // [ form_name => ['pages' => [...], ...] ]

		foreach ( $post_ids as $post_id ) {
			$raw = get_post_meta( (int) $post_id, '_elementor_data', true );
			if ( empty( $raw ) || ! is_string( $raw ) ) continue;

			// Ochrana před memory exhaustion u extrémně velkých stránek
			if ( strlen( $raw ) > 5_000_000 ) continue; // skip stránek > 5 MB

			$data = json_decode( $raw, true );
			if ( ! is_array( $data ) ) continue;

			$form_names = $this->extract_elementor_forms( $data );
			if ( empty( $form_names ) ) continue;

			$page_url   = get_permalink( (int) $post_id );
			$page_title = get_the_title( (int) $post_id );
			$edit_url   = get_edit_post_link( (int) $post_id, 'raw' ) ?? $page_url;

			foreach ( $form_names as $form_name ) {
				if ( ! isset( $found_forms[ $form_name ] ) ) {
					$found_forms[ $form_name ] = [
						'type'       => 'elementor',
						'plugin'     => 'Elementor Pro Forms',
						'id'         => $form_name,
						'name'       => $form_name,
						'pages'      => [],
						'edit_url'   => $edit_url,
						'detectable' => true,
					];
				}
				$found_forms[ $form_name ]['pages'][] = [
					'id'    => (int) $post_id,
					'title' => $page_title,
					'url'   => $page_url,
				];
			}
		}

		return array_values( $found_forms );
	}

	/**
	 * Rekurzivně projde elementy Elementoru a vrátí všechna nalezená jména formulářů.
	 */
	private function extract_elementor_forms( array $elements, int $depth = 0 ): array {
		if ( $depth > 10 ) return []; // ochrana před hlubokou rekurzí

		$names = [];
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) continue;

			// Form widget
			if ( ( $element['widgetType'] ?? '' ) === 'form' ) {
				$name = $element['settings']['form_name'] ?? '';
				if ( $name !== '' ) {
					$names[] = $name;
				}
			}

			// Rekurze do dětí
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$names = array_merge( $names, $this->extract_elementor_forms( $element['elements'], $depth + 1 ) );
			}
		}

		return $names;
	}

	// -------------------------------------------------------------------------
	// Contact Form 7
	// -------------------------------------------------------------------------
	private function scan_cf7(): array {
		if ( ! defined( 'WPCF7_VERSION' ) && ! class_exists( 'WPCF7_ContactForm' ) ) return [];

		$posts = get_posts( [
			'post_type'      => 'wpcf7_contact_form',
			'posts_per_page' => 200,
			'post_status'    => 'publish',
		] );

		$forms = [];
		foreach ( $posts as $p ) {
			// Najít stránky kde se shortcode formuláře používá
			$pages = $this->find_shortcode_pages( 'contact-form-7', 'id', (string) $p->ID );
			$forms[] = [
				'type'       => 'cf7',
				'plugin'     => 'Contact Form 7',
				'id'         => (string) $p->ID,
				'name'       => $p->post_title,
				'pages'      => $pages,
				'edit_url'   => admin_url( 'admin.php?page=wpcf7&post=' . $p->ID . '&action=edit' ),
				'detectable' => true,
			];
		}
		return $forms;
	}

	// -------------------------------------------------------------------------
	// WPForms
	// -------------------------------------------------------------------------
	private function scan_wpforms(): array {
		if ( ! defined( 'WPFORMS_VERSION' ) && ! function_exists( 'wpforms' ) ) return [];

		$posts = get_posts( [
			'post_type'      => 'wpforms',
			'posts_per_page' => 200,
			'post_status'    => 'any',
		] );

		$forms = [];
		foreach ( $posts as $p ) {
			$pages = $this->find_shortcode_pages( 'wpforms', 'id', (string) $p->ID );
			$forms[] = [
				'type'       => 'wpforms',
				'plugin'     => 'WPForms',
				'id'         => (string) $p->ID,
				'name'       => $p->post_title,
				'pages'      => $pages,
				'edit_url'   => admin_url( 'admin.php?page=wpforms-builder&view=fields&form_id=' . $p->ID ),
				'detectable' => true,
			];
		}
		return $forms;
	}

	// -------------------------------------------------------------------------
	// Fluent Forms
	// -------------------------------------------------------------------------
	private function scan_fluent(): array {
		if ( ! defined( 'FLUENTFORM_VERSION' ) ) return [];

		global $wpdb;
		$table = $wpdb->prefix . 'fluentform_forms';

		// Ověřit existenci tabulky bezpečně – bez string interpolace v LIKE
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s LIMIT 1',
				DB_NAME, $table )
		);
		if ( ! $table_exists ) return [];

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, title FROM `{$table}` WHERE status = %s LIMIT %d", 'published', 200 ),
			ARRAY_A
		);

		$forms = [];
		foreach ( $rows ?: [] as $r ) {
			$pages = $this->find_shortcode_pages( 'fluentform', 'id', (string) $r['id'] );
			$forms[] = [
				'type'       => 'fluent',
				'plugin'     => 'Fluent Forms',
				'id'         => (string) $r['id'],
				'name'       => $r['title'] ?: 'Form #' . $r['id'],
				'pages'      => $pages,
				'edit_url'   => admin_url( 'admin.php?page=fluent_forms&route=editor&form_id=' . $r['id'] ),
				'detectable' => true,
			];
		}
		return $forms;
	}

	// -------------------------------------------------------------------------
	// Gravity Forms
	// -------------------------------------------------------------------------
	private function scan_gravity(): array {
		if ( ! class_exists( 'GFAPI' ) ) return [];

		$gf_forms = \GFAPI::get_forms( true );
		if ( ! is_array( $gf_forms ) ) return [];

		$forms = [];
		foreach ( $gf_forms as $f ) {
			$id    = (string) ( $f['id'] ?? '' );
			$pages = $this->find_shortcode_pages( 'gravityforms', 'id', $id );
			$forms[] = [
				'type'       => 'gravity',
				'plugin'     => 'Gravity Forms',
				'id'         => $id,
				'name'       => $f['title'] ?? 'Form #' . $id,
				'pages'      => $pages,
				'edit_url'   => admin_url( 'admin.php?page=gf_edit_forms&id=' . $id ),
				'detectable' => true,
			];
		}
		return $forms;
	}

	// -------------------------------------------------------------------------
	// WP Registrace
	// -------------------------------------------------------------------------
	private function scan_registration(): array {
		return [ [
			'type'       => 'registration',
			'plugin'     => 'WordPress',
			'id'         => 'wp_registration',
			'name'       => 'Registrace uzivatele (WordPress)',
			'pages'      => [ [
				'id'    => 0,
				'title' => 'Registracni stranka',
				'url'   => wp_registration_url(),
			] ],
			'edit_url'   => admin_url( 'admin.php?page=smec-forms' ),
			'detectable' => true,
		] ];
	}

	// -------------------------------------------------------------------------
	// Hledat stránky kde se shortcode používá
	// -------------------------------------------------------------------------
	private function find_shortcode_pages( string $shortcode, string $attr, string $value ): array {
		global $wpdb;

		// LIKE search je pomalý ale pro admin scan přijatelný + cache chrání výkon
		$pattern = '%[' . $shortcode . '%' . $attr . '="' . $value . '"%]%';

		$post_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->prefix}posts
			 WHERE post_status = 'publish'
			   AND post_content LIKE %s
			 LIMIT 20",
			$pattern
		) );

		$pages = [];
		foreach ( $post_ids as $pid ) {
			$pages[] = [
				'id'    => (int) $pid,
				'title' => get_the_title( (int) $pid ),
				'url'   => get_permalink( (int) $pid ),
			];
		}
		return $pages;
	}

	// -------------------------------------------------------------------------
	// Statistiky
	// -------------------------------------------------------------------------
	public function stats( array $forms ): array {
		$detectable = array_filter( $forms, fn( $f ) => $f['detectable'] && $f['connected'] !== null );
		$connected  = array_filter( $detectable, fn( $f ) => $f['connected'] === true );
		$ignored    = array_filter( $detectable, fn( $f ) => $f['connected'] === 'ignored' );
		$missing    = array_filter( $detectable, fn( $f ) => $f['connected'] === false );

		return [
			'total'     => count( $detectable ),
			'connected' => count( $connected ),
			'ignored'   => count( $ignored ),
			'missing'   => count( $missing ),
		];
	}
}
