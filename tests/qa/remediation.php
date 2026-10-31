<?php
/**
 * Remediation test suite — the independent review findings, as executable
 * tests. Self-contained: brings its own faithful WP stubs (wpdb semantics,
 * map_meta_cap, hooks) and drives the REAL plugin classes against a scratch
 * database. Run:
 *
 *   HSW_QA_DSN="mysql:host=127.0.0.1;port=3306;dbname=hsw_qa" \
 *   HSW_QA_USER=root HSW_QA_PASS=root php tests/qa/remediation.php
 *
 * The scratch database is dropped and recreated on every run.
 */

error_reporting( E_ALL & ~E_DEPRECATED ); // the WC_Payment_Gateway stub still invites dynamic properties.

/* The plugin files begin with `defined( 'ABSPATH' ) || exit;`. */
define( 'ABSPATH', sys_get_temp_dir() . '/hsw_qa_ABSPATH/' );

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );
}

/* ---------------------------------------------------------------------
 * Minimal WordPress emulation (same semantics as core).
 * ------------------------------------------------------------------- */

class HSW_QA_WPDB {
	public $prefix       = 'wp_';
	public $last_error   = '';
	public $insert_id    = 0;
	public $rows_affected = 0;
	public $link;
	public $usermeta = 'wp_usermeta';

	public function __construct( $dsn, $user, $pass ) {
		mysqli_report( MYSQLI_REPORT_OFF );
		/* Parse host/port out of the DSN so CI can point each leg at its own
		 * engine (MySQL 8 on 3306, MariaDB on 3307). */
		$host = '127.0.0.1';
		$port = 3306;
		if ( preg_match( '/host=([^;]+)/', (string) $dsn, $m ) ) {
			$host = trim( $m[1] );
		}
		if ( preg_match( '/port=([^;]+)/', (string) $dsn, $m ) ) {
			$port = (int) $m[1];
		}
		$this->link = mysqli_connect( $host, $user, $pass, null, $port );
		if ( ! $this->link ) {
			fwrite( STDERR, 'DB connect failed: ' . mysqli_connect_error() . "\n" );
			exit( 1 );
		}
		mysqli_set_charset( $this->link, 'utf8mb4' );
		mysqli_select_db( $this->link, 'hsw_qa' );
	}

	/**
	 * Crash injection: when HSW_QA_DIE_PATTERN is set, the process exits(0)
	 * right BEFORE the Nth query matching the pattern (HSW_QA_DIE_AT) — the
	 * same thing a kill -9 between two statements does to production. The
	 * open transaction dies with the connection and InnoDB rolls it back.
	 */
	public static $die_hits = 0;

	public function get_charset_collate() {
		return ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
	}

	public function esc( $s ) {
		return mysqli_real_escape_string( $this->link, (string) $s );
	}

	public function prepare( $query, ...$args ) {
		if ( null === $query ) {
			return null;
		}
		$allowed = '(?:[1-9][0-9]*[$])?[-+0-9]*(?: |0|\'.)?[-+0-9]*(?:\.[0-9]+)?';
		$query   = str_replace( "'%s'", '%s', $query );
		$query   = str_replace( '"%s"', '%s', $query );
		$query   = preg_replace( "/%(?:%|$|(?!($allowed)?[sdfFi]))/", '%%\\1', $query );
		$split   = preg_split( "/(^|[^%]|(?:%%)+)(%(?:$allowed)?[sdfFi])/", $query, -1, PREG_SPLIT_DELIM_CAPTURE );
		$count   = count( $split );
		if ( isset( $args[0] ) && is_array( $args[0] ) && 1 === count( $args ) ) {
			$args = $args[0];
		}
		$out = '';
		$key = 2;
		while ( $key < $count ) {
			$ph   = $split[ $key ];
			$type = substr( $ph, -1 );
			$fmt  = substr( $ph, 1, -1 );
			if ( 'f' === $type ) {
				$type = 'F';
				$ph   = '%' . $fmt . $type;
			}
			if ( 'd' !== $type && 'F' !== $type ) {
				$ph = "'%" . $fmt . "s'";
			}
			$out .= $split[ $key - 2 ] . $split[ $key - 1 ] . $ph;
			$key += 3;
		}
		$out .= $split[ $count - 1 ];
		$esc = array();
		foreach ( $args as $v ) {
			$esc[] = ( is_int( $v ) || is_float( $v ) ) ? $v : $this->esc( $v );
		}
		return @vsprintf( $out, $esc );
	}

	public function query( $sql ) {
		$pattern = getenv( 'HSW_QA_DIE_PATTERN' );
		if ( $pattern && preg_match( $pattern, (string) $sql ) ) {
			self::$die_hits++;
			if ( self::$die_hits >= (int) getenv( 'HSW_QA_DIE_AT' ) ) {
				file_put_contents( sys_get_temp_dir() . '/hsw_qa_crash.txt', 'died before query: ' . substr( (string) $sql, 0, 160 ) . "\n" );
				exit( 0 );
			}
		}
		$this->last_error = '';
		$res              = @mysqli_query( $this->link, $sql );
		if ( false === $res ) {
			$this->last_error = mysqli_error( $this->link );
			return false;
		}
		if ( true === $res ) {
			$this->rows_affected = mysqli_affected_rows( $this->link );
			$this->insert_id     = mysqli_insert_id( $this->link );
			return $this->rows_affected;
		}
		$n = mysqli_num_rows( $res );
		mysqli_free_result( $res );
		return $n;
	}
	public function get_var( $sql ) {
		$res = @mysqli_query( $this->link, $sql );
		if ( false === $res ) {
			$this->last_error = mysqli_error( $this->link );
			return null;
		}
		$row = $res->fetch_row();
		$res->free();
		return is_array( $row ) ? $row[0] : null;
	}
	public function get_row( $sql ) {
		$res = @mysqli_query( $this->link, $sql );
		if ( false === $res ) {
			$this->last_error = mysqli_error( $this->link );
			return null;
		}
		$row = $res->fetch_object();
		$res->free();
		return $row ?: null;
	}
	public function get_col( $sql ) {
		$res = @mysqli_query( $this->link, $sql );
		if ( false === $res ) {
			$this->last_error = mysqli_error( $this->link );
			return array();
		}
		$out = array();
		while ( $row = $res->fetch_row() ) {
			$out[] = $row[0];
		}
		$res->free();
		return $out;
	}
	public function get_results( $sql ) {
		$res = @mysqli_query( $this->link, $sql );
		if ( false === $res ) {
			$this->last_error = mysqli_error( $this->link );
			return null;
		}
		$out = array();
		while ( $row = $res->fetch_object() ) {
			$out[] = $row;
		}
		$res->free();
		return $out;
	}
	public function insert( $table, $data, $format = array() ) {
		$cols = array();
		$vals = array();
		$i    = 0;
		foreach ( $data as $col => $value ) {
			$f      = isset( $format[ $i ] ) ? $format[ $i ] : '%s';
			$cols[] = "`$col`";
			$vals[] = $this->bind( $value, $f );
			$i++;
		}
		return $this->query( "INSERT INTO $table (" . implode( ',', $cols ) . ') VALUES (' . implode( ',', $vals ) . ')' );
	}
	public function update( $table, $data, $where, $format = array(), $where_format = array() ) {
		$sets = array();
		$wh   = array();
		$i    = 0;
		foreach ( $data as $col => $value ) {
			$sets[] = "`$col` = " . $this->bind( $value, isset( $format[ $i ] ) ? $format[ $i ] : '%s' );
			$i++;
		}
		$i = 0;
		foreach ( $where as $col => $value ) {
			$wh[] = "`$col` = " . $this->bind( $value, isset( $where_format[ $i ] ) ? $where_format[ $i ] : '%s' );
			$i++;
		}
		return $this->query( "UPDATE $table SET " . implode( ', ', $sets ) . ' WHERE ' . implode( ' AND ', $wh ) );
	}
	private function bind( $value, $f ) {
		if ( '%d' === $f ) {
			return (string) (int) $value;
		}
		if ( '%f' === $f ) {
			return sprintf( '%F', (float) $value );
		}
		return null === $value ? 'NULL' : "'" . $this->esc( $value ) . "'";
	}
	public function delete( $table, $where, $format = array() ) {
		$wh = array();
		$i  = 0;
		foreach ( $where as $col => $value ) {
			$wh[] = "`$col` = " . $this->bind( $value, isset( $format[ $i ] ) ? $format[ $i ] : '%s' );
			$i++;
		}
		return $this->query( "DELETE FROM $table WHERE " . implode( ' AND ', $wh ) );
	}
}

