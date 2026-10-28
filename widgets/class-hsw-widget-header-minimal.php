<?php
/**
 * Widget: مینیمال شیشه‌ای header.
 *
 * A floating glass pill with the real WordPress menu, WooCommerce search,
 * account/cart actions and a mobile drawer. The search opens a glass panel
 * under the pill; all interactions are vanilla JS.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;

class HSW_Widget_Header_Minimal extends HSW_Header_Base {

	public function get_name() {
		return 'hsw-header-minimal';
	}

	public function get_title() {
		return __( 'هدر مینیمال شیشه‌ای (HamiShop)', 'hamishop-elementor-widgets' );
	}

	public function get_icon() {
		return 'eicon-header';
	}

	public function get_keywords() {
		return array( 'header', 'minimal', 'glass', 'menu', 'هدر', 'سربرگ', 'مینیمال', 'شیشه‌ای', 'hamishop' );
	}

	protected function register_controls() {
		$this->content_brand( 'minimal' );
		$this->content_nav();
		$this->content_search();
		$this->content_actions();

		$this->start_controls_section(
			'sec_hd_cta',
			array(
				'label' => __( 'دکمه اقدام (CTA)', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_cta',
			array(
				'label'   => __( 'نمایش دکمه اقدام', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'cta_text',
			array(
				'label'     => __( 'متن دکمه', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'مشاهده فروشگاه', 'hamishop-elementor-widgets' ),
				'condition' => array( 'show_cta' => 'yes' ),
			)
		);

		$this->add_control(
			'cta_url',
			array(
				'label'     => __( 'لینک دکمه', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::URL,
				'default'   => array( 'url' => $this->shop_url() ),
				'condition' => array( 'show_cta' => 'yes' ),
			)
		);

		$this->end_controls_section();

		$this->content_style( 'minimal' );

		$this->start_controls_section(
			'sec_hd_min_style',
			array(
				'label' => __( 'پیل شیشه‌ای', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'pill_shadow',
				'label'    => __( 'سایه پیل', 'hamishop-elementor-widgets' ),
				'selector' => '{{WRAPPER}} .hsw-hd__pill',
			)
		);

		$this->add_control(
			'pill_blur',
			array(
				'label'      => __( 'میزان بلور شیشه', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 40,
					),
				),
				'default'    => array(
					'size' => 18,
					'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .hsw-hd' => '--hsw-hd-blur: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s            = $this->effective_settings();
		$uid          = $this->uid();
		$show_search  = ! isset( $s['show_search'] ) || 'yes' === $s['show_search'];
		$show_account = ! isset( $s['show_account'] ) || 'yes' === $s['show_account'];
		$show_cart    = ! isset( $s['show_cart'] ) || 'yes' === $s['show_cart'];
		$show_nav     = ! isset( $s['show_nav'] ) || 'yes' === $s['show_nav'];
		$show_cta     = ! isset( $s['show_cta'] ) || 'yes' === $s['show_cta'];
		$search_id    = $uid . '-minsearch';
		$drawer_id    = $uid . '-drawer';

		echo $this->open_root( 'minimal', $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.

		echo '<div class="hsw-hd__minwrap">';
		echo '<div class="hsw-hd__pill">';

		echo '<button class="hsw-hd__burger" type="button" data-hsw-hd-drawer-open aria-controls="' . esc_attr( $drawer_id ) . '" aria-expanded="false" aria-label="' . esc_attr__( 'باز کردن منو', 'hamishop-elementor-widgets' ) . '">' . HSW_Helpers::icon( 'menu' ) . '</button>';

		echo $this->brand_html( $s, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( $show_nav ) {
			echo '<nav class="hsw-hd__minnav" aria-label="' . esc_attr__( 'منوی اصلی', 'hamishop-elementor-widgets' ) . '">' . $this->menu_html( $s ) . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '<div class="hsw-hd__minactions">';
		if ( $show_search ) {
			echo '<button class="hsw-hd__iconbtn" type="button" data-hsw-hd-search-toggle aria-controls="' . esc_attr( $search_id ) . '" aria-expanded="false" aria-label="' . esc_attr__( 'باز کردن جستجو', 'hamishop-elementor-widgets' ) . '">' . HSW_Helpers::icon( 'search' ) . '</button>';
		}
		if ( $show_account ) {
			echo $this->account_html( $s, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( $show_cart ) {
			echo $this->cart_html( $s, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( $show_cta ) {
			echo $this->cta_html( $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';

		echo '</div>';

		if ( $show_search ) {
			echo '<div class="hsw-hd__minsearch" id="' . esc_attr( $search_id ) . '" data-hsw-hd-search-panel aria-hidden="true">' . $this->search_form( $s ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '</div>';

		echo $this->drawer_html( $s, 'minimal' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $show_cart ) {
			echo $this->cart_drawer_html( $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
	}

	/**
	 * CTA button used in the pill and drawer.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	protected function cta_html( $s ) {
		$text = isset( $s['cta_text'] ) && '' !== trim( (string) $s['cta_text'] ) ? trim( (string) $s['cta_text'] ) : __( 'مشاهده فروشگاه', 'hamishop-elementor-widgets' );
		$url  = isset( $s['cta_url'] ) ? $s['cta_url'] : array();
		$url  = is_array( $url ) ? ( $url['url'] ?? '' ) : $url;
		$url  = $url ? $url : $this->shop_url();

		return '<a class="hsw-hd__cta" href="' . esc_url( $url ) . '"><span>' . esc_html( $text ) . '</span>' . HSW_Helpers::icon( 'arrow_left_alt' ) . '</a>';
	}
}
