<?php
/**
 * Custom 404 screen.
 *
 * The theme's 404 is a bare heading; a shop loses the visitor there. This one
 * keeps the header/footer, shows a glassmorphism card over a deep neon
 * gradient, and offers a product search plus two ways out (shop / home).
 *
 * Toggle: HamiShop → کنترل ویجت‌ها → صفحهٔ ۴۰۴ اختصاصی (`hsw_custom_404_enabled`).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_404 {

	const OPTION = 'hsw_custom_404_enabled';

	public function __construct() {
		add_filter( 'template_include', array( $this, 'maybe_render' ), 99 );
	}

	/**
	 * Replace the 404 template with the plugin's own screen.
	 *
	 * Returning a real file keeps get_header()/get_footer() working, so the
	 * site chrome stays intact.
	 *
	 * @param string $template Theme template.
	 * @return string
	 */
	public function maybe_render( $template ) {
		if ( is_admin() || ! is_404() ) {
			return $template;
		}
		if ( 'yes' !== get_option( self::OPTION, 'yes' ) ) {
			return $template;
		}
		if ( defined( 'ELEMENTOR_VERSION' ) && isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $template;
		}

		$file = HSW_DIR . 'templates/404.php';
		if ( ! file_exists( $file ) ) {
			return $template;
		}
		return $file;
	}

	/**
	 * Inline styles for the screen (no extra HTTP request on an error page).
	 *
	 * @return string
	 */
	public static function styles() {
		return '
		.hsw-404{position:relative;overflow:hidden;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:72px 18px;background:
			radial-gradient(900px 520px at 12% 8%, color-mix(in srgb, var(--hsw-pal-accent, #f97316) 26%, transparent), transparent 60%),
			radial-gradient(760px 480px at 88% 18%, color-mix(in srgb, var(--hsw-primary, #2563eb) 30%, transparent), transparent 62%),
			linear-gradient(150deg, var(--hsw-pal-hero-a, #0b2036), var(--hsw-pal-hero-b, #173b5c) 55%, #050d18)}
		.hsw-404__orb{position:absolute;border-radius:50%;filter:blur(70px);opacity:.55;pointer-events:none;animation:hsw404float 12s ease-in-out infinite}
		.hsw-404__orb--1{width:340px;height:340px;background:var(--hsw-pal-accent,#f97316);top:-90px;inset-inline-start:-60px}
		.hsw-404__orb--2{width:420px;height:420px;background:color-mix(in srgb,var(--hsw-primary,#2563eb) 80%,#a855f7);bottom:-140px;inset-inline-end:-80px;animation-delay:-4s}
		.hsw-404__orb--3{width:240px;height:240px;background:#22d3ee;top:45%;inset-inline-start:60%;opacity:.32;animation-delay:-8s}
		@keyframes hsw404float{0%,100%{transform:translate3d(0,0,0)}50%{transform:translate3d(0,-26px,0)}}
		.hsw-404__card{position:relative;z-index:2;width:100%;max-width:620px;padding:clamp(28px,5vw,52px);text-align:center;border-radius:28px;
			background:rgba(255,255,255,.07);backdrop-filter:blur(22px);-webkit-backdrop-filter:blur(22px);
			border:1px solid rgba(255,255,255,.16);
			box-shadow:0 24px 80px rgba(0,0,0,.35), 0 0 40px rgba(249,115,22,.15)}
		.hsw-404__code{display:block;font-size:clamp(72px,14vw,136px);line-height:.92;font-weight:900;letter-spacing:-.04em;
			background:linear-gradient(120deg,#fff 10%,color-mix(in srgb,var(--hsw-pal-accent,#f97316) 85%,#fff) 45%,#7dd3fc 90%);
			-webkit-background-clip:text;background-clip:text;color:transparent;
			filter:drop-shadow(0 0 26px rgba(249,115,22,.45))}
		.hsw-404__title{margin:10px 0 8px;color:#fff;font-size:clamp(19px,3.4vw,26px);font-weight:800;line-height:1.5}
		.hsw-404__desc{margin:0 auto 22px;max-width:44ch;color:rgba(255,255,255,.72);font-size:13.5px;line-height:2}
		.hsw-404__search{display:flex;gap:8px;margin:0 auto 18px;max-width:430px}
		.hsw-404__search input[type=search]{flex:1;min-width:0;height:46px;border-radius:12px;border:1px solid rgba(255,255,255,.22);
			background:rgba(255,255,255,.1);color:#fff;padding:0 14px;font:inherit;font-size:13px;outline:none}
		.hsw-404__search input[type=search]::placeholder{color:rgba(255,255,255,.5)}
		.hsw-404__search input[type=search]:focus{border-color:rgba(255,255,255,.5);background:rgba(255,255,255,.16)}
		.hsw-404__search button{height:46px;padding:0 18px;border:0;border-radius:12px;cursor:pointer;font:inherit;font-size:13px;font-weight:800;color:#0b2036;
			background:linear-gradient(135deg,#fff,#dbeafe);box-shadow:0 8px 22px rgba(0,0,0,.25)}
		.hsw-404__actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
		.hsw-404__cta,.hsw-404__ghost{display:inline-flex;align-items:center;justify-content:center;gap:8px;height:48px;padding:0 22px;border-radius:14px;
			font-size:13.5px;font-weight:800;text-decoration:none;transition:transform .15s,box-shadow .2s,background-color .2s}
		.hsw-404__cta{color:#fff;background:linear-gradient(135deg,var(--hsw-pal-accent,#f97316),color-mix(in srgb,var(--hsw-primary,#2563eb) 70%,#f97316));
			box-shadow:0 14px 34px rgba(249,115,22,.38),0 0 0 1px rgba(255,255,255,.18) inset}
		.hsw-404__cta:hover{transform:translateY(-2px);box-shadow:0 18px 42px rgba(249,115,22,.5),0 0 0 1px rgba(255,255,255,.28) inset}
		.hsw-404__ghost{color:#fff;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.24)}
		.hsw-404__ghost:hover{background:rgba(255,255,255,.18);transform:translateY(-2px)}
		.hsw-404__cta:hover,.hsw-404__cta:focus,.hsw-404__ghost:hover,.hsw-404__ghost:focus{color:#fff}
		.hsw-404__search button:hover,.hsw-404__search button:focus{background:linear-gradient(135deg,#fff,#bfdbfe);color:#0b2036}
		@media (max-width:560px){.hsw-404__search{flex-direction:column}.hsw-404__search button{width:100%}.hsw-404__actions{flex-direction:column}.hsw-404__cta,.hsw-404__ghost{width:100%}}
		@media (prefers-reduced-motion:reduce){.hsw-404__orb{animation:none}}
		';
	}
}
