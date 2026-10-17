<?php
/**
 * Widget: HamiShop Checkout — WooCommerce's own checkout shortcode,
 * wrapped, toggled and fully styleable from Elementor.
 *
 * Philosophy: 100% functionally safe. Payment gateways render their own
 * fields and scripts, so we never rebuild the checkout markup. We render
 * WooCommerce's battle-tested [woocommerce_checkout] shortcode inside our
 * wrapper and add:
 *  (a) section visibility toggles via conditional hooks around the render,
 *  (b) Elementor style controls targeting WooCommerce's native selectors.
 * In the editor we never render the real shortcode — a styled placeholder
 * note is shown instead (avoids broken Woo states in the editor).
 *
 * @package HamiShop
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;

/**
 * Checkout widget.
 */
class HSW_Widget_Checkout extends \Elementor\Widget_Base {

	/**
	 * Widget id.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'hsw-checkout';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'HamiShop Checkout', 'hamishop-elementor-widgets' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-checkout';
	}

	/**
	 * Category.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return [ 'hamishop' ];
	}

	/**
	 * Keywords for the finder.
	 *
	 * @return string[]
	 */
	public function get_keywords() {
		return [ 'hamishop', 'hamisys', 'hami', 'checkout', 'payment', 'پرداخت', 'تسویه حساب' ];
	}

	/**
	 * Frontend styles — only loaded when this widget is on the page.
	 *
	 * @return string[]
	 */
	public function get_style_depends() {
		return [ 'hsw-base', 'hsw-checkout' ];
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->register_content_controls();
		$this->register_style_controls();
	}

