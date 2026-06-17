<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMEC_FormManager {

	private SMEC_Settings         $settings;
	private SMEC_ApiService       $api;
	private SMEC_MappingProcessor $processor;
	private SMEC_Queue            $queue;
	private SMEC_Logger           $logger;

	public function __construct(
		SMEC_Settings $settings,
		SMEC_ApiService $api,
		SMEC_MappingProcessor $processor,
		SMEC_Queue $queue,
		SMEC_Logger $logger
	) {
		$this->settings  = $settings;
		$this->api       = $api;
		$this->processor = $processor;
		$this->queue     = $queue;
		$this->logger    = $logger;
	}

	public function register(): void {
		// Nothing to hook at plugin level; individual integrations hook their own events
	}

	// -------------------------------------------------------------------------
	// Core: handle a form submission
	// -------------------------------------------------------------------------

	/**
	 * Called by form integrations when a form is submitted.
	 *
	 * @param string $form_type  e.g. 'elementor', 'cf7', 'fluent', 'wpforms', 'registration', 'woocommerce'
	 * @param string $form_id    Unique ID or name of the form
	 * @param array  $fields     Raw key→value array of form fields
	 * @param array  $context    Extra context (form_name, referrer, utm_*, etc.)
	 */
	public function handle_submission( string $form_type, string $form_id, array $fields, array $context = [] ): void {
		$mappings = $this->settings->get_form_mappings();

		$this->logger->debug( 'Form submission received', [
			'form_type' => $form_type,
			'form_id'   => $form_id,
			'fields'    => array_keys( $fields ),
		], 'forms' );

		$matched = false;
		foreach ( $mappings as $mapping ) {
			if ( empty( $mapping['enabled'] ) ) continue;
			if ( ( $mapping['form_type'] ?? '' ) !== $form_type ) continue;
			if ( ( $mapping['form_id'] ?? '' ) !== $form_id && ( $mapping['form_id'] ?? '' ) !== '*' ) continue;

			$matched = true;
			$this->process_mapping( $mapping, $fields, $context );
		}

		if ( ! $matched ) {
			$this->logger->info( 'Žádné mapování nenalezeno pro odeslaný formulář', [
				'form_type'    => $form_type,
				'form_id'      => $form_id,
				'total_maps'   => count( $mappings ),
				'enabled_maps' => count( array_filter( $mappings, fn( $m ) => ! empty( $m['enabled'] ) ) ),
			], 'forms' );
		}
	}

	private function process_mapping( array $mapping, array $fields, array $context ): void {
		// Check conditions
		if ( ! $this->processor->check_conditions( $mapping, $fields ) ) {
			$this->logger->debug( 'Mapping conditions not met', [ 'mapping_id' => $mapping['id'] ], 'forms' );
			return;
		}

		// Check consent field
		if ( ! empty( $mapping['consent_field'] ) ) {
			$consent_val = $fields[ $mapping['consent_field'] ] ?? '';
			if ( empty( $consent_val ) || in_array( strtolower( (string) $consent_val ), [ '0', 'false', 'no', 'off' ], true ) ) {
				$this->logger->info( 'Contact not imported – consent not given', [ 'mapping_id' => $mapping['id'] ], 'forms' );
				return;
			}
		}

		$contact = $this->processor->process( $mapping, $fields, $context );
		if ( $contact === null ) {
			return; // error already logged in processor
		}

		$import_settings = [
			'update_existing_contacts' => true,
			'skip_invalid_orders'      => true,
		];

		$result = $this->api->import_contacts( [ $contact ], $import_settings );

		if ( $result['success'] ) {
			$this->logger->info( 'Contact imported', [
				'mapping_id'   => $mapping['id'],
				'mapping_name' => $mapping['name']      ?? '',
				'form_type'    => $mapping['form_type'] ?? '',
				'form_id'      => $mapping['form_id']   ?? '',
				'email_hash'   => md5( $contact['emailaddress'] ),
			], 'import' );
			do_action( 'smec_contact_imported', $contact, $mapping );
		} else {
			$this->logger->error( 'Contact import failed', [
				'mapping_id' => $mapping['id'],
				'message'    => $result['message'] ?? '',
			], 'import' );

			// Queue for retry if retryable
			if ( ! empty( $result['retryable'] ) ) {
				$this->queue->add( $mapping['id'], $contact );
				$this->logger->info( 'Contact added to retry queue', [ 'mapping_id' => $mapping['id'] ], 'queue' );
			}
		}
	}

	// -------------------------------------------------------------------------
	// Test: send a test contact for a mapping
	// -------------------------------------------------------------------------
	public function send_test_contact( string $mapping_id, string $test_email ): array {
		$mapping = $this->settings->get_mapping_by_id( $mapping_id );
		if ( ! $mapping ) {
			return [ 'success' => false, 'message' => 'Mapování nenalezeno.' ];
		}

		if ( ! is_email( $test_email ) ) {
			return [ 'success' => false, 'message' => 'Neplatný testovací e-mail.' ];
		}

		$fake_fields = [ 'email' => $test_email, 'name' => 'Test', 'surname' => 'SmartEmailing Connect' ];
		$context     = [ 'form_name' => 'TEST' ];

		// Override email in system fields for test
		$test_mapping = $mapping;
		$test_mapping['system_fields']['emailaddress'] = [ 'source' => 'static', 'value' => $test_email ];

		$contact = $this->processor->process( $test_mapping, $fake_fields, $context );
		if ( $contact === null ) {
			return [ 'success' => false, 'message' => 'Nepodařilo se sestavit testovací kontakt.' ];
		}

		$result = $this->api->import_contacts( [ $contact ] );
		if ( $result['success'] ) {
			$this->logger->info( 'Test contact sent', [ 'mapping_id' => $mapping_id, 'test_email' => $test_email ], 'import' );
		}
		return $result;
	}

	// -------------------------------------------------------------------------
	// Mapping CRUD helpers (delegates to Settings)
	// -------------------------------------------------------------------------
	public function get_mappings(): array {
		return $this->settings->get_form_mappings();
	}

	public function get_mappings_for_type( string $form_type ): array {
		return array_values( array_filter(
			$this->settings->get_form_mappings(),
			fn( $m ) => ( $m['form_type'] ?? '' ) === $form_type
		) );
	}
}
