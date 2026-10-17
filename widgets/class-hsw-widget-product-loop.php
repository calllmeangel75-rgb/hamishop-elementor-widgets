<?php
/**
 * Widget: HamiShop Loop Item — one product card, fully customizable.
 *
 * Sources:
 *  - "loop"   : the current product inside any loop (Elementor Loop Grid,
 *               archive loops, single product related loops…)
 *  - "manual" : hand-picked products rendered as a responsive grid
 *  - "single" : one specific product (also used for editor previews)
 *
 * @package HamiShop
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;

/**
 * Loop Item widget.
 */
class HSW_Widget_Product_Loop extends \Elementor\Widget_Base {

	/**
	 * Widget id.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'hsw-loop-item';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'HamiShop Loop Item', 'hamishop-elementor-widgets' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-product-images';
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
		return [ 'hamishop', 'hami', 'product', 'loop', 'item', 'card', 'shop', 'woocommerce', 'محصول', 'کارتراه' ];
	}

	/**
	 * Frontend styles — only loaded when this widget is on the page.
	 *
	 * @return string[]
	 */
	public function get_style_depends() {
		return [ 'hsw-base', 'hsw-loop' ];
	}

	/**
	 * Custom controls: the async product chooser.
	 *
	 * @return string[]
	 */
	protected function register_controls() {
		$this->register_source_controls();
		$this->register_section_controls();
		$this->register_style_controls();
	}

