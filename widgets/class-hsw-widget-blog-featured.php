<?php
/**
 * Blog widget: featured article (مقاله ویژه).
 * The highlighted article of page 1 (respects category/search filters).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Blog_Featured extends HSW_Blog_Base {

        public function get_name() {
                return 'hsw-blog-featured';
        }

        public function get_title() {
                return __( 'وبلاگ: مقاله ویژه (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-post-featured';
        }

        public function get_keywords() {
                return array( 'blog', 'featured', 'sticky', 'مقاله ویژه', 'hamishop' );
        }

        protected function register_controls() {
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control( 'badge_text', array(
                        'label'   => __( 'برچسب مقاله ویژه', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::TEXT,
                        'default' => __( 'مقاله ویژه', 'hamishop-elementor-widgets' ),
                ) );

                $this->add_control( 'cta_label', array(
                        'label'   => __( 'متن دکمه', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::TEXT,
                        'default' => __( 'ادامه مطلب', 'hamishop-elementor-widgets' ),
                ) );

                $this->add_control( 'show_author', array(
                        'label'   => __( 'نمایش نویسنده', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::SWITCHER,
                        'default' => 'yes',
                ) );

                $this->add_control( 'show_excerpt', array(
                        'label'   => __( 'نمایش خلاصه', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::SWITCHER,
                        'default' => 'yes',
                ) );

                $this->add_control( 'excerpt_len', array(
                        'label'     => __( 'طول خلاصه (کلمه)', 'hamishop-elementor-widgets' ),
                        'type'      => Controls_Manager::NUMBER,
                        'default'   => 28,
                        'min'       => 10,
                        'max'       => 80,
                        'condition' => array( 'show_excerpt' => 'yes' ),
                ) );

                $this->end_controls_section();
        }

        protected function render() {
                $s = $this->effective_settings();

                /* Featured only on page 1 without an active search. */
                $f = $this->blog_filters();
                if ( $f['page'] > 1 || '' !== $f['s'] ) {
                        return;
                }

                $q = $this->blog_query( array( 'posts_per_page' => 1, 'paged' => 1 ) );
                if ( ! $q->have_posts() ) {
                        return;
                }
                $post = $q->posts[0];
                $cat  = $this->post_category( $post );

                echo '<div class="' . esc_attr( $this->root_class( 'blog-featured', 'hsw-bfeat' ) ) . '">';
                echo '<a class="hsw-bfeat__media" href="' . esc_url( get_permalink( $post ) ) . '">';
                echo $this->post_image( $post, 'large', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- leading image, never lazy.
                echo '<span class="hsw-bfeat__badge">' . esc_html( $s['badge_text'] ?? '' ) . '</span>';
                echo '</a>';

                echo '<div class="hsw-bfeat__body">';
                if ( $cat ) {
                        echo '<a class="hsw-bfeat__cat" href="' . esc_url( $cat['url'] ) . '">' . esc_html( $cat['name'] ) . '</a>';
                }
                echo '<h2 class="hsw-bfeat__title"><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h2>';

                if ( 'yes' === ( $s['show_excerpt'] ?? 'yes' ) ) {
                        $len  = (int) ( $s['excerpt_len'] ?? 28 );
                        $text = wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : $post->post_content );
                        $text = wp_trim_words( $text, $len, '…' );
                        echo '<p class="hsw-bfeat__ex">' . esc_html( $text ) . '</p>';
                }

                echo '<div class="hsw-bfeat__meta">';
                if ( 'yes' === ( $s['show_author'] ?? 'yes' ) ) {
                        echo '<span class="hsw-bfeat__author">' . HSW_Helpers::icon( 'person' ) . esc_html( $this->post_author( $post ) ) . '</span>';
                }
                echo '<span class="hsw-bfeat__date">' . HSW_Helpers::icon( 'calendar_today' ) . esc_html( $this->post_date( $post ) ) . '</span>';
                echo '<span class="hsw-bfeat__read">' . HSW_Helpers::icon( 'schedule' ) . esc_html( HSW_Helpers::fa_num( $this->read_time( $post ) ) . ' ' . __( 'دقیقه مطالعه', 'hamishop-elementor-widgets' ) ) . '</span>';
                echo '</div>';

                echo '<a class="hsw-bfeat__cta" href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( $s['cta_label'] ?? '' ) . HSW_Helpers::icon( 'arrow_back' ) . '</a>';
                echo '</div>';
                echo '</div>';
        }
}
