<?php
/**
 * Base for cart widgets — the WooCommerce cart page design (desktop + mobile).
 * Real cart resolvers (items, totals, savings, shipping), money formatting,
 * editor demo data and shared control builders.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

abstract class HSW_Cart_Base extends HSW_Widget_Base {

        public function get_categories() {
                return array( 'hamishop-cart' );
        }

        public function get_style_depends() {
                return array( 'hsw-fonts', 'hsw-cart' );
        }

        public function get_script_depends() {
                return array( 'hsw-cart' );
        }

        /* =========================================================================
         *  CONTEXT
         * ====================================================================== */

        /**
         * True when the live WooCommerce cart object is available.
         */
        protected function has_cart() {
                return function_exists( 'WC' ) && WC()->cart && function_exists( 'wc_get_cart_url' );
        }

        /**
         * True while inside the Elementor editor / preview iframe (any context).
         */
        protected function is_editing() {
                if ( ! class_exists( '\Elementor\Plugin' ) ) {
                        return false;
                }
                $el = \Elementor\Plugin::$instance;
                return ( isset( $el->editor ) && $el->editor->is_edit_mode() )
                        || ( isset( $el->preview ) && $el->preview->is_preview_mode() )
                        || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() && isset( $_POST['action'] ) && 'elementor_ajax' === $_POST['action'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }

        /**
         * Editor / ajax preview without a real cart → render the design demo.
         */
        protected function is_demo() {
                if ( $this->has_cart() ) {
                        return false;
                }
                return $this->is_editing();
        }

        /**
         * Value of a widget option with default fallback.
         */
        protected function opt( $key, $default = '' ) {
                $s = $this->effective_settings();
                return ( isset( $s[ $key ] ) && '' !== trim( (string) $s[ $key ] ) ) ? trim( (string) $s[ $key ] ) : $default;
        }

        /**
         * Root classes for cart widgets.
         */
        protected function root_class( $slug, $extra = '' ) {
                $s   = $this->effective_settings();
                $vis = isset( $s['vis'] ) ? sanitize_html_class( $s['vis'] ) : 'auto';
                $cls = 'hsw-c hsw-' . $slug . ' hsw-vis-' . $vis . $this->hsw_font_class();
                if ( $extra ) {
                        $cls .= ' ' . $extra;
                }
                return $cls;
        }

        /**
         * Cart URL (form action) — safe '#' inside the editor demo.
         */
        protected function cart_url() {
                return $this->has_cart() ? wc_get_cart_url() : '#';
        }

        /**
         * Checkout URL.
         */
        protected function checkout_url() {
                if ( $this->has_cart() && function_exists( 'wc_get_checkout_url' ) ) {
                        return wc_get_checkout_url();
                }
                return '#';
        }

        /**
         * Shop URL (back links).
         */
        protected function shop_url() {
                if ( function_exists( 'wc_get_page_permalink' ) ) {
                        return wc_get_page_permalink( 'shop' );
                }
                return home_url( '/' );
        }

        /* =========================================================================
         *  MONEY / NUMBERS (respect the shop currency config, Persian digits)
         * ====================================================================== */

        /**
         * Format an amount exactly like the design: ۶۵,۷۶۰,۰۰۰ + currency symbol.
         *
         * @param float $amount      Amount.
         * @param bool  $with_symbol Append the configured currency symbol.
         * @param bool  $minus_after Persian-style minus after digits (سود شما).
         */
        protected function money( $amount, $with_symbol = false, $minus_after = false ) {
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

        /**
         * Count with Persian digits ({count} template helper).
         */
        protected function fa_count( $n ) {
                return HSW_Helpers::fa_num( (int) $n );
        }

        /**
         * Replace {count} in a label template.
         */
        protected function with_count( $template, $count ) {
                return str_replace( '{count}', $this->fa_count( $count ), (string) $template );
        }

        /* =========================================================================
         *  LIVE CART RESOLVERS
         * ====================================================================== */

        /**
         * Number of units in the cart.
         */
        protected function cart_count() {
                if ( ! $this->has_cart() ) {
                        return 0;
                }
                return (int) WC()->cart->get_cart_contents_count();
        }

        /**
         * Normalized cart items (array of render payloads).
         */
        protected function cart_items() {
                if ( ! $this->has_cart() ) {
                        return array();
                }
                $items = array();
                foreach ( array_values( WC()->cart->get_cart() ) as $cart_item ) {
                        $data = $this->item_data( $cart_item );
                        if ( $data ) {
                                $items[] = $data;
                        }
                }
                return $items;
        }

        /**
         * Convert one cart item into a render payload.
         */
        protected function item_data( $cart_item ) {
                $product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
                if ( ! $product instanceof WC_Product ) {
                        return null;
                }
                $qty          = ( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 1;
                $price_unit   = (float) wc_get_price_to_display( $product, array( 'price' => $product->get_price( 'edit' ) ) );
                $regular_unit = 0;
                if ( $product->is_on_sale() && (float) $product->get_regular_price( 'edit' ) > (float) $product->get_price( 'edit' ) ) {
                        $regular_unit = (float) wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price( 'edit' ) ) );
                }
                $max = $product->get_max_purchase_quantity();
                return array(
                        'key'      => $cart_item['key'],
                        'name'     => $product->get_name(),
                        'url'      => $product->get_permalink( $cart_item ),
                        'img'      => $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ),
                        'eyebrow'  => HSW_Helpers::brand_label( $product ),
                        'chips'    => $this->item_chips( $cart_item, $product ),
                        'warranty' => $this->item_warranty( $product ),
                        'qty'      => $qty,
                        'min'      => 1,
                        'max'      => ( 0 < $max ) ? $max : 0,
                        'price'    => $price_unit * $qty,
                        'regular'  => $regular_unit * $qty,
                        'disc'     => HSW_Helpers::discount_percent( $product ),
                        'remove'   => function_exists( 'wc_get_cart_remove_url' ) ? wc_get_cart_remove_url( $cart_item['key'] ) : '',
                );
        }

        /**
         * Variation attribute chips (+ color dot when a hex is resolvable).
         */
        protected function item_chips( $cart_item, $product ) {
                $chips     = array();
                $variation = ( isset( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ) ? $cart_item['variation'] : array();
                foreach ( $variation as $attr => $value ) {
                        if ( '' === $value || null === $value ) {
                                continue;
                        }
                        $value   = (string) $value;
                        $tax     = ( 0 === strpos( $attr, 'attribute_pa_' ) ) ? substr( $attr, strlen( 'attribute_' ) ) : '';
                        $display = $value;
                        $hex     = '';
                        if ( $tax && taxonomy_exists( $tax ) ) {
                                $term = get_term_by( 'slug', $value, $tax );
                                if ( $term ) {
                                        $display = $term->name;
                                        foreach ( array( 'color', 'hex', 'color_hex', 'wpcsw_color' ) as $meta_key ) {
                                                $meta = get_term_meta( $term->term_id, $meta_key, true );
                                                if ( $meta && preg_match( '/^#[0-9a-fA-F]{3,8}$/', (string) $meta ) ) {
                                                        $hex = (string) $meta;
                                                        break;
                                                }
                                        }
                                }
                        }
                        if ( '' === $hex && preg_match( '/^#[0-9a-fA-F]{3,8}$/', $value ) ) {
                                $hex = $value;
                        }
                        $chips[] = array(
                                'label' => $display,
                                'hex'   => $hex,
                                'icon'  => '',
                        );
                        unset( $product );
                }
                return $chips;
        }

        /**
         * Warranty/guarantee text from the product attributes (if any).
         */
        protected function item_warranty( $product ) {
                $attrs = $product->get_attributes();
                if ( empty( $attrs ) || ! is_array( $attrs ) ) {
                        return '';
                }
                foreach ( $attrs as $attr ) {
                        if ( ! $attr instanceof WC_Product_Attribute ) {
                                continue;
                        }
                        $name = strtolower( $attr->get_name() );
                        $hit  = ( false !== strpos( $name, 'warranty' ) || false !== strpos( $name, 'guarantee' ) )
                                || ( function_exists( 'mb_strtolower' ) && ( false !== mb_strpos( mb_strtolower( $attr->get_name() ), 'گارانتی' ) || false !== mb_strpos( mb_strtolower( $attr->get_name() ), 'ضمانت' ) ) );
                        if ( ! $hit ) {
                                continue;
                        }
                        $names = array();
                        $terms = $attr->get_terms();
                        if ( $terms && ! is_wp_error( $terms ) ) {
                                foreach ( $terms as $t ) {
                                        $names[] = $t->name;
                                }
                        } else {
                                $opts = $attr->get_options();
                                foreach ( (array) $opts as $opt ) {
                                        $names[] = (string) $opt;
                                }
                        }
                        $text = trim( implode( ' ', array_filter( $names ) ) );
                        if ( '' !== $text ) {
                                return $text;
                        }
                }
                return '';
        }

        /**
         * Base (regular-price) subtotal of the cart — «قیمت پایه اقلام».
         */
        protected function base_subtotal() {
                if ( ! $this->has_cart() ) {
                        return 0;
                }
                $sum = 0;
                foreach ( WC()->cart->get_cart() as $cart_item ) {
                        $product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
                        if ( ! $product instanceof WC_Product ) {
                                continue;
                        }
                        $qty  = $cart_item['quantity'] ? (int) $cart_item['quantity'] : 1;
                        /*
                         * «قیمت پایه اقلام» is the pre-discount price: using the
                         * active price made the row equal to the sale subtotal and
                         * broke the base − savings = total relationship.
                         */
                        $base = (float) $product->get_regular_price( 'edit' );
                        if ( $base <= 0 ) {
                                $base = (float) $product->get_price( 'edit' );
                        }
                        $sum += (float) wc_get_price_to_display( $product, array( 'price' => $base ) ) * $qty;
                }
                return $sum;
        }

        /**
         * «سود شما از این خرید» = base subtotal − (total − shipping).
         */
        protected function savings() {
                if ( ! $this->has_cart() || ! class_exists( 'HSW_Helpers' ) ) {
                        return 0;
                }
                /*
                 * «سود شما از خرید» = تخفیف محصول (قیمت اصلی − قیمت فروش) + کوپن.
                 * The old base − (total − shipping) formula missed sale prices and
                 * counted payment fees against the customer, so the cart page and
                 * the checkout page showed different numbers.
                 */
                $saving = HSW_Helpers::savings();

                return (float) ( $saving['total'] ?? 0 );
        }

        /**
         * Shipping row state: [ 'amount', float ] | [ 'free', 0 ].
         */
        protected function shipping_row() {
                if ( ! $this->has_cart() ) {
                        return array( 'free', 0 );
                }
                $ship = (float) WC()->cart->get_shipping_total();
                if ( $ship > 0 ) {
                        return array( 'amount', $ship );
                }
                return array( 'free', 0 );
        }

        /**
         * Final payable amount (get_total).
         */
        protected function cart_total() {
                if ( ! $this->has_cart() ) {
                        return 0;
                }
                return (float) WC()->cart->get_total( 'edit' );
        }

        /**
         * Total tax amount (0 when taxes disabled).
         */
        protected function taxes_total() {
                if ( ! $this->has_cart() || ! wc_tax_enabled() ) {
                        return 0;
                }
                return (float) WC()->cart->get_taxes_total();
        }

        /* =========================================================================
         *  NONCE / FORM HELPERS
         * ====================================================================== */

        /**
         * WooCommerce cart nonce fields (update_cart verification).
         */
        protected function cart_nonce() {
                return wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce', true, false );
        }

        /**
         * Empty-cart nonce field (custom action).
         */
        protected function empty_nonce() {
                return wp_nonce_field( 'hsw-empty-cart', 'hsw_empty_cart_nonce', true, false );
        }

        /* =========================================================================
         *  DEMO DATA (editor preview only — mirrors the supplied mock)
         * ====================================================================== */

        protected function demo_items() {
                $ph = HSW_URL . 'assets/img/placeholder.svg';
                return array(
                        array(
                                'key'      => 'demo-1',
                                'name'     => 'گوشی موبایل سامسونگ Galaxy S24 Ultra 5G ظرفیت ۲۵۶ گیگابایت',
                                'url'      => '#',
                                'img'      => '<img src="' . esc_url( $ph ) . '" alt="" loading="lazy" />',
                                'eyebrow'  => 'گوشی هوشمند سامسونگ',
                                'chips'    => array(
                                        array( 'label' => 'مشکی تیتانیوم', 'hex' => '#2B2B2D', 'icon' => '' ),
                                        array( 'label' => '۲۵۶ گیگابایت', 'hex' => '', 'icon' => '' ),
                                ),
                                'warranty' => 'گارانتی ۱۸ ماهه شرکتی',
                                'qty'      => 1,
                                'min'      => 1,
                                'max'      => 5,
                                'price'    => 65760000,
                                'regular'  => 68500000,
                                'disc'     => 4,
                                'remove'   => '#',
                        ),
                        array(
                                'key'      => 'demo-2',
                                'name'     => 'شارژر دیواری سامسونگ ۴۵ وات اصل به همراه کابل Type-C',
                                'url'      => '#',
                                'img'      => '<img src="' . esc_url( $ph ) . '" alt="" loading="lazy" />',
                                'eyebrow'  => 'لوازم جانبی اورجینال',
                                'chips'    => array(
                                        array( 'label' => 'رنگ مشکی', 'hex' => '#000000', 'icon' => '' ),
                                ),
                                'warranty' => 'ضمانت ۶ ماهه تعویض اصالت کالا',
                                'qty'      => 1,
                                'min'      => 1,
                                'max'      => 10,
                                'price'    => 1450000,
                                'regular'  => 0,
                                'disc'     => 0,
                                'remove'   => '#',
                        ),
                );
        }

        protected function demo_totals() {
                return array(
                        'count'    => 2,
                        'base'     => 69950000,
                        'savings'  => 2740000,
                        'shipping' => array( 'free', 0 ),
                        'taxes'    => 0,
                        'total'    => 67210000,
                );
        }

        /* =========================================================================
         *  SHARED CONTROLS (same builders as the archive suite)
         * ====================================================================== */

        protected function ctrl_visibility( $default = 'auto' ) {
                $this->add_control(
                        'vis',
                        array(
                                'label'   => __( 'نمایش در', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => $default,
                                'options' => array(
                                        'auto'    => __( 'پیش‌فرض طرح', 'hamishop-elementor-widgets' ),
                                        'desktop' => __( 'فقط دسکتاپ', 'hamishop-elementor-widgets' ),
                                        'mobile'  => __( 'فقط موبایل', 'hamishop-elementor-widgets' ),
                                ),
                        )
                );
        }


        /**
         * Cart palette.
         *
         * Intentionally empty: these eight colours (brand, CTA, text, borders,
         * cards, success, error) live in the admin palette and every --hswc-*
         * token in hsw-cart.css already falls back to it, e.g.
         * `--hswc-card: var(--hsw-pal-surface, #fff)`. Keeping a second copy in
         * the widget only duplicated the settings panel; per-element colour
         * controls (row, image, quantity, totals, buttons) still override.
         *
         * @return array
         */
        protected function palette_map() {
                return array();
        }
}
