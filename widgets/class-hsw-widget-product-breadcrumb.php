<?php
/**
 * Widget: Breadcrumb محصول — faithful conversion of the supplied design.
 * Desktop: home icon + "/" separators. Mobile: text-only + chevron separators,
 * horizontally scrollable (hsw-noscroll).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Product_Breadcrumb extends HSW_Product_Base {

        public function get_name() {
                return 'hsw-product-breadcrumb';
        }

        public function get_title() {
                return __( 'همی‌راه محصول (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-product-breadcrumbs';
        }

        public function get_keywords() {
                return array( 'breadcrumb', 'مسیر', 'همی راه', 'hamishop', 'hamisys' );
        }

        protected function register_controls() {
                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control(
                        'show_home_icon',
                        array(
                                'label'     => __( 'آیکون خانه (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SWITCHER,
                                'default'   => 'yes',
                        )
                );

                $this->add_control(
                        'sep_text',
                        array(
                                'label'     => __( 'جداکننده (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => '/',
                                'label_block' => false,
                        )
                );

                $this->add_responsive_control(
                        'spacing_bottom',
                        array(
                                'label'      => __( 'فاصله از پایین', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
                                'default'    => array( 'size' => 24, 'unit' => 'px' ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-pbread' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->ctrl_text( 'bc_link', '{{WRAPPER}} .hsw-pbread__link', array( 'hover' => true ) );

                $this->ctrl_text( 'bc_cur', '{{WRAPPER}} .hsw-pbread__cur' );

                $this->add_control(
                        'bc_sep_heading',
                        array( 'label' => __( 'جداکننده', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->add_control(
                        'bc_sep_c',
                        array(
                                'label'     => __( 'رنگ جداکننده', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-pbread__sep' => 'color: {{VALUE}};' ),
                        )
                );

                $this->add_control(
                        'bc_ic_heading',
                        array( 'label' => __( 'آیکون‌ها', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->ctrl_icon( 'bc_home_ic', '{{WRAPPER}} .hsw-pbread__ic' );

                $this->end_controls_section();
        }

        protected function render() {
                list( $product, $demo ) = $this->product_or_demo();
                $s   = $this->effective_settings();
                $sep = isset( $s['sep_text'] ) && '' !== $s['sep_text'] ? $s['sep_text'] : '/';

                // Live front-end outside a single-product context → render nothing (no mock data).
                if ( ! $product && ! $demo ) {
                        return;
                }

                if ( $demo ) {
                        $items = array(
                                array( 'خانه', '#' ),
                                array( 'کالای دیجیتال', '#' ),
                                array( 'گوشی موبایل', '#' ),
                                array( 'سامسونگ', '' ),
                        );
                } else {
                        $items = function_exists( 'wc_get_breadcrumb' ) ? wc_get_breadcrumb() : array();
                }

                if ( empty( $items ) ) {
                        return;
                }

                $last  = count( $items ) - 1;
                $extra = 'hsw-noscroll';
                ?>
                <nav class="<?php echo esc_attr( $this->root_class( 'pbread', $extra ) ); ?>" aria-label="<?php esc_attr_e( 'مسیر راهنما', 'hamishop-elementor-widgets' ); ?>">
                        <?php foreach ( $items as $i => $item ) : ?>
                                <?php
                                $label = isset( $item[0] ) ? $item[0] : '';
                                $url   = isset( $item[1] ) ? $item[1] : '';
                                if ( '' === trim( (string) $label ) ) {
                                        continue;
                                }
                                $is_current = ( $i === $last );
                                ?>
                                <?php if ( $is_current || empty( $url ) ) : ?>
                                        <span class="hsw-pbread__cur"><?php echo esc_html( $label ); ?></span>
                                <?php else : ?>
                                        <a class="hsw-pbread__link" href="<?php echo esc_url( $url ); ?>">
                                                <?php if ( 0 === $i && 'yes' === ( $s['show_home_icon'] ?? 'yes' ) ) : ?>
                                                        <span class="hsw-ic hsw-pbread__ic hsw-d">home</span>
                                                <?php endif; ?>
                                                <span><?php echo esc_html( $label ); ?></span>
                                        </a>
                                <?php endif; ?>
                                <?php if ( ! $is_current ) : ?>
                                        <span class="hsw-pbread__sep" aria-hidden="true">
                                                <span class="hsw-d"><?php echo esc_html( $sep ); ?></span>
                                                <span class="hsw-ic hsw-m">chevron_left</span>
                                        </span>
                                <?php endif; ?>
                        <?php endforeach; ?>
                </nav>
                <?php
        }
}
