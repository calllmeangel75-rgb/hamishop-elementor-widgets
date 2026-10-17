<?php
/**
 * Shared helpers for all HamiShop widgets.
 *
 * @package HamiShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Static helper toolbox.
 */
final class HSW_Helpers {

        /**
         * Persian digit map.
         *
         * @var string[]
         */
        private static $fa_digits = [
                '0' => '۰',
                '1' => '۱',
                '2' => '۲',
                '3' => '۳',
                '4' => '۴',
                '5' => '۵',
                '6' => '۶',
                '7' => '۷',
                '8' => '۸',
                '9' => '۹',
        ];

        /**
         * Is Elementor edit/preview mode active?
         *
         * @return bool
         */
        public static function is_editor() {
                $elementor = \Elementor\Plugin::$instance;
                return $elementor->editor->is_edit_mode() || $elementor->preview->is_preview_mode();
        }

        /**
         * Resolve the product of the current loop (works inside Elementor Loop
         * templates, WooCommerce archives and single product pages).
         *
         * @return \WC_Product|null
         */
        public static function get_current_product() {
                global $product;

                if ( $product instanceof \WC_Product ) {
                        return $product;
                }

                $post_id = get_the_ID();
                if ( ! $post_id ) {
                        return null;
                }

                $wc_product = wc_get_product( $post_id );
                return $wc_product ? $wc_product : null;
        }

        /**
         * Resolve products from widget settings.
         *
         * Expected settings keys:
         *  - {source_key}        : 'loop' | 'manual' | 'single'
         *  - 'product_ids'       : int[] (manual, multiple)
         *  - 'product_id'        : int   (single mode)
         *
         * @param array  $settings   Elementor settings array.
         * @param string $source_key Setting key holding the source mode.
         * @return \WC_Product[]
         */
        public static function get_products_from_settings( array $settings, $source_key = 'product_source' ) {
                $source = isset( $settings[ $source_key ] ) ? $settings[ $source_key ] : 'loop';
                $items  = [];

                if ( 'manual' === $source ) {
                        $ids = isset( $settings['product_ids'] ) ? (array) $settings['product_ids'] : [];
                        foreach ( $ids as $id ) {
                                $p = wc_get_product( absint( $id ) );
                                if ( $p ) {
                                        $items[] = $p;
                                }
                        }
                } elseif ( 'single' === $source ) {
                        $id = isset( $settings['product_id'] ) ? absint( $settings['product_id'] ) : 0;
                        $p  = $id ? wc_get_product( $id ) : null;
                        if ( $p ) {
                                $items[] = $p;
                        }
                } else {
                        $current = self::get_current_product();
                        if ( $current ) {
                                $items[] = $current;
                        }
                }

                return $items;
        }

        /**
         * Convert latin digits to Persian digits inside text nodes (tag-safe).
         *
         * @param string $text Text or safe HTML.
         * @return string
         */
        public static function maybe_fa( $text ) {
                if ( ! self::fa_numbers_enabled() || '' === $text ) {
                        return $text;
                }

                if ( false === strpos( $text, '<' ) ) {
                        return strtr( $text, self::$fa_digits );
                }

                return preg_replace_callback(
                        '/>([^<>]+)</',
                        function ( $m ) {
                                return '>' . strtr( $m[1], self::$fa_digits ) . '<';
                        },
                        $text
                );
        }

        /**
         * Are Persian digits enabled?
         *
         * @return bool
         */
        public static function fa_numbers_enabled() {
                return 'yes' === get_option( 'hsw_persian_numbers', 'yes' );
        }

        /**
         * Discount percentage between regular & current price.
         *
         * @param \WC_Product $product Product.
         * @return int 0 when not on sale.
         */
        public static function sale_percent( $product ) {
                $regular = (float) $product->get_regular_price();
                $sale    = (float) $product->get_price();

                if ( $regular > 0 && $sale > 0 && $sale < $regular ) {
                        return (int) round( ( ( $regular - $sale ) / $regular ) * 100 );
                }
                return 0;
        }

