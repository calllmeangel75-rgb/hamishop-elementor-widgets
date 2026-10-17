<?php
/**
 * Product queries for the loop widgets.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Products {

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
