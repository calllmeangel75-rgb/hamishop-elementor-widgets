<?php
/**
 * Product queries for the loop widgets.
 *
 * Query results are cached as lean ID lists. The cache key includes the
 * normalized settings, the catalogue version and (for deals) the nearest sale
 * end, so a cached list can never outlive a running sale.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Products {

	/** Cache group used by the object cache. */
	const CACHE_GROUP = 'hsw_loop';

	/** Default transient lifetime in seconds. */
	const DEFAULT_TTL = 300;

	/** @var bool */
	private static $hooks_registered = false;

	public function __construct() {
		$this->register_invalidation_hooks();
	}

	/**
	 * Invalidate the loop cache whenever the catalogue, stock or a sale moves.
	 *
	 * WooCommerce's own `product` transient version already covers most saves;
	 * this marker exists for stock/sale hooks that do not always bump it (and
	 * for stores running an older WooCommerce).
	 */
	private function register_invalidation_hooks() {
		if ( self::$hooks_registered ) {
			return;
		}
		self::$hooks_registered = true;

		$hooks = array(
			'woocommerce_update_product',
			'woocommerce_new_product',
			'woocommerce_delete_product',
			'woocommerce_product_set_stock',
			'woocommerce_variation_set_stock',
			'woocommerce_product_set_stock_status',
			'woocommerce_variation_set_stock_status',
			'woocommerce_scheduled_sales',
		);

		foreach ( $hooks as $hook ) {
			add_action( $hook, array( __CLASS__, 'bump_version' ), 10, 0 );
		}
	}

	/**
	 * Move the loop cache version forward.
	 */
	public static function bump_version() {
		update_option( 'hsw_loop_version', (string) microtime( true ), false );
	}

	/**
	 * Current catalogue version.
	 *
	 * @return string
	 */
	public static function version() {
		static $version = null;
		if ( null !== $version ) {
			return $version;
		}

		$version = (string) get_option( 'hsw_loop_version', '1' );
		if ( class_exists( 'WC_Cache_Helper' ) ) {
			$version .= '|' . (string) WC_Cache_Helper::get_transient_version( 'product' );
		}
		return $version;
	}

	/**
	 * Same-category product IDs, walking up to the parent category when the
	 * current category has no other products.
	 *
	 * @param int $product_id Current product ID.
	 * @param int $limit      Max IDs to return (0/negative is treated as 1).
	 * @return int[]
	 */
	public static function related_category_ids( $product_id, $limit = 8 ) {
		$product_id = absint( $product_id );
		$limit      = max( 1, absint( $limit ) );

		if ( ! $product_id ) {
			return array();
		}

		$terms = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$level = array_values( array_unique( array_map( 'absint', (array) $terms ) ) );
		$seen  = array();

		while ( ! empty( $level ) ) {
			$level = array_values( array_diff( $level, $seen ) );
			if ( empty( $level ) ) {
				break;
			}
			$seen = array_merge( $seen, $level );

			$ids = get_posts(
				array(
					'post_type'           => 'product',
					'post_status'         => 'publish',
					'posts_per_page'      => $limit,
					'post__not_in'        => array( $product_id ),
					'fields'              => 'ids',
					'no_found_rows'       => true,
					'ignore_sticky_posts' => true,
					'orderby'             => 'date',
					'order'               => 'DESC',
					'tax_query'           => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy'         => 'product_cat',
							'field'            => 'term_id',
							'terms'            => $level,
							'include_children' => true,
						),
					),
				)
			);

			if ( ! empty( $ids ) ) {
				return array_values( array_map( 'intval', $ids ) );
			}

			$parents = array();
			foreach ( $level as $term_id ) {
				$parent = (int) get_term_field( 'parent', $term_id, 'product_cat' );
				if ( $parent > 0 ) {
					$parents[] = $parent;
				}
			}
			$level = array_values( array_unique( $parents ) );
		}

		return array();
	}

	/**
	 * Query products by source key.
	 *
	 * @param array $args {
	 *     source, count, ids, term_id, exclude_oos, days, ttl.
	 * }
	 * @return WC_Product[]
	 */
	public function query( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'source'      => 'sale',
				'count'       => 8,
				'ids'         => array(),
				'term_id'     => 0,
				'exclude_oos' => false,
				'days'        => 0,
				'ttl'         => null,
			)
		);

		$args['source']    = (string) $args['source'];
		$args['count']     = max( 1, min( 60, absint( $args['count'] ) ) );
		$args['term_id']   = absint( $args['term_id'] );
		$args['days']      = absint( $args['days'] );
		$args['ids']       = array_values( array_filter( array_map( 'absint', (array) $args['ids'] ) ) );
		$args['ttl']       = null === $args['ttl'] ? (int) apply_filters( 'hsw_loop_cache_ttl', self::DEFAULT_TTL ) : absint( $args['ttl'] );

		$key     = $this->cache_key( $args );
		$cached  = $key ? $this->cache_get( $key ) : null;
		$payload = is_array( $cached ) ? $cached : null;

		if ( null === $payload ) {
			$products = $this->run_query( $args );
			$timer    = 'sale' === $args['source'] ? (int) HSW_Helpers::latest_sale_end( $products ) : 0;
			$payload  = array(
				'ids'       => array_map(
					static function ( $product ) {
						return (int) $product->get_id();
					},
					$products
				),
				'timer_end' => $timer,
				'version'   => self::version(),
			);

			if ( $key ) {
				$ttl = $this->effective_ttl( $args['ttl'], $timer );
				if ( $ttl > 0 ) {
					$this->cache_set( $key, $payload, $ttl );
				}
			}
			return $products;
		}

		/* A deal list cached before the sale ended must not be reused. */
		if ( ! empty( $payload['timer_end'] ) && (int) $payload['timer_end'] <= time() ) {
			if ( $key ) {
				$this->cache_delete( $key );
			}
			$products = $this->run_query( $args );
			$timer    = 'sale' === $args['source'] ? (int) HSW_Helpers::latest_sale_end( $products ) : 0;
			$payload  = array(
				'ids'       => array_map(
					static function ( $product ) {
						return (int) $product->get_id();
					},
					$products
				),
				'timer_end' => $timer,
				'version'   => self::version(),
			);
			if ( $key ) {
				$ttl = $this->effective_ttl( $args['ttl'], $timer );
				if ( $ttl > 0 ) {
					$this->cache_set( $key, $payload, $ttl );
				}
			}
			return $products;
		}

		return $this->load_ids( (array) $payload['ids'] );
	}

	/**
	 * Run the actual query (cold path only).
	 *
	 * @param array $args Normalized args.
	 * @return WC_Product[]
	 */
	private function run_query( $args ) {
		$source      = $args['source'];
		$count       = (int) $args['count'];
		$exclude_oos = ! empty( $args['exclude_oos'] );
		$term_id     = (int) $args['term_id'];
		$want        = $exclude_oos ? min( 60, $count * 2 ) : $count;

		if ( 'manual' === $source ) {
			if ( empty( $args['ids'] ) ) {
				return array();
			}
			$products = $this->wc_query(
				array(
					'include' => $args['ids'],
					'orderby' => 'include',
					'limit'   => count( $args['ids'] ),
				)
			);
			return array_slice( $products, 0, $count );
		}

		switch ( $source ) {
			case 'sale':
				$ids = wc_get_product_ids_on_sale();
				if ( empty( $ids ) ) {
					return array();
				}
				$products = $this->wc_query(
					array(
						'include' => array_map( 'intval', $ids ),
						'orderby' => 'date',
						'order'   => 'DESC',
						'limit'   => $want,
						'term_id' => $term_id,
					)
				);
				break;

			case 'discount':
				$ordered = $this->discount_ordered_ids( $want, $exclude_oos, $term_id );
				if ( empty( $ordered ) ) {
					return array();
				}
				$products = $this->wc_query(
					array(
						'include' => $ordered,
						'orderby' => 'include',
						'limit'   => count( $ordered ),
						'term_id' => $term_id,
					)
				);
				break;

			case 'best_selling':
				$ordered = $this->best_seller_ids( $want, (int) $args['days'] );
				if ( empty( $ordered ) ) {
					return array();
				}
				$products = $this->wc_query(
					array(
						'include' => $ordered,
						'orderby' => 'include',
						'limit'   => count( $ordered ),
						'term_id' => $term_id,
					)
				);
				break;

			case 'rating':
			case 'popular':
				$ordered = $this->lookup_ordered_ids(
					'average_rating',
					$want,
					$exclude_oos,
					'popular' === $source ? 'total_sales' : ''
				);
				if ( empty( $ordered ) ) {
					return array();
				}
				$products = $this->wc_query(
					array(
						'include' => $ordered,
						'orderby' => 'include',
						'limit'   => count( $ordered ),
						'term_id' => $term_id,
					)
				);
				break;

			case 'featured':
				$ids = wc_get_featured_product_ids();
				if ( empty( $ids ) ) {
					return array();
				}
				$products = $this->wc_query(
					array(
						'include' => array_map( 'intval', $ids ),
						'orderby' => 'date',
						'order'   => 'DESC',
						'limit'   => $want,
						'term_id' => $term_id,
					)
				);
				break;

			case 'newest':
			case 'category':
			default:
				$products = $this->wc_query(
					array(
						'orderby' => 'date',
						'order'   => 'DESC',
						'limit'   => $want,
						'term_id' => $term_id,
					)
				);
				break;
		}

		if ( $exclude_oos ) {
			$products = array_values(
				array_filter(
					$products,
					static function ( $product ) {
						return $product instanceof WC_Product && $product->is_in_stock();
					}
				)
			);
		}

		return array_slice( $products, 0, $count );
	}

	/**
	 * Thin `wc_get_products()` wrapper with the shared visibility/term args.
	 *
	 * @param array $args Query args.
	 * @return WC_Product[]
	 */
	private function wc_query( $args ) {
		$defaults = array(
			'status'  => 'publish',
			'visible' => 'visible',
			'limit'   => 8,
			'orderby' => 'date',
			'order'   => 'DESC',
		);
		$args = wp_parse_args( $args, $defaults );

		if ( ! empty( $args['term_id'] ) ) {
			$slug = get_term_field( 'slug', (int) $args['term_id'], 'product_cat' );
			if ( $slug && ! is_wp_error( $slug ) ) {
				$args['category'] = array( $slug );
			}
			unset( $args['term_id'] );
		}

		$products = wc_get_products( $args );
		return is_array( $products ) ? array_values( array_filter( $products, 'is_object' ) ) : array();
	}

	/**
	 * Load products from a cached ID list without a second WP_Query.
	 *
	 * @param int[] $ids Product IDs in display order.
	 * @return WC_Product[]
	 */
	private function load_ids( $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
		if ( empty( $ids ) ) {
			return array();
		}

		/*
		 * Prime the post/meta/term caches for the whole list in one pass.
		 * `update_post_caches()` expects post objects — handing it raw IDs
		 * logged "Attempt to read property ID on int" and an empty cache key
		 * on every request that used a cached list. `_prime_post_caches()`
		 * is the ID-based companion and lives in core for exactly this case.
		 */
		if ( function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $ids, true, true );
		} else {
			$posts = get_posts(
				array(
					'post_type'           => 'product',
					'post__in'            => $ids,
					'orderby'             => 'post__in',
					'posts_per_page'      => count( $ids ),
					'no_found_rows'       => true,
					'ignore_sticky_posts' => true,
				)
			);
			update_post_caches( $posts, 'product', true, true );
		}

		$out = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			/* A cached ID must never surface a product that was unpublished. */
			if ( $product instanceof WC_Product && 'publish' === $product->get_status() ) {
				$out[] = $product;
			}
		}
		return $out;
	}

	/**
	 * Order ratings/total-sales using the lookup table (no postmeta sort).
	 *
	 * @param string $column     average_rating|total_sales.
	 * @param int    $limit      Number of candidates.
	 * @param bool   $in_stock   Exclude out-of-stock rows.
	 * @param string $secondary  Optional tie-breaker column.
	 * @return int[]
	 */
	private function lookup_ordered_ids( $column, $limit, $in_stock, $secondary = '' ) {
		global $wpdb;
		$allowed = array( 'average_rating', 'total_sales' );
		if ( ! in_array( $column, $allowed, true ) ) {
			return array();
		}

		$table = $wpdb->prefix . 'wc_product_meta_lookup';
		if ( ! $this->table_exists( $table ) ) {
			return array();
		}

		$where = "l.{$column} > 0";
		if ( 'total_sales' === $secondary ) {
			/* popular = rated or bought; a 5-star item with no reviews is still
			   alive, but so is a best-seller that nobody reviewed yet. */
			$where = '(l.average_rating > 0 OR l.total_sales > 0)';
		}
		if ( $in_stock ) {
			$where .= " AND l.stock_status <> 'outofstock'";
		}

		$order = "l.{$column} DESC";
		if ( in_array( $secondary, $allowed, true ) && $secondary !== $column ) {
			$order .= ", l.{$secondary} DESC";
		}

		$sql = "SELECT l.product_id FROM {$table} l INNER JOIN {$wpdb->posts} p ON p.ID = l.product_id
			WHERE p.post_type = 'product' AND p.post_status = 'publish' AND {$where}
			ORDER BY {$order}, l.product_id DESC LIMIT %d";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, max( 1, $limit * 3 ) ) );
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Best sellers over all time (lookup table) or the last N days (order table).
	 *
	 * @param int $limit Number of candidates.
	 * @param int $days  0 = all time, 7/30 = rolling window.
	 * @return int[]
	 */
	private function best_seller_ids( $limit, $days ) {
		global $wpdb;
		$lookup = $wpdb->prefix . 'wc_product_meta_lookup';
		if ( ! $this->table_exists( $lookup ) ) {
			return array();
		}

		$candidates = max( 1, $limit * 3 );

		if ( $days <= 0 ) {
			$sql = "SELECT l.product_id FROM {$lookup} l INNER JOIN {$wpdb->posts} p ON p.ID = l.product_id
				WHERE p.post_type = 'product' AND p.post_status = 'publish' AND l.total_sales > 0
				ORDER BY l.total_sales DESC, l.product_id DESC LIMIT %d";
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, $candidates ) ) );
		}

		$orders = $wpdb->prefix . 'wc_order_product_lookup';
		if ( ! $this->table_exists( $orders ) ) {
			return $this->best_seller_ids( $limit, 0 );
		}

		$since = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, $days ) * DAY_IN_SECONDS ) );
		$hpos  = $wpdb->prefix . 'wc_orders';

		if ( $this->table_exists( $hpos ) ) {
			$statuses = array( 'wc-completed', 'wc-processing', 'wc-on-hold' );
			$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
			$sql = "SELECT o.product_id, SUM(o.product_qty) AS qty
				FROM {$orders} o INNER JOIN {$hpos} hp ON hp.id = o.order_id
				WHERE o.date_created >= %s AND hp.status IN ({$placeholders})
				GROUP BY o.product_id ORDER BY qty DESC, o.product_id DESC LIMIT %d";
			$params = array_merge( array( $since ), $statuses, array( $candidates ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		} else {
			$statuses = array( 'wc-completed', 'wc-processing', 'wc-on-hold' );
			$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
			$sql = "SELECT o.product_id, SUM(o.product_qty) AS qty
				FROM {$orders} o INNER JOIN {$wpdb->posts} p ON p.ID = o.order_id
				WHERE o.date_created >= %s AND p.post_status IN ({$placeholders})
				GROUP BY o.product_id ORDER BY qty DESC, o.product_id DESC LIMIT %d";
			$params = array_merge( array( $since ), $statuses, array( $candidates ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		}

		if ( empty( $rows ) ) {
			return array();
		}

		$ids = array();
		foreach ( $rows as $row ) {
			$id = (int) $row->product_id;
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * Sale IDs ordered by actual discount percentage.
	 *
	 * Uses one grouped meta query instead of loading every sale product:
	 * simple products use their own regular/sale price, variable products
	 * contribute their best-discounted variation. The result is the same
	 * percentage the card badge shows, including variable products.
	 *
	 * @param int  $limit     Candidate count.
	 * @param bool $in_stock  Exclude out-of-stock products.
	 * @param int  $term_id   Optional product_cat term.
	 * @return int[]
	 */
	private function discount_ordered_ids( $limit, $in_stock = false, $term_id = 0 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wc_product_meta_lookup';
		if ( ! $this->table_exists( $table ) ) {
			return array();
		}

		/* Leave headroom: the category filter runs after the top candidates. */
		$candidates = max( 1, (int) $limit * ( $term_id ? 6 : 3 ) );
		$stock      = $in_stock ? " AND l.stock_status <> 'outofstock'" : '';

		$sql = "SELECT d.parent_id, MAX(d.disc) AS disc
			FROM (
				SELECT CASE WHEN p.post_parent > 0 THEN p.post_parent ELSE p.ID END AS parent_id,
					CASE
						WHEN CAST(reg.meta_value AS DECIMAL(19,4)) > 0
							AND CAST(sale.meta_value AS DECIMAL(19,4)) > 0
							AND CAST(sale.meta_value AS DECIMAL(19,4)) < CAST(reg.meta_value AS DECIMAL(19,4))
						THEN (CAST(reg.meta_value AS DECIMAL(19,4)) - CAST(sale.meta_value AS DECIMAL(19,4))) / CAST(reg.meta_value AS DECIMAL(19,4))
						ELSE 0
					END AS disc
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} reg ON reg.post_id = p.ID AND reg.meta_key = '_regular_price'
				INNER JOIN {$wpdb->postmeta} sale ON sale.post_id = p.ID AND sale.meta_key = '_sale_price'
				WHERE p.post_type IN ('product','product_variation')
					AND p.post_status = 'publish'
					AND reg.meta_value <> ''
					AND sale.meta_value <> ''
			) d
			INNER JOIN {$table} l ON l.product_id = d.parent_id
			INNER JOIN {$wpdb->posts} pp ON pp.ID = d.parent_id AND pp.post_type = 'product' AND pp.post_status = 'publish'
			WHERE l.onsale = 1{$stock}
			GROUP BY d.parent_id
			HAVING disc > 0
			ORDER BY disc DESC, d.parent_id DESC
			LIMIT %d";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$ordered = $wpdb->get_col( $wpdb->prepare( $sql, $candidates ) );
		return array_map( 'intval', (array) $ordered );
	}

	/**
	 * Cache table existence once per request.
	 *
	 * @param string $table Full table name.
	 * @return bool
	 */
	private function table_exists( $table ) {
		static $cache = array();
		if ( isset( $cache[ $table ] ) ) {
			return $cache[ $table ];
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$cache[ $table ] = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $cache[ $table ];
	}

	/**
	 * Build a stable cache key.
	 *
	 * @param array $args Normalized args.
	 * @return string
	 */
	private function cache_key( $args ) {
		if ( empty( $args['ttl'] ) ) {
			return '';
		}
		$hash = md5( wp_json_encode( $args ) . '|' . self::version() );
		return 'hsw_lp_' . $hash;
	}

	/**
	 * Read from object cache, then transient.
	 *
	 * @param string $key Cache key.
	 * @return array|null
	 */
	private function cache_get( $key ) {
		$hit = wp_cache_get( $key, self::CACHE_GROUP );
		if ( is_array( $hit ) ) {
			return $hit;
		}

		$hit = get_transient( $key );
		if ( is_array( $hit ) ) {
			wp_cache_set( $key, $hit, self::CACHE_GROUP, HOUR_IN_SECONDS );
			return $hit;
		}
		return null;
	}

	/**
	 * Write both cache layers.
	 *
	 * @param string $key  Cache key.
	 * @param array  $data Payload.
	 * @param int    $ttl  Seconds.
	 */
	private function cache_set( $key, $data, $ttl ) {
		$ttl = max( 1, (int) $ttl );
		wp_cache_set( $key, $data, self::CACHE_GROUP, $ttl );
		set_transient( $key, $data, $ttl );
	}

	/**
	 * Remove a stale payload.
	 *
	 * @param string $key Cache key.
	 */
	private function cache_delete( $key ) {
		wp_cache_delete( $key, self::CACHE_GROUP );
		delete_transient( $key );
	}

	/**
	 * Never cache past the nearest running sale.
	 *
	 * @param int $ttl   Requested TTL.
	 * @param int $timer Nearest sale end (0 = none).
	 * @return int
	 */
	private function effective_ttl( $ttl, $timer ) {
		$ttl = absint( $ttl );
		if ( $ttl <= 0 ) {
			return 0;
		}
		if ( $timer > 0 ) {
			$ttl = min( $ttl, max( 1, $timer - time() ) );
		}
		return $ttl;
	}
}
