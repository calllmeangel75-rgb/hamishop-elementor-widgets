<?php
/**
 * Widget: Parcel items preview (اقلام مرسوله) — faithful conversion of the
 * mobile mock: horizontal scroll of compact item cards (thumb + qty badge +
 * name + variant). Desktop shows the same strip inside a boxed card.
 * Live from the WooCommerce cart; demo items in the editor.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Checkout_Items extends HSW_Checkout_Base {

        public function get_name() {
                return 'hsw-checkout-items';
        }

        public function get_title() {
                return __( 'اقلام مرسوله (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-gallery-grid';
        }

        public function get_keywords() {
                return array( 'checkout', 'items', 'parcel', 'اقلام', 'مرسوله', 'hamishop' );
        }

        protected function register_controls() {
                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control(
                        'title',
                        array(
                                'label'   => __( 'عنوان', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'اقلام این مرسوله', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'count_tpl',
                        array(
                                'label'       => __( 'قالب شمارنده', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( '{count} قلم کالا', 'hamishop-elementor-widgets' ),
                                'description' => __( '{count} با تعداد اقلام جایگزین می‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_box( 'card', '{{WRAPPER}} .hsw-ckitems__card' );
                $this->style_color_var( 'sav_c', __( 'رنگ «سود شما از خرید»', 'hamishop-elementor-widgets' ), 'hsw-savings-color' );
                $this->style_typo_var( 'sav_fs', __( 'اندازه فونت «سود شما از خرید»', 'hamishop-elementor-widgets' ), 'hsw-savings-fs', array( 'default' => 13 ) );

                $this->a_color( 'hd_ic', __( 'آیکن عنوان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ckitems__hd .hsw-ic' );
                $this->a_text( 'hd_tx', '{{WRAPPER}} .hsw-ckitems__hd-t', array( 'default' => 14 ) );
                $this->a_text( 'item_tx', '{{WRAPPER}} .hsw-ckitems__name', array( 'default' => 14 ) );
                $this->a_text( 'item_sub', '{{WRAPPER}} .hsw-ckitems__chip', array( 'default' => 11 ) );

                $this->end_controls_section();
        }

        /**
         * Unified items (cart payload: name/img/chips/qty).
         */
        protected function items() {
                if ( $this->is_demo() ) {
                        return $this->demo_items();
                }
                return $this->cart_items();
        }

        protected function render() {
                $items = $this->items();
                $count = 0;
                foreach ( $items as $it ) {
                        $count += (int) ( isset( $it['qty'] ) ? $it['qty'] : 1 );
                }

                $title     = $this->opt( 'title', __( 'اقلام این مرسوله', 'hamishop-elementor-widgets' ) );
                $count_tpl = $this->with_count( $this->opt( 'count_tpl', __( '{count} قلم کالا', 'hamishop-elementor-widgets' ) ), $count );
                ?>
                <section class="<?php echo esc_attr( $this->root_class( 'ckitems' ) ); ?>">

                        <!-- =================== DESKTOP =================== -->
                        <article class="hsw-ckitems__card hsw-d">
                                <div class="hsw-ckitems__hd">
                                        <?php echo HSW_Helpers::icon( 'inventory_2' ); ?>
                                        <h2 class="hsw-ckitems__hd-t"><?php echo esc_html( $title ); ?></h2>
                                        <span class="hsw-ckitems__count"><?php echo esc_html( $count_tpl ); ?></span>
                                </div>

                                <div class="hsw-ckitems__row">
                                        <?php foreach ( $items as $it ) : ?>
                                                <a class="hsw-ckitems__item" href="<?php echo esc_url( isset( $it['url'] ) ? $it['url'] : '#' ); ?>">
                                                        <span class="hsw-ckitems__thumb">
                                                                <?php echo isset( $it['img'] ) ? $it['img'] : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP image html. ?>
                                                                <span class="hsw-ckitems__qty"><?php echo esc_html( HSW_Helpers::fa_num( (int) ( isset( $it['qty'] ) ? $it['qty'] : 1 ) ) ); ?>×</span>
                                                        </span>
                                                        <span class="hsw-ckitems__tx">
                                                                <span class="hsw-ckitems__name"><?php echo esc_html( $it['name'] ); ?></span>
                                                                <?php if ( ! empty( $it['chips'] ) ) : ?>
                                                                        <span class="hsw-ckitems__chip"><?php echo esc_html( $it['chips'][0]['label'] ); ?></span>
                                                                <?php endif; ?>
                                                        </span>
                                                </a>
                                        <?php endforeach; ?>
                                </div>

                                <?php echo HSW_Helpers::savings_html( 'hsw-savings--checkout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* inside. ?>
                        </article>

                        <!-- =================== MOBILE =================== -->
                        <section class="hsw-ckitems__mcard hsw-m">
                                <div class="hsw-ckitems__hd">
                                        <?php echo HSW_Helpers::icon( 'inventory_2' ); ?>
                                        <h2 class="hsw-ckitems__hd-t"><?php echo esc_html( $title ); ?></h2>
                                        <span class="hsw-ckitems__count"><?php echo esc_html( $count_tpl ); ?></span>
                                </div>

                                <div class="hsw-ckitems__row">
                                        <?php foreach ( $items as $it ) : ?>
                                                <a class="hsw-ckitems__item" href="<?php echo esc_url( isset( $it['url'] ) ? $it['url'] : '#' ); ?>">
                                                        <span class="hsw-ckitems__thumb">
                                                                <?php echo isset( $it['img'] ) ? $it['img'] : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                                <span class="hsw-ckitems__qty"><?php echo esc_html( HSW_Helpers::fa_num( (int) ( isset( $it['qty'] ) ? $it['qty'] : 1 ) ) ); ?>×</span>
                                                        </span>
                                                        <span class="hsw-ckitems__tx">
                                                                <span class="hsw-ckitems__name"><?php echo esc_html( $it['name'] ); ?></span>
                                                                <?php if ( ! empty( $it['chips'] ) ) : ?>
                                                                        <span class="hsw-ckitems__chip"><?php echo esc_html( $it['chips'][0]['label'] ); ?></span>
                                                                <?php endif; ?>
                                                        </span>
                                                </a>
                                        <?php endforeach; ?>
                                </div>
                        </section>

                </section>
                <?php
        }
}
