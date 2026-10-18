<?php
/**
 * Behavioral test: product-detail demo data must NEVER leak to the live
 * front-end. product_or_demo() must return demo=true ONLY when the Elementor
 * editor/preview is active AND no real product exists.
 *
 * Scenarios:
 *   A) Live front-end, widget on a page without product  → [ null, false ]  (render nothing)
 *   B) Elementor editor, no product context              → [ null, true  ]  (design demo OK)
 *   C) Any context WITH a real product (incl. editor)    → [ WC_Product, false ] (real data)
 *
 * Usage: /tmp/php tests/behavior-demo-guard.php
 */

error_reporting( E_ALL );
define( 'ABSPATH', '/home/z/my-project/' );

/* ---- Minimal WordPress/WooCommerce function stubs ---- */
$GLOBALS['hsw_test_post_id'] = 0;

function get_the_ID() {
        return $GLOBALS['hsw_test_post_id'];
}
function get_post_type( $id = null ) {
        return $GLOBALS['hsw_test_post_type'] ?? 'page';
}
function wc_get_product( $id = null ) {
        return $GLOBALS['hsw_test_product'] ?? null;
}
function is_shop() {
        return false;
}
function is_product_taxonomy() {
        return false;
}
function wp_doing_ajax() {
        return false;
}

class WC_Product {}

/* ---- Elementor stubs (same as harness) ---- */
require __DIR__ . '/stubs.php';

/* ---- Plugin bases + the three guarded widgets ---- */
$base = dirname( __DIR__ );
require $base . '/includes/class-hsw-widget-base.php';
require $base . '/includes/class-hsw-product-base.php';
require $base . '/widgets/class-hsw-widget-product-gallery.php';
require $base . '/widgets/class-hsw-widget-product-purchase.php';
require $base . '/widgets/class-hsw-widget-product-breadcrumb.php';

$el = new \Elementor\Plugin(); // stub constructor sets Plugin::$instance.

$failed = 0;
function check( $label, $cond ) {
        global $failed;
        echo ( $cond ? 'PASS' : 'FAIL' ) . ": $label\n";
        if ( ! $cond ) {
                $failed++;
        }
}

/**
 * Call the protected product_or_demo() on a constructor-less widget instance.
 */
function probe( $class ) {
        $rc   = new ReflectionClass( $class );
        $inst = $rc->newInstanceWithoutConstructor();
        $m    = $rc->getMethod( 'product_or_demo' );
        $m->setAccessible( true );
        return $m->invoke( $inst );
}

/* Scenario A: live front-end, no product anywhere. */
$el->editor  = null;
$el->preview = null;
list( $product, $demo ) = probe( 'HSW_Widget_Product_Purchase' );
check( 'A front-end / no product → demo=false', ( null === $product && false === $demo ) );

/* Scenario B: Elementor editor active, still no product. */
$el->editor = new class {
        public function is_edit_mode() {
                return true;
        }
};
list( $product, $demo ) = probe( 'HSW_Widget_Product_Gallery' );
check( 'B editor / no product → demo=true (design preview)', ( null === $product && true === $demo ) );

/* Scenario C: real product exists — even in the editor, real data wins. */
$GLOBALS['hsw_test_product'] = new WC_Product();
$GLOBALS['hsw_test_post_id'] = 123;
$GLOBALS['hsw_test_post_type'] = 'product';
list( $product, $demo ) = probe( 'HSW_Widget_Product_Breadcrumb' );
check( 'C product exists (editor open) → demo=false, real product', ( $product instanceof WC_Product && false === $demo ) );

echo $failed ? "BEHAVIOR RESULT: $failed FAILURE(S)\n" : "BEHAVIOR RESULT: ALL CLEAN\n";
exit( $failed ? 1 : 0 );
