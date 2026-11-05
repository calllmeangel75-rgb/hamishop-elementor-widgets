<?php
/**
 * Plugin orchestrator.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

final class HSW_Plugin {

        /** @var HSW_Plugin|null */
        private static $instance = null;

        /** @var HSW_Products */
        public $products;

        /** @var HSW_Helpers */
        public $helpers;

        public static function instance() {
                if ( null === self::$instance ) {
                        self::$instance = new self();
                }
                return self::$instance;
        }

        private function __construct() {
                $this->includes();

                $this->helpers  = new HSW_Helpers();
                $this->products = new HSW_Products();
                new HSW_Simple_Attributes();
                new HSW_Checkout_Digital();

                HSW_Account::instance();

                new HSW_Widget_Manager();
                HSW_Palette::instance();
                HSW_Branding::instance();
                HSW_Security::instance();
                HSW_Cache_Guard::instance();
                HSW_Optimizer::instance();
                HSW_Throttle::instance();
                HSW_Wallet::instance();
                new HSW_Wallet_Topup();
                new HSW_Wallet_Refunds();
                new HSW_Free_Shipping();
                new HSW_404();
                new HSW_Pagination();
                new HSW_Rebrand();
                new HSW_Request_Blocker();
                new HSW_Login();
                new HSW_Telegram_Poller();
                new HSW_Invoice();
                HSW_Coupon_Messages::instance();

                /* One SVG sprite per page, containing only the icons it rendered. */
                add_action( 'wp_footer', array( 'HSW_Helpers', 'print_sprite' ), 5 );

                /*
                 * On a composite product page the notices belong inside the
                 * widget, where the Style tab can reach them. Remove the
                 * standard callback only after the product query is ready.
                 */
                add_action( 'template_redirect', array( 'HSW_Helpers', 'maybe_defer_product_notices' ), 5 );
                add_action( 'wp_footer', array( 'HSW_Helpers', 'print_deferred_product_notices' ), 99 );
                new HSW_Rest();
                new HSW_Assets();

                add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
                add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
                add_filter( 'woocommerce_product_tabs', array( $this, 'localize_product_tabs' ), 50 );
                /*
                 * Without this the header cart badge never moves after an AJAX
                 * add-to-cart: WooCommerce only returns the fragments a plugin
                 * registers, and ours was never registered.
                 */
                add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'cart_fragments' ) );
                /* Versioned background work (Elementor caches + data migrations). */
                HSW_Upgrader::instance();

                /*
                 * Tell WooCommerce this plugin is HPOS-safe. Every order is read
                 * through `wc_get_order()` and its getters — there is no direct
                 * postmeta access — so the declaration is accurate. Without it
                 * WooCommerce reports the plugin as "uncertain" and can refuse to
                 * enable the high-performance order tables on the store.
                 */
                add_action( 'before_woocommerce_init', array( $this, 'declare_woocommerce_compatibility' ) );

                /* Loop cards use one dedicated, cropped size instead of `full`. */
                add_action( 'after_setup_theme', array( __CLASS__, 'register_image_sizes' ), 20 );

                /*
                 * The "sold" stock bar needs a baseline; record it the first
                 * time a managed product's stock is seen (and raise it on a
                 * restock). A sale never rewrites it.
                 */
                add_action( 'woocommerce_product_set_stock', array( 'HSW_Helpers', 'remember_initial_stock' ), 20 );
                add_action( 'woocommerce_variation_set_stock', array( 'HSW_Helpers', 'remember_initial_stock' ), 20 );
                add_action( 'woocommerce_update_product', array( 'HSW_Helpers', 'remember_initial_stock' ), 20 );

                /* Comment & review submission via admin-ajax.php and admin-post.php (bypasses WAF 403 on wp-comments-post.php). */
                add_action( 'wp_ajax_hsw_submit_comment', array( $this, 'submit_comment' ) );
                add_action( 'wp_ajax_nopriv_hsw_submit_comment', array( $this, 'submit_comment' ) );
                add_action( 'admin_post_hsw_submit_comment', array( $this, 'submit_comment' ) );
                add_action( 'admin_post_nopriv_hsw_submit_comment', array( $this, 'submit_comment' ) );
        }

        /**
         * One-time data migrations, versioned so they never run on every load.
         *
         * v1 — seed `_hsw_initial_stock` for managed products that predate the
         *      "sold" stock bar, so the bar has an honest baseline instead of a
         *      percentage invented from `total_sales`; and drop the loop query
         *      transients once, because their shape changed with the baseline.
         */
        /**
         * Handle post comment & product review submissions via admin-ajax.php and
         * admin-post.php so hosts/WAFs that block direct POST requests to
         * `/wp-comments-post.php` with 403 Forbidden never reject customer reviews
         * or article comments.
         */
        public function submit_comment() {
                $is_ajax = wp_doing_ajax() || ! empty( $_REQUEST['hsw_ajax'] ) || ( isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && 'xmlhttprequest' === strtolower( (string) $_SERVER['HTTP_X_REQUESTED_WITH'] ) );

                $send_error = static function ( $message, $code = 400 ) use ( $is_ajax ) {
                        $clean = trim( wp_strip_all_tags( (string) $message ) );
                        if ( '' === $clean ) {
                                $clean = __( 'ثبت دیدگاه انجام نشد. لطفاً دوباره تلاش کنید.', 'hamishop-elementor-widgets' );
                        }
                        if ( $is_ajax ) {
                                wp_send_json_error( array( 'message' => $clean ), (int) $code );
                        }
                        wp_die(
                                '<p>' . esc_html( $clean ) . '</p>',
                                esc_html__( 'خطا در ثبت دیدگاه', 'hamishop-elementor-widgets' ),
                                array(
                                        'response'  => (int) $code,
                                        'back_link' => true,
                                )
                        );
                };

                if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '' ) ) {
                        $send_error( __( 'درخواست نامعتبر است.', 'hamishop-elementor-widgets' ), 405 );
                }

                $post_id = isset( $_POST['comment_post_ID'] ) ? absint( $_POST['comment_post_ID'] ) : 0;
                $post    = $post_id ? get_post( $post_id ) : null;
                if ( ! $post || ! comments_open( $post_id ) ) {
                        $send_error( __( 'ثبت دیدگاه برای این بخش فعال نیست.', 'hamishop-elementor-widgets' ), 403 );
                }

                $is_product = ( 'product' === $post->post_type );
                if ( $is_product && function_exists( 'wc_review_ratings_enabled' ) && wc_review_ratings_enabled() && function_exists( 'wc_review_ratings_required' ) && wc_review_ratings_required() ) {
                        $rating = isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 0;
                        if ( $rating < 1 || $rating > 5 ) {
                                $send_error( __( 'لطفاً امتیاز خود را (از ۱ تا ۵ ستاره) انتخاب کنید.', 'hamishop-elementor-widgets' ), 400 );
                        }
                }

                $comment_text = isset( $_POST['comment'] ) ? trim( (string) wp_unslash( $_POST['comment'] ) ) : '';
                if ( '' === $comment_text ) {
                        $send_error( __( 'لطفاً متن دیدگاه خود را بنویسید.', 'hamishop-elementor-widgets' ), 400 );
                }

                if ( ! is_user_logged_in() && get_option( 'require_name_email' ) ) {
                        $author = isset( $_POST['author'] ) ? trim( (string) wp_unslash( $_POST['author'] ) ) : '';
                        $email  = isset( $_POST['email'] ) ? trim( (string) wp_unslash( $_POST['email'] ) ) : '';
                        if ( '' === $author || '' === $email ) {
                                $send_error( __( 'لطفاً نام و نشانی ایمیل خود را وارد کنید.', 'hamishop-elementor-widgets' ), 400 );
                        }
                        if ( ! is_email( $email ) ) {
                                $send_error( __( 'لطفاً یک نشانی ایمیل معتبر وارد کنید.', 'hamishop-elementor-widgets' ), 400 );
                        }
                }

                /* Catch any third-party preprocess_comment wp_die() call during AJAX. */
                $die_filter = null;
                if ( $is_ajax ) {
                        $die_filter = static function () use ( $send_error ) {
                                return static function ( $message = '', $title = '', $args = array() ) use ( $send_error ) {
                                        $code = is_array( $args ) && isset( $args['response'] ) ? (int) $args['response'] : 400;
                                        $send_error( is_scalar( $message ) ? (string) $message : '', $code ? $code : 400 );
                                };
                        };
                        add_filter( 'wp_die_ajax_handler', $die_filter, 999 );
                        add_filter( 'wp_die_handler', $die_filter, 999 );
                }

                $comment = wp_handle_comment_submission( wp_unslash( $_POST ) );

                if ( $die_filter ) {
                        remove_filter( 'wp_die_ajax_handler', $die_filter, 999 );
                        remove_filter( 'wp_die_handler', $die_filter, 999 );
                }

                if ( is_wp_error( $comment ) ) {
                        $err_code = (string) $comment->get_error_code();
                        $err_map  = array(
                                'comment_duplicate'     => __( 'دیدگاه تکراری شناسایی شد؛ به نظر می‌رسد این دیدگاه را قبلاً ارسال کرده‌اید.', 'hamishop-elementor-widgets' ),
                                'comment_flood'         => __( 'شما با سرعت زیادی در حال ارسال دیدگاه هستید. لطفاً کمی صبر کنید.', 'hamishop-elementor-widgets' ),
                                'require_name_email'    => __( 'لطفاً نام و نشانی ایمیل خود را وارد کنید.', 'hamishop-elementor-widgets' ),
                                'require_valid_email'   => __( 'لطفاً یک نشانی ایمیل معتبر وارد کنید.', 'hamishop-elementor-widgets' ),
                                'require_valid_comment' => __( 'لطفاً متن دیدگاه خود را بنویسید.', 'hamishop-elementor-widgets' ),
                        );
                        $msg = isset( $err_map[ $err_code ] ) ? $err_map[ $err_code ] : $comment->get_error_message();
                        $send_error( $msg, 400 );
                }

                $user            = wp_get_current_user();
                $cookies_consent = ! empty( $_POST['wp-comment-cookies-consent'] );
                do_action( 'set_comment_cookies', $comment, $user, $cookies_consent );

                if ( $is_product && isset( $_POST['rating'] ) ) {
                        $rating = max( 1, min( 5, absint( $_POST['rating'] ) ) );
                        if ( $rating > 0 && '' === (string) get_comment_meta( $comment->comment_ID, 'rating', true ) ) {
                                add_comment_meta( $comment->comment_ID, 'rating', $rating, true );
                                if ( class_exists( 'WC_Comments' ) && method_exists( 'WC_Comments', 'clear_transients' ) ) {
                                        WC_Comments::clear_transients( $post_id );
                                }
                        }
                }

                $approved = ( '1' === (string) $comment->comment_approved );
                $location = empty( $_POST['redirect_to'] ) ? get_comment_link( $comment ) : wp_validate_redirect( wp_unslash( $_POST['redirect_to'] ), get_permalink( $post_id ) );
                if ( ! $approved ) {
                        $location = add_query_arg(
                                array(
                                        'unapproved'      => (int) $comment->comment_ID,
                                        'moderation-hash' => wp_hash( $comment->comment_date_gmt ),
                                ),
                                $location
                        );
                }
                $location = apply_filters( 'comment_post_redirect', $location, $comment );

                if ( $is_ajax ) {
                        $author_name = get_comment_author( $comment );
                        $rating_val  = $is_product ? max( 0, min( 5, (int) get_comment_meta( $comment->comment_ID, 'rating', true ) ) ) : 0;
                        wp_send_json_success(
                                array(
                                        'comment_id' => (int) $comment->comment_ID,
                                        'approved'   => $approved,
                                        'author'     => $author_name,
                                        'date'       => HSW_Helpers::fa_num( get_comment_date( '', $comment ) ),
                                        'content'    => wpautop( esc_html( $comment->comment_content ) ),
                                        'text'       => wp_strip_all_tags( $comment->comment_content ),
                                        'rating'     => $rating_val,
                                        'redirect'   => $location,
                                        'message'    => $approved
                                                ? __( 'دیدگاه شما با موفقیت ثبت شد.', 'hamishop-elementor-widgets' )
                                                : __( 'دیدگاه شما با موفقیت ثبت شد و پس از تأیید مدیر نمایش داده می‌شود.', 'hamishop-elementor-widgets' ),
                                )
                        );
                }

                wp_safe_redirect( $location );
                exit;
        }

        /**
         * One batch of the v1 data migration.
         *
         * v1 — seed `_hsw_initial_stock` for managed products that predate the
         *      "sold" stock bar, so the bar has an honest baseline instead of a
         *      percentage invented from `total_sales`; and drop the loop query
         *      transients once, because their shape changed with the baseline.
         *
         * Called only by HSW_Upgrader, in bounded batches, from a background
         * tick. It used to run from `init` and walked up to 2000 products on the
         * first visitor's request after an update — a single slow query plus
         * 2000 meta writes on a shared host.
         *
         * @param int $after_id Return products with an ID greater than this.
         * @param int $limit    Products per call.
         * @return array{cursor:int,done:bool}
         */
        public static function migrate_batch( $after_id = 0, $limit = 200 ) {
                $target = '1';

                if ( version_compare( (string) get_option( 'hsw_db_version', '0' ), $target, '>=' ) ) {
                        return array(
                                'cursor' => (int) $after_id,
                                'done'   => true,
                        );
                }

                global $wpdb;

                $limit = max( 1, min( 500, (int) $limit ) );

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
                $ids = $wpdb->get_col(
                        $wpdb->prepare(
                                "SELECT p.ID FROM {$wpdb->posts} p
                                 INNER JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = '_manage_stock' AND s.meta_value = 'yes'
                                 LEFT JOIN {$wpdb->postmeta} i ON i.post_id = p.ID AND i.meta_key = '_hsw_initial_stock'
                                 WHERE p.post_type IN ('product','product_variation') AND p.post_status = 'publish'
                                   AND p.ID > %d AND i.meta_id IS NULL
                                 ORDER BY p.ID ASC
                                 LIMIT %d",
                                (int) $after_id,
                                $limit
                        )
                );

                $ids = is_array( $ids ) ? $ids : array();

                foreach ( $ids as $id ) {
                        HSW_Helpers::remember_initial_stock( (int) $id );
                        $after_id = (int) $id;
                }

                $done = count( $ids ) < $limit;

                if ( $done ) {
                        /* Loop ID lists and HTML fragments are rebuilt on next render. */
                        /*
                         * `esc_like()` on purpose, twice over: an unescaped `_` is a
                         * single-character wildcard, so the pattern matched more option names
                         * than intended — and because it started with a wildcard, MySQL could
                         * not use the `option_name` index and scanned the whole table.
                         */
                        $patterns = array(
                        	$wpdb->esc_like( '_transient_hsw_lp_' ) . '%',
                        	$wpdb->esc_like( '_transient_timeout_hsw_lp_' ) . '%',
                        	$wpdb->esc_like( '_transient_hsw_loop_' ) . '%',
                        	$wpdb->esc_like( '_transient_timeout_hsw_loop_' ) . '%',
                        );
                        
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
                        $wpdb->query(
                        	$wpdb->prepare(
                        		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
                        		$patterns[0],
                        		$patterns[1],
                        		$patterns[2],
                        		$patterns[3]
                        	)
                        );

                        update_option( 'hsw_db_version', $target, false );
                }

                return array(
                        'cursor' => (int) $after_id,
                        'done'   => $done,
                );
        }

        /**
         * Dedicated card image size for the three loop widgets.
         */
        public static function register_image_sizes() {
                add_image_size( 'hsw_card', 420, 420, true );
        }


        /**
         * Declare compatibility with WooCommerce features.
         */
        public function declare_woocommerce_compatibility() {
                if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
                        return;
                }
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', HSW_FILE, true );
        }

        /**
         * Keep the native WooCommerce product tabs Persian even when the
         * active theme/Elementor Product Tabs widget prints their default
         * English labels. Our own composite tabs are already Persian; this
         * covers themes that still output the native tab strip elsewhere on
         * the product page.
         *
         * @param array $tabs WooCommerce tabs.
         * @return array
         */
        /**
         * Refresh the header cart badge and subtotal after an AJAX add-to-cart.
         *
         * @param array $fragments Fragment selector => markup.
         * @return array
         */
        public function cart_fragments( $fragments ) {
                if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
                        return $fragments;
                }

                $count = (int) WC()->cart->get_cart_contents_count();
                $total = $count ? WC()->cart->get_cart_subtotal() : __( 'خالی', 'hamishop-elementor-widgets' );

                $fragments['[data-hsw-cart-count]'] = '<span class="hsw-hd__badge" data-hsw-cart-count data-count="' . (int) $count . '">' . esc_html( HSW_Helpers::fa_num( $count ) ) . '</span>';
                $fragments['[data-hsw-cart-total]'] = '<span class="hsw-hd__actionsub hsw-hd__carttotal" data-hsw-cart-total>' . wp_kses_post( $total ) . '</span>';

                /* Header cart sidebar: body + count update in place on AJAX add,
                   quantity change and remove — without reloading the page. */
                if ( class_exists( 'HSW_Cart_Drawer' ) ) {
                        $fragments['[data-hsw-cart-drawer-body]']  = HSW_Cart_Drawer::contents();
                        $fragments['[data-hsw-cart-drawer-count]'] = HSW_Cart_Drawer::count_html();
                }

                return $fragments;
        }

        public function localize_product_tabs( $tabs ) {
                if ( ! is_array( $tabs ) ) {
                        return $tabs;
                }
                $labels = array(
                        'description'            => __( 'توضیحات محصول', 'hamishop-elementor-widgets' ),
                        'additional_information' => __( 'مشخصات فنی', 'hamishop-elementor-widgets' ),
                        'reviews'                => __( 'دیدگاه‌ها و نظرات', 'hamishop-elementor-widgets' ),
                );
                foreach ( $labels as $key => $label ) {
                        if ( isset( $tabs[ $key ] ) && is_array( $tabs[ $key ] ) ) {
                                $tabs[ $key ]['title'] = $label;
                        }
                }
                return $tabs;
        }

        private function includes() {
                /*
                 * ONLY pure WordPress/WooCommerce classes here.
                 * Every class that (indirectly) extends \Elementor\Widget_Base
                 * — HSW_Widget_Base, HSW_Product_Base, HSW_Archive_Base,
                 * HSW_Cart_Base, HSW_Checkout_Base, HSW_Pay_Base — is loaded
                 * LAZILY inside register_widgets(). Loading any of them here
                 * fatals the whole site at plugins_loaded (the classic
                 * white-screen-of-death: "Class HSW_Widget_Base not found").
                 */
                require_once HSW_DIR . 'includes/class-hsw-icon-set.php';
                require_once HSW_DIR . 'includes/class-hsw-helpers.php';
                require_once HSW_DIR . 'includes/class-hsw-header-cascade.php';
                require_once HSW_DIR . 'includes/class-hsw-cart-drawer.php';
                require_once HSW_DIR . 'includes/trait-hsw-style-kit.php';
                require_once HSW_DIR . 'includes/class-hsw-branding.php';
                require_once HSW_DIR . 'includes/class-hsw-security.php';
                require_once HSW_DIR . 'includes/class-hsw-cache-guard.php';
                require_once HSW_DIR . 'includes/class-hsw-optimizer.php';
                require_once HSW_DIR . 'includes/class-hsw-throttle.php';
                require_once HSW_DIR . 'includes/class-hsw-wallet.php';
                require_once HSW_DIR . 'includes/class-hsw-wallet-pay.php';
                require_once HSW_DIR . 'includes/class-hsw-free-shipping.php';
                require_once HSW_DIR . 'includes/class-hsw-shipping-state.php';

                /*
                 * Jalali output is opt-in: the file is tiny, but the filter is
                 * only registered when the toggle is on, so a site that keeps
                 * Gregorian dates runs exactly the same code path as before.
                 */
                require_once HSW_DIR . 'includes/class-hsw-jalali.php';
                if ( HSW_Jalali::is_on() ) {
                        HSW_Jalali::boot();
                }
                require_once HSW_DIR . 'includes/class-hsw-404.php';
                require_once HSW_DIR . 'includes/class-hsw-pagination.php';
                require_once HSW_DIR . 'includes/class-hsw-rebrand.php';
                require_once HSW_DIR . 'includes/class-hsw-request-blocker.php';
                require_once HSW_DIR . 'includes/class-hsw-login.php';
                require_once HSW_DIR . 'includes/class-hsw-telegram-poller.php';
                require_once HSW_DIR . 'includes/class-hsw-invoice.php';
                require_once HSW_DIR . 'includes/class-hsw-coupon-messages.php';
                require_once HSW_DIR . 'includes/class-hsw-composite.php';
                require_once HSW_DIR . 'includes/class-hsw-products.php';
                require_once HSW_DIR . 'includes/class-hsw-simple-attributes.php';
                require_once HSW_DIR . 'includes/class-hsw-checkout-digital.php';
                require_once HSW_DIR . 'includes/class-hsw-rest.php';
                require_once HSW_DIR . 'includes/class-hsw-assets.php';
                require_once HSW_DIR . 'includes/class-hsw-archive-query.php';
                require_once HSW_DIR . 'includes/class-hsw-cart-actions.php';
                require_once HSW_DIR . 'includes/class-hsw-checkout-rest.php';
                require_once HSW_DIR . 'includes/class-hsw-checkout-complete.php';
                require_once HSW_DIR . 'includes/class-hsw-complete-route.php';
                require_once HSW_DIR . 'includes/class-hsw-setup-pages.php';
                require_once HSW_DIR . 'includes/class-hsw-account.php';
                require_once HSW_DIR . 'includes/class-hsw-email-verify.php';
                require_once HSW_DIR . 'includes/class-hsw-account-auth.php';
                require_once HSW_DIR . 'includes/class-hsw-account-support.php';
                require_once HSW_DIR . 'includes/class-hsw-account-admin.php';
                require_once HSW_DIR . 'includes/class-hsw-widget-manager.php';
                require_once HSW_DIR . 'includes/class-hsw-palette.php';
                require_once HSW_DIR . 'includes/class-hsw-upgrader.php';

                /* Archive filter/sort query wiring (real WooCommerce params). */
                new HSW_Archive_Query();

                /* Cart form actions (empty cart button from the design). */
                new HSW_Cart_Actions();

                /* Checkout shipping endpoints (choose rate / save note). */
                new HSW_Checkout_Rest();

                /* Final order placement for the design's own pay button. */
                new HSW_Checkout_Complete();

                /* Completion (thank-you) page routing + WooCommerce setting. */
                new HSW_Complete_Route();

                /* The two route pages: status, one-click creation, noindex. */
                new HSW_Setup_Pages();
        }

        /**
         * Elementor categories — one HamiShop brand, organized subcategories.
         */
        public function register_category( $elements_manager ) {
                $categories = array(
                        'hamishop-header'  => __( 'حامی — هدر و ناوبری', 'hamishop-elementor-widgets' ),
                        'hamishop-single'  => __( 'حامی — ویجت‌های صفحه محصول', 'hamishop-elementor-widgets' ),
                        'hamishop-loop'    => __( 'حامی — ویجت‌های لوپ محصولات', 'hamishop-elementor-widgets' ),
                        'hamishop-cart'    => __( 'حامی — ویجت‌های سبد خرید و پرداخت', 'hamishop-elementor-widgets' ),
                        'hamishop-archive' => __( 'حامی — ویجت‌های آرشیو محصولات', 'hamishop-elementor-widgets' ),
                        'hamishop-account' => __( 'حامی — پنل حساب کاربری', 'hamishop-elementor-widgets' ),
                        'hamishop-blog'    => __( 'حامی — ویجت‌های مجله و وبلاگ', 'hamishop-elementor-widgets' ),
                );
                foreach ( $categories as $slug => $title ) {
                        if ( class_exists( 'HSW_Widget_Manager' ) && ! HSW_Widget_Manager::category_has_enabled( $slug ) ) {
                                continue;
                        }
                        $elements_manager->add_category(
                                $slug,
                                array(
                                        'title' => $title . ' (HamiShop)',
                                        'icon'  => 'eicon-products',
                                )
                        );
                }
        }

        /**
         * Register widgets: product loop + single product page.
         * Fires inside elementor/widgets/register — Elementor classes are
         * guaranteed to exist here, so extending Widget_Base is safe.
         */
        public function register_widgets( $widgets_manager ) {
                if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
                        return; // extra belt-and-braces guard.
                }

                /* Base classes first — any problem here is caught, not fatal.
                 * HSW_Archive_Base MUST load here (not in includes()): it
                 * extends HSW_Widget_Base which only exists once Elementor is
                 * fully booted and register_widgets() has run. */
                try {
                        require_once HSW_DIR . 'includes/class-hsw-widget-base.php';
                        require_once HSW_DIR . 'includes/class-hsw-product-base.php';
                        require_once HSW_DIR . 'includes/class-hsw-archive-base.php';
                        require_once HSW_DIR . 'includes/class-hsw-blog-base.php';
                        require_once HSW_DIR . 'includes/class-hsw-header-base.php';
                } catch ( \Throwable $e ) {
                        hsw_report_fatal( $e, 'base-load' );
                        return;
                }

                /* One broken widget file must never white-screen the site. */
                $widget_files = array(
                        HSW_DIR . 'widgets/class-hsw-widget-header-store.php',
                        HSW_DIR . 'widgets/class-hsw-widget-header-minimal.php',
                        HSW_DIR . 'widgets/class-hsw-widget-header-gc.php',
                        HSW_DIR . 'widgets/class-hsw-widget-footer-sticky.php',

                        HSW_DIR . 'widgets/class-hsw-wonder-deals.php',
                        HSW_DIR . 'widgets/class-hsw-best-sellers.php',
                        HSW_DIR . 'widgets/class-hsw-popular.php',

                        HSW_DIR . 'widgets/class-hsw-widget-product-breadcrumb.php',
                        HSW_DIR . 'widgets/class-hsw-widget-product-gallery.php',
                        HSW_DIR . 'widgets/class-hsw-widget-product-trust.php',
                        HSW_DIR . 'widgets/class-hsw-widget-product-title.php',
                        HSW_DIR . 'widgets/class-hsw-widget-product-variants.php',
                        HSW_DIR . 'widgets/class-hsw-widget-product-features.php',
                        HSW_DIR . 'widgets/class-hsw-widget-product-purchase.php',
                        HSW_DIR . 'widgets/class-hsw-widget-product-tabs.php',
                        HSW_DIR . 'widgets/class-hsw-widget-product-accordions.php',
                        HSW_DIR . 'widgets/class-hsw-widget-product-sticky.php',
                        HSW_DIR . 'widgets/class-hsw-widget-product-related.php',
                        HSW_DIR . 'widgets/class-hsw-widget-product-page.php',

                        HSW_DIR . 'widgets/class-hsw-widget-archive-breadcrumb.php',
                        HSW_DIR . 'widgets/class-hsw-widget-archive-header.php',
                        HSW_DIR . 'widgets/class-hsw-widget-archive-filters.php',
                        HSW_DIR . 'widgets/class-hsw-widget-archive-toolbar.php',
                        HSW_DIR . 'widgets/class-hsw-widget-archive-grid.php',
                        HSW_DIR . 'widgets/class-hsw-widget-archive-pagination.php',
                        HSW_DIR . 'widgets/class-hsw-widget-archive-guide.php',
                        HSW_DIR . 'widgets/class-hsw-widget-archive-page.php',

                        HSW_DIR . 'includes/class-hsw-cart-base.php',
                        HSW_DIR . 'widgets/class-hsw-widget-cart-top.php',
                        HSW_DIR . 'widgets/class-hsw-widget-cart-banner.php',
                        HSW_DIR . 'widgets/class-hsw-widget-cart-items.php',
                        HSW_DIR . 'widgets/class-hsw-widget-cart-coupon.php',
                        HSW_DIR . 'widgets/class-hsw-widget-cart-summary.php',
                        HSW_DIR . 'widgets/class-hsw-widget-cart-page.php',

                        HSW_DIR . 'includes/class-hsw-checkout-base.php',
                        HSW_DIR . 'widgets/class-hsw-widget-checkout-top.php',
                        HSW_DIR . 'widgets/class-hsw-widget-checkout-address.php',
                        HSW_DIR . 'widgets/class-hsw-widget-checkout-shipping.php',
                        HSW_DIR . 'widgets/class-hsw-widget-checkout-schedule.php',
                        HSW_DIR . 'widgets/class-hsw-widget-checkout-note.php',
                        HSW_DIR . 'widgets/class-hsw-widget-checkout-items.php',
                        HSW_DIR . 'widgets/class-hsw-widget-checkout-summary.php',
                        HSW_DIR . 'widgets/class-hsw-widget-checkout-page.php',

                        HSW_DIR . 'includes/class-hsw-pay-base.php',
                        HSW_DIR . 'widgets/class-hsw-widget-pay-top.php',
                        HSW_DIR . 'widgets/class-hsw-widget-pay-recall.php',
                        HSW_DIR . 'widgets/class-hsw-widget-pay-methods.php',
                        HSW_DIR . 'widgets/class-hsw-widget-pay-coupon.php',
                        HSW_DIR . 'widgets/class-hsw-widget-pay-summary.php',
                        HSW_DIR . 'widgets/class-hsw-widget-pay-page.php',

                        HSW_DIR . 'includes/class-hsw-complete-base.php',
                        HSW_DIR . 'widgets/class-hsw-widget-complete-hero.php',
                        HSW_DIR . 'widgets/class-hsw-widget-complete-order.php',
                        HSW_DIR . 'widgets/class-hsw-widget-complete-delivery.php',
                        HSW_DIR . 'widgets/class-hsw-widget-complete-steps.php',
                        HSW_DIR . 'widgets/class-hsw-widget-complete-actions.php',
                        HSW_DIR . 'widgets/class-hsw-widget-complete-page.php',

                        HSW_DIR . 'widgets/class-hsw-widget-account.php',

                        HSW_DIR . 'widgets/class-hsw-widget-blog-hero.php',
                        HSW_DIR . 'widgets/class-hsw-widget-blog-tabs.php',
                        HSW_DIR . 'widgets/class-hsw-widget-blog-featured.php',
                        HSW_DIR . 'widgets/class-hsw-widget-blog-grid.php',
                        HSW_DIR . 'widgets/class-hsw-widget-blog-popular.php',
                        HSW_DIR . 'widgets/class-hsw-widget-blog-newsletter.php',
                        HSW_DIR . 'widgets/class-hsw-widget-blog-tags.php',
                        HSW_DIR . 'widgets/class-hsw-widget-blog-page.php',
                        HSW_DIR . 'widgets/class-hsw-widget-post-page.php',
                );
                foreach ( $widget_files as $widget_file ) {
                        try {
                                require_once $widget_file;
                        } catch ( \Throwable $e ) {
                                hsw_report_fatal( $e, 'widget-load:' . basename( $widget_file ) );
                        }
                }

                /* Elementor 3.1+: register() — older installs: register_widget_type(). */
                if ( method_exists( $widgets_manager, 'register' ) ) {
                        $register = array( $widgets_manager, 'register' );
                } else {
                        $register = array( $widgets_manager, 'register_widget_type' );
                }

                foreach ( array(
                        '\HSW_Widget_Header_Store',
                        '\HSW_Widget_Header_Minimal',
                        '\HSW_Widget_Header_GC',
                        '\HSW_Widget_Footer_Sticky',
                        '\HSW_Widget_Wonder_Deals',
                        '\HSW_Widget_Best_Sellers',
                        '\HSW_Widget_Popular',
                        '\HSW_Widget_Product_Breadcrumb',
                        '\HSW_Widget_Product_Gallery',
                        '\HSW_Widget_Product_Trust',
                        '\HSW_Widget_Product_Title',
                        '\HSW_Widget_Product_Variants',
                        '\HSW_Widget_Product_Features',
                        '\HSW_Widget_Product_Purchase',
                        '\HSW_Widget_Product_Tabs',
                        '\HSW_Widget_Product_Accordions',
                        '\HSW_Widget_Product_Sticky',
                        '\HSW_Widget_Product_Related',
                        '\HSW_Widget_Product_Page',
                        '\HSW_Widget_Archive_Breadcrumb',
                        '\HSW_Widget_Archive_Header',
                        '\HSW_Widget_Archive_Filters',
                        '\HSW_Widget_Archive_Toolbar',
                        '\HSW_Widget_Archive_Grid',
                        '\HSW_Widget_Archive_Pagination',
                        '\HSW_Widget_Archive_Guide',
                        '\HSW_Widget_Archive_Page',
                        '\HSW_Widget_Cart_Top',
                        '\HSW_Widget_Cart_Banner',
                        '\HSW_Widget_Cart_Items',
                        '\HSW_Widget_Cart_Coupon',
                        '\HSW_Widget_Cart_Summary',
                        '\HSW_Widget_Cart_Page',
                        '\HSW_Widget_Checkout_Top',
                        '\HSW_Widget_Checkout_Address',
                        '\HSW_Widget_Checkout_Shipping',
                        '\HSW_Widget_Checkout_Schedule',
                        '\HSW_Widget_Checkout_Note',
                        '\HSW_Widget_Checkout_Items',
                        '\HSW_Widget_Checkout_Summary',
                        '\HSW_Widget_Checkout_Page',
                        '\HSW_Widget_Pay_Top',
                        '\HSW_Widget_Pay_Recall',
                        '\HSW_Widget_Pay_Methods',
                        '\HSW_Widget_Pay_Coupon',
                        '\HSW_Widget_Pay_Summary',
                        '\HSW_Widget_Pay_Page',
                        '\HSW_Widget_Complete_Hero',
                        '\HSW_Widget_Complete_Order',
                        '\HSW_Widget_Complete_Delivery',
                        '\HSW_Widget_Complete_Steps',
                        '\HSW_Widget_Complete_Actions',
                        '\HSW_Widget_Complete_Page',
                        '\HSW_Widget_Account',
                        '\HSW_Widget_Blog_Hero',
                        '\HSW_Widget_Blog_Tabs',
                        '\HSW_Widget_Blog_Featured',
                        '\HSW_Widget_Blog_Grid',
                        '\HSW_Widget_Blog_Popular',
                        '\HSW_Widget_Blog_Newsletter',
                        '\HSW_Widget_Blog_Tags',
                        '\HSW_Widget_Blog_Page',
                        '\HSW_Widget_Post_Page',
                ) as $widget_class ) {
                        if ( ! class_exists( $widget_class ) ) {
                                continue;
                        }
                        try {
                                $widget = new $widget_class();
                                if ( class_exists( 'HSW_Widget_Manager' ) && ! HSW_Widget_Manager::is_enabled( $widget->get_name() ) ) {
                                        continue;
                                }
                                call_user_func( $register, $widget );
                        } catch ( \Throwable $e ) {
                                hsw_report_fatal( $e, 'widget-register:' . $widget_class );
                        }
                }
        }
}
