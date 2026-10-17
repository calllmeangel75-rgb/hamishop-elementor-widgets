<?php
/**
 * Asset registration. Styles/scripts are only printed when a widget that
 * depends on them is present on the page (Elementor native behavior).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Assets {

        public function __construct() {
                add_action( 'wp_enqueue_scripts', array( $this, 'register' ) );
                add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'editor_style' ) );
        }

        /**
         * Register (not enqueue) — Elementor enqueues widget dependencies automatically.
         */
        public function register() {
                wp_register_style(
                        'hsw-loop',
                        HSW_URL . 'assets/css/hsw-loop.css',
                        array(),
                        HSW_VERSION
                );

                wp_register_script(
                        'hsw-loop',
                        HSW_URL . 'assets/js/hsw-loop.js',
                        array(),
                        HSW_VERSION,
                        true
                );

                wp_localize_script(
                        'hsw-loop',
                        'hswData',
                        array(
                                'wcAjax'    => class_exists( 'WooCommerce' ) ? WC_AJAX::get_endpoint( 'add_to_cart' ) : '/?wc-ajax=add_to_cart',
                                'addedText' => __( 'به سبد خرید اضافه شد ✓', 'hamishop-elementor-widgets' ),
                        )
                );
        }

        public function editor_style() {
                wp_add_inline_style(
                        'wp-admin',
                        '.elementor-widget-hsw-wonder-deals .elementor-widget-container:before{content:"";display:block;height:0}'
                );
        }
}
