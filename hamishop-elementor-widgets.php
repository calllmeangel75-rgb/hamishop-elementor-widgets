<?php
/**
 * Plugin Name: HamiShop Elementor Widgets
 * Plugin URI:  https://hamisys.ir/
 * Description: ویجت‌های فروشگاهی المنتور برای ووکامرس — لوپ محصولات، صفحه محصول، سبد خرید، تسویه حساب و پنل حساب کاربری با شخصی‌سازی کامل فونت، رنگ و استایل؛ سبک با دارایی‌های لوکال و بارگذاری شرطی.
 * Version:     1.11.235
 * Author:      HamiSys
 * Author URI:  https://hamisys.ir/
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hamishop-elementor-widgets
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce, elementor
 * Elementor tested up to: 4.3.5
 * WC tested up to: 11.2.1
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
        /*
         * This copy never boots, so it never reaches the
         * `FeaturesUtil::declare_compatibility()` call in HSW_Plugin. WooCommerce
         * scans every *active* plugin for that declaration, so the inert copy
         * showed up in «ناسازگاری افزونهها» next to the real one — the warning
         * the shop owner saw. Declare it here too: this file really is inert,
         * and the feature it is being judged on is handled by the running copy.
         */
        add_action(
                'before_woocommerce_init',
                function () {
                        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
                                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
                        }
                }
        );

        if ( is_admin() ) {
                add_action( 'admin_notices', function () {
                        if ( ! current_user_can( 'activate_plugins' ) ) {
                                return;
                        }

                        /* Name both folders: "delete the extra one" is useless when
                           both rows in the list carry the same name. */
                        $mine  = basename( __DIR__ );
                        $other = '';
                        foreach ( (array) get_option( 'active_plugins', array() ) as $rel ) {
                                $dir = basename( dirname( (string) $rel ) );
                                /* Only another copy of THIS plugin counts. */
                                if ( '' !== $dir && $dir !== $mine && 0 === strpos( $dir, 'hamishop-elementor-widgets' ) ) {
                                        $other = $dir;
                                        break;
                                }
                        }

                        printf(
                                '<div class="notice notice-error"><p><strong>HamiShop Elementor Widgets:</strong> %s</p><p>%s</p></div>',
                                esc_html__( 'دو نسخهٔ هم‌زمان از این افزونه فعال است؛ این کپی کاری انجام نمی‌دهد.', 'hamishop-elementor-widgets' ),
                                $other
                                        ? esc_html( sprintf( 'نسخهٔ در حال اجرا در پوشهٔ «%1$s» است. پوشهٔ «%2$s» را از هاست (یا با Delete در فهرست افزونه‌ها) حذف کنید. تا وقتی هر دو نصب باشند، ووکامرس هم این کپی را در فهرست ناسازگاری نشان می‌دهد.', $other, $mine ) )
                                        : esc_html( 'لطفاً از فهرست افزونه‌ها نسخهٔ اضافی را حذف (Delete) کنید.' )
                        );
                } );
        }
        return; // another copy is already running — this file exits silently.
}

define( 'HSW_VERSION', '1.11.235' );
define( 'HSW_FILE', __FILE__ );
define( 'HSW_DIR', plugin_dir_path( __FILE__ ) );
define( 'HSW_URL', plugin_dir_url( __FILE__ ) );

/**
 * UPDATE-IN-PLACE GUARD.
 *
 * Uploading a new release from Plugins → Add New → Upload used to install it
 * NEXT TO the old one whenever the zip's top-level folder was not named exactly
 * like the running folder (GitHub "Source code" archives are called
 * `hamishop-elementor-widgets-1.11.x`). Two folders → two copies → the
 * duplicate-copy notice, and the old code kept running.
 *
 * WordPress lets a plugin rename the unpacked package before it is moved into
 * place (`upgrader_source_selection`). If the package is HamiShop, rename it to
 * the folder THIS copy runs from, so "Replace current with uploaded" overwrites
 * in place and the plugin keeps its slug, its active state and its settings.
 */
if ( ! function_exists( 'hsw_align_package_folder' ) ) {
        function hsw_align_package_folder( $source, $remote_source = '', $upgrader = null, $hook_extra = array() ) {
                if ( is_wp_error( $source ) || ! is_string( $source ) || '' === $source ) {
                        return $source;
                }
                unset( $upgrader, $hook_extra );

                $main = trailingslashit( $source ) . 'hamishop-elementor-widgets.php';
                if ( ! is_file( $main ) ) {
                        return $source; // some other plugin or theme.
                }
                $head = (string) file_get_contents( $main, false, null, 0, 2048 );
                if ( false === strpos( $head, 'Plugin Name: HamiShop Elementor Widgets' ) ) {
                        return $source;
                }

                $target  = basename( rtrim( HSW_DIR, '/\\' ) );
                $current = basename( rtrim( $source, '/\\' ) );
                /* Already right, or the zip had no top-level folder at all. */
                if ( '' === $target || $current === $target || trailingslashit( $source ) === trailingslashit( (string) $remote_source ) ) {
                        return $source;
                }

                global $wp_filesystem;
                if ( ! $wp_filesystem ) {
                        return $source;
                }
                $new = trailingslashit( dirname( rtrim( $source, '/\\' ) ) ) . $target . '/';
                if ( $wp_filesystem->exists( $new ) ) {
                        $wp_filesystem->delete( $new, true );
                }
                return $wp_filesystem->move( $source, $new, true ) ? $new : $source;
        }
        add_filter( 'upgrader_source_selection', 'hsw_align_package_folder', 10, 4 );
}

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
                        $file = (string) $e->getFile();
                        update_option(
                                'hsw_last_error',
                                array(
                                        'msg'     => $msg,
                                        'time'    => time(),
                                        'version' => defined( 'HSW_VERSION' ) ? HSW_VERSION : '',
                                        /*
                                         * The file and its mtime at the moment of the
                                         * error. If the file is edited afterwards the
                                         * message is stale — see the notice below.
                                         */
                                        'file'    => $file,
                                        'mtime'   => ( $file && is_file( $file ) ) ? (int) filemtime( $file ) : 0,
                                ),
                                false
                        );
                }
                if ( is_admin() ) {
                        add_action( 'admin_notices', function () use ( $msg ) {
                                if ( ! current_user_can( 'manage_options' ) ) {
                                        return;
                                }
                                /*
                                 * Mark the message as already shown so the stored
                                 * notice below does not print the same error a
                                 * second time in the same request.
                                 */
                                $GLOBALS['hsw_error_shown'] = true;
                                printf(
                                        '<div class="notice notice-error"><p><strong>HamiShop Elementor Widgets:</strong> <code>%s</code></p></div>',
                                        esc_html( $msg )
                                );
                        } );
                }
        }
}

