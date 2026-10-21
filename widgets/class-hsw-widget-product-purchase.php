<?php
/**
 * Widget: جعبه خرید و قیمت — faithful conversion of the supplied design.
 * Price block (old row + save chip + main price), quantity stepper,
 * add-to-cart CTA and the assurance rows.
 *
 * JS contract: data-hsw-product (+ data-hsw-max) on the root,
 * data-hsw-old / data-hsw-save / data-hsw-price on the price fields,
 * data-hsw-qty / data-hsw-qval on the stepper, data-hsw-add on the button.
 * Out-of-stock products get the is-oos root class.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class HSW_Widget_Product_Purchase extends HSW_Product_Base {

        /**
         * Widget slug.
         */
        public function get_name() {
                return 'hsw-product-purchase';
        }

        /**
         * Widget title.
         */
        public function get_title() {
                return __( 'جعبه خرید و قیمت (HamiShop)', 'hamishop-elementor-widgets' );
        }

        /**
         * Widget icon.
         */
        public function get_icon() {
                return 'eicon-product-add-to-cart';
        }

        /**
         * Widget keywords.
         */
        public function get_keywords() {
                return array( 'purchase', 'add to cart', 'خرید', 'قیمت', 'سبد خرید', 'hamishop', 'hamisys' );
        }

        /**
         * The design's two default assurance rows (stock line follows live status).
         *
         * @param bool $in_stock Whether the product is in stock.
         * @return array
         */
        protected function default_assure_items( $in_stock ) {
                $stock_text = $in_stock
                        ? __( 'موجود در انبار تهران - آماده پردازش و ارسال', 'hamishop-elementor-widgets' )
                        : __( 'فعلاً ناموجود', 'hamishop-elementor-widgets' );
                return array(
                        array(
                                'icon' => 'shield',
                                'text' => __( 'گارانتی ۱۸ ماهه همراه با کد فعال‌سازی رجیستری', 'hamishop-elementor-widgets' ),
                                'type' => 'shield',
                        ),
                        array(
                                'icon' => 'inventory_2',
                                'text' => $stock_text,
                                'type' => 'stock',
                        ),
                );
        }

        /**
         * Register widget controls.
         */
        protected function register_controls() {
                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility( 'desktop' );

                $this->add_control(
                        'cart_text',
                        array(
                                'label'   => __( 'متن دکمه خرید آنلاین', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'خرید آنلاین', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'qty_max',
                        array(
                                'label'       => __( 'حداکثر تعداد', 'hamishop-elementor-widgets' ),
                                'description' => __( 'به‌صورت data-hsw-max روی جعبه چاپ می‌شود (موتور JS فعلاً سقف ۹۹ را دارد).', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::NUMBER,
                                'default'     => 99,
                                'min'         => 1,
                                'max'         => 999,
                        )
                );

                $repeater = new Repeater();
                $repeater->add_control(
                        'icon',
                        array(
                                'label'   => __( 'آیکون', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => 'shield',
                                'options' => $this->product_icon_options(),
                        )
                );
                $repeater->add_control(
                        'text',
                        array(
                                'label'       => __( 'متن', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => '',
                                'label_block' => true,
                        )
                );
                $repeater->add_control(
                        'type',
                        array(
                                'label'   => __( 'نوع خط (رنگ آیکون)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => 'shield',
                                'options' => array(
                                        'shield' => __( 'سپر (گارانتی)', 'hamishop-elementor-widgets' ),
                                        'stock'  => __( 'موجودی', 'hamishop-elementor-widgets' ),
                                ),
                        )
                );
                $this->add_control(
                        'assure_items',
                        array(
                                'label'       => __( 'خطوط اطمینان', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $repeater->get_controls(),
                                'default'     => $this->default_assure_items( true ),
                                'title_field' => '{{{ text }}}',
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->ctrl_box( 'pb', '{{WRAPPER}} .hsw-ppurch' );

                $this->add_control(
                        'pp_price_heading',
                        array( 'label' => __( 'قیمت', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->add_control(
                        'pp_price_var',
                        array(
                                'label'     => __( 'رنگ قیمت اصلی', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-ppurch__main' => '--hsw-price-c: {{VALUE}};' ),
                        )
                );
                $this->ctrl_text( 'pp_price', '{{WRAPPER}} .hsw-ppurch__main' );

                $this->add_control(
                        'pp_unit_c',
                        array(
                                'label'     => __( 'رنگ واحد (تومان)', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-ppurch__unit' => '--hsw-unit-c: {{VALUE}};' ),
                        )
                );

                $this->ctrl_text( 'pp_old', '{{WRAPPER}} .hsw-ppurch__old' );

                $this->add_control(
                        'pp_save_var',
                        array(
                                'label'     => __( 'رنگ متن چیپ تخفیف', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-ppurch__save' => '--hsw-save-c: {{VALUE}};' ),
                        )
                );
                $this->add_control(
                        'pp_save_bg',
                        array(
                                'label'     => __( 'پس‌زمینه چیپ تخفیف', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-ppurch__save' => 'background-color: {{VALUE}};' ),
                        )
                );

                $this->add_control(
                        'pp_ctas_heading',
                        array( 'label' => __( 'دکمه‌ها', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->add_control(
                        'pp_cart_bgc',
                        array(
                                'label'     => __( 'پس‌زمینه دکمه سبد', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-ppurch__cart' => '--hsw-cart-bg: {{VALUE}};' ),
                        )
                );
                $this->add_control(
                        'pp_cart_col',
                        array(
                                'label'     => __( 'رنگ متن دکمه سبد', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-ppurch__cart' => '--hsw-cart-c: {{VALUE}};' ),
                        )
                );
                $this->ctrl_text( 'pp_cart', '{{WRAPPER}} .hsw-ppurch__cart' );
                $this->add_responsive_control(
                        'pp_cart_radius',
                        array(
                                'label'     => __( 'گردی دکمه سبد', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SLIDER,
                                'range'     => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
                                'selectors' => array( '{{WRAPPER}} .hsw-ppurch__cart' => 'border-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->add_control(
                        'pp_as_heading',
                        array( 'label' => __( 'خطوط اطمینان', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->ctrl_text( 'pp_as', '{{WRAPPER}} .hsw-ppurch__as' );
                $this->ctrl_icon( 'pp_as_ic', '{{WRAPPER}} .hsw-ppurch__as .hsw-ic' );

                $this->end_controls_section();
        }

        /**
         * Render the purchase box.
         */
        protected function render() {
                list( $product, $demo ) = $this->product_or_demo();
                $s = $this->effective_settings();

                // Live front-end outside a single-product context → render nothing (no mock data).
                if ( ! $product && ! $demo ) {
                        return;
                }

                $cfp = ( ! $demo && $product ) ? HSW_Helpers::is_call_for_price( $product ) : false;

                if ( $demo ) {
                        $d        = $this->demo();
                        $pid      = 0;
                        $sale     = $d['sale'];
                        $regular  = $d['regular'];
                        $save     = $d['save'];
                        $discount = isset( $d['discount'] ) ? (int) $d['discount'] : 0;
                        $in_stock = ! empty( $d['in_stock'] );
                } else {
                        $p        = $this->display_prices( $product );
                        $pid      = $product->get_id();
                        $sale     = HSW_Helpers::price_num( $p['sale'] );
                        $regular  = HSW_Helpers::price_num( $p['regular'] );
                        $save     = HSW_Helpers::price_num( $p['regular'] - $p['sale'] );
                        $discount = (int) $p['discount'];
                        $in_stock = $product->is_in_stock();
                }

                /* Old price + save chip are hidden when there is no discount (JS parity). */
                $has_discount = ( $discount > 0 );
                $hide_attr    = $has_discount ? '' : ' style="display:none"';

                $cart_text = ( isset( $s['cart_text'] ) && '' !== trim( (string) $s['cart_text'] ) ) ? $s['cart_text'] : __( 'خرید آنلاین', 'hamishop-elementor-widgets' );
                $qty_max   = isset( $s['qty_max'] ) ? max( 1, (int) $s['qty_max'] ) : 99;

                /* Assurance rows: repeater (type stock follows live stock status). */
                $assure = array();
                $raw    = ( isset( $s['assure_items'] ) && is_array( $s['assure_items'] ) ) ? $s['assure_items'] : array();
                foreach ( $raw as $item ) {
                        $type = ( isset( $item['type'] ) && 'stock' === $item['type'] ) ? 'stock' : 'shield';
                        $icon = isset( $item['icon'] ) ? $item['icon'] : ( 'stock' === $type ? 'inventory_2' : 'shield' );
                        $text = isset( $item['text'] ) ? $item['text'] : '';
                        if ( 'stock' === $type && ! $in_stock ) {
                                $text = __( 'فعلاً ناموجود', 'hamishop-elementor-widgets' );
                        }
                        if ( '' === trim( (string) $text ) ) {
                                continue;
                        }
                        $assure[] = array( 'icon' => $icon, 'text' => $text, 'type' => $type );
                }
                if ( empty( $assure ) ) {
                        $assure = $this->default_assure_items( $in_stock );
                }

                $root_extra = ( $in_stock || $cfp ) ? '' : 'is-oos';
                if ( $cfp ) {
                        $root_extra .= ' hsw-ppurch--cfp';
                }
                ?>
                <div class="<?php echo esc_attr( $this->root_class( 'ppurch', $root_extra ) ); ?>" data-hsw-product="<?php echo esc_attr( $pid ); ?>" data-hsw-max="<?php echo esc_attr( $qty_max ); ?>"<?php echo $cfp ? ' data-hsw-cfp="1"' : ''; ?>>
                        <?php if ( $cfp ) : ?>
                                <div class="hsw-ppurch__top hsw-ppurch__top--cfp">
                                        <div class="hsw-ppurch__cfp">
                                                <span class="hsw-ppurch__cfp-kicker"><?php esc_html_e( 'قیمت', 'hamishop-elementor-widgets' ); ?></span>
                                                <?php echo HSW_Helpers::cfp_inline_html( $product, false, 'hsw-ppurch__cfp-value' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
                                        </div>
                                </div>
                        <?php else : ?>
                                <div class="hsw-ppurch__top">
                                        <div>
                                                <div class="hsw-ppurch__oldrow">
                                                        <span class="hsw-ppurch__old" data-hsw-old<?php echo $hide_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $regular ); ?></span>
                                                        <span class="hsw-ppurch__save" data-hsw-save<?php echo $hide_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $save ); ?> <?php esc_html_e( 'تومان تخفیف ویژه', 'hamishop-elementor-widgets' ); ?></span>
                                                </div>
                                                <div class="hsw-ppurch__row">
                                                        <span class="hsw-ppurch__main" data-hsw-price><?php echo esc_html( $sale ); ?></span>
                                                        <span class="hsw-ppurch__unit"><?php esc_html_e( 'تومان', 'hamishop-elementor-widgets' ); ?></span>
                                                </div>
                                        </div>
                                        <div class="hsw-ppurch__qty">
                                                <span class="hsw-ppurch__qtylabel"><?php esc_html_e( 'تعداد:', 'hamishop-elementor-widgets' ); ?></span>
                                                <div class="hsw-ppurch__stepper">
                                                        <button type="button" class="hsw-ppurch__qbtn" data-hsw-qty="1" aria-label="<?php esc_attr_e( 'افزایش', 'hamishop-elementor-widgets' ); ?>"><span class="hsw-ic">add</span></button>
                                                        <span class="hsw-ppurch__qval" data-hsw-qval><?php echo esc_html( HSW_Helpers::fa_num( 1 ) ); ?></span>
                                                        <button type="button" class="hsw-ppurch__qbtn" data-hsw-qty="-1" aria-label="<?php esc_attr_e( 'کاهش', 'hamishop-elementor-widgets' ); ?>"><span class="hsw-ic">remove</span></button>
                                                </div>
                                        </div>
                                </div>
                        <?php endif; ?>
                        <div class="hsw-ppurch__ctas">
                                <?php if ( $cfp ) : ?>
                                        <?php echo HSW_Helpers::cfp_action_html( $product, 'hsw-ppurch__cart hsw-ppurch__cart--cfp', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
                                <?php else : ?>
                                        <button type="button" class="hsw-ppurch__cart" data-hsw-add>
                                                <span class="hsw-ic">shopping_cart</span>
                                                <span><?php echo esc_html( $cart_text ); ?></span>
                                        </button>
                                <?php endif; ?>
                        </div>
                        <div class="hsw-ppurch__assure">
                                <?php foreach ( $assure as $item ) : ?>
                                        <div class="hsw-ppurch__as hsw-ppurch__as--<?php echo esc_attr( $item['type'] ); ?>">
                                                <?php echo HSW_Helpers::icon( $item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                <span><?php echo esc_html( $item['text'] ); ?></span>
                                        </div>
                                <?php endforeach; ?>
                        </div>
                </div>
                <?php
        }
}
