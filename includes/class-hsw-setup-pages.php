<?php
/**
 * Checkout-route setup: the two pages the four-step flow needs.
 *
 * The pay step and the completion step are ordinary pages, but nothing used to
 * create them. A store that installed the plugin and placed the «صفحه کامل
 * تسویه» widget got a «ادامه و ورود به مرحله پرداخت» button with nowhere to go:
 * the JS fell back to WooCommerce's own checkout form, which that widget does
 * not render, and the only trace was a console warning. The route also accepts
 * a slug match, so a page named /payment/ worked by accident — until the owner
 * named it /pay/ and it silently stopped.
 *
 * This class does three things and nothing else:
 *
 *  1. reports the real state of both pages on the widget screen;
 *  2. creates only what is missing, and only when the admin presses the button
 *     (no silent page creation on activation);
 *  3. keeps both pages out of search results, with the same `noindex, nofollow`
 *     WooCommerce uses for its own cart and checkout pages.
 *
 * Pages are created with a shortcode, not Elementor data: a shortcode page
 * renders in any theme, survives an Elementor re-save, and needs no JSON blob
 * that a future Elementor version could reject.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

final class HSW_Setup_Pages {

	/** admin-post action that creates / links the pages. */
	const ACTION = 'hsw_setup_pages';

	/** Nonce action for that request. */
	const NONCE = 'hsw_setup_pages';

	/** Post meta marking a page this plugin created. */
	const META = HSW_Complete_Route::META;

	public function __construct() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_shortcode( 'hamishop_payment', array( __CLASS__, 'render_payment' ) );
		add_shortcode( 'hamishop_pay', array( __CLASS__, 'render_payment' ) );
		add_shortcode( 'hamishop_complete', array( __CLASS__, 'render_complete' ) );
	}

	/* =========================================================================
	 *  DEFINITIONS
	 * ====================================================================== */

	/**
	 * The two route pages.
	 *
	 * `slug` is what WooCommerce and the plugin's own resolver look for;
	 * `fallback` is used when an unrelated page already owns that slug, so this
	 * never hijacks a page it did not create.
	 *
	 * @return array<string,array>
	 */
	public static function pages() {
		return array(
			'payment'  => array(
				'option'    => HSW_Complete_Route::PAY_OPTION,
				'slug'      => 'payment',
				'fallback'  => 'hamishop-payment',
				'label'     => __( 'صفحهٔ پرداخت', 'hamishop-elementor-widgets' ),
				'title'     => __( 'پرداخت سفارش', 'hamishop-elementor-widgets' ),
				'widget'    => 'HSW_Widget_Pay_Page',
				'file'      => 'widgets/class-hsw-widget-pay-page.php',
				'requires'  => array(
					'includes/class-hsw-widget-base.php',
					'includes/class-hsw-cart-base.php',
					'includes/class-hsw-checkout-base.php',
					'includes/class-hsw-pay-base.php',
				),
				'shortcode' => '[hamishop_payment]',
			),
			'complete' => array(
				'option'    => HSW_Complete_Route::OPTION,
				'slug'      => 'complete',
				'fallback'  => 'hamishop-complete',
				'label'     => __( 'صفحهٔ تکمیل خرید', 'hamishop-elementor-widgets' ),
				'title'     => __( 'تکمیل خرید', 'hamishop-elementor-widgets' ),
				'widget'    => 'HSW_Widget_Complete_Page',
				'file'      => 'widgets/class-hsw-widget-complete-page.php',
				'requires'  => array(
					'includes/class-hsw-widget-base.php',
					'includes/class-hsw-cart-base.php',
					'includes/class-hsw-checkout-base.php',
					'includes/class-hsw-pay-base.php',
					'includes/class-hsw-order-auth.php',
					'includes/class-hsw-complete-base.php',
				),
				'shortcode' => '[hamishop_complete]',
			),
		);
	}

	/**
	 * State of every route page.
	 *
	 * wired   — the option points at a published page that carries our widget.
	 * unwired — such a page exists (found by slug) but the option is empty or
	 *           points somewhere else; linking it is one update_option call.
	 * missing — nothing usable exists; the page has to be created.
	 *
	 * @return array<string,array>
	 */
	public static function status() {
		$out = array();

		foreach ( self::pages() as $key => $page ) {
			$out[ $key ] = self::status_one( $key, $page );
		}

		return $out;
	}

	/**
	 * State of one route page.
	 *
	 * @param string $key  'payment' | 'complete'.
	 * @param array  $page Page definition.
	 * @return array{state:string,id:int,slug_taken:bool,title:string,edit_url:string}
	 */
	protected static function status_one( $key, $page ) {
		$option_id   = (int) get_option( $page['option'], 0 );
		$option_live = ( $option_id > 0 && 'publish' === get_post_status( $option_id ) );

		if ( $option_live && HSW_Complete_Route::page_is_ours( $option_id, $key ) ) {
			return self::row( 'wired', $option_id, $page );
		}

		/*
		 * A page at the canonical slug that carries the widget, even when the
		 * option points somewhere else. Checked before the option so a wrong
		 * choice in WooCommerce does not hide a correct page that exists.
		 */
		$slug_page = get_page_by_path( $page['slug'], OBJECT, 'page' );
		if ( $slug_page instanceof WP_Post && 'publish' === $slug_page->post_status && HSW_Complete_Route::page_is_ours( $slug_page->ID, $key ) ) {
			return self::row( 'unwired', (int) $slug_page->ID, $page );
		}

		/* A page this plugin created earlier under a different slug. */
		$marked = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery -- one row, admin screen only.
				'meta_value'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery -- see above.
			)
		);
		/*
		 * A marker alone is not enough: the page may have been emptied since it
		 * was created. Linking it would point the route at a page that no longer
		 * renders the step.
		 */
		if ( $marked && HSW_Complete_Route::page_is_ours( (int) $marked[0], $key ) ) {
			return self::row( 'unwired', (int) $marked[0], $page );
		}

		/*
		 * A hand-built page carrying the shortcode under another slug. Found by
		 * a content search — one or two queries on an admin screen, and only
		 * when everything cheaper has already failed.
		 */
		$shortcodes = ( 'payment' === $key )
			? array( 'hamishop_payment', 'hamishop_pay' )
			: array( 'hamishop_complete' );

		foreach ( $shortcodes as $shortcode ) {
			$found = get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					's'              => '[' . $shortcode,
				)
			);
			if ( $found && HSW_Complete_Route::page_is_ours( (int) $found[0], $key ) ) {
				return self::row( 'unwired', (int) $found[0], $page );
			}
		}

		/*
		 * Something published is selected in the setting but it is not ours.
		 * This is a real conflict, not "missing": the router will happily send
		 * customers there, and the page does not render the step. It must never
		 * be marked noindex or otherwise touched — the owner picked it for a
		 * reason we cannot see.
		 */
		if ( $option_live ) {
			return self::row( 'foreign', $option_id, $page );
		}

		$row               = self::row( 'missing', 0, $page );
		$row['slug_taken'] = (bool) $slug_page;

		return $row;
	}

	/**
	 * Build one status row.
	 *
	 * @param string $state State.
	 * @param int    $id    Page id.
	 * @param array  $page  Page definition.
	 * @return array
	 */
	protected static function row( $state, $id, $page ) {
		return array(
			'state'      => $state,
			'id'         => (int) $id,
			'slug_taken' => false,
			'title'      => $id > 0 ? (string) get_the_title( $id ) : '',
			'edit_url'   => $id > 0 ? (string) get_edit_post_link( $id, 'raw' ) : '',
		);
	}

	/* =========================================================================
	 *  CREATE / LINK
	 * ====================================================================== */

	/**
	 * Handle the «ساخت صفحه‌های لازم» button.
	 *
	 * Creates only what is missing, links a page that already exists, and never
	 * touches a page that is not ours. Re-running it is harmless.
	 */
	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'برای انجام این کار دسترسی مدیریت لازم است.', 'hamishop-elementor-widgets' ), 403 );
		}

		check_admin_referer( self::NONCE );

		$result = self::run();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => HSW_Widget_Manager::PAGE,
					'hsw-setup' => 'done',
					'created'   => $result['created'],
					'linked'    => $result['linked'],
					'failed'    => $result['failed'],
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Create / link whatever is missing.
	 *
	 * Separated from the request handler so the whole decision can be tested
	 * without a nonce, a redirect or an `exit`.
	 *
	 * @return array{created:int,linked:int,failed:int}
	 */
	public static function run() {
		$created = 0;
		$linked  = 0;
		$failed  = 0;

		foreach ( self::status() as $key => $status ) {
			$page = self::pages()[ $key ];

			if ( 'wired' === $status['state'] ) {
				continue;
			}

			if ( 'unwired' === $status['state'] && $status['id'] > 0 ) {
				update_option( $page['option'], (int) $status['id'], false );
				self::mark_noindex( (int) $status['id'], $key );
				$linked++;
				continue;
			}

			$id = self::create( $key, $page );
			if ( $id > 0 ) {
				$created++;
			} else {
				$failed++;
			}
		}

		return array(
			'created' => $created,
			'linked'  => $linked,
			'failed'  => $failed,
		);
	}

	/**
	 * Create one page, wire the option and mark it noindex.
	 *
	 * @param string $key  'payment' | 'complete'.
	 * @param array  $page Page definition.
	 * @return int Page id, 0 on failure.
	 */
	protected static function create( $key, $page ) {
		$slug = (string) $page['slug'];

		/* Never take over a page this plugin did not create. */
		if ( get_page_by_path( $slug, OBJECT, 'page' ) ) {
			$slug = (string) $page['fallback'];
		}
		if ( get_page_by_path( $slug, OBJECT, 'page' ) ) {
			$slug .= '-' . wp_rand( 100, 999 );
		}

		$id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => (string) $page['title'],
				'post_name'      => $slug,
				'post_content'   => (string) $page['shortcode'],
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'meta_input'     => array( self::META => $key ),
			),
			true
		);

		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}

		update_option( $page['option'], (int) $id, false );
		self::mark_noindex( (int) $id, $key );

		return (int) $id;
	}

	/**
	 * Keep a route page out of search results.
	 *
	 * `wp_robots` covers WordPress itself. The three post-meta keys cover the
	 * SEO plugins that replace that output with their own: Yoast, Rank Math and
	 * All in One SEO. Writing meta for a plugin that is not installed is a
	 * harmless row nobody reads.
	 *
	 * @param int    $id  Page id.
	 * @param string $key Route key.
	 */
	protected static function mark_noindex( $id, $key ) {
		update_post_meta( $id, self::META, $key );
		update_post_meta( $id, '_yoast_wpseo_meta-robots-noindex', '1' );
		update_post_meta( $id, 'rank_math_robots', array( 'noindex' ) );
		update_post_meta( $id, '_aioseo_robots_noindex', '1' );
	}

	/* =========================================================================
	 *  FRONT END
	 * ====================================================================== */

	/**
	 * `noindex, nofollow` on both route pages.
	 *
	 * Same directive WooCommerce prints for its own cart, checkout and account
	 * pages — a payment URL with an order key in it must never be indexed.
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public static function robots( $robots ) {
		if ( '' === self::current_route() ) {
			return $robots;
		}

		unset( $robots['index'], $robots['max-image-preview'] );
		$robots['noindex']  = true;
		$robots['nofollow'] = true;

		return $robots;
	}

	/**
	 * Which route page is being rendered, '' when none.
	 *
	 * Detection order: the wired option, the marker meta, then the shortcode in
	 * the page content. The last one matters before the page is wired, and for
	 * a page the owner built by hand.
	 *
	 * @return string 'payment' | 'complete' | ''
	 */
	public static function current_route() {
		if ( ! is_singular() ) {
			return '';
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		$id = (int) $post->ID;

		foreach ( self::pages() as $key => $page ) {
			/*
			 * Being selected in the setting is not enough. A page that does not
			 * render the step is not this route, and treating it as one marked
			 * an unrelated page noindex through wp_robots while its own SEO meta
			 * stayed empty — the de-indexing was invisible to whoever looked at
			 * the page in Yoast.
			 */
			if ( (int) get_option( $page['option'], 0 ) === $id && HSW_Complete_Route::page_is_ours( $id, $key ) ) {
				return $key;
			}
			if ( (string) get_post_meta( $id, self::META, true ) === $key && HSW_Complete_Route::page_is_ours( $id, $key ) ) {
				return $key;
			}
		}

		$content = (string) $post->post_content;

		if ( has_shortcode( $content, 'hamishop_payment' ) || has_shortcode( $content, 'hamishop_pay' ) ) {
			return 'payment';
		}
		if ( has_shortcode( $content, 'hamishop_complete' ) ) {
			return 'complete';
		}

		return '';
	}

	/**
	 * Enqueue the widget's own styles and scripts on a shortcode page.
	 *
	 * Elementor loads a widget's `get_style_depends()` for widgets it renders
	 * itself. A shortcode is outside that pipeline, so without this the page
	 * renders unstyled. Reading the list from the widget keeps the two in sync.
	 */
	public static function enqueue() {
		$key = self::current_route();
		if ( '' === $key ) {
			return;
		}

		$page = self::pages()[ $key ];
		if ( ! self::load_widget( $page ) ) {
			return;
		}

		$class = $page['widget'];

		try {
			$widget = new $class(
				array(
					'id'         => 'hsw-route-' . $key,
					'elType'     => 'widget',
					'widgetType' => 'hsw-' . $key,
					'settings'   => array(),
				),
				array()
			);
		} catch ( \Throwable $e ) {
			return;
		}

		foreach ( (array) $widget->get_style_depends() as $handle ) {
			wp_enqueue_style( $handle );
		}
		foreach ( (array) $widget->get_script_depends() as $handle ) {
			wp_enqueue_script( $handle );
		}
	}

	/**
	 * Payment-step shortcode.
	 *
	 * @return string
	 */
	public static function render_payment() {
		return self::render( 'payment' );
	}

	/**
	 * Completion-step shortcode.
	 *
	 * @return string
	 */
	public static function render_complete() {
		return self::render( 'complete' );
	}

	/**
	 * Render one route page through the existing composite renderer.
	 *
	 * @param string $key Route key.
	 * @return string
	 */
	protected static function render( $key ) {
		$pages = self::pages();
		if ( ! isset( $pages[ $key ] ) ) {
			return '';
		}

		$page = $pages[ $key ];
		if ( ! self::load_widget( $page ) ) {
			return '';
		}

		return HSW_Composite::part( $page['widget'] );
	}

	/**
	 * Make sure the page widget class is loaded.
	 *
	 * Widget files are required inside `register_widgets()`, which only runs
	 * once Elementor asks for its widget list. A shortcode can be rendered
	 * before that, so the file is loaded here when needed.
	 *
	 * @param array $page Page definition.
	 * @return bool
	 */
	protected static function load_widget( $page ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return false;
		}

		/*
		 * Every base class in the chain is required inside register_widgets(),
		 * which only runs once Elementor asks for its widget list. A shortcode
		 * can render before that, so the chain is loaded here. require_once
		 * makes the whole list a no-op when Elementor already did it.
		 */
		foreach ( (array) $page['requires'] as $relative ) {
			$file = HSW_DIR . (string) $relative;
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}

		$class = (string) $page['widget'];

		if ( ! class_exists( $class ) ) {
			$file = HSW_DIR . (string) $page['file'];
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}

		return class_exists( $class ) && class_exists( 'HSW_Composite' );
	}
}
