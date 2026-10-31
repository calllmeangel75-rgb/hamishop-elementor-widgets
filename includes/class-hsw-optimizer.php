<?php
/**
 * Keep front-end optimizers (LiteSpeed Cache above all) from breaking the look.
 *
 * Why this exists: on a live shop the page structure collapsed and the Persian
 * font fell back to Tahoma whenever LiteSpeed's "CSS Combine" / "Unique CSS"
 * was on, and both went away the moment the optimizer was switched off. Two
 * separate mechanisms are behind that:
 *
 *  - Combining rewrites the cascade. Anything whose rule only wins because it is
 *    printed later than the theme's now loses, and Elementor's per-post CSS
 *    (`elementor/css/post-<id>.css`) changes on every save, so the combined file
 *    is rebuilt and re-hashed constantly.
 *  - UCSS/CCSS keep only the rules the generator saw in use. A cart drawer, a
 *    tab panel or an accordion is `display:none` at generation time, so the CSS
 *    that styles it is dropped — the widget renders unstyled once it opens.
 *
 * LiteSpeed exposes a documented integration API (see its own API reference):
 *
 *   apply_filters( 'litespeed_conf', $slug )            read one setting
 *   do_action( 'litespeed_conf_force', $slug, $value )  override for this request only
 *   do_action( 'litespeed_purge_all' )                  purge every cache it manages
 *   do_action( 'litespeed_disable_all', $reason )       switch the plugin off for this request
 *
 * The option slugs used below are LiteSpeed's own constants
 * (`optm-css_exc` = CSS Excludes, `optm-ucss_exc` = UCSS excluded files,
 * `optm-css_font_display` = the font-display rewrite). Nothing is written to the
 * database: the exclusion is applied in memory, request by request, and only for
 * HamiShop's own files — the shop owner's other choices are left alone.
 *
 * Every entry point is inert when LiteSpeed is not installed, so a shop without
 * an optimizer pays nothing.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Optimizer {

	/** LiteSpeed: "Page Optimization → Tuning - CSS → CSS Excludes". */
	const LS_CSS_EXCLUDES = 'optm-css_exc';

	/** LiteSpeed: files UCSS must not strip rules from. */
	const LS_UCSS_EXCLUDES = 'optm-ucss_exc';

	/** LiteSpeed: the font-display value it rewrites every @font-face with. */
	const LS_FONT_DISPLAY = 'optm-css_font_display';

	/** Option that remembers which version the notice was dismissed for. */
	const DISMISS_OPTION = 'hsw_optimizer_notice_dismissed';

	/** @var HSW_Optimizer|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'exclude_own_assets' ), 1 );
		add_action( 'init', array( $this, 'disable_in_editor' ), 2 );

		/* Style choices live in these three options; a saved change must reach
		 * the cached HTML and the generated UCSS immediately. */
		add_action( 'update_option_hsw_palette', array( $this, 'purge_on_style_change' ) );
		add_action( 'update_option_hsw_account_settings', array( $this, 'purge_on_style_change' ) );
		add_action( 'update_option_hsw_security', array( $this, 'purge_on_style_change' ) );

		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'admin_init', array( $this, 'handle_dismiss' ) );
	}

	/* ---------------------------------------------------------------------
	 * Detection
	 * ------------------------------------------------------------------ */

	/**
	 * Is LiteSpeed Cache running in this request?
	 *
	 * `litespeed_conf` is the filter LiteSpeed itself defines for reading its
	 * settings, so its presence is the most reliable, version-independent test.
	 *
	 * @return bool
	 */
	public static function litespeed_active() {
		return defined( 'LSCWP_CTRL' ) || has_filter( 'litespeed_conf' );
	}

	/**
	 * Stable path fragments of HamiShop's own assets.
	 *
	 * @return string[]
	 */
	public static function exclude_lines() {
		$lines = array(
			'hamishop-elementor-widgets/assets/css',
		);

		return (array) apply_filters( 'hsw_optimizer_exclude', $lines );
	}

	/**
	 * Merge lines into an existing one-per-line option without touching what
	 * the shop owner already wrote, and without repeating ourselves.
	 *
	 * @param string   $existing Option value (newline separated).
	 * @param string[] $add      Lines to guarantee.
	 * @return string
	 */
	public static function merge_lines( $existing, $add ) {
		$lines = preg_split( '/\R+/', str_replace( "\r\n", "\n", (string) $existing ), -1, PREG_SPLIT_NO_EMPTY );
		$lines = is_array( $lines ) ? array_map( 'trim', $lines ) : array();
		$lines = array_values( array_filter( $lines ) );

		foreach ( (array) $add as $line ) {
			$line = trim( (string) $line );
			if ( '' !== $line && ! in_array( $line, $lines, true ) ) {
				$lines[] = $line;
			}
		}

		return implode( "\n", $lines );
	}

	/* ---------------------------------------------------------------------
	 * Behaviour
	 * ------------------------------------------------------------------ */

	/**
	 * Keep our stylesheets out of the combined file and out of UCSS stripping.
	 */
	public function exclude_own_assets() {
		if ( ! self::litespeed_active() ) {
			return;
		}

		foreach ( array( self::LS_CSS_EXCLUDES, self::LS_UCSS_EXCLUDES ) as $key ) {
			$current = (string) apply_filters( 'litespeed_conf', $key );
			$merged  = self::merge_lines( $current, self::exclude_lines() );

			if ( $merged !== $current ) {
				/** In memory only: the stored option is never rewritten. */
				do_action( 'litespeed_conf_force', $key, $merged );
			}
		}
	}

	/**
	 * An editor or preview request must never see optimized CSS.
	 *
	 * LiteSpeed already turns itself off for the editor itself; the preview
	 * iframe is a normal front-end render, and that is where the merchant judges
	 * whether a style change worked.
	 */
	public function disable_in_editor() {
		if ( ! self::litespeed_active() || ! is_admin() ) {
			return;
		}

		$preview = isset( $_GET['elementor-preview'] ) || isset( $_GET['fl_builder'] ) || isset( $_GET['et_fb'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $preview && class_exists( '\Elementor\Plugin' ) ) {
			$elementor = \Elementor\Plugin::instance();
			if ( isset( $elementor->editor ) && method_exists( $elementor->editor, 'is_edit_mode' ) && $elementor->editor->is_edit_mode() ) {
				$preview = true;
			}
		}

		if ( $preview ) {
			do_action( 'litespeed_disable_all', 'HamiShop: page builder edit/preview' );
		}
	}

	/**
	 * Purge the caches an optimizer keeps of our HTML/CSS.
	 *
	 * Called after a version bump (which already flushed Elementor's own CSS
	 * cache) and after the style options are saved. Each plugin is asked only
	 * through its own documented hook, so an absent optimizer changes nothing.
	 *
	 * @param string $reason Short text for the optimizer's own debug log.
	 */
	public static function purge( $reason = 'HamiShop: style assets changed' ) {
		if ( self::litespeed_active() ) {
			do_action( 'litespeed_purge_all', $reason );
		}
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}

		/** Let any other cache plugin follow the same moment. */
		do_action( 'hsw_optimizer_purged', $reason );
	}

	/**
	 * Option-save wrapper (the hooks pass the new value, which we do not need).
	 */
	public function purge_on_style_change() {
		self::purge( 'HamiShop: design settings saved' );
	}

	/* ---------------------------------------------------------------------
	 * Admin notice
	 * ------------------------------------------------------------------ */

	/**
	 * Where the merchant is told what the optimizer would have done.
	 *
	 * Shown on the HamiShop dashboard screen only, and dismissible per version.
	 */
	public function notice() {
		if ( ! self::litespeed_active() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'hamishop' ) ) {
			return;
		}

		if ( (string) get_option( self::DISMISS_OPTION ) === HSW_VERSION ) {
			return;
		}

		$combine = (string) apply_filters( 'litespeed_conf', 'optm-css_comb' );
		$ucss    = (string) apply_filters( 'litespeed_conf', 'optm-ucss' );
		$font    = (string) apply_filters( 'litespeed_conf', self::LS_FONT_DISPLAY );

		echo '<div class="notice notice-info is-dismissible"><p><strong>' . esc_html__( 'HamiShop: سازگاری با بهینه‌ساز لایت‌اسپید', 'hamishop-elementor-widgets' ) . '</strong></p>';
		echo '<p>' . esc_html__( 'فایل‌های CSS این افزونه از ادغام و از حذف قاعده‌های UCSS خارج می‌شوند (فقط در حافظه، تنظیمات شما دست‌نخورده است). برای اینکه ساختار صفحه و فونت وزیرمتن همیشه درست بماند، این موارد را هم در نظر بگیرید:', 'hamishop-elementor-widgets' ) . '</p>';
		echo '<ul style="margin:0 0 8px 14px">';
		echo '<li><code>elementor/css/post-</code> ' . esc_html__( 'و', 'hamishop-elementor-widgets' ) . ' <code>elementor/assets/css</code> ' . esc_html__( 'را در «CSS Excludes» نگه دارید؛ CSS المنتور با هر ذخیره عوض می‌شود و ادغام آن باعث تولید مداوم فایل ترکیبی و بی‌اعتبارشدن کش می‌شود.', 'hamishop-elementor-widgets' ) . '</li>';
		if ( '' !== $font && 'optional' !== $font && 'swap' === $font ) {
			echo '<li>' . esc_html__( 'تنظیم Font Display روی swap است؛ خوب است.', 'hamishop-elementor-widgets' ) . '</li>';
		} elseif ( '' !== $font ) {
			echo '<li><strong>' . esc_html__( 'Font Display', 'hamishop-elementor-widgets' ) . '</strong> = <code>' . esc_html( $font ) . '</code> — ' . esc_html__( 'این مقدار روی همهٔ @font-faceها نوشته می‌شود؛ اگر روی optional یا block باشد فونت گاهی روی Tahoma می‌ماند. آن را روی swap بگذارید.', 'hamishop-elementor-widgets' ) . '</li>';
		}
		if ( '' !== $ucss && false !== (bool) $ucss ) {
			echo '<li>' . esc_html__( 'UCSS/Critical CSS قاعده‌های سبد کشویی، تب‌ها و آکاردئون‌ها را «استفاده‌نشده» می‌بیند و حذفشان می‌کند؛ اگر بعد از فعال‌بودنش استایل این‌ها پرید، UCSS را برای این صفحه‌ها خاموش کنید.', 'hamishop-elementor-widgets' ) . '</li>';
		}
		echo '</ul>';
		if ( '' !== $combine ) {
			echo '<p>' . esc_html__( 'وضعیت فعلی CSS Combine در لایت‌اسپید:', 'hamishop-elementor-widgets' ) . ' <code>' . esc_html( $combine ) . '</code></p>';
		}
		echo '<p><a class="button" href="' . esc_url( wp_nonce_url( add_query_arg( 'hsw_dismiss_optimizer_notice', HSW_VERSION ), 'hsw_dismiss_optimizer', 'hsw_dismiss_nonce' ) ) . '">' . esc_html__( 'باشه، فعلاً نشان نده', 'hamishop-elementor-widgets' ) . '</a></p>';
		echo '</div>';
	}

	/**
	 * Remember the dismissal for this version only.
	 */
	public function handle_dismiss() {
		if ( ! isset( $_GET['hsw_dismiss_optimizer_notice'], $_GET['hsw_dismiss_nonce'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['hsw_dismiss_nonce'] ) ), 'hsw_dismiss_optimizer' ) ) {
			return;
		}

		update_option( self::DISMISS_OPTION, sanitize_text_field( wp_unslash( $_GET['hsw_dismiss_optimizer_notice'] ) ), false );

		$redirect = remove_query_arg( array( 'hsw_dismiss_optimizer_notice', 'hsw_dismiss_nonce' ) );
		if ( '' !== $redirect && wp_validate_redirect( $redirect ) ) {
			wp_safe_redirect( $redirect );
			exit;
		}
	}
}
