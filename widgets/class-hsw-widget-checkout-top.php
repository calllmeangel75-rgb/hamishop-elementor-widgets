<?php
/**
 * Widget: Checkout shipping header & steps — faithful conversion of the mock.
 * Desktop: sticky TopAppBar (back + title | support center + phone) + boxed
 * 3-step tracker with meta labels (مرحله اول / در حال تکمیل / مرحله بعد).
 * Mobile: compact header (back + title + support) + icon stepper with a
 * progress line.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class HSW_Widget_Checkout_Top extends HSW_Checkout_Base {

        public function get_name() {
                return 'hsw-checkout-top';
        }

        public function get_title() {
                return __( 'هدر و مراحل اطلاعات ارسال (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-site-identity';
        }

        public function get_keywords() {
                return array( 'checkout', 'shipping', 'steps', 'header', 'اطلاعات ارسال', 'مراحل خرید', 'hamishop' );
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
                                'label'       => __( 'چسبیدن هدر به بالای صفحه', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'default'     => 'yes',
                                'description' => __( 'اگر قالب هدر ثابت دارد، بهتر است خاموش باشد.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'title',
                        array(
                                'label'   => __( 'عنوان صفحه', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'اطلاعات ارسال', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'back_url',
                        array(
                                'label'       => __( 'لینک بازگشت (سبد خرید)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::URL,
                                'options'     => array( 'url', 'is_external', 'nofollow' ),
                                'description' => __( 'خالی = آدرس پیش‌فرض سبد خرید ووکامرس.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'show_support',
                        array(
                                'label'   => __( 'مرکز تماس و پشتیبانی (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'support_label',
                        array(
                                'label'     => __( 'برچسب پشتیبانی', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'مرکز تماس و پشتیبانی', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_support' => 'yes' ),
                        )
                );

                $this->add_control(
                        'support_phone',
                        array(
                                'label'       => __( 'شماره تماس', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( '۰۲۱-۹۱۰۰۸۸۷۷', 'hamishop-elementor-widgets' ),
                                'description' => __( 'با dir=ltr و ارقام لاتین هم پشتیبانی می‌شود.', 'hamishop-elementor-widgets' ),
                                'condition'   => array( 'show_support' => 'yes' ),
                        )
                );

                $this->add_control(
                        'support_url',
                        array(
                                'label'     => __( 'لینک آیکن پشتیبانی', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::URL,
                                'options'   => array( 'url', 'is_external', 'nofollow' ),
                                'condition' => array( 'show_support' => 'yes' ),
                        )
                );

                $this->add_control(
                        'steps_heading',
                        array( 'label' => __( 'مراحل ثبت سفارش', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $this->add_control(
                        'active_step',
                        array(
                                'label'   => __( 'مرحله فعال', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => '2',
                                'options' => array(
                                        '1' => __( '۱ — سبد خرید', 'hamishop-elementor-widgets' ),
                                        '2' => __( '۲ — اطلاعات ارسال', 'hamishop-elementor-widgets' ),
                                        '3' => __( '۳ — پرداخت', 'hamishop-elementor-widgets' ),
                                ),
                        )
                );

                $repeater = new Repeater();
                $repeater->add_control(
                        'name',
                        array(
                                'label'   => __( 'نام مرحله', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => 'مرحله',
                        )
                );
                $repeater->add_control(
                        'name_m',
                        array(
                                'label'       => __( 'نام موبایل (اختیاری)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'description' => __( 'خالی = همان نام مرحله.', 'hamishop-elementor-widgets' ),
                        )
                );
                $repeater->add_control(
                        'meta',
                        array(
                                'label'       => __( 'برچسب کوچک (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'description' => __( 'مثل «مرحله اول» یا «در حال تکمیل».', 'hamishop-elementor-widgets' ),
                        )
                );
                $this->add_control(
                        'steps',
                        array(
                                'label'       => __( 'مراحل', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $repeater->get_controls(),
                                'title_field' => '{{{ name }}}',
                                'default'     => array(
                                        array(
                                                'name'   => __( 'سبد خرید', 'hamishop-elementor-widgets' ),
                                                'name_m' => __( 'سبد خرید', 'hamishop-elementor-widgets' ),
                                                'meta'   => __( 'مرحله اول', 'hamishop-elementor-widgets' ),
                                        ),
                                        array(
                                                'name'   => __( 'اطلاعات و مشخصات ارسال', 'hamishop-elementor-widgets' ),
                                                'name_m' => __( 'اطلاعات ارسال', 'hamishop-elementor-widgets' ),
                                                'meta'   => __( 'در حال تکمیل', 'hamishop-elementor-widgets' ),
                                        ),
                                        array(
                                                'name'   => __( 'پرداخت نهایی', 'hamishop-elementor-widgets' ),
                                                'name_m' => __( 'پرداخت', 'hamishop-elementor-widgets' ),
                                                'meta'   => __( 'مرحله بعد', 'hamishop-elementor-widgets' ),
                                        ),
                                ),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_box( 'bar', '{{WRAPPER}} .hsw-cktop__bar', array( 'radius' => false ) );
                $this->a_text( 'title', '{{WRAPPER}} .hsw-cktop__title', array( 'default' => 18 ) );
                $this->a_color( 'back_c', __( 'دکمه بازگشت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cktop__back' );
                $this->a_text( 'support_lbl', '{{WRAPPER}} .hsw-cktop__support-lbl', array( 'default' => 11 ) );
                $this->a_text( 'support_ph', '{{WRAPPER}} .hsw-cktop__support-ph', array( 'default' => 14 ) );
                $this->a_color( 'support_ic', __( 'آیکن پشتیبانی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cktop__support .hsw-ic' );

                $this->a_box( 'track', '{{WRAPPER}} .hsw-cktop__track', array( 'radius' => false ) );
                $this->a_color( 'step_done', __( 'مرحله تکمیل‌شده', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cktop__step.is-done' );
                $this->a_color( 'step_done_bg', __( 'پس‌زمینه دایره تکمیل‌شده', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cktop__step.is-done .hsw-cktop__dot', 'background-color' );
                $this->a_color( 'step_active', __( 'مرحله فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cktop__step.is-active' );
                $this->a_color( 'step_active_bg', __( 'پس‌زمینه دایره فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cktop__step.is-active .hsw-cktop__dot', 'background-color' );
                $this->a_color( 'step_next', __( 'مرحله بعدی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cktop__step:not(.is-active):not(.is-done)' );
                $this->a_color( 'line_on', __( 'خط طی‌شده', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cktop__line.is-on', 'background-color' );

                $this->end_controls_section();
        }

        /**
         * Normalized steps: [ name_d, name_m, meta ].
         */
        protected function steps_list() {
                $s     = $this->effective_settings();
                $steps = ( isset( $s['steps'] ) && is_array( $s['steps'] ) ) ? $s['steps'] : array();
                if ( empty( $steps ) ) {
                        return array(
                                array( __( 'سبد خرید', 'hamishop-elementor-widgets' ), __( 'سبد خرید', 'hamishop-elementor-widgets' ), __( 'مرحله اول', 'hamishop-elementor-widgets' ) ),
                                array( __( 'اطلاعات و مشخصات ارسال', 'hamishop-elementor-widgets' ), __( 'اطلاعات ارسال', 'hamishop-elementor-widgets' ), __( 'در حال تکمیل', 'hamishop-elementor-widgets' ) ),
                                array( __( 'پرداخت نهایی', 'hamishop-elementor-widgets' ), __( 'پرداخت', 'hamishop-elementor-widgets' ), __( 'مرحله بعد', 'hamishop-elementor-widgets' ) ),
                        );
                }
                $out = array();
                foreach ( $steps as $st ) {
                        $name = isset( $st['name'] ) ? $st['name'] : '';
                        $out[] = array(
                                $name,
                                ( isset( $st['name_m'] ) && '' !== trim( (string) $st['name_m'] ) ) ? $st['name_m'] : $name,
                                ( isset( $st['meta'] ) && '' !== trim( (string) $st['meta'] ) ) ? $st['meta'] : '',
                        );
                }
                return $out;
        }

        protected function render() {
                $s      = $this->effective_settings();
                $steps  = $this->steps_list();
                $active = max( 1, min( count( $steps ), (int) $this->opt( 'active_step', '2' ) ) );
                $sticky = ( 'yes' === ( $s['sticky'] ?? 'yes' ) );
                $title  = $this->opt( 'title', __( 'اطلاعات ارسال', 'hamishop-elementor-widgets' ) );

                $back_url = $this->cart_url();
                if ( ! empty( $s['back_url']['url'] ) ) {
                        $back_url = $s['back_url']['url'];
                }

                $show_support = ( 'yes' === ( $s['show_support'] ?? 'yes' ) );
                $support_lbl  = $this->opt( 'support_label', __( 'مرکز تماس و پشتیبانی', 'hamishop-elementor-widgets' ) );
                $support_ph   = $this->opt( 'support_phone', $this->demo_support_phone() );
                $support_url  = ( ! empty( $s['support_url']['url'] ) ) ? $s['support_url']['url'] : '#';
                ?>
                <section class="<?php echo esc_attr( $this->root_class( 'cktop' ) ); ?>">

                        <!-- =================== DESKTOP =================== -->
                        <header class="hsw-cktop__bar hsw-d<?php echo $sticky ? ' is-sticky' : ''; ?>">
                                <div class="hsw-cktop__shell hsw-cktop__bar-in">
                                        <div class="hsw-cktop__brand">
                                                <a class="hsw-cktop__back" href="<?php echo esc_url( $back_url ); ?>" title="<?php esc_attr_e( 'بازگشت به سبد خرید', 'hamishop-elementor-widgets' ); ?>">
                                                        <i class="hsw-ic" aria-hidden="true">arrow_forward</i>
                                                </a>
                                                <h1 class="hsw-cktop__title"><?php echo esc_html( $title ); ?></h1>
                                        </div>
                                        <?php if ( $show_support ) : ?>
                                                <div class="hsw-cktop__support">
                                                        <div class="hsw-cktop__support-tx">
                                                                <span class="hsw-cktop__support-lbl"><?php echo esc_html( $support_lbl ); ?></span>
                                                                <span class="hsw-cktop__support-ph"><?php echo esc_html( $support_ph ); ?></span>
                                                        </div>
                                                        <a class="hsw-cktop__support-btn" href="<?php echo esc_url( $support_url ); ?>" aria-label="<?php esc_attr_e( 'پشتیبانی', 'hamishop-elementor-widgets' ); ?>">
                                                                <i class="hsw-ic" aria-hidden="true">support_agent</i>
                                                        </a>
                                                </div>
                                        <?php endif; ?>
                                </div>
                        </header>

                        <nav class="hsw-cktop__wrap hsw-d" aria-label="<?php esc_attr_e( 'مراحل خرید', 'hamishop-elementor-widgets' ); ?>">
                                <div class="hsw-cktop__shell">
                                        <div class="hsw-cktop__track">
                                                <?php foreach ( $steps as $i => $st ) : ?>
                                                        <?php
                                                        $n    = $i + 1;
                                                        $cls  = ( $n === $active ) ? ' is-active' : '';
                                                        $cls .= ( $n < $active ) ? ' is-done' : '';
                                                        ?>
                                                        <?php if ( 0 < $i ) : ?>
                                                                <span class="hsw-cktop__line<?php echo ( $n <= $active ) ? ' is-on' : ''; ?>" aria-hidden="true"></span>
                                                        <?php endif; ?>
                                                        <div class="hsw-cktop__step<?php echo esc_attr( $cls ); ?>">
                                                                <span class="hsw-cktop__dot">
                                                                        <?php if ( $n < $active ) : ?>
                                                                                <i class="hsw-ic is-fill" aria-hidden="true">check</i>
                                                                        <?php else : ?>
                                                                                <?php echo esc_html( HSW_Helpers::fa_num( $n ) ); ?>
                                                                        <?php endif; ?>
                                                                </span>
                                                                <span class="hsw-cktop__step-tx">
                                                                        <?php if ( '' !== $st[2] ) : ?>
                                                                                <span class="hsw-cktop__step-meta"><?php echo esc_html( $st[2] ); ?></span>
                                                                        <?php endif; ?>
                                                                        <span class="hsw-cktop__step-name"><?php echo esc_html( $st[0] ); ?></span>
                                                                </span>
                                                        </div>
                                                <?php endforeach; ?>
                                        </div>
                                </div>
                        </nav>

                        <!-- =================== MOBILE =================== -->
                        <header class="hsw-cktop__mbar hsw-m">
                                <div class="hsw-cktop__mrow">
                                        <a class="hsw-cktop__back" href="<?php echo esc_url( $back_url ); ?>" aria-label="<?php esc_attr_e( 'بازگشت به سبد خرید', 'hamishop-elementor-widgets' ); ?>">
                                                <i class="hsw-ic" aria-hidden="true">arrow_forward</i>
                                        </a>
                                        <h1 class="hsw-cktop__title"><?php echo esc_html( $title ); ?></h1>
                                        <?php if ( $show_support ) : ?>
                                                <a class="hsw-cktop__msupport" href="<?php echo esc_url( $support_url ); ?>" aria-label="<?php esc_attr_e( 'پشتیبانی آنلاین', 'hamishop-elementor-widgets' ); ?>">
                                                        <i class="hsw-ic" aria-hidden="true">support_agent</i>
                                                </a>
                                        <?php endif; ?>
                                </div>
                        </header>

                        <div class="hsw-cktop__mwrap hsw-m">
                                <section class="hsw-cktop__mtrack" aria-label="<?php esc_attr_e( 'مراحل ثبت سفارش', 'hamishop-elementor-widgets' ); ?>">
                                        <span class="hsw-cktop__mline" aria-hidden="true"><span style="width:<?php echo esc_attr( ( ( $active - 1 ) / max( 1, count( $steps ) - 1 ) ) * 100 ); ?>%"></span></span>
                                        <?php foreach ( $steps as $i => $st ) : ?>
                                                <?php
                                                $n    = $i + 1;
                                                $cls  = ( $n === $active ) ? ' is-active' : '';
                                                $cls .= ( $n < $active ) ? ' is-done' : '';
                                                ?>
                                                <div class="hsw-cktop__step<?php echo esc_attr( $cls ); ?>">
                                                        <span class="hsw-cktop__dot">
                                                                <?php if ( $n < $active ) : ?>
                                                                        <i class="hsw-ic is-fill" aria-hidden="true">check</i>
                                                                <?php elseif ( $n === $active ) : ?>
                                                                        <i class="hsw-ic" aria-hidden="true">local_shipping</i>
                                                                <?php else : ?>
                                                                        <?php echo esc_html( HSW_Helpers::fa_num( $n ) ); ?>
                                                                <?php endif; ?>
                                                        </span>
                                                        <span class="hsw-cktop__step-name"><?php echo esc_html( $st[1] ); ?></span>
                                                </div>
                                        <?php endforeach; ?>
                                </section>
                        </div>

                </section>
                <?php
        }
}
