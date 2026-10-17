<?php
/**
 * Widget: پرفروش‌ترین‌ها (Best Sellers) — Section 2 of the supplied design.
 * White box shell, icon header + "مشاهده فهرست کامل" link + arrows,
 * ranked horizontal carousel with ۱/۲/۳ colored rank badges and "خرید" button.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Widget_Best_Sellers extends HSW_Widget_Base {

	public function get_name() {
		return 'hsw-best-sellers';
	}

	public function get_title() {
		return __( 'پرفروش‌ترین‌ها (HamiShop)', 'hamishop-elementor-widgets' );
	}

	public function get_icon() {
		return 'eicon-rating';
	}

	public function get_keywords() {
		return array( 'پرفروش', 'رتبه', 'best', 'seller', 'top', 'hamishop' );
	}

	protected function register_controls() {
		/* ------------------------- CONTENT ------------------------- */
		$this->controls_source( 'best_selling' );
		$this->controls_header(
			array(
				'icon'   => 'trending_up',
				'title'  => __( 'پرفروش‌ترین‌های دیجیتال', 'hamishop-elementor-widgets' ),
				'badge'  => '',
				'sub'    => __( 'محبوب‌ترین خریدهای مشتریان در هفته گذشته به ترتیب امتیاز', 'hamishop-elementor-widgets' ),
				'link_d' => __( 'مشاهده فهرست کامل', 'hamishop-elementor-widgets' ),
				'link_m' => __( 'مشاهده همه', 'hamishop-elementor-widgets' ),
			)
		);
		$this->controls_card_content(
			array(
				'show_rank'   => 'yes',
				'price_label' => __( 'قیمت قطعی:', 'hamishop-elementor-widgets' ),
				'txt_buy'     => 'خرید',
				'ico_add'     => 'add_shopping_cart',
				'show_stock'  => false,
			)
		);

		/* ------------------------- STYLE ------------------------- */
		$this->controls_shell( 'box' );
		$this->controls_header_style();
		$this->controls_countdown_style();
		$this->controls_card_style();
		$this->controls_badge_style();
		$this->controls_price_style();
	}

	protected function render() {
		$s        = $this->get_settings_for_display();
		$products = $this->resolve_products();
		$args     = $this->renderer_args();

		$show_header = 'yes' === ( $s['show_header'] ?? 'yes' );
		$link_url    = $s['hdr_link_url']['url'] ?? '';
		?>
		<section class="hsw-loop hsw-loop--box hsw-loop--best hsw-loop--hsw-best-sellers">

			<?php if ( $show_header ) : ?>
			<header class="hsw-hdr">
				<div class="hsw-hdr__main">
					<div class="hsw-hdr__brand">
						<span class="hsw-hdr__bar" aria-hidden="true"></span>
						<span class="hsw-hdr__icon hsw-d"><?php echo HSW_Helpers::icon( $s['hdr_icon'] ?? 'trending_up' ); // phpcs:ignore ?></span>
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
						<?php if ( ! empty( $s['hdr_link_d'] ) ) : ?>
							<a class="hsw-hdr__link" href="<?php echo esc_url( $link_url ? $link_url : ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '#' ) ); ?>">
								<span class="hsw-d"><?php echo esc_html( $s['hdr_link_d'] ); ?></span>
								<span class="hsw-m"><?php echo esc_html( $s['hdr_link_m'] ?? $s['hdr_link_d'] ); ?></span>
								<?php echo HSW_Helpers::icon( 'arrow_back', 'hsw-d' ); // phpcs:ignore ?>
								<?php echo HSW_Helpers::icon( 'chevron_left', 'hsw-m' ); // phpcs:ignore ?>
							</a>
						<?php endif; ?>

						<div class="hsw-arrows hsw-d">
							<button type="button" class="hsw-arrow" data-hsw-dir="-1" aria-label="<?php esc_attr_e( 'قبلی', 'hamishop-elementor-widgets' ); ?>"><?php echo HSW_Helpers::icon( 'chevron_right' ); // phpcs:ignore ?></button>
							<button type="button" class="hsw-arrow" data-hsw-dir="1" aria-label="<?php esc_attr_e( 'بعدی', 'hamishop-elementor-widgets' ); ?>"><?php echo HSW_Helpers::icon( 'chevron_left' ); // phpcs:ignore ?></button>
						</div>
					</div>
				</div>
			</header>
			<?php endif; ?>

			<div class="hsw-track">
				<?php
				$cards = self::cards_html( 'best', $products, $args );
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
