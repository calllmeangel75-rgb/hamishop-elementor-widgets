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
                add_action( 'pre_get_posts', array( $this, 'apply' ) );
        }

        /**
         * Only touch the frontend product archive main query.
         */
        public function apply( $q ) {
                if ( is_admin() || ! $q->is_main_query() ) {
                        return;
                }
                if ( ! function_exists( 'is_woocommerce' ) || ! ( $q->is_post_type_archive( 'product' ) || $q->is_tax( get_object_taxonomies( 'product' ) ) ) ) {
                        return;
                }

                // phpcs:disable WordPress.Security.NonceVerification.Recommended -- public archive filters.
                $instock = isset( $_GET['instock'] ) ? sanitize_key( wp_unslash( $_GET['instock'] ) ) : '';
                $express = isset( $_GET['express'] ) ? sanitize_key( wp_unslash( $_GET['express'] ) ) : '';
                $orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
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

                if ( '1' === $express ) {
                        $slug = apply_filters( 'hsw_express_shipping_class', 'express' );
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

                if ( 'discount' === $orderby ) {
                        $q->set( 'hsw_orderby_discount', true );
                        add_filter( 'posts_join', array( $this, 'discount_join' ), 10, 2 );
                        add_filter( 'posts_orderby', array( $this, 'discount_orderby' ), 10, 2 );
                }
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
