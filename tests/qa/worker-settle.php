<?php
/**
 * QA worker: fire one of the three WooCommerce hooks (or call settle directly)
 * after a shared start time, so several processes settle one order at once.
 *
 * Usage: php worker-settle.php <order_id> <mode: direct|payment_complete|status_processing|status_completed> <start_epoch_float>
 */
require 'C:/xampp/htdocs/sks/wp-load.php';

if ( ! defined( 'HSW_QA_SETTLE_LOG' ) ) {
	define( 'HSW_QA_SETTLE_LOG', __DIR__ . '/out/settle.log' );
}

$oid   = absint( $argv[1] ?? 0 );
$mode  = $argv[2] ?? 'direct';
$start = (float) ( $argv[3] ?? 0 );

$wait = ( $start - microtime( true ) );
if ( $wait > 0 ) {
	usleep( (int) round( $wait * 1000000 ) );
}

$t0 = microtime( true );

switch ( $mode ) {
	case 'payment_complete':
		do_action( 'woocommerce_payment_complete', $oid );
		$out = 'hook:payment_complete';
		break;
	case 'status_processing':
		do_action( 'woocommerce_order_status_processing', $oid );
		$out = 'hook:status_processing';
		break;
	case 'status_completed':
		do_action( 'woocommerce_order_status_completed', $oid );
		$out = 'hook:status_completed';
		break;
	default:
		$p   = new HSW_Wallet_Topup();
		$p->settle( $oid );
		$out = 'direct';
}

$order = wc_get_order( $oid );

echo json_encode(
	array(
		'pid'   => getmypid(),
		'mode'  => $out,
		'ok'    => true,
		'code'  => '',
		'done'  => $order ? (string) $order->get_meta( HSW_Wallet_Topup::META_DONE ) : '',
		'ms'    => (int) round( ( microtime( true ) - $t0 ) * 1000 ),
	)
) . "\n";
