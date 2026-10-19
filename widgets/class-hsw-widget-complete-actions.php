<?php
/**
 * Widget: completion actions — the closing CTAs of the thank-you page:
 * view-the-order / pay-again (failed & pending orders), continue shopping,
 * the confirmation e-mail note and an optional support box.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Complete_Actions extends HSW_Complete_Base {

    public function get_name() {
        return 'hsw-complete-actions';
    }

    public function get_title() {
        return __( 'دکمه‌های تکمیل خرید (HamiShop)', 'hamishop-elementor-widgets' );
    }

    public function get_icon() {
        return 'eicon-button';
    }

    public function get_keywords() {
        return array( 'actions', 'buttons', 'cta', 'complete', 'دکمه', 'تکمیل خرید', 'hamishop' );
    }

    protected function register_controls() {
        /* ------------------------- CONTENT ------------------------- */
        $this->start_controls_section(
            'sec_content',
            array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
        );

        $this->ctrl_visibility();

        $this->add_control(
            'p_label',
            array(
                'label'   => __( 'دکمهٔ اصلی', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'مشاهدهٔ سفارش در حساب من', 'hamishop-elementor-widgets' ),
            )
        );

        $this->add_control(
            'p_icon',
            array(
                'label'   => __( 'آیکن دکمهٔ اصلی', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::SELECT,
                'default' => 'receipt_long',
                'options' => self::complete_icon_choices(),
            )
        );

        $this->add_control(
            'p_url',
            array(
                'label'       => __( 'لینک دکمهٔ اصلی', 'hamishop-elementor-widgets' ),
                'type'        => Controls_Manager::URL,
                'options'     => array( 'url', 'is_external', 'nofollow' ),
                'description' => __( 'خالی = هوشمند: برای سفارش پرداخت‌نشده «پرداخت سفارش»، برای کاربر واردشده «مشاهدهٔ سفارش» و در غیر این صورت «حساب کاربری».', 'hamishop-elementor-widgets' ),
            )
        );

        $this->add_control(
            'auto_pay',
            array(
                'label'       => __( 'جایگزینی خودکار برای سفارش پرداخت‌نشده', 'hamishop-elementor-widgets' ),
                'type'        => Controls_Manager::SWITCHER,
                'default'     => 'yes',
                'description' => __( 'اگر سفارش هنوز پرداخت نشده باشد (پرداخت ناموفق یا کارت‌به‌کارت)، دکمهٔ اصلی به «پرداخت سفارش» تبدیل می‌شود.', 'hamishop-elementor-widgets' ),
            )
        );

        $this->add_control(
            'pay_label',
            array(
                'label'     => __( 'برچسب حالت پرداخت سفارش', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::TEXT,
                'default'   => __( 'پرداخت سفارش', 'hamishop-elementor-widgets' ),
                'condition' => array( 'auto_pay' => 'yes' ),
            )
        );

        $this->add_control(
            's_label',
            array(
                'label'     => __( 'دکمهٔ دوم', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::TEXT,
                'default'   => __( 'ادامه خرید', 'hamishop-elementor-widgets' ),
                'separator' => 'before',
            )
        );

        $this->add_control(
            's_icon',
            array(
                'label'   => __( 'آیکن دکمهٔ دوم', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::SELECT,
                'default' => 'home',
                'options' => self::complete_icon_choices(),
            )
        );

        $this->add_control(
            's_url',
            array(
                'label'       => __( 'لینک دکمهٔ دوم', 'hamishop-elementor-widgets' ),
                'type'        => Controls_Manager::URL,
                'options'     => array( 'url', 'is_external', 'nofollow' ),
                'description' => __( 'خالی = صفحهٔ فروشگاه.', 'hamishop-elementor-widgets' ),
            )
        );

        $this->add_control(
            'show_email',
            array(
                'label'     => __( 'پیام ایمیل تأییدیه', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::SWITCHER,
                'default'   => 'yes',
                'separator' => 'before',
            )
        );

        $this->add_control(
            'email_tpl',
            array(
                'label'       => __( 'متن پیام ایمیل', 'hamishop-elementor-widgets' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => __( 'تأییدیهٔ سفارش به ایمیل {email} ارسال شد.', 'hamishop-elementor-widgets' ),
                'description' => __( '{email} = ایمیل سفارش (به‌صورت ناشناس نمایش داده می‌شود).', 'hamishop-elementor-widgets' ),
                'condition'   => array( 'show_email' => 'yes' ),
            )
        );

        $this->add_control(
            'show_support',
            array(
                'label'   => __( 'جعبهٔ پشتیبانی', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::SWITCHER,
                'default' => 'yes',
            )
        );

        $this->add_control(
            'sup_title',
            array(
                'label'     => __( 'عنوان پشتیبانی', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::TEXT,
                'default'   => __( 'پشتیبان شما هستیم', 'hamishop-elementor-widgets' ),
                'condition' => array( 'show_support' => 'yes' ),
            )
        );

        $this->add_control(
            'sup_text',
            array(
                'label'     => __( 'متن پشتیبانی', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::TEXTAREA,
                'rows'      => 2,
                'default'   => __( 'اگر سؤالی دربارهٔ سفارش دارید، تیم پشتیبانی پاسخگوی شماست.', 'hamishop-elementor-widgets' ),
                'condition' => array( 'show_support' => 'yes' ),
            )
        );

        $this->add_control(
            'sup_phone',
            array(
                'label'       => __( 'شمارهٔ پشتیبانی', 'hamishop-elementor-widgets' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => '',
                'description' => __( 'خالی = بدون شماره.', 'hamishop-elementor-widgets' ),
                'condition'   => array( 'show_support' => 'yes' ),
            )
        );

        $this->add_control(
            'sup_url',
            array(
                'label'     => __( 'لینک پشتیبانی (تماس/تیکت)', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::URL,
                'options'   => array( 'url', 'is_external', 'nofollow' ),
                'condition' => array( 'show_support' => 'yes' ),
            )
        );

        $this->end_controls_section();

        /* ------------------------- STYLE ------------------------- */
        $this->start_controls_section(
            'sec_style',
            array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
        );

        $this->a_box( 'card', '{{WRAPPER}} .hsw-cdact__card' );
        $this->a_color( 'p_bg', __( 'دکمهٔ اصلی — پس‌زمینه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cdact__btn.is-primary', 'background-color' );
        $this->a_color( 'p_c', __( 'دکمهٔ اصلی — متن', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cdact__btn.is-primary' );
        $this->a_color( 's_bc', __( 'دکمهٔ دوم — حاشیه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cdact__btn.is-ghost', 'border-color' );
        $this->a_color( 's_c', __( 'دکمهٔ دوم — متن', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cdact__btn.is-ghost' );
        $this->a_text( 'mail', '{{WRAPPER}} .hsw-cdact__mail', array( 'default' => 12 ) );
        $this->a_box( 'sup', '{{WRAPPER}} .hsw-cdact__sup' );

        $this->end_controls_section();
    }

    /**
     * Primary CTA resolved for the current state (manual URL wins).
     *
     * @param array|null $data Completion payload (null = no order).
     * @return array { url, external, label }
     */
    protected function primary_action( $data ) {
        $s        = $this->effective_settings();
        $url      = ( ! empty( $s['p_url']['url'] ) ) ? (string) $s['p_url']['url'] : '';
        $external = ! empty( $s['p_url']['is_external'] );
        $label    = $this->opt( 'p_label', __( 'مشاهدهٔ سفارش در حساب من', 'hamishop-elementor-widgets' ) );

        if ( '' === $url && is_array( $data ) ) {
            $needs_pay = ! empty( $data['info']['pay_url'] );
            if ( $needs_pay && 'yes' === ( $s['auto_pay'] ?? 'yes' ) ) {
                $url   = (string) $data['info']['pay_url'];
                $label = $this->opt( 'pay_label', __( 'پرداخت سفارش', 'hamishop-elementor-widgets' ) );
            } elseif ( is_user_logged_in() && ! empty( $data['info']['view_url'] ) ) {
                $url = (string) $data['info']['view_url'];
            }
        }
        if ( '' === $url ) {
            $url = $this->account_url();
        }

        return array( 'url' => $url, 'external' => $external, 'label' => $label );
    }

    /**
     * Privacy-friendly e-mail display (a***@example.com).
     */
    protected function mask_email( $email ) {
        $email = trim( (string) $email );
        if ( '' === $email || false === strpos( $email, '@' ) ) {
            return '';
        }
        list( $user, $domain ) = explode( '@', $email, 2 );
        $head = ( function_exists( 'mb_substr' ) ) ? mb_substr( $user, 0, 1 ) : substr( $user, 0, 1 );
        return $head . '***@' . $domain;
    }

    protected function render() {
        $data = $this->completion_data();
        $s    = $this->effective_settings();

        $primary   = $this->primary_action( $data );
        $secondary = array(
            'url'      => ( ! empty( $s['s_url']['url'] ) ) ? (string) $s['s_url']['url'] : $this->shop_url(),
            'external' => ! empty( $s['s_url']['is_external'] ),
            'label'    => $this->opt( 's_label', __( 'ادامه خرید', 'hamishop-elementor-widgets' ) ),
        );

        echo '<section class="' . esc_attr( $this->root_class( 'cdact' ) ) . '">';
        echo '<div class="hsw-cdact__card">';

        echo '<div class="hsw-cdact__btns">';
        echo '<a class="hsw-cdact__btn is-primary" href="' . esc_url( $primary['url'] ) . '"' . ( $primary['external'] ? ' target="_blank" rel="noopener"' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped here.
        echo HSW_Helpers::icon( (string) $this->opt( 'p_icon', 'receipt_long' ) ) . esc_html( $primary['label'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
        echo '</a>';
        echo '<a class="hsw-cdact__btn is-ghost" href="' . esc_url( $secondary['url'] ) . '"' . ( $secondary['external'] ? ' target="_blank" rel="noopener"' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped here.
        echo HSW_Helpers::icon( (string) $this->opt( 's_icon', 'home' ) ) . esc_html( $secondary['label'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
        echo '</a>';
        echo '</div>';

        $email = ( is_array( $data ) ) ? $this->mask_email( $data['address']['email'] ) : '';
        if ( 'yes' === ( $s['show_email'] ?? 'yes' ) && '' !== $email ) {
            $tpl = $this->opt( 'email_tpl', '' );
            echo '<p class="hsw-cdact__mail">' . HSW_Helpers::icon( 'mail' ) . esc_html( str_replace( '{email}', $email, $tpl ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
        }

        if ( 'yes' === ( $s['show_support'] ?? 'yes' ) ) {
            $phone     = trim( (string) $this->opt( 'sup_phone', '' ) );
            $sup_url   = ( ! empty( $s['sup_url']['url'] ) ) ? (string) $s['sup_url']['url'] : '';
            $sup_ext   = ! empty( $s['sup_url']['is_external'] );
            echo '<div class="hsw-cdact__sup">';
            echo '<span class="hsw-cdact__sup-i">' . HSW_Helpers::icon( 'support_agent', '', true ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
            echo '<div class="hsw-cdact__sup-b">';
            echo '<b class="hsw-cdact__sup-t">' . esc_html( $this->opt( 'sup_title', __( 'پشتیبان شما هستیم', 'hamishop-elementor-widgets' ) ) ) . '</b>';
            echo '<span class="hsw-cdact__sup-s">' . esc_html( $this->opt( 'sup_text', '' ) ) . '</span>';
            echo '</div>';
            if ( '' !== $phone ) {
                $tel = str_replace(
                    array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ),
                    array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
                    $phone
                );
                $tel = preg_replace( '/[^0-9+]/', '', $tel );
                echo '<a class="hsw-cdact__sup-ph" href="tel:' . esc_attr( (string) $tel ) . '" dir="ltr">' . esc_html( $phone ) . '</a>';
            } elseif ( '' !== $sup_url ) {
                echo '<a class="hsw-cdact__sup-ph is-link" href="' . esc_url( $sup_url ) . '"' . ( $sup_ext ? ' target="_blank" rel="noopener"' : '' ) . '>' . esc_html__( 'تماس با پشتیبانی', 'hamishop-elementor-widgets' ) . '</a>';
            }
            echo '</div>';
        }

        echo '</div>';
        echo '</section>';
    }
}
