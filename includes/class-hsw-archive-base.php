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
                $s    = $this->get_settings_for_display();
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
                // phpcs:disable WordPress.Security.NonceVerification.Recommended -- public archive filters.
                if ( ! empty( $_GET ) && is_array( $_GET ) ) {
                        foreach ( $_GET as $k => $v ) {
                                $k = sanitize_key( wp_unslash( $k ) );
                                if ( 0 !== strpos( $k, 'hsw_' ) && 'paged' !== $k ) {
                                        $params[ $k ] = sanitize_text_field( wp_unslash( $v ) );
                                }
                        }
                }
                // phpcs:enable
                return $params;
        }

        /**
         * Build an archive URL with modified params (null removes a key).
         *
         * @param array $changes Key => value|null.
         */
        protected function filter_url( $changes = array() ) {
                $params = $this->current_params();
                unset( $params['paged'] );
                foreach ( $changes as $k => $v ) {
                        if ( null === $v || '' === $v ) {
                                unset( $params[ $k ] );
                        } else {
                                $params[ $k ] = $v;
                        }
                }
                $url = $this->archive_url();
                if ( ! empty( $params ) ) {
                        $url = add_query_arg( array_map( 'rawurlencode', $params ), $url );
                }
                return $url;
        }

        /**
         * Count of active filter params (badges + mobile apply count).
         */
        protected function active_filter_count() {
                $count = 0;
                foreach ( array_keys( $this->current_params() ) as $k ) {
                        if ( 'orderby' === $k || 'view' === $k || 0 === strpos( $k, 'query_type_' ) ) {
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
                return ( $tax_obj && ! empty( $tax_obj->labels->singular_name ) ) ? $tax_obj->labels->singular_name : $tax;
        }

        /**
         * Value of a widget option with default fallback.
         */
        protected function opt( $key, $default = '' ) {
                $s = $this->get_settings_for_display();
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
         * Terms of an attribute taxonomy (for brand checkboxes, memory chips, colors).
         */
        protected function filter_terms( $taxonomy ) {
                if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
                        return array();
                }
                $terms = get_terms(
                        array(
                                'taxonomy'   => $taxonomy,
                                'hide_empty' => true,
                        )
                );
                if ( is_wp_error( $terms ) ) {
                        return array();
                }
                return $terms;
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
         * Single color control bound to a selector.
         */
        protected function a_color( $id, $label, $selector, $prop = 'color', $args = array() ) {
                $this->add_control(
                        $id,
                        wp_parse_args(
                                $args,
                                array(
                                        'label'     => $label,
                                        'type'      => Controls_Manager::COLOR,
                                        'selectors' => array( $selector => $prop . ': {{VALUE}};' ),
                                )
                        )
                );
        }

        /**
         * Box styles: background + border color/width + radius.
         */
        protected function a_box( $id, $selector, $opts = array() ) {
                $opts = wp_parse_args( $opts, array( 'border' => true, 'radius' => true, 'bg' => true ) );
                if ( $opts['bg'] ) {
                        $this->a_color( $id . '_bg', __( 'پس‌زمینه', 'hamishop-elementor-widgets' ), $selector, 'background-color' );
                }
                if ( $opts['border'] ) {
                        $this->a_color( $id . '_bc', __( 'رنگ حاشیه', 'hamishop-elementor-widgets' ), $selector, 'border-color' );
                }
                if ( $opts['radius'] ) {
                        $this->add_responsive_control(
                                $id . '_br',
                                array(
                                        'label'      => __( 'گردی گوشه‌ها', 'hamishop-elementor-widgets' ),
                                        'type'       => Controls_Manager::SLIDER,
                                        'size_units' => array( 'px' ),
                                        'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
                                        'selectors'  => array( $selector => 'border-radius: {{SIZE}}{{UNIT}};' ),
                                )
                        );
                }
        }

        /**
         * Text style: color + size + weight.
         */
        protected function a_text( $id, $selector, $args = array() ) {
                $args = wp_parse_args(
                        $args,
                        array(
                                'size'    => array( 'min' => 8, 'max' => 40 ),
                                'default' => 12,
                                'weight'  => true,
                        )
                );
                $this->a_color( $id . '_c', __( 'رنگ متن', 'hamishop-elementor-widgets' ), $selector );
                $this->add_control(
                        $id . '_s',
                        array(
                                'label'      => __( 'اندازه فونت', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => $args['size'] ),
                                'default'    => array( 'size' => $args['default'], 'unit' => 'px' ),
                                'selectors'  => array( $selector => 'font-size: {{SIZE}}{{UNIT}};' ),
                        )
                );
                if ( $args['weight'] ) {
                        $this->add_control(
                                $id . '_w',
                                array(
                                        'label'     => __( 'ضخامت فونت', 'hamishop-elementor-widgets' ),
                                        'type'      => Controls_Manager::SELECT,
                                        'default'   => '',
                                        'options'   => array(
                                                ''    => __( 'پیش‌فرض', 'hamishop-elementor-widgets' ),
                                                '400' => '400',
                                                '500' => '500',
                                                '600' => '600',
                                                '700' => '700',
                                                '800' => '800',
                                                '900' => '900',
                                        ),
                                        'selectors' => array( $selector => 'font-weight: {{VALUE}};' ),
                                )
                        );
                }
        }
}
