<?php
/**
 * QA probe: does MySQL GET_LOCK actually serialise separate CLI processes here?
 * Usage: php lock-probe.php <start_epoch_float>
 */
require 'C:/xampp/htdocs/sks/wp-load.php';

global $wpdb;

$start = (float) ( $argv[1] ?? 0 );
$wait  = $start - microtime( true );
if ( $wait > 0 ) {
	usleep( (int) round( $wait * 1000000 ) );
}

$t = microtime( true );
$got = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', 'hsw_qa_lock' ) );
$acquired = ( '1' === (string) $got );
$waited   = (int) round( ( microtime( true ) - $t ) * 1000 );

if ( $acquired ) {
	usleep( 800000 );
	$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', 'hsw_qa_lock' ) );
}

echo json_encode( array( 'pid' => getmypid(), 'got' => (string) $got, 'acquired' => $acquired, 'waited_ms' => $waited ) ) . "\n";
