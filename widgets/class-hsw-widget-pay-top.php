<?php
/**
 * Widget: Payment page header & steps — faithful conversion of the mock.
 * Desktop: brand TopNavBar (logo + امن-gateway tag | step links | SSL pill +
 * support) + boxed 3-step progress card (۱. سبد / ۲. آدرس / ۳. پرداخت).
 * Mobile: compact header (back + title/subtitle + support) + icon stepper
 * with the connecting progress line.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class HSW_Widget_Pay_Top extends HSW_Pay_Base {

        public function get_name() {
                return 'hsw-pay-top';
        }

        public function get_title() {
                return __( 'هدر و مراحل پرداخت (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-site-identity';
        }

        public function get_keywords() {
                return array( 'checkout', 'payment', 'header', 'steps', 'پرداخت', 'مراحل خرید', 'hamishop' );
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
                        'brand_heading',
                        array( 'label' => __( 'نوار برند (دسکتاپ)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $this->add_control(
                        'brand_text',
                        array(
                                'label'       => __( 'نام فروشگاه (خالی = بدون نوار برند)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => '',
                                'placeholder' => get_bloginfo( 'name' ),
                        )
                );

                $this->add_control(
                        'brand_url',
                        array(
                                'label'   => __( 'لینک نام فروشگاه', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::URL,
                                'options' => array( 'url', 'is_external', 'nofollow' ),
                        )
                );

                $this->add_control(
                        'brand_tag',
                        array(
                                'label'   => __( 'برچسب کنار لوگو', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'درگاه پرداخت امن', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'nav_heading',
                        array( 'label' => __( 'لینک‌های مراحل (دسکتاپ)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $nav = new Repeater();
                $nav->add_control(
                        'name',
                        array(
                                'label'   => __( 'عنوان', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => 'مرحله',
                        )
                );
                $nav->add_control(
                        'url',
                        array(
                                'label'       => __( 'لینک', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'description' => __( 'خالی = پیش‌فرض (سبد خرید / پرداخت / همین صفحه).', 'hamishop-elementor-widgets' ),
                        )
                );
                $this->add_control(
                        'nav_steps',
                        array(
                                'label'       => __( 'مراحل نوار بالای دسکتاپ', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $nav->get_controls(),
                                'title_field' => '{{{ name }}}',
                                'default'     => array(
                                        array( 'name' => __( 'سبد خرید', 'hamishop-elementor-widgets' ) ),
                                        array( 'name' => __( 'اطلاعات ارسال', 'hamishop-elementor-widgets' ) ),
                                        array( 'name' => __( 'پرداخت نهایی', 'hamishop-elementor-widgets' ) ),
                                ),
                        )
                );

                $this->add_control(
                        'active_step',
                        array(
                                'label'   => __( 'مرحله فعال', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => '3',
                                'options' => array(
                                        '1' => __( '۱ — سبد خرید', 'hamishop-elementor-widgets' ),
                                        '2' => __( '۲ — اطلاعات ارسال', 'hamishop-elementor-widgets' ),
                                        '3' => __( '۳ — پرداخت نهایی', 'hamishop-elementor-widgets' ),
                                ),
                        )
                );

                $this->add_control(
                        'ssl_heading',
                        array( 'label' => __( 'پشتیبانی و SSL', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $this->add_control(
                        'show_ssl',
                        array(
                                'label'   => __( 'نشان رمزنگاری SSL (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'ssl_text',
                        array(
                                'label'     => __( 'متن SSL', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'رمزنگاری ۲۵۶ بیتی SSL', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_ssl' => 'yes' ),
                        )
                );

                $this->add_control(
                        'show_support',
                        array(
                                'label'   => __( 'پشتیبانی تلفنی', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'support_phone',
                        array(
                                'label'       => __( 'متن پشتیبانی (خالی = بدون شماره)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => '',
                                'condition'   => array( 'show_support' => 'yes' ),
                        )
                );

                $this->add_control(
                        'support_url',
                        array(
                                'label'     => __( 'لینک پشتیبانی', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::URL,
                                'options'   => array( 'url', 'is_external', 'nofollow' ),
                                'condition' => array( 'show_support' => 'yes' ),
                        )
                );

                $this->add_control(
                        'prog_heading',
                        array( 'label' => __( 'کارت مراحل (دسکتاپ)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $prog = new Repeater();
                $prog->add_control(
                        'name',
                        array(
                                'label'   => __( 'عنوان مرحله', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => 'مرحله',
                        )
                );
                $prog->add_control(
                        'url',
                        array(
                                'label'       => __( 'لینک (مراحل گذشته)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'description' => __( 'خالی = پیش‌فرض.', 'hamishop-elementor-widgets' ),
                        )
                );
                $this->add_control(
                        'prog_steps',
                        array(
                                'label'       => __( 'مراحل کارت پیشرفت', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $prog->get_controls(),
                                'title_field' => '{{{ name }}}',
                                'default'     => array(
                                        array( 'name' => __( 'بررسی سبد خرید', 'hamishop-elementor-widgets' ) ),
                                        array( 'name' => __( 'آدرس و زمان تحویل', 'hamishop-elementor-widgets' ) ),
                                        array( 'name' => __( 'پرداخت و تایید نهایی', 'hamishop-elementor-widgets' ) ),
                                ),
                        )
                );

                $this->add_control(
                        'm_heading',
                        array( 'label' => __( 'موبایل', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $this->add_control(
                        'm_title',
                        array(
                                'label'   => __( 'عنوان هدر موبایل', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'پرداخت نهایی', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'm_subtitle',
                        array(
                                'label'   => __( 'زیرعنوان هدر موبایل', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'مرحله پایانی سفارش', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'back_url',
                        array(
                                'label'       => __( 'لینک بازگشت موبایل', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::URL,
                                'options'     => array( 'url', 'is_external', 'nofollow' ),
                                'description' => __( 'خالی = آدرس پیش‌فرض سبد خرید ووکامرس.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_box( 'bar', '{{WRAPPER}} .hsw-ptop__bar', array( 'radius' => false ) );
                $this->a_text( 'brand', '{{WRAPPER}} .hsw-ptop__brand-tx', array( 'default' => 18 ) );
                $this->a_color( 'brand_box', __( 'پس‌زمینه جعبه لوگو', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptop__logo', 'background-color' );
                $this->a_text( 'tag', '{{WRAPPER}} .hsw-ptop__tag', array( 'default' => 13 ) );
                $this->a_color( 'nav_c', __( 'لینک مراحل گذشته', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptop__nav-i' );
                $this->a_color( 'nav_on', __( 'مرحله فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptop__nav-i.is-active' );

                $this->a_box( 'prog', '{{WRAPPER}} .hsw-ptop__prog' );
                $this->a_color( 'p_done', __( 'مرحله تکمیل‌شده', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptop__step.is-done .hsw-ptop__dot', 'background-color' );
                $this->a_color( 'p_done_c', __( 'متن مرحله تکمیل‌شده', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptop__step.is-done .hsw-ptop__name' );
                $this->a_color( 'p_on', __( 'پس‌زمینه مرحله فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptop__step.is-active .hsw-ptop__dot', 'background-color' );
                $this->a_color( 'p_on_c', __( 'متن مرحله فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptop__step.is-active .hsw-ptop__name' );
                $this->a_color( 'line_on', __( 'خط طی‌شده', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptop__line b', 'background-color' );

                $this->a_box( 'mbar', '{{WRAPPER}} .hsw-ptop__mbar', array( 'radius' => false ) );
                $this->a_text( 'm_title', '{{WRAPPER}} .hsw-ptop__mtitle', array( 'default' => 18 ) );
                $this->a_color( 'm_active', __( 'متن مرحله فعال موبایل', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptop__mstep.is-active .hsw-ptop__mname' );

                $this->end_controls_section();
        }

        /**
         * Normalized step labels: desktop name (with auto number) + mobile name.
         */
        protected function steps_list() {
                $s     = $this->effective_settings();
                $steps = ( isset( $s['prog_steps'] ) && is_array( $s['prog_steps'] ) ) ? $s['prog_steps'] : array();
                $names = array(
                        array( __( 'بررسی سبد خرید', 'hamishop-elementor-widgets' ), __( 'سبد خرید', 'hamishop-elementor-widgets' ) ),
                        array( __( 'آدرس و زمان تحویل', 'hamishop-elementor-widgets' ), __( 'اطلاعات ارسال', 'hamishop-elementor-widgets' ) ),
                        array( __( 'پرداخت و تایید نهایی', 'hamishop-elementor-widgets' ), __( 'پرداخت نهایی', 'hamishop-elementor-widgets' ) ),
                );
                $urls  = array( '', '' );
                $out   = array();
                $max   = max( count( $steps ), 3 );
                for ( $i = 0; $i < $max; $i++ ) {
                        $row   = isset( $steps[ $i ] ) && is_array( $steps[ $i ] ) ? $steps[ $i ] : array();
                        $dname = isset( $row['name'] ) && '' !== trim( (string) $row['name'] ) ? trim( (string) $row['name'] ) : ( isset( $names[ $i ] ) ? $names[ $i ][0] : sprintf( __( 'مرحله %d', 'hamishop-elementor-widgets' ), $i + 1 ) );
                        $mname = isset( $names[ $i ] ) ? $names[ $i ][1] : $dname;
                        /* mobile labels: only available for the stock 3 steps. */
                        $out[] = array(
                                HSW_Helpers::fa_num( $i + 1 ) . '. ' . $dname,
                                $mname,
                                isset( $row['url'] ) ? (string) $row['url'] : '',
                        );
                }
                return $out;
        }

        protected function render() {
                $s      = $this->effective_settings();
                $steps  = $this->steps_list();
                $active = max( 1, min( count( $steps ), (int) $this->opt( 'active_step', '3' ) ) );
                $sticky = ( 'yes' === ( $s['sticky'] ?? 'yes' ) );

                $brand    = trim( (string) $this->opt( 'brand_text', '' ) );
                /* Legacy saves persisted the old demo brand — treat it as empty. */
                if ( 'فروشگاه آنلاین' === $brand ) {
                        $brand = '';
                }
                $brand_ur = ( ! empty( $s['brand_url']['url'] ) ) ? $s['brand_url']['url'] : $this->shop_url();
                $tag      = $this->opt( 'brand_tag', '' );

                /* Nav steps (desktop). */
                $nav = array();
                if ( isset( $s['nav_steps'] ) && is_array( $s['nav_steps'] ) ) {
                        foreach ( $s['nav_steps'] as $i => $row ) {
                                $name = isset( $row['name'] ) ? $row['name'] : '';
                                if ( '' === trim( (string) $name ) ) {
                                        continue;
                                }
                                $url = isset( $row['url'] ) ? trim( (string) $row['url'] ) : '';
                                if ( '' === $url ) {
                                        $url = ( 0 === $i ) ? $this->cart_url() : ( ( 1 === $i ) ? $this->checkout_url() : '#' );
                                }
                                $nav[] = array( $name, $url );
                        }
                }
                if ( empty( $nav ) ) {
                        $nav = array(
                                array( __( 'سبد خرید', 'hamishop-elementor-widgets' ), $this->cart_url() ),
                                array( __( 'اطلاعات ارسال', 'hamishop-elementor-widgets' ), $this->checkout_url() ),
                                array( __( 'پرداخت نهایی', 'hamishop-elementor-widgets' ), '#' ),
                        );
                }

                $show_ssl     = ( 'yes' === ( $s['show_ssl'] ?? 'yes' ) );
                $ssl_text     = $this->opt( 'ssl_text', __( 'رمزنگاری ۲۵۶ بیتی SSL', 'hamishop-elementor-widgets' ) );
                $show_support = ( 'yes' === ( $s['show_support'] ?? 'yes' ) );
                $support_ph   = trim( (string) $this->opt( 'support_phone', '' ) );
                /* Legacy saves persisted the old demo number — never show it live. */
                if ( in_array( $support_ph, array( '۰۲۱-۹۱۰۰۰۰۰۰', 'پشتیبانی: ۰۲۱-۹۱۰۰۰۰۰۰' ), true ) ) {
                        $support_ph = '';
                }
                if ( '' === $support_ph && $this->is_demo() ) {
                        $support_ph = $this->demo_pay_support_phone();
                }
                $support_url  = ( ! empty( $s['support_url']['url'] ) ) ? $s['support_url']['url'] : '#';

                $m_title    = $this->opt( 'm_title', __( 'پرداخت نهایی', 'hamishop-elementor-widgets' ) );
                /* Pages saved before the 1.11.x title fix keep the old shipping label. */
                if ( 'اطلاعات ارسال' === $m_title ) {
                        $m_title = __( 'پرداخت نهایی', 'hamishop-elementor-widgets' );
                }
                $m_subtitle = $this->opt( 'm_subtitle', '' );
                $back_url   = $this->cart_url();
                if ( ! empty( $s['back_url']['url'] ) ) {
                        $back_url = $s['back_url']['url'];
                }
                $line_pct = ( count( $steps ) > 1 ) ? ( ( $active - 1 ) / ( count( $steps ) - 1 ) ) * 100 : 100;
                ?>
                <section class="<?php echo esc_attr( $this->root_class( 'paytop' ) ); ?>">

                        <!-- =================== DESKTOP =================== -->
                        <header class="hsw-ptop__bar hsw-d<?php echo $sticky ? ' is-sticky' : ''; ?>">
                                <div class="hsw-ptop__shell hsw-ptop__bar-in">
                                        <?php if ( '' !== trim( (string) $brand ) ) : ?>
                                                <div class="hsw-ptop__brand">
                                                        <a class="hsw-ptop__brand-a" href="<?php echo esc_url( $brand_ur ); ?>">
                                                                <span class="hsw-ptop__logo"><?php echo HSW_Helpers::icon( 'shopping_bag' ); ?></span>
                                                                <span class="hsw-ptop__brand-tx"><?php echo esc_html( $brand ); ?></span>
                                                        </a>
                                                        <?php if ( '' !== trim( (string) $tag ) ) : ?>
                                                                <span class="hsw-ptop__sep" aria-hidden="true">|</span>
                                                                <span class="hsw-ptop__tag"><?php echo esc_html( $tag ); ?></span>
                                                        <?php endif; ?>
                                                </div>
                                        <?php endif; ?>

                                        <nav class="hsw-ptop__nav" aria-label="<?php esc_attr_e( 'مراحل خرید', 'hamishop-elementor-widgets' ); ?>">
                                                <?php foreach ( $nav as $i => $row ) : ?>
                                                        <?php if ( 0 < $i ) : ?>
                                                                <span class="hsw-ptop__nav-sep" aria-hidden="true">/</span>
                                                        <?php endif; ?>
                                                        <?php $is_active = ( ( $i + 1 ) === $active ); ?>
                                                        <?php if ( $is_active ) : ?>
                                                                <h1 class="hsw-ptop__nav-i is-active">
                                                                        <span class="hsw-ptop__nav-badge"><?php echo esc_html( HSW_Helpers::fa_num( $i + 1 ) ); ?></span>
                                                                        <?php echo esc_html( $row[0] ); ?>
                                                                </h1>
                                                        <?php else : ?>
                                                                <a class="hsw-ptop__nav-i" href="<?php echo esc_url( $row[1] ); ?>">
                                                                        <?php echo HSW_Helpers::icon( 'check_circle', true ); ?>
                                                                        <?php echo esc_html( $row[0] ); ?>
                                                                </a>
                                                        <?php endif; ?>
                                                <?php endforeach; ?>
                                        </nav>

                                        <div class="hsw-ptop__end">
                                                <?php if ( $show_ssl ) : ?>
                                                        <span class="hsw-ptop__ssl">
                                                                <?php echo HSW_Helpers::icon( 'lock' ); ?>
                                                                <?php echo esc_html( $ssl_text ); ?>
                                                        </span>
                                                <?php endif; ?>
                                                <?php if ( $show_support ) : ?>
                                                        <a class="hsw-ptop__support" href="<?php echo esc_url( $support_url ); ?>" title="<?php esc_attr_e( 'پشتیبانی ۲۴ ساعته', 'hamishop-elementor-widgets' ); ?>">
                                                                <?php echo HSW_Helpers::icon( 'support_agent' ); ?>
                                                                <?php if ( '' !== $support_ph ) : ?>
                                                                        <span><?php echo esc_html( $support_ph ); ?></span>
                                                                <?php endif; ?>
                                                        </a>
                                                <?php endif; ?>
                                        </div>
                                </div>
                        </header>

                        <div class="hsw-ptop__wrap hsw-d">
                                <div class="hsw-ptop__prog">
                                        <div class="hsw-ptop__track">
                                                <span class="hsw-ptop__line" aria-hidden="true"><b style="width:<?php echo esc_attr( $line_pct ); ?>%"></b></span>
                                                <?php foreach ( $steps as $i => $st ) : ?>
                                                        <?php
                                                        $n    = $i + 1;
                                                        $cls  = ( $n === $active ) ? ' is-active' : '';
                                                        $cls .= ( $n < $active ) ? ' is-done' : '';
                                                        ?>
                                                        <?php if ( $n < $active || $n === $active ) : ?>
                                                                <div class="hsw-ptop__step<?php echo esc_attr( $cls ); ?>">
                                                                        <span class="hsw-ptop__dot">
                                                                                <?php if ( $n < $active ) : ?>
                                                                                        <?php echo HSW_Helpers::icon( 'done' ); ?>
                                                                                <?php else : ?>
                                                                                        <?php echo esc_html( HSW_Helpers::fa_num( $n ) ); ?>
                                                                                <?php endif; ?>
                                                                        </span>
                                                                        <span class="hsw-ptop__name"><?php echo esc_html( $st[0] ); ?></span>
                                                                </div>
                                                        <?php else : ?>
                                                                <a class="hsw-ptop__step<?php echo esc_attr( $cls ); ?>" href="<?php echo esc_url( '' !== $st[2] ? $st[2] : $this->checkout_url() ); ?>">
                                                                        <span class="hsw-ptop__dot"><?php echo HSW_Helpers::icon( 'done' ); ?></span>
                                                                        <span class="hsw-ptop__name"><?php echo esc_html( $st[0] ); ?></span>
                                                                </a>
                                                        <?php endif; ?>
                                                <?php endforeach; ?>
                                        </div>
                                </div>
                        </div>

                        <!-- =================== MOBILE =================== -->
                        <header class="hsw-ptop__mbar hsw-m<?php echo $sticky ? ' is-sticky' : ''; ?>">
                                <div class="hsw-ptop__mrow">
                                        <a class="hsw-ptop__back" href="<?php echo esc_url( $back_url ); ?>" aria-label="<?php esc_attr_e( 'بازگشت', 'hamishop-elementor-widgets' ); ?>">
                                                <?php echo HSW_Helpers::icon( 'arrow_forward' ); ?>
                                        </a>
                                        <div class="hsw-ptop__mhead">
                                                <div class="hsw-ptop__mtitle" role="heading" aria-level="1"><?php echo esc_html( $m_title ); ?></div>
                                                <?php if ( '' !== trim( (string) $m_subtitle ) ) : ?>
                                                        <span class="hsw-ptop__msub"><?php echo esc_html( $m_subtitle ); ?></span>
                                                <?php endif; ?>
                                        </div>
                                        <?php if ( $show_support ) : ?>
                                                <a class="hsw-ptop__msupport" href="<?php echo esc_url( $support_url ); ?>" aria-label="<?php esc_attr_e( 'پشتیبانی', 'hamishop-elementor-widgets' ); ?>">
                                                        <?php echo HSW_Helpers::icon( 'support_agent' ); ?>
                                                </a>
                                        <?php endif; ?>
                                </div>
                        </header>

                        <div class="hsw-ptop__mwrap hsw-m">
                                <section class="hsw-ptop__mtrack" aria-label="<?php esc_attr_e( 'مراحل ثبت سفارش', 'hamishop-elementor-widgets' ); ?>">
                                        <span class="hsw-ptop__mline" aria-hidden="true"></span>
                                        <span class="hsw-ptop__mline is-on" aria-hidden="true" style="width:<?php echo esc_attr( ( ( $active - 1 ) / max( 1, count( $steps ) - 1 ) ) * 100 ); ?>%"></span>
                                        <?php foreach ( $steps as $i => $st ) : ?>
                                                <?php
                                                $n    = $i + 1;
                                                $cls  = ( $n === $active ) ? ' is-active' : '';
                                                $cls .= ( $n < $active ) ? ' is-done' : '';
                                                ?>
                                                <div class="hsw-ptop__mstep<?php echo esc_attr( $cls ); ?>">
                                                        <span class="hsw-ptop__mdot">
                                                                <?php if ( $n < $active ) : ?>
                                                                        <?php echo HSW_Helpers::icon( 'check' ); ?>
                                                                <?php else : ?>
                                                                        <?php echo esc_html( HSW_Helpers::fa_num( $n ) ); ?>
                                                                <?php endif; ?>
                                                        </span>
                                                        <span class="hsw-ptop__mname"><?php echo esc_html( $st[1] ); ?></span>
                                                </div>
                                        <?php endforeach; ?>
                                </section>
                        </div>

                </section>
                <?php
        }
}
