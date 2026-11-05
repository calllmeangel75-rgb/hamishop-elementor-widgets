<?php
/**
 * Widget: «هدر GC» — floating bar, circuit mark, panel navigation.
 *
 * The look is a single rounded bar that floats over the page and docks into a
 * full-width blurred bar once the visitor scrolls, a brand mark drawn as a
 * circuit trace, a gradient underline that follows the pointer, and dropdown
 * panels whose rows arrive one after another.
 *
 * Everything the shop owner is likely to change is a control: the mark, the
 * brand text, every menu row, every panel row (icon + title + description),
 * both panel footers, the three action buttons and the whole colour set.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Header_GC extends HSW_Header_Base {

	/** Panel slots a menu item may open. */
	const PANELS = array( 'p1', 'p2', 'p3', 'p4', 'p5', 'p6' );

	public function get_name() {
		return 'hsw-header-gc';
	}

	public function get_title() {
		return __( 'هدر GC (HamiShop)', 'hamishop-elementor-widgets' );
	}

	public function get_icon() {
		return 'eicon-header';
	}

	public function get_keywords() {
		return array( 'header', 'menu', 'gc', 'floating', 'mega', 'هدر', 'سربرگ', 'شناور', 'مگامنو', 'hamishop' );
	}

	public function get_style_depends() {
		return array( 'hsw-fonts', 'hsw-header-gc' );
	}

	public function get_script_depends() {
		return array( 'hsw-header-gc' );
	}

	/* =====================================================================
	 * Controls
	 * ================================================================== */

	protected function register_controls() {
		$this->gc_content_brand();
		$this->gc_content_menu();
		$this->gc_content_actions();
		$this->gc_content_behavior();
		$this->gc_style();
	}

	/**
	 * Brand: mark, name, latin line.
	 */
	protected function gc_content_brand() {
		$this->start_controls_section(
			'sec_gc_brand',
			array(
				'label' => __( 'برند و لوگو', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'mark_style',
			array(
				'label'       => __( 'نشان برند', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'logo',
				'options'     => array(
					'logo'    => __( 'لوگوی سایت (خودکار)', 'hamishop-elementor-widgets' ),
					'circuit' => __( 'نشان مدار GC (انیمیشن خط)', 'hamishop-elementor-widgets' ),
					'icon'    => __( 'آیکون از مجموعهٔ HamiShop', 'hamishop-elementor-widgets' ),
					'none'    => __( 'بدون نشان (فقط متن)', 'hamishop-elementor-widgets' ),
				),
				'description' => __( 'به‌صورت پیش‌فرض همان لوگویی می‌آید که در «سفارشی‌سازی ← هویت سایت ← لوگو» گذاشته‌ای؛ لازم نیست دوباره انتخابش کنی. اگر سایت لوگو نداشته باشد، نشان مدار GC جای آن را می‌گیرد.', 'hamishop-elementor-widgets' ),
			)
		);

		$this->add_control(
			'logo',
			array(
				'label'       => __( 'لوگوی جایگزین (اختیاری)', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::MEDIA,
				'condition'   => array( 'mark_style' => 'logo' ),
				'description' => __( 'خالی بگذار تا لوگوی سایت استفاده شود. فقط اگر برای این هدر لوگوی دیگری می‌خواهی، اینجا تصویر بگذار.', 'hamishop-elementor-widgets' ),
			)
		);

		/*
		 * Say out loud which logo the automatic path found. Without it the
		 * owner has to guess whether the widget is showing the theme logo, a
		 * stale one, or falling back to the signature mark.
		 */
		$this->add_control(
			'logo_status',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => $this->logo_status_html(),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
				'condition'       => array( 'mark_style' => 'logo' ),
			)
		);

		$this->add_control(
			'mark_icon',
			array(
				'label'     => __( 'آیکون نشان', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'storefront',
				'options'   => self::icon_options(),
				'condition' => array( 'mark_style' => 'icon' ),
			)
		);

		$this->add_control(
			'brand_name',
			array(
				'label'       => __( 'نام برند', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => get_bloginfo( 'name' ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'brand_en',
			array(
				'label'       => __( 'نام لاتین (زیر نام برند)', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
				'description' => __( 'با فاصلهٔ حروف باز و فونت مونو نوشته می‌شود؛ مثلاً GILACLICK. خالی = نمایش داده نمی‌شود.', 'hamishop-elementor-widgets' ),
			)
		);

		$this->add_control(
			'brand_url',
			array(
				'label'   => __( 'لینک برند', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::URL,
				'default' => array( 'url' => home_url( '/' ) ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Menu: top-level rows plus the panel rows they open.
	 */
	protected function gc_content_menu() {
		$this->start_controls_section(
			'sec_gc_menu',
			array(
				'label' => __( 'منوی اصلی', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_nav',
			array(
				'label'   => __( 'نمایش منو', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$repeater = new \Elementor\Repeater();

		$repeater->add_control(
			'gc_label',
			array(
				'label'       => __( 'عنوان', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'آیتم منو', 'hamishop-elementor-widgets' ),
				'label_block' => true,
			)
		);

		$repeater->add_control(
			'gc_url',
			array(
				'label' => __( 'لینک', 'hamishop-elementor-widgets' ),
				'type'  => Controls_Manager::URL,
			)
		);

		$repeater->add_control(
			'gc_panel',
			array(
				'label'       => __( 'پنل کشویی', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => '',
				'options'     => $this->panel_options(),
				'description' => __( 'اگر این آیتم باید زیرمنو باز کند، یک شمارهٔ پنل انتخاب کن و همان شماره را در «ردیف‌های پنل‌ها» پایین‌تر پر کن.', 'hamishop-elementor-widgets' ),
			)
		);

		$this->add_control(
			'gc_items',
			array(
				'label'       => __( 'آیتم‌های منو', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => $this->default_items(),
				'title_field' => '{{{ gc_label }}}',
				'separator'   => 'before',
			)
		);

		$panel = new \Elementor\Repeater();

		$panel->add_control(
			'gc_panel',
			array(
				'label'   => __( 'کدام پنل؟', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'p1',
				'options' => $this->panel_options( false ),
			)
		);

		$panel->add_control(
			'gc_kind',
			array(
				'label'   => __( 'نوع ردیف', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'link',
				'options' => array(
					'link'   => __( 'ردیف لینک', 'hamishop-elementor-widgets' ),
					'footer' => __( 'لینک پایین پنل', 'hamishop-elementor-widgets' ),
				),
			)
		);

		$panel->add_control(
			'gc_icon',
			array(
				'label'     => __( 'آیکون', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'storefront',
				'options'   => self::icon_options(),
				'condition' => array( 'gc_kind' => 'link' ),
			)
		);

		$panel->add_control(
			'gc_title',
			array(
				'label'       => __( 'عنوان', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
			)
		);

		$panel->add_control(
			'gc_desc',
			array(
				'label'       => __( 'توضیح کوتاه', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'condition'   => array( 'gc_kind' => 'link' ),
			)
		);

		$panel->add_control(
			'gc_url',
			array(
				'label' => __( 'لینک', 'hamishop-elementor-widgets' ),
				'type'  => Controls_Manager::URL,
			)
		);

		$this->add_control(
			'gc_panel_links',
			array(
				'label'       => __( 'ردیف‌های پنل‌ها', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $panel->get_controls(),
				'default'     => $this->default_panel_links(),
				'title_field' => '{{{ gc_title }}}',
				'separator'   => 'before',
				'description' => __( 'هر ردیف به یکی از شش پنل وصل می‌شود. آیتم منویی که «پنل ۱» را انتخاب کرده، همین ردیف‌های «پنل ۱» را نشان می‌دهد. ردیف نوع «لینک پایین پنل» به‌صورت لینک تأکیدشده زیر فهرست می‌آید.', 'hamishop-elementor-widgets' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Actions: cart, account, hamburger.
	 */
	protected function gc_content_actions() {
		$this->start_controls_section(
			'sec_gc_actions',
			array(
				'label' => __( 'دکمه‌های کنار منو', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_cart',
			array(
				'label'   => __( 'سبد خرید', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'cart_icon',
			array(
				'label'     => __( 'آیکون سبد', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'shopping_cart',
				'options'   => self::icon_options(),
				'condition' => array( 'show_cart' => 'yes' ),
			)
		);

		$this->add_control(
			'show_badge',
			array(
				'label'       => __( 'نشانگر تعداد کالا', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'condition'   => array( 'show_cart' => 'yes' ),
				'description' => __( 'فقط وقتی سبد کالا دارد و کوکی ووکامرس موجود است، یک درخواست برای خواندن تعداد فرستاده می‌شود.', 'hamishop-elementor-widgets' ),
			)
		);

		$this->add_control(
			'show_account',
			array(
				'label'     => __( 'دکمهٔ حساب کاربری', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'account_text',
			array(
				'label'     => __( 'متن دکمهٔ حساب', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'ورود / حساب کاربری', 'hamishop-elementor-widgets' ),
				'condition' => array( 'show_account' => 'yes' ),
			)
		);

		$this->add_control(
			'account_icon',
			array(
				'label'     => __( 'آیکون حساب', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'person',
				'options'   => self::icon_options(),
				'condition' => array( 'show_account' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Behaviour: fixed or in flow, and the mobile drawer.
	 */
	protected function gc_content_behavior() {
		$this->start_controls_section(
			'sec_gc_behavior',
			array(
				'label' => __( 'رفتار هدر', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'hd_scroll',
			array(
				'label'   => __( 'رفتار با اسکرول', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'dock',
				'options' => array(
					'dock'     => __( 'شناور و چسبان — همیشه دیده می‌شود و به نوار تمام‌عرض تبدیل می‌شود', 'hamishop-elementor-widgets' ),
					'autohide' => __( 'پنهان با اسکرول پایین — با اسکرول به بالا فوراً برمی‌گردد', 'hamishop-elementor-widgets' ),
					'static'   => __( 'درون جریان صفحه — اگر خودِ سکشن چسبان است', 'hamishop-elementor-widgets' ),
				),
				'description' => __( '«پنهان با اسکرول پایین» برای صفحه‌هایی است که می‌خواهی محتوا کامل دیده شود: نوار با رفتن به پایین کنار می‌رود و با کوچک‌ترین اسکرول به بالا برمی‌گردد. «درون جریان» را وقتی انتخاب کن که در تنظیمات همان سکشن المنتور «چسبان» را روشن کرده‌ای.', 'hamishop-elementor-widgets' ),
			)
		);

		$this->add_control(
			'dock_after',
			array(
				'label'      => __( 'فاصلهٔ چسبیدن (پیکسل)', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 0,
						'max'  => 200,
						'step' => 2,
					),
				),
				'default'    => array(
					'size' => 24,
					'unit' => 'px',
				),
				'condition'  => array( 'hd_scroll' => 'dock' ),
				'description' => __( 'بعد از این مقدار اسکرول، نوار شناور به نوار تمام‌عرض مات تبدیل می‌شود.', 'hamishop-elementor-widgets' ),
			)
		);

		$this->add_control(
			'show_drawer',
			array(
				'label'     => __( 'منوی موبایل (کشوی)', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'separator' => 'before',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style: colours (palette-first, no defaults) plus geometry and type.
	 */
	protected function gc_style() {
		$this->start_controls_section(
			'sec_gc_style',
			array(
				'label' => __( 'استایل هدر GC', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		/*
		 * No colour control carries a default. Elementor prints a default into
		 * the page CSS, and that is exactly how the older headers pinned a blue
		 * primary on every site that never touched the palette. An empty value
		 * emits nothing, so an untouched header follows HamiShop → پالت رنگی.
		 */
		$colours = array(
			'gc_primary'      => array( __( 'رنگ اصلی', 'hamishop-elementor-widgets' ), '--hsw-gc-primary-user', __( 'خالی = رنگ اصلی پالت.', 'hamishop-elementor-widgets' ) ),
			'gc_primary_dark' => array( __( 'رنگ اصلی تیره', 'hamishop-elementor-widgets' ), '--hsw-gc-primary-dark-user', __( 'خالی = نسخهٔ تیرهٔ رنگ اصلی پالت.', 'hamishop-elementor-widgets' ) ),
			'gc_glow'         => array( __( 'رنگ درخشش (خط و نشانگر)', 'hamishop-elementor-widgets' ), '--hsw-gc-glow-user', __( 'خالی = رنگ روشن پالت. خط زیر منو، هالهٔ لوگو و نشانگر تعداد از این رنگ ساخته می‌شوند.', 'hamishop-elementor-widgets' ) ),
			'gc_ink'          => array( __( 'رنگ تیرهٔ نشان و دکمهٔ اصلی', 'hamishop-elementor-widgets' ), '--hsw-gc-ink-user', __( 'خالی = رنگ متن اصلی پالت.', 'hamishop-elementor-widgets' ) ),
			'gc_bg'           => array( __( 'پس‌زمینهٔ نوار', 'hamishop-elementor-widgets' ), '--hsw-gc-bg-user', __( 'خالی = پس‌زمینهٔ کارت پالت.', 'hamishop-elementor-widgets' ) ),
			'gc_text'         => array( __( 'رنگ متن', 'hamishop-elementor-widgets' ), '--hsw-gc-text-user', __( 'خالی = رنگ متن پالت.', 'hamishop-elementor-widgets' ) ),
			'gc_muted'        => array( __( 'متن کم‌رنگ', 'hamishop-elementor-widgets' ), '--hsw-gc-muted-user', __( 'خالی = متن کم‌رنگ پالت.', 'hamishop-elementor-widgets' ) ),
			'gc_mint'         => array( __( 'پس‌زمینهٔ ملایم (آیکون پنل)', 'hamishop-elementor-widgets' ), '--hsw-gc-mint-user', __( 'خالی = سایهٔ روشن رنگ اصلی پالت.', 'hamishop-elementor-widgets' ) ),
			'gc_line'         => array( __( 'رنگ خط و حاشیه', 'hamishop-elementor-widgets' ), '--hsw-gc-line-user', __( 'خالی = رنگ حاشیهٔ پالت.', 'hamishop-elementor-widgets' ) ),
		);

		foreach ( $colours as $id => $row ) {
			$this->add_control(
				$id,
				array(
					'label'       => $row[0],
					'description' => $row[2],
					'type'        => Controls_Manager::COLOR,
					'selectors'   => array(
						'{{WRAPPER}} .hsw-gc' => $row[1] . ': {{VALUE}};',
					),
				)
			);
		}

		$this->add_responsive_control(
			'bar_height',
			array(
				'label'      => __( 'ارتفاع نوار', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 48,
						'max' => 120,
					),
				),
				'default'    => array(
					'size' => 66,
					'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .hsw-gc' => '--hsw-gc-h: {{SIZE}}{{UNIT}};',
				),
				'separator'  => 'before',
			)
		);

		$this->add_responsive_control(
			'mark_size',
			array(
				'label'      => __( 'اندازهٔ نشان', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 20,
						'max' => 96,
					),
				),
				'default'    => array(
					'size' => 38,
					'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .hsw-gc' => '--hsw-gc-mark: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'bar_radius',
			array(
				'label'      => __( 'گردی گوشهٔ نوار شناور', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 40,
					),
				),
				'default'    => array(
					'size' => 12,
					'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .hsw-gc' => '--hsw-gc-r-lg: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array( 'hd_scroll!' => 'static' ),
			)
		);

		$this->a_typo(
			'gc_link',
			__( 'فونت آیتم‌های منو', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-gc__link',
			array( 'size' => 15 )
		);

		$this->a_typo(
			'gc_brand',
			__( 'فونت نام برند', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-gc__name',
			array( 'size' => 19 )
		);

		$this->end_controls_section();
	}

	/**
	 * Panel slot choices.
	 *
	 * @param bool $with_none Include the «بدون پنل» row.
	 * @return array<string,string>
	 */
	protected function panel_options( $with_none = true ) {
		$options = array();

		if ( $with_none ) {
			$options[''] = __( 'بدون پنل', 'hamishop-elementor-widgets' );
		}

		foreach ( self::PANELS as $index => $key ) {
			/* translators: %s: panel number. */
			$options[ $key ] = sprintf( __( 'پنل %s', 'hamishop-elementor-widgets' ), $this->fa_digit( $index + 1 ) );
		}

		return $options;
	}

	/**
	 * Persian digit for a small number (no dependency on the fa_num helper).
	 *
	 * @param int $number 1–9.
	 * @return string
	 */
	private function fa_digit( $number ) {
		$digits = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );

		return isset( $digits[ $number ] ) ? $digits[ $number ] : (string) $number;
	}

	/**
	 * Default top-level rows: two panels and three plain links.
	 *
	 * @return array
	 */
	protected function default_items() {
		return array(
			array(
				'gc_label' => __( 'صفحه اصلی', 'hamishop-elementor-widgets' ),
				'gc_url'   => array( 'url' => home_url( '/' ) ),
				'gc_panel' => '',
			),
			array(
				'gc_label' => __( 'فروشگاه', 'hamishop-elementor-widgets' ),
				'gc_url'   => array( 'url' => $this->shop_url() ),
				'gc_panel' => 'p1',
			),
			array(
				'gc_label' => __( 'خدمات', 'hamishop-elementor-widgets' ),
				'gc_url'   => array( 'url' => '' ),
				'gc_panel' => 'p2',
			),
			array(
				'gc_label' => __( 'درباره ما', 'hamishop-elementor-widgets' ),
				'gc_url'   => array( 'url' => '' ),
				'gc_panel' => '',
			),
			array(
				'gc_label' => __( 'تماس با ما', 'hamishop-elementor-widgets' ),
				'gc_url'   => array( 'url' => '' ),
				'gc_panel' => '',
			),
		);
	}

	/**
	 * Default panel rows: four shop shortcuts, three service shortcuts and one
	 * footer link per panel.
	 *
	 * @return array
	 */
	protected function default_panel_links() {
		return array(
			array(
				'gc_panel' => 'p1',
				'gc_kind'  => 'link',
				'gc_icon'  => 'storefront',
				'gc_title' => __( 'همه محصولات', 'hamishop-elementor-widgets' ),
				'gc_desc'  => __( 'فهرست کامل محصولات و قیمت‌ها', 'hamishop-elementor-widgets' ),
				'gc_url'   => array( 'url' => $this->shop_url() ),
			),
			array(
				'gc_panel' => 'p1',
				'gc_kind'  => 'link',
				'gc_icon'  => 'grid_view',
				'gc_title' => __( 'دسته‌بندی‌ها', 'hamishop-elementor-widgets' ),
				'gc_desc'  => __( 'مرور محصولات بر اساس دسته', 'hamishop-elementor-widgets' ),
				'gc_url'   => array( 'url' => $this->shop_url() ),
			),
			array(
				'gc_panel' => 'p1',
				'gc_kind'  => 'link',
				'gc_icon'  => 'shopping_cart',
				'gc_title' => __( 'سبد خرید', 'hamishop-elementor-widgets' ),
				'gc_desc'  => __( 'بررسی و تکمیل سفارش', 'hamishop-elementor-widgets' ),
				'gc_url'   => array( 'url' => $this->cart_url() ),
			),
			array(
				'gc_panel' => 'p1',
				'gc_kind'  => 'link',
				'gc_icon'  => 'person',
				'gc_title' => __( 'حساب کاربری', 'hamishop-elementor-widgets' ),
				'gc_desc'  => __( 'سفارش‌ها، آدرس و پیگیری', 'hamishop-elementor-widgets' ),
				'gc_url'   => array( 'url' => $this->account_url() ),
			),
			array(
				'gc_panel' => 'p1',
				'gc_kind'  => 'footer',
				'gc_icon'  => '',
				'gc_title' => __( 'مشاهدهٔ فروشگاه', 'hamishop-elementor-widgets' ),
				'gc_desc'  => '',
				'gc_url'   => array( 'url' => $this->shop_url() ),
			),
			array(
				'gc_panel' => 'p2',
				'gc_kind'  => 'link',
				'gc_icon'  => 'support_agent',
				'gc_title' => __( 'پشتیبانی', 'hamishop-elementor-widgets' ),
				'gc_desc'  => __( 'پاسخ به پرسش‌ها و پیگیری', 'hamishop-elementor-widgets' ),
				'gc_url'   => array( 'url' => $this->account_url() ),
			),
			array(
				'gc_panel' => 'p2',
				'gc_kind'  => 'link',
				'gc_icon'  => 'receipt_long',
				'gc_title' => __( 'پیگیری سفارش', 'hamishop-elementor-widgets' ),
				'gc_desc'  => __( 'وضعیت سفارش‌های ثبت‌شده', 'hamishop-elementor-widgets' ),
				'gc_url'   => array( 'url' => $this->orders_url() ),
			),
			array(
				'gc_panel' => 'p2',
				'gc_kind'  => 'link',
				'gc_icon'  => 'call',
				'gc_title' => __( 'تماس با ما', 'hamishop-elementor-widgets' ),
				'gc_desc'  => __( 'راه‌های ارتباطی فروشگاه', 'hamishop-elementor-widgets' ),
				'gc_url'   => array( 'url' => home_url( '/contact/' ) ),
			),
			array(
				'gc_panel' => 'p2',
				'gc_kind'  => 'footer',
				'gc_icon'  => '',
				'gc_title' => __( 'همهٔ خدمات', 'hamishop-elementor-widgets' ),
				'gc_desc'  => '',
				'gc_url'   => array( 'url' => home_url( '/contact/' ) ),
			),
		);
	}

	/* =====================================================================
	 * Render
	 * ================================================================== */

	protected function render() {
		$s   = $this->effective_settings();
		$uid = $this->uid();

		$mode = isset( $s['hd_scroll'] ) ? (string) $s['hd_scroll'] : 'dock';
		if ( ! in_array( $mode, array( 'dock', 'autohide', 'static' ), true ) ) {
			$mode = 'dock';
		}
		$dock = isset( $s['dock_after']['size'] ) ? max( 0, (int) $s['dock_after']['size'] ) : 24;

		$classes = 'hsw-h hsw-gc hsw-gc--' . $mode . $this->hsw_font_class();

		echo '<div class="' . esc_attr( $classes ) . '" data-hsw-gc="' . esc_attr( $uid ) . '" data-hsw-gc-dock="' . esc_attr( (string) $dock ) . '">';
		echo '<div class="hsw-gc__bar" data-hsw-gc-bar>';
		echo '<div class="hsw-gc__bg" aria-hidden="true"></div>';
		echo '<div class="hsw-gc__inner hsw-gc__container">';

		echo $this->gc_brand_html( $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.

		if ( ! isset( $s['show_nav'] ) || 'yes' === $s['show_nav'] ) {
			echo $this->gc_nav_html( $s, $uid ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
		}

		echo $this->gc_actions_html( $s, $uid ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.

		echo '</div></div>';
		echo '<div class="hsw-gc__ph" aria-hidden="true"></div>';

		if ( ! isset( $s['show_drawer'] ) || 'yes' === $s['show_drawer'] ) {
			echo $this->gc_drawer_html( $s, $uid ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
		}

		echo '</div>';
	}

	/**
	 * Brand link: mark + name + latin line.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	protected function gc_brand_html( $s ) {
		$name = isset( $s['brand_name'] ) ? trim( (string) $s['brand_name'] ) : '';
		$en   = isset( $s['brand_en'] ) ? trim( (string) $s['brand_en'] ) : '';
		$url  = isset( $s['brand_url']['url'] ) ? trim( (string) $s['brand_url']['url'] ) : '';
		$url  = '' !== $url ? $url : home_url( '/' );
		$aria = '' !== $name ? $name : get_bloginfo( 'name' );

		$out  = '<a class="hsw-gc__brand" href="' . esc_url( $url ) . '" aria-label="' . esc_attr( $aria ) . '">';
		$out .= '<span class="hsw-gc__logo">';
		$out .= $this->gc_mark_html( $s );

		if ( '' !== $name || '' !== $en ) {
			$out .= '<span class="hsw-gc__type">';
			if ( '' !== $name ) {
				$out .= '<span class="hsw-gc__name">' . esc_html( $name ) . '</span>';
			}
			if ( '' !== $en ) {
				/* Decorative: the accessible name is the Persian one. */
				$out .= '<span class="hsw-gc__tagline" lang="en" aria-hidden="true">' . esc_html( $en ) . '</span>';
			}
			$out .= '</span>';
		}

		$out .= '</span></a>';

		return $out;
	}

	/**
	 * The brand mark: circuit signature, uploaded logo, sprite icon or nothing.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	protected function gc_mark_html( $s ) {
		$style = isset( $s['mark_style'] ) ? (string) $s['mark_style'] : 'logo';

		if ( 'none' === $style ) {
			return '';
		}

		if ( 'logo' === $style ) {
			$img = $this->gc_logo_html( $s );
			if ( '' !== $img ) {
				return '<span class="hsw-gc__mark hsw-gc__mark--image">' . $img . '</span>';
			}
			/* No image anywhere: fall through to the signature mark. */
			$style = 'circuit';
		}

		if ( 'icon' === $style ) {
			return HSW_Helpers::icon( $this->icon_of( $s, 'mark_icon', 'storefront' ), 'hsw-gc__mark' );
		}

		return $this->gc_circuit_svg();
	}

	/**
	 * Panel notice: which logo the automatic path will use on this site.
	 *
	 * Without it the chain is invisible — the owner cannot tell a theme logo
	 * from the signature fallback without opening the page. Built only in the
	 * editor, so a front-end render pays nothing for a control nobody sees.
	 *
	 * @return string
	 */
	protected function logo_status_html() {
		if ( ! $this->is_editor_preview() ) {
			return '';
		}

		return $this->logo_status_message();
	}

	/**
	 * The notice itself, without the editor guard, so it can be asserted.
	 *
	 * @return string
	 */
	protected function logo_status_message() {
		$id = (int) get_theme_mod( 'custom_logo' );
		if ( ! $id ) {
			$id = (int) get_option( 'site_logo' );
		}

		if ( ! $id ) {
			return '<strong>' . esc_html__( 'این سایت لوگویی ندارد.', 'hamishop-elementor-widgets' ) . '</strong><br>'
				. esc_html__( 'تا وقتی در «سفارشی‌سازی ← هویت سایت ← لوگو» تصویری انتخاب نشود، نشان مدار GC نمایش داده می‌شود. می‌توانی همین‌جا در «لوگوی جایگزین» تصویر بگذاری.', 'hamishop-elementor-widgets' );
		}

		$thumb = wp_get_attachment_image_url( $id, array( 40, 40 ) );
		if ( ! $thumb ) {
			$thumb = wp_get_attachment_url( $id );
		}
		$title = get_the_title( $id );
		$title = '' !== $title ? $title : '#' . $id;

		$out  = '<span style="display:flex;align-items:center;gap:8px;">';
		if ( $thumb ) {
			$out .= '<img src="' . esc_url( $thumb ) . '" alt="" style="width:32px;height:32px;object-fit:contain;background:#fff;border-radius:4px;flex:0 0 auto;">';
		}
		$out .= '<span><strong>' . esc_html__( 'لوگوی سایت شناسایی شد:', 'hamishop-elementor-widgets' ) . '</strong> ' . esc_html( $title );
		$out .= '<br><span style="opacity:.75">' . esc_html__( 'همین تصویر در هدر می‌آید. برای عوض کردنش، لوگوی سایت را در سفارشی‌ساز تغییر بده.', 'hamishop-elementor-widgets' ) . '</span></span></span>';

		return $out;
	}

	/**
	 * The site's own logo, without asking the shop owner for it twice.
	 *
	 * Order: the image picked on this widget, then the theme's custom logo
	 * (سفارشی‌سازی ← هویت سایت ← لوگو), then the `site_logo` option that the
	 * block themes use. Asking for a size instead of `full` keeps a 2000px
	 * upload from being downloaded into a 38px box.
	 *
	 * @param array $s Settings.
	 * @return string Image markup, or '' when the site has no logo at all.
	 */
	protected function gc_logo_html( $s ) {
		$logo_id = isset( $s['logo']['id'] ) ? absint( $s['logo']['id'] ) : 0;

		if ( ! $logo_id ) {
			$logo_id = (int) get_theme_mod( 'custom_logo' );
		}
		if ( ! $logo_id && function_exists( 'get_option' ) ) {
			$logo_id = (int) get_option( 'site_logo' );
		}

		$attrs = array(
			'class'         => 'hsw-gc__logoimg skip-lazy',
			'alt'           => '',
			'loading'       => 'eager',
			'decoding'      => 'async',
			'data-no-lazy'  => '1',
			'fetchpriority' => 'high',
		);

		$img = $logo_id ? wp_get_attachment_image( $logo_id, array( 320, 320 ), false, $attrs ) : '';

		/*
		 * An SVG logo has no intermediate sizes, so `wp_get_attachment_image`
		 * can come back empty even though the file exists. Fall back to the
		 * raw URL rather than dropping the mark.
		 */
		if ( ! $img && $logo_id ) {
			$url = wp_get_attachment_url( $logo_id );
			if ( $url ) {
				$img = '<img src="' . esc_url( $url ) . '" alt="" loading="eager" decoding="async" data-no-lazy="1" class="hsw-gc__logoimg skip-lazy">';
			}
		}

		if ( ! $img && ! empty( $s['logo']['url'] ) ) {
			$img = '<img src="' . esc_url( $s['logo']['url'] ) . '" alt="" loading="eager" decoding="async" data-no-lazy="1" class="hsw-gc__logoimg skip-lazy">';
		}

		return $img;
	}

	/**
	 * The «G» drawn as a circuit trace that ends in a glowing node.
	 *
	 * @return string
	 */
	protected function gc_circuit_svg() {
		return '<svg class="hsw-gc__mark" aria-hidden="true" focusable="false" viewBox="0 0 40 40">'
			. '<path class="hsw-gc__body" d="M9 1h22a8 8 0 0 1 8 8v22a8 8 0 0 1-8 8H9a8 8 0 0 1-8-8V8.2L8.2 1Z"></path>'
			. '<path class="hsw-gc__trace" d="M26.7 13.3A9.5 9.5 0 1 0 29.4 21.3H23.2" pathLength="1"></path>'
			. '<circle class="hsw-gc__halo" cx="21" cy="21.3" r="5"></circle>'
			. '<circle class="hsw-gc__node" cx="21" cy="21.3" r="2.5"></circle>'
			. '</svg>';
	}

	/**
	 * Group the panel rows by slot.
	 *
	 * @param array $s Settings.
	 * @return array<string,array{links:array,foot:array|null}>
	 */
	protected function gc_panel_groups( $s ) {
		$groups = array();
		$rows   = isset( $s['gc_panel_links'] ) && is_array( $s['gc_panel_links'] ) ? $s['gc_panel_links'] : array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$key = isset( $row['gc_panel'] ) ? (string) $row['gc_panel'] : '';
			if ( ! in_array( $key, self::PANELS, true ) ) {
				continue;
			}
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'links' => array(),
					'foot'  => null,
				);
			}
			if ( isset( $row['gc_kind'] ) && 'footer' === $row['gc_kind'] ) {
				$groups[ $key ]['foot'] = $row;
			} else {
				$groups[ $key ]['links'][] = $row;
			}
		}

		return $groups;
	}

	/**
	 * Desktop navigation.
	 *
	 * @param array  $s   Settings.
	 * @param string $uid Widget id.
	 * @return string
	 */
	protected function gc_nav_html( $s, $uid ) {
		$items  = isset( $s['gc_items'] ) && is_array( $s['gc_items'] ) ? $s['gc_items'] : array();
		$groups = $this->gc_panel_groups( $s );

		$out  = '<nav class="hsw-gc__nav" aria-label="' . esc_attr__( 'منوی اصلی', 'hamishop-elementor-widgets' ) . '">';
		$out .= '<ul class="hsw-gc__list" data-hsw-gc-list>';

		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$label = isset( $item['gc_label'] ) ? trim( (string) $item['gc_label'] ) : '';
			$label = '' !== $label ? $label : __( 'آیتم منو', 'hamishop-elementor-widgets' );
			$key   = isset( $item['gc_panel'] ) ? (string) $item['gc_panel'] : '';
			$panel = ( '' !== $key && isset( $groups[ $key ] ) && $groups[ $key ]['links'] ) ? $groups[ $key ] : null;
			$url   = isset( $item['gc_url']['url'] ) ? trim( (string) $item['gc_url']['url'] ) : '';

			if ( ! $panel ) {
				$out .= '<li class="hsw-gc__item">';
				$out .= '' !== $url
					? '<a class="hsw-gc__link" href="' . esc_url( $url ) . '"' . $this->link_attrs( $item ) . '>' . esc_html( $label ) . '</a>'
					: '<span class="hsw-gc__link">' . esc_html( $label ) . '</span>';
				$out .= '</li>';
				continue;
			}

			$panel_id = $uid . '-gc-' . $key . '-' . (int) $index;

			$out .= '<li class="hsw-gc__item" data-hsw-gc-panel>';
			$out .= '<button class="hsw-gc__link" type="button" data-hsw-gc-toggle aria-expanded="false" aria-controls="' . esc_attr( $panel_id ) . '">';
			$out .= '<span>' . esc_html( $label ) . '</span>';
			$out .= HSW_Helpers::icon( 'expand_more', 'hsw-gc__chevron' );
			$out .= '</button>';

			$out .= '<div class="hsw-gc__panel" id="' . esc_attr( $panel_id ) . '">';
			$out .= '<ul class="hsw-gc__panel-list">';

			foreach ( $panel['links'] as $i => $row ) {
				$out .= '<li style="--i:' . (int) $i . '">' . $this->gc_panel_row_html( $row ) . '</li>';
			}

			$out .= '</ul>';

			if ( $panel['foot'] ) {
				$out .= $this->gc_panel_foot_html( $panel['foot'] );
			}

			$out .= '</div></li>';
		}

		$out .= '</ul>';
		$out .= '<span class="hsw-gc__line" data-hsw-gc-line aria-hidden="true"></span>';
		$out .= '</nav>';

		return $out;
	}

	/**
	 * One row inside a dropdown panel.
	 *
	 * @param array $row Repeater row.
	 * @return string
	 */
	protected function gc_panel_row_html( $row ) {
		$title = isset( $row['gc_title'] ) ? trim( (string) $row['gc_title'] ) : '';
		$desc  = isset( $row['gc_desc'] ) ? trim( (string) $row['gc_desc'] ) : '';
		$icon  = isset( $row['gc_icon'] ) && '' !== $row['gc_icon'] ? (string) $row['gc_icon'] : 'storefront';
		$url   = isset( $row['gc_url']['url'] ) ? trim( (string) $row['gc_url']['url'] ) : '';

		if ( '' === $title ) {
			return '';
		}

		$out  = '<a class="hsw-gc__panel-link" href="' . esc_url( '' !== $url ? $url : '#' ) . '"' . $this->link_attrs( $row ) . '>';
		$out .= '<span class="hsw-gc__panel-icon">' . HSW_Helpers::icon( $icon ) . '</span>';
		$out .= '<span class="hsw-gc__panel-text">';
		$out .= '<span class="hsw-gc__panel-title">' . esc_html( $title ) . '</span>';
		if ( '' !== $desc ) {
			$out .= '<span class="hsw-gc__panel-desc">' . esc_html( $desc ) . '</span>';
		}
		$out .= '</span></a>';

		return $out;
	}

	/**
	 * Accent link at the bottom of a panel.
	 *
	 * @param array $row Repeater row.
	 * @return string
	 */
	protected function gc_panel_foot_html( $row ) {
		$title = isset( $row['gc_title'] ) ? trim( (string) $row['gc_title'] ) : '';
		$url   = isset( $row['gc_url']['url'] ) ? trim( (string) $row['gc_url']['url'] ) : '';

		if ( '' === $title ) {
			return '';
		}

		return '<a class="hsw-gc__panel-foot" href="' . esc_url( '' !== $url ? $url : '#' ) . '"' . $this->link_attrs( $row ) . '>'
			. '<span>' . esc_html( $title ) . '</span>'
			. HSW_Helpers::icon( 'arrow_back' )
			. '</a>';
	}

	/**
	 * Cart, account and hamburger.
	 *
	 * @param array  $s   Settings.
	 * @param string $uid Widget id.
	 * @return string
	 */
	protected function gc_actions_html( $s, $uid ) {
		$show_cart    = ! isset( $s['show_cart'] ) || 'yes' === $s['show_cart'];
		$show_account = ! isset( $s['show_account'] ) || 'yes' === $s['show_account'];
		$show_drawer  = ! isset( $s['show_drawer'] ) || 'yes' === $s['show_drawer'];
		$show_badge   = ! isset( $s['show_badge'] ) || 'yes' === $s['show_badge'];
		$account_text = isset( $s['account_text'] ) ? trim( (string) $s['account_text'] ) : '';
		$account_text = '' !== $account_text ? $account_text : __( 'ورود / حساب کاربری', 'hamishop-elementor-widgets' );

		$out = '<div class="hsw-gc__actions">';

		if ( $show_cart ) {
			$count = $this->cart_count();
			$out  .= '<a class="hsw-gc__btn" data-hsw-gc-cart href="' . esc_url( $this->cart_url() ) . '" aria-label="' . esc_attr__( 'سبد خرید', 'hamishop-elementor-widgets' ) . '">';
			$out  .= HSW_Helpers::icon( $this->icon_of( $s, 'cart_icon', 'shopping_cart' ) );
			if ( $show_badge ) {
				$out .= '<span class="hsw-gc__badge" data-hsw-gc-count' . ( $count > 0 ? '' : ' hidden' ) . '>' . esc_html( HSW_Helpers::fa_num( (string) $count ) ) . '</span>';
			}
			$out .= '</a>';
		}

		if ( $show_account ) {
			$out .= '<a class="hsw-gc__cta" href="' . esc_url( $this->account_url() ) . '">';
			$out .= HSW_Helpers::icon( $this->icon_of( $s, 'account_icon', 'person' ) );
			$out .= '<span class="hsw-gc__cta-label">' . esc_html( $account_text ) . '</span>';
			$out .= '</a>';
		}

		if ( $show_drawer ) {
			$out .= '<button class="hsw-gc__btn hsw-gc__burger" type="button" data-hsw-gc-open aria-controls="' . esc_attr( $uid . '-gc-drawer' ) . '" aria-expanded="false" aria-label="' . esc_attr__( 'باز کردن منو', 'hamishop-elementor-widgets' ) . '">';
			$out .= '<span class="hsw-gc__lines" aria-hidden="true"><i></i><i></i><i></i></span>';
			$out .= '</button>';
		}

		$out .= '</div>';

		return $out;
	}

	/**
	 * Mobile drawer. Mirrors the bar so one edit changes both.
	 *
	 * @param array  $s   Settings.
	 * @param string $uid Widget id.
	 * @return string
	 */
	protected function gc_drawer_html( $s, $uid ) {
		$items  = isset( $s['gc_items'] ) && is_array( $s['gc_items'] ) ? $s['gc_items'] : array();
		$groups = $this->gc_panel_groups( $s );
		$id     = $uid . '-gc-drawer';

		$out  = '<div class="hsw-gc__drawer" id="' . esc_attr( $id ) . '" data-hsw-gc-drawer data-hsw-gc-for="' . esc_attr( $uid ) . '" role="dialog" aria-modal="true" aria-label="' . esc_attr__( 'منوی سایت', 'hamishop-elementor-widgets' ) . '" hidden>';
		$out .= '<div class="hsw-gc__backdrop" data-hsw-gc-close></div>';
		$out .= '<div class="hsw-gc__sheet" data-hsw-gc-sheet>';

		$out .= '<div class="hsw-gc__drawer-head">';
		$out .= '<span class="hsw-gc__logo">' . $this->gc_mark_html( $s );
		$name = isset( $s['brand_name'] ) ? trim( (string) $s['brand_name'] ) : '';
		if ( '' !== $name ) {
			$out .= '<span class="hsw-gc__name">' . esc_html( $name ) . '</span>';
		}
		$out .= '</span>';
		$out .= '<button class="hsw-gc__btn" type="button" data-hsw-gc-close aria-label="' . esc_attr__( 'بستن منو', 'hamishop-elementor-widgets' ) . '">' . HSW_Helpers::icon( 'close' ) . '</button>';
		$out .= '</div>';

		$out .= '<nav class="hsw-gc__drawer-nav" aria-label="' . esc_attr__( 'منوی موبایل', 'hamishop-elementor-widgets' ) . '"><ul>';

		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$label = isset( $item['gc_label'] ) ? trim( (string) $item['gc_label'] ) : '';
			$label = '' !== $label ? $label : __( 'آیتم منو', 'hamishop-elementor-widgets' );
			$key   = isset( $item['gc_panel'] ) ? (string) $item['gc_panel'] : '';
			$panel = ( '' !== $key && isset( $groups[ $key ] ) && $groups[ $key ]['links'] ) ? $groups[ $key ] : null;
			$url   = isset( $item['gc_url']['url'] ) ? trim( (string) $item['gc_url']['url'] ) : '';

			$out .= '<li class="hsw-gc__drawer-item" style="--i:' . (int) $index . '">';

			if ( ! $panel ) {
				$out .= '' !== $url
					? '<a class="hsw-gc__drawer-link" href="' . esc_url( $url ) . '"' . $this->link_attrs( $item ) . '>' . esc_html( $label ) . '</a>'
					: '<span class="hsw-gc__drawer-link">' . esc_html( $label ) . '</span>';
				$out .= '</li>';
				continue;
			}

			$out .= '<details class="hsw-gc__drawer-group">';
			$out .= '<summary class="hsw-gc__drawer-link"><span>' . esc_html( $label ) . '</span>' . HSW_Helpers::icon( 'expand_more', 'hsw-gc__drawer-chevron' ) . '</summary>';
			$out .= '<ul class="hsw-gc__drawer-sub">';

			foreach ( $panel['links'] as $i => $row ) {
				$title = isset( $row['gc_title'] ) ? trim( (string) $row['gc_title'] ) : '';
				if ( '' === $title ) {
					continue;
				}
				$row_url = isset( $row['gc_url']['url'] ) ? trim( (string) $row['gc_url']['url'] ) : '';
				$icon    = isset( $row['gc_icon'] ) && '' !== $row['gc_icon'] ? (string) $row['gc_icon'] : 'storefront';

				$out .= '<li style="--i:' . (int) $i . '"><a class="hsw-gc__drawer-sublink" href="' . esc_url( '' !== $row_url ? $row_url : '#' ) . '"' . $this->link_attrs( $row ) . '>'
					. HSW_Helpers::icon( $icon )
					. '<span>' . esc_html( $title ) . '</span></a></li>';
			}

			if ( $panel['foot'] ) {
				$foot_title = isset( $panel['foot']['gc_title'] ) ? trim( (string) $panel['foot']['gc_title'] ) : '';
				$foot_url   = isset( $panel['foot']['gc_url']['url'] ) ? trim( (string) $panel['foot']['gc_url']['url'] ) : '';
				if ( '' !== $foot_title ) {
					$out .= '<li style="--i:' . count( $panel['links'] ) . '"><a class="hsw-gc__drawer-sublink hsw-gc__drawer-sublink--accent" href="' . esc_url( '' !== $foot_url ? $foot_url : '#' ) . '"' . $this->link_attrs( $panel['foot'] ) . '>'
						. HSW_Helpers::icon( 'arrow_back' )
						. '<span>' . esc_html( $foot_title ) . '</span></a></li>';
				}
			}

			$out .= '</ul></details></li>';
		}

		$out .= '</ul></nav>';

		$out .= '<div class="hsw-gc__drawer-actions" style="--i:' . count( $items ) . '">';

		if ( ! isset( $s['show_account'] ) || 'yes' === $s['show_account'] ) {
			$account_text = isset( $s['account_text'] ) ? trim( (string) $s['account_text'] ) : '';
			$account_text = '' !== $account_text ? $account_text : __( 'ورود / حساب کاربری', 'hamishop-elementor-widgets' );
			$out         .= '<a class="hsw-gc__drawer-btn hsw-gc__drawer-btn--dark" href="' . esc_url( $this->account_url() ) . '">'
				. HSW_Helpers::icon( $this->icon_of( $s, 'account_icon', 'person' ) )
				. '<span>' . esc_html( $account_text ) . '</span></a>';
		}

		if ( ! isset( $s['show_cart'] ) || 'yes' === $s['show_cart'] ) {
			$out .= '<a class="hsw-gc__drawer-btn" href="' . esc_url( $this->cart_url() ) . '">'
				. HSW_Helpers::icon( $this->icon_of( $s, 'cart_icon', 'shopping_cart' ) )
				. '<span>' . esc_html__( 'سبد خرید', 'hamishop-elementor-widgets' ) . '</span></a>';
		}

		$out .= '</div></div></div>';

		return $out;
	}

	/**
	 * `target` / `rel` for a repeater row's URL control.
	 *
	 * @param array  $row Repeater row.
	 * @param string $key Control key.
	 * @return string
	 */
	protected function link_attrs( $row, $key = 'gc_url' ) {
		if ( empty( $row[ $key ] ) || ! is_array( $row[ $key ] ) ) {
			return '';
		}

		$link   = $row[ $key ];
		$attrs  = '';
		$rel    = array();

		if ( ! empty( $link['is_external'] ) ) {
			$attrs .= ' target="_blank"';
			$rel[]  = 'noopener';
		}
		if ( ! empty( $link['nofollow'] ) ) {
			$rel[] = 'nofollow';
		}
		if ( $rel ) {
			$attrs .= ' rel="' . esc_attr( implode( ' ', array_unique( $rel ) ) ) . '"';
		}

		return $attrs;
	}
}
