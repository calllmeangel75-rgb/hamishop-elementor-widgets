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
                require_once HSW_DIR . 'includes/class-hsw-helpers.php';
                require_once HSW_DIR . 'includes/class-hsw-products.php';
                require_once HSW_DIR . 'includes/class-hsw-rest.php';
                require_once HSW_DIR . 'includes/class-hsw-assets.php';
                require_once HSW_DIR . 'includes/class-hsw-widget-base.php';
                require_once HSW_DIR . 'includes/class-hsw-product-base.php';
        }

        /**
         * Elementor category: «HamiShop».
         */
        public function register_category( $elements_manager ) {
                $elements_manager->add_category(
                        'hamishop',
                        array(
                                'title' => __( 'HamiShop — حامی', 'hamishop-elementor-widgets' ),
                                'icon'  => 'eicon-products',
                        )
                );
        }

        /**
         * Register widgets: product loop + single product page.
         */
        public function register_widgets( $widgets_manager ) {
                require_once HSW_DIR . 'widgets/class-hsw-wonder-deals.php';
                require_once HSW_DIR . 'widgets/class-hsw-best-sellers.php';
                require_once HSW_DIR . 'widgets/class-hsw-popular.php';

                require_once HSW_DIR . 'widgets/class-hsw-widget-product-breadcrumb.php';
                require_once HSW_DIR . 'widgets/class-hsw-widget-product-gallery.php';
                require_once HSW_DIR . 'widgets/class-hsw-widget-product-trust.php';
                require_once HSW_DIR . 'widgets/class-hsw-widget-product-title.php';
                require_once HSW_DIR . 'widgets/class-hsw-widget-product-variants.php';
                require_once HSW_DIR . 'widgets/class-hsw-widget-product-features.php';
                require_once HSW_DIR . 'widgets/class-hsw-widget-product-purchase.php';
                require_once HSW_DIR . 'widgets/class-hsw-widget-product-tabs.php';
                require_once HSW_DIR . 'widgets/class-hsw-widget-product-accordions.php';
                require_once HSW_DIR . 'widgets/class-hsw-widget-product-sticky.php';

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
                ) as $widget_class ) {
                        if ( class_exists( $widget_class ) ) {
                                call_user_func( $register, new $widget_class() );
                        }
                }
        }
}
