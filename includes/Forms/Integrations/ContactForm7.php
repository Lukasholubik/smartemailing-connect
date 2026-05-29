<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMEC_ContactForm7 {

	private SMEC_FormManager $manager;

	public function __construct( SMEC_FormManager $manager ) {
		$this->manager = $manager;
	}

	public function register(): void {
		add_action( 'wpcf7_mail_sent', [ $this, 'on_form_submitted' ], 10, 1 );
	}

	/**
	 * @param \WPCF7_ContactForm $contact_form
	 */
	public function on_form_submitted( $contact_form ): void {
		if ( ! method_exists( $contact_form, 'id' ) ) return;

		$form_id   = (string) $contact_form->id();
		$form_name = $contact_form->name() ?: $form_id;

		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) return;

		$posted = $submission->get_posted_data();
		$fields = [];
		foreach ( $posted as $key => $value ) {
			// Skip CF7 meta fields
			if ( str_starts_with( $key, '_' ) ) continue;
			$fields[ $key ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
		}

		$context = [
			'form_name'   => $form_name,
			'referrer'    => isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
			'current_url' => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '',
		];

		// Match by form ID (numeric) or form name
		$this->manager->handle_submission( 'cf7', $form_id, $fields, $context );
	}
}
