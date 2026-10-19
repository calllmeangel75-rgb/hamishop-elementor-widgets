<?php
/**
 * Completion-page routing + the native WooCommerce settings.
 *
 * «تکمیل خرید» is a real, designable Elementor page. Every order-received
 * redirect — WC core, every gateway's return URL, pay-links and the Store API
 * — flows through `woocommerce_get_checkout_order_received_url`, so filtering
 * that single URL source keeps the whole flow compatible with the shop's own
 * WooCommerce configuration (any permalink structure, any endpoint slug).
 * Requests that still hit /checkout/order-received/… are redirected too.
 *
 * The pages themselves are picked inside WooCommerce → Settings → Checkout
 * (native `single_select_page` fields):
 *  - `hsw_pay_page_id`      — payment step (مسیر: دکمهٔ «ادامه و ورود به مرحله پرداخت»).
 *  - `hsw_complete_page_id` — completion page (after payment).
 * Empty = untouched WooCommerce behavior (checkout URL / pay-page fallback card).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

final class HSW_Complete_Route {

    const OPTION     = 'hsw_complete_page_id';
    const PAY_OPTION = 'hsw_pay_page_id';

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

    /**
     * The configured payment-step page id (the page the shipping/checkout
     * summary CTA leads to), or 0 (disabled / guard tripped).
     */
    public static function pay_page_id() {
        $id = (int) get_option( self::PAY_OPTION, 0 );
        if ( $id <= 0 || 'publish' !== get_post_status( $id ) ) {
            return 0;
        }
        /* Never send the customer into the cart, the checkout page itself or
           the completion page. */
        if ( function_exists( 'wc_get_page_id' ) ) {
            $blocked = array( (int) wc_get_page_id( 'cart' ), (int) wc_get_page_id( 'checkout' ) );
            if ( self::page_id() ) {
                $blocked[] = self::page_id();
            }
            if ( in_array( $id, $blocked, true ) ) {
                return 0;
            }
        }
        return $id;
    }

    /**
     * Payment-step URL, '' when unconfigured (callers fall back to the
     * WooCommerce checkout URL = old behavior).
     */
    public static function pay_step_url() {
        $id = self::pay_page_id();
        if ( ! $id ) {
            return '';
        }
        $url = get_permalink( $id );
        return $url ? (string) $url : '';
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
     * Add «صفحهٔ پرداخت» + «صفحهٔ تکمیل خرید» after the checkout page option —
     * the settings live in WooCommerce's own configuration, saved by WC itself.
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
                'title' => __( 'مسیر خرید (حامی)', 'hamishop-elementor-widgets' ),
                'type'  => 'title',
                'id'    => 'hsw_complete_options',
                'desc'  => __( 'مسیر خرید با ویجت‌های جامع حامی: سبد خرید → تسویه حساب (اطلاعات ارسال) → پرداخت → تکمیل خرید. برای هر مرحله یک صفحه بسازید و ویجت جامع همان مرحله را داخل آن قرار دهید.', 'hamishop-elementor-widgets' ),
            ),
            array(
                'title'    => __( 'صفحهٔ پرداخت (مرحلهٔ ۳)', 'hamishop-elementor-widgets' ),
                'desc'     => __( 'صفحه‌ای که ویجت «صفحه کامل پرداخت — حامی» دارد. دکمهٔ «ادامه و ورود به مرحله پرداخت» در صفحهٔ تسویه به این صفحه می‌رود. خالی = همان صفحهٔ تسویه ووکامرس.', 'hamishop-elementor-widgets' ),
                'desc_tip' => true,
                'id'       => self::PAY_OPTION,
                'type'     => 'single_select_page',
                'default'  => '',
                'class'    => 'wc-enhanced-select-nostd',
                'css'      => 'min-width:300px;',
            ),
            array(
                'title'    => __( 'صفحهٔ تکمیل خرید (مرحلهٔ ۴)', 'hamishop-elementor-widgets' ),
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
