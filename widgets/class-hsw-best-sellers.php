<?php
/**
 * Widget: پرفروش‌ترین‌ها (Best Sellers) — Section 2 of the supplied design.
 *
 * The query can be scoped to the last 7 days, the last 30 days or all time.
 * Rank numbers come from the rendered order, so a filtered/lazy fragment and
 * the first page load always agree.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Widget_Best_Sellers extends HSW_Widget_Base {

	public function get_name() {
		return 'hsw-best-sellers';
	}

	/**
	 * Loop bundle (CSS + JS) — declared here because the base no longer defaults
	 * to hsw-loop for every widget.
	 *
	 * @return array
	 */
	public function get_style_depends() {
		return array( 'hsw-fonts', 'hsw-loop-box' );
	}

	/**
	 * @return array
	 */
	public function get_script_depends() {
		return array( 'hsw-loop' );
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
		$this->controls_best_range();
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
		$this->controls_theme();
		$this->controls_shell( 'box' );
		$this->controls_header_style();
		$this->controls_countdown_style();
		$this->controls_card_style();
		$this->controls_badge_style();
		$this->controls_stock_style();
		$this->controls_price_style();
		$this->controls_button_style();
	}

	protected function render() {
		$s     = $this->effective_settings();
		$range = (string) ( $s['best_range'] ?? '7' );
		$days  = 'all' === $range ? 0 : max( 1, (int) $range );

		$products = $this->resolve_products( array( 'days' => $days ) );
		$args     = $this->renderer_args();

		$cards = (float) ( $s['cards']['size'] ?? 4 );
		$fit   = $cards > 0 ? 'cards' : 'fixed';
		$title = trim( (string) ( $s['hdr_title'] ?? '' ) );
		?>
		<section class="hsw-loop hsw-loop--box hsw-loop--best hsw-loop--hsw-best-sellers<?php echo esc_attr( $this->preset_class( $s ) . $this->hsw_font_class() ); ?>" data-hsw-fit="<?php echo esc_attr( $fit ); ?>">
			<?php
			if ( 'yes' === ( $s['show_header'] ?? 'yes' ) ) {
				echo $this->header_html( $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			} else {
				echo '<div class="hsw-loop__bar">' . $this->arrows_html() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			if ( empty( $products ) ) {
				echo '<div class="hsw-loop__empty">' . esc_html__( 'محصولی برای نمایش یافت نشد.', 'hamishop-elementor-widgets' ) . '</div>';
			} else {
				echo '<div class="hsw-loop__row"><div class="hsw-loop__rail" id="' . esc_attr( $this->rail_id() ) . '" data-hsw-rail role="group" aria-label="' . esc_attr( '' !== $title ? $title : __( 'پرفروش‌ترین محصولات', 'hamishop-elementor-widgets' ) ) . '" tabindex="0">';
				echo self::cards_html( 'best', $products, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
				echo '</div></div>';
			}
			?>
		</section>
		<?php
	}
}
