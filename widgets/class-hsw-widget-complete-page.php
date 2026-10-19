<?php
/**
 * Composite widget: the complete order-completion (thank-you) page.
 *
 * Assembles success hero → order details (invoice + gateway notes) →
 * delivery info → next steps → closing actions in one drag. Works both on a
 * standalone page (routed by HSW_Complete_Route with ?order&key) and on
 * WooCommerce's own order-received endpoint (fallback, no setting needed).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Complete_Page extends HSW_Complete_Base {

    public function get_name() {
        return 'hsw-complete-page';
    }

    public function get_title() {
        return __( 'صفحه کامل تکمیل خرید — حامی (ویجت جامع)', 'hamishop-elementor-widgets' );
    }

    public function get_icon() {
        return 'eicon-check-circle';
    }

    public function get_keywords() {
        return array( 'complete', 'thank you', 'order received', 'جامع', 'تکمیل خرید', 'سفارش ثبت شد', 'hamishop', 'hamisys' );
    }

    public function get_style_depends() {
        return array( 'hsw-fonts', 'hsw-checkout', 'hsw-complete', 'hsw-composite' );
    }

    protected function register_controls() {
        $this->part_section( 'hero_', __( '۱. سربرگ موفقیت (شماره، تاریخ، وضعیت)', 'hamishop-elementor-widgets' ), '\HSW_Widget_Complete_Hero', array( 'vis', 'show_icon', 'icon', 'title', 'subtitle', 'show_meta', 'fa_digits', 'show_number', 'chip_order_label', 'show_date', 'chip_date_label', 'show_status', 'chip_status_label', 'nf_title', 'nf_text', 'nf_btn_label', 'nf_btn_url', 'nf_shop_label' ) );

        $this->part_section( 'cord_', __( '۲. جزئیات سفارش (اقلام، فاکتور، درگاه)', 'hamishop-elementor-widgets' ), '\HSW_Widget_Complete_Order', array( 'vis', 'title', 'count_tpl', 'show_img', 'subtotal_label', 'discount_label', 'coupon_tpl', 'shipping_label', 'free_text', 'tax_label', 'total_label', 'show_payment', 'payment_label', 'show_paid', 'paid_text', 'unpaid_text', 'show_note', 'note_label', 'show_gateway', 'gateway_title' ) );

        $this->part_section( 'cdel_', __( '۳. اطلاعات تحویل (نام، تلفن، نشانی)', 'hamishop-elementor-widgets' ), '\HSW_Widget_Complete_Delivery', array( 'vis', 'title', 'show_name', 'name_label', 'show_phone', 'phone_label', 'show_address', 'addr_label', 'show_postcode', 'pc_label', 'show_method', 'method_label' ) );

        $this->part_section( 'cstp_', __( '۴. مراحل بعدی سفارش', 'hamishop-elementor-widgets' ), '\HSW_Widget_Complete_Steps', array( 'vis', 'title', 'steps' ) );

        $this->part_section( 'cact_', __( '۵. دکمه‌های پایانی و پشتیبانی', 'hamishop-elementor-widgets' ), '\HSW_Widget_Complete_Actions', array( 'vis', 'p_label', 'p_icon', 'p_url', 'auto_pay', 'pay_label', 's_label', 's_icon', 's_url', 'show_email', 'email_tpl', 'show_support', 'sup_title', 'sup_text', 'sup_phone', 'sup_url' ) );

        /* ------------------------- LAYOUT ------------------------- */
        $this->start_controls_section(
            'sec_layout',
            array( 'label' => __( 'چیدمان صفحه', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
        );

        $this->add_responsive_control(
            'cp_side',
            array(
                'label'      => __( 'عرض ستون کناری (دسکتاپ)', 'hamishop-elementor-widgets' ),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => array( 'px' ),
                'range'      => array( 'px' => array( 'min' => 260, 'max' => 460 ) ),
                'default'    => array( 'size' => 340, 'unit' => 'px' ),
                'selectors'  => array( '{{WRAPPER}} .hsw-cp__body' => '--hsw-cp-side: {{SIZE}}{{UNIT}};' ),
            )
        );

        $this->add_responsive_control(
            'cp_gap',
            array(
                'label'      => __( 'فاصله ستون‌ها', 'hamishop-elementor-widgets' ),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => array( 'px' ),
                'range'      => array( 'px' => array( 'min' => 0, 'max' => 64 ) ),
                'default'    => array( 'size' => 24, 'unit' => 'px' ),
                'selectors'  => array( '{{WRAPPER}} .hsw-cp__body' => 'gap: {{SIZE}}{{UNIT}};' ),
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $root = $this->root_class( 'cpage', 'hsw-cp hsw-cp--complete' );

        echo '<div class="' . esc_attr( $root ) . '">';

        if ( $this->part_on( 'hero_' ) ) {
            $this->print_part( 'hero_', '\HSW_Widget_Complete_Hero' );
        }

        echo '<div class="hsw-cp__body">';

        echo '<div class="hsw-cp__col hsw-cp__col--main">';
        if ( $this->part_on( 'cord_' ) ) {
            $this->print_part( 'cord_', '\HSW_Widget_Complete_Order' );
        }
        if ( $this->part_on( 'cdel_' ) ) {
            $this->print_part( 'cdel_', '\HSW_Widget_Complete_Delivery' );
        }
        echo '</div>';

        echo '<div class="hsw-cp__col hsw-cp__col--side">';
        if ( $this->part_on( 'cstp_' ) ) {
            $this->print_part( 'cstp_', '\HSW_Widget_Complete_Steps' );
        }
        if ( $this->part_on( 'cact_' ) ) {
            $this->print_part( 'cact_', '\HSW_Widget_Complete_Actions' );
        }
        echo '</div>';

        echo '</div>'; // .hsw-cp__body
        echo '</div>';
    }
}
