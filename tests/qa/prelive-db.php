<?php
/**
 * P0-3 — HSW_Throttle against a REAL MariaDB (not the flock fake).
 *
 * QA-only; the release ZIP excludes tests/ (.gitattributes export-ignore). Requires:
 *   - a MariaDB/MySQL on 127.0.0.1:3307, database hsw_qa, user root, no password
 *   - the XAMPP PHP (it is the only binary here with pdo_mysql)
 *
 *   C:\xampp\php\php.exe tests/qa/prelive-db.php            # run
 *   C:\xampp\php\php.exe tests/qa/prelive-db.php --worker=hit --bucket=b --limit=1 --at=1234.5
 */

require_once __DIR__ . '/prelive-lib.php';

$DB_DSN  = getenv( 'HSW_QA_DSN' ) ?: 'mysql:host=127.0.0.1;port=3307;dbname=hsw_qa';
$DB_USER = getenv( 'HSW_QA_USER' ) ?: 'root';
$DB_PASS = getenv( 'HSW_QA_PASS' ) ?: '';

/** Real $wpdb for the statements HSW_Throttle issues. */
class HSW_Prelive_MySQL_WPDB {

	public $prefix = 'wp_';
	public $options = 'wp_options';
	public $usermeta = 'wp_usermeta';
	public $posts = 'wp_posts';
	public $postmeta = 'wp_postmeta';
	public $last_error = '';
	public $insert_id = 0;
	public $queries = array();
	private $pdo;

	public function __construct( $dsn, $user, $pass ) {
		$this->pdo = new PDO(
			$dsn,
			$user,
			$pass,
			array(
				PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
				PDO::ATTR_EMULATE_PREPARES => true,
			)
		);
	}

	public function pdo() {
		return $this->pdo;
	}

	public function get_charset_collate() {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}

	public function esc( $value ) {
		return substr( $this->pdo->quote( (string) $value ), 1, -1 );
	}

