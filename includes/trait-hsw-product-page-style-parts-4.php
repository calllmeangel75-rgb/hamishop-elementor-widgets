<?php
/**
 * Composite product-page Style sections: tabs, related products, trust strip.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

trait HSW_Product_Page_Style_Parts_4 {

	/** Tab strip, active/hover states, panel and review cards. */
	private function pps_register_tabs_section() {
		$this->pps_open( 'sec_style_tabs', __( 'تب‌ها و بخش‌های توضیحات', 'hamishop-elementor-widgets' ), array( 'ptb_on' => 'yes' ) );

		$this->pps_heading( 'tb_box_h', __( 'ظرف تب‌ها', 'hamishop-elementor-widgets' ), '' );
		$this->pps_box( 'tb_box', '{{WRAPPER}} .hsw-ptabs' );
		$this->pps_slider(
			'tb_mt',
			__( 'فاصله از بالا', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ptabs',
			'margin-top: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 100 ) ) )
		);

		$this->pps_heading( 'tb_nav_h', __( 'نوار تب‌ها', 'hamishop-elementor-widgets' ) );
		$this->pps_box( 'tb_nav', '{{WRAPPER}} .hsw-ptabs__nav', array( 'shadow' => false ) );
		$this->pps_slider(
			'tb_nav_gap',
			__( 'فاصله تب‌ها', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ptabs__nav',
			'gap: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ) )
		);
		$this->pps_color( 'tb_nav_line_c', __( 'رنگ خط زیر نوار', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__nav', 'border-bottom-color' );
		$this->pps_slider(
			'tb_nav_line_w',
			__( 'ضخامت خط زیر نوار', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ptabs__nav',
			'border-bottom-width: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 8 ) ) )
		);
		$this->pps_type( 'tb_btn_t', __( 'تایپوگرافی متن تب', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__btn' );
		$this->pps_color( 'tb_btn_c', __( 'رنگ تب غیرفعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__btn' );
		$this->pps_dimensions( 'tb_btn_pad', __( 'فاصله داخلی تب', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__btn' );
		$this->pps_color( 'tb_btn_hover_c', __( 'رنگ متن (هاور)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__btn:not(.is-active):hover', 'color', true );
		$this->pps_color( 'tb_btn_hover_bg', __( 'پس‌زمینه (هاور)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__btn:not(.is-active):hover', 'background-color', true );

		$this->pps_heading( 'tb_active_h', __( 'تب فعال', 'hamishop-elementor-widgets' ) );
		$this->pps_color( 'tb_active_c', __( 'رنگ متن تب فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__btn.is-active', 'color' );
		$this->pps_color( 'tb_active_bg', __( 'پس‌زمینه تب فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__btn.is-active', 'background-color' );
		$this->pps_color( 'tb_active_bc', __( 'رنگ خط زیر تب فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__btn.is-active', 'border-bottom-color' );
		$this->pps_slider(
			'tb_active_bw',
			__( 'ضخامت خط زیر تب فعال', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ptabs__btn.is-active',
			'border-bottom-width: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 8 ) ) )
		);

		$this->pps_heading( 'tb_review_h', __( 'کارت‌های دیدگاه', 'hamishop-elementor-widgets' ) );
		$this->pps_box( 'tb_rv', '{{WRAPPER}} .hsw-ptabs__rvitem', array( 'shadow' => false ) );
		$this->pps_type( 'tb_rv_name', __( 'نام نویسنده', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__rvname' );
		$this->pps_type( 'tb_rv_text', __( 'متن دیدگاه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__rvtext' );
		$this->pps_color( 'tb_rv_text_c', __( 'رنگ متن دیدگاه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__rvtext' );
		$this->pps_icon( 'tb_rv_star', __( 'ستاره‌های دیدگاه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__rvstars .hsw-ic' );
		$this->pps_close();
	}

	/** Related products carousel: heading, cards, price, CTA, badge, arrows. */
	private function pps_register_related_section() {
		$this->pps_open( 'sec_style_related', __( 'محصولات مرتبط', 'hamishop-elementor-widgets' ), array( 'prel_on' => 'yes' ) );

		$this->pps_heading( 'r_head_h', __( 'عنوان بخش', 'hamishop-elementor-widgets' ), '' );
		$this->pps_slider(
			'r_mt',
			__( 'فاصله از بالا', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-prel',
			'margin-top: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 120 ) ) )
		);
		$this->pps_type( 'r_title', __( 'تایپوگرافی عنوان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prel__title' );
		$this->pps_color( 'r_title_c', __( 'رنگ عنوان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prel__title' );
		$this->pps_type( 'r_sub', __( 'تایپوگرافی زیرعنوان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prel__sub' );
		$this->pps_color( 'r_sub_c', __( 'رنگ زیرعنوان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prel__sub' );
		$this->pps_color( 'r_more_c', __( 'رنگ لینک «مشاهده همه»', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prel__more' );
		$this->pps_color( 'r_more_h_c', __( 'رنگ لینک «مشاهده همه» (هاور)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prel__more:hover' );

		$this->pps_heading( 'r_track_h', __( 'ردیف کارت‌ها', 'hamishop-elementor-widgets' ) );
		$this->pps_slider(
			'r_gap',
			__( 'فاصله کارت‌ها', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-prel__track',
			'gap: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ) )
		);
		$this->pps_slider(
			'r_card_w',
			__( 'عرض هر کارت', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-prel__card',
			'flex: 0 0 {{SIZE}}{{UNIT}}; max-width: {{SIZE}}{{UNIT}};',
			array(
				'units' => array( '%', 'px' ),
				'range' => array(
					'%'  => array( 'min' => 15, 'max' => 100 ),
					'px' => array( 'min' => 120, 'max' => 420 ),
				),
			)
		);
		$this->pps_box( 'r_card', '{{WRAPPER}} .hsw-prel__card' );
		$this->pps_slider(
			'r_card_gap',
			__( 'فاصله داخلی کارت', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-prel__card',
			'gap: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ) )
		);
		$this->pps_shadow( 'r_card_hover', __( 'سایه هاور کارت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prel__card:hover' );
		$this->pps_slider(
			'r_media_radius',
			__( 'گردی گوشه تصویر', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-prel__media',
			'border-radius: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ) )
		);
		$this->pps_select(
			'r_media_ratio',
			__( 'نسبت تصویر کارت', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-prel__media',
			'aspect-ratio',
			array(
				''       => __( 'پیش‌فرض (۱:۱)', 'hamishop-elementor-widgets' ),
				'1 / 1'  => __( 'مربع ۱:۱', 'hamishop-elementor-widgets' ),
				'4 / 3'  => __( '۴:۳', 'hamishop-elementor-widgets' ),
				'3 / 4'  => __( '۳:۴', 'hamishop-elementor-widgets' ),
				'16 / 9' => __( '۱۶:۹', 'hamishop-elementor-widgets' ),
			)
		);
		$this->pps_select(
			'r_media_fit',
			__( 'نحوه قرارگیری تصویر', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-prel__media img',
			'object-fit',
			array(
				''        => __( 'پیش‌فرض (cover)', 'hamishop-elementor-widgets' ),
				'cover'   => __( 'پر کردن کادر', 'hamishop-elementor-widgets' ),
				'contain' => __( 'کامل داخل کادر', 'hamishop-elementor-widgets' ),
			)
		);

		$this->pps_heading( 'r_info_h', __( 'متن و قیمت کارت', 'hamishop-elementor-widgets' ) );
		$this->pps_type( 'r_name', __( 'نام محصول', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prel__name' );
		$this->pps_color( 'r_name_c', __( 'رنگ نام محصول', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prel__name' );
		$this->pps_color( 'r_name_h_c', __( 'رنگ نام محصول (هاور)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prel__name:hover' );
		$this->pps_type( 'r_price', __( 'قیمت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prel__price' );
		$this->pps_color( 'r_price_c', __( 'رنگ قیمت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prel__price' );
		$this->pps_type( 'r_old', __( 'قیمت قبلی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prel__price del' );
		$this->pps_color( 'r_old_c', __( 'رنگ قیمت قبلی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prel__price del' );
		$this->pps_type( 'r_cta', __( 'دکمه کارت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prel__cta' );
		$this->pps_color( 'r_cta_bg', __( 'پس‌زمینه دکمه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prel__cta', 'background-color' );
		$this->pps_color( 'r_cta_c', __( 'رنگ متن دکمه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prel__cta' );
		$this->pps_color( 'r_cta_hover_bg', __( 'پس‌زمینه دکمه (هاور)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prel__cta:hover', 'background-color' );
		$this->pps_color( 'r_cta_hover_c', __( 'رنگ متن دکمه (هاور)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prel__cta:hover' );
		$this->pps_slider(
			'r_cta_height',
			__( 'ارتفاع دکمه', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-prel__cta',
			'height: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 28, 'max' => 60 ) ) )
		);
		$this->pps_slider(
			'r_cta_radius',
			__( 'گردی گوشه دکمه', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-prel__cta',
			'border-radius: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ) )
		);

		$this->pps_heading( 'r_badge_h', __( 'برچسب تخفیف کارت', 'hamishop-elementor-widgets' ) );
		$this->pps_box( 'r_badge', '{{WRAPPER}} .hsw-prel__badge', array( 'shadow' => false ) );
		$this->pps_type( 'r_badge_t', __( 'تایپوگرافی برچسب', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prel__badge' );
		$this->pps_color( 'r_badge_c', __( 'رنگ متن برچسب', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prel__badge' );

		$this->pps_heading( 'r_nav_h', __( 'دکمه‌های جابه‌جایی', 'hamishop-elementor-widgets' ) );
		$this->pps_box( 'r_nav', '{{WRAPPER}} .hsw-prel__nav', array( 'shadow' => false ) );
		$this->pps_color( 'r_nav_c', __( 'رنگ آیکون', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prel__nav', 'color', true );
		$this->pps_color( 'r_nav_hover_c', __( 'رنگ آیکون (هاور)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-prel__nav:hover', 'color', true );
		$this->pps_slider(
			'r_nav_size',
			__( 'اندازه دکمه', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-prel__nav',
			'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 24, 'max' => 64 ) ) )
		);
		$this->pps_close();
	}

	/** Trust strip under the purchase box. */
	private function pps_register_trust_section() {
		$this->pps_open( 'sec_style_trust', __( 'سیگنال‌های اعتماد', 'hamishop-elementor-widgets' ), array( 'ptr_on' => 'yes' ) );

		$this->pps_heading( 'tr_box_h', __( 'نوار اعتماد', 'hamishop-elementor-widgets' ), '' );
		$this->pps_box( 'tr_box', '{{WRAPPER}} .hsw-ptrust' );
		$this->pps_icon( 'tr_icon', __( 'آیکون‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptrust__ic' );
		$this->pps_type( 'tr_title', __( 'عنوان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptrust__t' );
		$this->pps_color( 'tr_title_c', __( 'رنگ عنوان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptrust__t' );
		$this->pps_type( 'tr_sub', __( 'زیرعنوان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptrust__s' );
		$this->pps_color( 'tr_sub_c', __( 'رنگ زیرعنوان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptrust__s' );
		$this->pps_color( 'tr_div_c', __( 'رنگ جداکننده‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptrust__item + .hsw-ptrust__item', 'border-color' );
		$this->pps_close();
	}
}
