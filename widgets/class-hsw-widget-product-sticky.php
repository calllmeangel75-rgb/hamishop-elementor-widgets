<?php
/**
 * Widget: نوار خرید چسبان — faithful conversion of the supplied design.
 * Mobile-only fixed bottom purchase bar: dynamic price fields, quantity
 * stepper, add-to-cart button and the success toast (data-hsw-toast).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Product_Sticky extends HSW_Product_Base {

        /**
         * Widget slug.
         */
        public function get_name() {
                return 'hsw-product-sticky';
        }

        /**
         * Widget label in the editor panel.
         */
        public function get_title() {
                return __( 'نوار خرید چسبان (HamiShop)', 'hamishop-elementor-widgets' );
        }

        /**
         * Widget icon.
         */
        public function get_icon() {
                return 'eicon-cart-medium';
        }

        /**
         * Search keywords.
         */
        public function get_keywords() {
                return array( 'sticky', 'نوار چسبان', 'سبد خرید', 'افزودن به سبد', 'hamishop', 'hamisys' );
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

                $this->ctrl_visibility( 'mobile' );

                $this->add_control(
                        'cart_text',
                        array(
                                'label'   => __( 'متن دکمه سبد', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'افزودن به سبد', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'sticky_fixed',
                        array(
                                'label'       => __( 'چسبان به پایین صفحه (Fixed)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'default'     => 'yes',
                                'description' => __( 'با خاموش کردن، نوار در جای خود (static) نمایش داده می‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_control(
                        'bar_bg',
                        array(
                                'label'     => __( 'پس‌زمینه نوار', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-psticky' => 'background-color: {{VALUE}};' ),
                        )
                );

                $this->add_control(
                        'price_heading',
                        array( 'label' => __( 'قیمت', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->ctrl_text( 'st_price', '{{WRAPPER}} .hsw-psticky__pval' );
                $this->add_control(
                        'price_c',
                        array(
                                'label'     => __( 'رنگ قیمت اصلی', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-psticky__pval' => '--hsw-price-c: {{VALUE}};' ),
                        )
                );
                $this->ctrl_text( 'st_old', '{{WRAPPER}} .hsw-psticky__old' );
                $this->add_control(
                        'off_bg',
                        array(
                                'label'     => __( 'پس‌زمینه قرص تخفیف', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-psticky__off' => 'background-color: {{VALUE}};' ),
                        )
                );
                $this->add_control(
                        'off_c',
                        array(
                                'label'     => __( 'رنگ متن قرص تخفیف', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-psticky__off' => 'color: {{VALUE}};' ),
                        )
                );

                $this->add_control(
                        'cart_heading',
                        array( 'label' => __( 'دکمه سبد', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->add_control(
                        'cart_bg',
                        array(
                                'label'     => __( 'پس‌زمینه دکمه', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-psticky__cart' => '--hsw-cart-bg: {{VALUE}};' ),
                        )
                );
                $this->add_control(
                        'cart_c',
                        array(
                                'label'     => __( 'رنگ متن دکمه', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-psticky__cart' => '--hsw-cart-c: {{VALUE}};' ),
                        )
                );
                $this->ctrl_text( 'st_cart', '{{WRAPPER}} .hsw-psticky__cart' );

                $this->end_controls_section();
        }

        /**
         * Render the sticky purchase bar (aside root + toast).
         */
        protected function render() {
                list( $product, $demo ) = $this->product_or_demo();
                $s = $this->get_settings_for_display();

                /* Never render mock data outside the Elementor editor. */
                if ( ! $product && ! $this->is_demo() ) {
                        return;
                }

                if ( $demo ) {
                        $d        = $this->demo();
                        $sale     = $d['sale'];
                        $regular  = $d['regular'];
                        $discount = (int) $d['discount'];
                        $in_stock = ! empty( $d['in_stock'] );
                        $pid      = 0;
                } else {
                        $prices   = $this->display_prices( $product );
                        $sale     = HSW_Helpers::price_num( $prices['sale'] );
                        $regular  = HSW_Helpers::price_num( $prices['regular'] );
                        $discount = (int) $prices['discount'];
                        $in_stock = $product->is_in_stock();
                        $pid      = $product->get_id();
                }

                $cart_text = isset( $s['cart_text'] ) && '' !== trim( (string) $s['cart_text'] ) ? $s['cart_text'] : __( 'افزودن به سبد', 'hamishop-elementor-widgets' );
                $cart_url  = $demo ? '#' : ( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '#' );

                $classes = $this->root_class( 'psticky', 'hsw-m' );
                if ( ! $in_stock ) {
                        $classes .= ' is-oos';
                }

                $hidden   = ( 0 === $discount ) ? ' style="display:none"' : '';
                $is_fixed = ( 'yes' === ( $s['sticky_fixed'] ?? 'yes' ) );
                $static   = $is_fixed ? '' : ' style="position:static"';
                ?>
                <aside class="<?php echo esc_attr( $classes ); ?>" data-hsw-product="<?php echo esc_attr( $pid ); ?>"<?php echo $static; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded static attribute. ?>>
                        <div class="hsw-psticky__inner">
                                <div class="hsw-psticky__main">
                                        <div class="hsw-psticky__price">
                                                <div class="hsw-psticky__oldrow">
                                                        <span class="hsw-psticky__old" data-hsw-old<?php echo $hidden; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded static attribute. ?>><?php echo esc_html( $regular ); ?></span>
                                                        <span class="hsw-psticky__off" data-hsw-off<?php echo $hidden; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded static attribute. ?>>٪<?php echo esc_html( HSW_Helpers::fa_num( $discount ) ); ?>-</span>
                                                </div>
                                                <div class="hsw-psticky__prow">
                                                        <span class="hsw-psticky__pval" data-hsw-price><?php echo esc_html( $sale ); ?></span>
                                                        <span class="hsw-psticky__unit"><?php esc_html_e( 'تومان', 'hamishop-elementor-widgets' ); ?></span>
                                                </div>
                                        </div>
                                        <div class="hsw-psticky__side">
                                                <div class="hsw-psticky__stepper">
                                                        <button type="button" class="hsw-psticky__qbtn hsw-psticky__qbtn--plus" data-hsw-qty="1" aria-label="<?php esc_attr_e( 'افزایش تعداد', 'hamishop-elementor-widgets' ); ?>">
                                                                <?php echo HSW_Helpers::icon( 'add' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes internally. ?>
                                                        </button>
                                                        <span class="hsw-psticky__qval" data-hsw-qval><?php echo esc_html( HSW_Helpers::fa_num( 1 ) ); ?></span>
                                                        <button type="button" class="hsw-psticky__qbtn hsw-psticky__qbtn--minus" data-hsw-qty="-1" aria-label="<?php esc_attr_e( 'کاهش تعداد', 'hamishop-elementor-widgets' ); ?>">
                                                                <?php echo HSW_Helpers::icon( 'remove' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes internally. ?>
                                                        </button>
                                                </div>
                                                <button type="button" class="hsw-psticky__cart" data-hsw-add>
                                                        <?php echo HSW_Helpers::icon( 'shopping_cart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes internally. ?>
                                                        <span><?php echo esc_html( $cart_text ); ?></span>
                                                </button>
                                        </div>
                                </div>
                                <div class="hsw-psticky__toast" data-hsw-toast>
                                        <span class="hsw-psticky__toastmsg">
                                                <?php echo HSW_Helpers::icon( 'check_circle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes internally. ?>
                                                <span><?php esc_html_e( 'محصول با موفقیت به سبد خرید اضافه شد', 'hamishop-elementor-widgets' ); ?></span>
                                        </span>
                                        <a class="hsw-psticky__toastlink" href="<?php echo esc_url( $cart_url ); ?>"><?php esc_html_e( 'مشاهده سبد', 'hamishop-elementor-widgets' ); ?></a>
                                </div>
                        </div>
                </aside>
                <?php
        }
}
