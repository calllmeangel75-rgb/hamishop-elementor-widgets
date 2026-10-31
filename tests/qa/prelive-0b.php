<?php
/**
 * ITEM 0b — no DDL from `init` unless the shop asks for it (executable).
 * QA-only; the release ZIP excludes tests/ (.gitattributes export-ignore).
 *
 *   php tests/qa/prelive-0b.php
 *   HSW_PRELIVE_CONST=1 php tests/qa/prelive-0b.php     # emergency constant
 *   HSW_PRELIVE_WALLET=<path to an older file> php …
 */

require_once __DIR__ . '/prelive-lib.php';
@ini_set( 'error_log', 'NUL' );

if ( getenv( 'HSW_PRELIVE_CONST' ) ) {
	define( 'HSW_DISABLE_WALLET', true );
}

$file = getenv( 'HSW_PRELIVE_WALLET' );
if ( ! $file ) {
	$file = dirname( __DIR__, 2 ) . '/includes/class-hsw-wallet.php';
}
require_once $file;

$results = array();
function w_case( $id, $title, $fn ) {
	global $results;
	try {
		$r = $fn();
	} catch ( Throwable $e ) {
		$r = array( false, 'EXCEPTION ' . $e->getMessage() );
	}
	$results[] = $r;
	printf( "[%s] %-5s %s — %s\n", $r[0] ? 'PASS' : 'FAIL', $id, $title, $r[1] );
}

function w_reset( $opts = array() ) {
	HSW_Prelive_Store::reset();
	$GLOBALS['hsw_dbdelta'] = array();
	global $wpdb;
	$wpdb->queries = array();
	foreach ( $opts as $k => $v ) {
		update_option( $k, $v );
	}
}

function w_install() {
	HSW_Wallet::instance()->maybe_install();
}

if ( getenv( 'HSW_PRELIVE_CONST' ) ) {
	w_case(
		'0.7',
		'HSW_DISABLE_WALLET: no DDL even with autoinstall on',
		static function () {
			w_reset( array( 'hsw_wallet_autoinstall' => 'yes' ) );
			w_install();
			return array( empty( $GLOBALS['hsw_dbdelta'] ), 'dbDelta calls=' . count( $GLOBALS['hsw_dbdelta'] ) );
		}
	);
} else {
	w_case(
		'0.5',
		'autoinstall absent: no DDL and no schema queries on init',
		static function () {
			w_reset();
			w_install();
			global $wpdb;
			$schema_queries = array_filter(
				$wpdb->queries,
				static function ( $q ) { return false !== stripos( $q, 'SHOW TABLES' ) || false !== stripos( $q, 'CREATE TABLE' ); }
			);
			$ok = empty( $GLOBALS['hsw_dbdelta'] ) && empty( $schema_queries );
			return array( $ok, sprintf( 'dbDelta=%d schema-queries=%d', count( $GLOBALS['hsw_dbdelta'] ), count( $schema_queries ) ) );
		}
	);

	w_case(
		'0.6',
		'autoinstall=yes: DDL runs (the switch still works both ways)',
		static function () {
			w_reset( array( 'hsw_wallet_autoinstall' => 'yes' ) );
			w_install();
			return array( ! empty( $GLOBALS['hsw_dbdelta'] ), 'dbDelta calls=' . count( $GLOBALS['hsw_dbdelta'] ) );
		}
	);

	w_case(
		'0.8',
		'already installed (hsw_wallet_db=5): init does nothing at all',
		static function () {
			w_reset( array( 'hsw_wallet_db' => '5' ) );
			global $wpdb;
			w_install();
			return array( empty( $GLOBALS['hsw_dbdelta'] ) && empty( $wpdb->queries ), 'queries=' . count( $wpdb->queries ) );
		}
	);
}

$failed = 0;
foreach ( $results as $r ) {
	$failed += $r[0] ? 0 : 1;
}
printf( "\n0b: %d cases, %d failed\n", count( $results ), $failed );
exit( $failed ? 1 : 0 );
