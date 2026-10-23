<?php
/**
 * Front-end query wiring for the archive filter system.
 * Real WooCommerce params handled here:
 *   - instock=1       → only products with _stock_status = instock
 *   - express=1       → products in a shipping class (slug via hsw_express_shipping_class)
 *   - orderby=discount → sort by highest saving ratio (regular vs sale price)
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Archive_Query {

        public function __construct() {
                add_action( 'init', array( $this, 'normalize_filter_params' ), 5 );
                add_action( 'pre_get_posts', array( $this, 'apply' ) );
                add_filter( 'posts_clauses', array( $this, 'oos_last_clauses' ), 20, 2 );
                add_action( 'elementor/document/after_save', array( $this, 'sync_express_slug' ), 10, 2 );
        }

        /**
         * Without JavaScript the filter form may submit the same `filter_*`
         * checkbox name twice; PHP turns that into an array while WooCommerce
         * expects a comma-separated string. Normalise before the main query.
         *
         * Values are DECODED here, not re-encoded: WooCommerce runs
         * `wc_clean()` over every term, and that function deletes percent
         * octets — `wc_clean('%d8%b3%d8%a8%d8%b2')` is an empty string, so an
         * encoded Persian slug made the layered-nav filter vanish. Plain
         * `سبز` survives `wc_clean()` and `get_term_by()` sanitizes it back to
         * the stored slug on its own.
         */
        public function normalize_filter_params() {
                if ( empty( $_GET ) || ! is_array( $_GET ) ) {
                        return;
                }
                // phpcs:disable WordPress.Security.NonceVerification.Recommended -- public archive filters.
                foreach ( $_GET as $key => $value ) {
                        if ( ! is_string( $key ) || 0 !== strpos( $key, 'filter_' ) ) {
                                continue;
                        }

                        if ( is_array( $value ) ) {
                                $flat = array();
                                array_walk_recursive(
                                        $value,
                                        static function ( $item ) use ( &$flat ) {
                                                $flat[] = $item;
                                        }
                                );
                                $value = $flat;
                        } else {
                                $value = array( $value );
                        }

                        $terms = array();
                        foreach ( $value as $item ) {
                                foreach ( explode( ',', (string) $item ) as $part ) {
                                        $part = trim( sanitize_text_field( rawurldecode( (string) wp_unslash( $part ) ) ) );
                                        if ( '' !== $part ) {
                                                $terms[ $part ] = true;
                                        }
                                }
                        }

                        if ( empty( $terms ) ) {
                                unset( $_GET[ $key ] );
                                continue;
                        }
                        $_GET[ $key ] = implode( ',', array_keys( $terms ) );
                }
                // phpcs:enable
        }

        /**
         * Sort in-stock products first on the product archive.
         *
         * `_stock_status` lives in postmeta, so a LEFT JOIN plus a CASE at the
         * front of ORDER BY keeps every other ordering rule intact (price,
         * newest, discount…) and simply pushes the out-of-stock rows down.
         *
         * @param array    $clauses Query clauses.
         * @param WP_Query $q       Query.
         * @return array
         */
        public function oos_last_clauses( $clauses, $q ) {
                if ( 'yes' !== $q->get( 'hsw_oos_last' ) ) {
                        return $clauses;
                }

                global $wpdb;

                $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS hsw_stock ON ( {$wpdb->posts}.ID = hsw_stock.post_id AND hsw_stock.meta_key = '_stock_status' )";

                $rank = "CASE WHEN hsw_stock.meta_value = 'outofstock' THEN 1 ELSE 0 END ASC";
                $clauses['orderby'] = $clauses['orderby'] ? $rank . ', ' . $clauses['orderby'] : $rank;

                return $clauses;
        }

        /**
         * The express shipping-class slug: a site-wide option synced from the
         * archive widgets on every Elementor save, overridable by the
         * `hsw_express_shipping_class` filter. One source of truth for the
         * query, the filter widgets and the product cards.
         */
        public static function express_slug() {
                $slug = get_option( 'hsw_express_slug', 'express' );
                return (string) apply_filters( 'hsw_express_shipping_class', $slug );
        }

        /**
         * Persist the `express_slug` control value of any HSW widget inside a
         * saved Elementor document (template/page) as the site-wide option.
         *
         * IMPORTANT: never call `$document->get_elements_data()` in here.
         * Elementor fires this action from inside `Document::save()`, and
         * `get_elements_data()` walks the document again, which re-enters the
         * save pipeline and recurses until the PHP process dies (Apache child
         * exits with status 255 and the editor request is reset). The hook
         * already hands us the saved data, so use that.
         *
         * @param \Elementor\Core\Base\Document $document Saved document.
         * @param array                          $data     Saved elements data.
         */
        public function sync_express_slug( $document, $data = array() ) {
                static $busy = false;

                if ( $busy ) {
                        return;
                }

                if ( is_string( $data ) ) {
                        $data = json_decode( $data, true );
                }
                if ( ! is_array( $data ) ) {
                        return;
                }

                /*
                 * Elementor passes the whole document payload here
                 * (`['settings' => …, 'elements' => …]`), not the elements
                 * array itself.
                 */
                $elements = isset( $data['elements'] ) && is_array( $data['elements'] ) ? $data['elements'] : array();
                if ( empty( $elements ) ) {
                        return;
                }

                $busy = true;
                $slug = $this->find_express_slug( $elements );
                if ( $slug && get_option( 'hsw_express_slug' ) !== $slug ) {
                        update_option( 'hsw_express_slug', $slug );
                }
                $busy = false;
        }

        /**
         * Recursively walk Elementor element data for an express_slug setting.
         *
         * @param array $elements Elementor elements tree.
         * @return string
         */
        private function find_express_slug( $elements ) {
                foreach ( $elements as $el ) {
                        if ( ! is_array( $el ) ) {
                                continue;
                        }
                        if ( ! empty( $el['settings']['express_slug'] ) && is_string( $el['settings']['express_slug'] ) ) {
                                return sanitize_title( $el['settings']['express_slug'] );
                        }
                        if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
                                $found = $this->find_express_slug( $el['elements'] );
                                if ( $found ) {
                                        return $found;
                                }
                        }
                }
                return '';
        }

        /**
         * Only touch the frontend product archive main query.
         */
        public function apply( $q ) {
                if ( is_admin() || ( ! $q->is_main_query() && ! apply_filters( 'hsw_archive_query_allow', false, $q ) ) ) {
                        return;
                }
                if ( ! function_exists( 'is_woocommerce' ) || ! ( $q->is_post_type_archive( 'product' ) || $q->is_tax( get_object_taxonomies( 'product' ) ) ) ) {
                        return;
                }

                /*
                 * Out-of-stock products sink to the end of every shop/category
                 * archive. The flag is read back in oos_last_clauses() so the
                 * JOIN/ORDER BY only ever touches the product archive query.
                 */
                $oos_last = 'yes' === get_option( 'hsw_oos_last', 'yes' );

                if ( apply_filters( 'hsw_archive_oos_last', $oos_last ) ) {
                        $q->set( 'hsw_oos_last', 'yes' );
                }

                // phpcs:disable WordPress.Security.NonceVerification.Recommended -- public archive filters.
                $instock = isset( $_GET['instock'] ) ? sanitize_key( wp_unslash( $_GET['instock'] ) ) : '';
                $express = isset( $_GET['express'] ) ? sanitize_key( wp_unslash( $_GET['express'] ) ) : '';
                $orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
                $onsale  = isset( $_GET['onsale'] ) ? sanitize_key( wp_unslash( $_GET['onsale'] ) ) : '';
                $rating  = isset( $_GET['rating_filter'] ) ? (int) wp_unslash( $_GET['rating_filter'] ) : 0;
                // phpcs:enable

                if ( '1' === $instock ) {
                        $meta = $q->get( 'meta_query' );
                        $meta = is_array( $meta ) ? $meta : array();
                        $meta[] = array(
                                'key'   => '_stock_status',
                                'value' => 'instock',
                        );
                        $q->set( 'meta_query', $meta );
                }

                if ( '1' === $onsale && function_exists( 'wc_get_product_ids_on_sale' ) ) {
                        $sale_ids = wc_get_product_ids_on_sale();
                        $sale_ids = is_array( $sale_ids ) ? array_map( 'intval', $sale_ids ) : array();
                        $current  = array_map( 'intval', (array) $q->get( 'post__in' ) );
                        if ( empty( $current ) ) {
                                $q->set( 'post__in', $sale_ids ? $sale_ids : array( 0 ) );
                        } else {
                                $intersect = array_values( array_intersect( $current, $sale_ids ) );
                                $q->set( 'post__in', $intersect ? $intersect : array( 0 ) );
                        }
                }

                if ( $rating > 0 ) {
                        $meta = $q->get( 'meta_query' );
                        $meta = is_array( $meta ) ? $meta : array();
                        $meta[] = array(
                                'key'     => '_wc_average_rating',
                                'value'   => min( 5, $rating ),
                                'compare' => '>=',
                                'type'    => 'DECIMAL',
                        );
                        $q->set( 'meta_query', $meta );
                }

                if ( '1' === $express ) {
                        $slug = self::express_slug();
                        if ( $slug && taxonomy_exists( 'product_shipping_class' ) ) {
                                $tax = $q->get( 'tax_query' );
                                $tax = is_array( $tax ) ? $tax : array();
                                $tax[] = array(
                                        'taxonomy' => 'product_shipping_class',
                                        'field'    => 'slug',
                                        'terms'    => $slug,
                                );
                                $q->set( 'tax_query', $tax );
                        }
                }

                /*
                 * Attribute filters (`filter_pa_*`).
                 *
                 * WooCommerce turns these into a tax query only for taxonomies
                 * that exist in its own attribute table. A store that created
                 * its attributes another way — or with a non-ASCII name such as
                 * `pa_رنگ` — got a sidebar that ticked the box, highlighted the
                 * chip, and still listed every product. Adding the missing tax
                 * query here makes the filter real; taxonomies WooCommerce
                 * already handled are skipped so nothing is applied twice.
                 */
                $extra_tax = $this->attribute_filter_tax_query( $q );
                if ( $extra_tax ) {
                        $tax = $q->get( 'tax_query' );
                        $tax = is_array( $tax ) ? $tax : array();
                        $q->set( 'tax_query', array_merge( $tax, $extra_tax ) );
                }

                if ( 'discount' === $orderby ) {
                        $q->set( 'hsw_orderby_discount', true );
                        add_filter( 'posts_join', array( $this, 'discount_join' ), 10, 2 );
                        add_filter( 'posts_orderby', array( $this, 'discount_orderby' ), 10, 2 );
                }
        }

        /**
         * Build a tax query for `filter_*` params WooCommerce left alone.
         *
         * Values are normalised with `sanitize_title()`, which keeps existing
         * `%d8%b3…` octets and encodes plain UTF-8, so both URL spellings match
         * the term slug stored in the database.
         *
         * @param WP_Query $q Main archive query.
         * @return array Tax query rows (possibly empty).
         */
        private function attribute_filter_tax_query( $q ) {
                if ( empty( $_GET ) || ! is_array( $_GET ) ) {
                        return array();
                }

                $existing = $q->get( 'tax_query' );
                $existing = is_array( $existing ) ? $existing : array();
                $handled  = array();
                foreach ( $existing as $row ) {
                        if ( is_array( $row ) && ! empty( $row['taxonomy'] ) ) {
                                $handled[ (string) $row['taxonomy'] ] = true;
                        }
                }

                $out = array();
                // phpcs:disable WordPress.Security.NonceVerification.Recommended -- public archive filters.
                foreach ( $_GET as $key => $value ) {
                        if ( ! is_string( $key ) || 0 !== strpos( $key, 'filter_' ) ) {
                                continue;
                        }
                        $taxonomy = substr( $key, 7 );
                        if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) || isset( $handled[ $taxonomy ] ) ) {
                                continue;
                        }

                        $terms = is_array( $value ) ? $value : explode( ',', (string) $value );
                        $terms = array_map( 'strval', $terms );
                        $terms = array_map( 'wp_unslash', $terms );
                        $terms = array_map( 'sanitize_title', $terms );
                        $terms = array_values( array_unique( array_filter( $terms, 'strlen' ) ) );
                        if ( empty( $terms ) ) {
                                continue;
                        }

                        $query_type = isset( $_GET[ 'query_type_' . $taxonomy ] ) ? sanitize_key( wp_unslash( $_GET[ 'query_type_' . $taxonomy ] ) ) : 'and';

                        $out[] = array(
                                'taxonomy'         => $taxonomy,
                                'field'            => 'slug',
                                'terms'            => $terms,
                                'operator'         => 'or' === $query_type ? 'IN' : 'AND',
                                'include_children' => false,
                        );
                }
                // phpcs:enable

                return $out;
        }

        /**
         * Join the two price meta rows once each (aliases reused by orderby).
         */
        public function discount_join( $join, $q ) {
                if ( ! $q->get( 'hsw_orderby_discount' ) || false !== strpos( $join, 'hswp_reg' ) ) {
                        remove_filter( 'posts_join', array( $this, 'discount_join' ), 10 );
                        return $join;
                }
                global $wpdb;
                $join .= " LEFT JOIN {$wpdb->postmeta} AS hswp_reg ON ({$wpdb->posts}.ID = hswp_reg.post_id AND hswp_reg.meta_key = '_regular_price')
                           LEFT JOIN {$wpdb->postmeta} AS hswp_sal ON ({$wpdb->posts}.ID = hswp_sal.post_id AND hswp_sal.meta_key = '_price')";
                remove_filter( 'posts_join', array( $this, 'discount_join' ), 10 );
                return $join;
        }

        /**
         * DESC by saving ratio; plain-priced products last.
         */
        public function discount_orderby( $orderby, $q ) {
                if ( ! $q->get( 'hsw_orderby_discount' ) ) {
                        remove_filter( 'posts_orderby', array( $this, 'discount_orderby' ), 10 );
                        return $orderby;
                }
                remove_filter( 'posts_orderby', array( $this, 'discount_orderby' ), 10 );
                $ratio = "CASE WHEN hswp_reg.meta_value+0 > 0 AND hswp_sal.meta_value+0 > 0
                               AND hswp_sal.meta_value+0 < hswp_reg.meta_value+0
                               THEN (hswp_reg.meta_value+0 - hswp_sal.meta_value+0) / hswp_reg.meta_value+0
                               ELSE 0 END";
                return $ratio . ' DESC, ' . $orderby;
        }
}
