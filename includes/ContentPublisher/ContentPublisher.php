<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Content Publisher – při publikování článku spustí custom automation event v SE.
 *
 * Workflow:
 *  1. Zachytí přechod post_status → publish (transition_post_status hook).
 *  2. Ověří post type, kategorii a zda se již neodesílalo (meta _smec_pub_fired).
 *  3. Naplánuje WP Cron s volitelným zpožděním (neblokuje publish akci).
 *  4. Cron callback sestaví event_data z metadat postu a zavolá SE API.
 *  5. Výsledek zapíše do logu.
 */
class SMEC_ContentPublisher {

	private const CRON_HOOK = 'smec_content_publisher_fire';
	private const META_KEY  = '_smec_pub_fired';

	private SMEC_Settings   $settings;
	private SMEC_ApiService $api;
	private SMEC_Logger     $logger;

	public function __construct( SMEC_Settings $settings, SMEC_ApiService $api, SMEC_Logger $logger ) {
		$this->settings = $settings;
		$this->api      = $api;
		$this->logger   = $logger;
	}

	public function register(): void {
		add_action( 'transition_post_status', [ $this, 'on_status_change' ], 10, 3 );
		add_action( self::CRON_HOOK, [ $this, 'fire_event' ] );
	}

	// -------------------------------------------------------------------------

	/**
	 * Zachytí přechod na publish a naplánuje cron (nebo spustí okamžitě).
	 *
	 * @param string  $new_status
	 * @param string  $old_status
	 * @param WP_Post $post
	 */
	public function on_status_change( string $new_status, string $old_status, WP_Post $post ): void {
		// Zajímá nás pouze přechod NA publish (ne update už publikovaného)
		if ( $new_status !== 'publish' || $old_status === 'publish' ) {
			return;
		}

		$cfg = $this->settings->get_content_publisher();
		if ( empty( $cfg['enabled'] ) ) {
			return;
		}

		// Ověření post type
		if ( ! in_array( $post->post_type, (array) ( $cfg['post_types'] ?? [] ), true ) ) {
			return;
		}

		// Volitelný filtr kategorií (jen pro 'post', jiné CPT nemají taxonomy 'category')
		if ( ! empty( $cfg['categories'] ) && $post->post_type === 'post' ) {
			$post_cats = wp_get_post_categories( $post->ID, [ 'fields' => 'ids' ] );
			if ( empty( array_intersect( $cfg['categories'], $post_cats ) ) ) {
				return;
			}
		}

		// Neodesílat duplicitně (např. při save_post spuštěném vícekrát)
		if ( get_post_meta( $post->ID, self::META_KEY, true ) ) {
			return;
		}

		// Označit aby se neodesílalo znovu
		update_post_meta( $post->ID, self::META_KEY, current_time( 'mysql' ) );

		$delay_min = max( 0, (int) ( $cfg['delay_minutes'] ?? 0 ) );

		if ( $delay_min === 0 ) {
			// Spustit okamžitě na konci tohoto requestu – spolehlivější než čekat na WP Cron
			$post_id = $post->ID;
			add_action( 'shutdown', function () use ( $post_id ) {
				$this->fire_event( $post_id );
			} );
			$this->logger->info(
				sprintf( 'ContentPublisher: spustí se při shutdown tohoto requestu (post ID %d)', $post_id ),
				'content-publisher'
			);
		} else {
			wp_schedule_single_event(
				time() + $delay_min * MINUTE_IN_SECONDS,
				self::CRON_HOOK,
				[ $post->ID ]
			);
			$this->logger->info(
				sprintf( 'ContentPublisher: naplánováno přes WP Cron za %d min (post ID %d)', $delay_min, $post->ID ),
				'content-publisher'
			);
		}
	}

	/**
	 * WP Cron callback – sestaví event data a zavolá SE API.
	 *
	 * @param int $post_id
	 */
	public function fire_event( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post || $post->post_status !== 'publish' ) {
			$this->logger->warning(
				sprintf( 'ContentPublisher: post ID %d neexistuje nebo není publish – přeskočeno.', $post_id ),
				'content-publisher'
			);
			return;
		}

		$cfg = $this->settings->get_content_publisher();
		if ( empty( $cfg['enabled'] ) ) {
			return;
		}

		$trigger_email   = sanitize_email( $cfg['trigger_email'] ?? '' );
		$contact_list_id = (int) ( $cfg['contact_list_id'] ?? 0 );

