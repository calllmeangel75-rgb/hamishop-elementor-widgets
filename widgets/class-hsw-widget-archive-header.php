<?php
/**
 * Widget: Archive header — faithful conversion of the supplied design.
 * Desktop: gradient hero (H1 + count pill + update note + subcategory pills
 * + SEO description with «مشاهده بیشتر» toggle).
 * Mobile: compact header (H1 + count + راهنما trigger) + horizontal chips
 * + description teaser accordion.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class HSW_Widget_Archive_Header extends HSW_Archive_Base {

        public function get_name() {
                return 'hsw-archive-header';
        }

        public function get_title() {
                return __( 'هدر و توضیحات آرشیو (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-header';
        }

        public function get_keywords() {
                return array( 'archive', 'header', 'seo', 'عنوان آرشیو', 'توضیحات دسته', 'hamishop' );
        }

        protected function register_controls() {
                $this->ctrl_palette();

                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control(
                        'title_format',
                        array(
                                'label'       => __( 'قالب عنوان', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'خرید و قیمت {name}', 'hamishop-elementor-widgets' ),
                                'description' => __( '{name} با نام دسته‌بندی/آرشیو جایگزین می‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'show_count',
                        array(
                                'label'   => __( 'قرص تعداد کالا', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'update_note',
                        array(
                                'label'       => __( 'یادداشت به‌روزرسانی (خالی = مخفی)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'به‌روزرسانی قیمت‌ها: امروز، ساعت ۱۴:۳۰', 'hamishop-elementor-widgets' ),
                                'description' => __( '{time} = تاریخ و ساعت فعلی سایت.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'pills_heading',
                        array( 'label' => __( 'قرص‌های سریع (دسکتاپ) و چیپ‌ها (موبایل)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $this->add_control(
                        'pills_source',
                        array(
                                'label'   => __( 'منبع قرص‌ها', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => 'children',
                                'options' => array(
                                        'children'  => __( 'زیر‌دسته‌های دسته فعلی', 'hamishop-elementor-widgets' ),
                                        'attribute' => __( 'ترم‌های یک صفت (برند و...)', 'hamishop-elementor-widgets' ),
                                        'manual'    => __( 'دستی', 'hamishop-elementor-widgets' ),
                                ),
                        )
                );

                $this->add_control(
                        'pills_attr',
                        array(
                                'label'       => __( 'صفت (ویژگی)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SELECT2,
                                'options'     => self::attr_options(),
                                'default'     => 'pa_brand',
                                'label_block' => true,
                                'condition'   => array( 'pills_source' => 'attribute' ),
                        )
                );

                $repeater = new Repeater();
                $repeater->add_control(
                        'label',
                        array(
                                'label'   => __( 'برچسب', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                        )
                );
                $repeater->add_control(
                        'url',
                        array(
                                'label' => __( 'لینک', 'hamishop-elementor-widgets' ),
                                'type'  => Controls_Manager::URL,
                                'options' => array( 'url', 'is_external', 'nofollow' ),
                        )
                );
                $this->add_control(
                        'pills_manual',
                        array(
                                'label'       => __( 'قرص‌های دستی', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $repeater->get_controls(),
                                'title_field' => '{{{ label }}}',
                                'condition'   => array( 'pills_source' => 'manual' ),
                        )
                );

                $this->add_control(
                        'seo_desc',
                        array(
                                'label'       => __( 'توضیحات سئو (خالی = توضیح دسته)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::WYSIWYG,
                                'default'     => '',
                                'description' => __( 'در حالت عادی از فیلد توضیحات خود دسته‌بندی ووکامرس خوانده می‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'desc_pos',
                        array(
                                'label'       => __( 'محل نمایش توضیحات آرشیو', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SELECT,
                                'default'     => 'header',
                                'separator'   => 'before',
                                'options'     => array(
                                        'header' => __( 'بالا — داخل سربرگ', 'hamishop-elementor-widgets' ),
                                        'bottom' => __( 'پایین محصولات (بالای راهنمای خرید)', 'hamishop-elementor-widgets' ),
                                        'off'    => __( 'مخفی', 'hamishop-elementor-widgets' ),
                                ),
                                'description' => __( 'در «ویجت جامع» گزینه «پایین محصولات» توضیحات را بعد از گرید و قبل از راهنمای خرید نشان می‌دهد؛ یک بار بیشتر رندر نمی‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'desc_title',
                        array(
                                'label'       => __( 'عنوان توضیحات (حالت پایین محصولات)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'دربارهٔ {name}', 'hamishop-elementor-widgets' ),
                                'description' => __( '{name} با نام دسته جایگزین می‌شود.', 'hamishop-elementor-widgets' ),
                                'condition'   => array( 'desc_pos' => 'bottom' ),
                        )
                );

                $this->add_control(
                        'desc_clamp',
                        array(
                                'label'       => __( 'تعداد خطوط پیش از «مشاهده بیشتر»', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::NUMBER,
                                'default'     => 3,
                                'min'         => 1,
                                'max'         => 12,
                                'condition'   => array( 'desc_pos!' => 'off' ),
                        )
                );

                $this->add_control(
                        'more_text',
                        array(
                                'label'     => __( 'متن دکمه بسته', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'مشاهده بیشتر', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'desc_pos!' => 'off' ),
                        )
                );

                $this->add_control(
                        'less_text',
                        array(
                                'label'     => __( 'متن دکمه باز', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'بستن', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'desc_pos!' => 'off' ),
                        )
                );

                $this->add_control(
                        'show_guide_btn',
                        array(
                                'label'        => __( 'دکمه راهنما (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'         => Controls_Manager::SWITCHER,
                                'default'      => 'yes',
                                'description'  => __( 'ویجت «راهنما و سوالات متداول» را در صفحه بگذارید تا مودال باز شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_control(
                        'hd_grad_head',
                        array( 'label' => __( 'گرادیانت سربرگ', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING )
                );
                $this->a_color( 'hd_grad_a', __( 'گرادیانت — رنگ شروع', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ahd__hero', '--hswa-hero-a' );
                $this->a_color( 'hd_grad_b', __( 'گرادیانت — رنگ پایان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ahd__hero', '--hswa-hero-b' );
                $this->add_control(
                        'hd_grad_angle',
                        array(
                                'label'       => __( 'زاویه گرادیانت (deg)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::NUMBER,
                                'min'         => 0,
                                'max'         => 360,
                                'step'        => 1,
                                'placeholder' => '270',
                                'selectors'   => array( '{{WRAPPER}} .hsw-ahd__hero' => '--hswa-hero-angle: {{VALUE}}deg;' ),
                        )
                );

                $this->a_box( 'hd', '{{WRAPPER}} .hsw-ahd__hero' );
                $this->a_text( 'hd_h1', '{{WRAPPER}} .hsw-ahd__h1', array( 'default' => 24 ) );
                $this->a_color( 'hd_count_bg', __( 'پس‌زمینه قرص تعداد', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ahd__count', 'background-color' );
                $this->a_color( 'hd_count_tx', __( 'متن قرص تعداد', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ahd__count' );
                $this->a_color( 'hd_note', __( 'متن به‌روزرسانی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ahd__note' );
                $this->a_color( 'hd_pill_bg', __( 'پس‌زمینه قرص فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ahd__pill.is-active, {{WRAPPER}} .hsw-ahd__chip.is-active', 'background-color' );
                $this->a_color( 'hd_pill_tx', __( 'متن قرص فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ahd__pill.is-active, {{WRAPPER}} .hsw-ahd__chip.is-active' );
                $this->a_color( 'hd_pill2_bg', __( 'پس‌زمینه قرص عادی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ahd__pill, {{WRAPPER}} .hsw-ahd__chip', 'background-color' );
                $this->a_color( 'hd_pill2_bd', __( 'حاشیه قرص عادی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ahd__pill, {{WRAPPER}} .hsw-ahd__chip', 'border-color' );
                $this->a_text( 'hd_desc', '{{WRAPPER}} .hsw-ahd__desc', array( 'default' => 14 ) );

                $this->add_control(
                        'hd_desc_align',
                        array(
                                'label'     => __( 'چینش متن توضیحات', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SELECT,
                                'default'   => '',
                                'options'   => array(
                                        ''        => __( 'پیش‌فرض (دوطرفه)', 'hamishop-elementor-widgets' ),
                                        'start'   => __( 'شروع (راست)', 'hamishop-elementor-widgets' ),
                                        'center'  => __( 'وسط', 'hamishop-elementor-widgets' ),
                                        'left'    => __( 'چپ', 'hamishop-elementor-widgets' ),
                                        'justify' => __( 'دوطرفه (justify)', 'hamishop-elementor-widgets' ),
                                ),
                                'selectors' => array(
                                        '{{WRAPPER}} .hsw-ahd__desc, {{WRAPPER}} .hsw-ahd__mdesc' => 'text-align: {{VALUE}};',
                                ),
                        )
                );
                $this->add_control(
                        'hd_desc_lh',
                        array(
                                'label'       => __( 'ارتفاع خط توضیحات', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::NUMBER,
                                'min'         => 1,
                                'max'         => 3,
                                'step'        => 0.05,
                                'placeholder' => '1.9',
                                'selectors'   => array(
                                        '{{WRAPPER}} .hsw-ahd__desc, {{WRAPPER}} .hsw-ahd__mdesc' => 'line-height: {{VALUE}};',
                                ),
                        )
                );

                $this->add_control(
                        'hd_dbox_head',
                        array( 'label' => __( 'باکس توضیحات (حالت پایین محصولات)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->a_box( 'hd_dbox', '{{WRAPPER}} .hsw-ahd__descbox' );
                $this->add_responsive_control(
                        'hd_dbox_pad',
                        array(
                                'label'      => __( 'فاصله داخلی', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 0, 'max' => 48 ) ),
                                'selectors'  => array( '{{WRAPPER}} .hsw-ahd__descbox' => 'padding: {{SIZE}}{{UNIT}};' ),
                        )
                );
                $this->a_text( 'hd_dtitle', '{{WRAPPER}} .hsw-ahd__dtitle', array( 'default' => 16 ) );

                $this->add_control(
                        'hd_more_head',
                        array( 'label' => __( 'دکمه «مشاهده بیشتر»', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $more_sel = '{{WRAPPER}} .hsw-ahd__more, {{WRAPPER}} .hsw-ahd__mmore';
                $this->a_color( 'hd_more_tx', __( 'رنگ متن', 'hamishop-elementor-widgets' ), $more_sel );
                $this->a_color( 'hd_more_bg', __( 'پس‌زمینه', 'hamishop-elementor-widgets' ), $more_sel, 'background-color' );
                $this->a_color( 'hd_more_bd', __( 'رنگ حاشیه', 'hamishop-elementor-widgets' ), $more_sel, 'border-color' );
                $this->add_control(
                        'hd_more_br',
                        array(
                                'label'      => __( 'گردی گوشه‌ها', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
                                'selectors'  => array( $more_sel => 'border-radius: {{SIZE}}{{UNIT}};' ),
                        )
                );
                $this->add_control(
                        'hd_more_fs',
                        array(
                                'label'      => __( 'اندازه فونت', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 8, 'max' => 24 ) ),
                                'selectors'  => array( $more_sel => 'font-size: {{SIZE}}{{UNIT}};' ),
                        )
                );
                $this->add_control(
                        'hd_more_pad',
                        array(
                                'label'      => __( 'فاصله داخلی', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 0, 'max' => 24 ) ),
                                'selectors'  => array( $more_sel => 'padding: {{SIZE}}{{UNIT}};' ),
                        )
                );
                $this->a_color( 'hd_more_tx_h', __( 'متن (هاور)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ahd__more:hover, {{WRAPPER}} .hsw-ahd__more:focus-visible, {{WRAPPER}} .hsw-ahd__mmore:hover, {{WRAPPER}} .hsw-ahd__mmore:focus-visible' );
                $this->a_color( 'hd_more_bg_h', __( 'پس‌زمینه (هاور)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ahd__more:hover, {{WRAPPER}} .hsw-ahd__mmore:hover', 'background-color' );
                $this->a_color( 'hd_more_bd_h', __( 'حاشیه (هاور)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ahd__more:hover, {{WRAPPER}} .hsw-ahd__mmore:hover', 'border-color' );
                $this->a_color( 'hd_more_tx_o', __( 'متن (باز / فعال)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ahd__more.is-open, {{WRAPPER}} .hsw-ahd__mmore.is-open' );
                $this->a_color( 'hd_more_bg_o', __( 'پس‌زمینه (باز / فعال)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ahd__more.is-open, {{WRAPPER}} .hsw-ahd__mmore.is-open', 'background-color' );

                $this->end_controls_section();
        }

        /**
         * Archive title with {name} replacement.
         */
        protected function title_text() {
                $fmt = $this->opt( 'title_format', __( 'خرید و قیمت {name}', 'hamishop-elementor-widgets' ) );
                return str_replace( '{name}', $this->archive_name(), $fmt );
        }

        /**
         * Found products count.
         */
        protected function count_text() {
                if ( $this->is_demo() || ! $this->is_archive_context() ) {
                        return HSW_Helpers::fa_num( $this->demo()['count'] );
                }
                global $wp_query;
                $count = ( $wp_query && isset( $wp_query->found_posts ) && $wp_query->found_posts ) ? (int) $wp_query->found_posts : ( $this->current_term() ? (int) $this->current_term()->count : 0 );
                return HSW_Helpers::fa_num( $count );
        }

        /**
         * Quick pills: [ label, url, active ].
         */
        protected function pills() {
                $s    = $this->effective_settings();
                $src  = isset( $s['pills_source'] ) ? $s['pills_source'] : 'children';

                if ( $this->is_demo() || ! $this->is_archive_context() ) {
                        $d    = $this->demo();
                        $list = array();
                        foreach ( $d['pills'] as $i => $label ) {
                                $list[] = array( $label, '#', 0 === $i );
                        }
                        return $list;
                }

                $term = $this->current_term();
                if ( 'attribute' === $src ) {
                        $tax  = $this->opt( 'pills_attr', 'pa_brand' );
                        $sel  = $this->selected_slugs( $tax );
                        $out  = array();
                        foreach ( $this->filter_terms( $tax ) as $t ) {
                                $out[] = array( $t->name, $this->filter_url( array( 'filter_' . $tax => $t->slug ) ), in_array( $t->slug, $sel, true ) );
                        }
                        return $out;
                }
                if ( 'manual' === $src ) {
                        $out = array();
                        foreach ( (array) ( isset( $s['pills_manual'] ) ? $s['pills_manual'] : array() ) as $row ) {
                                $url  = isset( $row['url']['url'] ) ? $row['url']['url'] : '';
                                $out[] = array( $row['label'], $url ? $url : '#' . sanitize_title( $row['label'] ), false );
                        }
                        return $out;
                }
                /* children of the current term (or top-level cats on shop root). */
                $list = array();
                $parent = $term ? $term->term_id : 0;
                $children = get_terms(
                        array(
                                'taxonomy'   => 'product_cat',
                                'parent'     => $parent,
                                'hide_empty' => false,
                                'number'     => 8,
                                'exclude'    => $this->default_cat_ids(),
                        )
                );
                if ( ! is_wp_error( $children ) ) {
                        foreach ( $children as $c ) {
                                $list[] = array( $c->name, get_term_link( $c ), false );
                        }
                }
                return $list;
        }

        /**
         * Update note with {time} replacement.
         */
        protected function update_note() {
                $s   = $this->effective_settings();
                $txt = isset( $s['update_note'] ) ? trim( (string) $s['update_note'] ) : '';
                if ( '' === $txt ) {
                        return '';
                }
                $time = date_i18n( 'H:i' );
                return str_replace( '{time}', $time, $txt );
        }

        protected function render() {
                $s = $this->effective_settings();

                $pos = isset( $s['desc_pos'] ) ? (string) $s['desc_pos'] : 'header';
                if ( 'bottom' === $pos && ! $this->is_composite_part() ) {
                        $pos = 'header'; // The composite is the only place that can render the bottom box.
                }
                $desc_top    = ( 'header' === $pos );
                $desc_header = $desc_top ? $this->archive_desc_block_html( $s, 'header' ) : '';
                $desc_mobile = $desc_top ? $this->archive_desc_block_html( $s, 'mobile' ) : '';

                $title  = $this->title_text();
                $count  = $this->count_text();
                $pills  = $this->pills();
                $note   = $this->update_note();
                $guide  = ( 'yes' === ( $s['show_guide_btn'] ?? 'yes' ) );
                ?>
                <section class="<?php echo esc_attr( $this->root_class( 'ahd' ) ); ?>">

                        <!-- =================== DESKTOP =================== -->
                        <header class="hsw-ahd__hero hsw-d">
                                <div class="hsw-ahd__top">
                                        <div>
                                                <div class="hsw-ahd__row">
                                                        <h1 class="hsw-ahd__h1"><?php echo esc_html( $title ); ?></h1>
                                                        <?php if ( 'yes' === ( $s['show_count'] ?? 'yes' ) ) : ?>
                                                                <span class="hsw-ahd__count"><?php echo esc_html( $count ); ?> <?php esc_html_e( 'کالا', 'hamishop-elementor-widgets' ); ?></span>
                                                        <?php endif; ?>
                                                </div>
                                                <?php if ( $note ) : ?>
                                                        <p class="hsw-ahd__note"><?php echo esc_html( $note ); ?></p>
                                                <?php endif; ?>
                                        </div>
                                        <?php if ( ! empty( $pills ) ) : ?>
                                                <div class="hsw-ahd__pills">
                                                        <?php foreach ( $pills as $pill ) : ?>
                                                                <a class="hsw-ahd__pill<?php echo $pill[2] ? ' is-active' : ''; ?>" href="<?php echo esc_url( $pill[1] ); ?>"><?php echo esc_html( $pill[0] ); ?></a>
                                                        <?php endforeach; ?>
                                                </div>
                                        <?php endif; ?>
                                </div>
                                <?php echo $desc_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe HTML from archive_desc_block_html(). ?>
                        </header>

                        <!-- =================== MOBILE =================== -->
                        <div class="hsw-ahd__mhead hsw-m">
                                <div class="hsw-ahd__mtop">
                                        <div>
                                                <h1 class="hsw-ahd__h1 hsw-ahd__h1--m"><?php echo esc_html( $title ); ?></h1>
                                                <span class="hsw-ahd__mcount"><?php echo esc_html( $count ); ?> <?php esc_html_e( 'کالا موجود', 'hamishop-elementor-widgets' ); ?></span>
                                        </div>
                                        <?php if ( $guide ) : ?>
                                                <button type="button" class="hsw-ahd__guide" data-hsw-open="hsw-guide">
                                                        <i class="hsw-ic" aria-hidden="true">help</i>
                                                        <span><?php esc_html_e( 'راهنما', 'hamishop-elementor-widgets' ); ?></span>
                                                </button>
                                        <?php endif; ?>
                                </div>
                        </div>

                        <?php if ( ! empty( $pills ) ) : ?>
                                <div class="hsw-ahd__chips hsw-m">
                                        <?php foreach ( $pills as $i => $pill ) : ?>
                                                <a class="hsw-ahd__chip<?php echo $pill[2] ? ' is-active' : ''; ?>" href="<?php echo esc_url( $pill[1] ); ?>"><?php echo esc_html( $pill[0] ); ?></a>
                                        <?php endforeach; ?>
                                </div>
                        <?php endif; ?>

                        <?php echo $desc_mobile; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe HTML from archive_desc_block_html(). ?>

                </section>
                <?php
        }
}
