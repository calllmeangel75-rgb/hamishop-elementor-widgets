<?php
/**
 * Widget: ویژگی‌های شاخص محصول — faithful conversion of the supplied design.
 * Desktop: check-marked list inside a bordered box. Mobile: 2×2 bento grid
 * (icon box + label + value).
 *
 * Sources: repeater items by default (the design's Samsung demo texts), or the
 * first 4 attribute-spec rows as «k: v» when the auto switchers are on.
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
                $d     = $this->demo();
                $items = array();
                foreach ( $d['features_d'] as $text ) {
                        $items[] = array( 'text' => $text );
                }
                return $items;
        }

        /**
         * Default mobile items: the design's demo bento cells.
         *
         * @return array
         */
        protected function default_mob_items() {
                $d     = $this->demo();
                $items = array();
                foreach ( $d['features_m'] as $row ) {
                        $items[] = array(
                                'icon'  => isset( $row['icon'] ) ? $row['icon'] : 'tune',
                                'label' => isset( $row['label'] ) ? $row['label'] : '',
                                'value' => isset( $row['value'] ) ? $row['value'] : '',
                        );
                }
                return $items;
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
                        'desk_auto',
                        array(
                                'label'       => __( 'دسکتاپ: از مشخصات محصول', 'hamishop-elementor-widgets' ),
                                'description' => __( 'روشن (پیش‌فرض): تا ۴ مشخصه‌ی محصول به شکل «نام: مقدار» — اگر محصول مشخصه نداشت، از آیتم‌های فهرست زیر استفاده می‌شود.', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'default'     => 'yes',
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
                                'label'       => __( 'موبایل: از مشخصات محصول', 'hamishop-elementor-widgets' ),
                                'description' => __( 'روشن (پیش‌فرض): تا ۴ مشخصه‌ی محصول با آیکون tune — اگر محصول مشخصه نداشت، از گرید زیر استفاده می‌شود.', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'default'     => 'yes',
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
         * Render the desktop check-list and the mobile bento grid.
         */
        protected function render() {
                list( $product, $demo ) = $this->product_or_demo();
                $s = $this->effective_settings();

                /* --- Desktop: demo defaults | auto specs/bullets | repeater. --- */
                $desk = array();
                if ( $demo ) {
                        $desk = $this->default_desk_items();
                } elseif ( 'yes' === ( $s['desk_auto'] ?? '' ) ) {
                        foreach ( $this->feature_rows( $product ) as $row ) {
                                $desk[] = array( 'text' => '' !== $row['k'] ? trim( $row['k'] . ': ' . $row['v'] ) : $row['v'] );
                        }
                }
                if ( empty( $desk ) ) {
                        $raw = ( isset( $s['desk_items'] ) && is_array( $s['desk_items'] ) ) ? $s['desk_items'] : array();
                        foreach ( $raw as $item ) {
                                $text = isset( $item['text'] ) ? $item['text'] : '';
                                if ( '' !== trim( (string) $text ) ) {
                                        $desk[] = array( 'text' => $text );
                                }
                        }
                }

                /* --- Mobile: demo defaults | auto specs/bullets (tune icon) | repeater. --- */
                $mob = array();
                if ( $demo ) {
                        $mob = $this->default_mob_items();
                } elseif ( 'yes' === ( $s['mob_auto'] ?? '' ) ) {
                        foreach ( $this->feature_rows( $product ) as $row ) {
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
                                                        <li class="hsw-pfeat__item"><span class="hsw-ic hsw-pfeat__ic">check_circle</span><span><?php echo esc_html( $item['text'] ); ?></span></li>
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
