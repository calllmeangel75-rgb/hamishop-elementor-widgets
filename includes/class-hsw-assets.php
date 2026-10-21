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
                add_filter( 'elementor/frontend/print_google_fonts', array( $this, 'filter_google_fonts' ) );
                add_action( 'wp', array( $this, 'tweak_theme_a11y' ) );
                add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'editor_style' ) );
                add_filter( 'elementor/fonts/groups', array( $this, 'font_groups' ) );
                add_filter( 'elementor/fonts/additional_fonts', array( $this, 'additional_fonts' ) );
                add_action( 'elementor/element/after_section_end', array( $this, 'append_common_section' ), 10, 3 );
        }

        /**
         * Whether the current request is a WooCommerce product archive screen
         * handled by the HSW archive widgets.
         */
        private function is_archive_screen() {
                return function_exists( 'is_shop' ) && ( is_shop() || is_product_category() || is_product_tag() );
        }

        /**
         * Keep the archive page free of the theme's accessibility blockers,
         * with surgical overrides that never leak to other screens:
         * - scalable viewport (Woodmart's default `user-scalable=no` fails audits),
         * - a single valid skip link (the nav one points to a hidden target on mobile),
         * - sequential heading levels for the footer info boxes,
         * - readable footer text (Woodmart's #767676 is 4.23:1 on light surfaces).
         */
        public function tweak_theme_a11y() {
                if ( ! $this->is_archive_screen() ) {
                        return;
                }
                if ( function_exists( 'woodmart_meta_viewport' ) ) {
                        remove_action( 'wp_head', 'woodmart_meta_viewport', 10 );
                        add_action( 'wp_head', array( $this, 'print_viewport' ), 10 );
                }
                if ( function_exists( 'woodmart_get_skip_main_content_button' ) ) {
                        add_action( 'wp_body_open', array( $this, 'replace_skip_links' ), 1 );
                }
                add_filter( 'elementor/widget/render_content', array( $this, 'footer_info_heading' ), 10, 2 );
        }

        /**
         * Scalable viewport for archive pages.
         */
        public function print_viewport() {
                echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
        }

        /**
         * Replace the theme skip links with the one link whose target really
         * exists and is visible on every breakpoint (`#main-content`).
         */
        public function replace_skip_links() {
                remove_action( 'wp_body_open', 'woodmart_get_skip_main_content_button', 10 );
                echo '<div class="wd-skip-links wd-deferred"><a href="#main-content" class="wd-skip-content btn">' . esc_html__( 'پرش به محتوای اصلی', 'hamishop-elementor-widgets' ) . '</a></div>';
        }

        /**
         * Woodmart renders footer info-box titles as `h4`, which breaks the
         * heading order after the archive `h2` sections. Render them as `h3`;
         * the visual size comes from the `wd-fontsize-*` class, not the tag.
         *
         * @param string $content Widget HTML.
         * @param mixed  $widget  Widget instance.
         * @return string
         */
        public function footer_info_heading( $content, $widget ) {
                if ( ! $this->is_archive_screen() || ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) ) {
                        return $content;
                }
                if ( 'wd_infobox' !== $widget->get_name() || false === strpos( $content, 'info-box-title' ) ) {
                        return $content;
                }
                $count  = 0;
                $content = preg_replace( '/<h4(\s[^>]*class="[^"]*info-box-title[^"]*")/', '<h3$1', $content, 1, $count );
                if ( $count ) {
                        $content = preg_replace( '/<\/h4>/', '</h3>', $content, 1 );
                }
                return $content;
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
                        'hsw-header',
                        HSW_URL . 'assets/css/hsw-header.css',
                        array( 'hsw-fonts' ),
                        HSW_VERSION
                );

                wp_register_script(
                        'hsw-header',
                        HSW_URL . 'assets/js/hsw-header.js',
                        array(),
                        HSW_VERSION,
                        true
                );

                wp_register_style(
                        'hsw-composite',
                        HSW_URL . 'assets/css/hsw-composite.css',
                        array( 'hsw-fonts' ),
                        HSW_VERSION
                );

                wp_register_style(
                        'hsw-blog',
                        HSW_URL . 'assets/css/hsw-blog.css',
                        array( 'hsw-fonts' ),
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

                wp_register_style(
                        'hsw-complete',
                        HSW_URL . 'assets/css/hsw-complete.css',
                        array( 'hsw-checkout' ),
                        HSW_VERSION
                );

                wp_register_script(
                        'hsw-checkout',
                        HSW_URL . 'assets/js/hsw-checkout.js',
                        array(),
                        HSW_VERSION,
                        true
                );

                wp_register_style(
                        'hsw-account',
                        HSW_URL . 'assets/css/hsw-account.css',
                        array( 'hsw-fonts' ),
                        HSW_VERSION
                );

                wp_register_script(
                        'hsw-account',
                        HSW_URL . 'assets/js/hsw-account.js',
                        array(),
                        HSW_VERSION,
                        true
                );

                wp_localize_script(
                        'hsw-account',
                        'hswAccountData',
                        array(
                                'rest'         => esc_url_raw( rest_url( 'hsw/v1' ) ),
                                'nonce'        => wp_create_nonce( 'wp_rest' ),
                                'faDigits'     => true,
                                'errorText'    => __( 'خطا در بارگذاری علاقه‌مندی‌ها', 'hamishop-elementor-widgets' ),
                                'retryText'    => __( 'اتصال را بررسی کنید و صفحه را دوباره باز کنید.', 'hamishop-elementor-widgets' ),
                                'clearConfirm' => __( 'همهٔ محصولات از علاقه‌مندی‌ها حذف شوند؟', 'hamishop-elementor-widgets' ),
                                'showPassword' => __( 'نمایش رمز عبور', 'hamishop-elementor-widgets' ),
                                'hidePassword' => __( 'پنهان کردن رمز عبور', 'hamishop-elementor-widgets' ),
                                'ajax'         => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
                                'otpNonce'     => wp_create_nonce( 'hsw_otp' ),
                                'twoFactorNonce' => wp_create_nonce( 'hsw_2fa' ),
                                'phoneRequired' => __( 'شماره موبایل را وارد کنید.', 'hamishop-elementor-widgets' ),
                                'channelRequired' => __( 'روش دریافت کد را انتخاب کنید.', 'hamishop-elementor-widgets' ),
                                'codeRequired' => __( 'کد تأیید را وارد کنید.', 'hamishop-elementor-widgets' ),
                                'sending'      => __( 'در حال ارسال کد...', 'hamishop-elementor-widgets' ),
                                'verifying'    => __( 'در حال بررسی کد...', 'hamishop-elementor-widgets' ),
                                'sent'         => __( 'کد تأیید ارسال شد.', 'hamishop-elementor-widgets' ),
                                'verified'     => __( 'تأیید شد.', 'hamishop-elementor-widgets' ),
                                'sendError'    => __( 'ارسال کد ناموفق بود.', 'hamishop-elementor-widgets' ),
                                'verifyError'  => __( 'کد تأیید نامعتبر است.', 'hamishop-elementor-widgets' ),
                                'networkError' => __( 'خطا در ارتباط با سرور. دوباره تلاش کنید.', 'hamishop-elementor-widgets' ),
                                'openLink'     => __( 'باز کردن لینک', 'hamishop-elementor-widgets' ),
                        )
                );

                wp_localize_script(
                        'hsw-checkout',
                        'hswCheckoutData',
                        array(
                                'rest'        => esc_url_raw( rest_url( 'hsw/v1' ) ),
                                'nonce'       => wp_create_nonce( 'wp_rest' ),
                                'errorText'   => __( 'خطا در بروزرسانی روش ارسال', 'hamishop-elementor-widgets' ),
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
         * Product archives must make zero third-party font requests. Elementor
         * prints its global kit fonts (often Roboto/Roboto Slab) even though the
         * archive widgets use the locally bundled Vazirmatn. Keep the kit fonts
         * on other screens, but suppress the remote Google Fonts request on the
         * WooCommerce archive screens this plugin powers.
         *
         * @param bool $print Whether Elementor should print Google Fonts.
         * @return bool
         */
        public function filter_google_fonts( $print ) {
                if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_category() || is_product_tag() ) ) {
                        return false;
                }
                if ( function_exists( 'is_account_page' ) && is_account_page() ) {
                        return false;
                }
                return $print;
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
