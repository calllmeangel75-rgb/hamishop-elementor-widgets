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
                foreach ( array( 'get_shipping_city', 'get_shipping_address_1', 'get_billing_city', 'get_billing_address_1' ) as $g ) {
                        if ( '' !== trim( (string) $c->$g() ) ) {
                                return true;
                        }
                }
                return false;
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

        /**
         * Chosen shipping cost (0 = free) for the summary rows.
         */
        protected function chosen_shipping_cost() {
                if ( ! $this->has_cart() ) {
                        return 0.0; // no cart context (REST/cron/editor) — never fatal.
                }
                $rates = $this->shipping_rates();
                foreach ( $rates as $rate ) {
                        if ( ! empty( $rate['chosen'] ) ) {
                                return (float) $rate['cost'];
                        }
                }
                return (float) WC()->cart->get_shipping_total();
        }

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
