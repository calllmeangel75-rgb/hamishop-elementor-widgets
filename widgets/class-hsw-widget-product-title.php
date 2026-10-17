<?php
/**
 * Widget: Title, rating & meta of the product — faithful conversion of the
 * supplied design. Desktop: EN-title + SKU meta row, switchable H1/H2 title,
 * rating bar (score + reviews link + recommend percent). Mobile: stock pill
 * (pulse dot) + score row, H2 title, EN subtitle.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Product_Title extends HSW_Product_Base {

        /**
         * Widget slug (Elementor name).
         */
        public function get_name() {
                return 'hsw-product-title';
        }

        /**
         * Widget title in the editor panel.
         */
        public function get_title() {
                return __( 'عنوان، امتیاز و متای محصول (HamiShop)', 'hamishop-elementor-widgets' );
        }

        /**
         * Widget icon.
         */
        public function get_icon() {
                return 'eicon-product-title';
        }

        /**
         * Widget search keywords.
         */
        public function get_keywords() {
                return array( 'title', 'rating', 'عنوان محصول', 'امتیاز', 'دیدگاه', 'hamishop', 'hamisys' );
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
                        'title_en',
                        array(
                                'label'       => __( 'عنوان انگلیسی (متا / زیرنویس)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'label_block' => true,
                                'description' => __( 'خالی بگذارید تا در حالت دمو از داده نمونه استفاده شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'recommend',
                        array(
                                'label'   => __( 'درصد پیشنهاد خریداران (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => '۸۹',
                        )
                );

                $this->add_control(
                        'show_stock',
                        array(
                                'label'   => __( 'قرص موجودی (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'title_tag',
                        array(
                                'label'   => __( 'تگ عنوان (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => 'h1',
                                'options' => array(
                                        'h1' => 'H1',
                                        'h2' => 'H2',
                                ),
                        )
                );

                $this->add_control(
                        'rev_url',
                        array(
                                'label'       => __( 'لینک بخش دیدگاه‌ها', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::URL,
                                'default'     => array( 'url' => '' ),
                                'description' => __( 'خالی = #reviews', 'hamishop-elementor-widgets' ),
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
                                'selectors'  => array( '{{WRAPPER}} .hsw-ptitle' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->ctrl_text( 'tt', '{{WRAPPER}} .hsw-ptitle__h1, {{WRAPPER}} .hsw-ptitle__h2' );

                $this->add_control(
                        'tt_rate_heading',
                        array( 'label' => __( 'نوار امتیاز', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->add_control(
                        'tt_star_c',
                        array(
                                'label'     => __( 'رنگ ستاره', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-ptitle__star' => '--hsw-rate-star: {{VALUE}};' ),
                        )
                );
                $this->add_control(
                        'tt_revlink_c',
                        array(
                                'label'     => __( 'رنگ لینک دیدگاه‌ها', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-ptitle__revlink' => '--hsw-rate-link: {{VALUE}};' ),
                        )
                );

                $this->ctrl_text( 'tt_en', '{{WRAPPER}} .hsw-ptitle__en', array( 'separator' => true ) );

                $this->ctrl_text( 'tt_sku', '{{WRAPPER}} .hsw-ptitle__sku', array( 'separator' => true ) );

                $this->add_control(
                        'tt_stock_heading',
                        array( 'label' => __( 'قرص موجودی (موبایل)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->add_control(
                        'tt_stock_bg',
                        array(
                                'label'     => __( 'پس‌زمینه قرص', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-ptitle__stock' => '--hsw-stock-bg: {{VALUE}};' ),
                        )
                );
                $this->add_control(
                        'tt_stock_c',
                        array(
                                'label'     => __( 'رنگ متن قرص', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-ptitle__stock' => 'color: {{VALUE}};' ),
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Render the desktop + mobile title blocks.
         */
        protected function render() {
                list( $product, $demo ) = $this->product_or_demo();
                $s = $this->get_settings_for_display();

                if ( ! $product && ! $this->is_demo() ) {
                        return; // No product data outside the editor — render nothing.
                }

                $d = $this->demo();

                $name_en = isset( $s['title_en'] ) ? trim( (string) $s['title_en'] ) : '';
                $rec     = ( isset( $s['recommend'] ) && '' !== trim( (string) $s['recommend'] ) ) ? trim( (string) $s['recommend'] ) : '۸۹';
                $tag     = ( isset( $s['title_tag'] ) && 'h2' === $s['title_tag'] ) ? 'h2' : 'h1';
                $rev_url = ( isset( $s['rev_url']['url'] ) && '' !== $s['rev_url']['url'] ) ? $s['rev_url']['url'] : '#reviews';

                if ( $demo ) {
                        $name     = $d['name'];
                        $sku      = $d['sku'];
                        $rating   = $d['rating'];
                        $reviews  = HSW_Helpers::fa_num( $d['reviews'] );
                        $in_stock = (bool) $d['in_stock'];
                        if ( '' === $name_en ) {
                                $name_en = $d['name_en'];
                        }
                } else {
                        $name     = $product->get_name();
                        $sku      = $product->get_sku();
                        $rating   = HSW_Helpers::fa_num( number_format( (float) $product->get_average_rating(), 1 ) );
                        $reviews  = HSW_Helpers::fa_num( $product->get_review_count() );
                        $in_stock = (bool) $product->is_in_stock();
                }

                $stock_text = $in_stock ? __( 'موجود در انبار', 'hamishop-elementor-widgets' ) : __( 'ناموجود', 'hamishop-elementor-widgets' );
                ?>
                <div class="<?php echo esc_attr( $this->root_class( 'ptitle' ) ); ?>">
                        <div class="hsw-d">
                                <div class="hsw-ptitle__meta">
                                        <?php if ( '' !== $name_en ) : ?>
                                                <span class="hsw-ptitle__en" dir="ltr"><?php echo esc_html( $name_en ); ?></span>
                                        <?php endif; ?>
                                        <?php if ( '' !== (string) $sku ) : ?>
                                                <span class="hsw-ptitle__sku"><?php esc_html_e( 'کد کالا:', 'hamishop-elementor-widgets' ); ?> <?php echo esc_html( $sku ); ?></span>
                                        <?php endif; ?>
                                </div>
                                <<?php echo esc_html( $tag ); ?> class="hsw-ptitle__h1"><?php echo esc_html( $name ); ?></<?php echo esc_html( $tag ); ?>>
                                <div class="hsw-ptitle__ratebar">
                                        <span class="hsw-ptitle__score">
                                                <span class="hsw-ic hsw-ic--fill hsw-ptitle__star">star</span>
                                                <b><?php echo esc_html( $rating ); ?></b><i>(<?php esc_html_e( 'از ۵', 'hamishop-elementor-widgets' ); ?>)</i>
                                        </span>
                                        <span class="hsw-ptitle__dot" aria-hidden="true"></span>
                                        <a class="hsw-ptitle__revlink" href="<?php echo esc_url( $rev_url ); ?>"><?php echo esc_html( $reviews ); ?> <?php esc_html_e( 'دیدگاه ثبت شده کاربران', 'hamishop-elementor-widgets' ); ?></a>
                                        <span class="hsw-ptitle__dot" aria-hidden="true"></span>
                                        <span class="hsw-ptitle__rec"><?php echo esc_html( $rec ); ?>٪ <?php esc_html_e( 'خریداران پیشنهاد کرده‌اند', 'hamishop-elementor-widgets' ); ?></span>
                                </div>
                        </div>
                        <div class="hsw-m">
                                <div class="hsw-ptitle__mrow">
                                        <?php if ( 'yes' === ( $s['show_stock'] ?? 'yes' ) ) : ?>
                                                <?php if ( $in_stock ) : ?>
                                                        <span class="hsw-ptitle__stock"><i class="hsw-ptitle__pulse" aria-hidden="true"></i><?php echo esc_html( $stock_text ); ?></span>
                                                <?php else : ?>
                                                        <span class="hsw-ptitle__stock is-oos" style="color:var(--hsw-secondary)"><i class="hsw-ptitle__pulse" aria-hidden="true"></i><?php echo esc_html( $stock_text ); ?></span>
                                                <?php endif; ?>
                                        <?php endif; ?>
                                        <span class="hsw-ptitle__mscore">
                                                <span class="hsw-ptitle__mcnt">(<?php echo esc_html( $reviews ); ?> <?php esc_html_e( 'نظر', 'hamishop-elementor-widgets' ); ?>)</span>
                                                <b><?php echo esc_html( $rating ); ?></b>
                                                <span class="hsw-ic hsw-ic--fill">star</span>
                                        </span>
                                </div>
                                <h2 class="hsw-ptitle__h2"><?php echo esc_html( $name ); ?></h2>
                                <?php if ( '' !== $name_en ) : ?>
                                        <p class="hsw-ptitle__men" dir="ltr"><?php echo esc_html( $name_en ); ?></p>
                                <?php endif; ?>
                        </div>
                </div>
                <?php
        }
}
