<?php
/**
 * Checkout REST routes:
 *  - POST /hsw/v1/checkout/shipping : choose a shipping method (session-level,
 *    exactly like WooCommerce's own update_order_review) → returns the fresh
 *    formatted totals so the summary/bottom-bar widgets can patch themselves.
 *  - POST /hsw/v1/checkout/note     : store the design's order-note textarea +
 *    legal-invoice checkbox into the WC session before continuing to payment.
 *  - POST /hsw/v1/checkout/schedule : store the picked delivery day + time slot
 *    in the WC session; the order bridge appends them to the order note.
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
                        '/checkout/schedule',
                        array(
                                'methods'             => 'POST',
                                'callback'            => array( $this, 'save_schedule' ),
                                'permission_callback' => array( $this, 'can_checkout' ),
                                'args'                => array(
                                        'day'  => array( 'sanitize_callback' => 'sanitize_text_field' ),
                                        'slot' => array( 'sanitize_callback' => 'sanitize_text_field' ),
                                ),
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

                /*
                 * The address step used to be a read-only card that linked away
                 * to the account page; a customer with no saved address reached
                 * the pay step with nothing to deliver to. This route lets the
                 * checkout collect it in place.
                 */
                register_rest_route(
                        self::NS,
                        '/checkout/address',
                        array(
                                'methods'             => 'POST',
                                'callback'            => array( $this, 'save_address' ),
                                'permission_callback' => array( $this, 'can_checkout' ),
                                'args'                => array(
                                        'first_name' => array( 'sanitize_callback' => 'sanitize_text_field' ),
                                        'last_name'  => array( 'sanitize_callback' => 'sanitize_text_field' ),
                                        'phone'      => array( 'sanitize_callback' => 'sanitize_text_field' ),
                                        'state'      => array( 'sanitize_callback' => 'sanitize_text_field' ),
                                        'city'       => array( 'sanitize_callback' => 'sanitize_text_field' ),
                                        'address_1'  => array( 'sanitize_callback' => 'sanitize_text_field' ),
                                        'address_2'  => array( 'sanitize_callback' => 'sanitize_text_field' ),
                                        'postcode'   => array( 'sanitize_callback' => 'sanitize_text_field' ),
                                        'country'    => array( 'sanitize_callback' => 'sanitize_text_field' ),
                                ),
                        )
                );
        }

        /**
         * Save the delivery address entered during checkout.
         *
         * Writes to billing **and** shipping so the order, the account address
         * book and the pay step all agree, then recalculates shipping because
         * the province/city decide the rate.
         *
         * @param WP_REST_Request $request Request.
         * @return WP_REST_Response|WP_Error
         */
        public function save_address( $request ) {
                if ( ! function_exists( 'WC' ) || ! WC()->customer ) {
                        return new WP_Error( 'hsw_no_customer', __( 'مشتری یافت نشد.', 'hamishop-elementor-widgets' ), array( 'status' => 400 ) );
                }

                $customer = WC()->customer;

                $country = strtoupper( (string) $request->get_param( 'country' ) );
                if ( '' === $country ) {
                        $base    = function_exists( 'wc_get_base_location' ) ? wc_get_base_location() : array( 'country' => 'IR' );
                        $country = isset( $base['country'] ) && '' !== $base['country'] ? (string) $base['country'] : 'IR';
                }

                $values = array(
                        'first_name' => trim( (string) $request->get_param( 'first_name' ) ),
                        'last_name'  => trim( (string) $request->get_param( 'last_name' ) ),
                        'phone'      => trim( (string) $request->get_param( 'phone' ) ),
                        'state'      => trim( (string) $request->get_param( 'state' ) ),
                        'city'       => trim( (string) $request->get_param( 'city' ) ),
                        'address_1'  => trim( (string) $request->get_param( 'address_1' ) ),
                        'address_2'  => trim( (string) $request->get_param( 'address_2' ) ),
                        'postcode'   => trim( (string) $request->get_param( 'postcode' ) ),
                );

                /* Only the fields a delivery cannot work without. */
                $required = array(
                        'first_name' => __( 'نام گیرنده', 'hamishop-elementor-widgets' ),
                        'phone'      => __( 'شماره تماس', 'hamishop-elementor-widgets' ),
                        'state'      => __( 'استان', 'hamishop-elementor-widgets' ),
                        'city'       => __( 'شهر', 'hamishop-elementor-widgets' ),
                        'address_1'  => __( 'نشانی', 'hamishop-elementor-widgets' ),
                );
                foreach ( $required as $key => $label ) {
                        if ( '' === $values[ $key ] ) {
                                return new WP_Error(
                                        'hsw_address_' . $key,
                                        sprintf(
                                                /* translators: %s: field label. */
                                                __( '%s را وارد کنید.', 'hamishop-elementor-widgets' ),
                                                $label
                                        ),
                                        array( 'status' => 400 )
                                );
                        }
                }

                foreach ( $values as $key => $value ) {
                        foreach ( array( 'set_billing_', 'set_shipping_' ) as $prefix ) {
                                $setter = $prefix . $key;
                                if ( method_exists( $customer, $setter ) ) {
                                        $customer->$setter( $value );
                                }
                        }
                }

                foreach ( array( 'set_billing_country', 'set_shipping_country' ) as $setter ) {
                        if ( method_exists( $customer, $setter ) ) {
                                $customer->$setter( $country );
                        }
                }

                /* Persists to user meta for logged-in customers, session otherwise. */
                $customer->save();

                if ( WC()->cart ) {
                        WC()->cart->calculate_shipping();
                        WC()->cart->calculate_totals();
                }

                return rest_ensure_response(
                        array(
                                'ok'      => true,
                                'name'    => trim( $values['first_name'] . ' ' . $values['last_name'] ),
                                'phone'   => $values['phone'],
                                'postcode' => $values['postcode'],
                                'line'    => implode( '، ', array_filter( array( $values['state'], $values['city'], $values['address_1'], $values['address_2'] ) ) ),
                                'message' => __( 'آدرس ذخیره شد.', 'hamishop-elementor-widgets' ),
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
         * Store the picked delivery day + time slot in the WC session — the
         * order bridge appends «زمان تحویل: …» to the customer order note.
         */
        public function save_schedule( $request ) {
                if ( ! WC()->session ) {
                        return new WP_Error( 'hsw_no_session', __( 'نشست یافت نشد.', 'hamishop-elementor-widgets' ), array( 'status' => 400 ) );
                }

                $day  = sanitize_text_field( (string) $request->get_param( 'day' ) );
                $slot = sanitize_text_field( (string) $request->get_param( 'slot' ) );

                WC()->session->set( 'hsw_delivery_schedule', array( 'day' => $day, 'slot' => $slot ) );

                do_action( 'hsw_checkout_schedule_saved', $day, $slot );

                return rest_ensure_response( array( 'ok' => true, 'day' => $day, 'slot' => $slot ) );
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

                /*
                 * Coupon guessing: 10 requests/minute per IP+session, plus a lock
                 * after 10 wrong codes in 10 minutes. Thresholds are filterable
                 * through `hsw_checkout_rate_limit` (same filter as /complete).
                 */
                $gate = $this->coupon_gate();
                if ( is_wp_error( $gate ) ) {
                        return $gate;
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
                        /*
                         * `WC_Cart::is_coupon_valid()` does not exist — calling it threw
                         * "Call to undefined method" and WordPress answered the REST
                         * request with its generic "critical error" page, which is what
                         * the customer saw after typing a wrong coupon on the pay step.
                         *
                         * The validator lives on WC_Discounts and takes a WC_Coupon.
                         */
                        $coupon    = new WC_Coupon( $code );
                        $discounts = new WC_Discounts( $cart );
                        $valid     = $discounts->is_coupon_valid( $coupon );

                        if ( is_wp_error( $valid ) ) {
                                $this->coupon_failed();
                                return new WP_Error( 'hsw_bad_coupon', wp_strip_all_tags( $valid->get_error_message() ), array( 'status' => 400 ) );
                        }
                        $cart->apply_coupon( $code );
                        $cart->calculate_totals();
                        $applied = (array) $cart->get_applied_coupons();
                        if ( ! in_array( $code, $applied, true ) ) {
                                $this->coupon_failed();
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
         * Request limiter + wrong-code lock for the coupon endpoint.
         *
         * @return true|WP_Error
         */
        protected function coupon_gate() {
                if ( ! class_exists( 'HSW_Throttle' ) ) {
                        return true;
                }

                $conf = apply_filters(
                        'hsw_checkout_rate_limit',
                        array( 'limit' => 10, 'window' => 60, 'fail_limit' => 10, 'fail_window' => 600, 'lock' => 600 ),
                        'coupon'
                );
                $conf = wp_parse_args( $conf, array( 'limit' => 10, 'window' => 60, 'fail_limit' => 10, 'fail_window' => 600, 'lock' => 600 ) );

                $ip  = HSW_Throttle::ip();
                $sid = ( WC()->session ) ? (string) WC()->session->get_customer_id() : '';
                $key = md5( $ip . '|' . $sid );

                $lock = HSW_Throttle::peek( 'hsw-ck-coupon-lock-' . $key );
                if ( ! empty( $lock['hits'] ) ) {
                        return $this->coupon_locked( (int) $lock['retry_after'] );
                }

                $hit = HSW_Throttle::hit( 'hsw-ck-coupon-' . $key, (int) $conf['limit'], (int) $conf['window'] );
                if ( ! $hit['allowed'] ) {
                        return $this->coupon_locked( (int) $hit['retry_after'] );
                }

                return true;
        }

        /**
         * Count a wrong code; lock the session once the threshold is reached.
         *
         * @return void
         */
        protected function coupon_failed() {
                if ( ! class_exists( 'HSW_Throttle' ) ) {
                        return;
                }

                $conf = apply_filters(
                        'hsw_checkout_rate_limit',
                        array( 'limit' => 10, 'window' => 60, 'fail_limit' => 10, 'fail_window' => 600, 'lock' => 600 ),
                        'coupon'
                );
                $conf = wp_parse_args( $conf, array( 'fail_limit' => 10, 'fail_window' => 600, 'lock' => 600 ) );

                $key = md5( HSW_Throttle::ip() . '|' . ( ( WC()->session ) ? (string) WC()->session->get_customer_id() : '' ) );
                $hit = HSW_Throttle::hit( 'hsw-ck-coupon-fail-' . $key, (int) $conf['fail_limit'], (int) $conf['fail_window'] );

                if ( ! $hit['allowed'] ) {
                        HSW_Throttle::hit( 'hsw-ck-coupon-lock-' . $key, 1, (int) $conf['lock'] );
                }
        }

        /**
         * 429 with a Persian message and Retry-After.
         *
         * @param int $retry Seconds.
         * @return WP_Error
         */
        protected function coupon_locked( $retry ) {
                $error = new WP_Error(
                        'hsw_coupon_rate_limited',
                        __( 'تلاش‌های ناموفق زیاد بود؛ چند دقیقه بعد دوباره امتحان کنید.', 'hamishop-elementor-widgets' ),
                        array( 'status' => 429 )
                );
                $error->add_data( array( 'retry_after' => max( 1, (int) $retry ) ) );

                return $error;
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
