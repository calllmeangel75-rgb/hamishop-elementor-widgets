<?php
/** Disposable integration probe. Creates/deletes a user and order. Never on a live install. */
if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'HSW_QA_DISPOSABLE' ) ) { fwrite( STDERR, "Disposable CLI install required.\n" ); exit( 2 ); }
register_shutdown_function( static function () {
	$error = error_get_last();
	if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
		echo '::error::' . str_replace( array( "\n", "\r" ), ' ', $error['message'] ) . PHP_EOL;
	}
} );
$wp_load = getenv( 'HSW_WP_LOAD' );
if ( ! $wp_load || ! is_file( $wp_load ) ) { fwrite( STDERR, "HSW_WP_LOAD required.\n" ); exit( 2 ); }
require $wp_load;
if ( ! function_exists( 'wp_get_environment_type' ) || 'production' === wp_get_environment_type() || ! function_exists( 'wc_create_order' ) || ! class_exists( 'HSW_Account' ) ) { fwrite( STDERR, "Missing disposable WP/WC/HamiShop.\n" ); exit( 2 ); }
add_filter( 'pre_wp_mail', static function () { return true; } );
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
set_current_screen( 'shop_order' );
$user = 0; $order = null;
register_shutdown_function( static function () use ( &$user, &$order ) {
	if ( $order instanceof WC_Order ) { $order->delete( true ); }
	if ( $user ) { wp_delete_user( $user ); }
} );
$login = 'hsw_ci_' . strtolower( wp_generate_password( 12, false ) );
$user = wp_create_user( $login, wp_generate_password( 24 ), $login . '@example.invalid' );
if ( is_wp_error( $user ) ) { $user = 0; exit( 2 ); }
$order = wc_create_order( array( 'customer_id' => $user ) );
if ( is_wp_error( $order ) ) { $order = null; exit( 2 ); }
$order->set_payment_method( 'cod' ); $order->set_total( 25000000 ); $order->set_status( 'processing' ); $order->save();
$account = HSW_Account::instance();
$fail = 0;
$check = static function ( $name, $ok ) use ( &$fail ) { echo ( $ok ? 'PASS ' : '::error::FAIL ' ) . $name . PHP_EOL; if ( ! $ok ) { ++$fail; } };
$check( 'display points remain available separately', 2500 === $account->loyalty_points( $user ) );
$check( 'unconfirmed COD points cannot be converted', 0 === $account->convertible_loyalty_points( $user ) );
wp_set_current_user( $user );
$_POST['woocommerce_meta_nonce'] = wp_create_nonce( 'woocommerce_save_data' );
$account->confirm_cod_loyalty( $order );
$check( 'customer cannot confirm', ! $order->get_meta( '_hsw_cod_loyalty_confirmation', true ) );
wp_set_current_user( 1 );
$_POST['woocommerce_meta_nonce'] = 'invalid';
$account->confirm_cod_loyalty( $order );
$check( 'manager without nonce cannot confirm', ! $order->get_meta( '_hsw_cod_loyalty_confirmation', true ) );
$_POST['woocommerce_meta_nonce'] = wp_create_nonce( 'woocommerce_save_data' );
$account->confirm_cod_loyalty( $order );
$check( 'manager confirms COD points', 2500 === $account->convertible_loyalty_points( $user ) );
$check( 'confirmation does not change order status', 'processing' === $order->get_status() );
$order->set_total( 26000000 ); $order->save();
$check( 'changed total invalidates confirmation', 0 === $account->convertible_loyalty_points( $user ) );
$order->set_total( 25000000 ); $order->save();
$order->update_status( 'cancelled' );
$order->update_status( 'processing' );
$check( 'reopening cancelled order requires confirmation', 0 === $account->convertible_loyalty_points( $user ) );
unset( $_POST['woocommerce_meta_nonce'] );
exit( $fail ? 1 : 0 );
