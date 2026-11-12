<?php
/**
 * WooCommerce thank-you hooks for HamiShop's own completion screens.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'HSW_Thankyou' ) ) {
	/**
	 * Single place that fires `woocommerce_thankyou` and its gateway variant.
	 *
	 * HamiShop renders its own confirmation card on pages where WooCommerce's
	 * `checkout/thankyou.php` never runs, so analytics, marketing and gateway
	 * plugins would otherwise never hear about the order.
	 *
	 * The guard lives here (not in each caller) and is keyed by order, so the
	 * complete page, the checkout card and the pay page can all ask for it and
	 * the pair still fires exactly once per request.
	 */
	final class HSW_Thankyou {

		/**
		 * Orders whose hooks already fired in this request.
		 *
		 * @var array<int,bool>
		 */
		private static $fired = array();

		/**
		 * Fire WooCommerce's thank-you hooks once for this order.
		 *
		 * @param WC_Order|int $order Order object or order id.
		 * @return bool True when this call fired the hooks.
		 */
		public static function fire( $order ) {
			if ( ! $order instanceof WC_Order && function_exists( 'wc_get_order' ) ) {
				$maybe = wc_get_order( (int) $order );
				if ( $maybe instanceof WC_Order ) {
					$order = $maybe;
				}
			}

			$order_id = $order instanceof WC_Order ? (int) $order->get_id() : (int) $order;
			if ( $order_id <= 0 || isset( self::$fired[ $order_id ] ) ) {
				return false;
			}

			/* Detect what WooCommerce's own thank-you template is about to do
			   in this request (see core_owns_thankyou()). Observe() below also
			   marks an order when any other component has already fired the
			   public hook, so a late render never repeats it. */
			if ( self::core_owns_thankyou() ) {
				return false;
			}

			self::$fired[ $order_id ] = true;
			$method = $order instanceof WC_Order ? (string) $order->get_payment_method() : '';

			do_action( 'woocommerce_before_thankyou', $order_id );
			if ( '' !== $method ) {
				do_action( 'woocommerce_thankyou_' . $method, $order_id );
			}
			do_action( 'woocommerce_thankyou', $order_id );

			return true;
		}

		/**
		 * Remember an order whose public hook someone else already fired.
		 *
		 * Hooked to `woocommerce_thankyou` at priority 1. Without it the guard
		 * could only see its own calls, and a component that rendered the
		 * thank-you block before HamiShop did would be counted twice.
		 *
		 * @param int $order_id Order.
		 * @return void
		 */
		public static function observe( $order_id ) {
			$order_id = (int) $order_id;
			if ( $order_id > 0 ) {
				self::$fired[ $order_id ] = true;
			}
		}

		/**
		 * Has this order's thank-you block already been fired?
		 *
		 * @param int $order_id Order.
		 * @return bool
		 */
		public static function fired( $order_id ) {
			return isset( self::$fired[ (int) $order_id ] );
		}

		/**
		 * Forget every fired order. Tests only.
		 *
		 * @return void
		 */
		public static function reset() {
			self::$fired = array();
		}

		/**
		 * Will WooCommerce render its own thank-you template on this request?
		 *
		 * On the native `order-received` endpoint the checkout shortcode (or the
		 * Checkout block) prints `thankyou.php`, which fires the hooks itself.
		 * Firing them here as well would count every purchase twice.
		 *
		 * @return bool
		 */
		private static function core_owns_thankyou() {
			if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-received' ) ) {
				return false;
			}
			if ( ! function_exists( 'wc_get_page_id' ) ) {
				return false;
			}
			$page_id = (int) wc_get_page_id( 'checkout' );
			if ( $page_id <= 0 ) {
				return false;
			}
			$page = get_post( $page_id );
			if ( ! $page instanceof WP_Post ) {
				return false;
			}

			return has_shortcode( (string) $page->post_content, 'woocommerce_checkout' )
				|| has_block( 'woocommerce/checkout', $page );
		}
	}
}

/* Mark orders whose public thank-you hook another component already fired. */
if ( function_exists( 'add_action' ) ) {
	add_action( 'woocommerce_thankyou', array( 'HSW_Thankyou', 'observe' ), 1, 1 );
}
