<?php
/**
 * Keep front-end optimizers (LiteSpeed Cache above all) from breaking the look.
 *
 * Why this exists: on a live shop the page structure collapsed and the Persian
 * font fell back to Tahoma whenever LiteSpeed's page optimization was on, and
 * both came back the moment it was switched off. Two separate mechanisms:
 *
 *  - CSS Combine rewrites the cascade. Anything whose rule only wins because it
 *    is printed later than the theme's now loses, and Elementor's per-post CSS
 *    (`elementor/css/post-<id>.css`) changes on every save, so the combined file
 *    is rebuilt and re-hashed constantly.
 *  - UCSS/CCSS keeps only the rules the generator saw in use. A cart drawer, a
 *    tab panel or an accordion is `display:none` at generation time, so the CSS
 *    that styles it is dropped — the widget renders unstyled once it opens.
 *
 * The integration points below are the ones LiteSpeed itself uses; every line
 * was read from the installed plugin (litespeed-cache 7.9.2):
 *
 *   src/optimize.cls.php:1037  apply_filters( 'litespeed_optimize_css_excludes', conf('optm-css_exc') )
 *   src/ucss.cls.php:137       apply_filters( 'litespeed_ucss_exc', conf('optm-ucss_exc') )
 *   src/data.cls.php:655       load_css_exc( array $list_in ): array   (array in, array out)
 *   src/api.cls.php:70         add_filter( 'litespeed_conf', ... )     (read one setting)
 *   src/api.cls.php:110        add_action( 'litespeed_purge_all', ... )
 *   src/api.cls.php:163        add_action( 'litespeed_disable_all', ... )
 *   litespeed-cache.php:38     define( 'LSCWP_V', '7.9.2' )
 *
 * Note what is deliberately NOT used: `litespeed_conf_force` on `optm-css_exc`.
 * That option is an array and LiteSpeed appends to it
 * (`$excludes[] = 'litespeed-dummy.css'`), so forcing a string there breaks the
 * optimizer with a scalar-array notice. The filter above is the supported path,
 * runs at the point of use, and cannot be read too early.
 *
 * The filters are registered unconditionally: if LiteSpeed is not installed they
 * simply never fire, so a shop without an optimizer pays nothing.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Optimizer {

	/** LiteSpeed setting: the value it writes into every @font-face. */
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
		/*
		 * Priority 20: LiteSpeed merges its own `css_excludes.txt` into the list
		 * at priority 10 (Data::load_css_exc), so appending after it keeps both.
		 */
		add_filter( 'litespeed_optimize_css_excludes', array( $this, 'add_excludes' ), 20 );
		add_filter( 'litespeed_ucss_exc', array( $this, 'add_excludes' ), 20 );

		/*
		 * JS is not CSS: combining it changes *when* the code runs. Our files are
		 * already deferred and order-sensitive (feedback → product → checkout),
		 * and the header/menu/picker handlers rely on running before the first
		 * paint settles. LiteSpeed's "JS Combine" (on in the shop's export) would
		 * fold them into one file with someone else's script in the middle.
		 */
		add_filter( 'litespeed_optimize_js_excludes', array( $this, 'add_js_excludes' ), 20 );
		add_filter( 'litespeed_optm_js_defer_exc', array( $this, 'add_js_excludes' ), 20 );

		/*
		 * WP Rocket: the same rule for the same reason. Our stylesheets carry
		 * the layout (grid columns, the pinned header, the bottom sheets), so a
		 * "Remove Unused CSS" pass that decides a rule is unused does not just
		 * change a colour — it moves blocks, which is exactly the layout shift
		 * this plugin spends its time removing. `rocket_exclude_css` is the
		 * documented filter behind the "Excluded CSS files" field, and it takes
		 * the same substring lines LiteSpeed does.
		 */
		add_filter( 'rocket_exclude_css', array( $this, 'add_excludes' ) );

		add_action( 'init', array( $this, 'disable_in_editor' ), 2 );

		/* Style choices live in these three options; a saved change must reach
		 * the cached HTML and the generated UCSS immediately. */
		/*
		 * Four arguments, not the default one: `purge_on_style_change()` needs
		 * the option name to tell a style change from a security switch, and
		 * WordPress only passes what `add_action()` declares.
		 */
		add_action( 'update_option_hsw_palette', array( $this, 'purge_on_style_change' ), 10, 3 );
		add_action( 'update_option_hsw_account_settings', array( $this, 'purge_on_style_change' ), 10, 3 );
		add_action( 'update_option_hsw_security', array( $this, 'purge_on_style_change' ), 10, 3 );

		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'admin_init', array( $this, 'handle_dismiss' ) );
	}

	/* ---------------------------------------------------------------------
	 * Detection
	 * ------------------------------------------------------------------ */

	/**
	 * Is LiteSpeed Cache running in this request?
	 *
	 * `LSCWP_V` is defined by its main file; the `litespeed_conf` filter is the
	 * version-independent fallback. (`LSCWP_CTRL` is a router query key, not a
	 * constant, so checking it would always be false.)
	 *
	 * @return bool
	 */
	public static function litespeed_active() {
		return defined( 'LSCWP_V' ) || has_filter( 'litespeed_conf' );
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

		/*
		 * The folder the plugin REALLY runs from. A package unzipped from a
		 * GitHub "Source code" archive lands in `hamishop-elementor-widgets-1.11.x/`,
		 * and the fixed slug above is not a substring of that path — the optimizer
		 * then merged our CSS into its combined file and the layout broke.
		 */
		if ( defined( 'HSW_DIR' ) ) {
			$real = basename( rtrim( (string) HSW_DIR, '/\\' ) );
			if ( '' !== $real && 'hamishop-elementor-widgets' !== $real ) {
				$lines[] = $real . '/assets/css';
			}
		}

		return (array) apply_filters( 'hsw_optimizer_exclude', $lines );
	}

	/**
	 * Stable path fragments of HamiShop's own scripts.
	 *
	 * @return string[]
	 */
	public static function js_exclude_lines() {
		$lines = array(
			'hamishop-elementor-widgets/assets/js',
		);

		/* Same reason as exclude_lines(): match the real install folder. */
		if ( defined( 'HSW_DIR' ) ) {
			$real = basename( rtrim( (string) HSW_DIR, '/\\' ) );
			if ( '' !== $real && 'hamishop-elementor-widgets' !== $real ) {
				$lines[] = $real . '/assets/js';
			}
		}

		return (array) apply_filters( 'hsw_optimizer_js_exclude', $lines );
	}

	/**
	 * LiteSpeed filter callback for the JS lists (combine + defer).
	 *
	 * @param array|string $list The list LiteSpeed built so far.
	 * @return array
	 */
	public function add_js_excludes( $list ) {
		return self::merge_list( $list, self::js_exclude_lines() );
	}

	/**
	 * Append our lines to an optimizer list without touching what the shop owner
	 * wrote, and without ever repeating an entry.
	 *
	 * LiteSpeed hands these lists over as arrays; a string (an option written by
	 * an older build, or by another plugin's filter) is split into lines first.
	 *
	 * @param array|string $list  Existing list.
	 * @param string[]     $add   Lines to guarantee.
	 * @return array
	 */
	public static function merge_list( $list, $add ) {
		if ( is_array( $list ) ) {
			$out = array_map( 'trim', $list );
		} else {
			$split = preg_split( '/\R+/', str_replace( "\r\n", "\n", (string) $list ), -1, PREG_SPLIT_NO_EMPTY );
			$out   = is_array( $split ) ? array_map( 'trim', $split ) : array();
		}

		$out = array_values( array_filter( $out ) );

		foreach ( (array) $add as $line ) {
			$line = trim( (string) $line );
			if ( '' !== $line && ! in_array( $line, $out, true ) ) {
				$out[] = $line;
			}
		}

		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Behaviour
	 * ------------------------------------------------------------------ */

	/**
	 * LiteSpeed filter callback: keep our CSS out of the combined file and out
	 * of UCSS rule stripping.
	 *
	 * @param array|string $list The list LiteSpeed built so far.
	 * @return array
	 */
	public function add_excludes( $list ) {
		return self::merge_list( $list, self::exclude_lines() );
	}

	/**
	 * An editor or preview request must never see optimized CSS.
	 *
	 * LiteSpeed already turns itself off for the editor itself; the preview
	 * iframe is a normal front-end render, and that is where the merchant judges
	 * whether a style change worked.
	 */
	public function disable_in_editor() {
		if ( ! is_admin() ) {
			return;
		}

		$builder = isset( $_GET['elementor-preview'] ) || isset( $_GET['fl_builder'] ) || isset( $_GET['et_fb'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $builder && class_exists( '\Elementor\Plugin' ) ) {
			$elementor = \Elementor\Plugin::instance();
			if ( isset( $elementor->editor ) && method_exists( $elementor->editor, 'is_edit_mode' ) && $elementor->editor->is_edit_mode() ) {
				$builder = true;
			}
		}

		if ( $builder ) {
			do_action( 'litespeed_disable_all', 'HamiShop: page builder edit/preview' );
		}
	}

	/**
	 * Purge the caches an optimizer keeps of our HTML/CSS.
	 *
	 * Called after a version bump (which already flushed Elementor's own CSS
	 * cache) and after the style options are saved. Each plugin is asked only
	 * through its own documented hook or function, so an absent optimizer changes
	 * nothing.
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
	public function purge_on_style_change( $old = null, $new = null, $option = '' ) {
		/*
		 * `hsw_security` is hooked here too, but most of its keys never reach the
		 * front end: toggling the REST or XML-RPC switches used to purge the
		 * whole site cache for nothing. Only the keys that change markup or CSS
		 * are worth a purge.
		 */
		if ( 'hsw_security' === $option ) {
			$visual  = array( 'clean_head', 'clean_assets', 'clean_widgets', 'font_swap', 'hide_wp_logo', 'login_style' );
			$changed = false;

			foreach ( $visual as $key ) {
				$before = is_array( $old ) && isset( $old[ $key ] ) ? $old[ $key ] : null;
				$after  = is_array( $new ) && isset( $new[ $key ] ) ? $new[ $key ] : null;
				if ( $before !== $after ) {
					$changed = true;
					break;
				}
			}

			if ( ! $changed ) {
				return;
			}
		}

		self::purge( 'HamiShop: design settings saved' );
	}

	/* ---------------------------------------------------------------------
	 * Admin notice
	 * ------------------------------------------------------------------ */

	/**
	 * Tell the merchant what the optimizer is doing to the shop's CSS.
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

		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'HamiShop: سازگاری با بهینه‌ساز لایت‌اسپید', 'hamishop-elementor-widgets' ) . '</strong></p>';
		echo '<p>' . esc_html__( 'فایل‌های CSS و JS این افزونه از ادغام، از حذف قاعده‌های UCSS و از defer خودکار خارج می‌شوند؛ استایل‌های inline (پالت، گاردِ چیدمان و شیت‌ها) هم با data-no-optimize دست‌نخورده می‌مانند (تنظیمات شما تغییر نمی‌کند). برای اینکه ساختار صفحه و فونت وزیرمتن همیشه درست بماند، این موارد را هم در نظر بگیرید:', 'hamishop-elementor-widgets' ) . '</p>';
		echo '<ul style="margin:0 0 8px 14px">';
		echo '<li><code>elementor/css/post-</code> ' . esc_html__( 'و', 'hamishop-elementor-widgets' ) . ' <code>elementor/assets/css</code> ' . esc_html__( 'را هم در «CSS Excludes» بگذارید؛ CSS المنتور با هر ذخیره عوض می‌شود و ادغام آن باعث تولید مداوم فایل ترکیبی و بی‌اعتبارشدن کش می‌شود.', 'hamishop-elementor-widgets' ) . '</li>';
		if ( '' !== $font && 'swap' !== $font ) {
			echo '<li><strong>Font Display</strong> = <code>' . esc_html( $font ) . '</code> — ' . esc_html__( 'این مقدار روی همهٔ @font-faceها نوشته می‌شود؛ اگر optional یا block باشد فونت گاهی روی Tahoma می‌ماند. آن را swap یا خالی بگذارید.', 'hamishop-elementor-widgets' ) . '</li>';
		}
		if ( '' !== $ucss && '1' === $ucss ) {
			echo '<li>' . esc_html__( 'UCSS/Critical CSS قاعده‌های سبد کشویی، تب‌ها و آکاردئون‌ها را «استفاده‌نشده» می‌بیند و حذفشان می‌کند؛ اگر استایل این‌ها پرید، UCSS را برای این صفحه‌ها خاموش کن.', 'hamishop-elementor-widgets' ) . '</li>';
		}
		echo '</ul>';
		echo '<p>' . esc_html__( 'وضعیت فعلی:', 'hamishop-elementor-widgets' ) . ' CSS Combine = <code>' . esc_html( '' === $combine ? '0' : $combine ) . '</code>، UCSS = <code>' . esc_html( '' === $ucss ? '0' : $ucss ) . '</code></p>';
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
