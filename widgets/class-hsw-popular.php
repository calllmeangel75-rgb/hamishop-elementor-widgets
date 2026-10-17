<?php
/**
 * Widget: محبوب‌ترین‌ها / منتخب هوشمند (Most Popular) — Section 3 of the supplied design.
 * White box shell, icon header + interactive filter tabs (محبوب‌ترین‌ها /
 * جدیدترین‌ها / بیشترین تخفیف) switching server-rendered fragments,
 * horizontal carousel with featured/shipping/sale badges + OOS state.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Widget_Popular extends HSW_Widget_Base {

	public function get_name() {
		return 'hsw-popular';
	}

	public function get_title() {
		return __( 'محبوب‌ترین‌ها / منتخب (HamiShop)', 'hamishop-elementor-widgets' );
	}

	public function get_icon() {
		return 'eicon-tabs';
	}

	public function get_keywords() {
		return array( 'محبوب', 'منتخب', 'تب', 'جدیدترین', 'popular', 'tab', 'hamishop' );
	}

	protected function register_controls() {
		/* ------------------------- CONTENT ------------------------- */
		$this->controls_source( 'rating' );
		$this->controls_header(
			array(
				'icon'   => 'auto_awesome',
				'title'  => __( 'منتخب هوشمند کالاها', 'hamishop-elementor-widgets' ),
				'badge'  => '',
				'sub'    => __( 'بر اساس علایق و تازه‌ترین ورودی‌های انبار', 'hamishop-elementor-widgets' ),
				'link_d' => '',
				'link_m' => '',
			)
		);

		/* Tabs */
		$this->start_controls_section(
			'sec_tabs',
			array( 'label' => __( 'تب‌های فیلتر', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
		);

		$this->add_control(
			'show_tabs',
			array(
				'label'   => __( 'نمایش تب‌ها', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$repeater = new Repeater();
		$repeater->add_control(
			'tab_label',
			array(
				'label'   => __( 'عنوان تب', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'محبوب‌ترین‌ها', 'hamishop-elementor-widgets' ),
			)
		);
		$repeater->add_control(
			'tab_source',
			array(
				'label'   => __( 'منبع', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'rating',
				'options' => array(
					'rating'   => __( 'محبوب‌ترین (امتیاز)', 'hamishop-elementor-widgets' ),
					'newest'   => __( 'جدیدترین', 'hamishop-elementor-widgets' ),
					'discount' => __( 'بیشترین تخفیف', 'hamishop-elementor-widgets' ),
					'sale'     => __( 'تخفیف‌دارها', 'hamishop-elementor-widgets' ),
					'best_selling' => __( 'پرفروش‌ترین', 'hamishop-elementor-widgets' ),
					'featured' => __( 'منتخب (Featured)', 'hamishop-elementor-widgets' ),
				),
			)
		);

		$default_tabs = array(
			array( 'tab_label' => __( 'محبوب‌ترین‌ها', 'hamishop-elementor-widgets' ), 'tab_source' => 'rating' ),
			array( 'tab_label' => __( 'جدیدترین‌ها', 'hamishop-elementor-widgets' ), 'tab_source' => 'newest' ),
			array( 'tab_label' => __( 'بیشترین تخفیف', 'hamishop-elementor-widgets' ), 'tab_source' => 'discount' ),
		);

		$this->add_control(
			'tabs',
			array(
				'label'       => __( 'تب‌ها', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => $default_tabs,
				'title_field' => '{{{ tab_label }}}',
				'condition'   => array( 'show_tabs' => 'yes' ),
			)
		);

		$this->add_control(
			'hdr_note',
			array(
				'label'       => __( 'یادداشت کنار عنوان (موبایل)', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'بر اساس امتیاز کاربران', 'hamishop-elementor-widgets' ),
				'description' => __( 'در موبایل کنار عنوان نمایش داده می‌شود.', 'hamishop-elementor-widgets' ),
			)
		);

		$this->end_controls_section();

		$this->controls_card_content(
			array(
				'show_rank'   => false,
				'show_stock'  => false,
				'price_label' => false,
				'txt_buy'     => false,
			)
		);

		/* ------------------------- STYLE ------------------------- */
		$this->controls_shell( 'box' );
		$this->controls_header_style();
		$this->controls_card_style();
		$this->controls_badge_style();
		$this->controls_price_style();

		/* Tabs style */
		$this->start_controls_section(
			'sec_style_tabs',
			array( 'label' => __( 'استایل تب‌ها', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
		);
		$this->add_control( 'tabs_bg', array( 'label' => __( 'پس‌زمینه کانتینر', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-tabs' => '--hsw-tabs-bg: {{VALUE}};' ) ) );
		$this->add_control( 'tabs_border', array( 'label' => __( 'حاشیه کانتینر', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-tabs' => '--hsw-tabs-border: {{VALUE}};' ) ) );
		$this->add_control( 'tab_active_bg', array( 'label' => __( 'پس‌زمینه تب فعال', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-tab.is-active' => '--hsw-tab-active-bg: {{VALUE}};' ) ) );
		$this->add_control( 'tab_active_c', array( 'label' => __( 'رنگ تب فعال', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-tab.is-active' => '--hsw-tab-active-c: {{VALUE}};' ) ) );
		$this->add_control( 'tab_c', array( 'label' => __( 'رنگ تب غیرفعال', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-tab' => '--hsw-tab-c: {{VALUE}};' ) ) );
		$this->add_control(
			'tab_typo',
			array(
				'label'     => __( 'تایپوگرافی تب‌ها', 'hamishop-elementor-widgets' ),
				'type'      => Group_Control_Typography::get_type(),
				'selector'  => '{{WRAPPER}} .hsw-tab',
			)
		);
		$this->end_controls_section();
	}

	protected function render() {
		$s        = $this->get_settings_for_display();
		$products = $this->resolve_products();
		$args     = $this->renderer_args();

		$show_header = 'yes' === ( $s['show_header'] ?? 'yes' );
		$show_tabs   = 'yes' === ( $s['show_tabs'] ?? 'yes' );
		$tabs        = is_array( $s['tabs'] ?? null ) ? $s['tabs'] : array();

		/* Endpoint + signature for tab fragments */
		$sig      = md5( wp_salt( 'auth' ) . '|hsw|popular' );
		$endpoint = esc_url( rest_url( 'hsw/v1/loop' ) );
		$count    = max( 1, (int) ( $s['count']['size'] ?? 8 ) );
		$term_id  = isset( $s['source_term'] ) ? (int) $s['source_term'] : 0;
		?>
		<section class="hsw-loop hsw-loop--box hsw-loop--pop hsw-loop--hsw-popular"
			<?php if ( $show_tabs && ! empty( $tabs ) ) : ?>
			data-hsw-endpoint="<?php echo esc_url( add_query_arg( 'sig', $sig, $endpoint ) ); ?>"
			<?php endif; ?>
		>

			<?php if ( $show_header ) : ?>
			<header class="hsw-hdr">
				<div class="hsw-hdr__main">
					<div class="hsw-hdr__brand">
						<span class="hsw-hdr__bar" aria-hidden="true"></span>
						<span class="hsw-hdr__icon hsw-d"><?php echo HSW_Helpers::icon( $s['hdr_icon'] ?? 'auto_awesome' ); // phpcs:ignore ?></span>
						<div class="hsw-hdr__titles">
							<div class="hsw-hdr__row">
								<h2 class="hsw-hdr__title"><?php echo esc_html( $s['hdr_title'] ?? '' ); ?></h2>
							</div>
							<?php if ( ! empty( $s['hdr_sub'] ) ) : ?>
								<p class="hsw-hdr__sub"><?php echo esc_html( $s['hdr_sub'] ); ?></p>
							<?php endif; ?>
						</div>
					</div>

					<div class="hsw-hdr__side">
						<?php if ( ! empty( $s['hdr_note'] ) ) : ?>
							<span class="hsw-hdr__note hsw-m"><?php echo HSW_Helpers::icon( 'favorite' ); // phpcs:ignore ?><span><?php echo esc_html( $s['hdr_note'] ); ?></span></span>
						<?php endif; ?>

						<?php if ( $show_tabs && ! empty( $tabs ) ) : ?>
							<div class="hsw-tabs hsw-d" role="tablist">
								<?php $first = true; ?>
								<?php foreach ( $tabs as $tab ) : ?>
									<button type="button" role="tab" aria-selected="<?php echo $first ? 'true' : 'false'; ?>"
										class="hsw-tab<?php echo $first ? ' is-active' : ''; ?>"
										data-hsw-query="<?php echo esc_attr( http_build_query( array( 'type' => 'popular', 'source' => $tab['tab_source'], 'per_page' => $count, 'term_id' => $term_id, 'sig' => $sig ) ) ); ?>">
										<?php echo esc_html( $tab['tab_label'] ); ?>
									</button>
									<?php $first = false; ?>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</header>
			<?php endif; ?>

			<div class="hsw-track">
				<?php
				$cards = self::cards_html( 'popular', $products, $args );
				if ( '' === $cards ) {
					echo '<div class="hsw-empty">' . esc_html__( 'محصولی برای نمایش یافت نشد.', 'hamishop-elementor-widgets' ) . '</div>';
				} else {
					echo $cards; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
				}
				?>
			</div>
		</section>
		<?php
	}
}
