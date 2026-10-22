<?php
/**
 * Base for single-product-page widgets.
 * Adds local font dependency, current-product resolution with editor demo data,
 * variations payload for the JS state engine and reusable style-control builders.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Repeater;

abstract class HSW_Product_Base extends HSW_Widget_Base {

        public function get_categories() {
                return array( 'hamishop-single' );
        }

        public function get_style_depends() {
                return array( 'hsw-fonts', 'hsw-product' );
        }

        public function get_script_depends() {
                return array( 'hsw-product' );
        }

        /* =========================================================================
         *  PRODUCT RESOLUTION
         * ====================================================================== */

        /**
         * Current WooCommerce product (single product / theme-builder context).
         */
        protected function current_product() {
                global $product;
                if ( $product instanceof WC_Product ) {
                        return $product;
                }
                $post_id = get_the_ID();
                if ( $post_id && 'product' === get_post_type( $post_id ) ) {
                        return wc_get_product( $post_id );
                }
                return null;
        }

        /**
         * True while rendering inside the Elementor editor / preview iframe.
         */
        protected function is_editor_context() {
                if ( ! class_exists( '\Elementor\Plugin' ) ) {
                        return false;
                }
                $el = \Elementor\Plugin::$instance;
                return ( isset( $el->editor ) && $el->editor->is_edit_mode() )
                        || ( isset( $el->preview ) && $el->preview->is_preview_mode() );
        }

        /** @var WC_Product|null|false Cached editor-preview product (false = not resolved yet). */
        private static $preview_product_cache = false;

        /**
         * Real product used while building a template in the editor.
         *
         * A single-product template has no queried product in the editor, so
         * before falling back to the Samsung design mock we resolve the latest
         * published product — the template preview then shows 100% real data
         * (title, price, gallery, variations, specs) instead of fake content.
         *
         * @return WC_Product|null
         */
        protected function preview_product() {
                if ( false !== self::$preview_product_cache ) {
                        return self::$preview_product_cache;
                }
                self::$preview_product_cache = null;

                if ( ! $this->is_editor_context() || ! function_exists( 'wc_get_products' ) ) {
                        return null;
                }

                $ids = wc_get_products(
                        array(
                                'limit'   => 1,
                                'status'  => 'publish',
                                'orderby' => 'date',
                                'order'   => 'DESC',
                                'return'  => 'ids',
                        )
                );
                if ( ! empty( $ids ) ) {
                        $product = wc_get_product( $ids[0] );
                        if ( $product instanceof WC_Product ) {
                                self::$preview_product_cache = $product;
                        }
                }
                return self::$preview_product_cache;
        }

        /**
         * True only when there is NO product anywhere (not even a preview one)
         * and we are inside the editor — the design mock is a last resort.
         */
        protected function is_demo() {
                return $this->is_editor_context() && ! $this->current_product() && ! $this->preview_product();
        }

        /**
         * Product for rendering + whether demo (Samsung mock) data should be used.
         *
         * Editor: the queried product, else the latest published product
         * (real preview), else the design mock. Front-end: the real queried
         * product only — never the mock.
         *
         * @return array [ WC_Product|null $product, bool $demo ]
         */
        protected function product_or_demo() {
                $product = $this->current_product();
                if ( ! $product ) {
                        $product = $this->preview_product();
                }
                return array( $product, null === $product && $this->is_editor_context() );
        }

        /* =========================================================================
         *  DATA HELPERS
         * ====================================================================== */

        /**
         * Display prices without currency symbol (design renders «تومان» separately).
         */
        protected function display_prices( $product ) {
                if ( ! $product ) {
                        return array( 'sale' => 0, 'regular' => 0, 'discount' => 0 );
                }
                if ( HSW_Helpers::is_call_for_price( $product ) ) {
                        return array( 'sale' => 0, 'regular' => 0, 'discount' => 0, 'cfp' => true );
                }
                if ( $product->is_type( 'variable' ) ) {
                        $sale    = (float) $product->get_variation_price( 'min', true );
                        $regular = (float) $product->get_variation_regular_price( 'min', true );
                } else {
                        $sale    = (float) wc_get_price_to_display( $product );
                        $regular = (float) wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) );
                }
                $discount = ( $regular > 0 && $sale > 0 && $sale < $regular ) ? (int) round( ( ( $regular - $sale ) / $regular ) * 100 ) : 0;
                return array( 'sale' => $sale, 'regular' => $regular, 'discount' => $discount );
        }

        /**
         * Full variations payload for the JS variant engine.
         * Attribute values are resolved to display labels (term names).
         */
        protected function variations_payload( $product ) {
                if ( ! $product || ! $product->is_type( 'variable' ) ) {
                        return array();
                }
                $cfp = HSW_Helpers::is_call_for_price( $product );
                $out = array();
                foreach ( $product->get_available_variations() as $v ) {
                        if ( empty( $v['variation_id'] ) ) {
                                continue;
                        }
                        $var = wc_get_product( $v['variation_id'] );
                        if ( ! $var ) {
                                continue;
                        }
                        $attrs = array();
                        foreach ( $var->get_attributes() as $key => $slug ) {
                                $slug = (string) $slug;
                                if ( '' === $slug ) {
                                        continue; // "any value" — wildcard, let the JS match.
                                }
                                $label = $slug;
                                if ( taxonomy_exists( $key ) ) {
                                        $term = get_term_by( 'slug', $slug, $key );
                                        if ( $term && ! is_wp_error( $term ) ) {
                                                $label = $term->name;
                                        }
                                } else {
                                        /* Custom (non-taxonomy) attribute: map the stored slug back to its display option. */
                                        $product_attrs = $product->get_attributes();
                                        if ( isset( $product_attrs[ $key ] ) && $product_attrs[ $key ] instanceof WC_Product_Attribute ) {
                                                foreach ( $product_attrs[ $key ]->get_options() as $opt ) {
                                                        if ( sanitize_title( $opt ) === $slug ) {
                                                                $label = $opt;
                                                                break;
                                                        }
                                                }
                                        }
                                }
                                $attrs[ $key ] = $label;
                        }
                        $regular = (float) $var->get_regular_price();
                        $sale    = (float) $var->get_price();
                        $img_id  = $var->get_image_id() ? $var->get_image_id() : $product->get_image_id();
                        $out[]   = array(
                                'id'       => $var->get_id(),
                                'price'    => $cfp ? '' : HSW_Helpers::price_num( $sale > 0 ? $sale : $regular ),
                                'regular'  => $cfp ? '' : HSW_Helpers::price_num( $regular ),
                                'discount' => $cfp ? 0 : HSW_Helpers::discount_percent( $var ),
                                'image'    => $img_id ? wp_get_attachment_image_url( $img_id, 'woocommerce_single' ) : '',
                                'in_stock' => $var->is_in_stock(),
                                'attrs'    => $attrs,
                        );
                }
                return $out;
        }

        /**
         * Terms of a product attribute taxonomy with a real swatch color.
         *
         * WooCommerce itself has no color column, but many swatch plugins store
         * one in term meta (`color`, `product_attribute_color`, …). We honour
         * that first, then fall back to the term name (blue/red/آبی/…). The
         * Elementor repeater map is still the final override in the widget.
         *
         * @param WC_Product|null $product  Product.
         * @param string          $taxonomy Attribute taxonomy slug.
         * @return array<int,array{name:string,slug:string,label:string,color:string}>
         */
        protected function attribute_terms( $product, $taxonomy ) {
                if ( ! $product || ! $taxonomy ) {
                        return array();
                }
                $attrs = $product->get_attributes();
                if ( empty( $attrs[ $taxonomy ] ) || ! ( $attrs[ $taxonomy ] instanceof WC_Product_Attribute ) || ! $attrs[ $taxonomy ]->get_visible() ) {
                        return array();
                }
                $terms = array();
                foreach ( $attrs[ $taxonomy ]->get_terms() as $term ) {
                        if ( ! $term instanceof WP_Term ) {
                                continue;
                        }
                        $terms[] = array(
                                'name'  => $term->name,
                                'slug'  => $term->slug,
                                'label' => $this->attribute_value_label( $taxonomy, $term->name ),
                                'color' => $this->term_color( $term, $term->name ),
                        );
                }
                if ( empty( $terms ) && $attrs[ $taxonomy ]->is_taxonomy() === false ) {
                        foreach ( $attrs[ $taxonomy ]->get_options() as $opt ) {
                                $terms[] = array(
                                        'name'  => $opt,
                                        'slug'  => sanitize_title( $opt ),
                                        'label' => $this->attribute_value_label( $taxonomy, $opt ),
                                        'color' => $this->term_color( null, $opt ),
                                );
                        }
                }
                return $terms;
        }

        /**
         * Is this attribute a color attribute? (pa_color, colour, رنگ…)
         *
         * @param string $taxonomy Attribute taxonomy slug or label.
         * @return bool
         */
        protected function is_color_attribute( $taxonomy ) {
                $taxonomy = (string) $taxonomy;
                $slug     = strtolower( $taxonomy );
                if ( false !== strpos( $slug, 'color' ) || false !== strpos( $slug, 'colour' ) ) {
                        return true;
                }
                if ( false !== strpos( $taxonomy, 'رنگ' ) ) {
                        return true;
                }
                $tax = function_exists( 'get_taxonomy' ) ? get_taxonomy( $taxonomy ) : null;
                return ( $tax && isset( $tax->labels->name ) && false !== strpos( (string) $tax->labels->name, 'رنگ' ) );
        }

        /**
         * Human label for an attribute value (Persian names for common color
         * terms entered in English). Filterable for stores that intentionally
         * keep English labels.
         *
         * @param string $taxonomy Attribute taxonomy slug.
         * @param string $value    Raw term name.
         * @return string
         */
        protected function attribute_value_label( $taxonomy, $value ) {
                $label = $this->is_color_attribute( $taxonomy ) ? $this->color_name_label( $value ) : $value;
                return (string) apply_filters( 'hsw_attribute_value_label', $label, $value, $taxonomy );
        }

        /**
         * Localize a comma-separated color value list from `get_attribute()`.
         *
         * @param string $value Raw value, e.g. "blue, red".
         * @return string
         */
        protected function localize_color_value_list( $value ) {
                $parts = preg_split( '/\s*[,،]\s*/u', (string) $value );
                if ( ! is_array( $parts ) || count( $parts ) < 2 ) {
                        return $this->color_name_label( trim( (string) $value ) );
                }
                $labels = array();
                foreach ( $parts as $part ) {
                        $part = trim( $part );
                        if ( '' !== $part ) {
                                $labels[] = $this->color_name_label( $part );
                        }
                }
                return implode( '، ', $labels );
        }

        /**
         * Color stored on a term by common swatch plugins, then name fallback.
         *
         * @param WP_Term|null $term Term object (optional).
         * @param string       $name Term name fallback.
         * @return string CSS color (never empty).
         */
        protected function term_color( $term, $name = '' ) {
                if ( $term instanceof WP_Term ) {
                        foreach ( array( 'color', 'product_attribute_color', 'swatch_color', 'color_hex', 'hex', '_color' ) as $meta_key ) {
                                $raw = get_term_meta( $term->term_id, $meta_key, true );
                                if ( is_array( $raw ) ) {
                                        foreach ( array( 'color', 'hex', 'value' ) as $sub_key ) {
                                                if ( isset( $raw[ $sub_key ] ) ) {
                                                        $color = $this->normalize_css_color( $raw[ $sub_key ] );
                                                        if ( '' !== $color ) {
                                                                return $color;
                                                        }
                                                }
                                        }
                                        continue;
                                }
                                $color = $this->normalize_css_color( $raw );
                                if ( '' !== $color ) {
                                        return $color;
                                }
                        }
                }
                $color = $this->color_name_hex( $name ? $name : ( $term instanceof WP_Term ? $term->name : '' ) );
                return '' !== $color ? $color : '#cccccc';
        }

        /**
         * Validate a CSS color value before placing it in an inline style.
         *
         * @param mixed $value Raw meta value.
         * @return string Safe color or ''.
         */
        protected function normalize_css_color( $value ) {
                if ( ! is_scalar( $value ) ) {
                        return '';
                }
                $value = trim( (string) $value );
                if ( '' === $value ) {
                        return '';
                }
                if ( preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value ) ) {
                        return strtolower( $value );
                }
                if ( preg_match( '/^(?:rgb|rgba|hsl|hsla)\(\s*[0-9.,%\s\/-]+\)$/i', $value ) ) {
                        return strtolower( preg_replace( '/\s+/', ' ', $value ) );
                }
                $named = array( 'black', 'white', 'red', 'blue', 'green', 'yellow', 'orange', 'purple', 'pink', 'gray', 'grey', 'silver', 'gold', 'brown', 'beige', 'navy', 'turquoise', 'olive' );
                return in_array( strtolower( $value ), $named, true ) ? strtolower( $value ) : '';
        }

        /**
         * Friendly Persian labels for the most common English color terms.
         *
         * @param string $name Term name.
         * @return string
         */
        protected function color_name_label( $name ) {
                $key = $this->normalize_color_key( $name );
                $map = array(
                        'titanium black'   => 'مشکی تیتانیومی',
                        'titanium gray'    => 'خاکستری تیتانیومی',
                        'titanium grey'    => 'خاکستری تیتانیومی',
                        'titanium blue'    => 'آبی تیتانیومی',
                        'titanium cream'   => 'کرم تیتانیومی',
                        'titanium purple'  => 'بنفش تیتانیومی',
                        'black'            => 'مشکی',
                        'مشکی'             => 'مشکی',
                        'سیاه'             => 'مشکی',
                        'white'            => 'سفید',
                        'سفید'             => 'سفید',
                        'red'              => 'قرمز',
                        'قرمز'             => 'قرمز',
                        'سرخ'              => 'قرمز',
                        'blue'             => 'آبی',
                        'آبی'              => 'آبی',
                        'navy'             => 'سرمه‌ای',
                        'سرمه ای'          => 'سرمه‌ای',
                        'سرمه‌ای'          => 'سرمه‌ای',
                        'green'            => 'سبز',
                        'سبز'              => 'سبز',
                        'yellow'           => 'زرد',
                        'زرد'              => 'زرد',
                        'orange'           => 'نارنجی',
                        'نارنجی'           => 'نارنجی',
                        'purple'           => 'بنفش',
                        'بنفش'             => 'بنفش',
                        'pink'             => 'صورتی',
                        'صورتی'            => 'صورتی',
                        'gray'             => 'خاکستری',
                        'grey'             => 'خاکستری',
                        'خاکستری'          => 'خاکستری',
                        'طوسی'             => 'خاکستری',
                        'silver'           => 'نقره‌ای',
                        'نقره ای'          => 'نقره‌ای',
                        'نقره‌ای'          => 'نقره‌ای',
                        'gold'             => 'طلایی',
                        'طلایی'            => 'طلایی',
                        'brown'            => 'قهوه‌ای',
                        'قهوه ای'          => 'قهوه‌ای',
                        'قهوه‌ای'          => 'قهوه‌ای',
                        'beige'            => 'بژ',
                        'بژ'               => 'بژ',
                        'cream'            => 'کرم',
                        'کرم'              => 'کرم',
                        'turquoise'        => 'فیروزه‌ای',
                        'فیروزه ای'        => 'فیروزه‌ای',
                        'فیروزه‌ای'        => 'فیروزه‌ای',
                        'olive'            => 'زیتونی',
                        'زیتونی'           => 'زیتونی',
                );
                if ( isset( $map[ $key ] ) ) {
                        return $map[ $key ];
                }
                foreach ( $map as $needle => $label ) {
                        if ( false !== strpos( $key, $needle ) ) {
                                return $label;
                        }
                }
                return (string) $name;
        }

        /**
         * A safe color for common color names (English + Persian).
         *
         * @param string $name Term name.
         * @return string Hex color or ''.
         */
        protected function color_name_hex( $name ) {
                $key = $this->normalize_color_key( $name );
                $map = array(
                        'titanium black'   => '#202022',
                        'titanium gray'    => '#706e6b',
                        'titanium grey'    => '#706e6b',
                        'titanium blue'    => '#3b5f8a',
                        'titanium cream'   => '#e3decf',
                        'titanium purple'  => '#494554',
                        'black'            => '#202022',
                        'مشکی'             => '#202022',
                        'سیاه'             => '#202022',
                        'white'            => '#ffffff',
                        'سفید'             => '#ffffff',
                        'red'              => '#dd3333',
                        'قرمز'             => '#dd3333',
                        'سرخ'              => '#dd3333',
                        'blue'             => '#1e73be',
                        'آبی'              => '#1e73be',
                        'navy'             => '#1f2a44',
                        'سرمه‌ای'          => '#1f2a44',
                        'green'            => '#2e7d32',
                        'سبز'              => '#2e7d32',
                        'yellow'           => '#f2c200',
                        'زرد'              => '#f2c200',
                        'orange'           => '#ef7d1a',
                        'نارنجی'           => '#ef7d1a',
                        'purple'           => '#7b4bb7',
                        'بنفش'             => '#7b4bb7',
                        'pink'             => '#e26a9a',
                        'صورتی'            => '#e26a9a',
                        'gray'             => '#8a8a8a',
                        'grey'             => '#8a8a8a',
                        'خاکستری'          => '#8a8a8a',
                        'طوسی'             => '#8a8a8a',
                        'silver'           => '#c0c0c0',
                        'نقره‌ای'          => '#c0c0c0',
                        'gold'             => '#d4af37',
                        'طلایی'            => '#d4af37',
                        'brown'            => '#795548',
                        'قهوه‌ای'          => '#795548',
                        'beige'            => '#d8c3a5',
                        'بژ'               => '#d8c3a5',
                        'cream'            => '#efe6d2',
                        'کرم'              => '#efe6d2',
                        'turquoise'        => '#1abc9c',
                        'فیروزه‌ای'        => '#1abc9c',
                        'olive'            => '#4b5320',
                        'زیتونی'           => '#4b5320',
                );
                if ( isset( $map[ $key ] ) ) {
                        return $map[ $key ];
                }
                foreach ( $map as $needle => $hex ) {
                        if ( '' !== $needle && false !== strpos( $key, $needle ) ) {
                                return $hex;
                        }
                }
                return '';
        }

        /**
         * Normalize a term name for color matching (lowercase, separators).
         *
         * @param string $name Raw name.
         * @return string
         */
        protected function normalize_color_key( $name ) {
                $key = trim( wp_strip_all_tags( (string) $name ) );
                $key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $key, 'UTF-8' ) : strtolower( $key );
                $key = str_replace( array( '_', '-', 'ـ' ), ' ', $key );
                $key = preg_replace( '/\s+/u', ' ', $key );
                return trim( (string) $key );
        }

        /**
         * Key/value rows from product attributes (specs tab/accordion).
         */
        protected function specs_rows( $product ) {
                if ( ! $product ) {
                        return array();
                }
                $rows = array();
                foreach ( $product->get_attributes() as $attribute ) {
                        if ( ! $attribute->get_visible() ) {
                                continue;
                        }
                        $label = $attribute->get_name();
                        if ( $attribute->is_taxonomy() ) {
                                $tax   = get_taxonomy( $attribute->get_name() );
                                $label = $tax ? $tax->labels->name : $attribute->get_name();
                        }
                        $value = $product->get_attribute( $attribute->get_name() );
                        if ( '' === trim( (string) $value ) ) {
                                continue;
                        }
                        if ( $attribute->is_taxonomy() && $this->is_color_attribute( $attribute->get_name() ) ) {
                                $value = $this->localize_color_value_list( $value );
                        }
                        $rows[] = array( 'k' => $label, 'v' => $value );
                }
                return $rows;
        }

        /**
         * Dynamic feature rows for the «ویژگی‌های شاخص» widget: product
         * attributes first, then short-description bullets as fallback.
         *
         * @param WC_Product|null $product Product.
         * @param int             $limit   Max rows.
         * @return array [ ['k' => label, 'v' => value], ... ]
         */
        protected function feature_rows( $product, $limit = 4 ) {
                $rows = array();
                foreach ( array_slice( $this->specs_rows( $product ), 0, $limit ) as $r ) {
                        $rows[] = array( 'k' => $r['k'], 'v' => $r['v'] );
                }
                if ( $rows ) {
                        return $rows;
                }
                foreach ( $this->short_description_bullets( $product, $limit ) as $bullet ) {
                        $rows[] = array( 'k' => '', 'v' => $bullet );
                }
                return $rows;
        }

        /**
         * Bullet lines from the product short description (list items or lines).
         *
         * @param WC_Product|null $product Product.
         * @param int             $limit   Max bullets.
         * @return string[]
         */
        protected function short_description_bullets( $product, $limit = 4 ) {
                if ( ! $product ) {
                        return array();
                }
                $desc = (string) $product->get_short_description();
                if ( '' === trim( $desc ) ) {
                        return array();
                }
                $out = array();
                if ( preg_match_all( '/<li[^>]*>(.*?)<\/li>/is', $desc, $m ) ) {
                        foreach ( $m[1] as $li ) {
                                $txt = trim( wp_strip_all_tags( $li ) );
                                if ( '' !== $txt ) {
                                        $out[] = $txt;
                                }
                        }
                } else {
                        foreach ( preg_split( '/[\r\n]+/', wp_strip_all_tags( $desc ) ) as $line ) {
                                $txt = trim( $line );
                                if ( '' !== $txt ) {
                                        $out[] = $txt;
                                }
                        }
                }
                return array_slice( $out, 0, $limit );
        }

        /**
         * Variation attributes of a variable product that actually have option
         * values (used to auto-detect color/storage attributes per product).
         *
         * @param WC_Product|null $product Product.
         * @return array<string, string[]> attr name => option slugs
         */
        protected function auto_variation_attributes( $product ) {
                if ( ! $product || ! $product->is_type( 'variable' ) ) {
                        return array();
                }
                $out = array();
                foreach ( $product->get_variation_attributes() as $name => $options ) {
                        $options = array_values( array_filter( array_map( 'strval', (array) $options ), 'strlen' ) );
                        if ( $options ) {
                                $out[ $name ] = $options;
                        }
                }
                return $out;
        }

        /**
         * Best-matching attribute name for a role (color / storage) in a pool.
         *
         * @param array  $pool attr name => options.
         * @param string $role 'color' | 'storage' | ''.
         * @return string Attribute name or '' when the pool is empty.
         */
        protected function pick_attribute( array $pool, $role = '' ) {
                if ( ! $pool ) {
                        return '';
                }
                $preferred = array(
                        'color'   => array( 'color', 'colour', 'رنگ' ),
                        'storage' => array( 'storage', 'memory', 'capacity', 'حافظه', 'رم', 'ظرفیت' ),
                );
                if ( isset( $preferred[ $role ] ) ) {
                        foreach ( array_keys( $pool ) as $name ) {
                                $low = strtolower( $name );
                                foreach ( $preferred[ $role ] as $needle ) {
                                        if ( false !== strpos( $low, $needle ) ) {
                                                return $name;
                                        }
                                }
                        }
                }
                return (string) array_key_first( $pool );
        }

        /**
         * Latest reviews (name, initials, rating, text, verified-buyer flag).
         *
         * Reviews without a rating are included too, so stores that disable
         * star ratings still see their real comments.
         *
         * @param WC_Product|null $product Product.
         * @param int             $limit   Max reviews, 0 = all.
         * @return array
         */
        protected function reviews_list( $product, $limit = 5 ) {
                if ( ! $product ) {
                        return array();
                }
                $comments = get_comments(
                        array(
                                'post_id' => $product->get_id(),
                                'status'  => 'approve',
                                'number'  => $limit > 0 ? (int) $limit : 0,
                                'type'    => 'review',
                                'orderby' => 'comment_date',
                                'order'   => 'DESC',
                        )
                );
                $out = array();
                foreach ( $comments as $c ) {
                        $name = $c->comment_author;
                        $ini  = $this->initials( $name );
                        $out[] = array(
                                'name'     => $name,
                                'initials' => $ini,
                                'rating'   => (int) get_comment_meta( $c->comment_ID, 'rating', true ),
                                'text'     => wp_strip_all_tags( $c->comment_content ),
                                'verified' => wc_customer_bought_product( '', $c->comment_author_email, $product->get_id() ),
                        );
                }
                return $out;
        }

        /**
         * Two-letter avatar initials for a review author.
         *
         * @param string $name Display name.
         * @return string
         */
        protected function initials( $name ) {
                $parts = preg_split( '/\s+/u', trim( (string) $name ) );
                $ini   = '';
                foreach ( array_slice( is_array( $parts ) ? $parts : array(), 0, 2 ) as $part ) {
                        if ( '' === $part ) {
                                continue;
                        }
                        $ini .= function_exists( 'mb_substr' ) ? mb_substr( $part, 0, 1, 'UTF-8' ) : substr( $part, 0, 1 );
                }
                return $ini;
        }

        /**
         * Reviews are open for this product?
         *
         * @param WC_Product|null $product Product.
         * @return bool
         */
        protected function reviews_enabled( $product ) {
                if ( ! $product ) {
                        return false;
                }
                if ( 'yes' !== get_option( 'woocommerce_enable_reviews', 'yes' ) ) {
                        return false;
                }
                return comments_open( $product->get_id() );
        }

        /**
         * May the current visitor submit a review? (verified-buyer option)
         *
         * @param WC_Product|null $product Product.
         * @return bool
         */
        protected function review_form_allowed( $product ) {
                if ( ! $this->reviews_enabled( $product ) ) {
                        return false;
                }
                if ( 'yes' !== get_option( 'woocommerce_review_rating_verification_required', 'no' ) ) {
                        return true;
                }
                return wc_customer_bought_product( '', get_current_user_id(), $product->get_id() );
        }

        /**
         * Review cards markup shared by desktop tabs and mobile accordions.
         *
         * @param WC_Product|null $product Product.
         * @param int             $limit   Max reviews, 0 = all.
         * @return string
         */
        protected function review_cards_html( $product, $limit = 0 ) {
                $reviews = $this->reviews_list( $product, $limit );
                if ( empty( $reviews ) ) {
                        return '<p class="hsw-ptabs__rvtext">' . esc_html__( 'هنوز دیدگاهی ثبت نشده است.', 'hamishop-elementor-widgets' ) . '</p>';
                }

                ob_start();
                ?>
                <div class="hsw-ptabs__rvlist">
                        <?php foreach ( $reviews as $rv ) : ?>
                                <?php
                                $name     = isset( $rv['name'] ) ? $rv['name'] : '';
                                $initials = isset( $rv['initials'] ) ? $rv['initials'] : '';
                                $text     = isset( $rv['text'] ) ? $rv['text'] : '';
                                $verified = ! empty( $rv['verified'] );
                                $rating   = max( 0, min( 5, (int) ( isset( $rv['rating'] ) ? $rv['rating'] : 0 ) ) );
                                ?>
                                <div class="hsw-ptabs__rvitem">
                                        <div class="hsw-ptabs__rvtop">
                                                <div class="hsw-ptabs__rvwho">
                                                        <span class="hsw-ptabs__rvav"><?php echo esc_html( $initials ); ?></span>
                                                        <span class="hsw-ptabs__rvname"><?php echo esc_html( $name ); ?></span>
                                                        <?php if ( $verified ) : ?>
                                                                <span class="hsw-ptabs__rvtag"><?php esc_html_e( 'خریدار این کالا', 'hamishop-elementor-widgets' ); ?></span>
                                                        <?php endif; ?>
                                                </div>
                                                <?php if ( $rating > 0 ) : ?>
                                                        <div class="hsw-ptabs__rvstars" aria-label="<?php echo esc_attr( sprintf( __( 'امتیاز %s از ۵', 'hamishop-elementor-widgets' ), HSW_Helpers::fa_num( $rating ) ) ); ?>">
                                                                <?php for ( $star = 1; $star <= 5; $star++ ) : ?>
                                                                        <?php echo HSW_Helpers::icon( 'star', $star <= $rating ? 'hsw-ic--fill' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes internally. ?>
                                                                <?php endfor; ?>
                                                                <span class="hsw-ptabs__rvval"><?php echo esc_html( HSW_Helpers::fa_num( $rating ) . '.۰' ); ?></span>
                                                        </div>
                                                <?php endif; ?>
                                        </div>
                                        <p class="hsw-ptabs__rvtext"><?php echo esc_html( $text ); ?></p>
                                </div>
                        <?php endforeach; ?>
                </div>
                <?php
                return (string) ob_get_clean();
        }

        /**
         * Native WordPress/WooCommerce comment form with a rating field.
         *
         * Uses `comment_form()` so the standard nonce, comment_post_ID and
         * WooCommerce rating validation keep working. Unique IDs let the same
         * product show the form in both the desktop tab and mobile accordion.
         *
         * @param WC_Product|null $product Product.
         * @param string          $suffix  Context suffix (tabs/mobile).
         * @return string
         */
        protected function review_form_html( $product, $suffix = 'tabs' ) {
                if ( ! $product ) {
                        return '';
                }
                if ( ! $this->reviews_enabled( $product ) ) {
                        return '<p class="hsw-review-closed">' . esc_html__( 'ثبت دیدگاه برای این محصول فعال نیست.', 'hamishop-elementor-widgets' ) . '</p>';
                }
                if ( ! $this->review_form_allowed( $product ) ) {
                        return '<p class="hsw-review-closed">' . esc_html__( 'فقط خریداران این محصول می‌توانند دیدگاه ثبت کنند.', 'hamishop-elementor-widgets' ) . '</p>';
                }

                static $seq = 0;
                $seq++;
                $suffix = preg_replace( '/[^a-z0-9_-]+/i', '-', (string) $suffix );
                $uid    = 'hsw-review-' . (int) $seq . ( $suffix ? '-' . strtolower( $suffix ) : '' );

                $comment_field = '';
                if ( function_exists( 'wc_review_ratings_enabled' ) && wc_review_ratings_enabled() ) {
                        $required = function_exists( 'wc_review_ratings_required' ) && wc_review_ratings_required();
                        /*
                         * Stars instead of a <select>. The inputs keep the exact
                         * `rating` name WooCommerce validates on submit, so only
                         * the presentation changes. DOM order is 1→5 and the row
                         * is row-reversed, which makes `~ label` light up the
                         * higher stars when one is hovered or checked.
                         */
                        $labels = array(
                                1 => __( 'خیلی ضعیف', 'hamishop-elementor-widgets' ),
                                2 => __( 'ضعیف', 'hamishop-elementor-widgets' ),
                                3 => __( 'متوسط', 'hamishop-elementor-widgets' ),
                                4 => __( 'خوب', 'hamishop-elementor-widgets' ),
                                5 => __( 'عالی', 'hamishop-elementor-widgets' ),
                        );

                        $comment_field .= '<fieldset class="comment-form-rating hsw-stars">';
                        $comment_field .= '<legend class="hsw-stars__legend">' . esc_html__( 'امتیاز شما', 'hamishop-elementor-widgets' ) . ( $required ? '&nbsp;<span class="required">*</span>' : '' ) . '</legend>';
                        $comment_field .= '<div class="hsw-stars__row">';
                        /* Descending DOM order + row-reverse: the highest star sits
                           on the left of the row, so `~ label` lights the lower
                           ones to its right — i.e. the fill grows from the right,
                           which is how Persian readers expect a rating to fill. */
                        foreach ( array_reverse( $labels, true ) as $value => $label ) {
                                $rid = $uid . '-rating-' . $value;
                                $comment_field .= '<input type="radio" name="rating" id="' . esc_attr( $rid ) . '" value="' . (int) $value . '"' . ( $required ? ' required' : '' ) . '>';
                                $comment_field .= '<label for="' . esc_attr( $rid ) . '" title="' . esc_attr( $label ) . '">'
                                        . HSW_Helpers::icon( 'star' )
                                        . '<span class="hsw-stars__label">' . esc_html( $label ) . '</span>'
                                        . '</label>';
                        }
                        $comment_field .= '</div></fieldset>';
                }
                $comment_field .= '<p class="comment-form-comment"><label for="' . esc_attr( $uid . '-comment' ) . '">' . esc_html__( 'دیدگاه شما', 'hamishop-elementor-widgets' ) . '&nbsp;<span class="required">*</span></label>';
                $comment_field .= '<textarea id="' . esc_attr( $uid . '-comment' ) . '" name="comment" cols="45" rows="6" required></textarea></p>';

                $fields = array();
                if ( ! is_user_logged_in() ) {
                        $commenter = wp_get_current_commenter();
                        $fields    = array(
                                'author' => '<p class="comment-form-author"><label for="' . esc_attr( $uid . '-author' ) . '">' . esc_html__( 'نام', 'hamishop-elementor-widgets' ) . '&nbsp;<span class="required">*</span></label><input id="' . esc_attr( $uid . '-author' ) . '" name="author" type="text" value="' . esc_attr( $commenter['comment_author'] ) . '" autocomplete="name" required /></p>',
                                'email'  => '<p class="comment-form-email"><label for="' . esc_attr( $uid . '-email' ) . '">' . esc_html__( 'ایمیل', 'hamishop-elementor-widgets' ) . '&nbsp;<span class="required">*</span></label><input id="' . esc_attr( $uid . '-email' ) . '" name="email" type="email" value="' . esc_attr( $commenter['comment_author_email'] ) . '" autocomplete="email" required /></p>',
                        );
                }

                $account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
                $args        = array(
                        'id_form'              => $uid,
                        'class_form'           => 'hsw-review-form comment-form',
                        'id_submit'            => $uid . '-submit',
                        'class_submit'         => 'hsw-review-submit submit',
                        'title_reply'          => __( 'ثبت دیدگاه و امتیاز', 'hamishop-elementor-widgets' ),
                        'title_reply_before'   => '<h3 id="' . esc_attr( $uid . '-reply-title' ) . '" class="comment-reply-title hsw-review-form__title">',
                        'title_reply_after'    => '</h3>',
                        'cancel_reply_before'  => '',
                        'cancel_reply_after'   => '',
                        'comment_notes_before' => '',
                        'comment_notes_after'  => '',
                        'comment_field'        => $comment_field,
                        'fields'               => $fields,
                        'label_submit'         => __( 'ارسال دیدگاه', 'hamishop-elementor-widgets' ),
                        'logged_in_as'         => '',
                        'must_log_in'          => '<p class="must-log-in">' . sprintf( __( 'برای ثبت دیدگاه باید %1$sوارد حساب کاربری شوید%2$s.', 'hamishop-elementor-widgets' ), '<a href="' . esc_url( $account_url ) . '">', '</a>' ) . '</p>',
                        'format'               => 'html5',
                );

                ob_start();
                comment_form( apply_filters( 'woocommerce_product_review_comment_form_args', $args ), $product->get_id() );
                $html = (string) ob_get_clean();

                /* Core wrapper/input ids are hard-coded; make them unique per form. */
                $html = preg_replace( '/<div id="respond" class="comment-respond">/', '<div id="' . esc_attr( $uid . '-respond' ) . '" class="comment-respond hsw-review-respond">', $html, 1 );
                $html = str_replace( array( "id='comment_post_ID'", 'id="comment_post_ID"' ), 'id="' . esc_attr( $uid . '-comment-post-id' ) . '"', $html );
                $html = str_replace( array( "id='comment_parent'", 'id="comment_parent"' ), 'id="' . esc_attr( $uid . '-comment-parent' ) . '"', $html );
                $html = str_replace( array( "id='cancel-comment-reply-link'", 'id="cancel-comment-reply-link"' ), 'id="' . esc_attr( $uid . '-cancel-comment-reply' ) . '"', $html );
                $html = str_replace( '_wp_unfiltered_html_comment_disabled', esc_attr( $uid . '-unfiltered-html-comment' ), $html );
                $html = str_replace( array( "id='wp-comment-cookies-consent'", 'id="wp-comment-cookies-consent"' ), 'id="' . esc_attr( $uid . '-cookies-consent' ) . '"', $html );
                $html = str_replace( array( "for='wp-comment-cookies-consent'", 'for="wp-comment-cookies-consent"' ), 'for="' . esc_attr( $uid . '-cookies-consent' ) . '"', $html );
                return $html;
        }

        /**
         * Product gallery image ids (main first).
         */
        protected function gallery_ids( $product ) {
                if ( ! $product ) {
                        return array();
                }
                $ids = array_filter( array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) );
                return array_values( $ids );
        }

        /**
         * Demo data replicating the supplied Samsung mock (editor preview only).
         */
        protected function demo() {
                $ph = HSW_URL . 'assets/img/placeholder.svg';
                return array(
                        'name'     => 'گوشی موبایل سامسونگ مدل Galaxy S24 Ultra 5G ظرفیت 256 گیگابایت',
                        'name_en'  => 'Samsung Galaxy S24 Ultra 5G Dual SIM 256GB',
                        'sku'      => 'DKP-98314',
                        'rating'   => '۴.۸',
                        'reviews'  => 142,
                        'recommend'=> '۸۹٪',
                        'in_stock' => true,
                        'sale'     => '۶۲,۹۹۰,۰۰۰',
                        'regular'  => '۶۷,۰۰۰,۰۰۰',
                        'save'     => '۴,۰۱۰,۰۰۰',
                        'discount' => 6,
                        'colors'   => array(
                                array( 'name' => 'تیتانیوم مشکی', 'hex' => '#202022' ),
                                array( 'name' => 'تیتانیوم خاکستری', 'hex' => '#706e6b' ),
                                array( 'name' => 'تیتانیوم کرم', 'hex' => '#e3decf' ),
                                array( 'name' => 'تیتانیوم بنفش', 'hex' => '#494554' ),
                        ),
                        'storages' => array( '۲۵۶ گیگابایت / ۱۲ گیگابایت رم', '۵۱۲ گیگابایت / ۱۲ گیگابایت رم', '۱ ترابایت / ۱۲ گیگابایت رم' ),
                        'features_d' => array(
                                'فناوری هوش مصنوعی پیشرفته Galaxy AI',
                                'فریم مقاوم تیتانیومی با گواهی IP68',
                                'دوربین اصلی ۲۰۰ مگاپیکسلی با زوم اوپتیکال',
                                'باتری ۵۰۰۰ میلی‌آمپر با شارژ سریع ۴۵ وات',
                        ),
                        'features_m' => array(
                                array( 'icon' => 'photo_camera', 'label' => 'دوربین اصلی', 'value' => '۲۰۰ مگاپیکسل' ),
                                array( 'icon' => 'memory', 'label' => 'تراشه پردازنده', 'value' => 'Snapdragon 8 Gen 3' ),
                                array( 'icon' => 'battery_charging_full', 'label' => 'ظرفیت باتری', 'value' => '۵۰۰۰ میلی‌آمپر' ),
                                array( 'icon' => 'edit', 'label' => 'لوازم جانبی', 'value' => 'قلم هوشمند S-Pen' ),
                        ),
                        'specs'    => array(
                                array( 'ابعاد و وزن', '۱۶۲.۳ × ۷۹ × ۸.۶ میلی‌متر / ۲۳۲ گرم با فریم تیتانیومی' ),
                                array( 'صفحه نمایش', '۶.۸ اینچ Dynamic LTPO AMOLED 2X با نرخ بروزرسانی ۱۲۰ هرتز' ),
                                array( 'پردازنده (CPU)', 'Qualcomm Snapdragon 8 Gen 3 for Galaxy (۴ نانومتری)' ),
                                array( 'سیستم دوربین', 'ماژول چهارگانه ۲۰۰ + ۵۰ + ۱۰ + ۱۲ مگاپیکسل' ),
                                array( 'باتری و درگاه شارژ', '۵۰۰۰ میلی‌آمپر ساعت، درگاه Type-C 3.2' ),
                        ),
                        'reviews_l' => array(
                                array( 'name' => 'محمدرضا رضایی', 'initials' => 'م‌ر', 'rating' => 5, 'text' => 'کیفیت ساخت تیتانیوم واقعاً حس متفاوتی داره. عملکرد باتری و کیفیت نمایشگر بدون بازتاب نور عالیه.', 'verified' => true ),
                                array( 'name' => 'نگار صادقی', 'initials' => 'ن‌ص', 'rating' => 4, 'text' => 'باتری به راحتی یک روز و نیم سنگین رو دوام میاره. سرعت پردازش هوش مصنوعی هم عالیه.', 'verified' => true ),
                        ),
                        'gallery'  => array( $ph, $ph, $ph, $ph ),
                );
        }

        /* =========================================================================
         *  SHARED CONTROLS
         * ====================================================================== */

        /**
         * Visibility override: auto (design default) / desktop / mobile.
         * Renders class hsw-vis-{value} on the root element.
         */
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

        /**
         * Root classes for the widget (hsw-p + per-widget + visibility + font mode).
         */
        protected function root_class( $slug, $extra = '' ) {
                $s    = $this->effective_settings();
                $vis  = isset( $s['vis'] ) ? sanitize_html_class( $s['vis'] ) : 'auto';
                $cls  = 'hsw-p hsw-' . $slug . ' hsw-vis-' . $vis . $this->hsw_font_class();
                if ( $extra ) {
                        $cls .= ' ' . $extra;
                }
                return $cls;
        }

        /**
         * «درصد رضایت خریداران» — share of approved reviews rated 4★ or 5★.
         * Returns 0 when the product has no reviews (widget hides the chip).
         * Filterable: add_filter( 'hsw_recommend_percent', ... , 10, 2 ).
         */
        protected function recommend_percent( $product ) {
                if ( ! $product ) {
                        return 0;
                }
                $total = (int) $product->get_review_count();
                if ( $total <= 0 ) {
                        return 0;
                }
                $positive = (int) get_comments(
                        array(
                                'post_id'    => $product->get_id(),
                                'status'     => 'approve',
                                'type'       => 'review',
                                'count'      => true,
                                'meta_query' => array(
                                        array(
                                                'key'     => 'rating',
                                                'value'   => 4,
                                                'compare' => '>=',
                                                'type'    => 'NUMERIC',
                                        ),
                                ),
                        )
                );
                $percent = ( $positive > 0 ) ? (int) round( ( $positive / $total ) * 100 ) : 0;
                return (int) apply_filters( 'hsw_recommend_percent', $percent, $product );
        }

        /**
         * Box style controls: background, border color/width, radius, padding, shadow.
         */
        protected function ctrl_box( $id, $selector, $opts = array() ) {
                $opts = wp_parse_args( $opts, array( 'border' => true, 'shadow' => true, 'padding' => true, 'radius' => true ) );
                $this->add_control( $id . '_bg', array(
                        'label'     => __( 'پس‌زمینه', 'hamishop-elementor-widgets' ),
                        'type'      => Controls_Manager::COLOR,
                        'selectors' => array( $selector => 'background-color: {{VALUE}};' ),
                ) );
                if ( $opts['border'] ) {
                        $this->add_control( $id . '_bc', array(
                                'label'     => __( 'رنگ حاشیه', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( $selector => 'border-color: {{VALUE}};' ),
                        ) );
                        $this->add_responsive_control( $id . '_bw', array(
                                'label'     => __( 'ضخامت حاشیه', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SLIDER,
                                'range'     => array( 'px' => array( 'min' => 0, 'max' => 10 ) ),
                                'selectors' => array( $selector => 'border-width: {{SIZE}}{{UNIT}};' ),
                        ) );
                }
                if ( $opts['radius'] ) {
                        $this->add_responsive_control( $id . '_radius', array(
                                'label'     => __( 'گردی گوشه‌ها', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SLIDER,
                                'range'     => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
                                'selectors' => array( $selector => 'border-radius: {{SIZE}}{{UNIT}};' ),
                        ) );
                }
                if ( $opts['padding'] ) {
                        $this->add_responsive_control( $id . '_padding', array(
                                'label'      => __( 'پدینگ', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::DIMENSIONS,
                                'size_units' => array( 'px' ),
                                'selectors'  => array( $selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
                        ) );
                }
                if ( $opts['shadow'] ) {
                        $this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
                                'name'     => $id . '_shadow',
                                'selector' => $selector,
                        ) );
                }
        }

        /**
         * Text style controls: typography group + color (+ optional hover color).
         */
        protected function ctrl_text( $id, $selector, $opts = array() ) {
                $opts = wp_parse_args( $opts, array( 'hover' => false, 'separator' => false ) );
                $args = array(
                        'label'     => __( 'تایپوگرافی', 'hamishop-elementor-widgets' ),
                        'type'      => Group_Control_Typography::get_type(),
                        'selector'  => $selector,
                );
                if ( $opts['separator'] ) {
                        $args['separator'] = 'before';
                }
                $this->add_control( $id . '_typo', $args );
                $this->add_control( $id . '_c', array(
                        'label'     => __( 'رنگ متن', 'hamishop-elementor-widgets' ),
                        'type'      => Controls_Manager::COLOR,
                        'selectors' => array( $selector => 'color: {{VALUE}};' ),
                ) );
                if ( $opts['hover'] ) {
                        $this->add_control( $id . '_ch', array(
                                'label'     => __( 'رنگ متن (هاور)', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( $selector . ':hover' => 'color: {{VALUE}};' ),
                        ) );
                }
        }

        /**
         * Icon controls: color + font size (for .hsw-ic spans).
         */
        protected function ctrl_icon( $id, $selector ) {
                $this->add_control( $id . '_c', array(
                        'label'     => __( 'رنگ آیکون', 'hamishop-elementor-widgets' ),
                        'type'      => Controls_Manager::COLOR,
                        'selectors' => array( $selector => 'color: {{VALUE}};' ),
                ) );
                $this->add_control( $id . '_s', array(
                        'label'     => __( 'اندازه آیکون', 'hamishop-elementor-widgets' ),
                        'type'      => Controls_Manager::SLIDER,
                        'range'     => array( 'px' => array( 'min' => 10, 'max' => 64 ) ),
                        'selectors' => array( $selector => 'font-size: {{SIZE}}{{UNIT}};' ),
                ) );
        }

        /**
         * Extended icon picker options for the product page widgets.
         */
        protected function product_icon_options() {
                return array_merge( HSW_Widget_Base::icon_options(), array(
                        'zoom_in'               => 'بزرگنمایی',
                        'verified'              => 'تأیید شده',
                        'assignment_return'     => 'بازگشت کالا',
                        'check_circle'          => 'تیک دایره',
                        'flash_on'              => 'برق (خرید سریع)',
                        'shield'                => 'سپر (گارانتی)',
                        'inventory_2'           => 'انبار',
                        'tune'                  => 'تنظیمات (مشخصات)',
                        'description'           => 'سند (بررسی)',
                        'rate_review'           => 'دیدگاه',
                        'category'              => 'دسته‌ها',
                        'person'                => 'کاربر',
                        'expand_more'           => 'باز شدن',
                        'photo_camera'          => 'دوربین',
                        'memory'                => 'تراشه',
                        'battery_charging_full' => 'باتری',
                        'edit'                  => 'قلم / ویرایش',
                        'security'              => 'امنیت',
                        'home'                  => 'خانه',
                        'share'                 => 'اشتراک‌گذاری',
                        'search'                => 'جستجو',
                        'storefront'            => 'فروشگاه',
                        'workspace_premium'     => 'گواهی کیفیت',
                        'support_agent'         => 'پشتیبانی',
                        'payments'              => 'پرداخت',
                        'redeem'                => 'هدیه',
                        'discount'              => 'تخفیف',
                        'thumb_up'              => 'پیشنهاد',
                ) );
        }
}
