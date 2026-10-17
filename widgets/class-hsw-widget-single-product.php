<?php
/**
 * Widget: HamiShop Single Product — the full product detail layout.
 *
 * Sources:
 *  - "current" : the product of the current request (single product pages
 *                and Elementor Single Product templates)
 *  - "single"  : one hand-picked product (also used for editor previews)
 *
 * Layout: gallery + summary in a responsive two-column grid (start / end /
 * stacked positions), WooCommerce-native add-to-cart form, custom tabs
 * (description / attributes / reviews) and related & upsell product grids
 * rendered through the shared card renderer (hsw-card DOM contract).
 *
 * @package HamiShop
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;

/**
 * Single Product widget.
 */
class HSW_Widget_Single_Product extends \Elementor\Widget_Base {

        /**
         * Widget id.
         *
         * @return string
         */
        public function get_name() {
                return 'hsw-single-product';
        }

        /**
         * Widget title.
         *
         * @return string
         */
        public function get_title() {
                return __( 'HamiShop Single Product', 'hamishop-elementor-widgets' );
        }

        /**
         * Widget icon.
         *
         * @return string
         */
        public function get_icon() {
                return 'eicon-product-tabs';
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
                return [ 'hamishop', 'hamisys', 'hami', 'single', 'product', 'detail', 'محصول', 'محصول تکی' ];
        }

        /**
         * Frontend styles — only loaded when this widget is on the page.
         *
         * @return string[]
         */
        public function get_style_depends() {
                return [ 'hsw-base', 'hsw-single' ];
        }

        /**
         * Frontend script — hsw-frontend contains the gallery thumbnail switcher.
         *
         * @return string[]
         */
        public function get_script_depends() {
                return [ 'hsw-frontend' ];
        }

        /**
         * Custom controls.
         *
         * @return void
         */
        protected function register_controls() {
                $this->register_source_controls();
                $this->register_layout_controls();
                $this->register_section_controls();
                $this->register_style_controls();
        }

