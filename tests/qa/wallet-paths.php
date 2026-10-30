<?php
/**
 * QA: every money path, under real parallel processes.
 *
 *   php tests/qa/wallet-paths.php
 *
 * Uses one throw-away customer and temporary orders (`hsw_qa_` marker), and
 * deletes all of it at the end. Covers:
 *   1. ten simultaneous top-up requests  → one pending row
 *   2. six simultaneous approvals        → one credit
 *   3. wallet checkout, clicked twice     → one debit
 *   4. five simultaneous cancels          → one refund
 *   5. partial refund + full cancel       → exact total, once
 *   6. an interrupted write               → gap report + ledger-only repair
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

/* ---------------------------------------------------------------- fixtures */

$login = 'hswqa_paths_' . wp_rand( 1000, 9999 );
$uid   = wp_create_user( $login, wp_generate_password( 20 ), $login . '@example.invalid' );
if ( is_wp_error( $uid ) ) {
	echo "cannot create user\n";
	exit( 1 );
}
$uid = (int) $uid;
update_user_meta( $uid, 'hsw_qa_marker', 1 );

$order_ids = array();

function qa_order( $uid, $total, array &$order_ids ) {
	$order = wc_create_order( array( 'customer_id' => $uid ) );
	$order->set_total( $total );
	$order->set_payment_method( 'hsw_wallet' );
	$order->update_meta_data( 'hsw_qa_order', 1 );
	$order->save();
	$order_ids[] = $order->get_id();

	return $order;
}

function qa_balance( $uid ) {
	$ref  = new ReflectionClass( 'HSW_Wallet' );
	$prop = $ref->getProperty( 'balance_cache' );
	$prop->setAccessible( true );
	$prop->setValue( null, array() );

	return HSW_Wallet::balance( $uid );
}

function qa_reset( $uid, $amount ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HSW_Wallet::table() . ' WHERE user_id = %d', $uid ) );
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HSW_Wallet::balance_table() . ' WHERE user_id = %d', $uid ) );
	HSW_Wallet::set_balance( $uid, $amount, 'QA reset' );
}

/* ------------------------------------------------- 1) ten top-up requests */

qa_reset( $uid, 0 );

$rows = qa_parallel( __DIR__ . '/worker-job.php', array( 'topup', $uid, 50000 ), 10 );
$ok   = qa_count( $rows, 'ok' );
$open = qa_count( array_filter( $rows, function ( $r ) {
	return isset( $r['code'] ) && 'hsw_wallet_open' === $r['code'];
} ), 'ok' );

$pending = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HSW_Wallet::table() . " WHERE user_id = %d AND status = 'pending'", $uid ) );
qa( '10 parallel top-up requests → 1 accepted', 1 === $ok, "ok={$ok} open-errors={$open}" );
qa( 'exactly one pending row', 1 === $pending, "rows={$pending}" );

/* ---------------------------------------------------- 2) six approvals */

$pending_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HSW_Wallet::table() . " WHERE user_id = %d AND status = 'pending'", $uid ) );
$rows       = qa_parallel( __DIR__ . '/worker-job.php', array( 'decide', $pending_id ), 6 );
$ok         = qa_count( $rows, 'ok' );
qa( '6 parallel approvals → 1 accepted', 1 === $ok, "ok={$ok}" );
qa( 'wallet credited once', 50000.0 === qa_balance( $uid ), 'balance=' . qa_balance( $uid ) );

/* -------------------------------------------- 3) wallet checkout, twice */

$order = qa_order( $uid, 20000, $order_ids );
if ( null === WC()->cart ) {
	wc_load_cart();
}

$gateway = new HSW_Wallet_Gateway();
$r1      = $gateway->process_payment( $order->get_id() );
$r2      = $gateway->process_payment( $order->get_id() );

qa( 'first checkout succeeds', 'success' === ( $r1['result'] ?? '' ) );
qa( 'second click returns success too (idempotent)', 'success' === ( $r2['result'] ?? '' ) );
qa( 'wallet debited once', 30000.0 === qa_balance( $uid ), 'balance=' . qa_balance( $uid ) );
qa(
	'one spend row for the order',
	1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HSW_Wallet::table() . ' WHERE ref = %s', 'spend:' . $order->get_id() ) )
);

/* ------------------------------------------- 4) five simultaneous cancels */

$rows = qa_parallel( __DIR__ . '/worker-job.php', array( 'refund', $order->get_id() ), 5 );
/* The losers return 0.0 (nothing moved) without an error, so count money. */
$credited = 0;
foreach ( $rows as $row ) {
	if ( isset( $row['value'] ) && (float) $row['value'] > 0 ) {
		$credited++;
	}
}
qa( '5 parallel cancels → exactly one credited', 1 === $credited, 'credited=' . $credited . ' raw=' . wp_json_encode( $rows ) );
qa( 'balance restored to 50000', 50000.0 === qa_balance( $uid ), 'balance=' . qa_balance( $uid ) );
qa(
	'one refund row',
	1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HSW_Wallet::table() . " WHERE kind = 'refund' AND ref LIKE %s", 'refund:' . $order->get_id() . ':%' ) )
);

