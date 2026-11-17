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
/* Release notes live in docs/archive/release-notes/ since the docs cleanup. */
$note_path = $root . "/docs/archive/release-notes/RELEASE-NOTES-{$v_header}.md";
hsw_ci( 'version: release note exists', '' !== $v_header && is_file( $note_path ), "docs/archive/release-notes/RELEASE-NOTES-{$v_header}.md" );
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

/*
 * Being required is not the same as being registered. `register_widgets()`
 * loads the file, then walks a second, hand-written list of class names —
 * and skips anything that is not in it with a silent `continue`. A widget
 * that is required but missing from that list never appears in Elementor and
 * produces no error at all, which is how «هدر GC» shipped invisible.
 */
$register_start = strpos( $plugin, 'foreach ( array(' );
$register_block = '';
if ( false !== $register_start ) {
	$register_end   = strpos( $plugin, ') as $widget_class )', $register_start );
	$register_block = false !== $register_end
		? substr( $plugin, $register_start, $register_end - $register_start )
		: '';
}

$not_registered = array();
foreach ( glob( $root . '/widgets/*.php' ) ?: array() as $widget ) {
	$src = (string) file_get_contents( $widget );
	if ( ! preg_match( '/^\s*class\s+([A-Za-z_][A-Za-z0-9_]*)/m', $src, $class_match ) ) {
		continue;
	}
	if ( false === strpos( $register_block, "'\\" . $class_match[1] . "'" ) ) {
		$not_registered[] = $class_match[1];
	}
}
hsw_ci(
	'widgets: every widget class is in the register_widgets() list',
	'' !== $register_block && ! $not_registered,
	'' === $register_block ? 'could not locate the class list' : implode( ', ', $not_registered )
);

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
 * 6b) The checkout CTA must never select a form by a wrapper class
 *
 * `.woocommerce-checkout` is a BODY class on checkout pages, so
 * `.woocommerce-checkout form` matched the header search form first and
 * submitted it: clicking «ادامه و ورود به مرحله پرداخت» sent the customer to
 * `/?s=`. The selector is now WooCommerce's own form markers plus a guard.
 * ---------------------------------------------------------------------- */
$checkout_js = (string) file_get_contents( $root . '/assets/js/hsw-checkout.js' );
hsw_ci(
	'checkout: no wrapper-class form selector',
	false === strpos( $checkout_js, "'form.checkout, form[name=\"checkout\"], .woocommerce-checkout form'" ),
	'the search-form trap is back'
);
hsw_ci(
	'checkout: place-order guarded against search forms',
	false !== strpos( $checkout_js, "input[name=\"s\"]" ) && false !== strpos( $checkout_js, 'has_checkout_field' ),
	'missing the search/field guard'
);

/* -------------------------------------------------------------------------
 * 6c) The ticket CSV must count the same replies the ticket list counts
 *
 * `get_comments()` with no `status` defaults to `all`, which WordPress turns
 * into `comment_approved = '0' OR comment_approved = '1'` — approved AND
 * awaiting moderation. Counting only `'1'` made the CSV disagree with the
 * reply count on the ticket list. Spam and trash are not counted by either.
 * ---------------------------------------------------------------------- */
$support = (string) file_get_contents( $root . '/includes/class-hsw-account-support.php' );
hsw_ci(
	'tickets: CSV counts approved + pending replies',
	false !== strpos( $support, "comment_approved IN ('0', '1')" ),
	'reply_counts() no longer matches get_comments() default status'
);
hsw_ci(
	'tickets: CSV does not count only approved replies',
	false === strpos( $support, "AND comment_approved = '1'" ),
	'approved-only filter is back'
);

/* -------------------------------------------------------------------------
 * 6d) Every GC header colour role must be settable from the panel
 *
 * `--hsw-gc-cta-*`, `--hsw-gc-mark-*` and `--hsw-gc-badge-*` are roles the
 * stylesheet reads through a `-user` channel. When one of those channels has no
 * control, the panel silently loses that colour and the only way to change it
 * is custom CSS — which is exactly the "why is this colour coming from
 * somewhere else" confusion this check exists to prevent.
 * ---------------------------------------------------------------------- */
