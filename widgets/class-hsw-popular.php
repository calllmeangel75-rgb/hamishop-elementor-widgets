<?php
/**
 * Widget: محبوب‌ترین‌ها / منتخب هوشمند (Most Popular) — Section 3 of the supplied design.
 *
 * The first tab is rendered on the server. The remaining tabs are fetched from
 * the cached /hsw/v1/loop endpoint only after the visitor interacts with them;
 * there is no idle prefetch and no request on first paint.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Widget_Popular extends HSW_Widget_Base {

	public function get_name() {
		return 'hsw-popular';
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
		$this->controls_tabs();
		$this->controls_card_content(
			array(
				'show_rank'   => false,
				'show_stock'  => false,
				'price_label' => false,
				'txt_buy'     => false,
				'btn_variant' => 'icon',
			)
		);

		/* ------------------------- STYLE ------------------------- */
		$this->controls_theme();
		$this->controls_shell( 'box' );
		$this->controls_header_style();
		$this->controls_tabs_style();
		$this->controls_card_style();
		$this->controls_badge_style();
		$this->controls_stock_style();
		$this->controls_price_style();
		$this->controls_button_style();
		$this->controls_skeleton_style();
	}

	/**
	 * Post that owns this widget.
	 *
	 * Inside an Elementor page the current document is the page being
	 * rendered, which is what `_elementor_data` is keyed by. Falls back to
	 * the queried post for non-Elementor contexts.
	 *
	 * @return int
	 */
	protected function current_document_id() {
		if ( class_exists( '\Elementor\Plugin' ) ) {
			$documents = \Elementor\Plugin::$instance->documents;
			if ( $documents ) {
				$current = $documents->get_current();
				if ( $current ) {
					return (int) $current->get_main_id();
				}
			}
		}

		return (int) get_the_ID();
	}

	/**
	 * Valid repeater rows, mirroring the allow-list used by tabs_html().
	 *
	 * @param array $tabs Repeater rows.
	 * @return array
	 */
	private function valid_tabs( $tabs ) {
		$out     = array();
		$sources = self::loop_sources();

		foreach ( (array) $tabs as $tab ) {
			if ( ! is_array( $tab ) ) {
				continue;
			}
			$source = (string) ( $tab['tab_source'] ?? 'rating' );
			$label  = trim( (string) ( $tab['tab_label'] ?? '' ) );
			if ( ! in_array( $source, $sources, true ) || '' === $label ) {
				continue;
			}
			$out[] = $tab;
		}

		return $out;
	}

	protected function render() {
		$s        = $this->effective_settings();
		$show_tabs = 'yes' === ( $s['show_tabs'] ?? 'yes' );
		$tabs      = $this->valid_tabs( is_array( $s['tabs'] ?? null ) ? $s['tabs'] : array() );
		$tabbed    = $show_tabs && count( $tabs ) > 0;

		$count   = max( 1, min( 24, (int) ( $s['count']['size'] ?? 8 ) ) );
		$term_id = (int) ( $s['source_term'] ?? 0 );
		$source  = $tabbed ? (string) $tabs[0]['tab_source'] : (string) ( $s['source'] ?? 'rating' );
		if ( ! in_array( $source, self::loop_sources(), true ) ) {
			$source = 'rating';
		}

		$products = $this->resolve_products(
			array(
				'source'  => $source,
				'count'   => $count,
				'term_id' => $term_id,
			)
		);
		$args = $this->renderer_args();

		$cards = (float) ( $s['cards']['size'] ?? 4 );
		$fit   = $cards > 0 ? 'cards' : 'fixed';
		$title = trim( (string) ( $s['hdr_title'] ?? '' ) );

		$post_id   = $this->current_document_id();
		$widget_id = (string) $this->get_id();
		$sig       = HSW_Rest::loop_signature( $post_id, $widget_id );
		$endpoint  = rest_url( 'hsw/v1/loop' );
		$rail      = $this->rail_id();

		$tabs_markup = '';
		if ( $tabbed ) {
			$tabs_markup = $this->tabs_html(
				$tabs,
				array(
					'endpoint'  => $endpoint,
					'post_id'   => $post_id,
					'widget_id' => $widget_id,
					'sig'       => $sig,
					'per_page'  => $count,
					'term_id'   => $term_id,
				)
			);
		}

		$note = '';
		if ( '' !== trim( (string) ( $s['hdr_note'] ?? '' ) ) ) {
			$note = '<span class="hsw-loop__note">' . HSW_Helpers::icon( 'favorite' ) .
				'<span>' . esc_html( (string) $s['hdr_note'] ) . '</span></span>';
		}
		?>
		<section class="hsw-loop hsw-loop--box hsw-loop--pop hsw-loop--hsw-popular<?php echo esc_attr( $this->preset_class( $s ) . $this->hsw_font_class() ); ?>"
			data-hsw-fit="<?php echo esc_attr( $fit ); ?>"
			data-hsw-count="<?php echo esc_attr( $count ); ?>"
			<?php echo $this->theme_attr( $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
			<?php if ( $tabbed ) : ?>data-hsw-tabs="1"<?php endif; ?>>
			<?php
			if ( 'yes' === ( $s['show_header'] ?? 'yes' ) ) {
				echo $this->header_html( $s, 0, $note . $tabs_markup ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			} elseif ( $tabbed ) {
				echo '<div class="hsw-loop__bar">' . $tabs_markup . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			}

			if ( empty( $products ) ) {
				echo '<div class="hsw-loop__empty">' . esc_html__( 'محصولی برای نمایش یافت نشد.', 'hamishop-elementor-widgets' ) . '</div>';
			}

			echo '<div class="hsw-loop__row"><div class="hsw-loop__rail" id="' . esc_attr( $rail ) . '" data-hsw-rail data-hsw-src="' . esc_attr( $source ) . '" role="group" aria-label="' . esc_attr( '' !== $title ? $title : __( 'محبوب‌ترین محصولات', 'hamishop-elementor-widgets' ) ) . '" aria-busy="false" tabindex="0">';
			if ( ! empty( $products ) ) {
				echo self::cards_html( 'popular', $products, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			}
			echo '</div></div>';
			?>
		</section>
		<?php
	}
}
