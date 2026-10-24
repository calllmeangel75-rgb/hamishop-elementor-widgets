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
         * Convert Persian/Arabic digits back to latin, and drop anything else.
         *
         * Used wherever a customer types a number into a form (captcha answers,
         * order ids): a Persian keyboard produces ۱۲۳, which `(int)` turns into 0.
         *
         * @param string $value Raw input.
         * @return string Digits only.
         */
        public static function en_num( $value ) {
                $map = array(
                        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
                        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
                );
                return preg_replace( '/\D/', '', strtr( (string) $value, $map ) );
        }

        /**
         * Format a price number with thousands separator (+ optional Persian digits).
         */
        public static function price_num( $number, $persian = true ) {
                $formatted = number_format( (float) $number, 0, '.', ',' );
                return $persian ? self::fa_num( $formatted ) : $formatted;
        }

        /**
         * How much the customer is saving on the current cart.
         *
         * Two sources, added together:
         *  - product discount: regular price minus the price actually charged,
         *  - coupon discount: `get_discount_total()` (already excluding tax).
         *
         * Reading the regular price per item is the only way to know what the
         * shop "would have" charged; `get_subtotal()` alone cannot tell a sale
         * apart from a cheaper product. The loop is over cart items (a handful)
         * and only runs when a summary is rendered.
         *
         * @return array{product:float,coupon:float,total:float}
         */
        public static function savings() {
                $empty = array(
                        'product' => 0.0,
                        'coupon'  => 0.0,
                        'total'   => 0.0,
                );

                if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
                        return $empty;
                }

                $product_saving = 0.0;

                foreach ( WC()->cart->get_cart() as $item ) {
                        $product = isset( $item['data'] ) ? $item['data'] : null;
                        if ( ! $product instanceof WC_Product ) {
                                continue;
                        }

                        $regular = (float) $product->get_regular_price();
                        $price   = (float) $product->get_price();
                        $qty     = isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;

                        if ( $regular > $price && $qty > 0 ) {
                                $product_saving += ( $regular - $price ) * $qty;
                        }
                }

                $coupon_saving = (float) WC()->cart->get_discount_total();

                return array(
                        'product' => $product_saving,
                        'coupon'  => $coupon_saving,
                        'total'   => $product_saving + $coupon_saving,
                );
        }

        /**
         * The "سود شما از خرید" row, or an empty string when there is no saving.
         *
         * Returning '' instead of a zero row keeps the summary honest: a shop
         * with no active sale never shows the line at all.
         *
         * @param string $extra_class Extra class for family-specific styling.
         * @return string
         */
        public static function savings_html( $extra_class = '' ) {
                $saving = self::savings();

                if ( $saving['total'] <= 0 || ! function_exists( 'wc_price' ) ) {
                        return '';
                }

                $amount = self::fa_num( html_entity_decode( (string) wc_price( $saving['total'] ), ENT_QUOTES, 'UTF-8' ) );

                return '<div class="hsw-savings ' . esc_attr( $extra_class ) . '">'
                        . '<span class="hsw-savings__ic">' . self::icon( 'savings' ) . '</span>'
                        . '<span class="hsw-savings__label">' . esc_html__( 'سود شما از خرید', 'hamishop-elementor-widgets' ) . '</span>'
                        . '<span class="hsw-savings__amount">' . wp_kses_post( $amount ) . '</span>'
                        . '</div>';
        }

        /**
         * Amount in Persian words, for the invoice.
         *
         * The reference invoice prints the paid amount both as a number and as
         * words; on a printed document that is what makes a total hard to
         * alter. Handles up to hundreds of billions, which is enough for a
         * Toman store.
         *
         * @param float $amount Amount.
         * @return string
         */
        public static function number_words( $amount ) {
                $amount = (float) $amount;
                if ( $amount <= 0 ) {
                        return __( 'صفر', 'hamishop-elementor-widgets' );
                }

                $ones = array(
                        1  => 'یک',
                        2  => 'دو',
                        3  => 'سه',
                        4  => 'چهار',
                        5  => 'پنج',
                        6  => 'شش',
                        7  => 'هفت',
                        8  => 'هشت',
                        9  => 'نه',
                );
                $teens = array(
                        10 => 'ده',
                        11 => 'یازده',
                        12 => 'دوازده',
                        13 => 'سیزده',
                        14 => 'چهارده',
                        15 => 'پانزده',
                        16 => 'شانزده',
                        17 => 'هفده',
                        18 => 'هجده',
                        19 => 'نوزده',
                );
                $tens = array(
                        2 => 'بیست',
                        3 => 'سی',
                        4 => 'چهل',
                        5 => 'پنجاه',
                        6 => 'شصت',
                        7 => 'هفتاد',
                        8 => 'هشتاد',
                        9 => 'نود',
                );
                $hundreds = array(
                        1 => 'صد',
                        2 => 'دویست',
                        3 => 'سیصد',
                        4 => 'چهارصد',
                        5 => 'پانصد',
                        6 => 'ششصد',
                        7 => 'هفتصد',
                        8 => 'هشتصد',
                        9 => 'نهصد',
                );
                $scales = array( '', ' هزار', ' میلیون', ' میلیارد', ' هزار میلیارد' );

                /* Three digits at a time, right to left. */
                $three = static function ( $n ) use ( $ones, $teens, $tens, $hundreds ) {
                        $parts = array();

                        $h = intdiv( $n, 100 );
                        if ( $h > 0 ) {
                                $parts[] = $hundreds[ $h ];
                        }

                        $rest = $n % 100;
                        if ( $rest >= 10 && $rest < 20 ) {
                                $parts[] = $teens[ $rest ];
                        } else {
                                $t = intdiv( $rest, 10 );
                                if ( $t > 0 ) {
                                        $parts[] = $tens[ $t ];
                                }
                                $o = $rest % 10;
                                if ( $o > 0 ) {
                                        $parts[] = $ones[ $o ];
                                }
                        }

                        return implode( ' و ', $parts );
                };

                $whole = (int) floor( $amount );
                $chunks = array();
                $i      = 0;

                while ( $whole > 0 && $i < count( $scales ) ) {
                        $chunk = $whole % 1000;
                        if ( $chunk > 0 ) {
                                /* "هزار" reads better than "یک هزار". */
                                $word = ( 1 === $chunk && 1 === $i ) ? '' : $three( $chunk ) . ' ';
                                $chunks[] = $word . ltrim( $scales[ $i ] );
                        }
                        $whole = intdiv( $whole, 1000 );
                        ++$i;
                }

                return trim( implode( ' و ', array_reverse( $chunks ) ) );
        }

        /**
         * WooCommerce session notices, printed once per request.
         *
         * `wc_print_notices()` both prints AND clears the session queue. The
         * custom cart/checkout templates never called it, which had two
         * consequences: a wrong coupon code showed nothing at all, and every
         * "cart updated" notice stayed in the session forever — piling up until
         * the customer opened a completely unrelated page (the address book),
         * where all twenty of them were finally dumped at once.
         *
         * Printing here is therefore not just cosmetic; it is what drains the
         * queue. The static flag keeps a page that renders several widgets from
         * emitting the same block twice.
         *
         * @return string
         */
        public static function wc_notices() {
                static $printed = false;

                if ( $printed || ! function_exists( 'wc_print_notices' ) || ! function_exists( 'wc_get_notices' ) ) {
                        return '';
                }

                $queue = wc_get_notices();
                if ( empty( $queue ) ) {
                        return '';
                }

                $printed = true;

                ob_start();
                wc_print_notices();
                $html = trim( (string) ob_get_clean() );

                if ( '' === $html ) {
                        return '';
                }

                return '<div class="hsw-notices" role="status" aria-live="polite">' . $html . '</div>';
        }

        /**
         * Material Symbols icon markup, as an inline SVG symbol reference.
         *
         * The glyphs used to come from a 94 KB ligature font: the span's text was
         * the icon name and the font swapped it for a glyph. That meant a font
         * request for a dozen icons and a flash of English words before it
         * landed. The outlines now live in includes/class-hsw-icon-paths.php and
         * the page prints a sprite with only the icons it rendered. Two guards stop
         * that here, at the single choke point every widget goes through:
         *
         *   1. friendly names are mapped to their real Material name,
         *   2. anything HSW_Icon_Set does not know becomes a neutral dot glyph
         *      (and is reported under WP_DEBUG so the list can grow).
         *
         * @param string $name  Glyph name.
         * @param string $class Extra CSS class.
         * @param bool   $fill  Filled variant.
         * @return string
         */
        public static function icon( $name, $class = '', $fill = false ) {
                $name = self::icon_name( $name );

                /*
                 * Filled icons are a separate glyph, not a CSS variation: the
                 * font's FILL axis does not exist in an SVG sprite, so the
                 * filled outline gets its own symbol (`add` vs `add.fill`).
                 */
                if ( ! $fill && false !== strpos( ' ' . $class . ' ', ' is-fill ' ) ) {
                        $fill = true;
                        $class = trim( str_replace( 'is-fill', '', $class ) );
                }

                $key = $fill ? $name . '.fill' : $name;
                self::$icons[ $key ] = true;

                /*
                 * The sprite is printed once in the footer, so the reference is
                 * a same-document fragment: no extra request, no CORS, and it
                 * works in Safari (which still refuses `<use>` across files).
                 */
                return '<svg class="hsw-ic' . ( '' !== $class ? ' ' . esc_attr( $class ) : '' ) . '"'
                        . ' aria-hidden="true" focusable="false">'
                        . '<use href="#hsw-i-' . esc_attr( $key ) . '"></use>'
                        . '</svg>';
        }

        /**
         * Icon keys rendered during this request.
         *
         * Collected by icon() and consumed by print_sprite(), so a page only
         * carries the outlines it actually shows. A cart page with 14 icons
         * costs ~6 KB instead of a 94 KB font request.
         *
         * @var array<string,bool>
         */
        private static $icons = array();

        /**
         * Icons that JavaScript injects at runtime.
         *
         * They never pass through icon(), so they have to be declared: without
         * them the `<use>` points at a symbol that is not in the document and
         * the icon renders as nothing.
         *
         * @var string[]
         */
        private static $runtime_icons = array(
                'check_circle',
                'close',
                'delete',
                'add',
                'remove',
                'shopping_cart',
                'favorite',
                'favorite_border',
                'error',
                'warning',
        );

        /**
         * Print the sprite for the icons used on this page.
         *
         * Hooked to wp_footer. `display:none` (not `visibility`) keeps the
         * symbols out of layout without disabling them — a `display:none` SVG
         * still resolves `<use>` references.
         */
        public static function print_sprite() {
                if ( empty( self::$icons ) ) {
                        return;
                }

                /* Only pay for the runtime icons when a page has icons at all. */
                self::register_runtime_icons();

                $paths = include HSW_DIR . 'includes/class-hsw-icon-paths.php';
                if ( ! is_array( $paths ) ) {
                        return;
                }

                $keys = array_keys( self::$icons );

                /*
                 * A few icons are Iconsax-style stroke art on a 24×24 grid, so
                 * they cannot share the filled Material paths. They carry their
                 * own viewBox and body here; everything else still comes from
                 * the generated path table.
                 */
                $raw = array();
                $raw_file = HSW_DIR . 'includes/class-hsw-icon-raw.php';
                if ( file_exists( $raw_file ) ) {
                        require_once $raw_file;
                        if ( function_exists( 'hsw_icon_raw_map' ) ) {
                                $raw = hsw_icon_raw_map();
                        }
                }

                echo '<svg xmlns="http://www.w3.org/2000/svg" class="hsw-sprite" aria-hidden="true" focusable="false" style="position:absolute;width:0;height:0;overflow:hidden">';
                foreach ( $keys as $key ) {
                        if ( isset( $raw[ $key ] ) ) {
                                echo '<symbol id="hsw-i-' . esc_attr( $key ) . '" viewBox="' . esc_attr( $raw[ $key ]['viewBox'] ) . '"><g style="fill:none">' . $raw[ $key ]['body'] . '</g></symbol>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- local icon markup.
                                continue;
                        }
                        if ( ! isset( $paths[ $key ] ) ) {
                                continue;
                        }
                        echo '<symbol id="hsw-i-' . esc_attr( $key ) . '" viewBox="0 -960 960 960"><path d="' . $paths[ $key ] . '"/></symbol>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- path data is generated, digits and path commands only.
                }
                echo '</svg>';

                /* Tiny helper so scripts can build the same markup. */
                $js = <<<'JS'
window.hswIcon=function(n,c){return '<svg class="hsw-ic'+(c?' '+c:'')+'" aria-hidden="true" focusable="false"><use href="#hsw-i-'+n+'"></use></svg>';};
window.hswSetIcon=function(el,n){if(!el)return;var u=el.querySelector?el.querySelector('use'):null;if(u){u.setAttribute('href','#hsw-i-'+n);}else{el.outerHTML=window.hswIcon(n);}};
window.hswFill=function(el,on){if(!el)return;var u=el.querySelector?el.querySelector('use'):null;if(!u)return;var h=(u.getAttribute('href')||'').replace(/\.fill$/,'');u.setAttribute('href',h+(on?'.fill':''));};
JS;
                echo '<script>' . $js . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static script, no user input.
        }

        /**
         * Register the runtime icons so they are always present in the sprite.
         *
         * Called from the footer hook before printing.
         */
        public static function register_runtime_icons() {
                foreach ( self::$runtime_icons as $name ) {
                        self::$icons[ $name ] = true;
                }
        }

        /**
         * Resolve a friendly/legacy icon name to one the bundled font can draw.
         *
         * Never returns a name that is missing from the subset: an unknown input
         * degrades to `circle` (a neutral dot) instead of leaking English text.
         *
         * @param string $name Requested name.
         * @return string
         */
        public static function icon_name( $name ) {
                $name = strtolower( trim( (string) $name ) );

                /*
                 * Friendly names used across the codebase that Material Symbols
                 * does not ship under that spelling. Mapped here instead of
                 * editing ~120 call sites; every target is present in
                 * tools/_icons.txt.
                 */
                $aliases = array(
                        'discount'       => 'percent',
                        'alert'          => 'warning',
                        'bell'           => 'notifications',
                        'calendar'       => 'calendar_month',
                        'eye'            => 'visibility',
                        'gift'           => 'redeem',
                        'grid'           => 'grid_view',
                        'heart'          => 'favorite',
                        'favorite_border' => 'favorite',
                        'location'       => 'place',
                        'location_on'    => 'place',
                        'phone'          => 'call',
                        'phone_iphone'   => 'devices',
                        'plus'           => 'add',
                        'spark'          => 'auto_awesome',
                        'ticket'         => 'confirmation_number',
                        'trash'          => 'delete',
                        'truck'          => 'local_shipping',
                        'user'           => 'person',
                        'wallet'         => 'account_balance_wallet',
                        'shop'           => 'storefront',
                        'store'          => 'storefront',
                        'settings'       => 'settings',
                        'gear'           => 'settings',
                        'arrow-left'     => 'arrow_back',
                        'arrow-right'    => 'arrow_forward',
                        'arrow-left-alt' => 'arrow_left_alt',
                        'checkmark'      => 'check',
                        'check-circle'   => 'check_circle',
                        'chevron-left'   => 'chevron_left',
                        'chevron-right'  => 'chevron_right',
                        'credit-card'    => 'credit_card',
                        'shopping-bag'   => 'shopping_bag',
                        'cancel'         => 'close',
                        'x'              => 'close',
                        'success'        => 'check_circle',
                        'warning'        => 'warning',
                        'error'          => 'error',
                        'info'           => 'info',
                        'home'           => 'home',
                );
                if ( isset( $aliases[ $name ] ) ) {
                        $name = $aliases[ $name ];
                }

                if ( class_exists( 'HSW_Icon_Set' ) && HSW_Icon_Set::has( $name ) ) {
                        return $name;
                }

                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( sprintf( '[HSW/icon] "%s" is not in the bundled icon subset — rendered as a dot. Add it to tools/_icons.txt and rebuild.', $name ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                }

                return 'circle';
        }

        /**
         * Echo variant of icon() — keeps templates readable.
         *
         * @param string $name  Glyph name.
         * @param string $class Extra class.
         * @param bool   $fill  Filled variant.
         */
        public static function the_icon( $name, $class = '', $fill = false ) {
                echo self::icon( $name, $class, $fill ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr/esc_html inside icon().
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
