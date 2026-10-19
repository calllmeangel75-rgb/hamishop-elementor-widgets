<?php
/**
 * Widget: completion delivery card — recipient, phone, address, postcode and
 * the shipping method, read from the real WooCommerce order (shipping first,
 * billing fallback — exactly like the checkout suite).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Complete_Delivery extends HSW_Complete_Base {

    public function get_name() {
        return 'hsw-complete-delivery';
    }

    public function get_title() {
        return __( 'اطلاعات تحویل تکمیل خرید (HamiShop)', 'hamishop-elementor-widgets' );
    }

    public function get_icon() {
        return 'eicon-map-pin';
    }

    public function get_keywords() {
        return array( 'delivery', 'address', 'shipping', 'complete', 'تحویل', 'آدرس', 'تکمیل خرید', 'hamishop' );
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
                'default' => __( 'اطلاعات تحویل', 'hamishop-elementor-widgets' ),
            )
        );

        $this->add_control(
            'show_name',
            array(
                'label'     => __( 'نام تحویل‌گیرنده', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::SWITCHER,
                'default'   => 'yes',
                'separator' => 'before',
            )
        );

        $this->add_control(
            'name_label',
            array(
                'label'     => __( 'برچسب نام', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::TEXT,
                'default'   => __( 'تحویل‌گیرنده', 'hamishop-elementor-widgets' ),
                'condition' => array( 'show_name' => 'yes' ),
            )
        );

        $this->add_control(
            'show_phone',
            array(
                'label'   => __( 'شمارهٔ تماس', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::SWITCHER,
                'default' => 'yes',
            )
        );

        $this->add_control(
            'phone_label',
            array(
                'label'     => __( 'برچسب تلفن', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::TEXT,
                'default'   => __( 'شمارهٔ تماس', 'hamishop-elementor-widgets' ),
                'condition' => array( 'show_phone' => 'yes' ),
            )
        );

        $this->add_control(
            'show_address',
            array(
                'label'   => __( 'نشانی', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::SWITCHER,
                'default' => 'yes',
            )
        );

        $this->add_control(
            'addr_label',
            array(
                'label'     => __( 'برچسب نشانی', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::TEXT,
                'default'   => __( 'نشانی تحویل', 'hamishop-elementor-widgets' ),
                'condition' => array( 'show_address' => 'yes' ),
            )
        );

        $this->add_control(
            'show_postcode',
            array(
                'label'   => __( 'کد پستی', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::SWITCHER,
                'default' => 'yes',
            )
        );

        $this->add_control(
            'pc_label',
            array(
                'label'     => __( 'برچسب کد پستی', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::TEXT,
                'default'   => __( 'کد پستی', 'hamishop-elementor-widgets' ),
                'condition' => array( 'show_postcode' => 'yes' ),
            )
        );

        $this->add_control(
            'show_method',
            array(
                'label'   => __( 'روش ارسال', 'hamishop-elementor-widgets' ),
                'type'    => Controls_Manager::SWITCHER,
                'default' => 'yes',
            )
        );

        $this->add_control(
            'method_label',
            array(
                'label'     => __( 'برچسب روش ارسال', 'hamishop-elementor-widgets' ),
                'type'      => Controls_Manager::TEXT,
                'default'   => __( 'روش ارسال', 'hamishop-elementor-widgets' ),
                'condition' => array( 'show_method' => 'yes' ),
            )
        );

        $this->end_controls_section();

        /* ------------------------- STYLE ------------------------- */
        $this->start_controls_section(
            'sec_style',
            array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
        );

        $this->a_box( 'card', '{{WRAPPER}} .hsw-cddel__card' );
        $this->a_text( 't', '{{WRAPPER}} .hsw-cddel__t', array( 'default' => 15 ) );
        $this->a_text( 'lbl', '{{WRAPPER}} .hsw-cddel__lbl', array( 'default' => 11 ) );
        $this->a_text( 'val', '{{WRAPPER}} .hsw-cddel__v', array( 'default' => 13 ) );
        $this->a_color( 'ic_c', __( 'رنگ آیکن‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cddel__i .hsw-ic' );
        $this->a_box( 'ic', '{{WRAPPER}} .hsw-cddel__i' );

        $this->end_controls_section();
    }

    protected function render() {
        $data = $this->completion_data();
        if ( null === $data ) {
            return;
        }

        $s     = $this->effective_settings();
        $addr  = $data['address'];
        $rows  = array();

        if ( 'yes' === ( $s['show_name'] ?? 'yes' ) && '' !== trim( (string) $addr['name'] ) ) {
            $rows[] = array( 'person', $this->opt( 'name_label', __( 'تحویل‌گیرنده', 'hamishop-elementor-widgets' ) ), (string) $addr['name'] );
        }
        if ( 'yes' === ( $s['show_phone'] ?? 'yes' ) && '' !== trim( (string) $addr['phone'] ) ) {
            $rows[] = array( 'phone_iphone', $this->opt( 'phone_label', __( 'شمارهٔ تماس', 'hamishop-elementor-widgets' ) ), HSW_Helpers::fa_num( (string) $addr['phone'] ) );
        }
        if ( 'yes' === ( $s['show_address'] ?? 'yes' ) && '' !== trim( (string) $addr['line'] ) ) {
            $rows[] = array( 'location_on', $this->opt( 'addr_label', __( 'نشانی تحویل', 'hamishop-elementor-widgets' ) ), (string) $addr['line'] );
        }
        if ( 'yes' === ( $s['show_postcode'] ?? 'yes' ) && '' !== trim( (string) $addr['postcode'] ) ) {
            $rows[] = array( 'pin_drop', $this->opt( 'pc_label', __( 'کد پستی', 'hamishop-elementor-widgets' ) ), HSW_Helpers::fa_num( (string) $addr['postcode'] ) );
        }
        if ( 'yes' === ( $s['show_method'] ?? 'yes' ) && '' !== trim( (string) $data['shipping']['method'] ) ) {
            $rows[] = array( 'local_shipping', $this->opt( 'method_label', __( 'روش ارسال', 'hamishop-elementor-widgets' ) ), (string) $data['shipping']['method'] );
        }

        if ( ! $rows ) {
            return;
        }

        echo '<section class="' . esc_attr( $this->root_class( 'cddel' ) ) . '">';
        echo '<div class="hsw-cddel__card">';
        echo '<h3 class="hsw-cddel__t">' . HSW_Helpers::icon( 'local_shipping' ) . esc_html( $this->opt( 'title', __( 'اطلاعات تحویل', 'hamishop-elementor-widgets' ) ) ) . '</h3>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
        echo '<div class="hsw-cddel__rows">';

        foreach ( $rows as $row ) {
            echo '<div class="hsw-cddel__row">';
            echo '<span class="hsw-cddel__i">' . HSW_Helpers::icon( $row[0] ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
            echo '<div class="hsw-cddel__b">';
            echo '<span class="hsw-cddel__lbl">' . esc_html( $row[1] ) . '</span>';
            echo '<span class="hsw-cddel__v">' . esc_html( $row[2] ) . '</span>';
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
        echo '</section>';
    }
}
