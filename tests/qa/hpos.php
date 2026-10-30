<?php
/**
 * QA: toggle WooCommerce HPOS on, for the duration of a test run.
 *
 *   php tests/qa/hpos.php on      → backup the options, enable HPOS (+ sync)
 *   php tests/qa/hpos.php off     → restore the backed-up options
 *   php tests/qa/hpos.php status  → print the current state
 *
 * The original option values are copied to `hsw_qa_hpos_backup` so `off` puts
 * the site back exactly as it was.
 */
require 'C:/xampp/htdocs/sks/wp-load.php';

global $wpdb;

$action = (string) ( $argv[1] ?? 'status' );
$keys   = array(
	'woocommerce_custom_orders_table_enabled',
	'woocommerce_feature_custom_order_tables_enabled',
	'woocommerce_custom_orders_table_data_sync_enabled',
);

/*
 * Options are written with SQL, not `update_option()`: flipping the feature
 * flag inside a booted CLI process runs WooCommerce's feature hooks against a
 * half-initialised container and ends in a fatal page. A fresh process then
 * boots with the flag already in place.
 */
if ( 'on' === $action ) {
	$backup = array();
	foreach ( $keys as $key ) {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );
		$backup[ $key ] = $row ? $row->option_value : '__missing__';
	}
	$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES ('hsw_qa_hpos_backup', %s, 'no') ON DUPLICATE KEY UPDATE option_value = %s", wp_json_encode( $backup ), wp_json_encode( $backup ) ) );

	update_option( 'woocommerce_feature_custom_order_tables_enabled', 'yes' );
	foreach ( array( 'woocommerce_custom_orders_table_enabled' => 'yes', 'woocommerce_custom_orders_table_data_sync_enabled' => 'no' ) as $key => $value ) {
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s", $key ) );
		if ( $exists ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s", $value, $key ) );
		} else {
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'yes')", $key, $value ) );
		}
	}
	wp_cache_flush();

	echo "HPOS on\n";
} elseif ( 'off' === $action ) {
	$backup = json_decode( (string) $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'hsw_qa_hpos_backup'" ), true );
	foreach ( (array) $backup as $key => $value ) {
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s", $key ) );
		if ( '__missing__' === $value ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", $key ) );
			continue;
		}
		if ( $exists ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s", $value, $key ) );
		} else {
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'yes')", $key, $value ) );
		}
	}
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name = 'hsw_qa_hpos_backup'" );
	wp_cache_flush();

	echo "HPOS restored\n";
}

/* Report the state either way. */
$store = 'unknown';
try {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && method_exists( '\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ) ) {
		$store = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'hpos' : 'posts';
	}
} catch ( \Throwable $e ) {
	$store = 'error: ' . $e->getMessage();
}

/* Ask the data store itself, which is what `wc_get_order()` will use. */
$datastore = 'n/a';
try {
	$probe = wc_create_order();
	if ( $probe ) {
		$datastore = $probe->get_data_store()->get_current_class_name();
		$probe->delete( true );
	}
} catch ( \Throwable $e ) {
	$datastore = 'error: ' . $e->getMessage();
}

echo wp_json_encode(
	array(
		'enabled'   => get_option( 'woocommerce_custom_orders_table_enabled' ),
		'sync'      => get_option( 'woocommerce_custom_orders_table_data_sync_enabled' ),
		'usage'     => $store,
		'data_store'=> $datastore,
		'wc'        => defined( 'WC_VERSION' ) ? WC_VERSION : '',
	)
) . "\n";
