<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMEC_FluentForms {

	private SMEC_FormManager $manager;

	public function __construct( SMEC_FormManager $manager ) {
		$this->manager = $manager;
	}

	public function register(): void {
		add_action( 'fluentform/submission_inserted', [ $this, 'on_form_submitted' ], 10, 3 );
	}

	/**
	 * @param int    $entry_id
	 * @param array  $form_data
	 * @param object $form
	 */
	public function on_form_submitted( int $entry_id, array $form_data, object $form ): void {
		$form_id = (string) ( $form->id ?? '' );
		if ( $form_id === '' ) return;

		$fields = [];
		foreach ( $form_data as $key => $value ) {
			$fields[ $key ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
		}

		$context = [
			'form_name'   => $form->title ?? "Form {$form_id}",
			'referrer'    => isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
			'current_url' => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '',
		];

		$this->manager->handle_submission( 'fluent', $form_id, $fields, $context );
	}
}
