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
                                'description' => __( 'تکسانومی صفت رنگ محصول (پیش‌فرض ووکامرس: pa_color). اگر محصول چنین صفتی نداشته باشد، اولین صفت متغیر مناسب به‌صورت خودکار انتخاب می‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'attr_storage',
                        array(
                                'label'       => __( 'اسلاگ صفت حافظه', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => 'pa_memory',
                                'description' => __( 'تکسانومی صفت حافظه / رم محصول. اگر محصول چنین صفتی نداشته باشد، صفت متغیر بعدی به‌صورت خودکار انتخاب می‌شود.', 'hamishop-elementor-widgets' ),
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
                $s = $this->effective_settings();

                if ( ! $product && ! $this->is_demo() ) {
                        return; // No product data outside the editor — render nothing.
                }

                $d = $this->demo();

                /*
                 * A simple product has no variation to select. A visible
                 * colour attribute on one is just a specification, and showing
                 * swatches for it would promise a price/stock/cart change that
                 * cannot happen. The specs widget still prints it.
                 */
                if ( $product && ! $product->is_type( 'variable' ) ) {
                        return;
                }

                $attr_color   = ( isset( $s['attr_color'] ) && '' !== trim( (string) $s['attr_color'] ) ) ? trim( (string) $s['attr_color'] ) : 'pa_color';
                $attr_storage = ( isset( $s['attr_storage'] ) && '' !== trim( (string) $s['attr_storage'] ) ) ? trim( (string) $s['attr_storage'] ) : 'pa_memory';

                $lbl_color_d   = ( isset( $s['label_color_d'] ) && '' !== trim( (string) $s['label_color_d'] ) ) ? $s['label_color_d'] : __( 'رنگ:', 'hamishop-elementor-widgets' );
                $lbl_color_m   = ( isset( $s['label_color_m'] ) && '' !== trim( (string) $s['label_color_m'] ) ) ? $s['label_color_m'] : __( 'انتخاب رنگ:', 'hamishop-elementor-widgets' );
                $lbl_storage_d = ( isset( $s['label_storage_d'] ) && '' !== trim( (string) $s['label_storage_d'] ) ) ? $s['label_storage_d'] : __( 'حافظه داخلی و رم:', 'hamishop-elementor-widgets' );
                $lbl_storage_m = ( isset( $s['label_storage_m'] ) && '' !== trim( (string) $s['label_storage_m'] ) ) ? $s['label_storage_m'] : __( 'حافظه داخلی:', 'hamishop-elementor-widgets' );

                /* Remember what the editor asked for, so an auto-detected
                   attribute can borrow its own label instead of being called
                   «رنگ» or «حافظه» when it is actually «سایز». */
                $asked_color   = $attr_color;
                $asked_storage = $attr_storage;

                /* Colors: real product attribute terms; color hex from the repeater map. */
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
                if ( $product && $this->is_variation_attribute( $product, $attr_color ) ) {
                        foreach ( $this->attribute_terms( $product, $attr_color ) as $term ) {
                                $colors[] = array(
                                        'name'  => $term['name'],
                                        'label' => isset( $term['label'] ) && '' !== $term['label'] ? $term['label'] : $term['name'],
                                        'hex'   => isset( $color_map[ $term['name'] ] )
                                                ? $color_map[ $term['name'] ]
                                                : ( isset( $term['label'], $color_map[ $term['label'] ] ) ? $color_map[ $term['label'] ] : ( ! empty( $term['color'] ) ? $term['color'] : '' ) ),
                                );
                        }
                }

                $storages    = array(); // primary chip group (sticky bar / legacy contract)
                $chip_groups = array(); // every non-colour variation attribute

                if ( $product && $this->is_variation_attribute( $product, $attr_storage ) ) {
                        $primary = $this->attribute_terms( $product, $attr_storage );
                        if ( $primary ) {
                                $storages      = wp_list_pluck( $primary, 'name' );
                                $chip_groups[] = array(
                                        'tax'     => $attr_storage,
                                        'key'     => $this->real_taxonomy( $attr_storage ) ? $this->real_taxonomy( $attr_storage ) : $attr_storage,
                                        'label_d' => rtrim( (string) $lbl_storage_d, ': ' ),
                                        'label_m' => rtrim( (string) $lbl_storage_m, ': ' ),
                                        'terms'   => $storages,
                                );
                        }
                }

                if ( $demo ) {
                        /* Design mock — only when the site has no product at all. */
                        $colors   = $d['colors'];
                        $storages = $d['storages'];
                        $chip_groups = array(
                                array(
                                        'tax'     => 'storage',
                                        'key'     => 'storage',
                                        'label_d' => rtrim( (string) $lbl_storage_d, ': ' ),
                                        'label_m' => rtrim( (string) $lbl_storage_m, ': ' ),
                                        'terms'   => $storages,
                                ),
                        );
                } elseif ( $product && $product->is_type( 'variable' ) ) {
                        /*
                         * Auto-detect the product's own variation attributes when
                         * the configured slugs don't exist on this particular
                         * product. Only an attribute that is actually about colour
                         * can become the colour picker; every other variation
                         * attribute becomes a chip group of its own, so a product
                         * with pa_ram + pa_ssd renders two pickers instead of none.
                         */
                        $pool = $this->auto_variation_attributes( $product );
                        foreach ( array( $attr_color, $attr_storage ) as $used ) {
                                $used_key = $this->normalize_attribute_key( $used );
                                foreach ( array_keys( $pool ) as $pool_key ) {
                                        if ( $this->normalize_attribute_key( $pool_key ) === $used_key ) {
                                                unset( $pool[ $pool_key ] );
                                        }
                                }
                        }

                        if ( empty( $colors ) && $pool ) {
                                $candidate = $this->pick_attribute( $pool, 'color', false );
                                if ( '' !== $candidate ) {
                                        $attr_color = $candidate;
                                        foreach ( array_keys( $pool ) as $pool_key ) {
                                                if ( $this->normalize_attribute_key( $pool_key ) === $this->normalize_attribute_key( $candidate ) ) {
                                                        unset( $pool[ $pool_key ] );
                                                }
                                        }
                                        foreach ( $this->attribute_terms( $product, $attr_color ) as $term ) {
                                                $colors[] = array(
                                                        'name'  => $term['name'],
                                                        'label' => isset( $term['label'] ) && '' !== $term['label'] ? $term['label'] : $term['name'],
                                                        'hex'   => isset( $color_map[ $term['name'] ] )
                                                                ? $color_map[ $term['name'] ]
                                                                : ( isset( $term['label'], $color_map[ $term['label'] ] ) ? $color_map[ $term['label'] ] : ( ! empty( $term['color'] ) ? $term['color'] : '' ) ),
                                                );
                                        }
                                }
                        }

                        foreach ( $pool as $tax => $options ) {
                                $terms = $this->attribute_terms( $product, $tax );
                                if ( empty( $terms ) ) {
                                        continue;
                                }
                                $names = wp_list_pluck( $terms, 'name' );
                                $chip_groups[] = array(
                                        'tax'     => $tax,
                                        'key'     => $this->real_taxonomy( $tax ) ? $this->real_taxonomy( $tax ) : $tax,
                                        'label_d' => $this->attribute_label( $tax ),
                                        'label_m' => $this->attribute_label( $tax ),
                                        'terms'   => $names,
                                );
                                if ( empty( $storages ) ) {
                                        $storages = $names;
                                }
                        }
                }

                if ( empty( $colors ) && empty( $chip_groups ) ) {
                        return; // No real options on this product — render nothing (never fake data).
                }

                /*
                 * An auto-detected attribute is not the one the editor named, so
                 * it must not borrow that label («سایز» is not «حافظه داخلی و رم»).
                 */
                $color_label_d = rtrim( (string) $lbl_color_d, ': ' );
                $color_label_m = rtrim( (string) $lbl_color_m, ': ' );
                if ( $product && $this->normalize_attribute_key( $attr_color ) !== $this->normalize_attribute_key( $asked_color ) ) {
                        $color_label_d = $this->attribute_label( $attr_color );
                        $color_label_m = $this->attribute_label( $attr_color );
                }

                $first_color   = ! empty( $colors[0]['label'] ) ? $colors[0]['label'] : ( ! empty( $colors[0]['name'] ) ? $colors[0]['name'] : '' );
                $first_storage = ! empty( $chip_groups[0]['terms'][0] ) ? $chip_groups[0]['terms'][0] : '';

                $attrs_map = array(
                        'color'   => ! empty( $colors ) ? ( $this->real_taxonomy( $attr_color ) ? $this->real_taxonomy( $attr_color ) : $attr_color ) : '',
                        'storage' => ! empty( $chip_groups ) ? $chip_groups[0]['key'] : '',
                        'chips'   => array(),
                );
                foreach ( $chip_groups as $group ) {
                        $attrs_map['chips'][] = $group['key'];
                }
                $attrs_map['chips'] = array_values( array_unique( $attrs_map['chips'] ) );

                $pid = $product ? $product->get_id() : 0;
                ?>
                <div class="<?php echo esc_attr( $this->root_class( 'pvar' ) ); ?>" data-hsw-variants="<?php echo esc_attr( $pid ); ?>"<?php echo ( $product && HSW_Helpers::is_call_for_price( $product ) ) ? ' data-hsw-cfp="1"' : ''; ?>
                        <?php if ( $product && $product->is_type( 'variable' ) ) : ?>
                                data-hsw-variations='<?php echo esc_attr( wp_json_encode( $this->variations_payload( $product ) ) ); ?>'
                                data-hsw-attrs='<?php echo esc_attr( wp_json_encode( $attrs_map ) ); ?>'
                        <?php endif; ?>>
                        <div class="hsw-d">
                                <?php if ( ! empty( $colors ) ) : ?>
                                <div class="hsw-pvar__group">
                                        <div class="hsw-pvar__head">
                                                <span class="hsw-pvar__label"><?php echo esc_html( $color_label_d ); ?>: <strong class="hsw-pvar__selname" data-hsw-selname="color"><?php echo esc_html( $first_color ); ?></strong></span>
                                        </div>
                                        <div class="hsw-pvar__swatches">
                                                <?php foreach ( $colors as $i => $c ) : ?>
                                                        <?php $hex = isset( $c['hex'] ) ? (string) $c['hex'] : ''; ?>
                                                        <button type="button" class="hsw-pvar__swatch<?php echo 0 === $i ? ' is-active' : ''; ?><?php echo '' === $hex ? ' hsw-pvar__swatch--named' : ''; ?>" data-hsw-color="<?php echo esc_attr( $c['name'] ); ?>" data-hsw-label="<?php echo esc_attr( $c['label'] ); ?>"<?php echo '' !== $hex ? ' style="--sw:' . esc_attr( $hex ) . '"' : ''; ?> aria-label="<?php echo esc_attr( $c['label'] ); ?>"><?php if ( '' !== $hex ) : ?><span class="hsw-pvar__swdot"></span><?php else : ?><span class="hsw-pvar__swname"><?php echo esc_html( $c['label'] ); ?></span><?php endif; ?></button>
                                                <?php endforeach; ?>
                                        </div>
                                </div>
                                <?php endif; ?>
                                <?php foreach ( $chip_groups as $gi => $group ) : ?>
                                <div class="hsw-pvar__group">
                                        <div class="hsw-pvar__head"><span class="hsw-pvar__label"><?php echo esc_html( $group['label_d'] ); ?>:</span></div>
                                        <div class="hsw-pvar__chips">
                                                <?php foreach ( $group['terms'] as $i => $lbl ) : ?>
                                                        <button type="button" class="hsw-pvar__chip<?php echo 0 === $i ? ' is-active' : ''; ?>" data-hsw-attr="<?php echo esc_attr( $group['key'] ); ?>" data-hsw-val="<?php echo esc_attr( $lbl ); ?>"<?php echo 0 === $gi ? ' data-hsw-storage="' . esc_attr( $lbl ) . '"' : ''; ?>><?php echo esc_html( $lbl ); ?></button>
                                                <?php endforeach; ?>
                                        </div>
                                </div>
                                <?php endforeach; ?>
                        </div>
                        <div class="hsw-m">
                                <?php if ( ! empty( $colors ) ) : ?>
                                <div class="hsw-pvar__mcard">
                                        <div class="hsw-pvar__head">
                                                <span class="hsw-pvar__label"><?php echo esc_html( $color_label_m ); ?>:</span>
                                                <span class="hsw-pvar__label hsw-pvar__selname" data-hsw-selname="color"><?php echo esc_html( $first_color ); ?></span>
                                        </div>
                                        <div class="hsw-pvar__swatches">
                                                <?php foreach ( $colors as $i => $c ) : ?>
                                                        <?php $hex = isset( $c['hex'] ) ? (string) $c['hex'] : ''; ?>
                                                        <button type="button" class="hsw-pvar__mswatch<?php echo 0 === $i ? ' is-active' : ''; ?><?php echo '' === $hex ? ' hsw-pvar__mswatch--named' : ''; ?>" data-hsw-color="<?php echo esc_attr( $c['name'] ); ?>" data-hsw-label="<?php echo esc_attr( $c['label'] ); ?>"<?php echo '' !== $hex ? ' style="--sw:' . esc_attr( $hex ) . '"' : ''; ?> aria-label="<?php echo esc_attr( $c['label'] ); ?>"><?php if ( '' !== $hex ) : ?><span class="hsw-pvar__swdot"></span><?php else : ?><span class="hsw-pvar__swname"><?php echo esc_html( $c['label'] ); ?></span><?php endif; ?></button>
                                                <?php endforeach; ?>
                                        </div>
                                </div>
                                <?php endif; ?>
                                <?php foreach ( $chip_groups as $gi => $group ) : ?>
                                <div class="hsw-pvar__mcard">
                                        <div class="hsw-pvar__head">
                                                <span class="hsw-pvar__label"><?php echo esc_html( $group['label_m'] ); ?>:</span>
                                                <span class="hsw-pvar__label hsw-pvar__selname" data-hsw-selname="<?php echo esc_attr( $group['key'] ); ?>"<?php echo 0 === $gi ? ' data-hsw-selname-alias="storage"' : ''; ?>><?php echo esc_html( $group['terms'][0] ); ?></span>
                                        </div>
                                        <div class="hsw-pvar__mchips">
                                                <?php foreach ( $group['terms'] as $i => $lbl ) : ?>
                                                        <button type="button" class="hsw-pvar__mchip<?php echo 0 === $i ? ' is-active' : ''; ?>" data-hsw-attr="<?php echo esc_attr( $group['key'] ); ?>" data-hsw-val="<?php echo esc_attr( $lbl ); ?>"<?php echo 0 === $gi ? ' data-hsw-storage="' . esc_attr( $lbl ) . '"' : ''; ?>><?php echo esc_html( $lbl ); ?></button>
                                                <?php endforeach; ?>
                                        </div>
                                </div>
                                <?php endforeach; ?>
                        </div>
                </div>
                <?php
        }
}
