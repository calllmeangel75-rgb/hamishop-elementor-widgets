<?php
/**
 * Shared style controls for every HamiShop widget.
 *
 * The problem this solves: each widget used to declare its own sliders, so the
 * same "font size" or "column width" control existed in a dozen slightly
 * different flavours — some px-only, some without responsive variants, each
 * with its own range. Three helpers had even been copy-pasted verbatim into two
 * different base classes.
 *
 * Two rules make this kit predictable:
 *
 * 1. **Everything is a CSS custom property on the widget wrapper.** A control
 *    writes `--hsw-<something>` instead of a real property, and the family
 *    stylesheet consumes it with a fallback:
 *
 *        .hsw-csummary__final-v { font-size: var(--hsw-cart-total-fs, 1.125rem); }
 *
 *    Custom properties inherit, so one declaration on the wrapper reaches every
 *    descendant, the generated CSS stays a handful of lines, and a site can
 *    override any of it from its own stylesheet without fighting specificity.
 *
 * 2. **Control IDs never change.** Elementor stores values keyed by control
 *    name, so renaming one silently resets every existing page. The helpers
 *    below take the id explicitly and the migrations in this release keep the
 *    old names (`cp_side`, `cp_gap`, …).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

trait HSW_Style_Kit {

	/**
	 * Responsive font size, written to a CSS variable.
	 *
	 * Units are px / em / rem. `em` and `rem` are offered because Persian
	 * storefronts often want text that scales with the theme rather than a
	 * fixed pixel value — and a rem-based size is the only way to respect the
	 * visitor's own browser font-size setting.
	 *
	 * @param string $id      Control id (stable!).
	 * @param string $label   Human label.
	 * @param string $var     CSS variable name without the leading `--`.
	 * @param array  $args    default (number), unit (px|em|rem), range (array), heading (bool).
	 */
	protected function style_typo_var( $id, $label, $var, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'default' => 14,
				'unit'    => 'px',
				'range'   => array(),
				'heading' => false,
			)
		);

		if ( $args['heading'] ) {
			$this->add_control(
				$id . '_h',
				array(
					'label'     => $label,
					'type'      => Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
		}

		$range = $args['range'];
		if ( empty( $range ) ) {
			$range = array(
				'px'  => array( 'min' => 8, 'max' => 72 ),
				'em'  => array( 'min' => 0.5, 'max' => 4, 'step' => 0.05 ),
				'rem' => array( 'min' => 0.5, 'max' => 4, 'step' => 0.05 ),
			);
		}

		$this->add_responsive_control(
			$id,
			array(
				'label'      => $args['heading'] ? __( 'اندازه', 'hamishop-elementor-widgets' ) : $label,
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => $range,
				'default'    => array(
					'size' => $args['default'],
					'unit' => $args['unit'],
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--' . $var . ': {{SIZE}}{{UNIT}};',
				),
			)
		);
	}

	/**
	 * Responsive width / height written to a CSS variable.
	 *
	 * `%` is the reason this exists: a sidebar that is `380px` on a 1600px
	 * screen is a third of the page, and the same `380px` on a 1024px screen is
	 * almost half. Offering the unit in the control is cheaper than explaining
	 * breakpoints to whoever builds the page.
	 *
	 * @param string $id      Control id (stable!).
	 * @param string $label   Human label.
	 * @param string $var     CSS variable name without `--`.
	 * @param array  $args    default, unit (px|%), range, units (allowed units).
	 */
	protected function style_size_var( $id, $label, $var, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'default' => 320,
				'unit'    => 'px',
				'range'   => array(
					'px' => array( 'min' => 0, 'max' => 1200 ),
					'%'  => array( 'min' => 5, 'max' => 95 ),
				),
				'units'   => array( 'px', '%' ),
			)
		);

		$this->add_responsive_control(
			$id,
			array(
				'label'      => $label,
				'type'       => Controls_Manager::SLIDER,
				'size_units' => $args['units'],
				'range'      => $args['range'],
				'default'    => array(
					'size' => $args['default'],
					'unit' => $args['unit'],
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--' . $var . ': {{SIZE}}{{UNIT}};',
				),
			)
		);
	}

	/**
	 * Colour written to a CSS variable.
	 *
	 * @param string $id    Control id (stable!).
	 * @param string $label Human label.
	 * @param string $var   CSS variable name without `--`.
	 * @param array  $args  default (hex), heading (bool).
	 */
	protected function style_color_var( $id, $label, $var, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'default' => '',
				'heading' => false,
			)
		);

		if ( $args['heading'] ) {
			$this->add_control(
				$id . '_h',
				array(
					'label'     => $label,
					'type'      => Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
		}

		$control = array(
			'label'     => $args['heading'] ? __( 'رنگ', 'hamishop-elementor-widgets' ) : $label,
			'type'      => Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}}' => '--' . $var . ': {{VALUE}};',
			),
		);
		if ( '' !== $args['default'] ) {
			$control['default'] = $args['default'];
		}

		$this->add_control( $id, $control );
	}

	/**
	 * A whole "تایپوگرافی" section for a page-level widget.
	 *
	 * The variables are declared on the widget wrapper, so every child widget
	 * on that page inherits them — one place to set the type scale for the cart
	 * or the checkout, instead of hunting the same slider in six sub-widgets.
	 *
	 * @param string $prefix Control id prefix (also the variable namespace).
	 * @param array  $parts  key => array( label, default size, css fallback ).
	 * @param string $label  Section label.
	 */
	protected function style_typography_section( $prefix, $parts, $label = '' ) {
		$this->start_controls_section(
			$prefix . '_typo_sec',
			array(
				'label' => '' !== $label ? $label : __( 'تایپوگرافی', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		foreach ( $parts as $key => $conf ) {
			$this->style_typo_var(
				$prefix . '_fs_' . $key,
				$conf[0],
				'hsw-' . $prefix . '-' . $key . '-fs',
				array(
					'default' => $conf[1],
					'heading' => true,
				)
			);
		}

		$this->end_controls_section();
	}

	/**
	 * The two-column shell shared by cart, checkout, pay, complete, archive,
	 * blog, product and the account panel.
	 *
	 * Six widgets had their own copy of this pair of controls. The ids are kept
	 * as `{prefix}_side` / `{prefix}_gap` because that is what saved pages use.
	 *
	 * @param string $prefix   Control id prefix (`cp` everywhere today).
	 * @param string $side     Label for the side column.
	 * @param array  $args     side (default px), gap (default px), side_range, gap_range.
	 */
	protected function style_columns( $prefix, $side, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'side'       => 320,
				'gap'        => 24,
				'side_range' => array(
					'px' => array( 'min' => 160, 'max' => 720 ),
					'%'  => array( 'min' => 15, 'max' => 50 ),
				),
				'gap_range'  => array( 'px' => array( 'min' => 0, 'max' => 64 ) ),
				'heading'    => '',
			)
		);

		if ( '' !== $args['heading'] ) {
			$this->add_control(
				$prefix . '_layout_h',
				array(
					'label'     => $args['heading'],
					'type'      => Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
		}

		$this->add_responsive_control(
			$prefix . '_side',
			array(
				'label'      => $side,
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => $args['side_range'],
				'default'    => array(
					'size' => $args['side'],
					'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .hsw-cp' => '--hsw-cp-side: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			$prefix . '_gap',
			array(
				'label'      => __( 'فاصله ستون‌ها', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => $args['gap_range'],
				'default'    => array(
					'size' => $args['gap'],
					'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .hsw-cp' => '--hsw-cp-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);
	}
}