require_once __DIR__ . '/../..' . '/includes/class-hsw-throttle.php';

$GLOBALS['wpdb']            = new HSW_QA_WPDB( getenv( 'HSW_QA_DSN' ), getenv( 'HSW_QA_USER' ), getenv( 'HSW_QA_PASS' ) );
$GLOBALS['hsw_qa_options']  = array();
$GLOBALS['hsw_qa_hooks']    = array();
$GLOBALS['hsw_qa_user']     = array( 'id' => 0, 'role' => 'subscriber' );

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['hsw_qa_options'] ) ? $GLOBALS['hsw_qa_options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['hsw_qa_options'][ $name ] = $value;
	return true;
}
function delete_option( $name ) {
	unset( $GLOBALS['hsw_qa_options'][ $name ] );
	return true;
}
function add_action( $hook, $cb, ...$r ) {
	$GLOBALS['hsw_qa_hooks'][ $hook ][] = $cb;
}
function add_filter( $hook, $cb, ...$r ) {
	$GLOBALS['hsw_qa_hooks'][ $hook ][] = $cb;
}
function apply_filters( $hook, $value, ...$rest ) {
	foreach ( $GLOBALS['hsw_qa_hooks'][ $hook ] ?? array() as $cb ) {
		$value = call_user_func( $cb, $value, ...$rest );
	}
	return $value;
}
function do_action( $hook, ...$args ) {
	foreach ( $GLOBALS['hsw_qa_hooks'][ $hook ] ?? array() as $cb ) {
		call_user_func( $cb, ...$args );
	}
}
function did_action( $h ) {
	return 0;
}
function current_user_can( $cap, ...$args ) {
	$me = $GLOBALS['hsw_qa_user'];
	if ( 'edit_user' === $cap ) {
		$target = (int) ( $args[0] ?? 0 );
		if ( $target < 1 ) {
			return false;
		}
		if ( $target === (int) $me['id'] ) {
			return true; // WP core: self-edit needs no capability.
		}
		return 'administrator' === $me['role'];
	}
	$map = array(
		'manage_woocommerce' => array( 'administrator', 'shop_manager' ),
		'manage_options'     => array( 'administrator' ),
	);
	return in_array( $me['role'], $map[ $cap ] ?? array(), true );
}
function get_current_user_id() {
	return (int) $GLOBALS['hsw_qa_user']['id'];
}
function is_user_logged_in() {
	return get_current_user_id() > 0;
}
function wp_set_current_user( $id ) {
	$GLOBALS['hsw_qa_user']['id'] = (int) $id;
}
function absint( $v ) {
	return abs( (int) $v );
}
function sanitize_key( $k ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) );
}
function sanitize_text_field( $s ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) );
}
function wp_unslash( $v ) {
	return $v;
}
function wp_strip_all_tags( $s ) {
	return trim( strip_tags( (string) $s ) );
}
function esc_sql( $s ) {
	global $wpdb;
	return $wpdb->esc( $s );
}
function current_time( $type ) {
	return 'mysql' === $type ? date( 'Y-m-d H:i:s' ) : time();
}
function wp_cache_delete( ...$a ) {
}
function clean_user_cache( ...$a ) {
}
class WP_Error {
	protected $code;
	protected $message;
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}
	public function get_error_code() {
		return $this->code;
	}
	public function get_error_message() {
		return $this->message;
	}
}
function is_wp_error( $x ) {
	return $x instanceof WP_Error;
}
function wp_die( $m = '' ) {
	throw new RuntimeException( 'wp_die: ' . $m );
}
function wp_verify_nonce( $nonce, $action ) {
	/* The suite dispatches the real hooks with a POSTed nonce field; the
	 * capability rules under test live above this check. */
	return '' !== (string) $nonce;
}
function __( $s, $d = null ) {
	return $s;
}
function get_user_meta( $uid, $key, $single = false ) {
	global $wpdb;
	$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT meta_value FROM wp_usermeta WHERE user_id = %d AND meta_key = %s ORDER BY umeta_id ASC', $uid, $key ) );
	if ( ! $rows ) {
		return '';
	}
	$vals = array_map( function ( $r ) {
		$v = @unserialize( $r->meta_value );
		return false === $v ? $r->meta_value : $v;
	}, $rows );
	return $single ? $vals[0] : $vals;
}
function update_user_meta( $uid, $key, $value ) {
	global $wpdb;
	$packed = is_array( $value ) || is_object( $value ) ? serialize( $value ) : $value;
	$prev   = $wpdb->get_var( $wpdb->prepare( 'SELECT umeta_id FROM wp_usermeta WHERE user_id = %d AND meta_key = %s LIMIT 1', $uid, $key ) );
	if ( $prev ) {
		return (bool) $wpdb->query( $wpdb->prepare( 'UPDATE wp_usermeta SET meta_value = %s WHERE umeta_id = %d', $packed, (int) $prev ) );
	}
	return (bool) $wpdb->insert( 'wp_usermeta', array( 'user_id' => $uid, 'meta_key' => $key, 'meta_value' => $packed ) );
}
function delete_user_meta( $uid, $key ) {
	global $wpdb;
	return (bool) $wpdb->query( $wpdb->prepare( 'DELETE FROM wp_usermeta WHERE user_id = %d AND meta_key = %s', $uid, $key ) );
}
function get_user_by( $field, $value ) {
	global $wpdb;
	$col = 'id' === $field ? 'ID' : ( 'email' === $field ? 'user_email' : 'user_login' );
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT ID, user_login, user_email, display_name FROM wp_users WHERE {$col} = %s LIMIT 1", $value ) );
	if ( ! $row ) {
		return null;
	}
	$u               = new WP_User();
	$u->ID           = (int) $row->ID;
	$u->user_login   = $row->user_login;
	$u->user_email   = $row->user_email;
	$u->display_name = $row->display_name;
	$u->roles        = array( 'customer' );
	return $u;
}
class WP_User {
	public $ID;
	public $roles = array( 'customer' );
	public $user_login;
	public $user_email;
	public $display_name;
}
function user_can( $user, $cap ) {
	return false;
}
function wp_rand( $min = 0, $max = 0 ) {
	return $max ? random_int( $min, $max ) : random_int( 0, PHP_INT_MAX );
}
function wp_hash_password( $p ) {
	return password_hash( (string) $p, PASSWORD_BCRYPT );
}
function wp_check_password( $p, $h ) {
	return password_verify( (string) $p, (string) $h );
}
function set_transient( $k, $v, $ttl ) {
	return update_option( '_t_' . $k, array( 'v' => $v, 'exp' => time() + (int) $ttl ) );
}
function get_transient( $k ) {
	$v = get_option( '_t_' . $k );
	return ( is_array( $v ) && isset( $v['exp'] ) && $v['exp'] >= time() ) ? $v['v'] : false;
}
function delete_transient( $k ) {
	return delete_option( '_t_' . $k );
}

