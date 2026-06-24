<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMEC_ApiService {

	private const TIMEOUT      = 15;
	private const RETRY_CODES  = [ 429, 500, 502, 503, 504 ];

	private SMEC_Settings  $settings;
	private SMEC_Logger    $logger;
	private ?SMEC_Notifier $notifier = null;

	public function __construct( SMEC_Settings $settings, SMEC_Logger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	public function set_notifier( SMEC_Notifier $notifier ): void {
		$this->notifier = $notifier;
	}

	// -------------------------------------------------------------------------
	// Public API methods
	// -------------------------------------------------------------------------

	public function ping(): array {
		$result = $this->request( 'GET', 'ping' );
		if ( $result['success'] ) {
			return [ 'success' => true, 'message' => 'Připojení úspěšné.' ];
		}
		return [ 'success' => false, 'message' => $result['error'] ?? 'Připojení selhalo.' ];
	}

	public function get_contact_lists(): array {
		$result = $this->request( 'GET', 'contactlists', [], [ 'limit' => 100, 'offset' => 0 ] );
		if ( ! $result['success'] ) {
			return [ 'success' => false, 'message' => $result['error'], 'code' => $result['code'] ?? 0, 'data' => [] ];
		}
		$data = $result['body']['data'] ?? $result['body'] ?? [];
		return [ 'success' => true, 'data' => $data ];
	}

	public function create_contact_list( string $name ): array {
		$result = $this->request( 'POST', 'contactlists', [
			'name'             => $name,
			'sendername'       => '',
			'senderemail'      => '',
			'replyto'          => '',
			'publicname'       => $name,
			'publicdescription'=> '',
		] );
		if ( ! $result['success'] ) {
			return [ 'success' => false, 'message' => $result['error'] ];
		}
		return [ 'success' => true, 'data' => $result['body']['data'] ?? [] ];
	}

	public function get_custom_fields( int $limit = 100, int $offset = 0 ): array {
		$result = $this->request( 'GET', 'customfields', [], [ 'limit' => $limit, 'offset' => $offset ] );
		if ( ! $result['success'] ) {
			return [ 'success' => false, 'message' => $result['error'], 'code' => $result['code'] ?? 0, 'data' => [] ];
		}
		$data = $result['body']['data'] ?? $result['body'] ?? [];
		return [ 'success' => true, 'data' => $data ];
	}

	public function create_custom_field( array $field_data ): array {
		$result = $this->request( 'POST', 'customfields', $field_data );
		if ( ! $result['success'] ) {
			return [ 'success' => false, 'message' => $result['error'] ];
		}
		return [ 'success' => true, 'data' => $result['body']['data'] ?? [] ];
	}

	/**
	 * Import one or more contacts.
	 *
	 * @param array $contacts  Array of contact objects matching SE API /import format.
	 * @param array $settings  import settings (update_existing_contacts, skip_invalid_orders, etc.)
	 */
	public function import_contacts( array $contacts, array $import_settings = [] ): array {
		$body = [
			'settings' => array_merge( [
				'update_existing_contacts' => true,
				'skip_invalid_orders'      => true,
			], $import_settings ),
			'data' => $contacts,
		];

		$result = $this->request( 'POST', 'import', $body );
		if ( ! $result['success'] ) {
			return [ 'success' => false, 'message' => $result['error'], 'retryable' => $result['retryable'] ?? false ];
		}
		return [ 'success' => true, 'data' => $result['body'] ];
	}

	// -------------------------------------------------------------------------
	// Core HTTP layer
	// -------------------------------------------------------------------------

	private function request( string $method, string $endpoint, array $body = [], array $query = [] ): array {
		$cfg = $this->settings->get_api();

		if ( empty( $cfg['username'] ) || empty( $cfg['api_key'] ) ) {
			return [ 'success' => false, 'error' => 'API přihlašovací údaje nejsou nakonfigurované.' ];
		}

		// Validace base URL – zamezit SSRF
		$base_raw = $cfg['base_url'] ?? 'https://app.smartemailing.cz/api/v3/';
		$base_raw = rtrim( $base_raw, '/' );
		if ( ! SMEC_Settings::validate_webhook_url( $base_raw . '/ping' ) ) {
			// Fallback na bezpečnou výchozí URL
			$base_raw = 'https://app.smartemailing.cz/api/v3';
			$this->logger->error( 'Neplatna API base URL, pouzivam vychozi.', [], 'api' );
		}
		$base = $base_raw;
		$url  = $base . '/' . ltrim( $endpoint, '/' );

		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		// Sestavit auth bez logování klíče
		$auth = 'Basic ' . base64_encode( $cfg['username'] . ':' . $cfg['api_key'] );

		$args = [
			'method'  => strtoupper( $method ),
			'headers' => [
				'Authorization' => $auth,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			],
			'timeout' => self::TIMEOUT,
		];

		if ( ! empty( $body ) && in_array( strtoupper( $method ), [ 'POST', 'PUT', 'PATCH' ], true ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response  = wp_remote_request( $url, $args );
		$retryable = false;

		if ( is_wp_error( $response ) ) {
			$error = $response->get_error_message();
			$this->logger->error( 'API request failed', [
				'endpoint' => $endpoint,
				'method'   => $method,
				'error'    => $error,
			], 'api' );
			return [ 'success' => false, 'error' => $error, 'retryable' => true ];
		}

		$code        = wp_remote_retrieve_response_code( $response );
		$body_string = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body_string, true );

		if ( in_array( (int) $code, self::RETRY_CODES, true ) ) {
			$retryable = true;
		}

		if ( $this->settings->is_debug() ) {
			$this->logger->debug( 'API response', [
				'endpoint' => $endpoint,
				'method'   => $method,
				'code'     => $code,
				'response' => mb_substr( $body_string, 0, 2000 ),
			], 'api' );
		}

		if ( (int) $code >= 200 && (int) $code < 300 ) {
			// Reset error streak při úspěchu
			if ( $this->notifier ) {
				$this->notifier->reset_api_error_streak();
			}
			return [ 'success' => true, 'code' => $code, 'body' => $decoded ?? [] ];
		}

		$api_message = $decoded['message'] ?? $decoded['error'] ?? "HTTP {$code}";
		$this->logger->error( 'API error response', [
			'endpoint' => $endpoint,
			'code'     => $code,
			'response' => mb_substr( $body_string, 0, 1000 ),
		], 'api' );

		// Zaznamenat error streak
		if ( $this->notifier ) {
			$this->notifier->record_api_error();
		}

		return [
			'success'   => false,
			'error'     => $api_message,
			'code'      => $code,
			'retryable' => $retryable,
		];
	}

	// ── Content Publisher – automation event trigger ──────────────────────────

	/**
	 * Načte emailové adresy kontaktů ze SE seznamu.
	 * Endpoint: GET /v3/contacts?select=emailaddress&contactlist_id={id}
	 *
	 * @return array{success: bool, emails: string[], error?: string}
	 */
	public function get_emails_from_list( int $contact_list_id, int $limit = 500, int $offset = 0 ): array {
		$result = $this->request(
			'GET',
			'contacts',
			[],
			[
				'select'          => 'emailaddress',
				'contactlist_id'  => $contact_list_id,
				'limit'           => $limit,
				'offset'          => $offset,
			]
		);

		if ( ! $result['success'] ) {
			return [ 'success' => false, 'emails' => [], 'error' => $result['error'] ?? 'Chyba načítání kontaktů ze seznamu.' ];
		}

		$data   = $result['body']['data'] ?? [];
		$emails = array_values( array_filter( array_column( $data, 'emailaddress' ) ) );

		return [
			'success' => true,
			'emails'  => $emails,
			'total'   => (int) ( $result['body']['total_count'] ?? count( $emails ) ),
		];
	}

	/**
	 * Vyvolá custom automation event pro všechny kontakty v daném seznamu.
	 *
	 * Používá per-contact endpoint (garantovaně dokumentovaný v SE API v3):
	 *   POST /v3/contacts/{emailaddress}/automation-event-trigger
	 *
	 * Workflow:
	 *  1. Načtou se emailové adresy ze SE seznamu.
	 *  2. Pro každý kontakt se pošle samostatný API request s event_name a payload.
	 *
	 * @param  string  $event_name      Název custom eventu (shodný s triggerem v SE automatizaci)
	 * @param  array   $event_data      Datový payload – proměnné v SE šabloně
	 * @param  int     $contact_list_id ID kontaktního seznamu v SE
	 */
	public function trigger_automation_event( string $event_name, array $event_data, int $contact_list_id ): array {
		if ( empty( $event_name ) || $contact_list_id <= 0 ) {
			return [ 'success' => false, 'error' => 'Chybí event_name nebo contact_list_id.' ];
		}

		// 1. Načti emailové adresy ze seznamu (stránkováno po 500)
		$all_emails  = [];
		$offset      = 0;
		$batch_limit = 500;

		do {
			$list_result = $this->get_emails_from_list( $contact_list_id, $batch_limit, $offset );
			if ( ! $list_result['success'] ) {
				return [
					'success' => false,
					'error'   => 'Nepodařilo se načíst kontakty ze seznamu ID ' . $contact_list_id . ': ' . ( $list_result['error'] ?? '' ),
				];
			}
			$all_emails = array_merge( $all_emails, $list_result['emails'] );
			$offset    += $batch_limit;
			$has_more   = count( $list_result['emails'] ) === $batch_limit;
		} while ( $has_more && count( $all_emails ) < 5000 );

		if ( empty( $all_emails ) ) {
			return [ 'success' => false, 'error' => 'Seznam kontaktů ID ' . $contact_list_id . ' neobsahuje žádné kontakty.' ];
		}

		// 2. Odeslat per-contact automation event trigger
		// Endpoint: POST /v3/contacts/{emailaddress}/automation-event-trigger
		$ok     = 0;
		$failed = 0;
		$last_error = '';

		foreach ( $all_emails as $email ) {
			$payload = [
				'event_name' => $event_name,
				'payload'    => $event_data,
			];

			$result = $this->request(
				'POST',
				'contacts/' . rawurlencode( $email ) . '/automation-event-trigger',
				$payload
			);

			if ( $result['success'] ) {
				$ok++;
			} else {
				$failed++;
				$last_error = $result['error'] ?? 'Neznámá chyba.';
			}
		}

		if ( $ok === 0 ) {
			return [
				'success' => false,
				'error'   => 'Všechny triggery selhaly. Poslední chyba: ' . $last_error,
			];
		}

		return [
			'success'        => true,
			'contacts_count' => count( $all_emails ),
			'ok'             => $ok,
			'failed'         => $failed,
		];
	}
}
