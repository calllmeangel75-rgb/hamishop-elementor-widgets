<?php
/** Real WooCommerce order objects in memory only; no order is saved or paid. */
if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'HSW_QA_DISPOSABLE' ) ) {
	fwrite( STDERR, "Requires CLI and an explicitly disposable test install.\n" ); exit( 2 );
}
$wp_load = getenv( 'HSW_WP_LOAD' );
if ( ! $wp_load || ! is_file( $wp_load ) ) { fwrite( STDERR, "HSW_WP_LOAD is required.\n" ); exit( 2 ); }
require $wp_load;
if ( ! function_exists( 'wp_get_environment_type' ) || 'production' === wp_get_environment_type() || ! function_exists( 'WC' ) || ! defined( 'HSW_DIR' ) || ! class_exists( '\\Elementor\\Widget_Base' ) ) {
	fwrite( STDERR, "Requires non-production WordPress, WooCommerce, Elementor and HamiShop.\n" ); exit( 2 );
}
foreach ( array( 'widget', 'cart', 'checkout', 'pay', 'complete' ) as $base ) {
	require_once HSW_DIR . 'includes/class-hsw-' . $base . '-base.php';
}
class HSW_QA_WP_Payment_Indicator extends HSW_Complete_Base {
	public function get_name() { return 'hsw-qa-payment-indicator'; }
	public function show_paid( $order ) { return $this->completion_show_paid( $order ); }
}
$probe = new HSW_QA_WP_Payment_Indicator();
$failed = 0;
foreach ( array( array( 'cod', 'processing', false ), array( 'cod', 'completed', false ), array( 'bacs', 'processing', true ), array( 'bacs', 'pending', false ) ) as $test ) {
	$order = new WC_Order();
	$order->set_payment_method( $test[0] );
	$order->set_total( 100 );
	$order->set_status( $test[1] );
	$actual = $probe->show_paid( $order );
	$ok = $actual === $test[2];
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $test[0] . ' ' . $test[1] . PHP_EOL;
	if ( ! $ok ) { ++$failed; }
}
exit( $failed ? 1 : 0 );
