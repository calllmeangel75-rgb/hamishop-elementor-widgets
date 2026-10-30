<?php
/**
 * QA: migrate a synthetic wallet (legacy usermeta → hsw_wallet) at scale.
 *
 *   php tests/qa/migration-5000.php [--users=5000] [--keep]
 *
 * Builds throw-away users with logins like `hswqa_mig_#####` (never real
 * customers), runs HSW_Wallet::migrate() in batches, then checks money totals,
 * the anomaly report, the verbatim backup table, idempotency, resume after a
 * simulated timeout, and the gap report/repair pair. Everything it created is
 * deleted at the end unless --keep is passed.
 */

require 'C:/xampp/htdocs/sks/wp-load.php';

global $wpdb;

$opts = array();
foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( 0 === strpos( $arg, '--users=' ) ) {
		$opts['users'] = (int) substr( $arg, 8 );
	} elseif ( '--keep' === $arg ) {
		$opts['keep'] = true;
	}
}
$total_users = max( 20, (int) ( $opts['users'] ?? 5000 ) );
$keep        = ! empty( $opts['keep'] );

$pass = 0;
$fail = 0;
function qa( $label, $ok, $detail = '' ) {
	global $pass, $fail;
	$ok ? $pass++ : $fail++;
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( '' !== $detail ? "  ({$detail})" : '' ) . "\n";
}

function qa_cleanup() {
	global $wpdb;
	$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->users} WHERE user_login LIKE 'hswqa\\_mig\\_%'" ); // phpcs:ignore
	if ( ! $ids ) {
		return 0;
	}
	$in = implode( ',', array_map( 'intval', $ids ) );
	$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE user_id IN ({$in})" ); // phpcs:ignore
	$wpdb->query( "DELETE FROM {$wpdb->prefix}hsw_wallet WHERE user_id IN ({$in})" ); // phpcs:ignore
	$wpdb->query( "DELETE FROM {$wpdb->prefix}hsw_wallet_ledger WHERE user_id IN ({$in})" ); // phpcs:ignore
	$wpdb->query( "DELETE FROM {$wpdb->prefix}hsw_wallet_meta_backup WHERE user_id IN ({$in})" ); // phpcs:ignore
	$wpdb->query( "DELETE FROM {$wpdb->users} WHERE ID IN ({$in})" ); // phpcs:ignore

	return count( $ids );
}

echo 'cleanup: removed ' . qa_cleanup() . " leftover QA users\n";
delete_option( 'hsw_wallet_migration' );
$wpdb->query( 'DELETE FROM ' . HSW_Wallet::backup_table() ); // phpcs:ignore

/* ------------------------------------------------------- build the dataset */

$n = $total_users;
/* 96% normal, the rest deliberately broken; the tail users get no meta. */
$plan = array(
	'single'  => (int) round( $n * 0.96 ),
	'dupsame' => 50,
	'dupdiff' => 100,
	'empty'   => 30,
	'nan'     => 10,
	'persian' => 5,
	'decimal' => 5,
);
$used        = array_sum( $plan );
$plan['nometa'] = max( 0, $n - $used );
if ( $used > $n ) {
	$plan['single'] -= ( $used - $n );
	$plan['nometa']  = 0;
}

$expected_sum   = 0.0;
$expected_rows  = 0;
$expected_flags = array(); /* user_id => flags */
$user_ids       = array();
$meta_rows      = array();

$next_id = (int) $wpdb->get_var( "SELECT COALESCE(MAX(ID),0) FROM {$wpdb->users}" ) + 1;
$now     = current_time( 'mysql' );
$idx     = 0;

