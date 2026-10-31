<?php
/**
 * Optimizer compatibility (LiteSpeed) — executable cases.
 *
 * QA-only; the release ZIP excludes tests/ (.gitattributes export-ignore).
 *
 *   php tests/qa/prelive-optimizer.php
 *   HSW_OPT_ABSENT=1 php tests/qa/prelive-optimizer.php   # pretend the class is
 *                                                          # not wired in: every
 *                                                          # case must go red
 */

require_once __DIR__ . '/prelive-lib.php';

/* Stubs this file needs on top of the shared lib. */
function has_filter( $hook ) {
	return ! empty( $GLOBALS['hsw_filters'][ $hook ] ) ? 1 : false;
}
function has_action( $hook ) {
	return ! empty( $GLOBALS['hsw_hooks'][ $hook ] ) ? 1 : false;
}
function remove_query_arg( $key, $url = '' ) { return is_array( $key ) ? $url : $url; }
function wp_nonce_url( $url, $action, $name ) { return $url . '&' . $name . '=valid'; }
function get_current_screen() {
	return isset( $GLOBALS['hsw_screen'] ) ? (object) array( 'id' => $GLOBALS['hsw_screen'] ) : null;
}
function is_admin() {
	return ! empty( $GLOBALS['hsw_is_admin'] );
}

if ( ! getenv( 'HSW_OPT_ABSENT' ) ) {
	$file = getenv( 'HSW_PRELIVE_OPT' );
	require_once $file ? $file : dirname( __DIR__, 2 ) . '/includes/class-hsw-optimizer.php';
	HSW_Optimizer::instance();
}

/* ------------------------------------------------------------------ */

function o_reset( $litespeed = false, $excludes = '' ) {
	HSW_Prelive_Store::reset();
	$GLOBALS['hsw_filters'] = array();
	$GLOBALS['hsw_hooks']   = array();
	$GLOBALS['hsw_did']     = array();
	$GLOBALS['hsw_is_admin'] = false;
	$GLOBALS['hsw_caps']    = array();
	$GLOBALS['hsw_screen']  = null;
	$_GET                 = array();

	if ( $litespeed ) {
		add_filter(
			'litespeed_conf',
			static function ( $slug ) use ( $excludes ) {
				/* Emulate LiteSpeed's own rule: a forced value is what later reads see. */
				if ( isset( $GLOBALS['hsw_forced_map'][ $slug ] ) ) {
					return $GLOBALS['hsw_forced_map'][ $slug ];
				}
				if ( 'optm-css_exc' === $slug || 'optm-ucss_exc' === $slug ) {
					return $excludes;
				}
				if ( 'optm-css_comb' === $slug ) {
					return '1';
				}
				return '';
			}
		);
	}
}

function o_forced() {
	return $GLOBALS['hsw_forced'] ?? array();
}

function o_capture_force() {
	$GLOBALS['hsw_forced']     = array();
	$GLOBALS['hsw_forced_map'] = array();
	add_action(
		'litespeed_conf_force',
		static function ( $key, $value ) {
			$GLOBALS['hsw_forced'][] = array( $key, $value );
			$GLOBALS['hsw_forced_map'][ $key ] = $value;
		}
	);
}

$GLOBALS['hsw_results'] = array();
function o_case( $id, $title, callable $fn ) {
	try {
		$r = $fn();
	} catch ( Throwable $e ) {
		$r = array( false, 'EXCEPTION: ' . $e->getMessage() );
	}
	$GLOBALS['hsw_results'][] = $r;
	printf( "[%s] %-5s %s — %s\n", $r[0] ? 'PASS' : 'FAIL', $id, $title, $r[1] );
}

/* ------------------------------------------------------------------ */

o_case(
	'O1',
	'without LiteSpeed nothing is forced and nothing is purged',
	static function () {
		o_reset( false );
		o_capture_force();
		HSW_Optimizer::instance()->exclude_own_assets();
		HSW_Optimizer::purge();

		$forced = count( o_forced() );
		$purged = did_action( 'litespeed_purge_all' );
		return array( 0 === $forced && 0 === $purged, "forced={$forced} purgeCalls={$purged}" );
	}
);

