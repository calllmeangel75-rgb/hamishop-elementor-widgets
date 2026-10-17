<?php
/**
 * Plugin Name:       HamiShop Elementor Widgets
 * Plugin URI:        https://hamisys.ir/
 * Description:       ویجت‌های فروشگاهی سبک و بهینه برای المنتور: آرشیو فروشگاه، محصول تکی، سبد خرید، پرداخت و آیتم لوپ محصولات — کاملاً قابل شخصی‌سازی، بدون jQuery اضافه و فقط با بارگذاری شرطی فایل‌ها.
 * Version:           1.0.0
 * Author:            HamiSys
 * Author URI:        https://hamisys.ir/
 * Author Email:      info@hamisys.ir
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hamishop-elementor-widgets
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce, elementor
 * WC requires at least: 7.0
 * WC tested up to:   9.9
 * Elementor tested up to: 3.30
 *
 * @package HamiShop
 */

defined( 'ABSPATH' ) || exit;

define( 'HSW_VERSION', '1.0.0' );
define( 'HSW_PLUGIN_FILE', __FILE__ );
define( 'HSW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HSW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HSW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once HSW_PLUGIN_DIR . 'includes/class-hsw-plugin.php';

HSW_Plugin::instance();
