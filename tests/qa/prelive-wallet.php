<?php
/**
 * Wallet & points — the three money defects found by using the tab on a live site.
 *
 * QA-only; the release ZIP excludes tests/ (.gitattributes export-ignore). Run:
 *   php tests/qa/prelive-wallet.php
 *
 * Red before 1.11.191:
 *   1.1/1.2  the «فعال بودن کیف پول» switch wrote `wallet.enabled`, but every
 *            wallet surface reads `hsw_wallet_gateway_on` — nothing in the panel
 *            wrote that option, so the wallet could never be switched on.
 *   2.1      wallet top-ups counted in `total_spent`, so every top-up earned
 *            10% of its own amount back as convertible points (free money).
 */

require_once __DIR__ . '/prelive-lib.php';
@ini_set( 'error_log', 'NUL' );

/* ---- WooCommerce surface these cases need ---------------------------- */

if ( ! class_exists( 'WC_Order' ) ) {
	class WC_Order {
		public $total = 0.0;
		public $meta  = array();
		public function __construct( $total = 0.0, $meta = array() ) {
			$this->total = (float) $total;
			$this->meta  = $meta;
		}
		public function get_total() { return $this->total; }
		public function get_meta( $key ) { return $this->meta[ $key ] ?? ''; }
	}
}

$GLOBALS['hsw_cust_spent'] = 0.0;
$GLOBALS['hsw_cust_orders'] = array();

if ( ! function_exists( 'wc_get_customer_total_spent' ) ) {
	function wc_get_customer_total_spent( $user_id ) { return (float) $GLOBALS['hsw_cust_spent']; }
}
if ( ! function_exists( 'wc_get_orders' ) ) {
	function wc_get_orders( $args = array() ) { return $GLOBALS['hsw_cust_orders']; }
}
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() { return false; }
}
if ( ! function_exists( 'wc_get_order' ) ) {
	function wc_get_order( $id ) { return null; }
}

/* HSW_PRELIVE_ACCOUNT=<path> runs the same cases against an older copy. */
require_once getenv( 'HSW_PRELIVE_ACCOUNT' ) ?: dirname( __DIR__, 2 ) . '/includes/class-hsw-account.php';

$GLOBALS['hsw_results'] = array();

function w_case( $id, $title, callable $fn ) {
	try {
		$out = $fn();
	} catch ( Throwable $e ) {
		$out = array( false, 'EXCEPTION: ' . $e->getMessage() );
	}
	$ok = (bool) $out[0];
	$GLOBALS['hsw_results'][] = array( $ok, $id, $title );
	printf( "[%s] %s  %s — %s\n", $ok ? 'PASS' : 'FAIL', $id, $title, $out[1] );
}

function w_reset() {
	HSW_Prelive_Store::reset();
	$GLOBALS['hsw_cust_spent']  = 0.0;
	$GLOBALS['hsw_cust_orders'] = array();
	delete_option( 'hsw_wallet_gateway_on' );
}

/* ---------------------------------------------------------------------
 * 1) The switch in the panel must reach the gate every surface reads
 * ------------------------------------------------------------------ */

w_case(
	'1.1',
	'turning the wallet switch off writes the gate as "no"',
	static function () {
		w_reset();
		update_option( 'hsw_account_settings', array( 'wallet' => array( 'enabled' => 'yes' ) ) );
		update_option( 'hsw_wallet_gateway_on', 'yes' );

		$out = HSW_Account::instance()->sanitize_settings( array( 'wallet' => array( 'enabled' => 'no' ) ) );
		$gate = get_option( 'hsw_wallet_gateway_on' );

		return array( 'no' === $gate && 'no' === $out['wallet']['enabled'], 'gate=' . var_export( $gate, true ) );
	}
);

w_case(
	'1.2',
	'turning it back on writes the gate as "yes"',
	static function () {
		w_reset();
		update_option( 'hsw_account_settings', array( 'wallet' => array( 'enabled' => 'no' ) ) );

		HSW_Account::instance()->sanitize_settings( array( 'wallet' => array( 'enabled' => 'yes' ) ) );
		$gate = get_option( 'hsw_wallet_gateway_on' );

		return array( 'yes' === $gate, 'gate=' . var_export( $gate, true ) );
	}
);

w_case(
	'1.3',
	'saving the screen without touching the switch leaves the gate alone',
	static function () {
		w_reset();
		/* The stored value and the submitted value agree — no explicit toggle. */
		update_option( 'hsw_account_settings', array( 'wallet' => array( 'enabled' => 'yes' ) ) );

		HSW_Account::instance()->sanitize_settings( array( 'wallet' => array( 'enabled' => 'yes', 'min_topup' => 70000 ) ) );
		$gate = get_option( 'hsw_wallet_gateway_on' );

		return array( false === $gate, 'gate=' . var_export( $gate, true ) );
	}
);

/* ---------------------------------------------------------------------
 * 2) Top-ups are not purchases
 * ------------------------------------------------------------------ */

w_case(
	'2.1',
	'a wallet top-up does not earn loyalty points',
	static function () {
		w_reset();
		update_option( 'hsw_account_settings', array( 'loyalty' => array( 'enabled' => 'yes', 'rate' => 10000 ) ) );

		/* Only order: a 200,000 top-up, which WooCommerce counts as spent. */
		$GLOBALS['hsw_cust_spent']  = 200000.0;
		$GLOBALS['hsw_cust_orders'] = array( new WC_Order( 200000.0, array( '_hsw_wallet_topup_user' => 7 ) ) );

		$points = HSW_Account::instance()->loyalty_points( 7 );
		return array( 0 === $points, 'points=' . $points );
	}
);

w_case(
	'2.2',
	'a real purchase still earns them, top-up or not',
	static function () {
		w_reset();
		update_option( 'hsw_account_settings', array( 'loyalty' => array( 'enabled' => 'yes', 'rate' => 10000 ) ) );

		$GLOBALS['hsw_cust_spent']  = 29870000.0; // 200,000 top-up + 29,670,000 purchase
		$GLOBALS['hsw_cust_orders'] = array(
			new WC_Order( 200000.0, array( '_hsw_wallet_topup_user' => 7 ) ),
			new WC_Order( 29670000.0, array() ),
		);

		$points = HSW_Account::instance()->loyalty_points( 7 );
		return array( 2967 === $points, 'points=' . $points );
	}
);

$failed = 0;
foreach ( $GLOBALS['hsw_results'] as $r ) {
	$failed += $r[0] ? 0 : 1;
}
printf( "\nwallet: %d cases, %d failed\n", count( $GLOBALS['hsw_results'] ), $failed );
exit( $failed ? 1 : 0 );
