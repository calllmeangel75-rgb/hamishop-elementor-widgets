<?php
/**
 * Brand & logo tab — sender identity, white-label wording, logo precedence.
 *
 * QA-only; the release ZIP excludes tests/ (.gitattributes export-ignore). Run:
 *   php tests/qa/prelive-brand.php
 *
 * Three of these cases were red before 1.11.190:
 *   1.1  an empty «نام فرستنده» sent every mail as "WordPress" instead of the
 *        brand name its own help text promises;
 *   2.1  the rebrand switch only matched the exact string «ووکامرس», so
 *        «وضعیت ووکامرس» and «راه‌اندازی ووکامرس» stayed as they were;
 *   2.3  the filter also rewrote the help text that describes the filter,
 *        turning «وردپرس» به «پنل مدیریت» into «پنل مدیریت» به «پنل مدیریت».
 */

require_once __DIR__ . '/prelive-lib.php';
@ini_set( 'error_log', 'NUL' );

/* ---- WordPress surface the two classes touch ------------------------- */

if ( ! function_exists( 'get_theme_mod' ) ) {
	function get_theme_mod( $name, $default = false ) {
		return $GLOBALS['hsw_theme_mods'][ $name ] ?? $default;
	}
}
if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
	function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) {
		return isset( $GLOBALS['hsw_attachments'][ (int) $id ] ) ? $GLOBALS['hsw_attachments'][ (int) $id ] : false;
	}
}
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return ! empty( $GLOBALS['hsw_is_admin'] );
	}
}

require_once getenv( 'HSW_PRELIVE_BRAND' ) ?: dirname( __DIR__, 2 ) . '/includes/class-hsw-branding.php';
require_once getenv( 'HSW_PRELIVE_REBRAND' ) ?: dirname( __DIR__, 2 ) . '/includes/class-hsw-rebrand.php';

$GLOBALS['hsw_results'] = array();

function b_case( $id, $title, callable $fn ) {
	try {
		$out = $fn();
	} catch ( Throwable $e ) {
		$out = array( false, 'EXCEPTION: ' . $e->getMessage() );
	}
	$ok = (bool) $out[0];
	$GLOBALS['hsw_results'][] = array( $ok, $id, $title );
	printf( "[%s] %s  %s — %s\n", $ok ? 'PASS' : 'FAIL', $id, $title, $out[1] );
}

function b_reset() {
	HSW_Prelive_Store::reset();
	$GLOBALS['hsw_theme_mods']  = array();
	$GLOBALS['hsw_attachments'] = array();
	$GLOBALS['hsw_is_admin']    = false;
}

function b_brand( array $values ) {
	update_option( 'hsw_brand', $values );
}

/* ---------------------------------------------------------------------
 * 1) Sender identity
 * ------------------------------------------------------------------ */

b_case(
	'1.1',
	'an empty sender name falls back to the brand name, not "WordPress"',
	static function () {
		b_reset();
		b_brand( array( 'name' => 'فروشگاه پزشکی', 'from_name' => '' ) );
		$brand = HSW_Branding::instance();
		$out   = $brand->filter_from_name( 'WordPress' );
		return array( 'فروشگاه پزشکی' === $out, 'name=' . $out );
	}
);

b_case(
	'1.2',
	'a typed sender name wins over the brand name',
	static function () {
		b_reset();
		b_brand( array( 'name' => 'فروشگاه پزشکی', 'from_name' => 'واحد فروش' ) );
		$out = HSW_Branding::instance()->filter_from_name( 'WordPress' );
		return array( 'واحد فروش' === $out, 'name=' . $out );
	}
);

b_case(
	'1.3',
	'an empty from address leaves the site default alone',
	static function () {
		b_reset();
		b_brand( array( 'from_email' => '' ) );
		$out = HSW_Branding::instance()->filter_from_email( 'wordpress@localhost' );
		return array( 'wordpress@localhost' === $out, 'email=' . $out );
	}
);

b_case(
	'1.4',
	'a valid from address replaces the default',
	static function () {
		b_reset();
		b_brand( array( 'from_email' => 'no-reply@shop.ir' ) );
		$out = HSW_Branding::instance()->filter_from_email( 'wordpress@localhost' );
		return array( 'no-reply@shop.ir' === $out, 'email=' . $out );
	}
);

b_case(
	'1.5',
	'a broken from address is ignored instead of breaking wp_mail',
	static function () {
		b_reset();
		b_brand( array( 'from_email' => 'not-an-email' ) );
		$out = HSW_Branding::instance()->filter_from_email( 'wordpress@localhost' );
		return array( 'wordpress@localhost' === $out, 'email=' . $out );
	}
);

/* ---------------------------------------------------------------------
 * 2) White-label wording
 * ------------------------------------------------------------------ */

