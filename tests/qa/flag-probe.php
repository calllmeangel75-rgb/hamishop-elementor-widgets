<?php
/** QA probe: read the settle flag through the three available paths. */
require 'C:/xampp/htdocs/sks/wp-load.php';

$oid = absint( $argv[1] ?? 0 );

$order = wc_get_order( $oid );
echo 'order object: ' . ( $order ? get_class( $order ) : 'null' ) . "\n";
echo 'get_meta( flag ): ' . var_export( $order ? $order->get_meta( HSW_Wallet_Topup::META_DONE ) : null, true ) . "\n";
echo 'get_meta_data( flag ): ' . wp_json_encode( array_values( array_filter( (array) ( $order ? $order->get_meta_data() : array() ), function ( $m ) {
	return HSW_Wallet_Topup::META_DONE === $m->key;
} ) ) ) . "\n";
echo 'get_post_meta single: ' . var_export( get_post_meta( $oid, HSW_Wallet_Topup::META_DONE, true ), true ) . "\n";
echo 'get_post_meta all: ' . wp_json_encode( get_post_meta( $oid, HSW_Wallet_Topup::META_DONE, false ) ) . "\n";
echo 'order type: ' . ( $order ? $order->get_type() : '' ) . ' · id: ' . ( $order ? $order->get_id() : 0 ) . "\n";
