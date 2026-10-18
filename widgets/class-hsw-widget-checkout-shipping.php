<?php
/**
 * Widget: Shipping methods selector — faithful conversion of the mock.
 * Desktop: boxed card with radio rows (icon bubble, title + «پیشنهادی» badge,
 * description, price + optional strikethrough was-price).
 * Mobile: section with custom radio circles + badge chip.
 * Real WooCommerce rates when available (selection is stored through the
 * native WC session, same as WooCommerce's own review step); repeater demo
 * options in the editor.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class HSW_Widget_Checkout_Shipping extends HSW_Checkout_Base {

        public function get_name() {
                return 'hsw-checkout-shipping';
        }

        public function get_title() {
                return __( 'انتخاب روش ارسال (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-cart-medium';
        }

        public function get_keywords() {
                return array( 'checkout', 'shipping method', 'ارسال', 'پست', 'پیک', 'hamishop' );
        }

        public static function icon_choices() {
                return array(
                        'two_wheeler'        => 'پیک موتوری',
                        'markunread_mailbox' => 'صندوق پست',
                        'inventory_2'        => 'بسته/جعبه',
                        'local_shipping'     => 'کامیون ارسال',
                        'bolt'               => 'برق (اکسپرس)',
                        'schedule'           => 'ساعت (زمان‌بندی)',
                        'warehouse'          => 'انبار',
                        'flight_takeoff'     => 'هوایی',
                );
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
                                'label'   => __( 'عنوان (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'انتخاب روش ارسال', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'title_m',
                        array(
                                'label'   => __( 'عنوان (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'روش ارسال', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'hint_m',
                        array(
                                'label'   => __( 'راهنمای موبایل', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'انتخاب یک گزینه', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'free_text',
                        array(
                                'label'   => __( 'متن ارسال رایگان', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'رایگان', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'badge_text',
                        array(
                                'label'       => __( 'متن نشان پیشنهادی', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'پیشنهادی', 'hamishop-elementor-widgets' ),
                                'description' => __( 'برای روش‌های واقعی، روی گزینه انتخاب‌شده نشان داده می‌شود؛ برای دمو روی گزینه اول.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'demo_heading',
                        array( 'label' => __( 'گزینه‌های پیش‌فرض (و ویرایشگر)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $repeater = new Repeater();
                $repeater->add_control(
                        'title',
                        array(
                                'label'   => __( 'عنوان', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => 'روش ارسال',
                        )
                );
                $repeater->add_control(
                        'desc',
                        array(
                                'label'   => __( 'توضیح کوتاه', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                        )
                );
                $repeater->add_control(
                        'icon',
                        array(
                                'label'   => __( 'آیکن', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => 'local_shipping',
                                'options' => self::icon_choices(),
                        )
                );
                $repeater->add_control(
                        'cost',
                        array(
                                'label'       => __( 'هزینه (تومان)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::NUMBER,
                                'default'     => 0,
                                'description' => __( '۰ = رایگان.', 'hamishop-elementor-widgets' ),
                        )
                );
                $repeater->add_control(
                        'was',
                        array(
                                'label'       => __( 'قیمت خط‌خورده (اختیاری)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::NUMBER,
                                'description' => __( 'فقط وقتی هزینه ۰ است نمایش داده می‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );
                $repeater->add_control(
                        'badge',
                        array(
                                'label'   => __( 'نشان (اختیاری)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                        )
                );
                $repeater->add_control(
                        'chosen',
                        array(
                                'label'   => __( 'انتخاب‌شده', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                        )
                );
                $this->add_control(
                        'options',
                        array(
                                'label'       => __( 'گزینه‌ها', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $repeater->get_controls(),
                                'title_field' => '{{{ title }}}',
                                'default'     => $this->demo_rates_repeat(),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_box( 'card', '{{WRAPPER}} .hsw-ckship__card' );
                $this->a_color( 'hd_ic', __( 'آیکن عنوان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ckship__hd .hsw-ic' );
                $this->a_text( 'hd_tx', '{{WRAPPER}} .hsw-ckship__hd-t', array( 'default' => 16 ) );
                $this->a_text( 'opt_title', '{{WRAPPER}} .hsw-ckship__t', array( 'default' => 14 ) );
                $this->a_text( 'opt_desc', '{{WRAPPER}} .hsw-ckship__d', array( 'default' => 12 ) );
                $this->a_color( 'badge_bg', __( 'پس‌زمینه نشان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ckship__badge', 'background-color' );
                $this->a_color( 'badge_c', __( 'متن نشان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ckship__badge' );
                $this->a_color( 'price_c', __( 'قیمت گزینه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ckship__p' );
                $this->a_color( 'free_c', __( 'رنگ رایگان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ckship__p.is-free' );

                $this->end_controls_section();
        }

        /**
         * Repeater defaults mirroring the supplied mock.
         */
        protected function demo_rates_repeat() {
                $out = array();
                foreach ( $this->demo_rates() as $rate ) {
                        $out[] = array(
                                'title'  => $rate['title'],
                                'desc'   => $rate['desc'],
                                'icon'   => $rate['icon'],
                                'cost'   => $rate['cost'],
                                'was'    => $rate['was'],
                                'badge'  => $rate['badge'],
                                'chosen' => $rate['chosen'] ? 'yes' : '',
                        );
                }
                return $out;
        }

        /**
         * Unified option payloads (real rates win over the repeater).
         */
        protected function options_list() {
                $rates = $this->is_demo() ? array() : $this->shipping_rates();
                if ( ! empty( $rates ) ) {
                        $badge = $this->opt( 'badge_text', __( 'پیشنهادی', 'hamishop-elementor-widgets' ) );
                        $out   = array();
                        foreach ( $rates as $rate ) {
                                $out[] = array(
                                        'title'  => $rate['title'],
                                        'desc'   => '',
                                        'icon'   => $this->icon_for_rate( $rate['title'] ),
                                        'cost'   => $rate['cost'],
                                        'was'    => 0,
                                        'badge'  => ( ! empty( $rate['chosen'] ) && '' !== trim( $badge ) ) ? $badge : '',
                                        'chosen' => ! empty( $rate['chosen'] ),
                                );
                        }
                        return array( $out, true );
                }

                $s     = $this->get_settings_for_display();
                $opts  = ( isset( $s['options'] ) && is_array( $s['options'] ) ) ? $s['options'] : array();
                $list  = array();
                $first = true;
                foreach ( $opts as $opt ) {
                        $list[] = array(
                                'title'  => isset( $opt['title'] ) ? $opt['title'] : '',
                                'desc'   => ( isset( $opt['desc'] ) && '' !== trim( (string) $opt['desc'] ) ) ? $opt['desc'] : '',
                                'icon'   => isset( $opt['icon'] ) ? $opt['icon'] : 'local_shipping',
                                'cost'   => (float) ( isset( $opt['cost'] ) ? $opt['cost'] : 0 ),
                                'was'    => (float) ( isset( $opt['was'] ) ? $opt['was'] : 0 ),
                                'badge'  => ( isset( $opt['badge'] ) && '' !== trim( (string) $opt['badge'] ) ) ? $opt['badge'] : ( $first ? $this->opt( 'badge_text', '' ) : '' ),
                                'chosen' => ( isset( $opt['chosen'] ) && 'yes' === $opt['chosen'] ) || ( $first && ! isset( $opts[0]['chosen'] ) ),
                        );
                        $first = false;
                }
                if ( empty( $list ) ) {
                        foreach ( $this->demo_rates() as $rate ) {
                                $list[] = array(
                                        'title'  => $rate['title'],
                                        'desc'   => $rate['desc'],
                                        'icon'   => $rate['icon'],
                                        'cost'   => $rate['cost'],
                                        'was'    => $rate['was'],
                                        'badge'  => $rate['badge'],
                                        'chosen' => $rate['chosen'],
                                );
                        }
                }
                return array( $list, false );
        }

        /**
         * Price cell HTML (رایگان + optional strikethrough).
         */
        protected function shipping_price_html( $opt ) {
                $free = $this->opt( 'free_text', __( 'رایگان', 'hamishop-elementor-widgets' ) );
                if ( 0.0 === (float) $opt['cost'] ) {
                        $was = '';
                        if ( 0 < (float) $opt['was'] ) {
                                $was = '<span class="hsw-ckship__was">' . $this->money( (float) $opt['was'], true ) . '</span>';
                        }
                        return '<span class="hsw-ckship__p is-free">' . esc_html( $free ) . '</span>' . $was;
                }
                return '<span class="hsw-ckship__p">' . $this->money( (float) $opt['cost'], true ) . '</span>';
        }

        protected function render() {
                list( $options, $live ) = $this->options_list();

                $title  = $this->opt( 'title', __( 'انتخاب روش ارسال', 'hamishop-elementor-widgets' ) );
                $title_m = $this->opt( 'title_m', __( 'روش ارسال', 'hamishop-elementor-widgets' ) );
                $hint_m = $this->opt( 'hint_m', __( 'انتخاب یک گزینه', 'hamishop-elementor-widgets' ) );
                ?>
                <section class="<?php echo esc_attr( $this->root_class( 'ckship' ) ); ?>">

                        <!-- =================== DESKTOP =================== -->
                        <article class="hsw-ckship__card hsw-d" data-hswk-ship data-live="<?php echo $live ? '1' : '0'; ?>">
                                <div class="hsw-ckship__hd">
                                        <i class="hsw-ic is-fill" aria-hidden="true">local_shipping</i>
                                        <h2 class="hsw-ckship__hd-t"><?php echo esc_html( $title ); ?></h2>
                                </div>

                                <div class="hsw-ckship__opts" role="radiogroup" aria-label="<?php echo esc_attr( $title ); ?>">
                                        <?php foreach ( $options as $i => $opt ) : ?>
                                                <label class="hsw-ckship__opt<?php echo ! empty( $opt['chosen'] ) ? ' is-sel' : ''; ?>">
                                                        <input type="radio" class="hsw-ckship__input" name="hsw_shipping_method_d" value="<?php echo esc_attr( isset( $opt['id'] ) ? $opt['id'] : $i ); ?>"<?php checked( ! empty( $opt['chosen'] ) ); ?> />
                                                        <span class="hsw-ckship__ic"><i class="hsw-ic" aria-hidden="true"><?php echo esc_html( $opt['icon'] ); ?></i></span>
                                                        <span class="hsw-ckship__tx">
                                                                <span class="hsw-ckship__trow">
                                                                        <span class="hsw-ckship__t"><?php echo esc_html( $opt['title'] ); ?></span>
                                                                        <?php if ( '' !== trim( (string) $opt['badge'] ) ) : ?>
                                                                                <span class="hsw-ckship__badge"><?php echo esc_html( $opt['badge'] ); ?></span>
                                                                        <?php endif; ?>
                                                                </span>
                                                                <?php if ( '' !== trim( (string) $opt['desc'] ) ) : ?>
                                                                        <span class="hsw-ckship__d"><?php echo esc_html( $opt['desc'] ); ?></span>
                                                                <?php endif; ?>
                                                        </span>
                                                        <span class="hsw-ckship__price"><?php echo $this->shipping_price_html( $opt ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?></span>
                                                </label>
                                        <?php endforeach; ?>
                                </div>
                        </article>

                        <!-- =================== MOBILE =================== -->
                        <div class="hsw-m hsw-ckship__mwrap" data-hswk-ship data-live="<?php echo $live ? '1' : '0'; ?>">
                                <div class="hsw-ckship__mhd">
                                        <h2 class="hsw-ckship__hd-t hsw-ckship__hd-t--m">
                                                <i class="hsw-ic" aria-hidden="true">local_shipping</i>
                                                <?php echo esc_html( $title_m ); ?>
                                        </h2>
                                        <?php if ( '' !== trim( $hint_m ) ) : ?>
                                                <span class="hsw-ckship__hint"><?php echo esc_html( $hint_m ); ?></span>
                                        <?php endif; ?>
                                </div>

                                <div class="hsw-ckship__mopts" role="radiogroup" aria-label="<?php echo esc_attr( $title_m ); ?>">
                                        <?php foreach ( $options as $i => $opt ) : ?>
                                                <label class="hsw-ckship__opt hsw-ckship__opt--m<?php echo ! empty( $opt['chosen'] ) ? ' is-sel' : ''; ?>">
                                                        <input type="radio" class="hsw-ckship__input" name="hsw_shipping_method_m" value="<?php echo esc_attr( isset( $opt['id'] ) ? $opt['id'] : $i ); ?>"<?php checked( ! empty( $opt['chosen'] ) ); ?> />
                                                        <span class="hsw-ckship__dot" aria-hidden="true"><span></span></span>
                                                        <span class="hsw-ckship__tx">
                                                                <span class="hsw-ckship__trow">
                                                                        <span class="hsw-ckship__t"><?php echo esc_html( $opt['title'] ); ?></span>
                                                                        <?php if ( '' !== trim( (string) $opt['badge'] ) ) : ?>
                                                                                <span class="hsw-ckship__badge"><?php echo esc_html( $opt['badge'] ); ?></span>
                                                                        <?php endif; ?>
                                                                </span>
                                                                <?php if ( '' !== trim( (string) $opt['desc'] ) ) : ?>
                                                                        <span class="hsw-ckship__d"><?php echo esc_html( $opt['desc'] ); ?></span>
                                                                <?php endif; ?>
                                                        </span>
                                                        <span class="hsw-ckship__price hsw-ckship__price--m"><?php echo $this->shipping_price_html( $opt ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                                </label>
                                        <?php endforeach; ?>
                                </div>
                        </div>

                </section>
                <?php
        }
}
