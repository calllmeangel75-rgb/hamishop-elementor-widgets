<?php
/**
 * QA helper: create a throw-away top-up order for the concurrency test.
 *
 * Usage:
 *   php tests/qa/make-order.php create <user_id> <total>   → prints order id
 *   php tests/qa/make-order.php delete <order_id>
 */
require 'C:/xampp/htdocs/sks/wp-load.php';

$action = $argv[1] ?? 'create';

if ( 'delete' === $action ) {
	$id    = absint( $argv[2] ?? 0 );
	$order = $id ? wc_get_order( $id ) : null;
	if ( $order ) {
		$order->delete( true );
		echo "deleted order {$id}\n";
	} else {
		echo "order not found\n";
	}
	exit;
}

$uid   = absint( $argv[2] ?? 0 );
$total = (float) ( $argv[3] ?? 25000 );

$order = wc_create_order( array( 'customer_id' => $uid ) );
$order->set_total( $total );
$order->update_meta_data( HSW_Wallet_Topup::META_USER, $uid );
$order->update_meta_data( 'hsw_qa_order', 1 );
$order->save();

echo $order->get_id() . "\n";
