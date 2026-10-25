<?php
/**
 * Widget: Cart order summary — faithful conversion of the supplied design.
 * Desktop: sticky aside (invoice no., price rows, savings, shipping, VAT note,
 * final payable, CTA, trust badges) + help card.
 * Mobile: summary card (receipt style, dashed divider, guarantee note) and the
 * fixed bottom checkout bar. All values live from WooCommerce.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class HSW_Widget_Cart_Summary extends HSW_Cart_Base {

        public function get_name() {
                return 'hsw-cart-summary';
        }

        public function get_title() {
                return __( 'خلاصه فاکتور سبد خرید (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-price-table';
        }

        public function get_keywords() {
                return array( 'cart', 'totals', 'checkout', 'خلاصه فاکتور', 'پرداخت', 'hamishop' );
        }

        /**
         * Icons available in the bundled subset (for the trust repeater).
         */
        public static function icon_choices() {
                return array(
                        'lock'            => 'قفل (پرداخت امن)',
                        'verified'        => 'تأیید (اصالت)',
                        'verified_user'   => 'سپر تأیید',
                        'replay'          => 'بازگشت وجه',
                        'local_shipping'  => 'ارسال',
                        'warehouse'       => 'انبار',
                        'support_agent'   => 'پشتیبانی',
                        'receipt_long'    => 'فاکتور',
                        'savings'         => 'سود (قیمت)',
                        'percent'         => 'درصد',
                        'confirmation_number' => 'کوپن',
                        'local_activity'  => 'کارت هدیه',
                        'local_fire_department' => 'فایر (شگفت‌انگیز)',
                        'bolt'            => 'برق (اکسپرس)',
                        'trending_up'     => 'روند صعودی',
                        'auto_awesome'    => 'درخشش',
                        'favorite'        => 'قلب',
                        'star'            => 'ستاره',
                        'shopping_cart'   => 'سبد خرید',
                        'shopping_bag'    => 'کیف خرید',
                        'timer'           => 'تایمر',
                        'task_alt'        => 'تیک',
                        'help'            => 'راهنما',
                        'check_circle'    => 'تأیید دایره',
                );
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
                        'show_inv',
                        array(
                                'label'   => __( 'شماره پیش‌فاکتور', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'inv_text',
                        array(
                                'label'       => __( 'متن شماره پیش‌فاکتور', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'شماره پیش‌فاکتور: #۴۸۳۹۲', 'hamishop-elementor-widgets' ),
                                'condition'   => array( 'show_inv' => 'yes' ),
                                'description' => __( 'ووکامرس شماره پیش‌فاکتور ندارد؛ متن دلخواه وارد کنید.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'rows_heading',
                        array( 'label' => __( 'ردیف‌های فاکتور', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $this->add_control(
                        'base_label_d',
                        array(
                                'label'   => __( 'ردیف قیمت پایه (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'قیمت پایه اقلام ({count} کالا)', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'base_label_m',
                        array(
                                'label'   => __( 'ردیف قیمت کالاها (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'قیمت کالاها ({count} عدد)', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'show_savings',
                        array(
                                'label'   => __( 'ردیف سود شما از این خرید', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'savings_label',
                        array(
                                'label'     => __( 'برچسب سود', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'سود شما از این خرید', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_savings' => 'yes' ),
                        )
                );

                $this->add_control(
                        'ship_label_d',
                        array(
                                'label'   => __( 'برچسب ارسال (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'هزینه ارسال پستی', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'ship_label_m',
                        array(
                                'label'   => __( 'برچسب ارسال (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'هزینه ارسال اکسپرس', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'free_label',
                        array(
                                'label'   => __( 'برچسب وقتی رایگان است', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'رایگان', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'show_taxes',
                        array(
                                'label'       => __( 'ردیف مالیات', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'default'     => 'yes',
                                'description' => __( 'وقتی مالیات ثبت شده باشد مبلغ واقعی نمایش داده می‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'taxes_label',
                        array(
                                'label'     => __( 'برچسب مالیات', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'مالیات بر ارزش افزوده (۱۰٪ محاسبه‌شده)', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_taxes' => 'yes' ),
                        )
                );

                $this->add_control(
                        'taxes_note',
                        array(
                                'label'       => __( 'مقدار وقتی مالیاتی در کار نیست', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'لحاظ شده در مبالغ', 'hamishop-elementor-widgets' ),
                                'condition'   => array( 'show_taxes' => 'yes' ),
                        )
                );

                $this->add_control(
                        'final_label',
                        array(
                                'label'   => __( 'برچسب مبلغ نهایی', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'مبلغ نهایی پرداخت', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'final_note',
                        array(
                                'label'       => __( 'زیرنویس مبلغ نهایی (خالی = مخفی)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'همراه با اعمال تخفیف شگفت‌انگیز', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'cta_label',
                        array(
                                'label'   => __( 'متن دکمه پرداخت (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'تکمیل سفارش و ثبت آدرس', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'next_url',
                        array(
                                'label'       => __( 'لینک مرحله بعد (خالی = صفحه تسویه ووکامرس)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::URL,
                                'placeholder' => 'https://…',
                        )
                );

                $this->add_control(
                        'trust_heading',
                        array( 'label' => __( 'نشان‌های اعتماد (دسکتاپ)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $repeater = new Repeater();
                $repeater->add_control(
                        'icon',
                        array(
                                'label'   => __( 'آیکن', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => 'lock',
                                'options' => self::icon_choices(),
                        )
                );
                $repeater->add_control(
                        'title',
                        array(
                                'label'   => __( 'عنوان', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => 'عنوان',
                        )
                );
                $repeater->add_control(
                        'sub',
                        array(
                                'label'   => __( 'زیرنویس', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => '',
                        )
                );
                $this->add_control(
                        'trust',
                        array(
                                'label'       => __( 'نشان‌ها', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $repeater->get_controls(),
                                'title_field' => '{{{ title }}}',
                                'default'     => array(
                                        array( 'icon' => 'lock', 'title' => __( 'پرداخت امن', 'hamishop-elementor-widgets' ), 'sub' => __( 'درگاه شاپرک', 'hamishop-elementor-widgets' ) ),
                                        array( 'icon' => 'replay', 'title' => __( '۷ روز ضمانت', 'hamishop-elementor-widgets' ), 'sub' => __( 'بازگشت وجه', 'hamishop-elementor-widgets' ) ),
                                        array( 'icon' => 'local_shipping', 'title' => __( 'ارسال مطمئن', 'hamishop-elementor-widgets' ), 'sub' => __( 'بیمه مرسوله', 'hamishop-elementor-widgets' ) ),
                                ),
                        )
                );

                $this->add_control(
                        'help_heading',
                        array( 'label' => __( 'کارت پشتیبانی (دسکتاپ)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $this->add_control(
                        'show_help',
                        array(
                                'label'   => __( 'نمایش کارت پشتیبانی', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'help_title',
                        array(
                                'label'     => __( 'عنوان', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'نیاز به راهنمایی در خرید دارید؟', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_help' => 'yes' ),
                        )
                );

                $this->add_control(
                        'help_text',
                        array(
                                'label'     => __( 'متن', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXTAREA,
                                'rows'      => 2,
                                'default'   => __( 'کارشناسان ما در ساعات اداری آماده پاسخگویی هستند:', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_help' => 'yes' ),
                        )
                );

                $this->add_control(
                        'help_phone',
                        array(
                                'label'       => __( 'شماره تماس (خالی = مخفی)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => '021 - 88990011',
                                'condition'   => array( 'show_help' => 'yes' ),
                        )
                );

                $this->add_control(
                        'mobile_heading',
                        array( 'label' => __( 'موبایل', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $this->add_control(
                        'show_guarantee',
                        array(
                                'label'   => __( 'کادر ضمانت (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'guarantee_text',
                        array(
                                'label'     => __( 'متن ضمانت', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXTAREA,
                                'rows'      => 2,
                                'default'   => __( 'ضمانت اصالت و امکان بازگشت ۷ روزه بدون قید و شرط', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_guarantee' => 'yes' ),
                        )
                );

                $this->add_control(
                        'show_mbar',
                        array(
                                'label'   => __( 'نوار پرداخت پایین صفحه (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'mbar_label',
                        array(
                                'label'     => __( 'برچسب نوار پایین', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'مبلغ کل پرداختی:', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_mbar' => 'yes' ),
                        )
                );

                $this->add_control(
                        'mbar_cta',
                        array(
                                'label'     => __( 'متن دکمه نوار پایین', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'ثبت سفارش و ادامه', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_mbar' => 'yes' ),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_box( 'sm', '{{WRAPPER}} .hsw-csummary__card, {{WRAPPER}} .hsw-csummary__mcard' );
                $this->a_text( 'sm_h', '{{WRAPPER}} .hsw-csummary__title, {{WRAPPER}} .hsw-csummary__mtitle', array( 'default' => 16 ) );
                $this->a_color( 'sm_inv', __( 'متن شماره پیش‌فاکتور', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-csummary__inv' );
                $this->a_text( 'sm_row', '{{WRAPPER}} .hsw-csummary__row, {{WRAPPER}} .hsw-csummary__mrow', array( 'default' => 14 ) );
                $this->a_color( 'sm_label', __( 'برچسب ردیف‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-csummary__lbl' );
                $this->a_color( 'sm_save', __( 'ردیف سود شما', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-csummary__row.is-save, {{WRAPPER}} .hsw-csummary__mrow.is-save' );
                $this->a_color( 'sm_free', __( 'برچسب رایگان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-csummary__free' );
                $this->a_color( 'sm_mfree_bg', __( 'پس‌زمینه برچسب رایگان (موبایل)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-csummary__mbadge', 'background-color' );
                $this->a_color( 'sm_mfree', __( 'متن برچسب رایگان (موبایل)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-csummary__mbadge' );
                $this->a_color( 'sm_final', __( 'مبلغ نهایی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-csummary__final-v, {{WRAPPER}} .hsw-csummary__mtotal-v' );

                $this->a_color( 'cta_bg', __( 'پس‌زمینهٔ دکمهٔ «تکمیل سفارش» (دسکتاپ)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-csummary__cta', '--hsw-cs-cta-bg' );
                $this->a_color( 'cta_tx', __( 'متن دکمهٔ «تکمیل سفارش»', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-csummary__cta', '--hsw-cs-cta-tx' );
                $this->add_responsive_control(
                        'cta_br',
                        array(
                                'label'      => __( 'گردی دکمه پرداخت', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-csummary__cta' => 'border-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->a_color( 'mbar_bg', __( 'پس‌زمینه نوار پایین (موبایل)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-csummary__mbar', 'background-color' );
                $this->a_color( 'mcta_bg', __( 'پس‌زمینه دکمه نوار پایین', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-csummary__mcta', 'background-color' );
                $this->a_color( 'mcta_tx', __( 'متن دکمه نوار پایین', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-csummary__mcta' );

                $this->end_controls_section();
        }

        /* =========================================================================
         *  VALUES
         * ====================================================================== */

        protected function totals() {
                if ( $this->is_demo() ) {
                        return $this->demo_totals();
                }
                return array(
                        'count'    => $this->cart_count(),
                        'base'     => $this->base_subtotal(),
                        'savings'  => $this->savings(),
                        'shipping' => $this->shipping_row(),
                        'taxes'    => $this->taxes_total(),
                        'total'    => $this->cart_total(),
                );
        }

        protected function render() {
                $s     = $this->effective_settings();
                $demo  = $this->is_demo();
                $empty = ( ! $demo && ( ! $this->has_cart() || 0 === $this->cart_count() ) );
                if ( $empty ) {
                        return;
                }

                $t       = $this->totals();
                $sticky  = ( 'yes' === ( $s['sticky'] ?? 'yes' ) );
                $mbar    = ( 'yes' === ( $s['show_mbar'] ?? 'yes' ) );
                $cur     = get_woocommerce_currency_symbol();
                $extra   = trim( ( $sticky ? 'is-sticky' : '' ) . ' ' . ( $mbar ? 'is-mbar' : '' ) );

                $base_lbl_d = $this->with_count( $this->opt( 'base_label_d', __( 'قیمت پایه اقلام ({count} کالا)', 'hamishop-elementor-widgets' ) ), $t['count'] );
                $base_lbl_m = $this->with_count( $this->opt( 'base_label_m', __( 'قیمت کالاها ({count} عدد)', 'hamishop-elementor-widgets' ) ), $t['count'] );
                $free_lbl   = $this->opt( 'free_label', __( 'رایگان', 'hamishop-elementor-widgets' ) );
                $next_url   = ! empty( $s['next_url']['url'] ) ? (string) $s['next_url']['url'] : $this->checkout_url();
                ?>
                <section class="<?php echo esc_attr( $this->root_class( 'csummary', $extra ) ); ?>">

                        <!-- =================== DESKTOP =================== -->
                        <aside class="hsw-csummary__aside hsw-d">
                                <div class="hsw-csummary__card">
                                        <div class="hsw-csummary__head">
                                                <h2 class="hsw-csummary__title"><?php esc_html_e( 'خلاصه فاکتور سفارش', 'hamishop-elementor-widgets' ); ?></h2>
                                                <?php if ( 'yes' === ( $s['show_inv'] ?? 'yes' ) && '' !== trim( (string) ( $s['inv_text'] ?? '' ) ) ) : ?>
                                                        <span class="hsw-csummary__inv"><?php echo esc_html( $s['inv_text'] ); ?></span>
                                                <?php endif; ?>
                                        </div>

                                        <div class="hsw-csummary__rows">
                                                <div class="hsw-csummary__row">
                                                        <span class="hsw-csummary__lbl"><?php echo esc_html( $base_lbl_d ); ?></span>
                                                        <span class="hsw-csummary__val"><?php echo $this->money( $t['base'], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- internal money HTML. ?></span>
                                                </div>

                                                <?php if ( 'yes' === ( $s['show_savings'] ?? 'yes' ) && ( $demo || 0 < $t['savings'] ) ) : ?>
                                                        <div class="hsw-csummary__row is-save">
                                                                <span class="hsw-csummary__lbl is-save-lbl">
                                                                        <span><?php echo esc_html( $this->opt( 'savings_label', __( 'سود شما از این خرید', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                                        <?php echo HSW_Helpers::icon( 'percent' ); ?>
                                                                </span>
                                                                <span class="hsw-csummary__val is-save-v"><?php echo esc_html( $this->money( -1 * $t['savings'], false, true ) ); ?> <span class="hsw-c-cur"><?php echo esc_html( $cur ); ?></span></span>
                                                        </div>
                                                <?php endif; ?>

                                                <?php if ( 'amount' === $t['shipping'][0] ) : ?>
                                                        <div class="hsw-csummary__row">
                                                                <span class="hsw-csummary__lbl"><?php echo esc_html( $this->opt( 'ship_label_d', __( 'هزینه ارسال پستی', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                                <span class="hsw-csummary__val"><?php echo esc_html( $this->money( $t['shipping'][1] ) ); ?> <span class="hsw-c-cur"><?php echo esc_html( $cur ); ?></span></span>
                                                        </div>
                                                <?php else : ?>
                                                        <div class="hsw-csummary__row">
                                                                <span class="hsw-csummary__lbl"><?php echo esc_html( $this->opt( 'ship_label_d', __( 'هزینه ارسال پستی', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                                <span class="hsw-csummary__free"><?php echo esc_html( $free_lbl ); ?></span>
                                                        </div>
                                                <?php endif; ?>

                                                <?php if ( 'yes' === ( $s['show_taxes'] ?? 'yes' ) ) : ?>
                                                        <div class="hsw-csummary__row is-tax">
                                                                <span class="hsw-csummary__lbl"><?php echo esc_html( $this->opt( 'taxes_label', __( 'مالیات بر ارزش افزوده (۱۰٪ محاسبه‌شده)', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                                <span class="hsw-csummary__tax-v">
                                                                        <?php if ( 0 < $t['taxes'] ) : ?>
                                                                                <?php echo esc_html( $this->money( $t['taxes'] ) ); ?> <span class="hsw-c-cur"><?php echo esc_html( $cur ); ?></span>
                                                                        <?php else : ?>
                                                                                <?php echo esc_html( $this->opt( 'taxes_note', __( 'لحاظ شده در مبالغ', 'hamishop-elementor-widgets' ) ) ); ?>
                                                                        <?php endif; ?>
                                                                </span>
                                                        </div>
                                                <?php endif; ?>

                                                <div class="hsw-csummary__divider" aria-hidden="true"></div>

                                                <div class="hsw-csummary__final">
                                                        <div class="hsw-csummary__final-l">
                                                                <span class="hsw-csummary__final-t"><?php echo esc_html( $this->opt( 'final_label', __( 'مبلغ نهایی پرداخت', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                                <?php if ( '' !== trim( (string) ( $s['final_note'] ?? '' ) ) ) : ?>
                                                                        <span class="hsw-csummary__final-n"><?php echo esc_html( $s['final_note'] ); ?></span>
                                                                <?php endif; ?>
                                                        </div>
                                                        <div class="hsw-csummary__final-r">
                                                                <span class="hsw-csummary__final-v"><?php echo esc_html( $this->money( $t['total'] ) ); ?></span>
                                                                <span class="hsw-c-cur"><?php echo esc_html( $cur ); ?></span>
                                                        </div>
                                                </div>
                                        </div>

                                        <a class="hsw-csummary__cta" href="<?php echo esc_url( $next_url ); ?>">
                                                <span><?php echo esc_html( $this->opt( 'cta_label', __( 'تکمیل سفارش و ثبت آدرس', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                <?php echo HSW_Helpers::icon( 'arrow_back' ); ?>
                                        </a>

                                        <?php $trust = ( isset( $s['trust'] ) && is_array( $s['trust'] ) ) ? $s['trust'] : array(); ?>
                                        <?php if ( ! empty( $trust ) ) : ?>
                                                <div class="hsw-csummary__trust">
                                                        <?php foreach ( $trust as $row ) : ?>
                                                                <div class="hsw-csummary__trust-i">
                                                                        <?php echo HSW_Helpers::icon( isset( $row['icon'] ) ? $row['icon'] : 'lock' ); ?>
                                                                        <span class="hsw-csummary__trust-t"><?php echo esc_html( isset( $row['title'] ) ? $row['title'] : '' ); ?></span>
                                                                        <?php if ( ! empty( $row['sub'] ) ) : ?>
                                                                                <span class="hsw-csummary__trust-s"><?php echo esc_html( $row['sub'] ); ?></span>
                                                                        <?php endif; ?>
                                                                </div>
                                                        <?php endforeach; ?>
                                                </div>
                                        <?php endif; ?>
                                </div>

                                <?php if ( 'yes' === ( $s['show_help'] ?? 'yes' ) ) : ?>
                                        <div class="hsw-csummary__help">
                                                <span class="hsw-csummary__help-ic"><?php echo HSW_Helpers::icon( 'support_agent' ); ?></span>
                                                <div class="hsw-csummary__help-b">
                                                        <p class="hsw-csummary__help-t"><?php echo esc_html( $this->opt( 'help_title', '' ) ); ?></p>
                                                        <p class="hsw-csummary__help-x">
                                                                <?php echo esc_html( $this->opt( 'help_text', '' ) ); ?>
                                                                <?php if ( '' !== trim( (string) ( $s['help_phone'] ?? '' ) ) ) : ?>
                                                                        <span class="hsw-csummary__help-phone" dir="ltr"><?php echo esc_html( $s['help_phone'] ); ?></span>
                                                                <?php endif; ?>
                                                        </p>
                                                </div>
                                        </div>
                                <?php endif; ?>
                        </aside>

                        <!-- =================== MOBILE =================== -->
                        <section class="hsw-csummary__mcard hsw-m" aria-label="<?php esc_attr_e( 'خلاصه فاکتور', 'hamishop-elementor-widgets' ); ?>">
                                <h3 class="hsw-csummary__mtitle">
                                        <?php echo HSW_Helpers::icon( 'receipt_long' ); ?>
                                        <?php esc_html_e( 'خلاصه فاکتور سفارش', 'hamishop-elementor-widgets' ); ?>
                                </h3>

                                <div class="hsw-csummary__mrow">
                                        <span class="hsw-csummary__lbl"><?php echo esc_html( $base_lbl_m ); ?></span>
                                        <span class="hsw-csummary__mval"><?php echo esc_html( $this->money( $t['base'] ) ); ?> <span class="hsw-c-cur"><?php echo esc_html( $cur ); ?></span></span>
                                </div>

                                <?php if ( 'yes' === ( $s['show_savings'] ?? 'yes' ) && ( $demo || 0 < $t['savings'] ) ) : ?>
                                        <div class="hsw-csummary__mrow is-save">
                                                <span class="hsw-csummary__lbl is-save-lbl">
                                                        <?php echo HSW_Helpers::icon( 'savings' ); ?>
                                                        <span><?php echo esc_html( $this->opt( 'savings_label', __( 'سود شما از این خرید', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                </span>
                                                <span class="hsw-csummary__mval is-save-v"><?php echo esc_html( $this->money( -1 * $t['savings'], false, true ) ); ?> <span class="hsw-c-cur"><?php echo esc_html( $cur ); ?></span></span>
                                        </div>
                                <?php endif; ?>

                                <div class="hsw-csummary__mrow">
                                        <span class="hsw-csummary__lbl">
                                                <?php echo HSW_Helpers::icon( 'local_shipping' ); ?>
                                                <span><?php echo esc_html( $this->opt( 'ship_label_m', __( 'هزینه ارسال اکسپرس', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                        </span>
                                        <?php if ( 'amount' === $t['shipping'][0] ) : ?>
                                                <span class="hsw-csummary__mval"><?php echo esc_html( $this->money( $t['shipping'][1] ) ); ?> <span class="hsw-c-cur"><?php echo esc_html( $cur ); ?></span></span>
                                        <?php else : ?>
                                                <span class="hsw-csummary__mbadge"><?php echo esc_html( $free_lbl ); ?></span>
                                        <?php endif; ?>
                                </div>

                                <div class="hsw-csummary__mtotal">
                                        <span class="hsw-csummary__mtotal-t"><?php echo esc_html( $this->opt( 'final_label', __( 'مبلغ قابل پرداخت', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                        <span class="hsw-csummary__mtotal-r">
                                                <span class="hsw-csummary__mtotal-v"><?php echo esc_html( $this->money( $t['total'] ) ); ?></span>
                                                <span class="hsw-c-cur"><?php echo esc_html( $cur ); ?></span>
                                        </span>
                                </div>

                                <?php if ( 'yes' === ( $s['show_guarantee'] ?? 'yes' ) && '' !== trim( (string) ( $s['guarantee_text'] ?? '' ) ) ) : ?>
                                        <div class="hsw-csummary__guarantee">
                                                <?php echo HSW_Helpers::icon( 'verified' ); ?>
                                                <span><?php echo esc_html( $s['guarantee_text'] ); ?></span>
                                        </div>
                                <?php endif; ?>
                        </section>

                        <?php if ( 'yes' === ( $s['show_mbar'] ?? 'yes' ) ) : ?>
                                <aside class="hsw-csummary__mbar hsw-m" aria-label="<?php esc_attr_e( 'پرداخت سفارش', 'hamishop-elementor-widgets' ); ?>">
                                        <div class="hsw-csummary__mbar-in">
                                                <div class="hsw-csummary__mbar-p">
                                                        <span class="hsw-csummary__mbar-l"><?php echo esc_html( $this->opt( 'mbar_label', __( 'مبلغ کل پرداختی:', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                        <span class="hsw-csummary__mbar-v"><?php echo esc_html( $this->money( $t['total'] ) ); ?> <span class="hsw-c-cur"><?php echo esc_html( $cur ); ?></span></span>
                                                </div>
                                                <a class="hsw-csummary__mcta" href="<?php echo esc_url( $next_url ); ?>">
                                                        <span><?php echo esc_html( $this->opt( 'mbar_cta', __( 'ثبت سفارش و ادامه', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                        <?php echo HSW_Helpers::icon( 'arrow_back' ); ?>
                                                </a>
                                        </div>
                                </aside>
                        <?php endif; ?>

                </section>
                <?php
        }
}