		if ( ! is_email( $trigger_email ) && $contact_list_id <= 0 ) {
			$this->logger->error( 'ContentPublisher: není nastaven trigger email ani seznam kontaktů.', 'content-publisher' );
			return;
		}

		$event_name = sanitize_key( $cfg['event_name'] ?? 'new_article_published' );
		$event_data = $this->build_event_data( $post, $cfg );

		// Sestavit seznam příjemců
		$emails = is_email( $trigger_email ) ? [ $trigger_email ] : [];

		// Pokud je zadán seznam kontaktů, načíst z SE a sloučit
		if ( $contact_list_id > 0 ) {
			$offset = 0;
			do {
				$list_result = $this->api->get_emails_from_list( $contact_list_id, 500, $offset );
				if ( $list_result['success'] ) {
					$emails = array_unique( array_merge( $emails, $list_result['emails'] ) );
					$offset += 500;
					$has_more = count( $list_result['emails'] ) === 500;
				} else {
					$this->logger->warning(
						'ContentPublisher: nepodařilo se načíst kontakty ze seznamu ' . $contact_list_id . ': ' . ( $list_result['error'] ?? '' ),
						'content-publisher'
					);
					break;
				}
			} while ( $has_more ?? false );
		}

		if ( empty( $emails ) ) {
			$this->logger->error( 'ContentPublisher: žádní příjemci – event nebyl odeslán.', 'content-publisher' );
			return;
		}

		$result = $this->api->send_trigger_events( $event_name, $event_data, $emails );

		if ( $result['success'] ) {
			$this->logger->info(
				sprintf( 'ContentPublisher: event "%s" úspěšně odeslán pro post ID %d ("%s").', $event_name, $post_id, $post->post_title ),
				'content-publisher'
			);
		} else {
			$this->logger->error(
				sprintf(
					'ContentPublisher: chyba při odesílání eventu "%s" pro post ID %d – %s (kód: %s)',
					$event_name,
					$post_id,
					$result['error'] ?? 'neznámá chyba',
					$result['code'] ?? '?'
				),
				'content-publisher'
			);
			// Smazat meta flag aby bylo možné zkusit znovu (např. ruční resend)
			delete_post_meta( $post_id, self::META_KEY );
		}

		// Uložit poslední výsledek do post meta pro přehled v adminu
		update_post_meta( $post_id, '_smec_pub_last_result', [
			'success'    => $result['success'],
			'event_name' => $event_name,
			'list_id'    => $list_id,
			'sent_at'    => current_time( 'mysql' ),
			'error'      => $result['error'] ?? null,
		] );
	}

	/**
	 * Sestaví event_data payload z dat postu dle nastavení.
	 */
	private function build_event_data( WP_Post $post, array $cfg ): array {
		$data = [];

		if ( ! empty( $cfg['send_title'] ) ) {
			$data['post_title'] = $post->post_title;
		}
		if ( ! empty( $cfg['send_url'] ) ) {
			$data['post_url'] = get_permalink( $post->ID );
		}
		if ( ! empty( $cfg['send_excerpt'] ) ) {
			$data['post_excerpt'] = $post->post_excerpt
				?: wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
		}
		if ( ! empty( $cfg['send_thumbnail'] ) ) {
			$thumb = get_the_post_thumbnail_url( $post->ID, 'large' );
			if ( $thumb ) {
				$data['post_thumbnail'] = $thumb;
			}
		}
		if ( ! empty( $cfg['send_author'] ) ) {
			$data['post_author'] = get_the_author_meta( 'display_name', (int) $post->post_author );
		}
		if ( ! empty( $cfg['send_category'] ) && $post->post_type === 'post' ) {
			$cats = get_the_category( $post->ID );
			if ( $cats ) {
				$data['post_category'] = implode( ', ', wp_list_pluck( $cats, 'name' ) );
			}
		}

		$data['post_id']           = $post->ID;
		$data['post_type']         = $post->post_type;
		$data['post_date']         = $post->post_date;
		$data['post_date_formatted'] = date_i18n( get_option( 'date_format' ), strtotime( $post->post_date ) );

		return $data;
	}

	/**
	 * Ruční resend eventu pro konkrétní post (z admin UI).
	 * Smaže meta flag a okamžitě spustí fire_event.
	 */
	public function resend( int $post_id ): array {
		delete_post_meta( $post_id, self::META_KEY );
		$this->fire_event( $post_id );
		$result = get_post_meta( $post_id, '_smec_pub_last_result', true );
		return is_array( $result ) ? $result : [ 'success' => false, 'error' => 'Neznámá chyba.' ];
	}
}
