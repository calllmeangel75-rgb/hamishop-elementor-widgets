<?php
/**
 * WSOD harness: load every plugin file in the EXACT WordPress order.
 * Any compile-level fatal (Class not found, Cannot make static method…,
 * redeclare, abstract not implemented, visibility narrowing) surfaces here
 * before the user ever sees a white screen.
 *
 * Usage: /tmp/php tests/harness/harness.php
 */

error_reporting( E_ALL );
define( 'ABSPATH', '/home/z/my-project/' );

require __DIR__ . '/stubs.php';

$base = dirname( __DIR__ ); // plugin root — portable

/* Order mirrors HSW_Plugin::includes() + register_widgets() exactly. */
$files = array(
	/* ---- plugins_loaded: pure WP/WC classes ---- */
	'includes/class-hsw-helpers.php',
	'includes/class-hsw-composite.php',
	'includes/class-hsw-products.php',
	'includes/class-hsw-rest.php',
	'includes/class-hsw-assets.php',
	'includes/class-hsw-archive-query.php',
	'includes/class-hsw-cart-actions.php',
	'includes/class-hsw-checkout-rest.php',
	'includes/class-hsw-checkout-complete.php',
	/* ---- elementor/widgets/register: Elementor bases first ---- */
	'includes/class-hsw-widget-base.php',
	'includes/class-hsw-product-base.php',
	'includes/class-hsw-archive-base.php',
	'includes/class-hsw-cart-base.php',
	'includes/class-hsw-checkout-base.php',
	'includes/class-hsw-pay-base.php',
	'includes/class-hsw-blog-base.php',
	/* ---- widget files ---- */
	'widgets/class-hsw-wonder-deals.php',
	'widgets/class-hsw-best-sellers.php',
	'widgets/class-hsw-popular.php',
	'widgets/class-hsw-widget-product-breadcrumb.php',
	'widgets/class-hsw-widget-product-gallery.php',
	'widgets/class-hsw-widget-product-trust.php',
	'widgets/class-hsw-widget-product-title.php',
	'widgets/class-hsw-widget-product-variants.php',
	'widgets/class-hsw-widget-product-features.php',
	'widgets/class-hsw-widget-product-purchase.php',
	'widgets/class-hsw-widget-product-tabs.php',
	'widgets/class-hsw-widget-product-accordions.php',
	'widgets/class-hsw-widget-product-sticky.php',
	'widgets/class-hsw-widget-product-page.php',
	'widgets/class-hsw-widget-archive-breadcrumb.php',
	'widgets/class-hsw-widget-archive-header.php',
	'widgets/class-hsw-widget-archive-filters.php',
	'widgets/class-hsw-widget-archive-toolbar.php',
	'widgets/class-hsw-widget-archive-grid.php',
	'widgets/class-hsw-widget-archive-pagination.php',
	'widgets/class-hsw-widget-archive-guide.php',
	'widgets/class-hsw-widget-archive-page.php',
	'widgets/class-hsw-widget-cart-top.php',
	'widgets/class-hsw-widget-cart-banner.php',
	'widgets/class-hsw-widget-cart-items.php',
	'widgets/class-hsw-widget-cart-coupon.php',
	'widgets/class-hsw-widget-cart-summary.php',
	'widgets/class-hsw-widget-cart-page.php',
	'widgets/class-hsw-widget-checkout-top.php',
	'widgets/class-hsw-widget-checkout-address.php',
	'widgets/class-hsw-widget-checkout-shipping.php',
	'widgets/class-hsw-widget-checkout-schedule.php',
	'widgets/class-hsw-widget-checkout-note.php',
	'widgets/class-hsw-widget-checkout-items.php',
	'widgets/class-hsw-widget-checkout-summary.php',
	'widgets/class-hsw-widget-checkout-page.php',
	'widgets/class-hsw-widget-pay-top.php',
	'widgets/class-hsw-widget-pay-recall.php',
	'widgets/class-hsw-widget-pay-methods.php',
	'widgets/class-hsw-widget-pay-coupon.php',
	'widgets/class-hsw-widget-pay-summary.php',
	'widgets/class-hsw-widget-pay-page.php',
	'widgets/class-hsw-widget-blog-hero.php',
	'widgets/class-hsw-widget-blog-tabs.php',
	'widgets/class-hsw-widget-blog-featured.php',
	'widgets/class-hsw-widget-blog-grid.php',
	'widgets/class-hsw-widget-blog-popular.php',
	'widgets/class-hsw-widget-blog-newsletter.php',
	'widgets/class-hsw-widget-blog-tags.php',
	'widgets/class-hsw-widget-blog-page.php',
);

$failed = 0;
foreach ( $files as $rel ) {
	$path = $base . '/' . $rel;
	if ( ! file_exists( $path ) ) {
		echo "MISSING FILE: $rel\n";
		$failed++;
		continue;
	}
	try {
		require $path;
		echo "OK: $rel\n";
	} catch ( \Throwable $e ) {
		echo "CATCHABLE FAIL: $rel — " . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n";
		$failed++;
	}
}

/* Final reflection audit: every HSW class must resolve its parents. */
$declared = array_filter( get_declared_classes(), function ( $c ) {
	return 0 === strpos( $c, 'HSW_' );
} );
foreach ( $declared as $class ) {
	try {
		new ReflectionClass( $class );
	} catch ( \Throwable $e ) {
		echo "REFLECT FAIL: $class — " . $e->getMessage() . "\n";
		$failed++;
	}
}

echo "\n=== " . count( $declared ) . " HSW classes declared ===\n";
echo $failed ? "HARNESS RESULT: $failed FAILURE(S)\n" : "HARNESS RESULT: ALL CLEAN\n";
exit( $failed ? 1 : 0 );