	/**
	 * Content: checkout sections + layout.
	 *
	 * @return void
	 */
	private function register_content_controls() {
		$this->start_controls_section(
			'section_content',
			[
				'label' => __( 'Checkout', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'show_coupon',
			[
				'label'        => __( 'Coupon Form', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'hamishop-elementor-widgets' ),
				'label_off'    => __( 'Hide', 'hamishop-elementor-widgets' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => __( 'The coupon form above the checkout fields.', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'show_order_notes',
			[
				'label'        => __( 'Order Notes', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'hamishop-elementor-widgets' ),
				'label_off'    => __( 'Hide', 'hamishop-elementor-widgets' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => __( 'The "Order notes" textarea in the additional information section.', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'layout_width',
			[
				'label'        => __( 'Layout', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'two-col',
				'options'      => [
					'two-col' => __( 'Two Columns (WooCommerce default)', 'hamishop-elementor-widgets' ),
					'one-col' => __( 'Single Column (stacked)', 'hamishop-elementor-widgets' ),
				],
				'prefix_class' => 'hsw-checkout--',
			]
		);

		$this->add_control(
			'editor_note_text',
			[
				'label'       => __( 'Editor Placeholder Text', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 4,
				'default'     => __( 'The live checkout appears here for shop visitors with items in their cart. Add this widget to your Checkout page (set in WooCommerce → Settings → Advanced).', 'hamishop-elementor-widgets' ),
				'label_block' => true,
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Style controls — all {{WRAPPER}} scoped to WooCommerce native selectors.
	 *
	 * @return void
	 */
	private function register_style_controls() {
		$this->style_layout();
		$this->style_headings();
		$this->style_labels();
		$this->style_inputs();
		$this->style_coupon();
		$this->style_review();
		$this->style_payment();
		$this->style_terms();
	}

	/**
	 * Layout: form width, background, frame.
	 *
	 * @return void
	 */
	private function style_layout() {
		$this->start_controls_section(
			'style_section_layout',
			[
				'label' => __( 'Layout', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'form_width',
			[
				'label'      => __( 'Form Max Width', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ '%' ],
				'range'      => [
					'%' => [ 'min' => 50, 'max' => 100 ],
				],
				'default'    => [ 'size' => 100, 'unit' => '%' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-checkout .woocommerce-checkout' => 'max-width: {{SIZE}}%; margin-inline: auto;',
				],
			]
		);

		$this->add_control(
			'checkout_bg',
			[
				'label'     => __( 'Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout .woocommerce-checkout' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'checkout_padding',
			[
				'label'      => __( 'Padding', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 100 ] ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-checkout .woocommerce-checkout' => 'padding: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'checkout_radius',
			[
				'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'default'    => [ 'size' => 12, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-checkout .woocommerce-checkout' => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'checkout_border',
				'selector' => '{{WRAPPER}} .hsw-checkout .woocommerce-checkout',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'checkout_shadow',
				'selector' => '{{WRAPPER}} .hsw-checkout .woocommerce-checkout',
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Headings: billing / shipping / additional / order review titles.
	 *
	 * @return void
	 */
	private function style_headings() {
		$headings = '{{WRAPPER}} .hsw-checkout .woocommerce-billing-fields h3, {{WRAPPER}} .hsw-checkout .woocommerce-shipping-fields h3, {{WRAPPER}} .hsw-checkout .woocommerce-additional-fields h3, {{WRAPPER}} .hsw-checkout #order_review_heading';

		$this->start_controls_section(
			'style_section_headings',
			[
				'label' => __( 'Headings', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'headings_typography',
				'selector' => $headings,
			]
		);

		$this->add_control(
			'headings_color',
			[
				'label'     => __( 'Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					$headings => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'headings_spacing',
			[
				'label'      => __( 'Bottom Spacing', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
				'default'    => [ 'size' => 14, 'unit' => 'px' ],
				'selectors'  => [
					$headings => 'margin-block-end: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Field labels + required star.
	 *
	 * @return void
	 */
	private function style_labels() {
		$this->start_controls_section(
			'style_section_labels',
			[
				'label' => __( 'Labels', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'labels_typography',
				'selector' => '{{WRAPPER}} .hsw-checkout .form-row label',
			]
		);

		$this->add_control(
			'labels_color',
			[
				'label'     => __( 'Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout .form-row label' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'labels_required_color',
			[
				'label'     => __( 'Required Star Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#C0392B',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout .form-row .required' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Inputs: text, textarea, select + select2.
	 *
	 * @return void
	 */
	private function style_inputs() {
		$inputs = '{{WRAPPER}} .hsw-checkout .input-text, {{WRAPPER}} .hsw-checkout select';

		$this->start_controls_section(
			'style_section_inputs',
			[
				'label' => __( 'Inputs', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'inputs_typography',
				'selector' => $inputs,
			]
		);

		$this->add_control(
			'inputs_text_color',
			[
				'label'     => __( 'Text Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					$inputs => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'inputs_bg_color',
			[
				'label'     => __( 'Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [
					$inputs => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'inputs_border',
				'selector' => $inputs,
			]
		);

		$this->add_responsive_control(
			'inputs_radius',
			[
				'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'default'    => [ 'size' => 8, 'unit' => 'px' ],
				'selectors'  => [
					$inputs => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'inputs_padding',
			[
				'label'      => __( 'Padding', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'default'    => [ 'size' => 10, 'unit' => 'px' ],
				'selectors'  => [
					$inputs => 'padding: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'inputs_focus_border_color',
			[
				'label'     => __( 'Focus Border Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#C0392B',
				'separator' => 'before',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout .input-text:focus, {{WRAPPER}} .hsw-checkout select:focus' => 'border-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'select2_heading',
			[
				'label'     => __( 'Select2 Dropdowns', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'select2_border_color',
			[
				'label'     => __( 'Border Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#E5E7EB',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout .select2-container--default .select2-selection--single' => 'border-color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'select2_radius',
			[
				'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'default'    => [ 'size' => 8, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-checkout .select2-container--default .select2-selection--single' => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'select2_height',
			[
				'label'      => __( 'Height', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 30, 'max' => 64 ] ],
				'default'    => [ 'size' => 42, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-checkout .select2-container--default .select2-selection--single' => 'height: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Coupon form (woocommerce_before_checkout_form).
	 *
	 * @return void
	 */
	private function style_coupon() {
		$this->start_controls_section(
			'style_section_coupon',
			[
				'label'     => __( 'Coupon Form', 'hamishop-elementor-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_coupon' => 'yes' ],
			]
		);

		$this->add_control(
			'coupon_bg',
			[
				'label'     => __( 'Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#F9FAFB',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout .woocommerce-form-coupon' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'coupon_border',
				'selector' => '{{WRAPPER}} .hsw-checkout .woocommerce-form-coupon',
			]
		);

		$this->add_responsive_control(
			'coupon_radius',
			[
				'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'default'    => [ 'size' => 12, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-checkout .woocommerce-form-coupon' => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'coupon_padding',
			[
				'label'      => __( 'Padding', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
				'default'    => [ 'size' => 16, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-checkout .woocommerce-form-coupon' => 'padding: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'coupon_btn_heading',
			[
				'label'     => __( 'Coupon Button', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'coupon_btn_typography',
				'selector' => '{{WRAPPER}} .hsw-checkout .woocommerce-form-coupon .button',
			]
		);

		$this->start_controls_tabs( 'coupon_btn_tabs' );

		$this->start_controls_tab(
			'coupon_btn_tab_normal',
			[
				'label' => __( 'Normal', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'coupon_btn_text_color',
			[
				'label'     => __( 'Text Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout .woocommerce-form-coupon .button' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'coupon_btn_bg_color',
			[
				'label'     => __( 'Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout .woocommerce-form-coupon .button' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'coupon_btn_tab_hover',
			[
				'label' => __( 'Hover', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'coupon_btn_hover_text_color',
			[
				'label'     => __( 'Text Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout .woocommerce-form-coupon .button:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'coupon_btn_hover_bg_color',
			[
				'label'     => __( 'Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#C0392B',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout .woocommerce-form-coupon .button:hover' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'coupon_btn_radius',
			[
				'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'default'    => [ 'size' => 8, 'unit' => 'px' ],
				'separator'  => 'before',
				'selectors'  => [
					'{{WRAPPER}} .hsw-checkout .woocommerce-form-coupon .button' => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'coupon_btn_padding',
			[
				'label'      => __( 'Padding', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'default'    => [ 'size' => 10, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-checkout .woocommerce-form-coupon .button' => 'padding: {{SIZE}}{{UNIT}} 20px; padding-inline: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Order review table (#order_review).
	 *
	 * @return void
	 */
	private function style_review() {
		$this->start_controls_section(
			'style_section_review',
			[
				'label' => __( 'Order Review', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'review_bg',
			[
				'label'     => __( 'Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#F9FAFB',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout #order_review' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'review_border',
				'selector' => '{{WRAPPER}} .hsw-checkout #order_review',
			]
		);

		$this->add_responsive_control(
			'review_radius',
			[
				'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'default'    => [ 'size' => 12, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-checkout #order_review' => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'review_padding',
			[
				'label'      => __( 'Padding', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
				'default'    => [ 'size' => 20, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-checkout #order_review' => 'padding: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'review_table_heading',
			[
				'label'     => __( 'Products Table', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'review_thead_bg',
			[
				'label'     => __( 'Header Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#F3F4F6',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout #order_review table.shop_table thead th' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'review_thead_typography',
				'selector' => '{{WRAPPER}} .hsw-checkout #order_review table.shop_table thead th',
			]
		);

		$this->add_control(
			'review_thead_color',
			[
				'label'     => __( 'Header Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#6B7280',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout #order_review table.shop_table thead th' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'review_name_color',
			[
				'label'     => __( 'Product Name Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout #order_review .product-name' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'review_total_color',
			[
				'label'     => __( 'Product Total Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout #order_review .product-total' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'review_row_border_color',
			[
				'label'     => __( 'Row Border Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#E5E7EB',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout #order_review table.shop_table td, {{WRAPPER}} .hsw-checkout #order_review table.shop_table th' => 'border-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'review_order_total_typography',
				'selector' => '{{WRAPPER}} .hsw-checkout #order_review .order-total',
			]
		);

		$this->add_control(
			'review_order_total_color',
			[
				'label'     => __( 'Order Total Amount Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#C0392B',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout #order_review .order-total .woocommerce-Price-amount' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Payment area (#payment) incl. Place Order button.
	 *
	 * @return void
	 */
	private function style_payment() {
		$this->start_controls_section(
			'style_section_payment',
			[
				'label' => __( 'Payment', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'payment_bg',
			[
				'label'     => __( 'Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#F9FAFB',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout #payment' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'payment_border',
				'selector' => '{{WRAPPER}} .hsw-checkout #payment',
			]
		);

		$this->add_responsive_control(
			'payment_radius',
			[
				'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'default'    => [ 'size' => 12, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-checkout #payment' => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'payment_padding',
			[
				'label'      => __( 'Padding', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
				'default'    => [ 'size' => 20, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-checkout #payment' => 'padding: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'payment_methods_heading',
			[
				'label'     => __( 'Payment Methods', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'payment_methods_border_color',
			[
				'label'     => __( 'Method Box Border Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#E5E7EB',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout #payment ul.payment_methods li' => 'border-color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'payment_methods_gap',
			[
				'label'      => __( 'Method Boxes Gap', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
				'default'    => [ 'size' => 10, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-checkout #payment ul.payment_methods' => 'display: flex; flex-direction: column; gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'payment_method_padding',
			[
				'label'      => __( 'Method Box Padding', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'default'    => [ 'size' => 12, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-checkout #payment ul.payment_methods li' => 'padding: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'payment_label_typography',
				'selector' => '{{WRAPPER}} .hsw-checkout #payment ul.payment_methods li label',
			]
		);

		$this->add_control(
			'payment_label_color',
			[
				'label'     => __( 'Method Label Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout #payment ul.payment_methods li label' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'payment_box_bg',
			[
				'label'     => __( 'Method Description Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'separator' => 'before',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout #payment .payment_box' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'payment_box_typography',
				'selector' => '{{WRAPPER}} .hsw-checkout #payment .payment_box',
			]
		);

		$this->add_control(
			'place_order_heading',
			[
				'label'     => __( 'Place Order Button', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'place_order_typography',
				'selector' => '{{WRAPPER}} .hsw-checkout #payment #place_order',
			]
		);

		$this->add_control(
			'place_order_full_width',
			[
				'label'        => __( 'Full Width', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'selectors'    => [
					'{{WRAPPER}} .hsw-checkout #payment #place_order' => 'width: 100%;',
				],
			]
		);

		$this->start_controls_tabs( 'place_order_tabs' );

		$this->start_controls_tab(
			'place_order_tab_normal',
			[
				'label' => __( 'Normal', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'place_order_text_color',
			[
				'label'     => __( 'Text Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout #payment #place_order' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'place_order_bg_color',
			[
				'label'     => __( 'Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#16A34A',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout #payment #place_order' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'place_order_border',
				'selector' => '{{WRAPPER}} .hsw-checkout #payment #place_order',
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'place_order_tab_hover',
			[
				'label' => __( 'Hover', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'place_order_hover_text_color',
			[
				'label'     => __( 'Text Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout #payment #place_order:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'place_order_hover_bg_color',
			[
				'label'     => __( 'Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout #payment #place_order:hover' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'place_order_hover_border_color',
			[
				'label'     => __( 'Border Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout #payment #place_order:hover' => 'border-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'place_order_radius',
			[
				'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'default'    => [ 'size' => 8, 'unit' => 'px' ],
				'separator'  => 'before',
				'selectors'  => [
					'{{WRAPPER}} .hsw-checkout #payment #place_order' => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'place_order_padding',
			[
				'label'      => __( 'Padding', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'default'    => [ 'size' => 12, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-checkout #payment #place_order' => 'padding: {{SIZE}}{{UNIT}} 20px; padding-inline: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Terms & conditions wrapper.
	 *
	 * @return void
	 */
	private function style_terms() {
		$this->start_controls_section(
			'style_section_terms',
			[
				'label' => __( 'Terms & Conditions', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'terms_typography',
				'selector' => '{{WRAPPER}} .hsw-checkout .woocommerce-terms-and-conditions-wrapper',
			]
		);

		$this->add_control(
			'terms_color',
			[
				'label'     => __( 'Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#6B7280',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout .woocommerce-terms-and-conditions-wrapper' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'terms_link_color',
			[
				'label'     => __( 'Link Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#C0392B',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout .woocommerce-terms-and-conditions-wrapper a' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'terms_link_hover_color',
			[
				'label'     => __( 'Link Hover Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					'{{WRAPPER}} .hsw-checkout .woocommerce-terms-and-conditions-wrapper a:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Render output.
	 *
	 * Frontend always renders WooCommerce's own [woocommerce_checkout]
	 * shortcode — even with an empty cart (Woo prints its own
	 * "checkout unavailable" notice, which we style). Only the editor
	 * gets a placeholder note, never live checkout markup.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		$is_editor = HSW_Helpers::is_editor();

		echo '<div class="hsw-widget-root hsw-checkout">';

		if ( $is_editor ) {
			echo HSW_Helpers::editor_note( $settings['editor_note_text'] ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</div>';
			return;
		}

		// Toggle: checkout coupon form (hooked by Woo at woocommerce_before_checkout_form).
		$hide_coupon = ( 'yes' !== $settings['show_coupon'] );
		if ( $hide_coupon ) {
			remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
		}

		// Toggle: order notes field ($fields['order']['order_comments']).
		$hide_notes   = ( 'yes' !== $settings['show_order_notes'] );
		$notes_filter = null;
		if ( $hide_notes ) {
			$notes_filter = function ( $fields ) {
				if ( isset( $fields['order']['order_comments'] ) ) {
					unset( $fields['order']['order_comments'] );
				}
				return $fields;
			};
			add_filter( 'woocommerce_checkout_fields', $notes_filter, 999999 );
		}

		// Make sure the checkout JS exists even in theme-builder contexts.
		HSW_Assets::ensure_wc_script( 'wc-checkout' );

		echo do_shortcode( '[woocommerce_checkout]' ); // phpcs:ignore WordPress.Security.EscapeOutput

		// Remove the exact same closure instance we added — always balanced.
		if ( $hide_notes && $notes_filter ) {
			remove_filter( 'woocommerce_checkout_fields', $notes_filter, 999999 );
		}
		if ( $hide_coupon ) {
			add_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
		}

		echo '</div>';
	}
}
