<?php
/**
 * Document current COD loyalty behavior, not an approval of that policy.
 * Creates and deletes one user and one order. NEVER run on a real store.
 * This probe does not convert points, call a gateway or move wallet money.
 */
if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'HSW_QA_DISPOSABLE' ) ) {
	fwrite( STDERR, "Requires an explicitly disposable CLI install.\n" ); exit( 2 );
}
$wp_load = getenv( 'HSW_WP_LOAD' );
if ( ! $wp_load || ! is_file( $wp_load ) ) { fwrite( STDERR, "HSW_WP_LOAD required.\n" ); exit( 2 ); }
require $wp_load;
if ( ! function_exists( 'wp_get_environment_type' ) || 'production' === wp_get_environment_type() || ! function_exists( 'wc_create_order' ) || ! class_exists( 'HSW_Account' ) ) {
	fwrite( STDERR, "Requires disposable WordPress, WooCommerce and HamiShop.\n" ); exit( 2 );
}
add_filter( 'pre_wp_mail', static function () { return true; } );
require_once ABSPATH . 'wp-admin/includes/user.php';
$user_id = 0;
$order = null;
register_shutdown_function( static function () use ( &$user_id, &$order ) {
	if ( $order instanceof WC_Order ) { $order->delete( true ); }
	if ( $user_id ) { wp_delete_user( $user_id ); }
} );
$login = 'hsw_ci_' . strtolower( wp_generate_password( 12, false ) );
$user_id = wp_create_user( $login, wp_generate_password( 24 ), $login . '@example.invalid' );
if ( is_wp_error( $user_id ) ) { fwrite( STDERR, "Temporary user creation failed.\n" ); $user_id = 0; exit( 2 ); }
$order = wc_create_order( array( 'customer_id' => $user_id ) );
if ( is_wp_error( $order ) ) { fwrite( STDERR, "Temporary order creation failed.\n" ); $order = null; exit( 2 ); }
$order->set_payment_method( 'cod' );
$order->set_total( 25000000 );
$order->set_status( 'pending' );
$order->save();
$account = HSW_Account::instance();
$opts = array( 'loyalty' => array( 'rate' => 10000 ) );
$pending = $account->loyalty_points( $user_id, $opts );
$order->update_status( 'processing' );
$processing = $account->loyalty_points( $user_id, $opts );
$spent = wc_get_customer_total_spent( $user_id );
echo "COD pending points: $pending\nCOD processing points: $processing\nWooCommerce total spent: $spent\n";
$ok = 0 === $pending && 2500 === $processing && 25000000.0 === (float) $spent;
echo ( $ok ? 'PASS ' : 'FAIL ' ) . "observed current behavior (not a payment or conversion)\n";
exit( $ok ? 0 : 1 );
