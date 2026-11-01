<?php
/**
 * P1 batch — the crediting rule, the shop's choice, and the transaction frame.
 *
 * QA-only; the release ZIP excludes tests/ (.gitattributes export-ignore).
 *
 *   php tests/qa/prelive-p1.php
 *   HSW_PRELIVE_TOPUP=<file> HSW_PRELIVE_WALLET=<file>   # older copies => red
 */

require_once __DIR__ . '/prelive-lib.php';

/* ---- WooCommerce stubs so class-hsw-wallet-pay.php can load --------- */

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

/** The account service is only asked for two wallet settings. */
class HSW_Account {
	public static $settings = array();
	public static function instance() { return new self(); }
	public function setting( $path, $fallback = '' ) {
		return array_key_exists( $path, self::$settings ) ? self::$settings[ $path ] : $fallback;
	}
}

class HSW_Helpers {
	public static function fa_num( $v ) { return (string) $v; }
	public static function icon( $n, $c = '', $r = false ) { return ''; }
}

function wc_get_order( $id ) { return false; }
function wc_create_order( $args = array() ) { return false; }

$wallet_file = getenv( 'HSW_PRELIVE_WALLET' );
require_once $wallet_file ? $wallet_file : dirname( __DIR__, 2 ) . '/includes/class-hsw-wallet.php';

$topup_file = getenv( 'HSW_PRELIVE_TOPUP' );
require_once $topup_file ? $topup_file : dirname( __DIR__, 2 ) . '/includes/class-hsw-wallet-pay.php';

/* ---- helpers -------------------------------------------------------- */

function p1_reset() {
	HSW_Prelive_Store::reset();
	HSW_Account::$settings = array();
	$GLOBALS['hsw_tx']     = array();
	$GLOBALS['hsw_caps']   = array();
}

function p1_watch_tx() {
	global $wpdb;
	$wpdb->queries = array();
}

function p1_tx_seen() {
	global $wpdb;
	$out = array();
	foreach ( (array) $wpdb->queries as $sql ) {
		$s = trim( $sql );
		if ( preg_match( '/^\s*(START TRANSACTION|COMMIT|ROLLBACK)\b/i', $s ) ) {
			$out[] = $s;
		}
	}
	return $out;
}

function p1_depth() {
	$prop = new ReflectionProperty( 'HSW_Wallet', 'tx_depth' );
	$prop->setAccessible( true );
	return (int) $prop->getValue();
}

$GLOBALS['hsw_results'] = array();
function p1_case( $id, $title, callable $fn ) {
	try {
		$r = $fn();
	} catch ( Throwable $e ) {
		$r = array( false, 'EXCEPTION: ' . $e->getMessage() );
	}
	$GLOBALS['hsw_results'][] = $r;
	printf( "[%s] %-6s %s — %s\n", $r[0] ? 'PASS' : 'FAIL', $id, $title, $r[1] );
}

/* =====================================================================
 * P1-1 — HSW_Wallet_Topup::credit_amount()
 * ===================================================================== */

p1_case(
	'W1',
	'cap (default): a gateway fee on the order does NOT mint extra credit',
	static function () {
		$got = HSW_Wallet_Topup::credit_amount( 100000, 105000, 'cap' );
		return array( 100000.0 === $got, 'credit=' . $got . ' (intended 100000, paid 105000)' );
	}
);

p1_case(
	'W2',
	'cap: a coupon that lowers the total lowers the credit',
	static function () {
		$got = HSW_Wallet_Topup::credit_amount( 100000, 80000, 'cap' );
		return array( 80000.0 === $got, 'credit=' . $got . ' (never more than received)' );
	}
);

p1_case(
	'W3',
	'exact: the shop choice credits the amount the customer picked',
	static function () {
		$got = HSW_Wallet_Topup::credit_amount( 100000, 80000, 'exact' );
		return array( 100000.0 === $got, 'credit=' . $got . ' (paid 80000, policy exact)' );
	}
);

p1_case(
	'W4',
	'an order with no amount meta falls back to the paid total',
	static function () {
		$got = HSW_Wallet_Topup::credit_amount( 0, 50000, 'cap' );
		return array( 50000.0 === $got, 'credit=' . $got );
	}
);

p1_case(
	'W5',
	'nothing received => nothing credited (both policies)',
	static function () {
		$a = HSW_Wallet_Topup::credit_amount( 100000, 0, 'cap' );
		$b = HSW_Wallet_Topup::credit_amount( 100000, -500, 'exact' );
		return array( 0.0 === $a && 0.0 === $b, 'cap=' . $a . ' exact=' . $b );
	}
);

p1_case(
	'W6',
	'amounts are rounded to two decimals (the ledger column)',
	static function () {
		$got = HSW_Wallet_Topup::credit_amount( 100000.009, 100000.004, 'exact' );
		return array( 100000.01 === $got, 'credit=' . var_export( $got, true ) );
	}
);

