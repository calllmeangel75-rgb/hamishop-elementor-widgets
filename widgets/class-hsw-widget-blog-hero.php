<?php
/**
 * Blog widget: hero header (سربرگ مجله).
 * Badge + title + subtitle + live stats (posts/categories/comments) +
 * search form + hot-tag chips. All data is real WP content.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Blog_Hero extends HSW_Blog_Base {

        public function get_name() {
                return 'hsw-blog-hero';
        }

        public function get_title() {
                return __( 'وبلاگ: سربرگ مجله (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-post-list';
        }

        public function get_keywords() {
                return array( 'blog', 'magazine', 'hero', 'مجله', 'وبلاگ', 'سربرگ', 'hamishop' );
        }

        protected function register_controls() {
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control( 'eyebrow', array(
                        'label'   => __( 'برچسب بالا', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::TEXT,
                        'default' => __( 'مجله تخصصی', 'hamishop-elementor-widgets' ),
                ) );

                $this->add_control( 'title', array(
                        'label'       => __( 'عنوان', 'hamishop-elementor-widgets' ),
                        'type'        => Controls_Manager::TEXT,
                        'default'     => __( 'مجله فروشگاه', 'hamishop-elementor-widgets' ),
                        'label_block' => true,
                ) );

                $this->add_control( 'sub', array(
                        'label'       => __( 'توضیح کوتاه', 'hamishop-elementor-widgets' ),
                        'type'        => Controls_Manager::TEXTAREA,
                        'default'     => __( 'راهنمای خرید، بررسی تخصصی و مقایسه محصولات — به قلم کارشناسان ما.', 'hamishop-elementor-widgets' ),
                        'rows'        => 2,
                ) );

                $this->add_control( 'show_stats', array(
                        'label'   => __( 'نمایش آمار', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::SWITCHER,
                        'default' => 'yes',
                ) );

                $this->add_control( 'stat_articles_label', array(
                        'label'     => __( 'برچسب آمار مقاله', 'hamishop-elementor-widgets' ),
                        'type'      => Controls_Manager::TEXT,
                        'default'   => __( 'مقاله منتشرشده', 'hamishop-elementor-widgets' ),
                        'condition' => array( 'show_stats' => 'yes' ),
                ) );

                $this->add_control( 'stat_cats_label', array(
                        'label'     => __( 'برچسب آمار دسته', 'hamishop-elementor-widgets' ),
                        'type'      => Controls_Manager::TEXT,
                        'default'   => __( 'دسته‌بندی فعال', 'hamishop-elementor-widgets' ),
                        'condition' => array( 'show_stats' => 'yes' ),
                ) );

                $this->add_control( 'stat_comments_label', array(
                        'label'     => __( 'برچسب آمار دیدگاه', 'hamishop-elementor-widgets' ),
                        'type'      => Controls_Manager::TEXT,
                        'default'   => __( 'دیدگاه کاربران', 'hamishop-elementor-widgets' ),
                        'condition' => array( 'show_stats' => 'yes' ),
                ) );

                $this->add_control( 'show_search', array(
                        'label'   => __( 'نمایش جستجو', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::SWITCHER,
                        'default' => 'yes',
                        'separator' => 'before',
                ) );

                $this->add_control( 'search_ph', array(
                        'label'     => __( 'متن راهنمای جستجو', 'hamishop-elementor-widgets' ),
                        'type'      => Controls_Manager::TEXT,
                        'default'   => __( 'جستجو در مقالات…', 'hamishop-elementor-widgets' ),
                        'condition' => array( 'show_search' => 'yes' ),
                ) );

                $this->add_control( 'hot_title', array(
                        'label'     => __( 'عنوان برچسب‌های داغ', 'hamishop-elementor-widgets' ),
                        'type'      => Controls_Manager::TEXT,
                        'default'   => __( 'جستجوهای پرطرفدار:', 'hamishop-elementor-widgets' ),
                        'condition' => array( 'show_search' => 'yes' ),
                ) );

                $this->add_control( 'hot_count', array(
                        'label'     => __( 'تعداد برچسب داغ', 'hamishop-elementor-widgets' ),
                        'type'      => Controls_Manager::NUMBER,
                        'default'   => 4,
                        'min'       => 0,
                        'max'       => 10,
                        'condition' => array( 'show_search' => 'yes' ),
                ) );

                $this->end_controls_section();

                /* ---- style ---- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_control(
                        'palette_note',
                        array(
                                'label' => '',
                                'type'  => Controls_Manager::RAW_HTML,
                                'raw'   => '<div style="font-size:11px;line-height:1.8;color:#646970">' .
                                        esc_html__( 'رنگ‌ها از «HamiShop → پالت رنگی» می‌آید. برای تغییر، همان صفحه را باز کنید.', 'hamishop-elementor-widgets' ) .
                                        '</div>',
                                'separator' => 'after',
                        )
                );

                $this->add_responsive_control(
                        'sub_size',
                        array(
                                'label'      => __( 'اندازهٔ متن توضیح', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 11, 'max' => 24 ) ),
                                'default'    => array( 'size' => 13, 'unit' => 'px' ),
                                'selectors'  => array(
                                        '{{WRAPPER}} .hsw-bhero__sub' => 'font-size: {{SIZE}}{{UNIT}};',
                                ),
                        )
                );

                $this->a_align(
                        'sub',
                        __( 'تراز و جهت متن توضیح', 'hamishop-elementor-widgets' ),
                        '{{WRAPPER}} .hsw-bhero__sub',
                        array( 'align' => 'start', 'lh' => 1.9 )
                );

                $this->end_controls_section();
        }

        protected function render() {
                $s = $this->effective_settings();

                $posts     = (int) wp_count_posts( 'post' )->publish;
                $cats      = (int) wp_count_terms( array( 'taxonomy' => 'category', 'hide_empty' => true ) );
                $comments  = function_exists( 'wp_count_comments' ) ? (int) wp_count_comments()->approved : 0;

                echo '<div class="' . esc_attr( $this->root_class( 'blog-hero' ) ) . '">';

                if ( ! empty( $s['eyebrow'] ) ) {
                        echo '<span class="hsw-bhero__badge">' . esc_html( $s['eyebrow'] ) . '</span>';
                }
                if ( ! empty( $s['title'] ) ) {
                        echo '<h1 class="hsw-bhero__title">' . esc_html( $s['title'] ) . '</h1>';
                }
                if ( ! empty( $s['sub'] ) ) {
                        echo '<p class="hsw-bhero__sub">' . esc_html( $s['sub'] ) . '</p>';
                }

                if ( 'yes' === ( $s['show_stats'] ?? 'yes' ) ) {
                        echo '<div class="hsw-bhero__stats">';
                        printf(
                                '<div class="hsw-bhero__stat"><b>%s</b><span>%s</span></div>',
                                esc_html( HSW_Helpers::fa_num( $posts ) ),
                                esc_html( $s['stat_articles_label'] ?? '' )
                        );
                        printf(
                                '<div class="hsw-bhero__stat"><b>%s</b><span>%s</span></div>',
                                esc_html( HSW_Helpers::fa_num( $cats ) ),
                                esc_html( $s['stat_cats_label'] ?? '' )
                        );
                        printf(
                                '<div class="hsw-bhero__stat"><b>%s</b><span>%s</span></div>',
                                esc_html( HSW_Helpers::fa_num( $comments ) ),
                                esc_html( $s['stat_comments_label'] ?? '' )
                        );
                        echo '</div>';
                }

                if ( 'yes' === ( $s['show_search'] ?? 'yes' ) ) {
                        echo '<form class="hsw-bhero__search" method="get" action="' . esc_url( $this->blog_base_url() ) . '" role="search">';
                        echo '<span class="hsw-bhero__search-ic">' . HSW_Helpers::icon( 'search' ) . '</span>';
                        echo '<input type="search" name="blog_s" value="' . esc_attr( $this->blog_filters()['s'] ) . '" placeholder="' . esc_attr( $s['search_ph'] ?? '' ) . '" aria-label="' . esc_attr__( 'جستجو در مقالات', 'hamishop-elementor-widgets' ) . '">';
                        echo '<button type="submit">' . esc_html__( 'جستجو', 'hamishop-elementor-widgets' ) . '</button>';
                        echo '</form>';

                        $hot = (int) ( $s['hot_count'] ?? 4 );
                        if ( $hot > 0 ) {
                                $tags = $this->blog_tags( $hot );
                                if ( $tags ) {
                                        echo '<div class="hsw-bhero__hot">';
                                        if ( ! empty( $s['hot_title'] ) ) {
                                                echo '<span class="hsw-bhero__hot-t">' . esc_html( $s['hot_title'] ) . '</span>';
                                        }
                                        foreach ( $tags as $tag ) {
                                                echo '<a href="' . esc_url( $this->blog_url( array( 's' => $tag->name, 'page' => 1 ) ) ) . '">' . esc_html( $tag->name ) . '</a>';
                                        }
                                        echo '</div>';
                                }
                        }
                }

                echo '</div>';
        }
}
