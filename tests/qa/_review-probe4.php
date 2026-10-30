<?php
/**
 * REVIEW probe 4 (temporary): inputs, IDOR and the admin gate.
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
	$login = 'hswrev4_' . wp_rand( 100000, 999999 );
	$id    = (int) wp_create_user( $login, wp_generate_password( 20 ), $login . '@example.invalid' );
	( new WP_User( $id ) )->set_role( $role );
	update_user_meta( $id, 'hsw_review_marker', 4 );
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
function rest_call( $route, array $params ) {
	$req = new WP_REST_Request( 'POST', $route );
	$req->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
	$req->set_body_params( $params );
	$res = rest_do_request( $req );
	return array( $res->get_status(), $res->get_data() );
}

$uid = mk();
$uidB = mk();
HSW_Wallet::set_balance( $uid, 100000, 'review' );
wp_set_current_user( $uid );
rest_get_server(); /* fires rest_api_init */

/* ------------------------------------------------------- input matrix */
$cases = array(
	'-50000'          => 'negative amount',
	'0'               => 'zero',
	'NaN'             => 'NaN string',
	'INF'             => 'INF string',
	'1e400'           => 'overflow float',
	'۱۲۳۴۵۶'          => 'persian digits',
	''                => 'empty string',
	'50000.999'       => 'more than two decimals',
	'99999999999999999999' => 'beyond int range',
);

foreach ( $cases as $value => $label ) {
	list( $status, $data ) = rest_call( '/hsw/v1/account/wallet/topup', array( 'amount' => $value ) );
	$amount_stored = (float) $wpdb->get_var( $wpdb->prepare(
		"SELECT COALESCE(MAX(amount),0) FROM " . HSW_Wallet::table() . " WHERE user_id = %d AND status='pending'",
		$uid
	) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM " . HSW_Wallet::table() . " WHERE user_id = %d AND status='pending'", $uid ) );
	echo sprintf( "      %-24s status=%-3d stored=%-12s %s\n", $label, $status, $amount_stored, is_array( $data ) && isset( $data['message'] ) ? 'msg' : '' );
	qa( 'input "' . $label . '" never stores a negative/pending amount ≤ 0', $amount_stored > 0 || 400 === $status, 'status=' . $status . ' stored=' . $amount_stored );
}

/* negative becomes positive? */
list( $status, $data ) = rest_call( '/hsw/v1/account/wallet/topup', array( 'amount' => '-50000' ) );
$neg = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(MAX(amount),0) FROM " . HSW_Wallet::table() . " WHERE user_id = %d AND status='pending'", $uid ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM " . HSW_Wallet::table() . " WHERE user_id = %d AND status='pending'", $uid ) );
qa( 'N1 a negative amount is rejected, not converted to positive', 400 === $status, 'status=' . $status . ' stored=' . $neg );

/* ------------------------------------------------------------- IDOR */
list( $status, $data ) = rest_call( '/hsw/v1/account/wallet/topup', array( 'amount' => '50000', 'user_id' => $uidB ) );
$b_pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . HSW_Wallet::table() . " WHERE user_id = %d", $uidB ) );
$a_pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . HSW_Wallet::table() . " WHERE user_id = %d AND status='pending'", $uid ) );
qa( 'I1 user_id in the body is ignored (no IDOR)', 0 === $b_pending && 1 === $a_pending, "B={$b_pending} A={$a_pending}" );

wp_set_current_user( 0 );
list( $status, $data ) = rest_call( '/hsw/v1/account/wallet/topup', array( 'amount' => '50000' ) );
qa( 'I2 logged-out request is refused', 401 === $status || 403 === $status, 'status=' . $status );

/* -------------------------------------------------- admin gate as a customer */
wp_set_current_user( $uid );
$_REQUEST['_wpnonce'] = wp_create_nonce( 'hsw_wallet_action' );
$_REQUEST['id']       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . HSW_Wallet::table() . " WHERE user_id = %d AND status='pending'", $uid ) );
$_REQUEST['decision'] = 'approve';

$died = '';
add_filter( 'wp_die_handler', function () {
	return function ( $message ) {
		throw new RuntimeException( 'wp_die:' . ( is_wp_error( $message ) ? $message->get_error_code() : (string) $message ) );
	};
} );

try {
	HSW_Wallet::instance()->handle_admin_action();
} catch ( Throwable $e ) {
	$died = $e->getMessage();
}
$still_pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . HSW_Wallet::table() . " WHERE user_id = %d AND status='pending'", $uid ) );
qa( 'A1 customer cannot approve a top-up', 1 === $still_pending && '' !== $died, 'pending=' . $still_pending . ' died=' . substr( $died, 0, 40 ) );

/* a manager with a bad nonce */
$mgr = mk( 'shop_manager' );
wp_set_current_user( $mgr );
$_REQUEST['_wpnonce'] = 'bogus';
$died = '';
try {
	HSW_Wallet::instance()->handle_admin_action();
} catch ( Throwable $e ) {
	$died = $e->getMessage();
}
qa( 'A2 manager with a bad nonce is stopped', '' !== $died, 'died=' . substr( $died, 0, 40 ) );

/* ------------------------------------- migration with an out-of-range value */
$uidC = mk();
add_user_meta( $uidC, '_hsw_wallet_balance', '99999999999999999' ); /* 1e17 > DECIMAL(18,2) */
$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HSW_Wallet::balance_table() . ' WHERE user_id = %d', $uidC ) );
delete_option( 'hsw_wallet_migration' );
$run = HSW_Wallet::migrate( 200 );
$report = HSW_Wallet::migration_report();
$landed = $wpdb->get_var( $wpdb->prepare( 'SELECT balance FROM ' . HSW_Wallet::balance_table() . ' WHERE user_id = %d', $uidC ) );
$flags = array();
foreach ( (array) ( $report['anomaly_list'] ?? array() ) as $a ) {
	if ( (int) $a['user_id'] === $uidC ) {
		$flags = $a['flags'];
	}
}
qa( 'M1 an out-of-range legacy value is reported (not silently clamped)', in_array( 'non-canonical', $flags, true ) || in_array( 'out-of-range', $flags, true ), 'stored=' . var_export( $landed, true ) . ' flags=' . wp_json_encode( $flags ) );

foreach ( array( $uid, $uidB, $mgr, $uidC ) as $u ) {
	clean( $u );
}

echo "\nPASS={$pass} FAIL={$fail}\n";
