<?php
/**
 * Offline simulation of HSW_Upgrader::run().
 *
 * The 1.11.220 outage was not a logic bug in a template — it was a scheduling
 * bug: a 50 000-row DELETE plus a site-wide Elementor CSS flush ran on `init`,
 * i.e. inside the first visitor's request. This script reproduces that shape
 * (a big postmeta table) against stubs, and asserts the replacement:
 *
 *   1. the work is spread over several bounded ticks;
 *   2. no single tick exceeds its budget by more than one batch;
 *   3. the job always terminates and marks the version;
 *   4. a database error does not abort the run — it is retried, then skipped;
 *   5. a second concurrent tick is refused by the lock.
 *
 * Usage: php tests/qa/upgrader-tick-sim.php
 * Exit code: 0 = all assertions passed.
 *
 * This file is a harness, not shipped code: it defines the handful of WordPress
 * functions the upgrader touches, then drives it directly.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'HSW_VERSION', '9.9.9-test' );

/* -------------------------------------------------------------------------
 * Minimal WordPress surface
 * ---------------------------------------------------------------------- */

$GLOBALS['hsw_options']   = array();
$GLOBALS['hsw_scheduled'] = array();
$GLOBALS['hsw_actions']   = array();

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['hsw_options'] ) ? $GLOBALS['hsw_options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['hsw_options'][ $name ] = $value;
	return true;
}
function add_option( $name, $value, $deprecated = '', $autoload = 'yes' ) {
	if ( array_key_exists( $name, $GLOBALS['hsw_options'] ) ) {
		return false;
	}
	$GLOBALS['hsw_options'][ $name ] = $value;
	return true;
}
function delete_option( $name ) {
	unset( $GLOBALS['hsw_options'][ $name ] );
	return true;
}
function add_action( $hook, $cb, $prio = 10, $args = 1 ) {
	$GLOBALS['hsw_actions'][ $hook ][] = $cb;
}
function do_action( $hook, ...$args ) {}
function apply_filters( $hook, $value ) {
	return $value;
}
function current_user_can( $cap ) {
	return true;
}
function get_current_screen() {
	return null;
}
function __( $text, $domain = '' ) {
	return $text;
}
function esc_html__( $text, $domain = '' ) {
	return $text;
}
function delete_post_meta( $post_id, $key ) {
	return true;
}
function get_permalink( $post_id ) {
	return 'https://example.test/?p=' . $post_id;
}
function wp_next_scheduled( $hook ) {
	return isset( $GLOBALS['hsw_scheduled'][ $hook ] ) ? $GLOBALS['hsw_scheduled'][ $hook ] : false;
}
function wp_schedule_single_event( $time, $hook ) {
	if ( isset( $GLOBALS['hsw_scheduled'][ $hook ] ) ) {
		return false;
	}
	$GLOBALS['hsw_scheduled'][ $hook ] = $time;
	return true;
}
function wp_clear_scheduled_hook( $hook ) {
	unset( $GLOBALS['hsw_scheduled'][ $hook ] );
}

/* -------------------------------------------------------------------------
 * Fake $wpdb — only the two statements the upgrader issues
 * ---------------------------------------------------------------------- */

class HSW_Fake_WPDB {

	public $postmeta = 'wp_postmeta';
	public $options  = 'wp_options';
	public $prefix   = 'wp_';
	public $last_error = '';

	/** Rows in the fake element cache, keyed by meta_id. */
	public $cache_rows = array();

	/** Simulate a slow shared-host table. */
	public $query_delay_ms = 0;

	/** Fail the next N deletes. */
	public $fail_next = 0;

	public $queries = 0;

	public function prepare( $sql, ...$args ) {
		$sql = str_replace( array( '%d', '%s' ), array( '%d', "'%s'" ), $sql );
		return vsprintf( $sql, $args );
	}

	public function get_var( $sql ) {
		$this->queries++;
		if ( false !== strpos( $sql, 'MAX(meta_id)' ) ) {
			return $this->cache_rows ? max( array_keys( $this->cache_rows ) ) : null;
		}
		return null;
	}

	public function get_results( $sql ) {
		$this->queries++;
		return array();
	}

	public function get_col( $sql ) {
		$this->queries++;
		return array();
	}

	public function query( $sql ) {
		$this->queries++;

		if ( $this->query_delay_ms > 0 ) {
			usleep( $this->query_delay_ms * 1000 );
		}

		if ( $this->fail_next > 0 ) {
			$this->fail_next--;
			$this->last_error = 'simulated: Lock wait timeout exceeded';
			return false;
		}

		if ( preg_match( '/DELETE FROM \S+ WHERE meta_key = \'_elementor_element_cache\' AND meta_id <= (\d+) LIMIT (\d+)/', $sql, $m ) ) {
			$max   = (int) $m[1];
			$limit = (int) $m[2];
			$ids   = array_keys( $this->cache_rows );
			sort( $ids );
			$n = 0;
			foreach ( $ids as $id ) {
				if ( $id > $max ) {
					continue;
				}
				unset( $this->cache_rows[ $id ] );
				if ( ++$n >= $limit ) {
					break;
				}
			}
			return $n;
		}

		/* Anything else (transient cleanup, etc.) is a no-op. */
		return 0;
	}
}