/**
 * Last-chance reporter for a REAL fatal.
 *
 * `hsw_report_fatal()` only sees exceptions we caught ourselves. A fatal error
 * — an Error we never reached, an exhausted memory limit, a max_execution_time
 * kill — ends the request before any of that runs, and the visitor gets a blank
 * page or a 503 with nothing in the log that names the file.
 *
 * On a live shop that turned a 503 into hours of guessing: the PHP log held only
 * notices, so there was no way to tell a plugin fatal from a server-side kill.
 * This handler makes the next occurrence say where it happened.
 *
 * Cheap by design: one `error_get_last()` at shutdown, and only fatal types are
 * written. It never throws and never changes the response.
 */
if ( ! function_exists( 'hsw_shutdown_report' ) ) {
	function hsw_shutdown_report() {
		$err = error_get_last();

		if ( ! is_array( $err ) || ! isset( $err['type'] ) ) {
			return;
		}

		$fatal = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR );

		if ( ! in_array( (int) $err['type'], $fatal, true ) ) {
			return;
		}

		$file = (string) ( $err['file'] ?? '' );
		$msg  = sprintf(
			'[HSW/fatal] %s — %s:%d',
			(string) ( $err['message'] ?? '' ),
			basename( $file ),
			(int) ( $err['line'] ?? 0 )
		);

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $msg );

		/*
		 * Only our own files belong in the dashboard notice. A fatal inside
		 * another plugin is still worth the log line above, but it is not ours
		 * to surface.
		 */
		$ours = defined( 'HSW_DIR' ) && '' !== $file && 0 === strpos( wp_normalize_path( $file ), wp_normalize_path( HSW_DIR ) );

		if ( ! $ours ) {
			return;
		}

		update_option(
			'hsw_last_error',
			array(
				'msg'     => $msg,
				'time'    => time(),
				'version' => defined( 'HSW_VERSION' ) ? HSW_VERSION : '',
				'file'    => $file,
				'mtime'   => is_file( $file ) ? (int) filemtime( $file ) : 0,
			),
			false
		);
	}

	register_shutdown_function( 'hsw_shutdown_report' );
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
 * Leave no trace in `.htaccess` when the plugin is switched off.
 *
 * The custom admin path is a rewrite rule, not PHP. Deactivating without
 * removing it would leave a rule pointing at wp-admin with nothing managing
 * it, so the block is cleaned up explicitly.
 */
if ( ! function_exists( 'hsw_deactivation_cleanup' ) ) {
        function hsw_deactivation_cleanup() {
                if ( ! class_exists( 'HSW_Security' ) ) {
                        require_once HSW_DIR . 'includes/class-hsw-security.php';
                }
                HSW_Security::remove_rewrite();
                /* Never leave a polling schedule behind once the plugin is off. */
                wp_clear_scheduled_hook( 'hsw_tg_poll' );
                wp_clear_scheduled_hook( 'hsw_throttle_prune' );
                /* Background upgrade ticks must not outlive the plugin. */
                if ( class_exists( 'HSW_Upgrader' ) ) {
                        HSW_Upgrader::unschedule();
                }
        }
        register_deactivation_hook( __FILE__, 'hsw_deactivation_cleanup' );
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

                /* Already printed by hsw_report_fatal() in this very request. */
                if ( ! empty( $GLOBALS['hsw_error_shown'] ) ) {
                        return;
                }

                $err = get_option( 'hsw_last_error' );
                if ( ! is_array( $err ) || empty( $err['msg'] ) ) {
                        return;
                }

                /*
                 * An error recorded by an older plugin version is almost always
                 * a bug that the update fixed. Keeping it on screen forever made
                 * admins report already-solved problems, so a version change
                 * clears the record instead of nagging.
                 */
                $stored_version = isset( $err['version'] ) ? (string) $err['version'] : '';
                if ( defined( 'HSW_VERSION' ) && $stored_version !== HSW_VERSION ) {
                        delete_option( 'hsw_last_error' );
                        return;
                }

                /*
                 * Same version, but the file has been edited since the error was
                 * caught — a syntax error is recorded by the shutdown handler
                 * *before* the fix, so the record outlives the bug and nags about
                 * something that no longer happens. Compare the mtime: if the file
                 * changed, the message is stale.
                 */
                $err_file  = isset( $err['file'] ) ? (string) $err['file'] : '';
                $err_mtime = isset( $err['mtime'] ) ? (int) $err['mtime'] : 0;
                if ( $err_file && $err_mtime && is_file( $err_file ) && (int) filemtime( $err_file ) !== $err_mtime ) {
                        delete_option( 'hsw_last_error' );
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
