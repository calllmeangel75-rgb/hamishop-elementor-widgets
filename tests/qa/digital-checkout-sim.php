<?php
/**
 * Regression guard for the `is_digital()` recursion.
 *
 * `is_digital()` asks `WC()->cart->needs_shipping()`, which applies the
 * `woocommerce_cart_needs_shipping` filter — and that filter is this class's own
 * `filter_cart_needs_shipping()`, which called `is_digital()` again. On the live
 * shop that was an unbounded recursion: the request died with a stack overflow
 * (no PHP fatal, so nothing in error_log) and LiteSpeed answered 503.
 *
 * It only fired once the cart had an item in it, which is why an empty cart —
 * every crawler, every page view — looked perfectly fine.
 *
 * Run: php tests/qa/digital-checkout-sim.php
 */

error_reporting( E_ALL );

define( 'ABSPATH', __DIR__ );

$GLOBALS['hsw_filters']   = array();
$GLOBALS['hsw_calls']     = 0;
$GLOBALS['hsw_fail']      = 0;

function add_filter( $hook, $cb, $priority = 10, $args = 1 ) {
	$GLOBALS['hsw_filters'][ $hook ][] = $cb;
	return true;
}

function apply_filters( $hook, $value, ...$rest ) {
	foreach ( $GLOBALS['hsw_filters'][ $hook ] ?? array() as $cb ) {
		$value = $cb( $value, ...$rest );
	}
	return $value;
}

function get_option( $name, $default = false ) {
	return 'hsw_checkout_digital' === $name ? 'yes' : $default;
}

function check( $label, $ok, $detail = '' ) {
	$ok ? null : $GLOBALS['hsw_fail']++;
	echo ( $ok ? '[ok]   ' : '[FAIL] ' ) . $label . ( '' !== $detail ? "  ({$detail})" : '' ) . "\n";
}

/* ---------------------------------------------------------------- stubs */

/* `cart_items_all_digital()` checks `is_a( $product, 'WC_Product' )`, so the stub
   has to be one — otherwise every item is rejected and the test passes for the
   wrong reason. */
if ( ! class_exists( 'WC_Product' ) ) {
	class WC_Product {
	}
}

class HSW_QA_Product extends WC_Product {
	private $virtual;
	private $downloadable;

	public function __construct( $virtual, $downloadable ) {
		$this->virtual      = $virtual;
		$this->downloadable = $downloadable;
	}

	public function is_virtual() {
		return $this->virtual;
	}

	public function is_downloadable() {
		return $this->downloadable;
	}
}

class HSW_QA_Cart {
	public $items = array();
	public $raw_needs_shipping = true;
	/** Mirrors wc_shipping_enabled(): false when the shop has no shipping. */
	public $shipping_enabled = true;

	public function is_empty() {
		return empty( $this->items );
	}

	public function get_cart() {
		return $this->items;
	}

	/**
	 * Mirrors WC_Cart::needs_shipping(): the cached value goes through the
	 * filter on every call, which is what made the recursion possible.
	 */
	public function needs_shipping() {
		$GLOBALS['hsw_calls']++;
		/* WooCommerce returns false before any filter when shipping is off. */
		if ( ! $this->shipping_enabled ) {
			return false;
		}
		return apply_filters( 'woocommerce_cart_needs_shipping', $this->raw_needs_shipping );
	}
}

$GLOBALS['hsw_cart'] = new HSW_QA_Cart();

function WC() {
	return (object) array( 'cart' => $GLOBALS['hsw_cart'] );
}

require_once __DIR__ . '/../../includes/class-hsw-checkout-digital.php';

/* ---------------------------------------------------------------- tests */

$digital = new HSW_Checkout_Digital();

$GLOBALS['hsw_cart']->items = array(
	array( 'data' => new HSW_QA_Product( false, false ) ), // physical
	array( 'data' => new HSW_QA_Product( false, true ) ),  // downloadable
);

$GLOBALS['hsw_calls'] = 0;
$mixed = HSW_Checkout_Digital::is_digital();

check( 'mixed cart is not digital', false === $mixed );
check( 'needs_shipping was asked once, not in a loop', $GLOBALS['hsw_calls'] <= 3, $GLOBALS['hsw_calls'] . ' calls' );

/* All-digital cart: downloadable without "Virtual", the case the filter exists
   for. needs_shipping() is false there, so is_digital() must say yes. */
$GLOBALS['hsw_cart']->items            = array( array( 'data' => new HSW_QA_Product( false, true ) ) );
$GLOBALS['hsw_cart']->raw_needs_shipping = false;
$GLOBALS['hsw_calls']                  = 0;

check( 'download-only cart is digital', true === HSW_Checkout_Digital::is_digital() );
check( 'still no recursion', $GLOBALS['hsw_calls'] <= 3, $GLOBALS['hsw_calls'] . ' calls' );

/*
 * A shop with shipping switched off must not call every cart "digital": the
 * address form is trimmed and «تحویل دیجیتال سفارش» appears on a cart that has
 * a physical item in it.
 */
$GLOBALS['hsw_cart']->items            = array(
	array( 'data' => new HSW_QA_Product( false, false ) ),
	array( 'data' => new HSW_QA_Product( false, true ) ),
);
$GLOBALS['hsw_cart']->shipping_enabled = false;

check( 'mixed cart with shipping disabled is still not digital', false === HSW_Checkout_Digital::is_digital() );

$GLOBALS['hsw_cart']->shipping_enabled = true;

/* Empty cart: never digital, and the guard must not leak between calls. */
$GLOBALS['hsw_cart']->items = array();
check( 'empty cart is not digital', false === HSW_Checkout_Digital::is_digital() );

echo "\n=== " . ( $GLOBALS['hsw_fail'] ? $GLOBALS['hsw_fail'] . ' check(s) failed' : 'all checks passed' ) . " ===\n";
exit( $GLOBALS['hsw_fail'] ? 1 : 0 );
