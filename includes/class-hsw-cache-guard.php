<?php
/**
 * Keep customer-specific screens out of page caches.
 *
 * The payment, checkout, cart and account widgets render data from the
 * WooCommerce session — cart totals, addresses, the customer's name. Page-cache
 * plugins (WP Rocket, LiteSpeed, W3TC, Cloudflare APO) only auto-exclude the
 * pages WooCommerce registers as its own (cart / checkout / my-account). A
 * custom `/payment/` page built from these widgets is *not* on that list, so the
 * rendered HTML of one visitor's session can be served to the next.
 *
 * `nocache_headers()` alone is not enough: those plugins look for the
 * `DONOTCACHEPAGE` constant, and they read it before any output — so it has to
 * be set on `template_redirect`, not while a widget renders.
 *
 * The check is one postmeta read on singular requests and nothing at all on
 * archives, so it costs nothing measurable.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Cache_Guard {

	/**
	 * Widget families whose output depends on the visitor.
	 *
	 * Matched against the Elementor data / post content of the current page.
	 */
	const PRIVATE_WIDGETS = array(
		'hsw-pay-',
		'hsw-checkout-',
		'hsw-complete-',
		'hsw-cart-',
		'hsw-account',
	);

	/** @var HSW_Cache_Guard|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_disable_cache' ), 1 );
	}

	/**
	 * Mark the current request as uncacheable when it shows private data.
	 */
	public function maybe_disable_cache() {
		if ( ! $this->is_private_view() ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
			define( 'DONOTCACHEOBJECT', true );
		}
		if ( ! defined( 'DONOTCACHEDB' ) ) {
			define( 'DONOTCACHEDB', true );
		}

		/* CDN / proxy layer. */
		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'X-HSW-Private: 1' );
		}

		/* LiteSpeed reads its own constant; harmless when the plugin is absent. */
		do_action( 'litespeed_control_set_nocache', 'HamiShop: customer-specific screen' );
	}

	/**
	 * Does this request render anything customer-specific?
	 *
	 * @return bool
	 */
	private function is_private_view() {
		/* WooCommerce's own pages — excluded by most caches already, but a
		   defensive check costs nothing and covers plugins that do not. */
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			return true;
		}

		/* Order received / thank-you, including the custom complete route. */
		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return true;
		}
		if ( get_query_var( 'order-received' ) ) {
			return true;
		}

		if ( ! is_singular() ) {
			return false;
		}

		$post_id = (int) get_queried_object_id();
		if ( ! $post_id ) {
			return false;
		}

		$haystack = (string) get_post_meta( $post_id, '_elementor_data', true );
		if ( '' === $haystack ) {
			$haystack = (string) get_post_field( 'post_content', $post_id );
		}
		if ( '' === $haystack ) {
			return false;
		}

		foreach ( self::PRIVATE_WIDGETS as $needle ) {
			if ( false !== strpos( $haystack, $needle ) ) {
				return true;
			}
		}

		return false;
	}
}
