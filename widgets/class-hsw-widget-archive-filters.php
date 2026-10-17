<?php
/**
 * Widget: Archive advanced filters — faithful conversion of the supplied design.
 * Desktop: sidebar boxes (categories tree, stock/express switches, price range,
 * brand search+checkboxes, storage chips, color swatches).
 * Mobile: bottom sheet with the same filters + sticky trigger handled by the
 * toolbar widget via data-hsw-open="hsw-filter".
 * Real WooCommerce filtering: GET form → min_price/max_price, filter_{tax},
 * instock (handled by HSW_Archive_Query).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class HSW_Widget_Archive_Filters extends HSW_Archive_Base {

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

                $this->add_control(
                        'sec_cats',
                        array( 'label' => __( 'باکس دسته‌بندی‌ها', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'separator' => 'before' )
                );
                $this->add_control(
                        'sec_switches',
                        array( 'label' => __( 'باکس موجودی و ارسال', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' )
                );
                $this->add_control(
                        'sec_price',
                        array( 'label' => __( 'باکس محدوده قیمت', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' )
                );
                $this->add_control(
                        'sec_brand',
                        array( 'label' => __( 'باکس برند', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' )
                );
                $this->add_control(
                        'sec_memory',
                        array( 'label' => __( 'باکس حافظه داخلی', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' )
                );
                $this->add_control(
                        'sec_color',
                        array( 'label' => __( 'باکس رنگ', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' )
                );

                $this->add_control(
                        'attr_brand',
                        array(
                                'label'   => __( 'اسلاگ صفت برند', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => 'pa_brand',
                                'separator' => 'before',
                        )
                );

                $this->add_control(
                        'attr_memory',
                        array(
                                'label'       => __( 'اسلاگ صفت حافظه', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => 'pa_memory',
                                'description' => __( 'ترم‌های این صفت به‌صورت چیپ (دسکتاپ/موبایل) فیلتر می‌کنند.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'attr_color',
                        array(
                                'label'   => __( 'اسلاگ صفت رنگ', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => 'pa_color',
                        )
                );

                $repeater = new Repeater();
                $repeater->add_control(
                        'name',
                        array( 'label' => __( 'نام رنگ (ترم صفت)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::TEXT )
                );
                $repeater->add_control(
                        'hex',
                        array( 'label' => __( 'کد رنگ', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::COLOR, 'alpha' => false )
                );
                $this->add_control(
                        'color_map',
                        array(
                                'label'       => __( 'نقشه رنگ‌ها (نام ← کد)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $repeater->get_controls(),
                                'title_field' => '{{{ name }}}',
                                'default'     => array(
                                        array( 'name' => 'مشکی تیتانیوم', 'hex' => '#0b0b0d' ),
                                        array( 'name' => 'نقره‌ای', 'hex' => '#e2e8f0' ),
                                        array( 'name' => 'طلایی', 'hex' => '#fef3c7' ),
                                        array( 'name' => 'آبی اقیانوسی', 'hex' => '#1d4ed8' ),
                                        array( 'name' => 'سبز یشمی', 'hex' => '#059669' ),
                                        array( 'name' => 'بنفش دیپ', 'hex' => '#9333ea' ),
                                ),
                                'condition'   => array( 'sec_color' => 'yes' ),
                        )
                );

                $this->add_control(
                        'express_note',
                        array(
                                'label'       => __( 'کلاس ارسال فوری', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => '',
                                'description' => __( 'اسلاگ shipping-class کالاهای ارسال فوری؛ خالی = کلید «ارسال فوری» مخفی می‌شود. فیلتر: express=1', 'hamishop-elementor-widgets' ),
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
                $this->a_text( 'fb_i', '{{WRAPPER}} .hsw-af__row, {{WRAPPER}} .hsw-af__link', array( 'default' => 12, 'weight' => false ) );
                $this->a_color( 'fb_active', __( 'رنگ فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .is-active' );
                $this->a_color( 'fb_ic', __( 'رنگ آیکون اصلی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-af__headic, {{WRAPPER}} .hsw-af__apply' );
                $this->a_color( 'fb_switch', __( 'رنگ کلید سوییچ', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-af__switch input:checked + .hsw-af__track', 'background-color' );
                $this->a_color( 'fb_chip_bg', __( 'پس‌زمینه چیپ فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-af__mem.is-active', 'background-color' );
                $this->a_color( 'fb_chip_bd', __( 'حاشیه چیپ فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-af__mem.is-active', 'border-color' );
                $this->a_color( 'fb_sheet_bg', __( 'پس‌زمینه باتم‌شیت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-asheet__panel, {{WRAPPER}} .hsw-af__box', 'background-color' );
                $this->a_color( 'fb_apply', __( 'پس‌زمینه دکمه اعمال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-af__apply', 'background-color' );

                $this->end_controls_section();
        }

        /* ============================ DATA ============================ */

        /**
         * Categories box model: root → current(+count) → leafs.
         */
        protected function cat_tree() {
                if ( $this->is_demo() || ! $this->is_archive_context() ) {
                        $d = $this->demo()['cats'];
                        return array( 'root' => $d['root'], 'mid' => $d['mid'], 'leafs' => $d['leafs'] );
                }
                $term = $this->current_term();
                if ( ! $term || 'product_cat' !== $term->taxonomy ) {
                        return array();
                }
                $root_id = $term->parent ? $term->parent : 0;
                $root    = $root_id ? get_term( $root_id, 'product_cat' ) : null;
                $leafs   = get_terms(
                        array(
                                'taxonomy'   => 'product_cat',
                                'parent'     => $term->term_id,
                                'hide_empty' => false,
                                'number'     => 8,
                        )
                );
                return array(
                        'root'  => ( $root && ! is_wp_error( $root ) ) ? array( 'name' => $root->name, 'count' => (int) $root->count, 'url' => get_term_link( $root ) ) : null,
                        'mid'   => array( 'name' => $term->name, 'count' => (int) $term->count, 'url' => get_term_link( $term ) ),
                        'leafs' => is_wp_error( $leafs ) ? array() : $leafs,
                );
        }

        /**
         * Brand rows: [ name, count, slug, checked ].
         */
        protected function brand_rows() {
                $tax = $this->opt( 'attr_brand', 'pa_brand' );
                if ( $this->is_demo() || ! $this->is_archive_context() ) {
                        $rows = array();
                        foreach ( $this->demo()['brands'] as $b ) {
                                $rows[] = array( 'name' => $b['name'], 'count' => $b['count'], 'slug' => sanitize_title( $b['name'] ), 'checked' => $b['sel'] );
                        }
                        return $rows;
                }
                $sel  = $this->selected_slugs( $tax );
                $rows = array();
                foreach ( $this->filter_terms( $tax ) as $t ) {
                        $rows[] = array( 'name' => $t->name, 'count' => (int) $t->count, 'slug' => $t->slug, 'checked' => in_array( $t->slug, $sel, true ) );
                }
                return $rows;
        }

        /**
         * Memory chips: [ name, slug, checked ].
         */
        protected function memory_rows() {
                $tax = $this->opt( 'attr_memory', 'pa_memory' );
                if ( $this->is_demo() || ! $this->is_archive_context() ) {
                        $rows = array();
                        foreach ( $this->demo()['storages'] as $i => $m ) {
                                $rows[] = array( 'name' => $m, 'slug' => sanitize_title( $m ), 'checked' => 0 === $i );
                        }
                        return $rows;
                }
                $sel  = $this->selected_slugs( $tax );
                $rows = array();
                foreach ( $this->filter_terms( $tax ) as $t ) {
                        $rows[] = array( 'name' => $t->name, 'slug' => $t->slug, 'checked' => in_array( $t->slug, $sel, true ) );
                }
                return $rows;
        }

        /**
         * Color swatches: [ name, hex, checked ].
         */
        protected function color_rows() {
                $tax = $this->opt( 'attr_color', 'pa_color' );
                if ( $this->is_demo() || ! $this->is_archive_context() ) {
                        $rows = array();
                        foreach ( $this->demo()['colors'] as $i => $c ) {
                                $rows[] = array( 'name' => $c['name'], 'hex' => $c['hex'], 'checked' => 0 === $i );
                        }
                        return $rows;
                }
                $map = array();
                $s   = $this->get_settings_for_display();
                foreach ( (array) ( isset( $s['color_map'] ) ? $s['color_map'] : array() ) as $row ) {
                        $nm = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
                        if ( '' !== $nm ) {
                                $map[ $nm ] = ( isset( $row['hex'] ) && '' !== (string) $row['hex'] ) ? (string) $row['hex'] : '#cccccc';
                        }
                }
                $sel  = $this->selected_slugs( $tax );
                $rows = array();
                foreach ( $this->filter_terms( $tax ) as $t ) {
                        $rows[] = array(
                                'name'    => $t->name,
                                'hex'     => isset( $map[ $t->name ] ) ? $map[ $t->name ] : '#cccccc',
                                'checked' => in_array( $t->slug, $sel, true ),
                                'slug'    => $t->slug,
                        );
                }
                return $rows;
        }

        /**
         * Price slider bounds + current selection.
         */
        protected function price_state() {
                $b = $this->price_bounds();
                $p = $this->current_params();
                return array(
                        'min'  => $b['min'],
                        'max'  => $b['max'],
                        'from' => isset( $p['min_price'] ) && '' !== $p['min_price'] ? (float) $p['min_price'] : $b['min'],
                        'to'   => isset( $p['max_price'] ) && '' !== $p['max_price'] ? (float) $p['max_price'] : $b['max'],
                );
        }

        /**
         * Toggle states.
         */
        protected function switch_states() {
                $p = $this->current_params();
                $express_slug = $this->opt( 'express_slug', '' );
                return array(
                        'instock' => isset( $p['instock'] ) && '1' === $p['instock'],
                        'express' => isset( $p['express'] ) && '1' === $p['express'],
                        'has_express' => '' !== $express_slug || $this->is_demo(),
                );
        }

        /* ============================ RENDER ============================ */

        protected function render() {
                if ( ! $this->is_demo() && ! $this->is_archive_context() ) {
                        return;
                }

                $s     = $this->get_settings_for_display();
                $d     = $this->demo();
                $f_ttl = $this->opt( 'f_title', __( 'فیلترهای پیشرفته', 'hamishop-elementor-widgets' ) );

                $cats    = $this->cat_tree();
                $brands  = $this->brand_rows();
                $mems    = $this->memory_rows();
                $colors  = $this->color_rows();
                $price   = $this->price_state();
                $switch  = $this->switch_states();
                $active  = $this->active_filter_count();
                $reset   = $this->filter_url( array( 'instock' => null, 'express' => null, 'min_price' => null, 'max_price' => null, 'filter_' . $this->opt( 'attr_brand', 'pa_brand' ) => null, 'filter_' . $this->opt( 'attr_memory', 'pa_memory' ) => null, 'filter_' . $this->opt( 'attr_color', 'pa_color' ) => null ) );

                $tax_brand  = $this->opt( 'attr_brand', 'pa_brand' );
                $tax_memory = $this->opt( 'attr_memory', 'pa_memory' );
                $tax_color  = $this->opt( 'attr_color', 'pa_color' );
                ?>
                <div class="<?php echo esc_attr( $this->root_class( 'af' ) ); ?>"
                        data-hsw-tax-brand="<?php echo esc_attr( $tax_brand ); ?>"
                        data-hsw-tax-memory="<?php echo esc_attr( $tax_memory ); ?>"
                        data-hsw-tax-color="<?php echo esc_attr( $tax_color ); ?>">

                        <form method="get" action="<?php echo esc_url( $this->archive_url() ); ?>" class="hsw-af__form">

                                <!-- =================== DESKTOP SIDEBAR =================== -->
                                <aside class="hsw-af__side hsw-d">

                                        <!-- header & reset -->
                                        <div class="hsw-af__box hsw-af__box--head">
                                                <div class="hsw-af__headrow">
                                                        <span class="hsw-af__headtitle"><i class="hsw-ic hsw-af__headic" aria-hidden="true">tune</i><?php echo esc_html( $f_ttl ); ?></span>
                                                        <a class="hsw-af__reset" href="<?php echo esc_url( $reset ); ?>"><i class="hsw-ic" aria-hidden="true">close</i><?php esc_html_e( 'حذف همه', 'hamishop-elementor-widgets' ); ?></a>
                                                </div>
                                        </div>

                                        <?php if ( 'yes' === ( $s['sec_cats'] ?? 'yes' ) && ! empty( $cats ) ) : ?>
                                        <div class="hsw-af__box" data-hsw-collapse>
                                                <button type="button" class="hsw-af__boxhead">
                                                        <span class="hsw-af__boxtitle"><?php esc_html_e( 'دسته‌بندی‌ها', 'hamishop-elementor-widgets' ); ?></span>
                                                        <i class="hsw-ic hsw-af__chev" aria-hidden="true">expand_more</i>
                                                </button>
                                                <ul class="hsw-af__tree">
                                                        <?php if ( ! empty( $cats['root'] ) ) : ?>
                                                                <li>
                                                                        <a class="hsw-af__link" href="<?php echo esc_url( $cats['root']['url'] ); ?>">
                                                                                <span><?php echo esc_html( $cats['root']['name'] ); ?></span>
                                                                                <span class="hsw-af__cnt">(<?php echo esc_html( HSW_Helpers::fa_num( $cats['root']['count'] ) ); ?>)</span>
                                                                        </a>
                                                                        <ul class="hsw-af__branch">
                                                                                <li>
                                                                                        <a class="hsw-af__link is-active" href="<?php echo esc_url( $cats['mid']['url'] ); ?>">
                                                                                                <span><?php echo esc_html( $cats['mid']['name'] ); ?></span>
                                                                                                <span class="hsw-af__badge"><?php echo esc_html( HSW_Helpers::fa_num( $cats['mid']['count'] ) ); ?></span>
                                                                                        </a>
                                                                                        <ul class="hsw-af__leafs">
                                                                                                <?php foreach ( $cats['leafs'] as $leaf ) : ?>
                                                                                                        <li><a class="hsw-af__link" href="<?php echo esc_url( get_term_link( $leaf ) ); ?>"><span><?php echo esc_html( $leaf->name ); ?></span><span class="hsw-af__cnt"><?php echo esc_html( HSW_Helpers::fa_num( (int) $leaf->count ) ); ?></span></a></li>
                                                                                                <?php endforeach; ?>
                                                                                        </ul>
                                                                                </li>
                                                                        </ul>
                                                                </li>
                                                        <?php else : ?>
                                                                <li>
                                                                        <a class="hsw-af__link is-active" href="<?php echo esc_url( $cats['mid']['url'] ); ?>">
                                                                                <span><?php echo esc_html( $cats['mid']['name'] ); ?></span>
                                                                                <span class="hsw-af__badge"><?php echo esc_html( HSW_Helpers::fa_num( $cats['mid']['count'] ) ); ?></span>
                                                                        </a>
                                                                        <?php if ( ! empty( $cats['leafs'] ) ) : ?>
                                                                                <ul class="hsw-af__leafs">
                                                                                        <?php foreach ( $cats['leafs'] as $leaf ) : ?>
                                                                                                <li><a class="hsw-af__link" href="<?php echo esc_url( get_term_link( $leaf ) ); ?>"><span><?php echo esc_html( $leaf->name ); ?></span><span class="hsw-af__cnt"><?php echo esc_html( HSW_Helpers::fa_num( (int) $leaf->count ) ); ?></span></a></li>
                                                                                        <?php endforeach; ?>
                                                                                </ul>
                                                                        <?php endif; ?>
                                                                </li>
                                                        <?php endif; ?>
                                                </ul>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ( 'yes' === ( $s['sec_switches'] ?? 'yes' ) ) : ?>
                                        <div class="hsw-af__box hsw-af__box--switches">
                                                <label class="hsw-af__switchrow">
                                                        <span><?php esc_html_e( 'فقط کالاهای موجود', 'hamishop-elementor-widgets' ); ?></span>
                                                        <span class="hsw-af__switch">
                                                                <input type="checkbox" name="instock" value="1" <?php checked( $switch['instock'] ); ?> class="hsw-af__switchinput" data-hsw-autosubmit>
                                                                <span class="hsw-af__track"></span>
                                                                <span class="hsw-af__knob"></span>
                                                        </span>
                                                </label>
                                                <?php if ( $switch['has_express'] ) : ?>
                                                        <div class="hsw-af__hr"></div>
                                                        <label class="hsw-af__switchrow">
                                                                <span><?php esc_html_e( 'ارسال فوری (تحویل امروز)', 'hamishop-elementor-widgets' ); ?></span>
                                                                <span class="hsw-af__switch">
                                                                        <input type="checkbox" name="express" value="1" <?php checked( $switch['express'] ); ?> class="hsw-af__switchinput" data-hsw-autosubmit>
                                                                        <span class="hsw-af__track"></span>
                                                                        <span class="hsw-af__knob"></span>
                                                                </span>
                                                        </label>
                                                <?php endif; ?>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ( 'yes' === ( $s['sec_price'] ?? 'yes' ) ) : ?>
                                        <div class="hsw-af__box">
                                                <div class="hsw-af__boxhead">
                                                        <span class="hsw-af__boxtitle"><?php esc_html_e( 'محدوده قیمت', 'hamishop-elementor-widgets' ); ?></span>
                                                        <span class="hsw-af__unit"><?php esc_html_e( 'تومان', 'hamishop-elementor-widgets' ); ?></span>
                                                </div>
                                                <div class="hsw-af__sliderwrap">
                                                        <input type="range" class="hsw-af__range" min="<?php echo esc_attr( $price['min'] ); ?>" max="<?php echo esc_attr( $price['max'] ); ?>" value="<?php echo esc_attr( $price['to'] ); ?>" step="100000" data-hsw-pricemax>
                                                </div>
                                                <div class="hsw-af__pricetwo">
                                                        <div class="hsw-af__pricecell">
                                                                <span class="hsw-af__plabel"><?php esc_html_e( 'از:', 'hamishop-elementor-widgets' ); ?></span>
                                                                <input type="number" class="hsw-af__pnum" name="min_price" value="<?php echo esc_attr( $price['from'] ); ?>" min="<?php echo esc_attr( $price['min'] ); ?>" max="<?php echo esc_attr( $price['max'] ); ?>" step="100000" inputmode="numeric" data-hsw-pricemin>
                                                        </div>
                                                        <div class="hsw-af__pricecell is-end">
                                                                <span class="hsw-af__plabel"><?php esc_html_e( 'تا:', 'hamishop-elementor-widgets' ); ?></span>
                                                                <input type="number" class="hsw-af__pnum" name="max_price" value="<?php echo esc_attr( $price['to'] ); ?>" min="<?php echo esc_attr( $price['min'] ); ?>" max="<?php echo esc_attr( $price['max'] ); ?>" step="100000" inputmode="numeric" data-hsw-pricemaxinput>
                                                        </div>
                                                </div>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ( 'yes' === ( $s['sec_brand'] ?? 'yes' ) && ! empty( $brands ) ) : ?>
                                        <div class="hsw-af__box" data-hsw-collapse>
                                                <button type="button" class="hsw-af__boxhead">
                                                        <span class="hsw-af__boxtitle"><?php esc_html_e( 'برند محصول', 'hamishop-elementor-widgets' ); ?></span>
                                                        <i class="hsw-ic hsw-af__chev" aria-hidden="true">expand_more</i>
                                                </button>
                                                <div class="hsw-af__search">
                                                        <input type="text" class="hsw-af__searchinput" placeholder="<?php esc_attr_e( 'جستجوی نام برند...', 'hamishop-elementor-widgets' ); ?>" data-hsw-brandsearch>
                                                        <i class="hsw-ic" aria-hidden="true">search</i>
                                                </div>
                                                <div class="hsw-af__checks" data-hsw-brandlist>
                                                        <?php foreach ( $brands as $b ) : ?>
                                                                <label class="hsw-af__checkrow">
                                                                        <span class="hsw-af__checkname">
                                                                                <input type="checkbox" class="hsw-af__check" name="filter_<?php echo esc_attr( $tax_brand ); ?>" value="<?php echo esc_attr( $b['slug'] ); ?>" <?php checked( $b['checked'] ); ?> data-hsw-autosubmit-multi>
                                                                                <span><?php echo esc_html( $b['name'] ); ?></span>
                                                                        </span>
                                                                        <span class="hsw-af__cnt"><?php echo esc_html( HSW_Helpers::fa_num( $b['count'] ) ); ?></span>
                                                                </label>
                                                        <?php endforeach; ?>
                                                </div>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ( 'yes' === ( $s['sec_memory'] ?? 'yes' ) && ! empty( $mems ) ) : ?>
                                        <div class="hsw-af__box">
                                                <div class="hsw-af__boxtitle"><?php esc_html_e( 'حافظه داخلی', 'hamishop-elementor-widgets' ); ?></div>
                                                <div class="hsw-af__mems">
                                                        <?php foreach ( $mems as $m ) : ?>
                                                                <button type="button" class="hsw-af__mem<?php echo $m['checked'] ? ' is-active' : ''; ?>" data-hsw-mem="<?php echo esc_attr( $m['slug'] ); ?>" data-hsw-tax="<?php echo esc_attr( $tax_memory ); ?>"><?php echo esc_html( $m['name'] ); ?></button>
                                                        <?php endforeach; ?>
                                                </div>
                                                <input type="hidden" name="filter_<?php echo esc_attr( $tax_memory ); ?>" value="<?php echo esc_attr( implode( ',', wp_list_pluck( array_filter( $mems, function ( $m ) { return $m['checked']; } ) ), 'slug' ) ); ?>" data-hsw-meminput="<?php echo esc_attr( $tax_memory ); ?>">
                                        </div>
                                        <?php endif; ?>

                                        <?php if ( 'yes' === ( $s['sec_color'] ?? 'yes' ) && ! empty( $colors ) ) : ?>
                                        <div class="hsw-af__box">
                                                <div class="hsw-af__boxtitle"><?php esc_html_e( 'رنگ محصول', 'hamishop-elementor-widgets' ); ?></div>
                                                <div class="hsw-af__swatches">
                                                        <?php foreach ( $colors as $c ) : ?>
                                                                <button type="button" class="hsw-af__swatch<?php echo $c['checked'] ? ' is-active' : ''; ?>" style="--sw:<?php echo esc_attr( $c['hex'] ); ?>" title="<?php echo esc_attr( $c['name'] ); ?>" aria-label="<?php echo esc_attr( $c['name'] ); ?>" data-hsw-color="<?php echo esc_attr( $c['slug'] ); ?>" data-hsw-tax="<?php echo esc_attr( $tax_color ); ?>"></button>
                                                        <?php endforeach; ?>
                                                </div>
                                                <input type="hidden" name="filter_<?php echo esc_attr( $tax_color ); ?>" value="<?php echo esc_attr( implode( ',', wp_list_pluck( array_filter( $colors, function ( $c ) { return $c['checked']; } ) ), 'slug' ) ); ?>" data-hsw-colorinput="<?php echo esc_attr( $tax_color ); ?>">
                                        </div>
                                        <?php endif; ?>

                                </aside>

                                <!-- =================== MOBILE BOTTOM SHEET =================== -->
                                <div class="hsw-asheet hsw-asheet--filter hsw-m" data-hsw-sheet="hsw-filter" aria-hidden="true">
                                        <div class="hsw-asheet__backdrop" data-hsw-close></div>
                                        <div class="hsw-asheet__panel">
                                                <div class="hsw-asheet__head">
                                                        <span class="hsw-asheet__ttl"><?php esc_html_e( 'فیلترهای پیشرفته', 'hamishop-elementor-widgets' ); ?></span>
                                                        <span class="hsw-asheet__count" data-hsw-activecount><?php echo esc_html( HSW_Helpers::fa_num( $active ) ); ?> <?php esc_html_e( 'فیلتر انتخاب شده', 'hamishop-elementor-widgets' ); ?></span>
                                                        <button type="button" class="hsw-asheet__close" data-hsw-close aria-label="<?php esc_attr_e( 'بستن', 'hamishop-elementor-widgets' ); ?>"><i class="hsw-ic" aria-hidden="true">close</i></button>
                                                </div>

                                                <div class="hsw-asheet__body">

                                                        <?php if ( 'yes' === ( $s['sec_cats'] ?? 'yes' ) && ! empty( $cats ) ) : ?>
                                                        <div class="hsw-af__mgroup">
                                                                <span class="hsw-af__mgtitle"><?php esc_html_e( 'دسته‌بندی', 'hamishop-elementor-widgets' ); ?></span>
                                                                <div class="hsw-af__mcats">
                                                                        <span class="hsw-af__mcur"><span><?php echo esc_html( $cats['mid']['name'] ); ?> (<?php esc_html_e( 'انتخاب شده', 'hamishop-elementor-widgets' ); ?>)</span><i class="hsw-ic" aria-hidden="true">check_circle</i></span>
                                                                        <?php foreach ( $cats['leafs'] as $leaf ) : ?>
                                                                                <div class="hsw-af__mleaf"><?php echo esc_html( $leaf->name ); ?> (<?php echo esc_html( HSW_Helpers::fa_num( (int) $leaf->count ) ); ?>)</div>
                                                                        <?php endforeach; ?>
                                                                </div>
                                                        </div>
                                                        <?php endif; ?>

                                                        <?php if ( 'yes' === ( $s['sec_switches'] ?? 'yes' ) ) : ?>
                                                        <label class="hsw-af__mswitchrow">
                                                                <span><?php esc_html_e( 'فقط کالاهای موجود', 'hamishop-elementor-widgets' ); ?></span>
                                                                <span class="hsw-af__switch">
                                                                        <input type="checkbox" name="instock" value="1" <?php checked( $switch['instock'] ); ?> class="hsw-af__switchinput">
                                                                        <span class="hsw-af__track"></span>
                                                                        <span class="hsw-af__knob"></span>
                                                                </span>
                                                        </label>
                                                        <?php endif; ?>

                                                        <?php if ( 'yes' === ( $s['sec_price'] ?? 'yes' ) ) : ?>
                                                        <div class="hsw-af__mgroup">
                                                                <div class="hsw-af__mgtitle hsw-af__mgtitle--row">
                                                                        <span><?php esc_html_e( 'محدوده قیمت', 'hamishop-elementor-widgets' ); ?></span>
                                                                        <span class="hsw-af__mprice" data-hsw-mprice><?php esc_html_e( 'تا', 'hamishop-elementor-widgets' ); ?> <?php echo esc_html( HSW_Helpers::price_num( $price['to'] ) ); ?> <?php esc_html_e( 'تومان', 'hamishop-elementor-widgets' ); ?></span>
                                                                </div>
                                                                <input type="range" class="hsw-af__range" min="<?php echo esc_attr( $price['min'] ); ?>" max="<?php echo esc_attr( $price['max'] ); ?>" value="<?php echo esc_attr( $price['to'] ); ?>" step="100000" data-hsw-pricemax>
                                                                <div class="hsw-af__mbounds">
                                                                        <span><?php esc_html_e( 'از', 'hamishop-elementor-widgets' ); ?> <?php echo esc_html( HSW_Helpers::price_num( $price['min'] ) ); ?></span>
                                                                        <span><?php esc_html_e( 'تا', 'hamishop-elementor-widgets' ); ?> <?php echo esc_html( HSW_Helpers::price_num( $price['max'] ) ); ?></span>
                                                                </div>
                                                        </div>
                                                        <?php endif; ?>

                                                        <?php if ( 'yes' === ( $s['sec_brand'] ?? 'yes' ) && ! empty( $brands ) ) : ?>
                                                        <div class="hsw-af__mgroup">
                                                                <span class="hsw-af__mgtitle"><?php esc_html_e( 'برند', 'hamishop-elementor-widgets' ); ?></span>
                                                                <div class="hsw-af__mchecks">
                                                                        <?php foreach ( $brands as $b ) : ?>
                                                                                <label class="hsw-af__checkrow">
                                                                                        <span class="hsw-af__checkname">
                                                                                                <input type="checkbox" class="hsw-af__check" name="filter_<?php echo esc_attr( $tax_brand ); ?>" value="<?php echo esc_attr( $b['slug'] ); ?>" <?php checked( $b['checked'] ); ?>>
                                                                                                <span><?php echo esc_html( $b['name'] ); ?></span>
                                                                                        </span>
                                                                                        <span class="hsw-af__cnt"><?php echo esc_html( HSW_Helpers::fa_num( $b['count'] ) ); ?></span>
                                                                                </label>
                                                                        <?php endforeach; ?>
                                                                </div>
                                                        </div>
                                                        <?php endif; ?>

                                                        <?php if ( 'yes' === ( $s['sec_memory'] ?? 'yes' ) && ! empty( $mems ) ) : ?>
                                                        <div class="hsw-af__mgroup">
                                                                <span class="hsw-af__mgtitle"><?php esc_html_e( 'ظرفیت حافظه', 'hamishop-elementor-widgets' ); ?></span>
                                                                <div class="hsw-af__mmems">
                                                                        <?php foreach ( $mems as $m ) : ?>
                                                                                <button type="button" class="hsw-af__mem<?php echo $m['checked'] ? ' is-active' : ''; ?>" data-hsw-mem="<?php echo esc_attr( $m['slug'] ); ?>" data-hsw-tax="<?php echo esc_attr( $tax_memory ); ?>"><?php echo esc_html( $m['name'] ); ?></button>
                                                                        <?php endforeach; ?>
                                                                </div>
                                                        </div>
                                                        <?php endif; ?>

                                                </div>

                                                <div class="hsw-asheet__foot">
                                                        <a class="hsw-af__clear" href="<?php echo esc_url( $reset ); ?>"><?php esc_html_e( 'حذف همه', 'hamishop-elementor-widgets' ); ?></a>
                                                        <button type="submit" class="hsw-af__apply"><?php esc_html_e( 'اعمال فیلترها', 'hamishop-elementor-widgets' ); ?> (<?php echo esc_html( HSW_Helpers::fa_num( $this->is_demo() ? $d['count'] : $this->count_found() ) ); ?>)</button>
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
