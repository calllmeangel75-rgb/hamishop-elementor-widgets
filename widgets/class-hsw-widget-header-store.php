<?php
/**
 * Widget: فروشگاهی header.
 *
 * Three layers: optional utility bar, search + brand + account/cart row, and
 * a category/navigation row with a WooCommerce category mega menu. The same
 * widget renders a compact mobile header and a native-menu drawer.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Header_Store extends HSW_Header_Base {

	public function get_name() {
		return 'hsw-header-store';
	}

	public function get_title() {
		return __( 'هدر فروشگاهی (HamiShop)', 'hamishop-elementor-widgets' );
	}

	public function get_icon() {
		return 'eicon-header';
	}

	public function get_keywords() {
		return array( 'header', 'menu', 'store', 'woocommerce', 'هدر', 'سربرگ', 'فروشگاهی', 'مگامنو', 'hamishop' );
	}

	protected function register_controls() {
		$this->content_brand();
		$this->content_nav();
		$this->content_search();
		$this->content_actions();

		/* Utility bar */
		$this->start_controls_section(
			'sec_hd_topbar',
			array(
				'label' => __( 'نوار اعلان بالای هدر', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_topbar',
			array(
				'label'   => __( 'نمایش نوار اعلان', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'notice_badge',
			array(
				'label'     => __( 'برچسب اعلان', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'ارسال فوری', 'hamishop-elementor-widgets' ),
				'condition' => array( 'show_topbar' => 'yes' ),
			)
		);

		$this->add_control(
			'notice_text',
			array(
				'label'       => __( 'متن اعلان', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'خرید مطمئن تجهیزات پزشکی با ضمانت اصالت کالا', 'hamishop-elementor-widgets' ),
				'label_block' => true,
				'condition'   => array( 'show_topbar' => 'yes' ),
			)
		);

		$this->add_control(
			'locale_label',
			array(
				'label'     => __( 'برچسب زبان / واحد پول', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'فارسی (تومان)', 'hamishop-elementor-widgets' ),
				'condition' => array( 'show_topbar' => 'yes' ),
			)
		);

		$this->add_control(
			'top_link_1_text',
			array(
				'label'     => __( 'لینک اول — متن', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'پیگیری سفارش', 'hamishop-elementor-widgets' ),
				'condition' => array( 'show_topbar' => 'yes' ),
			)
		);

		$this->add_control(
			'top_link_1_url',
			array(
				'label'     => __( 'لینک اول — آدرس', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::URL,
				'default'   => array( 'url' => $this->orders_url() ),
				'condition' => array( 'show_topbar' => 'yes' ),
			)
		);

		$this->add_control(
			'top_link_2_text',
			array(
				'label'     => __( 'لینک دوم — متن', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'تماس با ما', 'hamishop-elementor-widgets' ),
				'condition' => array( 'show_topbar' => 'yes' ),
			)
		);

		$this->add_control(
			'top_link_2_url',
			array(
				'label'     => __( 'لینک دوم — آدرس', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::URL,
				'default'   => array( 'url' => home_url( '/contact/' ) ),
				'condition' => array( 'show_topbar' => 'yes' ),
			)
		);

		$this->end_controls_section();

		/* Mega menu */
		$this->start_controls_section(
			'sec_hd_mega',
			array(
				'label' => __( 'دسته‌بندی و مگامنو', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_mega',
			array(
				'label'   => __( 'نمایش مگامنوی دسته‌بندی محصولات', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'mega_title',
			array(
				'label'     => __( 'عنوان دکمه دسته‌بندی', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'دسته‌بندی محصولات', 'hamishop-elementor-widgets' ),
				'condition' => array( 'show_mega' => 'yes' ),
			)
		);

		$this->add_control(
			'mega_trigger',
			array(
				'label'     => __( 'نحوه باز شدن مگامنو', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'hover',
				'options'   => array(
					'hover' => __( 'با هاور (مشابه دیجی‌کالا)', 'hamishop-elementor-widgets' ),
					'click' => __( 'با کلیک', 'hamishop-elementor-widgets' ),
				),
				'condition' => array( 'show_mega' => 'yes' ),
			)
		);

		$this->add_control(
			'mega_cat_ids',
			array(
				'label'       => __( 'انتخاب دسته‌بندی‌های سربرگ', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => class_exists( 'HSW_Header_Cascade' ) ? HSW_Header_Cascade::category_options() : array(),
				'default'     => array(),
				'label_block' => true,
				'description' => __( 'هر دسته یا زیردسته را می‌توانید انتخاب کنید. اگر خالی بماند، تعداد دسته‌های پیش‌فرض زیر نمایش داده می‌شود.', 'hamishop-elementor-widgets' ),
				'condition'   => array( 'show_mega' => 'yes' ),
			)
		);

		$this->add_control(
			'mega_cats',
			array(
				'label'       => __( 'تعداد دسته‌بندی پیش‌فرض', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => '5',
				'options'     => array(
					'4'  => __( '۴ دسته', 'hamishop-elementor-widgets' ),
					'5'  => __( '۵ دسته', 'hamishop-elementor-widgets' ),
					'6'  => __( '۶ دسته', 'hamishop-elementor-widgets' ),
					'8'  => __( '۸ دسته', 'hamishop-elementor-widgets' ),
					'10' => __( '۱۰ دسته', 'hamishop-elementor-widgets' ),
					'12' => __( '۱۲ دسته', 'hamishop-elementor-widgets' ),
				),
				'description' => __( 'فقط وقتی هیچ دسته‌ای در «انتخاب دسته‌بندی‌های سربرگ» انتخاب نشده باشد.', 'hamishop-elementor-widgets' ),
				'condition'   => array( 'show_mega' => 'yes' ),
			)
		);

		$this->add_control(
			'mega_depth',
			array(
				'label'       => __( 'حداکثر لایه دسته‌بندی', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => '3',
				'options'     => array(
					'1' => __( '۱ لایه', 'hamishop-elementor-widgets' ),
					'2' => __( '۲ لایه', 'hamishop-elementor-widgets' ),
					'3' => __( '۳ لایه', 'hamishop-elementor-widgets' ),
				),
				'description' => __( 'پنل‌ها به سمت چپ (در راست‌چین) آبشاری باز می‌شوند. هر لایه یک ستون جدید است.', 'hamishop-elementor-widgets' ),
				'condition'   => array( 'show_mega' => 'yes' ),
			)
		);

		$this->add_control(
			'mega_leaf_products',
			array(
				'label'       => __( 'نمایش محصولات در انتهای مسیر', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'description' => __( 'اگر خاموش باشد، منو روی خودِ دسته‌بندی تمام می‌شود و پنل محصولات باز نمی‌شود.', 'hamishop-elementor-widgets' ),
				'condition'   => array( 'show_mega' => 'yes' ),
			)
		);

		$this->add_control(
			'mega_products',
			array(
				'label'     => __( 'تعداد محصول در پنل نهایی', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '4',
				'options'   => array(
					'3' => __( '۳ محصول', 'hamishop-elementor-widgets' ),
					'4' => __( '۴ محصول', 'hamishop-elementor-widgets' ),
					'6' => __( '۶ محصول', 'hamishop-elementor-widgets' ),
				),
				'description' => __( 'محصولات فقط وقتی بارگذاری می‌شوند که کاربر به انتهای مسیر دسته‌بندی برسد.', 'hamishop-elementor-widgets' ),
				'condition' => array(
					'show_mega'         => 'yes',
					'mega_leaf_products' => 'yes',
				),
			)
		);

		$this->add_control(
			'nav_note',
			array(
				'label'     => __( 'متن اعتماد کنار منو', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'ضمانت اصالت کالا', 'hamishop-elementor-widgets' ),
				'separator' => 'before',
			)
		);

		$this->end_controls_section();

		$this->content_style( 'store' );
		$this->content_mega_style();

		/* Store-specific style */
		$this->start_controls_section(
			'sec_hd_store_style',
			array(
				'label' => __( 'نوار اعلان و ناوبری', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'topbar_bg',
			array(
				'label'     => __( 'پس‌زمینه نوار اعلان', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#0f172a',
				'selectors' => array(
					'{{WRAPPER}} .hsw-hd' => '--hsw-hd-topbar: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'nav_bg',
			array(
				'label'     => __( 'پس‌زمینه ردیف ناوبری', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#f8fafc',
				'selectors' => array(
					'{{WRAPPER}} .hsw-hd' => '--hsw-hd-navbg: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s             = $this->effective_settings();
		$uid           = $this->uid();
		$show_search   = ! isset( $s['show_search'] ) || 'yes' === $s['show_search'];
		$show_account  = ! isset( $s['show_account'] ) || 'yes' === $s['show_account'];
		$show_cart     = ! isset( $s['show_cart'] ) || 'yes' === $s['show_cart'];
		$show_nav      = ! isset( $s['show_nav'] ) || 'yes' === $s['show_nav'];
		$show_mega     = ! isset( $s['show_mega'] ) || 'yes' === $s['show_mega'];
		$search_id     = $uid . '-msearch';
		$drawer_id     = $uid . '-drawer';
		$mega_title    = isset( $s['mega_title'] ) && '' !== trim( (string) $s['mega_title'] ) ? trim( (string) $s['mega_title'] ) : __( 'دسته‌بندی محصولات', 'hamishop-elementor-widgets' );
		$nav_note      = isset( $s['nav_note'] ) ? trim( (string) $s['nav_note'] ) : '';

		echo $this->open_root( 'store', $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
		echo $this->topbar_html( $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.

		echo '<div class="hsw-hd__main"><div class="hsw-hd__maininner">';

		echo '<button class="hsw-hd__burger" type="button" data-hsw-hd-drawer-open aria-controls="' . esc_attr( $drawer_id ) . '" aria-expanded="false" aria-label="' . esc_attr__( 'باز کردن منو', 'hamishop-elementor-widgets' ) . '">' . HSW_Helpers::icon( $this->icon_of( $s, 'menu_icon', 'menu' ) ) . '</button>';

		echo $this->brand_html( $s, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.

		if ( $show_search ) {
			echo '<div class="hsw-hd__searchwrap">' . $this->search_form( $s ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '<div class="hsw-hd__actions">';
		if ( $show_search ) {
			echo '<button class="hsw-hd__iconbtn hsw-hd__mobilesearch" type="button" data-hsw-hd-search-toggle aria-controls="' . esc_attr( $search_id ) . '" aria-expanded="false" aria-label="' . esc_attr__( 'باز کردن جستجو', 'hamishop-elementor-widgets' ) . '">' . HSW_Helpers::icon( $this->icon_of( $s, 'search_icon', 'search' ) ) . '</button>';
		}
		if ( $show_account ) {
			echo $this->account_html( $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( $show_cart ) {
			echo $this->cart_html( $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div></div></div>';

		if ( $show_search ) {
			echo '<div class="hsw-hd__msearch" id="' . esc_attr( $search_id ) . '" data-hsw-hd-search-panel aria-hidden="true">' . $this->search_form( $s, true ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '<div class="hsw-hd__nav"><div class="hsw-hd__navinner">';
		if ( $show_mega ) {
			echo $this->category_nav_html( $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo '<a class="hsw-hd__catbtn" href="' . esc_url( $this->shop_url() ) . '">' . HSW_Helpers::icon( 'grid_view' ) . '<span>' . esc_html( $mega_title ) . '</span></a>';
		}

		if ( $show_nav ) {
			echo '<nav class="hsw-hd__navmenu" aria-label="' . esc_attr__( 'منوی اصلی', 'hamishop-elementor-widgets' ) . '">' . $this->menu_html( $s ) . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( '' !== $nav_note ) {
			echo '<span class="hsw-hd__navnote">' . HSW_Helpers::icon( 'verified' ) . '<span>' . esc_html( $nav_note ) . '</span></span>';
		}
		echo '</div></div>';

		echo $this->drawer_html( $s, 'store' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $show_cart ) {
			echo $this->cart_drawer_html( $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
	}
}