foreach ( $plan as $kind => $count ) {
	for ( $k = 0; $k < $count; $k++ ) {
		$uid   = $next_id++;
		$login = 'hswqa_mig_' . ( 10000 + $idx );
		$idx++;
		$user_ids[ $uid ] = $login;

		$values = array();
		switch ( $kind ) {
			case 'single':
				$v        = mt_rand( 0, 5000000 );
				$values[] = $v;
				$expected_sum += $v;
				break;
			case 'dupsame':
				$v        = mt_rand( 1000, 90000 );
				$values   = array( $v, $v );
				$expected_sum += 2 * $v;
				$expected_flags[ $uid ][] = 'duplicate-rows';
				break;
			case 'dupdiff':
				$v        = mt_rand( 1000, 90000 );
				$values   = array( $v, $v + 500, 250 );
				$expected_sum += 2 * $v + 750;
				$expected_flags[ $uid ][] = 'duplicate-rows';
				$expected_flags[ $uid ][] = 'duplicate-differing';
				break;
			case 'empty':
				$values = array( '' );
				$expected_flags[ $uid ][] = 'empty';
				break;
			case 'nan':
				$values = array( 'abc' );
				$expected_flags[ $uid ][] = 'non-numeric';
				break;
			case 'persian':
				$values = array( '۱۲۳۴۵' );
				$expected_sum += 12345;
				$expected_flags[ $uid ][] = 'persian-digits';
				break;
			case 'decimal':
				$values = array( '1234.56' );
				$expected_sum += 1234.56;
				$expected_flags[ $uid ][] = 'decimal';
				break;
		}

		foreach ( $values as $v ) {
			$meta_rows[] = array( $uid, (string) $v );
			$expected_rows++;
		}
	}
}

/* Bulk insert users and meta in chunks. */
foreach ( array_chunk( array_keys( $user_ids ), 500 ) as $chunk ) {
	$rows = array();
	foreach ( $chunk as $uid ) {
		$login  = $user_ids[ $uid ];
		$rows[] = $wpdb->prepare( '(%d,%s,%s,%s,%s,%s,%s,%s,%s,%s)', $uid, $login, md5( $login ), $login . '@example.invalid', '', $login, 'customer', '', $now, '0' );
	}
	$wpdb->query( "INSERT INTO {$wpdb->users} (ID, user_login, user_pass, user_email, user_url, display_name, user_nicename, user_activation_key, user_registered, user_status) VALUES " . implode( ',', $rows ) ); // phpcs:ignore
}
foreach ( array_chunk( $meta_rows, 500 ) as $chunk ) {
	$rows = array();
	foreach ( $chunk as $m ) {
		$rows[] = $wpdb->prepare( "(%d,'_hsw_wallet_balance',%s)", $m[0], $m[1] );
	}
	$wpdb->query( "INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value) VALUES " . implode( ',', $rows ) ); // phpcs:ignore
}

$in          = implode( ',', array_map( 'intval', array_keys( $user_ids ) ) );
$real_with_meta = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key='_hsw_wallet_balance' AND user_id NOT IN ({$in})" ); // phpcs:ignore
$real_meta_sum  = (float) $wpdb->get_var( "SELECT COALESCE(SUM(CAST(meta_value AS DECIMAL(18,2))),0) FROM {$wpdb->usermeta} WHERE meta_key='_hsw_wallet_balance' AND user_id NOT IN ({$in})" ); // phpcs:ignore
$meta_sig_before = (string) $wpdb->get_var( "SELECT MD5(GROUP_CONCAT(CONCAT(user_id,':',meta_value) ORDER BY user_id, meta_id)) FROM {$wpdb->usermeta} WHERE meta_key='_hsw_wallet_balance' AND user_id IN ({$in})" ); // phpcs:ignore

printf(
	"dataset: %d users · %d with meta · %d meta rows · expected sum %.2f · real users with meta (untouched, also migrated): %d\n",
	count( $user_ids ),
	count( $user_ids ) - (int) $plan['nometa'],
	$expected_rows,
	$expected_sum,
	$real_with_meta
);

/* ------------------------------------------------------------- 1) run it */

$t0  = microtime( true );
$run = HSW_Wallet::migrate( 200 );
$sec = round( microtime( true ) - $t0, 2 );

