<?php
/**
 * P1 batch — top-up amount, transaction leak, wallet REST throttle.
 *
 * QA-only; the release ZIP excludes tests/ (.gitattributes export-ignore).
 *
 *   php tests/qa/prelive-p1.php
 *   HSW_PRELIVE_WALLET=<file> HSW_PRELIVE_TOPUP=<file>  # older copies => red
 */

require_once __DIR__ . '/prelive-lib.php';

/* ---- wallet-shaped stubs ------------------------------------------- */

$GLOBALS['hsw_credits'] = array();

class HSW_Helpers {
	public static function fa_num( $v ) { return (string) $v; }
	public static function icon( $n, $c = '', $r = false ) { return ''; }
}

/**
 * A fake order: meta bag + a fixed total, which is all settle() reads.
 */
class HSW_Prelive_Order {
	public $id;
	public $total;
	public $meta = array();
	public $number = '1001';
	public function __construct( $id, $total, $meta = array() ) {
		$this->id    = (int) $id;
		$this->total = (float) $total;
		$this->meta  = $meta;
	}
	public function get_id() { return $this->id; }
	public function get_total( $ctx = 'view' ) { return $this->total; }
	public function get_meta( $key = '', $single = true ) { return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : ''; }
	public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
	public function save_meta_data() {}
	public function get_order_number() { return $this->number; }
	public function get_checkout_payment_url() { return 'https://shop.example/order-pay/' . $this->id . '/'; }
}

$GLOBALS['hsw_orders'] = array();
function wc_get_order( $id ) {
	return isset( $GLOBALS['hsw_orders'][ (int) $id ] ) ? $GLOBALS['hsw_orders'][ (int) $id ] : false;
}

/* ---- classes under test -------------------------------------------- */

$wallet_file = getenv( 'HSW_PRELIVE_WALLET' );
require_once $wallet_file ? $wallet_file : dirname( __DIR__, 2 ) . '/includes/class-hsw-wallet.php';

/* Only the top-up class is needed from the gateway file, and it is at the tail. */
$topup_file = getenv( 'HSW_PRELIVE_TOPUP' );
$src        = file_get_contents( $topup_file ? $topup_file : dirname( __DIR__, 2 ) . '/includes/class-hsw-wallet-pay.php' );
$cut        = strpos( $src, 'class HSW_Wallet_Topup' );
eval( '?>' . substr( $src, $cut ) );

/* ---- helpers -------------------------------------------------------- */

function p1_reset() {
	HSW_Prelive_Store::reset();
	$GLOBALS['hsw_credits'] = array();
	$GLOBALS['hsw_orders']  = array();
	$GLOBALS['hsw_queries'] = array();
	$GLOBALS['hsw_tx']      = array();
}

function p1_order( $id, $total, $meta = array() ) {
	$GLOBALS['hsw_orders'][ $id ] = new HSW_Prelive_Order( $id, $total, $meta );
	return $id;
}

/**
 * The ledger read in settle() must say "not credited yet" so the credit runs.
 */
