<?php
/**
 * Widget: completion order card — items, invoice totals, payment method and
 * the gateway's thank-you output (bank-transfer instructions, tracking…).
 * Pure WooCommerce order data; nothing is hard-coded to the demo.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Complete_Order extends HSW_Complete_Base {

    public function get_name() {
        return 'hsw-complete-order';
    }

    public function get_title() {
        return __( 'جزئیات سفارش تکمیل خرید (HamiShop)', 'hamishop-elementor-widgets' );
    }

    public function get_icon() {
        return 'eicon-woocommerce';
    }

    public function get_keywords() {
        return array( 'order', 'invoice', 'complete', 'جزئیات سفارش', 'فاکتور', 'تکمیل خرید', 'hamishop' );
    }

    protected function register_controls() {
        /* ------------------------- CONTENT ------------------------- */
        $this->start_controls_section(
            'sec_content',
            array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
        );

        $this->ctrl_visibility();

        $this->add_control(
            'title',
            array(
                'label'   => __( 'عنوان کارت', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'جزئیات سفارش', 'hamishop-elementor-widgets' ),
            )
        );

        $this->add_control(
            'count_tpl',
            array(
                'label'       => __( 'شمارش اقلام', 'hamishop-elementor-widgets' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => __( '{count} قلم کالا', 'hamishop-elementor-widgets' ),
                'description' => __( '{count} = تعداد اقلام سفارش.', 'hamishop-elementor-widgets' ),
            )
        );

        $this->add_control(
            'show_img',
            array(
                'label'   => __( 'تصویر محصول', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::SWITCHER,
                'default' => 'yes',
            )
        );

        $this->add_control(
            'totals_heading',
            array(
                'label'     => __( 'برچسب‌های مبالغ', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $this->add_control(
            'subtotal_label',
            array(
                'label'   => __( 'جمع سبد', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'جمع سبد خرید', 'hamishop-elementor-widgets' ),
            )
        );

        $this->add_control(
            'discount_label',
            array(
                'label'   => __( 'برچسب تخفیف', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'تخفیف سفارش', 'hamishop-elementor-widgets' ),
            )
        );

        $this->add_control(
            'coupon_tpl',
            array(
                'label'       => __( 'قالب تخفیف کوپن', 'hamishop-elementor-widgets' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => __( 'تخفیف کوپن {code}', 'hamishop-elementor-widgets' ),
                'description' => __( '{code} = کد کوپن.', 'hamishop-elementor-widgets' ),
            )
        );

        $this->add_control(
            'shipping_label',
            array(
                'label'   => __( 'هزینهٔ ارسال', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'هزینهٔ ارسال', 'hamishop-elementor-widgets' ),
            )
        );

        $this->add_control(
            'free_text',
            array(
                'label'   => __( 'متن ارسال رایگان', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'رایگان', 'hamishop-elementor-widgets' ),
            )
        );

        $this->add_control(
            'tax_label',
            array(
                'label'   => __( 'مالیات', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'مالیات', 'hamishop-elementor-widgets' ),
            )
        );

        $this->add_control(
            'total_label',
            array(
                'label'   => __( 'مبلغ کل', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'مبلغ کل پرداختی', 'hamishop-elementor-widgets' ),
            )
        );

        $this->add_control(
            'show_payment',
            array(
                'label'     => __( 'اطلاعات پرداخت', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::SWITCHER,
                'default'   => 'yes',
                'separator' => 'before',
            )
        );

        $this->add_control(
            'payment_label',
            array(
                'label'     => __( 'برچسب روش پرداخت', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::TEXT,
                'default'   => __( 'روش پرداخت', 'hamishop-elementor-widgets' ),
                'condition' => array( 'show_payment' => 'yes' ),
            )
        );

        $this->add_control(
            'show_paid',
            array(
                'label'     => __( 'نشان وضعیت پرداخت', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::SWITCHER,
                'default'   => 'yes',
                'condition' => array( 'show_payment' => 'yes' ),
            )
        );

        $this->add_control(
            'paid_text',
            array(
                'label'     => __( 'متن پرداخت‌شده', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::TEXT,
                'default'   => __( 'پرداخت شده', 'hamishop-elementor-widgets' ),
                'condition' => array( 'show_payment' => 'yes', 'show_paid' => 'yes' ),
            )
        );

        $this->add_control(
            'unpaid_text',
            array(
                'label'     => __( 'متن در انتظار پرداخت', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::TEXT,
                'default'   => __( 'در انتظار پرداخت', 'hamishop-elementor-widgets' ),
                'condition' => array( 'show_payment' => 'yes', 'show_paid' => 'yes' ),
            )
        );

        $this->add_control(
            'show_note',
            array(
                'label'   => __( 'یادداشت مشتری', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::SWITCHER,
                'default' => 'yes',
            )
        );

        $this->add_control(
            'note_label',
            array(
                'label'     => __( 'برچسب یادداشت', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::TEXT,
                'default'   => __( 'یادداشت سفارش', 'hamishop-elementor-widgets' ),
                'condition' => array( 'show_note' => 'yes' ),
            )
        );

        $this->add_control(
            'show_gateway',
            array(
                'label'       => __( 'خروجی درگاه پرداخت', 'hamishop-elementor-widgets' ),
                'type'        => Controls_Manager::SWITCHER,
                'default'     => 'yes',
                'description' => __( 'دستورهای درگاه (کارت‌به‌کارت، کد پیگیری و…) مثل صفحهٔ تشکر خود ووکامرس.', 'hamishop-elementor-widgets' ),
            )
        );

        $this->add_control(
            'gateway_title',
            array(
                'label'     => __( 'عنوان بخش درگاه', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::TEXT,
                'default'   => __( 'اطلاعات پرداخت', 'hamishop-elementor-widgets' ),
                'condition' => array( 'show_gateway' => 'yes' ),
            )
        );

        $this->end_controls_section();

        /* ------------------------- STYLE ------------------------- */
        $this->start_controls_section(
            'sec_style',
            array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
        );

        $this->a_box( 'card', '{{WRAPPER}} .hsw-cdord__card' );
        $this->a_text( 't', '{{WRAPPER}} .hsw-cdord__t', array( 'default' => 16 ) );
        $this->a_text( 'name', '{{WRAPPER}} .hsw-cdord__name', array( 'default' => 13 ) );
        $this->a_box( 'thumb', '{{WRAPPER}} .hsw-cdord__thumb' );
        $this->a_text( 'row', '{{WRAPPER}} .hsw-cdord__row', array( 'default' => 13 ) );
        $this->a_color( 'discount_c', __( 'ردیف تخفیف', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cdord__row.is-discount' );
        $this->a_color( 'free_c', __( 'متن ارسال رایگان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cdord__v.is-free' );
        $this->a_box( 'final', '{{WRAPPER}} .hsw-cdord__final' );
        $this->a_text( 'total', '{{WRAPPER}} .hsw-cdord__final-v', array( 'default' => 18, 'size' => array( 'min' => 12, 'max' => 30 ) ) );

        $this->end_controls_section();
    }

    protected function render() {
        $data = $this->completion_data();
        if ( null === $data ) {
            return; // no order (and not editing) — the hero shows the not-found card.
        }

        $s      = $this->effective_settings();
        $order  = $this->completion_order();
        $totals = $data['totals'];

        echo '<section class="' . esc_attr( $this->root_class( 'cdord' ) ) . '">';
        echo '<div class="hsw-cdord__card">';

        /* ---------- header ---------- */
        echo '<div class="hsw-cdord__hd">';
        echo '<h3 class="hsw-cdord__t">' . esc_html( $this->opt( 'title', __( 'جزئیات سفارش', 'hamishop-elementor-widgets' ) ) ) . '</h3>';
        $count_tpl = $this->opt( 'count_tpl', '' );
        if ( '' !== $count_tpl ) {
            echo '<span class="hsw-cdord__badge">' . esc_html( $this->with_count( $count_tpl, (int) $data['count'] ) ) . '</span>';
        }
        echo '</div>';

        /* ---------- items ---------- */
        $show_img = ( 'yes' === ( $s['show_img'] ?? 'yes' ) );
        if ( ! empty( $data['items'] ) ) {
            echo '<ul class="hsw-cdord__items">';
            foreach ( $data['items'] as $item ) {
                echo '<li class="hsw-cdord__item">';
                if ( $show_img ) {
                    echo '<span class="hsw-cdord__thumb">';
                    if ( '' !== (string) $item['img'] ) {
                        echo $item['img']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WC product image markup.
                    } else {
                        echo HSW_Helpers::icon( 'inventory_2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
                    }
                    echo '</span>';
                }
                echo '<span class="hsw-cdord__name">';
                if ( '' !== (string) $item['url'] ) {
                    echo '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['name'] ) . '</a>';
                } else {
                    echo esc_html( $item['name'] );
                }
                echo '</span>';
                echo '<span class="hsw-cdord__qty">' . esc_html( '×' . HSW_Helpers::fa_num( (int) $item['qty'] ) ) . '</span>';
                echo '<span class="hsw-cdord__price">' . $this->money( (float) $item['total'], true ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- money() escapes.
                echo '</li>';
            }
            echo '</ul>';
        }

        /* ---------- totals ---------- */
        echo '<div class="hsw-cdord__rows">';
        echo '<div class="hsw-cdord__row"><span>' . esc_html( $this->opt( 'subtotal_label', '' ) ) . '</span>';
        echo '<span class="hsw-cdord__v">' . $this->money( (float) $totals['subtotal'], true ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        if ( 0 < (float) $totals['discount'] ) {
            $code  = (string) $totals['coupon_code'];
            $label = ( '' !== $code )
                ? str_replace( '{code}', $code, $this->opt( 'coupon_tpl', '' ) )
                : $this->opt( 'discount_label', '' );
            echo '<div class="hsw-cdord__row is-discount"><span>' . esc_html( $label ) . '</span>';
            echo '<span class="hsw-cdord__v">' . $this->money( -1 * (float) $totals['discount'], true, true ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        echo '<div class="hsw-cdord__row"><span>' . esc_html( $this->opt( 'shipping_label', '' ) ) . '</span>';
        if ( ! empty( $totals['shipping_free'] ) ) {
            echo '<span class="hsw-cdord__v is-free">' . esc_html( $this->opt( 'free_text', __( 'رایگان', 'hamishop-elementor-widgets' ) ) ) . '</span>';
        } else {
            echo '<span class="hsw-cdord__v">' . $this->money( (float) $totals['shipping'], true ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo '</div>';

        if ( 0 < (float) $totals['taxes'] ) {
            echo '<div class="hsw-cdord__row"><span>' . esc_html( $this->opt( 'tax_label', '' ) ) . '</span>';
            echo '<span class="hsw-cdord__v">' . $this->money( (float) $totals['taxes'], true ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        echo '<div class="hsw-cdord__final">';
        echo '<span class="hsw-cdord__final-l">' . esc_html( $this->opt( 'total_label', '' ) ) . '</span>';
        echo '<span class="hsw-cdord__final-v">' . $this->money( (float) $totals['total'], true ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';
        echo '</div>';

        /* ---------- payment ---------- */
        if ( 'yes' === ( $s['show_payment'] ?? 'yes' ) ) {
            $title = (string) $data['payment']['title'];
            if ( '' !== trim( $title ) || ! empty( $data['payment']['needs_payment'] ) ) {
                echo '<div class="hsw-cdord__pay">';
                echo '<span class="hsw-cdord__pay-i">' . HSW_Helpers::icon( 'credit_card' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo '<span class="hsw-cdord__pay-l">' . esc_html( $this->opt( 'payment_label', __( 'روش پرداخت', 'hamishop-elementor-widgets' ) ) ) . '</span>';
                echo '<b class="hsw-cdord__pay-v">' . esc_html( $title ) . '</b>';

                if ( 'yes' === ( $s['show_paid'] ?? 'yes' ) ) {
                    if ( ! empty( $data['payment']['paid'] ) ) {
                        echo '<span class="hsw-cdord__paid is-ok">' . HSW_Helpers::icon( 'check_circle', '', true ) . esc_html( $this->opt( 'paid_text', __( 'پرداخت شده', 'hamishop-elementor-widgets' ) ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    } elseif ( ! empty( $data['payment']['needs_payment'] ) ) {
                        echo '<span class="hsw-cdord__paid is-wait">' . HSW_Helpers::icon( 'schedule', '', true ) . esc_html( $this->opt( 'unpaid_text', __( 'در انتظار پرداخت', 'hamishop-elementor-widgets' ) ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }
                }
                echo '</div>';
            }
        }

        /* ---------- customer note ---------- */
        $note = trim( (string) $data['address']['note'] );
        if ( 'yes' === ( $s['show_note'] ?? 'yes' ) && '' !== $note ) {
            echo '<div class="hsw-cdord__note"><b>' . esc_html( $this->opt( 'note_label', __( 'یادداشت سفارش', 'hamishop-elementor-widgets' ) ) ) . ':</b> ' . esc_html( $note ) . '</div>';
        }

        /* ---------- gateway thank-you output ---------- */
        if ( 'yes' === ( $s['show_gateway'] ?? 'yes' ) ) {
            $notes = ( $order instanceof WC_Order ) ? $this->gateway_notes( $order ) : $this->demo_gateway_notes();
            if ( '' !== $notes ) {
                echo '<div class="hsw-cdord__gateway">';
                echo '<h4 class="hsw-cdord__gw-t">' . HSW_Helpers::icon( 'payments' ) . esc_html( $this->opt( 'gateway_title', __( 'اطلاعات پرداخت', 'hamishop-elementor-widgets' ) ) ) . '</h4>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo '<div class="hsw-cdord__gw-b">' . $notes . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gateway markup, mirrors WooCommerce core.
                echo '</div>';
            }
        }

        echo '</div>'; // .hsw-cdord__card
        echo '</section>';
    }

    /**
     * Demo bank instructions shown in the editor preview only.
     */
    protected function demo_gateway_notes() {
        return '<p>' . esc_html__( 'پرداخت شما با موفقیت انجام شد. کد پیگیری تراکنش: ۸۴۹۲۷۱۵۳', 'hamishop-elementor-widgets' ) . '</p>';
    }
}
