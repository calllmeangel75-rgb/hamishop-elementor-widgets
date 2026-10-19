<?php
/**
 * Widget: completion hero — the success banner of the «تکمیل خرید» page
 * (icon + title + subtitle + order meta chips), with a built-in friendly
 * «سفارش یافت نشد» state for invalid/expired links.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Complete_Hero extends HSW_Complete_Base {

    public function get_name() {
        return 'hsw-complete-hero';
    }

    public function get_title() {
        return __( 'سربرگ تکمیل خرید (HamiShop)', 'hamishop-elementor-widgets' );
    }

    public function get_icon() {
        return 'eicon-check-circle-o';
    }

    public function get_keywords() {
        return array( 'complete', 'thank you', 'order received', 'تکمیل خرید', 'سفارش ثبت شد', 'hamishop' );
    }

    protected function register_controls() {
        /* ------------------------- CONTENT ------------------------- */
        $this->start_controls_section(
            'sec_content',
            array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
        );

        $this->ctrl_visibility();

        $this->add_control(
            'show_icon',
            array(
                'label'   => __( 'نمایش آیکن موفقیت', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::SWITCHER,
                'default' => 'yes',
            )
        );

        $this->add_control(
            'icon',
            array(
                'label'     => __( 'آیکن', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::SELECT,
                'default'   => 'check_circle',
                'options'   => self::complete_icon_choices(),
                'condition' => array( 'show_icon' => 'yes' ),
            )
        );

        $this->add_control(
            'title',
            array(
                'label'   => __( 'عنوان', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'سفارش شما با موفقیت ثبت شد', 'hamishop-elementor-widgets' ),
            )
        );

        $this->add_control(
            'subtitle',
            array(
                'label'   => __( 'زیرعنوان', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::TEXTAREA,
                'rows'    => 2,
                'default' => __( 'از خرید شما سپاسگزاریم؛ جزئیات سفارش ثبت شد و می‌توانید همین‌جا آن را ببینید.', 'hamishop-elementor-widgets' ),
            )
        );

        $this->add_control(
            'meta_heading',
            array(
                'label'     => __( 'چیپ‌های اطلاعات سفارش', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $this->add_control(
            'show_meta',
            array(
                'label'   => __( 'نمایش نوار اطلاعات', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::SWITCHER,
                'default' => 'yes',
            )
        );

        $this->add_control(
            'fa_digits',
            array(
                'label'     => __( 'شمارهٔ سفارش با ارقام فارسی', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::SWITCHER,
                'default'   => 'yes',
                'condition' => array( 'show_meta' => 'yes' ),
            )
        );

        $this->add_control(
            'show_number',
            array(
                'label'     => __( 'شمارهٔ سفارش', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::SWITCHER,
                'default'   => 'yes',
                'condition' => array( 'show_meta' => 'yes' ),
            )
        );

        $this->add_control(
            'chip_order_label',
            array(
                'label'     => __( 'برچسب شمارهٔ سفارش', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::TEXT,
                'default'   => __( 'شمارهٔ سفارش', 'hamishop-elementor-widgets' ),
                'condition' => array( 'show_meta' => 'yes', 'show_number' => 'yes' ),
            )
        );

        $this->add_control(
            'show_date',
            array(
                'label'     => __( 'تاریخ ثبت', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::SWITCHER,
                'default'   => 'yes',
                'condition' => array( 'show_meta' => 'yes' ),
            )
        );

        $this->add_control(
            'chip_date_label',
            array(
                'label'     => __( 'برچسب تاریخ', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::TEXT,
                'default'   => __( 'تاریخ ثبت', 'hamishop-elementor-widgets' ),
                'condition' => array( 'show_meta' => 'yes', 'show_date' => 'yes' ),
            )
        );

        $this->add_control(
            'show_status',
            array(
                'label'     => __( 'وضعیت سفارش', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::SWITCHER,
                'default'   => 'yes',
                'condition' => array( 'show_meta' => 'yes' ),
            )
        );

        $this->add_control(
            'chip_status_label',
            array(
                'label'     => __( 'برچسب وضعیت', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::TEXT,
                'default'   => __( 'وضعیت سفارش', 'hamishop-elementor-widgets' ),
                'condition' => array( 'show_meta' => 'yes', 'show_status' => 'yes' ),
            )
        );

        /* ------------------------- NOT FOUND ------------------------- */
        $this->add_control(
            'nf_heading',
            array(
                'label'     => __( 'حالت «سفارش یافت نشد»', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
                'description' => __( 'وقتی لینک ناقص یا منقضی باشد (مهمانِ بدون کلید امن یا صفحهٔ بدون سفارش).', 'hamishop-elementor-widgets' ),
            )
        );

        $this->add_control(
            'nf_title',
            array(
                'label'   => __( 'عنوان', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'سفارش یافت نشد', 'hamishop-elementor-widgets' ),
            )
        );

        $this->add_control(
            'nf_text',
            array(
                'label'   => __( 'متن', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::TEXTAREA,
                'rows'    => 2,
                'default' => __( 'لینک مشاهدهٔ این سفارش ناقص یا منقضی شده است؛ برای دیدن سفارش‌های خود وارد حساب کاربری شوید.', 'hamishop-elementor-widgets' ),
            )
        );

        $this->add_control(
            'nf_btn_label',
            array(
                'label'   => __( 'دکمهٔ اصلی (حساب کاربری)', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'ورود / مشاهدهٔ سفارش‌ها', 'hamishop-elementor-widgets' ),
            )
        );

        $this->add_control(
            'nf_btn_url',
            array(
                'label'       => __( 'لینک دکمهٔ اصلی', 'hamishop-elementor-widgets' ),
                'type'        => Controls_Manager::URL,
                'options'     => array( 'url', 'is_external', 'nofollow' ),
                'description' => __( 'خالی = صفحهٔ حساب کاربری ووکامرس.', 'hamishop-elementor-widgets' ),
            )
        );

        $this->add_control(
            'nf_shop_label',
            array(
                'label'   => __( 'دکمهٔ دوم (فروشگاه)', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'ادامه خرید', 'hamishop-elementor-widgets' ),
            )
        );

        $this->end_controls_section();

        /* ------------------------- STYLE ------------------------- */
        $this->start_controls_section(
            'sec_style',
            array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
        );

        $this->a_box( 'card', '{{WRAPPER}} .hsw-cdhero__card' );
        $this->a_color( 'ring_bg', __( 'پس‌زمینه حلقهٔ آیکن', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cdhero__ring', 'background-color' );
        $this->a_color( 'ring_c', __( 'رنگ آیکن موفقیت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cdhero__ring .hsw-ic' );
        $this->a_color( 'nf_c', __( 'رنگ آیکن «یافت نشد»', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cdhero__ring.is-nf .hsw-ic' );
        $this->a_text( 't', '{{WRAPPER}} .hsw-cdhero__t', array( 'default' => 20, 'size' => array( 'min' => 12, 'max' => 34 ) ) );
        $this->a_text( 's', '{{WRAPPER}} .hsw-cdhero__s', array( 'default' => 14, 'size' => array( 'min' => 10, 'max' => 20 ) ) );
        $this->a_text( 'chip', '{{WRAPPER}} .hsw-cdhero__chip', array( 'default' => 12 ) );
        $this->a_box( 'chipbox', '{{WRAPPER}} .hsw-cdhero__chip' );
        $this->a_color( 'nfbtn_bg', __( 'دکمهٔ «یافت نشد» — پس‌زمینه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cdhero__btn.is-primary', 'background-color' );
        $this->a_color( 'nfbtn_c', __( 'دکمهٔ «یافت نشد» — متن', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cdhero__btn.is-primary' );
        $this->a_color( 'nfbtn2_bc', __( 'دکمهٔ دوم — حاشیه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cdhero__btn.is-ghost', 'border-color' );
        $this->a_color( 'nfbtn2_c', __( 'دکمهٔ دوم — متن', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cdhero__btn.is-ghost' );

        $this->end_controls_section();
    }

    protected function render() {
        $data = $this->completion_data();

        echo '<section class="' . esc_attr( $this->root_class( 'cdhero' ) ) . '">';

        if ( null === $data ) {
            $this->render_not_found();
        } else {
            $this->render_success( $data );
        }

        echo '</section>';
    }

    /**
     * Success banner with the live order meta chips.
     *
     * @param array $data Completion payload.
     */
    protected function render_success( $data ) {
        $s = $this->effective_settings();

        echo '<div class="hsw-cdhero__card">';

        if ( 'yes' === ( $s['show_icon'] ?? 'yes' ) ) {
            $icon = (string) $this->opt( 'icon', 'check_circle' );
            echo '<span class="hsw-cdhero__ring">' . HSW_Helpers::icon( $icon, 'is-fill' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
        }

        echo '<h2 class="hsw-cdhero__t">' . esc_html( $this->opt( 'title', __( 'سفارش شما با موفقیت ثبت شد', 'hamishop-elementor-widgets' ) ) ) . '</h2>';
        echo '<p class="hsw-cdhero__s">' . esc_html( $this->opt( 'subtitle', '' ) ) . '</p>';

        if ( 'yes' !== ( $s['show_meta'] ?? 'yes' ) ) {
            echo '</div>';
            return;
        }

        $fa   = ( 'yes' === ( $s['fa_digits'] ?? 'yes' ) );
        $meta = array();

        if ( 'yes' === ( $s['show_number'] ?? 'yes' ) ) {
            $meta[] = array(
                'icon'  => 'receipt_long',
                'label' => $this->opt( 'chip_order_label', __( 'شمارهٔ سفارش', 'hamishop-elementor-widgets' ) ),
                'value' => $this->number_display( $data, $fa ),
                'cls'   => '',
            );
        }
        if ( 'yes' === ( $s['show_date'] ?? 'yes' ) && '' !== trim( (string) $data['date'] ) ) {
            $meta[] = array(
                'icon'  => 'schedule',
                'label' => $this->opt( 'chip_date_label', __( 'تاریخ ثبت', 'hamishop-elementor-widgets' ) ),
                'value' => (string) $data['date'],
                'cls'   => '',
            );
        }
        if ( 'yes' === ( $s['show_status'] ?? 'yes' ) && '' !== trim( (string) $data['status']['label'] ) ) {
            $meta[] = array(
                'icon'  => (string) $data['status']['icon'],
                'label' => $this->opt( 'chip_status_label', __( 'وضعیت سفارش', 'hamishop-elementor-widgets' ) ),
                'value' => (string) $data['status']['label'],
                'cls'   => ' is-status is-' . sanitize_html_class( (string) $data['status']['key'] ),
            );
        }

        if ( $meta ) {
            echo '<div class="hsw-cdhero__meta">';
            foreach ( $meta as $chip ) {
                echo '<span class="hsw-cdhero__chip' . esc_attr( $chip['cls'] ) . '">';
                echo HSW_Helpers::icon( $chip['icon'], '', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
                echo '<b>' . esc_html( $chip['label'] ) . '</b>';
                echo '<span class="hsw-cdhero__chip-v">' . esc_html( $chip['value'] ) . '</span>';
                echo '</span>';
            }
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Invalid / expired / no-order state.
     */
    protected function render_not_found() {
        $s = $this->effective_settings();

        $account = $this->account_url();
        if ( ! empty( $s['nf_btn_url']['url'] ) ) {
            $account = (string) $s['nf_btn_url']['url'];
        }
        $external = ( ! empty( $s['nf_btn_url']['is_external'] ) ) ? ' target="_blank" rel="noopener"' : '';

        echo '<div class="hsw-cdhero__card is-nf">';
        echo '<span class="hsw-cdhero__ring is-nf">' . HSW_Helpers::icon( 'help', 'is-fill' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
        echo '<h2 class="hsw-cdhero__t">' . esc_html( $this->opt( 'nf_title', __( 'سفارش یافت نشد', 'hamishop-elementor-widgets' ) ) ) . '</h2>';
        echo '<p class="hsw-cdhero__s">' . esc_html( $this->opt( 'nf_text', '' ) ) . '</p>';
        echo '<div class="hsw-cdhero__acts">';
        echo '<a class="hsw-cdhero__btn is-primary" href="' . esc_url( $account ) . '"' . $external . '>' . esc_html( $this->opt( 'nf_btn_label', __( 'ورود / مشاهدهٔ سفارش‌ها', 'hamishop-elementor-widgets' ) ) ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
        echo '<a class="hsw-cdhero__btn is-ghost" href="' . esc_url( $this->shop_url() ) . '">' . esc_html( $this->opt( 'nf_shop_label', __( 'ادامه خرید', 'hamishop-elementor-widgets' ) ) ) . '</a>';
        echo '</div>';
        echo '</div>';
    }
}
