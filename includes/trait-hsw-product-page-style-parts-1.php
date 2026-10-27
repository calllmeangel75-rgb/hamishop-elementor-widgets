<?php
/**
 * Composite product-page Style sections: gallery, title/rating, stock/badges.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

trait HSW_Product_Page_Style_Parts_1 {

	/** Gallery card, frame, image, thumbnails, badges and arrows. */
	private function pps_register_gallery_section() {
		$this->pps_open( 'sec_style_gallery', __( 'گالری محصول', 'hamishop-elementor-widgets' ), array( 'pga_on' => 'yes' ) );
		$this->pps_note( 'g_note', __( 'خالی گذاشتن هر کنترل یعنی استفاده از پالت پنل مدیریت. کنترل‌های این بخش فقط روی همین ویجت صفحه محصول اعمال می‌شوند و بر پالت اولویت دارند.', 'hamishop-elementor-widgets' ) );

		$this->pps_heading( 'g_card_h', __( 'قاب اصلی گالری', 'hamishop-elementor-widgets' ), '' );
		$this->pps_box( 'g_card', '{{WRAPPER}} .hsw-pgal__card' );

		$this->pps_heading( 'g_frame_h', __( 'قاب تصویر', 'hamishop-elementor-widgets' ) );
		$this->pps_box( 'g_frame', '{{WRAPPER}} .hsw-pgal__frame', array( 'shadow' => false ) );
		$this->pps_slider(
			'g_img_pad',
			__( 'فاصله داخلی تصویر', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pgal__img',
			'padding: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 60 ) ) )
		);
		$this->pps_slider(
			'g_img_w',
			__( 'عرض تصویر اصلی', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pgal__img',
			'max-width: {{SIZE}}{{UNIT}}; margin-inline: auto;',
			array(
				'units' => array( '%', 'px' ),
				'range' => array(
					'%'  => array( 'min' => 30, 'max' => 100 ),
					'px' => array( 'min' => 120, 'max' => 900 ),
				),
			)
		);
		$this->pps_select(
			'g_ratio',
			__( 'نسبت تصویر', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pgal__frame',
			'aspect-ratio',
			array(
				''       => __( 'پیش‌فرض (۱:۱)', 'hamishop-elementor-widgets' ),
				'1 / 1'  => __( 'مربع ۱:۱', 'hamishop-elementor-widgets' ),
				'4 / 3'  => __( '۴:۳', 'hamishop-elementor-widgets' ),
				'3 / 4'  => __( '۳:۴', 'hamishop-elementor-widgets' ),
				'16 / 9' => __( '۱۶:۹', 'hamishop-elementor-widgets' ),
				'3 / 2'  => __( '۳:۲', 'hamishop-elementor-widgets' ),
			)
		);
		$this->pps_select(
			'g_fit',
			__( 'نحوه قرارگیری تصویر', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pgal__img',
			'object-fit',
			array(
				''        => __( 'پیش‌فرض (contain)', 'hamishop-elementor-widgets' ),
				'contain' => __( 'کامل داخل کادر', 'hamishop-elementor-widgets' ),
				'cover'   => __( 'پر کردن کادر', 'hamishop-elementor-widgets' ),
			)
		);

		$this->pps_heading( 'g_thumb_h', __( 'بندانگشتی‌ها', 'hamishop-elementor-widgets' ) );
		$this->pps_slider(
			'g_thumb_gap',
			__( 'فاصله بندانگشتی‌ها', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pgal__thumbs',
			'gap: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 32 ) ) )
		);
		$this->pps_slider(
			'g_thumb_size',
			__( 'اندازه بندانگشتی', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pgal__thumb',
			'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 36, 'max' => 120 ) ) )
		);
		$this->pps_slider(
			'g_thumb_radius',
			__( 'گردی گوشه بندانگشتی', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pgal__thumb',
			'border-radius: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ) )
		);
		$this->pps_var_color(
			'g_thumb_ring_c',
			__( 'رنگ حلقه بندانگشتی فعال', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pgal__thumb',
			'--hsw-thumb-ring'
		);
		$this->pps_slider(
			'g_thumb_ring_w',
			__( 'ضخامت حلقه بندانگشتی فعال', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pgal__thumb',
			'--hsw-thumb-ring-w: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 8 ) ) )
		);

		$this->pps_heading( 'g_badge_h', __( 'برچسب تخفیف / ویژه', 'hamishop-elementor-widgets' ) );
		$this->pps_var_color(
			'g_badge_bg',
			__( 'پس‌زمینه برچسب', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pgal__badge',
			'--hsw-badge-bg'
		);
		$this->pps_var_color(
			'g_badge_c',
			__( 'رنگ متن برچسب', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pgal__badge',
			'--hsw-badge-c'
		);
		$this->pps_slider(
			'g_badge_radius',
			__( 'گردی گوشه برچسب', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pgal__badge',
			'--hsw-badge-radius: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ) )
		);
		$this->pps_slider(
			'g_badge_fs',
			__( 'اندازه متن برچسب', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pgal__badge',
			'--hsw-badge-fs: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 8, 'max' => 24 ) ) )
		);
		$this->pps_dimensions(
			'g_badge_pad',
			__( 'فاصله داخلی برچسب', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pgal__badge',
			'--hsw-badge-pad'
		);

		$this->pps_heading( 'g_auth_h', __( 'برچسب اصالت / گارانتی', 'hamishop-elementor-widgets' ) );
		$this->pps_box( 'g_auth', '{{WRAPPER}} .hsw-pgal__auth', array( 'shadow' => false ) );
		$this->pps_type( 'g_auth_t', __( 'متن برچسب اصالت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pgal__auth' );
		$this->pps_icon( 'g_auth_i', __( 'آیکون اصالت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pgal__auth .hsw-ic' );

		$this->pps_heading( 'g_nav_h', __( 'دکمه‌های جابه‌جایی', 'hamishop-elementor-widgets' ) );
		$this->pps_box( 'g_nav', '{{WRAPPER}} .hsw-pgal__thumbs-nav', array( 'shadow' => false ) );
		$this->pps_color( 'g_nav_c', __( 'رنگ آیکون', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pgal__thumbs-nav', 'color', true );
		$this->pps_color( 'g_nav_hover_c', __( 'رنگ آیکون (هاور)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pgal__thumbs-nav:hover', 'color', true );
		$this->pps_slider(
			'g_nav_size',
			__( 'اندازه دکمه', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pgal__thumbs-nav',
			'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 24, 'max' => 64 ) ) )
		);

		$this->pps_heading( 'g_misc_h', __( 'سایر جزئیات', 'hamishop-elementor-widgets' ) );
		$this->pps_var_color(
			'g_dot_c',
			__( 'رنگ نقطه فعال موبایل', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pgal__dot',
			'--hsw-dot-bg'
		);
		$this->pps_color( 'g_zoom_c', __( 'رنگ آیکون بزرگ‌نمایی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pgal__zoom', 'color' );
		$this->pps_color( 'g_zoom_bg', __( 'پس‌زمینه بزرگ‌نمایی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pgal__zoom', 'background-color' );
		$this->pps_close();
	}

	/** Title, meta, rating, review link and review recommendation. */
	private function pps_register_title_section() {
		$this->pps_open( 'sec_style_title', __( 'عنوان، امتیاز و دیدگاه‌ها', 'hamishop-elementor-widgets' ), array( 'ptl_on' => 'yes' ) );

		$this->pps_heading( 't_name_head', __( 'عنوان محصول', 'hamishop-elementor-widgets' ), '' );
		$this->pps_type( 't_name', __( 'تایپوگرافی عنوان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__h1, {{WRAPPER}} .hsw-ptitle__h2' );
		$this->pps_color( 't_name_c', __( 'رنگ عنوان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__h1, {{WRAPPER}} .hsw-ptitle__h2' );
		$this->pps_align( 't_name_align', __( 'تراز عنوان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__h1, {{WRAPPER}} .hsw-ptitle__h2' );
		$this->pps_slider(
			't_name_mw',
			__( 'حداکثر عرض عنوان', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ptitle__h1, {{WRAPPER}} .hsw-ptitle__h2',
			'max-width: {{SIZE}}{{UNIT}};',
			array(
				'units' => array( '%', 'px' ),
				'range' => array(
					'%'  => array( 'min' => 30, 'max' => 100 ),
					'px' => array( 'min' => 160, 'max' => 1200 ),
				),
			)
		);
		$this->pps_slider(
			't_name_mb',
			__( 'فاصله زیر عنوان', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ptitle__h1, {{WRAPPER}} .hsw-ptitle__h2',
			'margin-bottom: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 60 ) ) )
		);
		$this->pps_slider(
			't_wrap_mb',
			__( 'فاصله پایین کل عنوان', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ptitle',
			'margin-bottom: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 60 ) ) )
		);

		$this->pps_heading( 't_meta_head', __( 'نام لاتین و کد کالا', 'hamishop-elementor-widgets' ) );
		$this->pps_type( 't_meta', __( 'تایپوگرافی متا', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__en, {{WRAPPER}} .hsw-ptitle__sku' );
		$this->pps_color( 't_meta_c', __( 'رنگ متا', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__en, {{WRAPPER}} .hsw-ptitle__sku' );

		$this->pps_heading( 't_rate_head', __( 'امتیاز و دیدگاه‌ها', 'hamishop-elementor-widgets' ) );
		$this->pps_slider(
			't_rate_gap',
			__( 'فاصله اجزای نوار امتیاز', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ptitle__ratebar',
			'gap: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ) )
		);
		$this->pps_color( 't_rate_bc', __( 'رنگ خط زیر نوار', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__ratebar', 'border-bottom-color' );
		$this->pps_slider(
			't_rate_bw',
			__( 'ضخامت خط زیر نوار', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ptitle__ratebar',
			'border-bottom-width: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 8 ) ) )
		);
		$this->pps_icon( 't_star', __( 'ستاره‌های امتیاز', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__star, {{WRAPPER}} .hsw-ptitle__mscore .hsw-ic' );
		$this->pps_type( 't_rate', __( 'متن امتیاز و تعداد دیدگاه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__score, {{WRAPPER}} .hsw-ptitle__revlink, {{WRAPPER}} .hsw-ptitle__mscore' );
		$this->pps_color( 't_rate_c', __( 'رنگ متن امتیاز', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__score, {{WRAPPER}} .hsw-ptitle__mscore' );
		$this->pps_color( 't_rev_c', __( 'رنگ لینک دیدگاه‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__revlink' );
		$this->pps_color( 't_rev_h_c', __( 'رنگ لینک دیدگاه‌ها (هاور)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__revlink:hover' );
		$this->pps_color( 't_rec_c', __( 'رنگ متن پیشنهاد خریداران', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__rec' );
		$this->pps_type( 't_rec', __( 'تایپوگرافی پیشنهاد خریداران', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__rec' );
		$this->pps_close();
	}

	/** Stock pill and badge states: in stock, low stock, out of stock. */
	private function pps_register_stock_section() {
		$this->pps_open( 'sec_style_stock', __( 'موجودی و برچسب‌ها', 'hamishop-elementor-widgets' ), array( 'ptl_on' => 'yes' ) );
		$this->pps_note( 's_note', __( 'رنگ‌های حالت موجود، کم‌موجود و ناموجود جداگانه‌اند. قرص موجودی در موبایل نمایش داده می‌شود؛ برچسب تخفیف گالری در بخش «گالری محصول» تنظیم می‌شود.', 'hamishop-elementor-widgets' ) );

		$this->pps_heading( 's_pill_head', __( 'قرص موجودی', 'hamishop-elementor-widgets' ), '' );
		$this->pps_type( 's_pill', __( 'تایپوگرافی قرص', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__stock' );
		$this->pps_var_color( 's_pill_bg', __( 'پس‌زمینه (موجود)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__stock', '--hsw-stock-bg' );
		$this->pps_var_color( 's_pill_c', __( 'رنگ متن (موجود)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__stock', '--hsw-stock-c' );
		$this->pps_var_color( 's_pill_bc', __( 'رنگ حاشیه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__stock', '--hsw-stock-bc' );
		$this->pps_slider(
			's_pill_bw',
			__( 'ضخامت حاشیه', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ptitle__stock',
			'--hsw-stock-bw: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 6 ) ) )
		);
		$this->pps_slider(
			's_pill_radius',
			__( 'گردی گوشه', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-ptitle__stock',
			'--hsw-stock-radius: {{SIZE}}{{UNIT}};',
			array( 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ) )
		);
		$this->pps_dimensions( 's_pill_pad', __( 'فاصله داخلی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__stock', '--hsw-stock-pad' );

		$this->pps_heading( 's_oos_h', __( 'حالت ناموجود', 'hamishop-elementor-widgets' ) );
		$this->pps_var_color( 's_oos_bg', __( 'پس‌زمینه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__stock.is-oos', '--hsw-stock-bg' );
		$this->pps_var_color( 's_oos_c', __( 'رنگ متن', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__stock.is-oos', '--hsw-stock-c' );
		$this->pps_var_color( 's_oos_bc', __( 'رنگ حاشیه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__stock.is-oos', '--hsw-stock-bc' );

		$this->pps_heading( 's_low_h', __( 'حالت کم‌موجود', 'hamishop-elementor-widgets' ) );
		$this->pps_var_color( 's_low_bg', __( 'پس‌زمینه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__stock.is-low-stock', '--hsw-stock-bg' );
		$this->pps_var_color( 's_low_c', __( 'رنگ متن', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__stock.is-low-stock', '--hsw-stock-c' );
		$this->pps_var_color( 's_low_bc', __( 'رنگ حاشیه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ptitle__stock.is-low-stock', '--hsw-stock-bc' );

		$this->pps_heading( 's_oos_btn_h', __( 'دکمه خرید در حالت ناموجود', 'hamishop-elementor-widgets' ) );
		$this->pps_color( 's_oos_btn_bg', __( 'پس‌زمینه دکمه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch.is-oos .hsw-ppurch__cart', 'background-color', true );
		$this->pps_color( 's_oos_btn_c', __( 'رنگ متن دکمه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ppurch.is-oos .hsw-ppurch__cart', 'color', true );
		$this->pps_close();
	}
}
