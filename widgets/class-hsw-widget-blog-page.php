<?php
/**
 * Composite widget: whole blog / magazine page (صفحه کامل مجله).
 *
 * Assembles blog hero → tabs/sort → featured article → grid/pagination +
 * sidebar (popular, newsletter, tags) in one drag, over live WP content.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;

class HSW_Widget_Blog_Page extends HSW_Blog_Base {

        public function get_name() {
                return 'hsw-blog-page';
        }

        public function get_title() {
                return __( 'صفحه کامل مجله — حامی (ویجت جامع)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-post-content';
        }

        public function get_keywords() {
                return array( 'blog', 'magazine', 'page', 'مجله', 'وبلاگ', 'جامع', 'hamishop', 'hamisys' );
        }

        public function get_style_depends() {
                return array( 'hsw-fonts', 'hsw-blog', 'hsw-composite' );
        }

        protected function register_controls() {
                $this->part_section( 'bhero_', __( '۱. سربرگ مجله', 'hamishop-elementor-widgets' ), '\HSW_Widget_Blog_Hero', array( 'vis', 'eyebrow', 'title', 'sub', 'show_stats', 'stat_articles_label', 'stat_cats_label', 'stat_comments_label', 'show_search', 'search_ph', 'hot_title', 'hot_count' ) );

                $this->part_section( 'btabs_', __( '۲. تب دسته‌ها و مرتب‌سازی', 'hamishop-elementor-widgets' ), '\HSW_Widget_Blog_Tabs', array( 'vis', 'all_label', 'tabs_count', 'show_sort', 'sort_label' ) );

                $this->part_section( 'bfeat_', __( '۳. مقاله ویژه', 'hamishop-elementor-widgets' ), '\HSW_Widget_Blog_Featured', array( 'vis', 'badge_text', 'cta_label', 'show_author', 'show_excerpt', 'excerpt_len' ) );

                $this->part_section( 'bgrid_', __( '۴. شبکه مقالات و صفحه‌بندی', 'hamishop-elementor-widgets' ), '\HSW_Widget_Blog_Grid', array( 'vis', 'per_page', 'skip_first', 'show_excerpt', 'excerpt_len', 'show_author', 'show_read', 'show_pagination', 'empty_text', 'cols', 'gap' ) );

                $this->part_section( 'bpop_', __( '۵. پربازدیدترین‌ها (سایدبار)', 'hamishop-elementor-widgets' ), '\HSW_Widget_Blog_Popular', array( 'vis', 'title', 'count', 'days', 'meta_label', 'show_cat' ) );

                $this->part_section( 'bnews_', __( '۶. خبرنامه (سایدبار)', 'hamishop-elementor-widgets' ), '\HSW_Widget_Blog_Newsletter', array( 'vis', 'title', 'sub', 'placeholder', 'btn_label', 'action_url', 'email_field', 'shortcode', 'note' ) );

                $this->part_section( 'btags_', __( '۷. برچسب‌های داغ (سایدبار)', 'hamishop-elementor-widgets' ), '\HSW_Widget_Blog_Tags', array( 'vis', 'title', 'count', 'hot_first' ) );

                /* Layout lives in the CONTENT tab: it is structure, not style. */
                $this->start_controls_section(
                        'sec_layout',
                        array( 'label' => __( 'چیدمان صفحه', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->style_columns(
                	'cp',
                	__( 'عرض سایدبار (دسکتاپ)', 'hamishop-elementor-widgets' ),
                	array(
                		'side'       => 300,
                		'gap'        => 24,
                		'side_range' => array(
                			'px' => array( 'min' => 200, 'max' => 420 ),
                			'%'  => array( 'min' => 15, 'max' => 50 ),
                		),
                	)
                );

                $this->add_responsive_control(
                        'bcols',
                        array(
                                'label'     => __( 'تعداد ستون کارت‌ها', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SELECT,
                                'default'   => '',
                                'options'   => array( '' => __( 'پیش‌فرض', 'hamishop-elementor-widgets' ), '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴' ),
                                'selectors' => array( '{{WRAPPER}} .hsw-bgrid__grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));' ),
                        )
                );

                $this->add_responsive_control(
                        'bgap',
                        array(
                                'label'      => __( 'فاصلهٔ افقی و عمودی کارت‌ها', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 0, 'max' => 48 ) ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-bgrid__grid' => 'gap: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->add_responsive_control(
                        'bpage_pad',
                        array(
                                'label'      => __( 'فاصلهٔ داخلی آرشیو', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::DIMENSIONS,
                                'size_units' => array( 'px', 'em' ),
                                'selectors'  => array(
                                        '{{WRAPPER}} .hsw-cp--blog' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                                ),
                        )
                );

                $this->a_color( 'bpage_bg', __( 'پس‌زمینهٔ کلی آرشیو', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cp--blog', 'background-color' );

                $this->end_controls_section();

                /* ---- hero description typography (native: mirroring drops CHOOSE controls) ---- */
                $this->start_controls_section(
                        'sec_bsub_typo',
                        array( 'label' => __( 'متن توضیح سربرگ مجله', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_responsive_control(
                        'bsub_fs',
                        array(
                                'label'      => __( 'اندازهٔ فونت توضیح', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 10, 'max' => 24 ) ),
                                'default'    => array( 'size' => 13, 'unit' => 'px' ),
                                'selectors'  => array(
                                        '{{WRAPPER}} .hsw-bhero__sub' => 'font-size: {{SIZE}}{{UNIT}};',
                                ),
                        )
                );

                $this->a_align(
                        'bsub',
                        __( 'تراز، جهت و ارتفاع خط', 'hamishop-elementor-widgets' ),
                        '{{WRAPPER}} .hsw-bhero__sub',
                        array( 'align' => 'start', 'lh' => 1.9 )
                );

                $this->end_controls_section();

                /* ------------------- ARCHIVE HEADER ------------------- */
                $this->start_controls_section(
                        'sec_blog_head',
                        array( 'label' => __( 'عنوان و سربرگ آرشیو', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_color( 'bh_bg', __( 'پس‌زمینهٔ سربرگ', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-bhero', 'background-color' );
                $this->a_text( 'bh_title', '{{WRAPPER}} .hsw-bhero__title', array( 'default' => 26 ) );
                $this->a_color( 'bh_sub', __( 'رنگ توضیح زیر عنوان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-bhero__sub' );

                $this->add_responsive_control(
                        'bh_pad',
                        array(
                                'label'      => __( 'فاصلهٔ داخلی سربرگ', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::DIMENSIONS,
                                'size_units' => array( 'px', 'em' ),
                                'selectors'  => array(
                                        '{{WRAPPER}} .hsw-bhero' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                                ),
                        )
                );

                $this->add_responsive_control(
                        'bh_gap',
                        array(
                                'label'      => __( 'فاصلهٔ عنوان تا فهرست مطالب', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 0, 'max' => 64 ) ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-bhero' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->end_controls_section();

                /* ------------------- FEATURED ------------------- */
                $this->start_controls_section(
                        'sec_blog_feat',
                        array( 'label' => __( 'مطلب شاخص', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_color( 'bf_bg', __( 'پس‌زمینهٔ کارت شاخص', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-bfeat', 'background-color' );
                $this->a_color( 'bf_bd', __( 'حاشیهٔ کارت شاخص', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-bfeat', 'border-color' );

                $this->add_responsive_control(
                        'bf_radius',
                        array(
                                'label'      => __( 'گردی گوشهٔ کارت شاخص', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 0, 'max' => 32 ) ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-bfeat' => 'border-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->add_group_control(
                        Group_Control_Box_Shadow::get_type(),
                        array(
                                'name'     => 'bf_shadow',
                                'label'    => __( 'سایهٔ کارت شاخص', 'hamishop-elementor-widgets' ),
                                'selector' => '{{WRAPPER}} .hsw-bfeat',
                        )
                );

                $this->add_responsive_control(
                        'bf_ratio',
                        array(
                                'label'     => __( 'نسبت تصویر شاخص', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SELECT,
                                'default'   => '',
                                'options'   => array( '' => __( 'پیش‌فرض', 'hamishop-elementor-widgets' ), '16/9' => '۱۶:۹', '4/3' => '۴:۳', '3/2' => '۳:۲', '1/1' => '۱:۱' ),
                                'selectors' => array( '{{WRAPPER}} .hsw-bfeat__media' => 'aspect-ratio: {{VALUE}}; min-height: 0;' ),
                        )
                );

                $this->a_text( 'bf_title', '{{WRAPPER}} .hsw-bfeat__title', array( 'default' => 22 ) );
                $this->a_text( 'bf_ex', '{{WRAPPER}} .hsw-bfeat__ex', array( 'default' => 14 ) );
                $this->a_color( 'bf_cat_bg', __( 'پس‌زمینهٔ برچسب دسته', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-bfeat__cat', 'background-color' );
                $this->a_color( 'bf_cat_tx', __( 'متن برچسب دسته', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-bfeat__cat' );

                $this->add_responsive_control(
                        'bf_pad',
                        array(
                                'label'      => __( 'فاصلهٔ داخلی بخش متن', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::DIMENSIONS,
                                'size_units' => array( 'px', 'em' ),
                                'selectors'  => array(
                                        '{{WRAPPER}} .hsw-bfeat__body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                                ),
                        )
                );

                $this->end_controls_section();

                /* ------------------- CARDS (shared) ------------------- */
                $this->start_controls_section(
                        'sec_blog_cards',
                        array( 'label' => __( 'کارت‌های مطالب (مشترک)', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_control(
                        'bcards_note',
                        array(
                                'type'            => Controls_Manager::RAW_HTML,
                                'raw'             => __( 'این تنظیمات روی همهٔ کارت‌های فهرست اعمال می‌شود؛ مطلب شاخص کنترل‌های جدا دارد.', 'hamishop-elementor-widgets' ),
                                'content_classes' => 'elementor-descriptor',
                        )
                );

                $this->a_color( 'bc_bg', __( 'پس‌زمینهٔ کارت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-bgrid__card', 'background-color' );
                $this->a_color( 'bc_bd', __( 'حاشیهٔ کارت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-bgrid__card', 'border-color' );

                $this->add_responsive_control(
                        'bc_radius',
                        array(
                                'label'      => __( 'گردی گوشهٔ کارت', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 0, 'max' => 28 ) ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-bgrid__card' => 'border-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->add_responsive_control(
                        'bc_pad',
                        array(
                                'label'      => __( 'فاصلهٔ داخلی کارت', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::DIMENSIONS,
                                'size_units' => array( 'px', 'em' ),
                                'selectors'  => array(
                                        '{{WRAPPER}} .hsw-bgrid__body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                                ),
                        )
                );

                $this->add_group_control(
                        Group_Control_Box_Shadow::get_type(),
                        array(
                                'name'     => 'bc_shadow',
                                'label'    => __( 'سایهٔ کارت', 'hamishop-elementor-widgets' ),
                                'selector' => '{{WRAPPER}} .hsw-bgrid__card',
                        )
                );

                $this->add_group_control(
                        Group_Control_Box_Shadow::get_type(),
                        array(
                                'name'     => 'bc_shadow_hv',
                                'label'    => __( 'سایهٔ هاور کارت', 'hamishop-elementor-widgets' ),
                                'selector' => '{{WRAPPER}} .hsw-bgrid__card:hover',
                        )
                );

                $this->add_responsive_control(
                        'bc_ratio',
                        array(
                                'label'     => __( 'نسبت تصویر کارت', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SELECT,
                                'default'   => '',
                                'options'   => array( '' => __( 'پیش‌فرض', 'hamishop-elementor-widgets' ), '16/9' => '۱۶:۹', '16/10' => '۱۶:۱۰', '4/3' => '۴:۳', '1/1' => '۱:۱' ),
                                'selectors' => array( '{{WRAPPER}} .hsw-bgrid__media' => 'aspect-ratio: {{VALUE}};' ),
                        )
                );

                $this->a_text( 'bc_title', '{{WRAPPER}} .hsw-bgrid__title', array( 'default' => 15 ) );
                $this->a_text( 'bc_ex', '{{WRAPPER}} .hsw-bgrid__ex', array( 'default' => 13 ) );

                $this->add_responsive_control(
                        'bc_lines',
                        array(
                                'label'     => __( 'تعداد خط عنوان', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SELECT,
                                'default'   => '',
                                'options'   => array( '' => __( 'پیش‌فرض', 'hamishop-elementor-widgets' ), '1' => '۱', '2' => '۲', '3' => '۳' ),
                                'selectors' => array(
                                        '{{WRAPPER}} .hsw-bgrid__title' => 'display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: {{VALUE}}; overflow: hidden;',
                                ),
                        )
                );

                $this->end_controls_section();

                /* ------------------- META ------------------- */
                $this->start_controls_section(
                        'sec_blog_meta',
                        array( 'label' => __( 'اطلاعات هر مطلب', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_color( 'bm_cat_bg', __( 'پس‌زمینهٔ برچسب دسته', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-bgrid__cat', 'background-color' );
                $this->a_color( 'bm_cat_tx', __( 'متن برچسب دسته', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-bgrid__cat' );
                $this->a_text( 'bm_meta', '{{WRAPPER}} .hsw-bgrid__meta', array( 'default' => 12 ) );
                $this->a_color( 'bm_meta_tx', __( 'رنگ تاریخ/نویسنده/دیدگاه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-bgrid__meta' );

                $this->add_responsive_control(
                        'bm_ic',
                        array(
                                'label'      => __( 'اندازهٔ آیکن‌های اطلاعات', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 10, 'max' => 22 ) ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-bgrid__meta .hsw-ic' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->end_controls_section();

                /* ------------------- PAGINATION ------------------- */
                $this->start_controls_section(
                        'sec_blog_pagi',
                        array( 'label' => __( 'صفحه‌بندی', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_color( 'bp_tx', __( 'رنگ شماره‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-bgrid__pagi a, {{WRAPPER}} .hsw-bgrid__pagi span' );
                $this->a_color( 'bp_bg', __( 'پس‌زمینهٔ دکمه‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-bgrid__pagi a', 'background-color' );
                $this->a_color( 'bp_on_bg', __( 'پس‌زمینهٔ صفحهٔ فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-bgrid__pagi .is-active, {{WRAPPER}} .hsw-bgrid__pagi .current', 'background-color' );
                $this->a_color( 'bp_on_tx', __( 'متن صفحهٔ فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-bgrid__pagi .is-active, {{WRAPPER}} .hsw-bgrid__pagi .current' );

                $this->add_responsive_control(
                        'bp_radius',
                        array(
                                'label'      => __( 'گردی دکمه‌های صفحه‌بندی', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 0, 'max' => 24 ) ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-bgrid__pagi a' => 'border-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->add_responsive_control(
                        'bp_gap',
                        array(
                                'label'      => __( 'فاصلهٔ بین شماره‌ها', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 0, 'max' => 20 ) ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-bgrid__pagi' => 'gap: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->end_controls_section();

                /* ------------------- STATES ------------------- */
                $this->start_controls_section(
                        'sec_blog_states',
                        array( 'label' => __( 'حالت‌های ویژه', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_color( 'bs_empty_tx', __( 'رنگ متن «مطلبی یافت نشد»', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-bgrid__empty' );
                $this->a_text( 'bs_empty_fs', '{{WRAPPER}} .hsw-bgrid__empty', array( 'default' => 14 ) );

                $this->end_controls_section();
        }

        /**
         * Render the full blog page.
         */
        protected function render() {
                $root = $this->root_class( 'cblog', 'hsw-cp hsw-cp--blog' );

                echo '<div class="' . esc_attr( $root ) . '">';

                if ( $this->part_on( 'bhero_' ) ) {
                        $this->print_part( 'bhero_', '\HSW_Widget_Blog_Hero' );
                }

                /*
                 * Tabs and the featured article span the full width, like the
                 * magazine mock: hero → tabs → featured → two columns
                 * (cards | sidebar). Inside the grid they used to squeeze into
                 * the main column, so the sidebar started next to the featured
                 * card and the archive read as a jumble.
                 */
                if ( $this->part_on( 'btabs_' ) ) {
                        echo '<div class="hsw-cp--blog__wide">';
                        $this->print_part( 'btabs_', '\HSW_Widget_Blog_Tabs' );
                        echo '</div>';
                }
                if ( $this->part_on( 'bfeat_' ) ) {
                        echo '<div class="hsw-cp--blog__wide">';
                        $this->print_part( 'bfeat_', '\HSW_Widget_Blog_Featured' );
                        echo '</div>';
                }

                echo '<div class="hsw-cp__body">';

                echo '<div class="hsw-cp__col hsw-cp__col--main">';
                if ( $this->part_on( 'bgrid_' ) ) {
                        $this->print_part( 'bgrid_', '\HSW_Widget_Blog_Grid' );
                }
                echo '</div>';

                echo '<aside class="hsw-cp__col hsw-cp__col--side hsw-cp__side">';
                if ( $this->part_on( 'bpop_' ) ) {
                        $this->print_part( 'bpop_', '\HSW_Widget_Blog_Popular' );
                }
                if ( $this->part_on( 'bnews_' ) ) {
                        $this->print_part( 'bnews_', '\HSW_Widget_Blog_Newsletter' );
                }
                if ( $this->part_on( 'btags_' ) ) {
                        $this->print_part( 'btags_', '\HSW_Widget_Blog_Tags' );
                }
                echo '</aside>';

                echo '</div>'; // .hsw-cp__body
                echo '</div>';
        }
}
