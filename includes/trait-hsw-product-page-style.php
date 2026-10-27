<?php
/**
 * Shared helpers for the composite product-page Style tab.
 *
 * The composite already owns layout and one “unified” colour/typography
 * section. The section traits add the per-part controls (gallery, title,
 * price, stock, description, features, variants, purchase, messages, tabs,
 * related). Every control is empty by default: the product stylesheets read
 * the admin palette through CSS variables, so an empty control means “use the
 * palette”. A filled control targets `{{WRAPPER}} …` and wins because it is
 * applied on the widget wrapper.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;

trait HSW_Product_Page_Style {

	/**
	 * Register all detailed sections. Called from the composite widget after
	 * the unified section, so a detailed control wins when both are filled.
	 */
	protected function register_product_page_detail_style() {
		$this->pps_register_gallery_section();
		$this->pps_register_title_section();
		$this->pps_register_stock_section();
		$this->pps_register_price_section();
		$this->pps_register_info_section();
		$this->pps_register_features_section();
		$this->pps_register_variants_section();
		$this->pps_register_purchase_section();
		$this->pps_register_messages_section();
		$this->pps_register_tabs_section();
		$this->pps_register_related_section();
		$this->pps_register_trust_section();
	}

	/**
	 * Open a Style section. The condition hides the whole section when the
	 * matching content part is disabled.
	 *
	 * @param string $id        Section id.
	 * @param string $label     Section label.
	 * @param array  $condition Elementor condition, e.g. array( 'pga_on' => 'yes' ).
	 */
	private function pps_open( $id, $label, $condition = array() ) {
		$args = array(
			'label' => $label,
			'tab'   => Controls_Manager::TAB_STYLE,
		);
		if ( ! empty( $condition ) ) {
			$args['condition'] = $condition;
		}
		$this->start_controls_section( $id, $args );
	}

	/** Close the current Style section. */
	private function pps_close() {
		$this->end_controls_section();
	}

	/**
	 * Explanatory note inside a section.
	 *
	 * @param string $id   Control id.
	 * @param string $text Already-translated text.
	 */
	private function pps_note( $id, $text ) {
		$this->add_control(
			$id,
			array(
				'type'      => Controls_Manager::RAW_HTML,
				'raw'       => '<div style="font-size:11px;line-height:1.8;color:#646970">' . esc_html( $text ) . '</div>',
				'separator' => 'after',
			)
		);
	}

	/**
	 * Sub-heading inside a section.
	 *
	 * @param string $id        Unique control id.
	 * @param string $label     Heading label.
	 * @param string $separator Elementor separator.
	 */
	private function pps_heading( $id, $label, $separator = 'before' ) {
		$this->add_control(
			$id,
			array(
				'label'     => $label,
				'type'      => Controls_Manager::HEADING,
				'separator' => $separator,
			)
		);
	}

	/**
	 * Colour control.
	 *
	 * @param string $id        Unique control id.
	 * @param string $label     Label.
	 * @param string $selector  CSS selector (one or comma-separated).
	 * @param string $property  CSS property.
	 * @param bool   $important Whether to append !important.
	 */
	private function pps_color( $id, $label, $selector, $property = 'color', $important = false ) {
		$this->add_control(
			$id,
			array(
				'label'     => $label,
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					$selector => $property . ': {{VALUE}}' . ( $important ? ' !important' : '' ) . ';',
				),
			)
		);
	}

	/**
	 * Colour control that writes a CSS custom property instead of a property.
	 *
	 * @param string $id       Unique control id.
	 * @param string $label    Label.
	 * @param string $selector CSS selector.
	 * @param string $var      Custom property name, e.g. --hsw-cart-bg.
	 */
	private function pps_var_color( $id, $label, $selector, $var ) {
		$this->add_control(
			$id,
			array(
				'label'     => $label,
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					$selector => $var . ': {{VALUE}};',
				),
			)
		);
	}

	/**
	 * Responsive slider with an arbitrary declaration template.
	 *
	 * @param string       $id          Unique control id.
	 * @param string       $label       Label.
	 * @param string|array $selectors   Selector or list of selectors.
	 * @param string       $declaration Declaration, e.g. 'gap: {{SIZE}}{{UNIT}};'.
	 * @param array        $args        units, range, default, responsive.
	 */
	private function pps_slider( $id, $label, $selectors, $declaration, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'units'      => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 100 ) ),
				'default'    => null,
				'responsive' => true,
			)
		);

		$control = array(
			'label'      => $label,
			'type'       => Controls_Manager::SLIDER,
			'size_units' => $args['units'],
			'range'      => $args['range'],
			'selectors'  => array_fill_keys( (array) $selectors, $declaration ),
		);
		if ( null !== $args['default'] ) {
			$control['default'] = $args['default'];
		}

		if ( $args['responsive'] ) {
			$this->add_responsive_control( $id, $control );
		} else {
			$this->add_control( $id, $control );
		}
	}

	/**
	 * Responsive dimensions control.
	 *
	 * @param string $id       Unique control id.
	 * @param string $label    Label.
	 * @param string $selector CSS selector.
	 * @param string $property CSS property, normally padding.
	 */
	private function pps_dimensions( $id, $label, $selector, $property = 'padding' ) {
		$this->add_responsive_control(
			$id,
			array(
				'label'      => $label,
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array(
					$selector => $property . ': {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
	}

	/**
	 * Typography group control without a default size. This intentionally does
	 * not write a colour, so the palette keeps working until the user picks one.
	 *
	 * @param string $id       Unique control id prefix.
	 * @param string $label    Heading label.
	 * @param string $selector CSS selector.
	 */
	private function pps_type( $id, $label, $selector ) {
		$this->pps_heading( $id . '_h', $label );
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => $id . '_typo',
				'label'    => __( 'تایپوگرافی', 'hamishop-elementor-widgets' ),
				'selector' => $selector,
			)
		);
	}

	/**
	 * Box-shadow group control.
	 *
	 * @param string $id       Unique control id prefix.
	 * @param string $label    Heading label.
	 * @param string $selector CSS selector.
	 */
	private function pps_shadow( $id, $label, $selector ) {
		$this->pps_heading( $id . '_h', $label );
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => $id . '_shadow',
				'label'    => __( 'سایه', 'hamishop-elementor-widgets' ),
				'selector' => $selector,
			)
		);
	}

	/**
	 * Responsive text alignment.
	 *
	 * @param string $id       Unique control id.
	 * @param string $label    Label.
	 * @param string $selector CSS selector.
	 */
	private function pps_align( $id, $label, $selector ) {
		$this->add_responsive_control(
			$id,
			array(
				'label'     => $label,
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'start'   => array(
						'title' => __( 'شروع سطر (راست در RTL)', 'hamishop-elementor-widgets' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'  => array(
						'title' => __( 'وسط‌چین', 'hamishop-elementor-widgets' ),
						'icon'  => 'eicon-text-align-center',
					),
					'end'     => array(
						'title' => __( 'پایان سطر (چپ در RTL)', 'hamishop-elementor-widgets' ),
						'icon'  => 'eicon-text-align-right',
					),
					'justify' => array(
						'title' => __( 'هم‌تراز دو طرف', 'hamishop-elementor-widgets' ),
						'icon'  => 'eicon-text-align-justify',
					),
				),
				'selectors' => array( $selector => 'text-align: {{VALUE}};' ),
			)
		);
	}

	/**
	 * Select control that writes one CSS property.
	 *
	 * @param string $id       Unique control id.
	 * @param string $label    Label.
	 * @param string $selector CSS selector.
	 * @param string $property CSS property.
	 * @param array  $options  Option value => label.
	 */
	private function pps_select( $id, $label, $selector, $property, $options ) {
		$this->add_control(
			$id,
			array(
				'label'     => $label,
				'type'      => Controls_Manager::SELECT,
				'options'   => $options,
				'selectors' => array( $selector => $property . ': {{VALUE}};' ),
			)
		);
	}

	/**
	 * Container box controls (background, border, radius, padding, shadow).
	 *
	 * @param string $id       Unique control id prefix.
	 * @param string $selector CSS selector.
	 * @param array  $opts     Passed to ctrl_box().
	 */
	private function pps_box( $id, $selector, $opts = array() ) {
		$this->ctrl_box( $id, $selector, $opts );
	}

	/**
	 * Icon colour + size.
	 *
	 * @param string $id       Unique control id prefix.
	 * @param string $label    Heading label.
	 * @param string $selector CSS selector.
	 */
	private function pps_icon( $id, $label, $selector ) {
		$this->pps_heading( $id . '_h', $label );
		$this->ctrl_icon( $id, $selector );
	}

	/**
	 * Shared WooCommerce notice controls. The shared stylesheet reads these
	 * custom properties, which lets a per-widget choice win without fighting
	 * the `!important` rules used to neutralise theme notice styles.
	 *
	 * @param string $id       Unique control id prefix, e.g. m_success.
	 * @param string $label    Section heading.
	 * @param string $selector Notice selector.
	 */
	private function pps_notice_style( $id, $label, $selector ) {
		$this->pps_heading( $id . '_h', $label );

		$this->pps_var_color( $id . '_bg', __( 'پس‌زمینه', 'hamishop-elementor-widgets' ), $selector, '--hsw-notice-bg' );
		$this->pps_var_color( $id . '_c', __( 'رنگ متن', 'hamishop-elementor-widgets' ), $selector, '--hsw-notice-c' );
		$this->pps_var_color( $id . '_bc', __( 'رنگ حاشیه', 'hamishop-elementor-widgets' ), $selector, '--hsw-notice-bc' );
		$this->pps_var_color( $id . '_ac', __( 'رنگ نوار کناری', 'hamishop-elementor-widgets' ), $selector, '--hsw-notice-accent' );
		$this->pps_slider(
			$id . '_bw',
			__( 'ضخامت حاشیه', 'hamishop-elementor-widgets' ),
			$selector,
			'--hsw-notice-bw: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 8 ) ) )
		);
		$this->pps_slider(
			$id . '_aw',
			__( 'ضخامت نوار کناری', 'hamishop-elementor-widgets' ),
			$selector,
			'--hsw-notice-accent-w: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 10 ) ) )
		);
		$this->pps_slider(
			$id . '_radius',
			__( 'گردی گوشه', 'hamishop-elementor-widgets' ),
			$selector,
			'--hsw-notice-radius: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ) )
		);
		$this->pps_dimensions( $id . '_pad', __( 'فاصله داخلی', 'hamishop-elementor-widgets' ), $selector, '--hsw-notice-pad' );
		$this->pps_type( $id . '_t', __( 'تایپوگرافی پیام', 'hamishop-elementor-widgets' ), $selector );
	}
}
