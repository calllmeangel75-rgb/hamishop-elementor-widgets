<?php
/**
 * Asset registration — everything is REGISTERED here and only ENQUEUED
 * when an actual widget is on the page (via get_style_depends()).
 * Zero bytes are loaded on pages without HamiShop widgets.
 *
 * @package HamiShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Frontend + editor assets.
 */
final class HSW_Assets {

	/**
	 * Bootstrap.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_frontend' ], 5 );
		add_action( 'elementor/editor/after_enqueue_styles', [ __CLASS__, 'register_editor_styles' ] );
		add_action( 'elementor/editor/after_enqueue_scripts', [ __CLASS__, 'register_editor_scripts' ] );
		add_action( 'elementor/preview/enqueue_styles', [ __CLASS__, 'register_editor_styles' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'register_settings_styles' ] );
	}

	/**
	 * Register (not enqueue) frontend files.
	 *
	 * @return void
	 */
	public static function register_frontend() {
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		wp_register_style(
			'hsw-base',
			HSW_PLUGIN_URL . 'assets/css/hsw-base.css',
			[],
			HSW_VERSION
		);

		wp_register_style(
			'hsw-loop',
			HSW_PLUGIN_URL . 'assets/css/hsw-loop.css',
			[ 'hsw-base' ],
			HSW_VERSION
		);

		wp_register_style(
			'hsw-archive',
			HSW_PLUGIN_URL . 'assets/css/hsw-archive.css',
			[ 'hsw-base' ],
			HSW_VERSION
		);

		wp_register_style(
			'hsw-single',
			HSW_PLUGIN_URL . 'assets/css/hsw-single.css',
			[ 'hsw-base' ],
			HSW_VERSION
		);

		wp_register_style(
			'hsw-cart',
			HSW_PLUGIN_URL . 'assets/css/hsw-cart.css',
			[ 'hsw-base' ],
			HSW_VERSION
		);

		wp_register_style(
			'hsw-checkout',
			HSW_PLUGIN_URL . 'assets/css/hsw-checkout.css',
			[ 'hsw-base' ],
			HSW_VERSION
		);

		wp_register_script(
			'hsw-frontend',
			HSW_PLUGIN_URL . 'assets/js/hsw-frontend' . $suffix . '.js',
			[],
			HSW_VERSION,
			true
		);

		// Defer when the browser supports it (WP 6.3+).
		if ( function_exists( 'wp_script_add_data' ) ) {
			wp_script_add_data( 'hsw-frontend', 'strategy', 'defer' );
		}

		wp_localize_script(
			'hsw-frontend',
			'hswData',
			[
				'restUrl'  => esc_url_raw( rest_url( 'hamishop/v1/load-more' ) ),
				'loading'  => __( 'Loading…', 'hamishop-elementor-widgets' ),
				'noMore'   => __( 'No more products', 'hamishop-elementor-widgets' ),
			]
		);
	}

	/**
	 * Editor + preview styles (tiny, structural only).
	 *
	 * @return void
	 */
	public static function register_editor_styles() {
		wp_enqueue_style(
			'hsw-editor',
			HSW_PLUGIN_URL . 'assets/css/hsw-editor.css',
			[],
			HSW_VERSION
		);
	}

	/**
	 * Editor scripts: the product-chooser control binding.
	 *
	 * @return void
	 */
	public static function register_editor_scripts() {
		wp_enqueue_script(
			'hsw-editor',
			HSW_PLUGIN_URL . 'assets/js/hsw-editor.js',
			[ 'jquery' ],
			HSW_VERSION,
			true
		);

		wp_localize_script(
			'hsw-editor',
			'hswEditor',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'hsw-editor' ),
			]
		);
	}

	/**
	 * Styles for the WP admin settings page.
	 *
	 * @return void
	 */
	public static function register_settings_styles() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'settings_page_hamishop' ) ) {
			return;
		}
		wp_enqueue_style(
			'hsw-settings',
			HSW_PLUGIN_URL . 'assets/css/hsw-editor.css',
			[],
			HSW_VERSION
		);
	}

	/**
	 * Make sure a WooCommerce frontend script handle exists, registering a
	 * minimal fallback from the WooCommerce plugin folder when the theme
	 * builder context skipped it.
	 *
	 * @param string $handle Script handle.
	 * @return void
	 */
	public static function ensure_wc_script( $handle ) {
		if ( wp_script_is( $handle, 'enqueued' ) ) {
			return;
		}

		if ( ! wp_script_is( $handle, 'registered' ) && defined( 'WC_PLUGIN_FILE' ) ) {
			$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
			$map    = [
				'wc-single-product'       => [
					'src'  => 'assets/js/frontend/single-product',
					'deps' => [ 'jquery' ],
				],
				'wc-add-to-cart'          => [
					'src'  => 'assets/js/frontend/add-to-cart',
					'deps' => [ 'jquery' ],
				],
				'wc-add-to-cart-variation'=> [
					'src'  => 'assets/js/frontend/add-to-cart-variation',
					'deps' => [ 'jquery', 'wp-util' ],
				],
				'wc-cart-fragments'       => [
					'src'  => 'assets/js/frontend/cart-fragments',
					'deps' => [ 'jquery', 'js-cookie' ],
				],
				'wc-checkout'             => [
					'src'  => 'assets/js/frontend/checkout',
					'deps' => [ 'jquery', 'jquery-ui-sortable', 'select2', 'selectWoo' ],
				],
				'photoswipe'              => [
					'src'  => 'assets/js/photoswipe/photoswipe',
					'deps' => [],
				],
				'photoswipe-ui-default'   => [
					'src'  => 'assets/js/photoswipe/photoswipe-ui-default',
					'deps' => [ 'photoswipe' ],
				],
			];

			if ( isset( $map[ $handle ] ) ) {
				wp_register_script(
					$handle,
					plugins_url( $map[ $handle ]['src'] . $suffix . '.js', WC_PLUGIN_FILE ),
					$map[ $handle ]['deps'],
					defined( 'WC_VERSION' ) ? WC_VERSION : HSW_VERSION,
					true
				);
			}
		}

		if ( wp_script_is( $handle, 'registered' ) ) {
			wp_enqueue_script( $handle );
		}
	}
}
