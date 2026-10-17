<?php
/**
 * Asset registration. Styles/scripts are only printed when a widget that
 * depends on them is present on the page (Elementor native behavior).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Assets {

        public function __construct() {
                add_action( 'wp_enqueue_scripts', array( $this, 'register' ) );
                add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'editor_style' ) );
                add_filter( 'elementor/fonts/groups', array( $this, 'font_groups' ) );
                add_filter( 'elementor/fonts/additional_fonts', array( $this, 'additional_fonts' ) );
                add_action( 'elementor/element/after_section_end', array( $this, 'append_common_section' ), 10, 3 );
        }

        /**
         * Append the shared «قالب‌سازگاری» style section to every HSW widget
         * (and only HSW widgets) — font mode: design / custom / theme-inherit.
         * This is what makes the widgets reusable on any site: پوشاک، دیجیتال،
         * هر قالبی با هر فونتی — بدون دست‌زدن به کد.
         */
        public function append_common_section( $element, $section_id, $args = array() ) {
                if ( ! $element instanceof \Elementor\Widget_Base ) {
                        return;
                }
                if ( 'hsw-' !== substr( $element->get_name(), 0, 4 ) ) {
                        return;
                }
                $tab = isset( $args['tab'] ) ? $args['tab'] : '';
                if ( \Elementor\Controls_Manager::TAB_STYLE !== $tab ) {
                        return;
                }
                /* Fire only once per widget instance (some widgets have several style sections). */
                if ( $element->get_controls( 'hsw_font_mode' ) ) {
                        return;
                }

                $element->start_controls_section(
                        'hsw_sec_common',
                        array(
                                'label' => __( 'سازگاری با قالب (فونت)', 'hamishop-elementor-widgets' ),
                                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
                        )
                );

                $element->add_control(
                        'hsw_font_mode',
                        array(
                                'label'   => __( 'فونت پایه ویجت', 'hamishop-elementor-widgets' ),
                                'type'    => \Elementor\Controls_Manager::SELECT,
                                'default' => 'design',
                                'options' => array(
                                        'design'  => __( 'فونت طرح (وزیرمتن لوکال)', 'hamishop-elementor-widgets' ),
                                        'custom'  => __( 'فونت سفارشی', 'hamishop-elementor-widgets' ),
                                        'inherit' => __( 'ارث‌بری فونت از قالب سایت', 'hamishop-elementor-widgets' ),
                                ),
                        )
                );

                $element->add_control(
                        'hsw_font_custom',
                        array(
                                'label'       => __( 'نام فونت سفارشی', 'hamishop-elementor-widgets' ),
                                'type'        => \Elementor\Controls_Manager::TEXT,
                                'description' => __( 'دقیقاً همان نام font-face که قالب لود کرده؛ مثلاً Peyda یا IRANSansX', 'hamishop-elementor-widgets' ),
                                'condition'   => array( 'hsw_font_mode' => 'custom' ),
                                'selectors'   => array(
                                        '{{WRAPPER}}' => "--hsw-font: '{{VALUE}}';",
                                ),
                        )
                );

                $element->end_controls_section();
        }

        /**
         * Register (not enqueue) — Elementor enqueues widget dependencies automatically.
         */
        public function register() {
                wp_register_style(
                        'hsw-fonts',
                        HSW_URL . 'assets/css/hsw-fonts.css',
                        array(),
                        HSW_VERSION
                );

                wp_register_style(
                        'hsw-loop',
                        HSW_URL . 'assets/css/hsw-loop.css',
                        array( 'hsw-fonts' ),
                        HSW_VERSION
                );

                wp_register_script(
                        'hsw-loop',
                        HSW_URL . 'assets/js/hsw-loop.js',
                        array(),
                        HSW_VERSION,
                        true
                );

                wp_register_style(
                        'hsw-product',
                        HSW_URL . 'assets/css/hsw-product.css',
                        array( 'hsw-fonts' ),
                        HSW_VERSION
                );

                wp_register_style(
                        'hsw-archive',
                        HSW_URL . 'assets/css/hsw-archive.css',
                        array( 'hsw-fonts' ),
                        HSW_VERSION
                );

                wp_register_script(
                        'hsw-archive',
                        HSW_URL . 'assets/js/hsw-archive.js',
                        array(),
                        HSW_VERSION,
                        true
                );

                wp_register_style(
                        'hsw-cart',
                        HSW_URL . 'assets/css/hsw-cart.css',
                        array( 'hsw-fonts' ),
                        HSW_VERSION
                );

                wp_register_script(
                        'hsw-cart',
                        HSW_URL . 'assets/js/hsw-cart.js',
                        array(),
                        HSW_VERSION,
                        true
                );

                wp_register_style(
                        'hsw-checkout',
                        HSW_URL . 'assets/css/hsw-checkout.css',
                        array( 'hsw-fonts' ),
                        HSW_VERSION
                );

                wp_register_script(
                        'hsw-checkout',
                        HSW_URL . 'assets/js/hsw-checkout.js',
                        array(),
                        HSW_VERSION,
                        true
                );

                wp_localize_script(
                        'hsw-checkout',
                        'hswCheckoutData',
                        array(
                                'rest'        => esc_url_raw( rest_url( 'hsw/v1' ) ),
                                'nonce'       => wp_create_nonce( 'wp_rest' ),
                                'errorText'   => __( 'خطا در بروزرسانی روش ارسال', 'hamishop-elementor-widgets' ),
                                'checkoutUrl' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
                        )
                );

                wp_register_script(
                        'hsw-product',
                        HSW_URL . 'assets/js/hsw-product.js',
                        array(),
                        HSW_VERSION,
                        true
                );

                wp_localize_script(
                        'hsw-loop',
                        'hswData',
                        array(
                                'wcAjax'    => class_exists( 'WooCommerce' ) ? WC_AJAX::get_endpoint( 'add_to_cart' ) : '/?wc-ajax=add_to_cart',
                                'addedText' => __( 'به سبد خرید اضافه شد ✓', 'hamishop-elementor-widgets' ),
                        )
                );

                wp_localize_script(
                        'hsw-product',
                        'hswProductData',
                        array(
                                'wcAjax'      => class_exists( 'WooCommerce' ) ? WC_AJAX::get_endpoint( 'add_to_cart' ) : '/?wc-ajax=add_to_cart',
                                'cartUrl'     => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
                                'checkoutUrl' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
                                'faDigits'    => true,
                        )
                );

                wp_localize_script(
                        'hsw-archive',
                        'hswArchiveData',
                        array(
                                'wcAjax'   => class_exists( 'WooCommerce' ) ? WC_AJAX::get_endpoint( 'add_to_cart' ) : '/?wc-ajax=add_to_cart',
                                'addedText' => __( 'به سبد خرید اضافه شد ✓', 'hamishop-elementor-widgets' ),
                                'currency' => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '',
                        )
                );
        }

        /**
         * Editor placeholder tweak.
         */
        public function editor_style() {
                wp_add_inline_style(
                        'wp-admin',
                        '.elementor-widget-hsw-wonder-deals .elementor-widget-container:before{content:"";display:block;height:0}'
                );
        }

        /**
         * Font group shown in Elementor typography controls.
         */
        public function font_groups( $groups ) {
                $groups['hamishop-fonts'] = __( 'HamiShop (لوکال)', 'hamishop-elementor-widgets' );
                return $groups;
        }

        /**
         * Locally-bundled fonts exposed to every Elementor typography control.
         */
        public function additional_fonts( $fonts ) {
                $fonts['Vazirmatn HSW'] = 'hamishop-fonts';
                return $fonts;
        }
}
