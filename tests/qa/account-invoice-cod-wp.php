<?php
/** Integration test: creates and deletes temporary orders on a disposable install only. */
if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'HSW_QA_DISPOSABLE' ) ) { fwrite( STDERR, "Requires an explicitly disposable CLI install.\n" ); exit( 2 ); }
$wp_load = getenv( 'HSW_WP_LOAD' );
if ( ! $wp_load || ! is_file( $wp_load ) ) { fwrite( STDERR, "HSW_WP_LOAD required.\n" ); exit( 2 ); }
require $wp_load;
if ( ! function_exists( 'wp_get_environment_type' ) || 'production' === wp_get_environment_type() || ! function_exists( 'wc_create_order' ) || ! class_exists( 'HSW_Account' ) ) { fwrite( STDERR, "Requires non-production WordPress, WooCommerce and HamiShop.\n" ); exit( 2 ); }
// Isolated CI must not send email; do not run this script on a live site.
add_filter( 'pre_wp_mail', static function () { return true; } );
$orders = array();
register_shutdown_function( static function () use ( &$orders ) {
	foreach ( $orders as $order ) { $order->delete( true ); }
} );
$method = new ReflectionMethod( 'HSW_Account', 'view_order_content' );
$method->setAccessible( true );
$account = HSW_Account::instance();
$failed = 0;
foreach ( array( 'cod', 'bacs' ) as $gateway ) {
	$order = wc_create_order( array( 'customer_id' => 1 ) );
	if ( is_wp_error( $order ) ) { fwrite( STDERR, $order->get_error_message() . "\n" ); exit( 2 ); }
	$orders[] = $order;
	$order->set_payment_method( $gateway );
	$order->set_payment_method_title( $gateway );
	$order->set_total( 25000000 );
	$order->set_status( 'processing' );
	$order->save();
	$html = $method->invoke( $account, array( 'view_order_id' => $order->get_id(), 'user_id' => 1 ) );
	$claims_paid = false !== strpos( $html, 'پرداخت شده' );
	$bad_amount = false !== strpos( $html, 'مبلغ پرداخت‌شده' );
	$has_total = false !== strpos( $html, 'مبلغ کل سفارش' );
	$ok = $has_total && ! $bad_amount && ( 'cod' === $gateway ? ! $claims_paid : $claims_paid );
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $gateway . " invoice\n";
	if ( ! $ok ) { ++$failed; }
}
exit( $failed ? 1 : 0 );
