<?php
/**
 * Widget: ویژگی‌های شاخص محصول — faithful conversion of the supplied design.
 * Desktop: check-marked list inside a bordered box. Mobile: 2×2 bento grid
 * (icon box + label + value).
 *
 * Sources: a selectable mix of WooCommerce attributes and the short-description
 * table/lines, with a repeater fallback. The row limit defaults to all rows.
 * Demo mode always renders the repeater defaults.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class HSW_Widget_Product_Features extends HSW_Product_Base {

        /**
         * Widget slug.
         */
        public function get_name() {
                return 'hsw-product-features';
        }

        /**
         * Widget title.
         */
        public function get_title() {
                return __( 'ویژگی‌های شاخص محصول (HamiShop)', 'hamishop-elementor-widgets' );
        }

        /**
         * Widget icon.
         */
        public function get_icon() {
                return 'eicon-bullet-list';
        }

        /**
         * Widget keywords.
         */
        public function get_keywords() {
                return array( 'features', 'ویژگی', 'قابلیت', 'مشخصات', 'hamishop', 'hamisys' );
        }

        /**
         * Default desktop items: the design's demo feature texts.
         *
         * @return array
         */
        protected function default_desk_items() {
                /*
                 * No demo defaults: the design's Samsung texts used to be the
                 * control default, so every product without attributes showed
                 * «Galaxy AI» / «تیتانیوم» as if they were real specs.
                 * Empty → the widget reads the product's own attributes and
                 * renders nothing when the product has none.
                 */
                return array();
        }

        /**
         * Default mobile items: the design's demo bento cells.
         *
         * @return array
         */
        protected function default_mob_items() {
                /* See default_desk_items(): no mock specs on real products. */
                return array();
        }

        /**
         * Register widget controls.
         */
        protected function register_controls() {
                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control(
                        'desk_title',
                        array(
                                'label'   => __( 'عنوان دسکتاپ', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'ویژگی‌های شاخص کالا:', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'mob_title',
                        array(
                                'label'   => __( 'عنوان موبایل', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'ویژگی‌های کلیدی', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'source',
                        array(
                                'label'       => __( 'منبع ویژگی‌ها', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SELECT,
                                'default'     => 'auto',
                                'options'     => array(
                                        'auto'              => __( 'خودکار — مشخصات محصول + توضیح کوتاه', 'hamishop-elementor-widgets' ),
                                        'attributes'        => __( 'فقط ویژگی‌های ووکامرس (Attributes)', 'hamishop-elementor-widgets' ),
                                        'short_description' => __( 'فقط جدول/متن توضیح کوتاه', 'hamishop-elementor-widgets' ),
                                        'manual'            => __( 'فقط فهرست دستی', 'hamishop-elementor-widgets' ),
                                ),
                                'description' => __( 'در حالت خودکار، ابتدا ویژگی‌های ووکامرس و سپس جدول یا خطوط توضیح کوتاه خوانده می‌شوند؛ ردیف‌های تکراری حذف می‌شوند. در حالت دستی فقط فهرست‌های زیر استفاده می‌شوند.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'rows_limit',
                        array(
                                'label'       => __( 'حداکثر تعداد ردیف', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::NUMBER,
                                'default'     => 0,
                                'min'         => 0,
                                'max'         => 50,
                                'step'        => 1,
                                'description' => __( '۰ یعنی همهٔ ردیف‌ها نمایش داده شوند. برای فهرست‌های طولانی می‌توانید مثلاً ۱۲ بگذارید.', 'hamishop-elementor-widgets' ),
                                'condition'   => array( 'source!' => 'manual' ),
                        )
                );

                $this->add_control(
                        'desk_auto',
                        array(
                                'label'       => __( 'دسکتاپ: استفاده خودکار از داده محصول', 'hamishop-elementor-widgets' ),
                                'description' => __( 'روشن (پیش‌فرض): ردیف‌ها از منبع انتخاب‌شده خوانده می‌شوند. اگر خاموش باشد یا داده‌ای پیدا نشود، از فهرست دستی زیر استفاده می‌شود.', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'default'     => 'yes',
                                'condition'   => array( 'source!' => 'manual' ),
                        )
                );

                $repeater_d = new Repeater();
                $repeater_d->add_control(
                        'text',
                        array(
                                'label'       => __( 'متن ویژگی', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => '',
                                'label_block' => true,
                        )
                );
                $this->add_control(
                        'desk_items',
                        array(
                                'label'       => __( 'فهرست ویژگی‌های دسکتاپ', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $repeater_d->get_controls(),
                                'default'     => $this->default_desk_items(),
                                'title_field' => '{{{ text }}}',
                        )
                );

                $this->add_control(
                        'mob_auto',
                        array(
                                'label'       => __( 'موبایل: استفاده خودکار از داده محصول', 'hamishop-elementor-widgets' ),
                                'description' => __( 'روشن (پیش‌فرض): ردیف‌ها از منبع انتخاب‌شده با آیکون tune خوانده می‌شوند. اگر خاموش باشد یا داده‌ای پیدا نشود، از گرید دستی زیر استفاده می‌شود.', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'default'     => 'yes',
                                'condition'   => array( 'source!' => 'manual' ),
                        )
                );

                $repeater_m = new Repeater();
                $repeater_m->add_control(
                        'icon',
                        array(
                                'label'   => __( 'آیکون', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => 'photo_camera',
                                'options' => $this->product_icon_options(),
                        )
                );
                $repeater_m->add_control(
                        'label',
                        array(
                                'label'   => __( 'برچسب', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => '',
                        )
                );
                $repeater_m->add_control(
                        'value',
                        array(
                                'label'       => __( 'مقدار', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => '',
                                'label_block' => true,
                        )
                );
                $this->add_control(
                        'mob_items',
                        array(
                                'label'       => __( 'گرید ویژگی‌های موبایل', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $repeater_m->get_controls(),
                                'default'     => $this->default_mob_items(),
                                'title_field' => '{{{ label }}}',
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->ctrl_box( 'ft', '{{WRAPPER}} .hsw-pfeat__box' );

                $this->ctrl_text( 'ft_t', '{{WRAPPER}} .hsw-pfeat__title' );

                $this->ctrl_text( 'ft_i', '{{WRAPPER}} .hsw-pfeat__list' );

                $this->ctrl_icon( 'ft_ic', '{{WRAPPER}} .hsw-pfeat__ic' );

                $this->add_control(
                        'ftm_heading',
                        array( 'label' => __( 'گرید موبایل', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->ctrl_box( 'ftm', '{{WRAPPER}} .hsw-pfeat__cell' );

                $this->add_control(
                        'ft_mic_bg',
                        array(
                                'label'     => __( 'پس‌زمینه قاب آیکون', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-pfeat__mic' => '--hsw-ft-mic-bg: {{VALUE}};' ),
                        )
                );

                $this->add_control(
                        'ft_mic_c',
                        array(
                                'label'     => __( 'رنگ آیکون', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-pfeat__mic' => '--hsw-ft-mic: {{VALUE}};' ),
                        )
                );

                $this->ctrl_text( 'ftm_l', '{{WRAPPER}} .hsw-pfeat__mlabel' );

                $this->ctrl_text( 'ftm_v', '{{WRAPPER}} .hsw-pfeat__mval' );

                $this->end_controls_section();
        }

        /**
         * Is this string one of the old design-demo feature texts?
         *
         * Templates saved before this release persisted the Samsung mock into
         * the repeater, so the value survives a default change. Dropping the
         * known strings keeps those pages clean without touching the editor.
         *
         * @param string $text Row text / label / value.
         * @return bool
         */
        protected function is_demo_text( $text ) {
                $text = trim( (string) $text );
                if ( '' === $text ) {
                        return false;
                }
                $d = $this->demo();
                foreach ( (array) $d['features_d'] as $demo ) {
                        if ( trim( (string) $demo ) === $text ) {
                                return true;
                        }
                }
                foreach ( (array) $d['features_m'] as $row ) {
                        if ( trim( (string) ( $row['label'] ?? '' ) ) === $text || trim( (string) ( $row['value'] ?? '' ) ) === $text ) {
                                return true;
                        }
                }
                return false;
        }

        /**
         * Render the desktop check-list and the mobile bento grid.
         */
        protected function render() {
                list( $product, $demo ) = $this->product_or_demo();
                $s = $this->effective_settings();

                $source = isset( $s['source'] ) ? (string) $s['source'] : 'auto';
                if ( ! in_array( $source, array( 'auto', 'attributes', 'short_description', 'manual' ), true ) ) {
                        $source = 'auto';
                }
                $limit     = isset( $s['rows_limit'] ) ? max( 0, (int) $s['rows_limit'] ) : 0;
                $desk_auto = 'yes' === ( $s['desk_auto'] ?? 'yes' );
                $mob_auto  = 'yes' === ( $s['mob_auto'] ?? 'yes' );

                /* --- Desktop: demo defaults | selected source | repeater. --- */
                $desk = array();
                if ( $demo ) {
                        $desk = $this->default_desk_items();
                } elseif ( 'manual' !== $source && $desk_auto ) {
                        foreach ( $this->feature_rows( $product, $limit, $source ) as $row ) {
                                $desk[] = array( 'text' => '' !== $row['k'] ? trim( $row['k'] . ': ' . $row['v'] ) : $row['v'] );
                        }
                }
                if ( empty( $desk ) ) {
                        $raw = ( isset( $s['desk_items'] ) && is_array( $s['desk_items'] ) ) ? $s['desk_items'] : array();
                        foreach ( $raw as $item ) {
                                $text = isset( $item['text'] ) ? $item['text'] : '';
                                if ( '' === trim( (string) $text ) || $this->is_demo_text( $text ) ) {
                                        continue;
                                }
                                $desk[] = array( 'text' => $text );
                        }
                }

                /* --- Mobile: demo defaults | selected source (tune icon) | repeater. --- */
                $mob = array();
                if ( $demo ) {
                        $mob = $this->default_mob_items();
                } elseif ( 'manual' !== $source && $mob_auto ) {
                        foreach ( $this->feature_rows( $product, $limit, $source ) as $row ) {
                                $mob[] = array( 'icon' => 'tune', 'label' => $row['k'], 'value' => $row['v'] );
                        }
                }
                if ( empty( $mob ) ) {
                        $raw_m = ( isset( $s['mob_items'] ) && is_array( $s['mob_items'] ) ) ? $s['mob_items'] : array();
                        foreach ( $raw_m as $item ) {
                                $label = isset( $item['label'] ) ? $item['label'] : '';
                                $value = isset( $item['value'] ) ? $item['value'] : '';
                                if ( '' === trim( (string) $label ) && '' === trim( (string) $value ) ) {
                                        continue;
                                }
                                if ( $this->is_demo_text( $label ) && $this->is_demo_text( $value ) ) {
                                        continue;
                                }
                                $mob[] = array(
                                        'icon'  => isset( $item['icon'] ) ? $item['icon'] : 'tune',
                                        'label' => $label,
                                        'value' => $value,
                                );
                        }
                }

                if ( empty( $desk ) && empty( $mob ) ) {
                        return;
                }

                $desk_title = ( isset( $s['desk_title'] ) && '' !== trim( (string) $s['desk_title'] ) ) ? $s['desk_title'] : __( 'ویژگی‌های شاخص کالا:', 'hamishop-elementor-widgets' );
                $mob_title  = ( isset( $s['mob_title'] ) && '' !== trim( (string) $s['mob_title'] ) ) ? $s['mob_title'] : __( 'ویژگی‌های کلیدی', 'hamishop-elementor-widgets' );
                ?>
                <div class="<?php echo esc_attr( $this->root_class( 'pfeat' ) ); ?>">
                        <?php if ( ! empty( $desk ) ) : ?>
                        <div class="hsw-d">
                                <div class="hsw-pfeat__box">
                                        <span class="hsw-pfeat__title"><?php echo esc_html( $desk_title ); ?></span>
                                        <ul class="hsw-pfeat__list">
                                                <?php foreach ( $desk as $item ) : ?>
                                                        <li class="hsw-pfeat__item"><?php echo HSW_Helpers::icon( 'check_circle', 'hsw-pfeat__ic' ); ?><span><?php echo esc_html( $item['text'] ); ?></span></li>
                                                <?php endforeach; ?>
                                        </ul>
                                </div>
                        </div>
                        <?php endif; ?>
                        <?php if ( ! empty( $mob ) ) : ?>
                        <div class="hsw-m">
                                <h3 class="hsw-pfeat__mtitle"><?php echo esc_html( $mob_title ); ?></h3>
                                <div class="hsw-pfeat__grid">
                                        <?php foreach ( $mob as $item ) : ?>
                                                <div class="hsw-pfeat__cell">
                                                        <span class="hsw-pfeat__mic"><?php echo HSW_Helpers::icon( $item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                                        <div>
                                                                <div class="hsw-pfeat__mlabel"><?php echo esc_html( $item['label'] ); ?></div>
                                                                <div class="hsw-pfeat__mval"><?php echo esc_html( $item['value'] ); ?></div>
                                                        </div>
                                                </div>
                                        <?php endforeach; ?>
                                </div>
                        </div>
                        <?php endif; ?>
                </div>
                <?php
        }
}
