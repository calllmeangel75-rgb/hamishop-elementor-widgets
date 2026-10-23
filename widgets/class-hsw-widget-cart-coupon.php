<?php
/**
 * Widget: Cart coupon — faithful conversion of the supplied design.
 * Desktop: voucher card (icon + texts + real WooCommerce coupon form) and the
 * bottom row (back-to-shop link + tax note). Mobile: compact coupon section.
 * The form posts coupon_code/apply_coupon to the cart URL — handled natively
 * by WooCommerce (WC_Form_Handler).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Cart_Coupon extends HSW_Cart_Base {

        public function get_name() {
                return 'hsw-cart-coupon';
        }

        public function get_title() {
                return __( 'کد تخفیف سبد خرید (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-tag';
        }

        public function get_keywords() {
                return array( 'cart', 'coupon', 'discount', 'کد تخفیف', 'کوپن', 'hamishop' );
        }

        protected function register_controls() {
                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control(
                        'title',
                        array(
                                'label'   => __( 'عنوان (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'کد تخفیف دارید؟', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'desc',
                        array(
                                'label'   => __( 'توضیح (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'کد هدیه یا کوپن ویژه خود را وارد کنید.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'placeholder',
                        array(
                                'label'   => __( 'متن راهنمای فیلد (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'کد تخفیف...', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'btn_label',
                        array(
                                'label'   => __( 'متن دکمه', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'اعمال کد', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'm_title',
                        array(
                                'label'   => __( 'عنوان (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'کد تخفیف یا کارت هدیه', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'm_placeholder',
                        array(
                                'label'   => __( 'متن راهنمای فیلد (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'کد تخفیف را وارد کنید', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'foot_heading',
                        array( 'label' => __( 'ردیف پایین (دسکتاپ)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $this->add_control(
                        'show_foot',
                        array(
                                'label'   => __( 'نمایش لینک ادامه خرید و یادداشت', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'back_label',
                        array(
                                'label'       => __( 'متن لینک ادامه خرید', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'ادامه خرید در کاتالوگ فروشگاه', 'hamishop-elementor-widgets' ),
                                'condition'   => array( 'show_foot' => 'yes' ),
                        )
                );

                $this->add_control(
                        'tax_note',
                        array(
                                'label'       => __( 'یادداشت مالیات', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'تمامی قیمت‌ها شامل مالیات بر ارزش افزوده قانونی است.', 'hamishop-elementor-widgets' ),
                                'condition'   => array( 'show_foot' => 'yes' ),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_box( 'cp', '{{WRAPPER}} .hsw-ccoupon__card, {{WRAPPER}} .hsw-ccoupon__mcard' );
                $this->a_color( 'cp_ic_bg', __( 'پس‌زمینه آیکن (دسکتاپ)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ccoupon__ic', 'background-color' );
                $this->a_color( 'cp_ic', __( 'آیکن (دسکتاپ)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ccoupon__ic .hsw-ic' );
                $this->a_text( 'cp_t', '{{WRAPPER}} .hsw-ccoupon__title, {{WRAPPER}} .hsw-ccoupon__mtitle', array( 'default' => 14 ) );
                $this->a_text( 'cp_d', '{{WRAPPER}} .hsw-ccoupon__desc', array( 'default' => 12 ) );

                $this->a_color( 'cp_in_bg', __( 'پس‌زمینه فیلد', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ccoupon__in', 'background-color' );
                $this->a_color( 'cp_in_bd', __( 'حاشیه فیلد', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ccoupon__in', 'border-color' );
                $this->a_color( 'cp_btn_bg', __( 'پس‌زمینه دکمه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ccoupon__btn', 'background-color' );
                $this->a_color( 'cp_btn_tx', __( 'متن دکمه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ccoupon__btn' );
                $this->add_responsive_control(
                        'cp_btn_br',
                        array(
                                'label'      => __( 'گردی دکمه', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-ccoupon__btn' => 'border-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->a_color( 'cp_back', __( 'لینک ادامه خرید', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ccoupon__back' );
                $this->a_color( 'cp_note', __( 'یادداشت مالیات', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ccoupon__tax' );

                $this->end_controls_section();
        }

        protected function render() {
                /* Coupons disabled → nothing to render (dynamic behavior). */
                if ( $this->has_cart() && function_exists( 'wc_coupons_enabled' ) && ! wc_coupons_enabled() ) {
                        return;
                }

                $s         = $this->effective_settings();
                $show_foot = ( 'yes' === ( $s['show_foot'] ?? 'yes' ) );
                ?>

                <?php
                /*
                 * Coupon feedback. The page root usually prints the queue first;
                 * this covers a page built from individual widgets, where the
                 * coupon block would otherwise stay silent on a wrong code.
                 */
                echo \HSW_Helpers::wc_notices(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WooCommerce.
                ?>
                <section class="<?php echo esc_attr( $this->root_class( 'ccoupon' ) ); ?>">

                        <!-- =================== DESKTOP =================== -->
                        <div class="hsw-d">
                                <div class="hsw-ccoupon__card">
                                        <div class="hsw-ccoupon__intro">
                                                <span class="hsw-ccoupon__ic"><i class="hsw-ic" aria-hidden="true">confirmation_number</i></span>
                                                <div>
                                                        <h4 class="hsw-ccoupon__title"><?php echo esc_html( $this->opt( 'title', __( 'کد تخفیف دارید؟', 'hamishop-elementor-widgets' ) ) ); ?></h4>
                                                        <p class="hsw-ccoupon__desc"><?php echo esc_html( $this->opt( 'desc', '' ) ); ?></p>
                                                </div>
                                        </div>
                                        <form method="post" action="<?php echo esc_url( $this->cart_url() ); ?>" class="hsw-ccoupon__form">
                                                <?php echo $this->cart_nonce(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hidden nonce inputs. ?>
                                                <input type="text" name="coupon_code" class="hsw-ccoupon__in" value="" placeholder="<?php echo esc_attr( $this->opt( 'placeholder', __( 'کد تخفیف...', 'hamishop-elementor-widgets' ) ) ); ?>" aria-label="<?php esc_attr_e( 'کد تخفیف', 'hamishop-elementor-widgets' ); ?>">
                                                <button type="submit" name="apply_coupon" value="1" class="hsw-ccoupon__btn"><?php echo esc_html( $this->opt( 'btn_label', __( 'اعمال کد', 'hamishop-elementor-widgets' ) ) ); ?></button>
                                        </form>
                                </div>

                                <?php if ( $show_foot ) : ?>
                                        <div class="hsw-ccoupon__foot">
                                                <a class="hsw-ccoupon__back" href="<?php echo esc_url( $this->shop_url() ); ?>">
                                                        <i class="hsw-ic" aria-hidden="true">arrow_forward</i>
                                                        <span><?php echo esc_html( $this->opt( 'back_label', '' ) ); ?></span>
                                                </a>
                                                <?php if ( '' !== trim( (string) ( $s['tax_note'] ?? '' ) ) ) : ?>
                                                        <span class="hsw-ccoupon__tax"><?php echo esc_html( $s['tax_note'] ); ?></span>
                                                <?php endif; ?>
                                        </div>
                                <?php endif; ?>
                        </div>

                        <!-- =================== MOBILE =================== -->
                        <div class="hsw-ccoupon__mcard hsw-m">
                                <span class="hsw-ccoupon__mtitle">
                                        <i class="hsw-ic" aria-hidden="true">local_activity</i>
                                        <?php echo esc_html( $this->opt( 'm_title', __( 'کد تخفیف یا کارت هدیه', 'hamishop-elementor-widgets' ) ) ); ?>
                                </span>
                                <form method="post" action="<?php echo esc_url( $this->cart_url() ); ?>" class="hsw-ccoupon__mform">
                                        <?php echo $this->cart_nonce(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hidden nonce inputs. ?>
                                        <input type="text" name="coupon_code" class="hsw-ccoupon__in" value="" placeholder="<?php echo esc_attr( $this->opt( 'm_placeholder', __( 'کد تخفیف را وارد کنید', 'hamishop-elementor-widgets' ) ) ); ?>" aria-label="<?php esc_attr_e( 'کد تخفیف', 'hamishop-elementor-widgets' ); ?>">
                                        <button type="submit" name="apply_coupon" value="1" class="hsw-ccoupon__btn"><?php echo esc_html( $this->opt( 'btn_label', __( 'اعمال کد', 'hamishop-elementor-widgets' ) ) ); ?></button>
                                </form>
                        </div>

                </section>
                <?php
        }
}
