<?php
/**
 * Composite widget: whole single-product page (صفحه کامل محصول).
 *
 * Assembles every product-page widget (breadcrumb → gallery → title →
 * variants → features → purchase → trust → tabs/accordions → sticky bar)
 * through HSW_Composite, so each section keeps its own look, controls and
 * dynamic WooCommerce data — but the whole page is placed with ONE widget.
 * Every part can be switched off, and the single widgets still work alone.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Product_Page extends HSW_Product_Base {

        public function get_name() {
                return 'hsw-product-page';
        }

        public function get_title() {
                return __( 'صفحه کامل محصول — حامی (ویجت جامع)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-single-product';
        }

        public function get_keywords() {
                return array( 'product', 'single', 'page', 'صفحه محصول', 'جامع', 'hamishop', 'hamisys' );
        }

        public function get_style_depends() {
                return array( 'hsw-fonts', 'hsw-product', 'hsw-composite' );
        }

        public function get_script_depends() {
                return array( 'hsw-product' );
        }

        protected function register_controls() {
                /* ------------------- بخش‌ها ------------------- */
                $this->part_section( 'pbc_', __( '۱. مسیر راهنما (بردکرامب)', 'hamishop-elementor-widgets' ), '\HSW_Widget_Product_Breadcrumb', array( 'vis', 'show_home_icon', 'sep_text', 'spacing_bottom' ) );

                $this->part_section( 'pga_', __( '۲. گالری تصاویر', 'hamishop-elementor-widgets' ), '\HSW_Widget_Product_Gallery', array( 'vis', 'show_auth', 'auth_text', 'show_zoom', 'thumbs_count', 'spacing_bottom' ) );

                $this->part_section( 'ptl_', __( '۳. عنوان، امتیاز و متا', 'hamishop-elementor-widgets' ), '\HSW_Widget_Product_Title', array( 'vis', 'title_en', 'title_tag', 'recommend_mode', 'recommend', 'show_stock', 'rev_url', 'spacing_bottom' ) );

                $this->part_section( 'pvr_', __( '۴. انتخاب رنگ و حافظه', 'hamishop-elementor-widgets' ), '\HSW_Widget_Product_Variants', array( 'vis', 'attr_color', 'attr_storage', 'label_color_d', 'label_color_m', 'label_storage_d', 'label_storage_m', 'color_map' ) );

                $this->part_section( 'pft_', __( '۵. ویژگی‌های کلیدی', 'hamishop-elementor-widgets' ), '\HSW_Widget_Product_Features', array( 'vis', 'desk_title', 'mob_title', 'desk_auto', 'mob_auto', 'desk_items', 'mob_items' ) );

                $this->part_section( 'ppu_', __( '۶. جعبه خرید (قیمت و دکمه‌ها)', 'hamishop-elementor-widgets' ), '\HSW_Widget_Product_Purchase', array( 'vis', 'type', 'cart_text', 'qty_max', 'assure_items' ) );

                $this->part_section( 'ptr_', __( '۷. سیگنال‌های اعتماد', 'hamishop-elementor-widgets' ), '\HSW_Widget_Product_Trust', array( 'vis', 'trust_items' ) );

                $this->part_section( 'ptb_', __( '۸. تب‌ها (دسکتاپ)', 'hamishop-elementor-widgets' ), '\HSW_Widget_Product_Tabs', array( 'vis', 'specs_title', 'lbl_specs', 'lbl_review', 'lbl_comments', 'tab_specs', 'tab_review', 'tab_comments', 'reviews_count' ) );

                $this->part_section( 'pac_', __( '۹. آکاردئون‌ها (موبایل)', 'hamishop-elementor-widgets' ), '\HSW_Widget_Product_Accordions', array( 'vis', 'acc_specs_title', 'acc_warranty_title', 'acc_reviews_title', 'acc_specs', 'acc_warranty', 'acc_reviews', 'reviews_count2', 'warranty_items' ) );

                $this->part_section( 'pst_', __( '۱۰. نوار خرید چسبان', 'hamishop-elementor-widgets' ), '\HSW_Widget_Product_Sticky', array( 'vis', 'sticky_fixed', 'cart_text' ) );

                /* ------------------- چیدمان ------------------- */
                $this->start_controls_section(
                        'sec_layout',
                        array( 'label' => __( 'چیدمان صفحه', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_responsive_control(
                        'cp_cols',
                        array(
                                'label'      => __( 'نسبت ستون تصاویر (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( '%' ),
                                'range'      => array( '%' => array( 'min' => 25, 'max' => 75 ) ),
                                'default'    => array( 'size' => 42, 'unit' => '%' ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-cp__hero' => '--hsw-cp-cols: {{SIZE}}%;' ),
                        )
                );

                $this->add_responsive_control(
                        'cp_gap',
                        array(
                                'label'      => __( 'فاصله ستون‌ها و بخش‌ها', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 0, 'max' => 64 ) ),
                                'default'    => array( 'size' => 24, 'unit' => 'px' ),
                                'selectors'  => array(
                                        '{{WRAPPER}} .hsw-cp__hero'  => 'gap: {{SIZE}}{{UNIT}};',
                                        '{{WRAPPER}} .hsw-cp__panels'=> 'margin-top: {{SIZE}}{{UNIT}};',
                                ),
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Render the full product page.
         */
        protected function render() {
                $root = $this->root_class( 'cpage', 'hsw-cp hsw-cp--product' );

                echo '<div class="' . esc_attr( $root ) . '">';

                if ( $this->part_on( 'pbc_' ) ) {
                        $this->print_part( 'pbc_', '\HSW_Widget_Product_Breadcrumb', 'hsw-cp__part hsw-cp__part--crumb' );
                }

                echo '<div class="hsw-cp__hero">';

                if ( $this->part_on( 'pga_' ) ) {
                        echo '<div class="hsw-cp__col hsw-cp__col--gallery">';
                        $this->print_part( 'pga_', '\HSW_Widget_Product_Gallery' );
                        echo '</div>';
                }

                echo '<div class="hsw-cp__col hsw-cp__col--summary">';
                if ( $this->part_on( 'ptl_' ) ) {
                        $this->print_part( 'ptl_', '\HSW_Widget_Product_Title' );
                }
                if ( $this->part_on( 'pvr_' ) ) {
                        $this->print_part( 'pvr_', '\HSW_Widget_Product_Variants' );
                }
                if ( $this->part_on( 'pft_' ) ) {
                        $this->print_part( 'pft_', '\HSW_Widget_Product_Features' );
                }
                if ( $this->part_on( 'ppu_' ) ) {
                        $this->print_part( 'ppu_', '\HSW_Widget_Product_Purchase' );
                }
                if ( $this->part_on( 'ptr_' ) ) {
                        $this->print_part( 'ptr_', '\HSW_Widget_Product_Trust' );
                }
                echo '</div>';

                echo '</div>'; // .hsw-cp__hero

                if ( $this->part_on( 'ptb_' ) || $this->part_on( 'pac_' ) ) {
                        echo '<div class="hsw-cp__panels">';
                        if ( $this->part_on( 'ptb_' ) ) {
                                $this->print_part( 'ptb_', '\HSW_Widget_Product_Tabs' );
                        }
                        if ( $this->part_on( 'pac_' ) ) {
                                $this->print_part( 'pac_', '\HSW_Widget_Product_Accordions' );
                        }
                        echo '</div>';
                }

                if ( $this->part_on( 'pst_' ) ) {
                        $this->print_part( 'pst_', '\HSW_Widget_Product_Sticky' );
                }

                echo '</div>';
        }
}
