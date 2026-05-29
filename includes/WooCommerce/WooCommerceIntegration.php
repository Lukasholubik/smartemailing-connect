<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMEC_WooCommerceIntegration {

	private SMEC_Settings   $settings;
	private SMEC_ApiService $api;
	private SMEC_Logger     $logger;

	public function __construct( SMEC_Settings $settings, SMEC_ApiService $api, SMEC_Logger $logger ) {
		$this->settings = $settings;
		$this->api      = $api;
		$this->logger   = $logger;
	}

	public function register(): void {
		$cfg = $this->settings->get_woocommerce();
		if ( empty( $cfg['enabled'] ) ) return;

		// Opt-in checkbox on checkout
		if ( ! empty( $cfg['require_optin'] ) ) {
			add_action( 'woocommerce_checkout_after_terms_and_conditions', [ $this, 'add_optin_checkbox' ] );
		}

		// Import on order status change
		$statuses = (array) ( $cfg['import_on_status'] ?? [ 'completed' ] );
		foreach ( $statuses as $status ) {
			$hook = 'woocommerce_order_status_' . $status;
			add_action( $hook, [ $this, 'on_order_status' ], 10, 1 );
		}
	}

	public function add_optin_checkbox(): void {
		$cfg   = $this->settings->get_woocommerce();
		$label = esc_html( $cfg['optin_label'] ?? 'Přihlásit se k odběru newsletteru' );
		echo '<p class="form-row smec-woo-optin">';
		echo '<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">';
		echo '<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="smec_newsletter_optin" value="1" />';
		echo ' <span>' . $label . '</span>';
		echo '</label>';
		echo '</p>';
	}

	public function on_order_status( int $order_id ): void {
		$cfg = $this->settings->get_woocommerce();

		// HPOS compatibility
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;

		// Check opt-in if required
		if ( ! empty( $cfg['require_optin'] ) ) {
			$optin = $order->get_meta( '_smec_newsletter_optin' );
			if ( empty( $optin ) ) return;
		}

		$billing_email = $order->get_billing_email();
		if ( ! is_email( $billing_email ) ) return;

		$contact = [ 'emailaddress' => $billing_email ];

		// System field mapping
		$billing_fields = [
			'first_name' => $order->get_billing_first_name(),
			'last_name'  => $order->get_billing_last_name(),
			'phone'      => $order->get_billing_phone(),
			'company'    => $order->get_billing_company(),
			'address_1'  => $order->get_billing_address_1(),
			'city'       => $order->get_billing_city(),
			'postcode'   => $order->get_billing_postcode(),
			'country'    => $order->get_billing_country(),
		];

		$field_map = $cfg['field_mapping'] ?? [];
		if ( isset( $field_map['name']['value'] ) ) {
			$contact['name'] = sanitize_text_field( $billing_fields[ $field_map['name']['value'] ] ?? $order->get_billing_first_name() );
		} else {
			$contact['name'] = sanitize_text_field( $order->get_billing_first_name() );
		}
		if ( isset( $field_map['surname']['value'] ) ) {
			$contact['surname'] = sanitize_text_field( $billing_fields[ $field_map['surname']['value'] ] ?? $order->get_billing_last_name() );
		} else {
			$contact['surname'] = sanitize_text_field( $order->get_billing_last_name() );
		}
		if ( ! empty( $order->get_billing_phone() ) ) {
			$contact['cellphone'] = sanitize_text_field( $order->get_billing_phone() );
		}

		// Contact list
		if ( ! empty( $cfg['list_id'] ) ) {
			$status  = in_array( $cfg['status'] ?? '', [ 'confirmed', 'unconfirmed' ], true ) ? $cfg['status'] : 'confirmed';
			$contact['contactlists'] = [ [ 'id' => (int) $cfg['list_id'], 'status' => $status ] ];
		}

		// Custom field mapping
		$custom_fields = [];
		foreach ( (array) ( $cfg['custom_field_mapping'] ?? [] ) as $row ) {
			if ( empty( $row['field_id'] ) ) continue;
			$value = '';
			if ( ( $row['source'] ?? '' ) === 'static' ) {
				$value = $row['value'] ?? '';
			} elseif ( ( $row['source'] ?? '' ) === 'form_field' ) {
				$value = (string) ( $billing_fields[ $row['value'] ] ?? '' );
			} elseif ( ( $row['source'] ?? '' ) === 'order_field' ) {
				$value = $this->get_order_field( $order, $row['value'] ?? '' );
			}
			if ( $value !== '' ) {
				$custom_fields[] = [ 'id' => (int) $row['field_id'], 'value' => $value ];
			}
		}
		if ( $custom_fields ) {
			$contact['customfields'] = $custom_fields;
		}

		// Tags
		$tags = array_map( 'sanitize_text_field', (array) ( $cfg['tags'] ?? [] ) );
		if ( $tags ) {
			$contact['tags'] = $tags;
		}

		$result = $this->api->import_contacts( [ $contact ] );
		if ( $result['success'] ) {
			$this->logger->info( 'WooCommerce customer imported', [ 'order_id' => $order_id ], 'woocommerce' );
		} else {
			$this->logger->error( 'WooCommerce import failed', [ 'order_id' => $order_id, 'error' => $result['message'] ?? '' ], 'woocommerce' );
		}
	}

	private function get_order_field( \WC_Order $order, string $field ): string {
		return match ( $field ) {
			'order_total'  => (string) $order->get_total(),
			'order_number' => (string) $order->get_order_number(),
			'order_date'   => $order->get_date_created()?->format( 'Y-m-d' ) ?? '',
			'order_status' => $order->get_status(),
			'payment_method' => $order->get_payment_method_title(),
			default        => '',
		};
	}

	// Save opt-in checkbox when order is placed
	public static function save_optin_to_order( int $order_id ): void {
		if ( ! empty( $_POST['smec_newsletter_optin'] ) ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$order->update_meta_data( '_smec_newsletter_optin', 1 );
				$order->save();
			}
		}
	}
}

// Save opt-in from checkout – hooked globally to work even if class is not fully loaded yet
add_action( 'woocommerce_checkout_create_order', static function( $order ) {
	if ( ! empty( $_POST['smec_newsletter_optin'] ) ) {
		$order->update_meta_data( '_smec_newsletter_optin', 1 );
	}
}, 10, 1 );
