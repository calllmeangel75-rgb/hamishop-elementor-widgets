<?php
/**
 * First-paint performance guards — executable cases.
 *
 * QA-only; the release ZIP excludes tests/ (.gitattributes export-ignore).
 *
 *   php tests/qa/prelive-perf.php
 *   HSW_PRELIVE_ASSETS=<path> php tests/qa/prelive-perf.php   # older copy → red
 *
 * Everything here comes from one Lighthouse run on the live shop (mobile,
 * Slow 4G): CLS 0.178 with two layout-shift events, 0.134 of it caused by the
 * mobile filter/sort sheets leaving the normal flow when `hsw-archive.js`
 * added `hsw-js` to <html> — 219px of the page collapsing after the first
 * paint. These cases pin the two halves of the fix: the flag now goes out in
 * the head, and the no-JS fallback it depends on stays in the stylesheet.
 */

require_once __DIR__ . '/prelive-lib.php';

if ( ! function_exists( '__return_false' ) ) {
	function __return_false() { return false; }
}
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() { return ! empty( $GLOBALS['hsw_is_admin'] ); }
}

$GLOBALS['hsw_is_admin'] = false;

require_once getenv( 'HSW_PRELIVE_ASSETS' ) ?: dirname( __DIR__, 2 ) . '/includes/class-hsw-assets.php';

$GLOBALS['hsw_results'] = array();

function p_reset() {
	HSW_Prelive_Store::reset();
	$GLOBALS['hsw_filters']  = array();
	$GLOBALS['hsw_is_admin'] = false;
}

function p_case( $id, $title, callable $fn ) {
	try {
		$r = $fn();
	} catch ( Throwable $e ) {
		$r = array( false, 'EXCEPTION: ' . $e->getMessage() );
	}
	$GLOBALS['hsw_results'][] = $r;
	printf( "[%s] %-5s %s — %s\n", $r[0] ? 'PASS' : 'FAIL', $id, $title, $r[1] );
}

function p_capture( callable $fn ) {
	ob_start();
	$fn();
	return (string) ob_get_clean();
}

$assets = new HSW_Assets();

/* ------------------------------------------------------------------ */

p_case(
	'P1',
	'the JS flag is printed as a script that adds hsw-js',
	static function () use ( $assets ) {
		p_reset();
		$out = p_capture( array( $assets, 'print_js_flag' ) );
		$ok  = false !== strpos( $out, '<script' )
			&& false !== strpos( $out, 'classList.add("hsw-js")' );
		return array( $ok, 'out=' . trim( $out ) );
	}
);

p_case(
	'P2',
	'it is a script, not a style: a <style> could never set the class',
	static function () use ( $assets ) {
		p_reset();
		$out = p_capture( array( $assets, 'print_js_flag' ) );
		return array( false === strpos( $out, '<style' ), 'starts=' . substr( ltrim( $out ), 0, 20 ) );
	}
);

p_case(
	'P3',
	'the hsw_js_flag filter turns it off',
	static function () use ( $assets ) {
		p_reset();
		$GLOBALS['hsw_filters']['hsw_js_flag'] = array( '__return_false' );
		$out = p_capture( array( $assets, 'print_js_flag' ) );
		return array( '' === trim( $out ), 'len=' . strlen( trim( $out ) ) );
	}
);

p_case(
	'P4',
	'nothing is printed in the admin',
	static function () use ( $assets ) {
		p_reset();
		$GLOBALS['hsw_is_admin'] = true;
		$out = p_capture( array( $assets, 'print_js_flag' ) );
		$GLOBALS['hsw_is_admin'] = false;
		return array( '' === trim( $out ), 'len=' . strlen( trim( $out ) ) );
	}
);

p_case(
	'P5',
	'the layout guard still ships (the flag must not replace it)',
	static function () use ( $assets ) {
		p_reset();
		$out = p_capture( array( $assets, 'print_layout_guard' ) );
		$ok  = false !== strpos( $out, 'hsw-layout-guard' )
			&& false !== strpos( $out, 'hsw-cp--archive' );
		return array( $ok, 'len=' . strlen( $out ) );
	}
);

/*
 * The no-JS fallback is what makes the early flag safe: with JavaScript off
 * the class never arrives, these rules keep applying, and the mobile filters
 * stay in the flow. Deleting them would leave the sheets fixed and unusable.
 */
p_case(
	'P6',
	'the no-JS fallback for the sheets is still in the stylesheet',
	static function () {
		$css = file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/hsw-archive.css' );
		$ok  = false !== strpos( $css, 'html:not(.hsw-js) .hsw-a .hsw-asheet--filter' )
			&& false !== strpos( $css, 'html:not(.hsw-js) .hsw-a .hsw-asheet--sort' );
		return array( $ok, 'archive.css=' . ( $ok ? 'has fallback' : 'FALLBACK MISSING' ) );
	}
);

p_case(
	'P7',
	'the sheets are position:fixed by default (the fallback overrides it)',
	static function () {
		$css = file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/hsw-archive.css' );
		$pos = strpos( $css, '.hsw-asheet {' );
		$ok  = false !== $pos && false !== strpos( substr( $css, $pos, 120 ), 'position: fixed' );
		return array( $ok, 'first .hsw-asheet block=' . ( false !== $pos ? 'found' : 'missing' ) );
	}
);

/* The logo is the top of the page: it must not be lazyloaded or over-sized. */
p_case(
	'P8',
	'the logo is eager and marked skip-lazy (host lazyload cannot swap it)',
	static function () {
		$php = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-hsw-header-base.php' );
		$ok  = false !== strpos( $php, "'hsw-hd__logoimg skip-lazy'" )
			&& false !== strpos( $php, "'loading'       => 'eager'" )
			&& false !== strpos( $php, "'data-no-lazy'  => '1'" );
		return array( $ok, 'header-base=' . ( $ok ? 'eager+skip-lazy' : 'STILL LAZY' ) );
	}
);

p_case(
	'P9',
	'the logo sizes attribute is computed, not the old 38vw constant',
	static function () {
		$php = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-hsw-header-base.php' );
		/* The literal may survive as the unknown-ratio fallback; what must be
		   gone is the hardcoded attribute passed to wp_get_attachment_image(). */
		$hard = false !== strpos( $php, "'sizes'         => '(max-width: 767px) 38vw, 264px'" );
		$ok   = ! $hard && false !== strpos( $php, "=> \$logo_sizes" );
		return array( $ok, 'hardcodedAttr=' . var_export( $hard, true ) . ' computed=' . var_export( false !== strpos( $php, '$logo_sizes' ), true ) );
	}
);

$failed = 0;
foreach ( $GLOBALS['hsw_results'] as $r ) {
	$failed += $r[0] ? 0 : 1;
}
printf( "\nperf: %d cases, %d failed\n", count( $GLOBALS['hsw_results'] ), $failed );
exit( $failed ? 1 : 0 );
