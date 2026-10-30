<?php
/**
 * REVIEW worker: the exact read-then-credit sequence of refund_order()
 * (class-hsw-wallet-pay.php lines 92–102) with a barrier in the middle, so the
 * TOCTOU between "how much is left to refund" and "credit it" becomes visible.
 *
 *   php _review-refund-worker.php <order_id> <amount> <refund_id> <user_id> <start>
 */
require 'C:/xampp/htdocs/sks/wp-load.php';

$oid    = absint( $argv[1] ?? 0 );
$amount = (float) ( $argv[2] ?? 0 );
$rid    = absint( $argv[3] ?? 0 ) * 1000 + ( getmypid() % 997 ) + 10;
$uid    = absint( $argv[4] ?? 0 );
$start  = (float) ( $argv[5] ?? 0 );

$order = wc_get_order( $oid );
$paid  = (float) $order->get_meta( HSW_Wallet_Refunds::META_PAID );

/* line 92: amount = min( refund amount, paid - refunded_total ) */
$amount = min( $amount, max( 0, $paid - HSW_Wallet_Refunds::refunded_total( $oid ) ) );

$wait = $start - microtime( true );
if ( $wait > 0 ) {
	usleep( (int) round( $wait * 1000000 ) );
}

/* line 102: credit with refund:<order>:<refund_id> */
$credited = HSW_Wallet::credit( $uid, $amount, 'refund', 'review race', 0, 'refund:' . $oid . ':' . $rid );

echo wp_json_encode(
	array(
		'pid'    => getmypid(),
		'amount' => $amount,
		'ok'     => ! is_wp_error( $credited ),
		'code'   => is_wp_error( $credited ) ? $credited->get_error_code() : '',
		'value'  => is_wp_error( $credited ) ? 0 : $credited,
	)
) . "\n";
