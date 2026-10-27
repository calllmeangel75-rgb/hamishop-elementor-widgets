<?php
/**
 * Composite widget: whole cart page (صفحه کامل سبد خرید).
 *
 * Assembles cart header/steps → free-shipping banner → items → coupon →
 * summary through HSW_Composite, with live WC()->cart data everywhere.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Cart_Page extends HSW_Cart_Base {

        public function get_name() {
                return 'hsw-cart-page';
        }

        public function get_title() {
                return __( 'صفحه کامل سبد خرید — حامی (ویجت جامع)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-cart';
        }

        public function get_keywords() {
                return array( 'cart', 'basket', 'سبد خرید', 'جامع', 'hamishop', 'hamisys' );
        }

        public function get_style_depends() {
                return array( 'hsw-fonts', 'hsw-cart', 'hsw-composite' );
        }

        protected function register_controls() {
                $this->part_section( 'ctop_', __( '۱. هدر و مراحل خرید', 'hamishop-elementor-widgets' ), '\HSW_Widget_Cart_Top', array( 'vis', 'show_bar', 'brand_name', 'brand_url', 'secure_text', 'show_bc', 'bc_home', 'bc_current', 'steps_heading', 'active_step', 'steps', 'm_title', 'm_count', 'show_empty_btn' ) );

                $this->part_section( 'cbanner_', __( '۲. بنر ارسال رایگان', 'hamishop-elementor-widgets' ), '\HSW_Widget_Cart_Banner', array( 'vis', 'threshold', 'title_active', 'desc_active', 'title_inactive', 'desc_inactive', 'show_pct', 'pct_text' ) );

                $this->part_section( 'citems_', __( '۳. اقلام سبد خرید', 'hamishop-elementor-widgets' ), '\HSW_Widget_Cart_Items', array( 'vis', 'box_title', 'count_tpl', 'show_empty_btn', 'empty_btn_label', 'show_eyebrow', 'show_chips', 'show_warranty', 'warehouse_text', 'show_old', 'show_disc', 'disc_tpl', 'show_favorite', 'show_del', 'empty_title', 'empty_desc', 'empty_btn' ) );

                $this->part_section( 'ccoupon_', __( '۴. کد تخفیف', 'hamishop-elementor-widgets' ), '\HSW_Widget_Cart_Coupon', array( 'vis', 'title', 'desc', 'placeholder', 'btn_label', 'm_title', 'm_placeholder', 'show_foot', 'back_label', 'tax_note' ) );

                $this->part_section( 'csummary_', __( '۵. خلاصه سفارش و پرداخت', 'hamishop-elementor-widgets' ), '\HSW_Widget_Cart_Summary', array( 'vis', 'sticky', 'show_inv', 'inv_text', 'rows_heading', 'base_label_d', 'base_label_m', 'show_savings', 'savings_label', 'ship_label_d', 'ship_label_m', 'free_label', 'show_taxes', 'taxes_label', 'taxes_note', 'final_label', 'final_note', 'cta_label', 'next_url', 'trust_heading', 'trust', 'show_help', 'help_title', 'help_text', 'help_phone', 'show_guarantee', 'guarantee_text', 'show_mbar', 'mbar_label', 'mbar_cta' ) );

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

                /* ---- دکمه‌های سفارش ---- */
                $this->a_color( 'ob_bg', __( 'دکمهٔ تکمیل سفارش — پس‌زمینه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-csummary__cta', 'background-color' );
                $this->a_color( 'ob_tx', __( 'دکمهٔ تکمیل سفارش — متن', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-csummary__cta' );
                $this->a_color( 'ob_hover_bg', __( 'دکمهٔ تکمیل سفارش — هاور', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-csummary__cta:hover', 'background-color' );
                $this->a_color( 'ob_mbg', __( 'دکمهٔ نوار موبایل — پس‌زمینه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-csummary__mcta', 'background-color' );
                $this->a_color( 'ob_mtx', __( 'دکمهٔ نوار موبایل — متن', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-csummary__mcta' );
                $this->end_controls_section();

                /*
                 * One type scale for the whole page: the variables land on the wrapper and
                 * every child widget inherits them.
                 */
                /*
                 * Child widgets are not registered with Elementor, so their own
                 * Style tab is unreachable. Mirror the five style sections into
                 * this widget: every colour/slider/dimensions control lands here
                 * with the same selector and responsive flags.
                 */
                $this->style_section_mirror( 'ctop_', __( 'استایل هدر و مراحل', 'hamishop-elementor-widgets' ), '\HSW_Widget_Cart_Top' );
                $this->style_section_mirror( 'cbanner_', __( 'استایل بنر ارسال رایگان', 'hamishop-elementor-widgets' ), '\HSW_Widget_Cart_Banner' );
                $this->style_section_mirror( 'citems_', __( 'استایل اقلام سبد', 'hamishop-elementor-widgets' ), '\HSW_Widget_Cart_Items' );
                $this->style_section_mirror( 'ccoupon_', __( 'استایل کد تخفیف', 'hamishop-elementor-widgets' ), '\HSW_Widget_Cart_Coupon' );
                $this->style_section_mirror( 'csummary_', __( 'استایل خلاصه سفارش', 'hamishop-elementor-widgets' ), '\HSW_Widget_Cart_Summary' );

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
         * Render the full cart page.
         */
        protected function render() {
                /*
                 * An empty cart must not keep the two-column grid: the empty
                 * state ended up in the main column with a summary beside it.
                 * In that case only the items block renders, full width and
                 * centred.
                 */
                $is_empty = ( ! $this->is_demo() && function_exists( 'WC' ) && WC()->cart && WC()->cart->is_empty() );

                $root = $this->root_class( 'ccart', 'hsw-cp hsw-cp--cart' . ( $is_empty ? ' is-empty' : '' ) );

                echo '<div class="' . esc_attr( $root ) . '">';

                /*
                 * Prints and clears the WooCommerce notice queue. The custom
                 * cart template never did, so a rejected coupon said nothing
                 * and "cart updated" messages accumulated in the session.
                 */
                echo \HSW_Helpers::wc_notices(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WooCommerce.

                if ( $this->part_on( 'ctop_' ) ) {
                        $this->print_part( 'ctop_', '\HSW_Widget_Cart_Top' );
                }
                if ( ! $is_empty && $this->part_on( 'cbanner_' ) ) {
                        $this->print_part( 'cbanner_', '\HSW_Widget_Cart_Banner' );
                }

                if ( $is_empty ) {
                        /* Full-width, centred empty state; no coupon, no summary. */
                        echo '<div class="hsw-cp__body hsw-cp__body--empty">';
                        if ( $this->part_on( 'citems_' ) ) {
                                $this->print_part( 'citems_', '\HSW_Widget_Cart_Items' );
                        }
                        echo '</div></div>';
                        return;
                }

                echo '<div class="hsw-cp__body">';

                echo '<div class="hsw-cp__col hsw-cp__col--main">';
                if ( $this->part_on( 'citems_' ) ) {
                        $this->print_part( 'citems_', '\HSW_Widget_Cart_Items' );
                }
                if ( $this->part_on( 'ccoupon_' ) ) {
                        $this->print_part( 'ccoupon_', '\HSW_Widget_Cart_Coupon' );
                }
                echo '</div>';

                if ( $this->part_on( 'csummary_' ) ) {
                        echo '<div class="hsw-cp__col hsw-cp__col--side">';
                        $this->print_part( 'csummary_', '\HSW_Widget_Cart_Summary' );
                        echo '</div>';
                }

                echo '</div>'; // .hsw-cp__body
                echo '</div>';
        }
}
