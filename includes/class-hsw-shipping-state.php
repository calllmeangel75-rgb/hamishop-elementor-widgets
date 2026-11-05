<?php
/**
 * One answer to "what is the shipping row?".
 *
 * This used to be answered in four places with four slightly different
 * questions: the cart base's `shipping_row()` for the cart and checkout
 * summaries, the checkout base's `chosen_shipping_cost()` for the pay summary,
 * and the REST controller's `totals_payload()` for the live patch after a rate
 * change. Three of them treated a zero total as «رایگان», so a destination with
 * no rate and a cart that needs no shipping both read as free delivery — and
 * the live patch could overwrite a correct label with that one.
 *
 * Five states, because "zero" has five meanings:
 *
 *   free         a rate is chosen and it really costs nothing
 *   amount       a rate is chosen and has a price
 *   pending      shipping is needed but nothing is chosen yet
 *   none         the cart/order does not need shipping (digital only)
 *   unavailable  no rate exists for this destination
 *
 * Loaded unconditionally: the REST patch runs on requests where Elementor never
 * registers a widget, so it cannot live in a widget base class.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

final class HSW_Shipping_State {

	const FREE        = 'free';
	const AMOUNT      = 'amount';
	const PENDING     = 'pending';
	const NONE        = 'none';
	const UNAVAILABLE = 'unavailable';

	/**
	 * State for the live cart.
	 *
	 * @return array{0:string,1:float}
	 */
	public static function for_cart() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return array( self::PENDING, 0.0 );
		}

		if ( ! WC()->cart->needs_shipping() ) {
			return array( self::NONE, 0.0 );
		}

		$total = (float) WC()->cart->get_shipping_total();
		if ( $total > 0 ) {
			return array( self::AMOUNT, $total );
		}

		/* Zero so far: find out *why* before calling it free. */
		$packages = WC()->shipping() ? WC()->shipping()->get_packages() : array();
		if ( empty( $packages ) ) {
			return array( self::PENDING, 0.0 );
		}
		if ( empty( $packages[0]['rates'] ) ) {
			return array( self::UNAVAILABLE, 0.0 );
		}

		$chosen = WC()->session ? (array) WC()->session->get( 'chosen_shipping_methods' ) : array();
		if ( empty( $chosen[0] ) || ! isset( $packages[0]['rates'][ $chosen[0] ] ) ) {
			return array( self::PENDING, 0.0 );
		}

		return array( self::FREE, 0.0 );
	}

	/**
	 * State for an order being paid (pay step / retry).
	 *
	 * A zero shipping total on an order means "no shipping line", not "free
	 * shipping". A digital order has no line because nothing ships; an order
	 * with a free-shipping rate has no line because the shop covered it. The
	 * items decide which one it is.
	 *
	 * @param WC_Order $order Order.
	 * @return array{0:string,1:float}
	 */
	public static function for_order( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return array( self::PENDING, 0.0 );
		}

		$ship = (float) $order->get_shipping_total();
		if ( $ship > 0 ) {
			return array( self::AMOUNT, $ship );
		}

		foreach ( $order->get_items() as $item ) {
			$product = method_exists( $item, 'get_product' ) ? $item->get_product() : null;
			if ( $product instanceof WC_Product && $product->needs_shipping() ) {
				return array( self::FREE, 0.0 );
			}
		}

		return array( self::NONE, 0.0 );
	}

	/**
	 * Customer-facing label for a state that has no amount.
	 *
	 * @param string $state     One of the constants.
	 * @param string $free_text Editable «رایگان» text ('' = default).
	 * @return string
	 */
	public static function label( $state, $free_text = '' ) {
		switch ( $state ) {
			case self::FREE:
				return '' !== trim( (string) $free_text ) ? (string) $free_text : __( 'رایگان', 'hamishop-elementor-widgets' );
			case self::PENDING:
				return __( 'در انتظار انتخاب روش ارسال', 'hamishop-elementor-widgets' );
			case self::NONE:
				return __( 'نیازی به ارسال ندارد', 'hamishop-elementor-widgets' );
			case self::UNAVAILABLE:
				return __( 'روش ارسال در دسترس نیست', 'hamishop-elementor-widgets' );
		}

		return '';
	}
}
