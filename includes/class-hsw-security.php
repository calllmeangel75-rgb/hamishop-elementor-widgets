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
		add_action( 'init', array( $this, 'guard_admin_url' ), 1 );
		add_filter( 'rest_endpoints', array( $this, 'filter_rest_users' ) );
		add_action( 'admin_notices', array( $this, 'rewrite_notice' ) );
		add_filter( 'determine_current_user', array( $this, 'resolve_user_from_logged_in_cookie' ), 20 );

		/*
		 * Set the flag now, not in guard_admin_url(): `determine_current_user`
		 * can fire during plugins_loaded, long before init, and it has to know
		 * that this request came in through the custom slug.
		 */
		$this->on_custom_slug = $this->is_custom_slug_request();

		$this->maybe_disable_xmlrpc();
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

		return 0 === strpos( $rel, '/' . $s['admin_slug'] );
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
		if ( $user_id || ! $this->on_custom_slug ) {
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
	 * @return int User ID, or 0 when nothing could be derived.
	 */
	private function inject_auth_cookie() {
		if ( ! defined( 'AUTH_COOKIE' ) || ! defined( 'LOGGED_IN_COOKIE' ) ) {
			return 0;
		}
		if ( isset( $_COOKIE[ AUTH_COOKIE ] ) || empty( $_COOKIE[ LOGGED_IN_COOKIE ] ) ) {
			return 0;
		}

		$parsed = wp_parse_auth_cookie( wp_unslash( $_COOKIE[ LOGGED_IN_COOKIE ] ), 'logged_in' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed and HMAC-verified by WordPress.
		if ( empty( $parsed['username'] ) || empty( $parsed['token'] ) ) {
			return 0;
		}

		$user = get_user_by( 'login', $parsed['username'] );
		if ( ! $user ) {
			return 0;
		}

		$auth = wp_generate_auth_cookie( $user->ID, (int) $parsed['expiration'], 'auth', $parsed['token'] );
		if ( ! $auth ) {
			return 0;
		}

		/* Full validation, including the session token. */
		$resolved = wp_validate_auth_cookie( $auth, 'auth' );
		if ( ! $resolved ) {
			return 0;
		}

		$_COOKIE[ AUTH_COOKIE ] = $auth;

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
		return array( 'wp-admin', 'wp-login', 'wp-content', 'wp-includes', 'wp-json', 'wp-cron', 'admin', 'login', 'dashboard', 'feed', 'xmlrpc' );
	}

	/**
	 * Sanitised slug, or '' when invalid.
	 *
	 * @param string $raw Raw input.
	 * @return string
	 */
	public static function sanitize_slug( $raw ) {
		$slug = sanitize_title( trim( (string) $raw, " /\\\t\n\r\0\x0B" ) );
		if ( '' === $slug || in_array( $slug, self::reserved_slugs(), true ) ) {
			return '';
		}
		// Keep the rewrite rule safe: no slashes, no dots, no regex metacharacters.
		$slug = preg_replace( '/[^a-z0-9_-]/', '', $slug );
		return (string) $slug;
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
		$s    = self::get();
		$slug = (string) $s['admin_slug'];

		if ( '' === $slug || ! self::rewrite_installed() ) {
			return;
		}
		if ( defined( 'HSW_DISABLE_ADMIN_URL' ) && HSW_DISABLE_ADMIN_URL ) {
			return;
		}

		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$request   = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
		$rel       = self::relative_path( $request, $home_path );

		/* Custom slug: hand the request to wp-admin under the real path. */
		if ( 0 === strpos( $rel, '/' . $slug ) ) {
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

		if ( 0 !== strpos( $rel, '/wp-admin' ) ) {
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
				'sslverify'   => false,
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
	 * permalink settings are saved, which drops every block that is not its
	 * own. Re-checking on admin_init keeps the custom URL alive without
	 * touching the front-end request path.
	 */
	public function keep_rewrite_alive() {
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

	private static function htaccess_path() {
		return ABSPATH . '.htaccess';
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
		$file = self::htaccess_path();
		if ( ! is_readable( $file ) ) {
			return false;
		}
		$content = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file, no HTTP.
		if ( false === strpos( $content, '# BEGIN ' . self::MARKER ) ) {
			return false;
		}
		$s = self::get();
		return '' !== $s['admin_slug'] && false !== strpos( $content, '^' . $s['admin_slug'] . '/(.*)$' );
	}

	/**
	 * Write the rewrite block. Returns false when `.htaccess` is not writable.
	 *
	 * @param string $slug Sanitised slug.
	 * @return bool
	 */
	public static function install_rewrite( $slug ) {
		$file    = self::htaccess_path();
		$content = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = self::strip_block( $content );
		$content = self::rules( $slug ) . $content;

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

		$in    = isset( $_POST['hsw_security'] ) ? (array) wp_unslash( $_POST['hsw_security'] ) : array();
		$raw   = (string) ( $in['admin_slug'] ?? '' );
		$slug  = self::sanitize_slug( $raw );
		$check = 'no';

		if ( '' !== trim( $raw ) && '' === $slug ) {
			add_settings_error( 'hsw_security', 'slug', __( 'آدرس واردشده معتبر نیست یا جزو مسیرهای رزروشدهٔ وردپرس است. یک نام دیگر انتخاب کنید.', 'hamishop-elementor-widgets' ), 'error' );
			return;
		}

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
						<strong><?php esc_html_e( 'چه اتفاقی می‌افتد؟', 'hamishop-elementor-widgets' ); ?></strong>
						<?php esc_html_e( 'کاربر مهمان (کسی که وارد نشده) با باز کردن wp-admin صفحهٔ ۴۰۴ می‌بیند. کاربر واردشده همیشه دسترسی دارد، پس امکان قفل‌شدن حساب مدیر وجود ندارد. آدرس سفارشی همیشه کار می‌کند و می‌توانید آن را بوکمارک کنید.', 'hamishop-elementor-widgets' ); ?>
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
