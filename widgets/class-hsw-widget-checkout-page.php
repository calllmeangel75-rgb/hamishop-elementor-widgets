<?php
/**
 * Composite widget: whole checkout page (صفحه کامل تسویه حساب).
 *
 * Assembles checkout header/steps → address → shipping → schedule → note →
 * items → summary in one drag. WooCommerce forms/fields stay fully live.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Checkout_Page extends HSW_Checkout_Base {

        public function get_name() {
                return 'hsw-checkout-page';
        }

        public function get_title() {
                return __( 'صفحه کامل تسویه حساب — حامی (ویجت جامع)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-checkout';
        }

        public function get_keywords() {
                return array( 'checkout', 'payment', 'تسویه', 'پرداخت', 'جامع', 'hamishop', 'hamisys' );
        }

        public function get_style_depends() {
                return array( 'hsw-fonts', 'hsw-checkout', 'hsw-composite' );
        }

        protected function register_controls() {
                $this->part_section( 'ctop_', __( '۱. هدر و مراحل تسویه', 'hamishop-elementor-widgets' ), '\HSW_Widget_Checkout_Top', array( 'vis', 'sticky', 'title', 'back_url', 'show_support', 'support_label', 'support_phone', 'support_url', 'steps_heading', 'active_step', 'steps' ) );

                $this->part_section( 'caddr_', __( '۲. آدرس و مشخصات گیرنده', 'hamishop-elementor-widgets' ), '\HSW_Widget_Checkout_Address', array( 'vis', 'title_d', 'title_m', 'chip_text', 'add_label_d', 'add_label_m', 'show_edit', 'edit_label', 'change_label', 'manage_url', 'phone_label', 'show_postcode', 'postcode_label' ) );

                $this->part_section( 'cship_', __( '۳. روش ارسال', 'hamishop-elementor-widgets' ), '\HSW_Widget_Checkout_Shipping', array( 'vis', 'title', 'title_m', 'free_text', 'badge_text', 'options' ) );

                $this->part_section( 'csched_', __( '۴. زمان تحویل', 'hamishop-elementor-widgets' ), '\HSW_Widget_Checkout_Schedule', array( 'vis', 'title', 'title_m', 'days_label', 'slots_label', 'slots_label_m', 'days', 'day_active', 'slots', 'slot_active' ) );

                $this->part_section( 'cnote_', __( '۵. یادداشت سفارش و فاکتور', 'hamishop-elementor-widgets' ), '\HSW_Widget_Checkout_Note', array( 'vis', 'label_d', 'placeholder_d', 'rows_d', 'label_m', 'placeholder_m', 'rows_m', 'show_invoice', 'invoice_label' ) );

                $this->part_section( 'citems_', __( '۶. اقلام سفارش', 'hamishop-elementor-widgets' ), '\HSW_Widget_Checkout_Items', array( 'vis', 'title', 'count_tpl' ) );

                $this->part_section( 'csum_', __( '۷. خلاصه سفارش و پرداخت', 'hamishop-elementor-widgets' ), '\HSW_Widget_Checkout_Summary', array( 'vis', 'sticky', 'title_d', 'count_tpl', 'items_label', 'ready_label', 'rows_heading', 'subtotal_tpl', 'discount_label', 'shipping_label', 'shipping_tip', 'taxes_label', 'taxes_included', 'total_label', 'free_text', 'cta_label', 'next_url', 'cta_note', 'trust_heading', 'trust', 'title_m', 'subtotal_tpl_m', 'discount_label_m', 'shipping_tpl_m', 'total_label_m', 'bar_pay_lbl', 'bar_cta' ) );

                $this->start_controls_section(
                        'sec_layout',
                        array( 'label' => __( 'چیدمان صفحه', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_responsive_control(
                        'cp_side',
                        array(
                                'label'      => __( 'عرض ستون خلاصه (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 280, 'max' => 520 ) ),
                                'default'    => array( 'size' => 380, 'unit' => 'px' ),
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

        /**
         * Render the full checkout page.
         */
        protected function render() {
                $root = $this->root_class( 'ccheckout', 'hsw-cp hsw-cp--checkout' );

                echo '<div class="' . esc_attr( $root ) . '">';

                if ( $this->part_on( 'ctop_' ) ) {
                        $this->print_part( 'ctop_', '\HSW_Widget_Checkout_Top' );
                }

                echo '<div class="hsw-cp__body">';

                echo '<div class="hsw-cp__col hsw-cp__col--main">';
                if ( $this->part_on( 'caddr_' ) ) {
                        $this->print_part( 'caddr_', '\HSW_Widget_Checkout_Address' );
                }
                if ( $this->part_on( 'cship_' ) ) {
                        $this->print_part( 'cship_', '\HSW_Widget_Checkout_Shipping' );
                }
                if ( $this->part_on( 'csched_' ) ) {
                        $this->print_part( 'csched_', '\HSW_Widget_Checkout_Schedule' );
                }
                if ( $this->part_on( 'cnote_' ) ) {
                        $this->print_part( 'cnote_', '\HSW_Widget_Checkout_Note' );
                }
                echo '</div>';

                echo '<div class="hsw-cp__col hsw-cp__col--side">';
                if ( $this->part_on( 'citems_' ) ) {
                        $this->print_part( 'citems_', '\HSW_Widget_Checkout_Items' );
                }
                if ( $this->part_on( 'csum_' ) ) {
                        $this->print_part( 'csum_', '\HSW_Widget_Checkout_Summary' );
                }
                echo '</div>';

                echo '</div>'; // .hsw-cp__body
                echo '</div>';
        }
}
