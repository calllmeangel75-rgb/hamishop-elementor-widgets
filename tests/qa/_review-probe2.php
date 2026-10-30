<?php
/**
 * REVIEW probe 2 (temporary):
 *  D. two partial refunds racing → more than the paid amount?
 *  E. decide() crash window (row claimed, money not moved)
 *  F. crash between debit and _hsw_wallet_paid → refund pays nothing
 *  G. re-paying a cancelled/refunded order → free order?
 *  H. migration running on a live site → a balance written first is kept, meta lost
 */
require 'C:/xampp/htdocs/sks/wp-load.php';
require __DIR__ . '/lib-parallel.php';

global $wpdb;

$pass = 0;
$fail = 0;
function qa( $label, $ok, $detail = '' ) {
	global $pass, $fail;
	$ok ? $pass++ : $fail++;
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( '' !== $detail ? "  ({$detail})" : '' ) . "\n";
}
function mk() {
	$login = 'hswrev2_' . wp_rand( 100000, 999999 );
	$id    = (int) wp_create_user( $login, wp_generate_password( 20 ), $login . '@example.invalid' );
	update_user_meta( $id, 'hsw_review_marker', 2 );
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
function order( $uid, $total, $method = 'hsw_wallet' ) {
	$o = wc_create_order( array( 'customer_id' => $uid ) );
	$o->set_total( $total );
	$o->set_payment_method( $method );
	$o->update_meta_data( 'hsw_review_order', 2 );
	$o->save();
	return $o;
}

/* ------------------------------------------------------- D: refund race */
$uid = mk();
HSW_Wallet::set_balance( $uid, 100000, 'review' );
$o = order( $uid, 20000 );
$o->update_meta_data( HSW_Wallet_Refunds::META_PAID, 20000 );
$o->save();

/* Two refund rows exist; each worker does exactly what refund_order() does
 * between line 92 and line 102 (read the cap, then credit) with a barrier. */
$w = __DIR__ . '/_review-refund-worker.php';
$rows = qa_parallel( $w, array( $o->get_id(), 15000, 111, $uid ), 2 );
$credited_total = 0.0;
foreach ( $rows as $row ) {
	$credited_total += (float) ( $row['value'] ?? 0 );
}
qa(
	'D1 two racing 15,000 partial refunds on a 20,000 order',
	$credited_total <= 20000.001,
	'credited total=' . $credited_total . ' paid=20000 ' . wp_json_encode( $rows )
);

/* --------------------------------------------------- E: decide() crash */
$uid2 = mk();
$pending = HSW_Wallet::request_topup( $uid2, 50000, 'review' );
$q = $wpdb->prepare( "SELECT id FROM " . HSW_Wallet::table() . " WHERE user_id = %d AND status='pending'", $uid2 );
$row_id = (int) $wpdb->get_var( $q );

/* exactly the claim statement of decide() (line 1290), then the process dies */
$wpdb->query( $wpdb->prepare( "UPDATE " . HSW_Wallet::table() . " SET status='done', ref=NULL WHERE id=%d AND status='pending'", $row_id ) );

$status = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . HSW_Wallet::table() . ' WHERE id = %d', $row_id ) );
$lost   = bal( $uid2 );
$gap    = array_filter( HSW_Wallet::balance_gaps( 500 ), function ( $g ) use ( $uid2 ) {
	return $g['user_id'] === $uid2;
} );
qa( 'E1 crash after the claim leaves the request marked done', 'done' === $status );
qa( 'E2 but no money was credited', 0.0 === $lost, 'balance=' . $lost );
qa( 'E3 the gap report cannot see it (balance never moved)', empty( $gap ), wp_json_encode( array_values( $gap ) ) );

/* ------------------------------------------- F: debit done, meta missing */
$uid3 = mk();
HSW_Wallet::set_balance( $uid3, 50000, 'review' );
$o2 = order( $uid3, 20000 );
HSW_Wallet::debit( $uid3, 20000, 'spend', 'review pay', 0, 'spend:' . $o2->get_id() );
/* mark_paid() never ran (process died between line 533 and 538) */
$refunded = ( new HSW_Wallet_Refunds() )->refund_order( $o2->get_id() );
qa( 'F1 cancel after the crash refunds nothing', 0.0 === (float) $refunded, 'refunded=' . var_export( $refunded, true ) . ' balance=' . bal( $uid3 ) );

/* ------------------------------------------- G: re-pay a refunded order */
$uid4 = mk();
HSW_Wallet::set_balance( $uid4, 100000, 'review' );
if ( null === WC()->cart ) {
	wc_load_cart();
}
$o3 = order( $uid4, 20000 );
$gw = new HSW_Wallet_Gateway();
$gw->process_payment( $o3->get_id() );
$after_pay = bal( $uid4 );

( new HSW_Wallet_Refunds() )->refund_order( $o3->get_id() );
$after_refund = bal( $uid4 );

/* customer "pays" the same order again through the gateway */
$o3 = wc_get_order( $o3->get_id() );
$o3->set_status( 'pending', 'review reopen' );
$o3->save();
$res = $gw->process_payment( $o3->get_id() );
$after_repay = bal( $uid4 );
$o3 = wc_get_order( $o3->get_id() );

qa( 'G1 first payment debited 20,000', abs( $after_pay - 80000 ) < 0.01, 'balance=' . $after_pay );
qa( 'G2 refund gave it back', abs( $after_refund - 100000 ) < 0.01, 'balance=' . $after_refund );
qa( 'G3 re-payment returns success', 'success' === ( $res['result'] ?? '' ), wp_json_encode( $res ) );
qa( 'G4 re-payment takes no money', abs( $after_repay - 100000 ) < 0.01, 'balance=' . $after_repay );
qa( 'G5 and the order ends up paid (' . $o3->get_status() . ')', in_array( $o3->get_status(), array( 'processing', 'completed' ), true ) );

/* ------------------------------- H: live migration races a new balance */
$uid5 = mk();
add_user_meta( $uid5, '_hsw_wallet_balance', '5000' );
$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HSW_Wallet::balance_table() . ' WHERE user_id = %d', $uid5 ) );
$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HSW_Wallet::table() . ' WHERE user_id = %d', $uid5 ) );

/* a credit lands before the migration reaches this user */
HSW_Wallet::credit( $uid5, 100, 'admin', 'review pre-migration credit' );
delete_option( 'hsw_wallet_migration' );
$run = HSW_Wallet::migrate( 1 ); /* batch of 1 user per step, several steps */
$final = bal( $uid5 );
qa( 'H1 pre-migration credit survives', 100.0 <= $final, 'balance=' . $final );
qa( 'H2 the old 5,000 meta is NOT added', abs( $final - 100 ) < 0.01, 'balance=' . $final . ' (expected 100 if meta was dropped, 5100 if it was added)' );

foreach ( array( $uid, $uid2, $uid3, $uid4, $uid5 ) as $u ) {
	foreach ( array( $o->get_id() ?? 0, $o2->get_id() ?? 0, $o3->get_id() ?? 0 ) as $oid ) {
		$oo = $oid ? wc_get_order( $oid ) : null;
		if ( $oo && (int) $oo->get_customer_id() === (int) $u ) {
			$oo->delete( true );
		}
	}
	clean( $u );
}

echo "\nPASS={$pass} FAIL={$fail}\n";
