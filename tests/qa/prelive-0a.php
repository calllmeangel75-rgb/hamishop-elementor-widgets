<?php
/**
 * ITEM 0a — wallet gateway kill switch (executable).
 * QA-only; the release ZIP excludes tests/ (.gitattributes export-ignore).
 *
 *   php tests/qa/prelive-0a.php                     # working tree
 *   HSW_PRELIVE_PAY=<path to an older file> php …   # reproduce the "before"
 */

require_once __DIR__ . '/prelive-lib.php';
@ini_set( 'error_log', 'NUL' );

$file = getenv( 'HSW_PRELIVE_PAY' );
if ( ! $file ) {
	$file = dirname( __DIR__, 2 ) . '/includes/class-hsw-wallet-pay.php';
}

/* ---- WooCommerce + wallet stubs (the gateway is what is under test) ---- */
class WC_Payment_Gateway {
	public $id = '';
	public $enabled = 'no';
	public $title = '';
	public $method_title = '';
	public $method_description = '';
	public $supports = array();
	public $settings = array();
	public function init_settings() {}
	public function get_option( $key, $default = '' ) { return $this->settings[ $key ] ?? $default; }
	public function get_method_title() { return $this->method_title; }
	public function is_available() { return true; }
	public function process_admin_options() {}
	public function get_return_url( $order = null ) { return 'https://shop.example/thanks/'; }
}
class WC_Order_Item_Fee {}
class HSW_Wallet {
	public static function balance( $user_id = 0 ) { return 250000.0; }
	public static function table() { return 'wp_hsw_wallet_ledger'; }
	public static function debit( $u, $a, $k = 'spend', $n = '', $ac = 0, $r = '' ) { return 1.0; }
	public static function credit( $u, $a, $k = 'admin', $n = '', $ac = 0, $r = '' ) { return 1.0; }
	public static function transaction_begin() {}
	public static function transaction_commit() {}
	public static function transaction_rollback() {}
}
function WC_container_stub() {
	static $wc = null;
	if ( null === $wc ) {
		$wc = new class() {
			public $cart = null;
			public $session = null;
			public $customer = null;
			public function payment_gateways() { return null; }
		};
	}
	return $wc;
}
$GLOBALS['hsw_wc'] = WC_container_stub();

require_once $file;

if ( function_exists( 'hsw_register_wallet_gateway' ) ) {
	hsw_register_wallet_gateway();
}

$results = array();
function g_case( $id, $title, $fn ) {
	global $results;
	try {
		$r = $fn();
	} catch ( Throwable $e ) {
		$r = array( false, 'EXCEPTION ' . $e->getMessage() );
	}
	$results[] = $r;
	printf( "[%s] %-5s %s — %s\n", $r[0] ? 'PASS' : 'FAIL', $id, $title, $r[1] );
}

function g_new_gateway() {
	return new HSW_Wallet_Gateway();
}

function g_reset( $opts = array() ) {
	HSW_Prelive_Store::reset();
	foreach ( $opts as $k => $v ) {
		update_option( $k, $v );
	}
	$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
}

g_case(
	'0.1',
	'default (option absent): wallet gateway is not enabled',
	static function () {
		g_reset();
		$g = g_new_gateway();
		return array( 'yes' !== $g->enabled, 'enabled=' . $g->enabled );
	}
);

g_case(
	'0.2',
	'option on + WooCommerce toggle on: gateway enabled and available',
	static function () {
		g_reset( array( 'hsw_wallet_gateway_on' => 'yes' ) );
		wp_set_current_user( 7 );
		$g = g_new_gateway();
		return array( 'yes' === $g->enabled && $g->is_available(), 'enabled=' . $g->enabled . ' available=' . ( $g->is_available() ? 'yes' : 'no' ) );
	}
);

g_case(
	'0.4',
	'is_globally_enabled() exists and follows the option (used by pay-methods)',
	static function () {
		if ( ! method_exists( 'HSW_Wallet_Gateway', 'is_globally_enabled' ) ) {
			return array( false, 'helper missing — the widget cannot hide the switch' );
		}
		if ( defined( 'HSW_DISABLE_WALLET' ) && HSW_DISABLE_WALLET ) {
			return array( false, 'constant defined too early in this process' );
		}
		g_reset();
		$off = HSW_Wallet_Gateway::is_globally_enabled();
		update_option( 'hsw_wallet_gateway_on', 'yes' );
		$on = HSW_Wallet_Gateway::is_globally_enabled();
		return array( false === $off && true === $on, 'off=' . var_export( $off, true ) . ' on=' . var_export( $on, true ) );
	}
);

g_case(
	'0.3',
	'HSW_DISABLE_WALLET overrides the option',
	static function () {
		if ( ! defined( 'HSW_DISABLE_WALLET' ) ) {
			define( 'HSW_DISABLE_WALLET', true );
		}
		g_reset( array( 'hsw_wallet_gateway_on' => 'yes' ) );
		wp_set_current_user( 7 );
		$g = g_new_gateway();
		return array( 'yes' !== $g->enabled && ! $g->is_available(), 'enabled=' . $g->enabled );
	}
);

$failed = 0;
foreach ( $results as $r ) {
	$failed += $r[0] ? 0 : 1;
}
printf( "\n0a: %d cases, %d failed\n", count( $results ), $failed );
exit( $failed ? 1 : 0 );
