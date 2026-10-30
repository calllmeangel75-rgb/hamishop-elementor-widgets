<?php
/**
 * Structural CI checks — no Composer, no WordPress, pure PHP 7.4+.
 *
 * These are the mistakes that a linter cannot see and a human reviewer forgets:
 * a version bump in one file but not the other, a translation string pointing at
 * a typo'd text domain, a `var_dump()` left behind, a `.min` asset that was never
 * rebuilt, a widget file nobody registered.
 *
 * Usage: php tools/ci-checks.php
 * Exit code: 0 = clean, 1 = at least one failure.
 */

$root    = dirname( __DIR__ );
$fails   = 0;
$checks  = 0;

/**
 * Report one check.
 *
 * @param string $label  What was checked.
 * @param bool   $ok     Result.
 * @param string $detail Failure detail.
 */
function hsw_ci( $label, $ok, $detail = '' ) {
	global $fails, $checks;
	$checks++;
	if ( ! $ok ) {
		$fails++;
	}
	echo ( $ok ? '[ok]   ' : '[FAIL] ' ) . $label . ( '' !== $detail ? ' — ' . $detail : '' ) . "\n";
}

/**
 * All PHP files of the plugin (tools/ and tests/ included: a broken test file
 * still breaks the repository).
 *
 * @param string $root Plugin root.
 * @return string[]
 */
function hsw_php_files( $root ) {
	$out = array();
	$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		$path = $file->getPathname();
		if ( '.php' !== substr( $path, -4 ) ) {
			continue;
		}
		if ( false !== strpos( $path, '/vendor/' ) || false !== strpos( $path, '\\vendor\\' ) ) {
			continue;
		}
		$out[] = $path;
	}
	sort( $out );
	return $out;
}

/**
 * Path relative to the plugin root, with forward slashes on every OS.
 *
 * @param string $root Root.
 * @param string $path Absolute path.
 * @return string
 */
function hsw_rel( $root, $path ) {
	$rel = str_replace( '\\', '/', $path );
	return str_replace( str_replace( '\\', '/', $root ) . '/', '', $rel );
}

$files = hsw_php_files( $root );

/* -------------------------------------------------------------------------
 * 1) Version consistency
 * ---------------------------------------------------------------------- */
$main = (string) file_get_contents( $root . '/hamishop-elementor-widgets.php' );
preg_match( '/^\s*\*\s*Version:\s*([0-9.]+)/mi', $main, $header );
preg_match( "/define\(\s*'HSW_VERSION'\s*,\s*'([0-9.]+)'\s*\)/", $main, $define );
$readme = (string) file_get_contents( $root . '/readme.txt' );
preg_match( '/^Stable tag:\s*([0-9.]+)/mi', $readme, $stable );

$v_header = $header[1] ?? '';
$v_define = $define[1] ?? '';
$v_stable = $stable[1] ?? '';

hsw_ci( 'version: header == HSW_VERSION', '' !== $v_header && $v_header === $v_define, "{$v_header} vs {$v_define}" );
hsw_ci( 'version: readme Stable tag matches', '' !== $v_header && $v_header === $v_stable, "{$v_header} vs {$v_stable}" );
hsw_ci( 'version: release note exists', '' !== $v_header && is_file( $root . "/RELEASE-NOTES-{$v_header}.md" ), "RELEASE-NOTES-{$v_header}.md" );
hsw_ci( 'version: changelog mentions it', false !== strpos( $readme, "= {$v_header} =" ), $v_header );

/* -------------------------------------------------------------------------
 * 2) Text domain
 *
 * `__( 'text', 'hamishop-elementor--widgets' )` renders fine but silently
 * never translates: the string is not in the POT because the domain differs.
 * ---------------------------------------------------------------------- */
$domain  = 'hamishop-elementor-widgets';
$bad_i18n = array();
$self     = str_replace( '\\', '/', __FILE__ );
foreach ( $files as $path ) {
	/* Skip this file: its docblock quotes the anti-pattern on purpose. */
	if ( str_replace( '\\', '/', $path ) === $self ) {
		continue;
	}
	$src  = (string) file_get_contents( $path );
	$rel  = hsw_rel( $root, $path );
	/* two-argument family: __() _e() esc_html__() esc_attr__() … */
	if ( preg_match_all( '/(?:__|_e|esc_html__|esc_attr__|esc_html_e|esc_attr_e)\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")\s*,\s*\'([^\']+)\'/s', $src, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $hit ) {
			$found = $hit[3];
			if ( $domain !== $found ) {
				$bad_i18n[] = $rel . " → '{$found}'";
			}
		}
	}
	/* _n( single, plural, number, domain ) and _x( text, context, domain ) */
	if ( preg_match_all( '/(?:_n|_x)\(\s*(?:\'[^\']*\'|"[^"]*")\s*,\s*(?:\'[^\']*\'|"[^"]*")\s*,\s*[^,]+,\s*\'([^\']+)\'/s', $src, $m2, PREG_SET_ORDER ) ) {
		foreach ( $m2 as $hit ) {
			if ( $domain !== $hit[1] ) {
				$bad_i18n[] = $rel . " → '{$hit[1]}'";
			}
		}
	}
}
hsw_ci(
	'text domain: every translation uses ' . $domain,
	! $bad_i18n,
	implode( ' | ', array_slice( array_unique( $bad_i18n ), 0, 5 ) )
);