	public function prepare( $query, ...$args ) {
		if ( isset( $args[0] ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$out = '';
		$i   = 0;
		$len = strlen( $query );
		for ( $p = 0; $p < $len; $p++ ) {
			$ch = $query[ $p ];
			if ( '%' === $ch && $p + 1 < $len && in_array( $query[ $p + 1 ], array( 's', 'd', 'f' ), true ) ) {
				$type = $query[ $p + 1 ];
				$val  = $args[ $i ] ?? null;
				$i++;
				if ( 's' === $type ) {
					$out .= "'" . $this->esc( (string) $val ) . "'";
				} elseif ( 'd' === $type ) {
					$out .= (string) (int) $val;
				} else {
					$out .= (string) (float) $val;
				}
				$p++;
				continue;
			}
			$out .= $ch;
		}
		return $out;
	}

	public function query( $sql ) {
		$this->queries[] = $sql;
		$n               = $this->pdo->exec( $sql );
		if ( false === $n ) {
			$this->last_error = implode( ' ', (array) $this->pdo->errorInfo() );
			return false;
		}
		$this->last_error = '';
		return $n;
	}

	public function get_row( $sql ) {
		$this->queries[] = $sql;
		$st              = $this->pdo->query( $sql );
		if ( ! $st ) {
			$this->last_error = implode( ' ', (array) $this->pdo->errorInfo() );
			return null;
		}
		$row = $st->fetch( PDO::FETCH_OBJ );
		return $row ? $row : null;
	}

	public function get_var( $sql ) {
		$this->queries[] = $sql;
		$st              = $this->pdo->query( $sql );
		if ( ! $st ) {
			$this->last_error = implode( ' ', (array) $this->pdo->errorInfo() );
			return null;
		}
		$v = $st->fetchColumn();
		return false === $v ? null : $v;
	}

	public function get_results( $sql ) {
		$this->queries[] = $sql;
		$st              = $this->pdo->query( $sql );
		return $st ? $st->fetchAll( PDO::FETCH_OBJ ) : array();
	}

	public function get_col( $sql ) {
		$this->queries[] = $sql;
		$st              = $this->pdo->query( $sql );
		return $st ? $st->fetchAll( PDO::FETCH_COLUMN ) : array();
	}

	public function insert( $table, $data, $format = null ) {
		$cols = array_keys( $data );
		$vals = array_map( array( $this, 'esc' ), array_values( $data ) );
		return $this->query( 'INSERT INTO ' . $table . ' (' . implode( ',', $cols ) . ') VALUES (\'' . implode( "','", $vals ) . '\')' );
	}

	public function update( $table, $data, $where, $f = null, $wf = null ) {
		$set = array();
		foreach ( $data as $k => $v ) {
			$set[] = $k . "='" . $this->esc( $v ) . "'";
		}
		$cond = array();
		foreach ( $where as $k => $v ) {
			$cond[] = $k . "='" . $this->esc( $v ) . "'";
		}
		return $this->query( 'UPDATE ' . $table . ' SET ' . implode( ',', $set ) . ' WHERE ' . implode( ' AND ', $cond ) );
	}

	public function delete( $table, $where, $format = null ) {
		$cond = array();
		foreach ( $where as $k => $v ) {
			$cond[] = $k . "='" . $this->esc( $v ) . "'";
		}
		return $this->query( 'DELETE FROM ' . $table . ' WHERE ' . implode( ' AND ', $cond ) );
	}
}

$GLOBALS['wpdb'] = new HSW_Prelive_MySQL_WPDB( $DB_DSN, $DB_USER, $DB_PASS );

/* Real DDL for dbDelta() so maybe_install() creates the real table. */
file_put_contents(
	ABSPATH . 'wp-admin/includes/upgrade.php',
	"<?php\nfunction dbDelta( \$sql ) { \$GLOBALS['wpdb']->query( \$sql ); return array(); }\n"
);
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

require_once dirname( __DIR__, 2 ) . '/includes/class-hsw-throttle.php';

function db_reset_table() {
	global $wpdb;
	$wpdb->query( 'DROP TABLE IF EXISTS wp_hsw_throttle' );
	$wpdb->query(
		'CREATE TABLE wp_hsw_throttle (
			bucket varchar(191) NOT NULL,
			hits int unsigned NOT NULL DEFAULT 0,
			expires bigint unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY (bucket),
			KEY expires (expires)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
	);
	HSW_Prelive_Store::write( 'options', 'hsw_throttle_db', '1' );
}

function db_rows() {
	global $wpdb;
	return $wpdb->get_results( 'SELECT bucket, hits, expires FROM wp_hsw_throttle' );
}

/* ---------------------------------------------------------------------
 * Worker: one racing process
 * ------------------------------------------------------------------ */

if ( isset( $argv[1] ) && 0 === strpos( $argv[1], '--worker=' ) ) {
	$mode = substr( $argv[1], 9 );
	$opt  = array();
	foreach ( array_slice( $argv, 2 ) as $arg ) {
		if ( 0 === strpos( $arg, '--' ) && false !== strpos( $arg, '=' ) ) {
			list( $k, $v ) = explode( '=', substr( $arg, 2 ), 2 );
			$opt[ $k ]     = $v;
		}
	}
	if ( isset( $opt['at'] ) ) {
		$wait = (float) $opt['at'] - microtime( true );
		if ( $wait > 0 ) {
			usleep( (int) ( $wait * 1000000 ) );
		}
	}
	$bucket = $opt['bucket'] ?? 'w';
	$limit  = (int) ( $opt['limit'] ?? 1 );

	if ( 'hit' === $mode ) {
		$r = HSW_Throttle::hit( $bucket, $limit, 60 );
		echo $r['allowed'] ? 'ALLOWED' : 'DENIED';
	} elseif ( 'claim' === $mode ) {
		$r = HSW_Throttle::hit( $bucket, 1, 60 );
		echo $r['allowed'] ? 'WON' : 'LOST';
	}
	exit( 0 );
}

/* ---------------------------------------------------------------------
 * Runner
 * ------------------------------------------------------------------ */

function db_parallel( $n, $mode, $bucket, $limit ) {
	$cmd    = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --worker=' . $mode
		. ' --bucket=' . escapeshellarg( $bucket ) . ' --limit=' . (int) $limit
		. ' --at=' . ( microtime( true ) + 2.5 );
	$procs  = array();
	$pipes  = array();
	$output = array();
	for ( $i = 0; $i < $n; $i++ ) {
		$procs[ $i ] = proc_open( $cmd, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes[ $i ] );
	}
	foreach ( $procs as $i => $proc ) {
		$output[ $i ] = trim( (string) stream_get_contents( $pipes[ $i ][1] ) );
		fclose( $pipes[ $i ][1] );
		fclose( $pipes[ $i ][2] );
		proc_close( $proc );
	}
	return $output;
}

$results = array();
function db_case( $id, $title, $fn ) {
	global $results;
	try {
		$r = $fn();
	} catch ( Throwable $e ) {
		$r = array( false, 'EXCEPTION ' . $e->getMessage() );
	}
	$results[] = $r;
	printf( "[%s] %-5s %s — %s\n", $r[0] ? 'PASS' : 'FAIL', $id, $title, $r[1] );
}

db_case(
	'3.1',
	'30 parallel hit(limit 1) => exactly 1 allowed',
	static function () {
		db_reset_table();
		$out = db_parallel( 30, 'hit', 'race-one', 1 );
		$n   = count( array_filter( $out, static function ( $l ) { return 'ALLOWED' === $l; } ) );
		$rows = db_rows();
		$hits = $rows ? (int) $rows[0]->hits : 0;
		return array( 1 === $n, sprintf( 'allowed=%d (want 1), rows=%d, hits=%d', $n, count( (array) $rows ), $hits ) );
	}
);

db_case(
	'3.2',
	'30 parallel hit(limit 5) => exactly 5 allowed',
	static function () {
		db_reset_table();
		$out = db_parallel( 30, 'hit', 'race-five', 5 );
		$n   = count( array_filter( $out, static function ( $l ) { return 'ALLOWED' === $l; } ) );
		return array( 5 === $n, sprintf( 'allowed=%d (want 5)', $n ) );
	}
);

db_case(
	'3.3',
	'table DROPPED => fail closed (allowed=false, retry_after=60)',
	static function () {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS wp_hsw_throttle' );
		$r = HSW_Throttle::hit( 'no-table', 5, 60 );
		return array(
			false === $r['allowed'] && 60 === (int) $r['retry_after'],
			sprintf( 'allowed=%s retry_after=%d', var_export( $r['allowed'], true ), $r['retry_after'] )
		);
	}
);

db_case(
	'3.4',
	'10 parallel claims on one code => exactly one winner',
	static function () {
		db_reset_table();
		$out = db_parallel( 10, 'claim', 'code-abc|hash', 1 );
		$n   = count( array_filter( $out, static function ( $l ) { return 'WON' === $l; } ) );
		return array( 1 === $n, sprintf( 'winners=%d (want 1)', $n ) );
	}
);

db_case(
	'3.5',
	'after a claim, the same bucket denies a replay (single-use)',
	static function () {
		db_reset_table();
		$a = HSW_Throttle::hit( 'replay', 1, 60 );
		$b = HSW_Throttle::hit( 'replay', 1, 60 );
		return array( $a['allowed'] && ! $b['allowed'], sprintf( 'first=%s second=%s', $a['allowed'] ? 'allowed' : 'denied', $b['allowed'] ? 'allowed' : 'denied' ) );
	}
);

$failed = 0;
foreach ( $results as $r ) {
	$failed += $r[0] ? 0 : 1;
}
printf( "\nDB: %d cases, %d failed\n", count( $results ), $failed );
exit( $failed ? 1 : 0 );
