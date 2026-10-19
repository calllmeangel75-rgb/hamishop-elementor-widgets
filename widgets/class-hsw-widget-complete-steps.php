<?php
/**
 * Widget: completion next-steps card — «بعد از ثبت سفارش چه اتفاقی می‌افتد؟»
 * (fully configurable repeater; the stock three steps mirror the shop flow).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class HSW_Widget_Complete_Steps extends HSW_Complete_Base {

    public function get_name() {
        return 'hsw-complete-steps';
    }

    public function get_title() {
        return __( 'مراحل بعدی تکمیل خرید (HamiShop)', 'hamishop-elementor-widgets' );
    }

    public function get_icon() {
        return 'eicon-number-field';
    }

    public function get_keywords() {
        return array( 'steps', 'timeline', 'complete', 'مراحل', 'پیگیری', 'تکمیل خرید', 'hamishop' );
    }

    protected function register_controls() {
        /* ------------------------- CONTENT ------------------------- */
        $this->start_controls_section(
            'sec_content',
            array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
        );

        $this->ctrl_visibility();

        $this->add_control(
            'title',
            array(
                'label'   => __( 'عنوان کارت', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'مراحل بعدی سفارش شما', 'hamishop-elementor-widgets' ),
            )
        );

        $rep = new Repeater();
        $rep->add_control(
            'icon',
            array(
                'label'   => __( 'آیکن', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::SELECT,
                'default' => 'confirmation_number',
                'options' => self::complete_icon_choices(),
            )
        );
        $rep->add_control(
            'name',
            array(
                'label'   => __( 'عنوان مرحله', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::TEXT,
                'default' => 'مرحله',
            )
        );
        $rep->add_control(
            'desc',
            array(
                'label' => __( 'توضیح کوتاه', 'hamishop-elementor-widgets' ),
                'type'  => Controls_Manager::TEXTAREA,
                'rows'  => 2,
            )
        );

        $this->add_control(
            'steps',
            array(
                'label'       => __( 'مراحل', 'hamishop-elementor-widgets' ),
                'type'        => Controls_Manager::REPEATER,
                'fields'      => $rep->get_controls(),
                'title_field' => '{{{ name }}}',
                'default'     => array(
                    array(
                        'icon' => 'confirmation_number',
                        'name' => __( 'تأیید و پردازش سفارش', 'hamishop-elementor-widgets' ),
                        'desc' => __( 'سفارش شما ثبت شد و برای بررسی و تأیید نهایی در صف پردازش قرار گرفت.', 'hamishop-elementor-widgets' ),
                    ),
                    array(
                        'icon' => 'inventory_2',
                        'name' => __( 'آماده‌سازی و بسته‌بندی', 'hamishop-elementor-widgets' ),
                        'desc' => __( 'کارشناسان ما کالا را بررسی، کنترل کیفی و بسته‌بندی می‌کنند.', 'hamishop-elementor-widgets' ),
                    ),
                    array(
                        'icon' => 'local_shipping',
                        'name' => __( 'ارسال و تحویل', 'hamishop-elementor-widgets' ),
                        'desc' => __( 'مرسوله با روش ارسال انتخابی به آدرس شما فرستاده می‌شود.', 'hamishop-elementor-widgets' ),
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

        $this->a_box( 'card', '{{WRAPPER}} .hsw-cdstep__card' );
        $this->a_text( 't', '{{WRAPPER}} .hsw-cdstep__t', array( 'default' => 15 ) );
        $this->a_text( 'name', '{{WRAPPER}} .hsw-cdstep__name', array( 'default' => 13 ) );
        $this->a_text( 'desc', '{{WRAPPER}} .hsw-cdstep__desc', array( 'default' => 12 ) );
        $this->a_color( 'dot_bg', __( 'پس‌زمینه دایرهٔ مرحله', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cdstep__dot', 'background-color' );
        $this->a_color( 'dot_c', __( 'رنگ آیکن مرحله', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cdstep__dot .hsw-ic' );

        $this->end_controls_section();
    }

    /**
     * Normalized steps list from the repeater.
     */
    protected function step_rows() {
        $s     = $this->effective_settings();
        $steps = ( isset( $s['steps'] ) && is_array( $s['steps'] ) ) ? $s['steps'] : array();
        $out   = array();
        foreach ( $steps as $row ) {
            $name = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
            if ( '' === $name ) {
                continue;
            }
            $out[] = array(
                'icon' => ( isset( $row['icon'] ) && '' !== (string) $row['icon'] ) ? (string) $row['icon'] : 'check_circle',
                'name' => $name,
                'desc' => isset( $row['desc'] ) ? trim( (string) $row['desc'] ) : '',
            );
        }
        return $out;
    }

    protected function render() {
        /* The next steps are order-independent copy — they show even before an
         * order resolves (never demo data: it is site copy, not order data). */
        $steps = $this->step_rows();
        if ( ! $steps ) {
            return;
        }

        echo '<section class="' . esc_attr( $this->root_class( 'cdstep' ) ) . '">';
        echo '<div class="hsw-cdstep__card">';
        echo '<h3 class="hsw-cdstep__t">' . HSW_Helpers::icon( 'rocket_launch' ) . esc_html( $this->opt( 'title', __( 'مراحل بعدی سفارش شما', 'hamishop-elementor-widgets' ) ) ) . '</h3>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.

        echo '<ol class="hsw-cdstep__list">';
        foreach ( $steps as $row ) {
            echo '<li class="hsw-cdstep__item">';
            echo '<span class="hsw-cdstep__dot">' . HSW_Helpers::icon( $row['icon'], '', true ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
            echo '<div class="hsw-cdstep__b">';
            echo '<span class="hsw-cdstep__name">' . esc_html( $row['name'] ) . '</span>';
            if ( '' !== $row['desc'] ) {
                echo '<span class="hsw-cdstep__desc">' . esc_html( $row['desc'] ) . '</span>';
            }
            echo '</div>';
            echo '</li>';
        }
        echo '</ol>';

        echo '</div>';
        echo '</section>';
    }
}
