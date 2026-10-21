<?php
/**
 * Shared rendering helpers (numbers, prices, badges, stock, icons).
 * All defaults replicate the pasted HTML design exactly.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Helpers {

        /** Persian digits map. */
        private static $fa_digits = array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' );

        /**
         * Convert latin digits to Persian digits.
         */
        public static function fa_num( $value ) {
                return strtr( (string) $value, self::$fa_digits );
        }

        /**
         * Format a price number with thousands separator (+ optional Persian digits).
         */
        public static function price_num( $number, $persian = true ) {
                $formatted = number_format( (float) $number, 0, '.', ',' );
                return $persian ? self::fa_num( $formatted ) : $formatted;
        }

        /**
         * Material Symbols icon markup (bundled subset font, zero external requests).
         */
        public static function icon( $name, $class = '', $fill = false ) {
                $aliases = array(
                        'discount' => 'percent',
                );
                if ( isset( $aliases[ $name ] ) ) {
                        $name = $aliases[ $name ];
                }
                $style = $fill ? ' style="font-variation-settings:\'FILL\' 1;"' : '';
                return '<span class="hsw-ic ' . esc_attr( $class ) . '"' . $style . '>' . esc_html( $name ) . '</span>';
        }

        /**
         * Discount percentage of a product (0 when not on sale).
         */
        public static function discount_percent( $product ) {
                if ( self::is_call_for_price( $product ) ) {
                        return 0;
                }
                $regular = (float) $product->get_regular_price();
                $sale    = (float) $product->get_price();
                if ( $regular <= 0 || $sale <= 0 || $sale >= $regular ) {
                        return 0;
                }
                return (int) round( ( ( $regular - $sale ) / $regular ) * 100 );
        }

        /**
         * Brand label: chosen attribute (default pa_brand) with category fallback.
         */
        public static function brand_label( $product, $attribute = 'pa_brand' ) {
                $attribute = $attribute ? $attribute : 'pa_brand';
                $brands    = $product->get_attribute( $attribute );
                if ( ! empty( $brands ) ) {
                        $parts = explode( ',', $brands );
                        return trim( $parts[0] );
                }
                $terms = get_the_terms( $product->get_id(), 'product_cat' );
                if ( $terms && ! is_wp_error( $terms ) ) {
                        $default_id = (int) get_option( 'default_product_cat', 0 );
                        foreach ( $terms as $term ) {
                                if ( $default_id && (int) $term->term_id === $default_id ) {
                                        continue;
                                }
                                if ( 'بدون دسته‌بندی' === $term->name || 'Uncategorized' === $term->name ) {
                                        continue;
                                }
                                return $term->name;
                        }
                }
                return '';
        }

        /**
         * Sold-percent + remaining stock text for the progress bar.
         * percent = total_sales / (total_sales + stock) bounded 5..95.
         */
        public static function stock_progress( $product ) {
                $stock = $product->get_stock_quantity();
                if ( null === $stock ) {
                        return null;
                }
                $sales = (int) $product->get_total_sales();
                $total = max( 1, $sales + max( 0, (int) $stock ) );
                $percent = (int) min( 95, max( 5, round( ( $sales / $total ) * 100 ) ) );

                if ( $stock <= 0 ) {
                        $left     = __( 'اتمام موجودی', 'hamishop-elementor-widgets' );
                        $critical = true;
                } elseif ( $stock <= 3 ) {
                        /* translators: %s: number */
                        $left     = sprintf( __( 'فقط %s عدد در انبار', 'hamishop-elementor-widgets' ), self::fa_num( $stock ) );
                        $critical = true;
                } else {
                        $left     = __( 'موجود در انبار', 'hamishop-elementor-widgets' );
                        $critical = false;
                }
                /* translators: %s: percent */
                $sold = sprintf( __( 'فروش رفته: %s٪', 'hamishop-elementor-widgets' ), self::fa_num( $percent ) );

                return array(
                        'percent'  => $percent,
                        'sold'     => $sold,
                        'left'     => $left,
                        'critical' => $critical,
                );
        }

        /**
         * Safe image markup with srcset/sizes + lazy loading.
         */
        public static function image( $product, $size = 'woocommerce_thumbnail' ) {
                $image_id = $product->get_image_id();
                if ( ! $image_id ) {
                        return '<span class="hsw-media__placeholder"></span>';
                }
                $html = wp_get_attachment_image(
                        $image_id,
                        $size,
                        false,
                        array(
                                'class'    => 'hsw-media__img',
                                'alt'      => trim( wp_strip_all_tags( $product->get_name() ) ),
                                'loading'  => 'lazy',
                                'decoding' => 'async',
                        )
                );
                if ( $html ) {
                        return $html;
                }
                $src = wp_get_attachment_image_url( $image_id, $size );
                return sprintf(
                        '<img class="hsw-media__img" src="%s" alt="%s" loading="lazy" decoding="async">',
                        esc_url( $src ),
                        esc_attr( trim( wp_strip_all_tags( $product->get_name() ) ) )
                );
        }

        /**
         * Store currency label for display (decoded WooCommerce symbol).
         */
        public static function currency_label() {
                if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
                        $symbol = wp_specialchars_decode( get_woocommerce_currency_symbol(), ENT_QUOTES );
                        if ( '' !== trim( (string) $symbol ) ) {
                                return trim( (string) $symbol );
                        }
                }
                return __( 'تومان', 'hamishop-elementor-widgets' );
        }

        /**
         * Sale-end timestamp for countdown (latest among given products), or 0.
         */
        public static function latest_sale_end( $products ) {
                $latest = 0;
                foreach ( $products as $product ) {
                        $end = $product->get_date_on_sale_to();
                        if ( $end instanceof WC_DateTime ) {
                                $latest = max( $latest, (int) $end->getTimestamp() );
                        }
                }
                return $latest;
        }

        /* =========================================================================
         *  HAMI CALL FOR PRICE ADAPTER
         *
         *  HamiShop renders its own price markup, so WooCommerce's
         *  `woocommerce_get_price_html` filter never runs inside these widgets.
         *  This small adapter reads the public Hami CFP API instead of guessing
         *  from raw product prices, keeping both plugins independent.
         * ====================================================================== */

        /**
         * Is the Hami Call for Price plugin active with its public API available?
         */
        public static function cfp_supported() {
                return class_exists( 'Hami_Helpers' )
                        && method_exists( 'Hami_Helpers', 'is_call_for_price' )
                        && method_exists( 'Hami_Helpers', 'get_contact' );
        }

        /**
         * Whether a product is flagged "call for price" by Hami CFP.
         */
        public static function is_call_for_price( $product ) {
                return $product instanceof WC_Product
                        && self::cfp_supported()
                        && (bool) Hami_Helpers::is_call_for_price( $product );
        }

        /**
         * Normalized contact data for a CFP product.
         *
         * @return array{url:string,channel:string,external:bool,label:string,icon:string}|null
         */
        public static function cfp_contact( $product ) {
                if ( ! self::is_call_for_price( $product ) ) {
                        return null;
                }

                $contact = Hami_Helpers::get_contact( $product );

                if ( ! is_array( $contact ) ) {
                        return null;
                }

                $contact = wp_parse_args(
                        $contact,
                        array(
                                'url'      => '',
                                'channel'  => 'tel',
                                'external' => false,
                                'label'    => __( 'تماس بگیرید', 'hamishop-elementor-widgets' ),
                                'icon'     => '📞',
                        )
                );

                if ( '' === trim( (string) $contact['label'] ) ) {
                        $contact['label'] = __( 'تماس بگیرید', 'hamishop-elementor-widgets' );
                }
                if ( '' === trim( (string) $contact['icon'] ) ) {
                        $contact['icon'] = '📞';
                }

                return $contact;
        }

        /**
         * Inline CFP label (used where the price would normally sit).
         *
         * @param WC_Product $product Product.
         * @param bool       $link    Render a contact link instead of plain text.
         * @param string     $class   Extra class names.
         */
        public static function cfp_inline_html( $product, $link = false, $class = '' ) {
                $contact = self::cfp_contact( $product );

                if ( ! $contact ) {
                        return '';
                }

                $inner = '<span class="hsw-cfp__icon" aria-hidden="true">' . esc_html( $contact['icon'] ) . '</span>' .
                        '<span class="hsw-cfp__label">' . esc_html( $contact['label'] ) . '</span>';

                $classes = trim( 'hsw-cfp hsw-cfp--inline hsw-cfp--' . sanitize_html_class( (string) $contact['channel'] ) . ( $class ? ' ' . $class : '' ) );

                if ( $link && '' !== (string) $contact['url'] ) {
                        $target = ! empty( $contact['external'] ) ? ' target="_blank" rel="noopener"' : '';
                        return sprintf(
                                '<a class="%1$s hsw-cfp--link" href="%2$s"%3$s>%4$s</a>',
                                esc_attr( $classes ),
                                esc_url( $contact['url'] ),
                                $target,
                                $inner
                        );
                }

                return '<span class="' . esc_attr( $classes ) . '">' . $inner . '</span>';
        }

        /**
         * CFP action link/button (archive CTA, purchase box, sticky bar).
         *
         * @param WC_Product $product    Product.
         * @param string     $classes    Extra class names.
         * @param bool       $show_label Show the contact label next to the icon.
         */
        public static function cfp_action_html( $product, $classes = '', $show_label = false ) {
                $contact = self::cfp_contact( $product );

                if ( ! $contact ) {
                        return '';
                }

                $inner = '<span class="hsw-cfp__icon" aria-hidden="true">' . esc_html( $contact['icon'] ) . '</span>';
                if ( $show_label ) {
                        $inner .= '<span class="hsw-cfp__label">' . esc_html( $contact['label'] ) . '</span>';
                }

                $classes = trim( $classes . ' hsw-cfp-link hsw-cfp-link--' . sanitize_html_class( (string) $contact['channel'] ) );

                if ( '' === (string) $contact['url'] ) {
                        return '<span class="' . esc_attr( $classes . ' hsw-cfp-link--disabled' ) . '">' . $inner . '</span>';
                }

                $target = ! empty( $contact['external'] ) ? ' target="_blank" rel="noopener"' : '';

                return sprintf(
                        '<a class="%1$s" href="%2$s"%3$s aria-label="%4$s">%5$s</a>',
                        esc_attr( $classes ),
                        esc_url( $contact['url'] ),
                        $target,
                        esc_attr( $contact['label'] ),
                        $inner
                );
        }
}
