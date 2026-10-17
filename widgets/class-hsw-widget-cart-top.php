<?php
/**
 * Widget: Cart header & steps — faithful conversion of the supplied design.
 * Desktop: minimal utility bar (brand + secure badge) + breadcrumb + 3-step tracker.
 * Mobile: sticky header (back + title + count + empty-cart) + stepper progress card.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class HSW_Widget_Cart_Top extends HSW_Cart_Base {

        public function get_name() {
                return 'hsw-cart-top';
        }

        public function get_title() {
                return __( 'هدر و مراحل سبد خرید (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-header';
        }

        public function get_keywords() {
                return array( 'cart', 'checkout steps', 'breadcrumb', 'سبد خرید', 'مراحل خرید', 'hamishop' );
        }

        protected function register_controls() {
                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control(
                        'show_bar',
                        array(
                                'label'   => __( 'نوار برند و ضمانت (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'brand_name',
                        array(
                                'label'     => __( 'نام فروشگاه', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'سام‌فروش', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_bar' => 'yes' ),
                        )
                );

                $this->add_control(
                        'brand_url',
                        array(
                                'label'     => __( 'لینک برند', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::URL,
                                'options'   => array( 'url', 'is_external', 'nofollow' ),
                                'condition' => array( 'show_bar' => 'yes' ),
                        )
                );

                $this->add_control(
                        'bar_note',
                        array(
                                'label'     => __( 'یادداشت کنار برند', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'سبد خرید خریدار', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_bar' => 'yes' ),
                        )
                );

                $this->add_control(
                        'secure_text',
                        array(
                                'label'     => __( 'متن نشان اعتماد', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'ضمانت خرید امن و اصالت کالا', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_bar' => 'yes' ),
                        )
                );

                $this->add_control(
                        'sticky_bar',
                        array(
                                'label'        => __( 'چسبیدن نوار به بالای صفحه', 'hamishop-elementor-widgets' ),
                                'type'         => Controls_Manager::SWITCHER,
                                'default'      => 'yes',
                                'condition'    => array( 'show_bar' => 'yes' ),
                                'description'  => __( 'اگر قالب هدر ثابت دارد، بهتر است خاموش باشد.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'show_bc',
                        array(
                                'label'   => __( 'مسیر راهنما (Breadcrumb)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'bc_home',
                        array(
                                'label'     => __( 'متن خانه', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'فروشگاه', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_bc' => 'yes' ),
                        )
                );

                $this->add_control(
                        'bc_current',
                        array(
                                'label'     => __( 'متن آیتم فعلی', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'سبد خرید فعلی', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_bc' => 'yes' ),
                        )
                );

                $this->add_control(
                        'steps_heading',
                        array( 'label' => __( 'مراحل ثبت سفارش', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $this->add_control(
                        'active_step',
                        array(
                                'label'   => __( 'مرحله فعال', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => '1',
                                'options' => array(
                                        '1' => __( '۱ — سبد خرید', 'hamishop-elementor-widgets' ),
                                        '2' => __( '۲ — اطلاعات ارسال', 'hamishop-elementor-widgets' ),
                                        '3' => __( '۳ — پرداخت', 'hamishop-elementor-widgets' ),
                                ),
                        )
                );

                $repeater = new Repeater();
                $repeater->add_control(
                        'label',
                        array(
                                'label'   => __( 'برچسب دسکتاپ', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => 'مرحله',
                        )
                );
                $repeater->add_control(
                        'label_m',
                        array(
                                'label'       => __( 'برچسب موبایل (اختیاری)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'description' => __( 'خالی = همان برچسب دسکتاپ.', 'hamishop-elementor-widgets' ),
                        )
                );
                $this->add_control(
                        'steps',
                        array(
                                'label'       => __( 'مراحل', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $repeater->get_controls(),
                                'title_field' => '{{{ label }}}',
                                'default'     => array(
                                        array( 'label' => __( 'سبد خرید', 'hamishop-elementor-widgets' ), 'label_m' => __( 'سبد خرید', 'hamishop-elementor-widgets' ) ),
                                        array( 'label' => __( 'اطلاعات ارسال', 'hamishop-elementor-widgets' ), 'label_m' => __( 'مشخصات و ارسال', 'hamishop-elementor-widgets' ) ),
                                        array( 'label' => __( 'پرداخت فاکتور', 'hamishop-elementor-widgets' ), 'label_m' => __( 'پرداخت', 'hamishop-elementor-widgets' ) ),
                                ),
                        )
                );

                $this->add_control(
                        'm_title',
                        array(
                                'label'     => __( 'عنوان هدر موبایل', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'سبد خرید', 'hamishop-elementor-widgets' ),
                                'separator' => 'before',
                        )
                );

                $this->add_control(
                        'm_count',
                        array(
                                'label'       => __( 'قالب زیرعنوان موبایل', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( '{count} کالا انتخابی', 'hamishop-elementor-widgets' ),
                                'description' => __( '{count} با تعداد کل اقلام جایگزین می‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'show_empty_btn',
                        array(
                                'label'   => __( 'دکمه خالی کردن سبد (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_box( 'bar', '{{WRAPPER}} .hsw-ctop__bar', array( 'radius' => false ) );
                $this->a_color( 'bar_ic_bg', __( 'پس‌زمینه آیکن برند', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ctop__brand-ic', 'background-color' );
                $this->a_color( 'bar_ic_tx', __( 'آیکن برند', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ctop__brand-ic .hsw-ic' );
                $this->a_text( 'bar_brand', '{{WRAPPER}} .hsw-ctop__brand-a', array( 'default' => 18 ) );
                $this->a_text( 'bar_note', '{{WRAPPER}} .hsw-ctop__brand-note', array( 'default' => 13 ) );
                $this->a_color( 'bar_secure_bg', __( 'پس‌زمینه نشان اعتماد', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ctop__secure', 'background-color' );
                $this->a_color( 'bar_secure_bd', __( 'حاشیه نشان اعتماد', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ctop__secure', 'border-color' );
                $this->a_text( 'bar_secure', '{{WRAPPER}} .hsw-ctop__secure', array( 'default' => 13 ) );

                $this->a_color( 'bc_tx', __( 'متن مسیر راهنما', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ctop__bc a' );
                $this->a_color( 'bc_cur', __( 'آیتم فعلی مسیر', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ctop__bc-cur' );

                $this->a_color( 'st_on', __( 'مرحله فعال (متن و نقطه)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ctop__step.is-active' );
                $this->a_color( 'st_on_bg', __( 'پس‌زمینه نقطه فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ctop__step.is-active .hsw-ctop__dot', 'background-color' );
                $this->a_color( 'st_on_tx', __( 'متن نقطه فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ctop__step.is-active .hsw-ctop__dot' );
                $this->a_color( 'st_line', __( 'خط طی‌شده', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ctop__line.is-on', 'background-color' );
                $this->a_color( 'st_off', __( 'مراحل بعدی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ctop__step:not(.is-active):not(.is-done)' );

                $this->end_controls_section();
        }

        /**
         * Step labels (desktop / mobile).
         */
        protected function steps_list() {
                $s     = $this->get_settings_for_display();
                $steps = ( isset( $s['steps'] ) && is_array( $s['steps'] ) ) ? $s['steps'] : array();
                if ( empty( $steps ) ) {
                        return array(
                                array( __( 'سبد خرید', 'hamishop-elementor-widgets' ), __( 'سبد خرید', 'hamishop-elementor-widgets' ) ),
                                array( __( 'اطلاعات ارسال', 'hamishop-elementor-widgets' ), __( 'مشخصات و ارسال', 'hamishop-elementor-widgets' ) ),
                                array( __( 'پرداخت فاکتور', 'hamishop-elementor-widgets' ), __( 'پرداخت', 'hamishop-elementor-widgets' ) ),
                        );
                }
                $out = array();
                foreach ( $steps as $st ) {
                        $out[] = array(
                                isset( $st['label'] ) ? $st['label'] : '',
                                ( isset( $st['label_m'] ) && '' !== trim( (string) $st['label_m'] ) ) ? $st['label_m'] : ( isset( $st['label'] ) ? $st['label'] : '' ),
                        );
                }
                return $out;
        }

        protected function render() {
                $s      = $this->get_settings_for_display();
                $steps  = $this->steps_list();
                $active = max( 1, min( count( $steps ), (int) $this->opt( 'active_step', '1' ) ) );
                $demo   = $this->is_demo();

                $count      = $demo ? 2 : $this->cart_count();
                $m_count    = $this->with_count( $this->opt( 'm_count', __( '{count} کالا انتخابی', 'hamishop-elementor-widgets' ) ), $count );
                $show_bar   = ( 'yes' === ( $s['show_bar'] ?? 'yes' ) );
                $show_bc    = ( 'yes' === ( $s['show_bc'] ?? 'yes' ) );
                $sticky     = ( 'yes' === ( $s['sticky_bar'] ?? 'yes' ) );
                $show_empty = ( 'yes' === ( $s['show_empty_btn'] ?? 'yes' ) );

                $brand_name = $this->opt( 'brand_name', __( 'سام‌فروش', 'hamishop-elementor-widgets' ) );
                $brand_url  = ( ! empty( $s['brand_url']['url'] ) ) ? $s['brand_url']['url'] : $this->shop_url();
                ?>
                <section class="<?php echo esc_attr( $this->root_class( 'ctop' ) ); ?>">

                        <!-- =================== DESKTOP =================== -->
                        <?php if ( $show_bar ) : ?>
                                <header class="hsw-ctop__bar hsw-d<?php echo $sticky ? ' is-sticky' : ''; ?>">
                                        <div class="hsw-ctop__shell hsw-ctop__bar-in">
                                                <div class="hsw-ctop__brand">
                                                        <a class="hsw-ctop__brand-a" href="<?php echo esc_url( $brand_url ); ?>">
                                                                <span class="hsw-ctop__brand-ic"><i class="hsw-ic" aria-hidden="true">shopping_bag</i></span>
                                                                <span><?php echo esc_html( $brand_name ); ?></span>
                                                        </a>
                                                        <?php if ( '' !== trim( (string) ( $s['bar_note'] ?? '' ) ) ) : ?>
                                                                <span class="hsw-ctop__brand-sep" aria-hidden="true"></span>
                                                                <span class="hsw-ctop__brand-note"><?php echo esc_html( $s['bar_note'] ); ?></span>
                                                        <?php endif; ?>
                                                </div>
                                                <?php if ( '' !== trim( (string) ( $s['secure_text'] ?? '' ) ) ) : ?>
                                                        <div class="hsw-ctop__secure">
                                                                <i class="hsw-ic" aria-hidden="true">verified_user</i>
                                                                <span><?php echo esc_html( $s['secure_text'] ); ?></span>
                                                        </div>
                                                <?php endif; ?>
                                        </div>
                                </header>
                        <?php endif; ?>

                        <div class="hsw-ctop__wrap hsw-d">
                                <div class="hsw-ctop__shell hsw-ctop__wrap-in">
                                        <div class="hsw-ctop__row">
                                                <?php if ( $show_bc ) : ?>
                                                        <nav class="hsw-ctop__bc" aria-label="breadcrumb">
                                                                <a href="<?php echo esc_url( $this->shop_url() ); ?>"><?php echo esc_html( $this->opt( 'bc_home', __( 'فروشگاه', 'hamishop-elementor-widgets' ) ) ); ?></a>
                                                                <i class="hsw-ic hsw-ctop__bc-sep" aria-hidden="true">chevron_left</i>
                                                                <span class="hsw-ctop__bc-cur"><?php echo esc_html( $this->opt( 'bc_current', __( 'سبد خرید فعلی', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                        </nav>
                                                <?php endif; ?>

                                                <ol class="hsw-ctop__track">
                                                        <?php foreach ( $steps as $i => $st ) : ?>
                                                                <?php
                                                                $n    = $i + 1;
                                                                $cls  = ( $n === $active ) ? ' is-active' : '';
                                                                $cls .= ( $n < $active ) ? ' is-done' : '';
                                                                ?>
                                                                <?php if ( 0 < $i ) : ?>
                                                                        <li class="hsw-ctop__line<?php echo ( $n <= $active ) ? ' is-on' : ''; ?>" aria-hidden="true"></li>
                                                                <?php endif; ?>
                                                                <li class="hsw-ctop__step<?php echo esc_attr( $cls ); ?>">
                                                                        <span class="hsw-ctop__dot"><?php echo esc_html( HSW_Helpers::fa_num( $n ) ); ?></span>
                                                                        <span><?php echo esc_html( $st[0] ); ?></span>
                                                                </li>
                                                        <?php endforeach; ?>
                                                </ol>
                                        </div>
                                </div>
                        </div>

                        <!-- =================== MOBILE =================== -->
                        <header class="hsw-ctop__mhead hsw-m">
                                <div class="hsw-ctop__mrow">
                                        <div class="hsw-ctop__mbrand">
                                                <a class="hsw-ctop__mback" href="<?php echo esc_url( $this->shop_url() ); ?>" aria-label="<?php esc_attr_e( 'بازگشت', 'hamishop-elementor-widgets' ); ?>">
                                                        <i class="hsw-ic" aria-hidden="true">arrow_forward</i>
                                                </a>
                                                <div>
                                                        <h1 class="hsw-ctop__mtitle"><?php echo esc_html( $this->opt( 'm_title', __( 'سبد خرید', 'hamishop-elementor-widgets' ) ) ); ?></h1>
                                                        <span class="hsw-ctop__msub"><?php echo esc_html( $m_count ); ?></span>
                                                </div>
                                        </div>
                                        <?php if ( $show_empty && ( $demo || ( $this->has_cart() && 0 < $count ) ) ) : ?>
                                                <form method="post" action="<?php echo esc_url( $this->cart_url() ); ?>" class="hsw-citems__emptyform">
                                                        <?php echo $this->empty_nonce(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hidden nonce inputs. ?>
                                                        <button type="submit" name="hsw_empty_cart" value="1" class="hsw-ctop__mempty" title="<?php esc_attr_e( 'خالی کردن سبد', 'hamishop-elementor-widgets' ); ?>">
                                                                <i class="hsw-ic" aria-hidden="true">delete_sweep</i>
                                                        </button>
                                                </form>
                                        <?php endif; ?>
                                </div>
                        </header>

                        <div class="hsw-ctop__mwrap hsw-m">
                                <section class="hsw-ctop__msteps" aria-label="<?php esc_attr_e( 'مراحل ثبت سفارش', 'hamishop-elementor-widgets' ); ?>">
                                        <?php foreach ( $steps as $i => $st ) : ?>
                                                <?php
                                                $n    = $i + 1;
                                                $cls  = ( $n === $active ) ? ' is-active' : '';
                                                $cls .= ( $n < $active ) ? ' is-done' : '';
                                                ?>
                                                <?php if ( 0 < $i ) : ?>
                                                        <div class="hsw-ctop__mline<?php echo ( $n <= $active ) ? ' is-on' : ''; ?>" aria-hidden="true"></div>
                                                <?php endif; ?>
                                                <div class="hsw-ctop__mstep<?php echo esc_attr( $cls ); ?>">
                                                        <span class="hsw-ctop__dot"><?php echo esc_html( HSW_Helpers::fa_num( $n ) ); ?></span>
                                                        <span class="hsw-ctop__mstep-label"><?php echo esc_html( $st[1] ); ?></span>
                                                </div>
                                        <?php endforeach; ?>
                                </section>
                        </div>

                </section>
                <?php
        }
}
