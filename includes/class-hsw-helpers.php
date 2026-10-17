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
                $style = $fill ? ' style="font-variation-settings:\'FILL\' 1;"' : '';
                return '<span class="hsw-ic ' . esc_attr( $class ) . '"' . $style . '>' . esc_html( $name ) . '</span>';
        }

        /**
         * Discount percentage of a product (0 when not on sale).
         */
        public static function discount_percent( $product ) {
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
                        return $terms[0]->name;
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
         * Safe image markup with lazy loading + decoding hints.
         */
        public static function image( $product, $size = 'woocommerce_thumbnail' ) {
                $image_id = $product->get_image_id();
                if ( ! $image_id ) {
                        return '<span class="hsw-media__placeholder"></span>';
                }
                $src = wp_get_attachment_image_url( $image_id, $size );
                $alt = trim( wp_strip_all_tags( $product->get_name() ) );
                return sprintf(
                        '<img class="hsw-media__img" src="%s" alt="%s" loading="lazy" decoding="async">',
                        esc_url( $src ),
                        esc_attr( $alt )
                );
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
}
