<?php
/**
 * Base for the «تکمیل خرید» (order completion / thank-you) suite.
 *
 * WooCommerce's own `woocommerce_get_checkout_order_received_url` filter — the
 * single URL source every gateway, WC core and the Store API use — routes the
 * customer to a real, designable Elementor page (order id + key appended).
 * The parts resolve the order from:
 *   - WooCommerce's order-received endpoint (checkout-page fallback), or
 *   - `?order={id}&key={key}` of the standalone completion page.
 * The Elementor editor shows a full demo dataset instead (demo-guard: real
 * pages never render demo data).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

abstract class HSW_Complete_Base extends HSW_Pay_Base {

    /** @var WC_Order|false|null Cached order lookup (null = not resolved yet). */
    private $completion_cache = null;

    public function get_style_depends() {
        return array( 'hsw-fonts', 'hsw-checkout', 'hsw-complete' );
    }

    public function get_script_depends() {
        return array();
    }

    /* =========================================================================
     *  ORDER RESOLUTION
     * ====================================================================== */

    /**
     * The order this completion view must show, or false.
     * Same access rules as WooCommerce's own thank-you: a valid order key
     * (guests included) or the logged-in owner.
     *
     * @return WC_Order|false
     */
    protected function completion_order() {
        if ( null !== $this->completion_cache ) {
            return $this->completion_cache;
        }
        $this->completion_cache = $this->resolve_completion_order();
        return $this->completion_cache;
    }

    /**
     * Uncached lookup (see completion_order()).
     *
     * @return WC_Order|false
     */
    private function resolve_completion_order() {
        if ( ! function_exists( 'wc_get_order' ) ) {
            return false;
        }

        $order = false;
        if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
            /* Classic flow: checkout page + order-received endpoint. */
            $order_id = absint( get_query_var( 'order-received' ) );
            if ( $order_id ) {
                $order = wc_get_order( $order_id );
            }
        } elseif ( isset( $_GET['order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- order key is the auth.
            /* Standalone completion page: ?order={id}&key={key}. */
            $order = wc_get_order( absint( wp_unslash( $_GET['order'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        if ( ! $order instanceof WC_Order ) {
            return false;
        }

        $key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( '' !== $key ) {
            return $order->key_is_valid( $key ) ? $order : false;
        }
        if ( is_user_logged_in() && (int) $order->get_customer_id() === get_current_user_id() ) {
            return $order;
        }
        return false;
    }

    /**
     * View state of this completion render: 'order' | 'demo' | 'none'.
     */
    protected function complete_state() {
        if ( false !== $this->completion_order() ) {
            return 'order';
        }
        return $this->is_editing() ? 'demo' : 'none';
    }

    /**
     * Normalized display payload for every completion part.
     * Real order on the front-end; demo dataset while editing; null otherwise.
     *
     * @return array|null
     */
    protected function completion_data() {
        $order = $this->completion_order();
        if ( $order instanceof WC_Order ) {
            return $this->order_completion_data( $order );
        }
        if ( 'demo' === $this->complete_state() ) {
            return $this->demo_completion_data();
        }
        return null;
    }

    /**
     * Real-order payload.
     *
     * @param WC_Order $order Order.
     * @return array
     */
    protected function order_completion_data( $order ) {
        /* -------- items -------- */
        $items = array();
        foreach ( $order->get_items() as $item ) {
            if ( ! $item instanceof WC_Order_Item_Product ) {
                continue;
            }
            $product = method_exists( $item, 'get_product' ) ? $item->get_product() : null;
            $items[] = array(
                'name'  => (string) $item->get_name(),
                'qty'   => max( 1, (int) $item->get_quantity() ),
                'total' => (float) $order->get_line_total( $item, true ),
                'url'   => $product ? (string) $product->get_permalink() : '',
                'img'   => $product ? $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ) : '',
            );
        }

        /* -------- totals -------- */
        $codes     = method_exists( $order, 'get_coupon_codes' ) ? (array) $order->get_coupon_codes() : array();
        $shipping  = (float) $order->get_shipping_total();
        $totals    = array(
            'subtotal'      => (float) $order->get_subtotal(),
            'discount'      => (float) $order->get_discount_total(),
            'coupon_code'   => $codes ? (string) $codes[0] : '',
            'shipping'      => $shipping,
            'shipping_free' => ( $shipping <= 0 ),
            'taxes'         => (float) $order->get_total_tax(),
            'total'         => (float) $order->get_total(),
        );

        /* -------- customer / delivery -------- */
        $name = method_exists( $order, 'get_formatted_shipping_full_name' ) ? trim( (string) $order->get_formatted_shipping_full_name() ) : '';
        if ( '' === $name && method_exists( $order, 'get_formatted_billing_full_name' ) ) {
            $name = trim( (string) $order->get_formatted_billing_full_name() );
        }
        $phone = method_exists( $order, 'get_shipping_phone' ) ? (string) $order->get_shipping_phone() : '';
        if ( '' === trim( $phone ) ) {
            $phone = (string) $order->get_billing_phone();
        }
        $postcode = (string) $order->get_shipping_postcode();
        if ( '' === trim( $postcode ) ) {
            $postcode = (string) $order->get_billing_postcode();
        }

        return array(
            'number'   => (string) $order->get_order_number(),
            'date'     => $this->order_date( $order ),
            'status'   => array(
                'key'   => (string) $order->get_status(),
                'label' => function_exists( 'wc_get_order_status_name' ) ? (string) wc_get_order_status_name( $order->get_status() ) : (string) $order->get_status(),
                'icon'  => $this->status_icon( (string) $order->get_status() ),
            ),
            'items'    => $items,
            'count'    => (int) $order->get_item_count(),
            'totals'   => $totals,
            'payment'  => array(
                'title'         => (string) $order->get_payment_method_title(),
                'paid'          => (bool) $order->is_paid(),
                'needs_payment' => (bool) $order->needs_payment(),
            ),
            'address'  => array(
                'name'     => $name,
                'phone'    => $phone,
                'line'     => $this->order_address_line( $order ),
                'postcode' => $postcode,
                'email'    => (string) $order->get_billing_email(),
                'note'     => (string) $order->get_customer_note(),
            ),
            'shipping' => array(
                'method' => (string) $order->get_shipping_method(),
            ),
            'info'     => array(
                'id'       => (int) $order->get_id(),
                'view_url' => (string) $order->get_view_order_url(),
                'pay_url'  => $order->needs_payment() ? (string) $order->get_checkout_payment_url() : '',
            ),
        );
    }

    /**
     * Demo payload for the Elementor editor (mirrors the payment-flow demo).
     *
     * @return array
     */
    protected function demo_completion_data() {
        $ph     = HSW_URL . 'assets/img/placeholder.svg';
        $cust   = $this->demo_pay_customer();
        $ship   = $this->demo_pay_shipping();
        $items  = array(
            array(
                'name'  => 'گوشی موبایل سامسونگ Galaxy S24 Ultra 5G ظرفیت ۲۵۶ گیگابایت',
                'qty'   => 1,
                'total' => 65760000,
                'url'   => '#',
                'img'   => '<img src="' . esc_url( $ph ) . '" alt="" loading="lazy" />',
            ),
            array(
                'name'  => 'شارژر دیواری سامسونگ ۴۵ وات اصل به همراه کابل Type-C',
                'qty'   => 1,
                'total' => 1450000,
                'url'   => '#',
                'img'   => '<img src="' . esc_url( $ph ) . '" alt="" loading="lazy" />',
            ),
            array(
                'name'  => 'کاور سیلیکونی شفاف ضدضربه',
                'qty'   => 1,
                'total' => 130000,
                'url'   => '#',
                'img'   => '<img src="' . esc_url( $ph ) . '" alt="" loading="lazy" />',
            ),
        );

        return array(
            'number'   => '14005',
            'date'     => '۱۹ مهر ۱۴۰۵',
            'status'   => array(
                'key'   => 'processing',
                'label' => 'در حال انجام',
                'icon'  => 'schedule',
            ),
            'items'    => $items,
            'count'    => 3,
            'totals'   => array(
                'subtotal'      => 67340000,
                'discount'      => 50000,
                'coupon_code'   => 'WELCOME',
                'shipping'      => 0,
                'shipping_free' => true,
                'taxes'         => 0,
                'total'         => 67290000,
            ),
            'payment'  => array(
                'title'         => 'پرداخت اینترنتی (با کلیه کارت‌های عضو شبکه شتاب)',
                'paid'          => true,
                'needs_payment' => false,
            ),
            'address'  => array(
                'name'     => $cust['name'],
                'phone'    => $cust['phone'],
                'line'     => $cust['line'],
                'postcode' => '۱۹۹۸۷۶۵۴۳۲',
                'email'    => 'customer@example.com',
                'note'     => '',
            ),
            'shipping' => array(
                'method' => $ship['method'],
            ),
            'info'     => array(
                'id'       => 14005,
                'view_url' => '#',
                'pay_url'  => '',
            ),
        );
    }

    /**
     * Order creation date in the store's own calendar (Jalali when available).
     *
     * `wc_format_datetime()` always prints the Gregorian calendar, which made
     * the thank-you page disagree with every other date on a Persian store.
     *
     * @param WC_Order $order Order.
     * @return string
     */
    protected function order_date( $order ) {
        $created = $order->get_date_created();
        if ( ! $created instanceof WC_DateTime ) {
            return function_exists( 'wc_format_datetime' ) ? (string) wc_format_datetime( $order->get_date_created() ) : '';
        }

        return HSW_Helpers::format_date( $created->getTimestamp(), 'j F Y، H:i' );
    }

    /**
     * One-line delivery address read from the ORDER (shipping → billing).
     *
     * @param WC_Order $order Order.
     * @return string
     */
    protected function order_address_line( $order ) {
        $line1 = trim( (string) $order->get_shipping_address_1() );
        $line2 = trim( (string) $order->get_shipping_address_2() );
        $city  = trim( (string) $order->get_shipping_city() );
        if ( '' === $line1 && '' === $city ) {
            $line1 = trim( (string) $order->get_billing_address_1() );
            $line2 = trim( (string) $order->get_billing_address_2() );
            $city  = trim( (string) $order->get_billing_city() );
        }

        $country = (string) $order->get_shipping_country();
        $state   = (string) $order->get_shipping_state();
        if ( '' === $country ) {
            $country = (string) $order->get_billing_country();
        }
        if ( '' === $state ) {
            $state = (string) $order->get_billing_state();
        }

        $state_name = '';
        if ( '' !== $state && function_exists( 'WC' ) && WC()->countries ) {
            $list = WC()->countries->get_states( $country );
            if ( is_array( $list ) && isset( $list[ $state ] ) ) {
                $state_name = (string) $list[ $state ];
            } elseif ( ! is_numeric( $state ) ) {
                $state_name = $state;
            }
        }

        $parts = array();
        foreach ( array( $state_name, $city, $line1, $line2 ) as $part ) {
            if ( '' !== trim( (string) $part ) ) {
                $parts[] = trim( (string) $part );
            }
        }
        return implode( '، ', $parts );
    }

    /**
     * Gateway thank-you output (bank-transfer instructions, tracking codes, …)
     * exactly like WooCommerce's own thank-you template.
     *
     * @param WC_Order $order Order.
     * @return string
     */
    protected function gateway_notes( $order ) {
        if ( ! $order instanceof WC_Order ) {
            return '';
        }
        $method = (string) $order->get_payment_method();
        if ( '' === $method ) {
            return '';
        }
        ob_start();
        do_action( 'woocommerce_thankyou_' . $method, $order->get_id() );
        return trim( (string) ob_get_clean() );
    }

    /**
     * Status → icon (bundled subset only).
     *
     * @param string $status Order status slug.
     * @return string
     */
    protected function status_icon( $status ) {
        if ( 'completed' === $status ) {
            return 'check_circle';
        }
        if ( in_array( $status, array( 'cancelled', 'failed', 'refunded' ), true ) ) {
            return 'close';
        }
        return 'schedule';
    }

    /**
     * Order number ready for display (Persian digits optional).
     *
     * @param array $data Completion payload.
     * @param bool  $fa   Convert digits.
     * @return string
     */
    protected function number_display( $data, $fa = true ) {
        $number = (string) $data['number'];
        if ( $fa ) {
            $number = HSW_Helpers::fa_num( $number );
        }
        return '#' . $number;
    }

    /**
     * My-account URL (login/orders) with fallback.
     *
     * @return string
     */
    protected function account_url() {
        if ( function_exists( 'wc_get_page_permalink' ) && function_exists( 'wc_get_page_id' ) && 0 < (int) wc_get_page_id( 'myaccount' ) ) {
            return wc_get_page_permalink( 'myaccount' );
        }
        return $this->shop_url();
    }

    /**
     * Icon choices for the completion suite (bundled subset only).
     */
    public static function complete_icon_choices() {
        return array(
            'check_circle'       => 'تیک دایره‌ای',
            'done'               => 'تیک',
            'verified'           => 'تأییدشده',
            'star'               => 'ستاره',
            'favorite'           => 'قلب',
            'local_activity'     => 'برچسب',
            'rocket_launch'      => 'پرتاب موشک',
            'inventory_2'        => 'بسته‌بندی',
            'local_shipping'     => 'ارسال',
            'confirmation_number'=> 'کد سفارش',
            'schedule'           => 'ساعت',
            'edit_note'          => 'یادداشت',
            'payments'           => 'پرداخت',
            'receipt_long'       => 'فاکتور',
            'support_agent'      => 'پشتیبانی',
            'shopping_cart'      => 'سبد خرید',
            'shopping_bag'       => 'کیف خرید',
            'home'               => 'خانه',
            'mail'               => 'ایمیل',
            'location_on'        => 'موقعیت',
            'person'             => 'شخص',
            'phone_iphone'       => 'موبایل',
            'help'               => 'راهنما',
        );
    }
}
