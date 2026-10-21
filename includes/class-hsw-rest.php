<?php
/**
 * REST routes:
 *  - /hsw/v1/search  : admin product search for the chooser control.
 *  - /hsw/v1/loop    : server-rendered cards fragment (tab switching).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Rest {

        const NS = 'hsw/v1';

        public function __construct() {
                add_action( 'rest_api_init', array( $this, 'routes' ) );
        }

        public function routes() {
                register_rest_route(
                        self::NS,
                        '/search',
                        array(
                                'methods'             => 'GET',
                                'callback'            => array( $this, 'search' ),
                                'permission_callback' => array( $this, 'can_edit' ),
                        )
                );

                register_rest_route(
                        self::NS,
                        '/loop',
                        array(
                                'methods'             => 'GET',
                                'callback'            => array( $this, 'loop_fragment' ),
                                'permission_callback' => '__return_true',
                        )
                );

                /*
                 * Header mega-menu product preview.
                 * Public and read-only, like /loop: it only exposes already
                 * published products of a public product_cat term, and the
                 * mega menu calls it lazily the first time a category opens.
                 */
                register_rest_route(
                        self::NS,
                        '/header-products',
                        array(
                                'methods'             => 'GET',
                                'callback'            => array( $this, 'header_products' ),
                                'permission_callback' => '__return_true',
                                'args'                => array(
                                        'cat'   => array( 'sanitize_callback' => 'absint' ),
                                        'limit' => array( 'sanitize_callback' => 'absint' ),
                                ),
                        )
                );
        }

        public function can_edit() {
                return current_user_can( 'edit_posts' );
        }

        /**
         * WooCommerce-style product search for select2.
         */
        public function search( $request ) {
                $term  = sanitize_text_field( (string) $request->get_param( 'term' ) );
                $limit = min( 30, max( 5, (int) $request->get_param( 'limit' ) ) );

                $query = new WP_Query(
                        array(
                                'post_type'      => 'product',
                                'post_status'    => 'publish',
                                'posts_per_page' => $limit,
                                's'              => $term,
                                'fields'         => 'ids',
                                'no_found_rows'  => true,
                        )
                );

                $out = array();
                foreach ( $query->posts as $id ) {
                        $product = wc_get_product( $id );
                        if ( ! $product ) {
                                continue;
                        }
                        $out[] = array(
                                'id'   => $product->get_id(),
                                'text' => sprintf( '%s (#%d)', wp_strip_all_tags( $product->get_name() ), $product->get_id() ),
                        );
                }
                return rest_ensure_response( $out );
        }

        /**
         * Render a cards fragment for tab switching.
         * Params: type (deal|best|popular), source, per_page, term_id, nonce_widget (widget signature hash).
         */
        public function loop_fragment( $request ) {
                $type     = in_array( $request->get_param( 'type' ), array( 'deal', 'best', 'popular' ), true ) ? $request->get_param( 'type' ) : 'deal';
                $source   = sanitize_key( (string) $request->get_param( 'source' ) );
                $per_page = min( 24, max( 1, (int) $request->get_param( 'per_page' ) ) );
                $term_id  = (int) $request->get_param( 'term_id' );
                $sig      = (string) $request->get_param( 'sig' );

                // Signature = md5( AUTH_SALT + widget_key ) — prevents arbitrary fragment injection.
                if ( ! hash_equals( md5( wp_salt( 'auth' ) . '|hsw|' . $type ), (string) $sig ) ) {
                        return new WP_Error( 'hsw_bad_sig', 'invalid signature', array( 'status' => 400 ) );
                }

                $allowed_sources = array( 'sale', 'best_selling', 'rating', 'newest', 'discount', 'featured' );
                if ( ! in_array( $source, $allowed_sources, true ) ) {
                        return new WP_Error( 'hsw_bad_source', 'invalid source', array( 'status' => 400 ) );
                }

                $products = HSW_Plugin::instance()->products->query(
                        array(
                                'source'  => $source,
                                'count'   => $per_page,
                                'term_id' => $term_id,
                        )
                );

                // Elementor is guaranteed loaded (boot is dependency-guarded),
                // but the base class only loads with widget registration —
                // load it lazily here for REST-only requests.
                if ( ! class_exists( 'HSW_Widget_Base' ) ) {
                        require_once HSW_DIR . 'includes/class-hsw-widget-base.php';
                }

                $html = HSW_Widget_Base::cards_html( $type, $products, array() );

                return rest_ensure_response(
                        array(
                                'html'  => $html,
                                'found' => count( $products ),
                        )
                );
        }

        /**
         * Compact product tiles for one product category (mega menu preview).
         *
         * Only public, already-published products are exposed, so the route is
         * open like /loop. The mega menu calls it on first expand so a header
         * with six categories costs zero product queries until it is opened.
         *
         * @param WP_REST_Request $request Request.
         * @return WP_REST_Response
         */
        public function header_products( $request ) {
                $term_id = absint( $request->get_param( 'cat' ) );
                $limit   = (int) $request->get_param( 'limit' );
                $limit   = $limit > 0 ? min( 8, $limit ) : 4;

                if ( ! $term_id || ! function_exists( 'wc_get_products' ) ) {
                        return rest_ensure_response( array( 'html' => '', 'found' => 0 ) );
                }

                $term = get_term( $term_id, 'product_cat' );
                if ( ! $term instanceof WP_Term ) {
                        return rest_ensure_response( array( 'html' => '', 'found' => 0 ) );
                }

                /*
                 * No `visibility` arg on purpose: the category counter in the
                 * menu comes from term counts, which include hidden products.
                 * Filtering by catalog visibility here produced empty previews
                 * for categories the menu advertised as non-empty.
                 */
                $products = wc_get_products(
                        array(
                                'status'  => 'publish',
                                'limit'   => $limit,
                                'category' => array( $term->slug ),
                                'orderby' => 'date',
                                'order'   => 'DESC',
                                'return'  => 'objects',
                        )
                );

                $html = '';
                foreach ( $products as $product ) {
                        $html .= $this->mini_product_html( $product );
                }

                return rest_ensure_response(
                        array(
                                'html'  => $html,
                                'found' => count( $products ),
                        )
                );
        }

        /**
         * One 64px tile: thumbnail + name + price (or the CFP contact text).
         *
         * @param WC_Product $product Product.
         * @return string
         */
        private function mini_product_html( $product ) {
                if ( ! $product instanceof WC_Product ) {
                        return '';
                }

                $name = $product->get_name();
                $url  = $product->get_permalink();

                $img = $product->get_image(
                        'woocommerce_gallery_thumbnail',
                        array(
                                'class'   => 'hsw-hd__megathumbimg',
                                'alt'     => $name,
                                'loading' => 'lazy',
                        )
                );
                // Some themes/older WC drop the attribute; make it explicit.
                if ( false === strpos( $img, 'loading=' ) ) {
                        $img = str_replace( '<img ', '<img loading="lazy" decoding="async" ', $img );
                }

                $meta = '';
                if ( HSW_Helpers::is_call_for_price( $product ) ) {
                        $meta = HSW_Helpers::cfp_inline_html( $product, false, 'hsw-hd__megacfp' );
                } else {
                        $meta = $product->get_price_html();
                }
                if ( '' === trim( (string) $meta ) ) {
                        $meta = '<span class="hsw-hd__megaprice">' . esc_html__( 'مشاهده', 'hamishop-elementor-widgets' ) . '</span>';
                }

                $out  = '<a class="hsw-hd__megathumb" href="' . esc_url( $url ) . '">';
                $out .= '<span class="hsw-hd__megathumbpic">' . $img . '</span>';
                $out .= '<span class="hsw-hd__megathumbtext">';
                $out .= '<span class="hsw-hd__megathumbname">' . esc_html( $name ) . '</span>';
                $out .= '<span class="hsw-hd__megathumbmeta">' . $meta . '</span>';
                $out .= '</span>';
                $out .= '</a>';

                return $out;
        }
}
