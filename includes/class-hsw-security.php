<?php
/**
 * Lightweight security hardening.
 *
 * Two jobs, both cheap enough to run on every request:
 *
 * 1. Move the dashboard to a custom path. The rewrite lives in the root
 *    `.htaccess`; `/wp-admin` answers anonymous visitors with a plain 404 so
 *    scanners and credential-stuffing bots cannot find the login surface.
 *    Logged-in users are always let through — a plugin must never be able to
 *    lock an administrator out of their own site.
 * 2. Stop user enumeration through the REST API: `/wp/v2/users` and
 *    `/wp/v2/users/<id>` disappear for anyone who cannot already list users.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Security {

	const OPTION = 'hsw_security';
	const MARKER = 'HamiShop Admin URL';

	/** @var HSW_Security|null */
	private static $instance = null;

	/** @var bool True while the current request arrived through the custom slug. */
	private $on_custom_slug = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 13 );
		add_action( 'admin_init', array( $this, 'maybe_save' ) );
		add_action( 'admin_init', array( $this, 'keep_rewrite_alive' ), 20 );
		add_filter( 'rest_endpoints', array( $this, 'filter_rest_users' ) );
		add_action( 'admin_notices', array( $this, 'rewrite_notice' ) );

		/*
		 * Everything below touches the admin URL. When the escape hatch is set in
		 * wp-config.php the whole mechanism has to stay inert — not just the 404
		 * guard: the cookie derivation and the request flag are part of the same
		 * feature, and leaving them on would keep a half-active state alive.
		 */
		if ( ! self::disabled() ) {
			add_action( 'init', array( $this, 'guard_admin_url' ), 1 );
			add_filter( 'determine_current_user', array( $this, 'resolve_user_from_logged_in_cookie' ), 20 );

			/*
			 * Set the flag now, not in guard_admin_url(): `determine_current_user`
			 * can fire during plugins_loaded, long before init, and it has to know
			 * that this request came in through the custom slug.
			 */
			$this->on_custom_slug = $this->is_custom_slug_request();
		}

		$this->maybe_disable_xmlrpc();
		$this->maybe_clean_frontend();
	}

	/* ---------------------------------------------------------------------
	 * Front-end cleanup (optional)
	 *
	 * These are the classic "head cleanup" tweaks: drop the emoji script, the
	 * RSD/WLW/shortlink/generator/oEmbed tags and — when asked — the block
	 * stylesheets on pages that do not need them.
	 *
	 * Cost: zero at runtime. Every item is a `remove_action` / `wp_dequeue_*`
	 * that stops a request from being made; nothing is added. The saving is
	 * small (a few KB and 2–4 requests) but it is never negative.
	 *
	 * Risk: removing `wp-block-library` / `global-styles` breaks the styling of
	 * posts written with the block editor, which is why it is a separate
	 * checkbox and why it is skipped on cart / checkout / account.
	 * ------------------------------------------------------------------ */

	private function maybe_clean_frontend() {
		$o = self::get();

		if ( 'yes' === $o['clean_head'] ) {
			add_action( 'init', array( $this, 'clean_head' ) );
		}
		if ( 'yes' === $o['clean_assets'] ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'clean_assets' ), 100 );
		}
		if ( 'yes' === $o['clean_widgets'] ) {
			add_action( 'widgets_init', array( $this, 'clean_widgets' ), 11 );
		}
		if ( 'yes' === $o['font_swap'] ) {
			add_action( 'template_redirect', array( $this, 'start_font_swap' ), 1 );
		}
	}

	/* ---------------------------------------------------------------------
	 * font-display: swap
	 *
	 * A font block (`font-display: block`) keeps the text invisible for up to
	 * three seconds while the file downloads; `swap` paints the fallback
	 * immediately and swaps when the webfont lands. Our own fonts already use
	 * swap, but themes and page builders inline their @font-face with `auto`
	 * or `block`, and there is no CSS-level way to override it.
	 *
	 * So we buffer the finished HTML and rewrite the value inside <style>
	 * blocks only — no parsing of markup, no touching of external files.
	 * ------------------------------------------------------------------ */

	/**
	 * Start buffering the front-end response.
	 */
	public function start_font_swap() {
		if ( is_admin() || wp_doing_ajax() || $this->is_elementor_context() ) {
			return;
		}

		if ( ! empty( $GLOBALS['wp_query'] ) && ( is_feed() || is_robots() || is_trackback() ) ) {
			return;
		}

		ob_start( array( $this, 'rewrite_font_display' ) );
	}

	/**
	 * Rewrite `font-display` inside inline styles and Google Fonts URLs.
	 *
	 * @param string $html Buffered response.
	 * @return string
	 */
	public function rewrite_font_display( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}

		/*
		 * Only touch <style> blocks; inline attributes never carry font-display.
		 * The pattern is written with double quotes so the character class can
		 * hold both quote types without escaping gymnastics.
		 */
		$html = preg_replace_callback(
			"#<style\b[^>]*>.*?</style>#is",
			static function ( $match ) {
				return preg_replace( '/font-display\s*:\s*(?:auto|block|fallback)\b/i', 'font-display:swap', $match[0] );
			},
			$html
		);

		/* Google Fonts: `display=block` (or a missing parameter) → swap. */
		$html = preg_replace_callback(
			"#https://fonts\.googleapis\.com/[^\"'\s>]+#i",
			static function ( $match ) {
				$url = $match[0];
				if ( preg_match( '/[?&]display=/i', $url ) ) {
					return preg_replace( '/([?&]display=)[a-z]+/i', '$1swap', $url );
				}
				return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . 'display=swap';
			},
			$html
		);

		return $html;
	}

	/**
	 * Elementor builds its preview inside the same front-end request, so the
	 * cleanup must never run there — a missing stylesheet makes the editor look
	 * broken even though the published page is fine.
	 *
	 * @return bool
	 */
	private function is_elementor_context() {
		if ( ! empty( $_GET['elementor-preview'] ) || ! empty( $_GET['elementor_library'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only context check.
			return true;
		}
		if ( is_admin() && isset( $_GET['action'] ) && 'elementor' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}
		return false;
	}

	/**
	 * Emoji + the head links nobody reads.
	 */
	public function clean_head() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

		remove_action( 'wp_head', 'feed_links_extra', 3 );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
	}

	/**
	 * wp-embed plus the block stylesheets on pages that do not use blocks.
	 *
	 * The REST API, feeds and the block editor itself keep working: only the
	 * front-end stylesheet is dequeued, and only outside the account area.
	 */
	public function clean_assets() {
		if ( is_admin() || $this->is_elementor_context() ) {
			return;
		}

		wp_dequeue_script( 'wp-embed' );

		$is_account_area = function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() );
		if ( $is_account_area ) {
			return;
		}

		/* Escape hatch: a site whose blog posts use blocks can keep the styles. */
		if ( apply_filters( 'hsw_keep_block_styles', false ) ) {
			return;
		}

		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'wc-blocks-style' );
		wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'classic-theme-styles' );
	}

	/**
	 * The default widgets almost no store uses.
	 */
	public function clean_widgets() {
		unregister_widget( 'WP_Widget_Calendar' );
		unregister_widget( 'WP_Widget_Meta' );
		unregister_widget( 'WP_Widget_RSS' );
		unregister_widget( 'WP_Widget_Tag_Cloud' );
		unregister_widget( 'WP_Widget_Links' );
	}

	/**
	 * Emergency off switch: `define( 'HSW_DISABLE_ADMIN_URL', true );` in
	 * wp-config.php disables the custom admin URL without touching the database.
	 *
	 * @return bool
	 */
	private static function disabled() {
		return defined( 'HSW_DISABLE_ADMIN_URL' ) && HSW_DISABLE_ADMIN_URL;
	}

	/**
	 * Does a request path fall inside the custom slug?
	 *
	 * Exact match or a real sub-path only. A plain prefix comparison would make
	 * `/manage-guide` look like `/manage` and swallow an unrelated page into the
	 * admin rewrite.
	 *
	 * @param string $rel  Request path relative to the home path.
	 * @param string $slug Custom slug.
	 * @return bool
	 */
	private static function path_matches_slug( $rel, $slug ) {
		$base = '/' . $slug;

		return $rel === $base || 0 === strpos( $rel, $base . '/' );
	}

	/**
	 * Does the current request path start with the saved slug?
	 *
	 * @return bool
	 */
	private function is_custom_slug_request() {
		$s = self::get();
		if ( '' === (string) $s['admin_slug'] ) {
			return false;
		}
		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$request   = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
		$rel       = self::relative_path( $request, $home_path );

		return self::path_matches_slug( $rel, (string) $s['admin_slug'] );
	}

	/**
	 * Resolve the current user for requests that arrive through the custom slug.
	 *
	 * WordPress scopes the `auth` cookie to `<home>/wp-admin`, so a request for
	 * `/manage/` never carries it. In the admin WordPress deliberately refuses to
	 * fall back to the `logged_in` cookie (`wp_validate_logged_in_cookie()` bails
	 * when `is_blog_admin()`), which leaves the user as 0 and every page answers
	 * "not allowed".
	 *
	 * Instead of widening the real cookie to the whole site — that would send the
	 * admin credential on every front-end request — the equivalent `auth` cookie
	 * is derived from the `logged_in` cookie for this single request only. The
	 * browser is never given a broader cookie, and existing sessions work without
	 * having to log in again.
	 *
	 * @param int $user_id User resolved so far (0 when none).
	 * @return int
	 */
	public function resolve_user_from_logged_in_cookie( $user_id ) {
		if ( $user_id || ! $this->on_custom_slug || self::disabled() ) {
			return $user_id;
		}
		$resolved = $this->inject_auth_cookie();
		return $resolved ? $resolved : $user_id;
	}

	/**
	 * Derive the `auth` cookie for this request and put it in $_COOKIE.
	 *
	 * `auth_redirect()` validates `$_COOKIE[ AUTH_COOKIE ]` directly and never
	 * runs through `determine_current_user`, so this has to be called eagerly —
	 * not only from the user-resolution filter.
	 *
	 * On HTTPS the matching cookie is `SECURE_AUTH_COOKIE` with the
	 * `secure_auth` scheme; minting a plain `auth` cookie there would be
	 * rejected by `auth_redirect()` and bounce the user back to the login screen.
	 *
	 * @return int User ID, or 0 when nothing could be derived.
	 */
	private function inject_auth_cookie() {
		if ( ! defined( 'AUTH_COOKIE' ) || ! defined( 'LOGGED_IN_COOKIE' ) ) {
			return 0;
		}

		$secure      = is_ssl() && defined( 'SECURE_AUTH_COOKIE' );
		$scheme      = $secure ? 'secure_auth' : 'auth';
		$cookie_name = $secure ? SECURE_AUTH_COOKIE : AUTH_COOKIE;

		if ( isset( $_COOKIE[ $cookie_name ] ) || empty( $_COOKIE[ LOGGED_IN_COOKIE ] ) ) {
			return 0;
		}

		$raw = wp_unslash( $_COOKIE[ LOGGED_IN_COOKIE ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by WordPress below.

		/*
		 * Validate before trusting anything in it: `wp_parse_auth_cookie()` only
		 * splits the string and checks the HMAC — it never looks at the session
		 * token. `wp_validate_auth_cookie()` is the call that also proves the
		 * session still exists, so it runs first and its result is what we build on.
		 */
		$logged_in_user = wp_validate_auth_cookie( $raw, 'logged_in' );
		if ( ! $logged_in_user ) {
			return 0;
		}

		$parsed = wp_parse_auth_cookie( $raw, 'logged_in' );
		if ( empty( $parsed['username'] ) || empty( $parsed['token'] ) || empty( $parsed['expiration'] ) ) {
			return 0;
		}

		$user = get_user_by( 'login', $parsed['username'] );
		if ( ! $user || (int) $user->ID !== (int) $logged_in_user ) {
			return 0;
		}

		$auth = wp_generate_auth_cookie( $user->ID, (int) $parsed['expiration'], $scheme, $parsed['token'] );
		if ( ! $auth ) {
			return 0;
		}

		/* Full validation of the derived cookie, including the session token. */
		$resolved = wp_validate_auth_cookie( $auth, $scheme );
		if ( ! $resolved ) {
			return 0;
		}

		$_COOKIE[ $cookie_name ] = $auth;

		return (int) $resolved;
	}

	/* ---------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	/**
	 * @return array<string,string>
	 */
	public static function defaults() {
		return array(
			'admin_slug'       => '',
			'admin_verified'   => 'no',
			'block_rest_users' => 'yes',
			'disable_xmlrpc'   => 'no',
			/* Front-end cleanup — off until the shop owner ticks the box. */
			'clean_head'       => 'no',
			'clean_assets'     => 'no',
			'clean_widgets'    => 'no',
			'font_swap'        => 'no',
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::defaults(), $saved );
	}

	/**
	 * Paths that must never be used as the custom admin slug.
	 *
	 * @return string[]
	 */
	private static function reserved_slugs() {
		return array( 'wp-admin', 'wp-login', 'wp-content', 'wp-includes', 'wp-json', 'wp-cron', 'wp-signup', 'wp-activate', 'admin', 'login', 'dashboard', 'feed', 'xmlrpc', 'robots', 'sitemap' );
	}

	/**
	 * Why a slug cannot be used, or '' when it is fine.
	 *
	 * A slug that collides with an existing page, a WooCommerce page, or a post
	 * type / taxonomy base would either shadow real content or be shadowed by it
	 * — the rewrite and WordPress would both claim the same URL.
	 *
	 * @param string $slug Sanitised slug.
	 * @return string Human-readable conflict label, '' when free.
	 */
	private static function slug_conflict( $slug ) {
		/* An existing page or post with this path. */
		$page = get_page_by_path( $slug, OBJECT, array( 'page', 'post' ) );
		if ( $page ) {
			return sprintf(
				/* translators: %s: post type label. */
				__( 'یک %s موجود با همین آدرس', 'hamishop-elementor-widgets' ),
				(string) get_post_type_object( $page->post_type )->labels->singular_name
			);
		}

		/* WooCommerce's own pages — the store cannot live under a slug that
		   already serves the shop, cart, checkout or account screen. */
		if ( function_exists( 'wc_get_page_id' ) ) {
			$wc_pages = array(
				'shop'      => __( 'صفحهٔ فروشگاه ووکامرس', 'hamishop-elementor-widgets' ),
				'cart'      => __( 'صفحهٔ سبد خرید ووکامرس', 'hamishop-elementor-widgets' ),
				'checkout'  => __( 'صفحهٔ تسویه‌حساب ووکامرس', 'hamishop-elementor-widgets' ),
				'myaccount' => __( 'صفحهٔ حساب کاربری ووکامرس', 'hamishop-elementor-widgets' ),
			);
			foreach ( $wc_pages as $key => $label ) {
				$page_id = (int) wc_get_page_id( $key );
				if ( $page_id > 0 && get_post_field( 'post_name', $page_id ) === $slug ) {
					return $label;
				}
			}
		}

		/* Post type and taxonomy rewrite bases. */
		$objects = array_merge(
			array_values( get_post_types( array( 'public' => true ), 'objects' ) ),
			array_values( get_taxonomies( array( 'public' => true ), 'objects' ) )
		);
		foreach ( $objects as $object ) {
			if ( empty( $object->rewrite ) ) {
				continue;
			}
			$base = isset( $object->rewrite['slug'] ) ? (string) $object->rewrite['slug'] : '';
			if ( '' !== $base && trim( $base, '/' ) === $slug ) {
				return sprintf(
					/* translators: %s: post type or taxonomy label. */
					__( 'پایهٔ آدرس «%s»', 'hamishop-elementor-widgets' ),
					(string) $object->labels->name
				);
			}
		}

		return '';
	}

	/**
	 * Validate a slug and explain why it was rejected.
	 *
	 * @param mixed $raw Raw input (may be an array from a malformed POST).
	 * @return array{slug:string,error:string}
	 */
	public static function validate_slug( $raw ) {
		$raw = is_string( $raw ) ? $raw : '';
		$raw = trim( $raw, " /\\\t\n\r\0\x0B" );

		if ( '' === $raw ) {
			return array(
				'slug'  => '',
				'error' => '',
			);
		}

		$slug = preg_replace( '/[^a-z0-9_-]/', '', (string) sanitize_title( $raw ) );

		if ( '' === $slug ) {
			return array(
				'slug'  => '',
				'error' => __( 'آدرس باید فقط شامل حروف انگلیسی کوچک، عدد، خط تیره یا زیرخط باشد.', 'hamishop-elementor-widgets' ),
			);
		}
		if ( strlen( $slug ) < 3 ) {
			return array(
				'slug'  => '',
				'error' => __( 'آدرس باید حداقل ۳ کاراکتر باشد.', 'hamishop-elementor-widgets' ),
			);
		}
		if ( in_array( $slug, self::reserved_slugs(), true ) ) {
			return array(
				'slug'  => '',
				/* translators: %s: slug. */
				'error' => sprintf( __( '«%s» جزو مسیرهای رزروشدهٔ وردپرس است. نام دیگری انتخاب کنید.', 'hamishop-elementor-widgets' ), $slug ),
			);
		}

		$conflict = self::slug_conflict( $slug );
		if ( '' !== $conflict ) {
			return array(
				'slug'  => '',
				/* translators: 1: slug, 2: conflicting object. */
				'error' => sprintf( __( '«%1$s» با %2$s تداخل دارد. نام دیگری انتخاب کنید.', 'hamishop-elementor-widgets' ), $slug, $conflict ),
			);
		}

		return array(
			'slug'  => $slug,
			'error' => '',
		);
	}

	/**
	 * Sanitised slug, or '' when invalid.
	 *
	 * Kept for callers that only need the value; `validate_slug()` is the one
	 * that can explain a rejection.
	 *
	 * @param string $raw Raw input.
	 * @return string
	 */
	public static function sanitize_slug( $raw ) {
		$result = self::validate_slug( $raw );
		return (string) $result['slug'];
	}

	/* ---------------------------------------------------------------------
	 * Admin URL guard
	 * ------------------------------------------------------------------ */

	/**
	 * Strip the site's subdirectory prefix from a request path.
	 *
	 * @param string $request   Request path.
	 * @param string $home_path Site home path (may be '/').
	 * @return string
	 */
	private static function relative_path( $request, $home_path ) {
		$home_path = rtrim( (string) $home_path, '/' );
		if ( '' !== $home_path && 0 === strpos( $request, $home_path ) ) {
			$request = substr( $request, strlen( $home_path ) );
		}
		return '/' . ltrim( (string) $request, '/' );
	}

	/**
	 * Serve the dashboard from the custom slug and hide the real path.
	 *
	 * Runs on `init` priority 1: `wp-admin/*.php` has already booted WordPress
	 * by then (wp-load fires init), but nothing has dispatched the page yet, so
	 * rewriting REQUEST_URI here is safe and complete.
	 */
	public function guard_admin_url() {
		if ( self::disabled() ) {
			return;
		}

		$s    = self::get();
		$slug = (string) $s['admin_slug'];

		if ( '' === $slug ) {
			return;
		}

		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$request   = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
		$rel       = self::relative_path( $request, $home_path );

		$is_slug  = self::path_matches_slug( $rel, $slug );
		$is_admin = 0 === strpos( $rel, '/wp-admin' );

		/*
		 * Compare the path first. Most requests are neither the slug nor
		 * wp-admin, and they must not pay for reading `.htaccess` at all.
		 */
		if ( ! $is_slug && ! $is_admin ) {
			return;
		}

		if ( ! self::rewrite_installed() ) {
			return;
		}

		/* Custom slug: hand the request to wp-admin under the real path. */
		if ( $is_slug ) {
			$rest  = substr( $rel, strlen( $slug ) + 1 ); // '' or '/post.php'.
			$query = isset( $_SERVER['QUERY_STRING'] ) && '' !== $_SERVER['QUERY_STRING']
				? '?' . wp_unslash( $_SERVER['QUERY_STRING'] )
				: '';

			/* Keep the query: admin pages live on `?page=…` and `?post=…`. */
			$_SERVER['REQUEST_URI'] = rtrim( $home_path, '/' ) . '/wp-admin' . $rest . $query;
			$this->on_custom_slug   = true;

			$uid = $this->inject_auth_cookie();
			if ( $uid ) {
				wp_set_current_user( $uid );
			}
			return;
		}

		/*
		 * admin-ajax.php and admin-post.php stay public: themes and plugins post
		 * to them from the storefront for logged-out visitors (cart, filters,
		 * contact forms). Blocking them breaks the shop, not the attacker.
		 */
		if ( preg_match( '#^/wp-admin/(admin-ajax|admin-post)\.php$#', $rel ) ) {
			return;
		}

		/* Never lock a signed-in user out of their own dashboard. */
		if ( is_user_logged_in() ) {
			/*
			 * Canonicalise the dashboard landing page only, and only once the
			 * rewrite has been proven to work (see verify()). Every other
			 * wp-admin file keeps answering normally, so a broken rewrite can
			 * never strand an administrator.
			 */
			if ( 'yes' === $s['admin_verified'] && in_array( rtrim( $rel, '/' ), array( '/wp-admin', '/wp-admin/index.php' ), true ) ) {
				$query = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : '';
				$to    = rtrim( $home_path, '/' ) . '/' . $slug . '/' . ( '' !== $query ? '?' . $query : '' );
				wp_safe_redirect( $to );
				exit;
			}
			return;
		}

		/* Anonymous hit on the real path: answer exactly like a missing file. */
		header( 'HTTP/1.1 404 Not Found' );
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, nofollow', true );
		echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL was not found on this server.</p></body></html>';
		exit;
	}

	/* ---------------------------------------------------------------------
	 * .htaccess rewrite
	 * ------------------------------------------------------------------ */

	/**
	 * `wp-admin` → custom path rewriting is not something WordPress can do on
	 * its own: the browser asks for a URL that has no file behind it, so the
	 * web server has to rewrite it. Apache reads the block we keep in the root
	 * `.htaccess`.
	 */

	/**
	 * Verify the rewrite actually reaches wp-admin.
	 *
	 * A block can sit in `.htaccess` and still be ignored (AllowOverride None,
	 * Nginx, a hardened host). Asking the site for the custom URL is the only
	 * honest check: WordPress answers a real request with a redirect to the
	 * login screen, while a missing rewrite answers 404.
	 *
	 * @param string $slug Sanitised slug.
	 * @return string 'yes' (works), 'no' (404), 'unknown' (could not reach).
	 */
	private static function verify( $slug ) {
		$url = trailingslashit( home_url( '/' ) ) . $slug . '/';

		$res = wp_remote_get(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 0,
				/* Same default WordPress uses for its own loopback checks. */
				'sslverify'   => (bool) apply_filters( 'https_local_ssl_verify', false ),
				'user-agent'  => 'HamiShop-Security-Check',
			)
		);

		if ( is_wp_error( $res ) ) {
			return 'unknown';
		}

		$code = (int) wp_remote_retrieve_response_code( $res );

		if ( 404 === $code ) {
			return 'no';
		}
		return $code >= 200 && $code < 400 ? 'yes' : 'unknown';
	}

	/**
	 * Put the block back if something rewrote `.htaccess` in the meantime.
	 *
	 * WordPress rebuilds `.htaccess` with `insert_with_markers()` whenever the
	 * permalink settings are saved, which preserves marker blocks but can still
	 * drop ours on hosts that rewrite the file themselves. Re-checking on
	 * admin_init keeps the custom URL alive; it costs one cached read per admin
	 * request and nothing on the front end.
	 */
	public function keep_rewrite_alive() {
		if ( self::disabled() ) {
			return;
		}

		$s = self::get();
		if ( '' === (string) $s['admin_slug'] || self::rewrite_installed() ) {
			return;
		}
		if ( self::install_rewrite( $s['admin_slug'] ) ) {
			return;
		}
		// Could not restore: stop claiming the feature is active.
		$s['admin_verified'] = 'no';
		update_option( self::OPTION, $s, false );
	}

	/**
	 * `.htaccess` lives at the site's home path, not at `ABSPATH` — on a
	 * WordPress install inside a subdirectory those differ, and writing to
	 * `ABSPATH` would put the rules in a file Apache never reads.
	 *
	 * @return string
	 */
	private static function htaccess_path() {
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$home = (string) get_home_path();

		return ( $home ? $home : ABSPATH ) . '.htaccess';
	}

	/**
	 * Read `.htaccess` once per request.
	 *
	 * Three callers ask for it (the guard, the keep-alive check and the admin
	 * notice); without this each of them re-reads the file.
	 *
	 * @return string Empty string when the file is missing or unreadable.
	 */
	private static function htaccess_contents() {
		static $contents = null;

		if ( null !== $contents ) {
			return $contents;
		}

		$file     = self::htaccess_path();
		$contents = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file, no HTTP.

		return $contents;
	}

	/**
	 * Keep a copy of the file as it was before the first edit, in an option.
	 *
	 * A `.htaccess.hsw-bak` next to the original would be served as plain text
	 * on hosts that do not deny `.ht*`, so the copy is stored in the database
	 * instead — it is small and never public.
	 *
	 * @param string $content Current file contents.
	 */
	private static function backup_once( $content ) {
		if ( '' === trim( $content ) ) {
			return;
		}
		if ( false !== get_option( 'hsw_htaccess_backup', false ) ) {
			return;
		}
		update_option( 'hsw_htaccess_backup', $content, false );
	}

	/**
	 * Rewrite block for a slug.
	 *
	 * @param string $slug Sanitised slug.
	 * @return string
	 */
	private static function rules( $slug ) {
		return '# BEGIN ' . self::MARKER . "\n"
			. "<IfModule mod_rewrite.c>\n"
			. "RewriteEngine On\n"
			. 'RewriteRule ^' . $slug . '/?$ wp-admin/index.php [L]' . "\n"
			. 'RewriteRule ^' . $slug . '/(.*)$ wp-admin/$1 [L]' . "\n"
			. "</IfModule>\n"
			. '# END ' . self::MARKER . "\n";
	}

	/**
	 * Remove our block from a string.
	 *
	 * The `s` modifier is essential: without it `.` stops at the first newline,
	 * the block is never matched, and every save appends another copy.
	 *
	 * @param string $content .htaccess contents.
	 * @return string
	 */
	private static function strip_block( $content ) {
		$out = preg_replace( '/[ \t]*# BEGIN ' . self::MARKER . '.*?# END ' . self::MARKER . "[ \t]*\r?\n?/s", '', (string) $content );
		return null === $out ? (string) $content : $out;
	}

	/**
	 * Is the rewrite block present and pointing at the saved slug?
	 *
	 * @return bool
	 */
	public static function rewrite_installed() {
		$content = self::htaccess_contents();
		if ( '' === $content || false === strpos( $content, '# BEGIN ' . self::MARKER ) ) {
			return false;
		}
		$s = self::get();
		return '' !== $s['admin_slug'] && false !== strpos( $content, '^' . $s['admin_slug'] . '/(.*)$' );
	}

	/**
	 * Write the rewrite block. Returns false when `.htaccess` is not writable.
	 *
	 * The block is prepended rather than appended on purpose: it has to be
	 * evaluated before WordPress' catch-all `RewriteRule . /index.php [L]`, and
	 * `insert_with_markers()` always appends a new block at the end of the file.
	 *
	 * @param string $slug Sanitised slug.
	 * @return bool
	 */
	public static function install_rewrite( $slug ) {
		$file    = self::htaccess_path();
		$content = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		self::backup_once( $content );

		$content = self::rules( $slug ) . self::strip_block( $content );

		return false !== @file_put_contents( $file, $content, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- .htaccess is not writable through the WP filesystem API on most hosts.
	}

	/**
	 * Drop the rewrite block (slug cleared, or plugin deactivated).
	 *
	 * @return bool
	 */
	public static function remove_rewrite() {
		$file = self::htaccess_path();
		if ( ! is_readable( $file ) ) {
			return true;
		}
		$content = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === strpos( $content, '# BEGIN ' . self::MARKER ) ) {
			return true;
		}
		return false !== @file_put_contents( $file, self::strip_block( $content ), LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/* ---------------------------------------------------------------------
	 * REST + XML-RPC
	 * ------------------------------------------------------------------ */

	/**
	 * Remove the user endpoints for anyone who cannot already list users.
	 *
	 * `/wp/v2/users/me` is left alone on purpose — the block editor and the
	 * account panel rely on it for the signed-in user.
	 *
	 * @param array $endpoints Registered REST endpoints.
	 * @return array
	 */
	public function filter_rest_users( $endpoints ) {
		$s = self::get();
		if ( 'yes' !== $s['block_rest_users'] ) {
			return $endpoints;
		}
		if ( current_user_can( 'list_users' ) ) {
			return $endpoints;
		}

		unset( $endpoints['/wp/v2/users'] );
		unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );

		return $endpoints;
	}

	/**
	 * Turn off XML-RPC when asked (it is another enumeration surface).
	 */
	public function maybe_disable_xmlrpc() {
		$s = self::get();
		if ( 'yes' === $s['disable_xmlrpc'] ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'wp_headers', array( $this, 'drop_pingback' ) );
		}
	}

	/**
	 * @param array $headers Response headers.
	 * @return array
	 */
	public function drop_pingback( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	/* ---------------------------------------------------------------------
	 * Admin
	 * ------------------------------------------------------------------ */

	public function menu() {
		add_submenu_page(
			'hamishop',
			__( 'امنیت', 'hamishop-elementor-widgets' ),
			__( 'امنیت', 'hamishop-elementor-widgets' ),
			'manage_options',
			'hamishop-security',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Warn when the rewrite block is gone — protection is silently off otherwise.
	 */
	public function rewrite_notice() {
		$s = self::get();
		if ( '' === $s['admin_slug'] || self::rewrite_installed() ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>HamiShop:</strong> '
			. esc_html__( 'قواعد بازنویسی آدرس پیشخوان از فایل .htaccess حذف شده است. تا وقتی دوباره ذخیره نکنید، آدرس سفارشی کار نمی‌کند و wp-admin برای همه باز است.', 'hamishop-elementor-widgets' )
			. ' <a href="' . esc_url( admin_url( 'admin.php?page=hamishop-security' ) ) . '">'
			. esc_html__( 'رفتن به تنظیمات امنیت', 'hamishop-elementor-widgets' ) . '</a></p></div>';
	}

	public function maybe_save() {
		if ( ! isset( $_POST['hsw_security_nonce'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! check_admin_referer( 'hsw_save_security', 'hsw_security_nonce' ) ) {
			return;
		}

		$in   = isset( $_POST['hsw_security'] ) ? (array) wp_unslash( $_POST['hsw_security'] ) : array();
		$raw  = isset( $in['admin_slug'] ) && is_string( $in['admin_slug'] ) ? $in['admin_slug'] : '';
		$slug = '';
		$check = 'no';

		/* `validate_slug()` explains the rejection: bad characters, a reserved
		   path, an existing page, a WooCommerce page, a rewrite base. */
		$validated = self::validate_slug( $raw );
		if ( '' !== $validated['error'] ) {
			add_settings_error( 'hsw_security', 'slug', $validated['error'], 'error' );
			return;
		}
		$slug = (string) $validated['slug'];

		if ( '' !== $slug ) {
			if ( ! self::install_rewrite( $slug ) ) {
				add_settings_error( 'hsw_security', 'htaccess', __( 'فایل .htaccess قابل نوشتن نیست، پس آدرس سفارشی فعال نشد. دسترسی نوشتن بدهید یا قواعد را دستی اضافه کنید.', 'hamishop-elementor-widgets' ), 'error' );
				return;
			}

			$check = self::verify( $slug );

			if ( 'no' === $check ) {
				self::remove_rewrite();
				add_settings_error(
					'hsw_security',
					'verify',
					__( 'قواعد در .htaccess نوشته شد ولی وب‌سرور آن را اجرا نمی‌کند (احتمالاً Nginx یا AllowOverride خاموش است). برای جلوگیری از قفل‌شدن، آدرس سفارشی فعال نشد. قواعد Nginx در پایین همین صفحه آمده است.', 'hamishop-elementor-widgets' ),
					'error'
				);
				return;
			}

			if ( 'unknown' === $check ) {
				add_settings_error(
					'hsw_security',
					'verify-unknown',
					__( 'آدرس سفارشی ذخیره شد ولی تست خودکار دسترسی ممکن نبود. یک‌بار آدرس را در مرورگر باز کنید؛ اگر خطای ۴۰۴ دیدید، قواعد .htaccess را دستی بررسی کنید.', 'hamishop-elementor-widgets' ),
					'warning'
				);
			}
		} else {
			self::remove_rewrite();
		}

		update_option(
			self::OPTION,
			array(
				'admin_slug'       => $slug,
				'admin_verified'   => '' !== $slug ? (string) $check : 'no',
				'block_rest_users' => ( 'yes' === ( $in['block_rest_users'] ?? '' ) ) ? 'yes' : 'no',
				'disable_xmlrpc'   => ( 'yes' === ( $in['disable_xmlrpc'] ?? '' ) ) ? 'yes' : 'no',
				'clean_head'       => ( 'yes' === ( $in['clean_head'] ?? '' ) ) ? 'yes' : 'no',
				'clean_assets'     => ( 'yes' === ( $in['clean_assets'] ?? '' ) ) ? 'yes' : 'no',
				'clean_widgets'    => ( 'yes' === ( $in['clean_widgets'] ?? '' ) ) ? 'yes' : 'no',
				'font_swap'        => ( 'yes' === ( $in['font_swap'] ?? '' ) ) ? 'yes' : 'no',
			),
			false
		);

		/* The rewrite check in rewrite_installed() reads the saved option. */
		add_settings_error( 'hsw_security', 'saved', __( 'تنظیمات امنیتی ذخیره شد.', 'hamishop-elementor-widgets' ), 'updated' );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s      = self::get();
		$slug   = (string) $s['admin_slug'];
		$home   = home_url( '/' );
		$custom = '' !== $slug ? trailingslashit( $home ) . $slug . '/' : '';
		$ready  = self::rewrite_installed();
		$warn   = '' !== $slug && ! $ready;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'امنیت', 'hamishop-elementor-widgets' ); ?></h1>
			<p class="description" style="max-width:780px">
				<?php esc_html_e( 'سبک و بدون بار اضافه: فقط دو قاعدهٔ ساده که روی هر درخواست هزینهٔ محسوسی ندارند.', 'hamishop-elementor-widgets' ); ?>
			</p>

			<?php settings_errors( 'hsw_security' ); ?>

			<?php if ( $warn ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'قواعد بازنویسی در .htaccess پیدا نشد؛ آدرس سفارشی الان کار نمی‌کند و wp-admin برای همه باز است. یک‌بار ذخیره کنید تا دوباره نوشته شود.', 'hamishop-elementor-widgets' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'hsw_save_security', 'hsw_security_nonce' ); ?>

				<h2><?php esc_html_e( 'آدرس پیشخوان', 'hamishop-elementor-widgets' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row"><label for="hsw-sec-slug"><?php esc_html_e( 'آدرس دلخواه', 'hamishop-elementor-widgets' ); ?></label></th>
						<td>
							<code dir="ltr"><?php echo esc_html( trailingslashit( $home ) ); ?></code>
							<input type="text" class="regular-text" id="hsw-sec-slug" name="hsw_security[admin_slug]"
								value="<?php echo esc_attr( $slug ); ?>" placeholder="manage" dir="ltr" style="max-width:220px" />
							<p class="description">
								<?php esc_html_e( 'خالی بگذارید تا wp-admin به حالت عادی برگردد. فقط حروف انگلیسی کوچک، عدد، خط تیره و زیرخط.', 'hamishop-elementor-widgets' ); ?>
							</p>
							<?php if ( '' !== $custom ) : ?>
								<p>
									<strong><?php esc_html_e( 'آدرس فعلی پیشخوان:', 'hamishop-elementor-widgets' ); ?></strong>
									<a href="<?php echo esc_url( $custom ); ?>" target="_blank" rel="noopener" dir="ltr"><?php echo esc_html( $custom ); ?></a>
									<?php if ( $ready ) : ?>
										<span style="color:#008a20">✔ <?php esc_html_e( 'فعال', 'hamishop-elementor-widgets' ); ?></span>
									<?php endif; ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					</tbody>
				</table>

				<div class="notice notice-info inline" style="max-width:780px">
					<p>
						<strong><?php esc_html_e( 'دقیقاً چه چیزی محافظت می‌شود؟', 'hamishop-elementor-widgets' ); ?></strong>
						<?php esc_html_e( 'کاربر مهمان (کسی که وارد نشده) با باز کردن wp-admin صفحهٔ ۴۰۴ می‌بیند؛ اسکنرها و ربات‌های حمله دیگر مسیر پیشخوان را پیدا نمی‌کنند. کاربر واردشده همیشه دسترسی دارد، پس امکان قفل‌شدن حساب مدیر وجود ندارد. آدرس سفارشی همیشه کار می‌کند و می‌توانید آن را بوکمارک کنید.', 'hamishop-elementor-widgets' ); ?>
					</p>
					<p>
						<strong><?php esc_html_e( 'چه چیزی محافظت نمی‌شود؟', 'hamishop-elementor-widgets' ); ?></strong>
						<?php esc_html_e( 'wp-login.php سر جای خود باز می‌ماند و هیچ‌جا مخفی نمی‌شود — این ماژول صفحهٔ ورود را جابه‌جا نمی‌کند. یعنی کسی که آدرس ورود را می‌داند همچنان به فرم ورود دسترسی دارد؛ چیزی که کم می‌شود «پیدا کردن خودکار پیشخوان» است، نه «ورود». برای محدودکردن تلاش ورود از یک افزونهٔ مخصوص همین کار استفاده کنید. همچنین توجه کنید این قابلیت کوکی نشست مدیر را برای همان درخواست از روی کوکی ورود می‌سازد تا آدرس سفارشی احراز هویت شود؛ اگر این سطح از اعتماد را نمی‌پسندید، با ثابت HSW_DISABLE_ADMIN_URL در wp-config.php خاموشش کنید.', 'hamishop-elementor-widgets' ); ?>
					</p>
					<p>
						<?php esc_html_e( 'admin-ajax.php و admin-post.php باز می‌مانند، چون فرم‌ها و فیلترهای فروشگاه از آن‌ها استفاده می‌کنند.', 'hamishop-elementor-widgets' ); ?>
					</p>
					<p>
						<?php esc_html_e( 'اگر از Nginx استفاده می‌کنید، .htaccess کار نمی‌کند؛ این دو خط را به کانفیگ سایت اضافه کنید:', 'hamishop-elementor-widgets' ); ?>
					</p>
					<pre dir="ltr" style="background:#f6f7f7;padding:10px;border-radius:6px;overflow:auto">location ~ ^/<?php echo esc_html( '' !== $slug ? $slug : 'manage' ); ?>/(.*)$ {
    rewrite ^/<?php echo esc_html( '' !== $slug ? $slug : 'manage' ); ?>/(.*)$ /wp-admin/$1 last;
}</pre>
				</div>

				<h2><?php esc_html_e( 'مخفی‌کردن فهرست کاربران', 'hamishop-elementor-widgets' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'REST API', 'hamishop-elementor-widgets' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="hsw_security[block_rest_users]" value="yes" <?php checked( 'yes', $s['block_rest_users'] ); ?> />
								<?php esc_html_e( 'بستن /wp-json/wp/v2/users برای کاربران مهمان', 'hamishop-elementor-widgets' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'بدون این گزینه، هر کسی می‌تواند با یک درخواست ساده نام کاربری مدیر را پیدا کند. مدیران و ویرایشگران دسترسی خود را حفظ می‌کنند.', 'hamishop-elementor-widgets' ); ?>
							</p>
							<p>
								<a href="<?php echo esc_url( rest_url( 'wp/v2/users' ) ); ?>" target="_blank" rel="noopener" dir="ltr"><?php echo esc_html( rest_url( 'wp/v2/users' ) ); ?></a>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'XML-RPC', 'hamishop-elementor-widgets' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="hsw_security[disable_xmlrpc]" value="yes" <?php checked( 'yes', $s['disable_xmlrpc'] ); ?> />
								<?php esc_html_e( 'غیرفعال‌کردن XML-RPC', 'hamishop-elementor-widgets' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'اگر از اپلیکیشن وردپرس یا جت‌پک استفاده نمی‌کنید، خاموش‌کردنش امن‌تر است؛ XML-RPC یکی دیگر از راه‌های لو دادن نام کاربری است.', 'hamishop-elementor-widgets' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'بهینه‌سازی و پاکسازی فرانت', 'hamishop-elementor-widgets' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="hsw_security[clean_head]" value="yes" <?php checked( 'yes', $s['clean_head'] ); ?> />
								<?php esc_html_e( 'حذف ایموجی و تگ‌های اضافی هدر', 'hamishop-elementor-widgets' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'اسکریپت ایموجی، RSD، WLW، shortlink، generator و لینک‌های oEmbed حذف می‌شوند. نه کوئری‌ای اضافه می‌کند نه چیزی را کند می‌کند — فقط چند تگ و یک درخواست کمتر.', 'hamishop-elementor-widgets' ); ?>
							</p>

							<label style="display:block;margin-top:10px">
								<input type="checkbox" name="hsw_security[clean_assets]" value="yes" <?php checked( 'yes', $s['clean_assets'] ); ?> />
								<?php esc_html_e( 'حذف wp-embed و استایل بلاک‌ها در صفحات غیر از سبد/تسویه/حساب', 'hamishop-elementor-widgets' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'اگر نوشته‌های بلاگ‌تان را با ویرایشگر بلاک (گوتنبرگ) نوشته‌اید، این گزینه را نزنید؛ استایل بلاک‌ها لازم می‌شود. با فیلتر hsw_keep_block_styles هم می‌توانید فقط استایل بلاک‌ها را نگه دارید.', 'hamishop-elementor-widgets' ); ?>
							</p>

							<label style="display:block;margin-top:10px">
								<input type="checkbox" name="hsw_security[clean_widgets]" value="yes" <?php checked( 'yes', $s['clean_widgets'] ); ?> />
								<?php esc_html_e( 'حذف ویجت‌های کم‌کاربرد (تقویم، متا، RSS، ابر برچسب، لینک‌ها)', 'hamishop-elementor-widgets' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'فقط فهرست ویجت‌های پیشخوان کوتاه می‌شود؛ روی سرعت سایت اثری ندارد.', 'hamishop-elementor-widgets' ); ?>
							</p>

							<label style="display:block;margin-top:10px">
								<input type="checkbox" name="hsw_security[font_swap]" value="yes" <?php checked( 'yes', $s['font_swap'] ); ?> />
								<?php esc_html_e( 'اجبار font-display: swap روی همهٔ فونت‌های سایت', 'hamishop-elementor-widgets' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'با font-display: block متن تا سه ثانیه نامرئی می‌ماند تا فونت برسد. با swap، متن با فونت جایگزین نمایش داده می‌شود و بعد فونت اصلی جای آن می‌نشیند. این گزینه مقدار auto/block/fallback را در استایل‌های درون‌صفحه و آدرس‌های Google Fonts به swap تغییر می‌دهد؛ روی فایل‌های CSS خارجی دست نمی‌برد.', 'hamishop-elementor-widgets' ); ?>
							</p>

							<p class="description" style="margin-top:12px">
								<strong><?php esc_html_e( 'هزینهٔ کارایی:', 'hamishop-elementor-widgets' ); ?></strong>
								<?php esc_html_e( 'صفر. این گزینه‌ها فقط چیزهایی را که وردپرس اضافه می‌کند حذف می‌کنند؛ هیچ کوئری یا فایلی اضافه نمی‌شود و در ویرایشگر المنتور هم اجرا نمی‌شوند.', 'hamishop-elementor-widgets' ); ?>
							</p>
						</td>
					</tr>
					</tbody>
				</table>

				<p class="submit">
					<?php submit_button( __( 'ذخیره تنظیمات', 'hamishop-elementor-widgets' ), 'primary', 'submit', false ); ?>
				</p>
			</form>
		</div>
		<?php
	}
}
