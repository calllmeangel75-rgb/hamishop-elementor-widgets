<?php
/**
 * REVIEW probe 3 (temporary): two *different* partial refunds racing.
 * Each worker uses its own refund id, so the unique ref cannot mask the race.
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

$login = 'hswrev3_' . wp_rand( 100000, 999999 );
$uid   = (int) wp_create_user( $login, wp_generate_password( 20 ), $login . '@example.invalid' );
update_user_meta( $uid, 'hsw_review_marker', 3 );
HSW_Wallet::set_balance( $uid, 100000, 'review' );

$order = wc_create_order( array( 'customer_id' => $uid ) );
$order->set_total( 20000 );
$order->set_payment_method( 'hsw_wallet' );
$order->update_meta_data( HSW_Wallet_Refunds::META_PAID, 20000 );
$order->update_meta_data( 'hsw_review_order', 3 );
$order->save();
$oid = $order->get_id();

$rows = qa_parallel( __DIR__ . '/_review-refund-worker.php', array( $oid, 15000, 0, $uid ), 2 );

$sum = (float) $wpdb->get_var( $wpdb->prepare(
	"SELECT COALESCE(SUM(amount),0) FROM " . HSW_Wallet::table() . " WHERE kind='refund' AND ref LIKE %s",
	'refund:' . $oid . ':%'
) );
$refs = $wpdb->get_col( $wpdb->prepare( "SELECT ref FROM " . HSW_Wallet::table() . " WHERE kind='refund' AND ref LIKE %s", 'refund:' . $oid . ':%' ) );

qa(
	'D1 two racing 15,000 refunds on a 20,000 order never exceed the paid amount',
	$sum <= 20000.001,
	'ledger refund sum=' . $sum . ' paid=20000 refs=' . wp_json_encode( $refs )
);

$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HSW_Wallet::table() . ' WHERE user_id = %d', $uid ) );
$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HSW_Wallet::balance_table() . ' WHERE user_id = %d', $uid ) );
$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->usermeta . ' WHERE user_id = %d', $uid ) );
$order->delete( true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $uid );

echo "\nPASS={$pass} FAIL={$fail}\n";
