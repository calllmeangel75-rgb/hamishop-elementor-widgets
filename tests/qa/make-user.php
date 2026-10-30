<?php
/**
 * QA helper: create (or reset) a throw-away customer for concurrency tests.
 *
 * Usage:
 *   php tests/qa/make-user.php create        → prints "<id> <login>"
 *   php tests/qa/make-user.php delete <id>   → removes user + meta + ledger rows
 */
require 'C:/xampp/htdocs/sks/wp-load.php';

$action = $argv[1] ?? 'create';

if ( 'delete' === $action ) {
	$id = absint( $argv[2] ?? 0 );
	if ( ! $id ) {
		echo "no id\n";
		exit( 1 );
	}
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}hsw_wallet_ledger WHERE user_id = %d", $id ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}hsw_wallet WHERE user_id = %d", $id ) );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $id );
	echo "deleted {$id}\n";
	exit;
}

$login = 'hsw_qa_conc_' . wp_rand( 1000, 9999 );
$id    = wp_create_user( $login, wp_generate_password( 24 ), $login . '@example.invalid' );
if ( is_wp_error( $id ) ) {
	echo 'ERR ' . $id->get_error_message() . "\n";
	exit( 1 );
}
$user = new WP_User( $id );
$user->set_role( 'customer' );
update_user_meta( $id, 'hsw_qa_created', time() );

echo $id . ' ' . $login . "\n";
