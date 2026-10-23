<?php
/**
 * Widget: Coupon & gift voucher — faithful conversion of the mock.
 * Desktop: «کد تخفیف یا کارت هدیه» card — applied-coupon row (green state
 * + deduct badge + remove button) + code form.
 * Mobile: compact coupon card with input + apply button.
 * Live: WooCommerce coupons through the plugin REST endpoint (apply /
 * remove) — totals patch themselves like the shipping flow.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Pay_Coupon extends HSW_Pay_Base {

        public function get_name() {
                return 'hsw-pay-coupon';
        }

        public function get_title() {
                return __( 'کد تخفیف و کارت هدیه پرداخت (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-cart-medium';
        }

        public function get_keywords() {
                return array( 'checkout', 'payment', 'coupon', 'gift', 'تخفیف', 'هدیه', 'hamishop' );
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
                                'label'   => __( 'عنوان بخش', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'کد تخفیف یا کارت هدیه', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'applied_tpl',
                        array(
                                'label'       => __( 'قالب کد اعمال‌شده', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'کد تخفیف {code} اعمال شد', 'hamishop-elementor-widgets' ),
                                'description' => __( '{code} و {amount} جابه‌جا می‌شوند.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'deduct_tpl',
                        array(
                                'label'   => __( 'قالب مبلغ کسرشده', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( '{amount} تومان کسر شد', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'remove_label',
                        array(
                                'label'   => __( 'متن حذف تخفیف', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'حذف تخفیف', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'placeholder',
                        array(
                                'label'   => __( 'راهنمای ورودی (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'کد تخفیف یا شناسه کارت هدیه دیگر را وارد کنید...', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'button_label',
                        array(
                                'label'   => __( 'متن دکمه (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'ثبت و بررسی', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'm_placeholder',
                        array(
                                'label'   => __( 'راهنمای ورودی (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'مثال: SPRING1403', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'm_button_label',
                        array(
                                'label'   => __( 'متن دکمه (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'اعمال کد', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'error_text',
                        array(
                                'label'   => __( 'پیام خطای کد نامعتبر', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'کد تخفیف وارد شده معتبر نیست.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_box( 'card', '{{WRAPPER}} .hsw-pcpn__card' );
                $this->a_text( 'hd', '{{WRAPPER}} .hsw-pcpn__hd-t', array( 'default' => 16 ) );
                $this->a_color( 'btn_bg', __( 'پس‌زمینه دکمه (دسکتاپ)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pcpn__btn', 'background-color' );
                $this->a_color( 'btn_c', __( 'متن دکمه (دسکتاپ)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pcpn__btn' );
                $this->a_color( 'm_btn_bg', __( 'پس‌زمینه دکمه (موبایل)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pcpn__mbtn', 'background-color' );

                $this->end_controls_section();
        }

        /**
         * Applied coupon rows markup (shared by PHP render + JS re-render).
         */
        protected function applied_rows() {
                $tpl      = $this->opt( 'applied_tpl', __( 'کد تخفیف {code} اعمال شد', 'hamishop-elementor-widgets' ) );
                $ded_tpl  = $this->opt( 'deduct_tpl', __( '{amount} تومان کسر شد', 'hamishop-elementor-widgets' ) );
                $rm_label = $this->opt( 'remove_label', __( 'حذف تخفیف', 'hamishop-elementor-widgets' ) );
                $rows     = $this->is_demo() ? array( $this->demo_coupon() ) : $this->applied_coupons();

                ob_start();
                foreach ( $rows as $c ) {
                        $amount_html = $this->money( (float) $c['amount'], true );
                        $line        = str_replace( '{code}', '<b class="hsw-pcpn__code">' . esc_html( $c['code'] ) . '</b>', $tpl );
                        $deduct      = str_replace( '{amount}', '<span class="hsw-pcpn__amt">' . $amount_html . '</span>', $ded_tpl );
                        ?>
                        <div class="hsw-pcpn__applied" data-hswk-coupon="<?php echo esc_attr( $c['code'] ); ?>">
                                <span class="hsw-pcpn__applied-l">
                                        <i class="hsw-ic" aria-hidden="true">check_circle</i>
                                        <span class="hsw-pcpn__applied-tx"><?php echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                        <?php if ( 0 < (float) $c['amount'] ) : ?>
                                                <span class="hsw-pcpn__deduct"><?php echo $deduct; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                        <?php endif; ?>
                                </span>
                                <button class="hsw-pcpn__remove" type="button" data-hswk-coupon-remove="<?php echo esc_attr( $c['code'] ); ?>">
                                        <i class="hsw-ic" aria-hidden="true">close</i>
                                        <span><?php echo esc_html( $rm_label ); ?></span>
                                </button>
                        </div>
                        <?php
                }
                return trim( ob_get_clean() );
        }

        protected function render() {
                $applied = $this->applied_rows();
                ?>
                <?php echo \HSW_Helpers::wc_notices(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WooCommerce. ?>
                <section class="<?php echo esc_attr( $this->root_class( 'pcpn' ) ); ?>">

                        <!-- =================== DESKTOP =================== -->
                        <div class="hsw-pcpn__card hsw-d">
                                <div class="hsw-pcpn__hd">
                                        <i class="hsw-ic" aria-hidden="true">loyalty</i>
                                        <h2 class="hsw-pcpn__hd-t"><?php echo esc_html( $this->opt( 'title', __( 'کد تخفیف یا کارت هدیه', 'hamishop-elementor-widgets' ) ) ); ?></h2>
                                </div>

                                <div class="hsw-pcpn__body" data-hswk-coupons>
                                        <?php echo $applied; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                                        <div class="hsw-pcpn__form">
                                                <span class="hsw-pcpn__field">
                                                        <i class="hsw-ic" aria-hidden="true">confirmation_number</i>
                                                        <input class="hsw-pcpn__input" type="text" name="hsw_coupon_code" autocomplete="off" placeholder="<?php echo esc_attr( $this->opt( 'placeholder', '' ) ); ?>" />
                                                </span>
                                                <button class="hsw-pcpn__btn" type="button" data-hswk-coupon-apply><?php echo esc_html( $this->opt( 'button_label', '' ) ); ?></button>
                                        </div>

                                        <p class="hsw-pcpn__msg" role="status" aria-live="polite"></p>
                                </div>
                        </div>

                        <!-- =================== MOBILE =================== -->
                        <section class="hsw-pcpn__mcard hsw-m">
                                <div class="hsw-pcpn__mhd">
                                        <label class="hsw-pcpn__mlabel" for="hsw-coupon-input-m">
                                                <i class="hsw-ic" aria-hidden="true">local_activity</i>
                                                <span><?php echo esc_html( $this->opt( 'title', '' ) ); ?></span>
                                        </label>
                                </div>
                                <div class="hsw-pcpn__mform" data-hswk-coupons>
                                        <input class="hsw-pcpn__minput" id="hsw-coupon-input-m" type="text" name="hsw_coupon_code_m" autocomplete="off" placeholder="<?php echo esc_attr( $this->opt( 'm_placeholder', '' ) ); ?>" />
                                        <button class="hsw-pcpn__mbtn" type="button" data-hswk-coupon-apply><?php echo esc_html( $this->opt( 'm_button_label', '' ) ); ?></button>
                                </div>
                                <div class="hsw-pcpn__mlist" data-hswk-coupons-m><?php echo $applied; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                                <p class="hsw-pcpn__msg" role="status" aria-live="polite"></p>
                        </section>

                </section>
                <?php
        }
}
