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

	const OPTION = 'hsw_digital_checkout';

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
		add_filter( 'woocommerce_billing_fields', array( $this, 'relax_billing_fields' ), 20 );
		add_filter( 'woocommerce_product_needs_shipping', array( $this, 'filter_product_needs_shipping' ), 20, 2 );
		add_filter( 'woocommerce_cart_needs_shipping', array( $this, 'filter_cart_needs_shipping' ), 20 );
	}

	/**
	 * Is the feature switched on? (HamiShop → ویجت‌ها → تنظیمات فروشگاه)
	 *
	 * @return bool
	 */
	public static function enabled() {
		return 'yes' === get_option( self::OPTION, 'yes' );
	}

	/**
	 * Is the current cart made only of products that need no shipping?
	 *
	 * An empty cart returns false on purpose: `needs_shipping()` is also false
	 * there, and a checkout with nothing in it must keep its normal shape. A
	 * mixed cart (one physical item among downloads) needs shipping too, so it
	 * keeps every field.
	 *
	 * If every product in the cart is virtual or downloadable, it is treated
	 * as a digital cart even if the merchant only checked "Downloadable"
	 * without checking "Virtual" in WooCommerce.
	 *
	 * @return bool
	 */
	public static function is_digital() {
		if ( ! self::enabled() ) {
			return false;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		if ( WC()->cart->is_empty() ) {
			return false;
		}
		if ( ! WC()->cart->needs_shipping() ) {
			return true;
		}
		return self::cart_items_all_digital();
	}

	/**
	 * Are all items currently in the cart virtual or downloadable?
	 *
	 * In standard WooCommerce, products marked only as "Downloadable" still
	 * report `needs_shipping() === true` unless also marked "Virtual". This
	 * helper ensures downloadable items never force physical delivery fields.
	 *
	 * @return bool
	 */
	public static function cart_items_all_digital() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return false;
		}
		$cart = WC()->cart->get_cart();
		if ( empty( $cart ) ) {
			return false;
		}
		foreach ( $cart as $item ) {
			if ( empty( $item['data'] ) || ! is_object( $item['data'] ) ) {
				return false;
			}
			$product = $item['data'];
			if ( ! is_a( $product, 'WC_Product' ) ) {
				return false;
			}
			if ( ! ( $product->is_virtual() || $product->is_downloadable() ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Inform WooCommerce whether a product needs shipping.
	 *
	 * Downloadable items without the "Virtual" checkbox in WooCommerce
	 * normally default to needing shipping. If digital checkout is enabled,
	 * any downloadable product needs no shipping.
	 *
	 * @param bool       $needs_shipping Current value.
	 * @param WC_Product $product        Product instance.
	 * @return bool
	 */
	public function filter_product_needs_shipping( $needs_shipping, $product ) {
		if ( self::enabled() && $product && is_a( $product, 'WC_Product' ) ) {
			if ( $product->is_virtual() || $product->is_downloadable() ) {
				return false;
			}
		}
		return $needs_shipping;
	}

	/**
	 * Inform WooCommerce whether the cart needs shipping.
	 *
	 * @param bool $needs_shipping Current value.
	 * @return bool
	 */
	public function filter_cart_needs_shipping( $needs_shipping ) {
		if ( self::is_digital() ) {
			return false;
		}
		return $needs_shipping;
	}

	/**
	 * Drop address fields from billing fields for digital carts.
	 *
	 * @param array $fields Billing fields.
	 * @return array
	 */
	public function relax_billing_fields( $fields ) {
		if ( ! self::is_digital() || ! is_array( $fields ) ) {
			return $fields;
		}

		foreach ( self::ADDRESS_FIELDS as $key ) {
			unset( $fields[ $key ] );
			$bare = preg_replace( '/^billing_/', '', $key );
			if ( $bare !== $key ) {
				unset( $fields[ $bare ] );
			}
		}

		return $fields;
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
			$bare = preg_replace( '/^billing_/', '', $key );
			if ( $bare !== $key ) {
				unset( $fields['billing'][ $bare ] );
			}
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