/* Stubs the wallet collaborates with. */
class HSW_Helpers {
	public static function fa_num( $n ) {
		return (string) $n;
	}
}
class HSW_Account {
	public static $min_topup   = 50000;
	public static $point_value = 100;
	public static $rate        = 10000;
	public static $total_spent = 0.0;
	private static $inst = null;
	public static function instance() {
		return self::$inst ??= new self();
	}
	public function setting( $path, $fallback = null ) {
		switch ( $path ) {
			case 'wallet.min_topup':
				return self::$min_topup;
			case 'wallet.point_value':
				return self::$point_value;
			case 'loyalty.rate':
				return self::$rate;
			default:
				return $fallback;
		}
	}
	public function loyalty_points( $user_id = 0, $opts = null ) {
		return (int) floor( self::$total_spent / max( 1, (int) self::$rate ) );
	}
}

/* WooCommerce order stub (only what refund/process tests touch). */
class HSW_QA_Order {
	public $id;
	public $meta = array();
	public $notes = array();
	public $payment_method = 'hsw_wallet';
	public $customer_id = 0;
	public $total = 0.0;
	public $status = 'pending';
	public $completed = 0;
	public function __construct( $id ) {
		$this->id = $id;
	}
	public function get_id() {
		return $this->id;
	}
	public function get_meta( $k ) {
		return $this->meta[ $k ] ?? '';
	}
	public function update_meta_data( $k, $v ) {
		$this->meta[ $k ] = $v;
	}
	public function save() {
	}
	public function get_payment_method() {
		return $this->payment_method;
	}
	public function get_customer_id() {
		return $this->customer_id;
	}
	public function get_order_number() {
		return $this->id;
	}
	public function get_total() {
		return $this->total;
	}
	public function add_order_note( $n ) {
		$this->notes[] = $n;
	}
	public function is_paid() {
		return in_array( $this->status, array( 'processing', 'completed' ), true );
	}
	public function payment_complete() {
		$this->status    = 'processing';
		$this->completed++;
	}
}
$GLOBALS['hsw_qa_orders'] = array();
function wc_get_order( $id ) {
	return $GLOBALS['hsw_qa_orders'][ (int) $id ] ?? null;
}
function wc_price( $a ) {
	return (string) $a;
}
function wc_add_notice( $m, $t = 'notice' ) {
}
class WC_Cart_Stub {
	public function empty_cart() {
	}
}
class WC_Stub {
	public $cart;
	public function __construct() {
		$this->cart = new WC_Cart_Stub();
	}
}
function WC() {
	static $wc = null;
	return $wc ??= new WC_Stub();
}
/* Defining the WC base lets the REAL HSW_Wallet_Gateway load (wallet-pay.php
 * only declares it when WC_Payment_Gateway exists). */
class WC_Payment_Gateway {
	public $id = 'stub';
	public function init_settings() {
	}
	public function get_return_url( $order ) {
		return 'http://localhost/return/';
	}
	public function is_available() {
		return true;
	}
}
function wp_json_encode( $x ) {
	return json_encode( $x );
}
function wp_remote_post( $url, $args = array() ) {
	return array( 'body' => '' );
}
function wp_mail( ...$a ) {
	return true;
}
function get_bloginfo( $k = '' ) {
	return 'HamiShop QA';
}
function wp_specialchars_decode( $s, $q = null ) {
	return htmlspecialchars_decode( (string) $s, ENT_QUOTES );
}
function is_email( $e ) {
	return filter_var( $e, FILTER_VALIDATE_EMAIL ) ? $e : false;
}

/* ---------------------------------------------------------------------
 * Schema (plugin DDL without zero-date defaults) + the REAL classes.
 * ------------------------------------------------------------------- */

global $wpdb;
$REPO_ROOT = dirname( __DIR__, 2 );
$prefix    = $wpdb->prefix;

$CRASH_CHILD = in_array( $argv[1] ?? '', array( 'crash-decide', 'crash-convert', 'decide-real', 'pay-real' ), true );

/* maybe_install() requires WP's upgrade.php for dbDelta — provide a stub
 * that runs the CREATE statements verbatim (the REAL plugin DDL). */
if ( ! is_dir( sys_get_temp_dir() . '/hsw_qa_ABSPATH/wp-admin/includes' ) ) {
	mkdir( sys_get_temp_dir() . '/hsw_qa_ABSPATH/wp-admin/includes', 0777, true );
	file_put_contents(
		sys_get_temp_dir() . '/hsw_qa_ABSPATH/wp-admin/includes/upgrade.php',
		"<?php\nfunction dbDelta( \$sql ) { global \$wpdb; \$wpdb->query( \$sql ); return array(); }\n"
	);
}
// (ABSPATH already defined above — see the fixture block at the top.)

if ( ! $CRASH_CHILD ) {
	mysqli_query( $wpdb->link, 'DROP DATABASE IF EXISTS hsw_qa' );
	mysqli_query( $wpdb->link, 'CREATE DATABASE hsw_qa CHARACTER SET utf8mb4' );
	mysqli_select_db( $wpdb->link, 'hsw_qa' );
	$GLOBALS['wpdb'] = new HSW_QA_WPDB( getenv( 'HSW_QA_DSN' ), getenv( 'HSW_QA_USER' ), getenv( 'HSW_QA_PASS' ) );
	$wpdb            = $GLOBALS['wpdb'];
	/* Mirror the strict CI servers: the DDL and the IGNORE semantics must
	 * hold under STRICT_TRANS_TABLES + NO_ZERO_DATE. */
	mysqli_query( $wpdb->link, "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'" );
}

