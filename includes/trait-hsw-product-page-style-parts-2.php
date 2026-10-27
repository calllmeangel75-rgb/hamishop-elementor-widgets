<?php
/**
 * Composite product-page Style sections: price, description, features, variants.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

trait HSW_Product_Page_Style_Parts_2 {

	/** Price, old price, unit, discount pill and call-for-price. */
	private function pps_register_price_section() {
		$this->pps_open( 'sec_style_price', __( 'قیمت', 'hamishop-elementor-widgets' ), array( 'ppu_on' => 'yes' ) );

		$this->pps_heading( 'p_main_head', __( 'قیمت اصلی', 'hamishop-elementor-widgets' ), '' );
		$this->pps_type( 'p_main', __( 'تایپوگرافی قیمت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__main' );
		$this->pps_color( 'p_main_c', __( 'رنگ قیمت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__main' );
		$this->pps_type( 'p_unit', __( 'تایپوگرافی واحد', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__unit' );
		$this->pps_color( 'p_unit_c', __( 'رنگ واحد', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__unit' );
		$this->pps_slider(
			'p_row_gap',
			__( 'فاصله قیمت و واحد', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ppurch__row',
			'gap: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ) )
		);
		$this->pps_align( 'p_align', __( 'تراز قیمت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__top' );

		$this->pps_heading( 'p_old_head', __( 'قیمت قبل از تخفیف', 'hamishop-elementor-widgets' ) );
		$this->pps_type( 'p_old', __( 'تایپوگرافی قیمت قبلی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__old' );
		$this->pps_color( 'p_old_c', __( 'رنگ قیمت قبلی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__old' );
		$this->pps_slider(
			'p_old_gap',
			__( 'فاصله اجزای ردیف قیمت قبلی', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ppurch__oldrow',
			'gap: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ) )
		);
		$this->pps_color( 'p_old_line', __( 'رنگ خط روی قیمت قبلی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__old', 'text-decoration-color' );

		$this->pps_heading( 'p_save_h', __( 'برچسب تخفیف', 'hamishop-elementor-widgets' ) );
		$this->pps_box( 'p_save', '{{WRAPPER}} .hsw-ppurch__save', array( 'shadow' => false ) );
		$this->pps_type( 'p_save_t', __( 'تایپوگرافی برچسب', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__save' );
		$this->pps_color( 'p_save_c', __( 'رنگ متن برچسب', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__save', 'color', true );
		$this->pps_slider(
			'p_save_mb',
			__( 'فاصله زیر برچسب', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ppurch__save',
			'margin-bottom: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ) )
		);

		$this->pps_heading( 'p_divider_h', __( 'جداکننده قیمت', 'hamishop-elementor-widgets' ) );
		$this->pps_color( 'p_div_c', __( 'رنگ خط', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__top', 'border-bottom-color' );
		$this->pps_slider(
			'p_div_w',
			__( 'ضخامت خط', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ppurch__top',
			'border-bottom-width: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 8 ) ) )
		);
		$this->pps_slider(
			'p_div_pad',
			__( 'فاصله زیر قیمت', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ppurch__top',
			'padding-bottom: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ) )
		);

		$this->pps_heading( 'p_cfp_h', __( 'قیمت تماس (Call for Price)', 'hamishop-elementor-widgets' ) );
		$this->pps_type( 'p_cfp_k', __( 'برچسب قیمت تماس', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__cfp-kicker' );
		$this->pps_type( 'p_cfp_v', __( 'مقدار قیمت تماس', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__cfp-value' );
		$this->pps_color( 'p_cfp_c', __( 'رنگ قیمت تماس', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch__cfp-value' );
		$this->pps_close();
	}

	/** Description panel, specification table and review content. */
	private function pps_register_info_section() {
		$this->pps_open( 'sec_style_info', __( 'متن معرفی و اطلاعات', 'hamishop-elementor-widgets' ), array( 'ptb_on' => 'yes' ) );

		$this->pps_heading( 'i_panel_h', __( 'پنل توضیحات', 'hamishop-elementor-widgets' ), '' );
		$this->pps_box( 'i_panel', '{{WRAPPER}} .hsw-ptabs__panel', array( 'shadow' => false ) );
		$this->pps_type( 'i_text', __( 'تایپوگرافی متن', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__panel' );
		$this->pps_color( 'i_text_c', __( 'رنگ متن', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__panel' );
		$this->pps_align( 'i_align', __( 'تراز متن', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__panel' );
		$this->pps_slider(
			'i_para_gap',
			__( 'فاصله پاراگراف‌ها', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ptabs__review p',
			'margin-bottom: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ) )
		);
		$this->pps_type( 'i_h2', __( 'عنوان داخل توضیحات', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__h2' );
		$this->pps_color( 'i_h2_c', __( 'رنگ عنوان داخل توضیحات', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__h2' );
		$this->pps_color( 'i_link_c', __( 'رنگ لینک‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__panel a' );
		$this->pps_color( 'i_link_h_c', __( 'رنگ لینک‌ها (هاور)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__panel a:hover' );

		$this->pps_heading( 'i_table_h', __( 'جدول مشخصات', 'hamishop-elementor-widgets' ) );
		$this->pps_type( 'i_th', __( 'عنوان ستون', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__th' );
		$this->pps_color( 'i_th_c', __( 'رنگ عنوان ستون', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__th' );
		$this->pps_type( 'i_td', __( 'مقدار', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__td' );
		$this->pps_color( 'i_td_c', __( 'رنگ مقدار', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__td' );
		$this->pps_color( 'i_table_bc', __( 'رنگ حاشیه جدول', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__table', 'border-color' );
		$this->pps_color( 'i_row_bg', __( 'پس‌زمینه ردیف', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__row', 'background-color' );
		$this->pps_color( 'i_row_bc', __( 'رنگ جداکننده ردیف', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__row + .hsw-ptabs__row', 'border-top-color' );
		$this->pps_dimensions( 'i_row_pad', __( 'فاصله داخلی ردیف', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__row' );

		$this->pps_heading( 'i_rev_h', __( 'دیدگاه‌ها', 'hamishop-elementor-widgets' ) );
		$this->pps_box( 'i_rev_card', '{{WRAPPER}} .hsw-ptabs__rvitem', array( 'shadow' => false ) );
		$this->pps_type( 'i_rev_name', __( 'نام نویسنده', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__rvname' );
		$this->pps_type( 'i_rev_text', __( 'متن دیدگاه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__rvtext' );
		$this->pps_icon( 'i_rev_star', __( 'ستاره دیدگاه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptabs__rvstars .hsw-ic' );
		$this->pps_close();
	}

	/** Features / specification bento box. */
	private function pps_register_features_section() {
		$this->pps_open( 'sec_style_features', __( 'ویژگی‌ها و مشخصات', 'hamishop-elementor-widgets' ), array( 'pft_on' => 'yes' ) );

		$this->pps_heading( 'f_box_h', __( 'جعبه ویژگی‌ها', 'hamishop-elementor-widgets' ), '' );
		$this->pps_box( 'f_box', '{{WRAPPER}} .hsw-pfeat__box' );
		$this->pps_type( 'f_title', __( 'عنوان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pfeat__title, {{WRAPPER}} .hsw-pfeat__mtitle' );
		$this->pps_color( 'f_title_c', __( 'رنگ عنوان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pfeat__title, {{WRAPPER}} .hsw-pfeat__mtitle' );
		$this->pps_type( 'f_item', __( 'متن ویژگی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pfeat__list' );
		$this->pps_color( 'f_item_c', __( 'رنگ متن ویژگی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pfeat__list' );
		$this->pps_icon( 'f_icon', __( 'آیکون ویژگی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pfeat__ic' );
		$this->pps_slider(
			'f_gap',
			__( 'فاصله ردیف‌ها', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pfeat__list',
			'gap: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ) )
		);

		$this->pps_heading( 'f_mobile_h', __( 'کارت‌های موبایل', 'hamishop-elementor-widgets' ) );
		$this->pps_box( 'f_cell', '{{WRAPPER}} .hsw-pfeat__cell', array( 'shadow' => false ) );
		$this->pps_slider(
			'f_mgap',
			__( 'فاصله کارت‌ها', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pfeat__grid',
			'gap: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ) )
		);
		$this->pps_box( 'f_mic', '{{WRAPPER}} .hsw-pfeat__mic', array( 'shadow' => false ) );
		$this->pps_icon( 'f_mic_i', __( 'آیکون کارت موبایل', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pfeat__mic .hsw-ic' );
		$this->pps_type( 'f_mlabel', __( 'برچسب کارت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pfeat__mlabel' );
		$this->pps_color( 'f_mlabel_c', __( 'رنگ برچسب کارت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pfeat__mlabel' );
		$this->pps_type( 'f_mval', __( 'مقدار کارت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pfeat__mval' );
		$this->pps_color( 'f_mval_c', __( 'رنگ مقدار کارت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pfeat__mval' );
		$this->pps_close();
	}

	/** Colour / storage selectors, chips and swatches. */
	private function pps_register_variants_section() {
		$this->pps_open( 'sec_style_variants', __( 'انتخاب‌گر متغیرها', 'hamishop-elementor-widgets' ), array( 'pvr_on' => 'yes' ) );

		$this->pps_heading( 'v_label_head', __( 'برچسب‌ها', 'hamishop-elementor-widgets' ), '' );
		$this->pps_type( 'v_label', __( 'تایپوگرافی برچسب', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pvar__label' );
		$this->pps_color( 'v_label_c', __( 'رنگ برچسب', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pvar__label' );
		$this->pps_color( 'v_sel_c', __( 'رنگ نام انتخاب‌شده', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pvar__selname' );

		$this->pps_heading( 'v_sw_h', __( 'نمونه‌های رنگ', 'hamishop-elementor-widgets' ) );
		$this->pps_slider(
			'v_sw_gap',
			__( 'فاصله نمونه‌ها', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pvar__swatches',
			'gap: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ) )
		);
		$this->pps_slider(
			'v_sw_size',
			__( 'اندازه نمونه رنگ', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pvar__swatch',
			'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 24, 'max' => 64 ) ) )
		);
		$this->pps_var_color(
			'v_sw_ring',
			__( 'رنگ حلقه انتخاب', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pvar__swatch',
			'--hsw-var-ring'
		);
		$this->pps_slider(
			'v_sw_ring_w',
			__( 'ضخامت حلقه انتخاب', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pvar__swatch',
			'--hsw-var-ring-w: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 6 ) ) )
		);

		$this->pps_heading( 'v_chip_h', __( 'چیپ‌های حافظه / سایز', 'hamishop-elementor-widgets' ) );
		$this->pps_box( 'v_chip', '{{WRAPPER}} .hsw-pvar__chip, {{WRAPPER}} .hsw-pvar__mchip', array( 'shadow' => false ) );
		$this->pps_type( 'v_chip_t', __( 'تایپوگرافی چیپ', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pvar__chip, {{WRAPPER}} .hsw-pvar__mchip' );
		$this->pps_color( 'v_chip_c', __( 'رنگ متن چیپ', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pvar__chip, {{WRAPPER}} .hsw-pvar__mchip' );
		$this->pps_slider(
			'v_chip_gap',
			__( 'فاصله چیپ‌ها', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pvar__chips, {{WRAPPER}} .hsw-pvar__mchips',
			'gap: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ) )
		);
		$this->pps_dimensions( 'v_chip_pad', __( 'فاصله داخلی چیپ', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pvar__chip, {{WRAPPER}} .hsw-pvar__mchip' );

		$this->pps_heading( 'v_chip_hover_h', __( 'حالت هاور', 'hamishop-elementor-widgets' ) );
		$this->pps_color( 'v_chip_hover_c', __( 'رنگ متن (هاور)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pvar__chip:not(.is-active):hover, {{WRAPPER}} .hsw-pvar__mchip:not(.is-active):hover', 'color', true );
		$this->pps_color( 'v_chip_hover_bg', __( 'پس‌زمینه (هاور)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pvar__chip:not(.is-active):hover, {{WRAPPER}} .hsw-pvar__mchip:not(.is-active):hover', 'background-color', true );
		$this->pps_color( 'v_chip_hover_bc', __( 'حاشیه (هاور)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pvar__chip:not(.is-active):hover, {{WRAPPER}} .hsw-pvar__mchip:not(.is-active):hover', 'border-color', true );

		$this->pps_heading( 'v_chip_active_h', __( 'حالت انتخاب‌شده', 'hamishop-elementor-widgets' ) );
		$this->pps_color( 'v_chip_active_c', __( 'رنگ متن', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pvar__chip.is-active, {{WRAPPER}} .hsw-pvar__mchip.is-active', 'color', true );
		$this->pps_color( 'v_chip_active_bg', __( 'پس‌زمینه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pvar__chip.is-active, {{WRAPPER}} .hsw-pvar__mchip.is-active', 'background-color', true );
		$this->pps_color( 'v_chip_active_bc', __( 'حاشیه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pvar__chip.is-active, {{WRAPPER}} .hsw-pvar__mchip.is-active', 'border-color', true );

		$this->pps_heading( 'v_chip_disabled_h', __( 'حالت غیرفعال', 'hamishop-elementor-widgets' ) );
		$this->pps_color( 'v_chip_disabled_c', __( 'رنگ متن', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pvar__chip:disabled, {{WRAPPER}} .hsw-pvar__mchip:disabled', 'color', true );
		$this->pps_slider(
			'v_chip_disabled_o',
			__( 'شفافیت', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pvar__chip:disabled, {{WRAPPER}} .hsw-pvar__mchip:disabled',
			'opacity: {{SIZE}};',
			array(
				'units' => array( 'px' ),
				'range' => array( 'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.05 ) ),
			)
		);
		$this->pps_close();
	}
}
