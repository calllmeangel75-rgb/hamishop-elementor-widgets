<?php
/**
 * Base for archive widgets — the store archive (desktop + mobile) design.
 * Current-term resolution, breadcrumb trail, URL filter helpers, demo data
 * and shared control builders.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

abstract class HSW_Archive_Base extends HSW_Widget_Base {

        public function get_categories() {
                return array( 'hamishop-archive' );
        }

        public function get_style_depends() {
                return array( 'hsw-fonts', 'hsw-archive' );
        }

        public function get_script_depends() {
                return array( 'hsw-archive' );
        }

        /* =========================================================================
         *  CONTEXT RESOLUTION
         * ====================================================================== */

        /**
         * True on any WooCommerce archive (shop / product taxonomy).
         */
        protected function is_archive_context() {
                return function_exists( 'is_woocommerce' ) && ( is_shop() || is_product_taxonomy() );
        }

        /**
         * Editor preview without a real archive → render the design demo.
         */
        protected function is_demo() {
                if ( $this->is_archive_context() ) {
                        return false;
                }
                if ( ! class_exists( '\Elementor\Plugin' ) ) {
                        return false;
                }
                $el      = \Elementor\Plugin::$instance;
                $editing = ( isset( $el->editor ) && $el->editor->is_edit_mode() )
                        || ( isset( $el->preview ) && $el->preview->is_preview_mode() )
                        || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() && isset( $_POST['action'] ) && 'elementor_ajax' === $_POST['action'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
                return $editing;
        }

        /**
         * Current archive term (null on the shop root).
         */
        protected function current_term() {
                if ( ! $this->is_archive_context() ) {
                        return null;
                }
                $obj = get_queried_object();
                return ( $obj instanceof WP_Term ) ? $obj : null;
        }

        /**
         * URL of the current archive (shop root when no term).
         */
        protected function archive_url() {
                $term = $this->current_term();
                if ( $term ) {
                        return get_term_link( $term );
                }
                if ( function_exists( 'wc_get_page_permalink' ) ) {
                        return wc_get_page_permalink( 'shop' );
                }
                return home_url( '/' );
        }

        /**
         * Root classes for archive widgets.
         */
        protected function root_class( $slug, $extra = '' ) {
                $s    = $this->effective_settings();
                $vis  = isset( $s['vis'] ) ? sanitize_html_class( $s['vis'] ) : 'auto';
                $cls  = 'hsw-a hsw-' . $slug . ' hsw-vis-' . $vis . $this->hsw_font_class();
                if ( $extra ) {
                        $cls .= ' ' . $extra;
                }
                return $cls;
        }

        /* =========================================================================
         *  URL / FILTER PARAM HELPERS
         * ====================================================================== */

        /**
         * Current GET params (sanitized) used by the filter system.
         */
        protected function current_params() {
                $params = array();
                $ignore = array( 'preview', 'preview_id', 'preview_nonce', '_thumbnail_id', 'elementor-preview' );
                // phpcs:disable WordPress.Security.NonceVerification.Recommended -- public archive filters.
                if ( ! empty( $_GET ) && is_array( $_GET ) ) {
                        foreach ( $_GET as $k => $v ) {
                                if ( ! is_string( $k ) ) {
                                        continue;
                                }
                                /* Keep Unicode attribute slugs such as filter_pa_جنس intact. */
                                $k = preg_replace( '/[^\p{L}\p{N}_\-]/u', '', wp_unslash( $k ) );
                                if ( '' === (string) $k || 0 === strpos( $k, 'hsw_' ) || in_array( $k, $ignore, true ) ) {
                                        continue;
                                }
                                if ( is_array( $v ) ) {
                                        /* Duplicate checkbox names arrive as arrays; WooCommerce expects CSV. */
                                        $flat = array();
                                        foreach ( $v as $item ) {
                                                if ( is_array( $item ) ) {
                                                        foreach ( $item as $sub ) {
                                                                $flat[] = $this->clean_param_value( $sub, $k );
                                                        }
                                                        continue;
                                                }
                                                $flat[] = $this->clean_param_value( $item, $k );
                                        }
                                        $flat = array_values( array_unique( array_filter( $flat, 'strlen' ) ) );
                                        if ( ! empty( $flat ) ) {
                                                $params[ $k ] = implode( ',', $flat );
                                        }
                                        continue;
                                }
                                $value = $this->clean_param_value( wp_unslash( (string) $v ), $k );
                                if ( '' !== $value ) {
                                        $params[ $k ] = $value;
                                }
                        }
                }
                // phpcs:enable
                return $params;
        }

        /**
         * Sanitize one GET value. Attribute slugs can legitimately contain
         * percent-encoded UTF-8 (e.g. %d9%be…) which sanitize_text_field strips,
         * so filter_* values use the slug-safe sanitizer instead.
         *
         * @param mixed  $value Raw value.
         * @param string $key   Param name.
         */
        protected function clean_param_value( $value, $key = '' ) {
                $value = (string) $value;
                if ( 0 === strpos( (string) $key, 'filter_' ) ) {
                        /*
                         * `sanitize_title()` percent-encodes non-ASCII slugs
                         * ("رم-4" → "%d8%b1%d9%85-4"). Keeping that form in memory
                         * meant the browser encoded the % signs again on submit
                         * ("%25d8…"), WooCommerce compared it against the term
                         * slug, found nothing, and the filter silently did
                         * nothing — which is why "حذف همه" looked broken: the
                         * page never changed in the first place.
                         *
                         * Everything downstream (row slugs, checkboxes, chips,
                         * filter_url) now uses the decoded UTF-8 slug and lets
                         * the URL layer encode exactly once.
                         */
                        $parts = array();
                        foreach ( explode( ',', $value ) as $part ) {
                                $part = rawurldecode( sanitize_title( $part ) );
                                if ( '' !== $part ) {
                                        $parts[] = $part;
                                }
                        }
                        return implode( ',', array_unique( $parts ) );
                }
                return sanitize_text_field( $value );
        }

        /**
         * Build an archive URL with modified params (null removes a key).
         *
         * @param array  $changes Key => value|null.
         * @param string $base    Optional base URL (defaults to the current archive).
         * @return string
         */
        protected function filter_url( $changes = array(), $base = '' ) {
                $params = $this->current_params();
                unset( $params['paged'] );
                foreach ( $changes as $k => $v ) {
                        if ( null === $v || '' === $v ) {
                                unset( $params[ $k ] );
                        } else {
                                $params[ $k ] = $v;
                        }
                }
                $url = '' !== (string) $base ? (string) $base : $this->archive_url();
                if ( ! empty( $params ) ) {
                        /*
                         * Let WordPress do the encoding. `add_query_arg()` runs
                         * every value through `urlencode_deep()` exactly once,
                         * so pre-encoding here produced `%25d8…` for a slug that
                         * was already `%d8…` — WooCommerce then compared a
                         * double-encoded string with the term slug, matched
                         * nothing, and the link looked dead (this is what made
                         * «حذف همه» and the chip «×» appear to do nothing).
                         */
                        $url = add_query_arg( $params, $url );
                }
                return $url;
        }

        /**
         * Count of active filter params (badges + mobile apply count).
         */
        protected function active_filter_count() {
                $count  = 0;
                $ignore = array( 'paged', 'orderby', 'view' );
                foreach ( array_keys( $this->current_params() ) as $k ) {
                        if ( in_array( $k, $ignore, true ) || 0 === strpos( $k, 'query_type_' ) ) {
                                continue;
                        }
                        $count++;
                }
                return $count;
        }

        /**
         * Active filter tag chips (desktop tags row + mobile badge row).
         * Each: [ 'label', 'remove_url' ].
         */
        protected function active_filter_tags() {
                $tags  = array();
                $term  = $this->current_term();
                $taxes = array();

                /* Attribute taxonomies we render filters for. */
                $attr_color   = $this->opt( 'attr_color', 'pa_color' );
                $attr_memory  = $this->opt( 'attr_memory', 'pa_memory' );
                $attr_brand   = $this->opt( 'attr_brand', 'pa_brand' );
                $taxes        = array_filter( array( $attr_brand, $attr_memory, $attr_color ) );

                foreach ( $this->current_params() as $k => $v ) {
                        if ( '' === $v ) {
                                continue;
                        }
                        $base = 'filter_';
                        if ( 0 === strpos( $k, $base ) ) {
                                $tax = substr( $k, strlen( $base ) );
                                if ( ! taxonomy_exists( $tax ) ) {
                                        continue;
                                }
                                $label = $this->taxonomy_label( $tax );
                                foreach ( array_filter( explode( ',', $v ) ) as $slug ) {
                                        $t = get_term_by( 'slug', $slug, $tax );
                                        if ( ! $t ) {
                                                continue;
                                        }
                                        $rest   = array_filter( explode( ',', $v ) );
                                        $rest   = array_diff( $rest, array( $slug ) );
                                        $params = $this->current_params();
                                        unset( $params['paged'] );
                                        if ( empty( $rest ) ) {
                                                unset( $params[ $k ] );
                                        } else {
                                                $params[ $k ] = implode( ',', $rest );
                                        }
                                        $tags[] = array(
                                                'label' => $label . ': ' . $t->name,
                                                'url'   => add_query_arg( $params, $this->archive_url() ),
                                        );
                                }
                                continue;
                        }
                        switch ( $k ) {
                                case 'min_price':
                                        $params = $this->current_params();
                                        unset( $params['paged'], $params['min_price'] );
                                        $tags[] = array(
                                                'label' => __( 'از قیمت:', 'hamishop-elementor-widgets' ) . ' ' . HSW_Helpers::price_num( $v ),
                                                'url'   => add_query_arg( $params, $this->archive_url() ),
                                        );
                                        break;
                                case 'max_price':
                                        $params = $this->current_params();
                                        unset( $params['paged'], $params['max_price'] );
                                        $tags[] = array(
                                                'label' => __( 'تا قیمت:', 'hamishop-elementor-widgets' ) . ' ' . HSW_Helpers::price_num( $v ),
                                                'url'   => add_query_arg( $params, $this->archive_url() ),
                                        );
                                        break;
                                case 'instock':
                                        $params = $this->current_params();
                                        unset( $params['paged'], $params['instock'] );
                                        $tags[] = array(
                                                'label' => __( 'فقط کالاهای موجود', 'hamishop-elementor-widgets' ),
                                                'url'   => add_query_arg( $params, $this->archive_url() ),
                                        );
                                        break;
                                case 'express':
                                        $params = $this->current_params();
                                        unset( $params['paged'], $params['express'] );
                                        $tags[] = array(
                                                'label' => __( 'ارسال فوری', 'hamishop-elementor-widgets' ),
                                                'url'   => add_query_arg( $params, $this->archive_url() ),
                                        );
                                        break;
                                case 'rating_filter':
                                        $params = $this->current_params();
                                        unset( $params['paged'], $params['rating_filter'] );
                                        $tags[] = array(
                                                /* translators: %s: rating value */
                                                'label' => sprintf( __( 'امتیاز %s و بالاتر', 'hamishop-elementor-widgets' ), HSW_Helpers::fa_num( (int) $v ) ),
                                                'url'   => add_query_arg( $params, $this->archive_url() ),
                                        );
                                        break;
                                case 'onsale':
                                        $params = $this->current_params();
                                        unset( $params['paged'], $params['onsale'] );
                                        $tags[] = array(
                                                'label' => __( 'فقط تخفیف‌دارها', 'hamishop-elementor-widgets' ),
                                                'url'   => add_query_arg( $params, $this->archive_url() ),
                                        );
                                        break;
                        }
                }

                unset( $term, $taxes );
                return $tags;
        }

        /**
         * Human label for a taxonomy.
         */
        protected function taxonomy_label( $tax ) {
                $tax_obj = get_taxonomy( $tax );
                $label   = ( $tax_obj && ! empty( $tax_obj->labels->singular_name ) ) ? (string) $tax_obj->labels->singular_name : (string) $tax;
                /* WooCommerce's Persian labels read «محصول رنگ». */
                return trim( (string) preg_replace( '/^محصول\s+/u', '', $label ) );
        }

        /**
         * Value of a widget option with default fallback.
         */
        protected function opt( $key, $default = '' ) {
                $s = $this->effective_settings();
                return ( isset( $s[ $key ] ) && '' !== trim( (string) $s[ $key ] ) ) ? trim( (string) $s[ $key ] ) : $default;
        }

        /**
         * Cheapest / priciest published product price (transient-cached).
         */
        protected function price_bounds() {
                $cache = get_transient( 'hsw_price_bounds' );
                if ( is_array( $cache ) && isset( $cache['min'], $cache['max'] ) ) {
                        return $cache;
                }
                global $wpdb;
                $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                        "SELECT MIN(pm.meta_value+0) AS minp, MAX(pm.meta_value+0) AS maxp
                         FROM {$wpdb->postmeta} pm
                         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                         WHERE pm.meta_key = '_price' AND pm.meta_value <> ''
                               AND p.post_type = 'product' AND p.post_status = 'publish'"
                );
                $bounds = array(
                        'min' => $row && $row->minp ? (float) $row->minp : 0,
                        'max' => $row && $row->maxp ? (float) $row->maxp : 100000000,
                );
                if ( $bounds['max'] <= $bounds['min'] ) {
                        $bounds['max'] = $bounds['min'] + 10000000;
                }
                set_transient( 'hsw_price_bounds', $bounds, 12 * HOUR_IN_SECONDS );
                return $bounds;
        }

        /**
         * Price slider bounds + the current request selection.
         *
         * Bounds are snapped to a human step so the range input never produces
         * fractional values (e.g. 96,760,560.5) and the number inputs can stay
         * empty until the visitor actually narrows the range.
         *
         * @return array{min: float, max: float, from: float, to: float, step: int, has_from: bool, has_to: bool}
         */
        protected function price_state() {
                $b    = $this->price_bounds();
                $step = $this->price_step( $b['min'], $b['max'] );
                $min  = floor( (float) $b['min'] / $step ) * $step;
                $max  = ceil( (float) $b['max'] / $step ) * $step;
                if ( $max <= $min ) {
                        $max = $min + $step;
                }

                $p        = $this->current_params();
                $has_from = isset( $p['min_price'] ) && '' !== $p['min_price'];
                $has_to   = isset( $p['max_price'] ) && '' !== $p['max_price'];
                $from     = $has_from ? max( $min, min( $max, (float) $p['min_price'] ) ) : $min;
                $to       = $has_to ? max( $min, min( $max, (float) $p['max_price'] ) ) : $max;
                if ( $from > $to ) {
                        $swap = $from;
                        $from = $to;
                        $to   = $swap;
                }

                return array(
                        'min'      => (float) $min,
                        'max'      => (float) $max,
                        'from'     => (float) $from,
                        'to'       => (float) $to,
                        'step'     => (int) $step,
                        'has_from' => $has_from,
                        'has_to'   => $has_to,
                );
        }

        /**
         * Human-friendly price step for a store's real price span.
         */
        protected function price_step( $min, $max ) {
                $span = max( 1.0, (float) $max - (float) $min );
                $raw  = $span / 1000;
                $step = (int) pow( 10, floor( log10( max( 1.0, $raw ) ) ) );
                $step = (int) apply_filters( 'hsw_archive_price_step', $step, array( 'min' => $min, 'max' => $max ) );
                return max( 1, $step );
        }

        /**
         * Terms of an attribute taxonomy (for brand checkboxes, memory chips, colors).
         *
         * @param string $taxonomy Taxonomy.
         * @param int    $limit    Max terms (0 = all).
         * @param string $orderby  get_terms orderby.
         * @param string $order    ASC|DESC.
         * @param bool   $hide_empty Hide terms without products.
         * @return WP_Term[]
         */
        protected function filter_terms( $taxonomy, $limit = 0, $orderby = 'name', $order = 'ASC', $hide_empty = true ) {
                if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
                        return array();
                }
                $args = array(
                        'taxonomy'   => $taxonomy,
                        'hide_empty' => (bool) $hide_empty,
                        'orderby'    => $orderby,
                        'order'      => $order,
                );
                if ( $limit > 0 ) {
                        $args['number'] = (int) $limit;
                }
                $terms = get_terms( $args );
                if ( is_wp_error( $terms ) ) {
                        return array();
                }
                return $terms;
        }

        /**
         * WooCommerce's default "uncategorised" product category (hidden from
         * category trees; it is never a real store category).
         *
         * @return int[]
         */
        protected function default_cat_ids() {
                $id = (int) get_option( 'default_product_cat' );
                return $id > 0 ? array( $id ) : array();
        }

        /**
         * Express shipping-class slug: widget option → site option (synced
         * from Elementor saves) → hsw_express_shipping_class filter.
         * One source of truth for every archive widget.
         */
        protected function express_slug() {
                $opt  = $this->opt( 'express_slug', '' );
                $slug = '' !== $opt ? $opt : ( class_exists( 'HSW_Archive_Query' ) ? HSW_Archive_Query::express_slug() : 'express' );
                return (string) apply_filters( 'hsw_express_shipping_class', $slug );
        }

        /**
         * Does this store have the express shipping class (for showing the toggle)?
         */
        protected function has_express() {
                if ( $this->is_demo() ) {
                        return true;
                }
                $slug = $this->express_slug();
                if ( '' === $slug || ! taxonomy_exists( 'product_shipping_class' ) ) {
                        return false;
                }
                $term = get_term_by( 'slug', $slug, 'product_shipping_class' );
                return ( $term && ! is_wp_error( $term ) );
        }

        /**
         * Attribute taxonomy options for SELECT controls (dynamic per store).
         *
         * @return array<string, string> taxonomy => "label (pa_x)"
         */
        public static function attr_options() {
                $out = array();
                if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
                        foreach ( wc_get_attribute_taxonomies() as $a ) {
                                $tax         = wc_attribute_taxonomy_name( $a->attribute_name );
                                $out[ $tax ] = $a->attribute_label . ' (' . $tax . ')';
                        }
                }
                if ( empty( $out ) ) {
                        $out = array(
                                'pa_brand'  => __( 'برند (pa_brand)', 'hamishop-elementor-widgets' ),
                                'pa_color'  => __( 'رنگ (pa_color)', 'hamishop-elementor-widgets' ),
                                'pa_memory' => __( 'حافظه (pa_memory)', 'hamishop-elementor-widgets' ),
                        );
                }
                return $out;
        }

        /**
         * Shipping-class options for express-shipping SELECT controls.
         *
         * @return array<string, string>
         */
        protected function shipping_class_options() {
                $out   = array( '' => __( '— خودکار —', 'hamishop-elementor-widgets' ) );
                $terms = get_terms(
                        array(
                                'taxonomy'   => 'product_shipping_class',
                                'hide_empty' => false,
                        )
                );
                if ( ! is_wp_error( $terms ) ) {
                        foreach ( $terms as $t ) {
                                $out[ $t->slug ] = $t->name . ' (' . $t->slug . ')';
                        }
                }
                return $out;
        }

        /**
         * Attribute taxonomies the store can actually filter on: global
         * attributes that have at least one term with products. Brand-like
         * attributes come first so auto-generated filter boxes feel right.
         *
         * @return string[] taxonomy names.
         */
        protected function filterable_attributes() {
                $out = array();
                if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
                        return $out;
                }
                $rows = array();
                foreach ( wc_get_attribute_taxonomies() as $a ) {
                        $tax   = wc_attribute_taxonomy_name( $a->attribute_name );
                        $terms = $this->filter_terms( $tax );
                        if ( empty( $terms ) ) {
                                continue;
                        }
                        $brandish = ( false !== strpos( $a->attribute_name, 'brand' ) || false !== strpos( $a->attribute_label, 'برند' ) ) ? 0 : 1;
                        $rows[]   = array(
                                'tax'   => $tax,
                                'brand' => $brandish,
                                'sort'  => count( $terms ),
                        );
                }
                usort(
                        $rows,
                        function ( $a, $b ) {
                                if ( $a['brand'] !== $b['brand'] ) {
                                        return $a['brand'] - $b['brand'];
                                }
                                return $b['sort'] - $a['sort'];
                        }
                );
                foreach ( $rows as $row ) {
                        $out[] = $row['tax'];
                }
                return $out;
        }

        /**
         * Default visual style for an attribute box based on its terms/types.
         */
        protected function attribute_kind( $tax ) {
                if ( function_exists( 'wc_get_attribute_taxonomy_names' ) && in_array( $tax, wc_get_attribute_taxonomy_names(), true ) ) {
                        $attribute = wc_get_attribute( wc_attribute_taxonomy_id_by_name( $tax ) );
                        if ( $attribute && isset( $attribute->type ) && 'color' === $attribute->type ) {
                                return 'color';
                        }
                }
                return 'select';
        }

        /**
         * Dynamic description of the current archive/term (+ {name} aware).
         * Custom (WYSIWYG) wins; otherwise the current term description; on
         * the shop root the shop page description (WooCommerce «آرشیو» page).
         *
         * @param string $custom Custom HTML from a widget control.
         * @return string Safe HTML.
         */
        protected function archive_desc_html( $custom = '', $source = 'both' ) {
                $custom = trim( (string) $custom );
                $source = in_array( $source, array( 'both', 'term', 'shop', 'auto', 'custom' ), true ) ? $source : 'both';

                if ( 'custom' === $source && '' !== $custom ) {
                        return wp_kses_post( $custom );
                }
                if ( $this->is_demo() || ! $this->is_archive_context() ) {
                        return esc_html( $this->demo()['desc'] );
                }

                $term = $this->current_term();

                /*
                 * «توضیح دسته» is the default: the archive copy must come from
                 * the term's own description field so each category reads
                 * differently, not from a fixed string in the widget.
                 */
                /*
                 * «both» = the generated intro (default copy) followed by the
                 * category's own description. That is what most shops want: a
                 * consistent opening sentence on every archive plus the real
                 * per-category copy underneath.
                 */
                if ( 'both' === $source ) {
                        $auto = trim( (string) $this->auto_archive_desc() );
                        $desc = $term ? term_description( $term ) : '';
                        $desc = ( $desc && '' !== trim( wp_strip_all_tags( $desc ) ) ) ? $desc : '';

                        if ( '' !== $auto && '' !== $desc ) {
                                return wp_kses_post( '<p>' . $auto . '</p>' . $desc );
                        }
                        if ( '' !== $desc ) {
                                return wp_kses_post( $desc );
                        }
                        return '' !== $auto ? wp_kses_post( $auto ) : $this->auto_archive_desc();
                }

                if ( 'term' === $source && $term ) {
                        $desc = term_description( $term );
                        if ( $desc && '' !== trim( wp_strip_all_tags( $desc ) ) ) {
                                return wp_kses_post( $desc );
                        }
                        return $this->auto_archive_desc();
                }

                if ( 'auto' === $source ) {
                        return $this->auto_archive_desc();
                }

                if ( 'shop' === $source || ! $term ) {
                        $shop_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;
                        if ( $shop_id > 0 ) {
                                $content = get_post_field( 'post_content', $shop_id );
                                if ( is_string( $content ) && '' !== trim( $content ) ) {
                                        return wp_kses_post( wpautop( $content ) );
                                }
                        }
                        return $this->auto_archive_desc();
                }

                if ( '' !== $custom ) {
                        return wp_kses_post( $custom );
                }

                return $this->auto_archive_desc();
        }

        /**
         * Shared archive-description block — one source of truth for the
         * header widget and the composite (so the same copy never renders
         * twice). Modes: «header» (desktop hero box), «mobile» teaser and
         * «box» (bottom-of-products box with a customizable title).
         *
         * @param array  $s    Effective settings (seo_desc, desc_title, desc_clamp, more_text, less_text).
         * @param string $mode header|mobile|box.
         * @return string Safe HTML ('' when there is nothing to show).
         */
        protected function archive_desc_block_html( array $s, $mode = 'header' ) {
                $desc = $this->archive_desc_html( isset( $s['seo_desc'] ) ? (string) $s['seo_desc'] : '', isset( $s['desc_source'] ) ? (string) $s['desc_source'] : 'both' );
                if ( '' === trim( wp_strip_all_tags( $desc ) ) ) {
                        return '';
                }

                $lines = ( isset( $s['desc_clamp'] ) && '' !== (string) $s['desc_clamp'] ) ? (int) $s['desc_clamp'] : 3;
                $lines = max( 1, min( 12, $lines ) );

                $more = isset( $s['more_text'] ) ? trim( (string) $s['more_text'] ) : '';
                $more = '' !== $more ? $more : __( 'مشاهده بیشتر', 'hamishop-elementor-widgets' );
                $less = isset( $s['less_text'] ) ? trim( (string) $s['less_text'] ) : '';
                $less = '' !== $less ? $less : __( 'بستن', 'hamishop-elementor-widgets' );

                $is_box    = ( 'box' === $mode );
                $is_mobile = ( 'mobile' === $mode );
                $wrap      = $is_box ? 'hsw-ahd__descbox' : ( $is_mobile ? 'hsw-ahd__mteaser hsw-m' : 'hsw-ahd__seobox' );
                $body      = $is_mobile ? 'hsw-ahd__mdesc' : 'hsw-ahd__desc';
                $button    = $is_mobile ? 'hsw-ahd__mmore' : 'hsw-ahd__more';

                $title = '';
                if ( $is_box ) {
                        $fmt   = isset( $s['desc_title'] ) ? trim( (string) $s['desc_title'] ) : '';
                        $fmt   = '' !== $fmt ? $fmt : __( 'دربارهٔ {name}', 'hamishop-elementor-widgets' );
                        $title = str_replace( '{name}', $this->archive_name(), $fmt );
                }

                ob_start();
                ?>
                <div class="<?php echo esc_attr( $wrap ); ?>">
                        <?php if ( '' !== $title ) : ?>
                                <h2 class="hsw-ahd__dtitle"><?php echo esc_html( $title ); ?></h2>
                        <?php endif; ?>
                        <div class="<?php echo esc_attr( $body ); ?> is-clamped" data-hsw-seo-target style="--hswa-seo-lines: <?php echo (int) $lines; ?>"><?php echo $desc; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized by archive_desc_html(). ?></div>
                        <button type="button" class="<?php echo esc_attr( $button ); ?>" data-hsw-seo data-hsw-close-txt="<?php echo esc_attr( $less ); ?>" aria-expanded="false">
                                <span><?php echo esc_html( $more ); ?></span>
                                <?php if ( ! $is_mobile ) : ?>
                                        <?php echo HSW_Helpers::icon( 'expand_more' ); ?>
                                <?php endif; ?>
                        </button>
                </div>
                <?php
                return trim( (string) ob_get_clean() );
        }

        /**
         * Auto-generated, store-specific description used when the current
         * term/shop has no description of its own. Built from the archive name,
         * the real product count and the store's own attributes/price range so
         * every archive gets relevant, unique copy — never hard-coded data.
         *
         * @return string Safe HTML ('' when nothing meaningful can be built).
         */
        protected function auto_archive_desc() {
                $name = $this->archive_name();
                if ( '' === trim( (string) $name ) ) {
                        return '';
                }

                $count = 0;
                if ( function_exists( 'wc_get_loop_prop' ) ) {
                        global $wp_query;
                        if ( $wp_query && isset( $wp_query->found_posts ) ) {
                                $count = (int) $wp_query->found_posts;
                        }
                }

                $lines   = array();
                $lines[] = sprintf(
                        /* translators: 1: archive name, 2: number of products */
                        __( 'در آرشیو «%1$s» تلاش کرده‌ایم بهترین گزینه‌ها را با قیمت مناسب و اطلاعات کامل در اختیار شما بگذاریم. در حال حاضر %2$s کالا در این بخش برای مقایسه و انتخاب موجود است.', 'hamishop-elementor-widgets' ),
                        $name,
                        HSW_Helpers::fa_num( max( 1, $count ) )
                );

                /* Real attributes of this archive (dynamic per store). */
                $facts = array();
                foreach ( array_slice( $this->filterable_attributes(), 0, 3 ) as $tax ) {
                        $terms = $this->filter_terms( $tax );
                        if ( empty( $terms ) ) {
                                continue;
                        }
                        $names = array();
                        foreach ( array_slice( $terms, 0, 4 ) as $term ) {
                                $names[] = $term->name;
                        }
                        $facts[] = $this->taxonomy_label( $tax ) . ': ' . implode( '، ', $names );
                }
                if ( ! empty( $facts ) ) {
                        $lines[] = __( 'برای انتخاب دقیق‌تر می‌توانید با فیلترهای همین صفحه، نتایج را بر اساس ویژگی‌های واقعی محصولات محدود کنید:', 'hamishop-elementor-widgets' ) . ' ' . implode( ' — ', $facts ) . '.';
                }

                /* Real price range of this store. */
                $price = $this->price_state();
                if ( ! empty( $price['min'] ) && ! empty( $price['max'] ) && $price['max'] > $price['min'] ) {
                        $lines[] = sprintf(
                                /* translators: 1: min price, 2: max price, 3: currency */
                                __( 'محدوده قیمت کالاهای این آرشیو از %1$s تا %2$s %3$s است؛ با اسلایدر قیمت می‌توانید بودجه خود را دقیق‌تر تعیین کنید.', 'hamishop-elementor-widgets' ),
                                HSW_Helpers::price_num( $price['min'] ),
                                HSW_Helpers::price_num( $price['max'] ),
                                HSW_Helpers::currency_label()
                        );
                }

                $lines[] = __( 'همه سفارش‌ها با ضمانت اصالت کالا، امکان مرجوعی و پشتیبانی ارسال سریع ثبت می‌شوند. اگر در انتخاب تردید دارید، از بخش «راهنمای خرید و سوالات متداول» پایین همین صفحه استفاده کنید.', 'hamishop-elementor-widgets' );

                $html = '';
                foreach ( $lines as $line ) {
                        $html .= '<p>' . esc_html( $line ) . '</p>';
                }
                return $html;
        }

        /**
         * Current archive name (term name / shop title / demo).
         */
        protected function archive_name() {
                if ( $this->is_demo() || ! $this->is_archive_context() ) {
                        return $this->demo()['term'];
                }
                $term = $this->current_term();
                if ( $term ) {
                        return $term->name;
                }
                $shop_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;
                return $shop_id > 0 ? get_the_title( $shop_id ) : __( 'فروشگاه', 'hamishop-elementor-widgets' );
        }

        /**
         * Selected slugs of a filter_{tax} param.
         */
        protected function selected_slugs( $taxonomy ) {
                $params = $this->current_params();
                $key    = 'filter_' . $taxonomy;
                if ( empty( $params[ $key ] ) ) {
                        return array();
                }
                return array_filter( explode( ',', (string) $params[ $key ] ) );
        }

        /* =========================================================================
         *  DEMO DATA (editor preview only)
         * ====================================================================== */

        /**
         * Demo archive mirroring the supplied design mock.
         */
        protected function demo() {
                $ph = HSW_URL . 'assets/img/placeholder.svg';
                $p  = array(
                        array(
                                'name' => 'گوشی موبایل سامسونگ Galaxy S24 Ultra 5G دو سیم‌کارت ظرفیت 256 رم 12 گیگابایت',
                                'brand' => 'سامسونگ', 'rating' => '۴.۸', 'reviews' => 142,
                                'price' => '۶۲,۹۹۰,۰۰۰', 'regular' => '۶۸,۵۰۰,۰۰۰', 'discount' => 8,
                                'badge' => 'sale', 'note' => 'موجود در انبار تهران (آماده ارسال)', 'note_icon' => 'task_alt', 'img' => $ph,
                        ),
                        array(
                                'name' => 'گوشی موبایل اپل مدل iPhone 13 Pro Max تک سیم‌کارت 256GB اکتیو CH',
                                'brand' => 'اپل', 'rating' => '۴.۹', 'reviews' => 320,
                                'price' => '۸۰,۵۰۰,۰۰۰', 'regular' => '۸۴,۰۰۰,۰۰۰', 'discount' => 4,
                                'badge' => 'sale', 'note' => 'ارسال اکسپرس امروز', 'note_icon' => 'bolt', 'img' => $ph,
                        ),
                        array(
                                'name' => 'گوشی موبایل شیائومی مدل 14 Ultra 5G دو سیم‌کارت ظرفیت 512 گیگابایت و رم 16',
                                'brand' => 'شیائومی', 'rating' => '۴.۶', 'reviews' => 78,
                                'price' => '۵۹,۲۰۰,۰۰۰', 'regular' => '', 'discount' => 0,
                                'badge' => 'featured', 'note' => 'گارانتی ۱۸ ماهه معتبر', 'note_icon' => 'task_alt', 'img' => $ph,
                        ),
                        array(
                                'name' => 'گوشی موبایل سامسونگ مدل Galaxy A55 5G دو سیم‌کارت ظرفیت 256GB رم 8GB',
                                'brand' => 'سامسونگ', 'rating' => '۴.۷', 'reviews' => 521,
                                'price' => '۲۰,۳۵۰,۰۰۰', 'regular' => '۲۳,۱۰۰,۰۰۰', 'discount' => 12,
                                'badge' => 'sale', 'note' => 'موجود در انبار تهران', 'note_icon' => 'task_alt', 'img' => $ph,
                        ),
                        array(
                                'name' => 'گوشی موبایل ناتینگ مدل Phone (2) دو سیم کارت ظرفیت 256 رم 12 گیگابایت',
                                'brand' => 'ناتینگ', 'rating' => '۴.۵', 'reviews' => 33,
                                'price' => '۳۳,۵۰۰,۰۰۰', 'regular' => '', 'discount' => 0,
                                'badge' => '', 'note' => 'رجیستر شده با کد فعال‌سازی', 'note_icon' => 'task_alt', 'img' => $ph,
                        ),
                        array(
                                'name' => 'گوشی موبایل شیائومی مدل Redmi Note 13 Pro 4G ظرفیت 256GB رم 8GB',
                                'brand' => 'شیائومی', 'rating' => '۴.۶', 'reviews' => 185,
                                'price' => '۱۳,۴۹۰,۰۰۰', 'regular' => '۱۴,۳۰۰,۰۰۰', 'discount' => 6,
                                'badge' => 'sale', 'note' => 'ارسال اکسپرس امروز', 'note_icon' => 'bolt', 'img' => $ph,
                        ),
                );
                return array(
                        'term'      => 'گوشی‌های هوشمند',
                        'count'     => 248,
                        'crumbs'    => array( 'فروشگاه اینترنتی', 'کالای دیجیتال', 'موبایل و گجت‌ها' ),
                        'desc'      => __( 'در این بخش کامل‌ترین و متنوع‌ترین آرشیو گوشی‌های موبایل هوشمند از برندهای معتبر جهانی مانند اپل، سامسونگ، شیائومی، آنر و ناتینگ‌فون گردآوری شده است. تمامی محصولات همراه با ضمانت اصالت کالا، ۱۸ ماه گارانتی شرکتی و کد رجیستری معتبر همتا عرضه می‌گردند. برای خریدی آگاهانه می‌توانید از ابزارهای هوشمند فیلتر سایدبار بر اساس بازه قیمت، ظرفیت حافظه رم، نوع تراشه، ظرفیت دوربین و پشتیبانی از شبکه 5G استفاده کنید.', 'hamishop-elementor-widgets' ),
                        'brands'    => array(
                                array( 'name' => 'سامسونگ (Samsung)', 'count' => 86, 'sel' => true ),
                                array( 'name' => 'اپل (Apple)', 'count' => 34, 'sel' => true ),
                                array( 'name' => 'شیائومی (Xiaomi)', 'count' => 72, 'sel' => false ),
                                array( 'name' => 'ناتینگ فون (Nothing)', 'count' => 14, 'sel' => false ),
                                array( 'name' => 'آنر (Honor)', 'count' => 29, 'sel' => false ),
                        ),
                        'storages'  => array( '۲۵۶ گیگ', '۱۲۸ گیگ', '۵۱۲ گیگ', '۱ ترابایت', '۶۴ گیگ', '۳۲ گیگ' ),
                        'colors'    => array(
                                array( 'name' => 'مشکی تیتانیوم', 'hex' => '#0b0b0d' ),
                                array( 'name' => 'نقره‌ای', 'hex' => '#e2e8f0' ),
                                array( 'name' => 'طلایی', 'hex' => '#fef3c7' ),
                                array( 'name' => 'آبی اقیانوسی', 'hex' => '#1d4ed8' ),
                                array( 'name' => 'سبز یشمی', 'hex' => '#059669' ),
                                array( 'name' => 'بنفش دیپ', 'hex' => '#9333ea' ),
                        ),
                        'pills'     => array( 'همه برندها', 'سامسونگ', 'اپل (آیفون)', 'شیائومی', 'آنر و ریلمی' ),
                        'chips'     => array( 'همه', 'سامسونگ', 'اپل (iPhone)', 'شیائومی', 'ناتینگ فون', 'آنر' ),
                        'cats'      => array(
                                'root'  => array( 'name' => 'کالای دیجیتال', 'count' => 850 ),
                                'mid'   => array( 'name' => 'گوشی‌های موبایل', 'count' => 248, 'active' => true ),
                                'leafs' => array(
                                        array( 'name' => 'گوشی پرچمدار', 'count' => 42 ),
                                        array( 'name' => 'میان‌رده و اقتصادی', 'count' => 156 ),
                                        array( 'name' => 'گوشی گیمینگ', 'count' => 28 ),
                                        array( 'name' => 'گوشی ساده دکمه‌ای', 'count' => 22 ),
                                ),
                        ),
                        'price_min' => 3000000,
                        'price_max' => 95000000,
                        'products'  => $p,
                );
        }

        /* =========================================================================
         *  SHARED CONTROLS
         * ====================================================================== */

        /**
         * Widget palette (Style tab). Overrides the archive design tokens on
         * the widget root so every inner element — header gradient, pills,
         * cards, buttons, boxes… — can be recolored from Style like the other
         * HamiShop widgets. Leaving a color empty keeps the design default.
         */
        protected function ctrl_palette() {
                $this->start_controls_section(
                        'sec_palette',
                        array( 'label' => __( 'پالت رنگ ویجت', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_color(
                        'pal_primary',
                        __( 'رنگ اصلی (لینک، دکمه، قرص فعال)', 'hamishop-elementor-widgets' ),
                        '{{WRAPPER}}',
                        '--hswa-primary: {{VALUE}}; --hswa-primary-dark: color-mix(in srgb, {{VALUE}}, #000 14%); --hswa-primary-50: color-mix(in srgb, {{VALUE}}, #fff 92%); --hswa-primary-100: color-mix(in srgb, {{VALUE}}, #fff 82%); --hswa-primary-light: color-mix(in srgb, {{VALUE}}, #fff 96%);'
                );
                $this->a_color(
                        'pal_ink',
                        __( 'رنگ عنوان‌ها', 'hamishop-elementor-widgets' ),
                        '{{WRAPPER}}',
                        '--hswa-ink: {{VALUE}}; --hswa-strong: color-mix(in srgb, {{VALUE}}, #000 12%);'
                );
                $this->a_color(
                        'pal_body',
                        __( 'رنگ متن‌ها', 'hamishop-elementor-widgets' ),
                        '{{WRAPPER}}',
                        '--hswa-body: {{VALUE}}; --hswa-soft: color-mix(in srgb, {{VALUE}}, #fff 22%);'
                );
                $this->a_color(
                        'pal_line',
                        __( 'رنگ خطوط و حاشیه‌ها', 'hamishop-elementor-widgets' ),
                        '{{WRAPPER}}',
                        '--hswa-line: {{VALUE}}; --hswa-line-soft: color-mix(in srgb, {{VALUE}}, #fff 55%); --hswa-faint: color-mix(in srgb, {{VALUE}}, #000 12%);'
                );
                $this->a_color( 'pal_card', __( 'رنگ کارت‌ها و باکس‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}}', '--hswa-card: {{VALUE}};' );
                $this->a_color( 'pal_page', __( 'رنگ پس‌زمینه ملایم (نوارها)', 'hamishop-elementor-widgets' ), '{{WRAPPER}}', '--hswa-page: {{VALUE}};' );

                $this->add_control(
                        'pal_status_head',
                        array( 'label' => __( 'رنگ‌های وضعیت', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->a_color( 'pal_ok', __( 'موجود / موفق', 'hamishop-elementor-widgets' ), '{{WRAPPER}}', '--hswa-emerald: {{VALUE}};' );
                $this->a_color( 'pal_warn', __( 'امتیاز / هشدار', 'hamishop-elementor-widgets' ), '{{WRAPPER}}', '--hswa-amber: {{VALUE}};' );
                $this->a_color( 'pal_danger', __( 'تخفیف / ناموجود', 'hamishop-elementor-widgets' ), '{{WRAPPER}}', '--hswa-rose: {{VALUE}};' );

                $this->end_controls_section();
        }

        protected function ctrl_visibility( $default = 'auto' ) {
                $this->add_control(
                        'vis',
                        array(
                                'label'   => __( 'نمایش در', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => $default,
                                'options' => array(
                                        'auto'    => __( 'پیش‌فرض طرح', 'hamishop-elementor-widgets' ),
                                        'desktop' => __( 'فقط دسکتاپ', 'hamishop-elementor-widgets' ),
                                        'mobile'  => __( 'فقط موبایل', 'hamishop-elementor-widgets' ),
                                ),
                        )
                );
        }

}
