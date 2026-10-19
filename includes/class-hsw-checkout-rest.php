<?php
/**
 * Checkout REST routes:
 *  - POST /hsw/v1/checkout/shipping : choose a shipping method (session-level,
 *    exactly like WooCommerce's own update_order_review) → returns the fresh
 *    formatted totals so the summary/bottom-bar widgets can patch themselves.
 *  - POST /hsw/v1/checkout/note     : store the design's order-note textarea +
 *    legal-invoice checkbox into the WC session before continuing to payment.
 *  - POST /hsw/v1/checkout/payment  : persist chosen_payment_method + the
 *    wallet-deduction flag in the WC session (payment step).
 *  - POST /hsw/v1/checkout/coupon   : apply / remove a WooCommerce coupon and
 *    return the fresh totals + coupon rows.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Checkout_Rest {

        const NS = 'hsw/v1';

        public function __construct() {
                add_action( 'rest_api_init', array( $this, 'routes' ) );
        }

        public function routes() {
                register_rest_route(
                        self::NS,
                        '/checkout/shipping',
                        array(
                                'methods'             => 'POST',
                                'callback'            => array( $this, 'choose_shipping' ),
                                'permission_callback' => array( $this, 'can_checkout' ),
                                'args'                => array(
                                        'rate' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
                                ),
                        )
                );

                register_rest_route(
                        self::NS,
                        '/checkout/note',
                        array(
                                'methods'             => 'POST',
                                'callback'            => array( $this, 'save_note' ),
                                'permission_callback' => array( $this, 'can_checkout' ),
                        )
                );

                register_rest_route(
                        self::NS,
                        '/checkout/payment',
                        array(
                                'methods'             => 'POST',
                                'callback'            => array( $this, 'choose_payment' ),
                                'permission_callback' => array( $this, 'can_checkout' ),
                        )
                );

                register_rest_route(
                        self::NS,
                        '/checkout/coupon',
                        array(
                                'methods'             => 'POST',
                                'callback'            => array( $this, 'coupon' ),
                                'permission_callback' => array( $this, 'can_checkout' ),
                                'args'                => array(
                                        'code'   => array( 'sanitize_callback' => 'sanitize_text_field' ),
                                        'remove' => array( 'sanitize_callback' => 'sanitize_text_field' ),
                                ),
                        )
                );
        }

        /**
         * WooCommerce loads the cart/session only for frontend requests; inside
         * REST they stay null until wc_load_cart() runs (the same trick the
         * Store API uses). Every checkout route calls this before touching
         * WC()->cart.
         */
        public static function ensure_cart() {
                if ( ! function_exists( 'WC' ) || ! function_exists( 'wc_load_cart' ) ) {
                        return;
                }
                if ( ! WC()->cart || ! WC()->session ) {
                        wc_load_cart();
                }
        }

        /**
         * Any visitor with a valid wp_rest nonce and a cart session.
         */
        public function can_checkout( $request ) {
                self::ensure_cart();
                $nonce = (string) $request->get_header( 'X-WP-Nonce' );
                if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
                        return new WP_Error( 'hsw_rest_nonce', __( 'نشست منقضی شده؛ صفحه را دوباره بارگذاری کنید.', 'hamishop-elementor-widgets' ), array( 'status' => 403 ) );
                }
                if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
                        return new WP_Error( 'hsw_no_cart', __( 'سبد خرید یافت نشد.', 'hamishop-elementor-widgets' ), array( 'status' => 400 ) );
                }
                return true;
        }

        /**
         * Choose a shipping rate (the design's radio list) and return totals.
         */
        public function choose_shipping( $request ) {
                if ( ! WC()->session ) {
                        return new WP_Error( 'hsw_no_session', __( 'نشست یافت نشد.', 'hamishop-elementor-widgets' ), array( 'status' => 400 ) );
                }

                $rate_key = (string) $request->get_param( 'rate' );

                /* Validate the requested key against the recalculated packages. */
                $shipping = WC()->shipping();
                if ( ! $shipping || ! method_exists( $shipping, 'calculate_shipping' ) ) {
                        return new WP_Error( 'hsw_no_shipping', __( 'روش ارسال در دسترس نیست.', 'hamishop-elementor-widgets' ), array( 'status' => 400 ) );
                }
                $packages = $shipping->calculate_shipping( WC()->cart->get_shipping_packages() );
                $valid    = array();
                if ( ! empty( $packages[0]['rates'] ) && is_array( $packages[0]['rates'] ) ) {
                        $valid = array_keys( $packages[0]['rates'] );
                }
                if ( ! in_array( $rate_key, $valid, true ) ) {
                        return new WP_Error( 'hsw_bad_rate', __( 'روش ارسال نامعتبر است.', 'hamishop-elementor-widgets' ), array( 'status' => 400 ) );
                }

                WC()->session->set( 'chosen_shipping_methods', array( $rate_key ) );
                WC()->cart->calculate_totals();

                return rest_ensure_response(
                        array(
                                'ok'     => true,
                                'rate'   => $rate_key,
                                'totals' => $this->totals_payload(),
                        )
                );
        }

        /**
         * Store the order note (+ legal invoice flag) in the WC session.
         */
        public function save_note( $request ) {
                $note    = sanitize_textarea_field( (string) $request->get_param( 'note' ) );
                $invoice = ( ! empty( $request->get_param( 'invoice' ) ) ) ? 'yes' : 'no';

                WC()->session->set( 'hsw_checkout_note', $note );
                WC()->session->set( 'hsw_checkout_invoice', $invoice );

                do_action( 'hsw_checkout_note_saved', $note, 'yes' === $invoice );

                return rest_ensure_response( array( 'ok' => true ) );
        }

        /**
         * Choose a payment method (+ wallet flag) — WooCommerce itself reads
         * chosen_payment_method from the session when the checkout is posted.
         */
        public function choose_payment( $request ) {
                if ( ! WC()->session ) {
                        return new WP_Error( 'hsw_no_session', __( 'نشست یافت نشد.', 'hamishop-elementor-widgets' ), array( 'status' => 400 ) );
                }

                $method = sanitize_text_field( (string) $request->get_param( 'method' ) );
                $wallet = ( ! empty( $request->get_param( 'wallet' ) ) ) ? 'yes' : 'no';

                if ( '' !== $method ) {
                        $gateways = WC()->payment_gateways()->get_available_payment_gateways();
                        if ( ! isset( $gateways[ $method ] ) ) {
                                return new WP_Error( 'hsw_bad_gateway', __( 'روش پرداخت نامعتبر است.', 'hamishop-elementor-widgets' ), array( 'status' => 400 ) );
                        }
                        WC()->session->set( 'chosen_payment_method', $method );
                }
                WC()->session->set( 'hsw_use_wallet', $wallet );

                do_action( 'hsw_checkout_payment_saved', $method, 'yes' === $wallet );

                return rest_ensure_response( array( 'ok' => true, 'method' => $method, 'wallet' => $wallet ) );
        }

        /**
         * Apply / remove a coupon and return the fresh totals (+ coupon rows).
         */
        public function coupon( $request ) {
                $cart = WC()->cart;
                if ( ! $cart ) {
                        return new WP_Error( 'hsw_no_cart', __( 'سبد خرید یافت نشد.', 'hamishop-elementor-widgets' ), array( 'status' => 400 ) );
                }

                $code   = wc_format_coupon_code( wc_clean( (string) $request->get_param( 'code' ) ) );
                $remove = wc_format_coupon_code( wc_clean( (string) $request->get_param( 'remove' ) ) );

                if ( '' !== $remove ) {
                        if ( ! WC()->cart->has_discount( $remove ) ) {
                                return new WP_Error( 'hsw_no_coupon', __( 'این کد روی سبد اعمال نشده است.', 'hamishop-elementor-widgets' ), array( 'status' => 400 ) );
                        }
                        $cart->remove_coupon( $remove );
                        $cart->calculate_totals();
                } elseif ( '' !== $code ) {
                        $discounts = new WC_Discounts( $cart );
                        $valid     = $cart->is_coupon_valid( $code, $discounts );
                        if ( is_wp_error( $valid ) ) {
                                return new WP_Error( 'hsw_bad_coupon', wp_strip_all_tags( $valid->get_error_message() ), array( 'status' => 400 ) );
                        }
                        $cart->apply_coupon( $code );
                        $cart->calculate_totals();
                        $applied = (array) $cart->get_applied_coupons();
                        if ( ! in_array( $code, $applied, true ) ) {
                                return new WP_Error( 'hsw_bad_coupon', __( 'کد تخفیف وارد شده معتبر نیست.', 'hamishop-elementor-widgets' ), array( 'status' => 400 ) );
                        }
                } else {
                        return new WP_Error( 'hsw_no_code', __( 'کد تخفیف را وارد کنید.', 'hamishop-elementor-widgets' ), array( 'status' => 400 ) );
                }

                return rest_ensure_response(
                        array(
                                'ok'      => true,
                                'coupons' => $this->coupons_payload(),
                                'totals'  => $this->totals_payload(),
                        )
                );
        }

        /**
         * Applied-coupon rows for the coupon widget (code + formatted amount).
         */
        protected function coupons_payload() {
                $out = array();
                foreach ( WC()->cart->get_applied_coupons() as $code ) {
                        $amount = method_exists( WC()->cart, 'get_coupon_discount_amount' ) ? (float) WC()->cart->get_coupon_discount_amount( $code, true ) : 0;
                        $out[]  = array(
                                'code'        => (string) $code,
                                'amount_html' => $this->money( $amount, true ),
                                'minus_html'  => $this->money( -1 * $amount, true, true ),
                        );
                }
                return $out;
        }

        /**
         * Formatted totals for the JS patcher — keys match the
         * data-hswk="…" hooks rendered by the summary widget.
         */
        protected function totals_payload() {
                $cart    = WC()->cart;
                $subtotal = 0;
                foreach ( $cart->get_cart() as $cart_item ) {
                        $product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
                        if ( ! $product instanceof WC_Product ) {
                                continue;
                        }
                        $qty = $cart_item['quantity'] ? (int) $cart_item['quantity'] : 1;
                        $subtotal += (float) wc_get_price_to_display( $product, array( 'price' => $product->get_price( 'edit' ) ) ) * $qty;
                }
                $discount = $subtotal - ( (float) $cart->get_total( 'edit' ) - (float) $cart->get_shipping_total() - (float) $cart->get_taxes_total() );
                if ( $discount < 0 ) {
                        $discount = 0;
                }

                $shipping_cost = (float) $cart->get_shipping_total();

                return array(
                        'count'          => (int) $cart->get_cart_contents_count(),
                        'subtotal_html'  => $this->money( $subtotal, true ),
                        'discount_html'  => $this->money( -1 * $discount, true, true ),
                        'shipping_html'  => ( $shipping_cost > 0 ) ? $this->money( $shipping_cost, true ) : '<span>' . esc_html__( 'رایگان', 'hamishop-elementor-widgets' ) . '</span>',
                        'shipping_free'  => ( $shipping_cost <= 0 ),
                        'taxes_html'     => wc_tax_enabled() ? $this->money( (float) $cart->get_taxes_total(), true ) : '',
                        'total_html'     => $this->money( (float) $cart->get_total( 'edit' ), true ),
                );
        }

        /**
         * Same money formatting as the widget renderers (Persian digits,
         * shop separators, currency symbol position).
         */
        protected function money( $amount, $with_symbol = true, $minus_after = false ) {
                $decimals = function_exists( 'wc_get_price_decimals' ) ? absint( wc_get_price_decimals() ) : 0;
                $amount   = (float) $amount;

                /* WooCommerce exposes *_separator() — the short *_sep() names
                 * do not exist and fataled with "Call to undefined function". */
                $dec_sep   = function_exists( 'wc_get_price_decimal_separator' ) ? wc_get_price_decimal_separator() : '.';
                $thou_sep  = function_exists( 'wc_get_price_thousand_separator' ) ? wc_get_price_thousand_separator() : ',';
                $formatted = number_format( abs( $amount ), $decimals, $dec_sep, $thou_sep );
                $out       = HSW_Helpers::fa_num( $formatted );
                if ( $minus_after && 0.0 > $amount ) {
                        $out .= '-';
                }
                if ( $with_symbol ) {
                        $pos  = get_option( 'woocommerce_currency_pos', 'right' );
                        $sym  = get_woocommerce_currency_symbol();
                        $unit = ' <span class="hsw-c-cur">' . esc_html( $sym ) . '</span>';
                        $out  = ( 'right' === $pos || 'right_space' === $pos ) ? $out . $unit : $unit . ' ' . $out;
                }
                return $out;
        }
}
