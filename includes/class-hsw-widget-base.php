<?php
/**
 * Base widget: shared renderers (faithful to the supplied HTML) + control builders.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Repeater;

abstract class HSW_Widget_Base extends Widget_Base {

	use HSW_Style_Kit;

        public function get_categories() {
                return array( 'hamishop-loop' );
        }

        /**
         * RENDER SAFETY NET — every HSW widget funnels through here.
         * A Throwable inside render() (e.g. a null WC()->cart in an unusual
         * context) used to fatal the whole page. Now: the error is reported
         * to error_log + the dashboard notice, admins see a small red box
         * with the message, and the rest of the page keeps working.
         */
        public function render_content() {
                try {
                        parent::render_content();
                } catch ( \Throwable $e ) {
                        if ( function_exists( 'hsw_report_fatal' ) ) {
                                hsw_report_fatal( $e, 'render:' . $this->get_name() );
                        }
                        if ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ) {
                                printf(
                                        '<div style="padding:12px;border:2px dashed #d63638;border-radius:8px;color:#8a2400;background:#fcf0f1;font:13px/1.8 sans-serif;direction:rtl">%s<br><code>%s</code></div>',
                                        esc_html__( 'خطای رندر ویجت حامی — متن کامل خطا در پیشخوان (نوار اعلان‌ها) ثبت شد. این کادر فقط برای مدیر نمایان است.', 'hamishop-elementor-widgets' ),
                                        esc_html( $e->getMessage() )
                                );
                        }
                }
        }

        /**
         * Extra root class from the common «قالب‌سازگاری» style section.
         * Returns ' hsw-font-inherit' when the widget must use the theme font.
         */
        public function hsw_font_class() {
                $s = $this->effective_settings();
                return ( isset( $s['hsw_font_mode'] ) && 'inherit' === $s['hsw_font_mode'] ) ? ' hsw-font-inherit' : '';
        }

        /* =========================================================================
         *  COMPOSITE SUPPORT
         *
         *  Whole-page composite widgets (HSW_Widget_*_Page) render the existing
         *  single widgets as their sections through HSW_Composite. Each part
         *  receives its own settings array injected here, so the single widgets
         *  stay untouched and keep working exactly as before when used alone.
         * ====================================================================== */

        /** @var array|null Settings injected by a composite parent. */
        private $hsw_injected_settings = null;

        /**
         * Inject a settings array produced by the composite's prefixed controls.
         *
         * @param array $settings Part settings (child-widget key space).
         * @return $this
         */
        public function hsw_inject_settings( array $settings ) {
                $this->hsw_injected_settings = $settings;
                return $this;
        }

        /**
         * Settings the render code must use: the injected composite settings when
         * present, otherwise this element's own saved settings.
         *
         * @return array
         */
        public function effective_settings() {
                return ( null !== $this->hsw_injected_settings ) ? $this->hsw_injected_settings : $this->get_settings_for_display();
        }

        /**
         * True when the widget is rendering as a composite part.
         */
        protected function is_composite_part() {
                return null !== $this->hsw_injected_settings;
        }

        /**
         * Resolve one part's settings from the composite's prefixed controls.
         * Every saved key starting with $prefix is forwarded with the prefix
         * stripped (e.g. 'ttl_' + 'vis' → 'vis').
         *
         * @param string $prefix Part control prefix, including the separator.
         * @return array
         */
        protected function part_settings( $prefix ) {
                $s   = $this->effective_settings();
                $out = array();
                foreach ( $s as $key => $value ) {
                        if ( 0 === strpos( $key, $prefix ) ) {
                                $out[ substr( $key, strlen( $prefix ) ) ] = $value;
                        }
                }
                return $out;
        }

        /**
         * Render one part widget of a composite, with its prefixed settings.
         *
         * @param string $prefix       Part control prefix (with separator).
         * @param string $widget_class Fully-qualified HSW widget class.
         * @return string
         */
        protected function part( $prefix, $widget_class ) {
                return HSW_Composite::part( $widget_class, $this->part_settings( $prefix ) );
        }

        /**
         * Is a composite part enabled? Parts default to enabled.
         *
         * @param string $prefix Part control prefix (with separator).
         * @return bool
         */
        protected function part_on( $prefix ) {
                $s = $this->effective_settings();
                return ! isset( $s[ $prefix . 'on' ] ) || 'yes' === $s[ $prefix . 'on' ];
        }

        /**
         * Echo one enabled composite part, optionally inside a layout wrapper.
         *
         * @param string $prefix       Part control prefix (with separator).
         * @param string $widget_class Fully-qualified HSW widget class.
         * @param string $wrap_class   Optional wrapper CSS class.
         */
        protected function print_part( $prefix, $widget_class, $wrap_class = '' ) {
                $html = $this->part( $prefix, $widget_class );
                if ( '' === trim( $html ) ) {
                        return;
                }
                if ( '' === $wrap_class ) {
                        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already-escaped widget markup.
                        return;
                }
                echo '<div class="' . esc_attr( $wrap_class ) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        /**
         * Open a composite section: enable switcher + mirrored child controls.
         *
         * @param string $prefix       Part control prefix (with separator).
         * @param string $label        Section label.
         * @param string $widget_class Fully-qualified part widget class.
         * @param array  $ids          Child control ids to expose.
         */
        protected function part_section( $prefix, $label, $widget_class, $ids = array() ) {
                $this->start_controls_section(
                        'sec_' . $prefix,
                        array(
                                'label' => $label,
                                'tab'   => Controls_Manager::TAB_CONTENT,
                        )
                );

                $this->add_control(
                        $prefix . 'on',
                        array(
                                'label'   => __( 'نمایش این بخش', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                                'separator' => 'after',
                        )
                );

                $this->part_controls( $prefix, $widget_class, $ids );

                $this->end_controls_section();
        }

        /**
         * Open a composite STYLE section: mirrors the child widget's style
         * controls (colors, sliders, headings…) so the whole page can be
         * recolored from the composite's Style tab, exactly like editing the
         * single widget. Controls are identified by their section id (the
         * child's `sec_style` by default).
         *
         * @param string $prefix     Part control prefix (with separator).
         * @param string $label      Section label.
         * @param string $widget_class Fully-qualified part widget class.
         * @param string $section_id Child style section id.
         */
        /**
         * Mirror a child widget's STYLE section without the type whitelist.
         *
         * `part_controls()` drops anything that is not in a short allow-list and
         * silently produced an empty section (measured: 31 style controls in the
         * child, 0 in the composite). This variant copies the registry entry
         * verbatim — selectors, responsive flags, size units, conditions — so a
         * colour/slider/dimensions control behaves exactly like the child's own.
         *
         * @param string $prefix      Setting prefix for this part.
         * @param string $label       Section label.
         * @param string $widget_class Child widget class.
         * @param string $section_id  Section to copy.
         * @return void
         */
        protected function style_section_mirror( $prefix, $label, $widget_class, $section_id = 'sec_style' ) {
                /* Thin alias: part_style_section owns the responsive/condition logic. */
                $this->part_style_section( $prefix, $label, $widget_class, $section_id );
        }

        protected function part_style_section( $prefix, $label, $widget_class, $section_id = 'sec_style' ) {
                $this->start_controls_section(
                        'hsw_style_' . $prefix . sanitize_key( $section_id ),
                        array(
                                'label' => $label,
                                'tab'   => Controls_Manager::TAB_STYLE,
                        )
                );

                $this->part_controls( $prefix, $widget_class, array(), $section_id );

                $this->end_controls_section();
        }

        /**
         * Mirror chosen content controls of a part widget into this composite
         * (prefixed ids, exact types + defaults + conditions). Responsive
         * controls (e.g. «تعداد ستون») keep their per-device switch in the
         * composite, whatever Elementor's responsive-control mode is.
         *
         * When $section_id is given, every control registered inside that
         * child section is mirrored instead ($ids is then ignored).
         *
         * @param string $prefix       Part control prefix (with separator).
         * @param string $widget_class Fully-qualified part widget class.
         * @param array  $ids          Child control ids to expose.
         * @param string $section_id   Child section id whose controls to mirror.
         */
        protected function part_controls( $prefix, $widget_class, $ids = array(), $section_id = '' ) {
                $controls = HSW_Composite::controls( $widget_class );

                if ( '' !== $section_id ) {
                        $ids = array();
                        foreach ( $controls as $control_id => $control_data ) {
                                if ( is_array( $control_data ) && isset( $control_data['section'] ) && $section_id === $control_data['section'] ) {
                                        if ( preg_match( '/_(tablet|mobile)$/', $control_id ) ) {
                                                continue; // Auto-generated variants of a responsive control.
                                        }
                                        $ids[] = $control_id;
                                }
                        }
                }
                $allowed  = array(
                        Controls_Manager::TEXT,
                        Controls_Manager::TEXTAREA,
                        Controls_Manager::SELECT,
                        Controls_Manager::SELECT2,
                        Controls_Manager::SWITCHER,
                        Controls_Manager::COLOR,
                        Controls_Manager::NUMBER,
                        Controls_Manager::SLIDER,
                        Controls_Manager::URL,
                        Controls_Manager::REPEATER,
                        Controls_Manager::HEADING,
                        Controls_Manager::DIVIDER,
                        Controls_Manager::WYSIWYG,
                        Controls_Manager::DIMENSIONS,
                        Controls_Manager::BOX_SHADOW,
                );
                $pass = array(
                        'label', 'type', 'default', 'options', 'condition', 'description',
                        'placeholder', 'label_block', 'label_on', 'label_off', 'separator',
                        'size_units', 'range', 'allow_empty', 'multiple', 'rows', 'min',
                        'max', 'step', 'fields', 'return_value', 'classes', 'selectors',
                        'tablet_default', 'mobile_default',
                );

                foreach ( $ids as $id ) {
                        if ( empty( $controls[ $id ] ) || ! is_array( $controls[ $id ] ) ) {
                                continue;
                        }
                        $source = $controls[ $id ];
                        $type   = isset( $source['type'] ) ? $source['type'] : '';
                        if ( ! in_array( $type, $allowed, true ) ) {
                                continue;
                        }

                        $args = array();
                        foreach ( $pass as $key ) {
                                if ( array_key_exists( $key, $source ) ) {
                                        $args[ $key ] = $source[ $key ];
                                }
                        }
                        if ( ! isset( $args['default'] ) && isset( $source['desktop_default'] ) ) {
                                $args['default'] = $source['desktop_default'];
                        }

                        /* Responsive mirroring: keep the device switch
                           (دسکتاپ / تبلت / موبایل) of the source control alive in the
                           composite, for every Elementor responsive-control mode. */
                        $devices       = array();
                        $is_responsive = false;

                        if ( ! empty( $source['is_responsive'] ) || ! empty( $source['responsive'] ) ) {
                                $is_responsive = true;
                                if ( ! empty( $source['responsive']['devices'] ) && is_array( $source['responsive']['devices'] ) ) {
                                        $devices = array_values( array_intersect( array( 'desktop', 'tablet', 'mobile' ), $source['responsive']['devices'] ) );
                                }
                        }

                        /* Elementor's CSS-file mode registers one control per device
                           (cols / cols_tablet / cols_mobile): borrow their defaults
                           even when the desktop control also carries `responsive`. */
                        foreach ( array( 'tablet', 'mobile' ) as $device ) {
                                $sibling = isset( $controls[ $id . '_' . $device ] ) ? $controls[ $id . '_' . $device ] : null;
                                if ( ! is_array( $sibling ) ) {
                                        continue;
                                }
                                $is_responsive = true;
                                if ( ! in_array( $device, $devices, true ) ) {
                                        $devices[] = $device;
                                }
                                if ( ! isset( $args[ $device . '_default' ] ) && array_key_exists( 'default', $sibling ) ) {
                                        $args[ $device . '_default' ] = $sibling['default'];
                                }
                        }
                        if ( $is_responsive && $devices && ! in_array( 'desktop', $devices, true ) ) {
                                array_unshift( $devices, 'desktop' );
                        }

                        if ( ! empty( $args['condition'] ) && is_array( $args['condition'] ) ) {
                                $condition = array();
                                foreach ( $args['condition'] as $ck => $cv ) {
                                        $base = preg_replace( '/(!|\[.*\])$/', '', (string) $ck );
                                        $base = preg_replace( '/_(tablet|mobile|widescreen|mobile_extra|tablet_extra|laptop)$/', '', $base );
                                        if ( in_array( $base, $ids, true ) ) {
                                                $condition[ $prefix . $ck ] = $cv;
                                        }
                                }
                                if ( $condition ) {
                                        $args['condition'] = $condition;
                                } else {
                                        unset( $args['condition'] );
                                }
                        }

                        if ( $is_responsive ) {
                                if ( count( $devices ) > 1 ) {
                                        $args['devices'] = $devices;
                                }
                                $this->add_responsive_control( $prefix . $id, $args );
                                continue;
                        }

                        $this->add_control( $prefix . $id, $args );
                }
        }

        /**
         * No assets by default: a widget must declare what it needs, otherwise the
         * loop bundle (CSS + JS) used to load on every page of the site.
         *
         * @return array
         */
        public function get_style_depends() {
                return array();
        }

        /**
         * @return array
         */
        public function get_script_depends() {
                return array();
        }

        /* =========================================================================
         *  RENDERERS (server-side cards; shared with the REST tab fragment)
         * ====================================================================== */

        /**
         * Render a list of cards for any of the three widgets.
         *
         * The rendered HTML is cached alongside the query cache: a warm request
         * therefore renders cards without loading a single product object.
         *
         * @param string       $type     deal|best|popular.
         * @param WC_Product[] $products Products.
         * @param array        $args     Options subset (badge texts, flags...).
         * @param int          $offset   Rank offset (best sellers).
         */
        public static function cards_html( $type, $products, $args = array(), $offset = 0 ) {
                $args = wp_parse_args( $args, self::default_args() );

                $products = array_values(
                        array_filter(
                                (array) $products,
                                static function ( $product ) {
                                        return $product instanceof WC_Product;
                                }
                        )
                );
                if ( empty( $products ) ) {
                        return '';
                }

                $ids     = array_map(
                        static function ( $product ) {
                                return (int) $product->get_id();
                        },
                        $products
                );
                $cache   = ! empty( $args['html_cache'] );
                $ttl     = max( 0, (int) ( $args['cache_ttl'] ?? 300 ) );
                $key     = '';
                $payload = '';

                if ( $cache && $ttl > 0 ) {
                        $key = 'hsw_lhc_' . md5(
                                $type . '|' . implode( ',', $ids ) . '|' . $offset . '|' .
                                wp_json_encode( $args ) . '|' . HSW_Products::version()
                        );
                        $payload = wp_cache_get( $key, HSW_Products::CACHE_GROUP );
                        if ( ! is_string( $payload ) ) {
                                $payload = get_transient( $key );
                        }
                        if ( is_string( $payload ) && '' !== $payload ) {
                                return $payload;
                        }
                }

                $html = '';
                $i    = (int) $offset;
                foreach ( $products as $product ) {
                        $i++;
                        $is_first = ( 1 === $i - (int) $offset ) && ! empty( $args['first_priority'] );
                        $html    .= self::card_html( $type, $product, $args, $i, $is_first );
                }

                if ( $key && '' !== $html ) {
                        $ttl = self::cards_ttl( $ttl, $args );
                        if ( $ttl > 0 ) {
                                wp_cache_set( $key, $html, HSW_Products::CACHE_GROUP, $ttl );
                                set_transient( $key, $html, $ttl );
                        }
                }

                return $html;
        }

        /**
         * Cap the HTML cache at the nearest sale end.
         *
         * @param int   $ttl  Requested TTL.
         * @param array $args Renderer args.
         * @return int
         */
        private static function cards_ttl( $ttl, $args ) {
                $ttl = absint( $ttl );
                if ( $ttl <= 0 ) {
                        return 0;
                }
                $until = (int) ( $args['cache_until'] ?? 0 );
                if ( $until > 0 ) {
                        $ttl = min( $ttl, max( 1, $until - time() ) );
                }
                return $ttl;
        }

        /**
         * Media block (image + badges + optional OOS overlay).
         *
         * @param WC_Product $product  Product.
         * @param array      $args     Renderer args.
         * @param string     $type     deal|best|popular.
         * @param bool       $priority First above-the-fold image.
         * @param string     $rank     Pre-built rank badge markup.
         * @return string
         */
        protected static function media_html( $product, $args, $type, $priority = false, $rank = '' ) {
                $oos   = ! $product->is_in_stock();
                $badge = '';

                if ( ! $oos && ! HSW_Helpers::is_call_for_price( $product ) && ! empty( $args['show_badge'] ) ) {
                        $percent = HSW_Helpers::discount_percent( $product );
                        $mod     = '';
                        $text    = '';
                        $label   = '';

                        if ( 'deal' === $type && $percent > 0 ) {
                                $mod   = 'sale';
                                $text  = HSW_Helpers::fa_num( $percent ) . '٪';
                                $label = $text . ' ' . $args['txt_sale'];
                        } elseif ( 'popular' === $type ) {
                                $featured = $product->is_featured();
                                $ship_min = (float) ( $args['free_ship_min'] ?? 0 );
                                if ( $featured ) {
                                        $mod   = 'feat';
                                        $text  = (string) $args['txt_feat'];
                                        $label = $text;
                                } elseif ( $percent > 0 ) {
                                        $mod   = 'sale';
                                        $text  = HSW_Helpers::fa_num( $percent ) . '٪';
                                        $label = $text . ' ' . $args['txt_sale'];
                                } elseif ( $ship_min > 0 && (float) $product->get_price() >= $ship_min ) {
                                        $mod   = 'ship';
                                        $text  = (string) $args['txt_ship'];
                                        $label = $text;
                                }
                        }

                        if ( '' !== $mod ) {
                                $icon  = 'ship' === $mod ? HSW_Helpers::icon( 'local_shipping' ) : '';
                                $badge = '<span class="hsw-loop__badge hsw-loop__badge--' . esc_attr( $mod ) . '" aria-label="' . esc_attr( $label ) . '">' .
                                        $icon . '<span>' . esc_html( $text ) . '</span></span>';
                        }
                }

                $overlay = '';
                if ( $oos ) {
                        $overlay = '<span class="hsw-loop__badge hsw-loop__badge--oos">' . esc_html( $args['txt_oos'] ) . '</span>' .
                                '<span class="hsw-loop__oos" aria-hidden="true"></span>';
                }

                $image = HSW_Helpers::image(
                        $product,
                        'hsw_card',
                        array(
                                'priority' => $priority,
                                /* One preload per page, for the first visible card. */
                                'preload'  => $priority && ! empty( $args['first_priority'] ),
                                'sizes'    => self::sizes_attr( $args ),
                        )
                );

                return '<div class="hsw-loop__media">' .
                        '<a class="hsw-loop__media-link" href="' . esc_url( $product->get_permalink() ) . '" tabindex="-1" aria-hidden="true">' . $image . '</a>' .
                        $badge . $rank . $overlay .
                        '</div>';
        }

        /**
         * `sizes` attribute that matches how many cards the rail shows.
         *
         * A fixed `45vw / 288px` pair made mobile download a desktop-sized
         * candidate (and vice versa) whenever the widget's «cards per view»
         * differed from the assumption. The numbers come from the same settings
         * that write `--hsw-loop-cards`, so the browser picks a candidate of the
         * right width at every breakpoint.
         *
         * @param array $args Renderer args.
         * @return string
         */
        protected static function sizes_attr( $args ) {
                $cards = static function ( $value, $fallback ) {
                        $value = (float) $value;
                        return $value > 0 ? min( 8, max( 1, $value ) ) : (float) $fallback;
                };

                $desktop = $cards( $args['cards'] ?? 0, 0 );
                $tablet  = $cards( $args['cards_tablet'] ?? 0, 0 );
                $mobile  = $cards( $args['cards_mobile'] ?? 0, 0 );

                /* 0 means "fixed card width" — fall back to the default order. */
                if ( $desktop <= 0 ) {
                        $desktop = 4;
                }

                $parts = array();

                if ( $mobile > 0 ) {
                        /* Full-bleed rail: the list is the viewport minus the
                           shell padding, so 100vw is the honest width here. */
                        $parts[] = sprintf( '(max-width: 767px) calc(100vw / %s)', self::trim_float( $mobile ) );
                } else {
                        $parts[] = '(max-width: 767px) 45vw';
                }

                if ( $tablet > 0 ) {
                        $parts[] = sprintf( '(min-width: 768px) and (max-width: 1024px) calc(100vw / %s)', self::trim_float( $tablet ) );
                }

                $parts[] = sprintf( 'calc(100vw / %s)', self::trim_float( $desktop ) );

                return implode( ', ', $parts );
        }

        /**
         * Float without a trailing `.0` for CSS `calc()`.
         *
         * @param float $value Number.
         * @return string
         */
        private static function trim_float( $value ) {
                $value = (float) $value;
                return (string) ( floor( $value ) === $value ? (int) $value : $value );
        }

        /**
         * Category / brand line.
         *
         * @param WC_Product $product Product.
         * @param array      $args    Renderer args.
         * @return string
         */
        protected static function cat_html( $product, $args ) {
                if ( empty( $args['show_brand'] ) ) {
                        return '';
                }
                $label = HSW_Helpers::brand_label( $product, $args['brand_attr'] );
                if ( '' === $label ) {
                        return '';
                }
                return '<span class="hsw-loop__cat">' . esc_html( $label ) . '</span>';
        }

        /**
         * Rating line.
         *
         * @param WC_Product $product Product.
         * @param array      $args    Renderer args.
         * @return string
         */
        protected static function rating_html( $product, $args ) {
                if ( empty( $args['show_rating'] ) || $product->get_review_count() <= 0 ) {
                        return '';
                }

                $avg   = HSW_Helpers::fa_num( number_format( (float) $product->get_average_rating(), 1 ) );
                $count = '';
                if ( ! empty( $args['show_rating_count'] ) ) {
                        /* translators: %s: review count */
                        $count = '<span class="hsw-loop__rating-count">(' . sprintf( __( '%s نظر', 'hamishop-elementor-widgets' ), HSW_Helpers::fa_num( $product->get_review_count() ) ) . ')</span>';
                }

                /* translators: %s: rating */
                $label = sprintf( __( 'امتیاز: %s از ۵', 'hamishop-elementor-widgets' ), $avg );
                return '<span class="hsw-loop__rating" aria-label="' . esc_attr( $label ) . '">' .
                        HSW_Helpers::icon( 'star' ) . '<span class="hsw-loop__rating-score">' . esc_html( $avg ) . '</span>' . $count .
                        '</span>';
        }

        /**
         * Pricing block.
         *
         * @param WC_Product $product Product.
         * @param array      $args    Renderer args.
         * @param bool       $oos     Out of stock.
         * @return string
         */
        protected static function price_html( $product, $args, $oos ) {
                $label = '';
                if ( ! empty( $args['price_label'] ) ) {
                        $label = '<span class="hsw-loop__price-label">' . esc_html( $args['price_label'] ) . '</span>';
                }

                if ( HSW_Helpers::is_call_for_price( $product ) ) {
                        return '<div class="hsw-loop__prices hsw-loop__prices--cfp">' . $label .
                                HSW_Helpers::cfp_inline_html( $product, false, 'hsw-loop__cfp' ) . '</div>';
                }

                $regular = (float) $product->get_regular_price();
                $sale    = (float) $product->get_price();

                if ( $product->is_type( 'variable' ) ) {
                        $regular = (float) $product->get_variation_regular_price( 'max' );
                        $sale    = (float) $product->get_variation_price( 'min' );
                }

                $on_sale = $sale > 0 && $regular > 0 && $sale < $regular;
                $old     = '';

                if ( $on_sale && ! empty( $args['show_old'] ) ) {
                        $old = '<del class="hsw-loop__old-price">' . esc_html( HSW_Helpers::price_num( $regular ) ) . '</del>';
                }

                $amount = $sale > 0 ? $sale : $regular;
                if ( $amount <= 0 ) {
                        return '<div class="hsw-loop__prices">' . $label . '</div>';
                }

                return '<div class="hsw-loop__prices">' . $label . $old .
                        '<div class="hsw-loop__price">' . esc_html( HSW_Helpers::price_num( $amount ) ) .
                        ' <span class="hsw-loop__currency">' . esc_html( $args['currency'] ) . '</span></div></div>';
        }

        /**
         * Add-to-cart / notify / call-for-price action.
         *
         * @param WC_Product $product Product.
         * @param array      $args    Renderer args.
         * @param string     $type    deal|best|popular.
         * @return string
         */
        protected static function actions_html( $product, $args, $type ) {
                if ( HSW_Helpers::is_call_for_price( $product ) ) {
                        $label = 'text' === ( $args['btn_variant'] ?? 'text' );
                        return '<div class="hsw-loop__actions">' .
                                HSW_Helpers::cfp_action_html( $product, 'hsw-loop__btn hsw-loop__btn--cfp', $label ) .
                                '</div>';
                }

                if ( ! $product->is_in_stock() ) {
                        return '<div class="hsw-loop__actions">' .
                                '<a class="hsw-loop__btn hsw-loop__btn--notify" href="' . esc_url( $product->get_permalink() ) . '" aria-label="' . esc_attr( $args['txt_notify'] ) . '">' .
                                HSW_Helpers::icon( 'notifications' ) . '<span class="hsw-loop__btn-label">' . esc_html( $args['txt_notify'] ) . '</span></a>' .
                                '</div>';
                }

                $variant = (string) ( $args['btn_variant'] ?? 'text' );
                if ( ! in_array( $variant, array( 'icon', 'text', 'plus' ), true ) ) {
                        $variant = 'text';
                }

                $purchasable = $product->is_purchasable();
                $simple      = $purchasable && $product->is_type( 'simple' );
                $classes     = 'hsw-loop__btn hsw-loop__btn--' . $variant;
                $label       = 'text' === $variant ? (string) $args['txt_buy'] : '';
                $aria        = '' !== $label ? $label : $args['txt_add'];

                if ( $simple ) {
                        $open = '<button type="button" class="' . esc_attr( $classes ) . '" data-product_id="' . esc_attr( $product->get_id() ) . '" aria-label="' . esc_attr( $aria ) . '" title="' . esc_attr( $aria ) . '">';
                        $close = '</button>';
                } else {
                        $open = '<a class="' . esc_attr( $classes ) . '" href="' . esc_url( $product->get_permalink() ) . '" aria-label="' . esc_attr( $aria ) . '" title="' . esc_attr( $aria ) . '">';
                        $close = '</a>';
                }

                $icon = 'plus' === $variant ? 'add' : (string) $args['ico_add'];
                $body = HSW_Helpers::icon( $icon );
                if ( 'text' === $variant && '' !== $label ) {
                        $body .= '<span class="hsw-loop__btn-label">' . esc_html( $label ) . '</span>';
                }

                return '<div class="hsw-loop__actions">' . $open . $body . $close . '</div>';
        }

        /**
         * Card — shared BEM markup for all three widgets.
         *
         * Order is fixed: image → category → title → rating → stock → prices →
         * action. Variants only add a modifier class; the structure is shared.
         *
         * @param string     $type     deal|best|popular.
         * @param WC_Product $product  Product.
         * @param array      $args     Renderer args.
         * @param int        $i        Output index (1-based).
         * @param bool       $priority First above-the-fold image.
         * @return string
         */
        protected static function card_html( $type, $product, $args, $i, $priority = false ) {
                $oos     = ! $product->is_in_stock();
                $classes = 'hsw-loop__card hsw-loop__card--' . sanitize_html_class( $type );
                if ( $oos ) {
                        $classes .= ' hsw-loop__card--oos';
                }
                if ( $i > 2 ) {
                        $classes .= ' hsw-loop__card--lazy';
                }

                $rank = '';
                if ( 'best' === $type && ! empty( $args['show_rank'] ) ) {
                        $mod  = $i <= 3 ? 'rank-' . $i : 'rank-n';
                        $rank = '<span class="hsw-loop__badge hsw-loop__badge--' . esc_attr( $mod ) . '" aria-label="' .
                                esc_attr( sprintf( __( 'رتبه %s', 'hamishop-elementor-widgets' ), HSW_Helpers::fa_num( $i ) ) ) . '">' .
                                esc_html( HSW_Helpers::fa_num( $i ) ) . '</span>';
                }

                $stock_html = '';
                if ( ! $oos && ! empty( $args['show_stock'] ) ) {
                        $stock = HSW_Helpers::stock_progress(
                                $product,
                                (string) ( $args['stock_mode'] ?? 'low' ),
                                (int) ( $args['stock_low'] ?? 3 )
                        );
                        if ( $stock ) {
                                $stock_html = '<div class="hsw-loop__stock">' .
                                        '<div class="hsw-loop__stock-meta"><span>' . esc_html( $stock['sold'] ) . '</span>' .
                                        '<span class="hsw-loop__stock-left' . ( $stock['critical'] ? '' : ' is-ok' ) . '">' . esc_html( $stock['left'] ) . '</span></div>' .
                                        '<div class="hsw-loop__stock-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . (int) $stock['percent'] . '" aria-label="' . esc_attr( $stock['left'] ) . '" style="--hsw-loop-stock-pct:' . (int) $stock['percent'] . '%">' .
                                        '<span class="hsw-loop__stock-fill"></span></div></div>';
                        }
                }

                return '<article class="' . esc_attr( $classes ) . '">' .
                        self::media_html( $product, $args, $type, $priority, $rank ) .
                        '<div class="hsw-loop__body">' .
                                self::cat_html( $product, $args ) .
                                '<h3 class="hsw-loop__name"><a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a></h3>' .
                                self::rating_html( $product, $args ) .
                                $stock_html .
                        '</div>' .
                        '<div class="hsw-loop__foot">' .
                                self::price_html( $product, $args, $oos ) .
                                self::actions_html( $product, $args, $type ) .
                        '</div></article>';
        }

        /**
         * Default renderer args (mirror the mock).
         */
        public static function default_args() {
                return array(
                        'show_brand'        => 'yes',
                        'brand_attr'        => 'pa_brand',
                        'show_rating'       => 'yes',
                        'show_rating_count' => 'yes',
                        'show_old'          => 'yes',
                        'show_stock'        => 'yes',
                        'show_rank'         => 'yes',
                        'show_badge'        => 'yes',
                        'stock_mode'        => 'low',
                        'stock_low'         => 3,
			'cards'             => 4,
			'cards_tablet'      => 3,
			'cards_mobile'      => 2.2,
			'timer_end_action'  => 'hide_timer',
			'timer_end_text'    => __( 'این پیشنهاد به پایان رسید', 'hamishop-elementor-widgets' ),
                        'btn_variant'       => 'text',
                        'price_label'       => '',
                        'currency'          => __( 'تومان', 'hamishop-elementor-widgets' ),
                        'txt_sale'          => __( 'تخفیف', 'hamishop-elementor-widgets' ),
                        'txt_feat'          => __( 'ویژه', 'hamishop-elementor-widgets' ),
                        'txt_ship'          => __( 'ارسال رایگان', 'hamishop-elementor-widgets' ),
                        'txt_oos'           => __( 'اتمام موجودی', 'hamishop-elementor-widgets' ),
                        'txt_notify'        => __( 'خبرم کن', 'hamishop-elementor-widgets' ),
                        'txt_add'           => __( 'افزودن به سبد خرید', 'hamishop-elementor-widgets' ),
                        'txt_buy'           => __( 'خرید', 'hamishop-elementor-widgets' ),
                        'ico_add'           => 'shopping_cart',
                        'free_ship_min'     => 0,
                        'html_cache'        => true,
                        'cache_ttl'         => 300,
                        'cache_until'       => 0,
                        'first_priority'    => true,
                );
        }

        /**
         * Allowed product sources (also used by the REST whitelist).
         *
         * @return string[]
         */
        public static function loop_sources() {
                return array( 'sale', 'best_selling', 'rating', 'popular', 'newest', 'discount', 'featured', 'category', 'manual' );
        }

        /**
         * Preset class for the selected palette.
         *
         * @param array $s Settings.
         * @return string
         */
        protected function preset_class( $s ) {
                $preset = (string) ( $s['loop_preset'] ?? 'auto' );
                if ( ! in_array( $preset, array( 'red', 'blue', 'green' ), true ) ) {
                        return '';
                }
                return ' hsw-loop--preset-' . $preset;
        }

        /**
         * `data-hsw-theme` for the widget root.
         *
         * The dark palette is the same token set with darker values, so it is
         * opt-in per widget (`dark`) or follows the visitor's system
         * preference (`auto`); the default stays light and leaves the store's
         * look untouched.
         *
         * @param array $s Settings.
         * @return string
         */
        protected function theme_attr( $s ) {
                $mode = (string) ( $s['loop_color_mode'] ?? 'light' );
                if ( ! in_array( $mode, array( 'light', 'dark', 'auto' ), true ) ) {
                        $mode = 'light';
                }
                return ' data-hsw-theme="' . esc_attr( $mode ) . '"';
        }

        /**
         * Rail ID shared by tabs and the rail (aria-controls).
         *
         * @return string
         */
        protected function rail_id() {
                $id = (string) $this->get_id();
                return 'hsw-rail-' . ( '' !== $id ? $id : wp_unique_id() );
        }

        /**
         * Countdown markup.
         *
         * The initial values are calculated server-side, so the widget never
         * paints a dead `00:00:00` while JavaScript is still loading. The
         * `role="timer"` + `aria-live="off"` pair announces the control once
         * without reading every second.
         *
         * When the end has already passed the behavior follows the widget
         * setting: hide the timer, hide the whole block (JS), or render the
         * editable «ended» note in the timer's place — never an empty 00:00:00.
         *
         * @param int    $end   Unix timestamp.
         * @param string $label Label before the digits.
         * @param array  $args  timer_end_action + timer_end_text.
         * @return string
         */
        protected function timer_html( $end, $label = '', $args = array() ) {
                $end    = (int) $end;
                $action = (string) ( $args['timer_end_action'] ?? 'hide_timer' );
                $text   = trim( (string) ( $args['timer_end_text'] ?? '' ) );

                if ( ! in_array( $action, array( 'hide_timer', 'hide_block', 'ended_note' ), true ) ) {
                        $action = 'hide_timer';
                }

                $ended = static function () use ( $text ) {
                        if ( '' === $text ) {
                                return '';
                        }
                        return '<div class="hsw-loop__timer hsw-loop__timer--ended" role="status">' .
                                HSW_Helpers::icon( 'timer' ) .
                                '<span class="hsw-loop__timer-label">' . esc_html( $text ) . '</span>' .
                                '</div>';
                };

                if ( $end <= time() ) {
                        return 'ended_note' === $action ? $ended() : '';
                }

                $diff = max( 0, $end - time() );
                $h    = (int) floor( $diff / 3600 );
                $m    = (int) floor( ( $diff % 3600 ) / 60 );
                $s    = (int) ( $diff % 60 );

                $pad = static function ( $value ) {
                        return str_pad( (string) $value, 2, '0', STR_PAD_LEFT );
                };

                $label = '' !== (string) $label ? '<span class="hsw-loop__timer-label">' . esc_html( $label ) . '</span>' : '';

                return '<div class="hsw-loop__timer" role="timer" aria-live="off" aria-label="' . esc_attr__( 'زمان باقی‌مانده', 'hamishop-elementor-widgets' ) . '" data-hsw-end="' . esc_attr( $end ) . '" data-hsw-end-action="' . esc_attr( $action ) . '" data-hsw-end-text="' . esc_attr( $text ) . '">' .
                        HSW_Helpers::icon( 'timer' ) .
                        $label .
                        '<span class="hsw-loop__timer-part" data-hsw-t="h">' . esc_html( HSW_Helpers::fa_num( $pad( $h ) ) ) . '</span>' .
                        '<span class="hsw-loop__timer-sep" aria-hidden="true">:</span>' .
                        '<span class="hsw-loop__timer-part" data-hsw-t="m">' . esc_html( HSW_Helpers::fa_num( $pad( $m ) ) ) . '</span>' .
                        '<span class="hsw-loop__timer-sep" aria-hidden="true">:</span>' .
                        '<span class="hsw-loop__timer-part" data-hsw-t="s">' . esc_html( HSW_Helpers::fa_num( $pad( $s ) ) ) . '</span>' .
                        '</div>';
        }

        /**
         * Previous/next buttons. Both are shared by the header and the
         * header-less layout.
         *
         * @return string
         */
        protected function arrows_html() {
                return '<div class="hsw-loop__arrows" role="group" aria-label="' . esc_attr__( 'پیمایش محصولات', 'hamishop-elementor-widgets' ) . '">' .
                        '<button type="button" class="hsw-loop__arrow" data-hsw-dir="-1" aria-label="' . esc_attr__( 'محصولات قبلی', 'hamishop-elementor-widgets' ) . '">' . HSW_Helpers::icon( 'chevron_right' ) . '</button>' .
                        '<button type="button" class="hsw-loop__arrow" data-hsw-dir="1" aria-label="' . esc_attr__( 'محصولات بعدی', 'hamishop-elementor-widgets' ) . '">' . HSW_Helpers::icon( 'chevron_left' ) . '</button>' .
                        '</div>';
        }

        /**
         * Shared header markup.
         *
         * @param array  $s     Settings.
         * @param int    $end   Countdown end (0 hides the timer).
         * @param string $extra Extra markup for the side column (tabs/note).
         * @return string
         */
        protected function header_html( $s, $end = 0, $extra = '' ) {
                if ( 'yes' !== ( $s['show_header'] ?? 'yes' ) ) {
                        return '';
                }

                $tag = (string) ( $s['hdr_tag'] ?? 'h2' );
                if ( ! in_array( $tag, array( 'h2', 'h3', 'div' ), true ) ) {
                        $tag = 'h2';
                }

                $title = (string) ( $s['hdr_title'] ?? '' );
                $badge = (string) ( $s['hdr_badge'] ?? '' );
                $sub   = (string) ( $s['hdr_sub'] ?? '' );
                $icon  = (string) ( $s['hdr_icon'] ?? 'local_fire_department' );

                $side = '';

                if ( '' !== $extra ) {
                        $side .= $extra;
                }

                $side .= $this->timer_html(
                        (int) $end,
                        (string) ( $s['cd_label'] ?? '' ),
                        array(
                                'timer_end_action' => (string) ( $s['cd_end_action'] ?? 'hide_timer' ),
                                'timer_end_text'   => (string) ( $s['cd_end_text'] ?? '' ),
                        )
                );
                $side .= $this->header_link_html( $s );
                $side .= $this->arrows_html();

                return '<header class="hsw-loop__header">' .
                        '<div class="hsw-loop__head-main">' .
                                ( '' !== $icon ? '<span class="hsw-loop__icon" aria-hidden="true">' . HSW_Helpers::icon( $icon ) . '</span>' : '' ) .
                                '<div class="hsw-loop__titles">' .
                                        '<div class="hsw-loop__title-row">' .
                                                ( '' !== $title ? '<' . $tag . ' class="hsw-loop__title">' . esc_html( $title ) . '</' . $tag . '>' : '' ) .
                                                ( '' !== $badge ? '<span class="hsw-loop__badge-note">' . esc_html( $badge ) . '</span>' : '' ) .
                                        '</div>' .
                                        ( '' !== $sub ? '<p class="hsw-loop__subtitle">' . esc_html( $sub ) . '</p>' : '' ) .
                                '</div>' .
                        '</div>' .
                        '<div class="hsw-loop__head-side">' . $side . '</div>' .
                        '</header>';
        }

        /**
         * Header link markup (desktop + mobile labels).
         *
         * @param array $s Settings.
         * @return string
         */
        protected function header_link_html( $s ) {
                $desktop = trim( (string) ( $s['hdr_link_d'] ?? '' ) );
                $mobile  = trim( (string) ( $s['hdr_link_m'] ?? '' ) );

                if ( '' === $desktop && '' === $mobile ) {
                        return '';
                }

                $url = (string) ( $s['hdr_link_url']['url'] ?? '' );
                if ( '' === $url ) {
                        $url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
                }

                $desktop = '' !== $desktop ? $desktop : $mobile;
                $mobile  = '' !== $mobile ? $mobile : $desktop;

                return '<a class="hsw-loop__link" href="' . esc_url( $url ) . '">' .
                        '<span class="hsw-loop__link-text hsw-loop__link-text--d">' . esc_html( $desktop ) . '</span>' .
                        '<span class="hsw-loop__link-text hsw-loop__link-text--m">' . esc_html( $mobile ) . '</span>' .
                        HSW_Helpers::icon( 'chevron_left' ) .
                        '</a>';
        }

        /**
         * Tab list for the popular widget.
         *
         * Each tab carries its own sanitized REST URL, so the browser never
         * builds a query string from values that came out of the DOM.
         *
         * @param array $tabs    Repeater rows.
         * @param array $context endpoint, post_id, widget_id, sig, per_page, term_id.
         * @return string
         */
        protected function tabs_html( $tabs, $context = array() ) {
                if ( empty( $tabs ) || ! is_array( $tabs ) ) {
                        return '';
                }

                $rail    = $this->rail_id();
                $sources = self::loop_sources();
                $context = wp_parse_args(
                        $context,
                        array(
                                'endpoint'  => rest_url( 'hsw/v1/loop' ),
                                'post_id'   => 0,
                                'widget_id' => '',
                                'sig'       => '',
                                'per_page'  => 8,
                                'term_id'   => 0,
                        )
                );

                $html  = '<div class="hsw-loop__tabs" role="tablist" aria-label="' . esc_attr__( 'فیلتر محصولات', 'hamishop-elementor-widgets' ) . '">';
                $first = true;
                $index = 0;

                foreach ( $tabs as $tab ) {
                        if ( ! is_array( $tab ) ) {
                                continue;
                        }
                        $source = (string) ( $tab['tab_source'] ?? 'rating' );
                        if ( ! in_array( $source, $sources, true ) ) {
                                continue;
                        }
                        $label = trim( (string) ( $tab['tab_label'] ?? '' ) );
                        if ( '' === $label ) {
                                continue;
                        }
                        $id  = $rail . '-tab-' . $index;
                        $url = add_query_arg(
                                array(
                                        'type'      => 'popular',
                                        'source'    => $source,
                                        'per_page'  => max( 1, (int) $context['per_page'] ),
                                        'term_id'   => max( 0, (int) $context['term_id'] ),
                                        'post_id'   => max( 0, (int) $context['post_id'] ),
                                        'widget_id' => (string) $context['widget_id'],
                                        'sig'       => (string) $context['sig'],
                                ),
                                (string) $context['endpoint']
                        );

                        $html .= '<button type="button" class="hsw-loop__tab' . ( $first ? ' is-active' : '' ) . '"' .
                                ' role="tab" id="' . esc_attr( $id ) . '" aria-controls="' . esc_attr( $rail ) . '"' .
                                ' aria-selected="' . ( $first ? 'true' : 'false' ) . '"' .
                                ' tabindex="' . ( $first ? '0' : '-1' ) . '"' .
                                ' data-hsw-src="' . esc_attr( $source ) . '"' .
                                ' data-hsw-url="' . esc_url( $url ) . '">' . esc_html( $label ) . '</button>';

                        $first = false;
                        $index++;
                }

                return $html . '</div>';
        }

        /**
         * Optional “view all” card at the end of a deal rail.
         *
         * @param array $s Settings.
         * @return string
         */
        protected function more_card_html( $s ) {
                $url = (string) ( $s['hdr_link_url']['url'] ?? '' );
                if ( '' === $url ) {
                        $url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
                }
                $label = trim( (string) ( $s['hdr_link_d'] ?? '' ) );
                if ( '' === $label ) {
                        $label = __( 'مشاهده همه', 'hamishop-elementor-widgets' );
                }

                return '<a class="hsw-loop__card hsw-loop__more" href="' . esc_url( $url ) . '">' .
                        '<span class="hsw-loop__more-icon" aria-hidden="true">' . HSW_Helpers::icon( 'arrow_back' ) . '</span>' .
                        '<span class="hsw-loop__more-label">' . esc_html( $label ) . '</span>' .
                        '</a>';
        }

        /**
         * Content: data source.
         */
        protected function controls_source( $default_source, $supports_manual = true ) {
                $this->start_controls_section(
                        'sec_source',
                        array( 'label' => __( 'منبع محصولات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $sources = array(
                        'sale'         => __( 'پیشنهاد ویژه (تخفیف‌دار)', 'hamishop-elementor-widgets' ),
                        'best_selling' => __( 'پرفروش‌ترین', 'hamishop-elementor-widgets' ),
                        'rating'       => __( 'محبوب‌ترین (بیشترین امتیاز)', 'hamishop-elementor-widgets' ),
                        'popular'      => __( 'محبوب‌ترین (امتیاز + فروش)', 'hamishop-elementor-widgets' ),
                        'newest'       => __( 'جدیدترین', 'hamishop-elementor-widgets' ),
                        'discount'     => __( 'بیشترین تخفیف', 'hamishop-elementor-widgets' ),
                        'featured'     => __( 'منتخب (Featured)', 'hamishop-elementor-widgets' ),
                        'category'     => __( 'دسته‌بندی خاص', 'hamishop-elementor-widgets' ),
                );
                if ( $supports_manual ) {
                        $sources['manual'] = __( 'انتخاب دستی محصولات', 'hamishop-elementor-widgets' );
                }

                $this->add_control(
                        'source',
                        array(
                                'label'   => __( 'نوع انتخاب', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => $default_source,
                                'options' => $sources,
                        )
                );

                $this->add_control(
                        'count',
                        array(
                                'label'   => __( 'تعداد محصولات', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SLIDER,
                                'range'   => array( 'px' => array( 'min' => 1, 'max' => 24 ) ),
                                'default' => array( 'size' => 8 ),
                        )
                );

                $this->add_control(
                        'source_term',
                        array(
                                'label'     => __( 'دسته‌بندی', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SELECT2,
                                'multiple'  => false,
                                'options'   => self::term_options(),
                                'condition' => array( 'source' => 'category' ),
                        )
                );

                $this->add_control(
                        'source_ids',
                        array(
                                'label'       => __( 'محصولات (جستجو بر اساس نام)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SELECT2,
                                'multiple'    => true,
                                'options'     => self::product_options(),
                                'label_block' => true,
                                'condition'   => array( 'source' => 'manual' ),
                                'description' => __( '۳۰۰ محصول اخیر نمایش داده می‌شود؛ برای فروشگاه‌های بزرگ از منبع دسته‌بندی استفاده کنید.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'exclude_oos',
                        array(
                                'label'       => __( 'حذف ناموجودها', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'default'     => 'yes',
                                'description' => __( 'کارت «اتمام موجودی» فقط در ویجت محبوب‌ترین‌ها نمایش داده می‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'cache_ttl',
                        array(
                                'label'       => __( 'مدت کش نتایج (ثانیه)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::NUMBER,
                                'default'     => 300,
                                'min'         => 0,
                                'max'         => 3600,
                                'step'        => 30,
                                'description' => __( '۰ = بدون کش. کش به‌طور خودکار با تغییر محصول، قیمت، موجودی یا پایان حراج باطل می‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Content: Flash Deal rows (1–3).
         */
        protected function controls_deal_rows() {
                $this->start_controls_section(
                        'sec_deal_rows',
                        array( 'label' => __( 'ردیف‌های پیشنهاد شگفت‌انگیز', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->add_control(
                        'deal_rows',
                        array(
                                'label'   => __( 'تعداد ردیف', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => '1',
                                'options' => array(
                                        '1' => '۱',
                                        '2' => '۲',
                                        '3' => '۳',
                                ),
                                'description' => __( 'برای نمایش بیشتر از سه ردیف، ویجت را چند بار در صفحه قرار دهید؛ تایمر همهٔ نمونه‌ها یکسان محاسبه می‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'show_more_card',
                        array(
                                'label'   => __( 'کارت «مشاهده همه» در انتهای ردیف', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'no',
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Content: Best Sellers date range.
         */
        protected function controls_best_range() {
                $this->start_controls_section(
                        'sec_best_range',
                        array( 'label' => __( 'بازهٔ پرفروش‌ترین‌ها', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->add_control(
                        'best_range',
                        array(
                                'label'   => __( 'بازهٔ زمانی فروش', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => '7',
                                'options' => array(
                                        '7'   => __( '۷ روز گذشته', 'hamishop-elementor-widgets' ),
                                        '30'  => __( '۳۰ روز گذشته', 'hamishop-elementor-widgets' ),
                                        'all' => __( 'همهٔ زمان‌ها', 'hamishop-elementor-widgets' ),
                                ),
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Content: section header.
         */
        protected function controls_header( $defaults ) {
                $this->start_controls_section(
                        'sec_header',
                        array( 'label' => __( 'سربرگ بخش', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->add_control(
                        'show_header',
                        array(
                                'label'   => __( 'نمایش سربرگ', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'hdr_icon',
                        array(
                                'label'   => __( 'آیکون', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => $defaults['icon'],
                                'options' => self::icon_options(),
                        )
                );

                $this->add_control(
                        'hdr_title',
                        array(
                                'label'       => __( 'عنوان', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => $defaults['title'],
                                'label_block' => true,
                        )
                );

                $this->add_control(
                        'hdr_tag',
                        array(
                                'label'   => __( 'سطح عنوان (HTML)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => 'h2',
                                'options' => array(
                                        'h2'  => 'H2',
                                        'h3'  => 'H3',
                                        'div' => 'DIV',
                                ),
                        )
                );

                if ( ! empty( $defaults['badge'] ) ) {
                        $this->add_control(
                                'hdr_badge',
                                array(
                                        'label'   => __( 'برچسب کنار عنوان', 'hamishop-elementor-widgets' ),
                                        'type'    => Controls_Manager::TEXT,
                                        'default' => $defaults['badge'],
                                )
                        );
                }

                $this->add_control(
                        'hdr_sub',
                        array(
                                'label'       => __( 'توضیح کوتاه', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => $defaults['sub'],
                                'label_block' => true,
                        )
                );

                $this->add_control(
                        'hdr_link_d',
                        array(
                                'label'   => __( 'متن لینک — دسکتاپ', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => $defaults['link_d'],
                        )
                );

                $this->add_control(
                        'hdr_link_m',
                        array(
                                'label'   => __( 'متن لینک — موبایل', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => $defaults['link_m'],
                        )
                );

                $this->add_control(
                        'hdr_link_url',
                        array(
                                'label'   => __( 'آدرس لینک', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::URL,
                                'default' => array( 'url' => '' ),
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Content: countdown (Section 1).
         */
        protected function controls_countdown() {
                $this->start_controls_section(
                        'sec_countdown',
                        array( 'label' => __( 'شمارش معکوس', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->add_control(
                        'show_cd',
                        array(
                                'label'   => __( 'نمایش تایمر', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'cd_mode',
                        array(
                                'label'     => __( 'منبع زمان پایان', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SELECT,
                                'default'   => 'auto',
                                'options'   => array(
                                        'auto'   => __( 'پایان حراج محصولات (خودکار)', 'hamishop-elementor-widgets' ),
                                        'manual' => __( 'زمان دستی', 'hamishop-elementor-widgets' ),
                                ),
                                'condition' => array( 'show_cd' => 'yes' ),
                        )
                );

                $this->add_control(
                        'cd_manual',
                        array(
                                'label'       => __( 'زمان پایان (مثال: 2025-12-31 23:59)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => '',
                                'condition'   => array( 'show_cd' => 'yes', 'cd_mode' => 'manual' ),
                                'label_block' => true,
                        )
                );

                $this->add_control(
                        'cd_label',
                        array(
                                'label'     => __( 'متن کنار تایمر', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'زمان باقی‌مانده:', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_cd' => 'yes' ),
                        )
                );

                $this->add_control(
                        'cd_end_action',
                        array(
                                'label'     => __( 'رفتار پس از پایان زمان', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SELECT,
                                'default'   => 'hide_timer',
                                'options'   => array(
                                        'hide_timer'  => __( 'فقط تایمر پنهان شود', 'hamishop-elementor-widgets' ),
                                        'hide_block'  => __( 'کل ویجت پنهان شود', 'hamishop-elementor-widgets' ),
                                        'ended_note'  => __( 'متن «پایان یافته» جای تایمر', 'hamishop-elementor-widgets' ),
                                ),
                                'condition' => array( 'show_cd' => 'yes' ),
                                'description' => __( 'هیچ‌وقت 00:00:00 مرده نمایش داده نمی‌شود؛ در حالت «کل ویجت پنهان شود» ارتفاع صفحه تغییر نمی‌کند.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'cd_end_text',
                        array(
                                'label'       => __( 'متن پایان تایمر', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'این پیشنهاد به پایان رسید', 'hamishop-elementor-widgets' ),
                                'condition'   => array( 'show_cd' => 'yes', 'cd_end_action' => 'ended_note' ),
                                'label_block' => true,
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Content: lead banner (Section 1).
         */
        protected function controls_lead() {
                $this->start_controls_section(
                        'sec_lead',
                        array( 'label' => __( 'کارت بنر شروع', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->add_control(
                        'show_lead',
                        array(
                                'label'   => __( 'نمایش بنر', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'lead_icon',
                        array(
                                'label'   => __( 'آیکون', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => 'local_fire_department',
                                'options' => self::icon_options(),
                        )
                );

                $this->add_control(
                        'lead_title',
                        array(
                                'label'       => __( 'عنوان بنر', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'تخفیف‌های ویژه روز', 'hamishop-elementor-widgets' ),
                                'label_block' => true,
                        )
                );

                $this->add_control(
                        'lead_sub',
                        array(
                                'label'   => __( 'زیرعنوان', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'بیش از ۵۰ کالای تخفیف‌دار', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'lead_btn',
                        array(
                                'label'   => __( 'متن دکمه', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'مشاهده همه', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'lead_url',
                        array(
                                'label'   => __( 'آدرس دکمه', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::URL,
                                'default' => array( 'url' => '' ),
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Content: texts & flags of the card itself.
         */
        protected function controls_card_content( $overrides = array() ) {
                $this->start_controls_section(
                        'sec_card_content',
                        array( 'label' => __( 'اجزای کارت محصول', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $controls = array(
                        'show_brand'        => array( __( 'نمایش دسته/برند', 'hamishop-elementor-widgets' ), 'yes' ),
                        'brand_attr'        => array( __( 'کلاس ویژگی برند (مثال: pa_brand)', 'hamishop-elementor-widgets' ), 'pa_brand' ),
                        'show_rating'       => array( __( 'نمایش امتیاز', 'hamishop-elementor-widgets' ), 'yes' ),
                        'show_rating_count' => array( __( 'نمایش تعداد نظر', 'hamishop-elementor-widgets' ), 'yes' ),
                        'show_old'          => array( __( 'نمایش قیمت قبل از تخفیف', 'hamishop-elementor-widgets' ), 'yes' ),
                        'show_stock'        => array( __( 'نوار پیشرفت موجودی', 'hamishop-elementor-widgets' ), 'yes' ),
                        'show_rank'         => array( __( 'نشان رتبه', 'hamishop-elementor-widgets' ), 'yes' ),
                        'show_badge'        => array( __( 'نشان تخفیف/ویژه/ارسال', 'hamishop-elementor-widgets' ), 'yes' ),
                        'currency'          => array( __( 'واحد پول', 'hamishop-elementor-widgets' ), 'تومان' ),
                        'price_label'       => array( __( 'برچسب قیمت (مثال: قیمت قطعی:)', 'hamishop-elementor-widgets' ), '' ),
                        'txt_sale'          => array( __( 'متن نشان تخفیف', 'hamishop-elementor-widgets' ), 'تخفیف' ),
                        'txt_feat'          => array( __( 'متن نشان ویژه', 'hamishop-elementor-widgets' ), 'ویژه' ),
                        'txt_ship'          => array( __( 'متن نشان ارسال رایگان', 'hamishop-elementor-widgets' ), 'ارسال رایگان' ),
                        'txt_oos'           => array( __( 'متن اتمام موجودی', 'hamishop-elementor-widgets' ), 'اتمام موجودی' ),
                        'txt_notify'        => array( __( 'متن دکمه خبرم کن', 'hamishop-elementor-widgets' ), 'خبرم کن' ),
                        'txt_buy'           => array( __( 'متن دکمه خرید', 'hamishop-elementor-widgets' ), 'خرید' ),
                        'txt_add'           => array( __( 'متن دکمه افزودن به سبد', 'hamishop-elementor-widgets' ), 'افزودن به سبد خرید' ),
                        'ico_add'           => array( __( 'آیکون دکمه', 'hamishop-elementor-widgets' ), 'shopping_cart' ),
                );

                $switchers = array( 'show_brand', 'show_rating', 'show_rating_count', 'show_old', 'show_stock', 'show_rank', 'show_badge' );

                foreach ( $controls as $id => $conf ) {
                        if ( isset( $overrides[ $id ] ) && false === $overrides[ $id ] ) {
                                continue;
                        }
                        $default = isset( $overrides[ $id ] ) ? $overrides[ $id ] : $conf[1];
                        if ( 'ico_add' === $id ) {
                                $this->add_control(
                                        $id,
                                        array(
                                                'label'   => $conf[0],
                                                'type'    => Controls_Manager::SELECT,
                                                'default' => $default,
                                                'options' => self::icon_options(),
                                        )
                                );
                        } elseif ( in_array( $id, $switchers, true ) ) {
                                $this->add_control(
                                        $id,
                                        array(
                                                'label'   => $conf[0],
                                                'type'    => Controls_Manager::SWITCHER,
                                                'default' => $default,
                                        )
                                );
                        } else {
                                $this->add_control(
                                        $id,
                                        array(
                                                'label'   => $conf[0],
                                                'type'    => Controls_Manager::TEXT,
                                                'default' => $default,
                                        )
                                );
                        }
                }

                $this->add_control(
                        'btn_variant',
                        array(
                                'label'       => __( 'شکل دکمه خرید', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SELECT,
                                'default'     => $overrides['btn_variant'] ?? 'text',
                                'options'     => array(
                                        'text' => __( 'آیکون + متن', 'hamishop-elementor-widgets' ),
                                        'icon' => __( 'فقط آیکون', 'hamishop-elementor-widgets' ),
                                        'plus' => __( 'دکمه گرد +', 'hamishop-elementor-widgets' ),
                                ),
                                'separator'   => 'before',
                        )
                );

                $this->add_control(
                        'stock_mode',
                        array(
                                'label'       => __( 'حالت نوار موجودی', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SELECT,
                                'default'     => 'low',
                                'options'     => array(
                                        'low'  => __( 'درصد باقی‌مانده تا آستانه', 'hamishop-elementor-widgets' ),
                                        'sold' => __( 'درصد فروش‌رفته (از موجودی ثبت‌شده)', 'hamishop-elementor-widgets' ),
                                ),
                                'condition'   => array( 'show_stock' => 'yes' ),
                                'description' => __( 'حالت «فروش‌رفته» از موجودی اولیهٔ ثبت‌شده حساب می‌کند (نه فروش کل محصول)؛ تا زمانی که برای محصول موجودی اولیه ثبت نشده باشد، نوار ساخته نمی‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'stock_low',
                        array(
                                'label'       => __( 'آستانه کمبود موجودی', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::NUMBER,
                                'default'     => 3,
                                'min'         => 1,
                                'max'         => 50,
                                'condition'   => array( 'show_stock' => 'yes' ),
                                'description' => __( 'زیر این تعداد، متن هشدار و رنگ خطر فعال می‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'free_ship_min',
                        array(
                                'label'       => __( 'حداقل قیمت برای نشان «ارسال رایگان»', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::NUMBER,
                                'default'     => 0,
                                'min'         => 0,
                                'description' => __( '۰ = خاموش. اگر قیمت محصول از این مقدار بیشتر باشد نشان ارسال رایگان می‌گیرد (فقط ویجت محبوب‌ترین‌ها).', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Style: shared design tokens (preset + palette overrides).
         */
        protected function controls_theme() {
                $this->start_controls_section(
                        'sec_style_theme',
                        array(
                                'label' => __( 'تم و رنگ‌های اصلی', 'hamishop-elementor-widgets' ),
                                'tab'   => Controls_Manager::TAB_STYLE,
                        )
                );

                $this->add_control(
                        'loop_preset',
                        array(
                                'label'       => __( 'پالت آماده', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SELECT,
                                'default'     => 'auto',
                                'options'     => array(
                                        'auto'  => __( 'خودکار (پالت سراسری حامی)', 'hamishop-elementor-widgets' ),
                                        'red'   => __( 'قرمز', 'hamishop-elementor-widgets' ),
                                        'blue'  => __( 'آبی', 'hamishop-elementor-widgets' ),
                                        'green' => __( 'سبز', 'hamishop-elementor-widgets' ),
                                ),
                                'description' => __( 'رنگ‌های سراسری از صفحه «تنظیمات حامی → پالت رنگ» خوانده می‌شوند؛ این پالت فقط برای همین ویجت است.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'loop_color_mode',
                        array(
                                'label'       => __( 'حالت روشن/تاریک', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SELECT,
                                'default'     => 'light',
                                'options'     => array(
                                        'light' => __( 'روشن', 'hamishop-elementor-widgets' ),
                                        'dark'  => __( 'تاریک', 'hamishop-elementor-widgets' ),
                                        'auto'  => __( 'خودکار بر اساس تنظیم سیستم کاربر', 'hamishop-elementor-widgets' ),
                                ),
                                'description' => __( 'همهٔ رنگ‌ها از همان توکن‌ها می‌آیند؛ حالت تاریک نسخهٔ تیرهٔ همین توکن‌هاست و ظاهر پیش‌فرض سایت را تغییر نمی‌دهد.', 'hamishop-elementor-widgets' ),
                        )
                );

                /*
                 * White text on a light gradient stop measured 3.43:1 (fails AA
                 * for normal text). The scrim is the store's own dial: 20% is
                 * enough for a typical blue start, more for a lighter palette.
                 */
                $this->add_responsive_control(
                        'deal_scrim',
                        array(
                                'label'      => __( 'تیرگی پردهٔ گرادیان (پیشنهاد شگفت‌انگیز)', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( '%' ),
                                'range'      => array( '%' => array( 'min' => 0, 'max' => 50, 'step' => 1 ) ),
                                'default'    => array( 'size' => 20, 'unit' => '%' ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop' => '--hsw-loop-deal-scrim: rgb(0 0 0 / {{SIZE}}%);' ),
                                'description' => __( 'حداقل ۱۵٪ برای متن سفید روی روشن‌ترین نقطهٔ گرادیان لازم است تا کنتراست AA شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'loop_primary',
                        array(
                                'label'     => __( 'رنگ اصلی (متن/تیره)', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-loop' => '--hsw-loop-primary: {{VALUE}};' ),
                                'separator' => 'before',
                        )
                );

                $this->add_control(
                        'loop_accent',
                        array(
                                'label'     => __( 'رنگ تأکید (دکمه/قیمت)', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-loop' => '--hsw-loop-accent: {{VALUE}};' ),
                        )
                );

                $this->add_control(
                        'loop_bg',
                        array(
                                'label'     => __( 'رنگ سطح', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-loop' => '--hsw-loop-bg: {{VALUE}};' ),
                        )
                );

                $this->add_control(
                        'loop_fg',
                        array(
                                'label'     => __( 'رنگ متن', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-loop' => '--hsw-loop-fg: {{VALUE}};' ),
                        )
                );

                $this->add_control(
                        'loop_muted',
                        array(
                                'label'     => __( 'رنگ متن کم‌رنگ', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-loop' => '--hsw-loop-muted: {{VALUE}};' ),
                        )
                );

                $this->add_control(
                        'loop_border',
                        array(
                                'label'     => __( 'رنگ حاشیه', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-loop' => '--hsw-loop-border: {{VALUE}};' ),
                        )
                );

                $this->add_responsive_control(
                        'loop_radius',
                        array(
                                'label'      => __( 'گردی پایه', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 0, 'max' => 48 ),
                                        'em'  => array( 'min' => 0, 'max' => 3, 'step' => 0.1 ),
                                        'rem' => array( 'min' => 0, 'max' => 3, 'step' => 0.1 ),
                                ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop' => '--hsw-loop-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->add_control(
                        'loop_theme_reset',
                        array(
                                'label'       => __( 'بازنشانی به تم پیش‌فرض', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'separator'   => 'before',
                                'description' => __( 'همهٔ رنگ‌ها و گردی‌های این گروه به مقادیر پالت/قالب برمی‌گردند.', 'hamishop-elementor-widgets' ),
                                'selectors'   => array(
                                        '{{WRAPPER}} .hsw-loop' => '--hsw-loop-primary: initial; --hsw-loop-accent: initial; --hsw-loop-bg: initial; --hsw-loop-fg: initial; --hsw-loop-muted: initial; --hsw-loop-border: initial; --hsw-loop-radius: initial;',
                                ),
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Style: section shell (background/border/padding/radius/shadow).
         */
        protected function controls_shell( $kind ) {
                $this->start_controls_section(
                        'sec_style_shell',
                        array(
                                'label' => __( 'بخش (پس‌زمینه و کادر)', 'hamishop-elementor-widgets' ),
                                'tab'   => Controls_Manager::TAB_STYLE,
                        )
                );

                if ( 'deal' === $kind ) {
                        $this->add_control(
                                'sec_grad',
                                array(
                                        'label'     => __( 'گرادیان پیشنهاد شگفت‌انگیز', 'hamishop-elementor-widgets' ),
                                        'type'      => Controls_Manager::HEADING,
                                        'separator' => 'before',
                                )
                        );
                        $this->add_control( 'sec_from', array( 'label' => __( 'رنگ ابتدا', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop--deal' => '--hsw-loop-deal-from: {{VALUE}};' ) ) );
                        $this->add_control( 'sec_via', array( 'label' => __( 'رنگ میانه', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop--deal' => '--hsw-loop-deal-via: {{VALUE}};' ) ) );
                        $this->add_control( 'sec_m', array( 'label' => __( 'رنگ انتها (موبایل)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop--deal' => '--hsw-loop-deal-to: {{VALUE}};' ) ) );
                        $this->add_control(
                                'sec_flat',
                                array(
                                        'label'     => __( 'حذف گرادیان (رنگ ساده)', 'hamishop-elementor-widgets' ),
                                        'type'      => Controls_Manager::SWITCHER,
                                        'selectors' => array( '{{WRAPPER}} .hsw-loop--deal' => '--hsw-loop-deal-gradient: none;' ),
                                )
                        );
                } else {
                        $this->add_control( 'sec_bg', array( 'label' => __( 'رنگ پس‌زمینه', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop--box' => '--hsw-loop-shell-bg: {{VALUE}};' ) ) );
                        $this->add_control( 'sec_border_c', array( 'label' => __( 'رنگ حاشیه', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop--box' => '--hsw-loop-shell-border: {{VALUE}};' ) ) );
                }

                $this->add_responsive_control(
                        'sec_padding',
                        array(
                                'label'           => __( 'پدینگ بخش', 'hamishop-elementor-widgets' ),
                                'type'            => Controls_Manager::DIMENSIONS,
                                'size_units'      => array( 'px', 'em', 'rem', '%' ),
                                'default'         => array(
                                        'top'      => 'deal' === $kind ? '12' : '0',
                                        'right'    => '0',
                                        'bottom'   => '12',
                                        'left'     => '0',
                                        'unit'     => 'px',
                                        'isLinked' => false,
                                ),
                                'tablet_default'  => array( 'top' => '16', 'right' => '16', 'bottom' => '16', 'left' => '16', 'unit' => 'px', 'isLinked' => true ),
                                'selectors'       => array(
                                        '{{WRAPPER}} .hsw-loop' => '--hsw-loop-shell-pad: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                                ),
                        )
                );

                $this->add_responsive_control(
                        'sec_radius',
                        array(
                                'label'      => __( 'گردی گوشه‌ها', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem', '%' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 0, 'max' => 40 ),
                                        'em'  => array( 'min' => 0, 'max' => 2.5, 'step' => 0.1 ),
                                        'rem' => array( 'min' => 0, 'max' => 2.5, 'step' => 0.1 ),
                                        '%'   => array( 'min' => 0, 'max' => 50 ),
                                ),
                                'default'    => array( 'size' => 16, 'unit' => 'px' ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop' => '--hsw-loop-shell-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->add_responsive_control(
                        'sec_border_w',
                        array(
                                'label'      => __( 'ضخامت حاشیه', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 0, 'max' => 8 ),
                                        'em'  => array( 'min' => 0, 'max' => 0.5, 'step' => 0.05 ),
                                        'rem' => array( 'min' => 0, 'max' => 0.5, 'step' => 0.05 ),
                                ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop' => '--hsw-loop-shell-bw: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->add_group_control(
                        Group_Control_Box_Shadow::get_type(),
                        array(
                                'name'     => 'sec_shadow',
                                'selector' => '{{WRAPPER}} .hsw-loop',
                        )
                );

                $this->add_control(
                        'sec_shell_reset',
                        array(
                                'label'       => __( 'بازنشانی به تم پیش‌فرض', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'separator'   => 'before',
                                'description' => __( 'گرادیان/پس‌زمینه، حاشیه، گردی، پدینگ و سایه به مقدار قالب برمی‌گردند.', 'hamishop-elementor-widgets' ),
                                'selectors'   => array(
                                        '{{WRAPPER}} .hsw-loop' => '--hsw-loop-shell-bg: initial; --hsw-loop-shell-border: initial; --hsw-loop-shell-bw: initial; --hsw-loop-shell-radius: initial; --hsw-loop-shell-shadow: none; --hsw-loop-shell-pad: initial; --hsw-loop-deal-from: initial; --hsw-loop-deal-via: initial; --hsw-loop-deal-to: initial; --hsw-loop-deal-gradient: initial; box-shadow: none;',
                                ),
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Style: header.
         */
        protected function controls_header_style() {
                $this->start_controls_section(
                        'sec_style_header',
                        array( 'label' => __( 'استایل سربرگ', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_control( 'hdr_ic_bg', array( 'label' => __( 'پس‌زمینه آیکون', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__header' => '--hsw-loop-hdr-icon-bg: {{VALUE}};' ) ) );
                $this->add_control( 'hdr_ic_c', array( 'label' => __( 'رنگ آیکون', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__header' => '--hsw-loop-hdr-icon-c: {{VALUE}};' ) ) );
                $this->add_responsive_control(
                        'hdr_ic_size',
                        array(
                                'label'      => __( 'اندازه آیکون', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 12, 'max' => 40 ),
                                        'em'  => array( 'min' => 0.75, 'max' => 2.5, 'step' => 0.05 ),
                                        'rem' => array( 'min' => 0.75, 'max' => 2.5, 'step' => 0.05 ),
                                ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop__header' => '--hsw-loop-hdr-icon-size: {{SIZE}}{{UNIT}};' ),
                        )
                );
                $this->add_responsive_control(
                        'hdr_ic_box',
                        array(
                                'label'      => __( 'اندازه قاب آیکون', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 24, 'max' => 64 ),
                                        'em'  => array( 'min' => 1.5, 'max' => 4, 'step' => 0.1 ),
                                        'rem' => array( 'min' => 1.5, 'max' => 4, 'step' => 0.1 ),
                                ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop__header' => '--hsw-loop-hdr-icon-box: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->add_group_control(
                        Group_Control_Typography::get_type(),
                        array(
                                'name'      => 'hdr_title_typo',
                                'label'     => __( 'تایپوگرافی عنوان', 'hamishop-elementor-widgets' ),
                                'selector'  => '{{WRAPPER}} .hsw-loop__title',
                                'separator' => 'before',
                        )
                );
                $this->add_control( 'hdr_title_c', array( 'label' => __( 'رنگ عنوان', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__header' => '--hsw-loop-hdr-title-c: {{VALUE}};' ) ) );

                $this->add_group_control(
                        Group_Control_Typography::get_type(),
                        array(
                                'name'      => 'hdr_sub_typo',
                                'label'     => __( 'تایپوگرافی توضیح', 'hamishop-elementor-widgets' ),
                                'selector'  => '{{WRAPPER}} .hsw-loop__subtitle',
                                'separator' => 'before',
                        )
                );
                $this->add_control( 'hdr_sub_c', array( 'label' => __( 'رنگ توضیح', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__header' => '--hsw-loop-hdr-sub-c: {{VALUE}};' ) ) );

                $this->add_control( 'hdr_badge_bg', array( 'label' => __( 'پس‌زمینه برچسب', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__header' => '--hsw-loop-hdr-badge-bg: {{VALUE}};' ) ) );
                $this->add_control( 'hdr_badge_c', array( 'label' => __( 'رنگ برچسب', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__header' => '--hsw-loop-hdr-badge-c: {{VALUE}};' ) ) );

                $this->add_group_control(
                        Group_Control_Typography::get_type(),
                        array(
                                'name'      => 'hdr_link_typo',
                                'label'     => __( 'تایپوگرافی لینک', 'hamishop-elementor-widgets' ),
                                'selector'  => '{{WRAPPER}} .hsw-loop__link',
                                'separator' => 'before',
                        )
                );
                $this->add_control( 'hdr_link_c', array( 'label' => __( 'رنگ لینک', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__header' => '--hsw-loop-hdr-link-c: {{VALUE}};' ) ) );
                $this->add_control( 'hdr_link_ch', array( 'label' => __( 'رنگ لینک (هاور)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__header' => '--hsw-loop-hdr-link-hover: {{VALUE}};' ) ) );

                $this->add_responsive_control(
                        'hdr_gap',
                        array(
                                'label'      => __( 'فاصله اجزای سربرگ', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 0, 'max' => 40 ),
                                        'em'  => array( 'min' => 0, 'max' => 2.5, 'step' => 0.05 ),
                                        'rem' => array( 'min' => 0, 'max' => 2.5, 'step' => 0.05 ),
                                ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop__header' => '--hsw-loop-hdr-gap: {{SIZE}}{{UNIT}};' ),
                                'separator'  => 'before',
                        )
                );

                $this->add_control(
                        'hdr_reset',
                        array(
                                'label'       => __( 'بازنشانی به تم پیش‌فرض', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'separator'   => 'before',
                                'description' => __( 'رنگ‌ها، تایپوگرافی و اندازه‌های این گروه به مقدار قالب برمی‌گردند.', 'hamishop-elementor-widgets' ),
                                'selectors'   => array(
                                        '{{WRAPPER}} .hsw-loop__header' => '--hsw-loop-hdr-icon-bg: initial; --hsw-loop-hdr-icon-c: initial; --hsw-loop-hdr-icon-size: initial; --hsw-loop-hdr-icon-box: initial; --hsw-loop-hdr-title-c: initial; --hsw-loop-hdr-sub-c: initial; --hsw-loop-hdr-badge-bg: initial; --hsw-loop-hdr-badge-c: initial; --hsw-loop-hdr-link-c: initial; --hsw-loop-hdr-link-hover: initial; --hsw-loop-hdr-gap: initial;',
                                ),
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Style: countdown + arrows.
         */
        protected function controls_countdown_style() {
                $this->start_controls_section(
                        'sec_style_cd',
                        array( 'label' => __( 'استایل تایمر و فلش‌ها', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_control( 'cd_bg', array( 'label' => __( 'پس‌زمینه کپسول تایمر', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__timer' => '--hsw-loop-timer-bg: {{VALUE}};' ) ) );
                $this->add_control( 'cd_val_c', array( 'label' => __( 'رنگ اعداد', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__timer' => '--hsw-loop-timer-c: {{VALUE}}; --hsw-loop-timer-part-c: {{VALUE}};' ) ) );
                $this->add_control( 'cd_box_bg', array( 'label' => __( 'پس‌زمینه جعبه‌های عدد', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__timer' => '--hsw-loop-timer-part-bg: {{VALUE}};' ) ) );
                $this->add_control( 'cd_box_c', array( 'label' => __( 'رنگ جعبه‌های عدد', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__timer' => '--hsw-loop-timer-part-c: {{VALUE}};' ) ) );
                $this->add_control( 'cd_label_c', array( 'label' => __( 'رنگ متن کنار تایمر', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__timer' => '--hsw-loop-timer-label-c: {{VALUE}};' ) ) );

                $this->add_responsive_control(
                        'cd_radius',
                        array(
                                'label'      => __( 'گردی کپسول', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem', '%' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 0, 'max' => 40 ),
                                        'em'  => array( 'min' => 0, 'max' => 2.5, 'step' => 0.1 ),
                                        'rem' => array( 'min' => 0, 'max' => 2.5, 'step' => 0.1 ),
                                        '%'   => array( 'min' => 0, 'max' => 50 ),
                                ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop__timer' => '--hsw-loop-timer-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->add_group_control(
                        Group_Control_Typography::get_type(),
                        array(
                                'name'     => 'cd_typo',
                                'label'    => __( 'تایپوگرافی تایمر', 'hamishop-elementor-widgets' ),
                                'selector' => '{{WRAPPER}} .hsw-loop__timer',
                        )
                );

                $this->add_control(
                        'arrow_bg',
                        array(
                                'label'     => __( 'پس‌زمینه فلش‌ها', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-loop__arrow' => '--hsw-loop-arrow-bg: {{VALUE}};' ),
                                'separator' => 'before',
                        )
                );
                $this->add_control( 'arrow_bgh', array( 'label' => __( 'پس‌زمینه فلش (هاور)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__arrow' => '--hsw-loop-arrow-bg-h: {{VALUE}};' ) ) );
                $this->add_control( 'arrow_c', array( 'label' => __( 'رنگ فلش‌ها', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__arrow' => '--hsw-loop-arrow-c: {{VALUE}};' ) ) );
                $this->add_control( 'arrow_ch', array( 'label' => __( 'رنگ فلش (هاور)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__arrow' => '--hsw-loop-arrow-c-h: {{VALUE}};' ) ) );
                $this->add_control( 'arrow_border', array( 'label' => __( 'حاشیه فلش‌ها', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__arrow' => '--hsw-loop-arrow-border: {{VALUE}};' ) ) );
                $this->add_responsive_control(
                        'arrow_radius',
                        array(
                                'label'      => __( 'گردی فلش‌ها', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem', '%' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 0, 'max' => 24 ),
                                        'em'  => array( 'min' => 0, 'max' => 2, 'step' => 0.1 ),
                                        'rem' => array( 'min' => 0, 'max' => 2, 'step' => 0.1 ),
                                        '%'   => array( 'min' => 0, 'max' => 50 ),
                                ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop__arrow' => '--hsw-loop-arrow-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );
                $this->add_responsive_control(
                        'arrow_size',
                        array(
                                'label'      => __( 'اندازه دکمه فلش', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 32, 'max' => 64 ),
                                        'em'  => array( 'min' => 2, 'max' => 4, 'step' => 0.1 ),
                                        'rem' => array( 'min' => 2, 'max' => 4, 'step' => 0.1 ),
                                ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop__arrow' => '--hsw-loop-arrow-size: {{SIZE}}{{UNIT}};' ),
                        )
                );
                $this->add_control( 'scrollbar_c', array( 'label' => __( 'رنگ اسکرول‌بار', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__rail' => '--hsw-loop-scrollbar: {{VALUE}};' ) ) );

                $this->add_control(
                        'cd_reset',
                        array(
                                'label'       => __( 'بازنشانی به تم پیش‌فرض', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'separator'   => 'before',
                                'selectors'   => array(
                                        '{{WRAPPER}} .hsw-loop__timer' => '--hsw-loop-timer-bg: initial; --hsw-loop-timer-c: initial; --hsw-loop-timer-part-bg: initial; --hsw-loop-timer-part-c: initial; --hsw-loop-timer-label-c: initial; --hsw-loop-timer-radius: initial;',
                                        '{{WRAPPER}} .hsw-loop__arrow' => '--hsw-loop-arrow-bg: initial; --hsw-loop-arrow-bg-h: initial; --hsw-loop-arrow-c: initial; --hsw-loop-arrow-c-h: initial; --hsw-loop-arrow-border: initial; --hsw-loop-arrow-radius: initial; --hsw-loop-arrow-size: initial;',
                                ),
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Style: card.
         */
        protected function controls_card_style() {
                $this->start_controls_section(
                        'sec_style_card',
                        array( 'label' => __( 'استایل کارت', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_responsive_control(
                        'cards',
                        array(
                                'label'          => __( 'تعداد کارت در نمای دید', 'hamishop-elementor-widgets' ),
                                'type'           => Controls_Manager::SLIDER,
                                'size_units'     => array( 'px' ),
                                'range'          => array( 'px' => array( 'min' => 0, 'max' => 8, 'step' => 0.1 ) ),
                                'default'        => array( 'size' => 4, 'unit' => 'px' ),
                                'tablet_default' => array( 'size' => 3, 'unit' => 'px' ),
                                'mobile_default' => array( 'size' => 2.2, 'unit' => 'px' ),
                                'description'    => __( 'کسری مجاز است (مثلاً ۲.۳). مقدار ۰ عرض ثابت کارت زیر را فعال می‌کند.', 'hamishop-elementor-widgets' ),
                                'selectors'      => array( '{{WRAPPER}} .hsw-loop' => '--hsw-loop-cards: {{SIZE}};' ),
                        )
                );

                $this->add_responsive_control(
                        'card_w',
                        array(
                                'label'      => __( 'عرض ثابت کارت', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', '%', 'em', 'rem' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 100, 'max' => 420 ),
                                        '%'   => array( 'min' => 10, 'max' => 100 ),
                                        'em'  => array( 'min' => 6, 'max' => 28, 'step' => 0.5 ),
                                        'rem' => array( 'min' => 6, 'max' => 28, 'step' => 0.5 ),
                                ),
                                'default'    => array( 'size' => 288, 'unit' => 'px' ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop' => '--hsw-loop-card-w: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->add_responsive_control(
                        'track_gap',
                        array(
                                'label'      => __( 'فاصله بین کارت‌ها', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 0, 'max' => 48 ),
                                        'em'  => array( 'min' => 0, 'max' => 3, 'step' => 0.1 ),
                                        'rem' => array( 'min' => 0, 'max' => 3, 'step' => 0.1 ),
                                ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop' => '--hsw-loop-gap: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->add_control( 'card_bg', array( 'label' => __( 'پس‌زمینه کارت', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-card-bg: {{VALUE}};' ) ) );
                $this->add_control( 'card_border_c', array( 'label' => __( 'رنگ حاشیه', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-card-border: {{VALUE}};' ) ) );
                $this->add_responsive_control(
                        'card_border_w',
                        array(
                                'label'      => __( 'ضخامت حاشیه', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 0, 'max' => 6 ),
                                        'em'  => array( 'min' => 0, 'max' => 0.4, 'step' => 0.05 ),
                                        'rem' => array( 'min' => 0, 'max' => 0.4, 'step' => 0.05 ),
                                ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-card-bw: {{SIZE}}{{UNIT}};' ),
                        )
                );
                $this->add_responsive_control(
                        'card_radius',
                        array(
                                'label'      => __( 'گردی کارت', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem', '%' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 0, 'max' => 40 ),
                                        'em'  => array( 'min' => 0, 'max' => 2.5, 'step' => 0.1 ),
                                        'rem' => array( 'min' => 0, 'max' => 2.5, 'step' => 0.1 ),
                                        '%'   => array( 'min' => 0, 'max' => 50 ),
                                ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-card-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );
                $this->add_responsive_control(
                        'card_padding',
                        array(
                                'label'          => __( 'پدینگ داخلی کارت', 'hamishop-elementor-widgets' ),
                                'type'           => Controls_Manager::SLIDER,
                                'size_units'     => array( 'px', 'em', 'rem' ),
                                'range'          => array(
                                        'px'  => array( 'min' => 0, 'max' => 40 ),
                                        'em'  => array( 'min' => 0, 'max' => 2.5, 'step' => 0.1 ),
                                        'rem' => array( 'min' => 0, 'max' => 2.5, 'step' => 0.1 ),
                                ),
                                'default'        => array( 'size' => 12, 'unit' => 'px' ),
                                'mobile_default' => array( 'size' => 10, 'unit' => 'px' ),
                                'selectors'      => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-card-pad: {{SIZE}}{{UNIT}};' ),
                        )
                );
                $this->add_group_control(
                        Group_Control_Box_Shadow::get_type(),
                        array(
                                'name'     => 'card_shadow',
                                'selector' => '{{WRAPPER}} .hsw-loop__card',
                        )
                );
                $this->add_control(
                        'no_lift',
                        array(
                                'label'     => __( 'غیرفعال کردن حرکت کارت در هاور', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SWITCHER,
                                'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-card-lift: none;' ),
                        )
                );

                /* ---- image ---- */
                $this->add_control(
                        'media_radius',
                        array(
                                'label'      => __( 'گردی تصویر', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem', '%' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 0, 'max' => 28 ),
                                        'em'  => array( 'min' => 0, 'max' => 2, 'step' => 0.1 ),
                                        'rem' => array( 'min' => 0, 'max' => 2, 'step' => 0.1 ),
                                        '%'   => array( 'min' => 0, 'max' => 50 ),
                                ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop__media' => '--hsw-loop-media-radius: {{SIZE}}{{UNIT}};' ),
                                'separator'  => 'before',
                        )
                );
                $this->add_control(
                        'media_ratio',
                        array(
                                'label'     => __( 'نسبت تصویر', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SELECT,
                                'default'   => '1',
                                'options'   => array(
                                        '1'      => __( 'مربع (۱:۱)', 'hamishop-elementor-widgets' ),
                                        '4 / 3'  => __( 'افقی (۴:۳)', 'hamishop-elementor-widgets' ),
                                        '3 / 4'  => __( 'عمودی (۳:۴)', 'hamishop-elementor-widgets' ),
                                        '16 / 9' => __( 'پهن (۱۶:۹)', 'hamishop-elementor-widgets' ),
                                ),
                                'selectors' => array( '{{WRAPPER}} .hsw-loop__media' => '--hsw-loop-media-ratio: {{VALUE}};' ),
                        )
                );
                $this->add_control(
                        'media_fit',
                        array(
                                'label'     => __( 'نحوه قرارگیری تصویر', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SELECT,
                                'default'   => 'cover',
                                'options'   => array(
                                        'cover'   => __( 'پر کردن قاب', 'hamishop-elementor-widgets' ),
                                        'contain' => __( 'نمایش کامل', 'hamishop-elementor-widgets' ),
                                ),
                                'selectors' => array( '{{WRAPPER}} .hsw-loop__media' => '--hsw-loop-media-fit: {{VALUE}};' ),
                        )
                );
                $this->add_control(
                        'zoom',
                        array(
                                'label'      => __( 'زوم تصویر در هاور', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'range'      => array( 'px' => array( 'min' => 1, 'max' => 1.3, 'step' => 0.01 ) ),
                                'default'    => array( 'size' => 1.05 ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-zoom: {{SIZE}};' ),
                        )
                );

                /* ---- title / category ---- */
                $this->add_control(
                        'clamp',
                        array(
                                'label'     => __( 'تعداد خطوط عنوان', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SELECT,
                                'default'   => '2',
                                'options'   => array( '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴' ),
                                'selectors' => array( '{{WRAPPER}} .hsw-loop__name' => '--hsw-loop-title-lines: {{VALUE}};' ),
                                'separator' => 'before',
                        )
                );

                $this->add_group_control(
                        Group_Control_Typography::get_type(),
                        array(
                                'name'     => 'title_typo',
                                'label'    => __( 'تایپوگرافی عنوان محصول', 'hamishop-elementor-widgets' ),
                                'selector' => '{{WRAPPER}} .hsw-loop__name',
                        )
                );
                $this->add_control( 'title_c', array( 'label' => __( 'رنگ عنوان', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-title-c: {{VALUE}};' ) ) );
                $this->add_control( 'title_ch', array( 'label' => __( 'رنگ عنوان (هاور)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop' => '--hsw-loop-title-hover: {{VALUE}};' ) ) );

                $this->add_group_control(
                        Group_Control_Typography::get_type(),
                        array(
                                'name'      => 'brand_typo',
                                'label'     => __( 'تایپوگرافی دسته/برند', 'hamishop-elementor-widgets' ),
                                'selector'  => '{{WRAPPER}} .hsw-loop__cat',
                                'separator' => 'before',
                        )
                );
                $this->add_control( 'brand_c', array( 'label' => __( 'رنگ دسته/برند', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-cat-c: {{VALUE}};' ) ) );

                $this->add_control( 'rate_star_c', array( 'label' => __( 'رنگ ستاره امتیاز', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-rating-c: {{VALUE}};' ), 'separator' => 'before' ) );

                $this->add_control(
                        'card_reset',
                        array(
                                'label'       => __( 'بازنشانی به تم پیش‌فرض', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'separator'   => 'before',
                                'description' => __( 'عرض/گردی/پدینگ/رنگ‌ها و تایپوگرافی این گروه به مقدار قالب برمی‌گردند.', 'hamishop-elementor-widgets' ),
                                'selectors'   => array(
                                        '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-card-bg: initial; --hsw-loop-card-border: initial; --hsw-loop-card-bw: initial; --hsw-loop-card-radius: initial; --hsw-loop-card-pad: initial; --hsw-loop-card-lift: initial; --hsw-loop-title-c: initial; --hsw-loop-cat-c: initial; --hsw-loop-rating-c: initial;',
                                        '{{WRAPPER}} .hsw-loop__media' => '--hsw-loop-media-radius: initial; --hsw-loop-media-ratio: initial; --hsw-loop-media-fit: initial;',
                                        '{{WRAPPER}} .hsw-loop' => '--hsw-loop-gap: initial; --hsw-loop-card-w: initial; --hsw-loop-cards: initial; --hsw-loop-title-hover: initial; --hsw-loop-zoom: 1.05;',
                                ),
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Style: badges / rank.
         */
        protected function controls_badge_style() {
                $this->start_controls_section(
                        'sec_style_badges',
                        array( 'label' => __( 'استایل نشان‌ها', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_control( 'badge_sale_bg', array( 'label' => __( 'پس‌زمینه نشان تخفیف', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-sale-bg: {{VALUE}};' ) ) );
                $this->add_control( 'badge_sale_c', array( 'label' => __( 'رنگ نشان تخفیف', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-sale-c: {{VALUE}};' ) ) );
                $this->add_control( 'badge_feat_bg', array( 'label' => __( 'پس‌زمینه نشان ویژه', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-feat-bg: {{VALUE}};' ) ) );
                $this->add_control( 'badge_feat_c', array( 'label' => __( 'رنگ نشان ویژه', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-feat-c: {{VALUE}};' ) ) );
                $this->add_control( 'badge_ship_bg', array( 'label' => __( 'پس‌زمینه نشان ارسال', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-ship-bg: {{VALUE}};' ) ) );
                $this->add_control( 'badge_ship_c', array( 'label' => __( 'رنگ نشان ارسال', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-ship-c: {{VALUE}};' ) ) );
                $this->add_control( 'badge_oos_bg', array( 'label' => __( 'پس‌زمینه نشان ناموجود', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-oos-bg: {{VALUE}};' ) ) );
                $this->add_control( 'badge_oos_c', array( 'label' => __( 'رنگ نشان ناموجود', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-oos-c: {{VALUE}};' ) ) );
                $this->add_responsive_control(
                        'badge_radius',
                        array(
                                'label'      => __( 'گردی نشان‌ها', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem', '%' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 0, 'max' => 24 ),
                                        'em'  => array( 'min' => 0, 'max' => 1.5, 'step' => 0.1 ),
                                        'rem' => array( 'min' => 0, 'max' => 1.5, 'step' => 0.1 ),
                                        '%'   => array( 'min' => 0, 'max' => 50 ),
                                ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-badge-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );
                $this->add_responsive_control(
                        'badge_fs',
                        array(
                                'label'      => __( 'اندازه متن نشان', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 8, 'max' => 20 ),
                                        'em'  => array( 'min' => 0.5, 'max' => 1.5, 'step' => 0.05 ),
                                        'rem' => array( 'min' => 0.5, 'max' => 1.5, 'step' => 0.05 ),
                                ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-badge-fs: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->add_control( 'rank_1_bg', array( 'label' => __( 'پس‌زمینه رتبه ۱', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-rank-1-bg: {{VALUE}};' ), 'separator' => 'before' ) );
                $this->add_control( 'rank_1_c', array( 'label' => __( 'رنگ رتبه ۱', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-rank-1-c: {{VALUE}};' ) ) );
                $this->add_control( 'rank_23_bg', array( 'label' => __( 'پس‌زمینه رتبه ۲ و ۳', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-rank-23-bg: {{VALUE}};' ) ) );
                $this->add_control( 'rank_23_c', array( 'label' => __( 'رنگ رتبه ۲ و ۳', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-rank-23-c: {{VALUE}};' ) ) );
                $this->add_control( 'rank_n_bg', array( 'label' => __( 'پس‌زمینه بقیه رتبه‌ها', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-rank-n-bg: {{VALUE}};' ) ) );
                $this->add_control( 'rank_n_c', array( 'label' => __( 'رنگ بقیه رتبه‌ها', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-rank-n-c: {{VALUE}};' ) ) );

                $this->add_control(
                        'badge_reset',
                        array(
                                'label'       => __( 'بازنشانی به تم پیش‌فرض', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'separator'   => 'before',
                                'selectors'   => array(
                                        '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-sale-bg: initial; --hsw-loop-sale-c: initial; --hsw-loop-feat-bg: initial; --hsw-loop-feat-c: initial; --hsw-loop-ship-bg: initial; --hsw-loop-ship-c: initial; --hsw-loop-oos-bg: initial; --hsw-loop-oos-c: initial; --hsw-loop-badge-radius: initial; --hsw-loop-badge-fs: initial; --hsw-loop-rank-1-bg: initial; --hsw-loop-rank-1-c: initial; --hsw-loop-rank-23-bg: initial; --hsw-loop-rank-23-c: initial; --hsw-loop-rank-n-bg: initial; --hsw-loop-rank-n-c: initial;',
                                ),
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Style: stock bar.
         */
        protected function controls_stock_style() {
                $this->start_controls_section(
                        'sec_style_stock',
                        array( 'label' => __( 'استایل نوار موجودی', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_control( 'stock_track', array( 'label' => __( 'رنگ زمینه نوار', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-stock-track: {{VALUE}};' ) ) );
                $this->add_control( 'stock_fill', array( 'label' => __( 'رنگ پرشدن نوار', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-stock-fill: {{VALUE}};' ) ) );
                $this->add_control( 'stock_warn', array( 'label' => __( 'رنگ هشدار کمبود', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-stock-warn: {{VALUE}};' ) ) );

                $this->add_responsive_control(
                        'stock_h',
                        array(
                                'label'      => __( 'ضخامت نوار', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 2, 'max' => 18 ),
                                        'em'  => array( 'min' => 0.1, 'max' => 1.2, 'step' => 0.05 ),
                                        'rem' => array( 'min' => 0.1, 'max' => 1.2, 'step' => 0.05 ),
                                ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-stock-h: {{SIZE}}{{UNIT}};' ),
                        )
                );
                $this->add_responsive_control(
                        'stock_radius',
                        array(
                                'label'      => __( 'گردی نوار', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem', '%' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 0, 'max' => 20 ),
                                        'em'  => array( 'min' => 0, 'max' => 1.5, 'step' => 0.1 ),
                                        'rem' => array( 'min' => 0, 'max' => 1.5, 'step' => 0.1 ),
                                        '%'   => array( 'min' => 0, 'max' => 50 ),
                                ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-stock-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );
                $this->add_responsive_control(
                        'stock_fs',
                        array(
                                'label'      => __( 'اندازه متن موجودی', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 8, 'max' => 18 ),
                                        'em'  => array( 'min' => 0.5, 'max' => 1.3, 'step' => 0.05 ),
                                        'rem' => array( 'min' => 0.5, 'max' => 1.3, 'step' => 0.05 ),
                                ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-stock-fs: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->add_control(
                        'stock_reset',
                        array(
                                'label'       => __( 'بازنشانی به تم پیش‌فرض', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'separator'   => 'before',
                                'selectors'   => array(
                                        '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-stock-track: initial; --hsw-loop-stock-fill: initial; --hsw-loop-stock-warn: initial; --hsw-loop-stock-h: initial; --hsw-loop-stock-radius: initial; --hsw-loop-stock-fs: initial;',
                                ),
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Style: price + old price.
         */
        protected function controls_price_style() {
                $this->start_controls_section(
                        'sec_style_price',
                        array( 'label' => __( 'استایل قیمت', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_group_control(
                        Group_Control_Typography::get_type(),
                        array(
                                'name'     => 'price_typo',
                                'label'    => __( 'تایپوگرافی قیمت', 'hamishop-elementor-widgets' ),
                                'selector' => '{{WRAPPER}} .hsw-loop__price',
                        )
                );
                $this->add_control( 'price_c', array( 'label' => __( 'رنگ قیمت جدید', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-price-c: {{VALUE}};' ) ) );
                $this->add_control( 'price_old_c', array( 'label' => __( 'رنگ قیمت قبلی', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-old-c: {{VALUE}};' ) ) );

                $this->add_group_control(
                        Group_Control_Typography::get_type(),
                        array(
                                'name'      => 'old_price_typo',
                                'label'     => __( 'تایپوگرافی قیمت قبلی', 'hamishop-elementor-widgets' ),
                                'selector'  => '{{WRAPPER}} .hsw-loop__old-price',
                                'separator' => 'before',
                        )
                );
                $this->add_control( 'price_currency_c', array( 'label' => __( 'رنگ واحد پول', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-currency-c: {{VALUE}};' ) ) );
                $this->add_control( 'price_label_c', array( 'label' => __( 'رنگ برچسب قیمت', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-price-label-c: {{VALUE}};' ) ) );

                $this->add_control(
                        'price_reset',
                        array(
                                'label'       => __( 'بازنشانی به تم پیش‌فرض', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'separator'   => 'before',
                                'selectors'   => array(
                                        '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-price-c: initial; --hsw-loop-old-c: initial; --hsw-loop-currency-c: initial; --hsw-loop-price-label-c: initial;',
                                ),
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Style: add-to-cart button.
         */
        protected function controls_button_style() {
                $this->start_controls_section(
                        'sec_style_button',
                        array( 'label' => __( 'استایل دکمه خرید', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_control( 'btn_bg', array( 'label' => __( 'پس‌زمینه دکمه', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-btn-bg: {{VALUE}};' ) ) );
                $this->add_control( 'btn_bgh', array( 'label' => __( 'پس‌زمینه (هاور)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-btn-bg-h: {{VALUE}};' ) ) );
                $this->add_control( 'btn_c', array( 'label' => __( 'رنگ دکمه', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-btn-c: {{VALUE}};' ) ) );
                $this->add_control( 'btn_ch', array( 'label' => __( 'رنگ (هاور)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-btn-c-h: {{VALUE}};' ) ) );
                $this->add_responsive_control(
                        'btn_radius',
                        array(
                                'label'      => __( 'گردی دکمه', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem', '%' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 0, 'max' => 28 ),
                                        'em'  => array( 'min' => 0, 'max' => 2, 'step' => 0.1 ),
                                        'rem' => array( 'min' => 0, 'max' => 2, 'step' => 0.1 ),
                                        '%'   => array( 'min' => 0, 'max' => 50 ),
                                ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-btn-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->a_button(
                        'add',
                        __( 'تنظیمات کامل دکمه', 'hamishop-elementor-widgets' ),
                        '{{WRAPPER}} .hsw-loop__btn',
                        array( 'size' => 11.5, 'radius' => 9 )
                );

                $this->add_control(
                        'btn_reset',
                        array(
                                'label'       => __( 'بازنشانی به تم پیش‌فرض', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'separator'   => 'before',
                                'selectors'   => array(
                                        '{{WRAPPER}} .hsw-loop__card' => '--hsw-loop-btn-bg: initial; --hsw-loop-btn-bg-h: initial; --hsw-loop-btn-c: initial; --hsw-loop-btn-c-h: initial; --hsw-loop-btn-radius: initial;',
                                ),
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Style: lead banner (Section 1).
         */
        protected function controls_lead_style() {
                $this->start_controls_section(
                        'sec_style_lead',
                        array( 'label' => __( 'استایل بنر شروع', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_control( 'lead_bg', array( 'label' => __( 'پس‌زمینه بنر', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__lead' => '--hsw-loop-lead-bg: {{VALUE}};' ) ) );
                $this->add_control( 'lead_border', array( 'label' => __( 'حاشیه بنر', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__lead' => '--hsw-loop-lead-border: {{VALUE}};' ) ) );
                $this->add_control( 'lead_c', array( 'label' => __( 'رنگ عنوان بنر', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__lead' => '--hsw-loop-lead-c: {{VALUE}};' ) ) );
                $this->add_control( 'lead_sub_c', array( 'label' => __( 'رنگ زیرعنوان', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__lead' => '--hsw-loop-lead-sub-c: {{VALUE}};' ) ) );
                $this->add_control( 'lead_btn_bg', array( 'label' => __( 'پس‌زمینه دکمه', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__lead' => '--hsw-loop-lead-btn-bg: {{VALUE}};' ) ) );
                $this->add_control( 'lead_btn_c', array( 'label' => __( 'رنگ دکمه', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__lead' => '--hsw-loop-lead-btn-c: {{VALUE}};' ) ) );
                $this->add_control( 'lead_btn_ch', array( 'label' => __( 'رنگ دکمه (هاور)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__lead' => '--hsw-loop-lead-btn-c-h: {{VALUE}};' ) ) );
                $this->add_control( 'lead_btn_bgh', array( 'label' => __( 'پس‌زمینه دکمه (هاور)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__lead' => '--hsw-loop-lead-btn-bg-h: {{VALUE}};' ) ) );

                $this->add_group_control(
                        Group_Control_Typography::get_type(),
                        array(
                                'name'      => 'lead_title_typo',
                                'label'     => __( 'تایپوگرافی عنوان بنر', 'hamishop-elementor-widgets' ),
                                'selector'  => '{{WRAPPER}} .hsw-loop__lead-title',
                                'separator' => 'before',
                        )
                );

                $this->add_responsive_control(
                        'lead_w',
                        array(
                                'label'          => __( 'عرض بنر', 'hamishop-elementor-widgets' ),
                                'type'           => Controls_Manager::SLIDER,
                                'size_units'     => array( 'px', 'em', 'rem', '%' ),
                                'range'          => array(
                                        'px'  => array( 'min' => 100, 'max' => 340 ),
                                        'em'  => array( 'min' => 6, 'max' => 24, 'step' => 0.5 ),
                                        'rem' => array( 'min' => 6, 'max' => 24, 'step' => 0.5 ),
                                        '%'   => array( 'min' => 20, 'max' => 100 ),
                                ),
                                'default'        => array( 'size' => 208, 'unit' => 'px' ),
                                'mobile_default' => array( 'size' => 160, 'unit' => 'px' ),
                                'selectors'      => array( '{{WRAPPER}} .hsw-loop__lead' => '--hsw-loop-lead-w: {{SIZE}}{{UNIT}};' ),
                        )
                );
                $this->add_responsive_control(
                        'lead_radius',
                        array(
                                'label'      => __( 'گردی بنر', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem', '%' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 0, 'max' => 40 ),
                                        'em'  => array( 'min' => 0, 'max' => 2.5, 'step' => 0.1 ),
                                        'rem' => array( 'min' => 0, 'max' => 2.5, 'step' => 0.1 ),
                                        '%'   => array( 'min' => 0, 'max' => 50 ),
                                ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop__lead' => '--hsw-loop-lead-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->add_control(
                        'lead_reset',
                        array(
                                'label'       => __( 'بازنشانی به تم پیش‌فرض', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'separator'   => 'before',
                                'selectors'   => array(
                                        '{{WRAPPER}} .hsw-loop__lead' => '--hsw-loop-lead-bg: initial; --hsw-loop-lead-border: initial; --hsw-loop-lead-c: initial; --hsw-loop-lead-sub-c: initial; --hsw-loop-lead-btn-bg: initial; --hsw-loop-lead-btn-c: initial; --hsw-loop-lead-btn-bg-h: initial; --hsw-loop-lead-btn-c-h: initial; --hsw-loop-lead-w: initial; --hsw-loop-lead-radius: initial;',
                                ),
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Content: tab list for the popular widget.
         */
        protected function controls_tabs() {
                $this->start_controls_section(
                        'sec_tabs',
                        array( 'label' => __( 'تب‌های فیلتر', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->add_control(
                        'show_tabs',
                        array(
                                'label'   => __( 'نمایش تب‌ها', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $repeater = new Repeater();
                $repeater->add_control(
                        'tab_label',
                        array(
                                'label'   => __( 'عنوان تب', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'محبوب‌ترین‌ها', 'hamishop-elementor-widgets' ),
                        )
                );
                $repeater->add_control(
                        'tab_source',
                        array(
                                'label'   => __( 'منبع', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => 'rating',
                                'options' => array(
                                        'rating'       => __( 'محبوب‌ترین (امتیاز)', 'hamishop-elementor-widgets' ),
                                        'popular'      => __( 'محبوب‌ترین (امتیاز + فروش)', 'hamishop-elementor-widgets' ),
                                        'newest'       => __( 'جدیدترین', 'hamishop-elementor-widgets' ),
                                        'discount'     => __( 'بیشترین تخفیف', 'hamishop-elementor-widgets' ),
                                        'sale'         => __( 'تخفیف‌دارها', 'hamishop-elementor-widgets' ),
                                        'best_selling' => __( 'پرفروش‌ترین', 'hamishop-elementor-widgets' ),
                                        'featured'     => __( 'منتخب (Featured)', 'hamishop-elementor-widgets' ),
                                ),
                        )
                );

                $this->add_control(
                        'tabs',
                        array(
                                'label'       => __( 'تب‌ها', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $repeater->get_controls(),
                                'default'     => array(
                                        array( 'tab_label' => __( 'محبوب‌ترین‌ها', 'hamishop-elementor-widgets' ), 'tab_source' => 'rating' ),
                                        array( 'tab_label' => __( 'جدیدترین‌ها', 'hamishop-elementor-widgets' ), 'tab_source' => 'newest' ),
                                        array( 'tab_label' => __( 'بیشترین تخفیف', 'hamishop-elementor-widgets' ), 'tab_source' => 'discount' ),
                                ),
                                'title_field' => '{{{ tab_label }}}',
                                'condition'   => array( 'show_tabs' => 'yes' ),
                        )
                );

                $this->add_control(
                        'hdr_note',
                        array(
                                'label'       => __( 'یادداشت کنار عنوان (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'بر اساس امتیاز کاربران', 'hamishop-elementor-widgets' ),
                                'description' => __( 'در موبایل کنار عنوان نمایش داده می‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Style: tabs.
         */
        protected function controls_tabs_style() {
                $this->start_controls_section(
                        'sec_style_tabs',
                        array( 'label' => __( 'استایل تب‌ها', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_control( 'tabs_bg', array( 'label' => __( 'پس‌زمینه کانتینر', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__tabs' => '--hsw-loop-tabs-bg: {{VALUE}};' ) ) );
                $this->add_control( 'tabs_border', array( 'label' => __( 'حاشیه کانتینر', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__tabs' => '--hsw-loop-tabs-border: {{VALUE}};' ) ) );
                $this->add_control( 'tab_active_bg', array( 'label' => __( 'پس‌زمینه تب فعال', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__tabs' => '--hsw-loop-tab-active-bg: {{VALUE}};' ) ) );
                $this->add_control( 'tab_active_c', array( 'label' => __( 'رنگ تب فعال', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__tabs' => '--hsw-loop-tab-active-c: {{VALUE}};' ) ) );
                $this->add_control( 'tab_c', array( 'label' => __( 'رنگ تب غیرفعال', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__tabs' => '--hsw-loop-tab-c: {{VALUE}};' ) ) );
                $this->add_control( 'tab_hover_c', array( 'label' => __( 'رنگ تب (هاور)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__tabs' => '--hsw-loop-tab-hover-c: {{VALUE}};' ) ) );

                $this->add_group_control(
                        Group_Control_Typography::get_type(),
                        array(
                                'name'     => 'tab_typo',
                                'label'    => __( 'تایپوگرافی تب‌ها', 'hamishop-elementor-widgets' ),
                                'selector' => '{{WRAPPER}} .hsw-loop__tab',
                        )
                );

                $this->add_responsive_control(
                        'tab_pad',
                        array(
                                'label'      => __( 'پدینگ تب', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 2, 'max' => 24 ),
                                        'em'  => array( 'min' => 0.1, 'max' => 1.5, 'step' => 0.05 ),
                                        'rem' => array( 'min' => 0.1, 'max' => 1.5, 'step' => 0.05 ),
                                ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop__tabs' => '--hsw-loop-tab-pad: {{SIZE}}{{UNIT}};' ),
                        )
                );
                $this->add_responsive_control(
                        'tab_radius',
                        array(
                                'label'      => __( 'گردی تب', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem', '%' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 0, 'max' => 28 ),
                                        'em'  => array( 'min' => 0, 'max' => 2, 'step' => 0.1 ),
                                        'rem' => array( 'min' => 0, 'max' => 2, 'step' => 0.1 ),
                                        '%'   => array( 'min' => 0, 'max' => 50 ),
                                ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop__tabs' => '--hsw-loop-tab-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->add_control(
                        'tabs_reset',
                        array(
                                'label'       => __( 'بازنشانی به تم پیش‌فرض', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'separator'   => 'before',
                                'selectors'   => array(
                                        '{{WRAPPER}} .hsw-loop__tabs' => '--hsw-loop-tabs-bg: initial; --hsw-loop-tabs-border: initial; --hsw-loop-tab-active-bg: initial; --hsw-loop-tab-active-c: initial; --hsw-loop-tab-c: initial; --hsw-loop-tab-hover-c: initial; --hsw-loop-tab-pad: initial; --hsw-loop-tab-radius: initial;',
                                ),
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Style: lazy-tab skeleton.
         */
        protected function controls_skeleton_style() {
                $this->start_controls_section(
                        'sec_style_skeleton',
                        array( 'label' => __( 'استایل اسکلتون تب‌ها', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_control( 'skel_bg', array( 'label' => __( 'رنگ پایه اسکلتون', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__skeleton' => '--hsw-loop-skel-bg: {{VALUE}};' ) ) );
                $this->add_control( 'skel_shine', array( 'label' => __( 'رنگ درخشش', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop__skeleton' => '--hsw-loop-skel-shine: {{VALUE}};' ) ) );
                $this->add_responsive_control(
                        'skel_radius',
                        array(
                                'label'      => __( 'گردی اسکلتون', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem', '%' ),
                                'range'      => array(
                                        'px'  => array( 'min' => 0, 'max' => 30 ),
                                        'em'  => array( 'min' => 0, 'max' => 2, 'step' => 0.1 ),
                                        'rem' => array( 'min' => 0, 'max' => 2, 'step' => 0.1 ),
                                        '%'   => array( 'min' => 0, 'max' => 50 ),
                                ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop__skeleton' => '--hsw-loop-skel-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );
                $this->add_control(
                        'skel_speed',
                        array(
                                'label'      => __( 'سرعت درخشش (ثانیه)', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'range'      => array( 'px' => array( 'min' => 0.6, 'max' => 3, 'step' => 0.1 ) ),
                                'default'    => array( 'size' => 1.4 ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop__skeleton' => '--hsw-loop-skel-speed: {{SIZE}}s;' ),
                        )
                );

                $this->add_control(
                        'skel_reset',
                        array(
                                'label'       => __( 'بازنشانی به تم پیش‌فرض', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'separator'   => 'before',
                                'selectors'   => array(
                                        '{{WRAPPER}} .hsw-loop__skeleton' => '--hsw-loop-skel-bg: initial; --hsw-loop-skel-shine: initial; --hsw-loop-skel-radius: initial; --hsw-loop-skel-speed: initial;',
                                ),
                        )
                );

                $this->end_controls_section();
        }

        /* =========================================================================
         *  OPTION LISTS
         * ====================================================================== */

        public static function icon_options() {
                return array(
                        'local_fire_department' => 'آتش (شگفت‌انگیز)',
                        'bolt'                  => 'برق (شگفت‌انگیز موبایل)',
                        'trending_up'           => 'روند صعودی (پرفروش)',
                        'auto_awesome'          => 'درخشش (منتخب)',
                        'favorite'              => 'قلب (محبوب)',
                        'star'                  => 'ستاره',
                        'shopping_cart'         => 'سبد خرید',
                        'shopping_bag'          => 'کیف خرید',
                        'add_shopping_cart'     => 'افزودن به سبد',
                        'timer'                 => 'تایمر',
                        'local_shipping'        => 'ارسال',
                        'notifications'         => 'اعلان',
                        'chevron_left'          => 'چپ',
                        'chevron_right'         => 'راست',
                        'arrow_back'            => 'بازگشت',
                        'arrow_forward'         => 'جلو',
                );
        }

        /**
         * Product options for SELECT2 (latest 300).
         */
        public static function product_options() {
                static $cache = null;
                if ( null !== $cache ) {
                        return $cache;
                }
                $cache = array();
                if ( class_exists( 'WooCommerce' ) ) {
                        $products = wc_get_products(
                                array(
                                        'limit'   => 300,
                                        'status'  => 'publish',
                                        'orderby' => 'date',
                                        'order'   => 'DESC',
                                )
                        );
                        foreach ( $products as $p ) {
                                $cache[ $p->get_id() ] = sprintf( '%s (#%d)', wp_strip_all_tags( $p->get_name() ), $p->get_id() );
                        }
                }
                return $cache;
        }

        /**
         * Category options for SELECT2.
         */
        public static function term_options() {
                static $cache = null;
                if ( null !== $cache ) {
                        return $cache;
                }
                $cache = array();
                if ( class_exists( 'WooCommerce' ) ) {
                        $terms = get_terms(
                                array(
                                        'taxonomy'   => 'product_cat',
                                        'hide_empty' => false,
                                        'number'     => 200,
                                )
                        );
                        if ( ! is_wp_error( $terms ) ) {
                                foreach ( $terms as $t ) {
                                        $cache[ $t->term_id ] = $t->name;
                                }
                        }
                }
                return $cache;
        }

        /**
         * Translate widget settings into renderer args.
         *
         * Static so the REST tab fragment can render exactly like the first
         * server-rendered tab (same badge texts, button variant, stock mode…).
         *
         * @param array $s Elementor settings.
         * @return array
         */
        public static function settings_to_args( $s ) {
                $s   = is_array( $s ) ? $s : array();
                $ttl = max( 0, (int) ( $s['cache_ttl'] ?? 300 ) );

                return wp_parse_args(
                        array(
                                'show_brand'        => $s['show_brand'] ?? 'yes',
                                'brand_attr'        => $s['brand_attr'] ?? 'pa_brand',
                                'show_rating'       => $s['show_rating'] ?? 'yes',
                                'show_rating_count' => $s['show_rating_count'] ?? 'yes',
                                'show_old'          => $s['show_old'] ?? 'yes',
                                'show_stock'        => $s['show_stock'] ?? 'yes',
                                'show_rank'         => $s['show_rank'] ?? 'yes',
                                'show_badge'        => $s['show_badge'] ?? 'yes',
                                'stock_mode'        => $s['stock_mode'] ?? 'low',
                                'stock_low'         => (int) ( $s['stock_low'] ?? 3 ),
				'cards'             => (float) ( $s['cards']['size'] ?? 4 ),
				'cards_tablet'      => (float) ( $s['cards_tablet']['size'] ?? 3 ),
				'cards_mobile'      => (float) ( $s['cards_mobile']['size'] ?? 2.2 ),
				'timer_end_action'  => (string) ( $s['cd_end_action'] ?? 'hide_timer' ),
				'timer_end_text'    => (string) ( $s['cd_end_text'] ?? __( 'این پیشنهاد به پایان رسید', 'hamishop-elementor-widgets' ) ),
                                'btn_variant'       => $s['btn_variant'] ?? 'text',
                                'price_label'       => $s['price_label'] ?? '',
                                'currency'          => $s['currency'] ?? __( 'تومان', 'hamishop-elementor-widgets' ),
                                'txt_sale'          => $s['txt_sale'] ?? __( 'تخفیف', 'hamishop-elementor-widgets' ),
                                'txt_feat'          => $s['txt_feat'] ?? __( 'ویژه', 'hamishop-elementor-widgets' ),
                                'txt_ship'          => $s['txt_ship'] ?? __( 'ارسال رایگان', 'hamishop-elementor-widgets' ),
                                'txt_oos'           => $s['txt_oos'] ?? __( 'اتمام موجودی', 'hamishop-elementor-widgets' ),
                                'txt_notify'        => $s['txt_notify'] ?? __( 'خبرم کن', 'hamishop-elementor-widgets' ),
                                'txt_add'           => $s['txt_add'] ?? __( 'افزودن به سبد خرید', 'hamishop-elementor-widgets' ),
                                'txt_buy'           => $s['txt_buy'] ?? __( 'خرید', 'hamishop-elementor-widgets' ),
                                'ico_add'           => $s['ico_add'] ?? 'shopping_cart',
                                'free_ship_min'     => (float) ( $s['free_ship_min'] ?? 0 ),
                                'html_cache'        => $ttl > 0,
                                'cache_ttl'         => $ttl,
                                'first_priority'    => true,
                        ),
                        self::default_args()
                );
        }

        /**
         * Translate widget settings into renderer args.
         */
        protected function renderer_args() {
                $args = self::settings_to_args( $this->effective_settings() );

                if ( $this->is_editor_preview() ) {
                        $args['html_cache'] = false;
                }

                return $args;
        }

        /**
         * Elementor editor / preview renders must not come from the HTML cache.
         *
         * @return bool
         */
        protected function is_editor_preview() {
                if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
                        return false;
                }
                $plugin = \Elementor\Plugin::$instance;
                if ( isset( $plugin->editor ) && $plugin->editor && $plugin->editor->is_edit_mode() ) {
                        return true;
                }
                if ( isset( $plugin->preview ) && $plugin->preview && $plugin->preview->is_preview_mode() ) {
                        return true;
                }
                return false;
        }

        /**
         * Resolve products from the source controls.
         *
         * @param array $args Optional overrides: source, count, days, term_id.
         * @return WC_Product[]
         */
        protected function resolve_products( $args = array() ) {
                $s      = $this->effective_settings();
                $source = (string) ( $args['source'] ?? ( $s['source'] ?? 'sale' ) );
                if ( ! in_array( $source, self::loop_sources(), true ) ) {
                        $source = 'sale';
                }

                $count = isset( $args['count'] ) ? (int) $args['count'] : (int) ( $s['count']['size'] ?? 8 );
                $count = max( 1, min( 60, $count ) );

                $ids = $s['source_ids'] ?? array();
                if ( is_string( $ids ) ) {
                        $ids = explode( ',', $ids );
                }
                $ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );

                $term_id = isset( $args['term_id'] ) ? (int) $args['term_id'] : (int) ( $s['source_term'] ?? 0 );
                $days    = isset( $args['days'] ) ? absint( $args['days'] ) : 0;
                $ttl     = max( 0, (int) ( $s['cache_ttl'] ?? 300 ) );

                $exclude = 'yes' === ( $s['exclude_oos'] ?? 'yes' ) && 'hsw-popular' !== $this->get_name();

                return HSW_Plugin::instance()->products->query(
                        array(
                                'source'      => $source,
                                'count'       => $count,
                                'ids'         => $ids,
                                'term_id'     => $term_id,
                                'exclude_oos' => $exclude,
                                'days'        => $days,
                                'ttl'         => $ttl,
                        )
                );
        }

        /**
         * Alignment + text direction (+ optional line-height) for long copy.
         *
         * The palette owns colours; this owns the reading experience of
         * descriptions and article bodies: start / center / end / justify,
         * an explicit RTL or LTR override, and a line-height slider.
         *
         * `text-align: start|end` follows the document direction, so «شروع»
         * means right on an RTL site and left on an LTR one — that is why the
         * choices are labelled by position, not by left/right.
         *
         * @param string $id       Control id prefix (must be unique per widget).
         * @param string $label    Section heading.
         * @param string $selector CSS selector (usually `{{WRAPPER}} .foo`).
         * @param array  $args     align (default justify), dir (inherit), lh (1.9|false).
         */
        protected function a_align( $id, $label, $selector, $args = array() ) {
                $args = wp_parse_args(
                        $args,
                        array(
                                'align' => 'justify',
                                'dir'   => 'inherit',
                                'lh'    => 1.9,
                        )
                );

                $this->add_responsive_control(
                        $id . '_al_h',
                        array(
                                'label'     => $label,
                                'type'      => Controls_Manager::HEADING,
                                'separator' => 'before',
                        )
                );

                $this->add_responsive_control(
                        $id . '_al',
                        array(
                                'label'     => __( 'تراز متن', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::CHOOSE,
                                'options'   => array(
                                        'start'   => array(
                                                'title' => __( 'شروع سطر (راست در RTL)', 'hamishop-elementor-widgets' ),
                                                'icon'  => 'eicon-text-align-left',
                                        ),
                                        'center'  => array(
                                                'title' => __( 'وسط‌چین', 'hamishop-elementor-widgets' ),
                                                'icon'  => 'eicon-text-align-center',
                                        ),
                                        'end'     => array(
                                                'title' => __( 'پایان سطر (چپ در RTL)', 'hamishop-elementor-widgets' ),
                                                'icon'  => 'eicon-text-align-right',
                                        ),
                                        'justify' => array(
                                                'title' => __( 'هم‌تراز دو طرف', 'hamishop-elementor-widgets' ),
                                                'icon'  => 'eicon-text-align-justify',
                                        ),
                                ),
                                'default'   => $args['align'],
                                'selectors' => array( $selector => 'text-align: {{VALUE}};' ),
                        )
                );

                $this->add_control(
                        $id . '_dir',
                        array(
                                'label'     => __( 'جهت متن', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SELECT,
                                'options'   => array(
                                        'inherit' => __( 'مطابق قالب سایت', 'hamishop-elementor-widgets' ),
                                        'rtl'     => __( 'راست به چپ (RTL)', 'hamishop-elementor-widgets' ),
                                        'ltr'     => __( 'چپ به راست (LTR)', 'hamishop-elementor-widgets' ),
                                ),
                                'default'   => $args['dir'],
                                'selectors' => array( $selector => 'direction: {{VALUE}};' ),
                        )
                );

                if ( false !== $args['lh'] ) {
                        $this->add_responsive_control(
                                $id . '_lh',
                                array(
                                        'label'      => __( 'ارتفاع خط', 'hamishop-elementor-widgets' ),
                                        'type'       => Controls_Manager::SLIDER,
                                        'size_units' => array( 'em', 'px' ),
                                        'range'      => array(
                                                'em' => array(
                                                        'min'  => 1,
                                                        'max'  => 3,
                                                        'step' => 0.05,
                                                ),
                                                'px' => array(
                                                        'min' => 14,
                                                        'max' => 64,
                                                ),
                                        ),
                                        'default'    => array(
                                                'size' => $args['lh'],
                                                'unit' => 'em',
                                        ),
                                        'selectors'  => array( $selector => 'line-height: {{SIZE}}{{UNIT}};' ),
                                )
                        );
                }
        }

        /**
         * Standard typography block: font size/weight/line-height + text colour.
         *
         * Companion to a_align(). Every text element in a widget should go
         * through one of these two so the style tabs stay predictable.
         *
         * @param string $id       Control id prefix.
         * @param string $label    Section heading.
         * @param string $selector CSS selector.
         * @param array  $args     size (px), weight (bool), color (bool), lh (em|false).
         */
        protected function a_typo( $id, $label, $selector, $args = array() ) {
                $args = wp_parse_args(
                        $args,
                        array(
                                'size'   => 14,
                                'weight' => true,
                                'color'  => true,
                                'lh'     => false,
                        )
                );

                $this->add_control(
                        $id . '_typo_h',
                        array(
                                'label'     => $label,
                                'type'      => Controls_Manager::HEADING,
                                'separator' => 'before',
                        )
                );

                $fields = array(
                        'font_size' => array(
                                'default' => array(
                                        'size' => $args['size'],
                                        'unit' => 'px',
                                ),
                        ),
                );
                if ( false !== $args['lh'] ) {
                        $fields['line_height'] = array(
                                'default' => array(
                                        'size' => $args['lh'],
                                        'unit' => 'em',
                                ),
                        );
                }

                $group = array(
                        'name'           => $id . '_typo',
                        'selector'       => $selector,
                        'fields_options' => $fields,
                );
                if ( ! $args['weight'] ) {
                        $group['exclude'] = array( 'font_weight' );
                }

                $this->add_group_control( Group_Control_Typography::get_type(), $group );

                if ( $args['color'] ) {
                        $this->add_control(
                                $id . '_c',
                                array(
                                        'label'     => __( 'رنگ متن', 'hamishop-elementor-widgets' ),
                                        'type'      => Controls_Manager::COLOR,
                                        'selectors' => array( $selector => 'color: {{VALUE}};' ),
                                )
                        );
                }
        }

        /**
         * Standard button block: background, text colour, hover, border, radius,
         * shadow and the button's own typography.
         *
         * Buttons used to inherit their colour from the palette with no way to
         * change a single one of them; this gives every call-to-action the same
         * control set (normal + hover) so nothing is hard-coded.
         *
         * @param string $id       Control id prefix.
         * @param string $label    Section heading.
         * @param string $selector CSS selector of the button.
         * @param array  $args     size (font px), radius (px).
         */
        protected function a_button( $id, $label, $selector, $args = array() ) {
                $args = wp_parse_args(
                        $args,
                        array(
                                'size'    => 12,
                                'radius'  => 9,
                                /*
                                 * Optional CSS variable to mirror on the wrapper
                                 * (e.g. `--hsw-cart-bg`): the quantity stepper and
                                 * any sibling reads it, so +/− follow the button
                                 * colour automatically instead of being kept in
                                 * sync by hand.
                                 */
                                'css_var' => '',
                        )
                );

                $hover = $selector . ':hover, ' . $selector . ':focus-visible';
                $var   = (string) $args['css_var'];

                $bg_selectors = array( $selector => 'background-color: {{VALUE}}; background-image: none;' );
                $c_selectors  = array( $selector => 'color: {{VALUE}};' );
                $h_selectors  = array( $hover => 'background-color: {{VALUE}}; background-image: none;' );
                if ( '' !== $var ) {
                        $bg_selectors['{{WRAPPER}}'] = $var . ': {{VALUE}};';
                        $c_selectors['{{WRAPPER}}']  = $var . '-c: {{VALUE}};';
                        $h_selectors['{{WRAPPER}}']  = $var . '-hover: {{VALUE}};';
                }

                $this->add_control(
                        $id . '_btn_h',
                        array(
                                'label'     => $label,
                                'type'      => Controls_Manager::HEADING,
                                'separator' => 'before',
                        )
                );

                $this->start_controls_tabs( $id . '_btn_tabs' );

                /* ---- normal ---- */
                $this->start_controls_tab(
                        $id . '_btn_tab_n',
                        array( 'label' => __( 'عادی', 'hamishop-elementor-widgets' ) )
                );

                $this->add_control(
                        $id . '_btn_bg',
                        array(
                                'label'     => __( 'پس‌زمینه دکمه', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => $bg_selectors,
                        )
                );

                $this->add_control(
                        $id . '_btn_c',
                        array(
                                'label'     => __( 'رنگ متن دکمه', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => $c_selectors,
                        )
                );

                $this->end_controls_tab();

                /* ---- hover ---- */
                $this->start_controls_tab(
                        $id . '_btn_tab_h',
                        array( 'label' => __( 'هاور', 'hamishop-elementor-widgets' ) )
                );

                $this->add_control(
                        $id . '_btn_bg_h',
                        array(
                                'label'     => __( 'پس‌زمینه (هاور)', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => $h_selectors,
                        )
                );

                $this->add_control(
                        $id . '_btn_c_h',
                        array(
                                'label'     => __( 'رنگ متن (هاور)', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( $hover => 'color: {{VALUE}};' ),
                        )
                );

                $this->end_controls_tab();
                $this->end_controls_tabs();

                $this->add_group_control(
                        Group_Control_Typography::get_type(),
                        array(
                                'name'           => $id . '_btn_typo',
                                'label'          => __( 'تایپوگرافی دکمه', 'hamishop-elementor-widgets' ),
                                'selector'       => $selector,
                                'fields_options' => array(
                                        'font_size' => array(
                                                'default' => array(
                                                        'size' => $args['size'],
                                                        'unit' => 'px',
                                                ),
                                        ),
                                ),
                                'separator'      => 'before',
                        )
                );

                $this->add_group_control(
                        Group_Control_Border::get_type(),
                        array(
                                'name'     => $id . '_btn_bd',
                                'label'    => __( 'حاشیه دکمه', 'hamishop-elementor-widgets' ),
                                'selector' => $selector,
                        )
                );

                $this->add_responsive_control(
                        $id . '_btn_r',
                        array(
                                'label'      => __( 'گردی گوشه‌ها', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
                                'default'    => array(
                                        'size' => $args['radius'],
                                        'unit' => 'px',
                                ),
                                'selectors'  => array( $selector => 'border-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->add_responsive_control(
                        $id . '_btn_pad',
                        array(
                                'label'      => __( 'فاصله داخلی', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::DIMENSIONS,
                                'size_units' => array( 'px' ),
                                'selectors'  => array(
                                        $selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                                ),
                        )
                );

                $this->add_group_control(
                        Group_Control_Box_Shadow::get_type(),
                        array(
                                'name'     => $id . '_btn_sh',
                                'label'    => __( 'سایه دکمه', 'hamishop-elementor-widgets' ),
                                'selector' => $selector,
                        )
                );
        }
        /**
         * Single color control bound to a selector.
         */
        protected function a_color( $id, $label, $selector, $prop = 'color', $args = array() ) {
                // $prop may be a single property ("color") or a full declaration
                // block for palette tokens ("--var: {{VALUE}}; …"). Only append
                // the value placeholder when it is not already part of $prop;
                // otherwise the generated CSS gets a stray ": value;" fragment.
                $decls = false !== strpos( $prop, '{{VALUE}}' ) ? $prop : $prop . ': {{VALUE}};';

                $this->add_control(
                        $id,
                        wp_parse_args(
                                $args,
                                array(
                                        'label'     => $label,
                                        'type'      => Controls_Manager::COLOR,
                                        'selectors' => array( $selector => $decls ),
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
                /*
                 * px / em / rem. `rem` respects the visitor's browser font-size
                 * setting, `em` scales with the parent — both matter on Persian
                 * storefronts where the theme often sets a larger base size than
                 * the design assumed. The control id and its px default are
                 * unchanged, so saved pages keep their exact value.
                 */
                $this->add_responsive_control(
                        $id . '_s',
                        array(
                                'label'      => __( 'اندازه فونت', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px', 'em', 'rem' ),
                                'range'      => array(
                                        'px'  => $args['size'],
                                        'em'  => array( 'min' => 0.5, 'max' => 4, 'step' => 0.05 ),
                                        'rem' => array( 'min' => 0.5, 'max' => 4, 'step' => 0.05 ),
                                ),
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
