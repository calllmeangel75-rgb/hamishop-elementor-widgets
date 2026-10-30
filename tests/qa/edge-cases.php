<?php
/**
 * QA probe: types, edge inputs and the ledger-failure rollback.
 * Usage: php edge-cases.php <user_id>
 */
require 'C:/xampp/htdocs/sks/wp-load.php';

global $wpdb;

$uid = absint( $argv[1] ?? 0 );
$bal = function () use ( $uid ) {
	return HSW_Wallet::balance( $uid );
};
$seed = function ( $value ) use ( $uid ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}hsw_wallet WHERE user_id = %d", $uid ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}hsw_wallet_ledger WHERE user_id = %d", $uid ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = '_hsw_wallet_balance'", $uid ) );
	if ( null !== $value ) {
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->prefix}hsw_wallet (user_id, balance, updated_at) VALUES (%d, %f, NOW())", $uid, $value ) );
	}
	/* Reset the per-request cache through a fresh object is impossible; re-read via DB reflection. */
	$ref = new ReflectionClass( 'HSW_Wallet' );
	$prop = $ref->getProperty( 'balance_cache' );
	$prop->setAccessible( true );
	$prop->setValue( null, array() );
};

$pass = 0;
$fail = 0;
function check( $label, $got, $want ) {
	global $pass, $fail;
	$ok = is_float( $want ) ? abs( (float) $got - $want ) < 0.001 : $got === $want;
	echo sprintf( "%-46s got=%-18s want=%-18s %s\n", $label, var_export( $got, true ), var_export( $want, true ), $ok ? 'PASS' : 'FAIL' );
	$ok ? $pass++ : $fail++;
}

/* 1. Persian digits: (float) '۳۰۰' is 0.0 → treated as "nothing to take". */
$seed( 1000 );
$r = HSW_Wallet::debit( $uid, '۳۰۰' );
check( 'persian digits debit (no-op, no error)', ! is_wp_error( $r ) ? $bal() : 'ERR', 1000.0 );

/* 2. Negative / zero debit are no-ops. */
$seed( 1000 );
HSW_Wallet::debit( $uid, -500 );
HSW_Wallet::debit( $uid, 0 );
check( 'negative + zero debit leave balance', $bal(), 1000.0 );

/* 3. Decimal amounts keep 2 decimals. */
$seed( 1000 );
HSW_Wallet::debit( $uid, 333.335 );
check( 'debit 333.335 (rounds to 333.34)', $bal(), 666.66 );

/* 4. String amount is accepted like a number. */
$seed( 1000 );
HSW_Wallet::debit( $uid, '250' );
check( 'debit "250" (string)', $bal(), 750.0 );

/* 5. Huge amount beyond DECIMAL(18,2): must not corrupt the balance. */
$seed( 1000 );
$r = HSW_Wallet::debit( $uid, 1.0e19 );
check( 'debit 1e19 → hsw_wallet_amount', is_wp_error( $r ) ? $r->get_error_code() : $bal(), 'hsw_wallet_amount' );

/* 6. Very large credit: DECIMAL(18,2) caps at 10^16. */
$seed( 0 );
$r = HSW_Wallet::credit( $uid, 99999999999999999 );
check( 'credit 1e17 (over DECIMAL(18,2))', is_wp_error( $r ) ? 'ERR' : $bal(), 'ERR' );

/* 7. Row missing: debit must fail, not create a negative row. */
$seed( null );
$r = HSW_Wallet::debit( $uid, 10 );
check( 'debit on missing row → error', is_wp_error( $r ) ? $r->get_error_code() : $bal(), 'hsw_wallet_short' );

/* 8. Empty meta only (no table row): balance reads 0 and adopt creates 0. */
$seed( null );
add_user_meta( $uid, '_hsw_wallet_balance', '' );
check( 'empty meta → balance 0', $bal(), 0.0 );

/* 9. Legacy meta (no table row yet) is adopted, duplicates summed. */
$seed( null );
add_user_meta( $uid, '_hsw_wallet_balance', '150' );
add_user_meta( $uid, '_hsw_wallet_balance', '50' );
$ref = new ReflectionClass( 'HSW_Wallet' );
$prop = $ref->getProperty( 'balance_cache' );
$prop->setAccessible( true );
$prop->setValue( null, array() );
check( 'legacy meta 150+50 adopted', $bal(), 200.0 );

/* 10. Ledger failure → balance rolled back, ledger stays empty. */
$seed( 1000 );
$wpdb->query( "RENAME TABLE {$wpdb->prefix}hsw_wallet_ledger TO {$wpdb->prefix}hsw_wallet_ledger_bak" );
$r = HSW_Wallet::debit( $uid, 300 );
$after_fail = $bal();
$wpdb->query( "RENAME TABLE {$wpdb->prefix}hsw_wallet_ledger_bak TO {$wpdb->prefix}hsw_wallet_ledger" );
check( 'ledger write fails → error code', is_wp_error( $r ) ? $r->get_error_code() : 'none', 'hsw_wallet_ledger' );
check( 'ledger write fails → balance restored', $after_fail, 1000.0 );

/* 11. Same ref twice → duplicate error, money only once. */
$seed( 0 );
HSW_Wallet::credit( $uid, 500, 'topup', 'dup test', 0, 'qa:dup' );
$r2 = HSW_Wallet::credit( $uid, 500, 'topup', 'dup test', 0, 'qa:dup' );
check( 'duplicate ref → hsw_wallet_duplicate', is_wp_error( $r2 ) ? $r2->get_error_code() : 'none', 'hsw_wallet_duplicate' );
check( 'duplicate ref → balance 500 only', $bal(), 500.0 );

echo "\nPASS={$pass} FAIL={$fail}\n";