$gc_css = (string) file_get_contents( $root . '/assets/css/hsw-header-gc.css' );
$gc_php = (string) file_get_contents( $root . '/widgets/class-hsw-widget-header-gc.php' );
preg_match_all( '/var\(\s*(--hsw-gc-[a-z0-9-]+-user)/', $gc_css, $gc_channels );
$gc_channels = array_unique( $gc_channels[1] );
$gc_missing  = array();
foreach ( $gc_channels as $channel ) {
	if ( false === strpos( $gc_php, "'{$channel}'" ) ) {
		$gc_missing[] = $channel;
	}
}
hsw_ci(
	'GC header: every colour role has a control',
	! $gc_missing,
	$gc_missing ? implode( ', ', $gc_missing ) : count( $gc_channels ) . ' channels'
);

/* ---------------------------------------------------------------------
 * 6e) The buy button never swallows a refused add
 *
 * `wc-ajax=add_to_cart` answers a variable product without a valid variation
 * with `{error, product_url}`. The product page used to follow `product_url`,
 * so the customer saw a page reload and no reason — and a miss that unlocked
 * the button (a duplicate mobile chip could do that) posted an add the shop
 * refused while a success toast was already on screen. Both paths are asserted
 * here: the refusal is shown as an error toast and no redirect happens.
 * ------------------------------------------------------------------- */
$product_js = (string) file_get_contents( $root . '/assets/js/hsw-product.js' );
$dead_ok    = (bool) preg_match( "/applyVariant\(\s*pid,\s*match,\s*match\s*\?\s*false\s*:\s*\(\s*selectionComplete/", $product_js );
$refuse_ok  = false !== strpos( $product_js, 'res.error || res.success === false' );
$redirect   = false !== strpos( $product_js, 'window.location = res.product_url' );
hsw_ci(
	'product page: a refused add is explained, never redirected',
	$dead_ok && $refuse_ok && ! $redirect,
	( $dead_ok ? '' : 'miss does not lock ' ) . ( $refuse_ok ? '' : 'refusal not handled ' ) . ( $redirect ? 'silent redirect present' : '' )
);

/* ---------------------------------------------------------------------
 * 6f) Opening a notification marks it read on arrival
 *
 * The click cannot wait for the REST round trip: the destination page rendered
 * first and printed the old badge, so the customer saw the notification still
 * counted after opening it. Both halves must exist — the link carries
 * `hsw_read=<id>` and the account class reads it before rendering.
 * ------------------------------------------------------------------- */
$account_php = (string) file_get_contents( $root . '/includes/class-hsw-account.php' );
$account_js  = (string) file_get_contents( $root . '/assets/js/hsw-account.js' );
$marker_ok   = false !== strpos( $account_php, 'mark_opened_notification' ) && false !== strpos( $account_php, "'hsw_read'" );
$js_ok       = false !== strpos( $account_js, "hsw_read=" );
$prevent_ok  = (bool) preg_match( "/var target = link[^\n]*\n\s*\n?\s*\/\*?[\s\S]{0,400}?event\.preventDefault\(\);/", $account_js )
	|| false !== strpos( $account_js, 'event.preventDefault();' );
hsw_ci(
	'notifications: opening one is marked on arrival',
	$marker_ok && $js_ok && $prevent_ok,
	( $marker_ok ? '' : 'server marker missing ' ) . ( $js_ok ? '' : 'link marker missing ' ) . ( $prevent_ok ? '' : 'anchor default not cancelled' )
);

/* ---------------------------------------------------------------------
 * 6g) The CSS rebuild walks meta_id, never drags Elementor blobs in batches
 *
 * `SELECT post_id, meta_value … WHERE meta_key = '_elementor_data' ORDER BY
 * post_id LIMIT 40` made MySQL sort every Elementor row on the site for each
 * batch and handed PHP up to 40 serialised page trees at a time (Query Monitor
 * caught it during an update). The batch query must stay index-only and the
 * «does this page use HamiShop?» test must stay in SQL.
 * ------------------------------------------------------------------- */
