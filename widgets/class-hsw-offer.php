<?php
/**
 * Widget: آفر حامی‌شاپ (HamiShop Offer).
 *
 * A standalone campaign widget. It deliberately does not reuse the hsw-loop
 * card markup: the design has its own lead banner, card proportions, stock
 * meter and per-card countdown, and keeping it separate means none of the
 * existing loop widgets can be affected by its CSS.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

class HSW_Widget_Offer extends HSW_Widget_Base {

	/** Maximum number of products a single instance may render. */
	const MAX_PRODUCTS = 8;

	public function get_name() {
		return 'hsw-offer';
	}

	public function get_style_depends() {
		return array( 'hsw-fonts', 'hsw-offer' );
	}

	public function get_script_depends() {
		return array( 'hsw-offer' );
	}

	public function get_title() {
		return __( 'آفر حامی‌شاپ (HamiShop)', 'hamishop-elementor-widgets' );
	}

	public function get_icon() {
		return 'eicon-price-list';
	}

	public function get_keywords() {
		return array( 'آفر', 'پیشنهاد', 'شگفت‌انگیز', 'تخفیف', 'کمپین', 'offer', 'deal', 'sale', 'hamishop' );
	}

	protected function register_controls() {
		$this->controls_offer_lead();
		$this->controls_offer_products();
		$this->controls_offer_style();
	}

	/* =========================================================================
	 *  CONTENT
	 * ====================================================================== */

	private function controls_offer_lead() {
		$this->start_controls_section(
			'sec_offer_lead',
			array(
				'label' => __( 'کارت کمپین', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'offer_badge',
			array(
				'label'   => __( 'برچسب بالای کارت', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'تخفیف‌های محدود ۲۴ ساعته', 'hamishop-elementor-widgets' ),
			)
		);

		$this->add_control(
			'offer_title',
			array(
				'label'       => __( 'عنوان اصلی', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 3,
				'default'     => "پیشنهاد\nشگفت‌انگیز",
				'label_block' => true,
			)
		);

		$this->add_control(
			'offer_title_tag',
			array(
				'label'   => __( 'تگ عنوان', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'h2',
				'options' => array(
					'h2'   => 'H2',
					'h3'   => 'H3',
					'div'  => 'div',
					'span' => 'span',
				),
			)
		);

		$this->add_control(
			'offer_sub',
			array(
				'label'       => __( 'زیرعنوان', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 2,
				'default'     => __( 'انتخاب دست‌چین از ساعت‌ها و اکسسوری‌های نفیس', 'hamishop-elementor-widgets' ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'offer_icon',
			array(
				'label'   => __( 'آیکن وسط', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'redeem',
				'options' => array(
					'redeem'                 => __( 'هدیه', 'hamishop-elementor-widgets' ),
					'local_fire_department'  => __( 'شعله', 'hamishop-elementor-widgets' ),
					'bolt'                   => __( 'صاعقه', 'hamishop-elementor-widgets' ),
					'auto_awesome'           => __( 'درخشش', 'hamishop-elementor-widgets' ),
					'savings'                => __( 'جایزه', 'hamishop-elementor-widgets' ),
				),
			)
		);

		$this->add_control(
			'offer_cta_text',
			array(
				'label'   => __( 'متن دکمه', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'مشاهده همه', 'hamishop-elementor-widgets' ),
			)
		);

		$this->add_control(
			'offer_cta_url',
			array(
				'label'       => __( 'لینک دکمه', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::URL,
				'default'     => array(
					'url' => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '',
				),
				'label_block' => true,
			)
		);

		$this->add_control(
			'offer_show_campaign_timer',
			array(
				'label'        => __( 'نمایش تایمر کمپین', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'offer_campaign_end',
			array(
				'label'       => __( 'پایان کمپین', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::DATE_TIME,
				'description' => __( 'اگر خالی باشد، نزدیک‌ترین پایان تخفیف محصولات استفاده می‌شود.', 'hamishop-elementor-widgets' ),
				'condition'   => array( 'offer_show_campaign_timer' => 'yes' ),
			)
		);

		$this->add_control(
			'offer_timer_label',
			array(
				'label'     => __( 'زیرنویس تایمر', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'زمان باقی‌مانده تا اتمام تخفیف', 'hamishop-elementor-widgets' ),
				'condition' => array( 'offer_show_campaign_timer' => 'yes' ),
			)
		);

		$this->add_control(
			'offer_end_text',
			array(
				'label'     => __( 'متن پس از پایان', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'پایان یافت', 'hamishop-elementor-widgets' ),
				'condition' => array( 'offer_show_campaign_timer' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	private function controls_offer_products() {
		$this->start_controls_section(
			'sec_offer_products',
			array(
				'label' => __( 'منبع و کارت محصولات', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'offer_source',
			array(
				'label'   => __( 'منبع محصولات', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'auto',
				'options' => array(
					'auto'   => __( 'خودکار: محصولات دارای تخفیف', 'hamishop-elementor-widgets' ),
					'manual' => __( 'دستی: شناسه محصولات', 'hamishop-elementor-widgets' ),
				),
			)
		);

		$this->add_control(
			'offer_ids',
			array(
				'label'       => __( 'شناسه محصولات', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'description' => __( 'حداکثر ۸ شناسه، جدا شده با کاما. مثال: 1490, 1620', 'hamishop-elementor-widgets' ),
				'placeholder' => '1490, 1620',
				'condition'   => array( 'offer_source' => 'manual' ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'offer_count',
			array(
				'label'      => __( 'تعداد محصولات', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 1,
						'max'  => self::MAX_PRODUCTS,
						'step' => 1,
					),
				),
				'default'    => array( 'size' => 6 ),
			)
		);

		$this->add_control(
			'offer_min_discount',
			array(
				'label'   => __( 'حداقل درصد تخفیف', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 0,
				'max'     => 90,
				'step'    => 1,
				'default' => 0,
			)
		);

		$this->add_control(
			'offer_order',
			array(
				'label'   => __( 'مرتب‌سازی', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'discount',
				'options' => array(
					'discount'   => __( 'بیشترین درصد تخفیف', 'hamishop-elementor-widgets' ),
					'end'        => __( 'نزدیک‌ترین پایان تخفیف', 'hamishop-elementor-widgets' ),
					'date'       => __( 'جدیدترین محصول', 'hamishop-elementor-widgets' ),
					'popularity' => __( 'پرفروش‌ترین', 'hamishop-elementor-widgets' ),
					'random'     => __( 'تصادفی', 'hamishop-elementor-widgets' ),
				),
			)
		);

		$this->add_control(
			'offer_exclude_oos',
			array(
				'label'        => __( 'فقط محصولات موجود', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'offer_hide_empty',
			array(
				'label'        => __( 'مخفی‌کردن ویجت اگر محصولی نیست', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'offer_show_note',
			array(
				'label'        => __( 'نمایش برچسب بالای کارت', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'offer_show_stock',
			array(
				'label'        => __( 'نمایش نوار موجودی', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'offer_show_card_timer',
			array(
				'label'        => __( 'نمایش تایمر هر کارت', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'offer_default_note',
			array(
				'label'     => __( 'برچسب پیش‌فرض کارت', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'ضمانت اصالت', 'hamishop-elementor-widgets' ),
				'condition' => array( 'offer_show_note' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * One color control mapped to a CSS custom property on the widget root.
	 *
	 * @param string $id      Control id.
	 * @param string $label   Label.
	 * @param string $var     CSS custom property.
	 * @param string $default Default value.
	 * @param bool   $alpha   Allow alpha channel.
	 * @return void
	 */
	private function offer_color_control( $id, $label, $var, $default, $alpha = false ) {
		$this->add_control(
			$id,
			array(
				'label'     => $label,
				'type'      => Controls_Manager::COLOR,
				'default'   => $default,
				'alpha'     => $alpha,
				'selectors' => array( '{{WRAPPER}} .hsw-offer' => $var . ': {{VALUE}};' ),
			)
		);
	}

	/**
	 * Every brand colour exposed by the offer widget.
	 *
	 * The decorative translucent glows stay in the stylesheet; these controls
	 * cover the text, backgrounds, borders, badges, stock meter and arrows.
	 */
	private function controls_offer_colors() {
		$groups = array(
			array(
				'label' => __( 'رنگ‌های کارت کمپین', 'hamishop-elementor-widgets' ),
				'items' => array(
					'offer_lead_title_color' => array( __( 'عنوان', 'hamishop-elementor-widgets' ), '--hsw-offer-lead-title', '#ffffff', false ),
					'offer_lead_sub_color'   => array( __( 'زیرعنوان', 'hamishop-elementor-widgets' ), '--hsw-offer-lead-sub', 'rgba(255, 241, 242, 0.9)', true ),
					'offer_lead_badge_bg'    => array( __( 'پس‌زمینه بج', 'hamishop-elementor-widgets' ), '--hsw-offer-lead-badge-bg', 'rgba(255, 255, 255, 0.15)', true ),
					'offer_lead_badge_text'  => array( __( 'متن بج', 'hamishop-elementor-widgets' ), '--hsw-offer-lead-badge-text', '#ffffff', false ),
					'offer_lead_badge_icon'  => array( __( 'آیکن بج', 'hamishop-elementor-widgets' ), '--hsw-offer-lead-badge-icon', '#fde047', false ),
					'offer_lead_icon_bg'     => array( __( 'پس‌زمینه آیکن وسط', 'hamishop-elementor-widgets' ), '--hsw-offer-lead-icon-bg', 'rgba(255, 255, 255, 0.15)', true ),
					'offer_lead_icon_color'  => array( __( 'آیکن وسط', 'hamishop-elementor-widgets' ), '--hsw-offer-lead-icon-color', '#ffffff', false ),
					'offer_timer_bg'         => array( __( 'پس‌زمینه تایمر', 'hamishop-elementor-widgets' ), '--hsw-offer-timer-bg', 'rgba(0, 0, 0, 0.35)', true ),
					'offer_timer_text'       => array( __( 'متن تایمر', 'hamishop-elementor-widgets' ), '--hsw-offer-timer-text', '#ffffff', false ),
					'offer_timer_label'      => array( __( 'زیرنویس تایمر', 'hamishop-elementor-widgets' ), '--hsw-offer-timer-label', 'rgba(255, 241, 242, 0.8)', true ),
					'offer_cta_bg'           => array( __( 'پس‌زمینه دکمه', 'hamishop-elementor-widgets' ), '--hsw-offer-cta-bg', '#ffffff', false ),
					'offer_cta_text'         => array( __( 'متن دکمه', 'hamishop-elementor-widgets' ), '--hsw-offer-cta-text', '#d91238', false ),
					'offer_cta_bg_hover'     => array( __( 'پس‌زمینه دکمه (هاور)', 'hamishop-elementor-widgets' ), '--hsw-offer-cta-bg-hover', '#fff1f2', false ),
					'offer_cta_text_hover'  => array( __( 'متن دکمه (هاور)', 'hamishop-elementor-widgets' ), '--hsw-offer-cta-text-hover', '#d91238', false ),
					'offer_shell_text'       => array( __( 'متن پوسته', 'hamishop-elementor-widgets' ), '--hsw-offer-shell-text', '#ffffff', false ),
					'offer_empty_text'       => array( __( 'متن حالت خالی', 'hamishop-elementor-widgets' ), '--hsw-offer-empty-text', '#ffffff', false ),
				),
			),
			array(
				'label' => __( 'رنگ‌های کارت محصول', 'hamishop-elementor-widgets' ),
				'items' => array(
					'offer_card_border_color' => array( __( 'حاشیه کارت', 'hamishop-elementor-widgets' ), '--hsw-offer-card-border', '#f1f5f9', false ),
					'offer_media_bg'          => array( __( 'پس‌زمینه تصویر', 'hamishop-elementor-widgets' ), '--hsw-offer-media-bg', '#f8fafc', false ),
					'offer_media_bg_hover'    => array( __( 'پس‌زمینه تصویر (هاور)', 'hamishop-elementor-widgets' ), '--hsw-offer-media-bg-hover', '#f1f5f9', false ),
					'offer_old_price'         => array( __( 'قیمت قدیمی', 'hamishop-elementor-widgets' ), '--hsw-offer-old', '#94a3b8', false ),
					'offer_discount_bg'       => array( __( 'پس‌زمینه بج تخفیف', 'hamishop-elementor-widgets' ), '--hsw-offer-discount-bg', '#d91238', false ),
					'offer_discount_text'     => array( __( 'متن بج تخفیف', 'hamishop-elementor-widgets' ), '--hsw-offer-discount-text', '#ffffff', false ),
					'offer_divider'           => array( __( 'خط جداکننده', 'hamishop-elementor-widgets' ), '--hsw-offer-divider', '#f1f5f9', false ),
				),
			),
			array(
				'label' => __( 'نوار موجودی', 'hamishop-elementor-widgets' ),
				'items' => array(
					'offer_stock_track' => array( __( 'ریل نوار', 'hamishop-elementor-widgets' ), '--hsw-offer-stock-track', '#f1f5f9', false ),
					'offer_stock_from'  => array( __( 'شروع رنگ نوار', 'hamishop-elementor-widgets' ), '--hsw-offer-stock-from', '#ef4444', false ),
					'offer_stock_to'    => array( __( 'پایان رنگ نوار', 'hamishop-elementor-widgets' ), '--hsw-offer-stock-to', '#e11d48', false ),
				),
			),
			array(
				'label' => __( 'برچسب‌های کارت', 'hamishop-elementor-widgets' ),
				'items' => array(
					'offer_note_green_bg'    => array( __( 'برچسب سبز — پس‌زمینه', 'hamishop-elementor-widgets' ), '--hsw-offer-note-green-bg', '#ecfdf5', false ),
					'offer_note_green_text'  => array( __( 'برچسب سبز — متن', 'hamishop-elementor-widgets' ), '--hsw-offer-note-green-text', '#047857', false ),
					'offer_note_amber_bg'    => array( __( 'برچسب کهربایی — پس‌زمینه', 'hamishop-elementor-widgets' ), '--hsw-offer-note-amber-bg', '#fffbeb', false ),
					'offer_note_amber_text'  => array( __( 'برچسب کهربایی — متن', 'hamishop-elementor-widgets' ), '--hsw-offer-note-amber-text', '#b45309', false ),
					'offer_note_blue_bg'     => array( __( 'برچسب آبی — پس‌زمینه', 'hamishop-elementor-widgets' ), '--hsw-offer-note-blue-bg', '#eff6ff', false ),
					'offer_note_blue_text'   => array( __( 'برچسب آبی — متن', 'hamishop-elementor-widgets' ), '--hsw-offer-note-blue-text', '#1d4ed8', false ),
					'offer_note_red_bg'      => array( __( 'برچسب قرمز — پس‌زمینه', 'hamishop-elementor-widgets' ), '--hsw-offer-note-red-bg', '#fff1f2', false ),
					'offer_note_red_text'    => array( __( 'برچسب قرمز — متن', 'hamishop-elementor-widgets' ), '--hsw-offer-note-red-text', '#d91238', false ),
				),
			),
			array(
				'label' => __( 'فلش‌ها', 'hamishop-elementor-widgets' ),
				'items' => array(
					'offer_arrow_bg'         => array( __( 'پس‌زمینه فلش', 'hamishop-elementor-widgets' ), '--hsw-offer-arrow-bg', '#ffffff', false ),
					'offer_arrow_text'       => array( __( 'آیکن فلش', 'hamishop-elementor-widgets' ), '--hsw-offer-arrow-text', '#334155', false ),
					'offer_arrow_border'     => array( __( 'حاشیه فلش', 'hamishop-elementor-widgets' ), '--hsw-offer-arrow-border', '#e2e8f0', false ),
					'offer_arrow_bg_hover'   => array( __( 'پس‌زمینه فلش (هاور)', 'hamishop-elementor-widgets' ), '--hsw-offer-arrow-bg-hover', '#f8fafc', false ),
					'offer_arrow_text_hover' => array( __( 'آیکن فلش (هاور)', 'hamishop-elementor-widgets' ), '--hsw-offer-arrow-text-hover', '#d91238', false ),
				),
			),
		);

		foreach ( $groups as $index => $group ) {
			$this->add_control(
				'offer_colors_group_' . (int) $index . '_h',
				array(
					'label'     => $group['label'],
					'type'      => Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);

			foreach ( $group['items'] as $id => $item ) {
				$this->offer_color_control( $id, $item[0], $item[1], $item[2], ! empty( $item[3] ) );
			}
		}
	}

	private function controls_offer_style() {
		$this->start_controls_section(
			'sec_offer_style',
			array(
				'label' => __( 'ظاهر آفر', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'offer_shell_from',
			array(
				'label'     => __( 'رنگ شروع گرادیان', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#d91238',
				'selectors' => array( '{{WRAPPER}} .hsw-offer' => '--hsw-offer-from: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'offer_shell_via',
			array(
				'label'     => __( 'رنگ میانه', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#e21a41',
				'selectors' => array( '{{WRAPPER}} .hsw-offer' => '--hsw-offer-via: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'offer_shell_to',
			array(
				'label'     => __( 'رنگ پایان گرادیان', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#e6123d',
				'selectors' => array( '{{WRAPPER}} .hsw-offer' => '--hsw-offer-to: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'offer_accent',
			array(
				'label'     => __( 'رنگ تاکید', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#d91238',
				'selectors' => array( '{{WRAPPER}} .hsw-offer' => '--hsw-offer-accent: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'offer_card_bg',
			array(
				'label'     => __( 'پس‌زمینه کارت', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array( '{{WRAPPER}} .hsw-offer__card' => 'background-color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'offer_card_text',
			array(
				'label'     => __( 'متن کارت', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#1e293b',
				'selectors' => array( '{{WRAPPER}} .hsw-offer' => '--hsw-offer-text: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'offer_muted',
			array(
				'label'     => __( 'متن کم‌رنگ', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#64748b',
				'selectors' => array( '{{WRAPPER}} .hsw-offer' => '--hsw-offer-muted: {{VALUE}};' ),
			)
		);

		$this->controls_offer_colors();

		$this->add_control(
			'offer_max_width',
			array(
				'label'      => __( 'حداکثر عرض', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 600,
						'max'  => 1600,
						'step' => 10,
					),
				),
				'default'    => array( 'size' => 1360 ),
				'selectors'  => array( '{{WRAPPER}} .hsw-offer' => '--hsw-offer-max: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'offer_radius',
			array(
				'label'      => __( 'گردی پوسته', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 0,
						'max'  => 48,
						'step' => 1,
					),
				),
				'default'    => array( 'size' => 24 ),
				'selectors'  => array( '{{WRAPPER}} .hsw-offer' => '--hsw-offer-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'offer_padding',
			array(
				'label'      => __( 'پدینگ پوسته', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 0,
						'max'  => 40,
						'step' => 1,
					),
				),
				'default'    => array( 'size' => 20 ),
				'selectors'  => array( '{{WRAPPER}} .hsw-offer' => '--hsw-offer-pad: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'offer_gap',
			array(
				'label'      => __( 'فاصله بین کارت‌ها', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 4,
						'max'  => 30,
						'step' => 1,
					),
				),
				'default'    => array( 'size' => 14 ),
				'selectors'  => array( '{{WRAPPER}} .hsw-offer' => '--hsw-offer-gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'offer_lead_width',
			array(
				'label'      => __( 'عرض کارت کمپین', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 200,
						'max'  => 360,
						'step' => 5,
					),
				),
				'default'    => array( 'size' => 280 ),
				'selectors'  => array( '{{WRAPPER}} .hsw-offer' => '--hsw-offer-lead-w: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'offer_card_width',
			array(
				'label'      => __( 'عرض کارت محصول', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 180,
						'max'  => 300,
						'step' => 5,
					),
				),
				'default'    => array( 'size' => 240 ),
				'selectors'  => array( '{{WRAPPER}} .hsw-offer' => '--hsw-offer-card-w: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'offer_lead_title_typo',
				'label'    => __( 'تایپوگرافی عنوان کمپین', 'hamishop-elementor-widgets' ),
				'selector' => '{{WRAPPER}} .hsw-offer__lead-title',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'offer_card_title_typo',
				'label'    => __( 'تایپوگرافی عنوان محصول', 'hamishop-elementor-widgets' ),
				'selector' => '{{WRAPPER}} .hsw-offer__name',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'offer_price_typo',
				'label'    => __( 'تایپوگرافی قیمت', 'hamishop-elementor-widgets' ),
				'selector' => '{{WRAPPER}} .hsw-offer__price',
			)
		);

		$this->end_controls_section();
	}

	/* =========================================================================
	 *  DATA
	 * ====================================================================== */

	/**
	 * Products limited to this widget's campaign.
	 *
	 * Automatic mode asks the cache for a wider pool (60) and then filters and
	 * sorts in PHP; sorting by discount/end is not a WooCommerce query order.
	 *
	 * @param array $s     Settings.
	 * @param int   $count Maximum output count.
	 * @param int   $min   Minimum discount percent.
	 * @param string $order Order key.
	 * @return WC_Product[]
	 */
	private function offer_products( $s, $count, $min, $order ) {
		$source = (string) ( $s['offer_source'] ?? 'auto' );
		$ids    = array_values( array_filter( array_map( 'absint', preg_split( '/[\s,]+/', (string) ( $s['offer_ids'] ?? '' ) ) ) ) );
		$ids    = array_slice( array_unique( $ids ), 0, self::MAX_PRODUCTS );

		if ( 'manual' === $source && $ids ) {
			$products = HSW_Plugin::instance()->products->query(
				array(
					'source'      => 'manual',
					'ids'         => $ids,
					'count'       => self::MAX_PRODUCTS,
					'exclude_oos' => 'yes' === ( $s['offer_exclude_oos'] ?? 'yes' ),
					'ttl'         => 300,
				)
			);
		} else {
			$products = HSW_Plugin::instance()->products->query(
				array(
					'source'      => 'sale',
					'count'       => 60,
					'exclude_oos' => 'yes' === ( $s['offer_exclude_oos'] ?? 'yes' ),
					'ttl'         => 300,
				)
			);
		}

		$rows = array();

		foreach ( $products as $product ) {
			if ( ! $product instanceof WC_Product || ! $product->is_on_sale() ) {
				continue;
			}

			$prices = $this->product_prices( $product );
			if ( $prices['percent'] < $min || $prices['sale'] <= 0 ) {
				continue;
			}

			$rows[] = array(
				'product' => $product,
				'percent' => $prices['percent'],
				'end'     => $this->product_sale_end( $product ),
				'date'    => (int) $product->get_date_created()->getTimestamp(),
				'sales'   => (int) $product->get_total_sales(),
			);
		}

		if ( 'manual' !== $source || ! $ids ) {
			switch ( $order ) {
				case 'end':
					usort( $rows, static function ( $a, $b ) {
						return ( $a['end'] ?: PHP_INT_MAX ) <=> ( $b['end'] ?: PHP_INT_MAX );
					} );
					break;
				case 'date':
					usort( $rows, static function ( $a, $b ) {
						return $b['date'] <=> $a['date'];
					} );
					break;
				case 'popularity':
					usort( $rows, static function ( $a, $b ) {
						return $b['sales'] <=> $a['sales'];
					} );
					break;
				case 'random':
					shuffle( $rows );
					break;
				case 'discount':
				default:
					usort( $rows, static function ( $a, $b ) {
						if ( $a['percent'] === $b['percent'] ) {
							return ( $a['end'] ?: PHP_INT_MAX ) <=> ( $b['end'] ?: PHP_INT_MAX );
						}
						return $b['percent'] <=> $a['percent'];
					} );
					break;
			}
		}

		$rows = array_slice( $rows, 0, $count );

		return array_map(
			static function ( $row ) {
				return $row['product'];
			},
			$rows
		);
	}

	/**
	 * Lowest variation regular/sale price and the resulting discount percent.
	 *
	 * Variable products use the same pair (`min` regular and `min` price) so the
	 * struck-through old price and the discount badge describe one variation,
	 * not two different ends of the range.
	 *
	 * @param WC_Product $product Product.
	 * @return array{regular:float,sale:float,percent:int}
	 */
	private function product_prices( $product ) {
		if ( $product->is_type( 'variable' ) ) {
			$regular = (float) $product->get_variation_regular_price( 'min', true );
			$sale    = (float) $product->get_variation_price( 'min', true );
		} else {
			$regular = (float) $product->get_regular_price();
			$sale    = (float) $product->get_price();
		}

		$percent = 0;
		if ( $regular > 0 && $sale > 0 && $sale < $regular ) {
			$percent = (int) round( ( 1 - ( $sale / $regular ) ) * 100 );
			$percent = max( 0, min( 99, $percent ) );
		}

		return array(
			'regular' => $regular,
			'sale'    => $sale,
			'percent' => $percent,
		);
	}

	/**
	 * Product sale end as a Unix timestamp, or 0 when WooCommerce has no date.
	 *
	 * @param WC_Product $product Product.
	 * @return int
	 */
	private function product_sale_end( $product ) {
		$date = $product->get_date_on_sale_to();
		return $date ? (int) $date->getTimestamp() : 0;
	}

	/**
	 * Campaign end: explicit control first, then the latest product sale end.
	 *
	 * @param array          $s        Settings.
	 * @param WC_Product[]   $products Products.
	 * @return int
	 */
	private function campaign_end( $s, $products ) {
		$raw = trim( (string) ( $s['offer_campaign_end'] ?? '' ) );
		if ( '' !== $raw ) {
			$date = date_create( $raw, wp_timezone() );
			if ( $date ) {
				return (int) $date->getTimestamp();
			}
		}

		$latest = 0;
		foreach ( $products as $product ) {
			$latest = max( $latest, $this->product_sale_end( $product ) );
		}

		return $latest;
	}

	/**
	 * Card note (small top badge) from real product signals.
	 *
	 * @param WC_Product $product Product.
	 * @param int        $percent Discount percent.
	 * @return array{text:string,class:string,icon:string}
	 */
	private function card_note( $product, $percent, $default_note = '' ) {
		$qty = $product->managing_stock() ? $product->get_stock_quantity() : null;

		if ( null !== $qty && $qty > 0 && $qty <= 3 ) {
			return array(
				'text'  => sprintf(
					/* translators: %s: remaining stock */
					__( 'تنها %s عدد مانده', 'hamishop-elementor-widgets' ),
					HSW_Helpers::fa_num( $qty )
				),
				'class' => 'is-red',
				'icon'  => 'warning',
			);
		}

		if ( $percent >= 40 ) {
			return array(
				'text'  => __( 'پیشنهاد لحظه‌ای', 'hamishop-elementor-widgets' ),
				'class' => 'is-amber',
				'icon'  => 'bolt',
			);
		}

		if ( $product->needs_shipping() ) {
			return array(
				'text'  => __( 'ارسال فوری', 'hamishop-elementor-widgets' ),
				'class' => 'is-blue',
				'icon'  => 'local_shipping',
			);
		}

		return array(
			'text'  => '' !== trim( (string) $default_note ) ? (string) $default_note : __( 'ضمانت اصالت', 'hamishop-elementor-widgets' ),
			'class' => 'is-green',
			'icon'  => 'verified',
		);
	}

	/**
	 * Stock meter data for a card.
	 *
	 * @param WC_Product $product Product.
	 * @return array{show:bool,percent:int,left:string}
	 */
	private function stock_data( $product ) {
		if ( ! $product->managing_stock() ) {
			return array(
				'show'    => false,
				'percent' => 0,
				'left'    => __( 'موجود', 'hamishop-elementor-widgets' ),
			);
		}

		$qty   = $product->get_stock_quantity();
		$qty   = null === $qty ? 0 : (int) $qty;
		$sales = max( 0, (int) $product->get_total_sales() );
		$base  = max( 1, $qty + $sales );
		$pct   = (int) round( ( $sales / $base ) * 100 );
		$pct   = max( 0, min( 100, $pct ) );

		if ( $qty <= 0 ) {
			$left = __( 'ناموجود', 'hamishop-elementor-widgets' );
		} elseif ( $qty <= 5 ) {
			/* translators: %s: remaining stock */
			$left = sprintf( __( 'تنها %s عدد مانده', 'hamishop-elementor-widgets' ), HSW_Helpers::fa_num( $qty ) );
		} else {
			$left = __( 'موجودی محدود', 'hamishop-elementor-widgets' );
		}

		return array(
			'show'    => true,
			'percent' => $pct,
			'left'    => $left,
		);
	}

	/**
	 * Countdown markup, safe for page caches: only the end timestamp is printed;
	 * the browser computes the remaining time on every tick.
	 *
	 * @param int    $end       Unix timestamp.
	 * @param string $class     Extra class.
	 * @param bool   $campaign  Campaign style (pill boxes) or card style.
	 * @return string
	 */
	private function countdown_html( $end, $class = '', $campaign = false, $ended_text = '' ) {
		if ( $end <= time() ) {
			return '';
		}

		$remaining = max( 0, $end - time() );
		$hours     = (int) floor( $remaining / 3600 );
		$minutes   = (int) floor( ( $remaining % 3600 ) / 60 );
		$seconds   = (int) ( $remaining % 60 );
		$classes   = trim( ( $campaign ? 'hsw-offer__countdown' : 'hsw-offer__timer' ) . ' ' . $class );

		$span = static function ( $unit, $value, $campaign ) {
			$class = $campaign ? 'hsw-offer__time' : 'hsw-offer__timer-part';
			return '<span class="' . esc_attr( $class ) . '" data-hsw-offer-unit="' . esc_attr( $unit ) . '">' .
				esc_html( HSW_Helpers::fa_num( str_pad( (string) $value, 2, '0', STR_PAD_LEFT ) ) ) .
				'</span>';
		};

		$sep = $campaign
			? '<span class="hsw-offer__time-sep" aria-hidden="true">:</span>'
			: '<span aria-hidden="true">:</span>';

		return '<span class="' . esc_attr( $classes ) . '" data-hsw-offer-end="' . esc_attr( (string) $end ) . '"' .
			( $campaign && '' !== $ended_text ? ' data-hsw-offer-ended-text="' . esc_attr( $ended_text ) . '"' : '' ) .
			' role="timer" aria-live="off">' .
			HSW_Helpers::icon( 'schedule' ) .
			$span( 'h', $hours, $campaign ) . $sep .
			$span( 'm', $minutes, $campaign ) . $sep .
			$span( 's', $seconds, $campaign ) .
			'</span>';
	}

	/**
	 * One product card.
	 *
	 * @param WC_Product $product Product.
	 * @param array      $s       Settings.
	 * @param int        $campaign_end Campaign end.
	 * @return string
	 */
	private function offer_card_html( $product, $s, $campaign_end, $index = 0 ) {
		$prices = $this->product_prices( $product );
		if ( $prices['sale'] <= 0 ) {
			return '';
		}

		$percent = $prices['percent'];
		$url     = $product->get_permalink();
		$name    = $product->get_name();
		$image_args = array(
			'loading' => 0 === $index ? 'eager' : 'lazy',
			'alt'     => $name,
		);
		if ( 0 === $index ) {
			$image_args['fetchpriority'] = 'high';
		}
		$image = $product->get_image( 'woocommerce_thumbnail', $image_args );
		$note    = $this->card_note( $product, $percent, $this->opt( 'offer_default_note' ) );
		$stock   = $this->stock_data( $product );
		$end     = $this->product_sale_end( $product );
		if ( ! $end ) {
			$end = $campaign_end;
		}

		ob_start();
		?>
		<a class="hsw-offer__card" href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( $name ); ?>">
			<div>
				<div class="hsw-offer__card-top">
					<?php if ( 'yes' === ( $s['offer_show_note'] ?? 'yes' ) ) : ?>
						<span class="hsw-offer__note <?php echo esc_attr( $note['class'] ); ?>">
							<?php echo HSW_Helpers::icon( $note['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span><?php echo esc_html( $note['text'] ); ?></span>
						</span>
					<?php else : ?>
						<span></span>
					<?php endif; ?>

					<?php if ( $percent > 0 ) : ?>
						<span class="hsw-offer__discount"><?php echo esc_html( sprintf( __( '%s٪ تخفیف', 'hamishop-elementor-widgets' ), HSW_Helpers::fa_num( $percent ) ) ); ?></span>
					<?php endif; ?>
				</div>

				<div class="hsw-offer__media"><?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<h3 class="hsw-offer__name"><?php echo esc_html( $name ); ?></h3>
			</div>

			<div class="hsw-offer__bottom">
				<div class="hsw-offer__prices">
					<?php if ( $prices['regular'] > $prices['sale'] ) : ?>
						<del class="hsw-offer__old"><?php echo esc_html( HSW_Helpers::price_num( $prices['regular'] ) ); ?></del>
					<?php else : ?>
						<span></span>
					<?php endif; ?>
					<span class="hsw-offer__price">
						<?php echo esc_html( HSW_Helpers::price_num( $prices['sale'] ) ); ?>
						<span class="hsw-offer__currency"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
					</span>
				</div>

				<?php if ( 'yes' === ( $s['offer_show_stock'] ?? 'yes' ) && $stock['show'] ) : ?>
					<div class="hsw-offer__stock">
						<div class="hsw-offer__stockbar"><span style="inline-size:<?php echo esc_attr( (string) $stock['percent'] ); ?>%"></span></div>
						<div class="hsw-offer__stockmeta">
							<strong><?php echo esc_html( $stock['left'] ); ?></strong>
							<span><?php echo esc_html( sprintf( __( 'فروش رفته: %s٪', 'hamishop-elementor-widgets' ), HSW_Helpers::fa_num( $stock['percent'] ) ) ); ?></span>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( 'yes' === ( $s['offer_show_card_timer'] ?? 'yes' ) && $end > time() ) : ?>
					<?php echo $this->countdown_html( $end, '', false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
			</div>
		</a>
		<?php
		return (string) ob_get_clean();
	}

	/* =========================================================================
	 *  RENDER
	 * ====================================================================== */

	protected function render() {
		$s    = $this->effective_settings();
		$count = max( 1, min( self::MAX_PRODUCTS, (int) ( $s['offer_count']['size'] ?? 6 ) ) );
		$min   = max( 0, min( 90, (int) ( $s['offer_min_discount'] ?? 0 ) ) );
		$order = (string) ( $s['offer_order'] ?? 'discount' );

		$products = $this->offer_products( $s, $count, $min, $order );

		if ( empty( $products ) ) {
			if ( 'yes' === ( $s['offer_hide_empty'] ?? '' ) ) {
				return;
			}
			echo '<section class="hsw-offer" data-hsw-offer><div class="hsw-offer__shell"><div class="hsw-offer__empty">' .
				esc_html__( 'محصولی با تخفیف برای این کمپین پیدا نشد.', 'hamishop-elementor-widgets' ) .
				'</div></div></section>';
			return;
		}

		$campaign_end = $this->campaign_end( $s, $products );
		$title        = (string) ( $s['offer_title'] ?? "پیشنهاد\nشگفت‌انگیز" );
		$badge        = $this->opt( 'offer_badge' );
		$sub          = $this->opt( 'offer_sub' );
		$icon         = $this->opt( 'offer_icon', 'redeem' );
		$cta_text     = $this->opt( 'offer_cta_text' );
		$cta_url      = (string) ( $s['offer_cta_url']['url'] ?? '' );
		if ( '' === $cta_url ) {
			$cta_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';
		}
		$show_timer   = 'yes' === ( $s['offer_show_campaign_timer'] ?? 'yes' ) && $campaign_end > time();

		echo '<section class="hsw-offer' . esc_attr( $this->preset_class( $s ) . $this->hsw_font_class() ) . '" data-hsw-offer>';
		echo '<div class="hsw-offer__shell"><div class="hsw-offer__layout">';

		/* Lead campaign card. */
		echo '<aside class="hsw-offer__lead">';
		echo '<div>';
		if ( '' !== $badge ) {
			echo '<span class="hsw-offer__lead-badge">' . HSW_Helpers::icon( 'bolt' ) . '<span>' . esc_html( $badge ) . '</span></span>';
		}
		$title_tag = (string) ( $s['offer_title_tag'] ?? 'h2' );
		if ( ! in_array( $title_tag, array( 'h2', 'h3', 'div', 'span' ), true ) ) {
			$title_tag = 'h2';
		}
		echo '<' . $title_tag . ' class="hsw-offer__lead-title">' . wp_kses_post( nl2br( esc_html( $title ) ) ) . '</' . $title_tag . '>';
		if ( '' !== $sub ) {
			echo '<p class="hsw-offer__lead-sub">' . esc_html( $sub ) . '</p>';
		}
		echo '</div>';

		if ( '' !== $icon ) {
			echo '<div class="hsw-offer__lead-icon">' . HSW_Helpers::icon( $icon ) . '</div>';
		}

		echo '<div class="hsw-offer__lead-bottom">';
		if ( $show_timer ) {
			echo $this->countdown_html( $campaign_end, '', true, $this->opt( 'offer_end_text' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$label = $this->opt( 'offer_timer_label' );
			if ( '' !== $label ) {
				echo '<p class="hsw-offer__countdown-label">' . esc_html( $label ) . '</p>';
			}
		}
		if ( '' !== $cta_text ) {
			echo '<a class="hsw-offer__cta" href="' . esc_url( $cta_url ) . '"><span>' . esc_html( $cta_text ) . '</span>' . HSW_Helpers::icon( 'arrow_back' ) . '</a>';
		}
		echo '</div></aside>';

		/* Rail. */
		echo '<div class="hsw-offer__main">';
		echo '<div class="hsw-offer__rail" data-hsw-offer-rail role="group" aria-label="' . esc_attr__( 'محصولات آفر', 'hamishop-elementor-widgets' ) . '" tabindex="0">';
		foreach ( $products as $index => $product ) {
			echo $this->offer_card_html( $product, $s, $campaign_end, (int) $index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( '' !== $cta_text ) {
			echo '<a class="hsw-offer__more" href="' . esc_url( $cta_url ) . '">' .
				'<span class="hsw-offer__more-icon">' . HSW_Helpers::icon( 'arrow_back' ) . '</span>' .
				'<span>' . esc_html( $cta_text ) . '</span>' .
				'</a>';
		}
		echo '</div>';
		echo '<button type="button" class="hsw-offer__arrow hsw-offer__arrow--prev" data-hsw-offer-dir="-1" aria-label="' . esc_attr__( 'محصولات قبلی', 'hamishop-elementor-widgets' ) . '">' . HSW_Helpers::icon( 'chevron_right' ) . HSW_Helpers::icon( 'chevron_right' ) . '</button>';
		echo '<button type="button" class="hsw-offer__arrow hsw-offer__arrow--next" data-hsw-offer-dir="1" aria-label="' . esc_attr__( 'محصولات بعدی', 'hamishop-elementor-widgets' ) . '">' . HSW_Helpers::icon( 'chevron_left' ) . HSW_Helpers::icon( 'chevron_left' ) . '</button>';
		echo '</div>';

		echo '</div></div></section>';
	}
}
