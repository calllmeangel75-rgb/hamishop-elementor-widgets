<?php
/**
 * Widget: گالری محصول — faithful conversion of the supplied design.
 * Desktop (hsw-d): discount badge + zoom button + main image + thumbnails row.
 * Mobile (hsw-m): discount/authenticity badges + main image + pagination dots.
 * Thumbs/dots carry data-hsw-src and swap .hsw-pgal__img (hsw-product.js).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Product_Gallery extends HSW_Product_Base {

        /**
         * Widget slug.
         */
        public function get_name() {
                return 'hsw-product-gallery';
        }

        /**
         * Widget label in the Elementor panel.
         */
        public function get_title() {
                return __( 'گالری محصول (HamiShop)', 'hamishop-elementor-widgets' );
        }

        /**
         * Widget icon.
         */
        public function get_icon() {
                return 'eicon-product-images';
        }

        /**
         * Search keywords.
         */
        public function get_keywords() {
                return array( 'gallery', 'گالری', 'تصویر محصول', 'hamishop', 'hamisys' );
        }

        /**
         * Accessible label for a gallery image («تصویر ۱», «تصویر ۲»…).
         *
         * @param int $n One-based image index.
         * @return string
         */
        private function image_label( $n ) {
                /* translators: %s: image number. */
                return sprintf( __( 'تصویر %s', 'hamishop-elementor-widgets' ), HSW_Helpers::fa_num( $n ) );
        }

        /**
         * Register content + style controls.
         */
        protected function register_controls() {
                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control(
                        'show_zoom',
                        array(
                                'label'   => __( 'دکمه زوم (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'show_auth',
                        array(
                                'label'   => __( 'بج اصالت کالا (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'auth_text',
                        array(
                                'label'       => __( 'متن بج اصالت', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'اصالت کالا', 'hamishop-elementor-widgets' ),
                                'label_block' => false,
                        )
                );

                $this->add_control(
                        'thumbs_count',
                        array(
                                'label'       => __( 'حداکثر تعداد بندانگشت‌ها (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::NUMBER,
                                'default'     => 0,
                                'min'         => 0,
                                'max'         => 20,
                                'step'        => 1,
                                'description' => __( 'صفر یعنی نمایش همه تصاویر گالری.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_responsive_control(
                        'spacing_bottom',
                        array(
                                'label'      => __( 'فاصله از پایین', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 0, 'max' => 100 ) ),
                                'default'    => array( 'size' => 0, 'unit' => 'px' ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-pgal__card' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->ctrl_box( 'gal', '{{WRAPPER}} .hsw-pgal__card' );

                $this->add_control(
                        'gal_badge_heading',
                        array( 'label' => __( 'بج تخفیف', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->add_control(
                        'gal_badge_bg',
                        array(
                                'label'     => __( 'رنگ پس‌زمینه', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-pgal__badge' => '--hsw-badge-bg: {{VALUE}};' ),
                        )
                );
                $this->add_control(
                        'gal_badge_c',
                        array(
                                'label'     => __( 'رنگ متن', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-pgal__badge' => '--hsw-badge-c: {{VALUE}};' ),
                        )
                );

                $this->add_control(
                        'gal_frame_heading',
                        array( 'label' => __( 'قاب تصویر', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->add_control(
                        'gal_frame_bg',
                        array(
                                'label'     => __( 'پس‌زمینه قاب', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-pgal__frame' => 'background-color: {{VALUE}};' ),
                        )
                );

                $this->add_control(
                        'gal_auth_heading',
                        array( 'label' => __( 'بج اصالت (موبایل)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->add_control(
                        'gal_auth_bg',
                        array(
                                'label'     => __( 'رنگ پس‌زمینه', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-pgal__auth' => '--hsw-auth-bg: {{VALUE}};' ),
                        )
                );
                $this->add_control(
                        'gal_auth_c',
                        array(
                                'label'     => __( 'رنگ متن و آیکون', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-pgal__auth' => '--hsw-auth-c: {{VALUE}};' ),
                        )
                );

                $this->add_control(
                        'gal_dots_heading',
                        array( 'label' => __( 'نقاط اسلاید (موبایل)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->add_control(
                        'gal_dot_bg',
                        array(
                                'label'     => __( 'رنگ نقطه فعال', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-pgal__dot.is-active' => '--hsw-dot-bg: {{VALUE}};' ),
                        )
                );

                $this->add_control(
                        'gal_thumbs_heading',
                        array( 'label' => __( 'بندانگشت‌ها (دسکتاپ)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->add_control(
                        'gal_thumb_size',
                        array(
                                'label'      => __( 'اندازه بندانگشت', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 32, 'max' => 160 ) ),
                                'default'    => array( 'size' => 64, 'unit' => 'px' ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-pgal__thumb' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->add_control(
                        'gal_zoom_heading',
                        array( 'label' => __( 'دکمه زوم', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->ctrl_icon( 'gal_zoom', '{{WRAPPER}} .hsw-pgal__zoom .hsw-ic' );

                $this->end_controls_section();
        }

        /**
         * Render the gallery: one desktop card (badge, zoom, frame, thumbs)
         * plus mobile-only badges and dots — all inside the same card.
         */
        protected function render() {
                list( $product, $demo ) = $this->product_or_demo();
                $s           = $this->effective_settings();
                $placeholder = HSW_URL . 'assets/img/placeholder.svg';

                // Live front-end outside a single-product context → render nothing (no mock data).
                if ( ! $product && ! $demo ) {
                        return;
                }

                if ( $demo ) {
                        $data     = $this->demo();
                        $images   = isset( $data['gallery'] ) && ! empty( $data['gallery'] ) ? $data['gallery'] : array( $placeholder );
                        $discount = isset( $data['discount'] ) ? (int) $data['discount'] : 0;
                        $title    = isset( $data['name'] ) ? $data['name'] : '';
                } else {
                        if ( ! $product ) {
                                return;
                        }
                        $images = array();
                        foreach ( $this->gallery_ids( $product ) as $attachment_id ) {
                                $url = wp_get_attachment_image_url( $attachment_id, 'woocommerce_single' );
                                $images[] = $url ? $url : $placeholder;
                        }
                        if ( empty( $images ) ) {
                                $images = array( $placeholder );
                        }
                        $prices   = $this->display_prices( $product );
                        $discount = (int) $prices['discount'];
                        $title    = $product->get_name();
                }

                $show_auth = ( 'yes' === ( $s['show_auth'] ?? 'yes' ) );
                $auth_text = isset( $s['auth_text'] ) && '' !== $s['auth_text'] ? $s['auth_text'] : __( 'اصالت کالا', 'hamishop-elementor-widgets' );

                $limit  = isset( $s['thumbs_count'] ) ? (int) $s['thumbs_count'] : 0;
                $thumbs = ( $limit > 0 ) ? array_slice( $images, 0, $limit ) : $images;
                $badge  = '٪' . HSW_Helpers::fa_num( $discount ) . ' تخفیف';
                ?>
                <section class="<?php echo esc_attr( $this->root_class( 'pgal' ) ); ?>">
                        <div class="hsw-pgal__card">
                                <?php if ( $discount > 0 ) : ?>
                                        <span class="hsw-pgal__badge hsw-d"><?php echo esc_html( $badge ); ?></span>
                                <?php endif; ?>

                                <?php if ( 'yes' === ( $s['show_zoom'] ?? 'yes' ) ) : ?>
                                        <button type="button" class="hsw-pgal__zoom hsw-d" data-hsw-zoom aria-label="<?php esc_attr_e( 'بزرگنمایی تصویر', 'hamishop-elementor-widgets' ); ?>">
                                                <span class="hsw-ic">zoom_in</span>
                                        </button>
                                <?php endif; ?>

                                <div class="hsw-pgal__frame">
                                        <img class="hsw-pgal__img" src="<?php echo esc_url( $images[0] ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="eager" fetchpriority="high" decoding="async">
                                </div>

                                <?php if ( count( $images ) > 1 ) : ?>
                                        <div class="hsw-pgal__thumbs hsw-d">
                                                <?php foreach ( $thumbs as $i => $url ) : ?>
                                                        <button type="button" class="hsw-pgal__thumb<?php echo esc_attr( 0 === $i ? ' is-active' : '' ); ?>" data-hsw-src="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( $this->image_label( $i + 1 ) ); ?>">
                                                                <img src="<?php echo esc_url( $url ); ?>" alt="" loading="lazy" decoding="async">
                                                        </button>
                                                <?php endforeach; ?>
                                        </div>
                                <?php endif; ?>

                                <?php if ( $discount > 0 || $show_auth ) : ?>
                                        <div class="hsw-pgal__mbadges hsw-m">
                                                <?php if ( $discount > 0 ) : ?>
                                                        <span class="hsw-pgal__badge"><?php echo esc_html( $badge ); ?></span>
                                                <?php endif; ?>
                                                <?php if ( $show_auth ) : ?>
                                                        <span class="hsw-pgal__auth"><span class="hsw-ic hsw-ic--fill">verified</span><?php echo esc_html( $auth_text ); ?></span>
                                                <?php endif; ?>
                                        </div>
                                <?php endif; ?>

                                <?php if ( count( $images ) > 1 ) : ?>
                                        <div class="hsw-pgal__dots hsw-m">
                                                <?php foreach ( $images as $i => $url ) : ?>
                                                        <button type="button" class="hsw-pgal__dot<?php echo esc_attr( 0 === $i ? ' is-active' : '' ); ?>" data-hsw-src="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( $this->image_label( $i + 1 ) ); ?>"></button>
                                                <?php endforeach; ?>
                                        </div>
                                <?php endif; ?>
                        </div>
                </section>
                <?php
        }
}
