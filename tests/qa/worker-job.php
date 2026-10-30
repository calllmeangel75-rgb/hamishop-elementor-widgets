<?php
/**
 * QA worker: one wallet operation, started at a shared instant.
 *
 *   php worker-job.php <job> <arg1> <arg2> <start_epoch>
 *
 * Jobs: topup | decide | refund | walletpay | settle | spend
 * Prints one JSON line.
 */
require 'C:/xampp/htdocs/sks/wp-load.php';

$job   = (string) ( $argv[1] ?? '' );
$a1    = (string) ( $argv[2] ?? '' );
$a2    = (string) ( $argv[3] ?? '' );
$start = (float) ( $argv[4] ?? 0 );

$wait = $start - microtime( true );
if ( $wait > 0 ) {
	usleep( (int) round( $wait * 1000000 ) );
}

$t0  = microtime( true );
$res = null;

switch ( $job ) {
	case 'topup':
		$res = HSW_Wallet::request_topup( absint( $a1 ), (float) $a2, 'QA parallel request' );
		break;

	case 'decide':
		$res = HSW_Wallet::decide( absint( $a1 ), true, 'QA parallel decide' );
		break;

	case 'refund':
		$r   = new HSW_Wallet_Refunds();
		$res = $r->refund_order( absint( $a1 ), 0 );
		break;

	case 'walletpay':
		$gateway = new HSW_Wallet_Gateway();
		$res     = $gateway->process_payment( absint( $a1 ) );
		break;

	case 'settle':
		$topup = new HSW_Wallet_Topup();
		$topup->settle( absint( $a1 ) );
		$res = true;
		break;

	case 'spend':
		$res = HSW_Wallet::debit( absint( $a1 ), (float) $a2, 'spend', 'QA spend', 0, 'qa:' . getmypid() );
		break;
}

$is_error = is_wp_error( $res );

echo wp_json_encode(
	array(
		'pid'   => getmypid(),
		'job'   => $job,
		'ok'    => ! $is_error,
		'code'  => $is_error ? $res->get_error_code() : '',
		'value' => is_scalar( $res ) ? $res : null,
		'ms'    => (int) round( ( microtime( true ) - $t0 ) * 1000 ),
	)
) . "\n";