o_case(
	'O2',
	'with LiteSpeed our CSS is added to CSS Excludes, owner lines kept',
	static function () {
		o_reset( true, 'divi/style.css' );
		o_capture_force();
		HSW_Optimizer::instance()->exclude_own_assets();

		$forced = o_forced();
		$value  = '';
		foreach ( $forced as $f ) {
			if ( HSW_Optimizer::LS_CSS_EXCLUDES === $f[0] ) {
				$value = $f[1];
			}
		}
		$ok = false !== strpos( $value, 'hamishop-elementor-widgets/assets/css' ) && false !== strpos( $value, 'divi/style.css' );
		return array( $ok, 'forced=' . count( $forced ) . ' value=' . str_replace( "\n", ' | ', $value ) );
	}
);

o_case(
	'O3',
	'running twice does not duplicate the exclude line',
	static function () {
		o_reset( true, 'divi/style.css' );
		o_capture_force();
		$opt = HSW_Optimizer::instance();
		$opt->exclude_own_assets();
		$opt->exclude_own_assets();

		$forced = o_forced();
		$seen   = array();
		foreach ( $forced as $f ) {
			$seen[ $f[0] ] = $f[1];
		}
		$value = isset( $seen[ HSW_Optimizer::LS_CSS_EXCLUDES ] ) ? $seen[ HSW_Optimizer::LS_CSS_EXCLUDES ] : (string) apply_filters( 'litespeed_conf', HSW_Optimizer::LS_CSS_EXCLUDES );
		$times = substr_count( $value, 'hamishop-elementor-widgets/assets/css' );
		$css_forced = 0;
		foreach ( $forced as $f ) {
			if ( HSW_Optimizer::LS_CSS_EXCLUDES === $f[0] ) {
				$css_forced++;
			}
		}
		return array( 1 === $times && $css_forced <= 1, "occurrences={$times} cssForcedAgain={$css_forced} value=" . str_replace( "\n", ' | ', $value ) );
	}
);

o_case(
	'O4',
	'a style change purges LiteSpeed when it is present',
	static function () {
		o_reset( true, '' );
		HSW_Optimizer::purge( 'test' );
		$with = did_action( 'litespeed_purge_all' );

		o_reset( false );
		HSW_Optimizer::purge( 'test' );
		$without = did_action( 'litespeed_purge_all' );

		return array( 1 === $with && 0 === $without, "withLS={$with} withoutLS={$without}" );
	}
);

o_case(
	'O5',
	'an Elementor preview request turns the optimizer off for that render',
	static function () {
		o_reset( true, '' );
		$GLOBALS['hsw_is_admin'] = true;
		$_GET['elementor-preview'] = '123';

		HSW_Optimizer::instance()->disable_in_editor();
		$preview = did_action( 'litespeed_disable_all' );

		o_reset( true, '' );
		$GLOBALS['hsw_is_admin'] = true;
		HSW_Optimizer::instance()->disable_in_editor();
		$normal = did_action( 'litespeed_disable_all' );

		return array( 1 === $preview && 0 === $normal, "preview={$preview} normalPage={$normal}" );
	}
);

o_case(
	'O6',
	'merge keeps CRLF input and never repeats an entry',
	static function () {
		$merged = HSW_Optimizer::merge_lines( "a.css\r\nb.css", array( 'b.css', 'c.css' ) );
		$lines  = explode( "\n", $merged );
		$ok     = 3 === count( $lines ) && 'a.css' === $lines[0] && 'b.css' === $lines[1] && 'c.css' === $lines[2];
		return array( $ok, str_replace( "\n", ' | ', $merged ) );
	}
);

$failed = 0;
foreach ( $GLOBALS['hsw_results'] as $r ) {
	$failed += $r[0] ? 0 : 1;
}
printf( "\noptimizer: %d cases, %d failed\n", count( $GLOBALS['hsw_results'] ), $failed );
exit( $failed ? 1 : 0 );
