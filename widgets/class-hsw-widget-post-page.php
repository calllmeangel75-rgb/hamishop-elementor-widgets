<?php
/**
 * Blog widget: full single-post page (صفحه کامل نوشته).
 *
 * Mobile-first article template that also scales to a two-column desktop
 * layout: sticky top bar + reading progress, breadcrumb, hero, collapsible
 * table of contents, body, in-article product card, tags, author box, real
 * WordPress comments, related posts and prev/next navigation.
 *
 * Every value comes from WordPress / WooCommerce — nothing is hard-coded, so
 * the widget drops onto any site's single-post template.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;

class HSW_Widget_Post_Page extends HSW_Blog_Base {

	public function get_name() {
		return 'hsw-post-page';
	}

	public function get_title() {
		return __( 'مجله: صفحه کامل نوشته (HamiShop)', 'hamishop-elementor-widgets' );
	}

	public function get_icon() {
		return 'eicon-single-post';
	}

	public function get_keywords() {
		return array( 'blog', 'post', 'single', 'article', 'نوشته', 'مقاله', 'مجله', 'hamishop' );
	}

	public function get_style_depends() {
		return array( 'hsw-fonts', 'hsw-post' );
	}

	public function get_script_depends() {
		return array( 'hsw-post' );
	}

	/* ---------------------------------------------------------------------
	 * Controls
	 * ------------------------------------------------------------------ */

	protected function register_controls() {
		$this->start_controls_section(
			'sec_src',
			array( 'label' => __( 'منبع نوشته', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
		);

		$this->add_control(
			'source',
			array(
				'label'   => __( 'کدام نوشته؟', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'current',
				'options' => array(
					'current' => __( 'نوشتهٔ جاری (پیشنهادی برای قالب تکی)', 'hamishop-elementor-widgets' ),
					'custom'  => __( 'یک نوشتهٔ مشخص', 'hamishop-elementor-widgets' ),
				),
			)
		);

		$this->add_control(
			'post_id',
			array(
				'label'       => __( 'شناسه نوشته', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 1,
				'condition'   => array( 'source' => 'custom' ),
				'description' => __( 'برای پیش‌نمایش در ادیتور. در قالب تکی، «نوشتهٔ جاری» را انتخاب کنید.', 'hamishop-elementor-widgets' ),
			)
		);

		$this->add_control(
			'show_progress',
			array(
				'label'   => __( 'نوار پیشرفت مطالعه', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_topbar',
			array(
				'label'   => __( 'نوار بالای چسبان', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_crumbs',
			array(
				'label'   => __( 'مسیر راهنما و برچسب دسته', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_meta',
			array(
				'label'   => __( 'نویسنده و آمار (تاریخ، زمان مطالعه، بازدید، دیدگاه)', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_hero',
			array(
				'label'   => __( 'تصویر شاخص', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_toc',
			array(
				'label'       => __( 'فهرست مطالب خودکار', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'description' => __( 'از هدینگ‌های h2 و h3 داخل متن نوشته ساخته می‌شود.', 'hamishop-elementor-widgets' ),
			)
		);

		$this->add_control(
			'toc_anchor',
			array(
				'label'       => __( 'لنگر سرفصل‌ها', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'slug',
				'options'     => array(
					'slug'  => __( 'از متن تیتر (پیشنهادی)', 'hamishop-elementor-widgets' ),
					'index' => __( 'شماره‌ای (hsw-h-1، hsw-h-2 …)', 'hamishop-elementor-widgets' ),
					'none'  => __( 'بدون لینک لنگر (فقط اسکرول)', 'hamishop-elementor-widgets' ),
				),
				'description' => __( 'گوگل می‌تواند آدرس‌های لنگر (…/#heading) را جداگانه ایندکس کند. «از متن تیتر» آدرس خوانا و باثبات می‌سازد و «بدون لینک لنگر» هیچ آدرس اضافه‌ای تولید نمی‌کند.', 'hamishop-elementor-widgets' ),
				'condition'   => array( 'show_toc' => 'yes' ),
			)
		);

		$this->add_control(
			'show_tags',
			array(
				'label'   => __( 'برچسب‌ها', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_author_box',
			array(
				'label'   => __( 'باکس نویسنده', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_comments',
			array(
				'label'   => __( 'دیدگاه‌ها و فرم ثبت نظر', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_related',
			array(
				'label'   => __( 'مقالات مرتبط', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'related_count',
			array(
				'label'     => __( 'تعداد مقالات مرتبط', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 3,
				'min'       => 1,
				'max'       => 6,
				'condition' => array( 'show_related' => 'yes' ),
			)
		);

		$this->add_control(
			'show_prevnext',
			array(
				'label'   => __( 'نوشتهٔ قبلی / بعدی', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_dock',
			array(
				'label'       => __( 'نوار چسبان پایین موبایل', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'description' => __( 'دکمهٔ پسند، پرش به دیدگاه‌ها و خرید محصول مقاله.', 'hamishop-elementor-widgets' ),
			)
		);

		$this->end_controls_section();

		/* ---- in-article product ---- */
		$this->start_controls_section(
			'sec_prod',
			array( 'label' => __( 'کارت محصول داخل مقاله', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
		);

		$this->add_control(
			'show_product',
			array(
				'label'   => __( 'نمایش کارت محصول', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'product_id',
			array(
				'label'       => __( 'شناسه محصول', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 1,
				'condition'   => array( 'show_product' => 'yes' ),
				'description' => __( 'اگر خالی بماند، اولین محصولِ دستهٔ همین نوشته پیشنهاد می‌شود.', 'hamishop-elementor-widgets' ),
			)
		);

		$this->add_control(
			'product_label',
			array(
				'label'     => __( 'برچسب بالای کارت', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'کالای معرفی‌شده در این مقاله', 'hamishop-elementor-widgets' ),
				'condition' => array( 'show_product' => 'yes' ),
			)
		);

		$this->end_controls_section();

		/* ---- style ---- */
		$this->start_controls_section(
			'sec_style',
			array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
		);

		$this->add_control(
			'palette_note',
			array(
				'label'     => '',
				'type'      => Controls_Manager::RAW_HTML,
				'raw'       => '<div style="font-size:11px;line-height:1.8;color:#646970">' .
					esc_html__( 'رنگ‌های این ویجت از «HamiShop → پالت رنگی» می‌آید. برای تغییر، همان صفحه را باز کنید.', 'hamishop-elementor-widgets' ) .
					'</div>',
				'separator' => 'after',
			)
		);

		$this->add_responsive_control(
			'max_width',
			array(
				'label'      => __( 'عرض محتوا', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array( 'min' => 640, 'max' => 1400 ),
					'%'  => array( 'min' => 50, 'max' => 100 ),
				),
				'default'    => array( 'size' => 1120, 'unit' => 'px' ),
				'selectors'  => array(
					'{{WRAPPER}} .hsw-pp' => '--hsw-pp-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'body_size',
			array(
				'label'      => __( 'اندازهٔ متن مقاله', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 12, 'max' => 22 ) ),
				'default'    => array( 'size' => 15, 'unit' => 'px' ),
				'selectors'  => array(
					'{{WRAPPER}} .hsw-pp__body' => '--hsw-pp-fs: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->a_align(
			'body',
			__( 'تراز و جهت متن مقاله', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pp__body',
			array( 'align' => 'justify', 'lh' => 2 )
		);

		$this->add_control(
			'body_c',
			array(
				'label'     => __( 'رنگ متن مقاله', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .hsw-pp__body' => 'color: {{VALUE}};' ),
			)
		);

		/* ---- titles & meta ---- */
		$this->a_typo( 'title', __( 'عنوان مقاله', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pp__title', array( 'size' => 30, 'lh' => 1.45 ) );
		$this->a_typo( 'stats', __( 'تاریخ و آمار', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pp__stats', array( 'size' => 11, 'weight' => false ) );
		$this->a_typo( 'author', __( 'نام نویسنده', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pp__authorname', array( 'size' => 12.5 ) );

		/* ---- table of contents ---- */
		$this->a_typo(
			'toc',
			__( 'فهرست مطالب', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pp__tocitem a, {{WRAPPER}} .hsw-pp__tocitem .hsw-pp__tocbtn',
			array( 'size' => 12, 'weight' => false )
		);
		$this->a_typo( 'tochead', __( 'تیتر فهرست مطالب', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pp__toctitle', array( 'size' => 12.5 ) );
		$this->a_typo( 'side', __( 'فهرست سایدبار', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pp__sidelink', array( 'size' => 11.5, 'weight' => false ) );

		/* ---- body extras ---- */
		$this->a_typo( 'tag', __( 'برچسب‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pp__tag', array( 'size' => 11, 'weight' => false ) );
		$this->a_typo( 'rel', __( 'عنوان مقالات مرتبط', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pp__reltitle', array( 'size' => 12.5, 'lh' => 1.65 ) );
		$this->a_typo( 'pop', __( 'پربازدیدترین‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pp__poptitle', array( 'size' => 11.5, 'weight' => false ) );
		$this->a_typo( 'bio', __( 'متن باکس نویسنده', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pp__authorboxbio', array( 'size' => 12, 'weight' => false ) );

		/* ---- comments ---- */
		$this->a_typo( 'cmt', __( 'متن دیدگاه‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pp__commentbody', array( 'size' => 12.5, 'lh' => 1.9 ) );
		$this->a_typo( 'cmtname', __( 'نام نویسندهٔ دیدگاه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pp__commentname', array( 'size' => 12.5 ) );

		/* ---- buttons ---- */
		$this->a_button(
			'prod',
			__( 'دکمهٔ خرید کارت محصول', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pp__prodbtn',
			array( 'size' => 11.5, 'radius' => 9 )
		);

		$this->a_button(
			'dock',
			__( 'دکمهٔ نوار پایین موبایل', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pp__dockcta',
			array( 'size' => 11.5, 'radius' => 9 )
		);

		$this->a_button(
			'submit',
			__( 'دکمهٔ ارسال دیدگاه', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-pp__submit',
			array( 'size' => 12, 'radius' => 9 )
		);

		$this->end_controls_section();

		/* ------------------- CONTENT TYPOGRAPHY ------------------- */
		$this->start_controls_section(
			'sec_pp_content',
			array( 'label' => __( 'محتوای نوشته (متن و تیترها)', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
		);

		$this->a_typo( 'pc_p', __( 'متن پاراگراف‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pp__body p, {{WRAPPER}} .hsw-pp__body li', array( 'size' => 15, 'lh' => 2 ) );

		$this->add_responsive_control(
			'pc_p_gap',
			array(
				'label'      => __( 'فاصلهٔ بین پاراگراف‌ها', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 48 ), 'em' => array( 'min' => 0, 'max' => 3, 'step' => 0.1 ) ),
				'selectors'  => array( '{{WRAPPER}} .hsw-pp__body p' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->a_typo( 'pc_h', __( 'تیترهای H2 تا H6', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pp__body h2, {{WRAPPER}} .hsw-pp__body h3, {{WRAPPER}} .hsw-pp__body h4, {{WRAPPER}} .hsw-pp__body h5, {{WRAPPER}} .hsw-pp__body h6', array( 'size' => 20, 'lh' => 1.7 ) );

		$this->add_responsive_control(
			'pc_h_gap',
			array(
				'label'      => __( 'فاصلهٔ بالا و پایین تیترها', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .hsw-pp__body h2, {{WRAPPER}} .hsw-pp__body h3, {{WRAPPER}} .hsw-pp__body h4' => 'margin-top: {{SIZE}}{{UNIT}}; margin-bottom: calc({{SIZE}}{{UNIT}} / 2);',
				),
			)
		);

		$this->a_color( 'pc_link', __( 'رنگ لینک‌های متن', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pp__body a' );
		$this->a_color( 'pc_link_hv', __( 'رنگ لینک در هاور', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pp__body a:hover' );

		$this->add_control(
			'pc_link_u',
			array(
				'label'     => __( 'زیرخط لینک‌ها', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '',
				'options'   => array( '' => __( 'پیش‌فرض', 'hamishop-elementor-widgets' ), 'none' => __( 'بدون زیرخط', 'hamishop-elementor-widgets' ), 'underline' => __( 'با زیرخط', 'hamishop-elementor-widgets' ) ),
				'selectors' => array( '{{WRAPPER}} .hsw-pp__body a' => 'text-decoration: {{VALUE}};' ),
			)
		);

		$this->a_color( 'pc_bq_bg', __( 'نقل‌قول — پس‌زمینه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pp__body blockquote', 'background-color' );
		$this->a_color( 'pc_bq_bd', __( 'نقل‌قول — حاشیهٔ کناری', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pp__body blockquote', 'border-inline-start-color' );

		$this->add_responsive_control(
			'pc_bq_pad',
			array(
				'label'      => __( 'نقل‌قول — فاصلهٔ داخلی', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .hsw-pp__body blockquote' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'pc_img_r',
			array(
				'label'      => __( 'گردی تصاویر داخل متن', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 32 ) ),
				'selectors'  => array( '{{WRAPPER}} .hsw-pp__body img' => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'pc_img_m',
			array(
				'label'      => __( 'فاصلهٔ اطراف تصاویر داخل متن', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 64 ) ),
				'selectors'  => array( '{{WRAPPER}} .hsw-pp__body img' => 'margin-top: {{SIZE}}{{UNIT}}; margin-bottom: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();

		/* ------------------- RELATED ------------------- */
		$this->start_controls_section(
			'sec_pp_related',
			array( 'label' => __( 'مطالب مرتبط', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
		);

		$this->add_responsive_control(
			'rel_cols',
			array(
				'label'     => __( 'تعداد ستون‌ها', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '',
				'options'   => array( '' => __( 'پیش‌فرض', 'hamishop-elementor-widgets' ), '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴' ),
				'selectors' => array( '{{WRAPPER}} .hsw-pp__relgrid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));' ),
			)
		);

		$this->add_responsive_control(
			'rel_gap',
			array(
				'label'      => __( 'فاصلهٔ بین کارت‌ها', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .hsw-pp__relgrid' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->a_color( 'rel_bg', __( 'پس‌زمینهٔ کارت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pp__relcard', 'background-color' );
		$this->a_color( 'rel_bd', __( 'حاشیهٔ کارت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pp__relcard', 'border-color' );

		$this->add_responsive_control(
			'rel_radius',
			array(
				'label'      => __( 'گردی کارت', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 28 ) ),
				'selectors'  => array( '{{WRAPPER}} .hsw-pp__relcard' => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'rel_shadow',
				'label'    => __( 'سایهٔ کارت', 'hamishop-elementor-widgets' ),
				'selector' => '{{WRAPPER}} .hsw-pp__relcard',
			)
		);

		$this->add_responsive_control(
			'rel_ratio',
			array(
				'label'     => __( 'نسبت تصویر کارت', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '',
				'options'   => array( '' => __( 'پیش‌فرض', 'hamishop-elementor-widgets' ), '16/9' => '۱۶:۹', '4/3' => '۴:۳', '1/1' => '۱:۱' ),
				'selectors' => array( '{{WRAPPER}} .hsw-pp__relmedia' => 'aspect-ratio: {{VALUE}};' ),
			)
		);

		$this->a_text( 'rel_title', '{{WRAPPER}} .hsw-pp__reltitle', array( 'default' => 14 ) );

		$this->end_controls_section();

		/*
		 * One type scale per widget. The variables land on the wrapper, so a
		 * child element only has to read them and the generated CSS stays a
		 * single declaration per part.
		 */
		$this->style_typography_section(
			'pp',
			array(
			'title' => array( __( 'عنوان مقاله', 'hamishop-elementor-widgets' ), 32 ),
			'meta' => array( __( 'تاریخ و نویسنده', 'hamishop-elementor-widgets' ), 13 ),
			'toc' => array( __( 'فهرست مطالب', 'hamishop-elementor-widgets' ), 12.5 ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Data
	 * ------------------------------------------------------------------ */

	/**
	 * Post to render.
	 *
	 * @return WP_Post|null
	 */
	protected function current_post() {
		$s = $this->effective_settings();

		if ( isset( $s['source'] ) && 'custom' === $s['source'] ) {
			$id = isset( $s['post_id'] ) ? absint( $s['post_id'] ) : 0;
			$p  = $id ? get_post( $id ) : null;
			if ( $p && 'post' === $p->post_type ) {
				return $p;
			}
		}

		$p = get_post();
		if ( $p && 'post' === $p->post_type ) {
			return $p;
		}

		/* Editor preview / not on a single post: fall back to the latest. */
		$latest = get_posts( array( 'numberposts' => 1, 'post_status' => 'publish' ) );
		return $latest ? $latest[0] : null;
	}

	/**
	 * Views counter meta key.
	 */
	const VIEWS_META = 'hsw_post_views';

	/**
	 * Stored view count (0 when never counted).
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	protected function views( $post_id ) {
		return (int) get_post_meta( $post_id, self::VIEWS_META, true );
	}

	/**
	 * Content with anchored headings + the generated table of contents.
	 *
	 * @param WP_Post $post Post.
	 * @return array{html:string,toc:array<int,array{level:int,id:string,text:string}>}
	 */
	protected function prepared_content( $post, $mode = 'slug' ) {
		$html = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
		$html = HSW_Helpers::demote_content_h1( $html );

		$toc  = array();
		$used = array();

		$html = preg_replace_callback(
			'#<h([23])([^>]*)>(.*?)</h\1>#is',
			static function ( $m ) use ( &$toc, &$used, $mode ) {
				$level = (int) $m[1];
				$attrs = $m[2];
				$inner = $m[3];
				$text  = trim( wp_strip_all_tags( $inner ) );

				if ( '' === $text ) {
					return $m[0];
				}

				$id = '';
				if ( preg_match( '/\sid=["\']([^"\']+)["\']/i', $attrs, $found ) ) {
					$id = $found[1];
				}
				if ( '' === $id ) {
					$id     = self::heading_id( $text, $mode, $used );
					$attrs .= ' id="' . esc_attr( $id ) . '"';
				}
				$used[ $id ] = true;

				$toc[] = array(
					'level' => $level,
					'id'    => $id,
					'text'  => $text,
				);

				return '<h' . $level . $attrs . '>' . $inner . '</h' . $level . '>';
			},
			$html
		);

		return array( 'html' => (string) $html, 'toc' => $toc );
	}

	/**
	 * Anchor id for one heading.
	 *
	 * `slug` keeps the Persian letters so the fragment is readable and stable
	 * (`#فناوری-حذف-نویز`). sanitize_title() is deliberately avoided: with the
	 * default 'save' context it percent-encodes non-ASCII, producing
	 * `%d9%81…` ids that are unreadable in a shared link.
	 *
	 * `index` and `none` fall back to `hsw-h-N` — `none` still needs an id so
	 * the scroll handler has a target, it just never exposes it as a URL.
	 *
	 * @param string $text Heading text.
	 * @param string $mode slug|index|none.
	 * @param array  $used Already used ids (by reference).
	 * @return string
	 */
	protected static function heading_id( $text, $mode, &$used ) {
		if ( 'slug' === $mode ) {
			$slug = preg_replace( '/[\x{200C}\x{200D}]+/u', '', (string) $text ); // ZWNJ / ZWJ.
			$slug = preg_replace( '/[\s]+/u', '-', (string) $slug );
			$slug = preg_replace( '/[^\p{L}\p{N}\-]+/u', '', (string) $slug );
			$slug = trim( preg_replace( '/-+/', '-', (string) $slug ), '-' );

			if ( '' !== $slug ) {
				$slug = function_exists( 'mb_strtolower' ) ? mb_strtolower( $slug, 'UTF-8' ) : strtolower( $slug );
				$base = $slug;
				$i    = 2;
				while ( isset( $used[ $slug ] ) ) {
					$slug = $base . '-' . $i;
					$i++;
				}
				return $slug;
			}
		}

		$n = 1;
		do {
			$id = 'hsw-h-' . $n;
			$n++;
		} while ( isset( $used[ $id ] ) );

		return $id;
	}

	/**
	 * One TOC entry: a real fragment link, or a button in «بدون لینک» mode.
	 *
	 * @param array  $item    level/id/text.
	 * @param string $mode    slug|index|none.
	 * @param string $variant main|side.
	 * @return string
	 */
	protected function toc_link_html( $item, $mode, $variant = 'main' ) {
		$is_main = 'main' === $variant;
		$class   = $is_main ? '' : 'hsw-pp__sidelink hsw-pp__sidelink--h' . (int) $item['level'];
		$inner   = $is_main
			? '<i class="hsw-pp__tocdot" aria-hidden="true"></i><span>' . esc_html( $item['text'] ) . '</span>'
			: esc_html( $item['text'] );

		if ( 'none' === $mode ) {
			return '<button type="button" class="' . esc_attr( trim( $class . ' hsw-pp__tocbtn' ) ) . '"'
				. ' data-hsw-pp-toclink data-target="' . esc_attr( $item['id'] ) . '">' . $inner . '</button>';
		}

		return '<a' . ( '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . ''
			. ' href="#' . esc_attr( $item['id'] ) . '" data-hsw-pp-toclink>' . $inner . '</a>';
	}

	/**
	 * Related posts from the first category.
	 *
	 * @param WP_Post $post  Post.
	 * @param int     $count Count.
	 * @return WP_Post[]
	 */
	protected function related_posts( $post, $count ) {
		$cats = wp_get_post_categories( $post->ID );

		return get_posts(
			array(
				'numberposts'         => max( 1, (int) $count ),
				'post_status'         => 'publish',
				'post__not_in'        => array( $post->ID ),
				'ignore_sticky_posts' => true,
				'category__in'        => $cats ? $cats : array(),
			)
		);
	}

	/**
	 * Product for the in-article card: explicit ID, else first product of the
	 * post's category, else nothing.
	 *
	 * @param WP_Post $post Post.
	 * @return WC_Product|null
	 */
	protected function article_product( $post ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$s   = $this->effective_settings();
		$pid = isset( $s['product_id'] ) ? absint( $s['product_id'] ) : 0;

		if ( $pid ) {
			$product = wc_get_product( $pid );
			return $product instanceof WC_Product ? $product : null;
		}

		/* Per-post override: editors can pick the exact product for an article. */
		$meta = absint( get_post_meta( $post->ID, '_hsw_product_id', true ) );
		if ( $meta ) {
			$product = wc_get_product( $meta );
			if ( $product instanceof WC_Product ) {
				return $product;
			}
		}

		/*
		 * Auto match. Blog categories and product categories are different
		 * taxonomies, so matching a blog slug against product_cat almost never
		 * hit and every article fell through to «newest product» — the card
		 * showed the same item everywhere. Match by category NAME, then by the
		 * post's tags, and show nothing when there is no honest match.
		 */
		$cats = wp_get_post_categories( $post->ID, array( 'fields' => 'all' ) );
		if ( $cats ) {
			$found = get_posts(
				array(
					'numberposts' => 1,
					'post_type'   => 'product',
					'post_status' => 'publish',
					'tax_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => 'product_cat',
							'field'    => 'name',
							'terms'    => wp_list_pluck( $cats, 'name' ),
						),
					),
				)
			);
			if ( $found ) {
				return wc_get_product( $found[0]->ID );
			}
		}

		$tags = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );
		if ( $tags ) {
			$found = get_posts(
				array(
					'numberposts' => 1,
					'post_type'   => 'product',
					'post_status' => 'publish',
					'tax_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => 'product_tag',
							'field'    => 'name',
							'terms'    => $tags,
						),
					),
				)
			);
			if ( $found ) {
				return wc_get_product( $found[0]->ID );
			}
		}

		/* No honest match: hide the card instead of promoting an unrelated item. */
		return null;
	}

	/* ---------------------------------------------------------------------
	 * Render
	 * ------------------------------------------------------------------ */

	protected function render() {
		$post = $this->current_post();

		if ( ! $post ) {
			echo '<div class="hsw-pp hsw-pp--empty">' . esc_html__( 'نوشته‌ای برای نمایش پیدا نشد.', 'hamishop-elementor-widgets' ) . '</div>';
			return;
		}

		$s        = $this->effective_settings();
		$on       = static function ( $key, $default = 'yes' ) use ( $s ) {
			return ! isset( $s[ $key ] ) ? ( 'yes' === $default ) : ( 'yes' === $s[ $key ] );
		};
		$toc_mode = isset( $s['toc_anchor'] ) && in_array( $s['toc_anchor'], array( 'slug', 'index', 'none' ), true ) ? $s['toc_anchor'] : 'slug';
		$content  = $this->prepared_content( $post, $toc_mode );
		$category = $this->post_category( $post );
		$author   = (int) $post->post_author;
		$product  = $on( 'show_product' ) ? $this->article_product( $post ) : null;
		$views    = $this->views( $post->ID );
		$comments = (int) get_comments_number( $post );

		$classes = 'hsw-pp hsw-vis-' . ( isset( $s['vis'] ) ? sanitize_html_class( $s['vis'] ) : 'auto' ) . $this->hsw_font_class();
		?>
		<div class="<?php echo esc_attr( $classes ); ?>" data-hsw-pp data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>">

			<?php if ( $on( 'show_progress' ) ) : ?>
				<div class="hsw-pp__progress" aria-hidden="true"><span data-hsw-pp-progress></span></div>
			<?php endif; ?>

			<?php if ( $on( 'show_topbar' ) ) : ?>
				<header class="hsw-pp__topbar">
					<a class="hsw-pp__back" href="<?php echo esc_url( $this->blog_url() ); ?>" aria-label="<?php esc_attr_e( 'بازگشت به مجله', 'hamishop-elementor-widgets' ); ?>">
						<?php HSW_Helpers::the_icon( 'arrow_forward' ); ?>
					</a>
					<div class="hsw-pp__topmeta">
						<span class="hsw-pp__toptitle"><?php echo esc_html( wp_trim_words( $post->post_title, 7, '…' ) ); ?></span>
						<span class="hsw-pp__topcat">
							<i class="hsw-pp__dot" aria-hidden="true"></i>
							<?php echo esc_html( $category ? $category['name'] : get_bloginfo( 'name' ) ); ?>
						</span>
					</div>
					<div class="hsw-pp__topacts">
						<button type="button" class="hsw-pp__iconbtn" data-hsw-pp-bookmark aria-label="<?php esc_attr_e( 'نشان‌کردن مقاله', 'hamishop-elementor-widgets' ); ?>">
							<?php HSW_Helpers::the_icon( 'bookmark' ); ?>
						</button>
						<button type="button" class="hsw-pp__iconbtn" data-hsw-pp-share aria-label="<?php esc_attr_e( 'اشتراک‌گذاری', 'hamishop-elementor-widgets' ); ?>">
							<?php HSW_Helpers::the_icon( 'share' ); ?>
						</button>
					</div>
				</header>
			<?php endif; ?>

			<div class="hsw-pp__wrap">
				<div class="hsw-pp__grid">
					<article class="hsw-pp__main">

						<?php if ( $on( 'show_crumbs' ) ) : ?>
							<div class="hsw-pp__crumbs">
								<nav class="hsw-pp__trail" aria-label="<?php esc_attr_e( 'مسیر راهنما', 'hamishop-elementor-widgets' ); ?>">
									<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'خانه', 'hamishop-elementor-widgets' ); ?></a>
									<?php HSW_Helpers::the_icon( 'chevron_left' ); ?>
									<a href="<?php echo esc_url( $this->blog_url() ); ?>"><?php esc_html_e( 'مجله', 'hamishop-elementor-widgets' ); ?></a>
									<?php if ( $category ) : ?>
										<?php HSW_Helpers::the_icon( 'chevron_left' ); ?>
										<a href="<?php echo esc_url( $category['url'] ); ?>"><?php echo esc_html( $category['name'] ); ?></a>
									<?php endif; ?>
								</nav>
								<?php if ( $category ) : ?>
									<span class="hsw-pp__badge"><?php echo esc_html( $category['name'] ); ?></span>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<h1 class="hsw-pp__title"><?php echo esc_html( $post->post_title ); ?></h1>

						<?php if ( $on( 'show_meta' ) ) : ?>
							<div class="hsw-pp__meta">
								<?php $author_name = $this->author_name( $author ); ?>
								<?php if ( '' !== $author_name ) : ?>
									<div class="hsw-pp__author">
										<?php echo get_avatar( $author, 44, '', '', array( 'class' => 'hsw-pp__avatar' ) ); ?>
										<div class="hsw-pp__authorinfo">
											<span class="hsw-pp__authorname"><?php echo esc_html( $author_name ); ?></span>
											<span class="hsw-pp__authorrole"><?php echo esc_html( $this->author_role( $author ) ); ?></span>
										</div>
									</div>
								<?php endif; ?>
								<ul class="hsw-pp__stats">
									<li><?php HSW_Helpers::the_icon( 'calendar_today' ); ?><span><?php echo esc_html( $this->post_date( $post ) ); ?></span></li>
									<li><?php HSW_Helpers::the_icon( 'schedule' ); ?><span><?php echo esc_html( sprintf( /* translators: %s: minutes. */ __( '%s دقیقه مطالعه', 'hamishop-elementor-widgets' ), HSW_Helpers::fa_num( $this->read_time( $post ) ) ) ); ?></span></li>
									<?php if ( $views > 0 ) : ?>
										<li><?php HSW_Helpers::the_icon( 'visibility' ); ?><span><?php echo esc_html( sprintf( /* translators: %s: views. */ __( '%s بازدید', 'hamishop-elementor-widgets' ), HSW_Helpers::fa_num( $views ) ) ); ?></span></li>
									<?php endif; ?>
									<li><?php HSW_Helpers::the_icon( 'chat_bubble' ); ?><span><?php echo esc_html( sprintf( /* translators: %s: comments. */ __( '%s دیدگاه', 'hamishop-elementor-widgets' ), HSW_Helpers::fa_num( $comments ) ) ); ?></span></li>
								</ul>
								<div class="hsw-pp__tools">
									<button type="button" class="hsw-pp__iconbtn" data-hsw-pp-font aria-label="<?php esc_attr_e( 'تغییر اندازه قلم', 'hamishop-elementor-widgets' ); ?>">
										<?php HSW_Helpers::the_icon( 'format_size' ); ?>
									</button>
									<button type="button" class="hsw-pp__iconbtn" data-hsw-pp-bookmark aria-label="<?php esc_attr_e( 'نشان‌کردن مقاله', 'hamishop-elementor-widgets' ); ?>">
										<?php HSW_Helpers::the_icon( 'bookmark' ); ?>
									</button>
									<button type="button" class="hsw-pp__iconbtn" data-hsw-pp-share aria-label="<?php esc_attr_e( 'اشتراک‌گذاری', 'hamishop-elementor-widgets' ); ?>">
										<?php HSW_Helpers::the_icon( 'share' ); ?>
									</button>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( $on( 'show_hero' ) && has_post_thumbnail( $post ) ) : ?>
							<figure class="hsw-pp__hero">
								<?php
								echo get_the_post_thumbnail(
									$post,
									'large',
									array(
										'class'         => 'hsw-pp__heroimg skip-lazy',
										'loading'       => 'eager',
										'fetchpriority' => 'high',
										'data-no-lazy'  => '1',
									)
								);
								?>
								<?php
								$caption = get_the_post_thumbnail_caption( $post );
								if ( $caption ) :
									?>
									<figcaption class="hsw-pp__caption"><?php echo esc_html( $caption ); ?></figcaption>
								<?php endif; ?>
							</figure>
						<?php endif; ?>

						<?php if ( $on( 'show_toc' ) && ! empty( $content['toc'] ) ) : ?>
							<details class="hsw-pp__toc" open>
								<summary class="hsw-pp__tochead">
									<span class="hsw-pp__toctitle">
										<?php HSW_Helpers::the_icon( 'format_list_numbered' ); ?>
										<?php esc_html_e( 'فهرست سرفصل‌های این مقاله', 'hamishop-elementor-widgets' ); ?>
									</span>
									<span class="hsw-pp__tocchev"><?php HSW_Helpers::the_icon( 'expand_more' ); ?></span>
								</summary>
								<ol class="hsw-pp__toclist">
									<?php foreach ( $content['toc'] as $item ) : ?>
										<li class="hsw-pp__tocitem hsw-pp__tocitem--h<?php echo (int) $item['level']; ?>">
											<?php echo $this->toc_link_html( $item, $toc_mode, 'main' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
										</li>
									<?php endforeach; ?>
								</ol>
							</details>
						<?php endif; ?>

						<div class="hsw-pp__body" data-hsw-pp-body>
							<?php echo $content['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core the_content output. ?>
						</div>

						<?php if ( $product ) : ?>
							<?php $this->render_product_card( $product, $s ); ?>
						<?php endif; ?>

						<?php
						$tags = get_the_tags( $post->ID );
						if ( $on( 'show_tags' ) && $tags ) :
							?>
							<div class="hsw-pp__tags">
								<span class="hsw-pp__tagslabel"><?php esc_html_e( 'برچسب‌های مرتبط:', 'hamishop-elementor-widgets' ); ?></span>
								<div class="hsw-pp__taglist">
									<?php foreach ( $tags as $tag ) : ?>
										<a class="hsw-pp__tag" href="<?php echo esc_url( get_tag_link( $tag ) ); ?>">#<?php echo esc_html( $tag->name ); ?></a>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( $on( 'show_author_box' ) && '' !== $this->author_name( $author ) ) : ?>
							<?php $this->render_author_box( $author ); ?>
						<?php endif; ?>

						<?php if ( $on( 'show_comments' ) ) : ?>
							<?php $this->render_comments( $post ); ?>
						<?php endif; ?>

						<?php if ( $on( 'show_related' ) ) : ?>
							<?php
							$related = $this->related_posts( $post, isset( $s['related_count'] ) ? absint( $s['related_count'] ) : 3 );
							if ( $related ) :
								?>
								<section class="hsw-pp__related">
									<div class="hsw-pp__sechead">
										<h2 class="hsw-pp__sectitle"><?php esc_html_e( 'مقالات پیشنهادی و مرتبط', 'hamishop-elementor-widgets' ); ?></h2>
										<a class="hsw-pp__seclink" href="<?php echo esc_url( $this->blog_url() ); ?>"><?php esc_html_e( 'مشاهده همه', 'hamishop-elementor-widgets' ); ?></a>
									</div>
									<div class="hsw-pp__relgrid">
										<?php foreach ( $related as $item ) : ?>
											<?php $rcat = $this->post_category( $item ); ?>
											<article class="hsw-pp__relcard">
												<a class="hsw-pp__relmedia" href="<?php echo esc_url( get_permalink( $item ) ); ?>">
													<?php echo get_the_post_thumbnail( $item, 'medium', array( 'class' => 'hsw-pp__relimg', 'loading' => 'lazy' ) ); ?>
												</a>
												<div class="hsw-pp__relbody">
													<?php if ( $rcat ) : ?>
														<span class="hsw-pp__relcat"><?php echo esc_html( $rcat['name'] ); ?></span>
													<?php endif; ?>
													<h3 class="hsw-pp__reltitle">
														<a href="<?php echo esc_url( get_permalink( $item ) ); ?>"><?php echo esc_html( $item->post_title ); ?></a>
													</h3>
													<span class="hsw-pp__relmeta"><?php echo esc_html( sprintf( /* translators: %s: minutes. */ __( '%s دقیقه مطالعه', 'hamishop-elementor-widgets' ), HSW_Helpers::fa_num( $this->read_time( $item ) ) ) ); ?></span>
												</div>
											</article>
										<?php endforeach; ?>
									</div>
								</section>
							<?php endif; ?>
						<?php endif; ?>

						<?php if ( $on( 'show_prevnext' ) ) : ?>
							<?php $this->render_prevnext( $post ); ?>
						<?php endif; ?>

					</article>

					<aside class="hsw-pp__side">
						<?php $this->render_sidebar( $post, $author, $content['toc'], $toc_mode ); ?>
					</aside>
				</div>
			</div>

			<?php if ( $on( 'show_dock' ) ) : ?>
				<?php $this->render_dock( $post, $product, $comments ); ?>
			<?php endif; ?>

		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Partials
	 * ------------------------------------------------------------------ */

	/**
	 * Author display name, or '' when the post has no (valid) author.
	 *
	 * @param int $author_id User ID.
	 * @return string
	 */
	protected function author_name( $author_id ) {
		$author_id = (int) $author_id;
		if ( $author_id <= 0 ) {
			return '';
		}
		return trim( (string) get_the_author_meta( 'display_name', $author_id ) );
	}

	/**
	 * Author role: stored description's first line, else a neutral label.
	 *
	 * @param int $author_id User ID.
	 * @return string
	 */
	protected function author_role( $author_id ) {
		$bio = (string) get_the_author_meta( 'description', $author_id );
		if ( '' !== trim( $bio ) ) {
			$first = preg_split( '/\r\n|\r|\n/', trim( $bio ) );
			$first = trim( (string) $first[0] );
			if ( '' !== $first ) {
				return wp_trim_words( $first, 6, '…' );
			}
		}
		return __( 'نویسندهٔ مجله', 'hamishop-elementor-widgets' );
	}

	/**
	 * In-article product card.
	 *
	 * @param WC_Product $product Product.
	 * @param array      $s       Settings.
	 */
	protected function render_product_card( $product, $s ) {
		$label   = isset( $s['product_label'] ) && '' !== trim( (string) $s['product_label'] ) ? trim( (string) $s['product_label'] ) : __( 'کالای معرفی‌شده در این مقاله', 'hamishop-elementor-widgets' );
		$cfp     = HSW_Helpers::is_call_for_price( $product );
		$regular = (float) $product->get_regular_price();
		$sale    = (float) $product->get_sale_price();
		$off     = ( $regular > 0 && $sale > 0 && $sale < $regular ) ? (int) round( ( ( $regular - $sale ) / $regular ) * 100 ) : 0;
		?>
		<section class="hsw-pp__prod">
			<div class="hsw-pp__prodlabel"><?php HSW_Helpers::the_icon( 'verified' ); ?><?php echo esc_html( $label ); ?></div>
			<div class="hsw-pp__prodcard">
				<?php if ( $off > 0 ) : ?>
					<span class="hsw-pp__prodoff"><?php echo esc_html( sprintf( /* translators: %s: percent. */ __( '%s٪ تخفیف', 'hamishop-elementor-widgets' ), HSW_Helpers::fa_num( $off ) ) ); ?></span>
				<?php endif; ?>
				<a class="hsw-pp__prodmedia" href="<?php echo esc_url( $product->get_permalink() ); ?>">
					<?php echo $product->get_image( 'woocommerce_thumbnail', array( 'class' => 'hsw-pp__prodimg', 'loading' => 'lazy' ) ); ?>
				</a>
				<div class="hsw-pp__prodinfo">
					<span class="hsw-pp__prodeyebrow"><?php echo esc_html( $this->product_eyebrow( $product ) ); ?></span>
					<h3 class="hsw-pp__prodtitle">
						<a href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
					</h3>
					<div class="hsw-pp__prodprice">
						<?php if ( $cfp ) : ?>
							<?php echo HSW_Helpers::cfp_inline_html( $product, true, 'hsw-pp__prodcfp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
						<?php else : ?>
							<?php if ( $sale > 0 && $regular > $sale ) : ?>
								<del><?php echo wp_kses_post( wc_price( $regular ) ); ?></del>
							<?php endif; ?>
							<ins><?php echo wp_kses_post( $product->get_price_html() ); ?></ins>
						<?php endif; ?>
					</div>
					<?php
					/*
					 * add_to_cart_url() on a simple product is "this page +
					 * ?add-to-cart=ID": clicking it reloaded the article and
					 * looked like a dead link. hsw-post.js has no add-to-cart
					 * handler either, so the button goes to the product page —
					 * which is what «بررسی مشخصات» promises.
					 */
					?>
					<a class="hsw-pp__prodbtn" href="<?php echo esc_url( $product->get_permalink() ); ?>">
						<?php HSW_Helpers::the_icon( 'shopping_cart' ); ?>
						<span><?php esc_html_e( 'خرید سریع و بررسی مشخصات', 'hamishop-elementor-widgets' ); ?></span>
					</a>
				</div>
			</div>
		</section>
		<?php
	}

	/**
	 * "Brand • category" eyebrow for the product card.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	protected function product_eyebrow( $product ) {
		$bits = array();

		$brands = get_the_terms( $product->get_id(), 'product_brand' );
		if ( $brands && ! is_wp_error( $brands ) ) {
			$bits[] = $brands[0]->name;
		}

		$cats = get_the_terms( $product->get_id(), 'product_cat' );
		if ( $cats && ! is_wp_error( $cats ) ) {
			$bits[] = $cats[0]->name;
		}

		return $bits ? implode( ' • ', $bits ) : __( 'پیشنهاد فروشگاه', 'hamishop-elementor-widgets' );
	}

	/**
	 * Author bio box.
	 *
	 * @param int $author_id User ID.
	 */
	protected function render_author_box( $author_id ) {
		$bio = trim( (string) get_the_author_meta( 'description', $author_id ) );
		?>
		<section class="hsw-pp__authorbox">
			<div class="hsw-pp__authorboxhead">
				<?php echo get_avatar( $author_id, 56, '', '', array( 'class' => 'hsw-pp__authorboximg' ) ); ?>
				<div>
					<h3 class="hsw-pp__authorboxname"><?php echo esc_html( get_the_author_meta( 'display_name', $author_id ) ); ?></h3>
					<span class="hsw-pp__authorboxrole"><?php echo esc_html( $this->author_role( $author_id ) ); ?></span>
				</div>
			</div>
			<?php if ( '' !== $bio ) : ?>
				<p class="hsw-pp__authorboxbio"><?php echo esc_html( $bio ); ?></p>
			<?php endif; ?>
			<a class="hsw-pp__authorboxlink" href="<?php echo esc_url( get_author_posts_url( $author_id ) ); ?>">
				<span><?php esc_html_e( 'مشاهده سایر مقالات نویسنده', 'hamishop-elementor-widgets' ); ?></span>
				<?php HSW_Helpers::the_icon( 'arrow_left' ); ?>
			</a>
		</section>
		<?php
	}

	/**
	 * Real WordPress comments + form.
	 *
	 * @param WP_Post $post Post.
	 */
	protected function render_comments( $post ) {
		$count = (int) get_comments_number( $post );

		if ( ! comments_open( $post ) && 0 === $count ) {
			return;
		}
		?>
		<section class="hsw-pp__comments" id="hsw-comments">
			<div class="hsw-pp__sechead">
				<h2 class="hsw-pp__sectitle">
					<?php HSW_Helpers::the_icon( 'forum' ); ?>
					<?php echo esc_html( sprintf( /* translators: %s: count. */ __( 'دیدگاه‌ها و نظرات کاربران (%s)', 'hamishop-elementor-widgets' ), HSW_Helpers::fa_num( $count ) ) ); ?>
				</h2>
				<?php if ( comments_open( $post ) ) : ?>
					<button type="button" class="hsw-pp__seclink" data-hsw-pp-commenttoggle>
						<?php HSW_Helpers::the_icon( 'add' ); ?>
						<span><?php esc_html_e( 'ثبت نظر', 'hamishop-elementor-widgets' ); ?></span>
					</button>
				<?php endif; ?>
			</div>

			<?php
			if ( $count > 0 ) {
				$comments = get_comments(
					array(
						'post_id' => $post->ID,
						'status'  => 'approve',
						'order'   => 'ASC',
					)
				);

				echo '<div class="hsw-pp__commentlist">';
				wp_list_comments(
					array(
						'style'       => 'div',
						'avatar_size' => 40,
						'short_ping'  => true,
						'callback'    => array( $this, 'comment_item' ),
					),
					$comments
				);
				echo '</div>';

				$pages = get_comment_pages_count( $comments, get_option( 'comments_per_page' ) );
				if ( $pages > 1 ) {
					paginate_comments_links( array( 'echo' => true, 'type' => 'plain' ) );
				}
			} else {
				echo '<p class="hsw-pp__noComments">' . esc_html__( 'هنوز دیدگاهی ثبت نشده — اولین نفر باشید.', 'hamishop-elementor-widgets' ) . '</p>';
			}

			if ( comments_open( $post ) ) {
				add_filter( 'comment_form_defaults', array( $this, 'comment_form_args' ), 99 );
				comment_form( array( 'post_id' => $post->ID ), $post->ID );
				remove_filter( 'comment_form_defaults', array( $this, 'comment_form_args' ), 99 );
			}
			?>
		</section>
		<?php
	}

	/**
	 * Comment markup matching the design (avatar + name + date + body).
	 *
	 * @param WP_Comment $comment Comment.
	 * @param array      $args    Args.
	 * @param int        $depth   Depth.
	 */
	public function comment_item( $comment, $args, $depth ) {
		$is_author = ( (int) $comment->user_id > 0 && (int) get_post_field( 'post_author', $comment->comment_post_ID ) === (int) $comment->user_id );
		?>
		<div id="comment-<?php echo esc_attr( (string) $comment->comment_ID ); ?>" class="hsw-pp__comment<?php echo $is_author ? ' is-author' : ''; ?>">
			<div class="hsw-pp__commenthead">
				<div class="hsw-pp__commentwho">
					<?php echo get_avatar( $comment, 40, '', '', array( 'class' => 'hsw-pp__commentavatar' ) ); ?>
					<div>
						<span class="hsw-pp__commentname">
							<?php echo esc_html( get_comment_author( $comment ) ); ?>
							<?php if ( $is_author ) : ?>
								<span class="hsw-pp__commentbadge"><?php esc_html_e( 'نویسنده', 'hamishop-elementor-widgets' ); ?></span>
							<?php endif; ?>
						</span>
						<span class="hsw-pp__commentdate"><?php echo esc_html( HSW_Helpers::fa_num( get_comment_date( '', $comment ) ) ); ?></span>
					</div>
				</div>
				<?php
				comment_reply_link(
					array_merge(
						$args,
						array(
							'depth'     => $depth,
							'max_depth' => $args['max_depth'],
							'reply_text' => __( 'پاسخ', 'hamishop-elementor-widgets' ),
							'before'    => '<span class="hsw-pp__commentreply">',
							'after'     => '</span>',
						)
					),
					$comment
				);
				?>
			</div>
			<div class="hsw-pp__commentbody">
				<?php comment_text( $comment ); ?>
				<?php if ( '0' === (string) $comment->comment_approved ) : ?>
					<p class="hsw-pp__commentmod"><?php esc_html_e( 'دیدگاه شما در انتظار تایید است.', 'hamishop-elementor-widgets' ); ?></p>
				<?php endif; ?>
			</div>
		<?php
		/*
		 * The root <div class="hsw-pp__comment"> opened above is deliberately
		 * NOT closed here: Walker_Comment::end_el() emits the matching
		 * </div><!-- #comment-## --> for `style => 'div'`. Closing it here too
		 * produced two extra </div> per comment, which closed .hsw-pp early and
		 * pushed the mobile dock out of the widget (it landed on <body>).
		 */
	}

	/**
	 * Comment form defaults (labels + classes).
	 *
	 * @param array $defaults Defaults.
	 * @return array
	 */
	public function comment_form_args( $defaults ) {
		$defaults['class_form']           = 'hsw-pp__form';
		$defaults['class_submit']         = 'hsw-pp__submit';
		$defaults['title_reply']          = __( 'ارسال دیدگاه یا پرسش درباره این مقاله', 'hamishop-elementor-widgets' );
		$defaults['title_reply_before']   = '<h3 class="hsw-pp__formtitle">';
		$defaults['title_reply_after']    = '</h3>';
		$defaults['comment_notes_before'] = '';
		$defaults['comment_field']        = '<p class="hsw-pp__field hsw-pp__field--full"><label for="comment">' . esc_html__( 'متن دیدگاه شما', 'hamishop-elementor-widgets' ) . ' <span class="required">*</span></label><textarea id="comment" name="comment" rows="4" required></textarea></p>';

		foreach ( array( 'author', 'email', 'url' ) as $field ) {
			if ( isset( $defaults[ 'fields' ][ $field ] ) ) {
				$defaults[ 'fields' ][ $field ] = str_replace( '<label', '<label class="hsw-pp__label"', $defaults[ 'fields' ][ $field ] );
			}
		}

		return $defaults;
	}

	/**
	 * Prev / next post navigation.
	 *
	 * @param WP_Post $post Post.
	 */
	protected function render_prevnext( $post ) {
		$prev = get_previous_post();
		$next = get_next_post();

		if ( ! $prev && ! $next ) {
			return;
		}
		?>
		<nav class="hsw-pp__pn" aria-label="<?php esc_attr_e( 'ناوبری نوشته‌ها', 'hamishop-elementor-widgets' ); ?>">
			<?php if ( $prev ) : ?>
				<a class="hsw-pp__pnitem" href="<?php echo esc_url( get_permalink( $prev ) ); ?>">
					<span class="hsw-pp__pnicons"><?php HSW_Helpers::the_icon( 'arrow_forward' ); ?></span>
					<span class="hsw-pp__pnbody">
						<span class="hsw-pp__pnlabel"><?php esc_html_e( 'پست قبلی', 'hamishop-elementor-widgets' ); ?></span>
						<span class="hsw-pp__pntitle"><?php echo esc_html( $prev->post_title ); ?></span>
					</span>
				</a>
			<?php endif; ?>
			<?php if ( $next ) : ?>
				<a class="hsw-pp__pnitem hsw-pp__pnitem--next" href="<?php echo esc_url( get_permalink( $next ) ); ?>">
					<span class="hsw-pp__pnbody">
						<span class="hsw-pp__pnlabel"><?php esc_html_e( 'پست بعدی', 'hamishop-elementor-widgets' ); ?></span>
						<span class="hsw-pp__pntitle"><?php echo esc_html( $next->post_title ); ?></span>
					</span>
					<span class="hsw-pp__pnicons"><?php HSW_Helpers::the_icon( 'arrow_back' ); ?></span>
				</a>
			<?php endif; ?>
		</nav>
		<?php
	}

	/**
	 * Desktop sidebar: TOC, author card, popular posts.
	 *
	 * @param WP_Post $post   Post.
	 * @param int     $author Author ID.
	 * @param array   $toc    TOC items.
	 * @param string  $mode   Anchor mode (slug|index|none).
	 */
	protected function render_sidebar( $post, $author, $toc, $mode = 'slug' ) {
		$popular = $this->popular_posts( 3 );
		?>
		<?php if ( ! empty( $toc ) ) : ?>
			<div class="hsw-pp__sidebox hsw-pp__sidebox--toc">
				<div class="hsw-pp__sidehead">
					<?php HSW_Helpers::the_icon( 'format_list_bulleted' ); ?>
					<span><?php esc_html_e( 'فهرست مطالب مقاله', 'hamishop-elementor-widgets' ); ?></span>
				</div>
				<nav class="hsw-pp__sidenav">
					<?php foreach ( $toc as $item ) : ?>
						<?php echo $this->toc_link_html( $item, $mode, 'side' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
					<?php endforeach; ?>
				</nav>
			</div>
		<?php endif; ?>

		<?php $side_name = $this->author_name( $author ); ?>
		<?php if ( '' !== $side_name ) : ?>
			<div class="hsw-pp__sidebox hsw-pp__sidebox--author">
				<?php echo get_avatar( $author, 80, '', '', array( 'class' => 'hsw-pp__sideavatar' ) ); ?>
				<h3 class="hsw-pp__sidename"><?php echo esc_html( $side_name ); ?></h3>
				<span class="hsw-pp__siderole"><?php echo esc_html( $this->author_role( $author ) ); ?></span>
				<a class="hsw-pp__sidelinkbtn" href="<?php echo esc_url( get_author_posts_url( $author ) ); ?>">
					<span><?php esc_html_e( 'سایر مقالات نویسنده', 'hamishop-elementor-widgets' ); ?></span>
					<?php HSW_Helpers::the_icon( 'arrow_left' ); ?>
				</a>
			</div>
		<?php endif; ?>

		<?php if ( $popular ) : ?>
			<div class="hsw-pp__sidebox hsw-pp__sidebox--popular">
				<div class="hsw-pp__sidehead">
					<?php HSW_Helpers::the_icon( 'trending_up' ); ?>
					<span><?php esc_html_e( 'پربازدیدترین مقالات', 'hamishop-elementor-widgets' ); ?></span>
				</div>
				<div class="hsw-pp__poplist">
					<?php foreach ( $popular as $item ) : ?>
						<a class="hsw-pp__popitem" href="<?php echo esc_url( get_permalink( $item ) ); ?>">
							<span class="hsw-pp__poppic"><?php echo get_the_post_thumbnail( $item, 'thumbnail', array( 'class' => 'hsw-pp__popimg', 'loading' => 'lazy' ) ); ?></span>
							<span class="hsw-pp__popbody">
								<span class="hsw-pp__poptitle"><?php echo esc_html( $item->post_title ); ?></span>
								<span class="hsw-pp__popdate"><?php echo esc_html( $this->post_date( $item ) ); ?></span>
							</span>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Mobile sticky dock.
	 *
	 * @param WP_Post         $post     Post.
	 * @param WC_Product|null $product  Article product.
	 * @param int             $comments Comment count.
	 */
	protected function render_dock( $post, $product, $comments ) {
		?>
		<aside class="hsw-pp__dock">
			<div class="hsw-pp__dockinner">
				<button type="button" class="hsw-pp__dockbtn" data-hsw-pp-like aria-pressed="false">
					<?php HSW_Helpers::the_icon( 'favorite' ); ?>
					<span class="hsw-pp__docklabel"><?php echo esc_html( HSW_Helpers::fa_num( $comments ) ); ?></span>
				</button>
				<a class="hsw-pp__dockbtn" href="#hsw-comments">
					<?php HSW_Helpers::the_icon( 'chat_bubble_outline' ); ?>
					<span class="hsw-pp__docklabel"><?php esc_html_e( 'نظر', 'hamishop-elementor-widgets' ); ?></span>
				</a>
				<?php if ( $product ) : ?>
					<a class="hsw-pp__dockcta" href="<?php echo esc_url( $product->get_permalink() ); ?>">
						<?php HSW_Helpers::the_icon( 'shopping_bag' ); ?>
						<span><?php esc_html_e( 'خرید این کالا', 'hamishop-elementor-widgets' ); ?></span>
					</a>
				<?php else : ?>
					<a class="hsw-pp__dockcta" href="#hsw-comments">
						<?php HSW_Helpers::the_icon( 'chat_bubble' ); ?>
						<span><?php esc_html_e( 'ثبت دیدگاه', 'hamishop-elementor-widgets' ); ?></span>
					</a>
				<?php endif; ?>
			</div>
		</aside>
		<?php
	}
}
