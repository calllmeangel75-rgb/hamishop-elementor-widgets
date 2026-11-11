<?php
/**
 * Disposable WP/WC regression: paying a wallet top-up, and what the pay step
 * may show and charge. Creates a user, a product, orders and wallet rows and
 * changes payment options. Never run on a real shop. Elementor must be active:
 * what the pay widgets show is part of what is checked.
 *
 * Covers four defects that were live together:
 *  1. a top-up settled with Cash on Delivery credited the wallet for free;
 *  2. a top-up could be paid from the wallet itself;
 *  3. an abandoned top-up (or any stale awaiting order) was charged instead of
 *     the basket the pay page was showing;
 *  4. `/order-pay/<id>/` without the order key made the pay step show a
 *     stranger's order.
 */
if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'HSW_QA_DISPOSABLE' ) ) { fwrite( STDERR, "Disposable CLI install required.\n" ); exit( 2 ); }
register_shutdown_function( static function () { $e = error_get_last(); if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { echo '::error::' . str_replace( "\n", ' ', $e['message'] ) . PHP_EOL; } } );
$path = getenv( 'HSW_WP_LOAD' );
if ( ! $path || ! is_file( $path ) ) { fwrite( STDERR, "HSW_WP_LOAD required.\n" ); exit( 2 ); }
require $path;
if ( ! function_exists( 'wp_get_environment_type' ) || 'production' === wp_get_environment_type() || ! class_exists( 'HSW_Wallet_Topup' ) || ! class_exists( 'HSW_Checkout_Complete' ) || ! function_exists( 'WC' ) ) { fwrite( STDERR, "Disposable WP/WC/HamiShop required.\n" ); exit( 2 ); }
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( ! HSW_Wallet::instance()->install_tables() ) { fwrite( STDERR, "Wallet tables unavailable.\n" ); exit( 2 ); }

/* A redirect-style gateway: the order stays pending until the bank calls back. */
if ( ! class_exists( 'HSW_QA_Bank_Gateway' ) ) {
	class HSW_QA_Bank_Gateway extends WC_Payment_Gateway {
		public function __construct() {
			$this->id = 'hswqa_bank'; $this->method_title = 'QA Bank'; $this->title = 'QA Bank';
			$this->has_fields = false; $this->enabled = 'yes'; $this->supports = array( 'products' );
		}
		public function is_available() { return true; }
		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			return array( 'result' => 'success', 'redirect' => 'https://bank.invalid/pay?order=' . (int) $order_id . '&amount=' . $order->get_total() );
		}
	}
}
$add_bank = static function ( $gateways ) { $gateways[] = 'HSW_QA_Bank_Gateway'; return $gateways; };
add_filter( 'woocommerce_payment_gateways', $add_bank );

