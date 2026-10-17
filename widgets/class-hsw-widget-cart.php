<?php
/**
 * Widget: HamiShop Cart — WooCommerce's own [woocommerce_cart] shortcode,
 * wrapped in a HamiShop frame and made fully styleable.
 *
 * Philosophy:
 *  The cart is business-critical (coupons, AJAX quantity updates, shipping
 *  calculator, notices, session handling), so this widget never reinvents it.
 *  It renders WooCommerce's battle-tested cart shortcode inside a wrapper and
 *  adds section visibility toggles + styling controls that target WooCommerce's
 *  native selectors — the same proven approach Elementor Pro uses.
 *
 * @package HamiShop
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;

/**
 * Cart widget.
 */
class HSW_Widget_Cart extends \Elementor\Widget_Base {

	/**
	 * Widget id.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'hsw-cart';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'HamiShop Cart', 'hamishop-elementor-widgets' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-cart-medium';
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
		return [ 'hamishop', 'hamisys', 'hami', 'cart', 'basket', 'سبد خرید' ];
	}

	/**
	 * Frontend styles — only loaded when this widget is on the page.
	 *
	 * @return string[]
	 */
	public function get_style_depends() {
		return [ 'hsw-base', 'hsw-cart' ];
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
	 * Content: cart sections + editor note.
	 *
	 * @return void
	 */
	private function register_content_controls() {
		$this->start_controls_section(
			'section_content',
			[
				'label' => __( 'Cart Sections', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'show_coupon',
			[
				'label'        => __( 'Coupon Field', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => __( 'The coupon input inside the cart form.', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'show_totals',
			[
				'label'        => __( 'Cart Totals', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => __( 'The totals / collaterals column next to the table.', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'show_cross_sells',
			[
				'label'        => __( 'Cross-Sell Products', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'no',
				'description'  => __( 'Suggested products rendered under the cart.', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'editor_note_text',
			[
				'label'       => __( 'Empty-Cart Editor Note', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 4,
				'default'     => __( 'The live cart appears here for logged-in shop visitors. Add the widget to your Cart page (set in WooCommerce → Settings → Advanced).', 'hamishop-elementor-widgets' ),
				'description' => __( 'Shown in the editor only while the cart is empty. Visitors never see it.', 'hamishop-elementor-widgets' ),
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Style sections for every WooCommerce cart element.
	 *
	 * @return void
	 */
	private function register_style_controls() {
		$this->style_table();
		$this->style_product();
		$this->style_qty();
		$this->style_coupon();
		$this->style_buttons();
		$this->style_totals();
		$this->style_crosssells();
		$this->style_empty();
	}

	/**
	 * Cart table styles.
	 *
	 * @return void
	 */
	private function style_table() {
		$this->start_controls_section(
			'style_section_table',
			[
				'label' => __( 'Cart Table', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'table_header_typography',
				'selector' => '{{WRAPPER}} .hsw-cart table.shop_table thead th',
			]
		);

		$this->add_control(
			'table_header_color',
			[
				'label'     => __( 'Header Text Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					'{{WRAPPER}} .hsw-cart table.shop_table thead th' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'table_header_bg',
			[
				'label'     => __( 'Header Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .hsw-cart table.shop_table thead th' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'table_header_padding',
			[
				'label'      => __( 'Header Padding', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-cart table.shop_table thead th' => 'padding: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'table_cell_padding',
			[
				'label'      => __( 'Cell Padding', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
				'separator'  => 'before',
				'selectors'  => [
					'{{WRAPPER}} .hsw-cart table.shop_table td' => 'padding: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'table_row_border_color',
			[
				'label'     => __( 'Row Border Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#E5E7EB',
				'selectors' => [
					'{{WRAPPER}} .hsw-cart table.shop_table tbody td' => 'border-top-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'table_border',
				'selector' => '{{WRAPPER}} .hsw-cart table.shop_table',
			]
		);

		$this->add_control(
			'table_radius',
			[
				'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-cart table.shop_table' => 'border-radius: {{SIZE}}{{UNIT}}; overflow: hidden;',
				],
			]
		);

		$this->add_control(
			'table_bg',
			[
				'label'     => __( 'Background Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .hsw-cart table.shop_table' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Product cell styles (thumbnail, name link, remove link, prices).
	 *
	 * @return void
	 */
	private function style_product() {
		$this->start_controls_section(
			'style_section_product',
			[
				'label' => __( 'Product Cell', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'product_image_size',
			[
				'label'      => __( 'Thumbnail Size', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 40, 'max' => 160 ] ],
				'default'    => [ 'size' => 90, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-cart .product-thumbnail img' => 'width: {{SIZE}}{{UNIT}}; height: auto;',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'product_name_typography',
				'selector' => '{{WRAPPER}} .hsw-cart .product-name a',
			]
		);

		$this->start_controls_tabs( 'product_name_tabs' );

		$this->start_controls_tab(
			'product_name_tab_normal',
			[
				'label' => __( 'Normal', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'product_name_color',
			[
				'label'     => __( 'Link Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					'{{WRAPPER}} .hsw-cart .product-name a' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'product_name_tab_hover',
			[
				'label' => __( 'Hover', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'product_name_hover_color',
			[
				'label'     => __( 'Link Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#C0392B',
				'selectors' => [
					'{{WRAPPER}} .hsw-cart .product-name a:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'heading_product_remove',
			[
				'label'     => __( 'Remove Link', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'product_remove_color',
			[
				'label'     => __( 'Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#C0392B',
				'selectors' => [
					'{{WRAPPER}} .hsw-cart .product-remove a' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'product_remove_hover_color',
			[
				'label'     => __( 'Hover Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					'{{WRAPPER}} .hsw-cart .product-remove a:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'product_price_color',
			[
				'label'     => __( 'Price Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'separator' => 'before',
				'selectors' => [
					'{{WRAPPER}} .hsw-cart .product-price' => 'color: {{VALUE}};',
					'{{WRAPPER}} .hsw-cart .product-subtotal' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Quantity input styles.
	 *
	 * @return void
	 */
	private function style_qty() {
		$this->start_controls_section(
			'style_section_qty',
			[
				'label' => __( 'Quantity Input', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'qty_width',
			[
				'label'      => __( 'Width', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 30, 'max' => 160 ] ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-cart .quantity .qty' => 'width: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'qty_height',
			[
				'label'      => __( 'Height', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 24, 'max' => 80 ] ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-cart .quantity .qty' => 'height: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'qty_typography',
				'selector' => '{{WRAPPER}} .hsw-cart .quantity .qty',
			]
		);

		$this->add_control(
			'qty_color',
			[
				'label'     => __( 'Text Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					'{{WRAPPER}} .hsw-cart .quantity .qty' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'qty_bg',
			[
				'label'     => __( 'Background Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .hsw-cart .quantity .qty' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'qty_border',
				'selector' => '{{WRAPPER}} .hsw-cart .quantity .qty',
			]
		);

		$this->add_control(
			'qty_radius',
			[
				'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'separator'  => 'before',
				'selectors'  => [
					'{{WRAPPER}} .hsw-cart .quantity .qty' => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Coupon input + apply button styles.
	 *
	 * @return void
	 */
	private function style_coupon() {
		$this->start_controls_section(
			'style_section_coupon',
			[
				'label'     => __( 'Coupon', 'hamishop-elementor-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_coupon' => 'yes' ],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'coupon_input_typography',
				'selector' => '{{WRAPPER}} .hsw-cart .coupon .input-text',
			]
		);

		$this->add_control(
			'coupon_input_color',
			[
				'label'     => __( 'Text Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					'{{WRAPPER}} .hsw-cart .coupon .input-text' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'coupon_input_bg',
			[
				'label'     => __( 'Background Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .hsw-cart .coupon .input-text' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'coupon_input_border',
				'selector' => '{{WRAPPER}} .hsw-cart .coupon .input-text',
			]
		);

		$this->add_control(
			'coupon_input_radius',
			[
				'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-cart .coupon .input-text' => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'coupon_input_padding',
			[
				'label'      => __( 'Padding', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-cart .coupon .input-text' => 'padding: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'heading_coupon_button',
			[
				'label'     => __( 'Apply Button', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'coupon_button_typography',
				'selector' => '{{WRAPPER}} .hsw-cart .coupon .button',
			]
		);

		$this->start_controls_tabs( 'coupon_button_tabs' );

		$this->start_controls_tab(
			'coupon_button_tab_normal',
			[
				'label' => __( 'Normal', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'coupon_button_text_color',
			[
				'label'     => __( 'Text Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [
					'{{WRAPPER}} .hsw-cart .coupon .button' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'coupon_button_bg_color',
			[
				'label'     => __( 'Background Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					'{{WRAPPER}} .hsw-cart .coupon .button' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'coupon_button_tab_hover',
			[
				'label' => __( 'Hover', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'coupon_button_hover_text_color',
			[
				'label'     => __( 'Text Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .hsw-cart .coupon .button:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'coupon_button_hover_bg_color',
			[
				'label'     => __( 'Background Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#C0392B',
				'selectors' => [
					'{{WRAPPER}} .hsw-cart .coupon .button:hover' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'coupon_button_radius',
			[
				'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'separator'  => 'before',
				'selectors'  => [
					'{{WRAPPER}} .hsw-cart .coupon .button' => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'coupon_button_padding',
			[
				'label'      => __( 'Padding', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-cart .coupon .button' => 'padding: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * "Update cart" + "Proceed to checkout" button styles.
	 *
	 * @return void
	 */
	private function style_buttons() {
		$this->start_controls_section(
			'style_section_buttons',
			[
				'label' => __( 'Action Buttons', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$buttons_selector = '{{WRAPPER}} .hsw-cart .woocommerce-cart-form .button[name="update_cart"], {{WRAPPER}} .hsw-cart .checkout-button';

		$this->add_control(
			'buttons_full_width',
			[
				'label'        => __( 'Full Width', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'selectors'    => [
					$buttons_selector => 'width: 100%;',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'buttons_typography',
				'selector' => $buttons_selector,
			]
		);

		$this->start_controls_tabs( 'buttons_tabs' );

		$this->start_controls_tab(
			'buttons_tab_normal',
			[
				'label' => __( 'Normal', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'buttons_text_color',
			[
				'label'     => __( 'Text Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [
					$buttons_selector => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'buttons_bg_color',
			[
				'label'     => __( 'Background Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					$buttons_selector => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'buttons_border',
				'selector' => $buttons_selector,
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'buttons_tab_hover',
			[
				'label' => __( 'Hover', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'buttons_hover_text_color',
			[
				'label'     => __( 'Text Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$buttons_selector . ':hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'buttons_hover_bg_color',
			[
				'label'     => __( 'Background Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#C0392B',
				'selectors' => [
					$buttons_selector . ':hover' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'buttons_hover_border_color',
			[
				'label'     => __( 'Border Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$buttons_selector . ':hover' => 'border-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'buttons_radius',
			[
				'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'default'    => [ 'size' => 8, 'unit' => 'px' ],
				'separator'  => 'before',
				'selectors'  => [
					$buttons_selector => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'buttons_padding',
			[
				'label'      => __( 'Padding', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'default'    => [ 'size' => 12, 'unit' => 'px' ],
				'selectors'  => [
					$buttons_selector => 'padding: {{SIZE}}{{UNIT}} 20px; padding-inline: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Cart totals column styles.
	 *
	 * @return void
	 */
	private function style_totals() {
		$this->start_controls_section(
			'style_section_totals',
			[
				'label'     => __( 'Cart Totals', 'hamishop-elementor-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_totals' => 'yes' ],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'totals_heading_typography',
				'selector' => '{{WRAPPER}} .hsw-cart .cart_totals h2',
			]
		);

		$this->add_control(
			'totals_heading_color',
			[
				'label'     => __( 'Heading Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					'{{WRAPPER}} .hsw-cart .cart_totals h2' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'heading_totals_rows',
			[
				'label'     => __( 'Rows', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'totals_label_typography',
				'selector' => '{{WRAPPER}} .hsw-cart .cart_totals table.shop_table th',
			]
		);

		$this->add_control(
			'totals_label_color',
			[
				'label'     => __( 'Label Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#6B7280',
				'selectors' => [
					'{{WRAPPER}} .hsw-cart .cart_totals table.shop_table th' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'totals_value_typography',
				'selector' => '{{WRAPPER}} .hsw-cart .cart_totals table.shop_table td',
			]
		);

		$this->add_control(
			'totals_value_color',
			[
				'label'     => __( 'Value Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					'{{WRAPPER}} .hsw-cart .cart_totals table.shop_table td' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'totals_order_total_color',
			[
				'label'     => __( 'Order Total Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#C0392B',
				'selectors' => [
					'{{WRAPPER}} .hsw-cart .cart_totals .order-total .woocommerce-Price-amount' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'totals_row_border_color',
			[
				'label'     => __( 'Row Border Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#E5E7EB',
				'selectors' => [
					'{{WRAPPER}} .hsw-cart .cart_totals table.shop_table th' => 'border-top-color: {{VALUE}};',
					'{{WRAPPER}} .hsw-cart .cart_totals table.shop_table td' => 'border-top-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'heading_totals_box',
			[
				'label'     => __( 'Box', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'totals_bg',
			[
				'label'     => __( 'Background Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .hsw-cart .cart_totals' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'totals_padding',
			[
				'label'      => __( 'Padding', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
				'default'    => [ 'size' => 20, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-cart .cart_totals' => 'padding: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'totals_radius',
			[
				'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
				'default'    => [ 'size' => 12, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-cart .cart_totals' => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Cross-sells styles.
	 *
	 * @return void
	 */
	private function style_crosssells() {
		$this->start_controls_section(
			'style_section_crosssells',
			[
				'label'     => __( 'Cross-Sells', 'hamishop-elementor-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_cross_sells' => 'yes' ],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'crosssells_heading_typography',
				'selector' => '{{WRAPPER}} .hsw-cart .cross-sells > h2',
			]
		);

		$this->add_control(
			'crosssells_heading_color',
			[
				'label'     => __( 'Heading Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					'{{WRAPPER}} .hsw-cart .cross-sells > h2' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'crosssells_columns',
			[
				'label'     => __( 'Columns', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '2',
				'options'   => [
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
				],
				'selectors' => [
					'{{WRAPPER}} .hsw-cart .cross-sells ul.products' => 'display: grid !important; grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
				],
			]
		);

		$this->add_responsive_control(
			'crosssells_gap',
			[
				'label'      => __( 'Gap', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
				'default'    => [ 'size' => 20, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-cart .cross-sells ul.products' => 'gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Empty-cart state styles.
	 *
	 * @return void
	 */
	private function style_empty() {
		$this->start_controls_section(
			'style_section_empty',
			[
				'label' => __( 'Empty Cart', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'empty_message_typography',
				'selector' => '{{WRAPPER}} .hsw-cart .cart-empty',
			]
		);

		$this->add_control(
			'empty_message_color',
			[
				'label'     => __( 'Message Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#6B7280',
				'selectors' => [
					'{{WRAPPER}} .hsw-cart .cart-empty' => 'color: {{VALUE}};',
				],
			]
		);

		$empty_button_selector = '{{WRAPPER}} .hsw-cart .return-to-shop .button';

		$this->add_control(
			'heading_empty_button',
			[
				'label'     => __( 'Return to Shop Button', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'empty_button_typography',
				'selector' => $empty_button_selector,
			]
		);

		$this->start_controls_tabs( 'empty_button_tabs' );

		$this->start_controls_tab(
			'empty_button_tab_normal',
			[
				'label' => __( 'Normal', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'empty_button_text_color',
			[
				'label'     => __( 'Text Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [
					$empty_button_selector => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'empty_button_bg_color',
			[
				'label'     => __( 'Background Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					$empty_button_selector => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'empty_button_border',
				'selector' => $empty_button_selector,
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'empty_button_tab_hover',
			[
				'label' => __( 'Hover', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'empty_button_hover_text_color',
			[
				'label'     => __( 'Text Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$empty_button_selector . ':hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'empty_button_hover_bg_color',
			[
				'label'     => __( 'Background Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#C0392B',
				'selectors' => [
					$empty_button_selector . ':hover' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'empty_button_hover_border_color',
			[
				'label'     => __( 'Border Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$empty_button_selector . ':hover' => 'border-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'empty_button_radius',
			[
				'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'default'    => [ 'size' => 8, 'unit' => 'px' ],
				'separator'  => 'before',
				'selectors'  => [
					$empty_button_selector => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'empty_button_padding',
			[
				'label'      => __( 'Padding', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'default'    => [ 'size' => 12, 'unit' => 'px' ],
				'selectors'  => [
					$empty_button_selector => 'padding: {{SIZE}}{{UNIT}} 20px; padding-inline: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Render output.
	 *
	 * Renders WooCommerce's own cart shortcode inside a HamiShop wrapper.
	 * Section toggles work via temporary WooCommerce filters that are always
	 * removed right after the shortcode output.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		$show_coupon      = isset( $settings['show_coupon'] ) ? $settings['show_coupon'] : 'yes';
		$show_totals      = isset( $settings['show_totals'] ) ? $settings['show_totals'] : 'yes';
		$show_cross_sells = isset( $settings['show_cross_sells'] ) ? $settings['show_cross_sells'] : 'no';
		$note_text        = isset( $settings['editor_note_text'] ) ? $settings['editor_note_text'] : __( 'The live cart appears here for logged-in shop visitors. Add the widget to your Cart page (set in WooCommerce → Settings → Advanced).', 'hamishop-elementor-widgets' );

		$has_items = ( WC()->cart && ! WC()->cart->is_empty() );
		$is_editor = HSW_Helpers::is_editor();

		// In the editor with an empty cart, avoid rendering the real shortcode
		// (WooCommerce cart states can confuse the editor) — show a friendly
		// preview note instead.
		if ( $is_editor && ! $has_items ) {
			echo '<div class="hsw-widget-root hsw-cart hsw-cart--preview">';
			echo HSW_Helpers::editor_note( $note_text ); // phpcs:ignore WordPress.Security.EscapeOutput -- esc_html() inside the helper.
			echo '</div>';
			return;
		}

		// WooCommerce front-end scripts can be missing in theme-builder contexts.
		if ( ! is_admin() && class_exists( 'HSW_Assets' ) ) {
			HSW_Assets::ensure_wc_script( 'wc-add-to-cart' );
			HSW_Assets::ensure_wc_script( 'wc-cart-fragments' );
		}

		echo '<div class="hsw-widget-root hsw-cart' . ( 'yes' !== $show_totals ? ' hsw-cart--no-totals' : '' ) . '">';

		// Toggle: coupon field (WooCommerce renders it inside the cart form when wc_coupons_enabled()).
		$add_coupon_filter = ( 'yes' !== $show_coupon );
		if ( $add_coupon_filter ) {
			add_filter( 'woocommerce_coupons_enabled', '__return_false', 999999 );
		}

		// Toggle: cross-sells (WooCommerce honours this filter inside woocommerce_cross_sell_display()).
		$hide_cross_sells = ( 'yes' !== $show_cross_sells );
		if ( $hide_cross_sells ) {
			add_filter( 'woocommerce_cross_sells_total', '__return_zero', 999999 );
		}

		echo do_shortcode( '[woocommerce_cart]' ); // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce's own cart markup.

		if ( $hide_cross_sells ) {
			remove_filter( 'woocommerce_cross_sells_total', '__return_zero', 999999 );
		}
		if ( $add_coupon_filter ) {
			remove_filter( 'woocommerce_coupons_enabled', '__return_false', 999999 );
		}

		echo '</div>';
	}
}
