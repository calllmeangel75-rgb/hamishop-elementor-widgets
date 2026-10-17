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
         * Register the loop widgets.
         */
        public function register_widgets( $widgets_manager ) {
                require_once HSW_DIR . 'widgets/class-hsw-wonder-deals.php';
                require_once HSW_DIR . 'widgets/class-hsw-best-sellers.php';
                require_once HSW_DIR . 'widgets/class-hsw-popular.php';

                $widgets_manager->register( new \HSW_Widget_Wonder_Deals() );
                $widgets_manager->register( new \HSW_Widget_Best_Sellers() );
                $widgets_manager->register( new \HSW_Widget_Popular() );
        }
}
