<?php
/**
 * Product queries for the loop widgets.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Products {

        /**
         * Same-category product IDs, walking up to the parent category when the
         * current category has no other products.
         *
         * The walk only moves up when the current level is empty. Products from
         * the first non-empty level are returned as-is, so a category with two
         * products still shows two cards (the current product is excluded).
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
                                        'tax_query'           => array(
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

                        /*
                         * Nothing left at this level: move exactly one level up.
                         * Multiple direct categories can share a parent, so the
                         * unique parent list is queried once.
                         */
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
         * @param array $args {source, count, ids, term_id, exclude_oos}
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
                        )
                );

                $exclude_oos = ! empty( $args['exclude_oos'] );
                $want        = (int) $args['count'];
                $limit       = $exclude_oos ? $want * 2 : $want;

                $base = array(
                        'status'   => 'publish',
                        'limit'    => $limit,
                        'orderby'  => 'date',
                        'order'    => 'DESC',
                        'visible'  => 'visible',
                );

                if ( ! empty( $args['term_id'] ) ) {
                        $base['category'] = array( get_term_field( 'slug', (int) $args['term_id'], 'product_cat' ) );
                }

                switch ( $args['source'] ) {
                        case 'manual':
                                $ids = array_filter( array_map( 'absint', (array) $args['ids'] ) );
                                if ( empty( $ids ) ) {
                                        return array();
                                }
                                $base['include'] = $ids;
                                $base['orderby'] = 'include';
                                break;

                        case 'sale':
                                $ids = wc_get_product_ids_on_sale();
                                if ( empty( $ids ) ) {
                                        return array();
                                }
                                $base['include'] = $ids;
                                $base['orderby'] = 'date';
                                break;

                        case 'best_selling':
                                $base['meta_key'] = 'total_sales'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                                $base['orderby']  = 'meta_value_num';
                                $base['order']    = 'DESC';
                                break;

                        case 'rating':
                                $base['meta_key'] = '_wc_average_rating'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                                $base['orderby']  = 'meta_value_num';
                                $base['order']    = 'DESC';
                                break;

                        case 'discount':
                                $ids = wc_get_product_ids_on_sale();
                                if ( empty( $ids ) ) {
                                        return array();
                                }
                                $base['include'] = $ids;
                                $base['limit']   = 60; // Wide net, sorted below by percent.
                                break;

                        case 'featured':
                                $ids = wc_get_featured_product_ids();
                                if ( empty( $ids ) ) {
                                        return array();
                                }
                                $base['include'] = $ids;
                                break;

                        case 'newest':
                        default:
                                $base['orderby'] = 'date';
                                break;
                }

                $products = wc_get_products( $base );

                if ( 'discount' === $args['source'] ) {
                        usort(
                                $products,
                                function ( $a, $b ) {
                                        return HSW_Helpers::discount_percent( $b ) <=> HSW_Helpers::discount_percent( $a );
                                }
                        );
                }

                if ( $exclude_oos && 'manual' !== $args['source'] ) {
                        $products = array_values(
                                array_filter(
                                        $products,
                                        function ( $p ) {
                                                return $p->is_in_stock();
                                        }
                                )
                        );
                }

                return array_slice( $products, 0, $want );
        }
}
