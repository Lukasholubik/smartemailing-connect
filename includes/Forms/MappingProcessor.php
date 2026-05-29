<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Resolves a form mapping config + raw form data into a SmartEmailing contact array.
 */
class SMEC_MappingProcessor {

	private SMEC_Logger $logger;

	private const PLACEHOLDERS = [
		'__FORM_NAME__',
		'__REFERRER__',
		'__CURRENT_URL__',
		'__PAGE_TITLE__',
		'__POST_ID__',
		'__POST_TITLE__',
		'__DATE_TIME__',
		'__DATE__',
		'__TIME__',
		'__SITE_URL__',
		'__SITE_NAME__',
		'__USER_ID__',
		'__USER_EMAIL__',
		'__UTM_SOURCE__',
		'__UTM_MEDIUM__',
		'__UTM_CAMPAIGN__',
		'__UTM_CONTENT__',
		'__UTM_TERM__',
	];

	public function __construct( SMEC_Logger $logger ) {
		$this->logger = $logger;
	}

	// -------------------------------------------------------------------------
	// Main entry point
	// -------------------------------------------------------------------------

	/**
	 * Build a SmartEmailing contact array from a mapping config and form fields.
	 *
	 * @param array $mapping      Stored mapping config from SMEC_Settings.
	 * @param array $form_fields  Raw key→value pairs from the form submission.
	 * @param array $context      Extra context: form_name, referrer, current_url, page_title, post_id, post_title, utm_*.
	 * @return array|null  Contact array ready for ApiService::import_contacts(), or null if email is missing/invalid.
	 */
	public function process( array $mapping, array $form_fields, array $context = [] ): ?array {
		$placeholders = $this->build_placeholders( $context, $form_fields );

		// --- Required: email ---
		$email = $this->resolve( $mapping['system_fields']['emailaddress'] ?? [], $form_fields, $placeholders );
		$email = sanitize_email( (string) $email );
		if ( ! is_email( $email ) ) {
			$this->logger->error( 'MappingProcessor: invalid or missing email', [ 'mapping_id' => $mapping['id'] ?? '' ], 'forms' );
			return null;
		}

		// --- System fields ---
		$contact = [ 'emailaddress' => $email ];

		$system_map = [
			'name'      => 'name',
			'surname'   => 'surname',
			'cellphone' => 'cellphone',
			'birthday'  => 'birthday',
			'nameday'   => 'nameday',
			'salution'  => 'salution',
		];

		foreach ( $system_map as $se_field => $_ ) {
			if ( ! isset( $mapping['system_fields'][ $se_field ] ) ) continue;
			$value = $this->resolve( $mapping['system_fields'][ $se_field ], $form_fields, $placeholders );
			if ( $value !== '' ) {
				$contact[ $se_field ] = sanitize_text_field( (string) $value );
			}
		}

		// --- Contact list ---
		if ( ! empty( $mapping['list_id'] ) ) {
			$status = in_array( $mapping['contact_status'] ?? 'confirmed', [ 'confirmed', 'unconfirmed' ], true )
				? $mapping['contact_status']
				: 'confirmed';

			$contact['contactlists'] = [
				[ 'id' => (int) $mapping['list_id'], 'status' => $status ],
			];
		}

		// --- Custom fields ---
		$custom_fields = [];
		foreach ( (array) ( $mapping['custom_field_mapping'] ?? [] ) as $row ) {
			if ( empty( $row['field_id'] ) ) continue;
			$value = $this->resolve( $row, $form_fields, $placeholders );
			if ( $value !== '' ) {
				$custom_fields[] = [ 'id' => (int) $row['field_id'], 'value' => (string) $value ];
			}
		}
		if ( $custom_fields ) {
			$contact['customfields'] = $custom_fields;
		}

		// --- Tags ---
		$tags = [];
		foreach ( (array) ( $mapping['tags'] ?? [] ) as $tag_row ) {
			$value = $this->resolve( $tag_row, $form_fields, $placeholders );
			if ( $value !== '' ) {
				$tags[] = sanitize_text_field( (string) $value );
			}
		}
		if ( $tags ) {
			$contact['tags'] = array_values( array_unique( $tags ) );
		}

		return $contact;
	}

	// -------------------------------------------------------------------------
	// Placeholder resolution
	// -------------------------------------------------------------------------

	private function build_placeholders( array $context, array $form_fields ): array {
		$now = current_time( 'timestamp' );

		$utm = $this->extract_utm( $context );

		return [
			'__FORM_NAME__'    => $context['form_name']   ?? '',
			'__REFERRER__'     => $context['referrer']    ?? ( isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '' ),
			'__CURRENT_URL__'  => $context['current_url'] ?? ( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '' ),
			'__PAGE_TITLE__'   => $context['page_title']  ?? get_the_title(),
			'__POST_ID__'      => (string) ( $context['post_id'] ?? get_the_ID() ),
			'__POST_TITLE__'   => $context['post_title']  ?? get_the_title(),
			'__DATE_TIME__'    => date_i18n( 'Y-m-d H:i:s', $now ),
			'__DATE__'         => date_i18n( 'Y-m-d', $now ),
			'__TIME__'         => date_i18n( 'H:i:s', $now ),
			'__SITE_URL__'     => get_site_url(),
			'__SITE_NAME__'    => get_bloginfo( 'name' ),
			'__USER_ID__'      => is_user_logged_in() ? (string) get_current_user_id() : '',
			'__USER_EMAIL__'   => is_user_logged_in() ? wp_get_current_user()->user_email : '',
			'__UTM_SOURCE__'   => $utm['utm_source']   ?? '',
			'__UTM_MEDIUM__'   => $utm['utm_medium']   ?? '',
			'__UTM_CAMPAIGN__' => $utm['utm_campaign'] ?? '',
			'__UTM_CONTENT__'  => $utm['utm_content']  ?? '',
			'__UTM_TERM__'     => $utm['utm_term']     ?? '',
		];
	}

	private function extract_utm( array $context ): array {
		$keys = [ 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' ];
		$utm  = [];
		foreach ( $keys as $k ) {
			$utm[ $k ] = sanitize_text_field( $context[ $k ] ?? '' );
		}
		return $utm;
	}

	private function resolve( array $config, array $form_fields, array $placeholders ): string {
		$source = $config['source'] ?? 'form_field';
		$value  = (string) ( $config['value'] ?? '' );

		return match ( $source ) {
			'static'      => $value,
			'placeholder' => $placeholders[ $value ] ?? '',
			'form_field'  => sanitize_text_field( (string) ( $form_fields[ $value ] ?? '' ) ),
			default       => '',
		};
	}

	// -------------------------------------------------------------------------
	// Condition evaluation (simple equality checks)
	// -------------------------------------------------------------------------

	public function check_conditions( array $mapping, array $form_fields ): bool {
		$conditions = $mapping['conditions'] ?? [];
		if ( empty( $conditions ) ) return true;

		foreach ( $conditions as $cond ) {
			$field    = $cond['field']    ?? '';
			$operator = $cond['operator'] ?? '==';
			$expected = (string) ( $cond['value'] ?? '' );
			$actual   = (string) ( $form_fields[ $field ] ?? '' );

			$passed = match ( $operator ) {
				'=='      => $actual === $expected,
				'!='      => $actual !== $expected,
				'contains'=> str_contains( $actual, $expected ),
				'not_empty'=> $actual !== '',
				'empty'   => $actual === '',
				default   => true,
			};

			if ( ! $passed ) return false;
		}

		return true;
	}
}
