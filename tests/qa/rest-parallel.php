<?php
/**
 * QA: the real REST endpoints, hit in parallel.
 *
 *   php tests/qa/rest-parallel.php
 *
 * Unlike calling `HSW_Wallet::request_topup()` directly, these requests go
 * through `rest_get_server()`: route registration, the argument schema and the
 * permission callback all run, then the callback. Two simultaneous requests for
 * the same action must produce one result, not two.
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


/** Balance without the per-request memory of HSW_Wallet. */
function qa_balance( $uid ) {
$ref  = new ReflectionClass( 'HSW_Wallet' );
$prop = $ref->getProperty( 'balance_cache' );
$prop->setAccessible( true );
$prop->setValue( null, array() );

return HSW_Wallet::balance( $uid );
}
$login = 'hswqa_rest_' . wp_rand( 1000, 9999 );
$uid   = (int) wp_create_user( $login, wp_generate_password( 20 ), $login . '@example.invalid' );
update_user_meta( $uid, 'hsw_qa_marker', 1 );

$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HSW_Wallet::table() . ' WHERE user_id = %d', $uid ) );
$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HSW_Wallet::balance_table() . ' WHERE user_id = %d', $uid ) );
HSW_Wallet::set_balance( $uid, 0, 'QA rest' );

/* ------------------------------------------- 1) ten REST top-up requests */

$rows = qa_parallel(
	__DIR__ . '/worker-rest.php',
	array( 'POST', '/hsw/v1/account/wallet/topup', 'amount=50000', $uid ),
	10
);

$accepted = 0;
$rejected = 0;
foreach ( $rows as $row ) {
	if ( ! empty( $row['ok'] ) ) {
		$accepted++;
	} else {
		$rejected++;
	}
}
$pending = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HSW_Wallet::table() . " WHERE user_id = %d AND status = 'pending'", $uid ) );

qa( '10 parallel REST top-ups → 1 accepted', 1 === $accepted, "accepted={$accepted} rejected={$rejected} " . wp_json_encode( array_slice( $rows, 0, 2 ) ) );
qa( 'one pending row', 1 === $pending, "rows={$pending}" );

/* -------------------------------- 2) parallel REST point conversions */

$pending_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HSW_Wallet::table() . " WHERE user_id = %d AND status = 'pending'", $uid ) );
$pending_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HSW_Wallet::table() . " WHERE user_id = %d AND status = 'pending'", $uid ) );
HSW_Wallet::decide( $pending_id, true, 'QA rest approve' );

$before = qa_balance( $uid );

/*
 * The QA user is given 1000 points. Four parallel conversions of 100 points are
 * all affordable, so all four must pay out (the old ref-based guard refused one
 * of them); four parallel conversions of 400 on the remaining 600 must pay out
 * exactly once.
 */
$rows = qa_parallel(
	__DIR__ . '/worker-rest.php',
	array( 'POST', '/hsw/v1/account/wallet/convert', 'points=100', $uid ),
	4
);

$credited = 0;
foreach ( $rows as $row ) {
	if ( ! empty( $row['ok'] ) ) {
		$credited++;
	}
}

$used_before_over = HSW_Wallet::points_used( $uid );

qa( '4 affordable parallel conversions → 4 payouts', 4 === $credited, "credited={$credited} " . wp_json_encode( $rows ) );
qa( 'points recorded exactly (4 × 100)', 400 === $used_before_over, "used={$used_before_over}" );

$rows = qa_parallel(
	__DIR__ . '/worker-rest.php',
	array( 'POST', '/hsw/v1/account/wallet/convert', 'points=400', $uid ),
	4
);

$credited = 0;
foreach ( $rows as $row ) {
	if ( ! empty( $row['ok'] ) ) {
		$credited++;
	}
}

$used      = HSW_Wallet::points_used( $uid );
$wallet    = qa_balance( $uid );
$rows_in_ledger = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HSW_Wallet::table() . " WHERE user_id = %d AND kind = 'points'", $uid ) );

qa( '4 over-limit parallel conversions → exactly 1 payout', 1 === $credited, "credited={$credited} " . wp_json_encode( $rows ) );
qa( 'points never exceed the earned total', 800 === $used, "used={$used}" );
qa( 'ledger has one row per payout', 5 === $rows_in_ledger, "rows={$rows_in_ledger}" );
qa( 'wallet grew by (800 points × point value)', $wallet === $before + ( 800 * 100 ), "before={$before} after={$wallet}" );

/* ---------------------------------------------------------------- cleanup */

$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HSW_Wallet::table() . ' WHERE user_id = %d', $uid ) );
$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HSW_Wallet::balance_table() . ' WHERE user_id = %d', $uid ) );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $uid );

echo "\nPASS={$pass} FAIL={$fail}\n";
exit( $fail > 0 ? 1 : 0 );