$migrated_users = count( $user_ids ) - (int) $plan['nometa'];
qa( 'users migrated (QA + real)', $run['users'] === $migrated_users + $real_with_meta, "got {$run['users']} want " . ( $migrated_users + $real_with_meta ) );
qa( 'total_before matches the independent sum', abs( $run['total_before'] - ( $expected_sum + $real_meta_sum ) ) < 0.05, sprintf( 'got %.2f want %.2f', $run['total_before'], $expected_sum + $real_meta_sum ) );
qa( 'total_after matches', abs( $run['total_after'] - ( $expected_sum + $real_meta_sum ) ) < 0.05, sprintf( 'got %.2f want %.2f', $run['total_after'], $expected_sum + $real_meta_sum ) );

$table_sum = (float) $wpdb->get_var( "SELECT COALESCE(SUM(balance),0) FROM " . HSW_Wallet::balance_table() . " WHERE user_id IN ({$in})" ); // phpcs:ignore
qa( 'wallet table sum matches', abs( $table_sum - $expected_sum ) < 0.05, sprintf( 'got %.2f', $table_sum ) );

$wallet_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . HSW_Wallet::balance_table() . " WHERE user_id IN ({$in})" ); // phpcs:ignore
qa( 'exactly one row per user', $wallet_rows === $migrated_users, "rows={$wallet_rows} users={$migrated_users}" );

$expected_anomalies = $plan['dupsame'] + $plan['dupdiff'] + $plan['empty'] + $plan['nan'] + $plan['persian'] + $plan['decimal'];
qa( 'anomaly count', (int) $run['anomalies'] === $expected_anomalies, "got {$run['anomalies']} want {$expected_anomalies}" );
echo "      migration took {$sec}s for " . count( $user_ids ) . " users\n";

/* ------------------------------------------- 2) report content, backup, meta */

$report = HSW_Wallet::migration_report();
$listed = array();
foreach ( (array) ( $report['anomaly_list'] ?? array() ) as $a ) {
	$listed[ (int) $a['user_id'] ] = $a;
}

$missing = 0;
$flagged_wrong = 0;
foreach ( $expected_flags as $uid => $flags ) {
	if ( ! isset( $listed[ $uid ] ) ) {
		$missing++;
		continue;
	}
	$got = (array) $listed[ $uid ]['flags'];
	sort( $flags );
	sort( $got );
	if ( $flags !== $got ) {
		$flagged_wrong++;
	}
}
qa( 'every suspicious user is in the report', 0 === $missing, "missing={$missing}" );
qa( 'each one is flagged for the right reason', 0 === $flagged_wrong, "wrong={$flagged_wrong}" );

$sample  = array_key_first( $expected_flags );
$raw     = isset( $listed[ $sample ] ) ? $listed[ $sample ]['values'] : array();
$want_values = array();
foreach ( $meta_rows as $m ) {
	if ( (int) $m[0] === (int) $sample ) {
		$want_values[] = (string) $m[1];
	}
}
qa( 'report keeps every raw value', $raw === $want_values, implode( ' | ', $raw ) );

$backup_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . HSW_Wallet::backup_table() . " WHERE user_id IN ({$in})" ); // phpcs:ignore
qa( 'verbatim backup of every meta row', $backup_rows === $expected_rows, "backup={$backup_rows} meta={$expected_rows}" );

$meta_sig_after = (string) $wpdb->get_var( "SELECT MD5(GROUP_CONCAT(CONCAT(user_id,':',meta_value) ORDER BY user_id, meta_id)) FROM {$wpdb->usermeta} WHERE meta_key='_hsw_wallet_balance' AND user_id IN ({$in})" ); // phpcs:ignore
qa( 'legacy meta untouched (rollback snapshot)', $meta_sig_before === $meta_sig_after );

/* --------------------------------------------------------- 3) idempotency */

$run2 = HSW_Wallet::migrate( 200, true );
$sum2 = (float) $wpdb->get_var( "SELECT COALESCE(SUM(balance),0) FROM " . HSW_Wallet::balance_table() . " WHERE user_id IN ({$in})" ); // phpcs:ignore
$rows2 = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . HSW_Wallet::balance_table() . " WHERE user_id IN ({$in})" ); // phpcs:ignore
qa( 'second run: total unchanged', abs( $sum2 - $expected_sum ) < 0.05, sprintf( 'got %.2f', $sum2 ) );
qa( 'second run: no extra rows', $rows2 === $migrated_users, "rows={$rows2}" );

