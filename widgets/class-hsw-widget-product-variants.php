<?php
/**
 * Widget: Color & storage picker of the product — the interaction heart.
 * Root .hsw-pvar carries the JS engine contract (data-hsw-variants /
 * data-hsw-variations / data-hsw-attrs + data-hsw-color|storage on the
 * buttons + data-hsw-selname spans). Desktop: labeled groups with round
 * swatches and outline chips. Mobile: two cards with the same options.
 * The first swatch/chip is pre-selected (is-active) on both blocks.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class HSW_Widget_Product_Variants extends HSW_Product_Base {

        /**
         * Widget slug (Elementor name).
         */
        public function get_name() {
                return 'hsw-product-variants';
        }

        /**
         * Widget title in the editor panel.
         */
        public function get_title() {
                return __( 'انتخاب رنگ و حافظه (HamiShop)', 'hamishop-elementor-widgets' );
        }

        /**
         * Widget icon.
         */
        public function get_icon() {
                return 'eicon-basket-medium';
        }

        /**
         * Widget search keywords.
         */
        public function get_keywords() {
                return array( 'variants', 'color', 'storage', 'رنگ', 'حافظه', 'انتخاب رنگ', 'hamishop', 'hamisys' );
        }

        /**
         * Register content + style controls.
         */
        protected function register_controls() {
                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control(
                        'attr_color',
                        array(
                                'label'       => __( 'اسلاگ صفت رنگ', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => 'pa_color',
                                'description' => __( 'تکسانومی صفت رنگ محصول (پیش‌فرض ووکامرس: pa_color)', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'attr_storage',
                        array(
                                'label'       => __( 'اسلاگ صفت حافظه', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => 'pa_memory',
                                'description' => __( 'تکسانومی صفت حافظه / رم محصول', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'label_color_d',
                        array(
                                'label'   => __( 'برچسب رنگ (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'رنگ:', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'label_color_m',
                        array(
                                'label'   => __( 'برچسب رنگ (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'انتخاب رنگ:', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'label_storage_d',
                        array(
                                'label'   => __( 'برچسب حافظه (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'حافظه داخلی و رم:', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'label_storage_m',
                        array(
                                'label'   => __( 'برچسب حافظه (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'حافظه داخلی:', 'hamishop-elementor-widgets' ),
                        )
                );

                $repeater = new Repeater();

                $repeater->add_control(
                        'name',
                        array(
                                'label'   => __( 'نام رنگ (ترم صفت)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                        )
                );

                $repeater->add_control(
                        'hex',
                        array(
                                'label' => __( 'کد رنگ', 'hamishop-elementor-widgets' ),
                                'type'  => Controls_Manager::COLOR,
                                'alpha' => false,
                        )
                );

                $this->add_control(
                        'color_map',
                        array(
                                'label'       => __( 'نقشه رنگ‌ها (نام ← کد)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $repeater->get_controls(),
                                'title_field' => '{{{ name }}}',
                                'default'     => array(
                                        array( 'name' => 'تیتانیوم مشکی', 'hex' => '#202022' ),
                                        array( 'name' => 'تیتانیوم خاکستری', 'hex' => '#706e6b' ),
                                        array( 'name' => 'تیتانیوم کرم', 'hex' => '#e3decf' ),
                                        array( 'name' => 'تیتانیوم بنفش', 'hex' => '#494554' ),
                                ),
                                'description' => __( 'نام هر ترم صفت رنگ را به کد رنگ وصل می‌کند؛ ترم بدون تطبیق: #cccccc', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->ctrl_text( 'var_l', '{{WRAPPER}} .hsw-pvar__label' );

                $this->add_control(
                        'var_sel_c',
                        array(
                                'label'     => __( 'رنگ نام انتخاب‌شده', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-pvar__selname' => '--hsw-var-sel: {{VALUE}};' ),
                        )
                );

                $this->add_control(
                        'var_ring_c',
                        array(
                                'label'     => __( 'رنگ حلقه سواچ فعال', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array(
                                        '{{WRAPPER}} .hsw-pvar__swatch.is-active, {{WRAPPER}} .hsw-pvar__mswatch.is-active' => '--hsw-var-ring: {{VALUE}};',
                                ),
                        )
                );

                $this->add_control(
                        'var_chips_heading',
                        array( 'label' => __( 'چیپ‌ها', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->add_control(
                        'var_chip_bg',
                        array(
                                'label'     => __( 'پس‌زمینه چیپ', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-pvar__chip, {{WRAPPER}} .hsw-pvar__mchip' => '--hsw-chip-bg: {{VALUE}};' ),
                        )
                );
                $this->add_control(
                        'var_chip_bdc',
                        array(
                                'label'     => __( 'رنگ حاشیه چیپ', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-pvar__chip, {{WRAPPER}} .hsw-pvar__mchip' => '--hsw-chip-bc: {{VALUE}};' ),
                        )
                );
                $this->add_control(
                        'var_chip_abc',
                        array(
                                'label'     => __( 'حاشیه چیپ فعال', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-pvar__chip, {{WRAPPER}} .hsw-pvar__mchip' => '--hsw-chip-abc: {{VALUE}};' ),
                        )
                );
                $this->add_control(
                        'var_chip_ac',
                        array(
                                'label'     => __( 'متن چیپ فعال', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-pvar__chip, {{WRAPPER}} .hsw-pvar__mchip' => '--hsw-chip-ac: {{VALUE}};' ),
                        )
                );

                $this->ctrl_text( 'var_chip', '{{WRAPPER}} .hsw-pvar__chip, {{WRAPPER}} .hsw-pvar__mchip' );

                $this->end_controls_section();
        }

        /**
         * Render the desktop + mobile variant pickers with the JS data contract.
         */
        protected function render() {
                list( $product, $demo ) = $this->product_or_demo();
                $s = $this->get_settings_for_display();

                if ( ! $product && ! $this->is_demo() ) {
                        return; // No product data outside the editor — render nothing.
                }

                $d = $this->demo();

                $attr_color   = ( isset( $s['attr_color'] ) && '' !== trim( (string) $s['attr_color'] ) ) ? trim( (string) $s['attr_color'] ) : 'pa_color';
                $attr_storage = ( isset( $s['attr_storage'] ) && '' !== trim( (string) $s['attr_storage'] ) ) ? trim( (string) $s['attr_storage'] ) : 'pa_memory';

                $lbl_color_d   = ( isset( $s['label_color_d'] ) && '' !== trim( (string) $s['label_color_d'] ) ) ? $s['label_color_d'] : __( 'رنگ:', 'hamishop-elementor-widgets' );
                $lbl_color_m   = ( isset( $s['label_color_m'] ) && '' !== trim( (string) $s['label_color_m'] ) ) ? $s['label_color_m'] : __( 'انتخاب رنگ:', 'hamishop-elementor-widgets' );
                $lbl_storage_d = ( isset( $s['label_storage_d'] ) && '' !== trim( (string) $s['label_storage_d'] ) ) ? $s['label_storage_d'] : __( 'حافظه داخلی و رم:', 'hamishop-elementor-widgets' );
                $lbl_storage_m = ( isset( $s['label_storage_m'] ) && '' !== trim( (string) $s['label_storage_m'] ) ) ? $s['label_storage_m'] : __( 'حافظه داخلی:', 'hamishop-elementor-widgets' );

                /* Colors: product attribute terms + repeater hex map (demo fallback). */
                $color_map = array();
                if ( ! empty( $s['color_map'] ) && is_array( $s['color_map'] ) ) {
                        foreach ( $s['color_map'] as $row ) {
                                $nm = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
                                if ( '' === $nm ) {
                                        continue;
                                }
                                $color_map[ $nm ] = ( isset( $row['hex'] ) && '' !== (string) $row['hex'] ) ? (string) $row['hex'] : '#cccccc';
                        }
                }

                $colors = array();
                $terms  = $product ? $this->attribute_terms( $product, $attr_color ) : array();
                foreach ( $terms as $term ) {
                        $colors[] = array(
                                'name' => $term['name'],
                                'hex'  => isset( $color_map[ $term['name'] ] ) ? $color_map[ $term['name'] ] : '#cccccc',
                        );
                }
                if ( empty( $colors ) ) {
                        $colors = $d['colors'];
                }

                /* Storage: product attribute terms (demo fallback). */
                $storages = array();
                $sterms   = $product ? $this->attribute_terms( $product, $attr_storage ) : array();
                foreach ( $sterms as $term ) {
                        $storages[] = $term['name'];
                }
                if ( empty( $storages ) ) {
                        $storages = $d['storages'];
                }

                $first_color   = ! empty( $colors[0]['name'] ) ? $colors[0]['name'] : '';
                $first_storage = ! empty( $storages[0] ) ? $storages[0] : '';

                $pid = $product ? $product->get_id() : 0;
                ?>
                <div class="<?php echo esc_attr( $this->root_class( 'pvar' ) ); ?>" data-hsw-variants="<?php echo esc_attr( $pid ); ?>"
                        <?php if ( $product && $product->is_type( 'variable' ) ) : ?>
                                data-hsw-variations='<?php echo esc_attr( wp_json_encode( $this->variations_payload( $product ) ) ); ?>'
                                data-hsw-attrs='<?php echo esc_attr( wp_json_encode( array( 'color' => $attr_color, 'storage' => $attr_storage ) ) ); ?>'
                        <?php endif; ?>>
                        <div class="hsw-d">
                                <div class="hsw-pvar__group">
                                        <div class="hsw-pvar__head">
                                                <span class="hsw-pvar__label"><?php echo esc_html( $lbl_color_d ); ?> <strong class="hsw-pvar__selname" data-hsw-selname="color"><?php echo esc_html( $first_color ); ?></strong></span>
                                        </div>
                                        <div class="hsw-pvar__swatches">
                                                <?php foreach ( $colors as $i => $c ) : ?>
                                                        <button type="button" class="hsw-pvar__swatch<?php echo 0 === $i ? ' is-active' : ''; ?>" data-hsw-color="<?php echo esc_attr( $c['name'] ); ?>" style="--sw:<?php echo esc_attr( $c['hex'] ); ?>" aria-label="<?php echo esc_attr( $c['name'] ); ?>"><span class="hsw-pvar__swdot"></span></button>
                                                <?php endforeach; ?>
                                        </div>
                                </div>
                                <div class="hsw-pvar__group">
                                        <div class="hsw-pvar__head"><span class="hsw-pvar__label"><?php echo esc_html( $lbl_storage_d ); ?></span></div>
                                        <div class="hsw-pvar__chips">
                                                <?php foreach ( $storages as $i => $lbl ) : ?>
                                                        <button type="button" class="hsw-pvar__chip<?php echo 0 === $i ? ' is-active' : ''; ?>" data-hsw-storage="<?php echo esc_attr( $lbl ); ?>"><?php echo esc_html( $lbl ); ?></button>
                                                <?php endforeach; ?>
                                        </div>
                                </div>
                        </div>
                        <div class="hsw-m">
                                <div class="hsw-pvar__mcard">
                                        <div class="hsw-pvar__head">
                                                <span class="hsw-pvar__label"><?php echo esc_html( $lbl_color_m ); ?></span>
                                                <span class="hsw-pvar__label hsw-pvar__selname" data-hsw-selname="color"><?php echo esc_html( $first_color ); ?></span>
                                        </div>
                                        <div class="hsw-pvar__swatches">
                                                <?php foreach ( $colors as $i => $c ) : ?>
                                                        <button type="button" class="hsw-pvar__mswatch<?php echo 0 === $i ? ' is-active' : ''; ?>" data-hsw-color="<?php echo esc_attr( $c['name'] ); ?>" style="--sw:<?php echo esc_attr( $c['hex'] ); ?>" aria-label="<?php echo esc_attr( $c['name'] ); ?>"><span class="hsw-pvar__swdot"></span></button>
                                                <?php endforeach; ?>
                                        </div>
                                </div>
                                <div class="hsw-pvar__mcard">
                                        <div class="hsw-pvar__head">
                                                <span class="hsw-pvar__label"><?php echo esc_html( $lbl_storage_m ); ?></span>
                                                <span class="hsw-pvar__label hsw-pvar__selname" data-hsw-selname="storage"><?php echo esc_html( $first_storage ); ?></span>
                                        </div>
                                        <div class="hsw-pvar__mchips">
                                                <?php foreach ( $storages as $i => $lbl ) : ?>
                                                        <button type="button" class="hsw-pvar__mchip<?php echo 0 === $i ? ' is-active' : ''; ?>" data-hsw-storage="<?php echo esc_attr( $lbl ); ?>"><?php echo esc_html( $lbl ); ?></button>
                                                <?php endforeach; ?>
                                        </div>
                                </div>
                        </div>
                </div>
                <?php
        }
}
