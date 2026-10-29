<?php
/**
 * Desktop store-header cascade menu.
 *
 * The root column is rendered with the page; every deeper column is fetched
 * on demand through /hsw/v1/header-cascade. That keeps the initial header
 * cheap even when the catalogue has hundreds of categories: no recursive
 * get_terms() walk and no product query until the visitor asks for one.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Header_Cascade {

	const MAX_DEPTH      = 3;
	const MAX_PRODUCTS   = 8;
	const CHILDREN_LIMIT = 20;

	/**
	 * Max category levels (1–3).
	 *
	 * @param array $s Widget settings.
	 * @return int
	 */
	public static function depth( $s ) {
		$depth = isset( $s['mega_depth'] ) ? absint( $s['mega_depth'] ) : 3;
		return max( 1, min( self::MAX_DEPTH, $depth ) );
	}

	/**
	 * Whether the leaf shows products instead of ending at the category.
	 *
	 * Missing means "on" so installs saved before this option existed keep the
	 * previous product-preview behaviour.
	 *
	 * @param array $s Widget settings.
	 * @return bool
	 */
	public static function products_enabled( $s ) {
		return ! isset( $s['mega_leaf_products'] ) || 'yes' === $s['mega_leaf_products'];
	}

	/**
	 * Products on the leaf panel (3, 4 or 6).
	 *
	 * @param array $s Widget settings.
	 * @return int
	 */
	public static function product_limit( $s ) {
		$limit = isset( $s['mega_products'] ) ? absint( $s['mega_products'] ) : 4;
		return in_array( $limit, array( 3, 4, 6 ), true ) ? $limit : 4;
	}

	/**
	 * Top-level categories in the root column.
	 *
	 * @param array $s Widget settings.
	 * @return int
	 */
	public static function category_limit( $s ) {
		$limit = isset( $s['mega_cats'] ) ? absint( $s['mega_cats'] ) : 5;
		return in_array( $limit, array( 4, 5, 6, 8, 10, 12 ), true ) ? $limit : 5;
	}

	/**
	 * All product categories offered by the Elementor selector.
	 *
	 * Empty categories are included on purpose: an explicitly selected term
	 * should still appear even when it has no products yet.
	 *
	 * @return array<int,string>
	 */
	public static function category_options() {
		static $options = null;

		if ( null !== $options ) {
			return $options;
		}

		$options = array();
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return $options;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $terms ) ) {
			return $options;
		}

		$default_id = (int) get_option( 'default_product_cat', 0 );
		foreach ( $terms as $term ) {
			if ( $default_id && (int) $term->term_id === $default_id ) {
				continue;
			}
			if ( in_array( $term->slug, array( 'uncategorized', 'بدون-دسته‌بندی' ), true ) ) {
				continue;
			}

			$label = $term->name;
			if ( $term->parent ) {
				$parent = get_term( $term->parent, 'product_cat' );
				if ( $parent instanceof WP_Term ) {
					$label = $parent->name . ' / ' . $label;
				}
			}
			$options[ (int) $term->term_id ] = $label;
		}

		return $options;
	}

	/**
	 * Categories explicitly chosen in Elementor, in selection order.
	 *
	 * @param array $s Widget settings.
	 * @return WP_Term[]
	 */
	public static function selected_categories( $s ) {
		$ids = isset( $s['mega_cat_ids'] ) ? $s['mega_cat_ids'] : array();
		if ( ! is_array( $ids ) ) {
			$ids = preg_split( '/[\s,]+/', (string) $ids );
		}

		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
		if ( ! $ids || ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'include'    => $ids,
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$map = array();
		foreach ( $terms as $term ) {
			$map[ (int) $term->term_id ] = $term;
		}

		$default_id = (int) get_option( 'default_product_cat', 0 );
		$out        = array();
		foreach ( $ids as $id ) {
			if ( ! isset( $map[ $id ] ) ) {
				continue;
			}
			$term = $map[ $id ];
			if ( $default_id && $id === $default_id ) {
				continue;
			}
			if ( in_array( $term->slug, array( 'uncategorized', 'بدون-دسته‌بندی' ), true ) ) {
				continue;
			}
			$out[] = $term;
		}

		return $out;
	}

	/**
	 * Categories used by the header: explicit selection, otherwise the
	 * configured number of top-level categories.
	 *
	 * @param array $s Widget settings.
	 * @return WP_Term[]
	 */
	public static function header_categories( $s ) {
		$selected = self::selected_categories( $s );
		if ( $selected ) {
			return $selected;
		}

		return self::categories( self::category_limit( $s ) );
	}

	/**
	 * Product categories for search / mega menu, excluding WooCommerce's
	 * placeholder term. Results are memoised for the current request so the
	 * search select, mega menu and drawer never repeat the same taxonomy query.
	 *
	 * @param int $limit  How many terms to return (0 = all).
	 * @param int $parent Parent term id.
	 * @return WP_Term[]
	 */
	public static function categories( $limit = 0, $parent = 0 ) {
		static $cache = array();

		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}

		$limit  = absint( $limit );
		$parent = absint( $parent );
		$key    = $parent . '|' . $limit;

		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}

		$args = array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'parent'     => $parent,
			'orderby'    => 'name',
			'order'      => 'ASC',
		);
		if ( $limit > 0 ) {
			/*
			 * Fetch a couple of spares: the default/uncategorized term is
			 * filtered below, and asking for exactly N would otherwise show
			 * N-1 rows for every setting.
			 */
			$args['number'] = $limit + 2;
		}

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			$cache[ $key ] = array();
			return $cache[ $key ];
		}

		$default_id = (int) get_option( 'default_product_cat', 0 );
		$terms      = array_values(
			array_filter(
				$terms,
				static function ( $term ) use ( $default_id ) {
					if ( $default_id && (int) $term->term_id === $default_id ) {
						return false;
					}
					return ! in_array( $term->slug, array( 'uncategorized', 'بدون-دسته‌بندی' ), true );
				}
			)
		);

		if ( $limit > 0 && count( $terms ) > $limit ) {
			$terms = array_slice( $terms, 0, $limit );
		}

		$cache[ $key ] = $terms;
		return $terms;
	}

	/**
	 * Root panel: only top-level categories, no product query.
	 *
	 * @param array $s Widget settings.
	 * @return string
	 */
	public static function root_html( $s ) {
		$categories = self::header_categories( $s );
		if ( ! $categories ) {
			return '';
		}

		$depth    = self::depth( $s );
		$products = self::products_enabled( $s );
		$limit    = self::product_limit( $s );
		$shop     = self::shop_url();

		$out  = '<div class="hsw-hd__megapanel hsw-hd__megapanel--cascade" data-hsw-hd-mega-panel'
			. ' data-hsw-hd-mega-depth="' . esc_attr( (string) $depth ) . '"'
			. ' data-hsw-hd-mega-limit="' . esc_attr( (string) $limit ) . '"'
			. ' data-hsw-hd-mega-products="' . esc_attr( $products ? '1' : '0' ) . '"'
			. ' data-hsw-hd-mega-error="' . esc_attr__( 'بارگذاری این دسته ناموفق بود. دوباره تلاش کنید.', 'hamishop-elementor-widgets' ) . '"'
			. ' aria-hidden="true">';
		$out .= '<div class="hsw-hd__cascade" data-hsw-hd-mega-cascade>';

		/* Root column (rightmost in RTL, fixed while deeper columns fly left). */
		$out .= '<div class="hsw-hd__cascadecol hsw-hd__cascadecol--root" data-hsw-hd-mega-col="0">';
		$out .= '<div class="hsw-hd__cascadehead">';
		$out .= '<span class="hsw-hd__cascadetitle">' . esc_html__( 'دسته‌بندی‌های فروشگاه', 'hamishop-elementor-widgets' ) . '</span>';
		$out .= '<a class="hsw-hd__cascadeall" href="' . esc_url( $shop ) . '">' . esc_html__( 'فروشگاه', 'hamishop-elementor-widgets' ) . HSW_Helpers::icon( 'arrow_left_alt' ) . '</a>';
		$out .= '</div>';
		$out .= '<ul class="hsw-hd__cascadelist">';

		foreach ( $categories as $category ) {
			$out .= self::node_html( $category, 1, $depth, $products );
		}

		$out .= '</ul></div>';
		$out .= '</div></div>';

		return $out;
	}

	/**
	 * One cascade column. Used by the REST endpoint for every depth.
	 *
	 * @param WP_Term $term     Parent category.
	 * @param int     $level    Level of the parent category (1–3).
	 * @param int     $depth    Max category levels.
	 * @param int     $limit    Products on the leaf.
	 * @param bool    $products Whether the leaf may list products.
	 * @return string
	 */
	public static function column_html( $term, $level, $depth, $limit, $products ) {
		if ( ! $term instanceof WP_Term ) {
			return '';
		}

		$level    = max( 1, min( self::MAX_DEPTH, absint( $level ) ) );
		$depth    = max( 1, min( self::MAX_DEPTH, absint( $depth ) ) );
		$limit    = max( 1, min( self::MAX_PRODUCTS, absint( $limit ) ) );
		$products = (bool) $products;
		$url      = self::term_url( $term );

		if ( '' === $url ) {
			return '';
		}

		$children = $level < $depth ? self::categories( self::CHILDREN_LIMIT, $term->term_id ) : array();

		if ( $children ) {
			$out  = '<div class="hsw-hd__cascadecol" data-hsw-hd-mega-col data-hsw-hd-mega-parent="' . esc_attr( (string) $term->term_id ) . '" data-hsw-hd-mega-level="' . esc_attr( (string) $level ) . '">';
			$out .= self::head_html( $term, $url );
			$out .= '<ul class="hsw-hd__cascadelist">';
			foreach ( $children as $child ) {
				$out .= self::node_html( $child, $level + 1, $depth, $products );
			}
			$out .= '</ul></div>';
			return $out;
		}

		if ( $products ) {
			return self::products_column_html( $term, $url, $limit );
		}

		return self::end_column_html( $term, $url );
	}

	/**
	 * Leaf product panel.
	 *
	 * @param WP_Term $term  Category.
	 * @param string  $url   Category URL.
	 * @param int     $limit Products.
	 * @return string
	 */
	public static function products_column_html( $term, $url, $limit ) {
		$limit = max( 1, min( self::MAX_PRODUCTS, absint( $limit ) ) );
		$rows  = self::product_rows_html( $term, $limit );

		$out  = '<div class="hsw-hd__cascadecol hsw-hd__cascadecol--products" data-hsw-hd-mega-col data-hsw-hd-mega-parent="' . esc_attr( (string) $term->term_id ) . '">';
		$out .= '<div class="hsw-hd__cascadehead">';
		$out .= '<span class="hsw-hd__cascadetitle">' . esc_html( sprintf( /* translators: %s: category name */ __( 'محصولات %s', 'hamishop-elementor-widgets' ), $term->name ) ) . '</span>';
		$out .= '<a class="hsw-hd__cascadeall" href="' . esc_url( $url ) . '">' . esc_html__( 'مشاهده همه', 'hamishop-elementor-widgets' ) . HSW_Helpers::icon( 'arrow_left_alt' ) . '</a>';
		$out .= '</div>';

		if ( '' !== $rows ) {
			$out .= '<div class="hsw-hd__cascadeproducts">' . $rows . '</div>';
		} else {
			$out .= '<p class="hsw-hd__cascadeempty">' . esc_html__( 'محصولی برای نمایش در این دسته نیست.', 'hamishop-elementor-widgets' ) . '</p>';
		}

		$out .= '</div>';
		return $out;
	}

	/**
	 * Panel used when product listing is disabled and a category has no
	 * children, so the cascade always ends on a real, clickable category.
	 *
	 * @param WP_Term $term Category.
	 * @param string  $url  Category URL.
	 * @return string
	 */
	private static function end_column_html( $term, $url ) {
		$out  = '<div class="hsw-hd__cascadecol hsw-hd__cascadecol--end" data-hsw-hd-mega-col data-hsw-hd-mega-parent="' . esc_attr( (string) $term->term_id ) . '">';
		$out .= self::head_html( $term, $url );
		$out .= '<div class="hsw-hd__cascadeendwrap"><a class="hsw-hd__cascadeend" href="' . esc_url( $url ) . '">';
		$out .= '<span>' . esc_html( sprintf( /* translators: %s: category name */ __( 'مشاهده محصولات %s', 'hamishop-elementor-widgets' ), $term->name ) ) . '</span>';
		$out .= HSW_Helpers::icon( 'arrow_left_alt' );
		$out .= '</a></div></div>';
		return $out;
	}

	/**
	 * Column heading with an "all" shortcut.
	 *
	 * @param WP_Term $term Category.
	 * @param string  $url  Category URL.
	 * @return string
	 */
	private static function head_html( $term, $url ) {
		return '<div class="hsw-hd__cascadehead">'
			. '<span class="hsw-hd__cascadetitle">' . esc_html( $term->name ) . '</span>'
			. '<a class="hsw-hd__cascadeall" href="' . esc_url( $url ) . '">' . esc_html__( 'مشاهده همه', 'hamishop-elementor-widgets' ) . HSW_Helpers::icon( 'arrow_left_alt' ) . '</a>'
			. '</div>';
	}

	/**
	 * Category product count including every descendant, matching what the
	 * category archive actually lists.
	 *
	 * `$term->count` holds only the products assigned to the term itself, so the
	 * header showed «۵» for a category whose page lists seven products (five
	 * direct + two in subcategories) while the archive sidebar printed «۷».
	 *
	 * @param WP_Term $term Category term.
	 * @return int
	 */
	public static function term_total_count( $term ) {
		if ( ! $term instanceof WP_Term ) {
			return 0;
		}

		$total    = (int) $term->count;
		$children = get_term_children( (int) $term->term_id, $term->taxonomy );

		if ( is_wp_error( $children ) || empty( $children ) ) {
			return $total;
		}

		foreach ( $children as $child_id ) {
			$child = get_term( (int) $child_id, $term->taxonomy );
			if ( $child instanceof WP_Term ) {
				$total += (int) $child->count;
			}
		}

		return $total;
	}

	/**
	 * Category row. At the last allowed level the row is a real link when the
	 * product panel is disabled, so the menu ends on the category itself.
	 *
	 * @param WP_Term $term     Category.
	 * @param int     $level    Category level (1–3).
	 * @param int     $depth    Max levels.
	 * @param bool    $products Whether products are enabled.
	 * @return string
	 */
	private static function node_html( $term, $level, $depth, $products ) {
		$url = self::term_url( $term );
		if ( '' === $url ) {
			return '';
		}

		$level    = max( 1, min( self::MAX_DEPTH, absint( $level ) ) );
		$terminal = $level >= $depth && ! $products;
		$classes  = 'hsw-hd__cascadenode';
		$classes .= $terminal ? ' hsw-hd__cascadenode--leaf' : '';

		$attributes = ' data-hsw-hd-mega-cat="' . esc_attr( (string) $term->term_id ) . '"'
			. ' data-hsw-hd-mega-url="' . esc_url( $url ) . '"'
			. ' data-hsw-hd-mega-name="' . esc_attr( $term->name ) . '"'
			. ' data-hsw-hd-mega-level="' . esc_attr( (string) $level ) . '"';
		if ( $terminal ) {
			$attributes .= ' data-hsw-hd-mega-terminal="1"';
		}

		$icon  = $level <= 1 ? '<span class="hsw-hd__cascadeicon">' . HSW_Helpers::icon( 'category' ) . '</span>' : '';
		$inner = $icon
			. '<span class="hsw-hd__cascadename">' . esc_html( $term->name ) . '</span>'
			. '<span class="hsw-hd__cascadecount">' . esc_html( HSW_Helpers::fa_num( self::term_total_count( $term ) ) ) . '</span>'
			. '<span class="hsw-hd__cascadechev">' . HSW_Helpers::icon( $terminal ? 'arrow_left_alt' : 'chevron_left' ) . '</span>';

		$out = '<li class="hsw-hd__cascadeitem">';
		if ( $terminal ) {
			$out .= '<a class="' . esc_attr( $classes ) . '" href="' . esc_url( $url ) . '"' . $attributes . '>' . $inner . '</a>';
		} else {
			$out .= '<button class="' . esc_attr( $classes ) . '" type="button" data-hsw-hd-mega-node aria-haspopup="true" aria-expanded="false"' . $attributes . '>' . $inner . '</button>';
		}
		$out .= '</li>';

		return $out;
	}

	/**
	 * Product rows for the leaf panel.
	 *
	 * @param WP_Term $term  Category.
	 * @param int     $limit Limit.
	 * @return string
	 */
	private static function product_rows_html( $term, $limit ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return '';
		}

		$products = wc_get_products(
			array(
				'status'   => 'publish',
				'limit'    => $limit + 6,
				'category' => array( $term->slug ),
				'orderby'  => 'date',
				'order'    => 'DESC',
				'return'   => 'objects',
			)
		);

		$rows  = '';
		$found = 0;

		foreach ( $products as $product ) {
			if ( $found >= $limit ) {
				break;
			}
			if ( ! $product instanceof WC_Product ) {
				continue;
			}
			if ( ! $product->is_visible() ) {
				continue;
			}
			if ( '' !== (string) get_post_field( 'post_password', $product->get_id() ) ) {
				continue;
			}
			$rows .= self::product_row_html( $product );
			$found++;
		}

		return $rows;
	}

	/**
	 * One product row.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private static function product_row_html( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		$name = $product->get_name();
		$url  = $product->get_permalink();
		$img  = $product->get_image(
			'woocommerce_gallery_thumbnail',
			array(
				'class'   => 'hsw-hd__cascadeproductimg',
				'alt'     => $name,
				'loading' => 'lazy',
			)
		);

		if ( false === strpos( $img, 'loading=' ) ) {
			$img = str_replace( '<img ', '<img loading="lazy" decoding="async" ', $img );
		}

		if ( HSW_Helpers::is_call_for_price( $product ) ) {
			$meta = HSW_Helpers::cfp_inline_html( $product, false, 'hsw-hd__cascadeprice' );
		} else {
			$meta = HSW_Helpers::price_html_fa( $product->get_price_html() );
		}
		if ( '' === trim( (string) $meta ) ) {
			$meta = '<span class="hsw-hd__cascadeprice">' . esc_html__( 'مشاهده', 'hamishop-elementor-widgets' ) . '</span>';
		}

		return '<a class="hsw-hd__cascadeproduct" href="' . esc_url( $url ) . '">'
			. '<span class="hsw-hd__cascadeproductpic">' . $img . '</span>'
			. '<span class="hsw-hd__cascadeproducttext">'
			. '<span class="hsw-hd__cascadeproductname">' . esc_html( $name ) . '</span>'
			. '<span class="hsw-hd__cascadeproductmeta">' . $meta . '</span>'
			. '</span></a>';
	}

	/**
	 * Category URL, or an empty string when WordPress cannot build it.
	 *
	 * @param WP_Term $term Term.
	 * @return string
	 */
	private static function term_url( $term ) {
		$url = get_term_link( $term );
		return is_wp_error( $url ) ? '' : (string) $url;
	}

	/**
	 * WooCommerce shop URL with archive/home fallbacks.
	 *
	 * @return string
	 */
	private static function shop_url() {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$url = wc_get_page_permalink( 'shop' );
			if ( $url ) {
				return $url;
			}
		}
		$url = get_post_type_archive_link( 'product' );
		return $url ? $url : home_url( '/' );
	}
}
