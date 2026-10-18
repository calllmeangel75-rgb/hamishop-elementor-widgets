<?php
/**
 * Blog widget: popular posts ranked list (پربازدیدترین‌های هفته).
 * WP has no native view counter → comment count is the popularity signal;
 * the meta label is configurable (default: دیدگاه).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Blog_Popular extends HSW_Blog_Base {

        public function get_name() {
                return 'hsw-blog-popular';
        }

        public function get_title() {
                return __( 'وبلاگ: پربازدیدترین‌ها (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-number-field';
        }

        public function get_keywords() {
                return array( 'blog', 'popular', 'top', 'پربازدید', 'پربحث', 'hamishop' );
        }

        protected function register_controls() {
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control( 'title', array(
                        'label'       => __( 'عنوان', 'hamishop-elementor-widgets' ),
                        'type'        => Controls_Manager::TEXT,
                        'default'     => __( 'پربازدیدترین‌های هفته', 'hamishop-elementor-widgets' ),
                        'label_block' => true,
                ) );

                $this->add_control( 'count', array(
                        'label'   => __( 'تعداد مقالات', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::NUMBER,
                        'default' => 4,
                        'min'     => 2,
                        'max'     => 10,
                ) );

                $this->add_control( 'days', array(
                        'label'       => __( 'بازه زمانی (روز)', 'hamishop-elementor-widgets' ),
                        'type'        => Controls_Manager::NUMBER,
                        'default'     => 7,
                        'min'         => 0,
                        'description' => __( '۰ = بدون محدودیت زمانی. اگر مقاله کافی در بازه نباشد، به همه مقالات برمی‌گردد.', 'hamishop-elementor-widgets' ),
                ) );

                $this->add_control( 'meta_label', array(
                        'label'   => __( 'برچسب معیار (مثلاً دیدگاه)', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::TEXT,
                        'default' => __( 'دیدگاه', 'hamishop-elementor-widgets' ),
                ) );

                $this->add_control( 'show_cat', array(
                        'label'   => __( 'نمایش دسته', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::SWITCHER,
                        'default' => 'yes',
                ) );

                $this->end_controls_section();
        }

        protected function render() {
                $s    = $this->effective_settings();
                $list = $this->popular_posts( (int) ( $s['count'] ?? 4 ), (int) ( $s['days'] ?? 7 ) );

                if ( ! $list ) {
                        return;
                }

                echo '<div class="' . esc_attr( $this->root_class( 'blog-popular', 'hsw-bpop' ) ) . '">';

                if ( ! empty( $s['title'] ) ) {
                        echo '<h3 class="hsw-bpop__title">' . HSW_Helpers::icon( 'local_fire_department' ) . esc_html( $s['title'] ) . '</h3>';
                }

                echo '<ol class="hsw-bpop__list">';
                $i = 0;
                foreach ( $list as $post ) {
                        $i++;
                        $cat = 'yes' === ( $s['show_cat'] ?? 'yes' ) ? $this->post_category( $post ) : null;

                        echo '<li class="hsw-bpop__item">';
                        printf( '<span class="hsw-bpop__rank hsw-bpop__rank--%d">%s</span>', min( 3, $i ), esc_html( HSW_Helpers::fa_num( $i ) ) );
                        echo '<div class="hsw-bpop__body">';
                        echo '<a class="hsw-bpop__link" href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a>';
                        echo '<div class="hsw-bpop__meta">';
                        if ( $cat ) {
                                echo '<span class="hsw-bpop__cat">' . esc_html( $cat['name'] ) . '</span>';
                        }
                        printf(
                                '<span class="hsw-bpop__views">%s %s</span>',
                                esc_html( $this->comment_count( $post ) ),
                                esc_html( $s['meta_label'] ?? '' )
                        );
                        echo '</div>';
                        echo '</div>';
                        echo '</li>';
                }
                echo '</ol>';
                echo '</div>';
        }
}
