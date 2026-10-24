<?php
/**
 * Widget: محصولات مرتبط — a horizontal product carousel.
 *
 * 4 cards on desktop, 2.5 on tablet, 1.5 on mobile, with a soft edge fade and
 * two arrow buttons (RTL-aware). The source can be WooCommerce's own related
 * products, the same category, upsells or the best sellers.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Product_Related extends HSW_Product_Base {

	/**
	 * Widget slug.
	 */
	public function get_name() {
		return 'hsw-product-related';
	}

	/**
	 * Widget title.
	 */
	public function get_title() {
		return __( 'محصولات مرتبط (HamiShop)', 'hamishop-elementor-widgets' );
	}

	/**
	 * Widget icon.
	 */
	public function get_icon() {
		return 'eicon-products';
	}

	/**
	 * Widget keywords.
	 */
	public function get_keywords() {
		return array( 'related', 'مرتبط', 'کاروسل', 'محصولات', 'hamishop', 'hamisys' );
	}

	/**
	 * Controls.
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'sec',
			array(
				'label' => __( 'محصولات مرتبط', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'vis',
			array(
				'label'        => __( 'نمایش بخش', 'hamishop-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'title',
			array(
				'label'   => __( 'عنوان', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'محصولات مرتبط', 'hamishop-elementor-widgets' ),
			)
		);

		$this->add_control(
			'sub',
			array(
				'label'       => __( 'زیرعنوان (اختیاری)', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
			)
		);

		$this->add_control(
			'source',
			array(
				'label'   => __( 'منبع محصولات', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'related',
				'options' => array(
					'related'      => __( 'محصولات مرتبط ووکامرس', 'hamishop-elementor-widgets' ),
					'category'     => __( 'محصولات هم‌دسته', 'hamishop-elementor-widgets' ),
					'upsells'      => __( 'محصولات تشویقی (Upsells)', 'hamishop-elementor-widgets' ),
					'best_selling' => __( 'پرفروش‌ترین‌ها', 'hamishop-elementor-widgets' ),
				),
			)
		);

		$this->add_control(
			'count',
			array(
				'label'   => __( 'تعداد محصولات', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 2,
				'max'     => 20,
				'default' => 8,
			)
		);

		$this->add_control(
			'more_text',
			array(
				'label'   => __( 'متن لینک «مشاهده همه»', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'مشاهده همه', 'hamishop-elementor-widgets' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Products for the carousel.
	 *
	 * @param int $limit Max items.
	 * @return WC_Product[]
	 */
	protected function products( $limit ) {
		/* HSW_Product_Base exposes product_or_demo(), not product(). */
		list( $product ) = $this->product_or_demo();
		if ( ! $product instanceof WC_Product ) {
			return array();
		}

		$settings = $this->effective_settings();
		$source   = isset( $settings['source'] ) ? (string) $settings['source'] : 'related';
		$ids    = array();

		if ( 'upsells' === $source ) {
			$ids = $product->get_upsell_ids();
		} elseif ( 'category' === $source ) {
			$terms = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
			$ids   = $terms ? get_posts(
				array(
					'post_type'      => 'product',
					'posts_per_page' => $limit,
					'post__not_in'   => array( $product->get_id() ),
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'tax_query'      => array(
						array(
							'taxonomy' => 'product_cat',
							'field'    => 'term_id',
							'terms'    => $terms,
						),
					),
				)
			) : array();
		} elseif ( 'best_selling' === $source ) {
			$ids = wc_get_products(
				array(
					'limit'    => $limit,
					'status'   => 'publish',
					'orderby'  => 'meta_value_num',
					'meta_key' => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'return'   => 'ids',
				)
			);
		} else {
			$ids = wc_get_related_products( $product->get_id(), $limit, array( $product->get_id() ) );
		}

		$ids = array_slice( array_filter( array_map( 'intval', (array) $ids ) ), 0, $limit );
		if ( empty( $ids ) ) {
			return array();
		}

		$out = array();
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( $p && $p->is_visible() ) {
				$out[] = $p;
			}
		}
		return $out;
	}

	/**
	 * Render.
	 */
	protected function render() {
		/*
		 * HSW_Product_Base has no opt() helper (that lives on the archive/cart
		 * bases); settings come from effective_settings(), which is also what
		 * the other product widgets use.
		 */
		$s = $this->effective_settings();

		if ( isset( $s['vis'] ) && 'yes' !== $s['vis'] ) {
			return;
		}

		$limit    = max( 2, min( 20, isset( $s['count'] ) ? (int) $s['count'] : 8 ) );
		$products = $this->products( $limit );

		if ( empty( $products ) ) {
			return;
		}

		$title = ( isset( $s['title'] ) && '' !== trim( (string) $s['title'] ) ) ? (string) $s['title'] : __( 'محصولات مرتبط', 'hamishop-elementor-widgets' );
		$sub   = isset( $s['sub'] ) ? (string) $s['sub'] : '';
		$more  = isset( $s['more_text'] ) ? (string) $s['more_text'] : '';
		$url   = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		?>
		<section class="<?php echo esc_attr( $this->root_class( 'prel' ) ); ?>" data-hsw-carousel>
			<header class="hsw-prel__head">
				<div class="hsw-prel__headtext">
					<h2 class="hsw-prel__title"><?php echo esc_html( $title ); ?></h2>
					<?php if ( '' !== trim( $sub ) ) : ?>
						<p class="hsw-prel__sub"><?php echo esc_html( $sub ); ?></p>
					<?php endif; ?>
				</div>
				<?php if ( '' !== trim( $more ) ) : ?>
					<a class="hsw-prel__more" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $more ); ?><?php echo HSW_Helpers::icon( 'arrow_back' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
				<?php endif; ?>
			</header>

			<div class="hsw-prel__wrap">
				<div class="hsw-prel__track" data-hsw-track>
					<?php foreach ( $products as $p ) : ?>
						<?php
						$pid   = $p->get_id();
						$link  = get_permalink( $pid );
						$img   = get_the_post_thumbnail_url( $pid, 'woocommerce_thumbnail' );
						$price = $p->get_price_html();
						$sale  = $p->is_on_sale();
						?>
						<article class="hsw-prel__card">
							<a class="hsw-prel__media" href="<?php echo esc_url( $link ); ?>">
								<?php if ( $img ) : ?>
									<img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $p->get_name() ); ?>" loading="lazy" decoding="async">
								<?php else : ?>
									<span class="hsw-prel__ph"><?php echo HSW_Helpers::icon( 'image' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<?php endif; ?>
								<?php if ( $sale ) : ?>
									<span class="hsw-prel__badge"><?php esc_html_e( 'تخفیف', 'hamishop-elementor-widgets' ); ?></span>
								<?php endif; ?>
							</a>
							<a class="hsw-prel__name" href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $p->get_name() ); ?></a>
							<div class="hsw-prel__price"><?php echo wp_kses_post( $price ); ?></div>
							<a class="hsw-prel__cta" href="<?php echo esc_url( $link ); ?>"><?php esc_html_e( 'مشاهده محصول', 'hamishop-elementor-widgets' ); ?></a>
						</article>
					<?php endforeach; ?>
				</div>

				<span class="hsw-prel__fade hsw-prel__fade--left" data-hsw-fade="next" aria-hidden="true"></span>
				<span class="hsw-prel__fade hsw-prel__fade--right" data-hsw-fade="prev" aria-hidden="true" hidden></span>
				<button type="button" class="hsw-prel__nav hsw-prel__nav--next" data-hsw-nav="next" aria-label="<?php esc_attr_e( 'بعدی', 'hamishop-elementor-widgets' ); ?>"><?php echo HSW_Helpers::icon( 'chevron_left' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				<button type="button" class="hsw-prel__nav hsw-prel__nav--prev" data-hsw-nav="prev" aria-label="<?php esc_attr_e( 'قبلی', 'hamishop-elementor-widgets' ); ?>"><?php echo HSW_Helpers::icon( 'chevron_right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
			</div>
		</section>
		<?php
	}
}
