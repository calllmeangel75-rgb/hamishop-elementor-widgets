<?php
/** Display-only payment indicator regression; no WordPress database. */
define( 'ABSPATH', __DIR__ );
abstract class HSW_Pay_Base {}
require __DIR__ . '/../../includes/class-hsw-complete-base.php';
class HSW_QA_Completion_Indicator extends HSW_Complete_Base {
	public function show_paid( $order ) { return $this->completion_show_paid( $order ); }
}
class HSW_QA_Indicator_Order {
	private $method;
	private $total;
	private $paid;
	public function __construct( $method, $total, $paid ) {
		$this->method = $method; $this->total = $total; $this->paid = $paid;
	}
	public function get_payment_method() { return $this->method; }
	public function get_total() { return $this->total; }
	public function is_paid() { return $this->paid; }
}
$tests = array(
	array( 'COD processing must not assert payment', 'cod', 25000000, true, false ),
	array( 'COD unpaid', 'cod', 25000000, false, false ),
	array( 'paid online gateway', 'card', 100, true, true ),
	array( 'unpaid online gateway', 'card', 100, false, false ),
	array( 'zero total keeps WooCommerce behavior', 'cod', 0, true, true ),
);
$probe = new HSW_QA_Completion_Indicator();
$fail = 0;
foreach ( $tests as $test ) {
	$order = new HSW_QA_Indicator_Order( $test[1], $test[2], $test[3] );
	$ok = $test[4] === $probe->show_paid( $order );
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $test[0] . PHP_EOL;
	if ( ! $ok ) { ++$fail; }
}
exit( $fail ? 1 : 0 );
