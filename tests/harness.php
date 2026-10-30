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

$base = dirname( __DIR__ ); // plugin root — portable

/* The main plugin file defines these; without them a widget that requires a
 * trait at load time (product-page) reports a false failure. */
define( 'HSW_DIR', $base . '/' );
define( 'HSW_URL', 'http://example.test/wp-content/plugins/hamishop-elementor-widgets/' );
define( 'HSW_VERSION', 'harness' );

require __DIR__ . '/stubs.php';

/*
 * A handful of WordPress functions are called at *file scope* by modules that
 * must decide early (the wallet gateway asks `did_action('woocommerce_loaded')`
 * so it can register before WooCommerce boots). Only the existence matters
 * here — the harness checks that the file compiles and loads, not that the
 * hook fires.
 */
if ( ! function_exists( 'did_action' ) ) {
	function did_action( $hook ) {
		return 0;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$args ) {
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( ...$args ) {
		return true;
	}
}

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
	'includes/class-hsw-complete-route.php',
	/* ---- elementor/widgets/register: Elementor bases first ---- */
	'includes/trait-hsw-style-kit.php',
	'includes/class-hsw-widget-base.php',
	'includes/class-hsw-product-base.php',
	'includes/class-hsw-archive-base.php',
	'includes/class-hsw-cart-base.php',
	'includes/class-hsw-checkout-base.php',
	'includes/class-hsw-pay-base.php',
	'includes/class-hsw-blog-base.php',
	'includes/class-hsw-complete-base.php',
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
	'widgets/class-hsw-widget-complete-hero.php',
	'widgets/class-hsw-widget-complete-order.php',
	'widgets/class-hsw-widget-complete-delivery.php',
	'widgets/class-hsw-widget-complete-steps.php',
	'widgets/class-hsw-widget-complete-actions.php',
	'widgets/class-hsw-widget-complete-page.php',
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
$loaded = array();
foreach ( $files as $rel ) {
	$path = $base . '/' . $rel;
	if ( ! file_exists( $path ) ) {
		echo "MISSING FILE: $rel\n";
		$failed++;
		continue;
	}
	try {
		require_once $path;
		$loaded[] = $rel;
		echo "OK: $rel\n";
	} catch ( \Throwable $e ) {
		$loaded[] = $rel;
		echo "CATCHABLE FAIL: $rel — " . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n";
		$failed++;
	}
}

/*
 * ---------------------------------------------------------------------------
 * Pass 2 — everything the hand-written list forgot.
 *
 * The list above carries the order that matters (Elementor bases before the
 * widgets that extend them). It used to be the whole story, which meant a new
 * file could ship without ever being compile-checked here: at the time this
 * pass was added, 42 of 106 files were silently skipped. The remainder is now
 * loaded dependency-first (traits → bases → rest) so a missing base or a
 * broken trait surfaces in CI instead of on a customer's site.
 * ---------------------------------------------------------------------------
 */
$all_php = array_merge(
	glob( $base . '/includes/*.php' ) ?: array(),
	glob( $base . '/widgets/*.php' ) ?: array()
);

$remaining = array();
foreach ( $all_php as $abs ) {
	$rel = str_replace( $base . '/', '', $abs );
	if ( in_array( $rel, $loaded, true ) ) {
		continue;
	}
	$remaining[] = $rel;
}

usort(
	$remaining,
	function ( $a, $b ) {
		$rank = function ( $rel ) {
			if ( false !== strpos( $rel, '/trait-' ) ) {
				return 0; // traits first: classes may `use` them at declaration.
			}
			if ( preg_match( '/-base\.php$/', $rel ) ) {
				return 1;
			}
			return 2;
		};
		$ra = $rank( $a );
		$rb = $rank( $b );
		return $ra === $rb ? strcmp( $a, $b ) : $ra - $rb;
	}
);

if ( $remaining ) {
	echo "\n-- pass 2: " . count( $remaining ) . " file(s) not in the explicit list --\n";
}

foreach ( $remaining as $rel ) {
	try {
		require_once $base . '/' . $rel;
		$loaded[] = $rel;
		echo "AUTO: $rel\n";
	} catch ( \Throwable $e ) {
		$loaded[] = $rel;
		echo "CATCHABLE FAIL: $rel — " . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n";
		$failed++;
	}
}

$covered = count( $loaded );
$expected = count( $all_php );
echo "\n=== files loaded: {$covered}/{$expected} ===\n";
if ( $covered < $expected ) {
	echo "COVERAGE GAP: " . ( $expected - $covered ) . " file(s) on disk were never loaded\n";
	$failed++;
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