$wpdb->query( 'CREATE TABLE wp_usermeta (umeta_id bigint unsigned NOT NULL AUTO_INCREMENT, user_id bigint unsigned NOT NULL DEFAULT 0, meta_key varchar(255), meta_value longtext, PRIMARY KEY (umeta_id), KEY meta_key (meta_key(191))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' );
$wpdb->query( 'CREATE TABLE wp_users (ID bigint unsigned NOT NULL AUTO_INCREMENT, user_login varchar(60) NOT NULL, user_email varchar(100) NOT NULL, display_name varchar(250) NOT NULL, PRIMARY KEY (ID)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' );

/* The DDL the plugin ships — zero-date defaults removed (finding #12). */
$ddl_ledger = "CREATE TABLE {$prefix}hsw_wallet_ledger (
	id bigint unsigned NOT NULL AUTO_INCREMENT,
	user_id bigint unsigned NOT NULL DEFAULT 0,
	amount decimal(18,2) NOT NULL DEFAULT 0,
	balance_after decimal(18,2) NOT NULL DEFAULT 0,
	kind varchar(32) NOT NULL DEFAULT 'credit',
	status varchar(16) NOT NULL DEFAULT 'done',
	note text NULL,
	ref varchar(64) NULL DEFAULT NULL,
	actor_id bigint unsigned NOT NULL DEFAULT 0,
	created_at datetime NOT NULL,
	PRIMARY KEY  (id),
	KEY user_time (user_id, created_at),
	KEY status (status),
	UNIQUE KEY uniq_ref (ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$ddl_balances = "CREATE TABLE {$prefix}hsw_wallet (
	user_id bigint unsigned NOT NULL,
	balance decimal(18,2) NOT NULL DEFAULT 0,
	points_used decimal(12,0) NOT NULL DEFAULT 0,
	updated_at datetime NOT NULL,
	PRIMARY KEY  (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$ddl_backup = "CREATE TABLE {$prefix}hsw_wallet_meta_backup (
	meta_id bigint unsigned NOT NULL,
	user_id bigint unsigned NOT NULL DEFAULT 0,
	meta_value longtext NULL,
	taken_at datetime NOT NULL,
	PRIMARY KEY  (meta_id),
	KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

/* Strict session: the DDL must survive STRICT + NO_ZERO_DATE. */
$mode_row  = mysqli_fetch_row( mysqli_query( $wpdb->link, 'SELECT @@sql_mode' ) );
$mode      = (string) $mode_row[0];
if ( ! $CRASH_CHILD ) {
	foreach ( array( $ddl_ledger, $ddl_balances, $ddl_backup ) as $ddl ) {
		if ( false === $wpdb->query( $ddl ) ) {
			echo "FATAL: DDL failed under sql_mode [{$mode}]: {$wpdb->last_error}\n";
			exit( 1 );
		}
	}
}
$GLOBALS['hsw_qa_sql_mode'] = $mode;

require $REPO_ROOT . '/includes/class-hsw-wallet.php';
require $REPO_ROOT . '/includes/class-hsw-wallet-pay.php';
require $REPO_ROOT . '/includes/class-hsw-account-admin.php';

/* Fire the gateway registration directly (suite has no woocommerce_loaded). */
hsw_register_wallet_gateway();

define( 'HSW_FILE', __FILE__ );
function plugin_basename( $f ) {
	return basename( dirname( $f ) ) . '/' . basename( $f );
}

/* ---------------------------------------------------------------------
 * Helpers
 * ------------------------------------------------------------------- */

$PASS = 0;
$FAIL = 0;
function qa( $label, $ok, $extra = '' ) {
	global $PASS, $FAIL;
	if ( $ok ) {
		$PASS++;
		echo "[ok]   {$label}\n";
	} else {
		$FAIL++;
		echo "[FAIL] {$label} {$extra}\n";
	}
}
function qa_balance( $uid ) {
	global $wpdb;
	return (float) $wpdb->get_var( $wpdb->prepare( 'SELECT balance FROM ' . HSW_Wallet::balance_table() . ' WHERE user_id = %d', $uid ) );
}
function qa_ledger( $uid = null ) {
	global $wpdb;
	$sql  = 'SELECT user_id, actor_id, amount, balance_after, kind, status, ref FROM ' . HSW_Wallet::table();
	$sql .= $uid ? $wpdb->prepare( ' WHERE user_id = %d', $uid ) : '';
	$sql .= ' ORDER BY id';
	return array_map( function ( $r ) {
		return array( 'user_id' => (int) $r->user_id, 'actor_id' => (int) $r->actor_id, 'amount' => (float) $r->amount, 'kind' => $r->kind, 'status' => $r->status, 'ref' => $r->ref );
	}, (array) $wpdb->get_results( $sql ) );
}

/* ---------------------------------------------------------------------
 * Child modes run BEFORE any test section: they must touch nothing but
 * their own scenario, otherwise every child would re-run the whole suite
 * and pollute the shared scratch database.
 * ------------------------------------------------------------------- */
$qa_mode = $argv[1] ?? '';
if ( $CRASH_CHILD ) {
	if ( 'crash-decide' === $qa_mode ) {
		$GLOBALS['hsw_qa_user'] = array( 'id' => 1, 'role' => 'administrator' );
		HSW_Wallet::decide( (int) $argv[2], true, '' );
	} elseif ( 'decide-real' === $qa_mode ) {
		$GLOBALS['hsw_qa_user'] = array( 'id' => 1, 'role' => 'administrator' );
		$r = HSW_Wallet::decide( (int) $argv[2], true, '' );
		echo is_wp_error( $r ) ? 'ERR:' . $r->get_error_code() : 'OK';
	} elseif ( 'crash-convert' === $qa_mode ) {
		HSW_Account::$total_spent = 150 * 10000;
		HSW_Wallet::convert_points( 9003, 100 );
	} elseif ( 'pay-real' === $qa_mode ) {
		$o = new HSW_QA_Order( (int) $argv[2] );
		$o->customer_id = (int) $argv[3];
		$o->total       = (float) $argv[4];
		$GLOBALS['hsw_qa_orders'][ (int) $argv[2] ] = $o;
		$g   = new HSW_Wallet_Gateway();
		$res = $g->process_payment( (int) $argv[2] );
		echo 'JSON:' . json_encode( array(
			'result'    => is_array( $res ) ? $res['result'] : 'error',
			'status'    => $o->status,
			'completed' => $o->completed,
		) );
	}
	exit( 0 );
}

/* =====================================================================
 * #1 — profile balance save: subscriber must NOT be able to credit
 * themselves through the personal_options_update hook with a real POST.
 * ==================================================================== */

$wpdb->query( "INSERT INTO wp_users (ID, user_login, user_email, display_name) VALUES (9001, 'sub9001', 'sub9001@example.com', 'Sub 9001')" );
update_user_meta( 9001, '_hsw_auth_phone', '09120009001' );

$admin_account = new HSW_Account();
$admin_screen  = new HSW_Account_Admin( $admin_account );

function qa_run_profile_save( $user_id, $as_role, $target_id, $amount ) {
	global $wpdb;
	$GLOBALS['hsw_qa_user'] = array( 'id' => $user_id, 'role' => $as_role );
	$_POST['hsw_wallet_balance']      = (string) $amount;
	$_POST['hsw_account_user_nonce']  = 'valid'; // stubbed nonce path accepts this.
	do_action( 'personal_options_update', $target_id );
	do_action( 'edit_user_profile_update', $target_id );
	unset( $_POST['hsw_wallet_balance'], $_POST['hsw_account_user_nonce'] );
	$GLOBALS['hsw_qa_user'] = array( 'id' => 0, 'role' => 'subscriber' );
}
function qa_set_balance_row( $uid, $value ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . HSW_Wallet::balance_table() . ' (user_id, balance, points_used, updated_at) VALUES (%d, %f, 0, NOW()) ON DUPLICATE KEY UPDATE balance = VALUES(balance)', $uid, $value ) );
}

