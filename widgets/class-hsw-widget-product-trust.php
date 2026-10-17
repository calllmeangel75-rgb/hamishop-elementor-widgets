<?php
/**
 * Widget: نوار اعتماد و ضمانت‌ها — faithful conversion of the supplied design.
 * Desktop-only three-column trust panel (icon + title + sub). The mobile
 * design has no trust panel, so the root always carries hsw-d — except when
 * the visibility control is explicitly set to «فقط موبایل».
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class HSW_Widget_Product_Trust extends HSW_Product_Base {

        /**
         * Widget slug.
         */
        public function get_name() {
                return 'hsw-product-trust';
        }

        /**
         * Widget label in the Elementor panel.
         */
        public function get_title() {
                return __( 'نوار اعتماد و ضمانت‌ها (HamiShop)', 'hamishop-elementor-widgets' );
        }

        /**
         * Widget icon.
         */
        public function get_icon() {
                return 'eicon-check';
        }

        /**
         * Search keywords.
         */
        public function get_keywords() {
                return array( 'trust', 'اعتماد', 'ضمانت', 'گارانتی', 'hamishop', 'hamisys' );
        }

        /**
         * The three hard-coded design defaults — used as repeater defaults
         * and re-rendered when the repeater is emptied by the user.
         *
         * @return array[] List of items: [ icon, title, sub ].
         */
        private function default_items() {
                return array(
                        array(
                                'icon'  => 'verified',
                                'title' => __( 'اصالت ۱۰۰٪ کالا', 'hamishop-elementor-widgets' ),
                                'sub'   => __( 'ضمانت رسمی', 'hamishop-elementor-widgets' ),
                        ),
                        array(
                                'icon'  => 'local_shipping',
                                'title' => __( 'ارسال فوری', 'hamishop-elementor-widgets' ),
                                'sub'   => __( 'تحویل اکسپرس', 'hamishop-elementor-widgets' ),
                        ),
                        array(
                                'icon'  => 'assignment_return',
                                'title' => __( '۷ روز بازگشت', 'hamishop-elementor-widgets' ),
                                'sub'   => __( 'بی قید و شرط', 'hamishop-elementor-widgets' ),
                        ),
                );
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

                $repeater = new Repeater();

                $repeater->add_control(
                        'icon',
                        array(
                                'label'   => __( 'آیکون', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => 'verified',
                                'options' => $this->product_icon_options(),
                        )
                );

                $repeater->add_control(
                        'title',
                        array(
                                'label'       => __( 'عنوان', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'اصالت ۱۰۰٪ کالا', 'hamishop-elementor-widgets' ),
                                'label_block' => true,
                        )
                );

                $repeater->add_control(
                        'sub',
                        array(
                                'label'       => __( 'زیرعنوان', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'ضمانت رسمی', 'hamishop-elementor-widgets' ),
                                'label_block' => true,
                        )
                );

                $this->add_control(
                        'trust_items',
                        array(
                                'label'       => __( 'آیتم‌های اعتماد', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $repeater->get_controls(),
                                'title_field' => '{{{ title }}}',
                                'default'     => $this->default_items(),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->ctrl_box( 'tr', '{{WRAPPER}} .hsw-ptrust' );

                $this->add_control(
                        'tr_ic_heading',
                        array( 'label' => __( 'آیکون‌ها', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->ctrl_icon( 'tr_ic', '{{WRAPPER}} .hsw-ptrust__ic' );

                $this->ctrl_text( 'tr_t', '{{WRAPPER}} .hsw-ptrust__t', array( 'separator' => true ) );

                $this->ctrl_text( 'tr_s', '{{WRAPPER}} .hsw-ptrust__s', array( 'separator' => true ) );

                $this->end_controls_section();
        }

        /**
         * Render the trust panel. Fully control-driven (no product data is
         * consumed), so it renders even outside single-product contexts.
         */
        protected function render() {
                // Base render contract — product context is resolved but unused here.
                list( $product, $demo ) = $this->product_or_demo();
                $s = $this->get_settings_for_display();

                $items = array();
                if ( ! empty( $s['trust_items'] ) && is_array( $s['trust_items'] ) ) {
                        foreach ( $s['trust_items'] as $item ) {
                                $items[] = array(
                                        'icon'  => isset( $item['icon'] ) && '' !== $item['icon'] ? $item['icon'] : 'verified',
                                        'title' => isset( $item['title'] ) ? $item['title'] : '',
                                        'sub'   => isset( $item['sub'] ) ? $item['sub'] : '',
                                );
                        }
                }
                if ( empty( $items ) ) {
                        $items = $this->default_items();
                }

                // hsw-d hides the panel on mobile; skipped when the user forces mobile-only.
                $extra = ( 'mobile' === ( $s['vis'] ?? 'auto' ) ) ? '' : 'hsw-d';
                ?>
                <div class="<?php echo esc_attr( $this->root_class( 'ptrust', $extra ) ); ?>">
                        <?php foreach ( $items as $item ) : ?>
                                <div class="hsw-ptrust__item">
                                        <?php echo HSW_Helpers::icon( $item['icon'], 'hsw-ptrust__ic' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                        <span class="hsw-ptrust__t"><?php echo esc_html( $item['title'] ); ?></span>
                                        <span class="hsw-ptrust__s"><?php echo esc_html( $item['sub'] ); ?></span>
                                </div>
                        <?php endforeach; ?>
                </div>
                <?php
        }
}
