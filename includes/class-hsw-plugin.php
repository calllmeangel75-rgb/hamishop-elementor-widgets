<?php
/**
 * Core plugin bootstrapper.
 *
 * @package HamiShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main (and only) plugin instance.
 */
final class HSW_Plugin {

        /**
         * Singleton instance.
         *
         * @var HSW_Plugin|null
         */
        private static $instance = null;

        /**
         * Get singleton instance.
         *
         * @return HSW_Plugin
         */
        public static function instance() {
                if ( null === self::$instance ) {
                        self::$instance = new self();
                }
                return self::$instance;
        }

        /**
         * Constructor. Hooks everything at the earliest safe moment.
         */
        private function __construct() {
                add_action( 'init', [ $this, 'load_textdomain' ], 1 );
                add_action( 'plugins_loaded', [ $this, 'boot' ], 20 );
        }

        /**
         * Load translations.
         *
         * @return void
         */
        public function load_textdomain() {
                load_plugin_textdomain(
                        'hamishop-elementor-widgets',
                        false,
                        dirname( HSW_PLUGIN_BASENAME ) . '/languages'
                );
        }

        /**
         * Boot the plugin only when WooCommerce + Elementor exist.
         *
         * @return void
         */
        public function boot() {
                require_once HSW_PLUGIN_DIR . 'includes/class-hsw-dependencies.php';

                if ( ! HSW_Dependencies::passes() ) {
                        add_action( 'admin_notices', [ 'HSW_Dependencies', 'missing_notice' ] );
                        return;
                }

                // Core classes.
                require_once HSW_PLUGIN_DIR . 'includes/class-hsw-helpers.php';
                require_once HSW_PLUGIN_DIR . 'includes/class-hsw-card-renderer.php';
                require_once HSW_PLUGIN_DIR . 'includes/class-hsw-widgets-manager.php';
                require_once HSW_PLUGIN_DIR . 'includes/class-hsw-assets.php';
                require_once HSW_PLUGIN_DIR . 'includes/class-hsw-rest.php';
                require_once HSW_PLUGIN_DIR . 'includes/class-hsw-settings.php';
                require_once HSW_PLUGIN_DIR . 'includes/controls/class-hsw-control-product-chooser.php';

                HSW_Settings::init();
                HSW_Assets::init();
                HSW_Rest::init();

                add_action( 'elementor/elements/categories_registered', [ $this, 'register_category' ] );
                add_action( 'elementor/controls/register', [ $this, 'register_controls' ] );
                add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );

                // Editor helper: async product search for the hsw_products control.
                add_action( 'wp_ajax_hsw_search_products', [ $this, 'ajax_search_products' ] );

                // Optional performance tweak: remove the heavy WooCommerce block library CSS.
                add_action( 'wp_enqueue_scripts', [ $this, 'maybe_remove_wc_blocks_css' ], 99 );
        }

        /**
         * AJAX product search for the product-chooser control (editor only).
         *
         * @return void
         */
        public function ajax_search_products() {
                check_ajax_referer( 'hsw-editor', 'nonce' );

                if ( ! current_user_can( 'edit_posts' ) ) {
                        wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
                }

                $term    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
                $include = isset( $_GET['include'] ) ? sanitize_text_field( wp_unslash( $_GET['include'] ) ) : '';

                $results = [];

                if ( '' !== $include ) {
                        $ids = array_filter( array_map( 'absint', explode( ',', $include ) ) );
                        foreach ( $ids as $pid ) {
                                $p = wc_get_product( $pid );
                                if ( $p ) {
                                        $results[] = self::product_to_option( $p );
                                }
                        }
                } else {
                        $query = new \WP_Query(
                                [
                                        'post_type'      => 'product',
                                        'post_status'    => 'publish',
                                        'posts_per_page' => 20,
                                        's'              => $term,
                                        'fields'         => 'ids',
                                        'no_found_rows'  => true,
                                ]
                        );

                        foreach ( $query->posts as $pid ) {
                                $p = wc_get_product( $pid );
                                if ( $p ) {
                                        $results[] = self::product_to_option( $p );
                                }
                        }
                }

                wp_send_json_success(
                        [
                                'results'    => $results,
                                'pagination' => [ 'more' => false ],
                        ]
                );
        }

        /**
         * Format a product for the select2 option list.
         *
         * @param \WC_Product $product Product.
         * @return array
         */
        private static function product_to_option( $product ) {
                $sku = $product->get_sku();
                return [
                        'id'   => (string) $product->get_id(),
                        'text' => $product->get_name() . ( $sku ? ' — ' . $sku : '' ),
                ];
        }

        /**
         * Register the "hamishop" Elementor category.
         *
         * @param \Elementor\Elements_Manager $elements_manager Elements manager.
         * @return void
         */
        public function register_category( $elements_manager ) {
                $elements_manager->add_category(
                        'hamishop',
                        [
                                'title' => __( 'HamiShop — WooCommerce', 'hamishop-elementor-widgets' ),
                                'icon'  => 'eicon-cart',
                        ]
                );
        }

        /**
         * Register custom controls.
         *
         * @param \Elementor\Controls_Manager $controls_manager Controls manager.
         * @return void
         */
        public function register_controls( $controls_manager ) {
                $controls_manager->register( new HSW_Control_Product_Chooser() );
        }

        /**
         * Register enabled widgets only (keeps the editor light).
         *
         * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager.
         * @return void
         */
        public function register_widgets( $widgets_manager ) {
                foreach ( HSW_Widgets_Manager::enabled_widgets() as $widget ) {
                        require_once HSW_PLUGIN_DIR . 'widgets/' . $widget['file'];
                        $widgets_manager->register( new $widget['class']() );
                }
        }

        /**
         * Dequeue WooCommerce block-library CSS when enabled in settings.
         *
         * @return void
         */
        public function maybe_remove_wc_blocks_css() {
                if ( 'yes' !== get_option( 'hsw_remove_wc_blocks_css', 'no' ) ) {
                        return;
                }
                if ( ! is_admin() && ! is_cart() && ! is_checkout() && ! is_account_page() ) {
                        wp_dequeue_style( 'wc-blocks-style' );
                        wp_deregister_style( 'wc-blocks-style' );
                }
        }
}