/* -------------------------------------------------------------------------
 * 3) Debug leftovers
 * ---------------------------------------------------------------------- */
$debug = array();
foreach ( $files as $path ) {
	$rel = hsw_rel( $root, $path );
	if ( 0 === strpos( $rel, 'tests/' ) || 0 === strpos( $rel, 'tools/' ) ) {
		continue; // the harness and build scripts may inspect things.
	}
	$src   = (string) file_get_contents( $path );
	$lines = preg_split( '/\R/', $src );
	foreach ( $lines as $i => $line ) {
		if ( preg_match( '/\b(var_dump|print_r|debug_print_backtrace)\s*\(/', $line )
			|| preg_match( '/console\.(log|debug)\s*\(/', $line )
			|| preg_match( '/\bxdebug_break\s*\(/', $line ) ) {
			$debug[] = $rel . ':' . ( $i + 1 );
		}
	}
}
hsw_ci( 'debug: no var_dump/print_r/console.log left', ! $debug, implode( ' | ', array_slice( $debug, 0, 5 ) ) );

/* -------------------------------------------------------------------------
 * 4) Minified assets exist for every source asset
 * ---------------------------------------------------------------------- */
$missing_min = array();
foreach ( array( 'css', 'js' ) as $type ) {
	foreach ( glob( $root . "/assets/{$type}/*." . $type ) ?: array() as $src ) {
		if ( false !== strpos( $src, '.min.' . $type ) ) {
			continue;
		}
		$min = preg_replace( '/\.' . $type . '$/', '.min.' . $type, $src );
		if ( ! is_file( $min ) ) {
			$missing_min[] = str_replace( $root . '/', '', $min );
		}
	}
}
hsw_ci( 'assets: every css/js has a .min twin', ! $missing_min, implode( ', ', array_slice( $missing_min, 0, 5 ) ) );

/* -------------------------------------------------------------------------
 * 5) Every widget file is in the catalog and required by the plugin
 * ---------------------------------------------------------------------- */
$catalog = (string) file_get_contents( $root . '/includes/class-hsw-widget-manager.php' );
$plugin  = (string) file_get_contents( $root . '/includes/class-hsw-plugin.php' );

$not_in_catalog = array();
$not_required   = array();
foreach ( glob( $root . '/widgets/*.php' ) ?: array() as $widget ) {
	$name = basename( $widget );
	$slug = preg_replace( '/^class-/', '', basename( $widget, '.php' ) );
	$slug = str_replace( '-widget-', '-', $slug ); // class-hsw-widget-cart-top → hsw-cart-top
	if ( false === strpos( $catalog, "'{$slug}'" ) ) {
		$not_in_catalog[] = $slug;
	}
	if ( false === strpos( $plugin, $name ) ) {
		$not_required[] = $name;
	}
}
hsw_ci( 'widgets: every file has a catalog entry', ! $not_in_catalog, implode( ', ', $not_in_catalog ) );
hsw_ci( 'widgets: every file is required by the plugin', ! $not_required, implode( ', ', array_slice( $not_required, 0, 5 ) ) );

/* -------------------------------------------------------------------------
 * 6) Repository hygiene: no local build output or scratch files
 * ---------------------------------------------------------------------- */
$stray = array();
foreach ( array( 'node_modules', 'package.json', 'package-lock.json' ) as $needle ) {
	if ( file_exists( $root . '/' . $needle ) ) {
		$stray[] = $needle;
	}
}
foreach ( glob( $root . '/tests/_tmp-*' ) ?: array() as $tmp ) {
	$stray[] = 'tests/' . basename( $tmp );
}
hsw_ci( 'repo: no node_modules / package.json / tests/_tmp-*', ! $stray, implode( ', ', $stray ) );

/* -------------------------------------------------------------------------
 * 7) Documentation set
 * ---------------------------------------------------------------------- */
foreach ( array( 'README.md', 'TECHNICAL-BRIEF.md', 'MARKETING.md', 'WIDGET-GUIDE.md', 'WIDGETS-REFERENCE.md', 'STAGING-CHECKLIST.md' ) as $doc ) {
	$size = is_file( $root . '/' . $doc ) ? filesize( $root . '/' . $doc ) : 0;
	hsw_ci( "docs: {$doc} present", $size > 500, $size . ' bytes' );
}

/* -------------------------------------------------------------------------
 * Summary
 * ---------------------------------------------------------------------- */
echo "\n=== {$checks} checks, " . ( $fails ? "{$fails} failed" : 'all passed' ) . " ===\n";
exit( $fails ? 1 : 0 );
