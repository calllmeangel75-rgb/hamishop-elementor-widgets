<?php
/**
 * HamiShop Elementor Widgets uninstaller.
 *
 * Cleans up plugin options when the plugin is deleted.
 *
 * @package HamiShop
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Widget toggles + performance options.
$options = [
	'hsw_widget_product_loop',
	'hsw_widget_shop_archive',
	'hsw_widget_single_product',
	'hsw_widget_cart',
	'hsw_widget_checkout',
	'hsw_persian_numbers',
	'hsw_remove_wc_blocks_css',
];

foreach ( $options as $option ) {
	delete_option( $option );
}

// Short-lived load-more transients.
global $wpdb;
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_hsw_lm_%' OR option_name LIKE '_transient_timeout_hsw_lm_%'"
);
