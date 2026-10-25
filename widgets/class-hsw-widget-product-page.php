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

                $this->part_section( 'ptb_', __( '۸. تب‌ها (دسکتاپ و موبایل)', 'hamishop-elementor-widgets' ), '\HSW_Widget_Product_Tabs', array( 'vis', 'specs_title', 'lbl_specs', 'lbl_review', 'lbl_comments', 'tab_specs', 'tab_review', 'tab_warranty', 'lbl_warranty', 'warranty_title', 'warranty_text', 'tab_comments', 'reviews_count' ) );

                $this->part_section( 'pac_', __( '۹. آکاردئون‌ها (موبایل)', 'hamishop-elementor-widgets' ), '\HSW_Widget_Product_Accordions', array( 'vis', 'acc_specs_title', 'acc_warranty_title', 'acc_reviews_title', 'acc_specs', 'acc_warranty', 'acc_reviews', 'reviews_count2', 'warranty_items' ) );

                $this->part_section( 'pst_', __( '۱۰. نوار خرید چسبان', 'hamishop-elementor-widgets' ), '\HSW_Widget_Product_Sticky', array( 'vis', 'sticky_fixed', 'cart_text' ) );
                $this->part_section( 'prel_', __( '۱۱. محصولات مرتبط', 'hamishop-elementor-widgets' ), '\HSW_Widget_Product_Related', array( 'vis', 'title', 'sub', 'source', 'count', 'more_text' ) );

                /* ------------------- چیدمان ------------------- */
                $this->start_controls_section(
                        'sec_layout',
                        array( 'label' => __( 'چیدمان صفحه', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_responsive_control(
                        'cp_cols',
                        array(
                                'label'      => __( 'عرض ستون تصاویر (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( '%', 'px' ),
                                'range'      => array(
                                        '%' => array( 'min' => 25, 'max' => 75 ),
                                        'px' => array( 'min' => 280, 'max' => 960 ),
                                ),
                                'default'    => array( 'size' => 42, 'unit' => '%' ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-cp__hero' => '--hsw-cp-cols: {{SIZE}}{{UNIT}};' ),
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

                /* ---------- رنگ‌ها و فونت یکپارچه ---------- */
                $this->start_controls_section(
                        'sec_unified',
                        array( 'label' => __( 'رنگ‌ها و فونت (یکپارچه)', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_control(
                        'uni_note',
                        array(
                                'label' => '',
                                'type'  => Controls_Manager::RAW_HTML,
                                'raw'   => '<div style="font-size:11px;line-height:1.8;color:#646970">' .
                                        esc_html__( 'این بخش رنگ و فونت همهٔ اجزای صفحهٔ محصول را یک‌جا کنترل می‌کند. برای تنظیم دقیق‌تر هر بخش، بخش‌های ۱ تا ۱۰ در تب «محتوا» را باز کنید.', 'hamishop-elementor-widgets' ) .
                                        '</div>',
                                'separator' => 'after',
                        )
                );

                /* ---- colors ---- */
                $this->add_control(
                        'uni_head_c',
                        array(
                                'label' => __( 'رنگ‌ها', 'hamishop-elementor-widgets' ),
                                'type'  => Controls_Manager::HEADING,
                        )
                );

                $colors = array(
                        'c_title'      => array( __( 'عنوان محصول', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__h' ),
                        'c_price'      => array( __( 'قیمت اصلی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__main' ),
                        'c_old'        => array( __( 'قیمت قبل از تخفیف', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__old' ),
                        'c_body'       => array( __( 'متن توضیحات، ویژگی‌ها و اعتماد', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__panel, {{WRAPPER}} .hsw-pfeat__item, {{WRAPPER}} .hsw-ptrust__t' ),
                        'c_tab'        => array( __( 'تب غیرفعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__btn' ),
                        'c_tab_on'     => array( __( 'تب فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__btn.is-active' ),
                        'c_var_label'  => array( __( 'برچسب رنگ / حافظه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pvar__label' ),
                        'c_bread'      => array( __( 'مسیر راهنما', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pbread__link, {{WRAPPER}} .hsw-pbread__cur' ),
                );
                foreach ( $colors as $id => $conf ) {
                        $this->add_control(
                                $id,
                                array(
                                        'label'     => $conf[0],
                                        'type'      => Controls_Manager::COLOR,
                                        'selectors' => array( $conf[1] => 'color: {{VALUE}};' ),
                                )
                        );
                }

                /* ---- icons & stars ----
                 * Several product stylesheets pin icon colours with !important,
                 * so a plain `color:` control never won. One switch here paints
                 * every product-page icon, and another owns the rating stars.
                 */
                $this->add_control(
                        'uni_icon_head',
                        array(
                                'label'     => __( 'آیکون‌ها و ستاره‌ها', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::HEADING,
                                'separator' => 'before',
                        )
                );
                $this->add_control(
                        'uni_icon_c',
                        array(
                                'label'       => __( 'رنگ آیکون‌ها', 'hamishop-elementor-widgets' ),
                                'description' => __( 'تیک ویژگی‌ها، خطوط گارانتی، تب‌ها و آکاردئون. خالی = رنگ پیش‌فرض هر بخش.', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::COLOR,
                                'selectors'   => array(
                                        '{{WRAPPER}} .hsw-pfeat__ic, {{WRAPPER}} .hsw-ppurch__as .hsw-ic, {{WRAPPER}} .hsw-pacc__title .hsw-ic, {{WRAPPER}} .hsw-pacc__chev, {{WRAPPER}} .hsw-pacc__wline .hsw-ic, {{WRAPPER}} .hsw-ptabs__btn .hsw-ic' => 'color: {{VALUE}} !important;',
                                ),
                        )
                );
                $this->add_control(
                        'uni_star_c',
                        array(
                                'label'       => __( 'رنگ ستاره‌ها', 'hamishop-elementor-widgets' ),
                                'description' => __( 'ستارهٔ امتیاز در دسکتاپ و موبایل.', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::COLOR,
                                'selectors'   => array(
                                        '{{WRAPPER}} .hsw-ptitle__star, {{WRAPPER}} .hsw-ptitle__mscore .hsw-ic' => 'color: {{VALUE}} !important;',
                                ),
                        )
                );

                /* ---- typography ---- */
                $this->a_typo( 't_title', __( 'عنوان محصول', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__h', array( 'size' => 24, 'lh' => 1.5 ) );
                $this->a_typo( 't_price', __( 'قیمت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__main', array( 'size' => 24 ) );
                $this->a_typo( 't_body', __( 'متن توضیحات و تب‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__panel', array( 'size' => 14, 'weight' => false, 'lh' => 1.9 ) );
                $this->a_typo( 't_feat', __( 'ویژگی‌های کلیدی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pfeat__item', array( 'size' => 13, 'weight' => false ) );
                $this->a_typo( 't_var', __( 'برچسب رنگ / حافظه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pvar__label', array( 'size' => 13 ) );
                $this->a_typo( 't_tab', __( 'تب‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__btn', array( 'size' => 14 ) );

                /* ---- buttons ---- */
                $this->a_button(
                        'buy',
                        __( 'دکمهٔ خرید (جعبه خرید)', 'hamishop-elementor-widgets' ),
                        '{{WRAPPER}} .hsw-ppurch__cart',
                        array( 'size' => 14, 'radius' => 12, 'css_var' => '--hsw-cart-bg' )
                );

                $this->a_button(
                        'sticky',
                        __( 'دکمهٔ نوار خرید چسبان', 'hamishop-elementor-widgets' ),
                        '{{WRAPPER}} .hsw-psticky__cart',
                        array( 'size' => 13, 'radius' => 10, 'css_var' => '--hsw-cart-bg' )
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

                /*
                 * One tab strip for both breakpoints. The mobile accordion is no
                 * longer printed: it carried a different set of sections, which
                 * made the phone and desktop pages say different things.
                 */
                if ( $this->part_on( 'ptb_' ) ) {
                        echo '<div class="hsw-cp__panels">';
                        $this->print_part( 'ptb_', '\HSW_Widget_Product_Tabs' );
                        echo '</div>';
                }

                if ( $this->part_on( 'pst_' ) ) {
                        $this->print_part( 'pst_', '\HSW_Widget_Product_Sticky' );
                }

                /* 11. Related products — the last block on the page. */
                if ( $this->part_on( 'prel_' ) ) {
                        $this->print_part( 'prel_', '\HSW_Widget_Product_Related' );
                }

                echo '</div>';
        }
}
