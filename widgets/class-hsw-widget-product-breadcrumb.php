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
                        $items = $this->breadcrumb_items( $product );
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
                                                        <?php echo HSW_Helpers::icon( 'home', 'hsw-pbread__ic hsw-d' ); ?>
                                                <?php endif; ?>
                                                <span><?php echo esc_html( $label ); ?></span>
                                        </a>
                                <?php endif; ?>
                                <?php if ( ! $is_current ) : ?>
                                        <span class="hsw-pbread__sep" aria-hidden="true">
                                                <span class="hsw-d"><?php echo esc_html( $sep ); ?></span>
                                                <?php echo HSW_Helpers::icon( 'chevron_left', 'hsw-m' ); ?>
                                        </span>
                                <?php endif; ?>
                        <?php endforeach; ?>
                </nav>
                <?php
        }

        /**
         * Real breadcrumb trail: خانه → فروشگاه → deepest product category → product name.
         *
         * WooCommerce has no `wc_get_breadcrumb()` helper (its breadcrumb API
         * echoes directly), so the trail is assembled from real data here —
         * fully dynamic per product.
         *
         * @param \WC_Product $product Current (or preview) product.
         * @return array<int, array{0:string,1:string}> Label/URL pairs.
         */
        protected function breadcrumb_items( $product ) {
                $items = array(
                        array( __( 'خانه', 'hamishop-elementor-widgets' ), home_url( '/' ) ),
                );

                $shop_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;
                if ( $shop_id > 0 ) {
                        $items[] = array( get_the_title( $shop_id ), (string) get_permalink( $shop_id ) );
                }

                if ( function_exists( 'wc_get_product_terms' ) && taxonomy_exists( 'product_cat' ) ) {
                        $terms = wc_get_product_terms( $product->get_id(), 'product_cat', array( 'fields' => 'all' ) );

                        $deepest = null;
                        $depth   = -1;
                        foreach ( (array) $terms as $term ) {
                                $term_depth = count( get_ancestors( $term->term_id, 'product_cat' ) );
                                if ( $term_depth > $depth ) {
                                        $depth   = $term_depth;
                                        $deepest = $term;
                                }
                        }

                        if ( $deepest ) {
                                $chain   = array_reverse( get_ancestors( $deepest->term_id, 'product_cat' ) );
                                $chain[] = $deepest->term_id;

                                foreach ( $chain as $term_id ) {
                                        $term = get_term( $term_id, 'product_cat' );
                                        if ( $term instanceof \WP_Term ) {
                                                $link    = get_term_link( $term );
                                                $items[] = array( $term->name, is_wp_error( $link ) ? '' : (string) $link );
                                        }
                                }
                        }
                }

                $items[] = array( $product->get_name(), '' );

                return $items;
        }
}