qa_set_balance_row( 9001, 100 );
qa_run_profile_save( 9001, 'subscriber', 9001, 999999 );
qa( '#1 subscriber cannot set own balance via personal_options_update', 100.0 === qa_balance( 9001 ), 'balance=' . qa_balance( 9001 ) );

qa_run_profile_save( 1, 'administrator', 9001, 250 );
qa( '#1 admin CAN set another user balance via edit_user_profile_update', 250.0 === qa_balance( 9001 ), 'balance=' . qa_balance( 9001 ) );
$adjust_rows = array_values( array_filter( qa_ledger( 9001 ), function ( $r ) {
	return 'adjust' === $r['kind'];
} ) );
qa( '#1 adjust row records the real delta and the acting admin', 1 === count( $adjust_rows ) && 150.0 === $adjust_rows[0]['amount'] && 1 === (int) ( $adjust_rows[0]['actor_id'] ?? 0 ), json_encode( $adjust_rows ) );

/* =====================================================================
 * #2 — decide(): claim + credit + receipt in one transaction. Crash
 * simulation: replicate the exact statements, exit before COMMIT, then a
 * fresh process must find the request still pending and no money moved.
 * ==================================================================== */

$wpdb->query( "INSERT INTO wp_users (ID, user_login, user_email, display_name) VALUES (9002, 'sub9002', 'sub9002@example.com', 'Sub 9002')" );
HSW_Account::$min_topup = 50000;
$req_id                 = HSW_Wallet::request_topup( 9002, 100000, 'tx test' );
qa( '#2 pending request created', $req_id > 0 );

if ( 'crash-decide' === ( $argv[1] ?? '' ) ) {
	/* Child: the REAL decide(), killed by the query injection right after the
	 * claim UPDATE — the same footprint a kill -9 leaves between statements. */
	$GLOBALS['hsw_qa_user'] = array( 'id' => 1, 'role' => 'administrator' );
	HSW_Wallet::decide( (int) $argv[2], true, '' );
	echo "UNREACHABLE\n";
	exit( 0 );
}
if ( 'decide-real' === ( $argv[1] ?? '' ) ) {
	/* Child: a parallel approval of the SAME request. */
	$GLOBALS['hsw_qa_user'] = array( 'id' => 1, 'role' => 'administrator' );
	$r                      = HSW_Wallet::decide( (int) $argv[2], true, '' );
	echo is_wp_error( $r ) ? 'ERR:' . $r->get_error_code() : 'OK';
	exit( 0 );
}

function qa_spawn( $args ) {
	$cmd  = PHP_BINARY . ' -d error_reporting=' . ( E_ALL & ~E_DEPRECATED ) . ' ' . escapeshellarg( __FILE__ ) . ' ' . implode( ' ', array_map( 'escapeshellarg', $args ) );
	$desc = array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) );
	$proc = proc_open( $cmd, $desc, $pipes, __DIR__, array_merge( getenv(), array() ) );
	$out  = stream_get_contents( $pipes[1] );
	fclose( $pipes[1] );
	proc_close( $proc );
	return trim( (string) $out );
}

/* Normal approval first (frees the one-open-request slot for user 9002). */
$GLOBALS['hsw_qa_user'] = array( 'id' => 1, 'role' => 'administrator' );
$decided = HSW_Wallet::decide( $req_id, true, '' );
qa( '#2 approve credits the wallet exactly once', true === $decided, is_wp_error( $decided ) ? $decided->get_error_code() : '' );
qa( '#2 balance after approve = 100000', 100000.0 === qa_balance( 9002 ), 'balance=' . qa_balance( 9002 ) );
$row = $wpdb->get_row( $wpdb->prepare( 'SELECT status, actor_id FROM ' . HSW_Wallet::table() . ' WHERE id = %d', $req_id ) );
qa( '#2 request recorded as done with actor', 'done' === $row->status && 1 === (int) $row->actor_id );

/* Parallel decide: two processes approve the same request at once. */
$req_id2 = HSW_Wallet::request_topup( 9002, 50000, 'parallel test' );
$out_a   = qa_spawn( array( 'decide-real', (string) $req_id2 ) );
$out_b   = qa_spawn( array( 'decide-real', (string) $req_id2 ) );
qa( '#2 parallel approve: exactly one wins, one sees a non-pending state', ( false !== strpos( $out_a, 'OK' ) ) !== ( false !== strpos( $out_b, 'OK' ) ), "A=$out_a B=$out_b" );
qa( '#2 parallel approve credits exactly once', 150000.0 === qa_balance( 9002 ), 'balance=' . qa_balance( 9002 ) );

/* Crash: the real decide() dies AFTER the claim (the injection matches the
 * debit UPDATE that comes next), before the credit. Fresh user: the crash
 * intentionally leaves a pending request behind. */
$wpdb->query( "INSERT INTO wp_users (ID, user_login, user_email, display_name) VALUES (9005, 'sub9005', 'sub9005@example.com', 'Sub 9005')" );
$req_id3 = HSW_Wallet::request_topup( 9005, 70000, 'crash test' );
putenv( 'HSW_QA_DIE_PATTERN=/SET balance = balance \+ /' );
putenv( 'HSW_QA_DIE_AT=1' );
$GLOBALS['hsw_qa_user'] = array( 'id' => 1, 'role' => 'administrator' );
qa_spawn( array( 'crash-decide', (string) $req_id3 ) );
putenv( 'HSW_QA_DIE_PATTERN' );
putenv( 'HSW_QA_DIE_AT' );
$row = $wpdb->get_row( $wpdb->prepare( 'SELECT status, ref FROM ' . HSW_Wallet::table() . ' WHERE id = %d', $req_id3 ) );
qa( '#2 crash after claim (real decide) leaves the request pending', 'pending' === $row->status && 'pending:9005' === $row->ref, "status={$row->status} ref={$row->ref}" );
qa( '#2 crash after claim moves no money', 0.0 === qa_balance( 9005 ), 'balance=' . qa_balance( 9005 ) );
$GLOBALS['hsw_qa_user'] = array( 'id' => 0, 'role' => 'subscriber' );