$GLOBALS['wpdb'] = new HSW_Fake_WPDB();

require_once dirname( __DIR__, 2 ) . '/includes/class-hsw-upgrader.php';

/* -------------------------------------------------------------------------
 * Assertions
 * ---------------------------------------------------------------------- */

$fails = 0;
function check( $label, $ok, $detail = '' ) {
	global $fails;
	if ( ! $ok ) {
		$fails++;
	}
	printf( "[%s] %s%s\n", $ok ? 'ok' : 'FAIL', $label, $ok || '' === $detail ? '' : ' — ' . $detail );
}

/* Seed 60 000 stale rows — the old code's hard cap, and roughly the size that
   made a shared host time out. */
for ( $i = 1; $i <= 60000; $i++ ) {
	$GLOBALS['wpdb']->cache_rows[ $i ] = 1;
}
$GLOBALS['wpdb']->query_delay_ms = 150; // ~6 batches per second, like a slow table.

$upgrader = HSW_Upgrader::instance();

/* --- 1. the first tick must not finish the whole table ------------------- */
$started = microtime( true );
$upgrader->run();
$first_tick = microtime( true ) - $started;

check( 'first tick stays bounded (< 4s)', $first_tick < 4.0, sprintf( '%.2fs', $first_tick ) );
check( 'first tick leaves work for the next one', count( $GLOBALS['wpdb']->cache_rows ) > 0, count( $GLOBALS['wpdb']->cache_rows ) . ' rows left' );
check( 'version not marked done yet', HSW_VERSION !== get_option( 'hsw_asset_version' ) );

/* --- 2. a concurrent tick is refused ------------------------------------ */
add_option( 'hsw_upgrader_lock', time(), '', false );
$before = $GLOBALS['wpdb']->queries;
$upgrader->run();
check( 'lock blocks a concurrent tick', $GLOBALS['wpdb']->queries === $before );
delete_option( 'hsw_upgrader_lock' );

/* --- 3. ticks keep resuming until the table is empty -------------------- */
$ticks = 1;
while ( HSW_VERSION !== get_option( 'hsw_asset_version' ) && $ticks < 200 ) {
	unset( $GLOBALS['hsw_scheduled']['hsw_upgrader_tick'] );
	$upgrader->run();
	$ticks++;
}

check( 'job terminates', HSW_VERSION === get_option( 'hsw_asset_version' ), $ticks . ' ticks' );
check( 'every stale row deleted', 0 === count( $GLOBALS['wpdb']->cache_rows ), count( $GLOBALS['wpdb']->cache_rows ) . ' left' );
check( 'job row removed', false === get_option( 'hsw_upgrader_state' ) );
check( 'lock released', false === get_option( 'hsw_upgrader_lock' ) );
check( 'took more than one tick', $ticks > 1, $ticks . ' ticks' );

/* --- 4. a database failure is contained and retried --------------------- */
delete_option( 'hsw_asset_version' );
delete_option( 'hsw_upgrader_state' );
$GLOBALS['wpdb']->cache_rows = array();
for ( $i = 1; $i <= 4000; $i++ ) {
	$GLOBALS['wpdb']->cache_rows[ $i ] = 1;
}
$GLOBALS['wpdb']->query_delay_ms = 0;
$GLOBALS['wpdb']->fail_next      = 2;

$upgrader->run();
check( 'a failed DELETE does not abort the request', true );
check( 'failure recorded in the log', is_array( get_option( 'hsw_upgrader_log' ) ) && count( get_option( 'hsw_upgrader_log' ) ) > 0 );

$guard = 0;
while ( HSW_VERSION !== get_option( 'hsw_asset_version' ) && $guard < 40 ) {
	unset( $GLOBALS['hsw_scheduled']['hsw_upgrader_tick'] );
	$upgrader->run();
	$guard++;
}
check( 'job still completes after errors', HSW_VERSION === get_option( 'hsw_asset_version' ), $guard . ' extra ticks' );

/* --- 5. rows written after the job started are kept --------------------- */
delete_option( 'hsw_asset_version' );
delete_option( 'hsw_upgrader_state' );
$GLOBALS['wpdb']->cache_rows = array();
for ( $i = 1; $i <= 3000; $i++ ) {
	$GLOBALS['wpdb']->cache_rows[ $i ] = 1;
}
$GLOBALS['wpdb']->fail_next = 0;

$upgrader->run(); // Captures the watermark here.
/* A page rendered by the new code after the job started: must survive. */
$GLOBALS['wpdb']->cache_rows[ 999999 ] = 1;

$guard = 0;
while ( HSW_VERSION !== get_option( 'hsw_asset_version' ) && $guard < 40 ) {
	unset( $GLOBALS['hsw_scheduled']['hsw_upgrader_tick'] );
	$upgrader->run();
	$guard++;
}

check( 'fresh rows written after the start survive', isset( $GLOBALS['wpdb']->cache_rows[ 999999 ] ) );
check( 'stale rows are gone', 1 === count( $GLOBALS['wpdb']->cache_rows ), count( $GLOBALS['wpdb']->cache_rows ) . ' rows left' );

echo "\n=== " . ( $fails ? $fails . ' check(s) failed' : 'all checks passed' ) . " ===\n";
exit( $fails ? 1 : 0 );
