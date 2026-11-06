<?php
/** Regression probe for cart variation labels. Stubs only; no WordPress DB. */
define( 'ABSPATH', __DIR__ );
abstract class HSW_Widget_Base {}
class WP_Term {
	public $name;
	public $term_id;
	public function __construct( $name, $id ) { $this->name = $name; $this->term_id = $id; }
}
function taxonomy_exists( $name ) { return in_array( $name, array( 'pa_رنگ', 'pa_size' ), true ); }
function sanitize_title( $name ) { return strtolower( rawurlencode( rawurldecode( $name ) ) ); }
function get_term_by( $field, $value, $taxonomy ) {
	if ( 'slug' !== $field ) { return false; }
	$terms = array(
		'pa_رنگ' => array( '%d9%82%d8%b1%d9%85%d8%b2' => 'قرمز', 'مشکی' => 'مشکی' ),
		'pa_size' => array( 'large' => 'Large' ),
	);
	return isset( $terms[ $taxonomy ][ $value ] ) ? new WP_Term( $terms[ $taxonomy ][ $value ], 1 ) : false;
}
function get_term_meta( $id, $key, $single ) { return ''; }
require __DIR__ . '/../../includes/class-hsw-cart-base.php';
class HSW_QA_Variation_Labels extends HSW_Cart_Base {
	public function chips( $variation ) { return $this->item_chips( array( 'variation' => $variation ), null ); }
}
$probe = new HSW_QA_Variation_Labels();
$tests = array(
	array( 'encoded taxonomy and term', array( 'attribute_pa_%d8%b1%d9%86%da%af' => '%d9%82%d8%b1%d9%85%d8%b2' ), 'قرمز' ),
	array( 'encoded taxonomy with decoded term lookup', array( 'attribute_pa_%d8%b1%d9%86%da%af' => '%d9%85%d8%b4%da%a9%db%8c' ), 'مشکی' ),
	array( 'ordinary taxonomy', array( 'attribute_pa_size' => 'large' ), 'Large' ),
	array( 'custom value unchanged', array( 'attribute_custom' => '50% off' ), '50% off' ),
	array( 'missing term unchanged', array( 'attribute_pa_size' => 'unknown' ), 'unknown' ),
);
$failed = 0;
foreach ( $tests as $test ) {
	$chips = $probe->chips( $test[1] );
	$actual = isset( $chips[0]['label'] ) ? $chips[0]['label'] : null;
	$ok = $test[2] === $actual;
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $test[0] . PHP_EOL;
	if ( ! $ok ) { ++$failed; }
}
exit( $failed ? 1 : 0 );
