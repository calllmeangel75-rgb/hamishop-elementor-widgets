<?php
/**
 * Widget: Archive advanced filters — rebuilt for v1.9.0.
 *
 * Fully dynamic: works on any store. Filter boxes are a repeater
 * (categories / attribute / price / stock / express / rating / on-sale) and
 * when it is left empty the widget auto-detects the store's real product
 * attributes and builds a sensible box set by itself.
 *
 * Desktop: sticky sidebar. Mobile: bottom sheet (sticky triggers live in the
 * toolbar widget). Filtering is plain GET — works without JavaScript; vanilla
 * JS adds instant submit, chips/swatches, brand search and sheet behavior.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class HSW_Widget_Archive_Filters extends HSW_Archive_Base {

        /** @var array<string,string> Effective attribute style cache. */
        private $style_cache = array();

        public function get_name() {
                return 'hsw-archive-filters';
        }

        public function get_title() {
                return __( 'فیلترهای پیشرفته آرشیو (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-filter';
        }

        public function get_keywords() {
                return array( 'filter', 'فیلتر', 'برند', 'قیمت', 'آرشیو', 'hamishop' );
        }

        /* =========================================================================
         *  CONTROLS
         * ====================================================================== */

        protected function register_controls() {
                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control(
                        'f_title',
                        array(
                                'label'   => __( 'عنوان باکس فیلتر', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'فیلترهای پیشرفته', 'hamishop-elementor-widgets' ),
                        )
                );

                /* ---- Boxes repeater (dynamic) ---- */
                $repeater = new Repeater();

                $repeater->add_control(
                        'kind',
                        array(
                                'label'   => __( 'نوع فیلتر', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => 'attr',
                                'options' => array(
                                        'cats'    => __( 'دسته‌بندی‌ها (درختی)', 'hamishop-elementor-widgets' ),
                                        'attr'    => __( 'ویژگی محصول (صفت ووکامرس)', 'hamishop-elementor-widgets' ),
                                        'price'   => __( 'محدوده قیمت', 'hamishop-elementor-widgets' ),
                                        'stock'   => __( 'سوییچ فقط موجود', 'hamishop-elementor-widgets' ),
                                        'express' => __( 'سوییچ ارسال فوری', 'hamishop-elementor-widgets' ),
                                        'rating'  => __( 'امتیاز محصول', 'hamishop-elementor-widgets' ),
                                        'onsale'  => __( 'سوییچ فقط تخفیف‌دارها', 'hamishop-elementor-widgets' ),
                                ),
                        )
                );

                $repeater->add_control(
                        'attr',
                        array(
                                'label'     => __( 'صفت (ویژگی)', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SELECT,
                                'options'   => self::attr_options(),
                                'default'   => 'pa_brand',
                                'condition' => array( 'kind' => 'attr' ),
                        )
                );

                $repeater->add_control(
                        'style',
                        array(
                                'label'     => __( 'شکل نمایش', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SELECT,
                                'default'   => 'checks',
                                'options'   => array(
                                        'checks' => __( 'چک‌باکس + تعداد', 'hamishop-elementor-widgets' ),
                                        'chips'  => __( 'دکمه‌های چیپ', 'hamishop-elementor-widgets' ),
                                        'swatch' => __( 'رنگ‌ها (دایره رنگی)', 'hamishop-elementor-widgets' ),
                                ),
                                'condition' => array( 'kind' => 'attr' ),
                        )
                );

                $repeater->add_control(
                        'search',
                        array(
                                'label'     => __( 'جستجوی داخل فهرست', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SWITCHER,
                                'default'   => 'yes',
                                'condition' => array( 'kind' => 'attr', 'style' => 'checks' ),
                        )
                );

                $repeater->add_control(
                        'cats_heading',
                        array(
                                'label'     => __( 'درخت دسته‌بندی', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::HEADING,
                                'condition' => array( 'kind' => 'cats' ),
                        )
                );

                $repeater->add_control(
                        'cats_counts',
                        array(
                                'label'     => __( 'نمایش تعداد محصولات', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SWITCHER,
                                'default'   => 'yes',
                                'condition' => array( 'kind' => 'cats' ),
                        )
                );

                $repeater->add_control(
                        'cats_inclusive',
                        array(
                                'label'       => __( 'شمارش شامل زیرشاخه‌ها', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'default'     => 'yes',
                                'description' => __( 'تعداد هر شاخه = محصولات خودش + همه زیرشاخه‌ها.', 'hamishop-elementor-widgets' ),
                                'condition'   => array( 'kind' => 'cats', 'cats_counts' => 'yes' ),
                        )
                );

                $repeater->add_control(
                        'cats_empty',
                        array(
                                'label'     => __( 'نمایش دسته‌های خالی', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SWITCHER,
                                'default'   => '',
                                'condition' => array( 'kind' => 'cats' ),
                        )
                );

                $repeater->add_control(
                        'cats_search',
                        array(
                                'label'     => __( 'جستجوی داخل درخت', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SWITCHER,
                                'default'   => 'yes',
                                'condition' => array( 'kind' => 'cats' ),
                        )
                );

                $repeater->add_control(
                        'cats_expand',
                        array(
                                'label'     => __( 'وضعیت باز شدن شاخه‌ها', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SELECT,
                                'default'   => 'current',
                                'options'   => array(
                                        'current' => __( 'فقط مسیر دسته فعلی', 'hamishop-elementor-widgets' ),
                                        'none'    => __( 'همه بسته', 'hamishop-elementor-widgets' ),
                                        'all'     => __( 'همه باز', 'hamishop-elementor-widgets' ),
                                ),
                                'condition' => array( 'kind' => 'cats' ),
                        )
                );

                $repeater->add_control(
                        'cats_top',
                        array(
                                'label'       => __( 'حداکثر شاخه‌های اصلی', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::NUMBER,
                                'default'     => 10,
                                'min'         => 0,
                                'max'         => 50,
                                'description' => __( 'بقیه با دکمه «نمایش بیشتر» باز می‌شوند. ۰ = بدون محدودیت.', 'hamishop-elementor-widgets' ),
                                'condition'   => array( 'kind' => 'cats' ),
                        )
                );

                $repeater->add_control(
                        'cats_depth',
                        array(
                                'label'       => __( 'حداکثر عمق زیرشاخه‌ها', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::NUMBER,
                                'default'     => 0,
                                'min'         => 0,
                                'max'         => 10,
                                'description' => __( '۰ = نمایش همه سطوح.', 'hamishop-elementor-widgets' ),
                                'condition'   => array( 'kind' => 'cats' ),
                        )
                );

                $repeater->add_control(
                        'title',
                        array(
                                'label'       => __( 'عنوان باکس (خالی = خودکار)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'label_block' => true,
                        )
                );

                $repeater->add_control(
                        'open',
                        array(
                                'label'   => __( 'باز باشد', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'boxes',
                        array(
                                'label'       => __( 'باکس‌های فیلتر', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $repeater->get_controls(),
                                'title_field' => '{{{ kind }}} {{{ attr }}} {{{ title }}}',
                                'description' => __( 'خالی بگذارید تا فیلترها به‌صورت خودکار از ویژگی‌ها و دسته‌های همین فروشگاه ساخته شوند. ترتیب ردیف‌ها = ترتیب باکس‌ها.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'auto_heading',
                        array(
                                'label'     => __( 'حالت خودکار', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::HEADING,
                                'separator' => 'before',
                        )
                );

                $this->add_control(
                        'auto_attrs',
                        array(
                                'label'       => __( 'تعداد باکس‌های خودکار ویژگی', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::NUMBER,
                                'default'     => 3,
                                'min'         => 0,
                                'max'         => 8,
                                'description' => __( 'در حالت خودکار چند صفت (ویژگی) فروشگاه به‌عنوان باکس فیلتر ساخته شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'express_slug',
                        array(
                                'label'       => __( 'کلاس ارسال فوری', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SELECT,
                                'options'     => $this->shipping_class_options(),
                                'default'     => '',
                                'description' => __( 'کالاهای دارای این کلاس ارسال، سوییچ «ارسال فوری» می‌گیرند. خالی = خودکار (express).', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'color_heading',
                        array(
                                'label'     => __( 'رنگ‌ها (برای باکس رنگ)', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::HEADING,
                                'separator' => 'before',
                        )
                );

                $repeater2 = new Repeater();
                $repeater2->add_control(
                        'name',
                        array( 'label' => __( 'نام رنگ (ترم صفت)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::TEXT )
                );
                $repeater2->add_control(
                        'hex',
                        array( 'label' => __( 'کد رنگ', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'alpha' => false )
                );
                $this->add_control(
                        'color_map',
                        array(
                                'label'       => __( 'نقشه رنگ‌ها (نام ترم ← کد رنگ)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $repeater2->get_controls(),
                                'title_field' => '{{{ name }}}',
                                'default'     => array(
                                        array( 'name' => 'مشکی', 'hex' => '#0b0b0d' ),
                                        array( 'name' => 'سفید', 'hex' => '#f8fafc' ),
                                        array( 'name' => 'نقره‌ای', 'hex' => '#cbd5e1' ),
                                        array( 'name' => 'طلایی', 'hex' => '#fbbf24' ),
                                        array( 'name' => 'آبی', 'hex' => '#2563eb' ),
                                        array( 'name' => 'سبز', 'hex' => '#059669' ),
                                        array( 'name' => 'قرمز', 'hex' => '#dc2626' ),
                                        array( 'name' => 'بنفش', 'hex' => '#9333ea' ),
                                ),
                                'description' => __( 'اگر ترم متای color داشته باشد خودکار خوانده می‌شود؛ این جدول برای وقتی است که برای رنگ کدی ثبت نشده.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_box( 'fb', '{{WRAPPER}} .hsw-af__box' );
                $this->a_text( 'fb_t', '{{WRAPPER}} .hsw-af__boxtitle', array( 'default' => 14 ) );
                /*
                 * The category tree renders `.hsw-af__catlink` / `.hsw-af__catname`,
                 * not `.hsw-af__link`, so those selectors must be listed here too —
                 * otherwise the font-size control never reached the sidebar
                 * category rows.
                 */
                $this->a_text( 'fb_i', '{{WRAPPER}} .hsw-af__link, {{WRAPPER}} .hsw-af__row, {{WRAPPER}} .hsw-af__catlink, {{WRAPPER}} .hsw-af__catname', array( 'default' => 12, 'weight' => false ) );
                $this->a_color( 'fb_active', __( 'رنگ فیلتر فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .is-active, {{WRAPPER}} .hsw-af__link.is-active' );
                $this->a_color( 'fb_ic', __( 'رنگ آیکون‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-af__headic, {{WRAPPER}} .hsw-af__apply .hsw-ic' );
                $this->a_color( 'fb_switch', __( 'رنگ سوییچ روشن', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-af__switch input:checked ~ .hsw-af__track', 'background-color' );
                $this->a_color( 'fb_chip_bg', __( 'پس‌زمینه چیپ فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-af__chip.is-active', 'background-color' );
                $this->a_color( 'fb_chip_bd', __( 'حاشیه چیپ فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-af__chip.is-active', 'border-color' );
                $this->a_color( 'fb_sheet_bg', __( 'پس‌زمینه باکس/باتم‌شیت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-af__box, {{WRAPPER}} .hsw-asheet__panel', 'background-color' );
                $this->a_color( 'fb_apply', __( 'پس‌زمینه دکمه اعمال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-af__apply', 'background-color' );

                $this->a_button(
                        'apply',
                        __( 'دکمهٔ اعمال فیلتر', 'hamishop-elementor-widgets' ),
                        '{{WRAPPER}} .hsw-af__apply',
                        array( 'size' => 12, 'radius' => 9 )
                );
                $this->end_controls_section();
        }

        /* =========================================================================
         *  BOX MODELS
         * ====================================================================== */

        /**
         * Filter boxes to render: repeater rows, or auto-detected on empty.
         *
         * @return array[] boxes.
         */
        protected function boxes() {
                $s    = $this->effective_settings();
                $rows = ( isset( $s['boxes'] ) && is_array( $s['boxes'] ) ) ? $s['boxes'] : array();

                $boxes = array();
                foreach ( $rows as $row ) {
                        $kind = isset( $row['kind'] ) ? (string) $row['kind'] : '';
                        if ( '' === $kind ) {
                                continue;
                        }
                        $boxes[] = array(
                                'kind'   => $kind,
                                'title'  => isset( $row['title'] ) ? trim( (string) $row['title'] ) : '',
                                'attr'   => isset( $row['attr'] ) ? (string) $row['attr'] : '',
                                'style'  => isset( $row['style'] ) && '' !== $row['style'] ? (string) $row['style'] : 'checks',
                                'search' => ! isset( $row['search'] ) || 'yes' === $row['search'],
                                'open'   => ! isset( $row['open'] ) || 'yes' === $row['open'],
                                /* Category tree options. */
                                'cats_counts'    => ! isset( $row['cats_counts'] ) || 'yes' === $row['cats_counts'],
                                'cats_inclusive' => ! isset( $row['cats_inclusive'] ) || 'yes' === $row['cats_inclusive'],
                                'cats_empty'     => isset( $row['cats_empty'] ) && 'yes' === $row['cats_empty'],
                                'cats_search'    => ! isset( $row['cats_search'] ) || 'yes' === $row['cats_search'],
                                'cats_expand'    => ( isset( $row['cats_expand'] ) && in_array( $row['cats_expand'], array( 'current', 'none', 'all' ), true ) ) ? $row['cats_expand'] : 'current',
                                'cats_top'       => isset( $row['cats_top'] ) ? max( 0, (int) $row['cats_top'] ) : 10,
                                'cats_depth'     => isset( $row['cats_depth'] ) ? max( 0, (int) $row['cats_depth'] ) : 0,
                        );
                }

                if ( empty( $boxes ) ) {
                        $boxes = $this->auto_boxes();
                }

                return $this->merge_switches( $boxes );
        }

        /**
         * Auto box set: categories → stock/express → price → top attributes.
         */
        protected function auto_boxes() {
                $boxes   = array();
                $boxes[] = array(
                        'kind'           => 'cats',
                        'title'          => '',
                        'attr'           => '',
                        'style'          => 'checks',
                        'search'         => true,
                        'open'           => true,
                        'cats_counts'    => true,
                        'cats_inclusive' => true,
                        'cats_empty'     => false,
                        'cats_search'    => true,
                        'cats_expand'    => 'current',
                        'cats_top'       => 10,
                        'cats_depth'     => 0,
                );

                $boxes[] = array( 'kind' => 'stock', 'title' => '', 'attr' => '', 'style' => 'checks', 'search' => false, 'open' => true );
                if ( $this->has_express() ) {
                        $boxes[] = array( 'kind' => 'express', 'title' => '', 'attr' => '', 'style' => 'checks', 'search' => false, 'open' => true );
                }

                $boxes[] = array( 'kind' => 'price', 'title' => '', 'attr' => '', 'style' => 'checks', 'search' => false, 'open' => true );

                $limit = (int) $this->opt( 'auto_attrs', 3 );
                if ( $limit > 0 ) {
                        foreach ( $this->filterable_attributes() as $tax ) {
                                if ( $limit <= 0 ) {
                                        break;
                                }
                                $style = 'checks';
                                if ( 'color' === $this->attribute_kind( $tax ) ) {
                                        $style = 'swatch';
                                } else {
                                        $terms = $this->filter_terms( $tax );
                                        if ( count( $terms ) <= 8 ) {
                                                $style = 'chips';
                                        }
                                }
                                $boxes[] = array(
                                        'kind'   => 'attr',
                                        'title'  => '',
                                        'attr'   => $tax,
                                        'style'  => $style,
                                        'search' => 'checks' === $style,
                                        'open'   => true,
                                );
                                $limit--;
                        }
                }

                return $boxes;
        }

        /**
         * Collapse stock/express rows into a single switches box.
         */
        protected function merge_switches( $boxes ) {
                $out = array();
                foreach ( $boxes as $box ) {
                        if ( 'stock' === $box['kind'] || 'express' === $box['kind'] ) {
                                $found = false;
                                foreach ( $out as $i => $existing ) {
                                        if ( 'switches' === $existing['kind'] ) {
                                                $out[ $i ][ $box['kind'] ] = true;
                                                $found                     = true;
                                                break;
                                        }
                                }
                                if ( ! $found ) {
                                        $out[] = array(
                                                'kind'    => 'switches',
                                                'title'   => '',
                                                'attr'    => '',
                                                'style'   => 'checks',
                                                'search'  => false,
                                                'open'    => $box['open'],
                                                'stock'   => 'stock' === $box['kind'],
                                                'express' => 'express' === $box['kind'],
                                        );
                                }
                                continue;
                        }
                        $out[] = $box;
                }
                return $out;
        }

        /**
         * Title of a box (custom → automatic label).
         */
        protected function box_title( $box ) {
                if ( '' !== $box['title'] ) {
                        return $box['title'];
                }
                switch ( $box['kind'] ) {
                        case 'cats':
                                return __( 'دسته‌بندی‌ها', 'hamishop-elementor-widgets' );
                        case 'price':
                                return __( 'محدوده قیمت', 'hamishop-elementor-widgets' );
                        case 'rating':
                                return __( 'امتیاز محصول', 'hamishop-elementor-widgets' );
                        case 'switches':
                                return __( 'فیلتر سریع', 'hamishop-elementor-widgets' );
                        case 'attr':
                                return $this->taxonomy_label( $box['attr'] );
                }
                return '';
        }

        /**
         * Term rows of an attribute box: [ name, slug, count, hex ].
         */
        protected function attr_rows( $box ) {
                $tax  = $box['attr'];
                $rows = array();

                if ( $tax && taxonomy_exists( $tax ) ) {
                        $map      = $this->color_map();
                        $terms    = $this->filter_terms( $tax );
                        $is_color = $this->looks_like_color( $tax, $terms );
                        foreach ( $terms as $t ) {
                                $rows[] = array(
                                        'name'  => $t->name,
                                        /* Decoded: see clean_param_value(). */
                                        'slug'  => rawurldecode( $t->slug ),
                                        'count' => (int) $t->count,
                                        'hex'   => $is_color ? $this->term_color( $t, $map ) : '',
                                );
                        }
                        return $rows;
                }

                /* Editor preview: synthesize rows from the demo data. */
                if ( $this->is_demo() ) {
                        $d = $this->demo();
                        if ( 'swatch' === $box['style'] ) {
                                foreach ( $d['colors'] as $c ) {
                                        $rows[] = array( 'name' => $c['name'], 'slug' => sanitize_title( $c['name'] ), 'count' => 0, 'hex' => $c['hex'] );
                                }
                        } elseif ( 'chips' === $box['style'] ) {
                                foreach ( $d['storages'] as $s ) {
                                        $rows[] = array( 'name' => $s, 'slug' => sanitize_title( $s ), 'count' => 0, 'hex' => '' );
                                }
                        } else {
                                foreach ( $d['brands'] as $b ) {
                                        $rows[] = array( 'name' => $b['name'], 'slug' => sanitize_title( $b['name'] ), 'count' => (int) $b['count'], 'hex' => '' );
                                }
                        }
                }
                return $rows;
        }

        /**
         * Attribute box style after auto-detection: colour taxonomies become
         * swatches; a swatch style on a non-colour attribute falls back to chips.
         */
        protected function effective_style( $box ) {
                $tax = isset( $box['attr'] ) ? (string) $box['attr'] : '';
                $key = $tax . '|' . ( isset( $box['style'] ) ? $box['style'] : 'checks' );
                if ( isset( $this->style_cache[ $key ] ) ) {
                        return $this->style_cache[ $key ];
                }
                $style = isset( $box['style'] ) && '' !== $box['style'] ? $box['style'] : 'checks';
                $rows  = $this->attr_rows( $box );
                if ( ! empty( $rows ) ) {
                        if ( $this->looks_like_color( $tax, $rows ) ) {
                                $style = 'swatch';
                        } elseif ( 'swatch' === $style ) {
                                $style = 'chips';
                        }
                }
                $this->style_cache[ $key ] = $style;
                return $style;
        }

        /**
         * name → hex map from the widget repeater.
         */
        protected function color_map() {
                $s   = $this->effective_settings();
                $map = array();
                foreach ( (array) ( isset( $s['color_map'] ) ? $s['color_map'] : array() ) as $row ) {
                        $nm = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
                        if ( '' === $nm ) {
                                continue;
                        }
                        $map[ $nm ] = isset( $row['hex'] ) && '' !== (string) $row['hex'] ? (string) $row['hex'] : '';
                }
                return $map;
        }

        /**
         * Color of a term: term meta first (color/hex/swatch), then map, then a
         * neutral placeholder.
         */
        protected function term_color( $term, $map ) {
                foreach ( array( 'color', 'hex', 'swatch_color', 'product_attribute_color' ) as $key ) {
                        $meta = get_term_meta( $term->term_id, $key, true );
                        if ( is_string( $meta ) && '' !== trim( $meta ) && preg_match( '/^#?[0-9a-fA-F]{3,8}$/', trim( $meta ) ) ) {
                                $val = trim( $meta );
                                return 0 === strpos( $val, '#' ) ? $val : '#' . $val;
                        }
                }
                if ( isset( $map[ $term->name ] ) && '' !== $map[ $term->name ] ) {
                        return $map[ $term->name ];
                }
                $loose = array();
                foreach ( $map as $name => $hex ) {
                        $loose[ mb_strtolower( trim( $name ) ) ] = $hex;
                }
                $key = mb_strtolower( trim( $term->name ) );
                if ( isset( $loose[ $key ] ) && '' !== $loose[ $key ] ) {
                        return $loose[ $key ];
                }
                $named = $this->color_hex( $term->name );
                if ( '' !== $named ) {
                        return $named;
                }
                $named = $this->color_hex( $term->slug );
                if ( '' !== $named ) {
                        return $named;
                }
                return '#cbd5e1';
        }

        /**
         * Does this taxonomy describe colors? Named colour attributes are
         * rendered as swatches even when WooCommerce stores them as a select.
         */
        protected function looks_like_color( $tax, $terms = array() ) {
                $label = mb_strtolower( $tax . ' ' . $this->taxonomy_label( $tax ) );
                if ( preg_match( '/colou?r|رنگ|shade|tone/u', $label ) ) {
                        return true;
                }
                if ( empty( $terms ) ) {
                        return false;
                }
                $hits  = 0;
                $total = 0;
                foreach ( $terms as $term ) {
                        if ( is_object( $term ) && isset( $term->name ) ) {
                                $name = (string) $term->name;
                        } elseif ( is_array( $term ) && isset( $term['name'] ) ) {
                                $name = (string) $term['name'];
                        } elseif ( is_scalar( $term ) ) {
                                $name = (string) $term;
                        } else {
                                $name = '';
                        }
                        if ( '' === trim( $name ) ) {
                                continue;
                        }
                        $total++;
                        if ( '' !== $this->color_hex( $name ) ) {
                                $hits++;
                        }
                }
                return $total > 0 && $hits >= max( 1, (int) ceil( $total * 0.6 ) );
        }

        /**
         * Common colour names (English + Persian) → hex. Longest names win so
         * «آبی روشن» beats «آبی». Filterable for store-specific palettes.
         */
        protected function color_hex( $name ) {
                $name = mb_strtolower( trim( (string) $name ) );
                if ( '' === $name ) {
                        return '';
                }
                $name = str_replace( array( 'ي', 'ك', '‌' ), array( 'ی', 'ک', ' ' ), $name );
                $name = preg_replace( '/\s+/u', ' ', $name );

                static $dict = null;
                if ( null === $dict ) {
                        $dict = array(
                                'آبی روشن' => '#60a5fa', 'آبی تیره' => '#1e40af', 'آبی' => '#2563eb',
                                'قرمز تیره' => '#991b1b', 'قرمز' => '#ef4444', 'سرخ' => '#dc2626',
                                'سبز روشن' => '#4ade80', 'سبز تیره' => '#166534', 'سبز' => '#16a34a',
                                'زرد' => '#facc15', 'نارنجی' => '#f97316', 'بنفش' => '#9333ea',
                                'صورتی' => '#ec4899', 'خاکستری' => '#9ca3af', 'طوسی' => '#9ca3af',
                                'مشکی' => '#111827', 'سیاه' => '#111827', 'سفید' => '#ffffff',
                                'نقره‌ای' => '#d1d5db', 'نقره' => '#d1d5db', 'طلایی' => '#d4af37',
                                'قهوه‌ای' => '#92400e', 'سرمه‌ای' => '#1e3a8a', 'فیروزه‌ای' => '#06b6d4',
                                'تیتانیوم' => '#6b7280', 'بژ' => '#e7d8c9', 'کرم' => '#f5f0e1',
                                'زیتونی' => '#6b7f2e', 'زرشکی' => '#7f1d1d', 'مرجانی' => '#f87171',
                                'گلبهی' => '#f43f5e', 'برنزی' => '#a16207', 'گرافیتی' => '#374151',
                                'یشمی' => '#10b981', 'یشم' => '#10b981',
                                'light blue' => '#60a5fa', 'dark blue' => '#1e40af', 'sky blue' => '#38bdf8',
                                'navy' => '#1e3a8a', 'blue' => '#2563eb', 'red' => '#ef4444',
                                'dark red' => '#991b1b', 'green' => '#16a34a', 'dark green' => '#166534',
                                'light green' => '#4ade80', 'yellow' => '#facc15', 'orange' => '#f97316',
                                'purple' => '#9333ea', 'violet' => '#8b5cf6', 'indigo' => '#4f46e5',
                                'pink' => '#ec4899', 'rose' => '#f43f5e', 'gray' => '#9ca3af',
                                'grey' => '#9ca3af', 'silver' => '#d1d5db', 'black' => '#111827',
                                'white' => '#ffffff', 'gold' => '#d4af37', 'golden' => '#d4af37',
                                'brown' => '#92400e', 'turquoise' => '#06b6d4', 'teal' => '#0d9488',
                                'cyan' => '#06b6d4', 'lime' => '#84cc16', 'olive' => '#6b7f2e',
                                'beige' => '#e7d8c9', 'cream' => '#f5f0e1', 'maroon' => '#7f1d1d',
                                'coral' => '#f87171', 'bronze' => '#a16207', 'graphite' => '#374151',
                                'titanium' => '#6b7280', 'charcoal' => '#374151',
                        );
                        $dict = (array) apply_filters( 'hsw_archive_color_dict', $dict );
                        uksort(
                                $dict,
                                function ( $a, $b ) {
                                        return mb_strlen( (string) $b ) <=> mb_strlen( (string) $a );
                                }
                        );
                }
                foreach ( $dict as $key => $hex ) {
                        $key = mb_strtolower( (string) $key );
                        $key = str_replace( array( 'ي', 'ك', '‌' ), array( 'ی', 'ک', ' ' ), $key );
                        $key = preg_replace( '/\s+/u', ' ', $key );
                        if ( '' !== $key && false !== mb_strpos( $name, $key ) ) {
                                return (string) $hex;
                        }
                }
                return '';
        }

        /* =========================================================================
         *  RENDER — one box at a time (used by both desktop + mobile)
         * ====================================================================== */

        /**
         * One filter box.
         */
        protected function box_html( $box ) {
                switch ( $box['kind'] ) {
                        case 'cats':
                                return $this->box_cats( $box );
                        case 'price':
                                return $this->box_price( $box );
                        case 'rating':
                                return $this->box_rating( $box );
                        case 'switches':
                                return $this->box_switches( $box );
                        case 'attr':
                                return $this->box_attr( $box );
                }
                return '';
        }

        /**
         * Box shell: title + optional collapse + body.
         */
        protected function box_shell( $title, $body, $open = true, $collapsible = true ) {
                if ( '' === trim( (string) $body ) ) {
                        return '';
                }
                $cls  = 'hsw-af__box' . ( $open ? '' : ' is-collapsed' );
                $html = '<div class="' . esc_attr( $cls ) . '"' . ( $collapsible ? ' data-hsw-collapse' : '' ) . '>';

                if ( $collapsible ) {
                        $html .= '<button type="button" class="hsw-af__boxhead">';
                        $html .= '<span class="hsw-af__boxtitle">' . esc_html( $title ) . '</span>';
                        $html .= HSW_Helpers::icon( 'expand_more', 'hsw-af__chev' );
                        $html .= '</button>';
                } else {
                        $html .= '<div class="hsw-af__boxhead"><span class="hsw-af__boxtitle">' . esc_html( $title ) . '</span></div>';
                }

                $html .= '<div class="hsw-af__boxbody">' . $body . '</div>';
                $html .= '</div>';
                return $html;
        }

        /**
         * Category tree: full product_cat hierarchy (any depth), counts,
         * collapsible branches and live search. Handles stores with 100+
         * categories without a flat wall of terms.
         */
        protected function box_cats( $box ) {
                if ( $this->is_demo() || ! $this->is_archive_context() ) {
                        return $this->box_shell( $this->box_title( $box ), $this->cats_demo_html( $box ), $box['open'] );
                }

                $terms = get_terms(
                        array(
                                'taxonomy'   => 'product_cat',
                                'hide_empty' => false,
                                'exclude'    => $this->default_cat_ids(),
                                'orderby'    => 'name',
                                'order'      => 'ASC',
                                'number'     => 0,
                        )
                );
                if ( is_wp_error( $terms ) || empty( $terms ) ) {
                        return '';
                }

                $by_id    = array();
                $children = array();
                foreach ( $terms as $t ) {
                        $id                             = (int) $t->term_id;
                        $by_id[ $id ]                   = $t;
                        $children[ (int) $t->parent ][] = $id;
                }

                $inclusive = array();
                $this->cat_counts( 0, $children, $by_id, $inclusive );

                $term       = $this->current_term();
                $current_id = $term ? (int) $term->term_id : 0;
                $path       = array();
                if ( $current_id ) {
                        $ancestors = get_ancestors( $current_id, 'product_cat', 'taxonomy' );
                        if ( ! is_wp_error( $ancestors ) ) {
                                foreach ( $ancestors as $aid ) {
                                        $path[ (int) $aid ] = true;
                                }
                        }
                        $path[ $current_id ] = true;
                }

                $ctx = array(
                        'by_id'          => $by_id,
                        'children'       => $children,
                        'inclusive'      => $inclusive,
                        'path'           => $path,
                        'current'        => $current_id,
                        'counts_on'      => ! empty( $box['cats_counts'] ),
                        'counts_include' => ! empty( $box['cats_inclusive'] ),
                        'show_empty'     => ! empty( $box['cats_empty'] ),
                        'expand'         => isset( $box['cats_expand'] ) ? (string) $box['cats_expand'] : 'current',
                        'max_top'        => isset( $box['cats_top'] ) ? (int) $box['cats_top'] : 10,
                        'max_depth'      => isset( $box['cats_depth'] ) ? (int) $box['cats_depth'] : 0,
                );

                $top_total = 0;
                if ( ! empty( $children[0] ) ) {
                        foreach ( $children[0] as $id ) {
                                $total = isset( $inclusive[ $id ] ) ? (int) $inclusive[ $id ] : 0;
                                if ( $ctx['show_empty'] || $total > 0 ) {
                                        $top_total++;
                                }
                        }
                }

                /*
                 * The active category must stay visible even when its top-level
                 * branch is beyond the «حداکثر شاخه‌های اصلی» limit. Reveal only
                 * the current path (at most one top-level node) and discount it
                 * from the «نمایش همه» counter.
                 */
                $revealed = 0;
                if ( $ctx['max_top'] > 0 && $current_id && ! empty( $children[0] ) ) {
                        $top_ancestor = $current_id;
                        $ancestors    = get_ancestors( $current_id, 'product_cat', 'taxonomy' );
                        if ( ! is_wp_error( $ancestors ) && ! empty( $ancestors ) ) {
                                $top_ancestor = (int) end( $ancestors );
                        }
                        $idx = 0;
                        foreach ( $children[0] as $id ) {
                                $total = isset( $inclusive[ $id ] ) ? (int) $inclusive[ $id ] : 0;
                                if ( ! $ctx['show_empty'] && $total <= 0 ) {
                                        continue;
                                }
                                if ( (int) $id === $top_ancestor ) {
                                        if ( $idx >= $ctx['max_top'] ) {
                                                $revealed = 1;
                                        }
                                        break;
                                }
                                $idx++;
                        }
                }

                $body = '';
                if ( ! empty( $box['cats_search'] ) && count( $terms ) > 6 ) {
                        $body .= '<div class="hsw-af__search hsw-af__catsearch">';
                        $body .= '<input type="text" class="hsw-af__searchinput" placeholder="' . esc_attr__( 'جستجوی دسته...', 'hamishop-elementor-widgets' ) . '" data-hsw-catsearch aria-label="' . esc_attr__( 'جستجوی دسته‌بندی', 'hamishop-elementor-widgets' ) . '">';
                        $body .= HSW_Helpers::icon( 'search' );
                        $body .= '</div>';
                }

                $top_index = 0;
                $body     .= '<ul class="hsw-af__tree hsw-af__cattree" data-hsw-cattree role="tree">';
                $body     .= $this->cats_branch_html( 0, $ctx, 0, $top_index );
                $body     .= '</ul>';

                $hidden_top = max( 0, $top_total - $ctx['max_top'] - $revealed );
                if ( $ctx['max_top'] > 0 && $hidden_top > 0 ) {
                        $body .= '<button type="button" class="hsw-af__catmore" data-hsw-catmore>';
                        $body .= esc_html__( 'نمایش همه دسته‌ها', 'hamishop-elementor-widgets' );
                        $body .= ' <span>(' . esc_html( HSW_Helpers::fa_num( $hidden_top ) ) . ')</span>';
                        $body .= '</button>';
                }

                $body .= '<div class="hsw-af__noresult" data-hsw-catempty hidden>' . esc_html__( 'دسته‌ای پیدا نشد.', 'hamishop-elementor-widgets' ) . '</div>';

                return $this->box_shell( $this->box_title( $box ), $body, $box['open'] );
        }

        /**
         * Inclusive product count of every category (own products + children).
         *
         * @param int   $parent   Parent term id (0 = root).
         * @param array $children Parent id → child ids map.
         * @param array $by_id    Term id → WP_Term map.
         * @param array $counts   Output counts (by reference).
         * @return int
         */
        protected function cat_counts( $parent, $children, $by_id, &$counts ) {
                $sum = 0;
                if ( ! empty( $children[ $parent ] ) ) {
                        foreach ( $children[ $parent ] as $id ) {
                                $sum += $this->cat_counts( $id, $children, $by_id, $counts );
                        }
                }
                if ( $parent > 0 && isset( $by_id[ $parent ] ) ) {
                        $sum += (int) $by_id[ $parent ]->count;
                        $counts[ $parent ] = $sum;
                }
                return $sum;
        }

        /**
         * One level of the category tree (recursive).
         *
         * @param int   $parent    Parent term id.
         * @param array $ctx       Tree context.
         * @param int   $depth     Current depth.
         * @param int   $top_index Rendered top-level counter (by reference).
         * @return string
         */
        protected function cats_branch_html( $parent, $ctx, $depth = 0, &$top_index = 0 ) {
                if ( empty( $ctx['children'][ $parent ] ) ) {
                        return '';
                }
                if ( $ctx['max_depth'] > 0 && $depth >= $ctx['max_depth'] ) {
                        return '';
                }

                $html = '';
                foreach ( $ctx['children'][ $parent ] as $id ) {
                        if ( ! isset( $ctx['by_id'][ $id ] ) ) {
                                continue;
                        }
                        $term  = $ctx['by_id'][ $id ];
                        $total = isset( $ctx['inclusive'][ $id ] ) ? (int) $ctx['inclusive'][ $id ] : (int) $term->count;
                        if ( ! $ctx['show_empty'] && $total <= 0 ) {
                                continue;
                        }

                        $is_top   = ( 0 === $depth );
                        $is_extra = $is_top && $ctx['max_top'] > 0 && $top_index >= $ctx['max_top'] && ! isset( $ctx['path'][ $id ] );
                        if ( $is_top ) {
                                $top_index++;
                        }

                        $has_children = ! empty( $ctx['children'][ $id ] ) && ( 0 === $ctx['max_depth'] || $depth + 1 < $ctx['max_depth'] );
                        $is_current   = ( (int) $id === (int) $ctx['current'] );
                        $is_ancestor  = ! $is_current && isset( $ctx['path'][ $id ] );
                        $is_open      = $has_children && ( 'all' === $ctx['expand'] || ( 'current' === $ctx['expand'] && isset( $ctx['path'][ $id ] ) ) );

                        $display = null;
                        if ( $ctx['counts_on'] ) {
                                $display = $ctx['counts_include'] ? $total : (int) $term->count;
                        }

                        $classes = array( 'hsw-af__catnode' );
                        if ( $is_open ) {
                                $classes[] = 'is-open';
                        }
                        if ( $is_current ) {
                                $classes[] = 'is-current';
                        } elseif ( $is_ancestor ) {
                                $classes[] = 'is-ancestor';
                        }
                        if ( $is_extra ) {
                                $classes[] = 'is-extra';
                        }

                        $html .= '<li class="' . esc_attr( implode( ' ', $classes ) ) . '"' . ( $is_extra ? ' hidden' : '' ) . '>';

                        $html .= '<div class="hsw-af__catrow">';
                        if ( $has_children ) {
                                $html .= '<button type="button" class="hsw-af__cattoggle" data-hsw-cattoggle aria-expanded="' . ( $is_open ? 'true' : 'false' ) . '" aria-label="' . esc_attr__( 'باز و بستن زیرشاخه‌ها', 'hamishop-elementor-widgets' ) . '">' . HSW_Helpers::icon( 'expand_more' ) . '</button>';
                        } else {
                                $html .= '<span class="hsw-af__catspacer" aria-hidden="true"></span>';
                        }

                        $link = get_term_link( $term );
                        if ( is_wp_error( $link ) ) {
                                $link = '#';
                        }
                        $link_class = 'hsw-af__catlink';
                        if ( $is_current ) {
                                $link_class .= ' is-active';
                        } elseif ( $is_ancestor ) {
                                $link_class .= ' is-ancestor';
                        }

                        $html .= '<a class="' . esc_attr( $link_class ) . '" href="' . esc_url( $link ) . '" data-hsw-catname="' . esc_attr( mb_strtolower( $term->name . ' ' . $term->slug ) ) . '"' . ( $is_current ? ' aria-current="page"' : '' ) . '><span class="hsw-af__catname">' . esc_html( $term->name ) . '</span></a>';

                        if ( null !== $display ) {
                                if ( $is_current ) {
                                        $html .= '<span class="hsw-af__badge">' . esc_html( HSW_Helpers::fa_num( $display ) ) . '</span>';
                                } else {
                                        $html .= '<span class="hsw-af__cnt">(' . esc_html( HSW_Helpers::fa_num( $display ) ) . ')</span>';
                                }
                        }

                        $html .= '</div>';

                        if ( $has_children ) {
                                $html .= '<ul class="hsw-af__catbranch" data-hsw-catbranch role="group"' . ( $is_open ? '' : ' hidden' ) . '>';
                                $html .= $this->cats_branch_html( $id, $ctx, $depth + 1, $top_index );
                                $html .= '</ul>';
                        }

                        $html .= '</li>';
                }

                return $html;
        }

        /**
         * Editor demo of the category tree (root → current → children).
         *
         * @param array $box Box model.
         * @return string
         */
        protected function cats_demo_html( $box ) {
                $d = $this->demo();
                if ( empty( $d['cats'] ) ) {
                        return '';
                }
                $d      = $d['cats'];
                $root   = $d['root'];
                $mid    = $d['mid'];
                $leafs  = $d['leafs'];
                $counts = ! empty( $box['cats_counts'] );

                $body = '';
                if ( ! empty( $box['cats_search'] ) ) {
                        $body .= '<div class="hsw-af__search hsw-af__catsearch">';
                        $body .= '<input type="text" class="hsw-af__searchinput" placeholder="' . esc_attr__( 'جستجوی دسته...', 'hamishop-elementor-widgets' ) . '" data-hsw-catsearch aria-label="' . esc_attr__( 'جستجوی دسته‌بندی', 'hamishop-elementor-widgets' ) . '">';
                        $body .= HSW_Helpers::icon( 'search' );
                        $body .= '</div>';
                }

                $body .= '<ul class="hsw-af__tree hsw-af__cattree" data-hsw-cattree role="tree">';
                $body .= '<li class="hsw-af__catnode is-open">';
                $body .= '<div class="hsw-af__catrow">';
                $body .= '<button type="button" class="hsw-af__cattoggle" data-hsw-cattoggle aria-expanded="true" aria-label="' . esc_attr__( 'باز و بستن زیرشاخه‌ها', 'hamishop-elementor-widgets' ) . '">' . HSW_Helpers::icon( 'expand_more' ) . '</button>';
                $body .= '<a class="hsw-af__catlink" href="#" data-hsw-catname="' . esc_attr( mb_strtolower( $root['name'] ) ) . '"><span class="hsw-af__catname">' . esc_html( $root['name'] ) . '</span></a>';
                if ( $counts ) {
                        $body .= '<span class="hsw-af__cnt">(' . esc_html( HSW_Helpers::fa_num( (int) $root['count'] ) ) . ')</span>';
                }
                $body .= '</div>';
                $body .= '<ul class="hsw-af__catbranch" data-hsw-catbranch>';
                $body .= '<li class="hsw-af__catnode is-open is-current">';
                $body .= '<div class="hsw-af__catrow">';
                $body .= '<button type="button" class="hsw-af__cattoggle" data-hsw-cattoggle aria-expanded="true" aria-label="' . esc_attr__( 'باز و بستن زیرشاخه‌ها', 'hamishop-elementor-widgets' ) . '">' . HSW_Helpers::icon( 'expand_more' ) . '</button>';
                $body .= '<a class="hsw-af__catlink is-active" href="#" aria-current="page" data-hsw-catname="' . esc_attr( mb_strtolower( $mid['name'] ) ) . '"><span class="hsw-af__catname">' . esc_html( $mid['name'] ) . '</span></a>';
                if ( $counts ) {
                        $body .= '<span class="hsw-af__badge">' . esc_html( HSW_Helpers::fa_num( (int) $mid['count'] ) ) . '</span>';
                }
                $body .= '</div>';
                $body .= '<ul class="hsw-af__catbranch" data-hsw-catbranch>';
                foreach ( $leafs as $leaf ) {
                        $body .= '<li class="hsw-af__catnode">';
                        $body .= '<div class="hsw-af__catrow"><span class="hsw-af__catspacer" aria-hidden="true"></span>';
                        $body .= '<a class="hsw-af__catlink" href="#" data-hsw-catname="' . esc_attr( mb_strtolower( $leaf['name'] ) ) . '"><span class="hsw-af__catname">' . esc_html( $leaf['name'] ) . '</span></a>';
                        if ( $counts ) {
                                $body .= '<span class="hsw-af__cnt">(' . esc_html( HSW_Helpers::fa_num( (int) $leaf['count'] ) ) . ')</span>';
                        }
                        $body .= '</div></li>';
                }
                $body .= '</ul></li></ul></li></ul>';

                return $body;
        }


        /**
         * Attribute box: checks / chips / swatches.
         */
        protected function box_attr( $box ) {
                $tax   = $box['attr'];
                $rows  = $this->attr_rows( $box );
                if ( empty( $rows ) ) {
                        return '';
                }
                $title = $this->box_title( $box );
                $sel   = $this->selected_slugs( $tax );
                $style = $this->effective_style( $box );
                $body  = '';

                if ( 'checks' === $style ) {
                        if ( $box['search'] && count( $rows ) > 6 ) {
                                $body .= '<div class="hsw-af__search">';
                                $body .= '<input type="text" class="hsw-af__searchinput" placeholder="' . esc_attr__( 'جستجو...', 'hamishop-elementor-widgets' ) . '" data-hsw-search>';
                                $body .= HSW_Helpers::icon( 'search' );
                                $body .= '</div>';
                        }
                        $body .= '<div class="hsw-af__checks" data-hsw-termlist>';
                        foreach ( $rows as $row ) {
                                $checked = in_array( $row['slug'], $sel, true );
                                $body   .= '<label class="hsw-af__checkrow">';
                                $body   .= '<span class="hsw-af__checkname">';
                                $body   .= '<input type="checkbox" class="hsw-af__check" name="filter_' . esc_attr( $tax ) . '[]" value="' . esc_attr( $row['slug'] ) . '"' . checked( $checked, true, false ) . ' data-hsw-auto>';
                                $body   .= '<span>' . esc_html( $row['name'] ) . '</span>';
                                $body   .= '</span>';
                                if ( $row['count'] > 0 ) {
                                        $body .= '<span class="hsw-af__cnt">' . esc_html( HSW_Helpers::fa_num( $row['count'] ) ) . '</span>';
                                }
                                $body .= '</label>';
                        }
                        $body .= '</div>';
                        return $this->box_shell( $title, $body, $box['open'] );
                }

                if ( 'chips' === $style ) {
                        $body .= '<div class="hsw-af__chipsbox">';
                        foreach ( $rows as $row ) {
                                $active = in_array( $row['slug'], $sel, true );
                                $body  .= '<button type="button" class="hsw-af__chip' . ( $active ? ' is-active' : '' ) . '" data-hsw-tax="' . esc_attr( $tax ) . '" data-hsw-value="' . esc_attr( $row['slug'] ) . '">' . esc_html( $row['name'] ) . '</button>';
                        }
                        $body .= '</div>';
                        $body .= $this->nojs_checklist( $tax, $rows, $sel );
                        return $this->box_shell( $title, $body, $box['open'] );
                }

                /* Swatches. */
                $body .= '<div class="hsw-af__swatches">';
                foreach ( $rows as $row ) {
                        $active = in_array( $row['slug'], $sel, true );
                        $body  .= '<button type="button" class="hsw-af__swatch' . ( $active ? ' is-active' : '' ) . '" style="--sw:' . esc_attr( $row['hex'] ) . '" title="' . esc_attr( $row['name'] ) . '" aria-label="' . esc_attr( $row['name'] ) . '" data-hsw-tax="' . esc_attr( $tax ) . '" data-hsw-value="' . esc_attr( $row['slug'] ) . '"></button>';
                }
                $body .= '</div>';
                $body .= $this->nojs_checklist( $tax, $rows, $sel );
                return $this->box_shell( $title, $body, $box['open'] );
        }

        /**
         * No-JS fallback for chip/swatch attribute boxes: a plain multi-checkbox
         * list, only parsed by browsers when scripting is disabled. It posts
         * `filter_tax[]` values, which HSW_Archive_Query::normalize_filter_params()
         * turns into the CSV the WooCommerce filter contract expects.
         *
         * @param string $tax  Attribute taxonomy.
         * @param array  $rows Term rows.
         * @param array  $sel  Selected slugs.
         * @return string
         */
        protected function nojs_checklist( $tax, $rows, $sel ) {
                $html = '<noscript><div class="hsw-af__nojslist"><div class="hsw-af__checks">';
                foreach ( $rows as $row ) {
                        $checked = in_array( $row['slug'], $sel, true );
                        $html   .= '<label class="hsw-af__checkrow"><span class="hsw-af__checkname">';
                        $html   .= '<input type="checkbox" class="hsw-af__check" name="filter_' . esc_attr( $tax ) . '[]" value="' . esc_attr( $row['slug'] ) . '"' . checked( $checked, true, false ) . '>';
                        $html   .= '<span>' . esc_html( $row['name'] ) . '</span></span></label>';
                }
                $html .= '</div></div></noscript>';
                return $html;
        }

        /**
         * Price box: max slider + from/to number inputs (works without JS).
         */
        protected function box_price( $box ) {
                $price = $this->price_state();
                $step  = (int) $price['step'];
                $cur   = $this->currency_label();

                $min_value = $price['has_from'] ? (string) (float) $price['from'] : '';
                $max_value = $price['has_to'] ? (string) (float) $price['to'] : '';
                $min_ph    = HSW_Helpers::price_num( $price['min'] );
                $max_ph    = HSW_Helpers::price_num( $price['max'] );

                /*
                 * Dual-handle price range.
                 *
                 * Before: one handle (max only), and the number inputs carried
                 * `step="10000"`, so typing 95000 made the browser refuse the
                 * whole form with «The two nearest valid values are 90000 and
                 * 100000». The ranges now have two handles and the number fields
                 * use step="any" with no min/max attributes — the server already
                 * clamps the values in price_state().
                 */
                /*
                 * The handles use a finer step than the server's rounding so a
                 * typed value (e.g. 95,500) is not snapped to the nearest 10,000
                 * and left disagreeing with the number field.
                 */
                $slide = max( 1, (int) ( $step / 10 ) );

                $body  = '<div class="hsw-af__sliderwrap" data-hsw-pricerange>';
                $body .= '<div class="hsw-af__pricetrack" aria-hidden="true"><span class="hsw-af__pricefill" data-hsw-pricefill></span></div>';
                $body .= '<input type="range" class="hsw-af__range hsw-af__range--min" min="' . esc_attr( $price['min'] ) . '" max="' . esc_attr( $price['max'] ) . '" value="' . esc_attr( $price['from'] ) . '" step="' . esc_attr( $slide ) . '" data-hsw-priceminrange aria-label="' . esc_attr__( 'حداقل قیمت', 'hamishop-elementor-widgets' ) . '">';
                $body .= '<input type="range" class="hsw-af__range hsw-af__range--max" min="' . esc_attr( $price['min'] ) . '" max="' . esc_attr( $price['max'] ) . '" value="' . esc_attr( $price['to'] ) . '" step="' . esc_attr( $slide ) . '" data-hsw-pricemax aria-label="' . esc_attr__( 'حداکثر قیمت', 'hamishop-elementor-widgets' ) . '">';
                $body .= '</div>';
                $body .= '<div class="hsw-af__pricetwo">';
                $body .= '<label class="hsw-af__pricecell"><span class="hsw-af__plabel">' . esc_html__( 'از:', 'hamishop-elementor-widgets' ) . '</span>';
                $body .= '<input type="number" class="hsw-af__pnum" name="min_price" value="' . esc_attr( $min_value ) . '" placeholder="' . esc_attr( $min_ph ) . '" step="any" inputmode="numeric" data-hsw-pricemin data-hsw-auto></label>';
                $body .= '<label class="hsw-af__pricecell is-end"><span class="hsw-af__plabel">' . esc_html__( 'تا:', 'hamishop-elementor-widgets' ) . ' <span class="hsw-af__unit">' . esc_html( $cur ) . '</span></span>';
                $body .= '<input type="number" class="hsw-af__pnum" name="max_price" value="' . esc_attr( $max_value ) . '" placeholder="' . esc_attr( $max_ph ) . '" step="any" inputmode="numeric" data-hsw-pricemaxinput data-hsw-auto></label>';
                $body .= '</div>';

                return $this->box_shell( $this->box_title( $box ), $body, $box['open'], false );
        }

        /**
         * Rating box: 5 → 1 stars as checkbox rows.
         */
        protected function box_rating( $box ) {
                $params  = $this->current_params();
                $current = isset( $params['rating_filter'] ) ? (int) $params['rating_filter'] : 0;

                $body = '<div class="hsw-af__checks">';
                for ( $i = 5; $i >= 1; $i-- ) {
                        $body .= '<label class="hsw-af__checkrow">';
                        $body .= '<span class="hsw-af__checkname">';
                        $body .= '<input type="checkbox" class="hsw-af__check" name="rating_filter" value="' . esc_attr( $i ) . '"' . checked( $current === $i, true, false ) . ' data-hsw-auto>';
                        $body .= '<span class="hsw-af__stars">';
                        for ( $s = 0; $s < 5; $s++ ) {
                                $body .= HSW_Helpers::icon( 'star', $s < $i ? 'is-on' : '', $s < $i );
                        }
                        $body .= '</span>';
                        $body .= '</span>';
                        $body .= '</label>';
                }
                $body .= '</div>';

                return $this->box_shell( $this->box_title( $box ), $body, $box['open'], false );
        }

        /**
         * Switches box: stock + express (switch rows).
         */
        protected function box_switches( $box ) {
                $params  = $this->current_params();
                $instock = isset( $params['instock'] ) && '1' === $params['instock'];
                $express = isset( $params['express'] ) && '1' === $params['express'];
                $onsale  = isset( $params['onsale'] ) && '1' === $params['onsale'];

                $rows = '';
                if ( ! empty( $box['stock'] ) ) {
                        $rows .= $this->switch_row( __( 'فقط کالاهای موجود', 'hamishop-elementor-widgets' ), 'instock', $instock );
                }
                if ( ! empty( $box['express'] ) && $this->has_express() ) {
                        $rows .= $this->switch_row( __( 'ارسال فوری (تحویل امروز)', 'hamishop-elementor-widgets' ), 'express', $express );
                }
                if ( ! empty( $box['onsale'] ) ) {
                        $rows .= $this->switch_row( __( 'فقط تخفیف‌دارها', 'hamishop-elementor-widgets' ), 'onsale', $onsale );
                }
                if ( '' === $rows ) {
                        return '';
                }

                $body = '<div class="hsw-af__switchbox">' . $rows . '</div>';
                return $this->box_shell( $this->box_title( $box ), $body, $box['open'], false );
        }

        /**
         * One switch row.
         */
        protected function switch_row( $label, $name, $checked ) {
                return '<label class="hsw-af__switchrow">' .
                        '<span>' . esc_html( $label ) . '</span>' .
                        '<span class="hsw-af__switch">' .
                                '<input type="checkbox" class="hsw-af__switchinput" name="' . esc_attr( $name ) . '" value="1"' . checked( $checked, true, false ) . ' data-hsw-auto>' .
                                '<span class="hsw-af__track"></span>' .
                                '<span class="hsw-af__knob"></span>' .
                        '</span>' .
                        '</label>';
        }

        /* =========================================================================
         *  RENDER — the form
         * ====================================================================== */

        /**
         * Currency label for the price box (WC symbol by default).
         */
        protected function currency_label() {
                $opt = $this->opt( 'currency', '' );
                return '' !== $opt ? $opt : HSW_Helpers::currency_label();
        }

        /**
         * Pass-through hidden fields (orderby/view/unknown params).
         *
         * @param string[] $rendered_taxes Attribute taxonomies rendered as boxes.
         */
        protected function pass_through_fields( $rendered_taxes ) {
                $skip = array( 'paged', 'instock', 'express', 'onsale', 'rating_filter', 'min_price', 'max_price' );
                $html = '';
                foreach ( $this->current_params() as $key => $value ) {
                        if ( '' === (string) $value || in_array( $key, $skip, true ) ) {
                                continue;
                        }
                        if ( 0 === strpos( $key, 'filter_' ) && in_array( substr( $key, 7 ), $rendered_taxes, true ) ) {
                                continue;
                        }
                        $html .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
                }
                return $html;
        }

        protected function render() {
                if ( ! $this->is_demo() && ! $this->is_archive_context() ) {
                        return;
                }

                $f_ttl = $this->opt( 'f_title', __( 'فیلترهای پیشرفته', 'hamishop-elementor-widgets' ) );

                $boxes  = $this->boxes();
                $active = $this->active_filter_count();
                $taxes  = array();
                foreach ( $boxes as $box ) {
                        if ( 'attr' === $box['kind'] && ! empty( $box['attr'] ) ) {
                                $taxes[] = $box['attr'];
                        }
                }

                $reset_changes = array(
                        'instock'       => null,
                        'express'       => null,
                        'onsale'        => null,
                        'rating_filter' => null,
                        'min_price'     => null,
                        'max_price'     => null,
                );
                foreach ( $taxes as $tax ) {
                        $reset_changes[ 'filter_' . $tax ]     = null;
                        $reset_changes[ 'query_type_' . $tax ] = null;
                }
                /*
                 * Also clear any filter that is in the URL but whose box is not
                 * rendered on this page (empty attribute, box removed from the
                 * template, a link shared by someone else…). Without this the
                 * reset link kept that parameter and the archive stayed filtered.
                 */
                foreach ( array_keys( $this->current_params() ) as $param_key ) {
                        if ( 0 === strpos( (string) $param_key, 'filter_' ) || 0 === strpos( (string) $param_key, 'query_type_' ) ) {
                                $reset_changes[ $param_key ] = null;
                        }
                }
                /*
                 * «حذف همه» must leave the category archive too. Resetting on
                 * `archive_url()` kept `/product-category/…/` in the path, so the
                 * grid stayed filtered by category and the button looked dead.
                 * The shop permalink is the real "no filters" destination; only
                 * the view switch (grid/list) survives.
                 */
                $reset_base = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : $this->archive_url();
                $reset      = $this->filter_url( $reset_changes, $reset_base );

                /* Store inputs live once per form: chips/swatches write here. */
                $store = '';
                foreach ( $boxes as $box ) {
                        if ( 'attr' === $box['kind'] && 'checks' !== $this->effective_style( $box ) ) {
                                $store .= '<input type="hidden" name="filter_' . esc_attr( $box['attr'] ) . '" value="' . esc_attr( implode( ',', $this->selected_slugs( $box['attr'] ) ) ) . '" data-hsw-taxinput="' . esc_attr( $box['attr'] ) . '">';
                        }
                }

                $body_d = '';
                $body_m = '';
                foreach ( $boxes as $box ) {
                        $html = $this->box_html( $box );
                        if ( '' === $html ) {
                                continue;
                        }
                        $body_d .= $html;
                        $body_m .= $html;
                }

                $found = $this->is_demo() ? (int) $this->demo()['count'] : $this->count_found();
                ?>
                <div class="<?php echo esc_attr( $this->root_class( 'af' ) ); ?>">

                        <form method="get" action="<?php echo esc_url( $this->archive_url() ); ?>" class="hsw-af__form">
                                <?php echo $this->pass_through_fields( $taxes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the method. ?>
                                <template data-hsw-store><?php echo $store; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></template>

                                <!-- =================== DESKTOP SIDEBAR =================== -->
                                <aside class="hsw-af__side hsw-d">
                                        <div class="hsw-af__box hsw-af__box--head">
                                                <div class="hsw-af__headrow">
                                                        <span class="hsw-af__headtitle"><?php echo HSW_Helpers::icon( 'tune', 'hsw-af__headic' ); ?><?php echo esc_html( $f_ttl ); ?></span>
                                                        <a class="hsw-af__reset" href="<?php echo esc_url( $reset ); ?>" data-hsw-reset data-hsw-reset-url="<?php echo esc_url( $reset_base ); ?>"><?php echo HSW_Helpers::icon( 'close' ); ?><?php esc_html_e( 'حذف همه', 'hamishop-elementor-widgets' ); ?></a>
                                                </div>
                                        </div>

                                        <?php echo $body_d; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above. ?>

                                        <button type="submit" class="hsw-af__apply hsw-af__apply--side hsw-nojs">
                                                <?php esc_html_e( 'اعمال فیلترها', 'hamishop-elementor-widgets' ); ?>
                                        </button>
                                </aside>

                                <!-- =================== MOBILE BOTTOM SHEET =================== -->
                                <?php echo HSW_Assets::sheet_guard(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal style tag. ?>
                                <div class="hsw-asheet hsw-asheet--filter hsw-m" data-hsw-sheet="hsw-filter" aria-hidden="true">
                                        <div class="hsw-asheet__backdrop" data-hsw-close></div>
                                        <div class="hsw-asheet__panel">
                                                <div class="hsw-asheet__head">
                                                        <span class="hsw-asheet__ttl"><?php esc_html_e( 'فیلترهای پیشرفته', 'hamishop-elementor-widgets' ); ?></span>
                                                        <?php if ( $active ) : ?>
                                                                <span class="hsw-asheet__count"><?php echo esc_html( HSW_Helpers::fa_num( $active ) ); ?> <?php esc_html_e( 'فیلتر فعال', 'hamishop-elementor-widgets' ); ?></span>
                                                        <?php endif; ?>
                                                        <button type="button" class="hsw-asheet__close" data-hsw-close aria-label="<?php esc_attr_e( 'بستن', 'hamishop-elementor-widgets' ); ?>"><?php echo HSW_Helpers::icon( 'close' ); ?></button>
                                                </div>

                                                <div class="hsw-asheet__body">
                                                        <?php echo $body_m; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above. ?>
                                                </div>

                                                <div class="hsw-asheet__foot">
                                                        <a class="hsw-af__clear" href="<?php echo esc_url( $reset ); ?>" data-hsw-reset data-hsw-reset-url="<?php echo esc_url( $reset_base ); ?>"><?php esc_html_e( 'حذف همه', 'hamishop-elementor-widgets' ); ?></a>
                                                        <button type="submit" class="hsw-af__apply">
                                                                <?php esc_html_e( 'اعمال فیلترها', 'hamishop-elementor-widgets' ); ?>
                                                                <?php if ( $found ) : ?>
                                                                        (<?php echo esc_html( HSW_Helpers::fa_num( $found ) ); ?>)
                                                                <?php endif; ?>
                                                        </button>
                                                </div>
                                        </div>
                                </div>
                        </form>
                </div>
                <?php
        }

        /**
         * found_posts (0 outside archive).
         */
        protected function count_found() {
                global $wp_query;
                return ( $wp_query && isset( $wp_query->found_posts ) ) ? (int) $wp_query->found_posts : 0;
        }
}
