<?php
/**
 * SQL portability check for the wallet statements.
 *
 *   HSW_QA_DSN="mysql:host=127.0.0.1;port=3306;dbname=test" \
 *   HSW_QA_USER=root HSW_QA_PASS=secret php tests/qa/sql-compat.php
 *
 * The plugin supports MySQL and MariaDB, and the credit path used to rely on
 * `INSERT … ON DUPLICATE KEY UPDATE … VALUES()`, which MySQL 8.0.20 deprecates
 * and whose replacement (`AS new`) MariaDB does not understand. This script
 * creates a scratch table and runs the exact statements the plugin now issues,
 * so the same file can be pointed at a MySQL 8 container in CI.
 */

$dsn  = getenv( 'HSW_QA_DSN' ) ?: 'mysql:host=127.0.0.1;port=3306;dbname=sks';
$user = getenv( 'HSW_QA_USER' ) ?: 'supports';
$pass = getenv( 'HSW_QA_PASS' ) ?: 'support$$$SKYSTORE';

$pass_count = 0;
$fail_count = 0;
function qa( $label, $ok, $detail = '' ) {
	global $pass_count, $fail_count;
	$ok ? $pass_count++ : $fail_count++;
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( '' !== $detail ? "  ({$detail})" : '' ) . "\n";
}

try {
	$pdo = new PDO( $dsn, $user, $pass, array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ) );
} catch ( Throwable $e ) {
	echo "SKIP  cannot connect: " . $e->getMessage() . "\n";
	exit( 2 );
}

$version = (string) $pdo->query( 'SELECT VERSION()' )->fetchColumn();
echo "server: {$version}\n";

/*
 * WordPress relaxes the SQL mode of its own connection (it drops strict mode and
 * NO_ZERO_DATE) so the `0000-00-00` defaults in core tables are accepted. Mirror
 * that here, otherwise the scratch table fails for a reason the plugin never
 * meets in production.
 */
$pdo->exec( "SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'" );

$t = 'hsw_qa_sql_compat';
$pdo->exec( "DROP TABLE IF EXISTS {$t}" );
$pdo->exec(
	"CREATE TABLE {$t} (
		user_id bigint unsigned NOT NULL,
		balance decimal(18,2) NOT NULL DEFAULT 0,
		points_used decimal(12,0) NOT NULL DEFAULT 0,
		updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY (user_id)
	) ENGINE=InnoDB"
);

/* 1. Credit on a missing row: UPDATE (0 rows) → INSERT IGNORE (1 row). */
$upd = $pdo->prepare( "UPDATE {$t} SET balance = balance + ?, updated_at = ? WHERE user_id = ?" );
$upd->execute( array( 100, '2026-01-01 00:00:00', 1 ) );
qa( 'credit: UPDATE affects 0 rows when the user has no row', 0 === $upd->rowCount() );

$ins = $pdo->prepare( "INSERT IGNORE INTO {$t} (user_id, balance, updated_at) VALUES (?, ?, ?)" );
$ins->execute( array( 1, 100, '2026-01-01 00:00:00' ) );
qa( 'credit: INSERT IGNORE creates the first row', 1 === $ins->rowCount() );

/* 2. Second credit takes the UPDATE branch. */
$upd->execute( array( 250.5, '2026-01-01 00:00:01', 1 ) );
qa( 'credit: UPDATE affects 1 row for an existing user', 1 === $upd->rowCount() );
$bal = (float) $pdo->query( "SELECT balance FROM {$t} WHERE user_id = 1" )->fetchColumn();
qa( 'credit: balance is the sum', abs( $bal - 350.5 ) < 0.001, (string) $bal );

/* 3. Debit guard lives in the WHERE clause. */
$deb = $pdo->prepare( "UPDATE {$t} SET balance = balance - ? WHERE user_id = ? AND balance >= ?" );
$deb->execute( array( 400, 1, 400 ) );
qa( 'debit: refused when the balance is short', 0 === $deb->rowCount() );
$deb->execute( array( 350.5, 1, 350.5 ) );
qa( 'debit: allowed for the exact balance', 1 === $deb->rowCount() );
$bal = (float) $pdo->query( "SELECT balance FROM {$t} WHERE user_id = 1" )->fetchColumn();
qa( 'debit: never negative', abs( $bal ) < 0.001, (string) $bal );

/* 4. Points claim. */
$claim = $pdo->prepare( "UPDATE {$t} SET points_used = points_used + ? WHERE user_id = ? AND points_used + ? <= ?" );
$claim->execute( array( 400, 1, 400, 1000 ) );
qa( 'points: claim inside the limit', 1 === $claim->rowCount() );
$claim->execute( array( 400, 1, 400, 1000 ) );
$claim->execute( array( 400, 1, 400, 1000 ) );
qa( 'points: never above the earned total', 800 === (int) $pdo->query( "SELECT points_used FROM {$t} WHERE user_id = 1" )->fetchColumn() );

/* 5. Unique reference with NULLs (idempotency). */
$pdo->exec( "DROP TABLE IF EXISTS {$t}_ledger" );
$pdo->exec( "CREATE TABLE {$t}_ledger (id bigint unsigned NOT NULL AUTO_INCREMENT, ref varchar(64) NULL DEFAULT NULL, PRIMARY KEY (id), UNIQUE KEY uniq_ref (ref)) ENGINE=InnoDB" );
$pdo->exec( "INSERT INTO {$t}_ledger (ref) VALUES (NULL), (NULL), ('topup:1')" );
qa( 'ref: many NULLs allowed', 3 === (int) $pdo->query( "SELECT COUNT(*) FROM {$t}_ledger" )->fetchColumn() );
$dup = false;
try {
	$pdo->exec( "INSERT INTO {$t}_ledger (ref) VALUES ('topup:1')" );
} catch ( PDOException $e ) {
	$dup = true;
}
qa( 'ref: a duplicate is rejected by the unique index', $dup );

/* 6. The deprecated syntax must not appear anywhere in the plugin. */
$root = dirname( __DIR__, 2 );
/** @var array<int,string> Files that still use the deprecated syntax. */
$hits = array();
foreach ( glob( $root . '/includes/*.php' ) as $file ) {
	/* Strip comments: the code explains *why* the syntax is gone. */
	$src = (string) file_get_contents( $file );
	$src = (string) preg_replace( '!/\*.*?\*/!s', '', $src );
	$src = (string) preg_replace( '!^\s*//.*$!m', '', $src );

	if ( preg_match( '/ON\s+DUPLICATE\s+KEY\s+UPDATE[^;]*VALUES\s*\(/i', $src ) ) {
		$hits[] = basename( $file );
	}
}
qa( 'no INSERT … ON DUPLICATE KEY UPDATE … VALUES() left', empty( $hits ), implode( ', ', $hits ) );

$pdo->exec( "DROP TABLE IF EXISTS {$t}" );
$pdo->exec( "DROP TABLE IF EXISTS {$t}_ledger" );

echo "\nserver={$version} PASS={$pass_count} FAIL={$fail_count}\n";
exit( $fail_count > 0 ? 1 : 0 );
