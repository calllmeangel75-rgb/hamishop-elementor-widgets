<?php
/**
 * Exercise cart label resolution with real WordPress taxonomy/terms.
 *
 * NOT a full cart, browser, or order test. Creates two temporary terms.
 * Run ONLY on a disposable, isolated install; shutdown cleanup cannot survive
 * a killed process. Requires HSW_QA_DISPOSABLE=1 and a non-production WP env.
 *   WP_ENVIRONMENT_TYPE=local HSW_QA_DISPOSABLE=1 HSW_WP_LOAD=/path/to/wp-load.php \
 *     php tests/qa/cart-variation-labels-wp.php
 */
if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'HSW_QA_DISPOSABLE' ) ) {
	fwrite( STDERR, "CLI and HSW_QA_DISPOSABLE=1 on an isolated install are required.\n" );
	exit( 2 );
}
$wp_load = getenv( 'HSW_WP_LOAD' );
if ( ! $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, "Set HSW_WP_LOAD to a disposable WordPress install.\n" );
	exit( 2 );
}
require $wp_load;
if ( ! function_exists( 'wp_get_environment_type' ) || 'production' === wp_get_environment_type() ) {
	fwrite( STDERR, "Refusing a production environment.\n" );
	exit( 2 );
}
if ( ! function_exists( 'WC' ) || ! defined( 'HSW_DIR' ) || ! class_exists( '\\Elementor\\Widget_Base' ) ) {
	fwrite( STDERR, "HamiShop, WooCommerce and Elementor must be active.\n" );
	exit( 2 );
}
require_once HSW_DIR . 'includes/class-hsw-widget-base.php';
require_once HSW_DIR . 'includes/class-hsw-cart-base.php';
class HSW_QA_WP_Variation_Labels extends HSW_Cart_Base {
	public function get_name() { return 'hsw-qa-variation-labels'; }
	public function chips( $variation ) { return $this->item_chips( array( 'variation' => $variation ), null ); }
}

/* The unique taxonomy is registered only in this PHP process. */
$tax = 'pa_رنگ_' . substr( md5( uniqid( '', true ) ), 0, 6 );
if ( taxonomy_exists( $tax ) || is_wp_error( register_taxonomy( $tax, 'product', array( 'public' => false, 'rewrite' => false ) ) ) ) {
	fwrite( STDERR, "Unable to register a unique temporary taxonomy.\n" );
	exit( 2 );
}
$term_ids = array();
register_shutdown_function( static function () use ( &$term_ids, $tax ) {
	foreach ( $term_ids as $term_id ) {
		wp_delete_term( $term_id, $tax );
	}
} );
$failed = 0;
$probe = new HSW_QA_WP_Variation_Labels();
$encoded_tax = sanitize_title( $tax );
if ( $encoded_tax === $tax || taxonomy_exists( $encoded_tax ) ) {
	fwrite( STDERR, "The test requires an encoded key distinct from its registered taxonomy.\n" );
	exit( 2 );
}
foreach ( array( 'قرمز', 'مشکی' ) as $name ) {
	$inserted = wp_insert_term( $name, $tax );
	if ( is_wp_error( $inserted ) ) {
		fwrite( STDERR, "Could not create temporary term: " . $inserted->get_error_message() . "\n" );
		exit( 2 );
	}
	$term_ids[] = (int) $inserted['term_id'];
	$term = get_term( (int) $inserted['term_id'], $tax );
	if ( ! $term instanceof WP_Term ) {
		fwrite( STDERR, "Could not read temporary term.\n" );
		exit( 2 );
	}
	$chips = $probe->chips( array( 'attribute_' . $encoded_tax => $term->slug ) );
	$ok = isset( $chips[0]['label'] ) && $name === $chips[0]['label'];
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . 'label for ' . $name . PHP_EOL;
	if ( ! $ok ) { ++$failed; }
}
exit( $failed ? 1 : 0 );
