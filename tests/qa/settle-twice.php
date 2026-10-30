<?php
/**
 * QA probe: call settle() twice in one process and print the state each time.
 * Usage: php settle-twice.php <order_id>
 */
require 'C:/xampp/htdocs/sks/wp-load.php';

$oid = absint( $argv[1] ?? 0 );

function qa_state( $oid, $label ) {
	$order = wc_get_order( $oid );
	$uid   = (int) $order->get_meta( HSW_Wallet_Topup::META_USER );
	$flag  = (string) $order->get_meta( HSW_Wallet_Topup::META_DONE );
	$meta  = get_post_meta( $oid, HSW_Wallet_Topup::META_DONE, false );
	echo sprintf(
		"%-8s balance=%.2f flag=%s flag_rows=%d\n",
		$label,
		HSW_Wallet::balance( $uid ),
		'' === $flag ? '(empty)' : $flag,
		is_array( $meta ) ? count( $meta ) : -1
	);
}

qa_state( $oid, 'before' );

$p = new HSW_Wallet_Topup();
$p->settle( $oid );
qa_state( $oid, 'settle1' );

/* New instance + new order object, like a second HTTP request. */
$p2 = new HSW_Wallet_Topup();
$p2->settle( $oid );
qa_state( $oid, 'settle2' );
