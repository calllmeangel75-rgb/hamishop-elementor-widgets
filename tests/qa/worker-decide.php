<?php
/**
 * QA worker: approve/reject one pending top-up request after a shared start.
 * Usage: php worker-decide.php <ledger_id> <approve:1|0> <start_epoch_float>
 */
require 'C:/xampp/htdocs/sks/wp-load.php';

$id     = absint( $argv[1] ?? 0 );
$approve = '1' === (string) ( $argv[2] ?? '1' );
$start  = (float) ( $argv[3] ?? 0 );

$wait = $start - microtime( true );
if ( $wait > 0 ) {
	usleep( (int) round( $wait * 1000000 ) );
}

$r = HSW_Wallet::decide( $id, $approve, 'QA parallel decide' );

echo json_encode(
	array(
		'pid' => getmypid(),
		'ok'  => ! is_wp_error( $r ),
		'code' => is_wp_error( $r ) ? $r->get_error_code() : '',
	)
) . "\n";
