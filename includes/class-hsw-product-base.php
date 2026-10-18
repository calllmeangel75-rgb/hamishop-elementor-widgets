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
                                'price'    => HSW_Helpers::price_num( $sale > 0 ? $sale : $regular ),
                                'regular'  => HSW_Helpers::price_num( $regular ),
                                'discount' => HSW_Helpers::discount_percent( $var ),
                                'image'    => $img_id ? wp_get_attachment_image_url( $img_id, 'woocommerce_single' ) : '',
                                'in_stock' => $var->is_in_stock(),
                                'attrs'    => $attrs,
                        );
                }
                return $out;
        }

        /**
         * Terms of a product attribute taxonomy with a chosen swatch color.
         * Color map: repeater in the variants widget (term name → hex).
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
                        $terms[] = array( 'name' => $term->name, 'slug' => $term->slug );
                }
                if ( empty( $terms ) && $attrs[ $taxonomy ]->is_taxonomy() === false ) {
                        foreach ( $attrs[ $taxonomy ]->get_options() as $opt ) {
                                $terms[] = array( 'name' => $opt, 'slug' => sanitize_title( $opt ) );
                        }
                }
                return $terms;
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
         */
        protected function reviews_list( $product, $limit = 5 ) {
                if ( ! $product ) {
                        return array();
                }
                $comments = get_comments(
                        array(
                                'post_id'      => $product->get_id(),
                                'status'       => 'approve',
                                'number'       => $limit,
                                'type'         => 'review',
                                'meta_key'     => 'rating',
                                'orderby'      => 'comment_date',
                                'order'        => 'DESC',
                        )
                );
                $out = array();
                foreach ( $comments as $c ) {
                        $name  = $c->comment_author;
                        $parts = preg_split( '/\s+/u', trim( $name ) );
                        $ini   = '';
                        foreach ( array_slice( $parts, 0, 2 ) as $p ) {
                                $ini .= function_exists( 'mb_substr' ) ? mb_substr( $p, 0, 1 ) : substr( $p, 0, 1 );
                        }
                        $out[] = array(
                                'name'    => $name,
                                'initials'=> $ini,
                                'rating'  => (int) get_comment_meta( $c->comment_ID, 'rating', true ),
                                'text'    => wp_strip_all_tags( $c->comment_content ),
                                'verified'=> wc_customer_bought_product( '', $c->comment_author_email, $product->get_id() ),
                        );
                }
                return $out;
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