/* =====================================================================
 * #3 — convert_points(): claim + credit in one transaction. Same crash
 * simulation: die between the claim and the credit; the claimed points
 * must roll back.
 * ==================================================================== */

$wpdb->query( "INSERT INTO wp_users (ID, user_login, user_email, display_name) VALUES (9003, 'sub9003', 'sub9003@example.com', 'Sub 9003')" );
HSW_Account::$total_spent = 150 * 10000; // earned 150.
qa_set_balance_row( 9003, 0 );

if ( 'crash-convert' === ( $argv[1] ?? '' ) ) {
	/* Child: the REAL convert_points(), killed right after the claim. */
	HSW_Wallet::convert_points( 9003, 100 );
	echo "UNREACHABLE\n";
	exit( 0 );
}
if ( 'pay-real' === ( $argv[1] ?? '' ) ) {
	/* Child: a parallel process_payment for the same order. The stub order is
	 * rebuilt from argv (memory is per-process), and the outcome is printed
	 * as JSON so the parent can count completions. */
	$o = new HSW_QA_Order( (int) $argv[2] );
	$o->customer_id = (int) $argv[3];
	$o->total       = (float) $argv[4];
	$GLOBALS['hsw_qa_orders'][ (int) $argv[2] ] = $o;
	$g   = new HSW_Wallet_Gateway();
	$res = $g->process_payment( (int) $argv[2] );
	echo json_encode( array(
		'result'    => is_array( $res ) ? $res['result'] : 'error',
		'status'    => $o->status,
		'completed' => $o->completed,
	) );
	exit( 0 );
}
putenv( 'HSW_QA_DIE_PATTERN=/SET balance = balance \+ /' );
putenv( 'HSW_QA_DIE_AT=1' );
qa_spawn( array( 'crash-convert' ) );
putenv( 'HSW_QA_DIE_PATTERN' );
putenv( 'HSW_QA_DIE_AT' );
$row = $wpdb->get_row( $wpdb->prepare( 'SELECT points_used, balance FROM ' . HSW_Wallet::balance_table() . ' WHERE user_id = %d', 9003 ) );
qa( '#3 crash after claim (real convert) restores claimed points', 0 === (int) $row->points_used, 'points_used=' . $row->points_used );
qa( '#3 crash after claim credits no money', 0.0 === (float) $row->balance );

/* Normal conversion still pays once and refuses over-conversion. */
$res = HSW_Wallet::convert_points( 9003, 50 );
qa( '#3 convert 50 points pays once', 5000.0 === (float) $res, 'got ' . json_encode( $res ) );
$row = $wpdb->get_row( $wpdb->prepare( 'SELECT points_used FROM ' . HSW_Wallet::balance_table() . ' WHERE user_id = %d', 9003 ) );
qa( '#3 points_used recorded once', 50 === (int) $row->points_used );
qa( '#3 over-conversion refused', is_wp_error( HSW_Wallet::convert_points( 9003, 500 ) ) );

/* =====================================================================
 * #4/#5 — migration reconcile: premature rows get flagged and their
 * legacy balance lands under `migrate:<uid>`, both directions.
 * ==================================================================== */

function qa_seed_meta( $uid, $values ) {
	global $wpdb;
	foreach ( (array) $values as $v ) {
		$wpdb->insert( 'wp_usermeta', array( 'user_id' => $uid, 'meta_key' => '_hsw_wallet_balance', 'meta_value' => $v ) );
	}
}

/* Case b (the race): first wallet write landed before the migration row. */
$wpdb->query( "INSERT INTO wp_users (ID, user_login, user_email, display_name) VALUES (9010, 'sub9010', 'sub9010@example.com', 'Sub 9010')" );
qa_seed_meta( 9010, array( '1000' ) );
HSW_Wallet::credit( 9010, 50, 'admin', 'write during migration window', 0, '' );
$out = HSW_Wallet::migrate( 200, true );
$rep = HSW_Wallet::migration_report();
$flags = array();
foreach ( (array) ( $rep['anomaly_list'] ?? array() ) as $a ) {
	if ( 9010 === (int) $a['user_id'] ) {
		$flags = $a['flags'];
	}
}
qa( '#4 race case flagged as premature-row', in_array( 'premature-row', $flags, true ), json_encode( $flags ) );
qa( '#4 race case recovers legacy money with migrate:<uid>', 1050.0 === qa_balance( 9010 ), 'balance=' . qa_balance( 9010 ) );
$has_line = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HSW_Wallet::table() . " WHERE ref = %s AND kind = 'migrate'", 'migrate:9010' ) );
qa( '#4 race case ledger line exists exactly once', 1 === $has_line );
qa( '#4 race-case balance stays in a sane range (no garbage credit)', qa_balance( 9010 ) < 1.0e16 && ! in_array( 'legacy-above-range', $flags, true ), 'balance=' . qa_balance( 9010 ) );

/* Case a (adoption): balance() ran first, only the statement line missing. */
$wpdb->query( "INSERT INTO wp_users (ID, user_login, user_email, display_name) VALUES (9011, 'sub9011', 'sub9011@example.com', 'Sub 9011')" );
qa_seed_meta( 9011, array( '750' ) );
HSW_Wallet::balance( 9011 ); // adoption creates the row with 750.
HSW_Wallet::migrate( 200, true );
qa( '#5 adopt case keeps the balance', 750.0 === qa_balance( 9011 ), 'balance=' . qa_balance( 9011 ) );
$line = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT amount FROM ' . HSW_Wallet::table() . " WHERE ref = %s", 'migrate:9011' ) );
qa( '#5 adopt case writes the opening line with the legacy amount', 750.0 === $line, "line={$line}" );

/* convert_points before migration must adopt, not seed a zero row. */
$wpdb->query( "INSERT INTO wp_users (ID, user_login, user_email, display_name) VALUES (9012, 'sub9012', 'sub9012@example.com', 'Sub 9012')" );
qa_seed_meta( 9012, array( '750' ) );
HSW_Account::$total_spent = 200 * 10000;
delete_option( 'hsw_wallet_migration' ); // migration not done yet.
HSW_Wallet::convert_points( 9012, 5 );
HSW_Wallet::migrate( 200, true );
qa( '#5 convert-before-migration keeps the legacy money', 1250.0 === qa_balance( 9012 ), 'balance=' . qa_balance( 9012 ) );

