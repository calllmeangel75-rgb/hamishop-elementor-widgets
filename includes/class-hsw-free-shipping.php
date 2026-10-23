<?php
/**
 * Free shipping above a threshold the shop owner sets.
 *
 * WooCommerce can do this with a "free shipping" zone plus a minimum-amount
 * coupon, but that setup lives three screens deep in shipping zones and only
 * works for shops that configured their zones that way. A store that already
 * charges a flat rate everywhere just wants one number: "above X, shipping is
 * free". This is that number.
 *
 * The filter runs after WooCommerce has priced the rates (priority 20) and
 * zeroes the cost of every rate in the package, so the customer sees
 * "ارسال رایگان" and the order total really drops by the shipping amount —
 * nothing is hidden in the tax rows.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Free_Shipping {

	const OPTION = 'hsw_free_shipping_min';

	public function __construct() {
		add_filter( 'woocommerce_package_rates', array( $this, 'apply' ), 20, 2 );
	}

	/**
	 * Threshold in store currency (0 = off).
	 *
	 * @return float
	 */
	public static function threshold() {
		return max( 0, (float) get_option( self::OPTION, 0 ) );
	}

	/**
	 * Free-shipping minimum configured in WooCommerce's own shipping zones.
	 *
	 * Stores often have both: a zone method («ارسال رایگان از ۵۰۰٬۰۰۰ تومان»)
	 * and this setting. Reading the zone value lets the settings screen say
	 * where the number comes from instead of looking like a conflict.
	 *
	 * @return float 0 when no free-shipping method has a minimum.
	 */
	public static function wc_threshold() {
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return 0.0;
		}

		$min = 0.0;
		foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
			foreach ( (array) $zone['shipping_methods'] as $method ) {
				if ( ! $method instanceof WC_Shipping_Method || 'free_shipping' !== $method->id ) {
					continue;
				}
				$amount = (float) $method->get_option( 'min_amount' );
				if ( $amount > $min ) {
					$min = $amount;
				}
			}
		}

		return $min;
	}

	/**
	 * Cart subtotal the threshold is compared against.
	 *
	 * `get_displayed_subtotal()` follows the shop's "prices entered with tax"
	 * setting, so the number the customer sees is the number we compare.
	 *
	 * @return float
	 */
	protected function subtotal() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0.0;
		}

		return (float) WC()->cart->get_displayed_subtotal();
	}

	/**
	 * Zero the shipping cost once the threshold is reached.
	 *
	 * @param array $rates   Shipping rates for the package.
	 * @param array $package Package (unused, kept for the filter signature).
	 * @return array
	 */
	public function apply( $rates, $package = array() ) {
		$min = self::threshold();

		if ( $min <= 0 || empty( $rates ) ) {
			return $rates;
		}

		if ( $this->subtotal() + 0.001 < $min ) {
			return $rates;
		}

		foreach ( $rates as $rate ) {
			$rate->cost            = 0;
			$rate->taxes           = array();
			$rate->method_id       = 'free_shipping';
			$rate->label           = __( 'ارسال رایگان', 'hamishop-elementor-widgets' );
			$rate->meta_data['hsw_free_shipping'] = true;
		}

		return $rates;
	}
}