$saved = array(
	'hsw_wallet_gateway_on'    => get_option( 'hsw_wallet_gateway_on', null ),
	'woocommerce_cod_settings' => get_option( 'woocommerce_cod_settings', null ),
);
update_option( 'hsw_wallet_gateway_on', 'yes', false );
update_option( 'woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => 'COD', 'enable_for_virtual' => 'yes' ) );
WC()->payment_gateways()->init();

$login = 'hsw_topup_' . strtolower( wp_generate_password( 10, false ) );
$user  = wp_create_user( $login, wp_generate_password( 24 ), $login . '@example.invalid' );
$other = wp_create_user( $login . '_o', wp_generate_password( 24 ), $login . '_o@example.invalid' );
if ( is_wp_error( $user ) || is_wp_error( $other ) ) { exit( 2 ); }

$product = new WC_Product_Simple();
$product->set_name( 'Temporary top-up guard product' ); $product->set_status( 'publish' ); $product->set_regular_price( '230000' ); $product->set_virtual( true ); $product->save();

register_shutdown_function( static function () use ( $user, $other, $product, $saved, $add_bank ) {
	global $wpdb;
	if ( WC()->cart ) { WC()->cart->empty_cart(); }
	foreach ( array( $user, $other ) as $uid ) {
		foreach ( wc_get_orders( array( 'customer_id' => $uid, 'limit' => -1, 'status' => array_keys( wc_get_order_statuses() ) ) ) as $order ) { $order->delete( true ); }
		$wpdb->delete( HSW_Wallet::table(), array( 'user_id' => $uid ) );
		$wpdb->delete( HSW_Wallet::balance_table(), array( 'user_id' => $uid ) );
		wp_delete_user( $uid );
	}
	$product->delete( true );
	foreach ( $saved as $name => $value ) { if ( null === $value ) { delete_option( $name ); } else { update_option( $name, $value ); } }
	remove_filter( 'woocommerce_payment_gateways', $add_bank );
} );

/*
 * A die() inside the code under test (wp_die(), wp_send_json(), a redirect
 * followed by exit) ends the script with status 0, and the step would read as
 * a pass with half of the assertions never reached. Registered after the
 * clean-up above, so that one still runs first.
 */
$done = false;
register_shutdown_function( static function () use ( &$done ) { if ( ! $done ) { echo '::error::FAIL the script stopped before its last assertion' . PHP_EOL; exit( 1 ); } } );

/* Become the customer BEFORE WooCommerce builds the session, cart and customer. */
wp_set_current_user( $user );
WC()->session = null; WC()->cart = null; WC()->customer = null;
wc_load_cart();
if ( ! WC()->cart || ! WC()->session || (string) WC()->session->get_customer_id() !== (string) $user ) { fwrite( STDERR, "WC session for the test user unavailable.\n" ); exit( 2 ); }
$customer = WC()->customer;
foreach ( array( 'first_name' => 'QA', 'last_name' => 'Tester', 'country' => 'IR', 'state' => 'THR', 'city' => 'Tehran', 'address_1' => 'QA street 1', 'postcode' => '1234567890', 'phone' => '09120000000', 'email' => $login . '@example.invalid' ) as $field => $value ) {
	$customer->{ 'set_billing_' . $field }( $value );
}
$customer->save();

$failed = 0;
$ran    = 0;
$check  = static function ( $name, $ok, $detail = '' ) use ( &$failed, &$ran ) { ++$ran; echo ( $ok ? 'PASS ' : '::error::FAIL ' ) . $name . ( $ok || '' === $detail ? '' : ' — ' . $detail ) . PHP_EOL; if ( ! $ok ) { ++$failed; } };
$rest   = static function ( $route, $params ) {
	$request = new WP_REST_Request( 'POST', $route );
	$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
	foreach ( $params as $key => $value ) { $request->set_param( $key, $value ); }
	$response = rest_do_request( $request );
	return array( 'status' => (int) $response->get_status(), 'data' => (array) $response->get_data() );
};
$topup = static function ( $amount ) use ( $rest ) {
	$out = $rest( '/hsw/v1/account/wallet/pay', array( 'amount' => $amount, 'gateway' => 'hswqa_bank' ) );
	return isset( $out['data']['order'] ) ? (int) $out['data']['order'] : 0;
};
$pay     = static function ( $method ) use ( $rest ) { wc_clear_notices(); return $rest( '/hsw/v1/checkout/complete', array( 'method' => $method, 'terms' => true ) ); };
$balance = static function () use ( $user ) { return (float) HSW_Wallet::balance( $user ); };
$hook    = static function ( $method ) {
	global $wp_filter;
	foreach ( $wp_filter['template_redirect']->callbacks as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof HSW_Wallet_Topup && $method === $callback['function'][1] ) { return call_user_func( $callback['function'] ); }
		}
	}
	return null;
};
global $wp;

/* --- A. the honest path: bank top-up, credited once ------------------------ */
$t1  = $topup( 150000 );
$out = $pay( 'hswqa_bank' );
$check( 'top-up is handed to the bank by the pay step', $t1 > 0 && 200 === $out['status'] && (int) ( $out['data']['order_id'] ?? 0 ) === $t1, wp_json_encode( $out ) );
$check( 'no credit before the bank confirms', 0.0 === $balance() );
wc_get_order( $t1 )->payment_complete( 'QA-TX-1' );
$check( 'credited when the bank confirms', 150000.0 === $balance(), (string) $balance() );
wc_get_order( $t1 )->update_status( 'completed' );
wc_get_order( $t1 )->payment_complete( 'QA-TX-1' );
$check( 'credited exactly once (status change + duplicate callback)', 150000.0 === $balance(), (string) $balance() );

/* --- B/C. COD and the wallet cannot pay a top-up ---------------------------- */
$t2  = $topup( 100000 );
$ids = array_keys( WC()->payment_gateways()->get_available_payment_gateways() );
$check( 'pay step hides COD and the wallet for a top-up', ! in_array( 'cod', $ids, true ) && ! in_array( 'hsw_wallet', $ids, true ) && in_array( 'hswqa_bank', $ids, true ), implode( ',', $ids ) );
$out = $pay( 'cod' );
$check( 'COD is refused for a top-up', 400 === $out['status'] && 'hsw_bad_gateway' === ( $out['data']['code'] ?? '' ), wp_json_encode( $out ) );
$out = $pay( 'hsw_wallet' );
$check( 'the wallet is refused for a top-up', 400 === $out['status'] && 'hsw_bad_gateway' === ( $out['data']['code'] ?? '' ), wp_json_encode( $out ) );
$check( 'refused attempts moved no money and paid nothing', 150000.0 === $balance() && wc_get_order( $t2 )->has_status( 'pending' ), $balance() . ' ' . wc_get_order( $t2 )->get_status() );

/* --- D. settle() holds on its own (WooCommerce's native pay form) ----------- */
$order = wc_get_order( $t2 ); $order->set_payment_method( 'cod' ); $order->save();
$order->update_status( 'processing' );
wc_get_order( $t2 )->update_status( 'completed' );
$check( 'a COD top-up reaching processing/completed earns no credit', 150000.0 === $balance(), (string) $balance() );
$notes = 0;
foreach ( wc_get_order_notes( array( 'order_id' => $t2 ) ) as $note ) { if ( false !== strpos( $note->content, '(cod)' ) ) { ++$notes; } }
$check( 'the shop manager is told why, once', 1 === $notes, (string) $notes );
$order = wc_get_order( $t2 ); $order->set_payment_method( 'hsw_wallet' ); $order->save();
$gateways = WC()->payment_gateways()->payment_gateways();
$result   = isset( $gateways['hsw_wallet'] ) ? $gateways['hsw_wallet']->process_payment( $t2 ) : array( 'result' => 'missing' );
wc_clear_notices();
$check( 'the wallet gateway itself refuses a top-up order', 'failure' === ( $result['result'] ?? '' ) && 150000.0 === $balance(), wp_json_encode( $result ) );

