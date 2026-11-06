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

	/**
	 * Master switch. Off by default: most stores already configured free
	 * shipping inside WooCommerce's own zones, and this filter rewrites the
	 * final rates (it would override weight / shipping-class conditions).
	 */
	const ENABLED = 'hsw_free_shipping_on';

	public function __construct() {
		/*
		 * Registered before the switch check: turning the feature off has to
		 * invalidate the rates WooCommerce cached in every session, and that is
		 * exactly the case where is_enabled() is false.
		 */
		add_action( 'update_option_' . self::ENABLED, array( __CLASS__, 'flush_cached_rates' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'flush_cached_rates' ) );

		if ( ! self::is_enabled() ) {
			return;
		}

		add_filter( 'woocommerce_package_rates', array( $this, 'apply' ), 20, 2 );
	}

	/**
	 * Drop the shipping rates WooCommerce cached in every session.
	 *
	 * `WC_Shipping::calculate_shipping_for_package()` keys its session cache on
	 * `WC_Cache_Helper::get_transient_version( 'shipping' )`, so bumping that
	 * version invalidates every visitor's cached rates at once. Without it a
	 * customer who already had a free rate kept it after the switch went off,
	 * until something else changed their package.
	 */
	public static function flush_cached_rates() {
		if ( class_exists( 'WC_Cache_Helper' ) ) {
			WC_Cache_Helper::get_transient_version( 'shipping', true );
		}
	}

	/**
	 * Whether the plugin may touch the shipping rates at all.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$on = 'yes' === get_option( self::ENABLED, 'no' );

		return (bool) apply_filters( 'hsw_free_shipping_enabled', $on );
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
	 * Threshold a customer-facing promise may quote, or 0 when it must not.
	 *
	 * `threshold()` is the number the owner typed and it stays on the settings
	 * screen after the switch is turned off — re-enabling should not require
	 * typing it again. But the cart banner and the checkout tip read that
	 * number directly, so with the switch off they promised free shipping this
	 * plugin was never going to apply. A promise needs both halves: the switch
	 * and the amount.
	 *
	 * @return float
	 */
	public static function promise_threshold() {
		return self::is_enabled() ? self::threshold() : 0.0;
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
			$rate->cost      = 0;
			$rate->taxes     = array();
			$rate->method_id = 'free_shipping';
			$rate->label     = __( 'ارسال رایگان', 'hamishop-elementor-widgets' );

			/*
			 * `$rate->meta_data[...] = true` did nothing: `meta_data` is
			 * protected and `__get()` returns `get_meta_data()` by value, so PHP
			 * wrote to a temporary array and dropped it. The rate still became
			 * free, but nothing downstream could see the flag.
			 */
			$rate->add_meta_data( 'hsw_free_shipping', true );
		}

		return $rates;
	}
}
