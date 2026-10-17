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
                add_filter( 'elementor/fonts/groups', array( $this, 'font_groups' ) );
                add_filter( 'elementor/fonts/additional_fonts', array( $this, 'additional_fonts' ) );
        }

        /**
         * Register (not enqueue) — Elementor enqueues widget dependencies automatically.
         */
        public function register() {
                wp_register_style(
                        'hsw-fonts',
                        HSW_URL . 'assets/css/hsw-fonts.css',
                        array(),
                        HSW_VERSION
                );

                wp_register_style(
                        'hsw-loop',
                        HSW_URL . 'assets/css/hsw-loop.css',
                        array( 'hsw-fonts' ),
                        HSW_VERSION
                );

                wp_register_script(
                        'hsw-loop',
                        HSW_URL . 'assets/js/hsw-loop.js',
                        array(),
                        HSW_VERSION,
                        true
                );

                wp_register_style(
                        'hsw-product',
                        HSW_URL . 'assets/css/hsw-product.css',
                        array( 'hsw-fonts' ),
                        HSW_VERSION
                );

                wp_register_script(
                        'hsw-product',
                        HSW_URL . 'assets/js/hsw-product.js',
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

                wp_localize_script(
                        'hsw-product',
                        'hswProductData',
                        array(
                                'wcAjax'      => class_exists( 'WooCommerce' ) ? WC_AJAX::get_endpoint( 'add_to_cart' ) : '/?wc-ajax=add_to_cart',
                                'cartUrl'     => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
                                'checkoutUrl' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
                                'faDigits'    => true,
                        )
                );
        }

        /**
         * Editor placeholder tweak.
         */
        public function editor_style() {
                wp_add_inline_style(
                        'wp-admin',
                        '.elementor-widget-hsw-wonder-deals .elementor-widget-container:before{content:"";display:block;height:0}'
                );
        }

        /**
         * Font group shown in Elementor typography controls.
         */
        public function font_groups( $groups ) {
                $groups['hamishop-fonts'] = __( 'HamiShop (لوکال)', 'hamishop-elementor-widgets' );
                return $groups;
        }

        /**
         * Locally-bundled fonts exposed to every Elementor typography control.
         */
        public function additional_fonts( $fonts ) {
                $fonts['Vazirmatn HSW'] = 'hamishop-fonts';
                return $fonts;
        }
}
