<?php
/**
 * Public woocommerce_thankyou regression on disposable WordPress only.
 *
 * The confirmation card HamiShop renders replaces WooCommerce's thank-you
 * template, so the shared guard has to fire the hook pair itself — exactly
 * once per order and request, with the order id, and never twice even when
 * both the complete page and the checkout card render in one request.
 */
if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'HSW_QA_DISPOSABLE' ) ) { exit( 2 ); }
require getenv( 'HSW_WP_LOAD' );
if ( 'production' === wp_get_environment_type() ) { exit( 2 ); }
if ( ! class_exists( 'HSW_Thankyou' ) ) {
    require_once dirname( __DIR__, 2 ) . '/includes/class-hsw-thankyou.php';
}

$failed = 0;
$check  = static function ( $name, $ok ) use ( &$failed ) {
    echo ( $ok ? 'PASS ' : '::error::FAIL ' ) . $name . PHP_EOL;
    if ( ! $ok ) { ++$failed; }
};

/* WooCommerce prints the order-details table on the public hook. */
$fire = static function ( $order ) {
    ob_start();
    $result = HSW_Thankyou::fire( $order );
    ob_end_clean();
    return $result;
};

$public = array();
$method = array();
$before = array();
add_action( 'woocommerce_thankyou', static function ( $order_id ) use ( &$public ) { $public[] = (int) $order_id; }, 10, 1 );
add_action( 'woocommerce_thankyou_cod', static function ( $order_id ) use ( &$method ) { $method[] = (int) $order_id; }, 10, 1 );
add_action( 'woocommerce_before_thankyou', static function ( $order_id ) use ( &$before ) { $before[] = (int) $order_id; }, 10, 1 );

$order = wc_create_order();
$order->set_payment_method( 'cod' );
$order->save();
$order_id = (int) $order->get_id();

/* Complete page then checkout card, same request: one pair, not two. */
$first  = $fire( $order );
$second = $fire( $order );
$check( 'first render fires the hooks', true === $first );
$check( 'second render in the same request is a no-op', false === $second );
$check( 'public woocommerce_thankyou fires exactly once with the order id', array( $order_id ) === $public );
$check( 'gateway woocommerce_thankyou_cod fires exactly once', array( $order_id ) === $method );
$check( 'woocommerce_before_thankyou leads the pair once', array( $order_id ) === $before );
$check( 'fired() reports the order as done', true === HSW_Thankyou::fired( $order_id ) );

/* Passing the id instead of the object behaves the same. */
HSW_Thankyou::reset();
$public = $method = $before = array();
$by_id = $fire( $order_id );
$check( 'order id is accepted as well as the object', true === $by_id && array( $order_id ) === $public && array( $order_id ) === $method );

/* An order with no payment method still announces the purchase publicly. */
HSW_Thankyou::reset();
$public = $method = $before = array();
$plain = wc_create_order();
$plain->save();
$plain_id = (int) $plain->get_id();
$fire( $plain );
$check( 'order without a gateway still fires the public hook once', array( $plain_id ) === $public );
$check( 'order without a gateway does not fire an empty gateway hook', array() === $method );

/* If another component already fired the public hook, core owns it. */
HSW_Thankyou::reset();
$public = $method = $before = array();
ob_start();
do_action( 'woocommerce_thankyou', $order_id );
ob_end_clean();
$public_before = $public;
$skipped = $fire( $order );
$check( 'guard stands down when the public hook already fired', false === $skipped && $public_before === $public && array() === $method && array() === $before );
$check( 'skipped order is still remembered', true === HSW_Thankyou::fired( $order_id ) );

/* A different order in the same request fires again. */
HSW_Thankyou::reset();
$public = $method = $before = array();
$fire( $order );
$fire( $plain );
$check( 'two different orders each fire once', array( $order_id, $plain_id ) === $public );

/* On the native order-received endpoint WooCommerce's own checkout renders the
   thank-you template, so HamiShop must not fire the pair a second time. */
HSW_Thankyou::reset();
$public = $method = $before = array();
$wp = $GLOBALS['wp'];
$had_query = isset( $wp->query_vars['order-received'] );
$old_query = $had_query ? $wp->query_vars['order-received'] : null;
$checkout_page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Checkout QA', 'post_content' => '[woocommerce_checkout]' ) );
$point_checkout = static function () use ( $checkout_page ) { return $checkout_page; };
add_filter( 'woocommerce_get_checkout_page_id', $point_checkout );
$wp->query_vars['order-received'] = $order_id;
$native = $fire( $order );
$check( 'native order-received page leaves the hooks to WooCommerce', false === $native && array() === $public && array() === $method && array() === $before );
if ( $had_query ) { $wp->query_vars['order-received'] = $old_query; } else { unset( $wp->query_vars['order-received'] ); }
remove_filter( 'woocommerce_get_checkout_page_id', $point_checkout );
wp_delete_post( $checkout_page, true );

/* Invalid input never fires and never marks anything. */
HSW_Thankyou::reset();
$public = $method = $before = array();
$check( 'zero id is ignored', false === $fire( 0 ) && array() === $public && false === HSW_Thankyou::fired( 0 ) );
$check( 'negative id is ignored', false === $fire( -5 ) && array() === $public );

$order->delete( true );
$plain->delete( true );

exit( $failed ? 1 : 0 );
