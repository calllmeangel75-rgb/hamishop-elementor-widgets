<?php
/**
 * REST routes:
 *  - /hsw/v1/search         : admin product search for the chooser control.
 *  - /hsw/v1/loop           : server-rendered cards fragment (tab switching).
 *  - /hsw/v1/header-cascade : lazy column for the store-header cascade menu.
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
                                'args'                => array(
                                        'term'  => array(
                                                'type'              => 'string',
                                                'required'          => true,
                                                'sanitize_callback' => 'sanitize_text_field',
                                                'validate_callback' => static function ( $value ) {
                                                        return is_string( $value ) && strlen( $value ) <= 120;
                                                },
                                        ),
                                        'limit' => array(
                                                'type'              => 'integer',
                                                'default'           => 20,
                                                'minimum'           => 5,
                                                'maximum'           => 30,
                                                'sanitize_callback' => 'absint',
                                                'validate_callback' => 'rest_validate_request_arg',
                                        ),
                                ),
                        )
                );

                /*
                 * Related products for the mobile «کالا اضافه شد» sheet.
                 * Public: it only exposes products that are already public.
                 */
                register_rest_route(
                        self::NS,
                        '/qty-nonce',
                        array(
                                'methods'             => 'GET',
                                'callback'            => array( $this, 'qty_nonce' ),
                                'permission_callback' => '__return_true',
                        )
                );

                register_rest_route(
                        self::NS,
                        '/related',
                        array(
                                'methods'             => 'GET',
                                'callback'            => array( $this, 'related' ),
                                'permission_callback' => '__return_true',
                                'args'                => array(
                                        'product' => array(
                                                'type'              => 'integer',
                                                'required'          => true,
                                                'sanitize_callback' => 'absint',
                                        ),
                                        'limit'   => array(
                                                'type'              => 'integer',
                                                'default'           => 4,
                                                'minimum'           => 1,
                                                'maximum'           => 8,
                                                'sanitize_callback' => 'absint',
                                        ),
                                ),
                        )
                );

                register_rest_route(
                        self::NS,
                        '/loop',
                        array(
                                'methods'             => 'GET',
                                'callback'            => array( $this, 'loop_fragment' ),
                                'permission_callback' => '__return_true',
                                'args'                => array(
                                        'type' => array(
                                                'type'              => 'string',
                                                'default'           => 'deal',
                                                'enum'              => array( 'deal', 'best', 'popular' ),
                                                'sanitize_callback' => 'sanitize_key',
                                                'validate_callback' => 'rest_validate_request_arg',
                                        ),
                                        'source' => array(
                                                'type'              => 'string',
                                                'required'          => true,
                                                'enum'              => array( 'sale', 'best_selling', 'rating', 'newest', 'discount', 'featured' ),
                                                'sanitize_callback' => 'sanitize_key',
                                                'validate_callback' => 'rest_validate_request_arg',
                                        ),
                                        'per_page' => array(
                                                'type'              => 'integer',
                                                'default'           => 8,
                                                'minimum'           => 1,
                                                'maximum'           => 24,
                                                'sanitize_callback' => 'absint',
                                                'validate_callback' => 'rest_validate_request_arg',
                                        ),
                                        'term_id' => array(
                                                'type'              => 'integer',
                                                'default'           => 0,
                                                'minimum'           => 0,
                                                'sanitize_callback' => 'absint',
                                                'validate_callback' => 'rest_validate_request_arg',
                                        ),
                                        'post_id' => array(
                                                'type'              => 'integer',
                                                'default'           => 0,
                                                'minimum'           => 0,
                                                'sanitize_callback' => 'absint',
                                                'validate_callback' => 'rest_validate_request_arg',
                                        ),
                                        'widget_id' => array(
                                                'type'              => 'string',
                                                'default'           => '',
                                                'sanitize_callback' => 'sanitize_text_field',
                                                'validate_callback' => static function ( $value ) {
                                                        return is_string( $value ) && strlen( $value ) <= 32;
                                                },
                                        ),
                                        'sig'       => array(
                                                'type'              => 'string',
                                                'required'          => true,
                                                'sanitize_callback' => 'sanitize_text_field',
                                        ),
                                ),
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
                                        'cat'   => array(
                                                'type'              => 'integer',
                                                'required'          => true,
                                                'minimum'           => 1,
                                                'sanitize_callback' => 'absint',
                                                'validate_callback' => static function ( $value ) {
                                                        return (bool) term_exists( absint( $value ), 'product_cat' );
                                                },
                                        ),
                                        'limit' => array(
                                                'type'              => 'integer',
                                                'default'           => 4,
                                                'minimum'           => 1,
                                                'maximum'           => 8,
                                                'sanitize_callback' => 'absint',
                                                'validate_callback' => 'rest_validate_request_arg',
                                        ),
                                ),
                        )
                );

                /*
                 * One lazy cascade column for the desktop store header. `/cat`
                 * is the category whose children/products should be returned.
                 * The transient mirror keeps repeat hovers free of taxonomy and
                 * product queries, while `product_version()` expires the cache
                 * whenever the catalogue changes.
                 */
                register_rest_route(
                        self::NS,
                        '/header-cascade',
                        array(
                                'methods'             => 'GET',
                                'callback'            => array( $this, 'header_cascade' ),
                                'permission_callback' => '__return_true',
                                'args'                => array(
                                        'cat'      => array(
                                                'type'              => 'integer',
                                                'required'          => true,
                                                'minimum'           => 1,
                                                'sanitize_callback' => 'absint',
                                                'validate_callback' => static function ( $value ) {
                                                        return (bool) term_exists( absint( $value ), 'product_cat' );
                                                },
                                        ),
                                        'level'    => array(
                                                'type'              => 'integer',
                                                'default'           => 1,
                                                'minimum'           => 1,
                                                'maximum'           => 3,
                                                'sanitize_callback' => 'absint',
                                                'validate_callback' => 'rest_validate_request_arg',
                                        ),
                                        'depth'    => array(
                                                'type'              => 'integer',
                                                'default'           => 3,
                                                'minimum'           => 1,
                                                'maximum'           => 3,
                                                'sanitize_callback' => 'absint',
                                                'validate_callback' => 'rest_validate_request_arg',
                                        ),
                                        'limit'    => array(
                                                'type'              => 'integer',
                                                'default'           => 4,
                                                'minimum'           => 1,
                                                'maximum'           => 8,
                                                'sanitize_callback' => 'absint',
                                                'validate_callback' => 'rest_validate_request_arg',
                                        ),
                                        'products' => array(
                                                'type'              => 'boolean',
                                                'default'           => true,
                                                'sanitize_callback' => 'rest_sanitize_boolean',
                                                'validate_callback' => 'rest_validate_request_arg',
                                        ),
                                ),
                        )
                );
        }

        /**
         * Signature for a /loop fragment request.
         *
         * Bound to the post + widget that produced the markup, so a caller
         * cannot ask the server to render an arbitrary widget's settings, and
         * it never expires (unlike a nonce, which would break tab switching
         * after 12–24 hours or as soon as a page cache stores the HTML).
         *
         * @param int    $post_id   Post holding the widget.
         * @param string $widget_id Elementor element ID.
         * @return string
         */
        public static function loop_signature( $post_id, $widget_id ) {
                return hash_hmac( 'sha256', (int) $post_id . '|' . (string) $widget_id, 'hsw-loop|' . wp_salt( 'auth' ) );
        }

        /**
         * Version marker that moves whenever a product changes, so cached
         * fragments expire with the catalogue and not on a fixed clock alone.
         *
         * @return string
         */
        private static function product_version() {
                if ( class_exists( 'WC_Cache_Helper' ) ) {
                        return (string) WC_Cache_Helper::get_transient_version( 'product' );
                }
                return '1';
        }

        /**
         * Send a short public cache header — the fragment is identical for every
         * visitor, so a proxy may keep it for a few minutes.
         *
         * @param int $seconds Max age.
         */
        private static function cache_header( $seconds = 300 ) {
                if ( headers_sent() ) {
                        return;
                }
                header( 'Cache-Control: public, max-age=' . absint( $seconds ) );
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
         *
         * The fragment must look exactly like the server-rendered cards, so the
         * widget's own Elementor settings are re-read here — without them the
         * first tab switch silently fell back to the default texts and badges.
         *
         * @param WP_REST_Request $request Request.
         * @return WP_REST_Response|WP_Error
         */
        /**
         * Related products for the post-add sheet.
         *
         * @param WP_REST_Request $request Request.
         * @return WP_REST_Response
         */
        /**
         * Fresh cart-quantity nonce for pages served from a full-page cache.
         *
         * @return WP_REST_Response
         */
        public function qty_nonce() {
                return new WP_REST_Response( array( 'nonce' => wp_create_nonce( 'hsw-cart-qty' ) ), 200 );
        }

        public function related( $request ) {
                $id    = (int) $request['product'];
                $limit = (int) $request['limit'];
                $out   = array();

                if ( ! function_exists( 'wc_get_related_products' ) || ! $id ) {
                        return rest_ensure_response( array( 'items' => array() ) );
                }

                $ids = wc_get_related_products( $id, $limit, array( $id ) );
                if ( empty( $ids ) ) {
                        /* Fall back to the same category so the sheet is never empty. */
                        $terms = wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'ids' ) );
                        if ( $terms && ! is_wp_error( $terms ) ) {
                                $ids = get_posts(
                                        array(
                                                'post_type'      => 'product',
                                                'posts_per_page' => $limit,
                                                'post__not_in'   => array( $id ),
                                                'fields'         => 'ids',
                                                'no_found_rows'  => true,
                                                'tax_query'      => array(
                                                        array(
                                                                'taxonomy' => 'product_cat',
                                                                'field'    => 'term_id',
                                                                'terms'    => $terms,
                                                        ),
                                                ),
                                        )
                                );
                        }
                }

                foreach ( (array) $ids as $pid ) {
                        $product = wc_get_product( $pid );
                        if ( ! $product || ! $product->is_visible() ) {
                                continue;
                        }

                        $image = get_the_post_thumbnail_url( $pid, 'woocommerce_thumbnail' );

                        $regular = (float) $product->get_regular_price();
                        $sale    = (float) $product->get_price();
                        $off     = ( $regular > 0 && $sale > 0 && $sale < $regular ) ? (int) round( ( ( $regular - $sale ) / $regular ) * 100 ) : 0;

                        $out[] = array(
                                'id'       => (int) $pid,
                                'name'     => $product->get_name(),
                                /* Clean price only: get_price_html() adds «قیمت اصلی … بود» for sales. */
                                'price'    => wp_strip_all_tags( wc_price( (float) $product->get_price() ) ),
                                'regular'  => $regular > 0 ? wp_strip_all_tags( wc_price( $regular ) ) : '',
                                'off'      => $off,
                                'rating'   => (float) $product->get_average_rating(),
                                'reviews'  => (int) $product->get_review_count(),
                                'image'    => $image ? $image : '',
                                'url'      => get_permalink( $pid ),
                                'add'      => ( $product->is_type( 'simple' ) && $product->is_in_stock() ),
                                'on_sale'  => $product->is_on_sale(),
                        );
                }

                return rest_ensure_response( array( 'items' => $out ) );
        }

        public function loop_fragment( $request ) {
                $type      = (string) $request->get_param( 'type' );
                $source    = (string) $request->get_param( 'source' );
                $per_page  = (int) $request->get_param( 'per_page' );
                $term_id   = (int) $request->get_param( 'term_id' );
                $post_id   = (int) $request->get_param( 'post_id' );
                $widget_id = (string) $request->get_param( 'widget_id' );
                $sig       = (string) $request->get_param( 'sig' );

                if ( ! hash_equals( self::loop_signature( $post_id, $widget_id ), $sig ) ) {
                        return new WP_Error( 'hsw_bad_sig', 'invalid signature', array( 'status' => 400 ) );
                }

                if ( $term_id && ! term_exists( $term_id, 'product_cat' ) ) {
                        return new WP_Error( 'hsw_bad_term', 'unknown product category', array( 'status' => 400 ) );
                }

                $version = self::product_version();
                $key     = 'hsw_loop_' . md5( implode( '|', array( $type, $source, $per_page, $term_id, $post_id, $widget_id, $version ) ) );

                $cached = get_transient( $key );
                if ( is_array( $cached ) && isset( $cached['html'] ) ) {
                        self::cache_header( 300 );
                        return rest_ensure_response( $cached );
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

                $settings = $this->widget_settings( $post_id, $widget_id );
                $html     = HSW_Widget_Base::cards_html( $type, $products, $settings );

                $payload = array(
                        'html'  => $html,
                        'found' => count( $products ),
                );

                set_transient( $key, $payload, 5 * MINUTE_IN_SECONDS );
                self::cache_header( 300 );

                return rest_ensure_response( $payload );
        }

        /**
         * Elementor settings for one widget on one published post.
         *
         * Only scalars are returned: the settings array also holds responsive
         * and typography sub-arrays, and handing those to the card renderer
         * would only risk `esc_html( array )` notices.
         *
         * @param int    $post_id   Post ID.
         * @param string $widget_id Elementor element ID.
         * @return array<string,scalar>
         */
        private function widget_settings( $post_id, $widget_id ) {
                if ( ! $post_id || '' === (string) $widget_id ) {
                        return array();
                }

                $post = get_post( $post_id );
                if ( ! $post || 'publish' !== $post->post_status || '' !== (string) $post->post_password ) {
                        return array();
                }

                $raw = get_post_meta( $post_id, '_elementor_data', true );
                if ( ! is_string( $raw ) || '' === $raw ) {
                        return array();
                }

                $data = json_decode( $raw, true );
                if ( ! is_array( $data ) ) {
                        return array();
                }

                $element = $this->find_element( $data, (string) $widget_id );
                if ( ! $element || empty( $element['settings'] ) || ! is_array( $element['settings'] ) ) {
                        return array();
                }

                $out = array();
                foreach ( $element['settings'] as $name => $value ) {
                        if ( is_scalar( $value ) ) {
                                $out[ $name ] = $value;
                        }
                }

                return $out;
        }

        /**
         * Depth-first search for an Elementor element by ID.
         *
         * @param array  $elements Elements.
         * @param string $id       Element ID.
         * @return array|null
         */
        private function find_element( $elements, $id ) {
                foreach ( (array) $elements as $element ) {
                        if ( ! is_array( $element ) ) {
                                continue;
                        }
                        if ( isset( $element['id'] ) && (string) $element['id'] === $id ) {
                                return $element;
                        }
                        if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
                                $found = $this->find_element( $element['elements'], $id );
                                if ( $found ) {
                                        return $found;
                                }
                        }
                }

                return null;
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
                        return rest_ensure_response( array( 'html' => '', 'found' => 0, 'count' => 0 ) );
                }

                $term = get_term( $term_id, 'product_cat' );
                if ( ! $term instanceof WP_Term ) {
                        return rest_ensure_response( array( 'html' => '', 'found' => 0, 'count' => 0 ) );
                }

                /*
                 * `count` is the raw `product_count_product_cat` termmeta: the
                 * number the menu shows. It includes products that are hidden
                 * from the catalog, so the preview below can legitimately return
                 * fewer tiles than the counter — better an honest empty slot than
                 * advertising a product the shop has hidden.
                 */
                $count = (int) $term->count;

                $key    = 'hsw_hp_' . md5( $term_id . '|' . $limit . '|' . self::product_version() );
                $cached = get_transient( $key );
                if ( is_array( $cached ) && isset( $cached['html'] ) ) {
                        self::cache_header( 300 );
                        return rest_ensure_response( $cached );
                }

                /* Fetch a few extra: hidden / password-protected entries are
                   dropped below and must not eat into the requested count. */
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

                $html  = '';
                $found = 0;
                foreach ( $products as $product ) {
                        if ( ! $product instanceof WC_Product ) {
                                continue;
                        }
                        if ( $found >= $limit ) {
                                break;
                        }
                        /* Never leak a product the shop hides from the catalog. */
                        if ( ! $product->is_visible() ) {
                                continue;
                        }
                        /*
                         * Password-protected products have no public page. The
                         * password lives on the post, not on WC_Product — there
                         * is no `get_password()` getter.
                         */
                        if ( '' !== (string) get_post_field( 'post_password', $product->get_id() ) ) {
                                continue;
                        }
                        $html .= $this->mini_product_html( $product );
                        $found++;
                }

                $payload = array(
                        'html'  => $html,
                        'found' => $found,
                        'count' => $count,
                );

                set_transient( $key, $payload, 5 * MINUTE_IN_SECONDS );
                self::cache_header( 300 );

                return rest_ensure_response( $payload );
        }

        /**
         * Lazy column for the store-header cascade menu.
         *
         * @param WP_REST_Request $request Request.
         * @return WP_REST_Response
         */
        public function header_cascade( $request ) {
                $term_id  = absint( $request->get_param( 'cat' ) );
                $level    = max( 1, min( 3, (int) $request->get_param( 'level' ) ) );
                $depth    = max( 1, min( 3, (int) $request->get_param( 'depth' ) ) );
                $limit    = max( 1, min( 8, (int) $request->get_param( 'limit' ) ) );
                $products = (bool) $request->get_param( 'products' );

                if ( ! $term_id || ! class_exists( 'HSW_Header_Cascade' ) ) {
                        return rest_ensure_response( array( 'html' => '', 'term' => 0, 'level' => $level ) );
                }

                $term = get_term( $term_id, 'product_cat' );
                if ( ! $term instanceof WP_Term ) {
                        return rest_ensure_response( array( 'html' => '', 'term' => 0, 'level' => $level ) );
                }

                $locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
                $key    = 'hsw_hc_' . md5( implode( '|', array( $term_id, $level, $depth, $limit, $products ? 1 : 0, self::product_version(), $locale ) ) );
                $cached = get_transient( $key );

                if ( is_array( $cached ) && isset( $cached['html'] ) ) {
                        self::cache_header( 300 );
                        return rest_ensure_response( $cached );
                }

                $payload = array(
                        'html'  => HSW_Header_Cascade::column_html( $term, $level, $depth, $limit, $products ),
                        'term'  => (int) $term->term_id,
                        'level' => $level,
                );

                set_transient( $key, $payload, 5 * MINUTE_IN_SECONDS );
                self::cache_header( 300 );

                return rest_ensure_response( $payload );
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
