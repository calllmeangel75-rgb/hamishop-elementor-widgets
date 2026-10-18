<?php
/**
 * Widget: Shipping recall — faithful conversion of the mock.
 * Desktop: «مشخصات تحویل سفارش» card — recipient + address on one column,
 * chosen shipping method + delivery slot in a tinted sub-box.
 * Mobile: «اطلاعات مرسوله» compact card (address + delivery line + free tag).
 * Live data: WooCommerce customer + chosen shipping rate.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Pay_Recall extends HSW_Pay_Base {

        public function get_name() {
                return 'hsw-pay-recall';
        }

        public function get_title() {
                return __( 'مشخصات تحویل سفارش (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-icon-box';
        }

        public function get_keywords() {
                return array( 'checkout', 'payment', 'shipping', 'recall', 'تحویل', 'ارسال', 'hamishop' );
        }

        protected function register_controls() {
                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control(
                        'd_heading',
                        array( 'label' => __( 'دسکتاپ', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING )
                );

                $this->add_control(
                        'title_d',
                        array(
                                'label'   => __( 'عنوان کارت', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'مشخصات تحویل سفارش', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'edit_label',
                        array(
                                'label'   => __( 'متن دکمه ویرایش', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'تغییر اطلاعات یا آدرس', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'edit_url',
                        array(
                                'label'       => __( 'لینک ویرایش', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::URL,
                                'options'     => array( 'url', 'is_external', 'nofollow' ),
                                'description' => __( 'خالی = صفحه آدرس‌های حساب کاربری.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'name_label',
                        array(
                                'label'   => __( 'برچسب تحویل‌گیرنده', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'تحویل‌گیرنده:', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'addr_label',
                        array(
                                'label'   => __( 'برچسب آدرس', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'آدرس:', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'method_label',
                        array(
                                'label'   => __( 'برچسب شیوه ارسال', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'شیوه ارسال:', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'slot_label',
                        array(
                                'label'   => __( 'برچسب بازه زمانی', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'بازه زمانی منتخب:', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'slot_text',
                        array(
                                'label'       => __( 'متن بازه زمانی', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'فردا (پنجشنبه) - ساعت ۱۴ الی ۱۸', 'hamishop-elementor-widgets' ),
                                'description' => __( 'اگر افزونه زمان‌بندی تحویل مقدار جلسه را در نشست ذخیره کند، همان نمایش داده می‌شود (فیلتر hsw_pay_slot_text).', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'm_heading',
                        array( 'label' => __( 'موبایل', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $this->add_control(
                        'title_m',
                        array(
                                'label'   => __( 'عنوان کارت موبایل', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'اطلاعات مرسوله', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'm_edit_label',
                        array(
                                'label'   => __( 'متن ویرایش موبایل', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'ویرایش', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'm_delivery',
                        array(
                                'label'   => __( 'خط تحویل موبایل (حالت پیش‌نمایش)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'تحویل: فردا (۱۰ الی ۱۴) - ارسال اکسپرس', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'free_text',
                        array(
                                'label'   => __( 'برچسب ارسال رایگان', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'رایگان', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_box( 'card', '{{WRAPPER}} .hsw-prec__card' );
                $this->a_text( 'hd', '{{WRAPPER}} .hsw-prec__hd-t', array( 'default' => 16 ) );
                $this->a_color( 'edit_c', __( 'دکمه ویرایش', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prec__edit' );
                $this->a_color( 'lbl_c', __( 'رنگ برچسب‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prec__lbl' );
                $this->a_box( 'ship_box', '{{WRAPPER}} .hsw-prec__box' );
                $this->a_color( 'method_c', __( 'مقدار شیوه ارسال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prec__method' );

                $this->end_controls_section();
        }

        /**
         * Chosen shipping method title (+ رایگان suffix when free).
         */
        protected function method_title() {
                if ( $this->is_demo() ) {
                        return $this->demo_pay_shipping()['method'];
                }
                $rates = $this->shipping_rates();
                foreach ( $rates as $rate ) {
                        if ( ! empty( $rate['chosen'] ) ) {
                                $title = (string) $rate['title'];
                                if ( (float) $rate['cost'] <= 0 && false === strpos( $title, 'رایگان' ) ) {
                                        $title .= ' (رایگان)';
                                }
                                return $title;
                        }
                }
                return '';
        }

        /**
         * Delivery slot text (filter lets scheduling plugins inject the session slot).
         */
        protected function slot_text() {
                $default = $this->opt( 'slot_text', __( 'فردا (پنجشنبه) - ساعت ۱۴ الی ۱۸', 'hamishop-elementor-widgets' ) );
                return (string) apply_filters( 'hsw_pay_slot_text', $default, $this->get_name() );
        }

        /**
         * Mobile delivery line.
         */
        protected function m_delivery_line() {
                if ( $this->is_demo() ) {
                        return $this->opt( 'm_delivery', __( 'تحویل: فردا (۱۰ الی ۱۴) - ارسال اکسپرس', 'hamishop-elementor-widgets' ) );
                }
                $method = $this->method_title();
                return ( '' !== $method ) ? 'تحویل: ' . $method : $this->opt( 'm_delivery', '' );
        }

        /**
         * Edit URL (defaults to the my-account address book).
         */
        protected function edit_url() {
                $s = $this->effective_settings();
                if ( ! empty( $s['edit_url']['url'] ) ) {
                        return $s['edit_url']['url'];
                }
                if ( $this->has_cart() && function_exists( 'wc_get_account_endpoint_url' ) ) {
                        return wc_get_account_endpoint_url( 'edit-address' );
                }
                return '#';
        }

        protected function render() {
                $is_demo = $this->is_demo();
                if ( $is_demo ) {
                        $cust   = $this->demo_pay_customer();
                        $name   = $cust['name'];
                        $phone  = $cust['phone'];
                        $addr   = $cust['line'];
                } else {
                        $name   = $this->customer_name();
                        $phone  = $this->customer_phone();
                        $addr   = $this->customer_address();
                }
                $method    = $this->method_title();
                $slot      = $this->slot_text();
                $free      = $this->opt( 'free_text', __( 'رایگان', 'hamishop-elementor-widgets' ) );
                $ship_free = $is_demo ? $this->demo_pay_shipping()['free'] : ( (float) $this->chosen_shipping_cost() <= 0 );
                ?>
                <section class="<?php echo esc_attr( $this->root_class( 'prec' ) ); ?>">

                        <!-- =================== DESKTOP =================== -->
                        <div class="hsw-prec__card hsw-d">
                                <div class="hsw-prec__hd">
                                        <div class="hsw-prec__hd-r">
                                                <i class="hsw-ic" aria-hidden="true">local_shipping</i>
                                                <h2 class="hsw-prec__hd-t"><?php echo esc_html( $this->opt( 'title_d', __( 'مشخصات تحویل سفارش', 'hamishop-elementor-widgets' ) ) ); ?></h2>
                                        </div>
                                        <a class="hsw-prec__edit" href="<?php echo esc_url( $this->edit_url() ); ?>">
                                                <span><?php echo esc_html( $this->opt( 'edit_label', __( 'تغییر اطلاعات یا آدرس', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                <i class="hsw-ic" aria-hidden="true">edit_note</i>
                                        </a>
                                </div>

                                <div class="hsw-prec__grid">
                                        <div class="hsw-prec__col">
                                                <div class="hsw-prec__row">
                                                        <i class="hsw-ic" aria-hidden="true">person</i>
                                                        <span class="hsw-prec__lbl"><?php echo esc_html( $this->opt( 'name_label', __( 'تحویل‌گیرنده:', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                        <span><?php echo esc_html( trim( $name . ( $phone ? ' (' . $phone . ')' : '' ) ) ); ?></span>
                                                </div>
                                                <div class="hsw-prec__row is-start">
                                                        <i class="hsw-ic" aria-hidden="true">pin_drop</i>
                                                        <span class="hsw-prec__lbl"><?php echo esc_html( $this->opt( 'addr_label', __( 'آدرس:', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                        <span class="hsw-prec__addr"><?php echo esc_html( $addr ); ?></span>
                                                </div>
                                        </div>

                                        <div class="hsw-prec__box">
                                                <div class="hsw-prec__box-r">
                                                        <span class="hsw-prec__lbl is-icon">
                                                                <i class="hsw-ic" aria-hidden="true">rocket_launch</i>
                                                                <?php echo esc_html( $this->opt( 'method_label', __( 'شیوه ارسال:', 'hamishop-elementor-widgets' ) ) ); ?>
                                                        </span>
                                                        <span class="hsw-prec__method"><?php echo esc_html( $method ); ?></span>
                                                </div>
                                                <div class="hsw-prec__box-r">
                                                        <span class="hsw-prec__lbl is-icon">
                                                                <i class="hsw-ic" aria-hidden="true">schedule</i>
                                                                <?php echo esc_html( $this->opt( 'slot_label', __( 'بازه زمانی منتخب:', 'hamishop-elementor-widgets' ) ) ); ?>
                                                        </span>
                                                        <span><?php echo esc_html( $slot ); ?></span>
                                                </div>
                                        </div>
                                </div>
                        </div>

                        <!-- =================== MOBILE =================== -->
                        <section class="hsw-prec__mcard hsw-m">
                                <div class="hsw-prec__mhd">
                                        <div class="hsw-prec__hd-r">
                                                <i class="hsw-ic" aria-hidden="true">local_shipping</i>
                                                <h2 class="hsw-prec__hd-t is-m"><?php echo esc_html( $this->opt( 'title_m', __( 'اطلاعات مرسوله', 'hamishop-elementor-widgets' ) ) ); ?></h2>
                                        </div>
                                        <a class="hsw-prec__medit" href="<?php echo esc_url( $this->edit_url() ); ?>">
                                                <span><?php echo esc_html( $this->opt( 'm_edit_label', __( 'ویرایش', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                <i class="hsw-ic" aria-hidden="true">arrow_back</i>
                                        </a>
                                </div>

                                <div class="hsw-prec__mbody">
                                        <div class="hsw-prec__mrow">
                                                <i class="hsw-ic" aria-hidden="true">location_on</i>
                                                <p class="hsw-prec__maddr"><?php echo esc_html( $addr ); ?></p>
                                        </div>
                                        <div class="hsw-prec__mfoot">
                                                <span class="hsw-prec__mdeliv">
                                                        <i class="hsw-ic" aria-hidden="true">schedule</i>
                                                        <?php echo esc_html( $this->m_delivery_line() ); ?>
                                                </span>
                                                <?php if ( $ship_free ) : ?>
                                                        <span class="hsw-prec__mfree"><?php echo esc_html( $free ); ?></span>
                                                <?php endif; ?>
                                        </div>
                                </div>
                        </section>

                </section>
                <?php
        }
}
