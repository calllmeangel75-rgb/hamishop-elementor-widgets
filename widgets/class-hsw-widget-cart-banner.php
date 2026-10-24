<?php
/**
 * Widget: Cart free-shipping banner — faithful conversion of the supplied design.
 * Dynamic: threshold, remaining amount and completion percent from the live cart.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Cart_Banner extends HSW_Cart_Base {

        public function get_name() {
                return 'hsw-cart-banner';
        }

        public function get_title() {
                return __( 'بنر ارسال رایگان سبد (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-banner';
        }

        public function get_keywords() {
                return array( 'cart', 'free shipping', 'progress', 'ارسال رایگان', 'سبد خرید', 'hamishop' );
        }

        protected function register_controls() {
                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control(
                        'threshold',
                        array(
                                'label'       => __( 'سقف خرید برای ارسال رایگان (تومان)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::NUMBER,
                                'default'     => 1000000,
                                'min'         => 0,
                                'step'        => 1000,
                                'description' => __( '۰ = ویجت پنهان می‌شود. مبلغ با محاسبه خودکار از سبد جایگزین می‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'title_active',
                        array(
                                'label'       => __( 'عنوان (ارسال فعال)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'ارسال رایگان سفارش شما فعال شد!', 'hamishop-elementor-widgets' ),
                                'condition'   => array( 'threshold!' => '' ),
                        )
                );

                $this->add_control(
                        'desc_active',
                        array(
                                'label'       => __( 'توضیح (ارسال فعال)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXTAREA,
                                'rows'        => 2,
                                'default'     => __( 'سفارش شما از سقف خرید {threshold} تومان عبور کرده و بدون دریافت هزینه پستی تحویل داده خواهد شد.', 'hamishop-elementor-widgets' ),
                                'description' => __( '{threshold} = سقف خرید.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'title_inactive',
                        array(
                                'label'       => __( 'عنوان (تا ارسال رایگان)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'تنها {remaining} تومان تا ارسال رایگان!', 'hamishop-elementor-widgets' ),
                                'description' => __( '{remaining} = مبلغ مانده تا ارسال رایگان.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'desc_inactive',
                        array(
                                'label'   => __( 'توضیح (تا ارسال رایگان)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXTAREA,
                                'rows'    => 2,
                                'default' => __( 'با {remaining} تومان خرید بیشتر، ارسال سفارش شما رایگان می‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'show_pct',
                        array(
                                'label'   => __( 'نمایش درصد تکمیل', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'pct_text',
                        array(
                                'label'       => __( 'قالب درصد', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( '{percent}٪ تکمیل', 'hamishop-elementor-widgets' ),
                                'condition'   => array( 'show_pct' => 'yes' ),
                                'description' => __( '{percent} = درصد پیشرفت.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_box( 'bn', '{{WRAPPER}} .hsw-cbanner__card' );
                $this->a_color( 'bn_ic_bg', __( 'پس‌زمینه آیکن', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cbanner__ic', 'background-color' );
                $this->a_color( 'bn_ic', __( 'آیکن', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cbanner__ic .hsw-ic' );
                $this->a_text( 'bn_title', '{{WRAPPER}} .hsw-cbanner__title', array( 'default' => 14 ) );
                $this->a_color( 'bn_pct', __( 'متن درصد', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cbanner__pct' );
                $this->a_text( 'bn_desc', '{{WRAPPER}} .hsw-cbanner__desc', array( 'default' => 12 ) );
                $this->a_color( 'bn_track', __( 'زمینه نوار پیشرفت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cbanner__track', 'background-color' );
                $this->a_color( 'bn_fill', __( 'نوار پیشرفت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cbanner__fill', 'background-color' );

                $this->end_controls_section();
        }

        /**
         * Banner state from the live cart (or the demo).
         *
         * @return array{active:bool,percent:int,title:string,desc:string,pct:string}|null
         */
        protected function banner_state() {
                /*
                 * HamiShop → کنترل ویجت‌ها → ارسال رایگان owns the number.
                 * The widget's own control is only a demo/fallback value: a
                 * banner that promises free shipping at 1,000,000 while the
                 * global threshold is 0 (off) is a lie the customer acts on.
                 */
                $global = class_exists( 'HSW_Free_Shipping' ) ? (float) HSW_Free_Shipping::threshold() : 0.0;

                if ( $this->is_demo() ) {
                        $threshold = $global > 0 ? $global : (float) $this->opt( 'threshold', 1000000 );
                        $subtotal  = 67210000;
                } else {
                        if ( $global <= 0 || ! $this->has_cart() ) {
                                return null;
                        }
                        $threshold = $global;
                        $subtotal  = (float) WC()->cart->get_displayed_subtotal();
                }

                if ( $threshold <= 0 ) {
                        return null;
                }

                $active    = ( $subtotal >= $threshold );
                $remaining = max( 0, $threshold - $subtotal );
                $percent   = min( 100, (int) round( ( $subtotal / $threshold ) * 100 ) );

                $title = $active ? $this->opt( 'title_active', __( 'ارسال رایگان سفارش شما فعال شد!', 'hamishop-elementor-widgets' ) )
                        : $this->opt( 'title_inactive', __( 'تنها {remaining} تومان تا ارسال رایگان!', 'hamishop-elementor-widgets' ) );
                $desc = $active ? $this->opt( 'desc_active', '' ) : $this->opt( 'desc_inactive', '' );

                $repl = array(
                        '{threshold}' => HSW_Helpers::price_num( $threshold ),
                        '{remaining}' => HSW_Helpers::price_num( $remaining ),
                        '{percent}'   => HSW_Helpers::fa_num( $percent ),
                );

                return array(
                        'active'  => $active,
                        'percent' => $percent,
                        'title'   => strtr( $title, $repl ),
                        'desc'    => strtr( $desc, $repl ),
                        'pct'     => strtr( $this->opt( 'pct_text', __( '{percent}٪ تکمیل', 'hamishop-elementor-widgets' ) ), $repl ),
                );
        }

        protected function render() {
                $state = $this->banner_state();
                if ( null === $state ) {
                        return;
                }
                ?>
                <section class="<?php echo esc_attr( $this->root_class( 'cbanner' ) ); ?>">
                        <div class="hsw-cbanner__card hsw-d">
                                <div class="hsw-cbanner__head">
                                        <div class="hsw-cbanner__head-l">
                                                <span class="hsw-cbanner__ic"><?php echo HSW_Helpers::icon( 'local_shipping' ); ?></span>
                                                <span class="hsw-cbanner__title"><?php echo esc_html( $state['title'] ); ?></span>
                                        </div>
                                        <?php if ( 'yes' === ( $this->effective_settings()['show_pct'] ?? 'yes' ) ) : ?>
                                                <span class="hsw-cbanner__pct"><?php echo esc_html( $state['pct'] ); ?></span>
                                        <?php endif; ?>
                                </div>
                                <?php if ( '' !== trim( $state['desc'] ) ) : ?>
                                        <p class="hsw-cbanner__desc"><?php echo esc_html( $state['desc'] ); ?></p>
                                <?php endif; ?>
                                <div class="hsw-cbanner__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $state['percent'] ); ?>">
                                        <div class="hsw-cbanner__fill" style="width:<?php echo esc_attr( $state['percent'] ); ?>%"></div>
                                </div>
                        </div>
                </section>
                <?php
        }
}
