<?php
/** Small isolated guard test; the WooCommerce integration scenario tests amounts. */
define( 'ABSPATH', __DIR__ );
abstract class HSW_Widget_Base {}
class WC_Order {}
class Fake_Cart {
	public $empty = false;
	public $calculations = 0;
	public function is_empty() { return $this->empty; }
	public function calculate_totals() { ++$this->calculations; }
}
$wc = (object) array( 'cart' => new Fake_Cart() );
function WC() { global $wc; return $wc; }
function wc_get_cart_url() { return ''; }
require __DIR__ . '/../../includes/class-hsw-cart-base.php';
class Display_Refresh_Probe extends HSW_Cart_Base {
	public $demo = false;
	public $order = false;
	protected function is_demo() { return $this->demo; }
	protected function paying_order() { return $this->order; }
	public function refresh() { $this->refresh_display_cart(); }
}
$probe = new Display_Refresh_Probe();
$probe->refresh();
if ( 1 !== $wc->cart->calculations ) { exit( 1 ); }
$probe->demo = true;
$probe->refresh();
$probe->demo = false;
$wc->cart->empty = true;
$probe->refresh();
$wc->cart->empty = false;
$probe->order = new WC_Order();
$probe->refresh();
if ( 1 !== $wc->cart->calculations ) { exit( 1 ); }
$wc->cart = null;
$probe->refresh();
echo "Display refresh guard: PASS\n";
