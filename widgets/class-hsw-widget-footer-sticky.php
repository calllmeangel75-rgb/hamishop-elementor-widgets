<?php
/**
 * Widget: فوتر چسبان (Sticky Footer Bar).
 *
 * A fixed bottom navigation for phones: home, shop, cart, wishlist, account.
 * Everything is scoped under `.hsw-sf` and the bar sits at z-index 9000 — below
 * the add-to-cart sheet (9990) and the product purchase bar (9995) — so it can
 * never fight with the rest of the plugin. A spacer with the same height is
 * printed so the last section is not hidden behind it.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Group_Control_Typography;

class HSW_Widget_Footer_Sticky extends HSW_Widget_Base {

	public function get_name() {
		return 'hsw-footer-sticky';
	}

	public function get_title() {
		return __( 'فوتر چسبان (HamiShop)', 'hamishop-elementor-widgets' );
	}

	public function get_icon() {
		return 'eicon-footer';
	}

	public function get_categories() {
		/* Must be a registered category, otherwise Elementor hides the widget. */
		return array( 'hamishop-header' );
	}

	public function get_keywords() {
		return array( 'footer', 'sticky', 'فوتر', 'چسبان', 'موبایل', 'hamishop' );
	}

	/**
	 * Only the shared font base + its own stylesheet — hsw-loop is not needed.
	 *
	 * @return array
	 */
	public function get_style_depends() {
		return array( 'hsw-fonts', 'hsw-footer' );
	}

	/**
	 * The bar is pure CSS (no JS).
	 *
	 * @return array
	 */
	public function get_script_depends() {
		return array();
	}

	/**
	 * Default items: the five shortcuts the shop needs.
	 *
	 * @return array
	 */
	private function defaults() {
		$cart = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
		$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		$acct = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );

		return array(
			array( 'text' => 'خانه', 'url' => home_url( '/' ), 'icon' => 'home' ),
			array( 'text' => 'فروشگاه', 'url' => $shop, 'icon' => 'storefront' ),
			array( 'text' => 'سبد خرید', 'url' => $cart, 'icon' => 'shopping_cart' ),
			array( 'text' => 'علاقه‌مندی‌ها', 'url' => home_url( '/my-account/wishlist/' ), 'icon' => 'heart' ),
			array( 'text' => 'حساب کاربری', 'url' => $acct, 'icon' => 'person' ),
		);
	}

	protected function register_controls() {
		/* ------------------------------ CONTENT ------------------------------ */
		$this->start_controls_section(
			'sec_items',
			array( 'label' => __( 'آیتم‌ها', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
		);

		$this->add_control(
			'show_title',
			array(
				'label'   => __( 'نمایش عنوان بخش', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => '',
			)
		);

		$this->add_control(
			'title',
			array(
				'label'       => __( 'عنوان', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'دسترسی سریع', 'hamishop-elementor-widgets' ),
				'label_block' => true,
				'condition'   => array( 'show_title' => 'yes' ),
			)
		);

		$rep = new Repeater();
		$rep->add_control( 'text', array( 'label' => __( 'متن لینک', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'خانه', 'label_block' => true ) );
		$rep->add_control( 'url', array( 'label' => __( 'آدرس', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::URL, 'default' => array( 'url' => '#' ), 'label_block' => true ) );
		/*
		 * Our sprite is not an Elementor icon library, so the ICONS control
		 * would render an empty picker and drop the default. A select of the
		 * plugin's own icon names keeps the defaults working and the UI clear.
		 */
		$rep->add_control(
			'icon',
			array(
				'label'   => __( 'آیکن', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'home',
				'options' => array(
					'home'          => __( 'خانه', 'hamishop-elementor-widgets' ),
					'storefront'    => __( 'فروشگاه', 'hamishop-elementor-widgets' ),
					'shopping_cart' => __( 'سبد خرید', 'hamishop-elementor-widgets' ),
					'heart'         => __( 'قلب / علاقه‌مندی', 'hamishop-elementor-widgets' ),
					'person'        => __( 'کاربر', 'hamishop-elementor-widgets' ),
					'wallet'        => __( 'کیف پول', 'hamishop-elementor-widgets' ),
					'settings'      => __( 'تنظیمات', 'hamishop-elementor-widgets' ),
					'grid_view'     => __( 'دسته‌بندی', 'hamishop-elementor-widgets' ),
					'local_shipping' => __( 'سفارش‌ها / ارسال', 'hamishop-elementor-widgets' ),
					'support_agent' => __( 'پشتیبانی', 'hamishop-elementor-widgets' ),
					'search'        => __( 'جستجو', 'hamishop-elementor-widgets' ),
					'star'          => __( 'ستاره', 'hamishop-elementor-widgets' ),
					'logout'        => __( 'خروج', 'hamishop-elementor-widgets' ),
					'check'         => __( 'تیک', 'hamishop-elementor-widgets' ),
					'none'          => __( 'بدون آیکن', 'hamishop-elementor-widgets' ),
				),
			)
		);
		$rep->add_control( 'new_tab', array( 'label' => __( 'بازشدن در تب جدید', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::SWITCHER ) );
		$rep->add_control( 'nofollow', array( 'label' => __( 'nofollow', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::SWITCHER ) );

		$this->add_control(
			'items',
			array(
				'label'       => __( 'فهرست آیتم‌ها', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $rep->get_controls(),
				'default'     => $this->defaults(),
				'title_field' => '{{{ text }}}',
			)
		);

		$this->add_control(
			'icon_pos',
			array(
				'label'   => __( 'جای آیکن', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'before',
				'options' => array(
					'before' => __( 'قبل از متن', 'hamishop-elementor-widgets' ),
					'after'  => __( 'بعد از متن', 'hamishop-elementor-widgets' ),
					'hide'   => __( 'بدون آیکن', 'hamishop-elementor-widgets' ),
				),
			)
		);

		$this->add_responsive_control(
			'align',
			array(
				'label'     => __( 'تراز آیتم‌ها', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::CHOOSE,
				'default'   => 'center',
				'options'   => array(
					'flex-start' => array( 'title' => __( 'شروع', 'hamishop-elementor-widgets' ), 'icon' => 'eicon-text-align-right' ),
					'center'     => array( 'title' => __( 'وسط', 'hamishop-elementor-widgets' ), 'icon' => 'eicon-text-align-center' ),
					'flex-end'   => array( 'title' => __( 'پایان', 'hamishop-elementor-widgets' ), 'icon' => 'eicon-text-align-left' ),
				),
				'selectors' => array( '{{WRAPPER}} .hsw-sf__item' => 'align-items: {{VALUE}}; text-align: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();

		/* ------------------------------- STYLE ------------------------------- */
		$this->start_controls_section(
			'sec_style_bar',
			array( 'label' => __( 'نوار', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
		);

		$this->add_control(
			'show_on_checkout',
			array(
				'label'       => __( 'نمایش در سبد، پرداخت و حساب کاربری', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => '',
				'description' => __( 'به‌صورت پیش‌فرض در این صفحات پنهان است تا مزاحم تکمیل سفارش نشود.', 'hamishop-elementor-widgets' ),
				'separator'   => 'before',
			)
		);

		$this->add_control(
			'z_index',
			array(
				'label'       => __( 'لایه (z-index)', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => 90,
				'min'         => 1,
				'max'         => 99999,
				'selectors'   => array( '{{WRAPPER}} .hsw-sf' => 'z-index: {{VALUE}};' ),
				'description' => __( 'پیش‌فرض ۹۰ است تا همیشه زیر هدر و منوی موبایل بماند (هدر ۱۰۰).', 'hamishop-elementor-widgets' ),
			)
		);

		$this->add_control(
			'glass',
			array(
				'label'       => __( 'حالت شیشه‌ای', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::SWITCHER,
				'description' => __( 'پس‌زمینهٔ نیمه‌شفاف با بلور پشت نوار.', 'hamishop-elementor-widgets' ),
				'selectors'   => array(
					'{{WRAPPER}} .hsw-sf' => 'backdrop-filter: blur(18px) saturate(180%); -webkit-backdrop-filter: blur(18px) saturate(180%);',
				),
			)
		);

		$this->add_control(
			'glass_bg',
			array(
				'label'     => __( 'رنگ پس‌زمینهٔ شیشه', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => 'rgba(255,255,255,.72)',
				'condition' => array( 'glass' => 'yes' ),
				'selectors' => array( '{{WRAPPER}} .hsw-sf' => 'background: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array( 'name' => 'bar_bg', 'types' => array( 'classic', 'gradient' ), 'selector' => '{{WRAPPER}} .hsw-sf' )
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array( 'name' => 'bar_border', 'selector' => '{{WRAPPER}} .hsw-sf' )
		);

		$this->add_responsive_control(
			'bar_radius',
			array(
				'label'      => __( 'گردی گوشه‌ها', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .hsw-sf' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array( 'name' => 'bar_shadow', 'selector' => '{{WRAPPER}} .hsw-sf' )
		);

		$this->add_responsive_control(
			'bar_padding',
			array(
				'label'      => __( 'فاصلهٔ داخلی', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px' ),
				'selectors'  => array( '{{WRAPPER}} .hsw-sf' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'sec_style_items',
			array( 'label' => __( 'آیتم‌ها', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
		);

		$this->add_responsive_control(
			'gap',
			array(
				'label'      => __( 'فاصلهٔ بین لینک‌ها', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}} .hsw-sf__nav' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'link_c',
			array(
				'label'     => __( 'رنگ لینک', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .hsw-sf__item' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'link_hover_c',
			array(
				'label'     => __( 'رنگ هاور / فوکوس', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .hsw-sf__item:hover, {{WRAPPER}} .hsw-sf__item:focus-visible, {{WRAPPER}} .hsw-sf__item.is-active' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array( 'name' => 'link_typo', 'selector' => '{{WRAPPER}} .hsw-sf__item' )
		);

		$this->add_responsive_control(
			'icon_size',
			array(
				'label'      => __( 'اندازهٔ آیکن', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 10, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .hsw-sf__ic' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'icon_c',
			array(
				'label'     => __( 'رنگ آیکن', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .hsw-sf__ic' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'icon_gap',
			array(
				'label'      => __( 'فاصلهٔ آیکن تا متن', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 20 ) ),
				'selectors'  => array( '{{WRAPPER}} .hsw-sf__item' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'sec_style_title',
			array(
				'label'     => __( 'عنوان', 'hamishop-elementor-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_title' => 'yes' ),
			)
		);

		$this->add_control(
			'title_c',
			array(
				'label'     => __( 'رنگ عنوان', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .hsw-sf__title' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array( 'name' => 'title_typo', 'selector' => '{{WRAPPER}} .hsw-sf__title' )
		);

		$this->add_responsive_control(
			'title_gap',
			array(
				'label'      => __( 'فاصله از فهرست', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .hsw-sf__title' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();

		/* Hidden on cart / checkout / order-received / account unless asked. */
		if ( 'yes' !== ( $s['show_on_checkout'] ?? '' ) ) {
			$hide = ( function_exists( 'is_cart' ) && is_cart() )
				|| ( function_exists( 'is_checkout' ) && is_checkout() )
				|| ( function_exists( 'is_account_page' ) && is_account_page() )
				|| ( function_exists( 'is_wc_endpoint_url' ) && ( is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'order-pay' ) ) );

			/* Custom «payment / checkout / cart» pages built with Elementor. */
			if ( ! $hide ) {
				$slug = (string) get_post_field( 'post_name', get_queried_object_id() );
				if ( '' !== $slug && preg_match( '/^(payment|checkout|cart|my-account|order-pay)/', $slug ) ) {
					$hide = true;
				}
			}
			if ( $hide ) {
				return;
			}
		}

		$items = isset( $s['items'] ) && is_array( $s['items'] ) ? $s['items'] : array();
		if ( empty( $items ) ) {
			$items = $this->defaults();
		}

		/*
		 * A saved row can carry an empty URL or the placeholder `#` (the
		 * control was cleared, or a template was imported without links).
		 * Rendering `href="#"` made every tab on the mobile bar dead, so fall
		 * back to the matching default shortcut; a row beyond the defaults
		 * keeps the anchor harmless.
		 */
		$fallback = $this->defaults();

		$pos   = $s['icon_pos'] ?? 'before';
		$title = isset( $s['title'] ) ? trim( (string) $s['title'] ) : '';

		echo '<nav class="hsw-sf" aria-label="' . esc_attr__( 'دسترسی سریع', 'hamishop-elementor-widgets' ) . '">';
		if ( 'yes' === ( $s['show_title'] ?? '' ) && '' !== $title ) {
			echo '<span class="hsw-sf__title">' . esc_html( $title ) . '</span>';
		}
		echo '<div class="hsw-sf__nav">';
		foreach ( array_values( $items ) as $index => $item ) {
			$url    = is_array( $item['url'] ?? null ) ? ( $item['url']['url'] ?? '' ) : (string) ( $item['url'] ?? '' );
			$text   = (string) ( $item['text'] ?? '' );
			$icon   = is_array( $item['icon'] ?? null ) ? ( $item['icon']['value'] ?? '' ) : (string) ( $item['icon'] ?? '' );
			if ( in_array( trim( $url ), array( '', '#' ), true ) && isset( $fallback[ $index ]['url'] ) ) {
				$url = $fallback[ $index ]['url'];
			}
			if ( 'none' === $icon ) {
				$icon = '';
			}
			/* One rel attribute only: noopener for _blank, nofollow on demand. */
			$new_tab = ! empty( $item['new_tab'] ) && 'yes' === $item['new_tab'];
			$rels    = array();
			if ( $new_tab ) {
				$rels[] = 'noopener';
			}
			if ( ! empty( $item['nofollow'] ) && 'yes' === $item['nofollow'] ) {
				$rels[] = 'nofollow';
			}
			$target = $new_tab ? ' target="_blank"' : '';
			$rel    = $rels ? ' rel="' . esc_attr( implode( ' ', $rels ) ) . '"' : '';

			echo '<a class="hsw-sf__item" href="' . esc_url( $url ? $url : '#' ) . '"' . $target . $rel . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes are literals.
			if ( 'before' === $pos && '' !== $icon ) {
				echo HSW_Helpers::icon( $icon, 'hsw-sf__ic' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '<span class="hsw-sf__label">' . esc_html( $text ) . '</span>';
			if ( 'after' === $pos && '' !== $icon ) {
				echo HSW_Helpers::icon( $icon, 'hsw-sf__ic' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</a>';
		}
		echo '</div></nav>';
	}
}