/* ------------------------------------- 4) resume after a simulated timeout */

$state            = HSW_Wallet::migration_report();
$state['done']    = false;
$state['last_user'] = (int) ( array_keys( $user_ids )[ (int) floor( count( $user_ids ) / 2 ) ] );
update_option( 'hsw_wallet_migration', $state, false );

$run3 = HSW_Wallet::migrate( 200 );
$sum3 = (float) $wpdb->get_var( "SELECT COALESCE(SUM(balance),0) FROM " . HSW_Wallet::balance_table() . " WHERE user_id IN ({$in})" ); // phpcs:ignore
qa( 'resume finishes', ! empty( $run3['done'] ) );
qa( 'resume changes no money', abs( $sum3 - $expected_sum ) < 0.05, sprintf( 'got %.2f', $sum3 ) );

/* ------------------------------------------------- 5) gap report + repair */

$gap_uid = (int) array_key_first( array_diff_key( $user_ids, array_flip( array_keys( $expected_flags ) ) ) );
$wpdb->query( $wpdb->prepare( 'UPDATE ' . HSW_Wallet::balance_table() . ' SET balance = balance + 100 WHERE user_id = %d', $gap_uid ) ); // phpcs:ignore
$balance_before_repair = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT balance FROM ' . HSW_Wallet::balance_table() . ' WHERE user_id = %d', $gap_uid ) ); // phpcs:ignore

$gaps = array_values( array_filter( HSW_Wallet::balance_gaps( 2000 ), function ( $g ) use ( $gap_uid ) {
	return $g['user_id'] === $gap_uid;
} ) );
qa( 'gap report finds the interrupted write', 1 === count( $gaps ) && abs( $gaps[0]['diff'] - 100 ) < 0.01, wp_json_encode( $gaps ) );

$repaired = HSW_Wallet::repair_gaps( 2000 );
$after    = array_values( array_filter( HSW_Wallet::balance_gaps( 2000 ), function ( $g ) use ( $gap_uid ) {
	return $g['user_id'] === $gap_uid;
} ) );
$balance_after_repair = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT balance FROM ' . HSW_Wallet::balance_table() . ' WHERE user_id = %d', $gap_uid ) ); // phpcs:ignore
$ledger_after_repair  = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM " . HSW_Wallet::table() . " WHERE user_id = %d AND status = 'done'", $gap_uid ) ); // phpcs:ignore

qa( 'repair closes the gap', empty( $after ), wp_json_encode( $after ) );
qa( 'repair never moves money', abs( $balance_after_repair - $balance_before_repair ) < 0.01, sprintf( 'before %.2f after %.2f', $balance_before_repair, $balance_after_repair ) );
qa( 'ledger now equals the balance', abs( $ledger_after_repair - $balance_after_repair ) < 0.01, sprintf( 'ledger %.2f balance %.2f', $ledger_after_repair, $balance_after_repair ) );
qa( 'repair is logged in the ledger', (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . HSW_Wallet::table() . " WHERE ref = %s", 'repair:' . $gap_uid . ':100.00' ) ) );

/* A second repair must not add a second line for the same gap. */
$again = HSW_Wallet::repair_gaps( 2000 );
qa( 'repair is idempotent', 0 === count( $again ), wp_json_encode( $again ) );

/* --------------------------------------------------------------- cleanup */

if ( $keep ) {
	echo "kept: " . count( $user_ids ) . " users\n";
} else {
	$removed = qa_cleanup();
	$wpdb->query( 'DELETE FROM ' . HSW_Wallet::backup_table() ); // phpcs:ignore
	delete_option( 'hsw_wallet_migration' );
	echo "cleanup: removed {$removed} QA users\n";
}

echo "\nPASS={$pass} FAIL={$fail}\n";
exit( $fail > 0 ? 1 : 0 );
