<?php
/**
 * Optimizer compatibility (LiteSpeed / WP Rocket / W3TC) — executable cases.
 *
 * QA-only; the release ZIP excludes tests/ (.gitattributes export-ignore).
 *
 *   php tests/qa/prelive-optimizer.php
 *   HSW_OPT_ABSENT=1 php tests/qa/prelive-optimizer.php  # class not wired in: all red
 *
 * The shapes under test come from the installed LiteSpeed source: the exclude
 * lists are ARRAYS (Data::load_css_exc( array ): array) and LiteSpeed appends
 * `$excludes[] = 'litespeed-dummy.css'` after the filter — so a string returned
 * here would break its CSS optimization outright.
 */

require_once __DIR__ . '/prelive-lib.php';

/* Stubs this file needs on top of the shared lib. */
function has_filter( $hook ) {
	return ! empty( $GLOBALS['hsw_filters'][ $hook ] ) ? 1 : false;
}
function has_action( $hook ) {
	return ! empty( $GLOBALS['hsw_hooks'][ $hook ] ) ? 1 : false;
}
function remove_query_arg( $key, $url = '' ) { return (string) $url; }
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

	/* Snapshot the registrations: o_reset() clears the hook registry below. */
	$GLOBALS['hsw_opt_filters'] = array(
		'litespeed_optimize_css_excludes' => (bool) has_filter( 'litespeed_optimize_css_excludes' ),
		'litespeed_ucss_exc'              => (bool) has_filter( 'litespeed_ucss_exc' ),
		'rocket_exclude_css'              => (bool) has_filter( 'rocket_exclude_css' ),
	);
} else {
	$GLOBALS['hsw_opt_filters'] = array();
}

/* ------------------------------------------------------------------ */

function o_reset() {
	HSW_Prelive_Store::reset();
	$GLOBALS['hsw_filters']  = array();
	$GLOBALS['hsw_hooks']    = array();
	$GLOBALS['hsw_did']      = array();
	$GLOBALS['hsw_is_admin'] = false;
	$GLOBALS['hsw_caps']     = array();
	$GLOBALS['hsw_screen']   = null;
	$_GET                  = array();
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
	'the two LiteSpeed filters are registered',
	static function () {
		$css  = ! empty( $GLOBALS['hsw_opt_filters']['litespeed_optimize_css_excludes'] );
		$ucss = ! empty( $GLOBALS['hsw_opt_filters']['litespeed_ucss_exc'] );
		return array( $css && $ucss, 'css_excludes=' . var_export( $css, true ) . ' ucss_exc=' . var_export( $ucss, true ) );
	}
);

o_case(
	'O2',
	'an array list (LiteSpeed shape) gets our line and keeps the owner line',
	static function () {
		o_reset();
		$out = HSW_Optimizer::instance()->add_excludes( array( 'divi/own-style.css' ) );
		$ok  = is_array( $out )
			&& in_array( 'hamishop-elementor-widgets/assets/css', $out, true )
			&& in_array( 'divi/own-style.css', $out, true );
		return array( $ok, gettype( $out ) . ' = ' . implode( ' | ', (array) $out ) );
	}
);

o_case(
	'O3',
	'the return value is an array, so LiteSpeed can still append to it',
	static function () {
		o_reset();
		$out = HSW_Optimizer::instance()->add_excludes( array() );
		$appended = false;
		if ( is_array( $out ) ) {
			$out[]    = 'litespeed-dummy.css';
			$appended = true;
		}
		return array( is_array( $out ) && $appended, gettype( $out ) . ' appended=' . var_export( $appended, true ) );
	}
);

o_case(
	'O4',
	'running the filter twice never duplicates the line',
	static function () {
		o_reset();
		$opt  = HSW_Optimizer::instance();
		$list = $opt->add_excludes( array( 'a.css' ) );
		$list = $opt->add_excludes( $list );
		$times = 0;
		foreach ( $list as $line ) {
			if ( 'hamishop-elementor-widgets/assets/css' === $line ) {
				$times++;
			}
		}
		return array( 1 === $times && in_array( 'a.css', $list, true ), 'occurrences=' . $times . ' list=' . implode( '|', $list ) );
	}
);

o_case(
	'O5',
	'a string list (older/foreign shape) is split, not cast',
	static function () {
		o_reset();
		$out = HSW_Optimizer::merge_list( "a.css\r\nb.css", array( 'b.css', 'c.css' ) );
		$ok  = array( 'a.css', 'b.css', 'c.css' ) === $out;
		return array( $ok, implode( '|', (array) $out ) );
	}
);

o_case(
	'O6',
	'purge fires litespeed_purge_all only when LiteSpeed is present',
	static function () {
		$GLOBALS['hsw_filters']['litespeed_conf'] = array( static function ( $slug ) { return ''; } );
		HSW_Optimizer::purge( 'test' );
		$with = did_action( 'litespeed_purge_all' );

		o_reset();
		HSW_Optimizer::purge( 'test' );
		$without = did_action( 'litespeed_purge_all' );

		return array( 1 === $with && 0 === $without, "withLS={$with} withoutLS={$without}" );
	}
);

o_case(
	'O7',
	'an Elementor preview request switches the optimizer off for that render',
	static function () {
		o_reset();
		$GLOBALS['hsw_is_admin']    = true;
		$_GET['elementor-preview'] = '123';
		HSW_Optimizer::instance()->disable_in_editor();
		$preview = did_action( 'litespeed_disable_all' );

		o_reset();
		$GLOBALS['hsw_is_admin'] = true;
		HSW_Optimizer::instance()->disable_in_editor();
		$normal = did_action( 'litespeed_disable_all' );

		return array( 1 === $preview && 0 === $normal, "preview={$preview} normalPage={$normal}" );
	}
);

o_case(
	'O8',
	'detection does not rely on LSCWP_CTRL (that is a router query key)',
	static function () {
		o_reset();
		$bare = HSW_Optimizer::litespeed_active();
		$GLOBALS['hsw_filters']['litespeed_conf'] = array( '__return_false' );
		$with = HSW_Optimizer::litespeed_active();
		return array( false === $bare && true === (bool) $with, 'withoutFilter=' . var_export( $bare, true ) . ' withFilter=' . var_export( (bool) $with, true ) );
	}
);

o_case(
	'O9',
	'WP Rocket gets the same exclusion through rocket_exclude_css',
	static function () {
		o_reset();
		$registered = ! empty( $GLOBALS['hsw_opt_filters']['rocket_exclude_css'] );
		$out        = HSW_Optimizer::instance()->add_excludes( array( 'themes/child/style.css' ) );
		$has_ours   = in_array( 'hamishop-elementor-widgets/assets/css', $out, true );
		$keeps_own  = in_array( 'themes/child/style.css', $out, true );
		return array(
			$registered && $has_ours && $keeps_own,
			'registered=' . var_export( $registered, true ) . ' ours=' . var_export( $has_ours, true ) . ' owner=' . var_export( $keeps_own, true ),
		);
	}
);

$failed = 0;
foreach ( $GLOBALS['hsw_results'] as $r ) {
	$failed += $r[0] ? 0 : 1;
}
printf( "\noptimizer: %d cases, %d failed\n", count( $GLOBALS['hsw_results'] ), $failed );
exit( $failed ? 1 : 0 );
