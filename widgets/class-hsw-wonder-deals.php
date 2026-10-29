<?php
/**
 * Widget: پیشنهاد شگفت‌انگیز (Wonder Deals) — Section 1 of the supplied design.
 *
 * One shared card/rail system with 1–3 rows per instance. The dashboard can
 * place the widget more than once for longer feeds; every instance still has a
 * single timer and a single query, so the page never pays for a second query
 * just to show another row.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Widget_Wonder_Deals extends HSW_Widget_Base {

	public function get_name() {
		return 'hsw-wonder-deals';
	}

	/**
	 * Loop bundle (CSS + JS) — declared here because the base no longer defaults
	 * to hsw-loop for every widget.
	 *
	 * @return array
	 */
	public function get_style_depends() {
		return array( 'hsw-fonts', 'hsw-loop-deal' );
	}

	/**
	 * @return array
	 */
	public function get_script_depends() {
		return array( 'hsw-loop' );
	}

	public function get_title() {
		return __( 'پیشنهاد شگفت‌انگیز (HamiShop)', 'hamishop-elementor-widgets' );
	}

	public function get_icon() {
		return 'eicon-fire';
	}

	public function get_keywords() {
		return array( 'شگفت انگیز', 'پیشنهاد ویژه', 'حراج', 'تخفیف', 'wonder', 'deal', 'sale', 'hamishop' );
	}

	protected function register_controls() {
		/* ------------------------- CONTENT ------------------------- */
		$this->controls_source( 'sale' );
		$this->controls_deal_rows();
		$this->controls_header(
			array(
				'icon'   => 'local_fire_department',
				'title'  => __( 'پیشنهاد شگفت‌انگیز روز', 'hamishop-elementor-widgets' ),
				'badge'  => __( 'تخفیف تا ۴۵٪', 'hamishop-elementor-widgets' ),
				'sub'    => __( 'فرصت طلایی خرید برترین کالاهای دیجیتال با تضمین بهترین قیمت بازار', 'hamishop-elementor-widgets' ),
				'link_d' => '',
				'link_m' => '',
			)
		);
		$this->controls_countdown();
		$this->controls_lead();
		$this->controls_card_content(
			array(
				'show_rank'   => false,
				'price_label' => false,
				'txt_buy'     => '',
			)
		);

		/* ------------------------- STYLE ------------------------- */
		$this->controls_theme();
		$this->controls_shell( 'deal' );
		$this->controls_header_style();
		$this->controls_countdown_style();
		$this->controls_card_style();
		$this->controls_badge_style();
		$this->controls_stock_style();
		$this->controls_price_style();
		$this->controls_button_style();
		$this->controls_lead_style();
	}

	/**
	 * Countdown end timestamp per settings.
	 *
	 * @param WC_Product[] $products Products in this instance.
	 * @return int
	 */
	private function cd_end( $products ) {
		$s = $this->effective_settings();
		if ( 'yes' !== ( $s['show_cd'] ?? 'yes' ) ) {
			return 0;
		}
		if ( 'manual' === ( $s['cd_mode'] ?? 'auto' ) ) {
			$raw = trim( (string) ( $s['cd_manual'] ?? '' ) );
			if ( '' === $raw ) {
				return 0;
			}
			$date = date_create( $raw, wp_timezone() );
			return $date ? (int) $date->getTimestamp() : 0;
		}
		return HSW_Helpers::latest_sale_end( $products );
	}

	/**
	 * Lead banner card.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	private function lead_html( $s ) {
		$url = (string) ( $s['lead_url']['url'] ?? '' );
		if ( '' === $url ) {
			$url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		}

		$title = trim( (string) ( $s['lead_title'] ?? '' ) );
		$sub   = trim( (string) ( $s['lead_sub'] ?? '' ) );
		$btn   = trim( (string) ( $s['lead_btn'] ?? '' ) );

		return '<div class="hsw-loop__lead">' .
			'<div class="hsw-loop__lead-body">' .
				HSW_Helpers::icon( (string) ( $s['lead_icon'] ?? 'local_fire_department' ), 'hsw-loop__lead-ico' ) .
				( '' !== $title ? '<span class="hsw-loop__lead-title">' . esc_html( $title ) . '</span>' : '' ) .
				( '' !== $sub ? '<span class="hsw-loop__lead-sub">' . esc_html( $sub ) . '</span>' : '' ) .
			'</div>' .
			( '' !== $btn ? '<a class="hsw-loop__lead-btn" href="' . esc_url( $url ) . '">' .
				'<span>' . esc_html( $btn ) . '</span>' . HSW_Helpers::icon( 'arrow_back' ) . '</a>' : '' ) .
			'</div>';
	}

	protected function render() {
		$s        = $this->effective_settings();
		$rows     = max( 1, min( 3, (int) ( $s['deal_rows'] ?? 1 ) ) );
		$per_row  = max( 1, min( 24, (int) ( $s['count']['size'] ?? 8 ) ) );
		$show_lead = 'yes' === ( $s['show_lead'] ?? 'yes' );

		$products = $this->resolve_products( array( 'count' => $per_row * $rows ) );
		$products = array_slice( $products, 0, $per_row * $rows );
		$cd_end   = $this->cd_end( $products );

		$args                  = $this->renderer_args();
		$args['cache_until']   = $cd_end;
		$args['first_priority'] = true;

		$cards = (float) ( $s['cards']['size'] ?? 4 );
		$fit   = $cards > 0 ? 'cards' : 'fixed';
		$rows  = max( 1, min( 3, $rows ) );
		$chunks = $products ? array_chunk( $products, $per_row ) : array();
		$chunks = array_slice( $chunks, 0, $rows );

		$title  = trim( (string) ( $s['hdr_title'] ?? '' ) );
		$rail   = $this->rail_id();
		$more   = 'yes' === ( $s['show_more_card'] ?? 'no' );
		$last   = count( $chunks ) - 1;
		?>
		<section class="hsw-loop hsw-loop--deal hsw-loop--hsw-wonder-deals<?php echo esc_attr( $this->preset_class( $s ) . $this->hsw_font_class() ); ?>" data-hsw-fit="<?php echo esc_attr( $fit ); ?>">
			<?php
			if ( 'yes' === ( $s['show_header'] ?? 'yes' ) ) {
				echo $this->header_html( $s, $cd_end ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			} elseif ( $cd_end > time() ) {
				echo '<div class="hsw-loop__bar">' . $this->timer_html( $cd_end, (string) ( $s['cd_label'] ?? '' ) ) . $this->arrows_html() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo '<div class="hsw-loop__bar">' . $this->arrows_html() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>

			<?php if ( empty( $chunks ) ) : ?>
				<div class="hsw-loop__empty"><?php esc_html_e( 'محصولی برای نمایش یافت نشد.', 'hamishop-elementor-widgets' ); ?></div>
			<?php else : ?>
				<div class="hsw-loop__rows">
					<?php foreach ( $chunks as $ri => $chunk ) : ?>
						<div class="hsw-loop__row">
							<div class="hsw-loop__rail"
								id="<?php echo esc_attr( $rail . ( $rows > 1 ? '-r' . $ri : '' ) ); ?>"
								data-hsw-rail
								role="group"
								aria-label="<?php echo esc_attr( '' !== $title ? $title : __( 'پیشنهاد شگفت‌انگیز', 'hamishop-elementor-widgets' ) ); ?>"
								tabindex="0">
								<?php
								if ( 0 === $ri && $show_lead ) {
									echo $this->lead_html( $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
								}

								$row_args = $args;
								if ( $ri > 0 ) {
									$row_args['first_priority'] = false;
								}
								echo self::cards_html( 'deal', $chunk, $row_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.

								if ( $more && $ri === $last ) {
									echo $this->more_card_html( $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
								}
								?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}
}
