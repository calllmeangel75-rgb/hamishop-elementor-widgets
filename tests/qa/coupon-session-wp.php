<?php
/** Disposable WP/WC coupon regression. Creates products and coupons and changes cart/options. Never run on a real shop. */
if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'HSW_QA_DISPOSABLE' ) ) { fwrite( STDERR, "Disposable CLI install required.\n" ); exit( 2 ); }
register_shutdown_function( static function () { $e = error_get_last(); if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { echo '::error::' . str_replace( "\n", ' ', $e['message'] ) . PHP_EOL; } } );
$path = getenv( 'HSW_WP_LOAD' );
if ( ! $path || ! is_file( $path ) ) { fwrite( STDERR, "HSW_WP_LOAD required.\n" ); exit( 2 ); }
require $path;
if ( ! function_exists( 'wp_get_environment_type' ) || 'production' === wp_get_environment_type() || ! class_exists( 'HSW_Checkout_Rest' ) || ! function_exists( 'WC' ) ) { fwrite( STDERR, "Disposable WP/WC/HamiShop required.\n" ); exit( 2 ); }
HSW_Checkout_Rest::ensure_cart();
if ( ! WC()->cart || ! WC()->session ) { fwrite( STDERR, "WC cart/session unavailable.\n" ); exit( 2 ); }
$original = get_option( 'woocommerce_calc_discounts_sequentially', 'no' );
$objects = array();
register_shutdown_function( static function () use ( &$objects, $original ) {
	WC()->cart->empty_cart();
	foreach ( $objects as $object ) { $object->delete( true ); }
	update_option( 'woocommerce_calc_discounts_sequentially', $original );
} );
update_option( 'woocommerce_calc_discounts_sequentially', 'no' );
WC()->cart->empty_cart();
$product = new WC_Product_Simple();
$product->set_name( 'Temporary coupon regression' ); $product->set_status( 'publish' ); $product->set_regular_price( '1220000' ); $product->set_virtual( true ); $product->save();
$objects[] = $product;
$codes = array();
foreach ( array( 50, 20 ) as $percent ) {
	$coupon = new WC_Coupon();
	$coupon->set_code( 'hswqa' . strtolower( wp_generate_password( 12, false ) ) );
	$coupon->set_discount_type( 'percent' ); $coupon->set_amount( $percent ); $coupon->save();
	$objects[] = $coupon; $codes[] = $coupon->get_code();
}
WC()->cart->add_to_cart( $product->get_id(), 1 ); WC()->cart->calculate_totals();
$rest = new HSW_Checkout_Rest();
$failed = 0;
$check = static function ( $name, $ok ) use ( &$failed ) { echo ( $ok ? 'PASS ' : '::error::FAIL ' ) . $name . PHP_EOL; if ( ! $ok ) { ++$failed; } };
$call = static function ( $key, $value ) use ( $rest ) { $request = new WP_REST_Request( 'POST', '/hsw/v1/checkout/coupon' ); $request->set_param( $key, $value ); return $rest->coupon( $request ); };
$one = $call( 'code', $codes[0] );
$check( '50 percent coupon applied', ! is_wp_error( $one ) && abs( (float) WC()->cart->get_total( 'edit' ) - 610000 ) < 0.01 );
$bad = $call( 'code', 'hsw-invalid-' . wp_generate_password( 12, false ) );
$check( 'invalid coupon leaves existing coupon and total intact', is_wp_error( $bad ) && WC()->cart->has_discount( $codes[0] ) && abs( (float) WC()->cart->get_total( 'edit' ) - 610000 ) < 0.01 );
$two = $call( 'code', $codes[1] );
$check( 'non-sequential 50 and 20 percent coupons', ! is_wp_error( $two ) && abs( (float) WC()->cart->get_total( 'edit' ) - 366000 ) < 0.01 );
// Model a new REST request: the session is populated, but WC_Cart was
// constructed after wp_loaded and has not yet hydrated its coupon properties.
// The action counter is request-scoped in WordPress; reset it for this fixture.
global $wp_actions;
unset( $wp_actions['woocommerce_load_cart_from_session'] );
WC()->cart = new WC_Cart();
$check( 'fresh REST cart starts unhydrated', ! WC()->cart->has_discount( $codes[0] ) );
$removed = $call( 'remove', $codes[0] );
$check( 'remove 50 percent leaves 20 percent', ! is_wp_error( $removed ) && ! WC()->cart->has_discount( $codes[0] ) && WC()->cart->has_discount( $codes[1] ) && abs( (float) WC()->cart->get_total( 'edit' ) - 976000 ) < 0.01 );
$removed_last = $call( 'remove', $codes[1] );
$check( 'remove last returns original total', ! is_wp_error( $removed_last ) && ! WC()->cart->has_discount() && abs( (float) WC()->cart->get_total( 'edit' ) - 1220000 ) < 0.01 );
exit( $failed ? 1 : 0 );
