<?php
/**
 * Widget: Payment summary & continue CTA — faithful conversion of the mock.
 * Desktop: sticky aside — pre-invoice card (items strip, price rows with
 * data-hswk hooks, final payable, CTA, gate note) + trust badges card.
 * Mobile: «جزئیات پرداخت» card + fixed bottom checkout bar with the total.
 * All amounts live from WooCommerce; JS patches totals after the shipping
 * method changes (hsw/v1/checkout/shipping).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class HSW_Widget_Checkout_Summary extends HSW_Checkout_Base {

        public function get_name() {
                return 'hsw-checkout-summary';
        }

        public function get_title() {
                return __( 'خلاصه پرداخت اطلاعات ارسال (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-price-table';
        }

        public function get_keywords() {
                return array( 'checkout', 'summary', 'totals', 'payment', 'خلاصه پرداخت', 'hamishop' );
        }

        public static function icon_choices() {
                return array(
                        'verified_user'   => 'سپر تأیید',
                        'shield'          => 'سپر ساده',
                        'lock'            => 'قفل (پرداخت امن)',
                        'verified'        => 'تأیید',
                        'replay'          => 'بازگشت وجه',
                        'local_shipping'  => 'ارسال',
                        'warehouse'       => 'انبار',
                        'support_agent'   => 'پشتیبانی',
                        'receipt_long'    => 'فاکتور',
                        'savings'         => 'سود (قیمت)',
                        'percent'         => 'درصد',
                        'bolt'            => 'برق (اکسپرس)',
                        'payments'        => 'کارت‌های پرداخت',
                        'storefront'      => 'فروشگاه',
                        'badge'           => 'نشان',
                        'task_alt'        => 'تیک',
                        'check_circle'    => 'تأیید دایره',
                        'star'            => 'ستاره',
                        'help'            => 'راهنما',
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
                        'd_heading',
                        array( 'label' => __( 'دسکتاپ', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING )
                );

                $this->add_control(
                        'title_d',
                        array(
                                'label'   => __( 'عنوان پیش‌فاکتور', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'خلاصه پیش‌فاکتور', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'count_tpl',
                        array(
                                'label'   => __( 'قالب شمارنده اقلام', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( '{count} کالا', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'items_label',
                        array(
                                'label'   => __( 'برچسب اقلام بسته', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'اقلام ارسالی در این بسته:', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'ready_label',
                        array(
                                'label'       => __( 'متن کنار اقلام', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'بسته آماده ارسال', 'hamishop-elementor-widgets' ),
                                'description' => __( 'خالی = نمایش داده نمی‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'rows_heading',
                        array( 'label' => __( 'ردیف‌های فاکتور', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $this->add_control(
                        'subtotal_tpl',
                        array(
                                'label'   => __( 'ردیف جمع اقلام', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'جمع قیمت اقلام ({count}):', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'discount_label',
                        array(
                                'label'   => __( 'ردیف تخفیف', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'تخفیف کالاها:', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'shipping_label',
                        array(
                                'label'   => __( 'ردیف هزینه ارسال', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'هزینه ارسال:', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'shipping_tip',
                        array(
                                'label'   => __( 'راهنمای آیکن ارسال', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'ارسال بالای ۱ میلیون رایگان است', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'taxes_label',
                        array(
                                'label'   => __( 'ردیف مالیات', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'مالیات بر ارزش افزوده:', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'taxes_included',
                        array(
                                'label'       => __( 'متن وقتی مالیات جداگانه ندارد', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'محاسبه در قیمت', 'hamishop-elementor-widgets' ),
                                'description' => __( 'وقتی مالیات در فروشگاه غیرفعال است نمایش داده می‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'total_label',
                        array(
                                'label'   => __( 'مبلغ قابل پرداخت', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'مبلغ قابل پرداخت:', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'free_text',
                        array(
                                'label'   => __( 'متن ارسال رایگان', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'رایگان', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'cta_label',
                        array(
                                'label'   => __( 'متن دکمه ادامه', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'ادامه و ورود به مرحله پرداخت', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'cta_note',
                        array(
                                'label'   => __( 'یادداشت زیر دکمه', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'ثبت نهایی سفارش پس از تایید اطلاعات در درگاه بانکی', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'trust_heading',
                        array( 'label' => __( 'نشان‌های اعتماد', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $trust = new Repeater();
                $trust->add_control(
                        'icon',
                        array(
                                'label'   => __( 'آیکن', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => 'verified_user',
                                'options' => self::icon_choices(),
                        )
                );
                $trust->add_control(
                        'title',
                        array(
                                'label'   => __( 'عنوان', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => 'ضمانت اصالت',
                        )
                );
                $trust->add_control(
                        'text',
                        array(
                                'label'   => __( 'توضیح', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                        )
                );
                $this->add_control(
                        'trust',
                        array(
                                'label'       => __( 'نشان‌ها', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $trust->get_controls(),
                                'title_field' => '{{{ title }}}',
                                'default'     => array(
                                        array(
                                                'icon'  => 'verified_user',
                                                'title' => __( 'ضمانت اصالت و سلامت فیزیکی', 'hamishop-elementor-widgets' ),
                                                'text'  => __( 'تمامی بسته‌ها با بسته‌بندی ضدضربه ارسال می‌شوند', 'hamishop-elementor-widgets' ),
                                        ),
                                        array(
                                                'icon'  => 'shield',
                                                'title' => __( 'درگاه پرداخت امن شاپرک', 'hamishop-elementor-widgets' ),
                                                'text'  => __( 'پروتکل رمزنگاری ۲۵۶ بیتی بانکی', 'hamishop-elementor-widgets' ),
                                        ),
                                ),
                        )
                );

                $this->add_control(
                        'm_heading',
                        array( 'label' => __( 'موبایل', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $this->add_control(
                        'title_m',
                        array(
                                'label'   => __( 'عنوان جزئیات پرداخت', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'جزئیات پرداخت', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'subtotal_tpl_m',
                        array(
                                'label'   => __( 'ردیف جمع اقلام', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'قیمت کالاها ({count} قلم)', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'discount_label_m',
                        array(
                                'label'   => __( 'ردیف تخفیف', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'تخفیف کالاها', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'shipping_tpl_m',
                        array(
                                'label'   => __( 'ردیف هزینه ارسال', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'هزینه ارسال با پیک اختصاصی', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'total_label_m',
                        array(
                                'label'   => __( 'مبلغ نهایی (کارت)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'مبلغ نهایی سفارش', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'bar_pay_lbl',
                        array(
                                'label'   => __( 'برچسب نوار پرداخت', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'مبلغ قابل پرداخت', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'bar_cta',
                        array(
                                'label'   => __( 'متن دکمه نوار پرداخت', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'تأیید و پرداخت نهایی', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_box( 'card', '{{WRAPPER}} .hsw-cksum__card' );
                $this->a_text( 'hd', '{{WRAPPER}} .hsw-cksum__hd-t', array( 'default' => 16 ) );
                $this->a_text( 'row', '{{WRAPPER}} .hsw-cksum__row', array( 'default' => 14 ) );
                $this->a_color( 'discount_c', __( 'ردیف تخفیف', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cksum__row.is-discount' );
                $this->a_text( 'total', '{{WRAPPER}} .hsw-cksum__total-v', array( 'default' => 20 ) );
                $this->a_color( 'cta_bg', __( 'پس‌زمینه دکمه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cksum__cta', 'background-color' );
                $this->a_color( 'cta_c', __( 'متن دکمه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cksum__cta' );
                $this->a_text( 'trust_t', '{{WRAPPER}} .hsw-cksum__trust-t', array( 'default' => 14 ) );

                $this->end_controls_section();
        }

        /* =========================================================================
         *  TOTALS
         * ====================================================================== */

        protected function totals() {
                if ( $this->is_demo() ) {
                        return $this->demo_totals();
                }
                $subtotal = $this->base_subtotal();
                $total    = $this->cart_total();
                $ship     = $this->chosen_shipping_cost();
                $discount = $subtotal - ( $total - $ship );
                return array(
                        'count'    => $this->cart_count(),
                        'subtotal' => $subtotal,
                        'discount' => ( 0 < $discount ) ? $discount : 0,
                        'shipping' => array( ( $ship > 0 ) ? 'amount' : 'free', $ship ),
                        'taxes'    => $this->taxes_total(),
                        'total'    => $total,
                );
        }

        /**
         * Discount row HTML (Persian minus after digits).
         */
        protected function discount_html( $amount ) {
                return $this->money( -1 * (float) $amount, true, true );
        }

        protected function render() {
                $s = $this->effective_settings();

                $t        = $this->totals();
                $items    = $this->is_demo() ? $this->demo_items() : $this->cart_items();
                $count    = (int) $t['count'];
                $sticky   = ( 'yes' === ( $s['sticky'] ?? 'yes' ) );
                $free     = $this->opt( 'free_text', __( 'رایگان', 'hamishop-elementor-widgets' ) );
                $cta_url  = $this->checkout_url();
                $taxes_on = ( ! $this->is_demo() ) && $this->taxes_total() > 0;

                $shipping_html = ( 'free' === $t['shipping'][0] )
                        ? '<span class="is-free">' . esc_html( $free ) . '</span>'
                        : $this->money( (float) $t['shipping'][1], true );
                ?>
                <section class="<?php echo esc_attr( $this->root_class( 'cksum' ) ); ?>">

                        <!-- =================== DESKTOP =================== -->
                        <aside class="hsw-cksum__aside hsw-d<?php echo $sticky ? ' is-sticky' : ''; ?>">

                                <div class="hsw-cksum__card">
                                        <div class="hsw-cksum__hd">
                                                <h3 class="hsw-cksum__hd-t"><?php echo esc_html( $this->opt( 'title_d', __( 'خلاصه پیش‌فاکتور', 'hamishop-elementor-widgets' ) ) ); ?></h3>
                                                <span class="hsw-cksum__badge"><?php echo esc_html( $this->with_count( $this->opt( 'count_tpl', __( '{count} کالا', 'hamishop-elementor-widgets' ) ), $count ) ); ?></span>
                                        </div>

                                        <?php if ( ! empty( $items ) ) : ?>
                                                <div class="hsw-cksum__strip">
                                                        <?php if ( '' !== trim( $this->opt( 'items_label', '' ) ) ) : ?>
                                                                <span class="hsw-cksum__strip-lbl"><?php echo esc_html( $this->opt( 'items_label', '' ) ); ?></span>
                                                        <?php endif; ?>
                                                        <div class="hsw-cksum__thumbs">
                                                                <?php foreach ( $items as $it ) : ?>
                                                                        <span class="hsw-cksum__thumb">
                                                                                <?php echo isset( $it['img'] ) ? $it['img'] : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                                                <span class="hsw-cksum__qty"><?php echo esc_html( HSW_Helpers::fa_num( (int) ( isset( $it['qty'] ) ? $it['qty'] : 1 ) ) ); ?>×</span>
                                                                        </span>
                                                                <?php endforeach; ?>
                                                                <?php if ( '' !== trim( $this->opt( 'ready_label', '' ) ) ) : ?>
                                                                        <span class="hsw-cksum__ready"><?php echo esc_html( $this->opt( 'ready_label', '' ) ); ?></span>
                                                                <?php endif; ?>
                                                        </div>
                                                </div>
                                        <?php endif; ?>

                                        <div class="hsw-cksum__rows">
                                                <div class="hsw-cksum__row">
                                                        <span><?php echo esc_html( $this->with_count( $this->opt( 'subtotal_tpl', __( 'جمع قیمت اقلام ({count}):', 'hamishop-elementor-widgets' ) ), $count ) ); ?></span>
                                                        <span class="hsw-cksum__v" data-hswk="subtotal"><?php echo $this->money( (float) $t['subtotal'], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                                </div>

                                                <?php if ( 0 < (float) $t['discount'] ) : ?>
                                                        <div class="hsw-cksum__row is-discount">
                                                                <span><?php echo esc_html( $this->opt( 'discount_label', __( 'تخفیف کالاها:', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                                <span class="hsw-cksum__v" data-hswk="discount"><?php echo $this->discount_html( $t['discount'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                                        </div>
                                                <?php endif; ?>

                                                <div class="hsw-cksum__row">
                                                        <span class="hsw-cksum__tip">
                                                                <?php echo esc_html( $this->opt( 'shipping_label', __( 'هزینه ارسال:', 'hamishop-elementor-widgets' ) ) ); ?>
                                                                <?php if ( '' !== trim( $this->opt( 'shipping_tip', '' ) ) ) : ?>
                                                                        <i class="hsw-ic" data-tip="<?php echo esc_attr( $this->opt( 'shipping_tip', '' ) ); ?>" title="<?php echo esc_attr( $this->opt( 'shipping_tip', '' ) ); ?>" aria-hidden="true">info</i>
                                                                <?php endif; ?>
                                                        </span>
                                                        <span class="hsw-cksum__v" data-hswk="shipping"><?php echo $shipping_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                                </div>

                                                <div class="hsw-cksum__row">
                                                        <span><?php echo esc_html( $this->opt( 'taxes_label', __( 'مالیات بر ارزش افزوده:', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                        <span class="hsw-cksum__v" data-hswk="taxes"><?php echo $taxes_on ? $this->money( (float) $t['taxes'], true ) : '<span>' . esc_html( $this->opt( 'taxes_included', __( 'محاسبه در قیمت', 'hamishop-elementor-widgets' ) ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                                </div>

                                                <div class="hsw-cksum__final">
                                                        <span><?php echo esc_html( $this->opt( 'total_label', __( 'مبلغ قابل پرداخت:', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                        <span class="hsw-cksum__total-v" data-hswk="total"><?php echo $this->money( (float) $t['total'], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                                </div>
                                        </div>

                                        <a class="hsw-cksum__cta hsw-k-cta" href="<?php echo esc_url( $cta_url ); ?>">
                                                <span><?php echo esc_html( $this->opt( 'cta_label', __( 'ادامه و ورود به مرحله پرداخت', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                <i class="hsw-ic" aria-hidden="true">arrow_back</i>
                                        </a>

                                        <?php if ( '' !== trim( $this->opt( 'cta_note', '' ) ) ) : ?>
                                                <p class="hsw-cksum__note"><?php echo esc_html( $this->opt( 'cta_note', '' ) ); ?></p>
                                        <?php endif; ?>
                                </div>

                                <?php $trust = ( isset( $s['trust'] ) && is_array( $s['trust'] ) ) ? $s['trust'] : array(); ?>
                                <?php if ( ! empty( $trust ) ) : ?>
                                        <div class="hsw-cksum__trust">
                                                <?php foreach ( $trust as $i => $tr ) : ?>
                                                        <?php if ( '' === trim( (string) ( isset( $tr['title'] ) ? $tr['title'] : '' ) ) ) { continue; } ?>
                                                        <div class="hsw-cksum__trust-i<?php echo ( 0 < $i ) ? ' is-divided' : ''; ?>">
                                                                <i class="hsw-ic" aria-hidden="true"><?php echo esc_html( isset( $tr['icon'] ) ? $tr['icon'] : 'verified_user' ); ?></i>
                                                                <div>
                                                                        <span class="hsw-cksum__trust-t"><?php echo esc_html( $tr['title'] ); ?></span>
                                                                        <?php if ( '' !== trim( (string) ( isset( $tr['text'] ) ? $tr['text'] : '' ) ) ) : ?>
                                                                                <span class="hsw-cksum__trust-d"><?php echo esc_html( $tr['text'] ); ?></span>
                                                                        <?php endif; ?>
                                                                </div>
                                                        </div>
                                                <?php endforeach; ?>
                                        </div>
                                <?php endif; ?>
                        </aside>

                        <!-- =================== MOBILE =================== -->
                        <section class="hsw-cksum__mcard hsw-m">
                                <h2 class="hsw-cksum__hd-t hsw-cksum__hd-t--m"><?php echo esc_html( $this->opt( 'title_m', __( 'جزئیات پرداخت', 'hamishop-elementor-widgets' ) ) ); ?></h2>

                                <div class="hsw-cksum__rows">
                                        <div class="hsw-cksum__row">
                                                <span><?php echo esc_html( $this->with_count( $this->opt( 'subtotal_tpl_m', __( 'قیمت کالاها ({count} قلم)', 'hamishop-elementor-widgets' ) ), $count ) ); ?></span>
                                                <span class="hsw-cksum__v" data-hswk="subtotal"><?php echo $this->money( (float) $t['subtotal'], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                        </div>

                                        <?php if ( 0 < (float) $t['discount'] ) : ?>
                                                <div class="hsw-cksum__row is-discount">
                                                        <span class="hsw-cksum__tip">
                                                                <i class="hsw-ic" aria-hidden="true">sell</i>
                                                                <?php echo esc_html( $this->opt( 'discount_label_m', __( 'تخفیف کالاها', 'hamishop-elementor-widgets' ) ) ); ?>
                                                        </span>
                                                        <span class="hsw-cksum__v" data-hswk="discount"><?php echo $this->discount_html( $t['discount'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                                </div>
                                        <?php endif; ?>

                                        <div class="hsw-cksum__row">
                                                <span><?php echo esc_html( $this->opt( 'shipping_tpl_m', __( 'هزینه ارسال با پیک اختصاصی', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                <span class="hsw-cksum__v" data-hswk="shipping"><?php echo $shipping_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                        </div>

                                        <div class="hsw-cksum__final">
                                                <span><?php echo esc_html( $this->opt( 'total_label_m', __( 'مبلغ نهایی سفارش', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                <span class="hsw-cksum__total-v" data-hswk="total"><?php echo $this->money( (float) $t['total'], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                        </div>
                                </div>
                        </section>

                        <div class="hsw-cksum__mbpad hsw-m" aria-hidden="true"></div>

                        <aside class="hsw-cksum__mbar hsw-m">
                                <div class="hsw-cksum__mbar-in">
                                        <div class="hsw-cksum__mbar-price">
                                                <span class="hsw-cksum__mbar-lbl"><?php echo esc_html( $this->opt( 'bar_pay_lbl', __( 'مبلغ قابل پرداخت', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                <span class="hsw-cksum__mbar-v" data-hswk="total"><?php echo $this->money( (float) $t['total'], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                        </div>
                                        <a class="hsw-cksum__cta hsw-cksum__cta--m hsw-k-cta" href="<?php echo esc_url( $cta_url ); ?>">
                                                <span><?php echo esc_html( $this->opt( 'bar_cta', __( 'تأیید و پرداخت نهایی', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                <i class="hsw-ic" aria-hidden="true">chevron_left</i>
                                        </a>
                                </div>
                        </aside>

                </section>
                <?php
        }
}
