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
                $this->start_controls_section(
                        'hsw_style_' . $prefix,
                        array(
                                'label' => $label,
                                'tab'   => Controls_Manager::TAB_STYLE,
                        )
                );

                $controls = HSW_Composite::controls( $widget_class );
                $skip     = array( 'section', 'tabs', 'tab', 'is_group', 'group_prefix', 'group_type', 'dynamic', 'frontend_available', 'render_type', 'name', 'inner', 'group_fields' );

                foreach ( $controls as $id => $source ) {
                        if ( ! is_array( $source ) || ! isset( $source['section'] ) || $section_id !== $source['section'] ) {
                                continue;
                        }
                        if ( preg_match( '/_(tablet|mobile)$/', (string) $id ) ) {
                                continue;
                        }

                        $args = $source;
                        foreach ( $skip as $key ) {
                                unset( $args[ $key ] );
                        }
                        if ( empty( $args['type'] ) || in_array( $args['type'], array( 'section', 'tab' ), true ) ) {
                                continue;
                        }

                        $this->add_control( $prefix . $id, $args );
                }

                $this->end_controls_section();
        }

        protected function part_style_section( $prefix, $label, $widget_class, $section_id = 'sec_style' ) {
                $this->start_controls_section(
                        'hsw_style_' . $prefix,
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
         * @param string       $type     deal|best|popular.
         * @param WC_Product[] $products Products.
         * @param array        $args     Options subset (badge texts, flags...).
         */
        public static function cards_html( $type, $products, $args = array() ) {
                $args = wp_parse_args( $args, self::default_args() );
                $html = '';
                $i    = 0;
                foreach ( $products as $product ) {
                        $i++;
                        if ( 'deal' === $type ) {
                                $html .= self::card_deal( $product, $args, $i );
                        } elseif ( 'best' === $type ) {
                                $html .= self::card_best( $product, $args, $i );
                        } else {
                                $html .= self::card_pop( $product, $args, $i );
                        }
                }
                return $html;
        }

        /**
         * Media block (image + badge + optional OOS overlay).
         */
        protected static function media_html( $product, $args, $type ) {
                $badge  = '';
                $oos    = ! $product->is_in_stock();
                $images = HSW_Helpers::image( $product );

                if ( ! $oos && ! HSW_Helpers::is_call_for_price( $product ) ) {
                        $percent = HSW_Helpers::discount_percent( $product );
                        if ( 'pop' === $type && ! empty( $args['show_badge'] ) ) {
                                $text_full = '';
                                $mod       = '';
                                $featured  = $product->is_featured();
                                $ship_min  = (float) ( $args['free_ship_min'] ?? 0 );
                                if ( $featured ) {
                                        $mod       = 'feat';
                                        $text_full = $args['txt_feat'];
                                } elseif ( $percent > 0 ) {
                                        $mod       = 'sale';
                                        $text_full = HSW_Helpers::fa_num( $percent ) . '٪ ' . $args['txt_sale'];
                                } elseif ( $ship_min > 0 && (float) $product->get_price() >= $ship_min ) {
                                        $mod       = 'ship';
                                        $text_full = $args['txt_ship'];
                                }
                                if ( $mod ) {
                                        $short = ( 'sale' === $mod ) ? HSW_Helpers::fa_num( $percent ) . '٪' : $text_full;
                                        $icon  = ( 'ship' === $mod ) ? HSW_Helpers::icon( 'local_shipping' ) : '';
                                        $badge = '<span class="hsw-badge hsw-badge--' . esc_attr( $mod ) . '"><span class="hsw-d">' . esc_html( $text_full ) . '</span><span class="hsw-m">' . esc_html( $short ) . '</span>' . $icon . '</span>';
                                }
                        } elseif ( 'deal' === $type && $percent > 0 ) {
                                $badge = '<span class="hsw-badge hsw-badge--sale"><span class="hsw-d">' . esc_html( HSW_Helpers::fa_num( $percent ) . '٪ ' . $args['txt_sale'] ) . '</span><span class="hsw-m">' . esc_html( HSW_Helpers::fa_num( $percent ) . '٪' ) . '</span></span>';
                        }
                }

                $overlay = $oos ? '<div class="hsw-oos"><span class="hsw-oos__txt">' . esc_html( $args['txt_oos'] ) . '</span></div>' : '';

                return '<div class="hsw-media">' . $badge . $images . $overlay . '</div>';
        }

        /**
         * Brand + rating meta row.
         */
        protected static function meta_html( $product, $args, $type ) {
                $brand = '';
                if ( ! empty( $args['show_brand'] ) ) {
                        $label = HSW_Helpers::brand_label( $product, $args['brand_attr'] );
                        if ( $label ) {
                                $brand = '<span class="hsw-brand">' . esc_html( $label ) . '</span>';
                        }
                }

                $rate = '';
                if ( ! empty( $args['show_rating'] ) && $product->get_review_count() > 0 ) {
                        $avg   = HSW_Helpers::fa_num( number_format( (float) $product->get_average_rating(), 1 ) );
                        $count = '';
                        if ( ! empty( $args['show_rating_count'] ) ) {
                                /* translators: %s: review count */
                                $count = ' <span class="hsw-rate__count hsw-m">(' . sprintf( __( '%s نظر', 'hamishop-elementor-widgets' ), HSW_Helpers::fa_num( $product->get_review_count() ) ) . ')</span>';
                        }
                        $rate = '<span class="hsw-rate' . ( 'best' === $type ? ' hsw-d' : '' ) . '">' . HSW_Helpers::icon( 'star' ) . '<span class="hsw-rate__score">' . esc_html( $avg ) . '</span>' . $count . '</span>';
                }

                if ( '' === $brand && '' === $rate ) {
                        return '';
                }
                return '<div class="hsw-meta">' . $brand . $rate . '</div>';
        }

        /**
         * Pricing block.
         */
        protected static function price_html( $product, $args, $oos ) {
                $label = '';
                if ( ! empty( $args['price_label'] ) ) {
                        $label = '<span class="hsw-price-label">' . esc_html( $args['price_label'] ) . '</span>';
                }

                if ( HSW_Helpers::is_call_for_price( $product ) ) {
                        return '<div class="hsw-pricing hsw-pricing--cfp">' . $label .
                                HSW_Helpers::cfp_inline_html( $product, false, 'hsw-price-cfp' ) . '</div>';
                }

                $regular = (float) $product->get_regular_price();
                $sale    = (float) $product->get_price();
                $on_sale = $sale > 0 && $regular > 0 && $sale < $regular;
                $old     = '';
                $label   = '';

                if ( $on_sale && ! empty( $args['show_old'] ) ) {
                        $old = '<del class="hsw-price-old">' . esc_html( HSW_Helpers::price_num( $regular ) ) . '</del>';
                }
                if ( ! empty( $args['price_label'] ) ) {
                        $label = '<span class="hsw-price-label">' . esc_html( $args['price_label'] ) . '</span>';
                }

                $amount = $on_sale ? $sale : $sale;
                if ( $amount <= 0 && 'variable' === $product->get_type() ) {
                        $amount = (float) $product->get_variation_price( 'min' );
                }

                return '<div class="hsw-pricing">' . $label . $old .
                        '<div class="hsw-price-new">' . esc_html( HSW_Helpers::price_num( $amount ) ) .
                        ' <span class="hsw-currency">' . esc_html( $args['currency'] ) . '</span></div></div>';
        }

        /**
         * Add-to-cart actions (desktop + mobile variants) or notify button.
         */
        protected static function actions_html( $product, $args, $type ) {
                if ( HSW_Helpers::is_call_for_price( $product ) ) {
                        $label = ( 'best' === $type );
                        return '<div class="hsw-actions hsw-actions--cfp">' .
                                HSW_Helpers::cfp_action_html( $product, 'hsw-add hsw-add--d hsw-d', $label ) .
                                HSW_Helpers::cfp_action_html( $product, 'hsw-add hsw-add--m hsw-m', false ) .
                                '</div>';
                }

                if ( ! $product->is_in_stock() ) {
                        return '<button type="button" class="hsw-notify">' . HSW_Helpers::icon( 'notifications' ) . '<span>' . esc_html( $args['txt_notify'] ) . '</span></button>';
                }

                $purchasable = $product->is_purchasable() && $product->is_in_stock();
                $simple      = $purchasable && $product->is_type( 'simple' );

                if ( $simple ) {
                        $open  = '<button type="button" class="hsw-add %s" data-product_id="' . esc_attr( $product->get_id() ) . '" aria-label="' . esc_attr( $args['txt_add'] ) . '">';
                        $close = '</button>';
                } else {
                        $open  = '<a class="hsw-add %s" href="' . esc_url( $product->get_permalink() ) . '" aria-label="' . esc_attr( $args['txt_add'] ) . '">';
                        $close = '</a>';
                }

                /*
                 * str_replace, NOT sprintf: $open can contain a percent-encoded
                 * permalink (Persian slugs are full of %d8/%af/%db), and sprintf
                 * reads every %XX as a format specifier — the widget died with
                 * "28 arguments are required, 2 given" on every variable product.
                 */
                $d = str_replace( '%s', 'hsw-add--d hsw-d', $open ) . HSW_Helpers::icon( $args['ico_add'] );
                /* Any card type shows the label once the author types one; the
                   deal card stays icon-only while the field is empty. */
                if ( '' !== trim( (string) $args['txt_buy'] ) ) {
                        $d .= '<span>' . esc_html( $args['txt_buy'] ) . '</span>';
                }
                $d .= $close;

                $m = str_replace( '%s', 'hsw-add--m hsw-m', $open ) . HSW_Helpers::icon( 'add' ) . $close;

                return '<div class="hsw-actions">' . $d . $m . '</div>';
        }

        /**
         * Card — Section 1 (پیشنهاد شگفت‌انگیز).
         */
        protected static function card_deal( $product, $args, $i ) {
                unset( $i );
                $oos    = ! $product->is_in_stock();
                $stock  = ( ! $oos && ! empty( $args['show_stock'] ) ) ? HSW_Helpers::stock_progress( $product ) : null;
                $stock_html = '';
                if ( $stock ) {
                        $left_mod = $stock['critical'] ? '' : ' hsw-stock__left--ok';
                        $stock_html = '<div class="hsw-stock hsw-d">' .
                                '<div class="hsw-stock__meta"><span>' . esc_html( $stock['sold'] ) . '</span><span class="hsw-stock__left' . $left_mod . '">' . esc_html( $stock['left'] ) . '</span></div>' .
                                '<div class="hsw-stock__track"><div class="hsw-stock__fill" style="width:' . (int) $stock['percent'] . '%"></div></div></div>';
                }

                return '<article class="hsw-card hsw-card--deal' . ( $oos ? ' hsw-card--oos' : '' ) . '">' .
                        self::media_html( $product, $args, 'deal' ) .
                        '<div class="hsw-card__body">' .
                                self::meta_html( $product, $args, 'deal' ) .
                                '<h3 class="hsw-card__title"><a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a></h3>' .
                                $stock_html .
                        '</div>' .
                        '<div class="hsw-card__foot">' .
                                self::price_html( $product, $args, $oos ) .
                                self::actions_html( $product, $args, 'deal' ) .
                        '</div></article>';
        }

        /**
         * Card — Section 2 (پرفروش‌ترین‌ها, ranked).
         */
        protected static function card_best( $product, $args, $i ) {
                $oos  = ! $product->is_in_stock();
                $rank = '';
                if ( ! empty( $args['show_rank'] ) ) {
                        $mod  = ( $i <= 3 ) ? ' hsw-rank--' . $i : '';
                        $rank = '<div class="hsw-rank' . $mod . '">' . esc_html( HSW_Helpers::fa_num( $i ) ) . '</div>';
                }

                return '<article class="hsw-card hsw-card--best' . ( $oos ? ' hsw-card--oos' : '' ) . '">' .
                        $rank .
                        self::media_html( $product, $args, 'best' ) .
                        '<div class="hsw-card__body">' .
                                self::meta_html( $product, $args, 'best' ) .
                                '<h3 class="hsw-card__title"><a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a></h3>' .
                        '</div>' .
                        '<div class="hsw-card__foot">' .
                                self::price_html( $product, $args, $oos ) .
                                self::actions_html( $product, $args, 'best' ) .
                        '</div></article>';
        }

        /**
         * Card — Section 3 (محبوب‌ترین‌ها / منتخب).
         */
        protected static function card_pop( $product, $args, $i ) {
                unset( $i );
                $oos = ! $product->is_in_stock();

                return '<article class="hsw-card hsw-card--pop' . ( $oos ? ' hsw-card--oos' : '' ) . '">' .
                        self::media_html( $product, $args, 'pop' ) .
                        '<div class="hsw-card__body">' .
                                self::meta_html( $product, $args, 'pop' ) .
                                '<h3 class="hsw-card__title"><a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a></h3>' .
                        '</div>' .
                        '<div class="hsw-card__foot">' .
                                self::price_html( $product, $args, $oos ) .
                                self::actions_html( $product, $args, 'pop' ) .
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
                );
        }

        /* =========================================================================
         *  CONTROL BUILDERS (shared personalization surface)
         * ====================================================================== */

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
                                'label'   => __( 'آیکون (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => $defaults['icon'],
                                'options' => self::icon_options(),
                        )
                );

                $this->add_control(
                        'hdr_title',
                        array(
                                'label'   => __( 'عنوان', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => $defaults['title'],
                                'label_block' => true,
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
                                'label'   => __( 'توضیح کوتاه', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => $defaults['sub'],
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
                        'show_brand'    => array( __( 'نمایش برند/دسته', 'hamishop-elementor-widgets' ), 'yes' ),
                        'brand_attr'    => array( __( 'کلاس ویژگی برند (مثال: pa_brand)', 'hamishop-elementor-widgets' ), 'pa_brand' ),
                        'show_rating'   => array( __( 'نمایش امتیاز', 'hamishop-elementor-widgets' ), 'yes' ),
                        'show_old'      => array( __( 'نمایش قیمت قبل از تخفیف', 'hamishop-elementor-widgets' ), 'yes' ),
                        'show_stock'    => array( __( 'نوار پیشرفت موجودی', 'hamishop-elementor-widgets' ), 'yes' ),
                        'show_rank'     => array( __( 'نشان رتبه', 'hamishop-elementor-widgets' ), 'yes' ),
                        'currency'      => array( __( 'واحد پول', 'hamishop-elementor-widgets' ), 'تومان' ),
                        'price_label'   => array( __( 'برچسب قیمت (مثال: قیمت قطعی:)', 'hamishop-elementor-widgets' ), '' ),
                        'txt_sale'      => array( __( 'متن نشان تخفیف', 'hamishop-elementor-widgets' ), 'تخفیف' ),
                        'txt_feat'      => array( __( 'متن نشان ویژه', 'hamishop-elementor-widgets' ), 'ویژه' ),
                        'txt_ship'      => array( __( 'متن نشان ارسال رایگان', 'hamishop-elementor-widgets' ), 'ارسال رایگان' ),
                        'txt_oos'       => array( __( 'متن اتمام موجودی', 'hamishop-elementor-widgets' ), 'اتمام موجودی' ),
                        'txt_notify'    => array( __( 'متن دکمه خبرم کن', 'hamishop-elementor-widgets' ), 'خبرم کن' ),
                        'txt_buy'       => array( __( 'متن دکمه خرید', 'hamishop-elementor-widgets' ), 'خرید' ),
                        'txt_add'       => array( __( 'متن دکمه افزودن به سبد', 'hamishop-elementor-widgets' ), 'افزودن به سبد خرید' ),
                        'ico_add'       => array( __( 'آیکون دکمه افزودن', 'hamishop-elementor-widgets' ), 'shopping_cart' ),
                );

                $switchers = array( 'show_brand', 'show_rating', 'show_old', 'show_stock', 'show_rank' );

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
                                        'label'       => __( 'گرادیان دسکتاپ (از / وسط / تا)', 'hamishop-elementor-widgets' ),
                                        'type'        => Controls_Manager::HEADING,
                                        'separator'   => 'before',
                                )
                        );
                        $this->add_control( 'sec_from', array( 'label' => __( 'رنگ لبه‌ها', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop--deal' => '--hsw-secondary: {{VALUE}};' ) ) );
                        $this->add_control( 'sec_via', array( 'label' => __( 'رنگ وسط', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop--deal' => '--hsw-secondary-container: {{VALUE}};' ) ) );
                        $this->add_control( 'sec_m', array( 'label' => __( 'رنگ موبایل (ساده)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop--deal' => '--hsw-sec-m: {{VALUE}};' ) ) );
                        $this->add_control(
                                'sec_flat',
                                array(
                                        'label'     => __( 'حذف گرادیان (رنگ ساده)', 'hamishop-elementor-widgets' ),
                                        'type'      => Controls_Manager::SWITCHER,
                                        'selectors' => array( '{{WRAPPER}} .hsw-loop--deal' => 'background-image: none;' ),
                                )
                        );
                } else {
                        $this->add_control( 'sec_bg', array( 'label' => __( 'رنگ پس‌زمینه', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop--box' => '--hsw-sec-bg: {{VALUE}};' ) ) );
                        $this->add_control( 'sec_border_c', array( 'label' => __( 'رنگ حاشیه', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop--box' => '--hsw-sec-border: {{VALUE}};' ) ) );
                }

                $this->add_responsive_control(
                        'sec_padding',
                        array(
                                'label'      => __( 'پدینگ بخش', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::DIMENSIONS,
                                'size_units' => array( 'px' ),
                                'default'         => array( 'top' => 'deal' === $kind ? '12' : '0', 'right' => '0', 'bottom' => '12', 'left' => '0', 'unit' => 'px', 'isLinked' => false ),
                                'tablet_default'  => array( 'top' => '16', 'right' => '16', 'bottom' => '16', 'left' => '16', 'unit' => 'px', 'isLinked' => true ),
                                'mobile_default'  => null,
                                'selectors'  => array( '{{WRAPPER}} .hsw-loop' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
                        )
                );

                $this->add_responsive_control(
                        'sec_radius',
                        array(
                                'label'      => __( 'گردی گوشه‌ها', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'     => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
                                'default'   => array( 'size' => 16, 'unit' => 'px' ),
                                'selectors' => array( '{{WRAPPER}} .hsw-loop' => 'border-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->add_group_control(
                        Group_Control_Box_Shadow::get_type(),
                        array(
                                'name'     => 'sec_shadow',
                                'selector' => '{{WRAPPER}} .hsw-loop',
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

                $this->add_control( 'hdr_bar_c', array( 'label' => __( 'رنگ نوار موبایل', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-hdr__bar' => 'background: {{VALUE}};' ) ) );

                $this->add_control( 'hdr_ic_bg', array( 'label' => __( 'پس‌زمینه آیکون', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-hdr__icon' => '--hsw-ic-bg: {{VALUE}};' ) ) );
                $this->add_control( 'hdr_ic_c', array( 'label' => __( 'رنگ آیکون', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-hdr__icon' => '--hsw-ic-color: {{VALUE}};' ) ) );
                $this->add_control(
                        'hdr_ic_size',
                        array(
                                'label'     => __( 'اندازه آیکون', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SLIDER,
                                'range'     => array( 'px' => array( 'min' => 12, 'max' => 40 ) ),
                                'selectors' => array( '{{WRAPPER}} .hsw-hdr__icon' => '--hsw-ic-size: {{SIZE}}{{UNIT}};' ),
                        )
                );
                $this->add_control(
                        'hdr_ic_box',
                        array(
                                'label'     => __( 'اندازه قاب آیکون', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SLIDER,
                                'range'     => array( 'px' => array( 'min' => 24, 'max' => 64 ) ),
                                'selectors' => array( '{{WRAPPER}} .hsw-hdr__icon' => '--hsw-ic-box: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->add_control(
                        'hdr_title_typo',
                        array(
                                'label'     => __( 'تایپوگرافی عنوان', 'hamishop-elementor-widgets' ),
                                'type'      => Group_Control_Typography::get_type(),
                                'selector'  => '{{WRAPPER}} .hsw-hdr__title',
                                'separator' => 'before',
                        )
                );
                $this->add_control( 'hdr_title_c', array( 'label' => __( 'رنگ عنوان', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-hdr__title' => 'color: {{VALUE}};' ) ) );

                $this->add_control(
                        'hdr_sub_typo',
                        array(
                                'label'     => __( 'تایپوگرافی توضیح', 'hamishop-elementor-widgets' ),
                                'type'      => Group_Control_Typography::get_type(),
                                'selector'  => '{{WRAPPER}} .hsw-hdr__sub',
                                'separator' => 'before',
                        )
                );
                $this->add_control( 'hdr_sub_c', array( 'label' => __( 'رنگ توضیح', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-hdr__sub' => 'color: {{VALUE}};' ) ) );

                $this->add_control( 'hdr_badge_bg', array( 'label' => __( 'پس‌زمینه برچسب', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-hdr__badge' => '--hsw-badge-bg: {{VALUE}};' ) ) );
                $this->add_control( 'hdr_badge_c', array( 'label' => __( 'رنگ برچسب', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-hdr__badge' => '--hsw-badge-c: {{VALUE}};' ) ) );

                $this->add_control(
                        'hdr_link_typo',
                        array(
                                'label'     => __( 'تایپوگرافی لینک', 'hamishop-elementor-widgets' ),
                                'type'      => Group_Control_Typography::get_type(),
                                'selector'  => '{{WRAPPER}} .hsw-hdr__link',
                                'separator' => 'before',
                        )
                );
                $this->add_control( 'hdr_link_c', array( 'label' => __( 'رنگ لینک', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-hdr__link' => '--hsw-link: {{VALUE}};' ) ) );
                $this->add_control( 'hdr_link_ch', array( 'label' => __( 'رنگ لینک (هاور)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-hdr__link:hover' => '--hsw-link-hover: {{VALUE}};' ) ) );

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

                $this->add_control( 'cd_bg', array( 'label' => __( 'پس‌زمینه کپسول تایمر', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-cd' => '--hsw-cd-bg: {{VALUE}};' ) ) );
                $this->add_control( 'cd_val_c', array( 'label' => __( 'رنگ اعداد', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-cd__val' => 'color: {{VALUE}};', '{{WRAPPER}} .hsw-cd' => '--hsw-cd-val: {{VALUE}};' ) ) );
                $this->add_control( 'cd_box_bg', array( 'label' => __( 'پس‌زمینه جعبه‌های موبایل', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-cd__box' => '--hsw-cdbox-bg: {{VALUE}};' ) ) );
                $this->add_control( 'cd_box_c', array( 'label' => __( 'رنگ اعداد موبایل', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-cd__box' => '--hsw-cdbox-c: {{VALUE}};' ) ) );

                $this->add_control(
                        'arrow_bg',
                        array(
                                'label'     => __( 'پس‌زمینه فلش‌ها', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-arrow' => '--hsw-arrow-bg: {{VALUE}};' ),
                                'separator' => 'before',
                        )
                );
                $this->add_control( 'arrow_bgh', array( 'label' => __( 'پس‌زمینه فلش (هاور)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-arrow:hover' => '--hsw-arrow-bgh: {{VALUE}};' ) ) );
                $this->add_control( 'arrow_c', array( 'label' => __( 'رنگ فلش‌ها', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-arrow' => '--hsw-arrow-c: {{VALUE}};' ) ) );
                $this->add_control( 'arrow_border', array( 'label' => __( 'حاشیه فلش‌ها', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-arrow' => '--hsw-arrow-border: {{VALUE}};' ) ) );
                $this->add_control(
                        'arrow_radius',
                        array(
                                'label'     => __( 'گردی فلش‌ها', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SLIDER,
                                'range'     => array( 'px' => array( 'min' => 0, 'max' => 20 ) ),
                                'selectors' => array( '{{WRAPPER}} .hsw-arrow' => 'border-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );
                $this->add_control( 'scrollbar_c', array( 'label' => __( 'رنگ اسکرول‌بار (دسکتاپ)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-track' => '--hsw-scrollbar: {{VALUE}};' ) ) );

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
                        'card_w',
                        array(
                                'label'          => __( 'عرض کارت', 'hamishop-elementor-widgets' ),
                                'type'           => Controls_Manager::SLIDER,
                                'size_units'     => array( 'px' ),
                                'range'          => array( 'px' => array( 'min' => 100, 'max' => 400 ) ),
                                'default'        => array( 'size' => 288, 'unit' => 'px' ),
                                'mobile_default' => array( 'size' => 144, 'unit' => 'px' ),
                                'selectors'      => array( '{{WRAPPER}} .hsw-card' => 'width: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->add_responsive_control(
                        'track_gap',
                        array(
                                'label'     => __( 'فاصله بین کارت‌ها', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SLIDER,
                                'range'     => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
                                'selectors' => array( '{{WRAPPER}} .hsw-track' => '--hsw-gap: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->add_control( 'card_bg', array( 'label' => __( 'پس‌زمینه کارت', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-card' => '--hsw-card-bg: {{VALUE}};' ) ) );
                $this->add_control( 'card_border_c', array( 'label' => __( 'رنگ حاشیه', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-card' => '--hsw-card-border: {{VALUE}};' ) ) );
                $this->add_control(
                        'card_radius',
                        array(
                                'label'     => __( 'گردی کارت', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SLIDER,
                                'range'     => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
                                'selectors' => array( '{{WRAPPER}} .hsw-card' => '--hsw-card-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );
                $this->add_responsive_control(
                        'card_padding',
                        array(
                                'label'      => __( 'پدینگ کارت', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
                                'default'        => array( 'size' => 12, 'unit' => 'px' ),
                                'mobile_default' => array( 'size' => 10, 'unit' => 'px' ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-card' => 'padding: {{SIZE}}{{UNIT}};' ),
                        )
                );
                $this->add_control(
                        'no_lift',
                        array(
                                'label'       => __( 'غیرفعال کردن حرکت کارت در هاور', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'selectors'   => array( '{{WRAPPER}} .hsw-card' => '--hsw-hover-lift: none;' ),
                        )
                );

                $this->add_control(
                        'media_radius',
                        array(
                                'label'     => __( 'گردی تصویر', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SLIDER,
                                'range'     => array( 'px' => array( 'min' => 0, 'max' => 24 ) ),
                                'selectors' => array( '{{WRAPPER}} .hsw-media' => '--hsw-media-radius: {{SIZE}}{{UNIT}};' ),
                                'separator' => 'before',
                        )
                );
                $this->add_control(
                        'zoom',
                        array(
                                'label'        => __( 'زوم تصویر در هاور', 'hamishop-elementor-widgets' ),
                                'type'         => Controls_Manager::SLIDER,
                                'range'        => array( 'px' => array( 'min' => 1, 'max' => 1.3, 'step' => 0.01 ) ),
                                'default'      => array( 'size' => 1.05 ),
                                'selectors'    => array( '{{WRAPPER}} .hsw-card' => '--hsw-zoom: {{SIZE}};' ),
                        )
                );
                $this->add_control(
                        'clamp',
                        array(
                                'label'     => __( 'تعداد خطوط عنوان', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SELECT,
                                'default'   => '2',
                                'options'   => array( '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴' ),
                                'selectors' => array( '{{WRAPPER}} .hsw-card__title' => '--hsw-clamp: {{VALUE}};' ),
                        )
                );

                $this->add_control(
                        'title_typo',
                        array(
                                'label'     => __( 'تایپوگرافی عنوان محصول', 'hamishop-elementor-widgets' ),
                                'type'      => Group_Control_Typography::get_type(),
                                'selector'  => '{{WRAPPER}} .hsw-card__title',
                                'separator' => 'before',
                        )
                );
                $this->add_control( 'title_c', array( 'label' => __( 'رنگ عنوان', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-card__title' => '--hsw-title-c: {{VALUE}};' ) ) );
                $this->add_control( 'title_ch', array( 'label' => __( 'رنگ عنوان (هاور)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-loop' => '--hsw-title-hover: {{VALUE}};' ) ) );

                $this->add_control(
                        'brand_typo',
                        array(
                                'label'     => __( 'تایپوگرافی برند', 'hamishop-elementor-widgets' ),
                                'type'      => Group_Control_Typography::get_type(),
                                'selector'  => '{{WRAPPER}} .hsw-brand',
                                'separator' => 'before',
                        )
                );
                $this->add_control( 'brand_c', array( 'label' => __( 'رنگ برند', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-brand' => 'color: {{VALUE}};' ) ) );

                $this->a_button(
                        'add',
                        __( 'دکمهٔ افزودن به سبد', 'hamishop-elementor-widgets' ),
                        '{{WRAPPER}} .hsw-add',
                        array( 'size' => 11.5, 'radius' => 9 )
                );

                $this->end_controls_section();
        }

        /**
         * Style: badges / rank / stock.
         */
        protected function controls_badge_style() {
                $this->start_controls_section(
                        'sec_style_badges',
                        array( 'label' => __( 'استایل نشان‌ها و موجودی', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_control( 'badge_sale_bg', array( 'label' => __( 'پس‌زمینه نشان تخفیف', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-badge--sale' => 'background: {{VALUE}};' ) ) );
                $this->add_control( 'badge_sale_c', array( 'label' => __( 'رنگ نشان تخفیف', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-badge--sale' => 'color: {{VALUE}};' ) ) );
                $this->add_control( 'badge_feat_bg', array( 'label' => __( 'پس‌زمینه نشان ویژه', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-badge--feat' => 'background: {{VALUE}};' ) ) );
                $this->add_control( 'badge_feat_c', array( 'label' => __( 'رنگ نشان ویژه', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-badge--feat' => 'color: {{VALUE}};' ) ) );
                $this->add_control( 'badge_ship_bg', array( 'label' => __( 'پس‌زمینه نشان ارسال', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-badge--ship' => 'background: {{VALUE}};' ) ) );
                $this->add_control( 'badge_ship_c', array( 'label' => __( 'رنگ نشان ارسال', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-badge--ship' => 'color: {{VALUE}};' ) ) );
                $this->add_control(
                        'badge_radius',
                        array(
                                'label'     => __( 'گردی نشان‌ها', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SLIDER,
                                'range'     => array( 'px' => array( 'min' => 0, 'max' => 20 ) ),
                                'selectors' => array( '{{WRAPPER}} .hsw-badge' => '--hsw-badge-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );

                $this->add_control( 'rank_1_bg', array( 'label' => __( 'پس‌زمینه رتبه ۱', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-rank--1' => '--hsw-rank-1: {{VALUE}};' ) ) );
                $this->add_control( 'rank_1_c', array( 'label' => __( 'رنگ رتبه ۱', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-rank--1' => 'color: {{VALUE}};' ) ) );
                $this->add_control( 'rank_23_bg', array( 'label' => __( 'پس‌زمینه رتبه ۲ و ۳', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-rank--2,{{WRAPPER}} .hsw-rank--3' => '--hsw-rank-23: {{VALUE}};' ) ) );
                $this->add_control( 'rank_23_c', array( 'label' => __( 'رنگ رتبه ۲ و ۳', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-rank--2,{{WRAPPER}} .hsw-rank--3' => 'color: {{VALUE}};' ) ) );
                $this->add_control( 'rank_n_bg', array( 'label' => __( 'پس‌زمینه بقیه رتبه‌ها', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-rank' => '--hsw-rank-n: {{VALUE}};' ) ) );
                $this->add_control( 'rank_n_c', array( 'label' => __( 'رنگ بقیه رتبه‌ها', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-rank' => 'color: {{VALUE}};' ) ) );

                $this->add_control( 'stock_track', array( 'label' => __( 'رنگ زمینه نوار موجودی', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-stock__track' => '--hsw-track-bg: {{VALUE}};' ) ) );
                $this->add_control( 'stock_fill', array( 'label' => __( 'رنگ پرشدن نوار', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-stock__fill' => '--hsw-fill: {{VALUE}};' ) ) );

                $this->add_control(
                        'rate_star_c',
                        array(
                                'label'     => __( 'رنگ ستاره امتیاز', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-rate .hsw-ic' => 'color: {{VALUE}};' ),
                                'separator' => 'before',
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Style: price + buttons.
         */
        protected function controls_price_style() {
                $this->start_controls_section(
                        'sec_style_price',
                        array( 'label' => __( 'استایل قیمت و دکمه‌ها', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_control(
                        'price_typo',
                        array(
                                'label'     => __( 'تایپوگرافی قیمت', 'hamishop-elementor-widgets' ),
                                'type'      => Group_Control_Typography::get_type(),
                                'selector'  => '{{WRAPPER}} .hsw-price-new',
                        )
                );
                $this->add_control( 'price_c', array( 'label' => __( 'رنگ قیمت جدید', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-price-new' => '--hsw-price-c: {{VALUE}};' ) ) );
                $this->add_control( 'price_old_c', array( 'label' => __( 'رنگ قیمت قبلی', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-price-old' => 'color: {{VALUE}};' ) ) );

                $this->add_control( 'btn_bg', array( 'label' => __( 'پس‌زمینه دکمه افزودن', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-add--d, {{WRAPPER}} .hsw-add--m' => 'background: {{VALUE}};' ), 'separator' => 'before' ) );
                $this->add_control( 'btn_bgh', array( 'label' => __( 'پس‌زمینه (هاور)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-add--d:hover' => 'background: {{VALUE}};', '{{WRAPPER}} .hsw-add--m:hover' => 'background: {{VALUE}};' ) ) );
                $this->add_control( 'btn_c', array( 'label' => __( 'رنگ دکمه', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-add--d, {{WRAPPER}} .hsw-add--m' => 'color: {{VALUE}};' ) ) );
                $this->add_control( 'btn_ch', array( 'label' => __( 'رنگ (هاور)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-add--d:hover' => 'color: {{VALUE}};', '{{WRAPPER}} .hsw-add--m:hover' => 'color: {{VALUE}};' ) ) );
                $this->add_control(
                        'btn_radius',
                        array(
                                'label'     => __( 'گردی دکمه', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SLIDER,
                                'range'     => array( 'px' => array( 'min' => 0, 'max' => 20 ) ),
                                'selectors' => array( '{{WRAPPER}} .hsw-add' => 'border-radius: {{SIZE}}{{UNIT}};' ),
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

                $this->add_control( 'lead_bg', array( 'label' => __( 'پس‌زمینه بنر', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-lead' => '--hsw-lead-bg: {{VALUE}};' ) ) );
                $this->add_control( 'lead_border', array( 'label' => __( 'حاشیه بنر', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-lead' => '--hsw-lead-border: {{VALUE}};' ) ) );
                $this->add_control( 'lead_sub_c', array( 'label' => __( 'رنگ زیرعنوان', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-lead__sub' => 'color: {{VALUE}};' ) ) );
                $this->add_control( 'lead_btn_bg', array( 'label' => __( 'پس‌زمینه دکمه', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-lead__btn' => '--hsw-leadbtn-bg: {{VALUE}};' ) ) );
                $this->add_control( 'lead_btn_c', array( 'label' => __( 'رنگ دکمه', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-lead__btn' => '--hsw-leadbtn-c: {{VALUE}};' ) ) );
                $this->add_control( 'lead_btn_bgh', array( 'label' => __( 'پس‌زمینه دکمه (هاور)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .hsw-lead__btn:hover' => '--hsw-leadbtn-bgh: {{VALUE}};' ) ) );

                $this->add_responsive_control(
                        'lead_w',
                        array(
                                'label'          => __( 'عرض بنر', 'hamishop-elementor-widgets' ),
                                'type'           => Controls_Manager::SLIDER,
                                'range'          => array( 'px' => array( 'min' => 100, 'max' => 300 ) ),
                                'default'        => array( 'size' => 208, 'unit' => 'px' ),
                                'mobile_default' => array( 'size' => 144, 'unit' => 'px' ),
                                'selectors'      => array( '{{WRAPPER}} .hsw-lead' => 'width: {{SIZE}}{{UNIT}};' ),
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
         */
        protected function renderer_args() {
                $s = $this->effective_settings();
                return wp_parse_args(
                        array(
                                'show_brand'        => $s['show_brand'] ?? 'yes',
                                'brand_attr'        => $s['brand_attr'] ?? 'pa_brand',
                                'show_rating'       => $s['show_rating'] ?? 'yes',
                                'show_rating_count' => 'yes',
                                'show_old'          => $s['show_old'] ?? 'yes',
                                'show_stock'        => $s['show_stock'] ?? 'yes',
                                'show_rank'         => $s['show_rank'] ?? 'yes',
                                'show_badge'        => 'yes',
                                'price_label'       => $s['price_label'] ?? '',
                                'currency'          => $s['currency'] ?? 'تومان',
                                'txt_sale'          => $s['txt_sale'] ?? 'تخفیف',
                                'txt_feat'          => $s['txt_feat'] ?? 'ویژه',
                                'txt_ship'          => $s['txt_ship'] ?? 'ارسال رایگان',
                                'txt_oos'           => $s['txt_oos'] ?? 'اتمام موجودی',
                                'txt_notify'        => $s['txt_notify'] ?? 'خبرم کن',
                                'txt_add'           => $s['txt_add'] ?? __( 'افزودن به سبد خرید', 'hamishop-elementor-widgets' ),
                                'txt_buy'           => $s['txt_buy'] ?? 'خرید',
                                'ico_add'           => $s['ico_add'] ?? 'shopping_cart',
                                'free_ship_min'     => (float) ( $s['free_ship_min'] ?? 0 ),
                        ),
                        self::default_args()
                );
        }

        /* =========================================================================
         *  PALETTE — shared Style-tab color system for every widget family.
         *
         *  Each family base overrides palette_map() with the CSS variables of
         *  its own token prefix (--hsw-*, --hswb-*, --hswc-*, --hswk-*,
         *  --hswa-*). The palette is injected automatically after the first
         *  content section (see maybe_add_palette()), so Content stays the
         *  first tab while the colors live in Style.
         * ====================================================================== */

        /** @var bool One-time guard for the automatic palette injection. */
        private $hsw_palette_added = false;

        /**
         * Semantic color roles of this family.
         *
         * Entry shape:
         *   'key' => array( 'label' => '…', 'decls' => '--var: {{VALUE}}; …' )
         *   'key' => array( 'heading' => '…' )   // status heading
         *
         * @return array<string,array<string,string>>
         */
        protected function palette_map() {
                return array(
                        'primary'      => array(
                                'label' => __( 'رنگ اصلی (برند)', 'hamishop-elementor-widgets' ),
                                'decls' => '--hsw-primary: {{VALUE}}; --hsw-primary-container: color-mix(in srgb, {{VALUE}}, #fff 14%); --hsw-primary-fixed: color-mix(in srgb, {{VALUE}}, #fff 86%); --hsw-on-primary: #fff;',
                        ),
                        'secondary'    => array(
                                'label' => __( 'رنگ تاکید (نارنجی / CTA)', 'hamishop-elementor-widgets' ),
                                'decls' => '--hsw-secondary: {{VALUE}}; --hsw-secondary-container: color-mix(in srgb, {{VALUE}}, #fff 10%); --hsw-on-secondary: #fff; --hsw-on-secondary-container: color-mix(in srgb, {{VALUE}}, #000 78%);',
                        ),
                        'surface'      => array(
                                'label' => __( 'پس‌زمینه صفحه', 'hamishop-elementor-widgets' ),
                                'decls' => '--hsw-surface: {{VALUE}}; --hsw-surface-bright: {{VALUE}};',
                        ),
                        'card'         => array(
                                'label' => __( 'رنگ کارت‌ها و سطوح', 'hamishop-elementor-widgets' ),
                                'decls' => '--hsw-surface-lowest: {{VALUE}}; --hsw-surface-low: color-mix(in srgb, {{VALUE}}, #000 3%); --hsw-surface-c: color-mix(in srgb, {{VALUE}}, #000 5%); --hsw-surface-high: color-mix(in srgb, {{VALUE}}, #000 7%); --hsw-surface-highest: color-mix(in srgb, {{VALUE}}, #000 10%); --hsw-surface-variant: color-mix(in srgb, {{VALUE}}, #000 12%);',
                        ),
                        'text'         => array(
                                'label' => __( 'رنگ متن', 'hamishop-elementor-widgets' ),
                                'decls' => '--hsw-on-surface: {{VALUE}}; --hsw-on-surface-variant: color-mix(in srgb, {{VALUE}}, #fff 28%);',
                        ),
                        'outline'      => array(
                                'label' => __( 'رنگ حاشیه‌ها', 'hamishop-elementor-widgets' ),
                                'decls' => '--hsw-outline: {{VALUE}}; --hsw-outline-variant: color-mix(in srgb, {{VALUE}}, #fff 55%);',
                        ),
                        'status_head'  => array(
                                'heading' => __( 'رنگ‌های وضعیت', 'hamishop-elementor-widgets' ),
                        ),
                        'amber'        => array(
                                'label' => __( 'کهربایی / هشدار', 'hamishop-elementor-widgets' ),
                                'decls' => '--hsw-amber: {{VALUE}}; --hsw-amber-light: color-mix(in srgb, {{VALUE}}, #fff 45%);',
                        ),
                        'emerald'      => array(
                                'label' => __( 'سبز / موفق', 'hamishop-elementor-widgets' ),
                                'decls' => '--hsw-emerald: {{VALUE}}; --hsw-emerald-bg: color-mix(in srgb, {{VALUE}}, #fff 92%);',
                        ),
                        'error'        => array(
                                'label' => __( 'قرمز / خطا', 'hamishop-elementor-widgets' ),
                                'decls' => '--hsw-error: {{VALUE}};',
                        ),
                );
        }

        /**
         * Build the palette section from palette_map().
         */
        protected function ctrl_palette() {
                $map = $this->palette_map();
                if ( empty( $map ) ) {
                        return;
                }

                $this->start_controls_section(
                        'sec_palette',
                        array(
                                'label' => __( 'پالت رنگ ویجت', 'hamishop-elementor-widgets' ),
                                'tab'   => Controls_Manager::TAB_STYLE,
                        )
                );

                foreach ( $map as $key => $item ) {
                        if ( isset( $item['heading'] ) ) {
                                $this->add_control(
                                        'pal_' . $key,
                                        array(
                                                'label'     => $item['heading'],
                                                'type'      => Controls_Manager::HEADING,
                                                'separator' => 'before',
                                        )
                                );
                                continue;
                        }
                        if ( empty( $item['label'] ) || empty( $item['decls'] ) ) {
                                continue;
                        }
                        $this->add_control(
                                'pal_' . $key,
                                array(
                                        'label'     => $item['label'],
                                        'type'      => Controls_Manager::COLOR,
                                        'selectors' => array(
                                                '{{WRAPPER}}' => $item['decls'],
                                        ),
                                )
                        );
                }

                $this->end_controls_section();
        }

        /**
         * Hook callback: inject the palette once, right after the widget's
         * first section. Registering it after content keeps Elementor's
         * Content tab first (Content → Style → Advanced) instead of opening
         * on Style.
         *
         * @param mixed  $element   Element instance.
         * @param string $section_id Closed section id.
         * @param array  $args      Section args.
         */
        public static function maybe_add_palette( $element, $section_id = '', $args = array() ) {
                if ( ! $element instanceof self ) {
                        return;
                }
                if ( $element->hsw_palette_added ) {
                        return;
                }
                $element->hsw_palette_added = true;
                $element->ctrl_palette();
        }

        /**
         * Resolve products from the source controls.
         */
        protected function resolve_products() {
                $s      = $this->effective_settings();
                $source = $s['source'] ?? 'sale';
                $count  = max( 1, (int) ( $s['count']['size'] ?? 8 ) );

                return HSW_Plugin::instance()->products->query(
                        array(
                                'source'      => $source,
                                'count'       => $count,
                                'ids'         => isset( $s['source_ids'] ) ? explode( ',', $s['source_ids'] ) : array(),
                                'term_id'     => isset( $s['source_term'] ) ? (int) $s['source_term'] : 0,
                                'exclude_oos' => ( 'yes' === ( $s['exclude_oos'] ?? 'yes' ) ) && 'popular' !== $this->get_name(),
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
