<?php
/**
 * Elementor widget: HamiShop customer account panel.
 *
 * One composite widget that renders the whole account experience: RTL shell,
 * WooCommerce endpoints, custom auth screens, support, notifications, wishlist
 * and wallet. Settings in the widget override the global account defaults.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;

class HSW_Widget_Account extends HSW_Widget_Base {

	public function get_name() {
		return 'hsw-account';
	}

	public function get_title() {
		return __( 'پنل حساب کاربری HamiShop', 'hamishop-elementor-widgets' );
	}

	public function get_icon() {
		return 'eicon-user-circle-o';
	}

	public function get_keywords() {
		return array( 'account', 'my account', 'customer', 'login', 'register', 'حساب کاربری', 'ورود', 'پشتیبانی', 'hamishop' );
	}

	public function get_categories() {
		return array( 'hamishop-account' );
	}

	public function get_style_depends() {
		return array( 'hsw-fonts', 'hsw-account' );
	}

	public function get_script_depends() {
		return array( 'hsw-account' );
	}

	/**
	 * The shared palette injects into the widget wrapper; the account root
	 * sets its own admin tokens, so this widget ships its own root palette.
	 */
	protected function palette_map() {
		return array();
	}

	protected function register_controls() {
		$this->start_controls_section(
			'sec_preview',
			array(
				'label' => __( 'پیش‌نمایش و وضعیت', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'ac_preview',
			array(
				'label'       => __( 'پیش‌نمایش در ویرایشگر', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => '',
				'options'     => array(
					''              => __( 'خودکار (وضعیت واقعی کاربر)', 'hamishop-elementor-widgets' ),
					'auth'          => __( 'فرم ورود / ثبت‌نام', 'hamishop-elementor-widgets' ),
					'dashboard'     => __( 'پیشخوان', 'hamishop-elementor-widgets' ),
					'support'       => __( 'تیکت‌های پشتیبانی', 'hamishop-elementor-widgets' ),
					'notifications' => __( 'اعلان‌ها', 'hamishop-elementor-widgets' ),
					'wishlist'      => __( 'علاقه‌مندی‌ها', 'hamishop-elementor-widgets' ),
					'wallet'        => __( 'کیف پول', 'hamishop-elementor-widgets' ),
				),
				'description' => __( 'این گزینه فقط در ویرایشگر المنتور اثر دارد. در سایت واقعی، اندپوینت جاری کاربر نمایش داده می‌شود.', 'hamishop-elementor-widgets' ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'sec_general',
			array(
				'label' => __( 'هدر و منوی حساب', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_switch_control( 'ac_show_breadcrumb', __( 'نمایش مسیر صفحه', 'hamishop-elementor-widgets' ) );
		$this->add_switch_control( 'ac_show_hero', __( 'نمایش هدر خوش‌آمد', 'hamishop-elementor-widgets' ) );
		$this->add_switch_control( 'ac_show_profile', __( 'نمایش کارت پروفایل', 'hamishop-elementor-widgets' ) );
		$this->add_switch_control( 'ac_show_membership', __( 'نمایش باشگاه مشتریان', 'hamishop-elementor-widgets' ) );
		$this->add_switch_control( 'ac_show_menu', __( 'نمایش منوی حساب', 'hamishop-elementor-widgets' ) );
		$this->add_switch_control( 'ac_show_logout', __( 'نمایش گزینهٔ خروج', 'hamishop-elementor-widgets' ) );
		$this->add_switch_control( 'ac_show_badge', __( 'نمایش نشان هدر', 'hamishop-elementor-widgets' ) );
		$this->add_switch_control( 'ac_show_catalog', __( 'نمایش دکمهٔ کاتالوگ', 'hamishop-elementor-widgets' ) );
		$this->add_text_control( 'ac_badge_text', __( 'متن نشان هدر', 'hamishop-elementor-widgets' ) );
		$this->add_text_control( 'ac_greeting', __( 'متن خوش‌آمدگویی', 'hamishop-elementor-widgets' ), 'text', __( 'برای نام کاربر از {name} استفاده کنید.', 'hamishop-elementor-widgets' ) );
		$this->add_text_control( 'ac_subtitle', __( 'زیرعنوان هدر', 'hamishop-elementor-widgets' ), 'textarea' );
		$this->add_text_control( 'ac_catalog_label', __( 'متن دکمهٔ کاتالوگ', 'hamishop-elementor-widgets' ) );
		$this->add_text_control( 'ac_catalog_url', __( 'لینک دکمهٔ کاتالوگ', 'hamishop-elementor-widgets' ), 'url' );
		$this->add_text_control( 'ac_menu_title', __( 'عنوان منوی حساب', 'hamishop-elementor-widgets' ) );
		$this->end_controls_section();

		$this->start_controls_section(
			'sec_menu_items',
			array(
				'label' => __( 'آیتم‌های منوی حساب', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_switch_control( 'ac_menu_orders', __( 'سفارش‌ها', 'hamishop-elementor-widgets' ), __( 'با غیرفعال کردن هر بخش، لینک آن از منو حذف می‌شود و باز کردن مستقیم آدرس آن نیز پیام «بخش غیرفعال است» نشان می‌دهد.', 'hamishop-elementor-widgets' ) );
		$this->add_switch_control( 'ac_menu_downloads', __( 'دانلودها', 'hamishop-elementor-widgets' ) );
		$this->add_switch_control( 'ac_menu_addresses', __( 'آدرس‌ها', 'hamishop-elementor-widgets' ) );
		$this->add_switch_control( 'ac_menu_wishlist', __( 'علاقه‌مندی‌ها', 'hamishop-elementor-widgets' ) );
		$this->add_switch_control( 'ac_menu_wallet', __( 'کیف پول', 'hamishop-elementor-widgets' ) );
		$this->add_switch_control( 'ac_menu_support', __( 'پشتیبانی', 'hamishop-elementor-widgets' ) );
		$this->add_switch_control( 'ac_menu_notifications', __( 'اعلان‌ها', 'hamishop-elementor-widgets' ) );
		$this->add_switch_control( 'ac_menu_payments', __( 'روش‌های پرداخت', 'hamishop-elementor-widgets' ) );
		$this->add_switch_control( 'ac_menu_account', __( 'اطلاعات حساب', 'hamishop-elementor-widgets' ) );
		$this->end_controls_section();

		$this->start_controls_section(
			'sec_dashboard',
			array(
				'label' => __( 'بخش‌های پیشخوان', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_switch_control( 'ac_show_stats', __( 'کارت‌های آماری', 'hamishop-elementor-widgets' ) );
		$this->add_switch_control( 'ac_show_recent', __( 'کارت سفارش اخیر', 'hamishop-elementor-widgets' ) );
		$this->add_switch_control( 'ac_show_shortcuts', __( 'میانبرهای پرکاربرد', 'hamishop-elementor-widgets' ) );
		$this->add_switch_control( 'ac_show_address', __( 'کارت نشانی پیش‌فرض', 'hamishop-elementor-widgets' ) );
		$this->add_switch_control( 'ac_shortcut_support', __( 'میانبر پشتیبانی', 'hamishop-elementor-widgets' ) );
		$this->add_switch_control( 'ac_shortcut_returns', __( 'میانبر درخواست مرجوعی', 'hamishop-elementor-widgets' ) );
		$this->add_switch_control( 'ac_shortcut_downloads', __( 'میانبر دانلودها', 'hamishop-elementor-widgets' ) );
		$this->end_controls_section();

		$this->start_controls_section(
			'sec_auth',
			array(
				'label' => __( 'فرم ورود و ثبت‌نام', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_text_control( 'ac_login_title', __( 'عنوان ورود', 'hamishop-elementor-widgets' ) );
		$this->add_text_control( 'ac_login_subtitle', __( 'زیرعنوان ورود', 'hamishop-elementor-widgets' ), 'textarea' );
		$this->add_text_control( 'ac_register_title', __( 'عنوان ثبت‌نام', 'hamishop-elementor-widgets' ) );
		$this->add_text_control( 'ac_register_subtitle', __( 'زیرعنوان ثبت‌نام', 'hamishop-elementor-widgets' ), 'textarea' );
		$this->add_text_control( 'ac_brand_title', __( 'عنوان پنل برند', 'hamishop-elementor-widgets' ) );
		$this->add_text_control( 'ac_brand_subtitle', __( 'زیرعنوان پنل برند', 'hamishop-elementor-widgets' ), 'textarea' );
		$this->end_controls_section();

		$this->start_controls_section(
			'sec_layout',
			array(
				'label' => __( 'چیدمان', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'ac_side_position',
			array(
				'label'   => __( 'جای منوی کناری', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''      => __( 'پیش‌فرض تنظیمات', 'hamishop-elementor-widgets' ),
					'right' => __( 'راست', 'hamishop-elementor-widgets' ),
					'left'  => __( 'چپ', 'hamishop-elementor-widgets' ),
				),
			)
		);
		$this->add_control(
			'ac_side_width',
			array(
				'label'       => __( 'عرض ستون کناری (px)', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 240,
				'max'         => 420,
				'step'        => 1,
				'default'     => '',
				'placeholder' => '320',
			)
		);
		$this->add_control(
			'ac_side_unit',
			array(
				'label'   => __( 'واحد عرض ستون کناری', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					''  => __( 'پیکسل (px)', 'hamishop-elementor-widgets' ),
					'%' => __( 'درصد (%)', 'hamishop-elementor-widgets' ),
				),
				'default' => '',
			)
		);
		$this->add_control(
			'ac_max_width',
			array(
				'label'       => __( 'حداکثر عرض (px)', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 960,
				'max'         => 1600,
				'default'     => '',
				'placeholder' => '1280',
			)
		);
		$this->add_control(
			'ac_max_unit',
			array(
				'label'   => __( 'واحد حداکثر عرض', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					''  => __( 'پیکسل (px)', 'hamishop-elementor-widgets' ),
					'%' => __( 'درصد (%)', 'hamishop-elementor-widgets' ),
				),
				'default' => '',
			)
		);
		$this->add_control(
			'ac_radius',
			array(
				'label'       => __( 'گردی گوشه‌ها (px)', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 40,
				'default'     => '',
				'placeholder' => '20',
			)
		);
		$this->end_controls_section();

		/*
		 * No palette section here on purpose: brand / accent / background / card /
		 * ink live in the admin palette and every --hsw-ac-* token in
		 * hsw-account.css already falls back to it. The typography and per-element
		 * controls below still override.
		 */


		/* ------------------- LAYOUT ------------------- */
		$this->start_controls_section(
			'sec_ac_layout',
			array( 'label' => __( 'چیدمان داشبورد', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
		);

		$this->add_responsive_control(
			'ac_gap',
			array(
				'label'      => __( 'فاصلهٔ محتوا و منوی کناری', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 64 ) ),
				'selectors'  => array( '{{WRAPPER}} .hsw-ac' => '--hsw-ac-gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'ac_side',
			array(
				'label'      => __( 'عرض منوی کناری', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array( 'px' => array( 'min' => 180, 'max' => 360 ), '%' => array( 'min' => 15, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .hsw-ac' => '--hsw-ac-side: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'ac_stats_cols',
			array(
				'label'     => __( 'تعداد ستون کارت‌های آمار', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '',
				'options'   => array( '' => __( 'پیش‌فرض', 'hamishop-elementor-widgets' ), '2' => '۲', '3' => '۳', '4' => '۴' ),
				'selectors' => array( '{{WRAPPER}} .hsw-ac__stats' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));' ),
			)
		);

		$this->add_responsive_control(
			'ac_stats_gap',
			array(
				'label'      => __( 'فاصلهٔ بین کارت‌های آمار', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .hsw-ac__stats' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();

		/* ------------------- CARDS (shared) ------------------- */
		$this->start_controls_section(
			'sec_ac_cards',
			array( 'label' => __( 'کارت‌ها (مشترک)', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
		);

		$this->add_control(
			'ac_cards_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => __( 'این تنظیمات روی کارت آمار، کارت سفارش و کارت‌های اطلاعات (آدرس/پروفایل) با هم اعمال می‌شود تا پنل شلوغ نشود.', 'hamishop-elementor-widgets' ),
				'content_classes' => 'elementor-descriptor',
			)
		);

		$this->a_color( 'ac_card_bg', __( 'پس‌زمینهٔ کارت‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ac', '--hsw-ac-card' );
		$this->a_color( 'ac_card_bd', __( 'حاشیهٔ کارت‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ac', '--hsw-ac-line' );

		$this->add_responsive_control(
			'ac_card_radius',
			array(
				'label'      => __( 'گردی گوشهٔ کارت‌ها', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 32 ) ),
				'selectors'  => array( '{{WRAPPER}} .hsw-ac' => '--hsw-ac-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'ac_card_pad',
			array(
				'label'      => __( 'فاصلهٔ داخلی کارت‌ها', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .hsw-ac__card, {{WRAPPER}} .hsw-ac__stat, {{WRAPPER}} .hsw-ac__order-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'ac_card_shadow',
				'label'    => __( 'سایهٔ کارت‌ها', 'hamishop-elementor-widgets' ),
				'selector' => '{{WRAPPER}} .hsw-ac__card, {{WRAPPER}} .hsw-ac__stat, {{WRAPPER}} .hsw-ac__order-card',
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'ac_card_shadow_hv',
				'label'    => __( 'سایهٔ هاور کارت‌ها', 'hamishop-elementor-widgets' ),
				'selector' => '{{WRAPPER}} .hsw-ac__stat:hover, {{WRAPPER}} .hsw-ac__order-card:hover',
			)
		);

		$this->end_controls_section();

		/* ------------------- HERO ------------------- */
		$this->start_controls_section(
			'sec_ac_hero',
			array( 'label' => __( 'هدر خوش‌آمدگویی', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
		);

		$this->a_color( 'ac_hero_bg', __( 'پس‌زمینهٔ هدر', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ac__hero', 'background-color' );
		$this->a_text( 'ac_hero_title', '{{WRAPPER}} .hsw-ac__hero-title', array( 'default' => 24 ) );
		$this->a_text( 'ac_hero_sub', '{{WRAPPER}} .hsw-ac__hero-sub', array( 'default' => 14 ) );

		$this->add_responsive_control(
			'ac_hero_pad',
			array(
				'label'      => __( 'فاصلهٔ داخلی هدر', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .hsw-ac__hero' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'ac_hero_radius',
			array(
				'label'      => __( 'گردی گوشهٔ هدر', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .hsw-ac__hero' => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();

		/* ------------------- MENU ------------------- */
		$this->start_controls_section(
			'sec_ac_menu',
			array( 'label' => __( 'منوی حساب', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
		);

		$this->a_color( 'ac_menu_bg', __( 'پس‌زمینهٔ منو', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ac__menu', 'background-color' );
		$this->a_color( 'ac_menu_tx', __( 'متن گزینه‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ac__menu-label' );
		$this->a_color( 'ac_menu_ic', __( 'آیکن گزینه‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ac__menu-ic' );
		$this->a_color( 'ac_menu_hv', __( 'پس‌زمینهٔ هاور', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ac__menu-item:hover', 'background-color' );
		$this->a_color( 'ac_menu_on_bg', __( 'پس‌زمینهٔ گزینهٔ فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ac__menu-item.is-active', 'background-color' );
		$this->a_color( 'ac_menu_on_tx', __( 'متن گزینهٔ فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ac__menu-item.is-active .hsw-ac__menu-label' );

		$this->add_responsive_control(
			'ac_menu_gap',
			array(
				'label'      => __( 'فاصلهٔ بین گزینه‌ها', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 24 ) ),
				'selectors'  => array( '{{WRAPPER}} .hsw-ac__menu-list' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'ac_menu_ic_size',
			array(
				'label'      => __( 'اندازهٔ آیکن منو', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 12, 'max' => 32 ) ),
				'selectors'  => array( '{{WRAPPER}} .hsw-ac__menu-ic .hsw-ic' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'ac_menu_pad',
			array(
				'label'      => __( 'فاصلهٔ داخلی منو', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .hsw-ac__menu' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		/* ------------------- ORDERS + BADGES ------------------- */
		$this->start_controls_section(
			'sec_ac_orders',
			array( 'label' => __( 'سفارش‌ها و برچسب وضعیت', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
		);

		$this->a_color( 'ac_order_bd', __( 'حاشیهٔ آیتم سفارش', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ac__order-card', 'border-color' );
		$this->a_text( 'ac_order_id', '{{WRAPPER}} .hsw-ac__order-id', array( 'default' => 15 ) );
		$this->a_text( 'ac_order_date', '{{WRAPPER}} .hsw-ac__order-date', array( 'default' => 13 ) );

		$this->add_responsive_control(
			'ac_order_pad',
			array(
				'label'      => __( 'فاصلهٔ داخلی آیتم سفارش', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .hsw-ac__order-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->a_color( 'ac_badge_bg', __( 'پس‌زمینهٔ برچسب وضعیت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ac__badge', 'background-color' );
		$this->a_color( 'ac_badge_tx', __( 'متن برچسب وضعیت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ac__badge' );

		$this->add_responsive_control(
			'ac_badge_radius',
			array(
				'label'      => __( 'گردی برچسب وضعیت', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 24 ) ),
				'selectors'  => array( '{{WRAPPER}} .hsw-ac__badge' => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();

		/* ------------------- INFO CARDS ------------------- */
		$this->start_controls_section(
			'sec_ac_info',
			array( 'label' => __( 'کارت‌های اطلاعات (آدرس و پروفایل)', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
		);

		$this->add_responsive_control(
			'ac_info_pad',
			array(
				'label'      => __( 'فاصلهٔ داخلی کارت اطلاعات', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .hsw-ac__addr-card, {{WRAPPER}} .hsw-ac__profile' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->a_color( 'ac_info_kicker', __( 'رنگ برچسب کوچک', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ac__addr-kicker' );
		$this->a_text( 'ac_info_value', '{{WRAPPER}} .hsw-ac__address-lines, {{WRAPPER}} .hsw-ac__addr-body', array( 'default' => 14 ) );
		$this->a_color( 'ac_info_link', __( 'رنگ لینک ویرایش', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ac__addr-edit, {{WRAPPER}} .hsw-ac__address-edit' );

		$this->add_responsive_control(
			'ac_info_row_gap',
			array(
				'label'      => __( 'فاصلهٔ بین ردیف‌های اطلاعات', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 24 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .hsw-ac__address-lines' => 'row-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		/* ------------------- MESSAGES / STATES ------------------- */
		$this->start_controls_section(
			'sec_ac_states',
			array( 'label' => __( 'پیام‌ها و حالت‌های ویژه', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
		);

		$this->a_color( 'ac_note_bg', __( 'پس‌زمینهٔ پیام', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ac__note', 'background-color' );
		$this->a_color( 'ac_note_tx', __( 'متن پیام', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ac__note' );

		$this->a_color( 'ac_empty_ic', __( 'آیکن حالت خالی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ac__empty-ic' );
		$this->a_text( 'ac_empty_tx', '{{WRAPPER}} .hsw-ac__empty', array( 'default' => 14 ) );

		$this->add_responsive_control(
			'ac_empty_pad',
			array(
				'label'      => __( 'فاصلهٔ داخلی حالت خالی', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .hsw-ac__empty' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		/* ------------------- BUTTONS / AVATAR ------------------- */
		$this->start_controls_section(
			'sec_ac_buttons',
			array( 'label' => __( 'دکمه‌ها و آواتار', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
		);

		$this->add_responsive_control(
			'ac_btn_radius',
			array(
				'label'      => __( 'گردی دکمه‌ها', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .hsw-ac__btn' => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'ac_btn_pad',
			array(
				'label'      => __( 'فاصلهٔ داخلی دکمه‌ها', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .hsw-ac__btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->a_color( 'ac_logout_bg', __( 'دکمهٔ خروج — پس‌زمینه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ac__btn--danger', 'background-color' );
		$this->a_color( 'ac_logout_tx', __( 'دکمهٔ خروج — متن', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ac__btn--danger' );

		$this->add_responsive_control(
			'ac_avatar_size',
			array(
				'label'      => __( 'اندازهٔ آواتار', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 28, 'max' => 96 ) ),
				'selectors'  => array( '{{WRAPPER}} .hsw-ac__avatar' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'ac_avatar_radius',
			array(
				'label'      => __( 'گردی آواتار', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 50 ) ),
				'selectors'  => array( '{{WRAPPER}} .hsw-ac__avatar' => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'sec_typography',
			array(
				'label' => __( 'تایپوگرافی', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'ac_typo_heading',
				'label'    => __( 'عنوان هدر', 'hamishop-elementor-widgets' ),
				'selector' => '{{WRAPPER}} .hsw-ac__hero-title',
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'ac_typo_body',
				'label'    => __( 'متن پنل', 'hamishop-elementor-widgets' ),
				'selector' => '{{WRAPPER}} .hsw-ac',
			)
		);
		$this->end_controls_section();
	}

	private function add_switch_control( $key, $label, $description = '' ) {
		$this->add_control(
			$key,
			array(
				'label'       => $label,
				'type'        => Controls_Manager::SELECT,
				'default'     => '',
				'description' => $description,
				'options'     => array(
					''    => __( 'پیش‌فرض تنظیمات', 'hamishop-elementor-widgets' ),
					'yes' => __( 'فعال', 'hamishop-elementor-widgets' ),
					'no'  => __( 'غیرفعال', 'hamishop-elementor-widgets' ),
				),
			)
		);
	}

	private function add_text_control( $key, $label, $type = 'text', $description = '' ) {
		if ( 'textarea' === $type ) {
			$this->add_control(
				$key,
				array(
					'label'       => $label,
					'type'        => Controls_Manager::TEXTAREA,
					'default'     => '',
					'description' => $description,
				)
			);
			return;
		}
		if ( 'url' === $type ) {
			$this->add_control(
				$key,
				array(
					'label'       => $label,
					'type'        => Controls_Manager::URL,
					'default'     => array( 'url' => '' ),
					'description' => $description,
					'options'     => false,
					'label_block' => true,
				)
			);
			return;
		}
		$this->add_control(
			$key,
			array(
				'label'       => $label,
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'description' => $description,
				'label_block' => true,
			)
		);
	}

	protected function render() {
		if ( ! class_exists( 'HSW_Account' ) ) {
			return;
		}
		$s    = $this->effective_settings();
		$args = array(
			'context' => 'widget',
			'editor'  => class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->editor->is_edit_mode(),
		);

		if ( ! empty( $s['ac_preview'] ) ) {
			$args['preview'] = sanitize_key( $s['ac_preview'] );
		}

		$switches = array(
			'ac_show_breadcrumb'  => array( 'general', 'show_breadcrumb' ),
			'ac_show_hero'        => array( 'general', 'show_hero' ),
			'ac_show_profile'     => array( 'general', 'show_profile' ),
			'ac_show_membership'  => array( 'general', 'show_membership' ),
			'ac_show_menu'        => array( 'general', 'show_menu' ),
			'ac_show_logout'      => array( 'general', 'show_logout' ),
			'ac_show_badge'       => array( 'general', 'show_badge' ),
			'ac_show_catalog'     => array( 'general', 'show_catalog_button' ),
			'ac_show_stats'       => array( 'dashboard', 'show_stats' ),
			'ac_show_recent'      => array( 'dashboard', 'show_recent_order' ),
			'ac_show_shortcuts'   => array( 'dashboard', 'show_shortcuts' ),
			'ac_show_address'     => array( 'dashboard', 'show_address' ),
			'ac_shortcut_support' => array( 'dashboard', 'shortcut_support' ),
			'ac_shortcut_returns' => array( 'dashboard', 'shortcut_returns' ),
			'ac_shortcut_downloads' => array( 'dashboard', 'shortcut_downloads' ),
		);
		foreach ( $switches as $key => $map ) {
			if ( isset( $s[ $key ] ) && in_array( $s[ $key ], array( 'yes', 'no' ), true ) ) {
				$args[ $map[0] ][ $map[1] ] = $s[ $key ];
			}
		}

		$menu_switches = array(
			'ac_menu_orders'        => 'orders',
			'ac_menu_downloads'     => 'downloads',
			'ac_menu_addresses'     => 'edit-address',
			'ac_menu_wishlist'      => 'wishlist',
			'ac_menu_wallet'        => 'wallet',
			'ac_menu_support'       => 'support',
			'ac_menu_notifications' => 'notifications',
			'ac_menu_payments'      => 'payment-methods',
			'ac_menu_account'       => 'edit-account',
		);
		foreach ( $menu_switches as $key => $item_key ) {
			if ( isset( $s[ $key ] ) && in_array( $s[ $key ], array( 'yes', 'no' ), true ) ) {
				$args['menu']['items'][ $item_key ] = array( 'enabled' => $s[ $key ] );
			}
		}
		if ( empty( $args['menu']['items'] ) ) {
			unset( $args['menu'] );
		}

		$texts = array(
			'ac_badge_text'      => array( 'general', 'badge_text' ),
			'ac_greeting'        => array( 'general', 'greeting' ),
			'ac_subtitle'        => array( 'general', 'subtitle' ),
			'ac_catalog_label'   => array( 'general', 'catalog_label' ),
			'ac_menu_title'      => array( 'general', 'menu_title' ),
			'ac_login_title'     => array( 'auth', 'login_title' ),
			'ac_login_subtitle'  => array( 'auth', 'login_subtitle' ),
			'ac_register_title'  => array( 'auth', 'register_title' ),
			'ac_register_subtitle' => array( 'auth', 'register_subtitle' ),
			'ac_brand_title'     => array( 'auth', 'brand_title' ),
			'ac_brand_subtitle'  => array( 'auth', 'brand_subtitle' ),
		);
		foreach ( $texts as $key => $map ) {
			if ( ! empty( $s[ $key ] ) ) {
				$args[ $map[0] ][ $map[1] ] = sanitize_textarea_field( $s[ $key ] );
			}
		}
		if ( ! empty( $s['ac_catalog_url']['url'] ) ) {
			$args['general']['catalog_url'] = esc_url_raw( $s['ac_catalog_url']['url'] );
		}
		if ( ! empty( $s['ac_side_position'] ) ) {
			$args['general']['side_position'] = 'left' === $s['ac_side_position'] ? 'left' : 'right';
		}

		$vars = $this->inline_vars( $s );
		if ( $vars ) {
			$args['inline_vars'] = $vars;
		}

		echo '<div class="hsw-ac-widget">' . HSW_Account::instance()->render( $args ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped account markup.
	}

	private function inline_vars( $s ) {
		$vars = array();
		/*
		 * Each entry: CSS variable, min, max, and the control that carries its
		 * unit. The unit is a separate select because a NUMBER control cannot
		 * hold one — with `%` the same sidebar is a third of a 1600px screen and
		 * a half of a 1024px one, which is exactly what the shop owner needs to
		 * be able to choose.
		 */
		$map  = array(
			'ac_side_width' => array( '--hsw-ac-side', 240, 420, 'ac_side_unit' ),
			'ac_gap'        => array( '--hsw-ac-gap', 0, 64, '' ),
			'ac_max_width'  => array( '--hsw-ac-max', 960, 1600, 'ac_max_unit' ),
			'ac_radius'     => array( '--hsw-ac-radius', 0, 40, '' ),
		);
		foreach ( $map as $key => $item ) {
			if ( isset( $s[ $key ] ) && '' !== $s[ $key ] && is_numeric( $s[ $key ] ) ) {
				$unit  = ( '' !== $item[3] && isset( $s[ $item[3] ] ) && '%' === $s[ $item[3] ] ) ? '%' : 'px';
				$value = absint( $s[ $key ] );
				if ( '%' === $unit ) {
					$value = max( 15, min( 95, $value ) );
				} else {
					$value = max( $item[1], min( $item[2], $value ) );
				}
				$vars[ $item[0] ] = $value . $unit;
			}
		}
		$colors = array(
			'ac_primary' => '--hsw-ac-primary',
			'ac_accent'  => '--hsw-ac-accent',
			'ac_bg'      => '--hsw-ac-bg',
			'ac_card'    => '--hsw-ac-card',
			'ac_ink'     => '--hsw-ac-ink',
		);
		foreach ( $colors as $key => $var ) {
			if ( ! empty( $s[ $key ] ) ) {
				$color = preg_replace( '/[;{}]/', '', (string) $s[ $key ] );
				$color = sanitize_text_field( $color );
				if ( '' !== $color ) {
					$vars[ $var ] = $color;
				}
			}
		}

		// Keep the interface to five meaningful colors and derive the rest.
		if ( ! empty( $vars['--hsw-ac-primary'] ) ) {
			$primary_dark = self::adjust_hex_color( $vars['--hsw-ac-primary'], -0.36 );
			$hero_a       = self::adjust_hex_color( $vars['--hsw-ac-primary'], -0.22 );
			$hero_b       = self::adjust_hex_color( $vars['--hsw-ac-primary'], 0.10 );
			if ( $primary_dark ) {
				$vars['--hsw-ac-primary-dark'] = $primary_dark;
			}
			if ( $hero_a ) {
				$vars['--hsw-ac-hero-a'] = $hero_a;
			}
			if ( $hero_b ) {
				$vars['--hsw-ac-hero-b'] = $hero_b;
			}
		}
		if ( ! empty( $vars['--hsw-ac-ink'] ) ) {
			$muted = self::mix_hex_color( $vars['--hsw-ac-ink'], '#ffffff', 0.52 );
			$line  = self::mix_hex_color( $vars['--hsw-ac-ink'], '#ffffff', 0.87 );
			if ( $muted ) {
				$vars['--hsw-ac-muted'] = $muted;
			}
			if ( $line ) {
				$vars['--hsw-ac-line'] = $line;
			}
		}

		return $vars;
	}

	/**
	 * Lighten/darken a hexadecimal color. $amount is between -1 and 1.
	 */
	private static function adjust_hex_color( $hex, $amount ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( ! preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) {
			return '';
		}
		$amount = max( -1, min( 1, (float) $amount ) );
		$target = $amount < 0 ? 0 : 255;
		$ratio  = abs( $amount );
		$out    = '#';
		for ( $i = 0; $i < 3; $i++ ) {
			$channel = hexdec( substr( $hex, $i * 2, 2 ) );
			$value   = (int) round( $channel + ( $target - $channel ) * $ratio );
			$out    .= str_pad( dechex( max( 0, min( 255, $value ) ) ), 2, '0', STR_PAD_LEFT );
		}
		return $out;
	}

	/**
	 * Mix two hexadecimal colors. $weight is the amount of $second (0..1).
	 */
	private static function mix_hex_color( $first, $second, $weight ) {
		$first  = ltrim( (string) $first, '#' );
		$second = ltrim( (string) $second, '#' );
		if ( 3 === strlen( $first ) ) {
			$first = $first[0] . $first[0] . $first[1] . $first[1] . $first[2] . $first[2];
		}
		if ( 3 === strlen( $second ) ) {
			$second = $second[0] . $second[0] . $second[1] . $second[1] . $second[2] . $second[2];
		}
		if ( ! preg_match( '/^[0-9a-f]{6}$/i', $first ) || ! preg_match( '/^[0-9a-f]{6}$/i', $second ) ) {
			return '';
		}
		$weight = max( 0, min( 1, (float) $weight ) );
		$out    = '#';
		for ( $i = 0; $i < 3; $i++ ) {
			$a     = hexdec( substr( $first, $i * 2, 2 ) );
			$b     = hexdec( substr( $second, $i * 2, 2 ) );
			$value = (int) round( $a + ( $b - $a ) * $weight );
			$out  .= str_pad( dechex( max( 0, min( 255, $value ) ) ), 2, '0', STR_PAD_LEFT );
		}
		return $out;
	}
}
