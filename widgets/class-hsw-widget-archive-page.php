<?php
/**
 * Composite widget: whole product-archive page (صفحه کامل آرشیو محصولات).
 *
 * Assembles breadcrumb → header → filters sidebar → toolbar → grid →
 * pagination → guide FAQ through HSW_Composite, resolving the live
 * WooCommerce archive term, real filtered queries and real products.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Archive_Page extends HSW_Archive_Base {

        public function get_name() {
                return 'hsw-archive-page';
        }

        public function get_title() {
                return __( 'صفحه کامل آرشیو — حامی (ویجت جامع)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-archive-posts';
        }

        public function get_keywords() {
                return array( 'archive', 'shop', 'category', 'آرشیو', 'فروشگاه', 'جامع', 'hamishop', 'hamisys' );
        }

        public function get_style_depends() {
                return array( 'hsw-fonts', 'hsw-archive', 'hsw-composite' );
        }

        public function get_script_depends() {
                return array( 'hsw-archive' );
        }

        protected function register_controls() {
                $this->part_section( 'abc_', __( '۱. مسیر راهنما (بردکرامب)', 'hamishop-elementor-widgets' ), '\HSW_Widget_Archive_Breadcrumb', array( 'vis', 'show_home_icon', 'home_label' ) );

                $this->part_section( 'ahd_', __( '۲. سربرگ آرشیو', 'hamishop-elementor-widgets' ), '\HSW_Widget_Archive_Header', array( 'vis', 'title_format', 'label', 'show_count', 'seo_desc', 'pills_source', 'pills_attr', 'pills_heading', 'show_guide_btn', 'update_note' ) );

                $this->part_section( 'af_', __( '۳. فیلترها (سایدبار + موبایل)', 'hamishop-elementor-widgets' ), '\HSW_Widget_Archive_Filters', array( 'vis', 'sec_cats', 'sec_brand', 'sec_memory', 'sec_color', 'sec_price', 'sec_switches', 'attr_brand', 'attr_memory', 'attr_color', 'express_note', 'color_map' ) );

                $this->part_section( 'at_', __( '۴. نوار ابزار و تگ‌های فیلتر', 'hamishop-elementor-widgets' ), '\HSW_Widget_Archive_Toolbar', array( 'vis', 'show_tags', 'show_view' ) );

                $this->part_section( 'ag_', __( '۵. شبکه محصولات', 'hamishop-elementor-widgets' ), '\HSW_Widget_Archive_Grid', array( 'vis', 'cols', 'currency', 'show_wishlist', 'express_note', 'express_class', 'city', 'stock_text' ) );

                $this->part_section( 'ap_', __( '۶. صفحه‌بندی', 'hamishop-elementor-widgets' ), '\HSW_Widget_Archive_Pagination', array( 'vis', 'show_state' ) );

                $this->part_section( 'agd_', __( '۷. راهنمای خرید (سوالات متداول)', 'hamishop-elementor-widgets' ), '\HSW_Widget_Archive_Guide', array( 'vis', 'g_title', 'm_intro', 'm_tip', 'faqs' ) );

                $this->start_controls_section(
                        'sec_layout',
                        array( 'label' => __( 'چیدمان صفحه', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_responsive_control(
                        'cp_side',
                        array(
                                'label'      => __( 'عرض ستون فیلتر (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 200, 'max' => 420 ) ),
                                'default'    => array( 'size' => 300, 'unit' => 'px' ),
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
                                'selectors'  => array(
                                        '{{WRAPPER}} .hsw-cp__body'  => 'gap: {{SIZE}}{{UNIT}};',
                                        '{{WRAPPER}} .hsw-cp__guide' => 'margin-top: {{SIZE}}{{UNIT}};',
                                ),
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Render the full archive page.
         */
        protected function render() {
                $root = $this->root_class( 'carchive', 'hsw-cp hsw-cp--archive' );

                echo '<div class="' . esc_attr( $root ) . '">';

                if ( $this->part_on( 'abc_' ) ) {
                        $this->print_part( 'abc_', '\HSW_Widget_Archive_Breadcrumb' );
                }
                if ( $this->part_on( 'ahd_' ) ) {
                        $this->print_part( 'ahd_', '\HSW_Widget_Archive_Header' );
                }

                echo '<div class="hsw-cp__body">';

                if ( $this->part_on( 'af_' ) ) {
                        echo '<div class="hsw-cp__col hsw-cp__col--side">';
                        $this->print_part( 'af_', '\HSW_Widget_Archive_Filters' );
                        echo '</div>';
                }

                echo '<div class="hsw-cp__col hsw-cp__col--main">';
                if ( $this->part_on( 'at_' ) ) {
                        $this->print_part( 'at_', '\HSW_Widget_Archive_Toolbar' );
                }
                if ( $this->part_on( 'ag_' ) ) {
                        $this->print_part( 'ag_', '\HSW_Widget_Archive_Grid' );
                }
                if ( $this->part_on( 'ap_' ) ) {
                        $this->print_part( 'ap_', '\HSW_Widget_Archive_Pagination' );
                }
                echo '</div>';

                echo '</div>'; // .hsw-cp__body

                if ( $this->part_on( 'agd_' ) ) {
                        echo '<div class="hsw-cp__guide">';
                        $this->print_part( 'agd_', '\HSW_Widget_Archive_Guide' );
                        echo '</div>';
                }

                echo '</div>';
        }
}
