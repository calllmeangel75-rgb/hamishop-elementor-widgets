<?php
/**
 * QA helper: seed a balance and clear the ledger for one user.
 *
 * Usage: php seed.php <user_id> <balance>
 */
require 'C:/xampp/htdocs/sks/wp-load.php';

global $wpdb;

$uid = absint( $argv[1] ?? 0 );
$bal = (string) ( $argv[2] ?? '0' );

delete_user_meta( $uid, '_hsw_wallet_balance' );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}hsw_wallet_ledger WHERE user_id = %d", $uid ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}hsw_wallet WHERE user_id = %d", $uid ) );

if ( 'none' !== $bal ) {
	HSW_Wallet::set_balance( $uid, (float) $bal, 'QA seed' );
}

clean_user_cache( $uid );
wp_cache_delete( $uid, 'user_meta' );

echo 'seeded ' . $uid . ' = ' . var_export( get_user_meta( $uid, '_hsw_wallet_balance', true ), true ) . "\n";
