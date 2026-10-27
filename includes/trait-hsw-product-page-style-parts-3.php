<?php
/**
 * Composite product-page Style sections: purchase and messages.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

trait HSW_Product_Page_Style_Parts_3 {

	/** Purchase box, quantity stepper and add-to-cart button states. */
	private function pps_register_purchase_section() {
		$this->pps_open( 'sec_style_purchase', __( 'تعداد و دکمه افزودن به سبد', 'hamishop-elementor-widgets' ), array( 'ppu_on' => 'yes' ) );

		$this->pps_heading( 'pu_box_h', __( 'جعبه خرید', 'hamishop-elementor-widgets' ), '' );
		$this->pps_box( 'pu_box', '{{WRAPPER}} .hsw-ppurch' );
		$this->pps_slider(
			'pu_gap',
			__( 'فاصله ردیف‌های جعبه', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ppurch',
			'gap: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ) )
		);

		$this->pps_heading( 'pu_qty_h', __( 'تعداد', 'hamishop-elementor-widgets' ) );
		$this->pps_note( 'pu_stepper_note', __( 'عرض استپر در حالت «در سبد»: مقدار خالی در دسکتاپ ۳۰٪، در تبلت و موبایل ۱۰۰٪ است. برای تغییر هر دستگاه از کنترل واکنش‌گرا استفاده کنید.', 'hamishop-elementor-widgets' ) );
		$this->pps_slider(
			'pu_stepper_w',
			__( 'عرض استپر (در سبد)', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ppurch',
			'--hsw-stepper-w: {{SIZE}}{{UNIT}};',
			array(
				'units' => array( '%', 'px' ),
				'range' => array(
					'%'  => array( 'min' => 10, 'max' => 100 ),
					'px' => array( 'min' => 80, 'max' => 320 ),
				),
			)
		);
		$this->pps_slider(
			'pu_stepper_h',
			__( 'ارتفاع استپر', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ppurch__stepper',
			'height: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 32, 'max' => 64 ) ) )
		);
		$this->pps_box( 'pu_stepper', '{{WRAPPER}} .hsw-ppurch__stepper', array( 'shadow' => false ) );
		$this->pps_slider(
			'pu_qbtn_w',
			__( 'عرض دکمه‌های تعداد', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ppurch__qbtn',
			'width: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 24, 'max' => 56 ) ) )
		);
		$this->pps_slider(
			'pu_qbtn_h',
			__( 'ارتفاع دکمه‌های تعداد', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ppurch__qbtn',
			'height: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 24, 'max' => 56 ) ) )
		);
		$this->pps_var_color( 'pu_qty_c', __( 'رنگ عدد و آیکون‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__stepper', '--hsw-qty-c' );
		$this->pps_type( 'pu_qty_t', __( 'تایپوگرافی عدد', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__qval' );
		$this->pps_color( 'pu_qbtn_hover_bg', __( 'پس‌زمینه دکمه تعداد (هاور)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__qbtn:hover', 'background-color' );
		$this->pps_slider(
			'pu_qbtn_disabled_o',
			__( 'شفافیت دکمه تعداد غیرفعال', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ppurch__qbtn:disabled',
			'opacity: {{SIZE}};',
			array(
				'units' => array( 'px' ),
				'range' => array( 'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.05 ) ),
			)
		);

		$this->pps_heading( 'pu_cart_head', __( 'دکمه افزودن به سبد', 'hamishop-elementor-widgets' ) );
		$this->pps_var_color( 'pu_cart_bg', __( 'پس‌زمینه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__cart', '--hsw-cart-bg' );
		$this->pps_var_color( 'pu_cart_c', __( 'رنگ متن', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__cart', '--hsw-cart-c' );
		$this->pps_type( 'pu_cart_t', __( 'تایپوگرافی دکمه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__cart' );
		$this->pps_slider(
			'pu_cart_height',
			__( 'ارتفاع دکمه', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ppurch__cart',
			'height: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 36, 'max' => 72 ) ) )
		);
		$this->pps_slider(
			'pu_cart_mw',
			__( 'حداکثر عرض دکمه', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ppurch__cart',
			'max-width: {{SIZE}}{{UNIT}}; margin-inline: auto;',
			array(
				'units' => array( '%', 'px' ),
				'range' => array(
					'%'  => array( 'min' => 30, 'max' => 100 ),
					'px' => array( 'min' => 120, 'max' => 900 ),
				),
			)
		);
		$this->pps_slider(
			'pu_cart_radius',
			__( 'گردی گوشه دکمه', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ppurch__cart',
			'border-radius: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ) )
		);
		$this->pps_slider(
			'pu_cart_bw',
			__( 'ضخامت حاشیه دکمه', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ppurch__cart',
			'border-width: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 8 ) ) )
		);
		$this->pps_color( 'pu_cart_bc', __( 'رنگ حاشیه دکمه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__cart', 'border-color' );
		$this->pps_slider(
			'pu_cart_icon',
			__( 'اندازه آیکون دکمه', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ppurch__cart .hsw-ic',
			'font-size: {{SIZE}}{{UNIT}} !important;',
			array( 'range' => array( 'px' => array( 'min' => 12, 'max' => 34 ) ) )
		);

		$this->pps_heading( 'pu_cart_hover_h', __( 'حالت هاور / فوکوس', 'hamishop-elementor-widgets' ) );
		$this->pps_var_color( 'pu_cart_hover_bg', __( 'پس‌زمینه هاور', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__cart', '--hsw-cart-bg-hover' );
		$this->pps_var_color( 'pu_cart_hover_c', __( 'رنگ متن هاور', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__cart', '--hsw-cart-c-hover' );
		$this->pps_color( 'pu_cart_focus_bg', __( 'پس‌زمینه فوکوس', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__cart:focus-visible', 'background-color', true );
		$this->pps_color( 'pu_cart_focus_c', __( 'رنگ متن فوکوس', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__cart:focus-visible', 'color', true );
		$this->pps_color( 'pu_cart_focus_oc', __( 'رنگ حلقه فوکوس', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__cart:focus-visible', 'outline-color', true );
		$this->pps_slider(
			'pu_cart_focus_ow',
			__( 'ضخامت حلقه فوکوس', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ppurch__cart:focus-visible',
			'outline-width: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 8 ) ) )
		);

		$this->pps_heading( 'pu_cart_states_h', __( 'حالت‌های دکمه', 'hamishop-elementor-widgets' ) );
		$this->pps_color( 'pu_cart_disabled_bg', __( 'پس‌زمینه غیرفعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__cart:disabled', 'background-color', true );
		$this->pps_color( 'pu_cart_disabled_c', __( 'رنگ متن غیرفعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__cart:disabled', 'color', true );
		$this->pps_slider(
			'pu_cart_disabled_o',
			__( 'شفافیت غیرفعال', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ppurch__cart:disabled',
			'opacity: {{SIZE}};',
			array(
				'units' => array( 'px' ),
				'range' => array( 'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.05 ) ),
			)
		);
		$this->pps_slider(
			'pu_cart_busy_o',
			__( 'شفافیت در حال افزودن', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ppurch__cart.is-busy',
			'opacity: {{SIZE}};',
			array(
				'units' => array( 'px' ),
				'range' => array( 'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.05 ) ),
			)
		);
		$this->pps_var_color( 'pu_cart_added_bg', __( 'پس‌زمینه «افزوده شد»', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__cart', '--hsw-cart-added-bg' );
		$this->pps_var_color( 'pu_cart_added_c', __( 'رنگ متن «افزوده شد»', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__cart', '--hsw-cart-added-c' );

		$this->pps_heading( 'pu_assure_h', __( 'متن‌های اعتماد زیر دکمه', 'hamishop-elementor-widgets' ) );
		$this->pps_type( 'pu_assure_t', __( 'تایپوگرافی متن‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__assure' );
		$this->pps_color( 'pu_assure_c', __( 'رنگ متن‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__assure' );
		$this->pps_color( 'pu_assure_bc', __( 'رنگ خط بالای متن‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__assure', 'border-top-color' );
		$this->pps_icon( 'pu_assure_i', __( 'آیکون‌های اعتماد', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__as .hsw-ic' );

		$this->pps_heading( 'pu_label_h', __( 'برچسب تعداد', 'hamishop-elementor-widgets' ) );
		$this->pps_type( 'pu_label_t', __( 'تایپوگرافی برچسب', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__qtylabel' );
		$this->pps_color( 'pu_label_c', __( 'رنگ برچسب', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__qtylabel' );
		$this->pps_close();
	}

	/** WooCommerce notices, success/error/info messages and sticky toast. */
	private function pps_register_messages_section() {
		$this->pps_open( 'sec_style_messages', __( 'پیام‌ها و اعلان‌ها', 'hamishop-elementor-widgets' ) );
		$this->pps_note( 'm_note', __( 'اعلان‌های ووکامرس داخل همین ویجت چاپ می‌شوند. پیام شناور سراسری افزودن به سبد از استایل مشترک HamiShop استفاده می‌کند و از این بخش جدا است.', 'hamishop-elementor-widgets' ) );

		$this->pps_heading( 'm_wrap_h', __( 'ظرف اعلان‌ها', 'hamishop-elementor-widgets' ), '' );
		$this->pps_slider(
			'm_wrap_mb',
			__( 'فاصله زیر ظرف', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-notices',
			'margin-bottom: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 60 ) ) )
		);
		$this->pps_slider(
			'm_wrap_gap',
			__( 'فاصله بین پیام‌ها', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-notices',
			'gap: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ) )
		);

		$this->pps_notice_style( 'm_success', __( 'پیام موفقیت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-notices .woocommerce-message' );

		$this->pps_notice_style( 'm_error', __( 'پیام خطا', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-notices .woocommerce-error' );

		$this->pps_notice_style( 'm_info', __( 'پیام اطلاع‌رسانی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-notices .woocommerce-info' );

		$this->pps_heading( 'm_toast_h', __( 'توست نوار خرید چسبان', 'hamishop-elementor-widgets' ) );
		$this->pps_box( 'm_toast', '{{WRAPPER}} .hsw-psticky__toast', array( 'shadow' => false ) );
		$this->pps_type( 'm_toast_t', __( 'تایپوگرافی توست', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-psticky__toast' );
		$this->pps_color( 'm_toast_c', __( 'رنگ متن توست', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-psticky__toast', 'color' );
		$this->pps_icon( 'm_toast_i', __( 'آیکون توست', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-psticky__toast .hsw-ic' );
		$this->pps_close();
	}
}
