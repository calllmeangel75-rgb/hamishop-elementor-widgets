<?php
/**
 * Composite widget: payment page (صفحه کامل پرداخت).
 *
 * Assembles payment header/steps → order recall → gateway methods → coupon →
 * summary in one drag, reading live WooCommerce session + gateways.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Pay_Page extends HSW_Pay_Base {

        public function get_name() {
                return 'hsw-pay-page';
        }

        public function get_title() {
                return __( 'صفحه کامل پرداخت — حامی (ویجت جامع)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-lock-user';
        }

        public function get_keywords() {
                return array( 'payment', 'gateway', 'pay', 'پرداخت', 'درگاه', 'جامع', 'hamishop', 'hamisys' );
        }

        public function get_style_depends() {
                return array( 'hsw-fonts', 'hsw-checkout', 'hsw-composite' );
        }

        protected function register_controls() {
                $this->part_section( 'paytop_', __( '۱. هدر پرداخت و مراحل', 'hamishop-elementor-widgets' ), '\HSW_Widget_Pay_Top', array( 'vis', 'sticky', 'brand_text', 'brand_url', 'brand_tag', 'nav_steps', 'active_step', 'show_ssl', 'ssl_text', 'show_support', 'support_phone', 'support_url', 'prog_steps', 'm_title', 'm_subtitle', 'back_url' ) );

                $this->part_section( 'prec_', __( '۲. یادآوری سفارش (آدرس، روش، زمان)', 'hamishop-elementor-widgets' ), '\HSW_Widget_Pay_Recall', array( 'vis', 'title_d', 'edit_label', 'edit_url', 'name_label', 'addr_label', 'method_label', 'slot_label', 'slot_text', 'title_m', 'm_edit_label', 'm_delivery', 'free_text' ) );

                $this->part_section( 'pm_', __( '۳. روش‌های پرداخت (درگاه‌ها و کیف پول)', 'hamishop-elementor-widgets' ), '\HSW_Widget_Pay_Methods', array( 'vis', 'source', 'shaparak_note', 'title_m', 'ribbon_text', 'm_badge_text', 'banks', 'show_wallet', 'wallet_title', 'wallet_desc', 'wallet_title_m', 'wallet_desc_m', 'wallet_switch' ) );

                $this->part_section( 'pcpn_', __( '۴. کد تخفیف', 'hamishop-elementor-widgets' ), '\HSW_Widget_Pay_Coupon', array( 'vis', 'title', 'applied_tpl', 'deduct_tpl', 'remove_label', 'placeholder', 'button_label', 'm_placeholder', 'm_button_label', 'error_text' ) );

                $this->part_section( 'psum_', __( '۵. خلاصه نهایی و پرداخت', 'hamishop-elementor-widgets' ), '\HSW_Widget_Pay_Summary', array( 'vis', 'sticky', 'title_d', 'count_tpl', 'subtotal_tpl', 'discount_label', 'coupon_tpl', 'shipping_label', 'free_note', 'total_label', 'taxes_note', 'cta_label', 'terms_link', 'terms_text', 'badges', 'show_help', 'help_title', 'help_text', 'subtotal_tpl_m', 'discount_label_m', 'shipping_label_m', 'free_note_m', 'total_label_m', 'terms_text_m', 'bar_pay_lbl', 'bar_cta' ) );

                $this->start_controls_section(
                        'sec_layout',
                        array( 'label' => __( 'چیدمان صفحه', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->style_columns(
                	'cp',
                	__( 'عرض ستون خلاصه (دسکتاپ)', 'hamishop-elementor-widgets' ),
                	array(
                		'side'       => 380,
                		'gap'        => 24,
                		'side_range' => array(
                			'px' => array( 'min' => 280, 'max' => 520 ),
                			'%'  => array( 'min' => 15, 'max' => 50 ),
                		),
                	)
                );
                $this->end_controls_section();

                /*
                 * One type scale for the whole page: the variables land on the wrapper and
                 * every child widget inherits them.
                 */
                $this->style_typography_section(
                	'cp',
                	array(
                		'title'  => array( __( 'عنوان بخش‌ها', 'hamishop-elementor-widgets' ), 16 ),
                		'text'   => array( __( 'متن‌ها', 'hamishop-elementor-widgets' ), 14 ),
                		'price'  => array( __( 'قیمت‌ها', 'hamishop-elementor-widgets' ), 18 ),
                		'button' => array( __( 'دکمه‌ها', 'hamishop-elementor-widgets' ), 14 ),
                	)
                );
        }

        /**
         * Render the full payment page.
         */
        protected function render() {
                $root = $this->root_class( 'cpay', 'hsw-cp hsw-cp--pay' );

                /* Order-received (thank-you): the payment is done — show the
                 * confirmation card instead of the pay UI. */
                if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
                        echo '<div class="' . esc_attr( $root ) . '">';
                        $this->render_order_received( $this->order_received_order() );
                        echo '</div>';
                        return;
                }

                /* Order-pay (redirect gateways / pay-links): WooCommerce's own pay
                 * form owns this state — render it so the payment can complete. */
                if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) {
                        echo '<div class="' . esc_attr( $root ) . '">';
                        echo do_shortcode( '[woocommerce_checkout]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce template.
                        echo '</div>';
                        return;
                }

                echo '<div class="' . esc_attr( $root ) . '">';

                /* Prints and clears the WooCommerce notice queue. */
                echo \HSW_Helpers::wc_notices(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WooCommerce.

                if ( $this->part_on( 'paytop_' ) ) {
                        $this->print_part( 'paytop_', '\HSW_Widget_Pay_Top' );
                }
                if ( $this->part_on( 'prec_' ) ) {
                        $this->print_part( 'prec_', '\HSW_Widget_Pay_Recall' );
                }

                echo '<div class="hsw-cp__body">';

                echo '<div class="hsw-cp__col hsw-cp__col--main">';
                if ( $this->part_on( 'pm_' ) ) {
                        $this->print_part( 'pm_', '\HSW_Widget_Pay_Methods' );
                }
                if ( $this->part_on( 'pcpn_' ) ) {
                        $this->print_part( 'pcpn_', '\HSW_Widget_Pay_Coupon' );
                }
                echo '</div>';

                if ( $this->part_on( 'psum_' ) ) {
                        echo '<div class="hsw-cp__col hsw-cp__col--side">';
                        $this->print_part( 'psum_', '\HSW_Widget_Pay_Summary' );
                        echo '</div>';
                }

                echo '</div>'; // .hsw-cp__body
                echo '</div>';
        }
}