/* --- E. the order-pay URL lists the same gateways --------------------------- */
$t3 = $topup( 120000 );
$wp->query_vars['order-pay'] = (string) $t3;
$ids = array_keys( WC()->payment_gateways()->get_available_payment_gateways() );
$check( 'order-pay of a top-up: COD and the wallet hidden', ! in_array( 'cod', $ids, true ) && ! in_array( 'hsw_wallet', $ids, true ), implode( ',', $ids ) );
unset( $wp->query_vars['order-pay'] );

/* --- F. a stale top-up never replaces the basket ---------------------------- */
WC()->cart->add_to_cart( $product->get_id(), 1 ); WC()->cart->calculate_totals();
$ids = array_keys( WC()->payment_gateways()->get_available_payment_gateways() );
$check( 'a normal basket still offers COD', in_array( 'cod', $ids, true ), implode( ',', $ids ) );
$out   = $pay( 'hswqa_bank' );
$paid  = wc_get_order( (int) ( $out['data']['order_id'] ?? 0 ) );
$check( 'the basket on screen is what gets charged, not the abandoned top-up', 200 === $out['status'] && $paid && $paid->get_id() !== $t3 && 230000.0 === (float) $paid->get_total() && 1 === count( $paid->get_items() ), wp_json_encode( $out ) );
$check( 'the abandoned top-up is left untouched', wc_get_order( $t3 )->has_status( 'pending' ) && 150000.0 === $balance() );

/* --- G. order-pay links: only an order this visitor may pay ----------------- */
WC()->cart->empty_cart(); WC()->session->set( 'order_awaiting_payment', null );
$mine = wc_create_order( array( 'customer_id' => $user ) ); $mine->add_product( $product, 1 ); $mine->calculate_totals(); $mine->set_status( 'pending' ); $mine->save();
$theirs = wc_create_order( array( 'customer_id' => $other ) ); $theirs->add_product( $product, 2 ); $theirs->calculate_totals(); $theirs->set_status( 'pending' ); $theirs->save();

$wp->query_vars['order-pay'] = (string) $theirs->get_id(); unset( $_GET['key'] );
$hook( 'remember_order_pay' );
$check( "someone else's order without a key is not remembered", ! WC()->session->get( 'order_awaiting_payment' ) );
$_GET['key'] = $theirs->get_order_key();
$hook( 'remember_order_pay' );
$check( "someone else's order is not remembered even with its key", ! WC()->session->get( 'order_awaiting_payment' ) );

$view = null;
if ( class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->widgets_manager->get_widget_types();
	if ( class_exists( 'HSW_Widget_Checkout_Items' ) ) {
		$widget = new HSW_Widget_Checkout_Items();
		$method = new ReflectionMethod( $widget, 'paying_order' );
		$method->setAccessible( true );
		$view = static function () use ( $widget, $method ) { return $method->invoke( $widget ); };
	}
}
if ( $view ) {
	unset( $_GET['key'] );
	$check( 'pay widgets do not show a foreign order named in the URL', null === $view() );
	$_GET['key'] = $theirs->get_order_key();
	$check( 'pay widgets do not show a foreign order even with its key', null === $view() );
	WC()->session->set( 'order_awaiting_payment', $theirs->get_id() ); unset( $wp->query_vars['order-pay'] );
	$check( 'pay widgets do not show a foreign order left in the session', null === $view() );
	WC()->session->set( 'order_awaiting_payment', null );
} else {
	/* What the pay widgets show is half of defect 4: not looking is not a pass. */
	echo '::error::FAIL the pay widgets could not be loaded (Elementor must be active)' . PHP_EOL; ++$failed;
}

$wp->query_vars['order-pay'] = (string) $mine->get_id(); unset( $_GET['key'] );
$hook( 'remember_order_pay' );
$check( 'own order without the key is not remembered', ! WC()->session->get( 'order_awaiting_payment' ) );
$_GET['key'] = $mine->get_order_key();
$hook( 'remember_order_pay' );
$check( 'own order with its key is remembered', absint( WC()->session->get( 'order_awaiting_payment' ) ) === $mine->get_id() );
if ( $view ) {
	$shown = $view();
	$check( 'pay widgets show the owner their own order', $shown instanceof WC_Order && $shown->get_id() === $mine->get_id() );
}
unset( $wp->query_vars['order-pay'], $_GET['key'] );

/* A skipped block must not read as a pass either. */
if ( 23 !== $ran ) { echo '::error::FAIL expected 23 assertions, ' . $ran . ' ran' . PHP_EOL; ++$failed; }
$done = true;
exit( $failed ? 1 : 0 );
