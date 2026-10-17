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

                new HSW_Rest();
                new HSW_Assets();

                add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
                add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
        }

        private function includes() {
                /*
                 * ONLY pure WordPress/WooCommerce classes here.
                 * The Elementor base classes (HSW_Widget_Base, HSW_Product_Base)
                 * are loaded lazily inside register_widgets() — loading them any
                 * earlier fatals the whole site when Elementor is not loaded yet
                 * (the classic white-screen-of-death).
                 */
                require_once HSW_DIR . 'includes/class-hsw-helpers.php';
                require_once HSW_DIR . 'includes/class-hsw-products.php';
                require_once HSW_DIR . 'includes/class-hsw-rest.php';
                require_once HSW_DIR . 'includes/class-hsw-assets.php';
                require_once HSW_DIR . 'includes/class-hsw-archive-base.php';
                require_once HSW_DIR . 'includes/class-hsw-archive-query.php';
                require_once HSW_DIR . 'includes/class-hsw-cart-actions.php';
                require_once HSW_DIR . 'includes/class-hsw-checkout-rest.php';

                /* Archive filter/sort query wiring (real WooCommerce params). */
                new HSW_Archive_Query();

                /* Cart form actions (empty cart button from the design). */
                new HSW_Cart_Actions();

                /* Checkout shipping endpoints (choose rate / save note). */
                new HSW_Checkout_Rest();
        }

        /**
         * Elementor categories — one HamiShop brand, organized subcategories.
         */
        public function register_category( $elements_manager ) {
                $categories = array(
                        'hamishop-single'  => __( 'حامی — ویجت‌های صفحه محصول', 'hamishop-elementor-widgets' ),
                        'hamishop-loop'    => __( 'حامی — ویجت‌های لوپ محصولات', 'hamishop-elementor-widgets' ),
                        'hamishop-cart'    => __( 'حامی — ویجت‌های سبد خرید و پرداخت', 'hamishop-elementor-widgets' ),
                        'hamishop-archive' => __( 'حامی — ویجت‌های آرشیو محصولات', 'hamishop-elementor-widgets' ),
                );
                foreach ( $categories as $slug => $title ) {
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

                /* Base classes first — any problem here is caught, not fatal. */
                try {
                        require_once HSW_DIR . 'includes/class-hsw-widget-base.php';
                        require_once HSW_DIR . 'includes/class-hsw-product-base.php';
                } catch ( \Throwable $e ) {
                        hsw_report_fatal( $e, 'base-load' );
                        return;
                }

                /* One broken widget file must never white-screen the site. */
                $widget_files = array(
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

                        HSW_DIR . 'widgets/class-hsw-widget-archive-breadcrumb.php',
                        HSW_DIR . 'widgets/class-hsw-widget-archive-header.php',
                        HSW_DIR . 'widgets/class-hsw-widget-archive-filters.php',
                        HSW_DIR . 'widgets/class-hsw-widget-archive-toolbar.php',
                        HSW_DIR . 'widgets/class-hsw-widget-archive-grid.php',
                        HSW_DIR . 'widgets/class-hsw-widget-archive-pagination.php',
                        HSW_DIR . 'widgets/class-hsw-widget-archive-guide.php',

                        HSW_DIR . 'includes/class-hsw-cart-base.php',
                        HSW_DIR . 'widgets/class-hsw-widget-cart-top.php',
                        HSW_DIR . 'widgets/class-hsw-widget-cart-banner.php',
                        HSW_DIR . 'widgets/class-hsw-widget-cart-items.php',
                        HSW_DIR . 'widgets/class-hsw-widget-cart-coupon.php',
                        HSW_DIR . 'widgets/class-hsw-widget-cart-summary.php',

                        HSW_DIR . 'includes/class-hsw-checkout-base.php',
                        HSW_DIR . 'widgets/class-hsw-widget-checkout-top.php',
                        HSW_DIR . 'widgets/class-hsw-widget-checkout-address.php',
                        HSW_DIR . 'widgets/class-hsw-widget-checkout-shipping.php',
                        HSW_DIR . 'widgets/class-hsw-widget-checkout-schedule.php',
                        HSW_DIR . 'widgets/class-hsw-widget-checkout-note.php',
                        HSW_DIR . 'widgets/class-hsw-widget-checkout-items.php',
                        HSW_DIR . 'widgets/class-hsw-widget-checkout-summary.php',

                        HSW_DIR . 'includes/class-hsw-pay-base.php',
                        HSW_DIR . 'widgets/class-hsw-widget-pay-top.php',
                        HSW_DIR . 'widgets/class-hsw-widget-pay-recall.php',
                        HSW_DIR . 'widgets/class-hsw-widget-pay-methods.php',
                        HSW_DIR . 'widgets/class-hsw-widget-pay-coupon.php',
                        HSW_DIR . 'widgets/class-hsw-widget-pay-summary.php',
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
                        '\HSW_Widget_Archive_Breadcrumb',
                        '\HSW_Widget_Archive_Header',
                        '\HSW_Widget_Archive_Filters',
                        '\HSW_Widget_Archive_Toolbar',
                        '\HSW_Widget_Archive_Grid',
                        '\HSW_Widget_Archive_Pagination',
                        '\HSW_Widget_Archive_Guide',
                        '\HSW_Widget_Cart_Top',
                        '\HSW_Widget_Cart_Banner',
                        '\HSW_Widget_Cart_Items',
                        '\HSW_Widget_Cart_Coupon',
                        '\HSW_Widget_Cart_Summary',
                        '\HSW_Widget_Checkout_Top',
                        '\HSW_Widget_Checkout_Address',
                        '\HSW_Widget_Checkout_Shipping',
                        '\HSW_Widget_Checkout_Schedule',
                        '\HSW_Widget_Checkout_Note',
                        '\HSW_Widget_Checkout_Items',
                        '\HSW_Widget_Checkout_Summary',
                        '\HSW_Widget_Pay_Top',
                        '\HSW_Widget_Pay_Recall',
                        '\HSW_Widget_Pay_Methods',
                        '\HSW_Widget_Pay_Coupon',
                        '\HSW_Widget_Pay_Summary',
                ) as $widget_class ) {
                        if ( ! class_exists( $widget_class ) ) {
                                continue;
                        }
                        try {
                                call_user_func( $register, new $widget_class() );
                        } catch ( \Throwable $e ) {
                                hsw_report_fatal( $e, 'widget-register:' . $widget_class );
                        }
                }
        }
}
