<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMEC_ElementorForms {

	private SMEC_FormManager $manager;

	public function __construct( SMEC_FormManager $manager ) {
		$this->manager = $manager;
	}

	public function register(): void {
		// Elementor Pro hook after form is sent successfully
		add_action( 'elementor_pro/forms/new_record', [ $this, 'on_form_submitted' ], 10, 2 );
	}

	/**
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $handler
	 */
	public function on_form_submitted( $record, $handler ): void {
		if ( ! method_exists( $record, 'get_form_settings' ) ) return;

		$form_name = $record->get_form_settings( 'form_name' );
		if ( empty( $form_name ) ) return;

		// Get all submitted fields as key → value
		$raw    = $record->get( 'fields' );
		$fields = [];
		foreach ( $raw as $field ) {
			$id    = $field['id']    ?? $field['alias'] ?? '';
			$value = $field['value'] ?? '';
			if ( $id !== '' ) {
				$fields[ $id ] = $value;
			}
		}

		$context = [
			'form_name'   => $form_name,
			'referrer'    => isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
			'current_url' => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '',
		];

		$this->manager->handle_submission( 'elementor', $form_name, $fields, $context );
	}
}
