<?php
/**
 * Digital-only carts (downloadable / virtual products).
 *
 * A cart that needs no shipping has no delivery address, no shipping method and
 * no delivery slot. WooCommerce still marks every billing address field as
 * required, so a shop selling a PDF asked the customer for a postcode. This
 * class relaxes the field set and tells the checkout widgets to drop the
 * shipping/schedule sections.
 *
 * The fields are read through `WC_Checkout::get_checkout_fields()`, so a shop
 * that manages its own fields (WooCommerce Persian and friends) keeps the last
 * word: we only relax what is still there.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Checkout_Digital {

	/**
	 * Billing fields that describe a delivery, not a customer.
	 *
	 * @var string[]
	 */
	const ADDRESS_FIELDS = array(
		'billing_company',
		'billing_country',
		'billing_address_1',
		'billing_address_2',
		'billing_city',
		'billing_state',
		'billing_postcode',
	);

	public function __construct() {
		add_filter( 'woocommerce_checkout_fields', array( $this, 'relax_fields' ), 20 );
	}

	/**
	 * Is the current cart made only of products that need no shipping?
	 *
	 * An empty cart returns false on purpose: `needs_shipping()` is also false
	 * there, and a checkout with nothing in it must keep its normal shape.
	 *
	 * @return bool
	 */
	public static function is_digital() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		if ( WC()->cart->is_empty() ) {
			return false;
		}
		return ! WC()->cart->needs_shipping();
	}

	/**
	 * Drop the address requirements for a digital cart.
	 *
	 * The fields are removed, not just made optional: leaving them on the form
	 * is exactly the problem — a customer buying a download should not read
	 * «کد پستی».
	 *
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	public function relax_fields( $fields ) {
		if ( ! self::is_digital() || ! is_array( $fields ) ) {
			return $fields;
		}

		foreach ( self::ADDRESS_FIELDS as $key ) {
			unset( $fields['billing'][ $key ] );
		}
		unset( $fields['shipping'] );

		return $fields;
	}

	/**
	 * Phone the customer already proved during OTP login.
	 *
	 * `_hsw_auth_phone` is written only after a code sent to that number was
	 * entered correctly (see HSW_Account_Auth::user_phone()), so it is safer
	 * than a typed `billing_phone` and saves the customer a second entry.
	 *
	 * @return string Empty when the customer did not log in by phone.
	 */
	public static function verified_phone() {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$phone = (string) get_user_meta( get_current_user_id(), '_hsw_auth_phone', true );
		$phone = preg_replace( '/[^0-9+]/', '', $phone );

		return (string) $phone;
	}
}
