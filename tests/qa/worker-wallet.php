<?php
/**
 * QA worker: perform ONE wallet operation after a shared start time, so several
 * OS processes hit the database at the same instant.
 *
 * Usage: php worker.php debit <user_id> <amount> <start_epoch_float>
 *        php worker.php credit <user_id> <amount> <start_epoch_float>
 */
require 'C:/xampp/htdocs/sks/wp-load.php';

$op     = $argv[1] ?? '';
$uid    = absint( $argv[2] ?? 0 );
$amount = (float) ( $argv[3] ?? 0 );
$start  = (float) ( $argv[4] ?? 0 );

$wait = ( $start - microtime( true ) );
if ( $wait > 0 ) {
	usleep( (int) round( $wait * 1000000 ) );
}

$t0 = microtime( true );

if ( 'debit' === $op ) {
	$result = HSW_Wallet::debit( $uid, $amount, 'spend', 'QA concurrent debit', 0, 'qa:' . getmypid() );
} elseif ( 'credit' === $op ) {
	$result = HSW_Wallet::credit( $uid, $amount, 'admin', 'QA concurrent credit', 0, 'qa:' . getmypid() );
} else {
	echo json_encode( array( 'error' => 'unknown op' ) ) . "\n";
	exit( 1 );
}

echo json_encode(
	array(
		'op'    => $op,
		'pid'   => getmypid(),
		'ok'    => ! is_wp_error( $result ),
		'code'  => is_wp_error( $result ) ? $result->get_error_code() : '',
		'bal'   => is_wp_error( $result ) ? null : (float) $result,
		'ms'    => (int) round( ( microtime( true ) - $t0 ) * 1000 ),
	)
) . "\n";