function p1_arm_wpdb() {
	global $wpdb;
	$wpdb->on_query = static function ( $sql ) use ( $wpdb ) {
		$GLOBALS['hsw_queries'][] = $sql;
		if ( false !== strpos( $sql, 'SELECT' ) && false !== strpos( $sql, 'ref' ) ) {
			return null; // no existing row
		}
		if ( false !== stripos( $sql, 'START TRANSACTION' ) || false !== stripos( $sql, 'COMMIT' ) || false !== stripos( $sql, 'ROLLBACK' ) ) {
			$GLOBALS['hsw_tx'][] = trim( $sql );
		}
		return null;
	};
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
 * P1-1 — credit what was bought, capped by what was paid
 * ===================================================================== */

p1_case(
	'W1',
	'gateway fee on the order does NOT inflate the credit',
	static function () {
		p1_reset();
		p1_arm_wpdb();
		$id = p1_order( 7001, 105000, array( '_hsw_wallet_topup_user' => 41, '_hsw_wallet_topup_amount' => 100000 ) );
		HSW_Wallet_Topup::instance()->settle( $id );
		$got = isset( $GLOBALS['hsw_credits'][0] ) ? $GLOBALS['hsw_credits'][0] : null;
		return array( 100000.0 === (float) $got, 'credited=' . var_export( $got, true ) . ' (want 100000, order total was 105000)' );
	}
);

p1_case(
	'W2',
	'a coupon that lowers the total lowers the credit (never credit more than paid)',
	static function () {
		p1_reset();
		p1_arm_wpdb();
		$id = p1_order( 7002, 80000, array( '_hsw_wallet_topup_user' => 41, '_hsw_wallet_topup_amount' => 100000 ) );
		HSW_Wallet_Topup::instance()->settle( $id );
		$got = isset( $GLOBALS['hsw_credits'][0] ) ? $GLOBALS['hsw_credits'][0] : null;
		return array( 80000.0 === (float) $got, 'credited=' . var_export( $got, true ) . ' (want 80000 = what was paid)' );
	}
);

p1_case(
	'W3',
	'an order without the amount meta still credits its total (no regression)',
	static function () {
		p1_reset();
		p1_arm_wpdb();
		$id = p1_order( 7003, 50000, array( '_hsw_wallet_topup_user' => 41 ) );
		HSW_Wallet_Topup::instance()->settle( $id );
		$got = isset( $GLOBALS['hsw_credits'][0] ) ? $GLOBALS['hsw_credits'][0] : null;
		return array( 50000.0 === (float) $got, 'credited=' . var_export( $got, true ) );
	}
);

/* =====================================================================
 * P1-2 — an exception must not leave the wallet transaction open
 * ===================================================================== */

p1_case(
	'T1',
	'throw inside the money write => frame closed, ROLLBACK issued',
	static function () {
		p1_reset();
		global $wpdb;
		$wpdb->on_query = static function ( $sql ) {
			$GLOBALS['hsw_tx'][] = trim( $sql );
			if ( false !== stripos( $sql, 'INSERT INTO' ) ) {
				throw new RuntimeException( 'boom' );
			}
			return null;
		};
		HSW_Wallet::instance();
		$depth_before = HSW_Prelive_Ref::tx_depth();
		$threw        = false;
		try {
			HSW_Wallet::credit( 41, 1000, 'test', '', 0, 'p1-t1' );
		} catch ( RuntimeException $e ) {
			$threw = true;
		}
		$depth_after = HSW_Prelive_Ref::tx_depth();
		$rolled      = in_array( 'ROLLBACK', $GLOBALS['hsw_tx'], true );
		return array( $threw && $depth_after === $depth_before && $rolled, sprintf( 'threw=%s depth %d=>%d rollback=%s', $threw ? 'y' : 'n', $depth_before, $depth_after, $rolled ? 'y' : 'n' ) );
	}
);

p1_case(
	'T2',
	'shutdown closes a frame left open by other code',
	static function () {
		p1_reset();
		global $wpdb;
		$GLOBALS['hsw_tx'] = array();
		$wpdb->on_query    = static function ( $sql ) { $GLOBALS['hsw_tx'][] = trim( $sql ); return null; };
		HSW_Wallet::transaction_begin();
		HSW_Wallet::transaction_begin();
		$open = HSW_Prelive_Ref::tx_depth();
		$closed = HSW_Wallet::release_open_transaction();
		$after = HSW_Prelive_Ref::tx_depth();
		return array( 2 === $open && 2 === $closed && 0 === $after, "open={$open} closed={$closed} after={$after}" );
	}
);

/* =====================================================================
 * P1-6 — wallet REST routes are rate limited per user
 * ===================================================================== */

p1_case(
	'R1',
	'11th top-up request in the window is refused with 429',
	static function () {
		p1_reset();
		p1_arm_wpdb();
		wp_set_current_user( 41 );
		$wallet = HSW_Wallet::instance();

		$codes = array();
		for ( $i = 0; $i < 11; $i++ ) {
			$req  = new HSW_Prelive_REST( array( 'amount' => 1000, 'note' => '' ) );
			$res  = $wallet->rest_topup( $req );
			$code = is_wp_error( $res ) ? ( (array) $res->get_error_data() )['status'] ?? 0 : ( method_exists( $res, 'get_status' ) ? $res->get_status() : 200 );
			$codes[] = (int) $code;
		}
		$blocked = in_array( 429, $codes, true );
		return array( $blocked, 'codes=' . implode( ',', $codes ) );
	}
);

/** Tiny stand-in for WP_REST_Request (only get_param is used). */
class HSW_Prelive_REST {
	private $params;
	public function __construct( $params ) { $this->params = $params; }
	public function get_param( $key ) { return isset( $this->params[ $key ] ) ? $this->params[ $key ] : ''; }
}

/* ---- reflection helper (private static $tx_depth) ------------------- */

class HSW_Prelive_Ref {
	public static function tx_depth() {
		$prop = new ReflectionProperty( 'HSW_Wallet', 'tx_depth' );
		$prop->setAccessible( true );
		return (int) $prop->getValue();
	}
}

$failed = 0;
foreach ( $GLOBALS['hsw_results'] as $r ) {
	$failed += $r[0] ? 0 : 1;
}
printf( "\np1 batch: %d cases, %d failed\n", count( $GLOBALS['hsw_results'] ), $failed );
exit( $failed ? 1 : 0 );
