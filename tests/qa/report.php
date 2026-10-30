<?php
/**
 * QA report: seeded state + ledger consistency for one user.
 *
 * Usage: php report.php <user_id>
 */
require 'C:/xampp/htdocs/sks/wp-load.php';

global $wpdb;

$uid = absint( $argv[1] ?? 0 );

$meta = get_user_meta( $uid, '_hsw_wallet_balance', true );
$rows = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT kind, status, COUNT(*) c, SUM(amount) s FROM {$wpdb->prefix}hsw_wallet_ledger WHERE user_id = %d GROUP BY kind, status",
		$uid
	)
);

$sum    = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM {$wpdb->prefix}hsw_wallet_ledger WHERE user_id = %d", $uid ) );
$count  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}hsw_wallet_ledger WHERE user_id = %d", $uid ) );
$minBal = $wpdb->get_var( $wpdb->prepare( "SELECT MIN(amount) FROM {$wpdb->prefix}hsw_wallet_ledger WHERE user_id = %d", $uid ) );

echo json_encode(
	array(
		'user'        => $uid,
		'meta_raw'    => var_export( $meta, true ),
		'balance'     => (float) HSW_Wallet::balance( $uid ),
		'ledger_rows' => $count,
		'ledger_sum'  => $sum,
		'match'       => abs( $sum - (float) HSW_Wallet::balance( $uid ) ) < 0.011,
		'groups'      => $rows,
	)
) . "\n";