b_case(
	'2.1',
	'«ووکامرس» is replaced inside a sentence, not only as a whole string',
	static function () {
		b_reset();
		b_brand( array( 'rebrand' => 'yes' ) );
		$r   = new HSW_Rebrand();
		$out = $r->translate( 'وضعیت ووکامرس', 'WooCommerce status', 'woocommerce' );
		return array( 'وضعیت فروشگاه' === $out, 'out=' . $out );
	}
);

b_case(
	'2.2',
	'the bare word still becomes «فروشگاه»',
	static function () {
		b_reset();
		b_brand( array( 'rebrand' => 'yes' ) );
		$out = ( new HSW_Rebrand() )->translate( 'ووکامرس', 'WooCommerce', 'woocommerce' );
		return array( 'فروشگاه' === $out, 'out=' . $out );
	}
);

b_case(
	'2.3',
	'our own help text is never rewritten by the filter it describes',
	static function () {
		b_reset();
		b_brand( array( 'rebrand' => 'yes' ) );
		$GLOBALS['hsw_is_admin'] = true;
		$text = 'در متن‌های ترجمه‌شده، «ووکامرس» به «فروشگاه» و در پیشخوان «وردپرس» به «پنل مدیریت» تبدیل می‌شود.';
		$out  = ( new HSW_Rebrand() )->translate( $text, $text, 'hamishop-elementor-widgets' );
		$GLOBALS['hsw_is_admin'] = false;
		return array( $out === $text, 'unchanged=' . var_export( $out === $text, true ) );
	}
);

b_case(
	'2.4',
	'unrelated strings pass through untouched',
	static function () {
		b_reset();
		b_brand( array( 'rebrand' => 'yes' ) );
		$out = ( new HSW_Rebrand() )->translate( 'محصول', 'Product', 'woocommerce' );
		return array( 'محصول' === $out, 'out=' . $out );
	}
);

b_case(
	'2.5',
	'«وردپرس» becomes «پنل مدیریت» in the admin only',
	static function () {
		b_reset();
		b_brand( array( 'rebrand' => 'yes' ) );
		$r = new HSW_Rebrand();

		$front = $r->translate( 'دربارهٔ وردپرس', 'About WordPress', 'default' );
		$GLOBALS['hsw_is_admin'] = true;
		$admin = $r->translate( 'دربارهٔ وردپرس', 'About WordPress', 'default' );
		$GLOBALS['hsw_is_admin'] = false;

		return array( 'دربارهٔ وردپرس' === $front && 'دربارهٔ پنل مدیریت' === $admin, 'front=' . $front . ' admin=' . $admin );
	}
);

/* ---------------------------------------------------------------------
 * 3) Logo precedence
 * ------------------------------------------------------------------ */

b_case(
	'3.1',
	'the library logo wins over the URL field',
	static function () {
		b_reset();
		$GLOBALS['hsw_attachments'][12494] = 'https://shop.ir/library-logo.png';
		b_brand( array( 'logo_id' => 12494, 'logo_url' => 'https://cdn.example/url-logo.png' ) );
		$out = HSW_Branding::logo_url();
		return array( 'https://shop.ir/library-logo.png' === $out, 'url=' . $out );
	}
);

b_case(
	'3.2',
	'the URL is used when no library logo is chosen',
	static function () {
		b_reset();
		b_brand( array( 'logo_id' => 0, 'logo_url' => 'https://cdn.example/url-logo.png' ) );
		$out = HSW_Branding::logo_url();
		return array( 'https://cdn.example/url-logo.png' === $out, 'url=' . $out );
	}
);

b_case(
	'3.3',
	'an empty picker falls back to the theme custom logo',
	static function () {
		b_reset();
		$GLOBALS['hsw_theme_mods']['custom_logo'] = 77;
		$GLOBALS['hsw_attachments'][77]          = 'https://shop.ir/theme-logo.png';
		b_brand( array( 'logo_id' => 0, 'logo_url' => '' ) );
		$out = HSW_Branding::logo_url();
		return array( 'https://shop.ir/theme-logo.png' === $out, 'url=' . $out );
	}
);

b_case(
	'3.4',
	'no logo anywhere is an empty string, not a broken <img>',
	static function () {
		b_reset();
		b_brand( array( 'logo_id' => 0, 'logo_url' => '' ) );
		$url  = HSW_Branding::logo_url();
		$html = HSW_Branding::logo_html();
		return array( '' === $url && '' === $html, 'url=' . var_export( $url, true ) . ' html=' . var_export( $html, true ) );
	}
);

$failed = 0;
foreach ( $GLOBALS['hsw_results'] as $r ) {
	$failed += $r[0] ? 0 : 1;
}
printf( "\nbrand: %d cases, %d failed\n", count( $GLOBALS['hsw_results'] ), $failed );
exit( $failed ? 1 : 0 );
