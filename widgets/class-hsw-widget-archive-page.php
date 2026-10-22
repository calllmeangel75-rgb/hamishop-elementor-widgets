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

                $this->part_section( 'ahd_', __( '۲. سربرگ آرشیو', 'hamishop-elementor-widgets' ), '\HSW_Widget_Archive_Header', array( 'vis', 'title_format', 'show_count', 'seo_desc', 'desc_pos', 'desc_title', 'desc_clamp', 'more_text', 'less_text', 'pills_source', 'pills_attr', 'pills_manual', 'pills_heading', 'show_guide_btn', 'update_note' ) );

                $this->part_section( 'af_', __( '۳. فیلترها (سایدبار + موبایل)', 'hamishop-elementor-widgets' ), '\HSW_Widget_Archive_Filters', array( 'vis', 'f_title', 'boxes', 'auto_attrs', 'express_slug', 'color_map' ) );

                $this->part_section( 'at_', __( '۴. نوار ابزار و تگ‌های فیلتر', 'hamishop-elementor-widgets' ), '\HSW_Widget_Archive_Toolbar', array( 'vis', 'show_tags', 'show_view' ) );

                $this->part_section( 'ag_', __( '۵. شبکه محصولات', 'hamishop-elementor-widgets' ), '\HSW_Widget_Archive_Grid', array( 'vis', 'cols', 'currency', 'show_wishlist', 'express_note', 'express_slug', 'city', 'stock_text' ) );

                $this->part_section( 'ap_', __( '۶. صفحه‌بندی', 'hamishop-elementor-widgets' ), '\HSW_Widget_Archive_Pagination', array( 'vis', 'show_state' ) );

                $this->part_section( 'agd_', __( '۷. راهنمای خرید (سوالات متداول)', 'hamishop-elementor-widgets' ), '\HSW_Widget_Archive_Guide', array( 'vis', 'g_title', 'faqs', 'm_intro', 'm_tip', 'faq_schema' ) );

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
                                        '{{WRAPPER}} .hsw-cp__desc'  => 'margin-top: {{SIZE}}{{UNIT}};',
                                ),
                        )
                );

                $this->end_controls_section();

                /* ---- archive description typography (shop + category archives) ---- */
                $this->start_controls_section(
                        'sec_desc_typo',
                        array( 'label' => __( 'متن توضیحات آرشیو', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_responsive_control(
                        'desc_fs',
                        array(
                                'label'      => __( 'اندازهٔ فونت توضیحات', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 10, 'max' => 26 ) ),
                                'default'    => array( 'size' => 14, 'unit' => 'px' ),
                                'selectors'  => array(
                                        '{{WRAPPER}} .hsw-ahd__desc' => 'font-size: {{SIZE}}{{UNIT}};',
                                ),
                        )
                );

                $this->a_align(
                        'desc',
                        __( 'تراز، جهت و ارتفاع خط (دسکتاپ)', 'hamishop-elementor-widgets' ),
                        '{{WRAPPER}} .hsw-ahd__desc',
                        array( 'align' => 'justify', 'lh' => 1.9 )
                );

                $this->add_responsive_control(
                        'mdesc_fs',
                        array(
                                'label'      => __( 'اندازهٔ فونت توضیحات (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 10, 'max' => 22 ) ),
                                'default'    => array( 'size' => 12, 'unit' => 'px' ),
                                'selectors'  => array(
                                        '{{WRAPPER}} .hsw-ahd__mdesc' => 'font-size: {{SIZE}}{{UNIT}};',
                                ),
                        )
                );

                $this->a_align(
                        'mdesc',
                        __( 'تراز، جهت و ارتفاع خط (موبایل)', 'hamishop-elementor-widgets' ),
                        '{{WRAPPER}} .hsw-ahd__mdesc',
                        array( 'align' => 'justify', 'lh' => 1.9 )
                );

                $this->a_button(
                        'apply',
                        __( 'دکمهٔ اعمال فیلتر', 'hamishop-elementor-widgets' ),
                        '{{WRAPPER}} .hsw-af__apply',
                        array( 'size' => 12, 'radius' => 9 )
                );

                $this->end_controls_section();

                /* Every part's full Style tab, mirrored here so the complete
                   archive page is editable from one place (colors, gradient,
                   buttons, hover/active states…) — like the single widgets. */
                $this->part_style_section( 'af_', __( 'استایل فیلترها', 'hamishop-elementor-widgets' ), '\HSW_Widget_Archive_Filters' );
                $this->part_style_section( 'at_', __( 'استایل نوار ابزار', 'hamishop-elementor-widgets' ), '\HSW_Widget_Archive_Toolbar' );
                $this->part_style_section( 'ag_', __( 'استایل کارت محصول', 'hamishop-elementor-widgets' ), '\HSW_Widget_Archive_Grid' );
                $this->part_style_section( 'ap_', __( 'استایل صفحه‌بندی', 'hamishop-elementor-widgets' ), '\HSW_Widget_Archive_Pagination' );
                $this->part_style_section( 'agd_', __( 'استایل راهنمای خرید', 'hamishop-elementor-widgets' ), '\HSW_Widget_Archive_Guide' );
                $this->part_style_section( 'abc_', __( 'استایل مسیر راهنما', 'hamishop-elementor-widgets' ), '\HSW_Widget_Archive_Breadcrumb' );
        }

        /**
         * Render the full archive page.
         */
        protected function render() {
                $root = $this->root_class( 'carchive', 'hsw-cp hsw-cp--archive' );

                /*
                 * The Elementor full-width archive template replaces the
                 * theme's #content wrapper, which breaks the theme skip link
                 * and leaves the page without a main landmark. The composite
                 * is the archive's main content, so provide both here. Skip
                 * this in the editor demo, where the theme wrapper may exist.
                 */
                $main_attrs = $this->is_demo() ? '' : ' id="content" role="main" tabindex="-1"';
                echo '<div class="' . esc_attr( $root ) . '"' . $main_attrs . '>';

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

                /* Archive description at the bottom — same copy as the header,
                   never rendered twice. Sits before the guide so the buying
                   guide stays the last widget on the page. */
                $ahd = $this->part_settings( 'ahd_' );
                if ( $this->part_on( 'ahd_' ) && isset( $ahd['desc_pos'] ) && 'bottom' === $ahd['desc_pos'] ) {
                        $desc_box = $this->archive_desc_block_html( $ahd, 'box' );
                        if ( '' !== $desc_box ) {
                                echo '<div class="hsw-cp__desc">' . $desc_box . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe HTML from archive_desc_block_html().
                        }
                }

                if ( $this->part_on( 'agd_' ) ) {
                        echo '<div class="hsw-cp__guide">';
                        $this->print_part( 'agd_', '\HSW_Widget_Archive_Guide' );
                        echo '</div>';
                }

                echo '</div>';
        }
}
