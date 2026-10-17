<?php
/**
 * Cart form actions WooCommerce does not provide natively.
 * Currently: the design's «خالی کردن سبد» button (POST hsw_empty_cart).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Cart_Actions {

        public function __construct() {
                add_action( 'wp_loaded', array( $this, 'handle_empty_cart' ), 30 );
        }

        /**
         * Empty the cart from the widget button (nonce-verified POST).
         */
        public function handle_empty_cart() {
                // phpcs:disable WordPress.Security.NonceVerification.Missing -- verified below.
                if ( empty( $_POST['hsw_empty_cart'] ) ) {
                        return;
                }
                $nonce = isset( $_POST['hsw_empty_cart_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hsw_empty_cart_nonce'] ) ) : '';
                // phpcs:enable
                if ( ! wp_verify_nonce( $nonce, 'hsw-empty-cart' ) ) {
                        return;
                }
                if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
                        return;
                }

                WC()->cart->empty_cart();
                wc_add_notice( apply_filters( 'hsw_cart_emptied_message', __( 'سبد خرید خالی شد.', 'hamishop-elementor-widgets' ) ), 'notice' );

                wp_safe_redirect( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' ) );
                exit;
        }
}
