<?php
/**
 * REVIEW probe 1 (temporary, deleted afterwards):
 *  A. can a plain customer set their own wallet balance from profile.php?
 *  B. does set_balance leave the ledger out of sync?
 *  C. can repair_gaps collide with itself on the same amount?
 */
require 'C:/xampp/htdocs/sks/wp-load.php';

global $wpdb;

$pass = 0;
$fail = 0;
function qa( $label, $ok, $detail = '' ) {
	global $pass, $fail;
	$ok ? $pass++ : $fail++;
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( '' !== $detail ? "  ({$detail})" : '' ) . "\n";
}

function mk( $role = 'customer' ) {
	$login = 'hswrev_' . wp_rand( 100000, 999999 );
	$id    = (int) wp_create_user( $login, wp_generate_password( 20 ), $login . '@example.invalid' );
	( new WP_User( $id ) )->set_role( $role );
	update_user_meta( $id, 'hsw_review_marker', 1 );

	return $id;
}

function clean( $uid ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HSW_Wallet::table() . ' WHERE user_id = %d', $uid ) );
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HSW_Wallet::balance_table() . ' WHERE user_id = %d', $uid ) );
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->usermeta . ' WHERE user_id = %d', $uid ) );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $uid );
}

function bal( $uid ) {
	$ref  = new ReflectionClass( 'HSW_Wallet' );
	$prop = $ref->getProperty( 'balance_cache' );
	$prop->setAccessible( true );
	$prop->setValue( null, array() );

	return HSW_Wallet::balance( $uid );
}

/* ------------------------------------------------------------------ A */
$uid = mk( 'customer' );
HSW_Wallet::set_balance( $uid, 0, 'review' );

wp_set_current_user( $uid );

ob_start();
$admin = new HSW_Account_Admin( HSW_Account::instance() );
$admin->user_fields( get_userdata( $uid ) );
$profile_html = ob_get_clean();

qa( 'A1 profile.php shows the wallet field to a customer', false !== strpos( $profile_html, 'hsw-wallet-balance' ), 'html length=' . strlen( $profile_html ) );

preg_match( '/name="hsw_account_user_nonce" value="([^"]+)"/', $profile_html, $m );
$nonce = $m[1] ?? '';

$_POST['hsw_account_user_nonce'] = $nonce;
$_POST['hsw_wallet_balance']     = '999999';
$admin->save_user_fields( $uid );
unset( $_POST['hsw_account_user_nonce'], $_POST['hsw_wallet_balance'] );

$after = bal( $uid );
qa( 'A2 customer raised their own balance via profile update', 999999.0 === $after, 'balance=' . $after );

/* nonce is bound to the user id: a second customer cannot use it */
$uid2 = mk( 'customer' );
wp_set_current_user( $uid2 );
$_POST['hsw_account_user_nonce'] = $nonce;
$_POST['hsw_wallet_balance']     = '55555';
$admin->save_user_fields( $uid2 );
unset( $_POST['hsw_account_user_nonce'], $_POST['hsw_wallet_balance'] );
qa( 'A3 nonce from another user is rejected', 0.0 === bal( $uid2 ), 'balance=' . bal( $uid2 ) );

/* ------------------------------------------------------------------ B */
$uid3 = mk( 'customer' );
HSW_Wallet::set_balance( $uid3, 0, 'review' );
HSW_Wallet::set_balance( $uid3, 5000, 'review set' );

$ledger = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM " . HSW_Wallet::table() . " WHERE user_id = %d AND status='done'", $uid3 ) );
$gaps   = array_values( array_filter( HSW_Wallet::balance_gaps( 500 ), function ( $g ) use ( $uid3 ) {
	return $g['user_id'] === $uid3;
} ) );
qa( 'B1 set_balance writes amount 0 in the ledger', 0.0 === $ledger, 'ledger sum=' . $ledger . ' balance=' . bal( $uid3 ) );
qa( 'B2 therefore the gap report flags a legitimate admin edit', 1 === count( $gaps ), wp_json_encode( $gaps ) );

/* ------------------------------------------------------------------ C */
$uid4 = mk( 'customer' );
HSW_Wallet::set_balance( $uid4, 0, 'review' );
$wpdb->query( $wpdb->prepare( 'UPDATE ' . HSW_Wallet::balance_table() . ' SET balance = 100 WHERE user_id = %d', $uid4 ) );
$first = HSW_Wallet::repair_gaps( 500 );
qa( 'C1 first repair closes the gap', empty( array_filter( HSW_Wallet::balance_gaps( 500 ), function ( $g ) use ( $uid4 ) {
	return $g['user_id'] === $uid4;
} ) ) );

/* a second, different gap of the SAME amount must still be repairable */
$wpdb->query( $wpdb->prepare( 'UPDATE ' . HSW_Wallet::balance_table() . ' SET balance = 200 WHERE user_id = %d', $uid4 ) );
$second = array_values( array_filter( HSW_Wallet::repair_gaps( 500 ), function ( $r ) use ( $uid4 ) {
	return $r['user_id'] === $uid4;
} ) );
$remaining = array_values( array_filter( HSW_Wallet::balance_gaps( 500 ), function ( $g ) use ( $uid4 ) {
	return $g['user_id'] === $uid4;
} ) );
qa( 'C2 second gap of the same amount is repaired (ref would be identical)', ! empty( $second ), 'second=' . wp_json_encode( $second ) );
qa( 'C3 gap after second repair', empty( $remaining ), wp_json_encode( $remaining ) );

foreach ( array( $uid, $uid2, $uid3, $uid4 ) as $u ) {
	clean( $u );
}

echo "\nPASS={$pass} FAIL={$fail}\n";