	/**
	 * Content: source.
	 *
	 * @return void
	 */
	private function register_source_controls() {
		$this->start_controls_section(
			'section_source',
			[
				'label' => __( 'Products Source', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'product_source',
			[
				'label'   => __( 'Source', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'loop',
				'options' => [
					'loop'   => __( 'Current Loop Product', 'hamishop-elementor-widgets' ),
					'manual' => __( 'Manual Selection', 'hamishop-elementor-widgets' ),
					'single' => __( 'Single Product', 'hamishop-elementor-widgets' ),
				],
			]
		);

		$this->add_control(
			'product_ids',
			[
				'label'       => __( 'Products', 'hamishop-elementor-widgets' ),
				'type'        => 'hsw_products',
				'multiple'    => true,
				'label_block' => true,
				'condition'   => [ 'product_source' => 'manual' ],
			]
		);

		$this->add_control(
			'product_id',
			[
				'label'       => __( 'Product', 'hamishop-elementor-widgets' ),
				'type'        => 'hsw_products',
				'multiple'    => false,
				'label_block' => true,
				'condition'   => [ 'product_source' => 'single' ],
			]
		);

		$this->add_responsive_control(
			'grid_columns',
			[
				'label'           => __( 'Columns', 'hamishop-elementor-widgets' ),
				'type'            => Controls_Manager::SELECT,
				'default'         => '4',
				'tablet_default'  => '3',
				'mobile_default'  => '2',
				'options'         => [
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
					'5' => '5',
					'6' => '6',
				],
				'condition'       => [ 'product_source' => 'manual' ],
				'selectors'       => [
					'{{WRAPPER}} .hsw-loop-grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
				],
				'selectors_dictionary' => [],
			]
		);

		$this->add_responsive_control(
			'grid_gap',
			[
				'label'      => __( 'Grid Gap', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [
					'px' => [ 'min' => 0, 'max' => 80 ],
				],
				'default'    => [ 'size' => 20, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-loop-grid' => 'gap: {{SIZE}}{{UNIT}};',
				],
				'condition'  => [ 'product_source' => 'manual' ],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Content: card sections toggles.
	 *
	 * @return void
	 */
	private function register_section_controls() {
		// Badge.
		$this->start_controls_section(
			'section_badge',
			[
				'label' => __( 'Badge', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'show_badge',
			[
				'label'        => __( 'Sale Badge', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'hamishop-elementor-widgets' ),
				'label_off'    => __( 'Hide', 'hamishop-elementor-widgets' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'show_badge_new',
			[
				'label'        => __( 'New Badge', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'no',
			]
		);

		$this->add_control(
			'new_badge_days',
			[
				'label'     => __( 'Consider "New" for (days)', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 14,
				'min'       => 1,
				'max'       => 365,
				'condition' => [ 'show_badge_new' => 'yes' ],
			]
		);

		$this->add_control(
			'badge_text_new',
			[
				'label'       => __( 'New Badge Text', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => __( 'New', 'hamishop-elementor-widgets' ),
				'condition'   => [ 'show_badge_new' => 'yes' ],
			]
		);

		$this->end_controls_section();

		// Image.
		$this->start_controls_section(
			'section_image',
			[
				'label' => __( 'Image', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'show_image',
			[
				'label'        => __( 'Image', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'image_hover_swap',
			[
				'label'        => __( 'Swap Image on Hover', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => [ 'show_image' => 'yes' ],
			]
		);

		$this->end_controls_section();

		// Categories.
		$this->start_controls_section(
			'section_cats',
			[
				'label' => __( 'Categories', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'show_cats',
			[
				'label'        => __( 'Categories', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'cats_count',
			[
				'label'     => __( 'Max Categories', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 1,
				'min'       => 1,
				'max'       => 5,
				'condition' => [ 'show_cats' => 'yes' ],
			]
		);

		$this->end_controls_section();

		// Title.
		$this->start_controls_section(
			'section_title',
			[
				'label' => __( 'Title', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'show_title',
			[
				'label'        => __( 'Title', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'title_tag',
			[
				'label'     => __( 'HTML Tag', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'h3',
				'options'   => [
					'h1' => 'H1',
					'h2' => 'H2',
					'h3' => 'H3',
					'h4' => 'H4',
					'h5' => 'H5',
					'h6' => 'H6',
				],
				'condition' => [ 'show_title' => 'yes' ],
			]
		);

		$this->end_controls_section();

		// Rating.
		$this->start_controls_section(
			'section_rating',
			[
				'label' => __( 'Rating', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'show_rating',
			[
				'label'        => __( 'Rating', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'show_rating_count',
			[
				'label'        => __( 'Show Review Count', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => [ 'show_rating' => 'yes' ],
			]
		);

		$this->end_controls_section();

		// Excerpt.
		$this->start_controls_section(
			'section_excerpt',
			[
				'label' => __( 'Short Description', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'show_excerpt',
			[
				'label'        => __( 'Short Description', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'no',
			]
		);

		$this->add_control(
			'excerpt_length',
			[
				'label'     => __( 'Words Limit', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 12,
				'min'       => 1,
				'max'       => 60,
				'condition' => [ 'show_excerpt' => 'yes' ],
			]
		);

		$this->end_controls_section();

		// Price.
		$this->start_controls_section(
			'section_price',
			[
				'label' => __( 'Price', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'show_price',
			[
				'label'        => __( 'Price', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->end_controls_section();

		// Add to cart.
		$this->start_controls_section(
			'section_cart',
			[
				'label' => __( 'Add to Cart', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'show_cart',
			[
				'label'        => __( 'Add to Cart Button', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'cart_icon',
			[
				'label'     => __( 'Button Icon', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'none',
				'options'   => [
					'none' => __( 'None', 'hamishop-elementor-widgets' ),
					'cart' => __( 'Cart', 'hamishop-elementor-widgets' ),
					'bag'  => __( 'Bag', 'hamishop-elementor-widgets' ),
				],
				'condition' => [ 'show_cart' => 'yes' ],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Style controls for every card element.
	 *
	 * @return void
	 */
	private function register_style_controls() {
		$this->style_card();
		$this->style_badge();
		$this->style_image();
		$this->style_cats();
		$this->style_title();
		$this->style_rating();
		$this->style_excerpt();
		$this->style_price();
		$this->style_cart();
	}

	/**
	 * Card frame styles.
	 *
	 * @return void
	 */
	private function style_card() {
		$this->start_controls_section(
			'style_section_card',
			[
				'label' => __( 'Card', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'card_layout',
			[
				'label'   => __( 'Layout', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'vertical',
				'options' => [
					'vertical'   => __( 'Vertical', 'hamishop-elementor-widgets' ),
					'horizontal' => __( 'Horizontal', 'hamishop-elementor-widgets' ),
				],
				'prefix_class' => 'hsw-layout-',
			]
		);

		$this->add_control(
			'card_bg',
			[
				'label'     => __( 'Background Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .hsw-card' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .hsw-card',
			]
		);

		$this->add_responsive_control(
			'card_radius',
			[
				'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-card' => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'card_padding',
			[
				'label'      => __( 'Padding', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
				'default'    => [ 'size' => 16, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-card' => 'padding: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'card_shadow',
				'selector' => '{{WRAPPER}} .hsw-card',
			]
		);

		$this->add_control(
			'heading_card_hover',
			[
				'label'     => __( 'Hover', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'card_hover_bg',
			[
				'label'     => __( 'Hover Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .hsw-card:hover' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'card_hover_lift',
			[
				'label'        => __( 'Lift on Hover', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'card_hover_lift_size',
			[
				'label'      => __( 'Lift Amount (px)', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'default'    => [ 'size' => 6, 'unit' => 'px' ],
				'condition'  => [ 'card_hover_lift' => 'yes' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-card:hover' => 'transform: translateY(calc(-1 * {{SIZE}}px));',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Badge styles.
	 *
	 * @return void
	 */
	private function style_badge() {
		$this->start_controls_section(
			'style_section_badge',
			[
				'label'     => __( 'Badge', 'hamishop-elementor-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_badge' => 'yes' ],
			]
		);

		$this->add_responsive_control(
			'badge_pos_x',
			[
				'label'   => __( 'Horizontal Position', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::CHOOSE,
				'default' => 'start',
				'options' => [
					'start' => [ 'title' => __( 'Start', 'hamishop-elementor-widgets' ), 'icon' => 'eicon-h-align-left' ],
					'end'   => [ 'title' => __( 'End', 'hamishop-elementor-widgets' ), 'icon' => 'eicon-h-align-right' ],
				],
				'selectors_dictionary' => [
					'start' => 'inset-inline-start: 12px; inset-inline-end: auto;',
					'end'   => 'inset-inline-end: 12px; inset-inline-start: auto;',
				],
				'selectors' => [
					'{{WRAPPER}} .hsw-card__badge' => '{{VALUE}}',
				],
			]
		);

		$this->add_responsive_control(
			'badge_pos_y',
			[
				'label'   => __( 'Vertical Position', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::CHOOSE,
				'default' => 'top',
				'options' => [
					'top'    => [ 'title' => __( 'Top', 'hamishop-elementor-widgets' ), 'icon' => 'eicon-v-align-top' ],
					'bottom' => [ 'title' => __( 'Bottom', 'hamishop-elementor-widgets' ), 'icon' => 'eicon-v-align-bottom' ],
				],
				'selectors_dictionary' => [
					'top'    => 'top: 12px; bottom: auto;',
					'bottom' => 'bottom: 12px; top: auto;',
				],
				'selectors' => [
					'{{WRAPPER}} .hsw-card__badge' => '{{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'badge_typography',
				'selector' => '{{WRAPPER}} .hsw-card__badge',
			]
		);

		$this->start_controls_tabs( 'badge_tabs' );

		$this->start_controls_tab(
			'badge_tab_sale',
			[
				'label' => __( 'Sale', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'badge_sale_color',
			[
				'label'     => __( 'Text Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [
					'{{WRAPPER}} .hsw-card__badge--sale' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'badge_sale_bg',
			[
				'label'     => __( 'Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#C0392B',
				'selectors' => [
					'{{WRAPPER}} .hsw-card__badge--sale' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'badge_tab_new',
			[
				'label' => __( 'New', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'badge_new_color',
			[
				'label'     => __( 'Text Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#1F2937',
				'selectors' => [
					'{{WRAPPER}} .hsw-card__badge--new' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'badge_new_bg',
			[
				'label'     => __( 'Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FDE68A',
				'selectors' => [
					'{{WRAPPER}} .hsw-card__badge--new' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'badge_radius',
			[
				'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'default'    => [ 'size' => 6, 'unit' => 'px' ],
				'separator'  => 'before',
				'selectors'  => [
					'{{WRAPPER}} .hsw-card__badge' => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'badge_padding',
			[
				'label'      => __( 'Padding', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'default'    => [ 'size' => 4, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-card__badge' => 'padding: {{SIZE}}{{UNIT}} 12px; padding-inline: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Image styles.
	 *
	 * @return void
	 */
	private function style_image() {
		$this->start_controls_section(
			'style_section_image',
			[
				'label'     => __( 'Image', 'hamishop-elementor-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_image' => 'yes' ],
			]
		);

		$this->add_control(
			'image_ratio',
			[
				'label'   => __( 'Aspect Ratio', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '1/1',
				'options' => [
					'auto' => __( 'Auto', 'hamishop-elementor-widgets' ),
					'1/1'  => __( '1:1', 'hamishop-elementor-widgets' ),
					'3/4'  => __( '3:4', 'hamishop-elementor-widgets' ),
					'4/3'  => __( '4:3', 'hamishop-elementor-widgets' ),
					'16/9' => __( '16:9', 'hamishop-elementor-widgets' ),
				],
				'selectors_dictionary' => [
					'auto' => 'aspect-ratio: auto; height: auto;',
				],
				'selectors' => [
					'{{WRAPPER}} .hsw-card__media' => 'aspect-ratio: {{VALUE}}; height: auto;',
				],
			]
		);

		$this->add_control(
			'image_hover_zoom',
			[
				'label'        => __( 'Zoom on Hover', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_responsive_control(
			'image_radius',
			[
				'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-card__media' => 'border-radius: {{SIZE}}{{UNIT}}; overflow: hidden;',
				],
			]
		);

		$this->add_responsive_control(
			'image_spacing',
			[
				'label'      => __( 'Bottom Spacing', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
				'default'    => [ 'size' => 14, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-card__media' => 'margin-block-end: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Categories styles.
	 *
	 * @return void
	 */
	private function style_cats() {
		$this->start_controls_section(
			'style_section_cats',
			[
				'label'     => __( 'Categories', 'hamishop-elementor-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_cats' => 'yes' ],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'cats_typography',
				'selector' => '{{WRAPPER}} .hsw-card__cat',
			]
		);

		$this->add_control(
			'cats_color',
			[
				'label'     => __( 'Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#9CA3AF',
				'selectors' => [
					'{{WRAPPER}} .hsw-card__cat' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'cats_hover_color',
			[
				'label'     => __( 'Hover Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .hsw-card__cat:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'cats_spacing',
			[
				'label'      => __( 'Bottom Spacing', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
				'default'    => [ 'size' => 4, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-card__cats' => 'margin-block-end: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Title styles.
	 *
	 * @return void
	 */
	private function style_title() {
		$this->start_controls_section(
			'style_section_title',
			[
				'label'     => __( 'Title', 'hamishop-elementor-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_title' => 'yes' ],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .hsw-card__title',
			]
		);

		$this->add_control(
			'title_color',
			[
				'label'     => __( 'Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#1F2937',
				'selectors' => [
					'{{WRAPPER}} .hsw-card__title a' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'title_hover_color',
			[
				'label'     => __( 'Hover Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .hsw-card__title a:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'title_spacing',
			[
				'label'      => __( 'Bottom Spacing', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
				'default'    => [ 'size' => 6, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-card__title' => 'margin-block-end: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'title_lines',
			[
				'label'   => __( 'Line Clamp', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '2',
				'options' => [
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'0' => __( 'Unlimited', 'hamishop-elementor-widgets' ),
				],
				'selectors_dictionary' => [
					'0' => 'none',
					'1' => '1',
					'2' => '2',
					'3' => '3',
				],
				'selectors' => [
					'{{WRAPPER}} .hsw-card__title a' => 'display: -webkit-box; -webkit-line-clamp: {{VALUE}}; -webkit-box-orient: vertical; overflow: hidden;',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Rating styles.
	 *
	 * @return void
	 */
	private function style_rating() {
		$this->start_controls_section(
			'style_section_rating',
			[
				'label'     => __( 'Rating', 'hamishop-elementor-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_rating' => 'yes' ],
			]
		);

		$this->add_control(
			'rating_star_color',
			[
				'label'     => __( 'Stars Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#F59E0B',
				'selectors' => [
					'{{WRAPPER}} .hsw-rating__fill' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'rating_base_color',
			[
				'label'     => __( 'Empty Stars Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#D1D5DB',
				'selectors' => [
					'{{WRAPPER}} .hsw-rating__base' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'rating_size',
			[
				'label'      => __( 'Star Size', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 8, 'max' => 32 ] ],
				'default'    => [ 'size' => 14, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-rating' => 'font-size: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'rating_count_typography',
				'selector' => '{{WRAPPER}} .hsw-rating__count',
				'condition'=> [ 'show_rating_count' => 'yes' ],
			]
		);

		$this->add_control(
			'rating_count_color',
			[
				'label'     => __( 'Count Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#9CA3AF',
				'selectors' => [
					'{{WRAPPER}} .hsw-rating__count' => 'color: {{VALUE}};',
				],
				'condition' => [ 'show_rating_count' => 'yes' ],
			]
		);

		$this->add_responsive_control(
			'rating_spacing',
			[
				'label'      => __( 'Bottom Spacing', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
				'default'    => [ 'size' => 6, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-card__rating' => 'margin-block-end: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Excerpt styles.
	 *
	 * @return void
	 */
	private function style_excerpt() {
		$this->start_controls_section(
			'style_section_excerpt',
			[
				'label'     => __( 'Short Description', 'hamishop-elementor-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_excerpt' => 'yes' ],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'excerpt_typography',
				'selector' => '{{WRAPPER}} .hsw-card__excerpt',
			]
		);

		$this->add_control(
			'excerpt_color',
			[
				'label'     => __( 'Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#6B7280',
				'selectors' => [
					'{{WRAPPER}} .hsw-card__excerpt' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'excerpt_lines',
			[
				'label'   => __( 'Line Clamp', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '2',
				'options' => [
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'0' => __( 'Unlimited', 'hamishop-elementor-widgets' ),
				],
				'selectors' => [
					'{{WRAPPER}} .hsw-card__excerpt' => 'display: -webkit-box; -webkit-line-clamp: {{VALUE}}; -webkit-box-orient: vertical; overflow: hidden;',
				],
			]
		);

		$this->add_responsive_control(
			'excerpt_spacing',
			[
				'label'      => __( 'Bottom Spacing', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
				'default'    => [ 'size' => 6, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-card__excerpt' => 'margin-block-end: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Price styles.
	 *
	 * @return void
	 */
	private function style_price() {
		$this->start_controls_section(
			'style_section_price',
			[
				'label'     => __( 'Price', 'hamishop-elementor-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_price' => 'yes' ],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'price_typography',
				'selector' => '{{WRAPPER}} .hsw-card__price',
			]
		);

		$this->add_control(
			'price_color',
			[
				'label'     => __( 'Price Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					'{{WRAPPER}} .hsw-card__price' => 'color: {{VALUE}};',
					'{{WRAPPER}} .hsw-card__price .woocommerce-Price-amount' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'price_sale_color',
			[
				'label'     => __( 'Sale Price Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#C0392B',
				'selectors' => [
					'{{WRAPPER}} .hsw-card__price ins .woocommerce-Price-amount' => 'color: {{VALUE}};',
					'{{WRAPPER}} .hsw-card__price ins' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'price_regular_color',
			[
				'label'     => __( 'Regular Price Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#9CA3AF',
				'selectors' => [
					'{{WRAPPER}} .hsw-card__price del .woocommerce-Price-amount' => 'color: {{VALUE}};',
					'{{WRAPPER}} .hsw-card__price del' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'price_spacing',
			[
				'label'      => __( 'Bottom Spacing', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
				'default'    => [ 'size' => 10, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-card__price' => 'margin-block-end: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Add-to-cart styles.
	 *
	 * @return void
	 */
	private function style_cart() {
		$this->start_controls_section(
			'style_section_cart',
			[
				'label'     => __( 'Add to Cart', 'hamishop-elementor-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_cart' => 'yes' ],
			]
		);

		$this->add_control(
			'cart_full_width',
			[
				'label'        => __( 'Full Width', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'selectors'    => [
					'{{WRAPPER}} .hsw-card__actions .button' => 'width: 100%;',
				],
			]
		);

		$this->add_responsive_control(
			'cart_align',
			[
				'label'   => __( 'Alignment', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::CHOOSE,
				'default' => 'stretch',
				'options' => [
					'start'   => [ 'title' => __( 'Start', 'hamishop-elementor-widgets' ), 'icon' => 'eicon-h-align-right' ],
					'center'  => [ 'title' => __( 'Center', 'hamishop-elementor-widgets' ), 'icon' => 'eicon-align-center-h' ],
					'stretch' => [ 'title' => __( 'Stretch', 'hamishop-elementor-widgets' ), 'icon' => 'eicon-align-stretch-h' ],
				],
				'selectors_dictionary' => [
					'start'   => 'justify-content: flex-start;',
					'center'  => 'justify-content: center;',
					'stretch' => 'justify-content: stretch;',
				],
				'selectors' => [
					'{{WRAPPER}} .hsw-card__actions' => 'display: flex; {{VALUE}}',
				],
				'condition' => [ 'cart_full_width!' => 'yes' ],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'cart_typography',
				'selector' => '{{WRAPPER}} .hsw-card__actions .button',
			]
		);

		$this->start_controls_tabs( 'cart_tabs' );

		$this->start_controls_tab(
			'cart_tab_normal',
			[
				'label' => __( 'Normal', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'cart_text_color',
			[
				'label'     => __( 'Text Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [
					'{{WRAPPER}} .hsw-card__actions .button' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'cart_bg_color',
			[
				'label'     => __( 'Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					'{{WRAPPER}} .hsw-card__actions .button' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'cart_border',
				'selector' => '{{WRAPPER}} .hsw-card__actions .button',
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'cart_tab_hover',
			[
				'label' => __( 'Hover', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'cart_hover_text_color',
			[
				'label'     => __( 'Text Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .hsw-card__actions .button:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'cart_hover_bg_color',
			[
				'label'     => __( 'Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#C0392B',
				'selectors' => [
					'{{WRAPPER}} .hsw-card__actions .button:hover' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'cart_hover_border_color',
			[
				'label'     => __( 'Border Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .hsw-card__actions .button:hover' => 'border-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'cart_radius',
			[
				'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'default'    => [ 'size' => 8, 'unit' => 'px' ],
				'separator'  => 'before',
				'selectors'  => [
					'{{WRAPPER}} .hsw-card__actions .button' => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'cart_padding',
			[
				'label'      => __( 'Padding', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'default'    => [ 'size' => 10, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-card__actions .button' => 'padding: {{SIZE}}{{UNIT}} 20px; padding-inline: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Render output.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$source   = isset( $settings['product_source'] ) ? $settings['product_source'] : 'loop';

		$products = HSW_Helpers::get_products_from_settings( $settings );

		if ( empty( $products ) ) {
			echo '<div class="hsw-widget-root">';
			echo HSW_Helpers::editor_note( __( 'Select a product to display its card.', 'hamishop-elementor-widgets' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</div>';
			return;
		}

		if ( 'manual' === $source && count( $products ) > 1 ) {
			echo '<div class="hsw-widget-root"><div class="hsw-loop-grid">';
			foreach ( $products as $product ) {
				if ( $product && $product->is_visible() ) {
					HSW_Card_Renderer::render( $product, $settings );
				}
			}
			echo '</div></div>';
			return;
		}

		echo '<div class="hsw-widget-root">';
		foreach ( $products as $product ) {
			HSW_Card_Renderer::render( $product, $settings );
		}
		echo '</div>';
	}
}