        /**
         * Was the product created within the last N days?
         *
         * @param \WC_Product $product Product.
         * @param int         $days    Window in days.
         * @return bool
         */
        public static function is_new( $product, $days = 14 ) {
                $created = $product->get_date_created();
                if ( ! $created ) {
                        return false;
                }
                return ( time() - $created->getTimestamp() ) <= ( $days * DAY_IN_SECONDS );
        }

        /**
         * Trim a plain text to N words (unicode safe).
         *
         * @param string $text  Text.
         * @param int    $words Word limit.
         * @return string
         */
        public static function trim_words( $text, $words = 12 ) {
                $text = wp_strip_all_tags( (string) $text );
                return wp_trim_words( $text, max( 1, (int) $words ), '…' );
        }

        /**
         * Lightweight star rating markup (no icon-font dependency).
         *
         * @param \WC_Product $product    Product.
         * @param bool        $show_count Show review count.
         * @return string
         */
        public static function rating_stars( $product, $show_count = false ) {
                if ( ! wc_review_ratings_enabled() ) {
                        return '';
                }

                $average = (float) $product->get_average_rating();
                if ( $average <= 0 ) {
                        return '';
                }

                $percent = max( 0, min( 100, (int) round( ( $average / 5 ) * 100 ) ) );
                $count   = (int) $product->get_review_count();

                $out  = '<span class="hsw-rating" aria-hidden="true">';
                $out .= '<span class="hsw-rating__base">★★★★★</span>';
                $out .= '<span class="hsw-rating__fill" style="--hsw-rating:' . esc_attr( $percent ) . '%">★★★★★</span>';
                $out .= '</span>';

                if ( $show_count && $count > 0 ) {
                        $out .= '<span class="hsw-rating__count">(' . esc_html( self::maybe_fa( (string) $count ) ) . ')</span>';
                }

                return '<span class="hsw-rating-wrap">' . $out . '</span>';
        }

        /**
         * WooCommerce-native add-to-cart markup (keeps core AJAX behaviour).
         * Call inside the loop context or with a product set as global.
         *
         * @param \WC_Product $product Product.
         * @return string
         */
        public static function add_to_cart_markup( $product ) {
                $prev = isset( $GLOBALS['product'] ) ? $GLOBALS['product'] : null;
                $GLOBALS['product'] = $product;

                ob_start();
                woocommerce_template_loop_add_to_cart();
                $html = ob_get_clean();

                // Restore the original global product if one existed.
                if ( $prev instanceof \WC_Product ) {
                        $GLOBALS['product'] = $prev;
                }

                return $html;
        }

        /**
         * Product image <img> tag (lazy, with width/height to avoid CLS).
         *
         * @param \WC_Product $product Product.
         * @param string      $class   Extra CSS class.
         * @param string|null $size    Image size.
         * @return string
         */
        public static function product_image( $product, $class = 'hsw-card__image', $size = 'woocommerce_thumbnail' ) {
                $image_id = $product->get_image_id();

                if ( ! $image_id ) {
                        // WooCommerce ships an inline placeholder — no external request.
                        $placeholder = wc_placeholder_img_src( $size );
                        return '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $placeholder ) . '" alt="' . esc_attr( $product->get_name() ) . '" loading="lazy" />';
                }

                $src = wp_get_attachment_image_src( $image_id, $size );
                if ( ! $src ) {
                        return '';
                }

                $meta = wp_get_attachment_metadata( $image_id );

                return sprintf(
                        '<img class="%1$s" src="%2$s" alt="%3$s" loading="lazy" decoding="async"%4$s%5$s />',
                        esc_attr( $class ),
                        esc_url( $src[0] ),
                        esc_attr( wp_get_attachment_caption( $image_id ) ? wp_get_attachment_caption( $image_id ) : $product->get_name() ),
                        ( $meta && ! empty( $meta['width'] ) ) ? ' width="' . esc_attr( (string) $meta['width'] ) . '"' : '',
                        ( $meta && ! empty( $meta['height'] ) ) ? ' height="' . esc_attr( (string) $meta['height'] ) . '"' : ''
                );
        }

