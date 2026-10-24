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

                HSW_Account::instance();

                new HSW_Widget_Manager();
                HSW_Palette::instance();
                HSW_Branding::instance();
                HSW_Security::instance();
                HSW_Cache_Guard::instance();
                HSW_Throttle::instance();
                HSW_Wallet::instance();
                new HSW_Wallet_Topup();
                new HSW_Free_Shipping();
                new HSW_404();
                new HSW_Pagination();
                HSW_Coupon_Messages::instance();

                /* One SVG sprite per page, containing only the icons it rendered. */
                add_action( 'wp_footer', array( 'HSW_Helpers', 'print_sprite' ), 5 );
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
                add_action( 'init', array( $this, 'maybe_flush_element_cache' ) );

                /*
                 * Tell WooCommerce this plugin is HPOS-safe. Every order is read
                 * through `wc_get_order()` and its getters — there is no direct
                 * postmeta access — so the declaration is accurate. Without it
                 * WooCommerce reports the plugin as "uncertain" and can refuse to
                 * enable the high-performance order tables on the store.
                 */
                add_action( 'before_woocommerce_init', array( $this, 'declare_woocommerce_compatibility' ) );
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
         * Drop Elementor's cached element HTML whenever the plugin version moves.
         *
         * Elementor stores the finished HTML of every element in
         * `_elementor_element_cache` and, on a cache hit, skips the render pass
         * entirely — including `elementor/widget/render_content`, which is where
         * the icon-name guard lives. Without this flush an updated plugin keeps
         * serving yesterday's markup, so a fixed icon (or any other template
         * change) would never appear on an existing page.
         *
         * Runs once per version. The work is sliced so a huge postmeta table is
         * never locked by one long DELETE, but the flush completes inside a single
         * request and the marker always moves afterwards.
         *
         * Spreading the slices across requests does NOT work: Elementor re-caches
         * every element it renders, so the next request finds a freshly populated
         * table, deletes it again, and the marker never reaches the current
         * version — the element cache would stay permanently disabled.
         */
        public function maybe_flush_element_cache() {
                if ( HSW_VERSION === get_option( 'hsw_asset_version' ) ) {
                        return;
                }

                global $wpdb;

                $started = microtime( true );
                $slices  = 0;
                $deleted = 0;

                do {
                        $deleted = $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_element_cache' LIMIT 1000" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                        $slices++;
                } while (
                        is_int( $deleted ) && $deleted > 0
                        && $slices < 50
                        && ( microtime( true ) - $started ) < 5
                );

                /* A failed query leaves the marker alone so the next request retries. */
                if ( false !== $deleted ) {
                        update_option( 'hsw_asset_version', HSW_VERSION, false );
                }
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
                require_once HSW_DIR . 'includes/trait-hsw-style-kit.php';
                require_once HSW_DIR . 'includes/class-hsw-branding.php';
                require_once HSW_DIR . 'includes/class-hsw-security.php';
                require_once HSW_DIR . 'includes/class-hsw-cache-guard.php';
                require_once HSW_DIR . 'includes/class-hsw-throttle.php';
                require_once HSW_DIR . 'includes/class-hsw-wallet.php';
                require_once HSW_DIR . 'includes/class-hsw-wallet-pay.php';
                require_once HSW_DIR . 'includes/class-hsw-free-shipping.php';
                require_once HSW_DIR . 'includes/class-hsw-404.php';
                require_once HSW_DIR . 'includes/class-hsw-pagination.php';
                require_once HSW_DIR . 'includes/class-hsw-coupon-messages.php';
                require_once HSW_DIR . 'includes/class-hsw-composite.php';
                require_once HSW_DIR . 'includes/class-hsw-products.php';
                require_once HSW_DIR . 'includes/class-hsw-rest.php';
                require_once HSW_DIR . 'includes/class-hsw-assets.php';
                require_once HSW_DIR . 'includes/class-hsw-archive-query.php';
                require_once HSW_DIR . 'includes/class-hsw-cart-actions.php';
                require_once HSW_DIR . 'includes/class-hsw-checkout-rest.php';
                require_once HSW_DIR . 'includes/class-hsw-checkout-complete.php';
                require_once HSW_DIR . 'includes/class-hsw-complete-route.php';
                require_once HSW_DIR . 'includes/class-hsw-account.php';
                require_once HSW_DIR . 'includes/class-hsw-email-verify.php';
                require_once HSW_DIR . 'includes/class-hsw-account-auth.php';
                require_once HSW_DIR . 'includes/class-hsw-account-support.php';
                require_once HSW_DIR . 'includes/class-hsw-account-admin.php';
                require_once HSW_DIR . 'includes/class-hsw-widget-manager.php';
                require_once HSW_DIR . 'includes/class-hsw-palette.php';

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

                /*
                 * Inject the family palette into every HSW widget right after
                 * its first section. This keeps Elementor's Content tab first
                 * and gives all widget families the same Style-tab palette.
                 */
                if ( class_exists( '\HSW_Widget_Base' ) ) {
                        add_action( 'elementor/element/after_section_end', array( '\HSW_Widget_Base', 'maybe_add_palette' ), 10, 3 );
                }

                /* One broken widget file must never white-screen the site. */
                $widget_files = array(
                        HSW_DIR . 'widgets/class-hsw-widget-header-store.php',
                        HSW_DIR . 'widgets/class-hsw-widget-header-minimal.php',

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
