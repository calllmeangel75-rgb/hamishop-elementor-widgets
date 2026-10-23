<?php
/**
 * Widget: Delivery address — faithful conversion of the mock.
 * Desktop: boxed card (header + add-new) with the rich selected-address box
 * (check + recipient chip + edit/change links + phone/postcode footer).
 * Mobile: compact card with the right accent bar + dashed add-new button.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Checkout_Address extends HSW_Checkout_Base {

        public function get_name() {
                return 'hsw-checkout-address';
        }

        public function get_title() {
                return __( 'آدرس تحویل سفارش (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-icon-box';
        }

        public function get_keywords() {
                return array( 'checkout', 'address', 'shipping', 'آدرس', 'ارسال', 'hamishop' );
        }

        protected function register_controls() {
                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control(
                        'title_d',
                        array(
                                'label'   => __( 'عنوان (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'آدرس تحویل سفارش', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'title_m',
                        array(
                                'label'   => __( 'عنوان (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'نشانی تحویل سفارش', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'chip_text',
                        array(
                                'label'       => __( 'برچسب تحویل‌گیرنده', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'تحویل‌گیرنده اصلی', 'hamishop-elementor-widgets' ),
                                'description' => __( 'خالی = نمایش داده نمی‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'links_heading',
                        array( 'label' => __( 'دکمه‌ها', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $this->add_control(
                        'add_label_d',
                        array(
                                'label'   => __( 'متن افزودن (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'افزودن آدرس جدید', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'add_label_m',
                        array(
                                'label'   => __( 'متن افزودن (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( '+ افزودن نشانی جدید', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'show_edit',
                        array(
                                'label' => __( 'دکمه ویرایش (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'  => Controls_Manager::SWITCHER,
                        )
                );

                $this->add_control(
                        'edit_label',
                        array(
                                'label'     => __( 'متن ویرایش', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'ویرایش', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_edit' => 'yes' ),
                        )
                );

                $this->add_control(
                        'change_label',
                        array(
                                'label'   => __( 'متن تغییر آدرس', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'تغییر آدرس', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'manage_url',
                        array(
                                'label'       => __( 'لینک مدیریت آدرس', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::URL,
                                'options'     => array( 'url', 'is_external', 'nofollow' ),
                                'description' => __( 'خالی = صفحه «حساب کاربری ← آدرس‌ها» ووکامرس.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'rows_heading',
                        array( 'label' => __( 'ردیف مشخصات', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $this->add_control(
                        'phone_label',
                        array(
                                'label'   => __( 'برچسب شماره تماس', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'شماره تماس:', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'show_postcode',
                        array(
                                'label' => __( 'نمایش کد پستی', 'hamishop-elementor-widgets' ),
                                'type'  => Controls_Manager::SWITCHER,
                        )
                );

                $this->add_control(
                        'postcode_label',
                        array(
                                'label'     => __( 'برچسب کد پستی', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'کد پستی:', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_postcode' => 'yes' ),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_box( 'card', '{{WRAPPER}} .hsw-ckaddr__card' );
                $this->a_color( 'hd_ic', __( 'آیکن عنوان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ckaddr__hd .hsw-ic' );
                $this->a_text( 'hd_tx', '{{WRAPPER}} .hsw-ckaddr__hd-t', array( 'default' => 16 ) );
                $this->a_color( 'add_c', __( 'دکمه افزودن', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ckaddr__add' );
                $this->a_text( 'name_tx', '{{WRAPPER}} .hsw-ckaddr__name', array( 'default' => 14 ) );
                $this->a_text( 'chip_tx', '{{WRAPPER}} .hsw-ckaddr__chip', array( 'default' => 11 ) );
                $this->a_color( 'chip_bg', __( 'پس‌زمینه برچسب', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ckaddr__chip', 'background-color' );
                $this->a_text( 'line_tx', '{{WRAPPER}} .hsw-ckaddr__line', array( 'default' => 14 ) );
                $this->a_color( 'meta_c', __( 'متن ردیف تماس/کدپستی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ckaddr__meta' );

                $this->end_controls_section();
        }

        /**
         * WooCommerce «my account → addresses» URL.
         */
        protected function manage_url() {
                if ( ! empty( $this->effective_settings()['manage_url']['url'] ) ) {
                        return $this->effective_settings()['manage_url']['url'];
                }
                if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
                        return wc_get_account_endpoint_url( 'edit-address' );
                }
                return home_url( '/my-account/edit-address/' );
        }

        protected function render() {
                $s = $this->effective_settings();

                $demo = $this->is_demo();
                if ( $demo ) {
                        $addr = $this->demo_address();
                } else {
                        $addr = array(
                                'name'     => $this->customer_name(),
                                'chip'     => $this->opt( 'chip_text', __( 'تحویل‌گیرنده اصلی', 'hamishop-elementor-widgets' ) ),
                                'line'     => $this->customer_address(),
                                'phone'    => $this->customer_phone(),
                                'postcode' => $this->customer_postcode(),
                        );
                        if ( $this->is_editing() && ! $this->customer_has_address() && '' === trim( $addr['name'] . $addr['phone'] . $addr['postcode'] ) ) {
                                // Editor preview with an empty guest customer → show the design mock.
                                $addr = $this->demo_address();
                        }
                }

                $title_d   = $this->opt( 'title_d', __( 'آدرس تحویل سفارش', 'hamishop-elementor-widgets' ) );
                $title_m   = $this->opt( 'title_m', __( 'نشانی تحویل سفارش', 'hamishop-elementor-widgets' ) );
                $add_d     = $this->opt( 'add_label_d', __( 'افزودن آدرس جدید', 'hamishop-elementor-widgets' ) );
                $add_m     = $this->opt( 'add_label_m', __( '+ افزودن نشانی جدید', 'hamishop-elementor-widgets' ) );
                $change    = $this->opt( 'change_label', __( 'تغییر آدرس', 'hamishop-elementor-widgets' ) );
                $edit      = $this->opt( 'edit_label', __( 'ویرایش', 'hamishop-elementor-widgets' ) );
                $show_edit = ( 'yes' === ( $s['show_edit'] ?? '' ) );
                $manage    = $this->manage_url();

                /*
                 * No saved address → ask for it here. Linking to the account
                 * page mid-checkout lost the customer, and the pay step then
                 * had nothing to deliver to.
                 */
                $has_address = $demo || $this->customer_has_address();
                ?>
                <section class="<?php echo esc_attr( $this->root_class( 'ckaddr' ) ); ?>">

                <?php if ( ! $has_address ) : ?>
                        <?php echo $this->address_form_html( $title_d ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* inside. ?>
                <?php else : ?>

                        <!-- =================== DESKTOP =================== -->
                        <article class="hsw-ckaddr__card hsw-d">
                                <div class="hsw-ckaddr__hd">
                                        <div class="hsw-ckaddr__hd-r">
                                                <i class="hsw-ic is-fill" aria-hidden="true">location_on</i>
                                                <h2 class="hsw-ckaddr__hd-t"><?php echo esc_html( $title_d ); ?></h2>
                                        </div>
                                        <?php if ( '' !== trim( $add_d ) ) : ?>
                                                <a class="hsw-ckaddr__add" href="<?php echo esc_url( $manage ); ?>">
                                                        <i class="hsw-ic" aria-hidden="true">add_circle</i>
                                                        <span><?php echo esc_html( $add_d ); ?></span>
                                                </a>
                                        <?php endif; ?>
                                </div>

                                <div class="hsw-ckaddr__box">
                                        <div class="hsw-ckaddr__box-top">
                                                <div class="hsw-ckaddr__who">
                                                        <span class="hsw-ckaddr__check" aria-hidden="true"><i class="hsw-ic" aria-hidden="true">check</i></span>
                                                        <?php if ( '' !== trim( (string) $addr['chip'] ) ) : ?>
                                                                <span class="hsw-ckaddr__chip"><?php echo esc_html( $addr['chip'] ); ?></span>
                                                        <?php endif; ?>
                                                        <span class="hsw-ckaddr__name"><?php echo esc_html( $addr['name'] ); ?></span>
                                                </div>
                                                <div class="hsw-ckaddr__ops">
                                                        <?php if ( $show_edit ) : ?>
                                                                <a class="hsw-ckaddr__op" href="<?php echo esc_url( $manage ); ?>">
                                                                        <i class="hsw-ic" aria-hidden="true">edit</i>
                                                                        <span><?php echo esc_html( $edit ); ?></span>
                                                                </a>
                                                                <span class="hsw-ckaddr__sep" aria-hidden="true">|</span>
                                                        <?php endif; ?>
                                                        <a class="hsw-ckaddr__op" href="<?php echo esc_url( $manage ); ?>">
                                                                <span><?php echo esc_html( $change ); ?></span>
                                                        </a>
                                                </div>
                                        </div>

                                        <p class="hsw-ckaddr__line"><?php echo ( '' !== trim( (string) $addr['line'] ) ) ? esc_html( $addr['line'] ) : '—'; ?></p>

                                        <div class="hsw-ckaddr__meta">
                                                <span class="hsw-ckaddr__meta-i">
                                                        <i class="hsw-ic" aria-hidden="true">phone_iphone</i>
                                                        <span><?php echo esc_html( $this->opt( 'phone_label', __( 'شماره تماس:', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                        <b class="hsw-ckaddr__ltr"><?php echo esc_html( $addr['phone'] ); ?></b>
                                                </span>
                                                <?php if ( 'yes' === ( $s['show_postcode'] ?? '' ) ) : ?>
                                                        <span class="hsw-ckaddr__meta-i">
                                                                <i class="hsw-ic" aria-hidden="true">mail</i>
                                                                <span><?php echo esc_html( $this->opt( 'postcode_label', __( 'کد پستی:', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                                <b><?php echo esc_html( $addr['postcode'] ); ?></b>
                                                        </span>
                                                <?php endif; ?>
                                        </div>
                                </div>
                        </article>

                        <!-- =================== MOBILE =================== -->
                        <div class="hsw-m hsw-ckaddr__mwrap">
                                <div class="hsw-ckaddr__mhd">
                                        <h2 class="hsw-ckaddr__hd-t hsw-ckaddr__hd-t--m">
                                                <i class="hsw-ic is-fill" aria-hidden="true">location_on</i>
                                                <?php echo esc_html( $title_m ); ?>
                                        </h2>
                                        <a class="hsw-ckaddr__mchange" href="<?php echo esc_url( $manage ); ?>"><?php echo esc_html( $change ); ?></a>
                                </div>

                                <div class="hsw-ckaddr__mcard">
                                        <p class="hsw-ckaddr__line hsw-ckaddr__line--m"><?php echo ( '' !== trim( (string) $addr['line'] ) ) ? esc_html( $addr['line'] ) : '—'; ?></p>
                                        <div class="hsw-ckaddr__mmeta">
                                                <span class="hsw-ckaddr__meta-i">
                                                        <i class="hsw-ic" aria-hidden="true">person</i>
                                                        <span><?php echo esc_html( $addr['name'] ); ?></span>
                                                </span>
                                                <span class="hsw-ckaddr__meta-i">
                                                        <i class="hsw-ic" aria-hidden="true">phone_iphone</i>
                                                        <b class="hsw-ckaddr__ltr"><?php echo esc_html( $addr['phone'] ); ?></b>
                                                </span>
                                        </div>
                                </div>

                                <?php if ( '' !== trim( $add_m ) ) : ?>
                                        <a class="hsw-ckaddr__madd" href="<?php echo esc_url( $manage ); ?>">
                                                <i class="hsw-ic" aria-hidden="true">add_circle</i>
                                                <span><?php echo esc_html( $add_m ); ?></span>
                                        </a>
                                <?php endif; ?>
                        </div>

                <?php endif; ?>
                </section>
                <?php
        }

        /**
         * Inline delivery-address form, shown while the customer has no address.
         *
         * Deliberately short: the five fields a courier needs. Everything else
         * (company, second line, notes) stays optional in the account editor.
         *
         * @param string $title Section title.
         * @return string
         */
        protected function address_form_html( $title ) {
                $country = '';
                $state   = '';
                $city    = '';
                $line1   = '';
                $line2   = '';
                $postcode = '';
                $first   = '';
                $last    = '';
                $phone   = '';

                if ( function_exists( 'WC' ) && WC()->customer ) {
                        $c        = WC()->customer;
                        $country  = (string) $c->get_shipping_country();
                        $state    = (string) $c->get_shipping_state();
                        $city     = (string) $c->get_shipping_city();
                        $line1    = (string) $c->get_shipping_address_1();
                        $line2    = (string) $c->get_shipping_address_2();
                        $postcode = (string) $c->get_shipping_postcode();
                        $first    = (string) $c->get_shipping_first_name();
                        $last     = (string) $c->get_shipping_last_name();

                        /* Fall back to the billing half when shipping is empty. */
                        if ( '' === trim( $first . $last . $city . $line1 ) ) {
                                $first    = (string) $c->get_billing_first_name();
                                $last     = (string) $c->get_billing_last_name();
                                $city     = (string) $c->get_billing_city();
                                $line1    = (string) $c->get_billing_address_1();
                                $line2    = (string) $c->get_billing_address_2();
                                $postcode = (string) $c->get_billing_postcode();
                                $state    = (string) $c->get_billing_state();
                        }

                        if ( '' === trim( $phone ) ) {
                                $phone = (string) $c->get_billing_phone();
                        }
                }

                if ( '' === $country ) {
                        $base    = function_exists( 'wc_get_base_location' ) ? wc_get_base_location() : array( 'country' => 'IR' );
                        $country = isset( $base['country'] ) && '' !== $base['country'] ? (string) $base['country'] : 'IR';
                }

                $states = array();
                if ( function_exists( 'WC' ) && WC()->countries ) {
                        $list = WC()->countries->get_states( $country );
                        if ( is_array( $list ) ) {
                                $states = $list;
                        }
                }

                $field = function ( $name, $label, $value, $type = 'text', $extra = '', $required = false ) {
                        $mark  = $required ? ' <span class="hsw-ckaddr__req" aria-hidden="true">*</span>' : '';
                        $html  = '<label class="hsw-ckaddr__fl" for="hsw-ckaddr-' . esc_attr( $name ) . '">' . esc_html( $label ) . $mark . '</label>';
                        $html .= '<input class="hsw-ckaddr__fi" id="hsw-ckaddr-' . esc_attr( $name ) . '" type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( $required ? ' required aria-required="true"' : '' ) . $extra . '>';
                        return $html;
                };

                ob_start();
                ?>
                <article class="hsw-ckaddr__card hsw-ckaddr__card--form">
                        <div class="hsw-ckaddr__hd">
                                <div class="hsw-ckaddr__hd-r">
                                        <i class="hsw-ic is-fill" aria-hidden="true">location_on</i>
                                        <h2 class="hsw-ckaddr__hd-t"><?php echo esc_html( $title ); ?></h2>
                                </div>
                        </div>

                        <p class="hsw-ckaddr__formhint">
                                <?php esc_html_e( 'برای ادامهٔ خرید، نشانی تحویل را کامل کنید. این آدرس در حساب کاربری شما هم ذخیره می‌شود.', 'hamishop-elementor-widgets' ); ?>
                        </p>

                        <form class="hsw-ckaddr__form" data-hsw-address-form novalidate>
                                <div class="hsw-ckaddr__frow">
                                        <div class="hsw-ckaddr__fcell">
                                                <?php echo $field( 'first_name', __( 'نام', 'hamishop-elementor-widgets' ), $first, 'text', ' autocomplete="given-name"', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        </div>
                                        <div class="hsw-ckaddr__fcell">
                                                <?php echo $field( 'last_name', __( 'نام خانوادگی', 'hamishop-elementor-widgets' ), $last, 'text', ' autocomplete="family-name"' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        </div>
                                </div>

                                <div class="hsw-ckaddr__frow">
                                        <div class="hsw-ckaddr__fcell">
                                                <?php echo $field( 'phone', __( 'شماره تماس', 'hamishop-elementor-widgets' ), $phone, 'tel', ' inputmode="tel" dir="ltr" autocomplete="tel"', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        </div>
                                        <div class="hsw-ckaddr__fcell">
                                                <?php echo $field( 'postcode', __( 'کد پستی', 'hamishop-elementor-widgets' ), $postcode, 'text', ' inputmode="numeric" dir="ltr" autocomplete="postal-code"' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        </div>
                                </div>

                                <div class="hsw-ckaddr__frow">
                                        <div class="hsw-ckaddr__fcell">
                                                <label class="hsw-ckaddr__fl" for="hsw-ckaddr-state"><?php esc_html_e( 'استان', 'hamishop-elementor-widgets' ); ?> <span class="hsw-ckaddr__req" aria-hidden="true">*</span></label>
                                                <?php if ( $states ) : ?>
                                                        <select class="hsw-ckaddr__fi" id="hsw-ckaddr-state" name="state" required>
                                                                <option value=""><?php esc_html_e( 'انتخاب کنید', 'hamishop-elementor-widgets' ); ?></option>
                                                                <?php foreach ( $states as $code => $label ) : ?>
                                                                        <option value="<?php echo esc_attr( $code ); ?>"<?php selected( $state, $code ); ?>><?php echo esc_html( $label ); ?></option>
                                                                <?php endforeach; ?>
                                                        </select>
                                                <?php else : ?>
                                                        <?php echo $field( 'state', __( 'استان', 'hamishop-elementor-widgets' ), $state, 'text', '', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                <?php endif; ?>
                                        </div>
                                        <div class="hsw-ckaddr__fcell">
                                                <?php echo $field( 'city', __( 'شهر', 'hamishop-elementor-widgets' ), $city, 'text', ' autocomplete="address-level2"', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        </div>
                                </div>

                                <div class="hsw-ckaddr__frow">
                                        <div class="hsw-ckaddr__fcell hsw-ckaddr__fcell--wide">
                                                <?php echo $field( 'address_1', __( 'نشانی کامل', 'hamishop-elementor-widgets' ), $line1, 'text', ' autocomplete="street-address"', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        </div>
                                </div>

                                <div class="hsw-ckaddr__frow">
                                        <div class="hsw-ckaddr__fcell hsw-ckaddr__fcell--wide">
                                                <?php echo $field( 'address_2', __( 'پلاک، واحد، طبقه (اختیاری)', 'hamishop-elementor-widgets' ), $line2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        </div>
                                </div>

                                <input type="hidden" name="country" value="<?php echo esc_attr( $country ); ?>">

                                <p class="hsw-ckaddr__fmsg" data-hsw-address-msg role="alert" hidden></p>

                                <button type="submit" class="hsw-ckaddr__fsubmit">
                                        <i class="hsw-ic" aria-hidden="true">check</i>
                                        <span><?php esc_html_e( 'ثبت آدرس و ادامه', 'hamishop-elementor-widgets' ); ?></span>
                                </button>
                        </form>
                </article>
                <?php
                return (string) ob_get_clean();
        }
}