        /**
         * Secondary gallery image for the hover-swap effect.
         *
         * @param \WC_Product $product Product.
         * @return string Empty string when no gallery image.
         */
        public static function product_hover_image( $product ) {
                $gallery_ids = $product->get_gallery_image_ids();
                if ( empty( $gallery_ids ) ) {
                        return '';
                }

                $second_id = (int) $gallery_ids[0];
                $src       = wp_get_attachment_image_src( $second_id, 'woocommerce_thumbnail' );
                if ( ! $src ) {
                        return '';
                }

                $meta = wp_get_attachment_metadata( $second_id );

                return sprintf(
                        '<img class="hsw-card__image hsw-card__image--alt" src="%1$s" alt="%2$s" loading="lazy" decoding="async"%3$s%4$s />',
                        esc_url( $src[0] ),
                        esc_attr( $product->get_name() ),
                        ( $meta && ! empty( $meta['width'] ) ) ? ' width="' . esc_attr( (string) $meta['width'] ) . '"' : '',
                        ( $meta && ! empty( $meta['height'] ) ) ? ' height="' . esc_attr( (string) $meta['height'] ) . '"' : ''
                );
        }

        /**
         * First N product categories as links.
         *
         * @param \WC_Product $product Product.
         * @param int         $limit   Max terms.
         * @return string
         */
        public static function product_categories( $product, $limit = 1 ) {
                $terms = get_the_terms( $product->get_id(), 'product_cat' );

                if ( ! $terms || is_wp_error( $terms ) ) {
                        return '';
                }

                usort( $terms, function ( $a, $b ) {
                        return strcmp( $a->name, $b->name );
                } );

                $terms = array_slice( $terms, 0, max( 1, (int) $limit ) );

                $links = [];
                foreach ( $terms as $term ) {
                        $links[] = '<a class="hsw-card__cat" href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a>';
                }

                return implode( '', $links );
        }

        /**
         * Inline SVG icons (no icon font, no external request).
         *
         * @param string $name Icon key.
         * @return string
         */
        public static function svg( $name ) {
                $icons = [
                        'cart' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="9" cy="21" r="1.4"/><circle cx="19" cy="21" r="1.4"/><path d="M2.5 3h2l2.6 12.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L22.5 7H6"/></svg>',
                        'bag'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 7h12l1.2 13.2a1.6 1.6 0 0 1-1.6 1.8H6.4a1.6 1.6 0 0 1-1.6-1.8L6 7z"/><path d="M9 10V6a3 3 0 0 1 6 0v4"/></svg>',
                        'eye'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M1.5 12S5.5 5 12 5s10.5 7 10.5 7-4 7-10.5 7S1.5 12 1.5 12z"/><circle cx="12" cy="12" r="3"/></svg>',
                        'chevron' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7"/></svg>',
                ];

                $icons = apply_filters( 'hsw_icons', $icons );

                return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
        }

        /**
         * Editor-only placeholder box shown when there is nothing to render yet.
         *
         * @param string $message Message.
         * @return string
         */
        public static function editor_note( $message ) {
                if ( ! self::is_editor() ) {
                        return '';
                }
                return '<div class="hsw-editor-note">' . esc_html( $message ) . '</div>';
        }

        /**
         * Sanitize a CSS color-ish value coming from an Elementor control.
         *
         * @param mixed $value Value.
         * @return string
         */
        public static function sanitize_css_unit( $value ) {
                return esc_attr( preg_replace( '/[^a-zA-Z0-9_%.,\- \(\)]/', '', (string) $value ) );
        }
}
