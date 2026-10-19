<?php
/**
 * Completion-page routing + the native WooCommerce setting.
 *
 * «تکمیل خرید» is a real, designable Elementor page. Every order-received
 * redirect — WC core, every gateway's return URL, pay-links and the Store API
 * — flows through `woocommerce_get_checkout_order_received_url`, so filtering
 * that single URL source keeps the whole flow compatible with the shop's own
 * WooCommerce configuration (any permalink structure, any endpoint slug).
 * Requests that still hit /checkout/order-received/… are redirected too.
 *
 * The page itself is picked inside WooCommerce → Settings → Checkout
 * (native `single_select_page` field), stored as `hsw_complete_page_id`.
 * Empty = untouched WooCommerce behavior (the pay-page fallback card).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

final class HSW_Complete_Route {

    const OPTION = 'hsw_complete_page_id';

    public function __construct() {
        add_filter( 'woocommerce_get_settings_checkout', array( $this, 'settings_field' ), 20, 2 );
        add_filter( 'woocommerce_get_checkout_order_received_url', array( $this, 'route_url' ), 20, 2 );
        add_action( 'template_redirect', array( $this, 'route_request' ) );
    }

    /* =========================================================================
     *  PAGE RESOLUTION
     * ====================================================================== */

    /**
     * The configured completion page id, or 0 (disabled / guard tripped).
     */
    public static function page_id() {
        $id = (int) get_option( self::OPTION, 0 );
        if ( $id <= 0 || 'publish' !== get_post_status( $id ) ) {
            return 0;
        }
        /* Never route the customer back into the cart / checkout pages. */
        if ( function_exists( 'wc_get_page_id' ) ) {
            $blocked = array( (int) wc_get_page_id( 'cart' ), (int) wc_get_page_id( 'checkout' ) );
            if ( in_array( $id, $blocked, true ) ) {
                return 0;
            }
        }
        return $id;
    }

    /**
     * Completion-page URL for an order (order id + key), '' when unusable.
     *
     * @param WC_Order|mixed $order Order.
     * @return string
     */
    public static function url_for( $order ) {
        if ( ! $order instanceof WC_Order ) {
            return '';
        }
        $page_id = self::page_id();
        if ( ! $page_id ) {
            return '';
        }
        $url = get_permalink( $page_id );
        if ( ! $url ) {
            return '';
        }
        return add_query_arg(
            array(
                'order' => $order->get_id(),
                'key'   => $order->get_order_key(),
            ),
            $url
        );
    }

    /* =========================================================================
     *  ROUTING
     * ====================================================================== */

    /**
     * Route every "order received" URL (gateway returns, WC core,
     * pay-links, Store API) to the configured completion page.
     *
     * @param string $url   WooCommerce's own order-received URL.
     * @param mixed  $order Order behind the URL.
     * @return string
     */
    public function route_url( $url, $order ) {
        if ( is_admin() ) {
            return $url; // keep the canonical WC URL in wp-admin.
        }
        $custom = self::url_for( $order );
        return '' !== $custom ? $custom : $url;
    }

    /**
     * Requests that still hit WooCommerce's own order-received endpoint
     * (bookmarks, already-issued gateway return URLs) are redirected too.
     */
    public function route_request() {
        if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }
        if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-received' ) || ! function_exists( 'wc_get_order' ) ) {
            return;
        }

        $order_id = absint( get_query_var( 'order-received' ) );
        $order    = $order_id ? wc_get_order( $order_id ) : false;
        if ( ! $order instanceof WC_Order || ! $this->may_view( $order ) ) {
            return; // let WooCommerce handle invalid / unauthorized views itself.
        }

        $url = self::url_for( $order );
        if ( '' === $url ) {
            return;
        }

        nocache_headers();
        wp_safe_redirect( $url, 302 );
        exit;
    }

    /**
     * WooCommerce's own access rules: a valid order key (guests included)
     * or the logged-in owner.
     *
     * @param WC_Order $order Order.
     * @return bool
     */
    private function may_view( $order ) {
        $key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- order key is the auth.
        if ( '' !== $key ) {
            return $order->key_is_valid( $key );
        }
        return is_user_logged_in() && (int) $order->get_customer_id() === get_current_user_id();
    }

    /* =========================================================================
     *  WOOCOMMERCE SETTING (Settings → Checkout)
     * ====================================================================== */

    /**
     * Add «صفحهٔ تکمیل خرید» after the checkout page option — the setting
     * lives in WooCommerce's own configuration, saved by WC itself.
     *
     * @param array  $settings        Settings rows.
     * @param string $current_section Current settings section.
     * @return array
     */
    public function settings_field( $settings, $current_section = '' ) {
        if ( '' !== (string) $current_section || ! is_array( $settings ) ) {
            return $settings;
        }

        $custom = array(
            array(
                'title' => __( 'تکمیل خرید (حامی)', 'hamishop-elementor-widgets' ),
                'type'  => 'title',
                'id'    => 'hsw_complete_options',
                'desc'  => __( 'صفحه‌ای که مشتری بعد از پرداخت می‌بیند. یک صفحه بسازید و ویجت «صفحه کامل تکمیل خرید — حامی» را در آن قرار دهید.', 'hamishop-elementor-widgets' ),
            ),
            array(
                'title'    => __( 'صفحهٔ تکمیل خرید', 'hamishop-elementor-widgets' ),
                'desc'     => __( 'بعد از پرداخت (و بازگشت از درگاه)، مشتری به این صفحه هدایت می‌شود و شماره و جزئیات سفارش را می‌بیند. خالی = رفتار پیش‌فرض ووکامرس.', 'hamishop-elementor-widgets' ),
                'desc_tip' => true,
                'id'       => self::OPTION,
                'type'     => 'single_select_page',
                'default'  => '',
                'class'    => 'wc-enhanced-select-nostd',
                'css'      => 'min-width:300px;',
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'hsw_complete_options',
            ),
        );

        return array_merge( $settings, $custom );
    }
}
