<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMEC_NativeRegistration {

	private SMEC_FormManager $manager;

	public function __construct( SMEC_FormManager $manager ) {
		$this->manager = $manager;
	}

	public function register(): void {
		add_action( 'user_register', [ $this, 'on_user_registered' ], 10, 1 );
		add_action( 'woocommerce_registration_redirect', [ $this, 'on_woo_registration' ], 10, 1 );
	}

	public function on_user_registered( int $user_id ): void {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) return;

		$fields = [
			'user_email'    => $user->user_email,
			'user_login'    => $user->user_login,
			'first_name'    => $user->first_name,
			'last_name'     => $user->last_name,
			'display_name'  => $user->display_name,
		];

		$context = [
			'form_name'   => 'wp_registration',
			'current_url' => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '',
		];

		$this->manager->handle_submission( 'registration', 'wp_registration', $fields, $context );
	}

	public function on_woo_registration( string $redirect ): string {
		// WooCommerce registration fires user_register too, so we'll use 'woocommerce_registration' as a separate form_id
		// if admins want to handle it separately
		return $redirect;
	}
}
