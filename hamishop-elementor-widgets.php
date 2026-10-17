<?php
/**
 * Plugin Name: HamiShop Elementor Widgets
 * Plugin URI:  https://hamisys.ir/
 * Description: ویجت‌های فروشگاهی المنتور برای ووکامرس — لوپ محصولات (پیشنهاد شگفت‌انگیز، پرفروش‌ترین‌ها، محبوب‌ترین‌ها) با شخصی‌سازی کامل فونت، رنگ و استایل؛ سبک و بهینه برای Lighthouse 100.
 * Version:     1.6.1
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

/*
 * DUPLICATE-COPY GUARD — the classic white-screen killer.
 * If two copies of this plugin are active at once (e.g. v1.5.0 folder
 * plus a freshly uploaded v1.6.x folder), PHP would re-declare HSW_Plugin
 * and fatal the ENTIRE site. With this guard the second copy simply does
 * nothing and shows a notice instead.
 */
if ( defined( 'HSW_VERSION' ) ) {
        if ( is_admin() ) {
                add_action( 'admin_notices', function () {
                        if ( ! current_user_can( 'activate_plugins' ) ) {
                                return;
                        }
                        printf(
                                '<div class="notice notice-warning"><p><strong>HamiShop Elementor Widgets:</strong> %s</p></div>',
                                esc_html( 'یک نسخهٔ تکراری از این افزونه شناسایی شد؛ این کپی غیرفعال ماند. لطفاً از فهرست افزونه‌ها نسخهٔ اضافی را حذف (Delete) کنید.' )
                        );
                } );
        }
        return; // another copy is already running — this file exits silently.
}

define( 'HSW_VERSION', '1.6.1' );
define( 'HSW_FILE', __FILE__ );
define( 'HSW_DIR', plugin_dir_path( __FILE__ ) );
define( 'HSW_URL', plugin_dir_url( __FILE__ ) );

/**
 * Report a caught fatal (Throwable) without ever killing the site:
 * error_log + store for the dashboard notice + instant admin notice.
 */
if ( ! function_exists( 'hsw_report_fatal' ) ) {
        function hsw_report_fatal( $e, $where = '' ) {
                $msg = sprintf(
                        '[HSW/%s] %s — %s:%d',
                        (string) $where,
                        $e->getMessage(),
                        basename( (string) $e->getFile() ),
                        (int) $e->getLine()
                );
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log( $msg );
                $prev = get_option( 'hsw_last_error' );
                if ( ! is_array( $prev ) || ! isset( $prev['msg'] ) || $prev['msg'] !== $msg ) {
                        update_option( 'hsw_last_error', array( 'msg' => $msg, 'time' => time() ), false );
                }
                if ( is_admin() ) {
                        add_action( 'admin_notices', function () use ( $msg ) {
                                if ( ! current_user_can( 'manage_options' ) ) {
                                        return;
                                }
                                printf(
                                        '<div class="notice notice-error"><p><strong>HamiShop Elementor Widgets:</strong> <code>%s</code></p></div>',
                                        esc_html( $msg )
                                );
                        } );
                }
        }
}

/**
 * Boot the plugin once WooCommerce + Elementor are available.
 *
 * SAFETY: classes that extend \Elementor\Widget_Base are NEVER loaded from
 * here. If Elementor or WooCommerce is missing, we silently return — the
 * admin notice below tells the user what to install. This is what prevents
 * the white screen of death.
 * Boot is also Throwable-safe: even if something fatals inside our own
 * includes, the site stays alive and the error surfaces in the dashboard.
 */
if ( ! function_exists( 'hsw_boot' ) ) {
        function hsw_boot() {
                if ( ! class_exists( 'WooCommerce' ) || ! did_action( 'elementor/loaded' ) ) {
                        return; // admin notice explains what is missing.
                }
                try {
                        require_once HSW_DIR . 'includes/class-hsw-plugin.php';
                        HSW_Plugin::instance();
                } catch ( \Throwable $e ) {
                        hsw_report_fatal( $e, 'boot' );
                }
        }
        add_action( 'plugins_loaded', 'hsw_boot', 20 );
}

/**
 * Activation guard (older WP versions ignore the "Requires Plugins" header):
 * never stay active without the dependencies, otherwise the site white-screens.
 */
if ( ! function_exists( 'hsw_activation_guard' ) ) {
        function hsw_activation_guard() {
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
                deactivate_plugins( plugin_basename( __FILE__ ) );
                wp_die(
                        esc_html( 'افزونه HamiShop Elementor Widgets غیرفعال شد. ابتدا این افزونه‌ها را نصب و فعال کنید: ' . implode( ' + ', $missing ) ),
                        'HamiShop Elementor Widgets',
                        array( 'back_link' => true )
                );
        }
        register_activation_hook( __FILE__, 'hsw_activation_guard' );
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

/**
 * Dashboard notice with the LAST caught plugin error — so a non-technical
 * admin can see the exact message (and send it to support) without enabling
 * WP_DEBUG. Shows a «پاک کردن» link once the issue is fixed.
 */
if ( ! function_exists( 'hsw_last_error_notice' ) ) {
        function hsw_last_error_notice() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        return;
                }
                $err = get_option( 'hsw_last_error' );
                if ( ! is_array( $err ) || empty( $err['msg'] ) ) {
                        return;
                }
                $clear = wp_nonce_url( add_query_arg( 'hsw_clear_error', '1', admin_url( 'index.php' ) ), 'hsw_clear_error' );
                printf(
                        '<div class="notice notice-error"><p><strong>HamiShop Elementor Widgets — آخرین خطای ثبت‌شده:</strong><br><code>%s</code><br><span style="color:#646970">%s</span> · <a href="%s">پاک کردن این پیام</a></p></div>',
                        esc_html( (string) $err['msg'] ),
                        esc_html( 'زمان: ' . date_i18n( 'Y-m-d H:i', (int) $err['time'] ) . ' — این پیام فقط برای مدیر نمایش داده می‌شود؛ لطفاً متن خطا را هنگام گزارش مشکل ارسال کنید.' ),
                        esc_url( $clear )
                );
        }
        add_action( 'admin_notices', 'hsw_last_error_notice' );
}

/**
 * Clear the stored error via the nonce-protected link.
 */
if ( ! function_exists( 'hsw_maybe_clear_error' ) ) {
        function hsw_maybe_clear_error() {
                if ( empty( $_GET['hsw_clear_error'] ) || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified below.
                        return;
                }
                check_admin_referer( 'hsw_clear_error' );
                delete_option( 'hsw_last_error' );
        }
        add_action( 'admin_init', 'hsw_maybe_clear_error' );
}
