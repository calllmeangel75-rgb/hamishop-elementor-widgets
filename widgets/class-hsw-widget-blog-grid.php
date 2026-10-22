<?php
/**
 * Blog widget: article grid + pagination (شبکه مقالات).
 * Real WP_Query cards (image, category, title, excerpt, meta, read time).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Blog_Grid extends HSW_Blog_Base {

        public function get_name() {
                return 'hsw-blog-grid';
        }

        public function get_title() {
                return __( 'وبلاگ: شبکه مقالات (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-posts-grid';
        }

        public function get_keywords() {
                return array( 'blog', 'grid', 'posts', 'شبکه مقالات', 'مجله', 'hamishop' );
        }

        protected function register_controls() {
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control( 'per_page', array(
                        'label'   => __( 'تعداد در هر صفحه', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::NUMBER,
                        'default' => 6,
                        'min'     => 2,
                        'max'     => 24,
                ) );

                $this->add_control( 'skip_first', array(
                        'label'       => __( 'رد کردن مقاله ویژه', 'hamishop-elementor-widgets' ),
                        'type'        => Controls_Manager::SWITCHER,
                        'default'     => 'yes',
                        'description' => __( 'مقاله ویژه (بخش اول صفحه) در شبکه تکرار نشود.', 'hamishop-elementor-widgets' ),
                ) );

                $this->add_control( 'show_excerpt', array(
                        'label'   => __( 'نمایش خلاصه', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::SWITCHER,
                        'default' => 'yes',
                ) );

                $this->add_control( 'excerpt_len', array(
                        'label'     => __( 'طول خلاصه (کلمه)', 'hamishop-elementor-widgets' ),
                        'type'      => Controls_Manager::NUMBER,
                        'default'   => 16,
                        'min'       => 6,
                        'max'       => 60,
                        'condition' => array( 'show_excerpt' => 'yes' ),
                ) );

                $this->add_control( 'show_author', array(
                        'label'   => __( 'نمایش نویسنده', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::SWITCHER,
                        'default' => 'yes',
                        'separator' => 'before',
                ) );

                $this->add_control( 'show_read', array(
                        'label'   => __( 'نمایش زمان مطالعه', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::SWITCHER,
                        'default' => 'yes',
                ) );

                $this->add_control( 'show_pagination', array(
                        'label'   => __( 'نمایش صفحه‌بندی', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::SWITCHER,
                        'default' => 'yes',
                        'separator' => 'before',
                ) );

                $this->add_control( 'empty_text', array(
                        'label'   => __( 'متن حالت خالی', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::TEXT,
                        'default' => __( 'مقاله‌ای برای نمایش پیدا نشد.', 'hamishop-elementor-widgets' ),
                ) );

                $this->end_controls_section();

                $this->start_controls_section(
                        'sec_layout',
                        array( 'label' => __( 'چیدمان', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_responsive_control( 'cols', array(
                        'label'          => __( 'تعداد ستون‌ها', 'hamishop-elementor-widgets' ),
                        'type'           => Controls_Manager::SLIDER,
                        'range'          => array( 'px' => array( 'min' => 1, 'max' => 4 ) ),
                        'default'        => array( 'size' => 3 ),
                        'tablet_default' => array( 'size' => 2 ),
                        'mobile_default' => array( 'size' => 1 ),
                        'selectors'      => array( '{{WRAPPER}} .hsw-bgrid__grid' => 'grid-template-columns: repeat({{SIZE}}, minmax(0,1fr));' ),
                ) );

                $this->add_responsive_control( 'gap', array(
                        'label'      => __( 'فاصله کارت‌ها', 'hamishop-elementor-widgets' ),
                        'type'       => Controls_Manager::SLIDER,
                        'size_units' => array( 'px' ),
                        'range'      => array( 'px' => array( 'min' => 8, 'max' => 48 ) ),
                        'default'    => array( 'size' => 20, 'unit' => 'px' ),
                        'selectors'  => array( '{{WRAPPER}} .hsw-bgrid__grid' => 'gap: {{SIZE}}{{UNIT}};' ),
                ) );

                $this->end_controls_section();
        }

        protected function render() {
                $s        = $this->effective_settings();
                $f        = $this->blog_filters();
                $per_page = max( 2, (int) ( $s['per_page'] ?? 6 ) );
                $skip     = 'yes' === ( $s['skip_first'] ?? 'yes' ) && $f['page'] === 1 && '' === $f['s'];

                $q = $this->blog_query(
                        array(
                                'posts_per_page' => $per_page,
                                'offset'         => ( $f['page'] - 1 ) * $per_page + ( $skip ? 1 : 0 ),
                        )
                );

                echo '<div class="' . esc_attr( $this->root_class( 'blog-grid', 'hsw-bgrid' ) ) . '">';

                if ( ! $q->have_posts() ) {
                        echo '<p class="hsw-bgrid__empty">' . esc_html( $s['empty_text'] ?? '' ) . '</p>';
                        echo '</div>';
                        return;
                }

                echo '<div class="hsw-bgrid__grid">';
                $hsw_i = 0;
                foreach ( $q->posts as $post ) {
                        $cat = $this->post_category( $post );

                        echo '<article class="hsw-bgrid__card">';
                        echo '<a class="hsw-bgrid__media" href="' . esc_url( get_permalink( $post ) ) . '">' . $this->post_image( $post, 'medium_large', 0 === $hsw_i ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo '</a>';
                        echo '<div class="hsw-bgrid__body">';
                        if ( $cat ) {
                                echo '<a class="hsw-bgrid__cat" href="' . esc_url( $cat['url'] ) . '">' . esc_html( $cat['name'] ) . '</a>';
                        }
                        echo '<h3 class="hsw-bgrid__title"><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h3>';

                        if ( 'yes' === ( $s['show_excerpt'] ?? 'yes' ) ) {
                                $text = wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : $post->post_content );
                                echo '<p class="hsw-bgrid__ex">' . esc_html( wp_trim_words( $text, (int) ( $s['excerpt_len'] ?? 16 ), '…' ) ) . '</p>';
                        }

                        echo '<div class="hsw-bgrid__meta">';
                        if ( 'yes' === ( $s['show_author'] ?? 'yes' ) ) {
                                echo '<span>' . HSW_Helpers::icon( 'person' ) . esc_html( $this->post_author( $post ) ) . '</span>';
                        }
                        echo '<span>' . HSW_Helpers::icon( 'calendar_today' ) . esc_html( $this->post_date( $post ) ) . '</span>';
                        if ( 'yes' === ( $s['show_read'] ?? 'yes' ) ) {
                                echo '<span>' . HSW_Helpers::icon( 'schedule' ) . esc_html( HSW_Helpers::fa_num( $this->read_time( $post ) ) . ' ' . __( 'دقیقه', 'hamishop-elementor-widgets' ) ) . '</span>';
                        }
                        echo '</div>';

                        echo '</div></article>';
                        $hsw_i++;
                }
                echo '</div>';

                if ( 'yes' === ( $s['show_pagination'] ?? 'yes' ) ) {
                        $found = (int) $q->found_posts;
                        if ( $skip ) {
                                $found = max( 0, $found - 1 );
                        }
                        $max = (int) ceil( $found / $per_page );

                        if ( $max > 1 ) {
                                $base = str_replace( '999999', '%#%', $this->blog_url( array( 'page' => 999999 ) ) );
                                $links = paginate_links(
                                        array(
                                                'base'      => $base,
                                                'format'    => '',
                                                'current'   => $f['page'],
                                                'total'     => $max,
                                                'mid_size'  => 1,
                                                'end_size'  => 1,
                                                'prev_text' => HSW_Helpers::icon( 'chevron_right' ) . '<span class="hsw-bgrid__pnav-t">' . __( 'قبلی', 'hamishop-elementor-widgets' ) . '</span>',
                                                'next_text' => '<span class="hsw-bgrid__pnav-t">' . __( 'بعدی', 'hamishop-elementor-widgets' ) . '</span>' . HSW_Helpers::icon( 'chevron_left' ),
                                                'type'      => 'plain',
                                        )
                                );
                                if ( $links ) {
                                        echo '<nav class="hsw-bgrid__pagi" aria-label="' . esc_attr__( 'صفحه‌بندی مقالات', 'hamishop-elementor-widgets' ) . '">' . $links . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                }
                        }
                }

                echo '</div>';
        }
}
