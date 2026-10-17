<?php
/**
 * Widget: HamiShop Shop Archive — WooCommerce product archive grid.
 *
 * Modes:
 *  - "archive" : renders the global $wp_query products (Elementor Product
 *                Archive theme templates — shop page, product cats, tags…)
 *  - "custom"  : builds its own WP_Query — usable anywhere.
 *
 * Pagination: numbered pages, "Load More" (custom mode, REST-backed) or none.
 *
 * @package HamiShop
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;

/**
 * Shop Archive widget.
 */
class HSW_Widget_Shop_Archive extends \Elementor\Widget_Base {

	/**
	 * Widget id.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'hsw-shop-archive';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'HamiShop Archive', 'hamishop-elementor-widgets' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-products';
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
		return [ 'hamishop', 'hamisys', 'hami', 'shop', 'archive', 'products', 'grid', 'فروشگاه', 'آرشیو' ];
	}

	/**
	 * Frontend styles — only loaded when this widget is on the page.
	 *
	 * @return string[]
	 */
	public function get_style_depends() {
		return [ 'hsw-base', 'hsw-archive' ];
	}

	/**
	 * Frontend scripts (load-more handler).
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		return [ 'hsw-frontend' ];
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->register_query_controls();
		$this->register_toolbar_controls();
		$this->register_pagination_controls();
		$this->register_style_controls();
	}

	/**
	 * Content: query source.
	 *
	 * @return void
	 */
	private function register_query_controls() {
		$this->start_controls_section(
			'section_query',
			[
				'label' => __( 'Query', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'archive_mode',
			[
				'label'   => __( 'Mode', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'archive',
				'options' => [
					'archive' => __( 'Current Archive', 'hamishop-elementor-widgets' ),
					'custom'  => __( 'Custom Query', 'hamishop-elementor-widgets' ),
				],
				'description' => __( 'Current Archive renders the page\'s own product query (count comes from the theme / WooCommerce settings). Custom Query works anywhere.', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'query_cats',
			[
				'label'       => __( 'Product Categories', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->get_term_options( 'product_cat' ),
				'condition'   => [ 'archive_mode' => 'custom' ],
			]
		);

		$this->add_control(
			'query_tags',
			[
				'label'       => __( 'Product Tags', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->get_term_options( 'product_tag' ),
				'condition'   => [ 'archive_mode' => 'custom' ],
			]
		);

		$this->add_control(
			'query_on_sale',
			[
				'label'        => __( 'Only On-Sale Products', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'no',
				'condition'    => [ 'archive_mode' => 'custom' ],
			]
		);

		$this->add_control(
			'query_featured',
			[
				'label'        => __( 'Only Featured Products', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'no',
				'condition'    => [ 'archive_mode' => 'custom' ],
			]
		);

		$this->add_control(
			'query_per_page',
			[
				'label'     => __( 'Products Per Page', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 12,
				'min'       => 1,
				'max'       => 48,
				'condition' => [ 'archive_mode' => 'custom' ],
			]
		);

		$this->add_control(
			'query_orderby',
			[
				'label'     => __( 'Order By', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'menu_order',
				'options'   => [
					'menu_order' => __( 'Default', 'hamishop-elementor-widgets' ),
					'date'       => __( 'Date', 'hamishop-elementor-widgets' ),
					'title'      => __( 'Title', 'hamishop-elementor-widgets' ),
					'rand'       => __( 'Random', 'hamishop-elementor-widgets' ),
				],
				'condition' => [ 'archive_mode' => 'custom' ],
			]
		);

		$this->add_control(
			'query_order',
			[
				'label'     => __( 'Order', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'desc',
				'options'   => [
					'asc'  => __( 'Ascending', 'hamishop-elementor-widgets' ),
					'desc' => __( 'Descending', 'hamishop-elementor-widgets' ),
				],
				'condition' => [ 'archive_mode' => 'custom' ],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Content: archive toolbar + category pills.
	 *
	 * @return void
	 */
	private function register_toolbar_controls() {
		$this->start_controls_section(
			'section_toolbar',
			[
				'label' => __( 'Toolbar', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'show_title',
			[
				'label'        => __( 'Archive Title', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => [ 'archive_mode' => 'archive' ],
			]
		);

		$this->add_control(
			'title_tag',
			[
				'label'     => __( 'Title HTML Tag', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'h1',
				'options'   => [
					'h1' => 'H1',
					'h2' => 'H2',
					'h3' => 'H3',
				],
				'condition' => [ 'show_title' => 'yes' ],
			]
		);

		$this->add_control(
			'show_result_count',
			[
				'label'        => __( 'Result Count', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => [ 'archive_mode' => 'archive' ],
			]
		);

		$this->add_control(
			'show_ordering',
			[
				'label'        => __( 'Ordering Dropdown', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => [ 'archive_mode' => 'archive' ],
			]
		);

		$this->add_control(
			'show_cats_bar',
			[
				'label'        => __( 'Category Filter Pills', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'no',
				'description'  => __( 'Links to the category archive pages. Works in both modes.', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'cats_bar_terms',
			[
				'label'       => __( 'Categories to Show', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->get_term_options( 'product_cat' ),
				'description' => __( 'Leave empty to show the first five product categories.', 'hamishop-elementor-widgets' ),
				'condition'   => [ 'show_cats_bar' => 'yes' ],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Content: pagination.
	 *
	 * @return void
	 */
	private function register_pagination_controls() {
		$this->start_controls_section(
			'section_pagination',
			[
				'label' => __( 'Pagination', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'pagination_type',
			[
				'label'   => __( 'Type', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'numbers',
				'options' => [
					'numbers'   => __( 'Numbers', 'hamishop-elementor-widgets' ),
					'load_more' => __( 'Load More Button', 'hamishop-elementor-widgets' ),
					'none'      => __( 'None', 'hamishop-elementor-widgets' ),
				],
			]
		);

		$this->add_control(
			'load_more_text',
			[
				'label'       => __( 'Button Text', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Load More', 'hamishop-elementor-widgets' ),
				'condition'   => [ 'pagination_type' => 'load_more' ],
				'description' => __( 'Load More works with Custom Query; in Current Archive mode it falls back to numbered pagination.', 'hamishop-elementor-widgets' ),
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Style controls dispatcher.
	 *
	 * @return void
	 */
	private function register_style_controls() {
		$this->style_toolbar();
		$this->style_cats_bar();
		$this->style_grid();
		$this->style_pagination();
		$this->style_load_more();
	}

	/**
	 * Toolbar styles.
	 *
	 * @return void
	 */
	private function style_toolbar() {
		$this->start_controls_section(
			'style_section_toolbar',
			[
				'label' => __( 'Toolbar', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'archive_title_typography',
				'selector' => '{{WRAPPER}} .hsw-archive__title',
			]
		);

		$this->add_control(
			'archive_title_color',
			[
				'label'     => __( 'Title Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					'{{WRAPPER}} .hsw-archive__title' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'heading_toolbar_meta',
			[
				'label'     => __( 'Result Count & Ordering', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'toolbar_meta_typography',
				'selector' => '{{WRAPPER}} .woocommerce-result-count, {{WRAPPER}} .woocommerce-ordering select',
			]
		);

		$this->add_control(
			'toolbar_meta_color',
			[
				'label'     => __( 'Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#6B7280',
				'selectors' => [
					'{{WRAPPER}} .woocommerce-result-count'    => 'color: {{VALUE}};',
					'{{WRAPPER}} .woocommerce-ordering select' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Category pills styles.
	 *
	 * @return void
	 */
	private function style_cats_bar() {
		$this->start_controls_section(
			'style_section_cats_bar',
			[
				'label'     => __( 'Category Pills', 'hamishop-elementor-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_cats_bar' => 'yes' ],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'cats_bar_typography',
				'selector' => '{{WRAPPER}} .hsw-archive__cat',
			]
		);

		$this->start_controls_tabs( 'cats_bar_tabs' );

		$this->start_controls_tab(
			'cats_bar_tab_normal',
			[
				'label' => __( 'Normal', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'cats_bar_color',
			[
				'label'     => __( 'Text Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#374151',
				'selectors' => [
					'{{WRAPPER}} .hsw-archive__cat' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'cats_bar_bg',
			[
				'label'     => __( 'Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#F3F4F6',
				'selectors' => [
					'{{WRAPPER}} .hsw-archive__cat' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'cats_bar_border',
				'selector' => '{{WRAPPER}} .hsw-archive__cat',
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'cats_bar_tab_hover',
			[
				'label' => __( 'Hover', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'cats_bar_hover_color',
			[
				'label'     => __( 'Text Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [
					'{{WRAPPER}} .hsw-archive__cat:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'cats_bar_hover_bg',
			[
				'label'     => __( 'Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					'{{WRAPPER}} .hsw-archive__cat:hover' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'cats_bar_radius',
			[
				'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'separator'  => 'before',
				'selectors'  => [
					'{{WRAPPER}} .hsw-archive__cat' => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'cats_bar_padding',
			[
				'label'      => __( 'Padding (Inline)', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
				'default'    => [ 'size' => 14, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-archive__cat' => 'padding-block: 6px; padding-inline: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'cats_bar_gap',
			[
				'label'      => __( 'Gap', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
				'default'    => [ 'size' => 8, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-archive__cats' => 'gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Grid styles.
	 *
	 * @return void
	 */
	private function style_grid() {
		$this->start_controls_section(
			'style_section_grid',
			[
				'label' => __( 'Grid', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'grid_columns',
			[
				'label'          => __( 'Columns', 'hamishop-elementor-widgets' ),
				'type'           => Controls_Manager::SELECT,
				'default'        => '4',
				'tablet_default' => '3',
				'mobile_default' => '2',
				'options'        => [
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
					'5' => '5',
					'6' => '6',
				],
				'selectors'      => [
					'{{WRAPPER}} .hsw-archive__grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
				],
			]
		);

		$this->add_responsive_control(
			'grid_gap',
			[
				'label'      => __( 'Grid Gap', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 80 ] ],
				'default'    => [ 'size' => 20, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-archive__grid' => 'gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Numbered pagination styles.
	 *
	 * @return void
	 */
	private function style_pagination() {
		$this->start_controls_section(
			'style_section_pagination',
			[
				'label' => __( 'Pagination', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'pagination_typography',
				'selector' => '{{WRAPPER}} .hsw-pagination .page-numbers li .page-numbers',
			]
		);

		$this->add_responsive_control(
			'pagination_gap',
			[
				'label'      => __( 'Gap', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'default'    => [ 'size' => 6, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-pagination .page-numbers' => 'gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->start_controls_tabs( 'pagination_tabs' );

		$this->start_controls_tab(
			'pagination_tab_normal',
			[
				'label' => __( 'Normal', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'pagination_color',
			[
				'label'     => __( 'Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					'{{WRAPPER}} .hsw-pagination .page-numbers li .page-numbers' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'pagination_bg',
			[
				'label'     => __( 'Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#F3F4F6',
				'selectors' => [
					'{{WRAPPER}} .hsw-pagination .page-numbers li .page-numbers' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'pagination_border',
				'selector' => '{{WRAPPER}} .hsw-pagination .page-numbers li .page-numbers',
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'pagination_tab_current',
			[
				'label' => __( 'Current', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'pagination_current_color',
			[
				'label'     => __( 'Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [
					'{{WRAPPER}} .hsw-pagination .page-numbers li .page-numbers.current' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'pagination_current_bg',
			[
				'label'     => __( 'Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					'{{WRAPPER}} .hsw-pagination .page-numbers li .page-numbers.current' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'pagination_tab_hover',
			[
				'label' => __( 'Hover', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'pagination_hover_color',
			[
				'label'     => __( 'Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .hsw-pagination .page-numbers li a.page-numbers:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'pagination_hover_bg',
			[
				'label'     => __( 'Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .hsw-pagination .page-numbers li a.page-numbers:hover' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'pagination_radius',
			[
				'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'default'    => [ 'size' => 8, 'unit' => 'px' ],
				'separator'  => 'before',
				'selectors'  => [
					'{{WRAPPER}} .hsw-pagination .page-numbers li .page-numbers' => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'pagination_padding',
			[
				'label'      => __( 'Padding (Inline)', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
				'default'    => [ 'size' => 14, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-pagination .page-numbers li .page-numbers' => 'padding-block: 8px; padding-inline: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Load-more button styles.
	 *
	 * @return void
	 */
	private function style_load_more() {
		$this->start_controls_section(
			'style_section_load_more',
			[
				'label'     => __( 'Load More Button', 'hamishop-elementor-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'pagination_type' => 'load_more' ],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'load_more_typography',
				'selector' => '{{WRAPPER}} .hsw-load-more__button',
			]
		);

		$this->start_controls_tabs( 'load_more_tabs' );

		$this->start_controls_tab(
			'load_more_tab_normal',
			[
				'label' => __( 'Normal', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'load_more_color',
			[
				'label'     => __( 'Text Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [
					'{{WRAPPER}} .hsw-load-more__button' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'load_more_bg',
			[
				'label'     => __( 'Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [
					'{{WRAPPER}} .hsw-load-more__button' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'load_more_border',
				'selector' => '{{WRAPPER}} .hsw-load-more__button',
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'load_more_tab_hover',
			[
				'label' => __( 'Hover', 'hamishop-elementor-widgets' ),
			]
		);

		$this->add_control(
			'load_more_hover_color',
			[
				'label'     => __( 'Text Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [
					'{{WRAPPER}} .hsw-load-more__button:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'load_more_hover_bg',
			[
				'label'     => __( 'Background', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#C0392B',
				'selectors' => [
					'{{WRAPPER}} .hsw-load-more__button:hover' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'load_more_hover_border_color',
			[
				'label'     => __( 'Border Color', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .hsw-load-more__button:hover' => 'border-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'load_more_radius',
			[
				'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'default'    => [ 'size' => 8, 'unit' => 'px' ],
				'separator'  => 'before',
				'selectors'  => [
					'{{WRAPPER}} .hsw-load-more__button' => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'load_more_padding',
			[
				'label'      => __( 'Padding (Inline)', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
				'default'    => [ 'size' => 32, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .hsw-load-more__button' => 'padding-block: 12px; padding-inline: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'load_more_shadow',
				'selector' => '{{WRAPPER}} .hsw-load-more__button',
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
		$mode     = isset( $settings['archive_mode'] ) ? $settings['archive_mode'] : 'archive';

		if ( 'archive' === $mode ) {
			$this->render_archive( $settings );
			return;
		}

		$this->render_custom( $settings );
	}

	/**
	 * Mode "archive": the global product archive query.
	 *
	 * @param array $settings Widget settings.
	 * @return void
	 */
	private function render_archive( array $settings ) {
		global $wp_query;

		// Only meaningful on a WooCommerce product archive.
		if ( ! $this->is_product_archive_context() ) {
			echo '<div class="hsw-widget-root hsw-archive">';
			echo HSW_Helpers::editor_note( __( 'Place this widget on a Product Archive template, or switch to Custom Query.', 'hamishop-elementor-widgets' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
			echo '</div>';
			return;
		}

		if ( ! $wp_query->have_posts() && ! HSW_Helpers::is_editor() ) {
			return; // Nothing to render on the frontend.
		}

		echo '<div class="hsw-widget-root hsw-archive">';

		if ( ! $wp_query->have_posts() ) {
			echo HSW_Helpers::editor_note( __( 'No products found in the current archive.', 'hamishop-elementor-widgets' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
			echo '</div>';
			return;
		}

		$this->render_cats_bar( $settings );
		$this->render_toolbar( $settings );
		$this->render_grid( $wp_query, $settings );
		$this->render_pagination( 'archive', $wp_query, $settings );

		echo '</div>';
	}

	/**
	 * Mode "custom": own WP_Query — works anywhere.
	 *
	 * @param array $settings Widget settings.
	 * @return void
	 */
	private function render_custom( array $settings ) {
		$args  = $this->build_query_args( $settings );
		$query = new \WP_Query( $args );

		echo '<div class="hsw-widget-root hsw-archive">';

		$this->render_cats_bar( $settings );
		$this->render_grid( $query, $settings );
		$this->render_pagination( 'custom', $query, $settings, $args );

		echo '</div>';
	}

	/**
	 * Toolbar: archive title, result count, ordering dropdown.
	 *
	 * @param array $settings Widget settings.
	 * @return void
	 */
	private function render_toolbar( array $settings ) {
		$show_title = isset( $settings['show_title'] ) && 'yes' === $settings['show_title'];
		$show_count = isset( $settings['show_result_count'] ) && 'yes' === $settings['show_result_count'];
		$show_order = isset( $settings['show_ordering'] ) && 'yes' === $settings['show_ordering'];

		$title = $show_title ? (string) woocommerce_page_title( false ) : '';

		if ( '' === $title && ! $show_count && ! $show_order ) {
			return;
		}

		echo '<div class="hsw-archive__toolbar">';

		if ( '' !== $title ) {
			$tag = isset( $settings['title_tag'] ) ? $settings['title_tag'] : 'h1';
			$tag = in_array( $tag, [ 'h1', 'h2', 'h3' ], true ) ? $tag : 'h1';

			echo '<' . tag_escape( $tag ) . ' class="hsw-archive__title">' . esc_html( $title ) . '</' . tag_escape( $tag ) . '>';
		}

		if ( $show_count || $show_order ) {
			echo '<div class="hsw-archive__meta">';

			if ( $show_count ) {
				ob_start();
				wc_get_template( 'loop/result-count.php' );
				$count_html = ob_get_clean();

				echo $count_html; // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce template generates escaped markup.
			}

			if ( $show_order ) {
				woocommerce_catalog_ordering(); // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce generates escaped markup.
			}

			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Category filter pills — links to term archive pages (both modes).
	 *
	 * @param array $settings Widget settings.
	 * @return void
	 */
	private function render_cats_bar( array $settings ) {
		if ( ! isset( $settings['show_cats_bar'] ) || 'yes' !== $settings['show_cats_bar'] ) {
			return;
		}

		$chosen = isset( $settings['cats_bar_terms'] ) ? array_filter( array_map( 'absint', (array) $settings['cats_bar_terms'] ) ) : [];

		if ( ! empty( $chosen ) ) {
			$terms = get_terms(
				[
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
					'include'    => $chosen,
					'number'     => 50,
				]
			);
		} else {
			$terms = get_terms(
				[
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
					'number'     => 5,
				]
			);
		}

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		echo '<div class="hsw-archive__cats">';

		foreach ( $terms as $term ) {
			$link = get_term_link( $term );

			if ( is_wp_error( $link ) ) {
				continue;
			}

			echo '<a class="hsw-archive__cat" href="' . esc_url( $link ) . '">' . esc_html( $term->name ) . '</a>';
		}

		echo '</div>';
	}

	/**
	 * Shared products grid — one hsw-card per visible product.
	 *
	 * @param \WP_Query $query    Query to iterate.
	 * @param array     $settings Widget settings.
	 * @return void
	 */
	private function render_grid( $query, array $settings ) {
		$card_args = $this->card_args( $settings );

		echo '<div class="hsw-archive__grid">';

		$rendered = 0;

		while ( $query->have_posts() ) {
			$query->the_post();

			$product = wc_get_product( get_the_ID() );
			if ( $product && $product->is_visible() ) {
				HSW_Card_Renderer::render( $product, $card_args );
				$rendered++;
			}
		}

		wp_reset_postdata();

		if ( 0 === $rendered ) {
			echo HSW_Helpers::editor_note( __( 'No products match this query.', 'hamishop-elementor-widgets' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
		}

		echo '</div>';
	}

	/**
	 * Pagination dispatcher: numbers, load-more (custom only) or none.
	 *
	 * @param string     $mode       'archive' | 'custom'.
	 * @param \WP_Query  $query      The rendered query.
	 * @param array      $settings   Widget settings.
	 * @param array|null $query_args Raw custom-query args (load-more payload).
	 * @return void
	 */
	private function render_pagination( $mode, $query, array $settings, $query_args = null ) {
		$type = isset( $settings['pagination_type'] ) ? $settings['pagination_type'] : 'numbers';

		if ( 'none' === $type ) {
			return;
		}

		if ( 'load_more' === $type ) {
			if ( 'custom' === $mode && is_array( $query_args ) ) {
				$this->render_load_more( $query, $query_args, $settings );
				return;
			}

			// Archive mode: fall back to numbered pages.
		}

		$current = 'archive' === $mode ? max( 1, (int) get_query_var( 'paged' ) ) : max( 1, (int) $query->get( 'paged' ) );

		$links = paginate_links(
			[
				'total'     => (int) $query->max_num_pages,
				'current'   => $current,
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
				'type'      => 'list',
			]
		);

		if ( ! empty( $links ) ) {
			echo '<nav class="hsw-archive__pagination hsw-pagination">' . wp_kses_post( $links ) . '</nav>';
		}
	}

	/**
	 * REST-backed load-more button (custom mode only).
	 *
	 * @param \WP_Query $query      The rendered query.
	 * @param array     $query_args Raw custom-query args.
	 * @param array     $settings   Widget settings.
	 * @return void
	 */
	private function render_load_more( $query, array $query_args, array $settings ) {
		$store_args = $query_args;
		unset( $store_args['paged'] ); // The REST handler injects the page.

		$key = HSW_Rest::store(
			[
				'query_args' => $store_args,
				'card_args'  => $this->card_args( $settings ),
				'max_pages'  => (int) $query->max_num_pages,
			]
		);

		$text = isset( $settings['load_more_text'] ) && '' !== (string) $settings['load_more_text']
			? $settings['load_more_text']
			: __( 'Load More', 'hamishop-elementor-widgets' );

		echo '<div class="hsw-load-more"><button type="button" class="hsw-load-more__button" data-key="' . esc_attr( $key ) . '" data-page="1">' . esc_html( $text ) . '</button></div>';
	}

	/**
	 * Build the custom-mode WP_Query args.
	 *
	 * @param array $settings Widget settings.
	 * @return array
	 */
	private function build_query_args( array $settings ) {
		$orderby = isset( $settings['query_orderby'] ) ? $settings['query_orderby'] : 'menu_order';
		if ( ! in_array( $orderby, [ 'menu_order', 'date', 'title', 'rand' ], true ) ) {
			$orderby = 'menu_order';
		}

		$order = isset( $settings['query_order'] ) && 'asc' === $settings['query_order'] ? 'asc' : 'desc';

		$per_page = 12;
		if ( isset( $settings['query_per_page'] ) ) {
			$per_page = max( 1, min( 48, (int) $settings['query_per_page'] ) );
		}

		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'orderby'        => $orderby,
			'order'          => $order,
			'no_found_rows'  => false,
			'paged'          => max( 1, (int) get_query_var( 'paged' ) ),
		];

		$tax_query = [ 'relation' => 'AND' ];

		$cats = isset( $settings['query_cats'] ) ? array_filter( array_map( 'absint', (array) $settings['query_cats'] ) ) : [];
		if ( ! empty( $cats ) ) {
			$tax_query[] = [
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $cats,
				'operator' => 'IN',
			];
		}

		$tags = isset( $settings['query_tags'] ) ? array_filter( array_map( 'absint', (array) $settings['query_tags'] ) ) : [];
		if ( ! empty( $tags ) ) {
			$tax_query[] = [
				'taxonomy' => 'product_tag',
				'field'    => 'term_id',
				'terms'    => $tags,
				'operator' => 'IN',
			];
		}

		if ( isset( $settings['query_featured'] ) && 'yes' === $settings['query_featured'] ) {
			$tax_query[] = [
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => 'featured',
			];
		}

		// Always exclude products hidden from the catalogue.
		$tax_query[] = [
			'taxonomy' => 'product_visibility',
			'field'    => 'name',
			'terms'    => 'exclude-from-catalog',
			'operator' => 'NOT IN',
		];

		$args['tax_query'] = $tax_query;

		if ( isset( $settings['query_on_sale'] ) && 'yes' === $settings['query_on_sale'] ) {
			$args['post__in'] = array_merge( (array) wc_get_product_ids_on_sale(), [ 0 ] );
		}

		return $args;
	}

	/**
	 * Settings copy handed to HSW_Card_Renderer.
	 *
	 * This widget reuses the setting names "show_title" / "title_tag" for the
	 * ARCHIVE heading — they must not leak into the product cards (otherwise
	 * disabling the archive title would hide every card title, and the card
	 * titles would render as h1). Card sections use the renderer defaults.
	 *
	 * @param array $settings Widget settings.
	 * @return array
	 */
	private function card_args( array $settings ) {
		$card_args = $settings;

		$card_args['show_title'] = 'yes';
		$card_args['title_tag']  = 'h3';

		return $card_args;
	}

	/**
	 * id => name options for a SELECT2 term control (empty on failure).
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return array
	 */
	private function get_term_options( $taxonomy ) {
		$options = [];

		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 200,
			]
		);

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[ $term->term_id ] = $term->name;
			}
		}

		return $options;
	}

	/**
	 * Is the current page a WooCommerce product archive?
	 *
	 * @return bool
	 */
	private function is_product_archive_context() {
		return is_post_type_archive( 'product' )
			|| is_tax( get_object_taxonomies( 'product' ) )
			|| is_shop();
	}
}