        /**
         * Content: product source.
         *
         * @return void
         */
        private function register_source_controls() {
                $this->start_controls_section(
                        'section_source',
                        [
                                'label' => __( 'Product Source', 'hamishop-elementor-widgets' ),
                                'tab'   => Controls_Manager::TAB_CONTENT,
                        ]
                );

                $this->add_control(
                        'product_source',
                        [
                                'label'   => __( 'Source', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => 'current',
                                'options' => [
                                        'current' => __( 'Current Product', 'hamishop-elementor-widgets' ),
                                        'single'  => __( 'Manual Pick', 'hamishop-elementor-widgets' ),
                                ],
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

                $this->end_controls_section();
        }

        /**
         * Content: layout.
         *
         * @return void
         */
        private function register_layout_controls() {
                $this->start_controls_section(
                        'section_layout',
                        [
                                'label' => __( 'Layout', 'hamishop-elementor-widgets' ),
                                'tab'   => Controls_Manager::TAB_CONTENT,
                        ]
                );

                $this->add_control(
                        'image_position',
                        [
                                'label'        => __( 'Image Position', 'hamishop-elementor-widgets' ),
                                'type'         => Controls_Manager::SELECT,
                                'default'      => 'start',
                                'options'      => [
                                        'start' => __( 'Start (image first)', 'hamishop-elementor-widgets' ),
                                        'end'   => __( 'End (image last)', 'hamishop-elementor-widgets' ),
                                        'top'   => __( 'Top (stacked)', 'hamishop-elementor-widgets' ),
                                ],
                                'prefix_class' => 'hsw-pos-',
                        ]
                );

                $this->add_control(
                        'layout_ratio',
                        [
                                'label'       => __( 'Gallery Width', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SLIDER,
                                'size_units'  => [ '%' ],
                                'range'       => [
                                        '%' => [ 'min' => 20, 'max' => 70 ],
                                ],
                                'default'     => [
                                        'size' => 45,
                                        'unit' => '%',
                                ],
                                'description' => __( 'Gallery column width. Applies to the "Start" position.', 'hamishop-elementor-widgets' ),
                                'selectors'   => [
                                        '{{WRAPPER}} .hsw-single__layout' => 'grid-template-columns: {{SIZE}}{{UNIT}} minmax(0, 1fr);',
                                ],
                        ]
                );

                $this->add_control(
                        'gallery_sticky',
                        [
                                'label'        => __( 'Sticky Gallery', 'hamishop-elementor-widgets' ),
                                'type'         => Controls_Manager::SWITCHER,
                                'return_value' => 'yes',
                                'default'      => 'yes',
                                'description'  => __( 'Keeps the gallery in view while scrolling the summary (desktop only).', 'hamishop-elementor-widgets' ),
                        ]
                );

                $this->add_control(
                        'show_thumbs',
                        [
                                'label'        => __( 'Thumbnails', 'hamishop-elementor-widgets' ),
                                'type'         => Controls_Manager::SWITCHER,
                                'return_value' => 'yes',
                                'default'      => 'yes',
                        ]
                );

                $this->add_control(
                        'show_breadcrumbs',
                        [
                                'label'        => __( 'Breadcrumbs', 'hamishop-elementor-widgets' ),
                                'type'         => Controls_Manager::SWITCHER,
                                'return_value' => 'yes',
                                'default'      => 'yes',
                        ]
                );

                $this->end_controls_section();
        }

        /**
         * Content: summary sections toggles.
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
                                'default'   => 'h1',
                                'options'   => [
                                        'h1' => 'H1',
                                        'h2' => 'H2',
                                        'h3' => 'H3',
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

                // Short description.
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
                                'default'      => 'yes',
                        ]
                );

                $this->end_controls_section();

                // Meta (SKU / categories / tags).
                $this->start_controls_section(
                        'section_meta',
                        [
                                'label' => __( 'Product Meta', 'hamishop-elementor-widgets' ),
                                'tab'   => Controls_Manager::TAB_CONTENT,
                        ]
                );

                $this->add_control(
                        'show_meta',
                        [
                                'label'        => __( 'Product Meta', 'hamishop-elementor-widgets' ),
                                'type'         => Controls_Manager::SWITCHER,
                                'return_value' => 'yes',
                                'default'      => 'yes',
                        ]
                );

                $this->add_control(
                        'show_meta_sku',
                        [
                                'label'        => __( 'SKU', 'hamishop-elementor-widgets' ),
                                'type'         => Controls_Manager::SWITCHER,
                                'return_value' => 'yes',
                                'default'      => 'yes',
                                'condition'    => [ 'show_meta' => 'yes' ],
                        ]
                );

                $this->add_control(
                        'show_meta_cats',
                        [
                                'label'        => __( 'Categories', 'hamishop-elementor-widgets' ),
                                'type'         => Controls_Manager::SWITCHER,
                                'return_value' => 'yes',
                                'default'      => 'yes',
                                'condition'    => [ 'show_meta' => 'yes' ],
                        ]
                );

                $this->add_control(
                        'show_meta_tags',
                        [
                                'label'        => __( 'Tags', 'hamishop-elementor-widgets' ),
                                'type'         => Controls_Manager::SWITCHER,
                                'return_value' => 'yes',
                                'default'      => 'yes',
                                'condition'    => [ 'show_meta' => 'yes' ],
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
                                'label'        => __( 'Add to Cart Form', 'hamishop-elementor-widgets' ),
                                'type'         => Controls_Manager::SWITCHER,
                                'return_value' => 'yes',
                                'default'      => 'yes',
                        ]
                );

                $this->end_controls_section();

                // Tabs.
                $this->start_controls_section(
                        'section_tabs',
                        [
                                'label' => __( 'Tabs', 'hamishop-elementor-widgets' ),
                                'tab'   => Controls_Manager::TAB_CONTENT,
                        ]
                );

                $this->add_control(
                        'show_tabs',
                        [
                                'label'        => __( 'Tabs', 'hamishop-elementor-widgets' ),
                                'type'         => Controls_Manager::SWITCHER,
                                'return_value' => 'yes',
                                'default'      => 'yes',
                        ]
                );

                $this->add_control(
                        'tab_description',
                        [
                                'label'        => __( 'Description Tab', 'hamishop-elementor-widgets' ),
                                'type'         => Controls_Manager::SWITCHER,
                                'return_value' => 'yes',
                                'default'      => 'yes',
                                'condition'    => [ 'show_tabs' => 'yes' ],
                        ]
                );

                $this->add_control(
                        'tab_attributes',
                        [
                                'label'        => __( 'Additional Information Tab', 'hamishop-elementor-widgets' ),
                                'type'         => Controls_Manager::SWITCHER,
                                'return_value' => 'yes',
                                'default'      => 'yes',
                                'condition'    => [ 'show_tabs' => 'yes' ],
                        ]
                );

                $this->add_control(
                        'tab_reviews',
                        [
                                'label'        => __( 'Reviews Tab', 'hamishop-elementor-widgets' ),
                                'type'         => Controls_Manager::SWITCHER,
                                'return_value' => 'yes',
                                'default'      => 'yes',
                                'description'  => __( 'Only works in "Current Product" mode on real single product pages.', 'hamishop-elementor-widgets' ),
                                'condition'    => [ 'show_tabs' => 'yes' ],
                        ]
                );

                $this->add_control(
                        'label_description',
                        [
                                'label'       => __( 'Description Label', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'Description', 'hamishop-elementor-widgets' ),
                                'condition'   => [ 'tab_description' => 'yes' ],
                        ]
                );

                $this->add_control(
                        'label_attributes',
                        [
                                'label'       => __( 'Additional Information Label', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'Additional Information', 'hamishop-elementor-widgets' ),
                                'condition'   => [ 'tab_attributes' => 'yes' ],
                        ]
                );

                $this->add_control(
                        'label_reviews',
                        [
                                'label'       => __( 'Reviews Label', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'Reviews', 'hamishop-elementor-widgets' ),
                                'condition'   => [ 'tab_reviews' => 'yes' ],
                        ]
                );

                $this->end_controls_section();

                // Related products.
                $this->start_controls_section(
                        'section_related',
                        [
                                'label' => __( 'Related & Upsells', 'hamishop-elementor-widgets' ),
                                'tab'   => Controls_Manager::TAB_CONTENT,
                        ]
                );

                $this->add_control(
                        'show_related',
                        [
                                'label'        => __( 'Related Products', 'hamishop-elementor-widgets' ),
                                'type'         => Controls_Manager::SWITCHER,
                                'return_value' => 'yes',
                                'default'      => 'yes',
                        ]
                );

                $this->add_control(
                        'related_count',
                        [
                                'label'     => __( 'Related Count', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::NUMBER,
                                'default'   => 4,
                                'min'       => 1,
                                'max'       => 12,
                                'condition' => [ 'show_related' => 'yes' ],
                        ]
                );

                $this->add_control(
                        'related_heading',
                        [
                                'label'       => __( 'Related Heading', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'Related Products', 'hamishop-elementor-widgets' ),
                                'condition'   => [ 'show_related' => 'yes' ],
                        ]
                );

                $this->add_control(
                        'show_upsells',
                        [
                                'label'        => __( 'Upsell Products', 'hamishop-elementor-widgets' ),
                                'type'         => Controls_Manager::SWITCHER,
                                'return_value' => 'yes',
                                'default'      => 'no',
                        ]
                );

                $this->add_control(
                        'upsells_count',
                        [
                                'label'     => __( 'Upsells Count', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::NUMBER,
                                'default'   => 4,
                                'min'       => 1,
                                'max'       => 12,
                                'condition' => [ 'show_upsells' => 'yes' ],
                        ]
                );

                $this->add_control(
                        'upsells_heading',
                        [
                                'label'       => __( 'Upsells Heading', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'Upsell Products', 'hamishop-elementor-widgets' ),
                                'condition'   => [ 'show_upsells' => 'yes' ],
                        ]
                );

                $this->add_responsive_control(
                        'related_columns',
                        [
                                'label'           => __( 'Cards Columns', 'hamishop-elementor-widgets' ),
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
                                'selectors'       => [
                                        '{{WRAPPER}} .hsw-related-grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
                                ],
                                'selectors_dictionary' => [],
                        ]
                );

                $this->end_controls_section();
        }

        /**
         * Style controls for every element.
         *
         * @return void
         */
        private function register_style_controls() {
                $this->style_layout();
                $this->style_breadcrumbs();
                $this->style_badge();
                $this->style_title();
                $this->style_rating();
                $this->style_price();
                $this->style_excerpt();
                $this->style_meta();
                $this->style_cart();
                $this->style_tabs();
                $this->style_related();
                $this->style_upsells();
        }

        /**
         * Layout styles.
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

                $this->add_responsive_control(
                        'summary_padding',
                        [
                                'label'      => __( 'Summary Padding', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => [ 'px', 'rem' ],
                                'range'      => [ 'px' => [ 'min' => 0, 'max' => 80 ] ],
                                'selectors'  => [
                                        '{{WRAPPER}} .hsw-single__summary' => 'padding: {{SIZE}}{{UNIT}};',
                                ],
                        ]
                );

                $this->add_responsive_control(
                        'gallery_gap',
                        [
                                'label'      => __( 'Thumbnails Spacing', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => [ 'px', 'rem' ],
                                'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
                                'default'    => [ 'size' => 12, 'unit' => 'px' ],
                                'selectors'  => [
                                        '{{WRAPPER}} .hsw-gallery__thumbs' => 'margin-block-start: {{SIZE}}{{UNIT}};',
                                ],
                        ]
                );

                $this->add_responsive_control(
                        'thumbs_size',
                        [
                                'label'      => __( 'Thumbnail Size', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => [ 'px' ],
                                'range'      => [ 'px' => [ 'min' => 40, 'max' => 140 ] ],
                                'default'    => [ 'size' => 80, 'unit' => 'px' ],
                                'selectors'  => [
                                        '{{WRAPPER}} .hsw-gallery__thumb' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                                ],
                        ]
                );

                $this->end_controls_section();
        }

        /**
         * Breadcrumbs styles.
         *
         * @return void
         */
        private function style_breadcrumbs() {
                $this->start_controls_section(
                        'style_section_breadcrumbs',
                        [
                                'label'     => __( 'Breadcrumbs', 'hamishop-elementor-widgets' ),
                                'tab'       => Controls_Manager::TAB_STYLE,
                                'condition' => [ 'show_breadcrumbs' => 'yes' ],
                        ]
                );

                $this->add_group_control(
                        Group_Control_Typography::get_type(),
                        [
                                'name'     => 'breadcrumbs_typography',
                                'selector' => '{{WRAPPER}} .hsw-single__breadcrumbs',
                        ]
                );

                $this->add_control(
                        'breadcrumbs_color',
                        [
                                'label'     => __( 'Color', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'default'   => '#6B7280',
                                'selectors' => [
                                        '{{WRAPPER}} .hsw-single__breadcrumbs, {{WRAPPER}} .hsw-single__breadcrumbs a' => 'color: {{VALUE}};',
                                ],
                        ]
                );

                $this->add_control(
                        'breadcrumbs_link_hover_color',
                        [
                                'label'     => __( 'Link Hover Color', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'default'   => '#C0392B',
                                'selectors' => [
                                        '{{WRAPPER}} .hsw-single__breadcrumbs a:hover' => 'color: {{VALUE}};',
                                ],
                        ]
                );

                $this->add_responsive_control(
                        'breadcrumbs_spacing',
                        [
                                'label'      => __( 'Bottom Spacing', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => [ 'px', 'rem' ],
                                'range'      => [ 'px' => [ 'min' => 0, 'max' => 80 ] ],
                                'default'    => [ 'size' => 24, 'unit' => 'px' ],
                                'selectors'  => [
                                        '{{WRAPPER}} .hsw-single__breadcrumbs' => 'margin-block-end: {{SIZE}}{{UNIT}};',
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
                                        'start' => 'justify-content: flex-start;',
                                        'end'   => 'justify-content: flex-end;',
                                ],
                                'selectors' => [
                                        '{{WRAPPER}} .hsw-single__badges' => '{{VALUE}}',
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
                                        'top'    => 'margin-block-start: 0; margin-block-end: 10px;',
                                        'bottom' => 'margin-block-start: 10px; margin-block-end: 0;',
                                ],
                                'selectors' => [
                                        '{{WRAPPER}} .hsw-single__badges' => '{{VALUE}}',
                                ],
                        ]
                );

                $this->add_group_control(
                        Group_Control_Typography::get_type(),
                        [
                                'name'     => 'badge_typography',
                                'selector' => '{{WRAPPER}} .hsw-single__badge',
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
                                        '{{WRAPPER}} .hsw-single__badge--sale' => 'color: {{VALUE}};',
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
                                        '{{WRAPPER}} .hsw-single__badge--sale' => 'background-color: {{VALUE}};',
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
                                        '{{WRAPPER}} .hsw-single__badge--new' => 'color: {{VALUE}};',
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
                                        '{{WRAPPER}} .hsw-single__badge--new' => 'background-color: {{VALUE}};',
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
                                        '{{WRAPPER}} .hsw-single__badge' => 'border-radius: {{SIZE}}{{UNIT}};',
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
                                'default'    => [ 'size' => 2, 'unit' => 'px' ],
                                'selectors'  => [
                                        '{{WRAPPER}} .hsw-single__badge' => 'padding: {{SIZE}}{{UNIT}} 12px; padding-inline: {{SIZE}}{{UNIT}};',
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
                                'selector' => '{{WRAPPER}} .hsw-single__title',
                        ]
                );

                $this->add_control(
                        'title_color',
                        [
                                'label'     => __( 'Color', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'default'   => '#111827',
                                'selectors' => [
                                        '{{WRAPPER}} .hsw-single__title' => 'color: {{VALUE}};',
                                ],
                        ]
                );

                $this->add_control(
                        'title_hover_color',
                        [
                                'label'     => __( 'Hover Color', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => [
                                        '{{WRAPPER}} .hsw-single__title:hover' => 'color: {{VALUE}};',
                                ],
                        ]
                );

                $this->add_responsive_control(
                        'title_spacing',
                        [
                                'label'      => __( 'Bottom Spacing', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => [ 'px', 'rem' ],
                                'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
                                'default'    => [ 'size' => 10, 'unit' => 'px' ],
                                'selectors'  => [
                                        '{{WRAPPER}} .hsw-single__title' => 'margin-block-end: {{SIZE}}{{UNIT}};',
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
                        'rating_fill_color',
                        [
                                'label'     => __( 'Filled Stars Color', 'hamishop-elementor-widgets' ),
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
                                'label'      => __( 'Stars Size', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => [ 'px' ],
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
                        ]
                );

                $this->add_control(
                        'rating_count_color',
                        [
                                'label'     => __( 'Review Count Color', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'default'   => '#9CA3AF',
                                'selectors' => [
                                        '{{WRAPPER}} .hsw-rating__count' => 'color: {{VALUE}};',
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
                                'selector' => '{{WRAPPER}} .hsw-single__price',
                        ]
                );

                $this->add_control(
                        'price_color',
                        [
                                'label'     => __( 'Price Color', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'default'   => '#111827',
                                'selectors' => [
                                        '{{WRAPPER}} .hsw-single__price' => 'color: {{VALUE}};',
                                        '{{WRAPPER}} .hsw-single__price .woocommerce-Price-amount' => 'color: {{VALUE}};',
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
                                        '{{WRAPPER}} .hsw-single__price ins' => 'color: {{VALUE}};',
                                        '{{WRAPPER}} .hsw-single__price ins .woocommerce-Price-amount' => 'color: {{VALUE}};',
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
                                        '{{WRAPPER}} .hsw-single__price del' => 'color: {{VALUE}};',
                                        '{{WRAPPER}} .hsw-single__price del .woocommerce-Price-amount' => 'color: {{VALUE}};',
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
                                'default'    => [ 'size' => 12, 'unit' => 'px' ],
                                'selectors'  => [
                                        '{{WRAPPER}} .hsw-single__price' => 'margin-block-end: {{SIZE}}{{UNIT}};',
                                ],
                        ]
                );

                $this->end_controls_section();
        }

        /**
         * Short description styles.
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
                                'selector' => '{{WRAPPER}} .hsw-single__excerpt',
                        ]
                );

                $this->add_control(
                        'excerpt_color',
                        [
                                'label'     => __( 'Color', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'default'   => '#6B7280',
                                'selectors' => [
                                        '{{WRAPPER}} .hsw-single__excerpt' => 'color: {{VALUE}};',
                                ],
                        ]
                );

                $this->add_responsive_control(
                        'excerpt_lines',
                        [
                                'label'   => __( 'Line Clamp', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => '0',
                                'options' => [
                                        '1' => '1',
                                        '2' => '2',
                                        '3' => '3',
                                        '4' => '4',
                                        '5' => '5',
                                        '0' => __( 'Unlimited', 'hamishop-elementor-widgets' ),
                                ],
                                'selectors' => [
                                        '{{WRAPPER}} .hsw-single__excerpt' => 'display: -webkit-box; -webkit-line-clamp: {{VALUE}}; -webkit-box-orient: vertical; overflow: hidden;',
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
                                'default'    => [ 'size' => 12, 'unit' => 'px' ],
                                'selectors'  => [
                                        '{{WRAPPER}} .hsw-single__excerpt' => 'margin-block-end: {{SIZE}}{{UNIT}};',
                                ],
                        ]
                );

                $this->end_controls_section();
        }

        /**
         * Product meta styles.
         *
         * @return void
         */
        private function style_meta() {
                $this->start_controls_section(
                        'style_section_meta',
                        [
                                'label'     => __( 'Product Meta', 'hamishop-elementor-widgets' ),
                                'tab'       => Controls_Manager::TAB_STYLE,
                                'condition' => [ 'show_meta' => 'yes' ],
                        ]
                );

                $this->add_group_control(
                        Group_Control_Typography::get_type(),
                        [
                                'name'     => 'meta_typography',
                                'selector' => '{{WRAPPER}} .hsw-single__meta',
                        ]
                );

                $this->add_control(
                        'meta_label_color',
                        [
                                'label'     => __( 'Label Color', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'default'   => '#111827',
                                'selectors' => [
                                        '{{WRAPPER}} .hsw-single__meta-label' => 'color: {{VALUE}};',
                                ],
                        ]
                );

                $this->add_control(
                        'meta_value_color',
                        [
                                'label'     => __( 'Value Color', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'default'   => '#6B7280',
                                'selectors' => [
                                        '{{WRAPPER}} .hsw-single__meta-row' => 'color: {{VALUE}};',
                                ],
                        ]
                );

                $this->add_responsive_control(
                        'meta_row_gap',
                        [
                                'label'      => __( 'Row Gap', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => [ 'px', 'rem' ],
                                'range'      => [ 'px' => [ 'min' => 0, 'max' => 20 ] ],
                                'default'    => [ 'size' => 4, 'unit' => 'px' ],
                                'selectors'  => [
                                        '{{WRAPPER}} .hsw-single__meta-row' => 'margin-block-end: {{SIZE}}{{UNIT}};',
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
                                        '{{WRAPPER}} .hsw-single__cart .button' => 'width: 100%;',
                                        '{{WRAPPER}} .hsw-single__cart .single_add_to_cart_button' => 'width: 100%;',
                                ],
                        ]
                );

                $this->add_group_control(
                        Group_Control_Typography::get_type(),
                        [
                                'name'     => 'cart_typography',
                                'selector' => '{{WRAPPER}} .hsw-single__cart .button, {{WRAPPER}} .hsw-single__cart .single_add_to_cart_button',
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
                                        '{{WRAPPER}} .hsw-single__cart .button' => 'color: {{VALUE}};',
                                        '{{WRAPPER}} .hsw-single__cart .single_add_to_cart_button' => 'color: {{VALUE}};',
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
                                        '{{WRAPPER}} .hsw-single__cart .button' => 'background-color: {{VALUE}};',
                                        '{{WRAPPER}} .hsw-single__cart .single_add_to_cart_button' => 'background-color: {{VALUE}};',
                                ],
                        ]
                );

                $this->add_group_control(
                        Group_Control_Border::get_type(),
                        [
                                'name'     => 'cart_border',
                                'selector' => '{{WRAPPER}} .hsw-single__cart .button, {{WRAPPER}} .hsw-single__cart .single_add_to_cart_button',
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
                                        '{{WRAPPER}} .hsw-single__cart .button:hover' => 'color: {{VALUE}};',
                                        '{{WRAPPER}} .hsw-single__cart .single_add_to_cart_button:hover' => 'color: {{VALUE}};',
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
                                        '{{WRAPPER}} .hsw-single__cart .button:hover' => 'background-color: {{VALUE}};',
                                        '{{WRAPPER}} .hsw-single__cart .single_add_to_cart_button:hover' => 'background-color: {{VALUE}};',
                                ],
                        ]
                );

                $this->add_control(
                        'cart_hover_border_color',
                        [
                                'label'     => __( 'Border Color', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => [
                                        '{{WRAPPER}} .hsw-single__cart .button:hover' => 'border-color: {{VALUE}};',
                                        '{{WRAPPER}} .hsw-single__cart .single_add_to_cart_button:hover' => 'border-color: {{VALUE}};',
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
                                        '{{WRAPPER}} .hsw-single__cart .button' => 'border-radius: {{SIZE}}{{UNIT}};',
                                        '{{WRAPPER}} .hsw-single__cart .single_add_to_cart_button' => 'border-radius: {{SIZE}}{{UNIT}};',
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
                                        '{{WRAPPER}} .hsw-single__cart .button' => 'padding: {{SIZE}}{{UNIT}} 20px; padding-inline: {{SIZE}}{{UNIT}};',
                                        '{{WRAPPER}} .hsw-single__cart .single_add_to_cart_button' => 'padding: {{SIZE}}{{UNIT}} 20px; padding-inline: {{SIZE}}{{UNIT}};',
                                ],
                        ]
                );

                $this->end_controls_section();
        }

        /**
         * Tabs styles.
         *
         * @return void
         */
        private function style_tabs() {
                $this->start_controls_section(
                        'style_section_tabs',
                        [
                                'label'     => __( 'Tabs', 'hamishop-elementor-widgets' ),
                                'tab'       => Controls_Manager::TAB_STYLE,
                                'condition' => [ 'show_tabs' => 'yes' ],
                        ]
                );

                $this->add_responsive_control(
                        'tabs_nav_gap',
                        [
                                'label'      => __( 'Nav Gap', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => [ 'px', 'rem' ],
                                'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
                                'default'    => [ 'size' => 4, 'unit' => 'px' ],
                                'selectors'  => [
                                        '{{WRAPPER}} .hsw-single__tabs-nav' => 'gap: {{SIZE}}{{UNIT}};',
                                ],
                        ]
                );

                $this->add_group_control(
                        Group_Control_Typography::get_type(),
                        [
                                'name'     => 'tab_btn_typography',
                                'selector' => '{{WRAPPER}} .hsw-single__tabs-btn',
                        ]
                );

                $this->add_control(
                        'tab_btn_color',
                        [
                                'label'     => __( 'Color', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'default'   => '#6B7280',
                                'selectors' => [
                                        '{{WRAPPER}} .hsw-single__tabs-btn' => 'color: {{VALUE}};',
                                ],
                        ]
                );

                $this->add_control(
                        'tab_btn_bg',
                        [
                                'label'     => __( 'Background', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => [
                                        '{{WRAPPER}} .hsw-single__tabs-btn' => 'background-color: {{VALUE}};',
                                ],
                        ]
                );

                $this->add_group_control(
                        Group_Control_Border::get_type(),
                        [
                                'name'     => 'tab_btn_border',
                                'selector' => '{{WRAPPER}} .hsw-single__tabs-btn',
                        ]
                );

                $this->add_responsive_control(
                        'tab_btn_radius',
                        [
                                'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => [ 'px', 'rem' ],
                                'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
                                'selectors'  => [
                                        '{{WRAPPER}} .hsw-single__tabs-btn' => 'border-radius: {{SIZE}}{{UNIT}};',
                                ],
                        ]
                );

                $this->add_responsive_control(
                        'tab_btn_padding',
                        [
                                'label'      => __( 'Padding Inline', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => [ 'px', 'rem' ],
                                'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
                                'default'    => [ 'size' => 18, 'unit' => 'px' ],
                                'selectors'  => [
                                        '{{WRAPPER}} .hsw-single__tabs-btn' => 'padding-inline: {{SIZE}}{{UNIT}};',
                                ],
                        ]
                );

                $this->add_control(
                        'heading_tab_active',
                        [
                                'label'     => __( 'Active Tab', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::HEADING,
                                'separator' => 'before',
                        ]
                );

                $this->add_control(
                        'tab_btn_active_color',
                        [
                                'label'     => __( 'Color', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'default'   => '#111827',
                                'selectors' => [
                                        '{{WRAPPER}} .hsw-single__tabs-btn.is-active' => 'color: {{VALUE}};',
                                ],
                        ]
                );

                $this->add_control(
                        'tab_btn_active_bg',
                        [
                                'label'     => __( 'Background', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => [
                                        '{{WRAPPER}} .hsw-single__tabs-btn.is-active' => 'background-color: {{VALUE}};',
                                ],
                        ]
                );

                $this->add_control(
                        'tab_btn_active_border',
                        [
                                'label'     => __( 'Border Color', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'default'   => '#111827',
                                'selectors' => [
                                        '{{WRAPPER}} .hsw-single__tabs-btn.is-active' => 'border-block-end-color: {{VALUE}}; border-color: {{VALUE}};',
                                ],
                        ]
                );

                $this->add_control(
                        'heading_tab_panel',
                        [
                                'label'     => __( 'Panel', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::HEADING,
                                'separator' => 'before',
                        ]
                );

                $this->add_group_control(
                        Group_Control_Typography::get_type(),
                        [
                                'name'     => 'panel_typography',
                                'selector' => '{{WRAPPER}} .hsw-single__tab-panel',
                        ]
                );

                $this->add_control(
                        'panel_color',
                        [
                                'label'     => __( 'Text Color', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'default'   => '#111827',
                                'selectors' => [
                                        '{{WRAPPER}} .hsw-single__tab-panel' => 'color: {{VALUE}};',
                                ],
                        ]
                );

                $this->add_control(
                        'panel_bg',
                        [
                                'label'     => __( 'Background', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => [
                                        '{{WRAPPER}} .hsw-single__tab-panel' => 'background-color: {{VALUE}};',
                                ],
                        ]
                );

                $this->add_responsive_control(
                        'panel_padding',
                        [
                                'label'      => __( 'Padding', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => [ 'px', 'rem' ],
                                'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
                                'selectors'  => [
                                        '{{WRAPPER}} .hsw-single__tab-panel' => 'padding: {{SIZE}}{{UNIT}};',
                                ],
                        ]
                );

                $this->add_group_control(
                        Group_Control_Border::get_type(),
                        [
                                'name'     => 'panel_border',
                                'selector' => '{{WRAPPER}} .hsw-single__tab-panel',
                        ]
                );

                $this->add_responsive_control(
                        'panel_radius',
                        [
                                'label'      => __( 'Border Radius', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => [ 'px', 'rem' ],
                                'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
                                'selectors'  => [
                                        '{{WRAPPER}} .hsw-single__tab-panel' => 'border-radius: {{SIZE}}{{UNIT}};',
                                ],
                        ]
                );

                $this->add_responsive_control(
                        'panel_top_spacing',
                        [
                                'label'      => __( 'Top Spacing', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => [ 'px', 'rem' ],
                                'range'      => [ 'px' => [ 'min' => 0, 'max' => 80 ] ],
                                'default'    => [ 'size' => 0, 'unit' => 'px' ],
                                'selectors'  => [
                                        '{{WRAPPER}} .hsw-single__tab-panel' => 'margin-block-start: {{SIZE}}{{UNIT}};',
                                ],
                        ]
                );

                $this->end_controls_section();
        }

        /**
         * Related products styles.
         *
         * @return void
         */
        private function style_related() {
                $this->start_controls_section(
                        'style_section_related',
                        [
                                'label'     => __( 'Related Products', 'hamishop-elementor-widgets' ),
                                'tab'       => Controls_Manager::TAB_STYLE,
                                'condition' => [ 'show_related' => 'yes' ],
                        ]
                );

                $this->add_group_control(
                        Group_Control_Typography::get_type(),
                        [
                                'name'     => 'related_heading_typography',
                                'selector' => '{{WRAPPER}} .hsw-single__related .hsw-single__related-heading',
                        ]
                );

                $this->add_control(
                        'related_heading_color',
                        [
                                'label'     => __( 'Heading Color', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'default'   => '#111827',
                                'selectors' => [
                                        '{{WRAPPER}} .hsw-single__related .hsw-single__related-heading' => 'color: {{VALUE}};',
                                ],
                        ]
                );

                $this->add_responsive_control(
                        'related_heading_spacing',
                        [
                                'label'      => __( 'Heading Bottom Spacing', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => [ 'px', 'rem' ],
                                'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
                                'default'    => [ 'size' => 12, 'unit' => 'px' ],
                                'selectors'  => [
                                        '{{WRAPPER}} .hsw-single__related .hsw-single__related-heading' => 'margin-block-end: {{SIZE}}{{UNIT}};',
                                ],
                        ]
                );

                $this->add_responsive_control(
                        'related_grid_gap',
                        [
                                'label'      => __( 'Grid Gap', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => [ 'px', 'rem' ],
                                'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
                                'default'    => [ 'size' => 20, 'unit' => 'px' ],
                                'selectors'  => [
                                        '{{WRAPPER}} .hsw-single__related .hsw-related-grid' => 'gap: {{SIZE}}{{UNIT}};',
                                ],
                        ]
                );

                $this->end_controls_section();
        }

        /**
         * Upsell products styles.
         *
         * @return void
         */
        private function style_upsells() {
                $this->start_controls_section(
                        'style_section_upsells',
                        [
                                'label'     => __( 'Upsell Products', 'hamishop-elementor-widgets' ),
                                'tab'       => Controls_Manager::TAB_STYLE,
                                'condition' => [ 'show_upsells' => 'yes' ],
                        ]
                );

                $this->add_group_control(
                        Group_Control_Typography::get_type(),
                        [
                                'name'     => 'upsells_heading_typography',
                                'selector' => '{{WRAPPER}} .hsw-single__upsells .hsw-single__related-heading',
                        ]
                );

                $this->add_control(
                        'upsells_heading_color',
                        [
                                'label'     => __( 'Heading Color', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'default'   => '#111827',
                                'selectors' => [
                                        '{{WRAPPER}} .hsw-single__upsells .hsw-single__related-heading' => 'color: {{VALUE}};',
                                ],
                        ]
                );

                $this->add_responsive_control(
                        'upsells_heading_spacing',
                        [
                                'label'      => __( 'Heading Bottom Spacing', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => [ 'px', 'rem' ],
                                'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
                                'default'    => [ 'size' => 12, 'unit' => 'px' ],
                                'selectors'  => [
                                        '{{WRAPPER}} .hsw-single__upsells .hsw-single__related-heading' => 'margin-block-end: {{SIZE}}{{UNIT}};',
                                ],
                        ]
                );

                $this->add_responsive_control(
                        'upsells_grid_gap',
                        [
                                'label'      => __( 'Grid Gap', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => [ 'px', 'rem' ],
                                'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
                                'default'    => [ 'size' => 20, 'unit' => 'px' ],
                                'selectors'  => [
                                        '{{WRAPPER}} .hsw-upsells-grid' => 'gap: {{SIZE}}{{UNIT}};',
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
                $source   = isset( $settings['product_source'] ) ? $settings['product_source'] : 'current';

                if ( 'single' === $source ) {
                        $pid     = isset( $settings['product_id'] ) ? absint( $settings['product_id'] ) : 0;
                        $product = $pid ? wc_get_product( $pid ) : null;
                } else {
                        $product = HSW_Helpers::get_current_product();
                }

                if ( ! $product instanceof \WC_Product ) {
                        echo '<div class="hsw-widget-root">';
                        echo HSW_Helpers::editor_note( __( 'No product found. Use this widget on a Single Product template or pick a product manually.', 'hamishop-elementor-widgets' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside helper.
                        echo '</div>';
                        return;
                }

                // WooCommerce scripts: the add-to-cart handler + variation form.
                HSW_Assets::ensure_wc_script( 'wc-add-to-cart' );
                if ( $product->is_type( 'variable' ) ) {
                        HSW_Assets::ensure_wc_script( 'wc-add-to-cart-variation' );
                }

                $title     = $product->get_name();
                $raw_tag   = isset( $settings['title_tag'] ) ? $settings['title_tag'] : 'h1';
                $title_tag = in_array( $raw_tag, [ 'h1', 'h2', 'h3' ], true ) ? $raw_tag : 'h1';

                /*
                 * Capture WooCommerce-generated blocks (all of them produce their own
                 * escaped markup, so they are echoed as-is further below).
                 */
                $prev_product = isset( $GLOBALS['product'] ) ? $GLOBALS['product'] : null;
                $GLOBALS['product'] = $product;

                ob_start();
                woocommerce_template_single_add_to_cart(); // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce generates escaped markup.
                $cart_html = trim( (string) ob_get_clean() );

                $reviews_html = '';
                if ( 'yes' === $settings['show_tabs'] && 'yes' === $settings['tab_reviews'] && 'current' === $source && comments_open( $product->get_id() ) ) {
                        ob_start();
                        comments_template(); // phpcs:ignore WordPress.Security.EscapeOutput -- WordPress core template, escaped internally.
                        $reviews_html = (string) ob_get_clean();
                }

                if ( $prev_product instanceof \WC_Product ) {
                        $GLOBALS['product'] = $prev_product;
                }

                $breadcrumb_html = '';
                if ( 'yes' === $settings['show_breadcrumbs'] && function_exists( 'woocommerce_breadcrumb' ) ) {
                        ob_start();
                        woocommerce_breadcrumb(); // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce generates escaped markup.
                        $breadcrumb_html = trim( (string) ob_get_clean() );
                }

                // Tabs registry (first registered tab is the active one).
                $tabs = [];
                if ( 'yes' === $settings['show_tabs'] ) {
                        if ( 'yes' === $settings['tab_description'] ) {
                                $tabs['description'] = $settings['label_description'];
                        }
                        if ( 'yes' === $settings['tab_attributes'] ) {
                                $tabs['attributes'] = $settings['label_attributes'];
                        }
                        if ( '' !== trim( $reviews_html ) ) {
                                $tabs['reviews'] = $settings['label_reviews'];
                        }
                }

                $description_html = '';
                if ( isset( $tabs['description'] ) ) {
                        $description_html = wp_kses_post( apply_filters( 'the_content', (string) $product->get_description() ) );
                        if ( '' === trim( $description_html ) ) {
                                $description_html = '<p>' . esc_html__( 'No description provided.', 'hamishop-elementor-widgets' ) . '</p>';
                        }
                }

                $attributes_html = '';
                if ( isset( $tabs['attributes'] ) ) {
                        ob_start();
                        wc_display_product_attributes( $product ); // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce generates escaped markup.
                        $attributes_html = trim( (string) ob_get_clean() );
                        if ( '' === $attributes_html ) {
                                $attributes_html = '<p>' . esc_html__( 'No additional information.', 'hamishop-elementor-widgets' ) . '</p>';
                        }
                }

                /*
                 * Gallery data.
                 * Main image: "woocommerce_single" (fallback "large"), link to "full".
                 * Thumbs: "woocommerce_gallery_thumbnail" — the first thumb is the
                 * main image itself. The hsw-frontend script swaps the main image via
                 * the data-src / data-large / data-alt attributes.
                 */
                $image_id    = (int) $product->get_image_id();
                $gallery_ids = array_merge( $image_id ? [ $image_id ] : [], (array) $product->get_gallery_image_ids() );
                $show_thumbs = 'yes' === $settings['show_thumbs'] && ! empty( $gallery_ids );

                $main_src    = '';
                $main_large  = '';
                $main_w      = 0;
                $main_h      = 0;
                $main_alt    = $title;
                $thumb_items = [];

                if ( $image_id ) {
                        $single_arr = wp_get_attachment_image_src( $image_id, 'woocommerce_single' );
                        if ( ! $single_arr ) {
                                $single_arr = wp_get_attachment_image_src( $image_id, 'large' );
                        }
                        $full_arr = wp_get_attachment_image_src( $image_id, 'full' );
                        $meta     = wp_get_attachment_metadata( $image_id );

                        if ( $single_arr ) {
                                $main_src = $single_arr[0];
                                $main_w   = (int) $single_arr[1];
                                $main_h   = (int) $single_arr[2];
                        }
                        if ( $meta && ! empty( $meta['width'] ) ) {
                                $main_w = (int) $meta['width'];
                        }
                        if ( $meta && ! empty( $meta['height'] ) ) {
                                $main_h = (int) $meta['height'];
                        }
                        if ( $full_arr ) {
                                $main_large = $full_arr[0];
                        }

                        $alt_meta = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
                        if ( '' === (string) $alt_meta ) {
                                $caption = wp_get_attachment_caption( $image_id );
                                $main_alt = $caption ? $caption : $title;
                        } else {
                                $main_alt = $alt_meta;
                        }
                }

                if ( '' === $main_src ) {
                        $main_src   = (string) wc_placeholder_img_src( 'woocommerce_single' );
                        $main_large = $main_src;
                }
                if ( '' === $main_large ) {
                        $main_large = $main_src;
                }

                foreach ( $gallery_ids as $gid ) {
                        $gid = (int) $gid;
                        if ( ! $gid ) {
                                continue;
                        }

                        $thumb_arr = wp_get_attachment_image_src( $gid, 'woocommerce_gallery_thumbnail' );
                        if ( ! $thumb_arr ) {
                                continue;
                        }

                        $single_arr = wp_get_attachment_image_src( $gid, 'woocommerce_single' );
                        if ( ! $single_arr ) {
                                $single_arr = $thumb_arr;
                        }
                        $full_arr = wp_get_attachment_image_src( $gid, 'full' );
                        if ( ! $full_arr ) {
                                $full_arr = $single_arr;
                        }

                        $caption = wp_get_attachment_caption( $gid );

                        $thumb_items[] = [
                                'src'    => $thumb_arr[0],
                                'w'      => (int) $thumb_arr[1],
                                'h'      => (int) $thumb_arr[2],
                                'single' => $single_arr[0],
                                'large'  => $full_arr[0],
                                'alt'    => $caption ? $caption : $title,
                        ];
                }

                $sticky_class = ( 'yes' === $settings['gallery_sticky'] ) ? ' hsw-sticky' : '';

                $percent   = HSW_Helpers::sale_percent( $product );
                $show_sale = 'yes' === $settings['show_badge'] && $percent > 0;
                $show_new  = 'yes' === $settings['show_badge_new'] && HSW_Helpers::is_new( $product, (int) $settings['new_badge_days'] );

                $sku       = $product->get_sku();
                $cats_list = wc_get_product_category_list( $product->get_id(), '، ' );
                $tags_list = wc_get_product_tag_list( $product->get_id(), '، ' );

                $related_ids = [];
                if ( 'yes' === $settings['show_related'] ) {
                        $related_ids = array_filter( array_map( 'absint', (array) wc_get_related_products( $product->get_id(), max( 1, (int) $settings['related_count'] ) ) ) );
                }

                $upsell_ids = [];
                if ( 'yes' === $settings['show_upsells'] ) {
                        $upsell_ids = array_slice( array_filter( array_map( 'absint', (array) $product->get_upsell_ids() ) ), 0, max( 1, (int) $settings['upsells_count'] ) );
                }

                $card_args = array_merge(
                        $settings,
                        [
                                'show_excerpt'   => 'no',
                                'show_cats'      => 'no',
                                'show_badge_new' => 'no',
                        ]
                );
                ?>
                <div class="hsw-widget-root hsw-single">

                        <?php if ( '' !== $breadcrumb_html ) : ?>
                                <nav class="hsw-single__breadcrumbs" aria-label="breadcrumb">
                                        <?php echo $breadcrumb_html; // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce generates escaped markup. ?>
                                </nav>
                        <?php endif; ?>

                        <div class="hsw-single__layout">

                                <div class="hsw-single__gallery hsw-gallery<?php echo esc_attr( $sticky_class ); ?>">
                                        <a class="hsw-gallery__main" href="<?php echo esc_url( $main_large ); ?>" data-elementor-open-lightbox="yes" aria-label="<?php echo esc_attr( $title ); ?>">
                                                <img class="hsw-gallery__image"
                                                        src="<?php echo esc_url( $main_src ); ?>"
                                                        alt="<?php echo esc_attr( $main_alt ); ?>"<?php
                                                        echo $main_w ? ' width="' . esc_attr( (string) $main_w ) . '"' : '';
                                                        echo $main_h ? ' height="' . esc_attr( (string) $main_h ) . '"' : ''; ?>
                                                        decoding="async" fetchpriority="high" />
                                        </a>

                                        <?php if ( $show_thumbs ) : ?>
                                                <div class="hsw-gallery__thumbs" role="tablist">
                                                        <?php foreach ( $thumb_items as $index => $thumb ) : ?>
                                                                <button type="button"
                                                                        class="hsw-gallery__thumb<?php echo 0 === $index ? ' is-active' : ''; ?>"
                                                                        data-src="<?php echo esc_url( $thumb['single'] ); ?>"
                                                                        data-large="<?php echo esc_url( $thumb['large'] ); ?>"
                                                                        data-alt="<?php echo esc_attr( $thumb['alt'] ); ?>"
                                                                        aria-label="<?php echo esc_attr( $thumb['alt'] ); ?>"
                                                                        aria-current="<?php echo 0 === $index ? 'true' : 'false'; ?>">
                                                                        <img src="<?php echo esc_url( $thumb['src'] ); ?>" alt="" loading="lazy" decoding="async" width="<?php echo esc_attr( (string) $thumb['w'] ); ?>" height="<?php echo esc_attr( (string) $thumb['h'] ); ?>" />
                                                                </button>
                                                        <?php endforeach; ?>
                                                </div>
                                        <?php endif; ?>
                                </div>

                                <div class="hsw-single__summary">

                                        <?php if ( $show_sale || $show_new ) : ?>
                                                <div class="hsw-single__badges">
                                                        <?php if ( $show_sale ) : ?>
                                                                <span class="hsw-single__badge hsw-single__badge--sale"><?php echo esc_html( HSW_Helpers::maybe_fa( '٪' . (string) $percent ) ); ?></span>
                                                        <?php endif; ?>
                                                        <?php if ( $show_new ) : ?>
                                                                <span class="hsw-single__badge hsw-single__badge--new"><?php echo esc_html( '' !== (string) $settings['badge_text_new'] ? $settings['badge_text_new'] : __( 'New', 'hamishop-elementor-widgets' ) ); ?></span>
                                                        <?php endif; ?>
                                                </div>
                                        <?php endif; ?>

                                        <?php if ( 'yes' === $settings['show_title'] ) : ?>
                                                <<?php echo tag_escape( $title_tag ); ?> class="hsw-single__title"><?php echo esc_html( $title ); ?></<?php echo tag_escape( $title_tag ); ?>>
                                        <?php endif; ?>

                                        <?php if ( 'yes' === $settings['show_rating'] ) : ?>
                                                <div class="hsw-single__rating">
                                                        <?php echo HSW_Helpers::rating_stars( $product, 'yes' === $settings['show_rating_count'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside helper. ?>
                                                </div>
                                        <?php endif; ?>

                                        <?php if ( 'yes' === $settings['show_price'] ) : ?>
                                                <div class="hsw-single__price"><?php echo HSW_Helpers::maybe_fa( $product->get_price_html() ); // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce generates escaped markup. ?></div>
                                        <?php endif; ?>

                                        <?php if ( 'yes' === $settings['show_excerpt'] ) : ?>
                                                <div class="hsw-single__excerpt"><?php echo esc_html( wp_strip_all_tags( (string) $product->get_short_description() ) ); ?></div>
                                        <?php endif; ?>

                                        <?php if ( 'yes' === $settings['show_meta'] && ( ( 'yes' === $settings['show_meta_sku'] && $sku ) || ( 'yes' === $settings['show_meta_cats'] && $cats_list ) || ( 'yes' === $settings['show_meta_tags'] && $tags_list ) ) ) : ?>
                                                <div class="hsw-single__meta">
                                                        <?php if ( 'yes' === $settings['show_meta_sku'] && $sku ) : ?>
                                                                <div class="hsw-single__meta-row">
                                                                        <span class="hsw-single__meta-label"><?php echo esc_html__( 'SKU:', 'hamishop-elementor-widgets' ); ?></span>
                                                                        <?php echo esc_html( HSW_Helpers::maybe_fa( $sku ) ); ?>
                                                                </div>
                                                        <?php endif; ?>

                                                        <?php if ( 'yes' === $settings['show_meta_cats'] && $cats_list ) : ?>
                                                                <div class="hsw-single__meta-row">
                                                                        <span class="hsw-single__meta-label"><?php echo esc_html__( 'Categories:', 'hamishop-elementor-widgets' ); ?></span>
                                                                        <?php echo $cats_list; // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce generates escaped markup. ?>
                                                                </div>
                                                        <?php endif; ?>

                                                        <?php if ( 'yes' === $settings['show_meta_tags'] && $tags_list ) : ?>
                                                                <div class="hsw-single__meta-row">
                                                                        <span class="hsw-single__meta-label"><?php echo esc_html__( 'Tags:', 'hamishop-elementor-widgets' ); ?></span>
                                                                        <?php echo $tags_list; // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce generates escaped markup. ?>
                                                                </div>
                                                        <?php endif; ?>
                                                </div>
                                        <?php endif; ?>

                                        <?php if ( 'yes' === $settings['show_cart'] && '' !== $cart_html ) : ?>
                                                <div class="hsw-single__cart"><?php echo $cart_html; // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce generates escaped markup. ?></div>
                                        <?php endif; ?>

                                        <?php if ( ! empty( $tabs ) ) : ?>
                                                <div class="hsw-single__tabs">
                                                        <div class="hsw-single__tabs-nav" role="tablist">
                                                                <?php $first_tab = true; ?>
                                                                <?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
                                                                        <button type="button" class="hsw-single__tabs-btn<?php echo $first_tab ? ' is-active' : ''; ?>" data-tab="<?php echo esc_attr( $tab_key ); ?>" aria-selected="<?php echo $first_tab ? 'true' : 'false'; ?>"><?php echo esc_html( $tab_label ); ?></button>
                                                                        <?php $first_tab = false; ?>
                                                                <?php endforeach; ?>
                                                        </div>
                                                        <?php $first_panel = true; ?>
                                                        <?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
                                                                <?php
                                                                if ( 'description' === $tab_key ) {
                                                                        $panel_html = $description_html;
                                                                } elseif ( 'attributes' === $tab_key ) {
                                                                        $panel_html = $attributes_html;
                                                                } else {
                                                                        $panel_html = $reviews_html;
                                                                }
                                                                ?>
                                                                <div class="hsw-single__tab-panel<?php echo $first_panel ? ' is-active' : ''; ?>" data-panel="<?php echo esc_attr( $tab_key ); ?>"><?php echo $panel_html; // phpcs:ignore WordPress.Security.EscapeOutput -- panels are escaped above (wp_kses_post / WooCommerce markup). ?></div>
                                                                <?php $first_panel = false; ?>
                                                        <?php endforeach; ?>
                                                </div>
                                        <?php endif; ?>

                                        <?php if ( ! empty( $related_ids ) ) : ?>
                                                <section class="hsw-single__related">
                                                        <h2 class="hsw-single__related-heading"><?php echo esc_html( $settings['related_heading'] ); ?></h2>
                                                        <div class="hsw-related-grid">
                                                                <?php foreach ( $related_ids as $related_id ) : ?>
                                                                        <?php $related_product = wc_get_product( $related_id ); ?>
                                                                        <?php if ( $related_product && $related_product->is_visible() ) : ?>
                                                                                <?php HSW_Card_Renderer::render( $related_product, $card_args ); ?>
                                                                        <?php endif; ?>
                                                                <?php endforeach; ?>
                                                        </div>
                                                </section>
                                        <?php endif; ?>

                                        <?php if ( ! empty( $upsell_ids ) ) : ?>
                                                <section class="hsw-single__upsells">
                                                        <h2 class="hsw-single__related-heading"><?php echo esc_html( $settings['upsells_heading'] ); ?></h2>
                                                        <div class="hsw-related-grid hsw-upsells-grid">
                                                                <?php foreach ( $upsell_ids as $upsell_id ) : ?>
                                                                        <?php $upsell_product = wc_get_product( $upsell_id ); ?>
                                                                        <?php if ( $upsell_product && $upsell_product->is_visible() ) : ?>
                                                                                <?php HSW_Card_Renderer::render( $upsell_product, $card_args ); ?>
                                                                        <?php endif; ?>
                                                                <?php endforeach; ?>
                                                        </div>
                                                </section>
                                        <?php endif; ?>

                                </div>
                        </div>
                </div>
                <?php
        }
}
