<?php
/**
 * Base for the checkout shipping-information widgets (اطلاعات ارسال).
 * Extends the cart base so money formatting, option/style control builders
 * and editor-demo detection are shared; adds the checkout-specific resolvers:
 * customer delivery address, live shipping rates, schedule demo data.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

abstract class HSW_Checkout_Base extends HSW_Cart_Base {

        public function get_categories() {
                return array( 'hamishop-cart' );
        }

        public function get_style_depends() {
                return array( 'hsw-fonts', 'hsw-checkout' );
        }

        public function get_script_depends() {
                return array( 'hsw-checkout' );
        }

        /**
         * Root classes for checkout widgets (own palette root .hsw-k).
         */
        protected function root_class( $slug, $extra = '' ) {
                $s   = $this->effective_settings();
                $vis = isset( $s['vis'] ) ? sanitize_html_class( $s['vis'] ) : 'auto';
                $cls = 'hsw-k hsw-' . $slug . ' hsw-vis-' . $vis . $this->hsw_font_class();
                if ( $extra ) {
                        $cls .= ' ' . $extra;
                }
                return $cls;
        }

        /**
         * Next stop after this shipping-information page: the payment page
         * picked in WooCommerce → Settings → Checkout («صفحهٔ پرداخت»).
         * Falls back to the WooCommerce checkout URL when unset.
         */
        /**
         * Is a separate payment page configured?
         *
         * Without one `pay_step_url()` falls back to the checkout page itself, so
         * the «ادامه و ورود به مرحله پرداخت» button pointed at the page it was
         * already on and the order was never placed — the visitor just saw the
         * form reload. When there is no pay page the CTA now submits the
         * WooCommerce checkout instead of navigating.
         *
         * @return bool
         */
        protected function has_pay_step() {
                return class_exists( 'HSW_Complete_Route' ) && '' !== (string) HSW_Complete_Route::pay_step_url();
        }

        protected function pay_step_url() {
                if ( class_exists( 'HSW_Complete_Route' ) ) {
                        $url = HSW_Complete_Route::pay_step_url();
                        if ( '' !== $url ) {
                                return $url;
                        }
                }
                return $this->checkout_url();
        }

        /** @var string|null Cached auth status for order-received. */
        private $order_received_status_cache = null;

        /** @var WC_Order|false|null Cached raw order. */
        private $order_received_raw_cache = null;

        /**
         * Resolve the raw order on order-received before authorization.
         *
         * @return WC_Order|false
         */
        protected function raw_order_received_order() {
                if ( null !== $this->order_received_raw_cache ) {
                        return $this->order_received_raw_cache;
                }
                if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-received' ) || ! function_exists( 'wc_get_order' ) ) {
                        $this->order_received_raw_cache = false;
                        return false;
                }
                $order_id = absint( get_query_var( 'order-received' ) );
                $order    = $order_id ? wc_get_order( $order_id ) : false;
                $this->order_received_raw_cache = ( $order instanceof WC_Order ) ? $order : false;
                return $this->order_received_raw_cache;
        }

        /**
         * Auth status on order-received.
         *
         * @return string
         */
        protected function order_received_auth_status() {
                if ( null !== $this->order_received_status_cache ) {
                        return $this->order_received_status_cache;
                }
                $raw = $this->raw_order_received_order();
                if ( ! $raw instanceof WC_Order ) {
                        $this->order_received_status_cache = class_exists( 'HSW_Order_Auth' ) ? HSW_Order_Auth::STATUS_DENIED : 'denied';
                        return $this->order_received_status_cache;
                }
                $this->order_received_status_cache = class_exists( 'HSW_Order_Auth' )
                        ? HSW_Order_Auth::check_order_access( $raw )
                        : 'granted';
                return $this->order_received_status_cache;
        }

        /**
         * The order behind WooCommerce's order-received endpoint — only when the
         * visitor is authorized (valid key for guest, logged-in owner, or verified).
         *
         * @return WC_Order|false
         */
        protected function order_received_order() {
                $raw = $this->raw_order_received_order();
                if ( ! $raw instanceof WC_Order ) {
                        return false;
                }
                if ( 'granted' === $this->order_received_auth_status() ) {
                        return $raw;
                }
                return false;
        }

        /**
         * Confirmation card rendered instead of the form on order-received.
         *
         * @param WC_Order|false $order Order.
         */
        protected function render_order_received( $order ) {
                $shop   = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
                $status = $this->order_received_auth_status();

                echo '<section class="hsw-done-final">';
                echo '<div class="hsw-done-final__card">';

                if ( 'need_verification' === $status ) {
                        $raw      = $this->raw_order_received_order();
                        $order_id = $raw instanceof WC_Order ? (int) $raw->get_id() : 0;
                        $account  = function_exists( 'wc_get_page_permalink' ) && function_exists( 'wc_get_page_id' ) && 0 < (int) wc_get_page_id( 'myaccount' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
                        if ( isset( $_SERVER['REQUEST_URI'] ) ) {
                                $current_url = home_url( add_query_arg( array() ) );
                                $account     = add_query_arg( array( 'redirect_to' => $current_url, 'redirect' => $current_url ), $account );
                        }
                        $error = class_exists( 'HSW_Order_Auth' ) ? HSW_Order_Auth::get_submission_error() : null;

                        echo HSW_Helpers::icon( 'mail', 'hsw-done-final__ic is-verify', true );
                        echo '<h2 class="hsw-done-final__t">' . esc_html__( 'تأیید دسترسی به سفارش', 'hamishop-elementor-widgets' ) . '</h2>';
                        echo '<p class="hsw-done-final__s">' . esc_html__( 'این سفارش متعلق به یک حساب کاربری ثبت‌شده است. برای مشاهدهٔ جزئیات سفارش، لطفاً نشانی ایمیل صورتحساب سفارش را وارد کنید یا وارد حساب خود شوید.', 'hamishop-elementor-widgets' ) . '</p>';

                        if ( ! empty( $error ) ) {
                                echo '<div class="hsw-cdhero__verify-error" role="alert">' . esc_html( $error ) . '</div>';
                        }

                        echo '<form method="post" class="hsw-cdhero__verify-form">';
                        wp_nonce_field( 'hsw_verify_order_' . $order_id, 'hsw_order_nonce' );
                        echo '<div class="hsw-cdhero__verify-row">';
                        echo '<input type="email" name="hsw_verify_email" class="hsw-cdhero__input" placeholder="' . esc_attr__( 'نشانی ایمیل صورتحساب (مثلاً user@example.com)', 'hamishop-elementor-widgets' ) . '" required autocomplete="email" />';
                        echo '<button type="submit" class="hsw-done-final__btn is-primary">' . esc_html__( 'تأیید و مشاهدهٔ سفارش', 'hamishop-elementor-widgets' ) . '</button>';
                        echo '</div>';
                        echo '</form>';

                        echo '<div class="hsw-done-final__acts">';
                        echo '<a class="hsw-done-final__btn is-ghost" href="' . esc_url( $account ) . '">' . esc_html__( 'ورود به حساب کاربری', 'hamishop-elementor-widgets' ) . '</a>';
                        echo '<a class="hsw-done-final__btn is-ghost" href="' . esc_url( $shop ) . '">' . esc_html__( 'ادامه خرید', 'hamishop-elementor-widgets' ) . '</a>';
                        echo '</div>';
                        echo '</div>';
                        echo '</section>';
                        return;
                }

                echo HSW_Helpers::icon( 'check_circle', 'hsw-done-final__ic', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo '<h2 class="hsw-done-final__t">' . esc_html__( 'سفارش شما ثبت شد', 'hamishop-elementor-widgets' ) . '</h2>';

                if ( $order ) {
                        echo '<p class="hsw-done-final__s">' . esc_html__( 'از خرید شما سپاسگزاریم؛ جزئیات سفارش برای شما ثبت شد.', 'hamishop-elementor-widgets' ) . '</p>';

                        $rows = array(
                                array(
                                        'label' => __( 'شمارهٔ سفارش', 'hamishop-elementor-widgets' ),
                                        'value' => '#' . $order->get_order_number(),
                                ),
                                array(
                                        'label' => __( 'وضعیت سفارش', 'hamishop-elementor-widgets' ),
                                        'value' => function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $order->get_status() ) : $order->get_status(),
                                ),
                                array(
                                        'label' => __( 'مبلغ کل', 'hamishop-elementor-widgets' ),
                                        'value' => $this->money( (float) $order->get_total(), true ),
                                ),
                        );

                        $method_title = (string) $order->get_payment_method_title();
                        if ( '' !== $method_title ) {
                                $rows[] = array(
                                        'label' => __( 'روش پرداخت', 'hamishop-elementor-widgets' ),
                                        'value' => $method_title,
                                );
                        }

                        echo '<div class="hsw-done-final__rows">';
                        foreach ( $rows as $row ) {
                                echo '<div class="hsw-done-final__row"><span>' . esc_html( $row['label'] ) . '</span><b>' . wp_kses_post( $row['value'] ) . '</b></div>';
                        }
                        echo '</div>';

                        /* Gateway thank-you output (e.g. bank-transfer instructions),
                         * exactly like WooCommerce's own thank-you template. The
                         * shared guard fires the method and public hooks once. */
                        ob_start();
                        HSW_Thankyou::fire( $order );
                        $extra = trim( (string) ob_get_clean() );
                        if ( '' !== $extra ) {
                                echo '<div class="hsw-done-final__extra">' . $extra . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gateway markup, mirrors WooCommerce core.
                        }
                } else {
                        echo '<p class="hsw-done-final__s">' . esc_html__( 'سفارش یافت نشد یا لینک مشاهدهٔ آن منقضی شده است.', 'hamishop-elementor-widgets' ) . '</p>';
                }

                echo '<div class="hsw-done-final__acts">';
                if ( $order && is_user_logged_in() ) {
                        echo '<a class="hsw-done-final__btn is-primary" href="' . esc_url( $order->get_view_order_url() ) . '">' . esc_html__( 'مشاهدهٔ سفارش', 'hamishop-elementor-widgets' ) . '</a>';
                } elseif ( ! $order && function_exists( 'wc_get_page_permalink' ) && function_exists( 'wc_get_page_id' ) && 0 < (int) wc_get_page_id( 'myaccount' ) ) {
                        echo '<a class="hsw-done-final__btn is-primary" href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">' . esc_html__( 'حساب کاربری من', 'hamishop-elementor-widgets' ) . '</a>';
                }
                echo '<a class="hsw-done-final__btn is-ghost" href="' . esc_url( $shop ) . '">' . esc_html__( 'ادامه خرید', 'hamishop-elementor-widgets' ) . '</a>';
                echo '</div>';
                echo '</div></section>';
        }

        /* =========================================================================
         *  LIVE CHECKOUT RESOLVERS
         * ====================================================================== */

        /**
         * WooCommerce customer (delivery target) when available.
         */
        protected function customer() {
                if ( function_exists( 'WC' ) && WC()->customer ) {
                        return WC()->customer;
                }
                return null;
        }

        /**
         * Recipient name: shipping first, fallback to billing.
         */
        protected function customer_name() {
                $c = $this->customer();
                if ( ! $c ) {
                        return '';
                }
                $first = $c->get_shipping_first_name();
                $last  = $c->get_shipping_last_name();
                if ( '' === trim( $first . $last ) ) {
                        $first = $c->get_billing_first_name();
                        $last  = $c->get_billing_last_name();
                }
                return trim( $first . ' ' . $last );
        }

        /**
         * One-line delivery address (province/city + street + extras).
         */
        protected function customer_address() {
                $c = $this->customer();
                if ( ! $c ) {
                        return '';
                }
                $parts = array();
                $line1 = trim( (string) $c->get_shipping_address_1() );
                $line2 = trim( (string) $c->get_shipping_address_2() );
                $city  = trim( (string) $c->get_shipping_city() );
                $state = '';
                if ( function_exists( 'WC' ) && WC()->countries ) {
                        $cc    = (string) $c->get_shipping_country();
                        $scode = (string) $c->get_shipping_state();
                        $list  = ( '' !== $cc ) ? WC()->countries->get_states( $cc ) : array();
                        if ( '' !== $scode ) {
                                if ( is_array( $list ) && isset( $list[ $scode ] ) ) {
                                        $state = (string) $list[ $scode ];
                                } elseif ( ! is_numeric( $scode ) ) {
                                        $state = $scode; // Free-text state typed by the customer.
                                }
                        }
                }
                if ( '' !== $state ) {
                        $parts[] = $state;
                }
                if ( '' !== $city ) {
                        $parts[] = $city;
                }
                if ( '' !== $line1 ) {
                        $parts[] = $line1;
                }
                if ( '' !== $line2 ) {
                        $parts[] = $line2;
                }
                if ( empty( $parts ) ) {
                        // Empty shipping → fall back to billing fields.
                        $parts[] = trim( (string) $c->get_billing_address_1() );
                        $city_b  = trim( (string) $c->get_billing_city() );
                        if ( '' !== $city_b ) {
                                array_unshift( $parts, $city_b );
                        }
                }
                return trim( implode( '، ', array_filter( $parts ) ) );
        }

        /**
         * True when the customer has a real address (city or street line).
         * Guest defaults only carry the store base country/state, which alone
         * should not count as an address.
         */
        protected function customer_has_address() {
                $c = $this->customer();
                if ( ! $c ) {
                        return false;
                }

                /*
                 * A digital cart needs no courier, so street/city/state are irrelevant.
                 * Customer only needs first_name and phone.
                 */
                if ( class_exists( 'HSW_Checkout_Digital' ) && HSW_Checkout_Digital::is_digital() ) {
                        $name  = $this->customer_name();
                        $phone = $this->customer_phone();
                        if ( '' === trim( (string) $phone ) ) {
                                $phone = HSW_Checkout_Digital::verified_phone();
                        }
                        return ( '' !== trim( (string) $name ) && '' !== trim( (string) $phone ) );
                }

                /*
                 * Every field a courier needs must be there. The old check was
                 * an OR: a city alone counted as "has address", the customer
                 * reached the pay step, and the order had no street or phone.
                 * Each field falls back to billing when shipping is empty.
                 */
                $required = array(
                        'first_name' => array( 'get_shipping_first_name', 'get_billing_first_name' ),
                        'phone'      => array( 'get_shipping_phone', 'get_billing_phone' ),
                        'state'      => array( 'get_shipping_state', 'get_billing_state' ),
                        'city'       => array( 'get_shipping_city', 'get_billing_city' ),
                        'address_1'  => array( 'get_shipping_address_1', 'get_billing_address_1' ),
                );

                foreach ( $required as $getters ) {
                        $value = '';
                        foreach ( $getters as $getter ) {
                                if ( ! is_callable( array( $c, $getter ) ) ) {
                                        continue;
                                }
                                $value = trim( (string) $c->$getter() );
                                if ( '' !== $value ) {
                                        break;
                                }
                        }
                        if ( '' === $value ) {
                                return false;
                        }
                }

                return true;
        }

        /**
         * Delivery phone: shipping, fallback billing.
         */
        protected function customer_phone() {
                $c = $this->customer();
                if ( ! $c ) {
                        return '';
                }
                $phone = $c->get_shipping_phone();
                if ( '' === trim( (string) $phone ) ) {
                        $phone = $c->get_billing_phone();
                }
                return (string) $phone;
        }

        /**
         * Postal code.
         */
        protected function customer_postcode() {
                $c = $this->customer();
                if ( ! $c ) {
                        return '';
                }
                $pc = $c->get_shipping_postcode();
                if ( '' === trim( (string) $pc ) ) {
                        $pc = $c->get_billing_postcode();
                }
                return (string) $pc;
        }

        /**
         * Real WooCommerce shipping rates for the current cart (first package).
         * array( id, title, cost, chosen ).
         */
        protected function shipping_rates() {
                if ( ! $this->has_cart() || ! WC()->cart->needs_shipping() || ! function_exists( 'WC' ) ) {
                        return array();
                }
                $this->refresh_display_cart();
                $shipping = WC()->shipping();
                if ( ! $shipping || ! method_exists( $shipping, 'calculate_shipping' ) ) {
                        return array();
                }
                $packages = $shipping->calculate_shipping( WC()->cart->get_shipping_packages() );
                if ( empty( $packages ) || empty( $packages[0]['rates'] ) || ! is_array( $packages[0]['rates'] ) ) {
                        return array();
                }
                $chosen = array();
                if ( WC()->session ) {
                        $chosen = (array) WC()->session->get( 'chosen_shipping_methods' );
                }
                $out = array();
                foreach ( $packages[0]['rates'] as $key => $rate ) {
                        $out[] = array(
                                'id'     => (string) $key,
                                'title'  => $rate->get_label(),
                                'cost'   => (float) $rate->get_cost(),
                                'chosen' => ( isset( $chosen[0] ) && (string) $chosen[0] === (string) $key ),
                        );
                }
                if ( $out && ! wp_list_filter( $out, array( 'chosen' => true ) ) ) {
                        $out[0]['chosen'] = true;
                }
                return $out;
        }

        /*
         * `chosen_shipping_cost()` used to live here and answered "0 means
         * free". Three widgets read it and each added its own `> 0` test, so a
         * zero for "no rate for this address" became «رایگان» in all three.
         * `HSW_Cart_Base::shipping_state()` is the single answer now; this
         * method is gone on purpose, not by accident.
         */

        /**
         * Pick an icon for a shipping rate by keyword (پیک/پست/تیپاکس/…).
         */
        protected function icon_for_rate( $title ) {
                $t = (string) $title;
                if ( false !== strpos( $t, 'پیک' ) || false !== strpos( $t, 'موتور' ) ) {
                        return 'two_wheeler';
                }
                if ( false !== strpos( $t, 'پست' ) || false !== strpos( $t, 'مرسولات' ) ) {
                        return 'markunread_mailbox';
                }
                if ( false !== strpos( $t, 'تیپاکس' ) || false !== strpos( $t, 'tipax' ) ) {
                        return 'inventory_2';
                }
                if ( false !== strpos( $t, 'فوری' ) || false !== strpos( $t, 'سریع' ) || false !== strpos( $t, 'اکسپرس' ) ) {
                        return 'bolt';
                }
                return 'local_shipping';
        }

        /* =========================================================================
         *  DEMO DATA (editor preview only — mirrors the supplied checkout mock)
         * ====================================================================== */

        /**
         * Demo delivery address (Ali Hosseini / Tehran — the supplied mock).
         */
        protected function demo_address() {
                return array(
                        'name'     => 'علی حسینی',
                        'chip'     => 'تحویل‌گیرنده اصلی',
                        'line'     => 'تهران، منطقه ۳، خیابان ولیعصر، بالاتر از میدان ونک، خیابان نگار، پلاک ۲۴، واحد ۶',
                        'phone'    => '۰۹۱۲۳۴۵۶۷۸۹',
                        'postcode' => '۱۹۶۹۸۴۵۲۱۱',
                );
        }

        /**
         * Demo shipping options (express courier / post / tipax — the mock).
         */
        protected function demo_rates() {
                return array(
                        array(
                                'id'     => 'express',
                                'title'  => 'ارسال سریع با پیک موتوری ویژه تهران',
                                'desc'   => 'تحویل امروز در بازه ۱۶ الی ۲۰ با رهگیری زنده سفیر',
                                'icon'   => 'two_wheeler',
                                'cost'   => 0,
                                'was'    => 45000,
                                'badge'  => 'پیشنهادی',
                                'chosen' => true,
                        ),
                        array(
                                'id'     => 'pishtaz',
                                'title'  => 'پست پیشتاز سراسری',
                                'desc'   => 'تحویل ۲ الی ۳ روز کاری در سراسر کشور',
                                'icon'   => 'markunread_mailbox',
                                'cost'   => 38000,
                                'was'    => 0,
                                'badge'  => '',
                                'chosen' => false,
                        ),
                        array(
                                'id'     => 'tipax',
                                'title'  => 'ارسال با تیپاکس',
                                'desc'   => 'تحویل درب منزل با بسته‌بندی امن و بیمه مرسوله',
                                'icon'   => 'inventory_2',
                                'cost'   => 55000,
                                'was'    => 0,
                                'badge'  => '',
                                'chosen' => false,
                        ),
                );
        }

        /**
         * Demo delivery days (the mock: ۲۴/۲۵/۲۶ دی + a full-capacity day).
         */
        protected function demo_days() {
                return array(
                        array( 'meta' => 'امروز', 'day' => '۲۴ دی', 'weekday' => 'سه‌شنبه', 'disabled' => 'no', 'tag' => '' ),
                        array( 'meta' => 'فردا', 'day' => '۲۵ دی', 'weekday' => 'چهارشنبه', 'disabled' => 'no', 'tag' => '' ),
                        array( 'meta' => 'پس‌فردا', 'day' => '۲۶ دی', 'weekday' => 'پنج‌شنبه', 'disabled' => 'no', 'tag' => '' ),
                        array( 'meta' => '۲۸ دی', 'day' => 'شنبه', 'weekday' => 'ظرفیت تکمیل', 'disabled' => 'yes', 'tag' => 'ظرفیت تکمیل' ),
                );
        }

        /**
         * Demo delivery time slots.
         */
        protected function demo_slots() {
                return array(
                        array( 'label' => 'ساعت ۹ تا ۱۳', 'tag' => 'صبح' ),
                        array( 'label' => 'ساعت ۱۳ تا ۱۷', 'tag' => 'عصر' ),
                        array( 'label' => 'ساعت ۱۷ تا ۲۱', 'tag' => 'شبانه', 'chosen' => 'yes' ),
                );
        }

        /**
         * Demo parcel items (flagship phone + wireless headphones — the mock).
         */
        protected function demo_items() {
                $ph = HSW_URL . 'assets/img/placeholder.svg';
                return array(
                        array(
                                'key'   => 'demo-1',
                                'name'  => 'گوشی موبایل سامسونگ Galaxy S24 Ultra ظرفیت ۲۵۶ گیگابایت',
                                'url'   => '#',
                                'img'   => '<img src="' . esc_url( $ph ) . '" alt="" loading="lazy" />',
                                'chips' => array(
                                        array( 'label' => 'مشکی فانتوم', 'hex' => '#2B2B2D', 'icon' => '' ),
                                ),
                                'qty'   => 1,
                        ),
                        array(
                                'key'   => 'demo-2',
                                'name'  => 'هدفون بی‌سیم پرو مکس با نویز کنسلینگ',
                                'url'   => '#',
                                'img'   => '<img src="' . esc_url( $ph ) . '" alt="" loading="lazy" />',
                                'chips' => array(
                                        array( 'label' => 'سرمه‌ای مات', 'hex' => '#26334D', 'icon' => '' ),
                                ),
                                'qty'   => 1,
                        ),
                );
        }

        /**
         * Demo invoice totals — exactly the supplied mock:
         * ۶۷,۹۰۰,۰۰۰ − ۳,۴۶۰,۰۰۰ + ارسال رایگان = ۶۴,۴۴۰,۰۰۰ تومان.
         */
        protected function demo_totals() {
                return array(
                        'count'    => 2,
                        'subtotal' => 67900000,
                        'discount' => 3460000,
                        'shipping' => array( 'free', 0 ),
                        'taxes'    => 0,
                        'total'    => 64440000,
                );
        }

        /**
         * Support phone for the desktop top app bar (mock: ۰۲۱-۹۱۰۰۸۸۷۷).
         */
        protected function demo_support_phone() {
                return '۰۲۱-۹۱۰۰۸۸۷۷';
        }

}
