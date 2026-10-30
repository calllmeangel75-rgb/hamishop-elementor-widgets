<?php
/**
 * QA helper: run several PHP workers as real parallel OS processes.
 *
 * The existing PowerShell helper only runs on Windows; CI and this suite need
 * something portable. Every worker takes a shared start timestamp and waits for
 * it, so all of them hit the database in the same instant.
 *
 * @param string   $worker  Path of the worker script.
 * @param string[] $argv    Arguments *without* the start time.
 * @param int      $count   How many processes.
 * @param float    $lead    Seconds to wait before the shared start.
 * @return array<int,array> Decoded JSON lines (empty when a worker printed nothing).
 */
function qa_parallel( $worker, array $argv, $count, $lead = 4.0 ) {
	$start = microtime( true ) + $lead;
	$procs = array();

	$cmd = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $worker );
	foreach ( $argv as $arg ) {
		$cmd .= ' ' . escapeshellarg( (string) $arg );
	}
	$cmd .= ' ' . escapeshellarg( (string) $start );

	for ( $i = 0; $i < $count; $i++ ) {
		$descriptor = array(
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);
		$proc = proc_open( $cmd, $descriptor, $pipes, null, null );
		if ( is_resource( $proc ) ) {
			$procs[] = array( $proc, $pipes );
		}
	}

	$out = array();
	foreach ( $procs as $item ) {
		list( $proc, $pipes ) = $item;
		$stdout = stream_get_contents( $pipes[1] );
		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		proc_close( $proc );

		$lines = preg_split( '/\r?\n/', trim( (string) $stdout ) );
		$last  = end( $lines );
		$data  = json_decode( (string) $last, true );

		$out[] = is_array( $data ) ? $data : array( 'raw' => trim( $stdout . ' ' . $stderr ) );
	}

	return $out;
}

/**
 * Sum a column of worker results.
 *
 * @param array  $rows   Results.
 * @param string $key    Field.
 * @return int
 */
function qa_count( array $rows, $key ) {
	$n = 0;
	foreach ( $rows as $row ) {
		if ( ! empty( $row[ $key ] ) ) {
			$n++;
		}
	}

	return $n;
}