$upgrader_php = (string) file_get_contents( $root . '/includes/class-hsw-upgrader.php' );
$blob_batch   = (bool) preg_match( "/SELECT\s+post_id,\s*meta_value\s+FROM[^;]*ORDER BY post_id/si", $upgrader_php );
$idx_ok       = (bool) preg_match( "/WHERE meta_key = '_elementor_data' AND meta_id > %d\s+ORDER BY meta_id ASC/si", $upgrader_php );
$sql_like_ok  = false !== strpos( $upgrader_php, 'meta_value LIKE %s' ) && false !== strpos( $upgrader_php, "'%hsw-%'" );
hsw_ci(
	'upgrader: CSS rebuild scans meta_id, not Elementor blobs',
	! $blob_batch && $idx_ok && $sql_like_ok,
	( $blob_batch ? 'blob batch query present ' : '' ) . ( $idx_ok ? '' : 'meta_id cursor missing ' ) . ( $sql_like_ok ? '' : 'SQL LIKE test missing' )
);

/* ---------------------------------------------------------------------
 * 6h) Invoice and postal label are staff documents
 *
 * The owner found «چاپ فاکتور» inside the customer's own order view. Both
 * sheets carry the letterhead, stamp and shipment chart, so the key in the URL
 * must not be enough — `edit_shop_orders` (or `manage_woocommerce`) is. The
 * account panel must not render the button for a customer either.
 * ------------------------------------------------------------------- */
$invoice_php = (string) file_get_contents( $root . '/includes/class-hsw-invoice.php' );
$staff_only  = false !== strpos( $invoice_php, 'if ( ! $is_staff ) {' )
	&& false !== strpos( $invoice_php, "'error' => 'forbidden'" );
$no_owner    = false === strpos( $invoice_php, '! $is_owner && ! $is_staff' );
$account_guarded = false !== strpos( $account_php, "\$may_print = current_user_can( 'edit_shop_orders' )" );
hsw_ci(
	'invoice: printable only with order access',
	$staff_only && $no_owner && $account_guarded,
	( $staff_only ? '' : 'authorize() still allows owners ' ) . ( $no_owner ? '' : 'owner branch present ' ) . ( $account_guarded ? '' : 'account button not guarded' )
);

/* ---------------------------------------------------------------------
 * 6i) Paying an existing order empties the cart it was built from
 *
 * `pay_awaiting_order()` is reached only when the order matches the cart hash,
 * yet it never emptied the cart: the customer paid, saw «ثبت شد», and the same
 * product was still counted in the header. The normal checkout path always
 * emptied it; the resumed-payment path must too.
 * ------------------------------------------------------------------- */
$checkout_php = (string) file_get_contents( $root . '/includes/class-hsw-checkout-complete.php' );
$pay_pos      = strpos( $checkout_php, 'protected function pay_awaiting_order' );
$pay_body     = false !== $pay_pos ? substr( $checkout_php, $pay_pos, 6000 ) : '';
hsw_ci(
	'checkout: a resumed payment empties its cart',
	'' !== $pay_body && false !== strpos( $pay_body, 'empty_cart()' ),
	false !== $pay_pos ? ( false !== strpos( $pay_body, 'empty_cart()' ) ? 'cart cleared' : 'cart kept after payment' ) : 'pay_awaiting_order not found'
);

/* -------------------------------------------------------------------------
 * 7) Documentation set
 * ---------------------------------------------------------------------- */
foreach ( array( 'README.md', 'CHANGELOG.md', 'docs/WIDGETS.md', 'docs/ARCHITECTURE.md', 'docs/DEVELOPMENT.md', 'docs/PERFORMANCE.md', 'docs/STAGING-CHECKLIST.md', 'docs/MARKETING.md' ) as $doc ) {
	$size = is_file( $root . '/' . $doc ) ? filesize( $root . '/' . $doc ) : 0;
	hsw_ci( "docs: {$doc} present", $size > 500, $size . ' bytes' );
}

/* -------------------------------------------------------------------------
 * Summary
 * ---------------------------------------------------------------------- */
echo "\n=== {$checks} checks, " . ( $fails ? "{$fails} failed" : 'all passed' ) . " ===\n";
exit( $fails ? 1 : 0 );
