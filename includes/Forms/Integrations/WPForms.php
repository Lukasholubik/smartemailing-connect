<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMEC_WPForms {

	private SMEC_FormManager $manager;

	public function __construct( SMEC_FormManager $manager ) {
		$this->manager = $manager;
	}

	public function register(): void {
		add_action( 'wpforms_process_complete', [ $this, 'on_form_submitted' ], 10, 4 );
	}

	/**
	 * @param array $fields    Processed fields array.
	 * @param array $entry     Form entry data.
	 * @param array $form_data Form settings.
	 * @param int   $entry_id  Saved entry ID.
	 */
	public function on_form_submitted( array $fields, array $entry, array $form_data, int $entry_id ): void {
		$form_id = (string) ( $form_data['id'] ?? '' );
		if ( $form_id === '' ) return;

		$flat = [];
		foreach ( $fields as $field ) {
			$key   = sanitize_key( $field['name'] ?? $field['label'] ?? $field['id'] ?? '' );
			$value = $field['value'] ?? '';
			if ( $key ) {
				$flat[ $key ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
			}
		}

		$context = [
			'form_name'   => $form_data['settings']['form_title'] ?? "Form {$form_id}",
			'referrer'    => isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
			'current_url' => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '',
		];

		$this->manager->handle_submission( 'wpforms', $form_id, $flat, $context );
	}
}
