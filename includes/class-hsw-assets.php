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
		add_filter( 'wp_resource_hints', array( $this, 'resource_hints' ), 10, 2 );
                add_filter( 'elementor/frontend/print_google_fonts', array( $this, 'filter_google_fonts' ) );
                add_action( 'wp', array( $this, 'tweak_theme_a11y' ) );
                add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'editor_style' ) );
                add_filter( 'elementor/fonts/groups', array( $this, 'font_groups' ) );
                add_filter( 'elementor/fonts/additional_fonts', array( $this, 'additional_fonts' ) );
                add_action( 'elementor/element/after_section_end', array( $this, 'append_common_section' ), 10, 3 );
                add_filter( 'elementor/widget/render_content', array( $this, 'guard_icon_markup' ), 20, 2 );
                add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
        }

        /**
         * Typography and ticket styling for our own admin screens.
         *
         * The bundled Vazirmatn is only loaded on the storefront, so wp-admin fell
         * back to whatever the theme/browser picked (Tahoma here). The font files
         * are already in the plugin, so the settings and ticket screens use them
         * too — no extra request on any other admin page.
         */
        public function admin_styles() {
                $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
                if ( ! $screen ) {
                        return;
                }

                $post_type = isset( $screen->post_type ) ? (string) $screen->post_type : '';
                $screen_id = (string) $screen->id;
                $ours      = ( 'hsw_ticket' === $post_type ) || false !== strpos( $screen_id, 'hamishop' );

                if ( ! $ours ) {
                        return;
                }

                wp_enqueue_style( 'hsw-fonts', $this->asset( 'assets/css/hsw-fonts.css' ), array(), HSW_VERSION );

                /*
                 * WordPress ships a locale override — `.locale-fa-ir body {
                 * font-family: Tahoma, Arial, sans-serif }` in common.css — which
                 * outranks a plain `body` selector, so a bare rule here changed
                 * nothing on a Persian install. Matching the same specificity and
                 * marking it important wins regardless of load order.
                 *
                 * `font-family: inherit` on the controls is the other half: inputs
                 * and buttons do not inherit the body font on their own. The icon
                 * font (`.hsw-ic`) is unaffected — it carries its own
                 * `!important` family on a class selector.
                 */
                wp_add_inline_style(
                        'hsw-fonts',
                        "body,body.locale-fa-ir,body.wp-admin{font-family:'Vazirmatn HSW',Vazirmatn,Tahoma,sans-serif!important}"
                        . "input,select,textarea,button,.button,.wp-core-ui .button{font-family:inherit}"
                );

                if ( 'hsw_ticket' === $post_type ) {
                        wp_enqueue_style(
                                'hsw-admin-tickets',
                                $this->asset( 'assets/css/hsw-admin-tickets.css' ),
                                array( 'hsw-fonts' ),
                                HSW_VERSION
                        );
                }
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
         * Last line of defence: validate every `.hsw-ic` name in the final markup.
         *
         * `HSW_Helpers::icon()` already refuses unknown names, but roughly 120
         * places write the span by hand (`<?php echo HSW_Helpers::icon( 'delete_sweep' ); ?>`).
         * Those bypass the helper, so a typo — or an icon that was never added to
         * the font subset — would paint the word itself into the page. Running
         * the name through `HSW_Helpers::icon_name()` here means no widget can
         * ever emit English icon text, however the markup was written.
         *
         * @param string $content Widget HTML.
         * @param mixed  $widget  Widget instance.
         * @return string
         */
        public function guard_icon_markup( $content, $widget ) {
                if ( ! is_string( $content ) || false === strpos( $content, 'hsw-ic' ) ) {
                        return $content;
                }

                $fixed = preg_replace_callback(
                        '/(<(?:i|span)\b[^>]*\bclass="[^"]*\bhsw-ic\b[^"]*"[^>]*>)([a-z0-9_\-]+)(<\/(?:i|span)>)/i',
                        static function ( $m ) {
                                return $m[1] . esc_html( HSW_Helpers::icon_name( $m[2] ) ) . $m[3];
                        },
                        $content
                );

                return is_string( $fixed ) ? $fixed : $content;
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
                                'default' => 'palette',
                                'options' => array(
                                        'palette' => __( 'فونت پالت (سراسری)', 'hamishop-elementor-widgets' ),
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
         * Warm the gravatar connection: author boxes and comments load avatars
         * from secure.gravatar.com, and Lighthouse flagged the extra handshake.
         *
         * @param array  $urls          Hint URLs.
         * @param string $relation_type Relation type.
         * @return array
         */
        public function resource_hints( $urls, $relation_type ) {
                if ( 'preconnect' === $relation_type && ! in_array( 'https://secure.gravatar.com', $urls, true ) ) {
                        $urls[] = 'https://secure.gravatar.com';
                }
                return $urls;
        }

        /**
         * Asset URL, preferring the minified build.
         *
         * tools/build-assets.py writes `.min.css` / `.min.js` next to every
         * source. They are used unless SCRIPT_DEBUG is on, so development keeps
         * readable files while production downloads 26–38% less.
         *
         * @param string $rel Path relative to the plugin root.
         * @return string
         */
        private function asset( $rel ) {
                $min = preg_replace( '/\.(css|js)$/', '.min.$1', $rel );

                if ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) && $min && file_exists( HSW_DIR . $min ) ) {
                        return HSW_URL . $min;
                }

                return HSW_URL . $rel;
        }

        /**
         * Where the add-to-cart modal is allowed to open.
         *
         * The modal is a strong pattern on a listing (the visitor is browsing
         * and "continue shopping" keeps them there) and a nuisance on a product
         * page, where the visitor is already looking at the product and the
         * modal covers the gallery they may still be reading. So the archive
         * gets it by default and the product page is opt-in.
         *
         * @return array{archive:bool,product:bool}
         */
        public static function feedback_settings() {
                $saved = get_option( 'hsw_add_feedback', array() );
                $saved = is_array( $saved ) ? $saved : array();

                /*
                 * Three explicit states per context: modal / bar / off. The old
                 * pair of checkboxes plus one global style select could not say
                 * "bar in the archive, nothing on the product page".
                 */
                $modes = array( 'modal', 'bar', 'off' );

                $archive = isset( $saved['archive'] ) ? (string) $saved['archive'] : '';
                if ( ! in_array( $archive, $modes, true ) ) {
                        /* Legacy: modal_archive=yes + style=modal|toast. */
                        $legacy_on = ! isset( $saved['modal_archive'] ) ? true : ( 'yes' === $saved['modal_archive'] );
                        $legacy_st = ( isset( $saved['style'] ) && 'toast' === $saved['style'] ) ? 'bar' : 'modal';
                        $archive   = $legacy_on ? $legacy_st : 'off';
                }

                $product = isset( $saved['product'] ) ? (string) $saved['product'] : '';
                if ( ! in_array( $product, $modes, true ) ) {
                        $product = ( isset( $saved['modal_product'] ) && 'yes' === $saved['modal_product'] ) ? 'modal' : 'off';
                }

                return array(
                        'archive'       => 'off' !== $archive,
                        'product'       => 'off' !== $product,
                        'archive_style' => $archive,
                        'product_style' => $product,
                        'style'         => 'bar' === $archive ? 'toast' : 'modal',
                );
        }

        /**
         * Register (not enqueue) — Elementor enqueues widget dependencies automatically.
         */
        public function register() {
                wp_register_style(
                        'hsw-fonts',
                        $this->asset( 'assets/css/hsw-fonts.css' ),
                        array(),
                        HSW_VERSION
                );


                wp_register_style(
                        'hsw-header',
                        $this->asset( 'assets/css/hsw-header.css' ),
                        array( 'hsw-fonts' ),
                        HSW_VERSION
                );

                wp_register_script(
                        'hsw-header',
                        $this->asset( 'assets/js/hsw-header.js' ),
                        array(),
                        HSW_VERSION,
                        true
                );

                wp_register_style(
                        'hsw-composite',
                        $this->asset( 'assets/css/hsw-composite.css' ),
                        array( 'hsw-fonts' ),
                        HSW_VERSION
                );

                wp_register_style(
                        'hsw-blog',
                        $this->asset( 'assets/css/hsw-blog.css' ),
                        array( 'hsw-fonts' ),
                        HSW_VERSION
                );

                wp_register_style(
                        'hsw-post',
                        $this->asset( 'assets/css/hsw-post.css' ),
                        array( 'hsw-fonts' ),
                        HSW_VERSION
                );

                wp_register_script(
                        'hsw-post',
                        $this->asset( 'assets/js/hsw-post.js' ),
                        array(),
                        HSW_VERSION,
                        true
                );

                /*
                 * Shared add-to-cart feedback (toast + cart modal). It is only a
                 * dependency of the families that add to the cart from a
                 * listing, so a page without them never downloads it.
                 */
                wp_register_script(
                        'hsw-feedback',
                        $this->asset( 'assets/js/hsw-feedback.js' ),
                        array(),
                        HSW_VERSION,
                        true
                );

                wp_register_style(
                        'hsw-loop',
                        $this->asset( 'assets/css/hsw-loop.css' ),
                        array( 'hsw-fonts' ),
                        HSW_VERSION
                );

                wp_register_script(
                        'hsw-loop',
                        $this->asset( 'assets/js/hsw-loop.js' ),
                        array( 'hsw-feedback' ),
                        HSW_VERSION,
                        true
                );

                wp_register_style(
                        'hsw-product',
                        $this->asset( 'assets/css/hsw-product.css' ),
                        array( 'hsw-fonts' ),
                        HSW_VERSION
                );

                wp_register_style(
                        'hsw-archive',
                        $this->asset( 'assets/css/hsw-archive.css' ),
                        array( 'hsw-fonts' ),
                        HSW_VERSION
                );

                wp_register_script(
                        'hsw-archive',
                        $this->asset( 'assets/js/hsw-archive.js' ),
                        array( 'hsw-feedback' ),
                        HSW_VERSION,
                        true
                );

                wp_register_style(
                        'hsw-footer',
                        $this->asset( 'assets/css/hsw-footer.css' ),
                        array( 'hsw-fonts' ),
                        HSW_VERSION
                );

                wp_register_style(
                        'hsw-cart',
                        $this->asset( 'assets/css/hsw-cart.css' ),
                        array( 'hsw-fonts' ),
                        HSW_VERSION
                );

                wp_register_script(
                        'hsw-cart',
                        $this->asset( 'assets/js/hsw-cart.js' ),
                        array(),
                        HSW_VERSION,
                        true
                );

                wp_register_style(
                        'hsw-checkout',
                        $this->asset( 'assets/css/hsw-checkout.css' ),
                        array( 'hsw-fonts' ),
                        HSW_VERSION
                );

                wp_register_style(
                        'hsw-complete',
                        $this->asset( 'assets/css/hsw-complete.css' ),
                        array( 'hsw-checkout' ),
                        HSW_VERSION
                );

                wp_register_script(
                        'hsw-checkout',
                        $this->asset( 'assets/js/hsw-checkout.js' ),
                        array(),
                        HSW_VERSION,
                        true
                );

                wp_register_style(
                        'hsw-account',
                        $this->asset( 'assets/css/hsw-account.css' ),
                        array( 'hsw-fonts' ),
                        HSW_VERSION
                );

                wp_register_script(
                        'hsw-account',
                        $this->asset( 'assets/js/hsw-account.js' ),
                        array(),
                        HSW_VERSION,
                        true
                );

                wp_add_inline_script(
                        'hsw-account',
                        'window.hswAccountData=' . wp_json_encode( array(
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
                                'emailNonce'   => wp_create_nonce( 'hsw_email_verify' ),
                                'ticketNonce'  => wp_create_nonce( 'hsw_ticket_ajax' ),
                                'ticketCreated' => __( 'تیکت شما ثبت شد.', 'hamishop-elementor-widgets' ),
                                'ticketEmpty'  => __( 'موضوع و متن پیام را کامل کنید.', 'hamishop-elementor-widgets' ),
                                'captchaWrong' => __( 'جواب سؤال امنیتی درست نیست.', 'hamishop-elementor-widgets' ),
                                'replyEmpty'   => __( 'متن پیام را بنویسید.', 'hamishop-elementor-widgets' ),
                                'closeConfirm' => __( 'این گفتگو بسته شود؟ بعد از بستن نمی‌توانید پیام بفرستید.', 'hamishop-elementor-widgets' ),
                                'imageTooBig'  => __( 'عکس خیلی بزرگ است. یک عکس دیگر انتخاب کنید.', 'hamishop-elementor-widgets' ),
                                'noOrders'     => __( 'سفارشی پیدا نشد.', 'hamishop-elementor-widgets' ),
                                'emailInvalid' => __( 'ایمیل معتبر وارد کنید.', 'hamishop-elementor-widgets' ),
                                'emailSent'    => __( 'کد تأیید ارسال شد. صندوق ایمیل را بررسی کنید.', 'hamishop-elementor-widgets' ),
                                'emailDone'    => __( 'ایمیل شما تأیید شد.', 'hamishop-elementor-widgets' ),
                                'emailFailed'  => __( 'انجام نشد. دوباره تلاش کنید.', 'hamishop-elementor-widgets' ),
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
                        ) ) . ';',
                        'before'
                );;

                wp_add_inline_script(
                        'hsw-checkout',
                        'window.hswCheckoutData=' . wp_json_encode( array(
                                'rest'        => esc_url_raw( rest_url( 'hsw/v1' ) ),
                                'nonce'       => wp_create_nonce( 'wp_rest' ),
                                'errorText'   => __( 'خطا در بروزرسانی روش ارسال', 'hamishop-elementor-widgets' ),
                                'addressRequired' => __( 'نام، شماره تماس، استان، شهر و نشانی را کامل کنید.', 'hamishop-elementor-widgets' ),
                        ) ) . ';',
                        'before'
                );;

                wp_register_script(
                        'hsw-product',
                        $this->asset( 'assets/js/hsw-product.js' ),
                        array( 'hsw-feedback' ),
                        HSW_VERSION,
                        true
                );

                /*
                 * wp_localize_script() **enqueues** the handle, which pulled
                 * hsw-loop.js into every page of the site. An inline script only
                 * attaches to the handle and prints when the widget enqueues it.
                 */
                $hsw_data = array(
                        'wcAjax'    => class_exists( 'WooCommerce' ) ? WC_AJAX::get_endpoint( 'add_to_cart' ) : '/?wc-ajax=add_to_cart',
                        'addedText' => __( 'به سبد خرید اضافه شد ✓', 'hamishop-elementor-widgets' ),
                        'errorText' => __( 'افزودن به سبد خرید ممکن نشد. دوباره تلاش کنید.', 'hamishop-elementor-widgets' ),
                );
                wp_add_inline_script( 'hsw-loop', 'window.hswData=' . wp_json_encode( $hsw_data ) . ';', 'before' );

                wp_add_inline_script(
                        'hsw-product',
                        'window.hswProductData=' . wp_json_encode( array(
                                'wcAjax'      => class_exists( 'WooCommerce' ) ? WC_AJAX::get_endpoint( 'add_to_cart' ) : '/?wc-ajax=add_to_cart',
                                /* Quantity stepper → real cart line (0 removes it). */
                                'qtyAjax'     => class_exists( 'WooCommerce' ) ? WC_AJAX::get_endpoint( 'hsw_set_qty' ) : '/?wc-ajax=hsw_set_qty',
                                'qtyNonce'    => wp_create_nonce( 'hsw-cart-qty' ),
                                'faDigits'    => true,
                                /* Feedback after a successful add — the product page had none. */
                                'cartUrl'     => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ),
                                'addedText'   => __( 'محصول به سبد خرید اضافه شد', 'hamishop-elementor-widgets' ),
                                'viewCart'    => __( 'مشاهده سبد خرید', 'hamishop-elementor-widgets' ),
                                'continueText' => __( 'ادامه خرید', 'hamishop-elementor-widgets' ),
                                'closeText'    => __( 'بستن', 'hamishop-elementor-widgets' ),
                                'modalTitle'   => __( 'به سبد خرید اضافه شد', 'hamishop-elementor-widgets' ),
                                'modal'        => self::feedback_settings()['product'],
                                'feedbackStyle' => self::feedback_settings()['product_style'],

                                /* Mobile «کالا اضافه شد» bottom sheet. */
                                'relatedUrl'  => esc_url_raw( rest_url( 'hsw/v1/related' ) ),
                                'sheet'       => (bool) apply_filters( 'hsw_product_sheet', true ),
                                'sheetTitle'  => __( 'خریدت رو کامل‌تر کن', 'hamishop-elementor-widgets' ),
                                'sheetAdded'  => __( 'کالا اضافه شد!', 'hamishop-elementor-widgets' ),
                                'sheetCart'   => __( 'برو به سبد خرید', 'hamishop-elementor-widgets' ),
                                'sheetMore'   => __( 'افزودن', 'hamishop-elementor-widgets' ),
                                'sheetDone'   => __( 'افزوده شد', 'hamishop-elementor-widgets' ),
                        ) ) . ';',
                        'before'
                );;

                wp_add_inline_script(
                        'hsw-archive',
                        'window.hswArchiveData=' . wp_json_encode( array(
                                'wcAjax'    => class_exists( 'WooCommerce' ) ? WC_AJAX::get_endpoint( 'add_to_cart' ) : '/?wc-ajax=add_to_cart',
                                'addedText' => __( 'به سبد خرید اضافه شد ✓', 'hamishop-elementor-widgets' ),
                                'errorText' => __( 'افزودن به سبد خرید ممکن نشد. دوباره تلاش کنید.', 'hamishop-elementor-widgets' ),
                                'currency'  => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '',
                                /* Shared feedback module (toast + cart modal). */
                                'cartUrl'      => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ),
                                'viewCart'     => __( 'مشاهده سبد خرید', 'hamishop-elementor-widgets' ),
                                'continueText' => __( 'ادامه خرید', 'hamishop-elementor-widgets' ),
                                'closeText'    => __( 'بستن', 'hamishop-elementor-widgets' ),
                                'modalTitle'   => __( 'به سبد خرید اضافه شد', 'hamishop-elementor-widgets' ),
                                'modal'        => self::feedback_settings()['archive'],
                                'feedbackStyle' => self::feedback_settings()['archive_style'],
                        ) ) . ';',
                        'before'
                );;
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