/* Garbage legacy value: flagged, never credited (live-site finding #14). */
$wpdb->query( "INSERT INTO wp_users (ID, user_login, user_email, display_name) VALUES (9013, 'sub9013', 'sub9013@example.com', 'Sub 9013')" );
qa_seed_meta( 9013, array( '99999999999999999' ) );
HSW_Wallet::migrate( 200, true );
$flags9013 = array();
foreach ( (array) ( HSW_Wallet::migration_report()['anomaly_list'] ?? array() ) as $a ) {
	if ( 9013 === (int) $a['user_id'] ) {
		$flags9013 = $a['flags'];
	}
}
qa( '#5 garbage 1e17 legacy flagged legacy-above-range, not credited', in_array( 'legacy-above-range', $flags9013, true ) && qa_balance( 9013 ) < 1.0e16, json_encode( $flags9013 ) . ' balance=' . qa_balance( 9013 ) );

/* =====================================================================
 * #9 — cancelling/refunding a top-up order reverses the credit with
 * `topup-reverse:<order>`, idempotently, and never goes negative.
 * ==================================================================== */

/* Fresh user so the balances below are independent of earlier tests. */
$wpdb->query( "INSERT INTO wp_users (ID, user_login, user_email, display_name) VALUES (9014, 'sub9014', 'sub9014@example.com', 'Sub 9014')" );

HSW_Wallet::credit( 9014, 100000, 'topup', 'seed topup', 0, 'topup:7001' ); // the topup credit.
$order7001 = new HSW_QA_Order( 7001 );
$order7001->customer_id = 9014;
$order7001->total       = 100000;
$order7001->update_meta_data( HSW_Wallet_Topup::META_USER, 9014 );
$GLOBALS['hsw_qa_orders'][7001] = $order7001;
$refunds = new HSW_Wallet_Refunds();
$rev = $refunds->refund_order( 7001 );
qa( '#9 cancel of a credited topup order debits the wallet', 100000.0 === (float) $rev && 0.0 === qa_balance( 9014 ), 'rev=' . json_encode( $rev ) . ' balance=' . qa_balance( 9014 ) );
$ref2 = $refunds->refund_order( 7001 );
qa( '#9 second cancel is a no-op (idempotent ref)', 0.0 === (float) $ref2 && 0.0 === qa_balance( 9014 ) );
$has_rev = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HSW_Wallet::table() . " WHERE ref LIKE %s AND kind = 'topup-reverse'", 'topup-reverse:7001:%' ) );
qa( '#9 exactly one topup-reverse ledger row', 1 === $has_rev );

/* Spent credit: flag for admin, never negative. */
HSW_Wallet::credit( 9014, 100000, 'topup', 'seed topup 2', 0, 'topup:7002' );
HSW_Wallet::debit( 9014, 100000, 'spend', 'spent it', 0, 'spend:7002x' );
$order7002 = new HSW_QA_Order( 7002 );
$order7002->customer_id = 9014;
$order7002->update_meta_data( HSW_Wallet_Topup::META_USER, 9014 );
$GLOBALS['hsw_qa_orders'][7002] = $order7002;
$rev2 = $refunds->refund_order( 7002 );
qa( '#9 spent credit is flagged for admin review, not forced negative', is_wp_error( $rev2 ) && 0.0 === qa_balance( 9014 ) && count( $order7002->notes ) > 0, 'rev=' . json_encode( $rev2 ) );

/* Unpaid pending topup order: nothing to reverse. */
$order7003 = new HSW_QA_Order( 7003 );
$order7003->customer_id = 9014;
$order7003->update_meta_data( HSW_Wallet_Topup::META_USER, 9014 );
$GLOBALS['hsw_qa_orders'][7003] = $order7003;
qa( '#9 unpaid topup order cancel moves nothing', 0.0 === (float) $refunds->refund_order( 7003 ) );

/* =====================================================================
 * #6 — repair refs are timestamped: the same gap twice must both repair.
 * #7 — set_balance range guard. #10 — topup range guard. #11 — long refs
 * fail instead of being truncated.
 * ==================================================================== */

$m = new ReflectionMethod( 'HSW_Wallet', 'apply_balance' );
$m->setAccessible( true );
$m->invoke( null, 9002, 100.0 );
$rep1 = HSW_Wallet::repair_gaps();
$rep1_rows = array_values( array_filter( $rep1, function ( $r ) {
	return 9002 === $r['user_id'];
} ) );
qa( '#6 first +100 gap repaired', ! empty( $rep1_rows ) && true === $rep1_rows[0]['repaired'], json_encode( $rep1 ) );
$m->invoke( null, 9002, 100.0 );
$rep2 = HSW_Wallet::repair_gaps();
$rep2_rows = array_values( array_filter( $rep2, function ( $r ) {
	return 9002 === $r['user_id'];
} ) );
qa( '#6 identical gap later also repairs (stable-per-gap ref)', ! empty( $rep2_rows ) && true === $rep2_rows[0]['repaired'], json_encode( $rep2 ) );

qa( '#7 set_balance above 1e16 is refused', is_wp_error( HSW_Wallet::set_balance( 9002, 1.0e18 ) ) );
$bal_before_guard = qa_balance( 9002 );
qa( '#7 balance unchanged after refused set_balance', $bal_before_guard === qa_balance( 9002 ) );

$bad = HSW_Wallet::request_topup( 9002, 9.2e18 );
qa( '#10 topup above range refused with the right error', is_wp_error( $bad ) && 'hsw_wallet_amount' === $bad->get_error_code(), is_wp_error( $bad ) ? $bad->get_error_code() : 'no error' );
$open = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HSW_Wallet::table() . " WHERE ref = %s", 'pending:9002' ) );
qa( '#10 no pending row written for the refused topup', 0 === $open );

qa( '#11 65-char ref fails instead of truncating', is_wp_error( HSW_Wallet::credit( 9002, 5, 'admin', '', 0, str_repeat( 'x', 64 ) . 'A' ) ) );
qa( '#11 balance unchanged by the refused long ref', $bal_before_guard === qa_balance( 9002 ) );

/* =====================================================================
 * B — process_payment through the REAL gateway: normal, retry after the
 * charge, insufficient balance, and two parallel attempts.
 * ==================================================================== */

$gateway = new HSW_Wallet_Gateway();

/* Normal: enough balance → charged once, order completed once. */
$pre_gw = qa_balance( 9002 );
HSW_Wallet::credit( 9002, 300, 'admin', 'gateway seed', 0, 'admin-batch-gw:9002' );
$o8001 = new HSW_QA_Order( 8001 );
$o8001->customer_id = 9002;
$o8001->total       = 250;
$o8001->update_meta_data( HSW_Wallet_Refunds::META_PAID, 0 );
$GLOBALS['hsw_qa_orders'][8001] = $o8001;
$res = $gateway->process_payment( 8001 );
qa( 'B normal process_payment succeeds', is_array( $res ) && 'success' === $res['result'] );
qa( 'B normal completes the order exactly once', 'processing' === $o8001->status && 1 === $o8001->completed );
qa( 'B normal charged the wallet once', round( $pre_gw + 300 - 250, 2 ) === qa_balance( 9002 ), 'balance=' . qa_balance( 9002 ) . ' expected=' . round( $pre_gw + 300 - 250, 2 ) );

