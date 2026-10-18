<?php
/**
 * Blog widget: category tabs + sort (تب‌های دسته و مرتب‌سازی).
 * Server-side links (query params) — no JS required, SEO friendly.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Blog_Tabs extends HSW_Blog_Base {

        public function get_name() {
                return 'hsw-blog-tabs';
        }

        public function get_title() {
                return __( 'وبلاگ: تب دسته‌ها و مرتب‌سازی (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-tabs';
        }

        public function get_keywords() {
                return array( 'blog', 'tabs', 'sort', 'دسته', 'مرتب‌سازی', 'hamishop' );
        }

        protected function register_controls() {
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control( 'all_label', array(
                        'label'   => __( 'برچسب «همه»', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::TEXT,
                        'default' => __( 'همه مقالات', 'hamishop-elementor-widgets' ),
                ) );

                $this->add_control( 'tabs_count', array(
                        'label'   => __( 'تعداد دسته‌ها', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::NUMBER,
                        'default' => 5,
                        'min'     => 1,
                        'max'     => 12,
                ) );

                $this->add_control( 'show_sort', array(
                        'label'   => __( 'نمایش مرتب‌سازی', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::SWITCHER,
                        'default' => 'yes',
                ) );

                $this->add_control( 'sort_label', array(
                        'label'     => __( 'برچسب مرتب‌سازی', 'hamishop-elementor-widgets' ),
                        'type'      => Controls_Manager::TEXT,
                        'default'   => __( 'مرتب‌سازی:', 'hamishop-elementor-widgets' ),
                        'condition' => array( 'show_sort' => 'yes' ),
                ) );

                $this->end_controls_section();
        }

        protected function render() {
                $s       = $this->effective_settings();
                $f       = $this->blog_filters();
                $current = $f['cat'];

                echo '<div class="' . esc_attr( $this->root_class( 'blog-tabs', 'hsw-btabs' ) ) . '">';

                echo '<nav class="hsw-btabs__pills" aria-label="' . esc_attr__( 'دسته‌بندی مقالات', 'hamishop-elementor-widgets' ) . '">';
                printf(
                        '<a href="%s" class="hsw-btabs__pill%s">%s</a>',
                        esc_url( $this->blog_url( array( 'cat' => '', 'page' => 1 ) ) ),
                        '' === $current ? ' is-on' : '',
                        esc_html( $s['all_label'] ?? '' )
                );
                foreach ( $this->blog_categories( (int) ( $s['tabs_count'] ?? 5 ) ) as $term ) {
                        printf(
                                '<a href="%s" class="hsw-btabs__pill%s">%s<span class="hsw-btabs__n">%s</span></a>',
                                esc_url( $this->blog_url( array( 'cat' => $term->slug, 'page' => 1 ) ) ),
                                $current === $term->slug ? ' is-on' : '',
                                esc_html( $term->name ),
                                esc_html( HSW_Helpers::fa_num( (int) $term->count ) )
                        );
                }
                echo '</nav>';

                if ( 'yes' === ( $s['show_sort'] ?? 'yes' ) ) {
                        echo '<div class="hsw-btabs__sort">';
                        if ( ! empty( $s['sort_label'] ) ) {
                                echo '<span class="hsw-btabs__sort-t">' . esc_html( $s['sort_label'] ) . '</span>';
                        }
                        echo '<select class="hsw-btabs__select" onchange="if(this.value){location.href=this.value}" aria-label="' . esc_attr__( 'مرتب‌سازی مقالات', 'hamishop-elementor-widgets' ) . '">';
                        foreach ( $this->sort_options() as $key => $label ) {
                                $active = ( '' === $f['sort'] ? 'newest' : $f['sort'] ) === $key;
                                printf(
                                        '<option value="%s"%s>%s</option>',
                                        esc_url( $this->blog_url( array( 'sort' => $key, 'page' => 1 ) ) ),
                                        selected( $active, true, false ),
                                        esc_html( $label )
                                );
                        }
                        echo '</select>';
                        echo '</div>';
                }

                echo '</div>';
        }
}
