<?php
/**
 * Plugin Name: HamiShop Elementor Widgets
 * Plugin URI:  https://hamisys.ir/
 * Description: ویجت‌های فروشگاهی المنتور برای ووکامرس — لوپ محصولات (پیشنهاد شگفت‌انگیز، پرفروش‌ترین‌ها، محبوب‌ترین‌ها) با شخصی‌سازی کامل فونت، رنگ و استایل؛ سبک و بهینه برای Lighthouse 100.
 * Version:     1.1.1
 * Author:      HamiSys
 * Author URI:  https://hamisys.ir/
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hamishop-elementor-widgets
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce, elementor
 * Elementor tested up to: 3.30
 * WC tested up to: 10.0
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

define( 'HSW_VERSION', '1.1.1' );
define( 'HSW_FILE', __FILE__ );
define( 'HSW_DIR', plugin_dir_path( __FILE__ ) );
define( 'HSW_URL', plugin_dir_url( __FILE__ ) );

/**
 * Boot the plugin once WooCommerce + Elementor are available.
 * Guarded against double-declaration when two copies of the plugin are
 * active at once (classic white-screen scenario after a failed update).
 */
if ( ! function_exists( 'hsw_boot' ) ) {
        function hsw_boot() {
                require_once HSW_DIR . 'includes/class-hsw-plugin.php';
                HSW_Plugin::instance();
        }
        add_action( 'plugins_loaded', 'hsw_boot', 20 );
}

/**
 * Admin notice when dependencies are missing.
 */
if ( ! function_exists( 'hsw_missing_deps_notice' ) ) {
        function hsw_missing_deps_notice() {
                if ( ! current_user_can( 'activate_plugins' ) ) {
                        return;
                }
                $missing = array();
                if ( ! class_exists( 'WooCommerce' ) ) {
                        $missing[] = 'WooCommerce';
                }
                if ( ! did_action( 'elementor/loaded' ) ) {
                        $missing[] = 'Elementor';
                }
                if ( empty( $missing ) ) {
                        return;
                }
                printf(
                        '<div class="notice notice-error"><p><strong>HamiShop Elementor Widgets:</strong> %s</p></div>',
                        esc_html( 'برای کار کردن این افزونه، ابتدا این افزونه‌ها را نصب و فعال کنید: ' . implode( ' + ', $missing ) )
                );
        }
        add_action( 'admin_notices', 'hsw_missing_deps_notice' );
}