/* status change to refunded must not pay again */
$order->update_status( 'refunded', 'QA state' );
qa( 'refunded status adds nothing', 50000.0 === qa_balance( $uid ), 'balance=' . qa_balance( $uid ) );

/* ------------------------------------- 5) partial refund then full cancel */

$order2 = qa_order( $uid, 20000, $order_ids );
$gateway->process_payment( $order2->get_id() );
qa( 'second order paid', 30000.0 === qa_balance( $uid ), 'balance=' . qa_balance( $uid ) );

$refund1 = wc_create_refund( array( 'order_id' => $order2->get_id(), 'amount' => 5000, 'reason' => 'QA partial' ) );
qa( 'partial refund credited 5000', 35000.0 === qa_balance( $uid ), 'balance=' . qa_balance( $uid ) );

$refund2 = wc_create_refund( array( 'order_id' => $order2->get_id(), 'amount' => 5000, 'reason' => 'QA partial 2' ) );
qa( 'second partial refund credited', 40000.0 === qa_balance( $uid ), 'balance=' . qa_balance( $uid ) );

$order2->update_status( 'cancelled', 'QA cancel' );
qa( 'cancel refunds the rest (10000)', 50000.0 === qa_balance( $uid ), 'balance=' . qa_balance( $uid ) );
qa( 'refunded total equals the paid amount', 20000.0 === HSW_Wallet_Refunds::refunded_total( $order2->get_id() ), 'sum=' . HSW_Wallet_Refunds::refunded_total( $order2->get_id() ) );

$order2->update_status( 'cancelled', 'QA cancel again' );
qa( 'second cancel adds nothing', 50000.0 === qa_balance( $uid ), 'balance=' . qa_balance( $uid ) );

/* --------------------------------------------- 6) top-up order, settled 3× */

$topup_order = qa_order( $uid, 25000, $order_ids );
$topup_order->update_meta_data( HSW_Wallet_Topup::META_USER, $uid );
$topup_order->save();

$before_settle = qa_balance( $uid );
$rows          = qa_parallel( __DIR__ . '/worker-job.php', array( 'settle', $topup_order->get_id() ), 3 );
$ok            = qa_count( $rows, 'ok' );
$after_settle  = qa_balance( $uid );

qa( '3 parallel settles all return cleanly', 3 === $ok, "ok={$ok}" );
qa( 'top-up credited exactly once', abs( ( $after_settle - $before_settle ) - 25000 ) < 0.01, sprintf( 'delta=%.2f', $after_settle - $before_settle ) );
qa(
	'one topup row',
	1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HSW_Wallet::table() . ' WHERE ref = %s', 'topup:' . $topup_order->get_id() ) )
);

/* --------------------------------------------- 7) interrupted write + repair */

/* Simulate: balance moved, PHP died before the ledger insert. */
$wpdb->query( $wpdb->prepare( 'UPDATE ' . HSW_Wallet::balance_table() . ' SET balance = balance - 1234 WHERE user_id = %d', $uid ) );
qa_balance( $uid );

$gap = array_values( array_filter( HSW_Wallet::balance_gaps( 500 ), function ( $g ) use ( $uid ) {
	return $g['user_id'] === $uid;
} ) );
qa( 'gap report sees the interrupted write', 1 === count( $gap ) && abs( $gap[0]['diff'] - ( -1234 ) ) < 0.01, wp_json_encode( $gap ) );

$before = qa_balance( $uid );
$repair = HSW_Wallet::repair_gaps( 500 );
$after  = qa_balance( $uid );
$gap2   = array_values( array_filter( HSW_Wallet::balance_gaps( 500 ), function ( $g ) use ( $uid ) {
	return $g['user_id'] === $uid;
} ) );

qa( 'repair writes a ledger line', ! empty( $repair ) );
qa( 'repair does not move money', abs( $before - $after ) < 0.01, sprintf( '%.2f → %.2f', $before, $after ) );
qa( 'gap closed', empty( $gap2 ), wp_json_encode( $gap2 ) );

/* ---------------------------------------------------------------- cleanup */

$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HSW_Wallet::table() . ' WHERE user_id = %d', $uid ) );
$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HSW_Wallet::balance_table() . ' WHERE user_id = %d', $uid ) );
foreach ( $order_ids as $oid ) {
	$o = wc_get_order( $oid );
	if ( $o ) {
		$o->delete( true );
	}
}
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $uid );

echo "\nPASS={$pass} FAIL={$fail}\n";
exit( $fail > 0 ? 1 : 0 );
