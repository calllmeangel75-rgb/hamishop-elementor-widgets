<?php
/**
 * HamiShop widget manager.
 *
 * A small, native WordPress screen under the HamiShop menu that decides which
 * HamiShop widgets are registered in Elementor. General widgets (the composite
 * pages and the independent loop widgets) are enabled by default; page parts
 * stay out of the Elementor panel until an administrator explicitly enables
 * them. The filter only affects registration — composite widgets still render
 * their own internal parts.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

final class HSW_Widget_Manager {

	/** Option that stores the enabled widget slugs. */
	const OPTION = 'hsw_enabled_widgets';

	/** Admin page slug. */
	const PAGE = 'hamishop-widgets';

	/** @var array|null Runtime cache of enabled slugs. */
	private static $enabled_cache = null;

	/** @var array|null Runtime cache of the widget catalog. */
	private static $catalog_cache = null;

	/** @var array|null Runtime cache of every widget slug. */
	private static $all_cache = null;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 11 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_hsw_widgets_reset', array( $this, 'handle_reset' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( HSW_FILE ), array( $this, 'plugin_action_links' ) );
		$this->maybe_add_header_widgets();
		$this->maybe_add_post_widget();
	}

	/**
	 * One-time migration for sites that already saved the widget option.
	 *
	 * New general widgets are enabled by default on fresh installs. Existing
	 * sites have a saved array that predates the header group, so merge the
	 * two new slugs once. A later deliberate opt-out is preserved because the
	 * migration guard never runs again.
	 */
	private function maybe_add_header_widgets() {
		if ( get_option( 'hsw_header_widgets_migrated' ) ) {
			return;
		}

		$saved = get_option( self::OPTION, null );
		if ( is_array( $saved ) ) {
			$valid = array_keys( self::all_widgets() );
			$saved = array_values( array_unique( array_intersect( array_map( 'sanitize_key', $saved ), $valid ) ) );
			foreach ( array( 'hsw-header-store', 'hsw-header-minimal' ) as $slug ) {
				if ( isset( self::all_widgets()[ $slug ] ) && ! in_array( $slug, $saved, true ) ) {
					$saved[] = $slug;
				}
			}
			update_option( self::OPTION, $saved );
			self::$enabled_cache = null;
		}

		update_option( 'hsw_header_widgets_migrated', 1, false );
	}
	/**
	 * Enable the single-post template widget on sites that already saved the
	 * widget option (it shipped in 1.11.7, after most installs were configured).
	 * Same one-shot guard idea as the header migration: a later deliberate
	 * opt-out is preserved because this never runs again.
	 */
	private function maybe_add_post_widget() {
		if ( get_option( 'hsw_post_widget_migrated' ) ) {
			return;
		}

		$saved = get_option( self::OPTION, null );
		if ( is_array( $saved ) ) {
			$valid = array_keys( self::all_widgets() );
			$saved = array_values( array_unique( array_intersect( array_map( 'sanitize_key', $saved ), $valid ) ) );
			if ( isset( self::all_widgets()['hsw-post-page'] ) && ! in_array( 'hsw-post-page', $saved, true ) ) {
				$saved[] = 'hsw-post-page';
			}
			update_option( self::OPTION, $saved );
			self::$enabled_cache = null;
		}

		update_option( 'hsw_post_widget_migrated', 1, false );
	}

	/* ---------------------------------------------------------------------
	 * Catalog
	 * ------------------------------------------------------------------ */

	/**
	 * The single source of truth for the manager UI.
	 *
	 * «general» widgets are registered by default. «parts» are the individual
	 * page sections: useful when someone builds a page by hand, but hidden from
	 * the panel by default so the Elementor sidebar stays short and calm.
	 *
	 * @return array
	 */
	public static function catalog() {
		if ( null !== self::$catalog_cache ) {
			return self::$catalog_cache;
		}

		self::$catalog_cache = array(
			'header'  => array(
				'label'       => __( 'هدر و ناوبری', 'hamishop-elementor-widgets' ),
				'category'    => 'hamishop-header',
				'icon'        => 'dashicons-menu-alt3',
				'accent'      => '#0f766e',
				'description' => __( 'دو هدر کامل المنتور با منوی واقعی وردپرس، جستجوی محصول ووکامرس، سبد خرید و حساب کاربری. هر دو پیش‌فرض فعال‌اند.', 'hamishop-elementor-widgets' ),
				'general'     => array(
					'hsw-header-store'   => array(
						'label' => __( 'هدر فروشگاهی', 'hamishop-elementor-widgets' ),
						'desc'  => __( 'نوار اعلان، جستجوی محصول، سبد خرید و مگامنوی دسته‌بندی.', 'hamishop-elementor-widgets' ),
					),
					'hsw-header-minimal' => array(
						'label' => __( 'هدر مینیمال شیشه‌ای', 'hamishop-elementor-widgets' ),
						'desc'  => __( 'پیل شیشه‌ای شناور، منوی واقعی و پنل جستجوی سبک.', 'hamishop-elementor-widgets' ),
					),
					'hsw-footer-sticky'  => array(
						'label' => __( 'فوتر چسبان (نوار پایین موبایل)', 'hamishop-elementor-widgets' ),
						'desc'  => __( 'پنج لینک سریع: خانه، فروشگاه، سبد خرید، علاقه‌مندی‌ها و حساب کاربری. فقط در موبایل/تبلت نمایش داده می‌شود.', 'hamishop-elementor-widgets' ),
					),
				),
				'parts'       => array(),
			),
			'loop'    => array(
				'label'       => __( 'لوپ محصولات', 'hamishop-elementor-widgets' ),
				'category'    => 'hamishop-loop',
				'icon'        => 'dashicons-grid-view',
				'accent'      => '#1d4ed8',
				'description' => __( 'ویجت‌های مستقل صفحه اصلی برای نمایش پیشنهاد شگفت‌انگیز، پرفروش‌ترین‌ها و محصولات منتخب. هر سه پیش‌فرض فعال‌اند.', 'hamishop-elementor-widgets' ),
				'general'     => array(
					'hsw-wonder-deals' => array(
						'label' => __( 'پیشنهاد شگفت‌انگیز', 'hamishop-elementor-widgets' ),
						'desc'  => __( 'پیشنهاد روز با شمارش معکوس و کاروسل تخفیف.', 'hamishop-elementor-widgets' ),
					),
					'hsw-best-sellers' => array(
						'label' => __( 'پرفروش‌ترین‌ها', 'hamishop-elementor-widgets' ),
						'desc'  => __( 'کاروسل پرفروش‌ترین‌ها با رتبه‌بندی و دکمه خرید.', 'hamishop-elementor-widgets' ),
					),
					'hsw-popular'      => array(
						'label' => __( 'محبوب‌ترین‌ها / منتخب', 'hamishop-elementor-widgets' ),
						'desc'  => __( 'تب‌های محبوب، جدید و تخفیف‌دار با محصولات منتخب.', 'hamishop-elementor-widgets' ),
					),
				),
				'parts'       => array(),
			),
			'single'  => array(
				'label'       => __( 'صفحه محصول', 'hamishop-elementor-widgets' ),
				'category'    => 'hamishop-single',
				'icon'        => 'dashicons-products',
				'accent'      => '#0e7490',
				'description' => __( 'ویجت جامع، کل صفحه محصول را با یک ویجت می‌سازد. بخش‌های مستقل در پایین قرار دارند و فقط با فعال‌سازی در پنل المنتور دیده می‌شوند.', 'hamishop-elementor-widgets' ),
				'general'     => array(
					'hsw-product-page' => array(
						'label' => __( 'صفحه کامل محصول', 'hamishop-elementor-widgets' ),
						'desc'  => __( 'گالری، خرید، تب‌ها و نوار چسبان در یک ویجت جامع.', 'hamishop-elementor-widgets' ),
					),
				),
				'parts'       => array(
					array(
						'label' => __( 'بخش‌های صفحه محصول', 'hamishop-elementor-widgets' ),
						'items' => array(
							'hsw-product-breadcrumb'  => array(
								'label' => __( 'مسیر راهنما (بردکرامب)', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'مسیر خانه، فروشگاه و دسته‌بندی محصول.', 'hamishop-elementor-widgets' ),
							),
							'hsw-product-gallery'     => array(
								'label' => __( 'گالری تصاویر', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'گالری با زوم، بندانگشتی و برچسب اصالت.', 'hamishop-elementor-widgets' ),
							),
							'hsw-product-title'       => array(
								'label' => __( 'عنوان، امتیاز و متا', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'عنوان محصول، امتیاز، دیدگاه و وضعیت موجودی.', 'hamishop-elementor-widgets' ),
							),
							'hsw-product-variants'    => array(
								'label' => __( 'انتخاب رنگ و حافظه', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'انتخاب ویژگی‌ها با اتصال زنده به ووکامرس.', 'hamishop-elementor-widgets' ),
							),
							'hsw-product-features'    => array(
								'label' => __( 'ویژگی‌های شاخص', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'ویژگی‌های کلیدی در نسخه دسکتاپ و موبایل.', 'hamishop-elementor-widgets' ),
							),
							'hsw-product-purchase'    => array(
								'label' => __( 'جعبه خرید و قیمت', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'قیمت، تخفیف، تعداد و دکمه افزودن به سبد.', 'hamishop-elementor-widgets' ),
							),
							'hsw-product-trust'       => array(
								'label' => __( 'سیگنال‌های اعتماد', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'گارانتی، ارسال و نشان‌های اطمینان.', 'hamishop-elementor-widgets' ),
							),
							'hsw-product-tabs'        => array(
								'label' => __( 'تب‌های محصول (دسکتاپ)', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'توضیحات، مشخصات فنی و دیدگاه‌ها.', 'hamishop-elementor-widgets' ),
							),
							'hsw-product-accordions'  => array(
								'label' => __( 'آکاردئون‌ها (موبایل)', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'نسخه موبایل مشخصات، گارانتی و دیدگاه‌ها.', 'hamishop-elementor-widgets' ),
							),
							'hsw-product-related'     => array(
								'label' => __( 'محصولات مرتبط', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'کاروسل محصولات مرتبط، هم‌دسته، تشویقی یا پرفروش.', 'hamishop-elementor-widgets' ),
							),
							'hsw-product-sticky'      => array(
								'label' => __( 'نوار خرید چسبان', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'نوار همیشه‌درمعرض قیمت و افزودن به سبد.', 'hamishop-elementor-widgets' ),
							),
						),
					),
				),
			),
			'flow'    => array(
				'label'       => __( 'سبد خرید و پرداخت', 'hamishop-elementor-widgets' ),
				'category'    => 'hamishop-cart',
				'icon'        => 'dashicons-cart',
				'accent'      => '#a21caf',
				'description' => __( 'صفحه‌های سبد خرید، تسویه حساب، پرداخت و تکمیل سفارش. ویجت جامع هر صفحه فعال است؛ بخش‌های ریز فقط در صورت نیاز نمایش داده می‌شوند.', 'hamishop-elementor-widgets' ),
				'general'     => array(
					'hsw-cart-page'     => array(
						'label' => __( 'صفحه کامل سبد خرید', 'hamishop-elementor-widgets' ),
						'desc'  => __( 'سبد خرید کامل با مراحل، بنر ارسال و خلاصه فاکتور.', 'hamishop-elementor-widgets' ),
					),
					'hsw-checkout-page' => array(
						'label' => __( 'صفحه کامل تسویه حساب', 'hamishop-elementor-widgets' ),
						'desc'  => __( 'فرم آدرس، روش ارسال، زمان تحویل و خلاصه پرداخت.', 'hamishop-elementor-widgets' ),
					),
					'hsw-pay-page'      => array(
						'label' => __( 'صفحه کامل پرداخت', 'hamishop-elementor-widgets' ),
						'desc'  => __( 'روش‌های پرداخت، بازبینی سفارش و پرداخت نهایی.', 'hamishop-elementor-widgets' ),
					),
					'hsw-complete-page' => array(
						'label' => __( 'صفحه کامل تکمیل خرید', 'hamishop-elementor-widgets' ),
						'desc'  => __( 'صفحه تشکر با جزئیات سفارش، تحویل و مراحل بعدی.', 'hamishop-elementor-widgets' ),
					),
				),
				'parts'       => array(
					array(
						'label' => __( 'سبد خرید', 'hamishop-elementor-widgets' ),
						'items' => array(
							'hsw-cart-top'     => array(
								'label' => __( 'هدر و مراحل سبد خرید', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'سربرگ سبد و نشانگر مراحل خرید.', 'hamishop-elementor-widgets' ),
							),
							'hsw-cart-banner'  => array(
								'label' => __( 'بنر ارسال رایگان', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'پیشرفت سبد تا ارسال رایگان.', 'hamishop-elementor-widgets' ),
							),
							'hsw-cart-items'   => array(
								'label' => __( 'اقلام سبد خرید', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'لیست محصولات با تعداد، حذف و تصویر.', 'hamishop-elementor-widgets' ),
							),
							'hsw-cart-coupon'  => array(
								'label' => __( 'کد تخفیف سبد', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'فرم اعمال کوپن روی سبد خرید.', 'hamishop-elementor-widgets' ),
							),
							'hsw-cart-summary' => array(
								'label' => __( 'خلاصه فاکتور سبد', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'جمع، ارسال و دکمه ادامه خرید.', 'hamishop-elementor-widgets' ),
							),
						),
					),
					array(
						'label' => __( 'تسویه حساب', 'hamishop-elementor-widgets' ),
						'items' => array(
							'hsw-checkout-top'      => array(
								'label' => __( 'هدر و مراحل تسویه حساب', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'سربرگ و مراحل اطلاعات ارسال.', 'hamishop-elementor-widgets' ),
							),
							'hsw-checkout-address'  => array(
								'label' => __( 'آدرس تحویل سفارش', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'فرم اطلاعات مشتری و آدرس تحویل.', 'hamishop-elementor-widgets' ),
							),
							'hsw-checkout-shipping' => array(
								'label' => __( 'روش ارسال', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'انتخاب روش ارسال و هزینه آن.', 'hamishop-elementor-widgets' ),
							),
							'hsw-checkout-schedule' => array(
								'label' => __( 'زمان و روز تحویل', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'انتخاب روز و بازه زمانی تحویل.', 'hamishop-elementor-widgets' ),
							),
							'hsw-checkout-note'     => array(
								'label' => __( 'یادداشت سفارش و فاکتور', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'یادداشت مشتری و درخواست فاکتور.', 'hamishop-elementor-widgets' ),
							),
							'hsw-checkout-items'    => array(
								'label' => __( 'اقلام مرسوله', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'محصولات سفارش و تعداد هر کدام.', 'hamishop-elementor-widgets' ),
							),
							'hsw-checkout-summary'  => array(
								'label' => __( 'خلاصه پرداخت', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'جمع فاکتور و اطلاعات نهایی ارسال.', 'hamishop-elementor-widgets' ),
							),
						),
					),
					array(
						'label' => __( 'پرداخت', 'hamishop-elementor-widgets' ),
						'items' => array(
							'hsw-pay-top'     => array(
								'label' => __( 'هدر و مراحل پرداخت', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'سربرگ پرداخت و مراحل نهایی خرید.', 'hamishop-elementor-widgets' ),
							),
							'hsw-pay-recall'  => array(
								'label' => __( 'مشخصات تحویل سفارش', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'بازبینی آدرس و اطلاعات تحویل.', 'hamishop-elementor-widgets' ),
							),
							'hsw-pay-methods' => array(
								'label' => __( 'روش‌های پرداخت', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'درگاه‌های پرداخت و پرداخت با کیف پول.', 'hamishop-elementor-widgets' ),
							),
							'hsw-pay-coupon'  => array(
								'label' => __( 'کد تخفیف و کارت هدیه', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'اعمال کوپن یا کارت هدیه.', 'hamishop-elementor-widgets' ),
							),
							'hsw-pay-summary' => array(
								'label' => __( 'خلاصه فاکتور و پرداخت نهایی', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'جمع نهایی و دکمه پرداخت.', 'hamishop-elementor-widgets' ),
							),
						),
					),
					array(
						'label' => __( 'تکمیل خرید', 'hamishop-elementor-widgets' ),
						'items' => array(
							'hsw-complete-hero'     => array(
								'label' => __( 'سربرگ تکمیل خرید', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'پیام تشکر و شماره سفارش.', 'hamishop-elementor-widgets' ),
							),
							'hsw-complete-order'    => array(
								'label' => __( 'جزئیات سفارش', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'اقلام، مبلغ و وضعیت سفارش.', 'hamishop-elementor-widgets' ),
							),
							'hsw-complete-delivery' => array(
								'label' => __( 'اطلاعات تحویل', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'آدرس و روش تحویل سفارش.', 'hamishop-elementor-widgets' ),
							),
							'hsw-complete-steps'    => array(
								'label' => __( 'مراحل بعدی خرید', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'پیگیری سفارش و اقدام‌های بعدی.', 'hamishop-elementor-widgets' ),
							),
							'hsw-complete-actions'  => array(
								'label' => __( 'دکمه‌های تکمیل خرید', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'بازگشت به فروشگاه و مشاهده سفارش‌ها.', 'hamishop-elementor-widgets' ),
							),
						),
					),
				),
			),
			'archive' => array(
				'label'       => __( 'آرشیو محصولات', 'hamishop-elementor-widgets' ),
				'category'    => 'hamishop-archive',
				'icon'        => 'dashicons-filter',
				'accent'      => '#b45309',
				'description' => __( 'صفحه کامل آرشیو با هدر، فیلتر، ابزار مرتب‌سازی، گرید و صفحه‌بندی. بخش‌های مستقل برای ساخت دستی آرشیو در پایین قرار دارند.', 'hamishop-elementor-widgets' ),
				'general'     => array(
					'hsw-archive-page' => array(
						'label' => __( 'صفحه کامل آرشیو', 'hamishop-elementor-widgets' ),
						'desc'  => __( 'آرشیو یکپارچه با فیلتر، گرید و صفحه‌بندی.', 'hamishop-elementor-widgets' ),
					),
				),
				'parts'       => array(
					array(
						'label' => __( 'بخش‌های آرشیو', 'hamishop-elementor-widgets' ),
						'items' => array(
							'hsw-archive-breadcrumb' => array(
								'label' => __( 'مسیر راهنما آرشیو', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'مسیر فروشگاه و دسته‌بندی جاری.', 'hamishop-elementor-widgets' ),
							),
							'hsw-archive-header'     => array(
								'label' => __( 'هدر و توضیحات آرشیو', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'عنوان، توضیح و تصویر دسته‌بندی.', 'hamishop-elementor-widgets' ),
							),
							'hsw-archive-filters'    => array(
								'label' => __( 'فیلترهای پیشرفته', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'فیلتر ویژگی‌ها، برند و قیمت.', 'hamishop-elementor-widgets' ),
							),
							'hsw-archive-toolbar'    => array(
								'label' => __( 'نوار مرتب‌سازی و ابزار', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'مرتب‌سازی، تعداد نمایش و نمایش شبکه‌ای.', 'hamishop-elementor-widgets' ),
							),
							'hsw-archive-grid'       => array(
								'label' => __( 'گرید محصولات آرشیو', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'کارت‌های محصول هماهنگ با بقیه ویجت‌ها.', 'hamishop-elementor-widgets' ),
							),
							'hsw-archive-pagination' => array(
								'label' => __( 'صفحه‌بندی آرشیو', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'صفحه‌بندی و بارگذاری بیشتر.', 'hamishop-elementor-widgets' ),
							),
							'hsw-archive-guide'      => array(
								'label' => __( 'راهنما و سوالات متداول', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'راهنمای خرید و پرسش‌های پرتکرار.', 'hamishop-elementor-widgets' ),
							),
						),
					),
				),
			),
			'blog'    => array(
				'label'       => __( 'مجله و وبلاگ', 'hamishop-elementor-widgets' ),
				'category'    => 'hamishop-blog',
				'icon'        => 'dashicons-admin-post',
				'accent'      => '#be123c',
				'description' => __( 'صفحه کامل مجله با مقاله ویژه، شبکه مقالات، خبرنامه و برچسب‌ها. ویجت جامع پیش‌فرض فعال است و بخش‌های مستقل در پایین قرار دارند.', 'hamishop-elementor-widgets' ),
				'general'     => array(
					'hsw-blog-page' => array(
						'label' => __( 'صفحه کامل مجله', 'hamishop-elementor-widgets' ),
						'desc'  => __( 'مجله کامل با مقاله ویژه، شبکه و خبرنامه.', 'hamishop-elementor-widgets' ),
					),
					'hsw-post-page' => array(
						'label' => __( 'صفحه کامل نوشته', 'hamishop-elementor-widgets' ),
						'desc'  => __( 'قالب تکی مقاله: سربرگ، فهرست مطالب، بدنه، کارت محصول، دیدگاه‌ها و مقالات مرتبط.', 'hamishop-elementor-widgets' ),
					),
				),
				'parts'       => array(
					array(
						'label' => __( 'بخش‌های مجله', 'hamishop-elementor-widgets' ),
						'items' => array(
							'hsw-blog-hero'       => array(
								'label' => __( 'سربرگ مجله', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'عنوان، جستجو و دسته‌های مجله.', 'hamishop-elementor-widgets' ),
							),
							'hsw-blog-tabs'       => array(
								'label' => __( 'تب دسته‌ها و مرتب‌سازی', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'فیلتر دسته‌ها و ترتیب مقالات.', 'hamishop-elementor-widgets' ),
							),
							'hsw-blog-featured'   => array(
								'label' => __( 'مقاله ویژه', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'مقاله شاخص با تصویر بزرگ.', 'hamishop-elementor-widgets' ),
							),
							'hsw-blog-grid'       => array(
								'label' => __( 'شبکه مقالات', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'کارت‌های مقاله با تصویر و دسته.', 'hamishop-elementor-widgets' ),
							),
							'hsw-blog-popular'    => array(
								'label' => __( 'پربازدیدترین‌ها', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'فهرست پربازدیدترین مقالات.', 'hamishop-elementor-widgets' ),
							),
							'hsw-blog-newsletter' => array(
								'label' => __( 'خبرنامه', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'فرم عضویت در خبرنامه مجله.', 'hamishop-elementor-widgets' ),
							),
							'hsw-blog-tags'       => array(
								'label' => __( 'برچسب‌های داغ', 'hamishop-elementor-widgets' ),
								'desc'  => __( 'موضوعات و برچسب‌های پرطرفدار.', 'hamishop-elementor-widgets' ),
							),
						),
					),
				),
			),
			'account' => array(
				'label'       => __( 'حساب کاربری', 'hamishop-elementor-widgets' ),
				'category'    => 'hamishop-account',
				'icon'        => 'dashicons-admin-users',
				'accent'      => '#047857',
				'description' => __( 'ویجت کامل پنل حساب کاربری HamiShop. تنظیمات ظاهر، منو، پشتیبانی و اعلان‌های آن در منوی «پنل حساب کاربری» است.', 'hamishop-elementor-widgets' ),
				'general'     => array(
					'hsw-account' => array(
						'label' => __( 'پنل حساب کاربری HamiShop', 'hamishop-elementor-widgets' ),
						'desc'  => __( 'ورود، سفارش‌ها، پشتیبانی، کیف پول و علاقه‌مندی‌ها.', 'hamishop-elementor-widgets' ),
					),
				),
				'parts'       => array(),
			),
		);

		return self::$catalog_cache;
	}

	/**
	 * Flattened widget map: slug => label/desc/kind/group.
	 *
	 * @return array
	 */
	public static function all_widgets() {
		if ( null !== self::$all_cache ) {
			return self::$all_cache;
		}

		$all = array();
		foreach ( self::catalog() as $group_key => $group ) {
			foreach ( $group['general'] as $slug => $item ) {
				$all[ $slug ] = array_merge(
					$item,
					array(
						'kind'  => 'general',
						'group' => $group_key,
					)
				);
			}
			foreach ( $group['parts'] as $subgroup ) {
				foreach ( $subgroup['items'] as $slug => $item ) {
					$all[ $slug ] = array_merge(
						$item,
						array(
							'kind'  => 'part',
							'group' => $group_key,
						)
					);
				}
			}
		}

		self::$all_cache = $all;
		return self::$all_cache;
	}

	/**
	 * Default enabled set: all general widgets, no page parts.
	 *
	 * @return array
	 */
	public static function defaults() {
		$defaults = array();
		foreach ( self::catalog() as $group ) {
			foreach ( array_keys( $group['general'] ) as $slug ) {
				$defaults[] = $slug;
			}
		}
		return array_values( array_unique( $defaults ) );
	}

	/**
	 * Sanitize the saved option against the catalog.
	 *
	 * @param mixed $value Raw option value.
	 * @return array
	 */
	public static function sanitize_enabled( $value ) {
		$valid = array_keys( self::all_widgets() );
		$value = is_array( $value ) ? $value : array();
		$value = array_filter( $value, 'is_string' );
		$value = array_map( 'sanitize_key', $value );
		$value = array_values( array_unique( array_intersect( $value, $valid ) ) );

		self::$enabled_cache = $value;
		return $value;
	}

	/**
	 * Enabled slugs. Unknown/new widgets stay enabled so a plugin update never
	 * makes a freshly added widget disappear before an admin can manage it.
	 *
	 * @return array
	 */
	public static function enabled() {
		if ( null !== self::$enabled_cache ) {
			return self::$enabled_cache;
		}

		$saved = get_option( self::OPTION, null );
		if ( ! is_array( $saved ) ) {
			$saved = self::defaults();
		}

		self::$enabled_cache = self::sanitize_enabled( $saved );
		return self::$enabled_cache;
	}

	/**
	 * Whether a widget should be registered in Elementor.
	 *
	 * @param string $slug Widget slug.
	 * @return bool
	 */
	public static function is_enabled( $slug ) {
		$slug = sanitize_key( $slug );
		if ( ! isset( self::all_widgets()[ $slug ] ) ) {
			return true;
		}
		return in_array( $slug, self::enabled(), true );
	}

	/**
	 * Whether a category still contains at least one enabled widget.
	 *
	 * @param string $category Elementor category slug.
	 * @return bool
	 */
	public static function category_has_enabled( $category ) {
		foreach ( self::all_widgets() as $slug => $widget ) {
			if ( self::catalog()[ $widget['group'] ]['category'] === $category && self::is_enabled( $slug ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Enabled / total counts for a group.
	 *
	 * @param string $group_key Group key.
	 * @return array
	 */
	private static function fa_num( $number ) {
		return strtr( (string) $number, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
	}

	public static function group_counts( $group_key ) {
		$group   = self::catalog()[ $group_key ] ?? array();
		$count   = 0;
		$enabled = 0;
		foreach ( $group['general'] as $slug => $item ) {
			++$count;
			$enabled += self::is_enabled( $slug ) ? 1 : 0;
		}
		foreach ( $group['parts'] as $subgroup ) {
			foreach ( $subgroup['items'] as $slug => $item ) {
				++$count;
				$enabled += self::is_enabled( $slug ) ? 1 : 0;
			}
		}
		return array( 'enabled' => $enabled, 'total' => $count );
	}

	/* ---------------------------------------------------------------------
	 * Admin screen
	 * ------------------------------------------------------------------ */

	public function menu() {
		add_submenu_page(
			'hamishop',
			__( 'کنترل ویجت‌ها', 'hamishop-elementor-widgets' ),
			__( 'کنترل ویجت‌ها', 'hamishop-elementor-widgets' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);

		/* Account first, then support tickets, then this screen. The first
		 * submenu is also the top-level HamiShop landing link. */
		global $submenu;
		if ( ! empty( $submenu['hamishop'] ) && is_array( $submenu['hamishop'] ) ) {
			$account = null;
			$rest    = array();
			foreach ( $submenu['hamishop'] as $item ) {
				if ( isset( $item[2] ) && 'hamishop-account' === $item[2] ) {
					$account = $item;
				} else {
					$rest[] = $item;
				}
			}
			if ( $account ) {
				$submenu['hamishop'] = array_merge( array( $account ), $rest );
			}
		}
	}


	/**
	 * `options.php` in the same directory as the current admin screen.
	 *
	 * The dashboard can live behind a custom path (HamiShop → امنیت): a
	 * relative `options.php` then resolves outside wp-admin and WordPress
	 * redirects it to a URL the security guard answers with 404, so nothing is
	 * saved. Deriving the action from the current request keeps the POST on the
	 * path that actually works, with or without the custom slug.
	 *
	 * @return string
	 */
	protected function options_form_action() {
		/*
		 * HamiShop → امنیت can move the dashboard to a custom slug. The guard
		 * rewrites REQUEST_URI to /wp-admin/… before the page renders, and a POST
		 * there is answered with 404, so the form has to use the public path.
		 */
		if ( class_exists( 'HSW_Security' ) ) {
			$security = HSW_Security::get();
			$slug     = isset( $security['admin_slug'] ) ? trim( (string) $security['admin_slug'] ) : '';
			if ( '' !== $slug ) {
				return home_url( '/' . $slug . '/options.php' );
			}
		}
		return admin_url( 'options.php' );
	}


	public function register_settings() {
		register_setting(
			'hsw_widgets_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_enabled' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'hsw_widgets_group',
			'hsw_oos_last',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_yes_no' ),
				'default'           => 'yes',
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'hsw_widgets_group',
			HSW_Free_Shipping::OPTION,
			array(
				'type'              => 'number',
				'sanitize_callback' => array( __CLASS__, 'sanitize_free_shipping' ),
				'default'           => 0,
				'show_in_rest'      => false,
			)
		);

		foreach ( array( 'hsw_404_title', 'hsw_404_text', 'hsw_404_cta', 'hsw_404_home', 'hsw_404_search_ph' ) as $hsw_404_key ) {
			register_setting(
				'hsw_widgets_group',
				$hsw_404_key,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => '',
					'show_in_rest'      => false,
				)
			);
		}

		register_setting(
			'hsw_widgets_group',
			'hsw_custom_404_enabled',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_yes_no' ),
				'default'           => 'yes',
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'hsw_widgets_group',
			'hsw_add_feedback',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_feedback' ),
				'default'           => array(
					'modal_archive' => 'yes',
					'modal_product' => 'no',
				),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * yes/no checkbox helper.
	 *
	 * @param mixed $input Raw input.
	 * @return string
	 */
	public static function sanitize_yes_no( $input ) {
		return ( 'yes' === $input ) ? 'yes' : 'no';
	}

	/**
	 * Free-shipping threshold: a positive amount or 0 for "off".
	 *
	 * @param mixed $input Raw input.
	 * @return float
	 */
	public static function sanitize_free_shipping( $input ) {
		$value = (float) str_replace( array( ',', '٬', ' ' ), '', (string) $input );

		return $value > 0 ? $value : 0;
	}

	/**
	 * Two checkboxes, each stored as `yes` or `no`.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize_feedback( $input ) {
		$input = is_array( $input ) ? $input : array();
		$modes = array( 'modal', 'bar', 'off' );

		$archive = isset( $input['archive'] ) && in_array( $input['archive'], $modes, true ) ? $input['archive'] : 'modal';
		$product = isset( $input['product'] ) && in_array( $input['product'], $modes, true ) ? $input['product'] : 'off';

		return array(
			'archive'       => $archive,
			'product'       => $product,
			/* Legacy mirrors, so older templates keep working. */
			'modal_archive' => 'off' !== $archive ? 'yes' : 'no',
			'modal_product' => 'off' !== $product ? 'yes' : 'no',
			'style'         => 'bar' === $archive ? 'toast' : 'modal',
		);
	}

	public function plugin_action_links( $links ) {
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'کنترل ویجت‌ها', 'hamishop-elementor-widgets' ) . '</a>';
		return $links;
	}

	/**
	 * Reset the option back to the general-only default set.
	 */
	public function handle_reset() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی لازم برای این کار را ندارید.', 'hamishop-elementor-widgets' ) );
		}
		check_admin_referer( 'hsw_widgets_reset' );

		update_option( self::OPTION, self::defaults() );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => self::PAGE,
					'hsw-reset' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$catalog   = self::catalog();
		$all       = self::all_widgets();
		$enabled   = self::enabled();
		$total     = count( $all );
		$active    = count( $enabled );
		$defaults  = self::defaults();
		$reset_url = wp_nonce_url(
			add_query_arg( array( 'action' => 'hsw_widgets_reset' ), admin_url( 'admin-post.php' ) ),
			'hsw_widgets_reset'
		);

		$this->page_styles();
		?>
		<div class="wrap hsw-wm" dir="rtl">
			<div class="hsw-wm-head">
				<div class="hsw-wm-brand">
					<span class="hsw-wm-logo">HamiShop</span>
					<div>
						<h1><?php esc_html_e( 'کنترل ویجت‌ها', 'hamishop-elementor-widgets' ); ?></h1>
						<p><?php esc_html_e( 'پنل المنتور را سبک نگه دارید؛ فقط ویجت‌هایی را فعال کنید که واقعاً لازم دارید.', 'hamishop-elementor-widgets' ); ?></p>
					</div>
				</div>
				<div class="hsw-wm-stats" aria-live="polite">
					<span><strong data-hsw-active><?php echo esc_html( self::fa_num( $active ) ); ?></strong> <?php esc_html_e( 'فعال', 'hamishop-elementor-widgets' ); ?></span>
					<span><strong><?php echo esc_html( self::fa_num( $total ) ); ?></strong> <?php esc_html_e( 'ویجت', 'hamishop-elementor-widgets' ); ?></span>
					<span><strong><?php echo esc_html( self::fa_num( count( $defaults ) ) ); ?></strong> <?php esc_html_e( 'پیش‌فرض', 'hamishop-elementor-widgets' ); ?></span>
				</div>
			</div>

			<?php settings_errors(); ?>
			<?php if ( isset( $_GET['hsw-reset'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag after a nonce-protected action. ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'ویجت‌ها به حالت پیش‌فرض برگشتند: ویجت‌های اصلی فعال و بخش‌های ریز غیرفعال شدند.', 'hamishop-elementor-widgets' ); ?></p></div>
			<?php endif; ?>

			<div class="hsw-wm-layout">
				<aside class="hsw-wm-side">
					<nav class="hsw-wm-nav" aria-label="<?php esc_attr_e( 'گروه‌های ویجت', 'hamishop-elementor-widgets' ); ?>">
						<span class="hsw-wm-navtitle"><?php esc_html_e( 'گروه‌ها', 'hamishop-elementor-widgets' ); ?></span>
						<?php foreach ( $catalog as $group_key => $group ) : ?>
							<?php $counts = self::group_counts( $group_key ); ?>
							<a href="#hsw-group-<?php echo esc_attr( $group_key ); ?>" data-hsw-nav="<?php echo esc_attr( $group_key ); ?>">
								<span class="dashicons <?php echo esc_attr( $group['icon'] ); ?>" aria-hidden="true"></span>
								<span><?php echo esc_html( $group['label'] ); ?></span>
								<em data-hsw-nav-count><?php echo esc_html( self::fa_num( $counts['enabled'] ) . '/' . self::fa_num( $counts['total'] ) ); ?></em>
							</a>
						<?php endforeach; ?>
					</nav>

					<nav class="hsw-wm-nav hsw-wm-nav--settings" aria-label="<?php esc_attr_e( 'تنظیمات فروشگاه', 'hamishop-elementor-widgets' ); ?>">
						<span class="hsw-wm-navtitle"><?php esc_html_e( 'تنظیمات فروشگاه', 'hamishop-elementor-widgets' ); ?></span>
						<a href="#hsw-group-feedback"><span class="dashicons dashicons-cart" aria-hidden="true"></span><span><?php esc_html_e( 'رفتار افزودن به سبد', 'hamishop-elementor-widgets' ); ?></span></a>
						<a href="#hsw-group-catalog"><span class="dashicons dashicons-sort" aria-hidden="true"></span><span><?php esc_html_e( 'ترتیب محصولات', 'hamishop-elementor-widgets' ); ?></span></a>
						<a href="#hsw-group-shipping"><span class="dashicons dashicons-car" aria-hidden="true"></span><span><?php esc_html_e( 'ارسال رایگان', 'hamishop-elementor-widgets' ); ?></span></a>
						<a href="#hsw-group-404"><span class="dashicons dashicons-warning" aria-hidden="true"></span><span><?php esc_html_e( 'صفحهٔ ۴۰۴', 'hamishop-elementor-widgets' ); ?></span></a>
					</nav>

					<div class="hsw-wm-tip">
						<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
						<h2><?php esc_html_e( 'چطور انتخاب کنیم؟', 'hamishop-elementor-widgets' ); ?></h2>
						<p><?php esc_html_e( 'ویجت‌های اصلی و جامع، یک صفحه کامل را با یک ویجت می‌سازند و برای ساخت سریع بهترین انتخاب‌اند. بخش‌های ریز فقط وقتی لازم‌اند که بخواهید صفحه‌ای را دستی و بخش‌به‌بخش بچینید.', 'hamishop-elementor-widgets' ); ?></p>
					</div>

					<div class="hsw-wm-tip hsw-wm-tip--warn">
						<span class="dashicons dashicons-warning" aria-hidden="true"></span>
						<h2><?php esc_html_e( 'قبل از غیرفعال کردن', 'hamishop-elementor-widgets' ); ?></h2>
						<p><?php esc_html_e( 'اگر ویجتی را در یک صفحهٔ ساخته‌شده استفاده کرده‌اید، غیرفعال کردن آن می‌تواند آن بخش را از صفحه حذف کند. ویجت‌های جامع، بخش‌های داخلی خود را مستقل از این تنظیم نمایش می‌دهند.', 'hamishop-elementor-widgets' ); ?></p>
					</div>

					<div class="hsw-wm-reset">
						<a class="button" href="<?php echo esc_url( $reset_url ); ?>" data-hsw-confirm="<?php esc_attr_e( 'همهٔ انتخاب‌ها به حالت پیش‌فرض برگردند؟ بخش‌های ریز غیرفعال می‌شوند.', 'hamishop-elementor-widgets' ); ?>"><?php esc_html_e( 'بازگردانی پیش‌فرض', 'hamishop-elementor-widgets' ); ?></a>
						<a class="hsw-wm-accountlink" href="<?php echo esc_url( admin_url( 'admin.php?page=hamishop' ) ); ?>"><?php esc_html_e( 'تنظیمات پنل حساب کاربری', 'hamishop-elementor-widgets' ); ?> &larr;</a>
					</div>
				</aside>

				<main class="hsw-wm-main">
					<form method="post" action="<?php echo esc_url( $this->options_form_action() ); ?>" class="hsw-wm-form" data-hsw-widgets>
						<?php settings_fields( 'hsw_widgets_group' ); ?>
						<input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>[]" value="">

						<div class="hsw-wm-toolbar">
							<label class="hsw-wm-search">
								<span class="screen-reader-text"><?php esc_html_e( 'جستجوی ویجت', 'hamishop-elementor-widgets' ); ?></span>
								<span class="dashicons dashicons-search" aria-hidden="true"></span>
								<input type="search" placeholder="<?php esc_attr_e( 'جستجوی نام یا شناسه ویجت…', 'hamishop-elementor-widgets' ); ?>" data-hsw-search>
							</label>
							<div class="hsw-wm-presets" role="group" aria-label="<?php esc_attr_e( 'انتخاب سریع', 'hamishop-elementor-widgets' ); ?>">
								<button type="button" class="button" data-hsw-preset="default"><?php esc_html_e( 'فقط ویجت‌های اصلی', 'hamishop-elementor-widgets' ); ?></button>
								<button type="button" class="button" data-hsw-preset="all"><?php esc_html_e( 'فعال‌سازی همه', 'hamishop-elementor-widgets' ); ?></button>
								<button type="button" class="button-link" data-hsw-preset="none"><?php esc_html_e( 'پاک کردن همه', 'hamishop-elementor-widgets' ); ?></button>
							</div>
						</div>

						<div class="hsw-wm-empty" data-hsw-empty hidden><?php esc_html_e( 'ویجتی با این جستجو پیدا نشد.', 'hamishop-elementor-widgets' ); ?></div>

						<?php foreach ( $catalog as $group_key => $group ) : ?>
							<?php
							$counts = self::group_counts( $group_key );
							?>
							<section class="hsw-wm-group" id="hsw-group-<?php echo esc_attr( $group_key ); ?>" data-hsw-group-card="<?php echo esc_attr( $group_key ); ?>" style="--hsw-wm-accent:<?php echo esc_attr( $group['accent'] ); ?>">
								<header class="hsw-wm-grouphead">
									<span class="hsw-wm-groupicon dashicons <?php echo esc_attr( $group['icon'] ); ?>" aria-hidden="true"></span>
									<div class="hsw-wm-grouptitle">
										<h2><?php echo esc_html( $group['label'] ); ?></h2>
										<p><?php echo esc_html( $group['description'] ); ?></p>
									</div>
									<div class="hsw-wm-groupmeta">
										<span class="hsw-wm-groupcount"><b data-hsw-group-active><?php echo esc_html( self::fa_num( $counts['enabled'] ) ); ?></b> / <?php echo esc_html( self::fa_num( $counts['total'] ) ); ?> <?php esc_html_e( 'فعال', 'hamishop-elementor-widgets' ); ?></span>
										<button type="button" class="button-link" data-hsw-group-toggle><?php esc_html_e( 'فعال‌سازی همه', 'hamishop-elementor-widgets' ); ?></button>
									</div>
								</header>

								<div class="hsw-wm-rows">
									<?php foreach ( $group['general'] as $slug => $item ) : ?>
										<?php $this->render_row( $slug, $item, 'general', $group_key ); ?>
									<?php endforeach; ?>

									<?php if ( ! empty( $group['parts'] ) ) : ?>
										<div class="hsw-wm-parts">
											<div class="hsw-wm-partshead">
												<div>
													<strong><?php esc_html_e( 'بخش‌های مستقل صفحه', 'hamishop-elementor-widgets' ); ?></strong>
													<span><?php esc_html_e( 'پیش‌فرض خاموش‌اند؛ فقط در صورت نیاز فعال کنید.', 'hamishop-elementor-widgets' ); ?></span>
												</div>
												<span class="hsw-wm-partsbadge"><?php esc_html_e( 'زیرویجت‌ها', 'hamishop-elementor-widgets' ); ?></span>
											</div>
											<?php foreach ( $group['parts'] as $subgroup ) : ?>
												<div class="hsw-wm-subgroup" data-hsw-subgroup>
													<span class="hsw-wm-subtitle"><?php echo esc_html( $subgroup['label'] ); ?></span>
													<?php foreach ( $subgroup['items'] as $slug => $item ) : ?>
														<?php $this->render_row( $slug, $item, 'part', $group_key ); ?>
													<?php endforeach; ?>
												</div>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
								</div>
							</section>
						<?php endforeach; ?>

						<div class="hsw-wm-sectiontitle" id="hsw-settings">
							<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
							<div>
								<h2><?php esc_html_e( 'تنظیمات فروشگاه', 'hamishop-elementor-widgets' ); ?></h2>
								<p><?php esc_html_e( 'سه تنظیم سراسری که روی همهٔ ویجت‌ها اثر می‌گذارد.', 'hamishop-elementor-widgets' ); ?></p>
							</div>
						</div>

						<?php $fb = HSW_Assets::feedback_settings(); ?>
						<section class="hsw-wm-group" id="hsw-group-feedback" style="--hsw-wm-accent:#0e7490">
							<header class="hsw-wm-grouphead">
								<span class="hsw-wm-groupicon dashicons dashicons-cart" aria-hidden="true"></span>
								<div class="hsw-wm-grouptitle">
									<h2><?php esc_html_e( 'رفتار افزودن به سبد خرید', 'hamishop-elementor-widgets' ); ?></h2>
									<p><?php esc_html_e( 'بعد از افزودن محصول، چه چیزی به مشتری نشان داده شود؟', 'hamishop-elementor-widgets' ); ?></p>
								</div>
							</header>

							<?php
							$fb_modes = array(
								'modal' => __( 'مودال میان‌صفحه', 'hamishop-elementor-widgets' ),
								'bar'   => __( 'نوار تک‌خطی', 'hamishop-elementor-widgets' ),
								'off'   => __( 'غیرفعال', 'hamishop-elementor-widgets' ),
							);
							?>
							<div class="hsw-wm-rows">
								<div class="hsw-wm-row hsw-wm-row--field">
									<span class="hsw-wm-rowtext">
										<strong><?php esc_html_e( 'در آرشیو و فهرست محصولات', 'hamishop-elementor-widgets' ); ?></strong>
										<small><?php esc_html_e( 'مودال: پنجرهٔ شیشه‌ای با محتوای سبد. نوار: یک خط بالای صفحه با لینک «مشاهده سبد خرید». غیرفعال: هیچ بازخوردی نمایش داده نمی‌شود.', 'hamishop-elementor-widgets' ); ?></small>
									</span>
									<span class="hsw-wm-field">
										<select name="hsw_add_feedback[archive]" id="hsw-fb-archive">
											<?php foreach ( $fb_modes as $mode => $label ) : ?>
												<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $fb['archive_style'], $mode ); ?>><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
									</span>
								</div>

								<div class="hsw-wm-row hsw-wm-row--field">
									<span class="hsw-wm-rowtext">
										<strong><?php esc_html_e( 'در صفحهٔ محصول', 'hamishop-elementor-widgets' ); ?></strong>
										<small><?php esc_html_e( 'پیش‌فرض «غیرفعال»: مشتری همان کالا را می‌بیند و مودال روی گالری می‌افتد. اگر بازخورد می‌خواهید، مودال یا نوار را انتخاب کنید.', 'hamishop-elementor-widgets' ); ?></small>
									</span>
									<span class="hsw-wm-field">
										<select name="hsw_add_feedback[product]" id="hsw-fb-product">
											<?php foreach ( $fb_modes as $mode => $label ) : ?>
												<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $fb['product_style'], $mode ); ?>><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
									</span>
								</div>
							</div>
						</section>

						<section class="hsw-wm-group" id="hsw-group-catalog" style="--hsw-wm-accent:#7c3aed">
							<header class="hsw-wm-grouphead">
								<span class="hsw-wm-groupicon dashicons dashicons-sort" aria-hidden="true"></span>
								<div class="hsw-wm-grouptitle">
									<h2><?php esc_html_e( 'ترتیب محصولات', 'hamishop-elementor-widgets' ); ?></h2>
									<p><?php esc_html_e( 'محصولات ناموجود کجای فهرست فروشگاه بیایند؟', 'hamishop-elementor-widgets' ); ?></p>
								</div>
							</header>

							<div class="hsw-wm-rows">
								<label class="hsw-wm-row hsw-wm-row--field" for="hsw-oos-last">
									<span class="hsw-wm-rowtext">
										<strong><?php esc_html_e( 'ناموجودها انتهای فهرست', 'hamishop-elementor-widgets' ); ?></strong>
										<small><?php esc_html_e( 'با روشن بودن، محصولات ناموجود در همهٔ صفحات فروشگاه و دسته‌بندی‌ها بعد از موجودها نمایش داده می‌شوند؛ ترتیب بقیه (قیمت، جدیدترین، تخفیف) دست‌نخورده می‌ماند.', 'hamishop-elementor-widgets' ); ?></small>
									</span>
									<span class="hsw-wm-switch">
										<input type="hidden" name="hsw_oos_last" value="no" />
										<input type="checkbox" id="hsw-oos-last" name="hsw_oos_last" value="yes" <?php checked( 'yes', get_option( 'hsw_oos_last', 'yes' ) ); ?> />
										<span class="hsw-wm-track" aria-hidden="true"></span>
									</span>
								</label>
							</div>
						</section>

						<section class="hsw-wm-group" id="hsw-group-shipping" style="--hsw-wm-accent:#16a34a">
							<header class="hsw-wm-grouphead">
								<span class="hsw-wm-groupicon dashicons dashicons-car" aria-hidden="true"></span>
								<div class="hsw-wm-grouptitle">
									<h2><?php esc_html_e( 'ارسال رایگان', 'hamishop-elementor-widgets' ); ?></h2>
									<p><?php esc_html_e( 'بالای این مبلغ، هزینهٔ ارسال صفر می‌شود و مشتری «ارسال رایگان» می‌بیند.', 'hamishop-elementor-widgets' ); ?></p>
								</div>
							</header>

							<div class="hsw-wm-rows">
								<div class="hsw-wm-row hsw-wm-row--field">
									<span class="hsw-wm-rowtext">
										<strong><?php esc_html_e( 'سقف ارسال رایگان (تومان)', 'hamishop-elementor-widgets' ); ?></strong>
										<small><?php esc_html_e( 'صفر یعنی غیرفعال. مبلغ بر اساس جمع اقلام سبد سنجیده می‌شود، نه مبلغ نهایی.', 'hamishop-elementor-widgets' ); ?></small>
									</span>
									<span class="hsw-wm-field">
										<input type="number" min="0" step="1000" name="<?php echo esc_attr( HSW_Free_Shipping::OPTION ); ?>" value="<?php echo esc_attr( (string) (int) HSW_Free_Shipping::threshold() ); ?>" class="small-text" dir="ltr" />
									</span>
								</div>
							</div>

							<?php
							/*
							 * Stores often have a free-shipping method in a WooCommerce
							 * zone *and* this setting. Show the zone value so the number
							 * in the cart does not look like a bug in this field.
							 */
							$wc_free = class_exists( 'HSW_Free_Shipping' ) ? HSW_Free_Shipping::wc_threshold() : 0;
							if ( $wc_free > 0 ) :
								?>
								<p class="hsw-wm-note hsw-wm-note--info">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: amount. */
											__( 'در روش‌های ارسال ووکامرس، «ارسال رایگان» از %s تومان به بالا فعال است؛ همان عدد در سبد خرید اعمال می‌شود. اگر می‌خواهید سقف این افزونه جای آن را بگیرد، ابتدا آن روش را در ووکامرس حذف کنید.', 'hamishop-elementor-widgets' ),
											HSW_Helpers::fa_num( number_format_i18n( $wc_free ) )
										)
									);
									?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping' ) ); ?>"><?php esc_html_e( 'ویرایش روش‌های ارسال', 'hamishop-elementor-widgets' ); ?></a>
								</p>
							<?php endif; ?>
						</section>

						<section class="hsw-wm-group" id="hsw-group-404" style="--hsw-wm-accent:#dc2626">
							<header class="hsw-wm-grouphead">
								<span class="hsw-wm-groupicon dashicons dashicons-warning" aria-hidden="true"></span>
								<div class="hsw-wm-grouptitle">
									<h2><?php esc_html_e( 'صفحهٔ ۴۰۴ اختصاصی', 'hamishop-elementor-widgets' ); ?></h2>
									<p><?php esc_html_e( 'به جای صفحهٔ ۴۰۴ سادهٔ قالب، یک صفحهٔ گلس‌مورفیسم با جستجوی محصول و دکمهٔ بازگشت به فروشگاه نشان داده می‌شود.', 'hamishop-elementor-widgets' ); ?></p>
								</div>
							</header>

							<div class="hsw-wm-rows">
								<label class="hsw-wm-row hsw-wm-row--field" for="hsw-custom-404">
									<span class="hsw-wm-rowtext">
										<strong><?php esc_html_e( 'استفاده از صفحهٔ ۴۰۴ اختصاصی', 'hamishop-elementor-widgets' ); ?></strong>
										<small><?php esc_html_e( 'هدر و فوتر سایت حفظ می‌شوند؛ فقط محتوای صفحه عوض می‌شود.', 'hamishop-elementor-widgets' ); ?></small>
									</span>
									<span class="hsw-wm-switch">
										<input type="hidden" name="hsw_custom_404_enabled" value="no" />
										<input type="checkbox" id="hsw-custom-404" name="hsw_custom_404_enabled" value="yes" <?php checked( 'yes', get_option( 'hsw_custom_404_enabled', 'yes' ) ); ?> />
										<span class="hsw-wm-track" aria-hidden="true"></span>
									</span>
								</label>

								<div class="hsw-wm-row hsw-wm-row--field">
									<span class="hsw-wm-rowtext">
										<strong><?php esc_html_e( 'عنوان صفحه', 'hamishop-elementor-widgets' ); ?></strong>
										<small><?php esc_html_e( 'خالی بگذارید تا متن پیش‌فرض استفاده شود.', 'hamishop-elementor-widgets' ); ?></small>
									</span>
									<span class="hsw-wm-field">
										<input type="text" name="hsw_404_title" value="<?php echo esc_attr( get_option( 'hsw_404_title', '' ) ); ?>" placeholder="<?php esc_attr_e( 'صفحه‌ای که دنبالش بودید پیدا نشد!', 'hamishop-elementor-widgets' ); ?>" />
									</span>
								</div>

								<div class="hsw-wm-row hsw-wm-row--field">
									<span class="hsw-wm-rowtext">
										<strong><?php esc_html_e( 'توضیح صفحه', 'hamishop-elementor-widgets' ); ?></strong>
										<small><?php esc_html_e( 'جملهٔ کوتاه زیر عنوان.', 'hamishop-elementor-widgets' ); ?></small>
									</span>
									<span class="hsw-wm-field">
										<input type="text" name="hsw_404_text" value="<?php echo esc_attr( get_option( 'hsw_404_text', '' ) ); ?>" placeholder="<?php esc_attr_e( 'ممکن است آدرس اشتباه باشد یا صفحه جابه‌جا شده باشد.', 'hamishop-elementor-widgets' ); ?>" />
									</span>
								</div>

								<div class="hsw-wm-row hsw-wm-row--field">
									<span class="hsw-wm-rowtext">
										<strong><?php esc_html_e( 'متن دکمهٔ اصلی', 'hamishop-elementor-widgets' ); ?></strong>
										<small><?php esc_html_e( 'دکمهٔ برجسته که به فروشگاه می‌رود.', 'hamishop-elementor-widgets' ); ?></small>
									</span>
									<span class="hsw-wm-field">
										<input type="text" name="hsw_404_cta" value="<?php echo esc_attr( get_option( 'hsw_404_cta', '' ) ); ?>" placeholder="<?php esc_attr_e( 'ورود به فروشگاه', 'hamishop-elementor-widgets' ); ?>" />
									</span>
								</div>

								<div class="hsw-wm-row hsw-wm-row--field">
									<span class="hsw-wm-rowtext">
										<strong><?php esc_html_e( 'متن دکمهٔ فرعی', 'hamishop-elementor-widgets' ); ?></strong>
										<small><?php esc_html_e( 'دکمهٔ شیشه‌ای که به صفحهٔ اصلی برمی‌گردد.', 'hamishop-elementor-widgets' ); ?></small>
									</span>
									<span class="hsw-wm-field">
										<input type="text" name="hsw_404_home" value="<?php echo esc_attr( get_option( 'hsw_404_home', '' ) ); ?>" placeholder="<?php esc_attr_e( 'بازگشت به صفحهٔ اصلی', 'hamishop-elementor-widgets' ); ?>" />
									</span>
								</div>

								<div class="hsw-wm-row hsw-wm-row--field">
									<span class="hsw-wm-rowtext">
										<strong><?php esc_html_e( 'متن کادر جستجو', 'hamishop-elementor-widgets' ); ?></strong>
										<small><?php esc_html_e( 'متن راهنمای داخل فیلد جستجوی محصول.', 'hamishop-elementor-widgets' ); ?></small>
									</span>
									<span class="hsw-wm-field">
										<input type="text" name="hsw_404_search_ph" value="<?php echo esc_attr( get_option( 'hsw_404_search_ph', '' ) ); ?>" placeholder="<?php esc_attr_e( 'نام محصول را جستجو کنید…', 'hamishop-elementor-widgets' ); ?>" />
									</span>
								</div>
							</div>
						</section>

						<div class="hsw-wm-savebar">
							<div class="hsw-wm-savecount">
								<strong data-hsw-save-count><?php echo esc_html( self::fa_num( $active ) ); ?></strong>
								<?php esc_html_e( 'ویجت برای پنل المنتور فعال است', 'hamishop-elementor-widgets' ); ?>
								<span class="hsw-wm-dirty" data-hsw-dirty hidden><?php esc_html_e( 'تغییرات ذخیره نشده', 'hamishop-elementor-widgets' ); ?></span>
							</div>
							<div class="hsw-wm-saveactions">
								<?php submit_button( __( 'ذخیره تغییرات', 'hamishop-elementor-widgets' ), 'primary', 'submit', false ); ?>
							</div>
						</div>
					</form>
				</main>
			</div>
		</div>
		<?php
		$this->page_script();
	}

	/**
	 * One widget row.
	 *
	 * @param string $slug      Widget slug.
	 * @param array  $item      Label/description.
	 * @param string $kind      general|part.
	 * @param string $group_key Group key.
	 */
	private function render_row( $slug, $item, $kind, $group_key ) {
		$checked = self::is_enabled( $slug );
		$id      = 'hsw-wm-' . $slug;
		?>
		<div class="hsw-wm-row <?php echo 'general' === $kind ? 'is-general' : 'is-part'; ?>" data-hsw-row data-hsw-kind="<?php echo esc_attr( $kind ); ?>" data-hsw-group="<?php echo esc_attr( $group_key ); ?>">
			<label class="hsw-wm-switch" for="<?php echo esc_attr( $id ); ?>">
				<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::OPTION ); ?>[]" value="<?php echo esc_attr( $slug ); ?>" data-hsw-check data-hsw-kind="<?php echo esc_attr( $kind ); ?>" data-hsw-group="<?php echo esc_attr( $group_key ); ?>" <?php checked( $checked ); ?>>
				<span class="hsw-wm-track" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php echo esc_html( $item['label'] ); ?></span>
			</label>
			<div class="hsw-wm-copy">
				<label class="hsw-wm-title" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $item['label'] ); ?></label>
				<span class="hsw-wm-desc"><?php echo esc_html( $item['desc'] ); ?></span>
				<code class="hsw-wm-slug"><?php echo esc_html( $slug ); ?></code>
			</div>
			<span class="hsw-wm-badge <?php echo 'general' === $kind ? 'is-general' : 'is-part'; ?>"><?php echo 'general' === $kind ? esc_html__( 'ویجت اصلی', 'hamishop-elementor-widgets' ) : esc_html__( 'بخش مستقل', 'hamishop-elementor-widgets' ); ?></span>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Assets (inline, scoped to this screen)
	 * ------------------------------------------------------------------ */

	private function page_styles() {
		?>
		<style>
			.hsw-wm{--hsw-wm-ink:#0f2740;--hsw-wm-muted:#5b6b80;--hsw-wm-line:#dde5ee;--hsw-wm-soft:#f5f8fc;--hsw-wm-white:#fff;max-width:1460px;color:var(--hsw-wm-ink)}
			.hsw-wm *{box-sizing:border-box}
			.hsw-wm-head{display:flex;align-items:center;justify-content:space-between;gap:18px;background:radial-gradient(circle at 88% 20%,rgba(56,189,248,.18),transparent 32%),linear-gradient(135deg,#081b2e,#123a5c 58%,#0d2f4c);color:#fff;border-radius:20px;padding:24px 26px;margin:12px 0 18px;flex-wrap:wrap;box-shadow:0 18px 36px -24px rgba(8,27,46,.8)}
			.hsw-wm-brand{display:flex;align-items:center;gap:16px}
			.hsw-wm-logo{font-weight:800;font-size:19px;letter-spacing:.2px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.24);padding:9px 14px;border-radius:13px;box-shadow:inset 0 1px 0 rgba(255,255,255,.16)}
			.hsw-wm-head h1{color:#fff;margin:0 0 4px;font-size:23px;font-weight:800}
			.hsw-wm-head p{margin:0;color:rgba(255,255,255,.75);font-size:13px}
			.hsw-wm-stats{display:flex;gap:8px;flex-wrap:wrap}
			.hsw-wm-stats span{display:flex;flex-direction:column;align-items:center;gap:2px;min-width:76px;background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.16);border-radius:12px;padding:8px 12px;color:rgba(255,255,255,.7);font-size:10px}
			.hsw-wm-stats strong{color:#fff;font-size:19px;line-height:1.1}
			.hsw-wm-layout{display:grid;grid-template-columns:262px minmax(0,1fr);gap:18px;align-items:start}
			.hsw-wm-side{position:sticky;top:42px;display:grid;gap:12px}
			.hsw-wm-nav{background:var(--hsw-wm-white);border:1px solid var(--hsw-wm-line);border-radius:16px;padding:12px;box-shadow:0 8px 24px -20px rgba(15,39,64,.5)}
			.hsw-wm-navtitle{display:block;font-size:10px;font-weight:800;letter-spacing:.14em;color:#8492a6;padding:4px 8px 8px}
			.hsw-wm-nav a{display:flex;align-items:center;gap:8px;padding:9px 9px;border-radius:10px;text-decoration:none;color:#243b53;font-weight:600;font-size:13px}
			.hsw-wm-nav a:hover{background:var(--hsw-wm-soft);color:#0b5c8a}
			.hsw-wm-nav a .dashicons{font-size:17px;width:17px;height:17px;color:#5b7b9a}
			.hsw-wm-nav a span:nth-child(2){flex:1}
			.hsw-wm-nav em{font-style:normal;font-size:10px;color:#7b8ca0;background:#edf2f7;border-radius:999px;padding:2px 7px}
			.hsw-wm-tip{background:var(--hsw-wm-white);border:1px solid var(--hsw-wm-line);border-radius:16px;padding:14px 15px;box-shadow:0 8px 24px -22px rgba(15,39,64,.5)}
			.hsw-wm-tip .dashicons{color:#0e7490;font-size:20px;width:20px;height:20px}
			.hsw-wm-tip h2{font-size:13px;margin:7px 0 5px}
			.hsw-wm-tip p{margin:0;color:var(--hsw-wm-muted);font-size:11.5px;line-height:1.9}
			.hsw-wm-tip--warn .dashicons{color:#b45309}
			.hsw-wm-tip--warn{background:#fffbeb;border-color:#fde68a}
			.hsw-wm-reset{display:flex;flex-direction:column;gap:9px;padding:2px}
			.hsw-wm-reset .button{justify-content:center}
			.hsw-wm-accountlink{font-size:11px;color:#64748b;text-decoration:none;padding:0 2px}
			.hsw-wm-accountlink:hover{color:#0e7490}
			.hsw-wm-main{min-width:0}
			.hsw-wm-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--hsw-wm-white);border:1px solid var(--hsw-wm-line);border-radius:16px;padding:12px 14px;margin-bottom:14px;flex-wrap:wrap;box-shadow:0 8px 24px -22px rgba(15,39,64,.5)}
			.hsw-wm-search{position:relative;flex:1;min-width:220px;display:flex;align-items:center}
			.hsw-wm-search .dashicons{position:absolute;inset-inline-start:11px;color:#8492a6;font-size:18px;width:18px;height:18px;pointer-events:none}
			.hsw-wm-search input{width:100%;height:38px;border:1px solid #d7e0ea;border-radius:10px;padding-inline:36px 12px;background:#f8fafc;font-size:12px;outline:none}
			.hsw-wm-search input:focus{border-color:#0e7490;box-shadow:0 0 0 3px rgba(14,116,144,.13);background:#fff}
			.hsw-wm-presets{display:flex;align-items:center;gap:7px;flex-wrap:wrap}
			.hsw-wm-presets .button{font-size:11px;min-height:32px;line-height:30px;padding:0 11px;border-radius:8px}
			.hsw-wm-presets .button-link{font-size:11px;color:#b91c1c;text-decoration:none}
			.hsw-wm-empty{background:#fff;border:1px dashed #cbd5e1;border-radius:14px;padding:24px;text-align:center;color:#64748b;margin-bottom:14px}
			.hsw-wm-group{background:var(--hsw-wm-white);border:1px solid var(--hsw-wm-line);border-radius:18px;margin-bottom:16px;overflow:hidden;box-shadow:0 12px 30px -26px rgba(15,39,64,.55);scroll-margin-top:42px}
			.hsw-wm-grouphead{display:flex;align-items:flex-start;gap:13px;padding:18px 20px 15px;border-bottom:1px solid #edf2f7;background:linear-gradient(180deg,#fff,var(--hsw-wm-soft))}
			.hsw-wm-groupicon{flex:0 0 auto;display:flex!important;align-items:center;justify-content:center;width:42px;height:42px;border-radius:12px;background:#eef7fa;background:color-mix(in srgb,var(--hsw-wm-accent) 12%,#fff);color:var(--hsw-wm-accent);font-size:21px!important}
			.hsw-wm-grouptitle{flex:1;min-width:0}
			.hsw-wm-grouptitle h2{margin:0 0 3px;font-size:16px;display:flex;align-items:center;gap:7px}
			.hsw-wm-grouptitle h2::after{content:"";width:7px;height:7px;border-radius:50%;background:var(--hsw-wm-accent);box-shadow:0 0 0 3px color-mix(in srgb,var(--hsw-wm-accent) 14%,#fff)}
			.hsw-wm-grouptitle p{margin:0;color:var(--hsw-wm-muted);font-size:11.5px;line-height:1.85;max-width:680px}
			.hsw-wm-groupmeta{display:flex;flex-direction:column;align-items:flex-end;gap:5px;flex:0 0 auto}
			.hsw-wm-groupcount{font-size:11px;color:#64748b;background:#fff;border:1px solid #e2e8f0;border-radius:999px;padding:4px 9px;white-space:nowrap}
			.hsw-wm-groupcount b{color:var(--hsw-wm-accent);font-size:12px}
			.hsw-wm-groupmeta .button-link{font-size:10.5px;color:#0e7490;text-decoration:none}
			.hsw-wm-rows{padding:5px 10px 12px}
			.hsw-wm-row{display:grid;grid-template-columns:44px minmax(0,1fr) auto;align-items:center;gap:10px;padding:10px 11px;border-radius:11px;border:1px solid transparent;transition:background .15s,border-color .15s}
			.hsw-wm-row:hover{background:#f8fbfe;border-color:#e8eff6}
			.hsw-wm-row.is-general{background:#fbfdfe;background:linear-gradient(90deg,color-mix(in srgb,var(--hsw-wm-accent) 5%,#fff),#fff 70%);border-color:#edf2f7;margin:4px 0}
			.hsw-wm-row.is-part{opacity:.94}
			.hsw-wm-row[hidden]{display:none!important}
			/* Setting rows hold a label + one control (no 44px switch column). */
			.hsw-wm-row--field{grid-template-columns:minmax(0,1fr) auto;gap:16px}
			.hsw-wm-rowtext{display:grid;gap:3px;min-width:0}
			.hsw-wm-rowtext strong{font-size:12.5px;font-weight:700;color:#18344f}
			.hsw-wm-rowtext small{display:block;font-size:11px;line-height:1.8;color:#6b7c90}
			.hsw-wm-field{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex:0 0 auto}
			.hsw-wm-field select{min-width:210px;max-width:280px;height:34px;border:1px solid #d7e0ea;border-radius:9px;background:#fff;padding:0 9px;font-size:12px;color:#18344f}
			.hsw-wm-field select:focus{border-color:#0e7490;box-shadow:0 0 0 3px rgba(14,116,144,.13);outline:none}
			.hsw-wm-field input[type="number"]{width:150px;height:34px;border:1px solid #d7e0ea;border-radius:9px;background:#fff;padding:0 9px;font-size:12px;color:#18344f;text-align:center}
			.hsw-wm-field input[type="number"]:focus{border-color:#0e7490;box-shadow:0 0 0 3px rgba(14,116,144,.13);outline:none}
			.hsw-wm-sectiontitle{display:flex;align-items:center;gap:12px;margin:26px 0 14px;padding:0 4px}
			.hsw-wm-sectiontitle .dashicons{display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:10px;background:#eef7fa;color:#0e7490;font-size:19px}
			.hsw-wm-sectiontitle h2{margin:0;font-size:15px;font-weight:800;color:#0f2740}
			.hsw-wm-sectiontitle p{margin:2px 0 0;font-size:11.5px;color:#6b7c90}
			.hsw-wm-nav--settings{margin-top:4px}
			.hsw-wm-note{display:flex;align-items:flex-start;gap:8px;flex-wrap:wrap;margin:0;padding:10px 12px;border-radius:10px;font-size:11.5px;line-height:1.9}
			.hsw-wm-note--info{background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a}
			.hsw-wm-note a{color:#1d4ed8;font-weight:700;text-decoration:none}
			.hsw-wm-switch{display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;width:44px;height:30px;margin:0}
			.hsw-wm-switch input{position:absolute;opacity:0;width:1px;height:1px;margin:0}
			.hsw-wm-track{width:40px;height:23px;border-radius:999px;background:#cbd5e1;position:relative;transition:background .18s;box-shadow:inset 0 1px 2px rgba(15,39,64,.15)}
			.hsw-wm-track::after{content:"";position:absolute;top:3px;inset-inline-start:3px;width:17px;height:17px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(15,39,64,.28);transition:inset-inline-start .18s}
			.hsw-wm-switch input:checked + .hsw-wm-track{background:var(--hsw-wm-accent,#0e7490)}
			.hsw-wm-switch input:checked + .hsw-wm-track::after{inset-inline-start:20px}
			.hsw-wm-switch input:focus-visible + .hsw-wm-track{outline:3px solid rgba(14,116,144,.25);outline-offset:2px}
			.hsw-wm-copy{min-width:0;display:grid;gap:2px}
			.hsw-wm-title{font-weight:700;font-size:12.5px;color:#18344f;cursor:pointer;justify-self:start}
			.hsw-wm-desc{font-size:11px;color:#6b7c90;line-height:1.75}
			.hsw-wm-slug{display:inline-block;justify-self:start;direction:ltr;text-align:left;font-size:9.5px;color:#8492a6;background:#f1f5f9;border-radius:5px;padding:1px 6px;margin-top:2px;border:1px solid #e8eef5}
			.hsw-wm-badge{font-size:9.5px;font-weight:700;border-radius:999px;padding:4px 9px;white-space:nowrap}
			.hsw-wm-badge.is-general{color:#0e5b70;background:#eef7fa;border:1px solid #cfe7ef;color:color-mix(in srgb,var(--hsw-wm-accent) 86%,#000);background:color-mix(in srgb,var(--hsw-wm-accent) 11%,#fff);border:1px solid color-mix(in srgb,var(--hsw-wm-accent) 22%,#fff)}
			.hsw-wm-badge.is-part{color:#64748b;background:#f8fafc;border:1px solid #e2e8f0}
			.hsw-wm-parts{margin-top:10px;border-top:1px dashed #cfdae6;padding-top:8px}
			.hsw-wm-partshead{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:7px 10px 5px;flex-wrap:wrap}
			.hsw-wm-partshead strong{display:block;font-size:11.5px;color:#334155}
			.hsw-wm-partshead span{display:block;font-size:10.5px;color:#8492a6;margin-top:1px}
			.hsw-wm-partsbadge{font-size:9px!important;font-weight:700;color:#64748b!important;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:999px;padding:3px 8px;white-space:nowrap}
			.hsw-wm-subgroup{padding:4px 0 2px}
			.hsw-wm-subgroup[hidden]{display:none!important}
			.hsw-wm-subtitle{display:block;font-size:10px;font-weight:800;letter-spacing:.04em;color:#7b8ca0;padding:8px 11px 4px}
			.hsw-wm-savebar{position:sticky;bottom:14px;z-index:5;display:flex;align-items:center;justify-content:space-between;gap:14px;background:rgba(255,255,255,.95);border:1px solid #dbe5ef;border-radius:16px;padding:12px 16px;margin:4px 0 20px;box-shadow:0 16px 36px -22px rgba(15,39,64,.65);backdrop-filter:blur(8px);flex-wrap:wrap}
			.hsw-wm-savecount{font-size:12px;color:#475569}
			.hsw-wm-savecount strong{font-size:20px;color:#0f2740;margin-inline-end:3px}
			.hsw-wm-dirty{display:inline-block;margin-inline-start:9px;font-size:10px;font-weight:700;color:#b45309;background:#fffbeb;border:1px solid #fde68a;border-radius:999px;padding:2px 7px}
			.hsw-wm-dirty[hidden]{display:none}
			.hsw-wm-saveactions .button{min-height:38px;padding:0 20px;border-radius:9px;font-weight:700}
			@media(max-width:1080px){.hsw-wm-layout{grid-template-columns:1fr}.hsw-wm-side{position:static;grid-template-columns:1fr 1fr;grid-template-areas:"nav nav" "tip warn" "reset reset"}.hsw-wm-nav{grid-area:nav}.hsw-wm-tip{grid-area:tip}.hsw-wm-tip--warn{grid-area:warn}.hsw-wm-reset{grid-area:reset;flex-direction:row;align-items:center;justify-content:space-between}.hsw-wm-multi{display:none}}
			@media(max-width:782px){.hsw-wm-head{padding:18px}.hsw-wm-brand{align-items:flex-start}.hsw-wm-logo{display:none}.hsw-wm-side{grid-template-columns:1fr;grid-template-areas:"nav" "tip" "warn" "reset"}.hsw-wm-grouphead{padding:14px 14px 12px}.hsw-wm-groupmeta{width:100%;flex-direction:row;align-items:center;justify-content:space-between;padding-inline-start:55px}.hsw-wm-row{grid-template-columns:44px minmax(0,1fr);gap:7px}.hsw-wm-row--field{grid-template-columns:1fr;gap:8px}.hsw-wm-field{justify-content:flex-start}.hsw-wm-field select{min-width:0;width:100%;max-width:none}.hsw-wm-badge{grid-column:2;justify-self:start;margin-top:2px}.hsw-wm-toolbar{align-items:stretch}.hsw-wm-presets{justify-content:space-between}.hsw-wm-savebar{position:static}.hsw-wm-reset{flex-direction:column;align-items:stretch}.hsw-wm-reset .button{text-align:center}}
			@media(prefers-reduced-motion:reduce){.hsw-wm *,.hsw-wm *::before,.hsw-wm *::after{transition:none!important;scroll-behavior:auto!important}}
		</style>
		<?php
	}

	private function page_script() {
		?>
		<script>
		( function () {
			'use strict';
			var form = document.querySelector( '[data-hsw-widgets]' );
			if ( ! form ) { return; }

			var boxes   = Array.prototype.slice.call( form.querySelectorAll( '[data-hsw-check]' ) );
			var groups  = Array.prototype.slice.call( document.querySelectorAll( '[data-hsw-group-card]' ) );
			var navs    = Array.prototype.slice.call( document.querySelectorAll( '[data-hsw-nav]' ) );
			var search  = form.querySelector( '[data-hsw-search]' );
			var empty   = document.querySelector( '[data-hsw-empty]' );
			var dirty   = document.querySelector( '[data-hsw-dirty]' );
			var active  = document.querySelector( '[data-hsw-active]' );
			var saveN   = document.querySelector( '[data-hsw-save-count]' );
			var fa = function ( n ) {
				return String( n ).replace( /[0-9]/g, function ( d ) { return '۰۱۲۳۴۵۶۷۸۹'[ Number( d ) ]; } );
			};
			var count = function ( list ) {
				return list.filter( function ( b ) { return b.checked; } ).length;
			};
			var markDirty = function () {
				if ( dirty ) { dirty.hidden = false; }
			};

			function refresh() {
				var total = count( boxes );
				if ( active ) { active.textContent = fa( total ); }
				if ( saveN ) { saveN.textContent = fa( total ); }

				groups.forEach( function ( group ) {
					var key     = group.getAttribute( 'data-hsw-group-card' );
					var own     = boxes.filter( function ( b ) { return b.getAttribute( 'data-hsw-group' ) === key; } );
					var enabled = count( own );
					var el      = group.querySelector( '[data-hsw-group-active]' );
					var nav     = document.querySelector( '[data-hsw-nav="' + key + '"] em' );
					var toggle  = group.querySelector( '[data-hsw-group-toggle]' );
					if ( el ) { el.textContent = fa( enabled ); }
					if ( nav ) { nav.textContent = fa( enabled ) + '/' + fa( own.length ); }
					if ( toggle ) {
						toggle.textContent = enabled === own.length ? 'غیرفعال‌سازی همه' : 'فعال‌سازی همه';
					}
				} );
			}

			function filter() {
				var q = ( search && search.value ? search.value : '' ).trim().toLowerCase();
				var visible = 0;

				document.querySelectorAll( '[data-hsw-row]' ).forEach( function ( row ) {
					var match = ! q || row.textContent.toLowerCase().indexOf( q ) !== -1;
					row.hidden = ! match;
					if ( match ) { visible++; }
				} );

				groups.forEach( function ( group ) {
					var has = group.querySelector( '[data-hsw-row]:not([hidden])' );
					group.hidden = ! has;
				} );

				document.querySelectorAll( '[data-hsw-subgroup]' ).forEach( function ( sub ) {
					var has = sub.querySelector( '[data-hsw-row]:not([hidden])' );
					sub.hidden = ! has;
				} );

				navs.forEach( function ( nav ) {
					var target = document.querySelector( '#' + nav.getAttribute( 'href' ).slice( 1 ) );
					nav.hidden = target ? target.hidden : false;
				} );

				if ( empty ) { empty.hidden = visible > 0; }
			}

			boxes.forEach( function ( box ) {
				box.addEventListener( 'change', function () {
					markDirty();
					refresh();
				} );
			} );

			if ( search ) {
				search.addEventListener( 'input', filter );
			}

			document.querySelectorAll( '[data-hsw-preset]' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var mode = btn.getAttribute( 'data-hsw-preset' );
					boxes.forEach( function ( box ) {
						if ( 'all' === mode ) {
							box.checked = true;
						} else if ( 'none' === mode ) {
							box.checked = false;
						} else {
							box.checked = box.getAttribute( 'data-hsw-kind' ) === 'general';
						}
					} );
					markDirty();
					refresh();
					filter();
				} );
			} );

			groups.forEach( function ( group ) {
				var toggle = group.querySelector( '[data-hsw-group-toggle]' );
				if ( ! toggle ) { return; }
				toggle.addEventListener( 'click', function () {
					var own = boxes.filter( function ( b ) { return b.getAttribute( 'data-hsw-group' ) === group.getAttribute( 'data-hsw-group-card' ); } );
					var target = count( own ) !== own.length;
					own.forEach( function ( b ) { b.checked = target; } );
					markDirty();
					refresh();
					filter();
				} );
			} );

			document.querySelectorAll( '[data-hsw-confirm]' ).forEach( function ( link ) {
				link.addEventListener( 'click', function ( e ) {
					if ( ! window.confirm( link.getAttribute( 'data-hsw-confirm' ) ) ) {
						e.preventDefault();
					}
				} );
			} );

			form.addEventListener( 'submit', function () {
				if ( dirty ) { dirty.hidden = true; }
			} );

			refresh();
		}() );
		</script>
		<?php
	}
}
