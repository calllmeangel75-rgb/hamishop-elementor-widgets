<?php
/**
 * Widget: Order note + legal-invoice option — faithful conversion of the mock.
 * Desktop: card with «یادداشت سفارش و توضیحات ارسال (اختیاری)» textarea and
 * the corporate-invoice checkbox. Mobile: compact note section.
 * The note is pushed to the WC session (hsw/v1/checkout/note) when the
 * customer continues to payment.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Checkout_Note extends HSW_Checkout_Base {

        public function get_name() {
                return 'hsw-checkout-note';
        }

        public function get_title() {
                return __( 'یادداشت سفارش و فاکتور (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-post-content';
        }

        public function get_keywords() {
                return array( 'checkout', 'note', 'invoice', 'یادداشت', 'فاکتور', 'hamishop' );
        }

        protected function register_controls() {
                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control(
                        'label_d',
                        array(
                                'label'   => __( 'برچسب (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'یادداشت سفارش و توضیحات ارسال (اختیاری)', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'placeholder_d',
                        array(
                                'label'   => __( 'متن راهنما (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'توضیحات مربوط به نحوه تحویل، کد ورودی ساختمان، زنگ واحد و هماهنگی با راننده...', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'rows_d',
                        array(
                                'label'   => __( 'تعداد سطر (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::NUMBER,
                                'default' => 3,
                                'min'     => 2,
                                'max'     => 10,
                        )
                );

                $this->add_control(
                        'label_m',
                        array(
                                'label'   => __( 'برچسب (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'یادداشت و هماهنگی برای پیک (اختیاری)', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'placeholder_m',
                        array(
                                'label'   => __( 'متن راهنما (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'مثال: زنگ واحد خراب است یا لطفا قبل از تحویل تماس بگیرید...', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'rows_m',
                        array(
                                'label'   => __( 'تعداد سطر (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::NUMBER,
                                'default' => 2,
                                'min'     => 2,
                                'max'     => 10,
                        )
                );

                $this->add_control(
                        'show_invoice',
                        array(
                                'label'   => __( 'چک‌باکس فاکتور رسمی (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'invoice_label',
                        array(
                                'label'     => __( 'متن چک‌باکس فاکتور', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'درخواست صدور فاکتور رسمی حقوقی (ویژه شرکت‌ها و اشخاص حقوقی)', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_invoice' => 'yes' ),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_box( 'card', '{{WRAPPER}} .hsw-cknote__card' );
                $this->a_text( 'lbl', '{{WRAPPER}} .hsw-cknote__lbl', array( 'default' => 14 ) );
                $this->a_color( 'lbl_ic', __( 'آیکن برچسب', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cknote__lbl .hsw-ic' );
                $this->a_text( 'inv', '{{WRAPPER}} .hsw-cknote__inv-tx', array( 'default' => 14 ) );

                $this->end_controls_section();
        }

        protected function render() {
                $s = $this->effective_settings();

                $label_d = $this->opt( 'label_d', __( 'یادداشت سفارش و توضیحات ارسال (اختیاری)', 'hamishop-elementor-widgets' ) );
                $label_m = $this->opt( 'label_m', __( 'یادداشت و هماهنگی برای پیک (اختیاری)', 'hamishop-elementor-widgets' ) );
                $ph_d    = $this->opt( 'placeholder_d', '' );
                $ph_m    = $this->opt( 'placeholder_m', '' );
                $rows_d  = max( 2, (int) $this->opt( 'rows_d', '3' ) );
                $rows_m  = max( 2, (int) $this->opt( 'rows_m', '2' ) );
                $invoice = ( 'yes' === ( $s['show_invoice'] ?? 'yes' ) );
                ?>
                <section class="<?php echo esc_attr( $this->root_class( 'cknote' ) ); ?>">

                        <!-- =================== DESKTOP =================== -->
                        <article class="hsw-cknote__card hsw-d">
                                <label class="hsw-cknote__lbl" for="hsw-ck-note-<?php echo esc_attr( $this->get_id() ); ?>">
                                        <?php echo HSW_Helpers::icon( 'notes' ); ?>
                                        <?php echo esc_html( $label_d ); ?>
                                </label>
                                <textarea class="hsw-cknote__ta hsw-k-note-ta" id="hsw-ck-note-<?php echo esc_attr( $this->get_id() ); ?>" rows="<?php echo esc_attr( $rows_d ); ?>" placeholder="<?php echo esc_attr( $ph_d ); ?>"></textarea>

                                <?php if ( $invoice ) : ?>
                                        <div class="hsw-cknote__inv">
                                                <label class="hsw-cknote__inv-row">
                                                        <input type="checkbox" class="hsw-cknote__check hsw-k-note-inv" />
                                                        <span class="hsw-cknote__inv-tx"><?php echo esc_html( $this->opt( 'invoice_label', __( 'درخواست صدور فاکتور رسمی حقوقی (ویژه شرکت‌ها و اشخاص حقوقی)', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                </label>
                                        </div>
                                <?php endif; ?>
                        </article>

                        <!-- =================== MOBILE =================== -->
                        <section class="hsw-cknote__mcard hsw-m">
                                <label class="hsw-cknote__lbl" for="hsw-ck-notem-<?php echo esc_attr( $this->get_id() ); ?>">
                                        <?php echo HSW_Helpers::icon( 'edit_note' ); ?>
                                        <?php echo esc_html( $label_m ); ?>
                                </label>
                                <textarea class="hsw-cknote__ta hsw-cknote__ta--m hsw-k-note-ta" id="hsw-ck-notem-<?php echo esc_attr( $this->get_id() ); ?>" rows="<?php echo esc_attr( $rows_m ); ?>" placeholder="<?php echo esc_attr( $ph_m ); ?>"></textarea>
                        </section>

                </section>
                <?php
        }
}
