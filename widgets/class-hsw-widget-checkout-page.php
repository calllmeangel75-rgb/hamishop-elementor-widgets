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
                 * «ثبت سفارش» is WooCommerce's own #place_order — the theme styles it, so
                 * these controls stay empty by default and only emit CSS when a colour is
                 * picked (no visual change out of the box).
                 */
                $this->start_controls_section(
                        'sec_place_order',
                        array( 'label' => __( 'دکمهٔ ثبت سفارش (ووکامرس)', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_color( 'po_bg', __( 'پس‌زمینه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} #place_order', 'background-color' );
                $this->a_color( 'po_tx', __( 'متن', 'hamishop-elementor-widgets' ), '{{WRAPPER}} #place_order' );
                $this->a_color( 'po_hover_bg', __( 'پس‌زمینهٔ هاور', 'hamishop-elementor-widgets' ), '{{WRAPPER}} #place_order:hover', 'background-color' );
                $this->add_responsive_control(
                        'po_radius',
                        array(
                                'label'      => __( 'گردی گوشه‌ها', 'hamishop-elementor-widgets' ),
                                'type'       => \Elementor\Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
                                'selectors'  => array( '{{WRAPPER}} #place_order' => 'border-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->end_controls_section();

                /*
                 * One type scale for the whole page: the variables land on the wrapper and
                 * every child widget inherits them.
                 */
                /*
                 * Child widgets are not registered with Elementor, so their Style
                 * tab never reaches the panel. Mirror each style section here.
                 */
                $this->style_section_mirror( 'ctop_', __( 'استایل هدر و مراحل', 'hamishop-elementor-widgets' ), '\HSW_Widget_Checkout_Top' );
                $this->style_section_mirror( 'caddr_', __( 'استایل آدرس و گیرنده', 'hamishop-elementor-widgets' ), '\HSW_Widget_Checkout_Address' );
                $this->style_section_mirror( 'cship_', __( 'استایل روش ارسال', 'hamishop-elementor-widgets' ), '\HSW_Widget_Checkout_Shipping' );
                $this->style_section_mirror( 'csched_', __( 'استایل زمان تحویل', 'hamishop-elementor-widgets' ), '\HSW_Widget_Checkout_Schedule' );
                $this->style_section_mirror( 'cnote_', __( 'استایل یادداشت سفارش', 'hamishop-elementor-widgets' ), '\HSW_Widget_Checkout_Note' );
                $this->style_section_mirror( 'citems_', __( 'استایل اقلام سفارش', 'hamishop-elementor-widgets' ), '\HSW_Widget_Checkout_Items' );
                $this->style_section_mirror( 'csum_', __( 'استایل خلاصه سفارش', 'hamishop-elementor-widgets' ), '\HSW_Widget_Checkout_Summary' );

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
         * Render the full checkout page.
         */
        protected function render() {
                $root = $this->root_class( 'ccheckout', 'hsw-cp hsw-cp--checkout' );

                echo '<div class="' . esc_attr( $root ) . '">';

                /* Prints and clears the WooCommerce notice queue. */
                echo \HSW_Helpers::wc_notices(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WooCommerce.

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