p1_case(
	'W7',
	'the policy comes from the shop setting, defaults to cap, filter wins',
	static function () {
		p1_reset();
		$default = HSW_Wallet_Topup::policy();

		HSW_Account::$settings['wallet.topup_policy'] = 'exact';
		$chosen = HSW_Wallet_Topup::policy();

		add_filter( 'hsw_wallet_topup_policy', static function () { return 'cap'; } );
		$filtered = HSW_Wallet_Topup::policy();
		$GLOBALS['hsw_filters'] = array();

		return array( 'cap' === $default && 'exact' === $chosen && 'cap' === $filtered, "default={$default} setting={$chosen} filtered={$filtered}" );
	}
);

/* =====================================================================
 * Points -> wallet is a per-shop choice
 * ===================================================================== */

p1_case(
	'PC1',
	'conversion is on by default, off only on an explicit "no" or filter',
	static function () {
		p1_reset();
		$default = HSW_Wallet::points_convert_allowed();

		HSW_Account::$settings['wallet.points_convert'] = 'no';
		$off = HSW_Wallet::points_convert_allowed();

		HSW_Account::$settings['wallet.points_convert'] = 'yes';
		$on = HSW_Wallet::points_convert_allowed();

		add_filter( 'hsw_wallet_points_convert', static function () { return false; } );
		$filtered = HSW_Wallet::points_convert_allowed();
		$GLOBALS['hsw_filters'] = array();

		return array( true === $default && false === $off && true === $on && false === $filtered, 'default=' . var_export( $default, true ) . " no={$off} yes={$on} filtered={$filtered}" );
	}
);

p1_case(
	'PC2',
	'with conversion off, convert_points() refuses before touching the DB',
	static function () {
		p1_reset();
		p1_watch_tx();
		HSW_Account::$settings['wallet.points_convert'] = 'no';
		$res = HSW_Wallet::convert_points( 41, 100 );
		$code = is_wp_error( $res ) ? $res->get_error_code() : 'NOT AN ERROR: ' . var_export( $res, true );
		return array( 'hsw_points_convert_off' === $code && ! $GLOBALS['hsw_tx'], 'code=' . $code . ' queries=' . count( $GLOBALS['hsw_tx'] ) );
	}
);

/* =====================================================================
 * P1-2 — no transaction frame may stay open
 * ===================================================================== */

p1_case(
	'T1',
	'shutdown closes frames left open by other code and issues ROLLBACK',
	static function () {
		p1_reset();
		p1_watch_tx();
		HSW_Wallet::transaction_begin();
		HSW_Wallet::transaction_begin();
		$open  = p1_depth();
		$closed = HSW_Wallet::release_open_transaction();
		$after = p1_depth();
		$rolled = in_array( 'ROLLBACK', p1_tx_seen(), true );
		return array( 2 === $open && 2 === $closed && 0 === $after && $rolled, "open={$open} closed={$closed} after={$after} rollback=" . var_export( $rolled, true ) );
	}
);

p1_case(
	'T2',
	'a committed transaction is NOT rolled back by the shutdown guard',
	static function () {
		p1_reset();
		p1_watch_tx();
		HSW_Wallet::transaction_begin();
		HSW_Wallet::transaction_commit();
		$closed = HSW_Wallet::release_open_transaction();
		return array( 0 === $closed && ! in_array( 'ROLLBACK', p1_tx_seen(), true ), 'closed=' . $closed . ' tx=' . implode( '|', p1_tx_seen() ) );
	}
);

p1_case(
	'T3',
	'write() closes its own frame when the DB throws mid-write (try/finally)',
	static function () {
		p1_reset();
		global $wpdb;
		$wpdb->queries  = array();
		$wpdb->on_query = static function ( $sql ) {
			/* The first wallet query after BEGIN blows up. */
			if ( false !== stripos( $sql, 'hsw_wallet' ) ) {
				throw new RuntimeException( 'simulated failure mid-write' );
			}
			return null;
		};

		$threw = false;
		try {
			HSW_Wallet::credit( 41, 1000, 'test', '', 0, 'p1-t3' );
		} catch ( RuntimeException $e ) {
			$threw = true;
		}
		$depth = p1_depth();
		$tx    = p1_tx_seen();
		$wpdb->on_query = null;
		return array( $threw && 0 === $depth && in_array( 'ROLLBACK', $tx, true ), 'threw=' . var_export( $threw, true ) . ' depth=' . $depth . ' tx=' . implode( '|', $tx ) );
	}
);

$failed = 0;
foreach ( $GLOBALS['hsw_results'] as $r ) {
	$failed += $r[0] ? 0 : 1;
}
printf( "\np1 batch: %d cases, %d failed\n", count( $GLOBALS['hsw_results'] ), $failed );
exit( $failed ? 1 : 0 );
