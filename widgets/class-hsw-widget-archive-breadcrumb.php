<?php
/**
 * Widget: Archive breadcrumbs — faithful conversion of the supplied design.
 * Desktop: white bar with home icon + trail. Mobile: compact 11px row.
 * Real trail: shop page → term ancestors → current term.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Archive_Breadcrumb extends HSW_Archive_Base {

        public function get_name() {
                return 'hsw-archive-breadcrumb';
        }

        public function get_title() {
                return __( 'بردکرامب آرشیو (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-site-identity';
        }

        public function get_keywords() {
                return array( 'breadcrumb', 'بردکرامب', 'مسیر', 'آرشیو', 'hamishop' );
        }

        protected function register_controls() {
                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control(
                        'home_label',
                        array(
                                'label'   => __( 'متن خانه', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'فروشگاه اینترنتی', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'show_home_icon',
                        array(
                                'label'   => __( 'آیکون خانه', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_box( 'bc', '{{WRAPPER}} .hsw-abc__bar' );
                $this->a_text( 'bc_t', '{{WRAPPER}} .hsw-abc__bar' );
                $this->a_color( 'bc_link', __( 'رنگ لینک‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-abc__link' );
                $this->a_color( 'bc_cur', __( 'رنگ آیتم فعلی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-abc__cur' );
                $this->a_color( 'bc_ic', __( 'رنگ آیکون‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-abc__bar .hsw-ic, {{WRAPPER}} .hsw-abc__ic' );

                $this->end_controls_section();
        }

        /**
         * Breadcrumb trail: [ label, url|null ].
         */
        protected function trail() {
                $s = $this->effective_settings();
                if ( $this->is_demo() || ! $this->is_archive_context() ) {
                        $d    = $this->demo();
                        $list = array();
                        foreach ( $d['crumbs'] as $c ) {
                                $list[] = array( $c, '#' );
                        }
                        $list[] = array( $d['term'], null );
                        return $list;
                }

                $home  = isset( $s['home_label'] ) && '' !== trim( (string) $s['home_label'] ) ? $s['home_label'] : __( 'فروشگاه اینترنتی', 'hamishop-elementor-widgets' );
                $trail = array( array( $home, function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ) );

                $term = $this->current_term();
                if ( $term && 'product_cat' === $term->taxonomy ) {
                        $ancestors = array_reverse( get_ancestors( $term->term_id, 'product_cat' ) );
                        foreach ( $ancestors as $anc_id ) {
                                $anc = get_term( $anc_id, 'product_cat' );
                                if ( $anc && ! is_wp_error( $anc ) ) {
                                        $trail[] = array( $anc->name, get_term_link( $anc ) );
                                }
                        }
                        $trail[] = array( $term->name, null );
                } elseif ( $term ) {
                        $trail[] = array( $term->name, null );
                } else {
                        $trail = array( array( $home, null ) ); // shop root.
                }
                return $trail;
        }

        protected function render() {
                $trail = $this->trail();
                $s     = $this->effective_settings();
                $home_ic = ( 'yes' === ( $s['show_home_icon'] ?? 'yes' ) );
                ?>
                <nav class="<?php echo esc_attr( $this->root_class( 'abc' ) ); ?>" aria-label="<?php esc_attr_e( 'مسیر راهنما', 'hamishop-elementor-widgets' ); ?>">
                        <div class="hsw-abc__bar">
                                <?php foreach ( $trail as $i => $item ) : ?>
                                        <?php if ( $i > 0 ) : ?>
                                                <?php echo HSW_Helpers::icon( 'chevron_left', 'hsw-abc__sep' ); ?>
                                        <?php endif; ?>
                                        <?php if ( null !== $item[1] ) : ?>
                                                <a class="hsw-abc__link<?php echo ( 0 === $i && $home_ic ) ? ' has-home' : ''; ?>" href="<?php echo esc_url( $item[1] ); ?>">
                                                        <?php if ( 0 === $i && $home_ic ) : ?>
                                                                <?php echo HSW_Helpers::icon( 'home', 'hsw-abc__ic' ); ?>
                                                        <?php endif; ?>
                                                        <span><?php echo esc_html( $item[0] ); ?></span>
                                                </a>
                                        <?php else : ?>
                                                <span class="hsw-abc__cur"><?php echo esc_html( $item[0] ); ?></span>
                                        <?php endif; ?>
                                <?php endforeach; ?>
                        </div>
                </nav>
                <?php
        }
}
