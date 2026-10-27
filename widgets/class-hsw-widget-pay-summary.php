<?php
/**
 * Widget: Invoice summary & final payment CTA — faithful conversion of the mock.
 * Desktop: sticky aside — invoice card (price rows, coupon rows, shipping
 * pill, final payable, terms, CTA, 3 trust badges) + help box.
 * Mobile: «خلاصه فاکتور سفارش» card + fixed bottom bar (total + CTA +
 * trust micro-badges) + body spacer.
 * Amounts live from WooCommerce; JS patches totals after coupon/shipping
 * changes (data-hswk hooks, same contract as the shipping step).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class HSW_Widget_Pay_Summary extends HSW_Pay_Base {

        public function get_name() {
                return 'hsw-pay-summary';
        }

        public function get_title() {
                return __( 'خلاصه فاکتور و پرداخت نهایی (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-price-table';
        }

        public function get_keywords() {
                return array( 'checkout', 'payment', 'summary', 'invoice', 'total', 'فاکتور', 'پرداخت', 'hamishop' );
        }

        protected function register_controls() {
                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control(
                        'sticky',
                        array(
                                'label'   => __( 'چسبیدن ستون خلاصه (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'd_heading',
                        array( 'label' => __( 'دسکتاپ', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING )
                );

                $this->add_control(
                        'title_d',
                        array(
                                'label'   => __( 'عنوان فاکتور', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'خلاصه فاکتور سفارش', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'count_tpl',
                        array(
                                'label'   => __( 'قالب شمارنده اقلام', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( '{count} قلم کالا', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'subtotal_tpl',
                        array(
                                'label'   => __( 'ردیف قیمت اقلام', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'قیمت اقلام ({count} کالا)', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'discount_label',
                        array(
                                'label'   => __( 'ردیف تخفیف کالاها', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'تخفیف شگفت‌انگیز کالاها', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'coupon_tpl',
                        array(
                                'label'   => __( 'قالب ردیف کوپن', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'تخفیف کوپن اختصاصی ({code})', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'shipping_label',
                        array(
                                'label'   => __( 'ردیف ارسال', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'هزینه بسته‌بندی و ارسال', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'free_note',
                        array(
                                'label'   => __( 'متن ارسال رایگان (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'رایگان (خرید بالای ۵۰۰ هزار)', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'total_label',
                        array(
                                'label'   => __( 'مبلغ نهایی (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'مبلغ نهایی پرداخت', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'taxes_note',
                        array(
                                'label'   => __( 'یادداشت مالیات', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'با احتساب مالیات بر ارزش افزوده', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'cta_label',
                        array(
                                'label'   => __( 'متن دکمه پرداخت (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'پرداخت و تکمیل سفارش', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'terms_link',
                        array(
                                'label'   => __( 'متن لینک قوانین', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'قوانین و مقررات خرید اینترنتی', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'terms_text',
                        array(
                                'label'   => __( 'بقیه متن قوانین (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXTAREA,
                                'default' => __( 'و حفظ حریم خصوصی فروشگاه را مطالعه کرده و می‌پذیرم.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'badges_heading',
                        array( 'label' => __( 'نشان‌های اعتماد (دسکتاپ)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $badge = new Repeater();
                $badge->add_control(
                        'icon',
                        array(
                                'label'   => __( 'آیکن', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => 'verified_user',
                                'options' => self::badge_icon_choices(),
                        )
                );
                $badge->add_control(
                        'title',
                        array(
                                'label'   => __( 'عنوان', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => 'نشان',
                        )
                );
                $this->add_control(
                        'badges',
                        array(
                                'label'       => __( 'نشان‌ها', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $badge->get_controls(),
                                'title_field' => '{{{ title }}}',
                                'default'     => array(
                                        array( 'icon' => 'verified_user', 'title' => __( 'ضمانت اصالت', 'hamishop-elementor-widgets' ) ),
                                        array( 'icon' => 'history', 'title' => __( '۷ روز بازگشت', 'hamishop-elementor-widgets' ) ),
                                        array( 'icon' => 'shield', 'title' => __( 'درگاه شاپرک', 'hamishop-elementor-widgets' ) ),
                                ),
                        )
                );

                $this->add_control(
                        'help_heading',
                        array( 'label' => __( 'جعبه راهنما (دسکتاپ)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $this->add_control(
                        'show_help',
                        array(
                                'label'   => __( 'نمایش جعبه راهنما', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'help_title',
                        array(
                                'label'     => __( 'عنوان راهنما', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'نیاز به راهنمایی در پرداخت دارید؟', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_help' => 'yes' ),
                        )
                );

                $this->add_control(
                        'help_text',
                        array(
                                'label'     => __( 'توضیح راهنما', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXTAREA,
                                'default'   => __( 'کارشناسان ما به‌صورت شبانه‌روزی آماده پاسخگویی هستند.', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_help' => 'yes' ),
                        )
                );

                $this->add_control(
                        'm_heading',
                        array( 'label' => __( 'موبایل', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $this->add_control(
                        'subtotal_tpl_m',
                        array(
                                'label'   => __( 'ردیف قیمت اقلام (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'مبلغ کالاها ({count} قلم)', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'discount_label_m',
                        array(
                                'label'   => __( 'ردیف سود تخفیف (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'سود شما از تخفیف‌ها', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'shipping_label_m',
                        array(
                                'label'   => __( 'ردیف ارسال (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'هزینه بسته‌بندی و ارسال', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'free_note_m',
                        array(
                                'label'   => __( 'متن ارسال رایگان (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'رایگان (خرید بالای ۵۰۰ تومان)', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'wallet_label_m',
                        array(
                                'label'   => __( 'ردیف کسر کیف پول (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'کسر از موجودی کیف پول', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'total_label_m',
                        array(
                                'label'   => __( 'مبلغ نهایی (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'مبلغ قابل پرداخت', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'terms_text_m',
                        array(
                                'label'   => __( 'متن قوانین (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXTAREA,
                                'default' => __( 'قوانین، مقررات خرید و حریم خصوصی فروشگاه را مطالعه کرده و می‌پذیرم.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'bar_pay_lbl',
                        array(
                                'label'   => __( 'برچسب نوار پرداخت (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'مبلغ قابل پرداخت:', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'bar_cta',
                        array(
                                'label'   => __( 'متن دکمه نوار پرداخت (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'پرداخت و ثبت نهایی', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'bar_trust_heading',
                        array( 'label' => __( 'نشان‌های زیر نوار (موبایل)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $trust = new Repeater();
                $trust->add_control(
                        'icon',
                        array(
                                'label'   => __( 'آیکن', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => 'verified_user',
                                'options' => self::badge_icon_choices(),
                        )
                );
                $trust->add_control(
                        'title',
                        array(
                                'label'   => __( 'متن', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => 'نشان',
                        )
                );
                $this->add_control(
                        'bar_trust',
                        array(
                                'label'       => __( 'نشان‌های نوار پرداخت', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $trust->get_controls(),
                                'title_field' => '{{{ title }}}',
                                'default'     => array(
                                        array( 'icon' => 'verified_user', 'title' => __( 'اتصال امن شاپرک SSL', 'hamishop-elementor-widgets' ) ),
                                        array( 'icon' => 'published_with_changes', 'title' => __( 'ضمانت بازگشت ۷ روزه', 'hamishop-elementor-widgets' ) ),
                                ),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_box( 'card', '{{WRAPPER}} .hsw-psum__card' );
                $this->style_color_var( 'sav_c', __( 'رنگ «سود شما از خرید»', 'hamishop-elementor-widgets' ), 'hsw-savings-color' );
                $this->style_typo_var( 'sav_fs', __( 'اندازه فونت «سود شما از خرید»', 'hamishop-elementor-widgets' ), 'hsw-savings-fs', array( 'default' => 13 ) );

                $this->a_text( 'hd', '{{WRAPPER}} .hsw-psum__hd-t', array( 'default' => 16 ) );
                $this->a_text( 'row', '{{WRAPPER}} .hsw-psum__row', array( 'default' => 14 ) );
                $this->a_color( 'discount_c', __( 'ردیف تخفیف', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-psum__row.is-discount' );
                $this->a_text( 'total', '{{WRAPPER}} .hsw-psum__total-v', array( 'default' => 20 ) );
                $this->a_color( 'cta_bg', __( 'پس‌زمینه دکمه پرداخت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-psum__cta', 'background-color' );
                $this->a_color( 'cta_c', __( 'متن دکمه پرداخت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-psum__cta' );
                $this->a_text( 'help_t', '{{WRAPPER}} .hsw-psum__help-t', array( 'default' => 14 ) );

                $this->end_controls_section();
        }

        /* =========================================================================
         *  TOTALS (desktop = full invoice, mobile = its own demo set)
         * ====================================================================== */

        protected function totals() {
                if ( $this->is_demo() ) {
                        return $this->demo_totals();
                }
                $subtotal = $this->base_subtotal();
                $total    = $this->cart_total();
                $ship     = $this->chosen_shipping_cost();
                $taxes    = $this->taxes_total();
                $coupons  = $this->applied_coupons();
                $c_total  = 0;
                foreach ( $coupons as $c ) {
                        $c_total += (float) $c['amount'];
                }
                $discount = $subtotal - ( $total - $ship - $taxes ) - $c_total;
                $wallet   = (float) apply_filters( 'hsw_wallet_balance', 0 );
                return array(
                        'count'    => $this->cart_count(),
                        'subtotal' => $subtotal,
                        'discount' => ( 0 < $discount ) ? $discount : 0,
                        'coupons'  => $coupons,
                        'shipping' => array( ( $ship > 0 ) ? 'amount' : 'free', $ship ),
                        'taxes'    => $taxes,
                        'wallet'   => ( $this->wallet_enabled() && $wallet > 0 ) ? $wallet : 0,
                        'total'    => $total,
                );
        }

        protected function totals_m() {
                if ( $this->is_demo() ) {
                        return $this->demo_totals_m();
                }
                return $this->totals(); // live totals are the same for both layouts.
        }

        /**
         * Discount row HTML (Persian minus after digits).
         */
        protected function discount_html( $amount ) {
                return $this->money( -1 * (float) $amount, true, true );
        }

        protected function render() {
                $s       = $this->effective_settings();
                $t       = $this->totals();
                $tm      = $this->totals_m();
                $sticky  = ( 'yes' === ( $s['sticky'] ?? 'yes' ) );
                $terms   = $this->terms_url();
                ?>
                <section class="<?php echo esc_attr( $this->root_class( 'psum' ) ); ?>">

                        <!-- =================== DESKTOP =================== -->
                        <aside class="hsw-psum__aside hsw-d<?php echo $sticky ? ' is-sticky' : ''; ?>">

                                <div class="hsw-psum__card">
                                        <div class="hsw-psum__hd">
                                                <h3 class="hsw-psum__hd-t"><?php echo esc_html( $this->opt( 'title_d', __( 'خلاصه فاکتور سفارش', 'hamishop-elementor-widgets' ) ) ); ?></h3>
                                                <span class="hsw-psum__badge"><?php echo esc_html( $this->with_count( $this->opt( 'count_tpl', '' ), $t['count'] ) ); ?></span>
                                        </div>

                                        <div class="hsw-psum__rows">
                                                <div class="hsw-psum__row">
                                                        <span><?php echo esc_html( $this->with_count( $this->opt( 'subtotal_tpl', '' ), $t['count'] ) ); ?></span>
                                                        <span class="hsw-psum__v" data-hswk="subtotal"><?php echo $this->money( (float) $t['subtotal'], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                                </div>

                                                <?php if ( 0 < (float) $t['discount'] ) : ?>
                                                        <div class="hsw-psum__row is-discount">
                                                                <span><?php echo esc_html( $this->opt( 'discount_label', '' ) ); ?></span>
                                                                <span class="hsw-psum__v" data-hswk="discount"><?php echo $this->discount_html( $t['discount'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                                        </div>
                                                <?php endif; ?>

                                                <?php if ( 0 < (float) $t['discount'] || ! empty( $t['coupons'] ) ) : ?>
                                                <div data-hswk="couponrows">
                                                        <?php foreach ( $t['coupons'] as $c ) : ?>
                                                                <div class="hsw-psum__row is-discount" data-hswk-coupon-row="<?php echo esc_attr( $c['code'] ); ?>">
                                                                        <span><?php echo esc_html( str_replace( '{code}', $c['code'], $this->opt( 'coupon_tpl', '' ) ) ); ?></span>
                                                                        <span class="hsw-psum__v"><?php echo $this->discount_html( $c['amount'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                                                </div>
                                                        <?php endforeach; ?>
                                                </div>
                                                <?php endif; ?>

                                                <div class="hsw-psum__row">
                                                        <span><?php echo esc_html( $this->opt( 'shipping_label', '' ) ); ?></span>
                                                        <span class="hsw-psum__v" data-hswk="shipping">
                                                                <?php if ( 'free' === $t['shipping'][0] ) : ?>
                                                                        <span class="hsw-psum__freenote"><?php echo esc_html( $this->opt( 'free_note', '' ) ); ?></span>
                                                                <?php else : ?>
                                                                        <?php echo $this->money( (float) $t['shipping'][1], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                                <?php endif; ?>
                                                        </span>
                                                </div>

                                                <?php echo HSW_Helpers::savings_html( 'hsw-savings--pay' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* inside. ?>

                                                <div class="hsw-psum__final">
                                                        <span>
                                                                <span class="hsw-psum__final-t"><?php echo esc_html( $this->opt( 'total_label', '' ) ); ?></span>
                                                                <span class="hsw-psum__final-n"><?php echo esc_html( $this->opt( 'taxes_note', '' ) ); ?></span>
                                                        </span>
                                                        <span class="hsw-psum__final-v">
                                                                <span class="hsw-psum__total-v" data-hswk="total"><?php echo $this->money( (float) $t['total'], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                                        </span>
                                                </div>
                                        </div>

                                        <div class="hsw-psum__terms">
                                                <label class="hsw-psum__terms-l">
                                                        <input type="checkbox" class="hsw-k-terms" />
                                                        <span class="hsw-psum__terms-tx">
                                                                <?php if ( '' !== $terms ) : ?>
                                                                        <a class="hsw-psum__terms-a" href="<?php echo esc_url( $terms ); ?>" target="_blank" rel="nofollow"><?php echo esc_html( $this->opt( 'terms_link', '' ) ); ?></a>
                                                                <?php else : ?>
                                                                        <span class="hsw-psum__terms-a"><?php echo esc_html( $this->opt( 'terms_link', '' ) ); ?></span>
                                                                <?php endif; ?>
                                                                <?php echo esc_html( $this->opt( 'terms_text', '' ) ); ?>
                                                        </span>
                                                </label>
                                        </div>

                                        <button class="hsw-psum__cta hsw-k-pay" type="button">
                                                <?php echo HSW_Helpers::icon( 'lock', true ); ?>
                                                <span><?php echo esc_html( $this->opt( 'cta_label', '' ) ); ?></span>
                                        </button>

                                        <p class="hsw-psum__msg" data-hswk-paymsg hidden></p>

                                        <?php $badges = ( isset( $s['badges'] ) && is_array( $s['badges'] ) ) ? $s['badges'] : array(); ?>
                                        <?php if ( ! empty( $badges ) ) : ?>
                                                <div class="hsw-psum__badges">
                                                        <?php foreach ( $badges as $b ) : ?>
                                                                <?php if ( '' === trim( (string) ( isset( $b['title'] ) ? $b['title'] : '' ) ) ) { continue; } ?>
                                                                <div class="hsw-psum__badge-i">
                                                                        <?php echo HSW_Helpers::icon( isset( $b['icon'] ) ? $b['icon'] : 'verified_user' ); ?>
                                                                        <span><?php echo esc_html( $b['title'] ); ?></span>
                                                                </div>
                                                        <?php endforeach; ?>
                                                </div>
                                        <?php endif; ?>
                                </div>

                                <?php if ( 'yes' === ( $s['show_help'] ?? 'yes' ) ) : ?>
                                        <div class="hsw-psum__help">
                                                <?php echo HSW_Helpers::icon( 'contact_support' ); ?>
                                                <div class="hsw-psum__help-tx">
                                                        <span class="hsw-psum__help-t"><?php echo esc_html( $this->opt( 'help_title', '' ) ); ?></span>
                                                        <span class="hsw-psum__help-d"><?php echo esc_html( $this->opt( 'help_text', '' ) ); ?></span>
                                                </div>
                                        </div>
                                <?php endif; ?>
                        </aside>

                        <!-- =================== MOBILE =================== -->
                        <section class="hsw-psum__mcard hsw-m">
                                <div class="hsw-psum__mhd">
                                        <?php echo HSW_Helpers::icon( 'receipt_long' ); ?>
                                        <h2 class="hsw-psum__hd-t is-m"><?php echo esc_html( $this->opt( 'title_d', '' ) ); ?></h2>
                                </div>

                                <div class="hsw-psum__mrows">
                                        <div class="hsw-psum__row">
                                                <span><?php echo esc_html( $this->with_count( $this->opt( 'subtotal_tpl_m', '' ), $tm['count'] ) ); ?></span>
                                                <span class="hsw-psum__v" data-hswk="subtotal"><?php echo $this->money( (float) $tm['subtotal'], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                        </div>

                                        <?php if ( 0 < (float) $tm['discount'] ) : ?>
                                                <div class="hsw-psum__row is-discount">
                                                        <span class="hsw-psum__lbl-ic">
                                                                <?php echo HSW_Helpers::icon( 'savings' ); ?>
                                                                <?php echo esc_html( $this->opt( 'discount_label_m', '' ) ); ?>
                                                        </span>
                                                        <span class="hsw-psum__v" data-hswk="discount"><?php echo $this->discount_html( $tm['discount'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                                </div>
                                        <?php endif; ?>

                                        <div class="hsw-psum__row">
                                                <span><?php echo esc_html( $this->opt( 'shipping_label_m', '' ) ); ?></span>
                                                <span class="hsw-psum__v" data-hswk="shipping">
                                                        <?php if ( 'free' === $tm['shipping'][0] ) : ?>
                                                                <span class="hsw-psum__freenote is-m"><?php echo esc_html( $this->opt( 'free_note_m', '' ) ); ?></span>
                                                        <?php else : ?>
                                                                <?php echo $this->money( (float) $tm['shipping'][1], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                        <?php endif; ?>
                                                </span>
                                        </div>

                                        <?php if ( 0 < (float) $tm['wallet'] ) : ?>
                                                <div class="hsw-psum__row is-wallet" data-hswk-wallet-row>
                                                        <span><?php echo esc_html( $this->opt( 'wallet_label_m', '' ) ); ?></span>
                                                        <span class="hsw-psum__v"><?php echo $this->discount_html( $tm['wallet'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                                </div>
                                        <?php endif; ?>
                                </div>

                                <div class="hsw-psum__final">
                                        <span>
                                                <span class="hsw-psum__final-t"><?php echo esc_html( $this->opt( 'total_label_m', '' ) ); ?></span>
                                                <span class="hsw-psum__final-n"><?php echo esc_html( $this->opt( 'taxes_note', '' ) ); ?></span>
                                        </span>
                                        <span class="hsw-psum__final-v">
                                                <span class="hsw-psum__total-v" data-hswk="total"><?php echo $this->money( (float) $tm['total'], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                        </span>
                                </div>

                                <label class="hsw-psum__terms-l is-m">
                                        <input type="checkbox" class="hsw-k-terms" />
                                        <span class="hsw-psum__terms-tx">
                                                <?php if ( '' !== $terms ) : ?>
                                                        <a class="hsw-psum__terms-a" href="<?php echo esc_url( $terms ); ?>" target="_blank" rel="nofollow"><?php echo esc_html( $this->opt( 'terms_text_m', '' ) ); ?></a>
                                                <?php else : ?>
                                                        <?php echo esc_html( $this->opt( 'terms_text_m', '' ) ); ?>
                                                <?php endif; ?>
                                        </span>
                                </label>
                        </section>

                        <div class="hsw-psum__mbpad hsw-m" aria-hidden="true"></div>

                        <aside class="hsw-psum__mbar hsw-m">
                                <div class="hsw-psum__mbar-in">
                                        <div class="hsw-psum__mbar-price">
                                                <span class="hsw-psum__mbar-lbl"><?php echo esc_html( $this->opt( 'bar_pay_lbl', '' ) ); ?></span>
                                                <span class="hsw-psum__mbar-v" data-hswk="total"><?php echo $this->money( (float) $tm['total'], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                        </div>
                                        <button class="hsw-psum__cta hsw-psum__cta--m hsw-k-pay" type="button">
                                                <?php echo HSW_Helpers::icon( 'lock' ); ?>
                                                <span><?php echo esc_html( $this->opt( 'bar_cta', '' ) ); ?></span>
                                        </button>
                                </div>
                                <p class="hsw-psum__msg is-m" data-hswk-paymsg hidden></p>
                                <?php $trust = ( isset( $s['bar_trust'] ) && is_array( $s['bar_trust'] ) ) ? $s['bar_trust'] : array(); ?>
                                <?php if ( ! empty( $trust ) ) : ?>
                                        <div class="hsw-psum__mtrust">
                                                <?php foreach ( $trust as $ti => $tr ) : ?>
                                                        <?php if ( '' === trim( (string) ( isset( $tr['title'] ) ? $tr['title'] : '' ) ) ) { continue; } ?>
                                                        <?php if ( 0 < $ti ) : ?>
                                                                <span class="hsw-psum__mtrust-dot" aria-hidden="true"></span>
                                                        <?php endif; ?>
                                                        <span class="hsw-psum__mtrust-i">
                                                                <?php echo HSW_Helpers::icon( isset( $tr['icon'] ) ? $tr['icon'] : 'verified_user' ); ?>
                                                                <?php echo esc_html( $tr['title'] ); ?>
                                                        </span>
                                                <?php endforeach; ?>
                                        </div>
                                <?php endif; ?>
                        </aside>

                </section>
                <?php
        }
}
