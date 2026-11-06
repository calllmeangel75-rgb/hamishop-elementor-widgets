<?php
/**
 * Regression test: the free-shipping rate cache must be invalidated on the
 * *first* save of the option too.
 *
 * `update_option_{$option}` only fires when an option already exists and its
 * value changes. The first save of a missing option goes through
 * `add_option()`, which fires `add_option_{$option}` — and that path was not
 * hooked, so the very first activation (or the first threshold) left every
 * cached rate untouched.
 *
 * This runs against a real WordPress + WooCommerce install and drives the real
 * `update_option()` / `delete_option()` path, not the invalidation function.
 *
 * Usage:
 *   HSW_WP_LOAD=/path/to/wp-load.php php tests/qa/free-shipping-cache.php
 *   php tests/qa/free-shipping-cache.php            # tries a few known paths
 *
 * Exit code: 0 = every assertion passed.
 */

$wp_load = getenv( 'HSW_WP_LOAD' );

if ( ! $wp_load ) {
	$candidates = array(
		__DIR__ . '/../../../../wp-load.php',
		__DIR__ . '/../../../../../wp-load.php',
	);

	foreach ( $candidates as $candidate ) {
		if ( file_exists( $candidate ) ) {
			$wp_load = $candidate;
			break;
		}
	}
}

if ( ! $wp_load || ! file_exists( $wp_load ) ) {
	fwrite( STDERR, "Set HSW_WP_LOAD to the wp-load.php of a WordPress install with WooCommerce.\n" );
	exit( 2 );
}

define( 'WP_USE_THEMES', false );
require $wp_load;

if ( ! class_exists( 'WC_Cache_Helper' ) ) {
	fwrite( STDERR, "WooCommerce is not active on this install.\n" );
	exit( 2 );
}

$on_option  = HSW_Free_Shipping::ENABLED;
$min_option = HSW_Free_Shipping::OPTION;

$original = array(
	$on_option  => get_option( $on_option, null ),
	$min_option => get_option( $min_option, null ),
);

$restore = static function () use ( $original ) {
	foreach ( $original as $name => $value ) {
		$exists = null !== $value;
		if ( $exists ) {
			update_option( $name, $value, false );
		} else {
			delete_option( $name );
		}
	}
};

register_shutdown_function( $restore );

$version = static function () {
	return (string) WC_Cache_Helper::get_transient_version( 'shipping' );
};

/**
 * The session key WooCommerce uses for a package's cached rates.
 *
 * `WC_Shipping::calculate_shipping_for_package()` keys the session on
 * `'wc_ship_' . md5( wp_json_encode( $package ) . WC_Cache_Helper::get_transient_version( 'shipping' ) )`.
 * The method is private, so this repeats the same inputs and the same formula.
 */
$session_key = static function ( array $package ) use ( $version ) {
	return 'shipping_for_package_wc_ship_' . md5( wp_json_encode( $package ) . $version() );
};

$package = array(
	'contents'        => array( array( 'product_id' => 13192, 'quantity' => 1 ) ),
	'destination'     => array( 'country' => 'IR', 'state' => 'THR', 'postcode' => '12345' ),
	'contents_cost'   => 250000,
	'applied_coupons' => array(),
);

$results = array();

$check = static function ( $label, $expected, $actual ) use ( &$results ) {
	$ok        = $expected === $actual;
	$results[] = $ok;
	printf(
		"%s  %-58s expected %-5s got %s\n",
		$ok ? 'PASS' : 'FAIL',
		$label,
		var_export( $expected, true ),
		var_export( $actual, true )
	);
};

/* ---------------------------------------------------------------
 * 1. First save of an option that does not exist (add_option path)
 * --------------------------------------------------------------- */
delete_option( $on_option );
$before = $version();
update_option( $on_option, 'yes', false );
$check( 'first save of the switch bumps the version', true, $before !== $version() );

delete_option( $min_option );
$before = $version();
update_option( $min_option, 500000, false );
$check( 'first save of the threshold bumps the version', true, $before !== $version() );

/* ---------------------------------------------------------------
 * 2. Existing option, value changes
 * --------------------------------------------------------------- */
$before = $version();
update_option( $on_option, 'no', false );
$check( 'switch on → off bumps the version', true, $before !== $version() );

$before = $version();
update_option( $on_option, 'yes', false );
$check( 'switch off → on bumps the version', true, $before !== $version() );

$before = $version();
update_option( $min_option, 750000, false );
$check( 'threshold change bumps the version', true, $before !== $version() );

/* ---------------------------------------------------------------
 * 3. Saving the same value again must not invalidate anything
 * --------------------------------------------------------------- */
$before = $version();
update_option( $on_option, 'yes', false );
$check( 'unchanged save does not bump the version', false, $before !== $version() );

/* ---------------------------------------------------------------
 * 4. The cached entry is orphaned, not rewritten
 * --------------------------------------------------------------- */
$key_before = $session_key( $package );
update_option( $min_option, 900000, false );
$key_after = $session_key( $package );

$check( 'the session cache key changes for the same package', true, $key_before !== $key_after );

/* ---------------------------------------------------------------
 * 5. A different package keeps its own key shape
 * --------------------------------------------------------------- */
$other = $package;
$other['contents_cost'] = 1000;
$check( 'a different package has a different key', true, $session_key( $other ) !== $session_key( $package ) );

$failed = count( array_filter( $results, static function ( $ok ) { return ! $ok; } ) );

echo "\n" . ( $failed ? $failed . ' assertion(s) failed' : 'all assertions passed' ) . "\n";

exit( $failed ? 1 : 0 );
