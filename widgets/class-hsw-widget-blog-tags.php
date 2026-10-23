<?php
/**
 * Blog widget: hot tags cloud (برچسب‌های داغ).
 * Real tags ordered by usage count; links go to tag archives.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Blog_Tags extends HSW_Blog_Base {

        public function get_name() {
                return 'hsw-blog-tags';
        }

        public function get_title() {
                return __( 'وبلاگ: برچسب‌های داغ (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-tags';
        }

        public function get_keywords() {
                return array( 'blog', 'tags', 'tag cloud', 'برچسب', 'hamishop' );
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
                        'default'     => __( 'برچسب‌های داغ', 'hamishop-elementor-widgets' ),
                        'label_block' => true,
                ) );

                $this->add_control( 'count', array(
                        'label'   => __( 'تعداد برچسب‌ها', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::NUMBER,
                        'default' => 12,
                        'min'     => 2,
                        'max'     => 40,
                ) );

                $this->add_control( 'hot_first', array(
                        'label'   => __( 'هایلایت برچسب اول', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::SWITCHER,
                        'default' => 'yes',
                ) );

                $this->end_controls_section();

		/*
		 * One type scale per widget. The variables land on the wrapper, so a
		 * child element only has to read them and the generated CSS stays a
		 * single declaration per part.
		 */
		$this->style_typography_section(
			'btags',
			array(
			'title' => array( __( 'عنوان بخش', 'hamishop-elementor-widgets' ), 15 ),
			'tag' => array( __( 'متن برچسب‌ها', 'hamishop-elementor-widgets' ), 12.5 ),
			)
		);
        }

        protected function render() {
                $s    = $this->effective_settings();
                $tags = $this->blog_tags( (int) ( $s['count'] ?? 12 ) );

                if ( ! $tags ) {
                        return;
                }

                echo '<div class="' . esc_attr( $this->root_class( 'blog-tags', 'hsw-btags' ) ) . '">';

                if ( ! empty( $s['title'] ) ) {
                        echo '<h3 class="hsw-btags__title">' . esc_html( $s['title'] ) . '</h3>';
                }

                echo '<div class="hsw-btags__cloud">';
                foreach ( $tags as $i => $tag ) {
                        $hot = ( 'yes' === ( $s['hot_first'] ?? 'yes' ) && 0 === $i ) ? ' is-hot' : '';
                        printf(
                                '<a class="hsw-btags__tag%s" href="%s">%s<span class="hsw-btags__n">%s</span></a>',
                                $hot,
                                esc_url( get_tag_link( $tag ) ),
                                esc_html( $tag->name ),
                                esc_html( HSW_Helpers::fa_num( (int) $tag->count ) )
                        );
                }
                echo '</div>';
                echo '</div>';
        }
}