/* Retry after a crash between COMMIT and payment_complete: the wallet is
 * charged, the order still pending → the retry must FINISH the order. */
HSW_Wallet::debit( 9002, 120, 'spend', 'simulated charge', 0, 'spend:8002' );
HSW_Wallet_Refunds::mark_paid( 8002, 120 );
$o8002 = new HSW_QA_Order( 8002 );
$o8002->customer_id = 9002;
$o8002->total       = 120;
$o8002->update_meta_data( HSW_Wallet_Refunds::META_PAID, 120 );
$GLOBALS['hsw_qa_orders'][8002] = $o8002;
$pre = qa_balance( 9002 );
$res = $gateway->process_payment( 8002 );
qa( 'B retry after crash returns success', is_array( $res ) && 'success' === $res['result'] );
qa( 'B retry FINISHES the pending order (payment_complete)', 'processing' === $o8002->status && 1 === $o8002->completed, 'status=' . $o8002->status . ' completed=' . $o8002->completed );
qa( 'B retry charges nothing more', $pre === qa_balance( 9002 ), 'balance=' . qa_balance( 9002 ) );

/* Insufficient balance → failure, no money moved. */
$o8003 = new HSW_QA_Order( 8003 );
$o8003->customer_id = 9002;
$o8003->total       = 999999;
$GLOBALS['hsw_qa_orders'][8003] = $o8003;
$pre = qa_balance( 9002 );
$res = $gateway->process_payment( 8003 );
qa( 'B insufficient balance fails cleanly', is_array( $res ) && 'failure' === $res['result'] );
qa( 'B insufficient balance moves no money', $pre === qa_balance( 9002 ) );

/* Parallel double-pay: two processes, one charge, one completed order. */
HSW_Wallet::credit( 9002, 200, 'admin', 'gateway seed 2', 0, 'admin-batch-gw2:9002' );
$pre_par = qa_balance( 9002 );
$o8004 = new HSW_QA_Order( 8004 );
$o8004->customer_id = 9002;
$o8004->total       = 200;
$GLOBALS['hsw_qa_orders'][8004] = $o8004;
$pa = qa_spawn( array( 'pay-real', '8004', '9002', '200' ) );
$pb = qa_spawn( array( 'pay-real', '8004', '9002', '200' ) );
$ja = json_decode( substr( (string) $pa, (int) strpos( (string) $pa, 'JSON:' ) + 5 ), true );
$jb = json_decode( substr( (string) $pb, (int) strpos( (string) $pb, 'JSON:' ) + 5 ), true );
qa( 'B parallel double-pay: both attempts succeed, order never left pending', is_array( $ja ) && is_array( $jb ) && 'success' === $ja['result'] && 'success' === $jb['result'] && 'pending' !== $ja['status'] && 'pending' !== $jb['status'], "A=$pa B=$pb" );
qa( 'B parallel double-pay charges once', round( $pre_par - 200, 2 ) === qa_balance( 9002 ), 'balance=' . qa_balance( 9002 ) . ' expected=' . round( $pre_par - 200, 2 ) );

/* =====================================================================
 * G — the WP-CLI commands must be registered on an installed site too.
 * ==================================================================== */

$GLOBALS['hsw_qa_cli_commands'] = array();
class WP_CLI {
	public static function add_command( $name, $cb, $args = array() ) {
		$GLOBALS['hsw_qa_cli_commands'][] = $name;
	}
	public static function log( $m ) {
	}
	public static function success( $m ) {
	}
	public static function warning( $m ) {
	}
	public static function error( $m, $exit = true ) {
	}
}
if ( ! defined( 'WP_CLI' ) ) {
	define( 'WP_CLI', true );
}
HSW_Wallet::register_cli();
qa( 'G wallet CLI commands registered (migrate/report/repair)', 3 === count( preg_grep( '/^hsw wallet /', $GLOBALS['hsw_qa_cli_commands'] ) ), json_encode( $GLOBALS['hsw_qa_cli_commands'] ) );

/* =====================================================================
 * #12-real — maybe_install() with the plugin's own dbDelta DDL on this
 * strict server, then SHOW TABLE STATUS must say InnoDB for every table.
 * ==================================================================== */

$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'hsw_wallet_ledger' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'hsw_wallet' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'hsw_wallet_meta_backup' );
delete_option( 'hsw_wallet_db' );
HSW_Wallet::instance()->maybe_install();
$engines = array();
foreach ( array( 'hsw_wallet_ledger', 'hsw_wallet', 'hsw_wallet_meta_backup' ) as $qa_table ) {
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT ENGINE, TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $wpdb->prefix . $qa_table ) );
	$engines[ $qa_table ] = $row ? $row->ENGINE : 'MISSING';
}
qa( '#12 maybe_install creates all three tables with real DDL', 'MISSING' !== $engines['hsw_wallet_ledger'] && 'MISSING' !== $engines['hsw_wallet'] && 'MISSING' !== $engines['hsw_wallet_meta_backup'], json_encode( $engines ) );
qa( '#12 all wallet tables are InnoDB', 'InnoDB' === $engines['hsw_wallet_ledger'] && 'InnoDB' === $engines['hsw_wallet'] && 'InnoDB' === $engines['hsw_wallet_meta_backup'], json_encode( $engines ) );
qa( '#12 schema version stamped after a successful install', '5' === get_option( 'hsw_wallet_db' ) );

/* =====================================================================
 * #12 — the plugin DDL must not carry zero-date defaults.
 * ==================================================================== */

$src = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-hsw-wallet.php' );
qa( '#12 no zero-date DEFAULT left in the wallet DDL', false === strpos( $src, "DEFAULT '0000-00-00" ) );
qa( '#12 schema version only stamped after SHOW TABLES (source check)', false !== strpos( $src, "\$installed = \$wpdb->get_var" ) );
qa( '#12 suite ran under a strict sql_mode', false !== strpos( $GLOBALS['hsw_qa_sql_mode'], 'STRICT_TRANS_TABLES' ) && false !== strpos( $GLOBALS['hsw_qa_sql_mode'], 'NO_ZERO_DATE' ), 'sql_mode=' . $GLOBALS['hsw_qa_sql_mode'] );

echo "\nsql_mode: {$GLOBALS['hsw_qa_sql_mode']}\n";
echo 'server: ' . (string) $wpdb->get_var( 'SELECT VERSION()' ) . "\n";
echo "=== {$PASS} passed, {$FAIL} failed ===\n";
exit( $FAIL > 0 ? 1 : 0 );
