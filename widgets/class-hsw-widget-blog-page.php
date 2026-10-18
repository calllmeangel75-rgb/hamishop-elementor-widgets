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

                $this->start_controls_section(
                        'sec_layout',
                        array( 'label' => __( 'چیدمان صفحه', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_responsive_control(
                        'cp_side',
                        array(
                                'label'      => __( 'عرض سایدبار (دسکتاپ)', 'hamishop-elementor-widgets' ),
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
                                'selectors'  => array( '{{WRAPPER}} .hsw-cp__body' => 'gap: {{SIZE}}{{UNIT}};' ),
                        )
                );

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

                echo '<div class="hsw-cp__body">';

                echo '<div class="hsw-cp__col hsw-cp__col--main">';
                if ( $this->part_on( 'btabs_' ) ) {
                        $this->print_part( 'btabs_', '\HSW_Widget_Blog_Tabs' );
                }
                if ( $this->part_on( 'bfeat_' ) ) {
                        $this->print_part( 'bfeat_', '\HSW_Widget_Blog_Featured' );
                }
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
