<?php
/**
 * Pre-live fix session — WordPress/WooCommerce stub layer.
 *
 * NOT part of the plugin; QA-only; the release ZIP excludes tests/ (.gitattributes export-ignore). It exists so every claim in
 * tests/qa/prelive.php has an executed result instead of a code reading:
 *
 *   - a file-backed store (flock) stands in for options/transients/usermeta,
 *     so several PHP processes can race the same state like they would
 *     against MySQL;
 *   - a tiny $wpdb emulates exactly the statements HSW_Throttle issues
 *     (INSERT … ON DUPLICATE KEY UPDATE / SELECT / DELETE) under the same
 *     lock, which reproduces the unique-key atomicity of the real table;
 *   - wp_send_json_* throw instead of exit, so a test can inspect the exact
 *     HTTP status + payload the customer would receive.
 *
 * Set HSW_PRELIVE_STATE to a file path to share the store between processes.
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', rtrim( sys_get_temp_dir(), '/\\' ) . '/hsw_prelive_abspath/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'OBJECT', 'OBJECT' );

if ( ! is_dir( ABSPATH . 'wp-admin/includes' ) ) {
	@mkdir( ABSPATH . 'wp-admin/includes', 0777, true );
}
/* dbDelta() is replaced by a spy so "did the installer run?" is observable.
 * Always rewritten: prelive-db.php swaps in a real-DDL version in the same path. */
file_put_contents( ABSPATH . 'wp-admin/includes/upgrade.php', "<?php\nfunction dbDelta( \$sql ) { \$GLOBALS['hsw_dbdelta'][] = \$sql; return array(); }\n" );

/* ---------------------------------------------------------------------
 * File-backed store
 * ------------------------------------------------------------------ */

final class HSW_Prelive_Store {

	private static $path = null;
	private static $data = null;

	private static function path() {
		if ( null === self::$path ) {
			$file       = getenv( 'HSW_PRELIVE_STATE' );
			self::$path = $file ? $file : sys_get_temp_dir() . '/hsw_prelive_' . getmypid() . '.json';
		}
		return self::$path;
	}

	public static function reset() {
		self::$data = null;
		@unlink( self::path() );
	}

	/** Open the store for exclusive access; Windows denies a second handle to a locked file, so retry. */
	private static function open( $mode = 'c+' ) {
		$file = self::path();
		for ( $try = 0; $try < 200; $try++ ) {
			$fh = @fopen( $file, $mode );
			if ( $fh ) {
				if ( @flock( $fh, LOCK_EX ) ) {
					return $fh;
				}
				fclose( $fh );
			}
			usleep( 2000 );
		}
		throw new RuntimeException( 'store lock timeout' );
	}

	/** Read-modify-write under an exclusive lock (stand-in for a DB transaction). */
	public static function mutate( callable $fn ) {
		$fh = self::open();
		$raw = stream_get_contents( $fh );
		$all = ( '' === $raw ) ? array() : json_decode( $raw, true );
		if ( ! is_array( $all ) ) {
			$all = array();
		}
		$result = $fn( $all );
		ftruncate( $fh, 0 );
		rewind( $fh );
		fwrite( $fh, json_encode( $all ) );
		fflush( $fh );
		flock( $fh, LOCK_UN );
		fclose( $fh );
		return $result;
	}

	public static function read( $section, $key, $default = null ) {
		$all = self::snapshot();
		return array_key_exists( $key, $all[ $section ] ?? array() ) ? $all[ $section ][ $key ] : $default;
	}

	public static function write( $section, $key, $value ) {
		self::mutate(
			static function ( &$all ) use ( $section, $key, $value ) {
				$all[ $section ][ $key ] = $value;
			}
		);
	}

	public static function delete( $section, $key ) {
		self::mutate(
			static function ( &$all ) use ( $section, $key ) {
				unset( $all[ $section ][ $key ] );
			}
		);
	}

	public static function snapshot() {
		if ( ! is_file( self::path() ) ) {
			return array();
		}
		$fh = self::open();
		$raw = stream_get_contents( $fh );
		flock( $fh, LOCK_UN );
		fclose( $fh );
		$all = ( '' === $raw ) ? array() : json_decode( $raw, true );
		return is_array( $all ) ? $all : array();
	}
}

/* ---------------------------------------------------------------------
 * $wpdb — only the statements HSW_Throttle actually issues
 * ------------------------------------------------------------------ */

class HSW_Prelive_WPDB {

	public $prefix = 'wp_';
	public $options = 'wp_options';
	public $usermeta = 'wp_usermeta';
	public $posts = 'wp_posts';
	public $postmeta = 'wp_postmeta';
	public $last_error = '';
	public $insert_id = 0;
	public $queries = array();
	/** Emulates MySQL's connection-local LAST_INSERT_ID(expr). */
	public $last_insert = 0;

	public function get_charset_collate() {
		return 'DEFAULT CHARACTER SET utf8mb4';
	}

	public function esc( $value ) {
		return addslashes( (string) $value );
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
		$this->queries[]   = $sql;
		$this->last_insert = 0;

		/* INSERT … ON DUPLICATE KEY UPDATE hits = LAST_INSERT_ID(IF(expires > $now, hits + 1, 1)), … */
		if ( preg_match( '/INSERT INTO\s+\S*hsw_throttle\s*\(bucket, hits, expires\)\s*VALUES\s*\(\s*\'((?:[^\'\\\\]|\\\\.)*)\',\s*[^,]+,\s*(\d+)\s*\).*?hits\s*=\s*(?:LAST_INSERT_ID\()?IF\(expires > (\d+),\s*hits \+ 1,\s*1\).*?expires\s*=\s*IF\(expires > (\d+),\s*expires,\s*(\d+)\)/is', $sql, $m ) ) {
			$bucket = stripslashes( $m[1] );
			$exp    = (int) $m[2];
			$now    = (int) $m[3];
			$exp2   = (int) $m[5];
			HSW_Prelive_Store::mutate(
				static function ( &$all ) use ( $bucket, $exp, $now, $exp2 ) {
					$row = $all['throttle'][ $bucket ] ?? null;
					if ( null === $row ) {
						$all['throttle'][ $bucket ] = array( 'hits' => 1, 'expires' => $exp );
						return;
					}
					$all['throttle'][ $bucket ] = array(
						'hits'    => ( (int) $row['expires'] > $now ) ? ( (int) $row['hits'] + 1 ) : 1,
						'expires' => ( (int) $row['expires'] > $now ) ? (int) $row['expires'] : $exp2,
					);
				}
			);
			$row               = HSW_Prelive_Store::read( 'throttle', $bucket );
			$this->last_insert = is_array( $row ) ? (int) $row['hits'] : 1;
			return 1;
		}

		if ( preg_match( "/DELETE FROM\s+\S*hsw_throttle\s+WHERE expires < (\d+)/i", $sql, $m ) ) {
			$cut = (int) $m[1];
			return HSW_Prelive_Store::mutate(
				static function ( &$all ) use ( $cut ) {
					$n = 0;
					foreach ( (array) ( $all['throttle'] ?? array() ) as $bucket => $row ) {
						if ( (int) $row['expires'] < $cut ) {
							unset( $all['throttle'][ $bucket ] );
							$n++;
						}
					}
					return $n;
				}
			);
		}

		if ( preg_match( '/^\s*START TRANSACTION|^\s*COMMIT|^\s*ROLLBACK/i', $sql ) ) {
			return true;
		}

		$this->last_error = '';
		return 0;
	}

	public function get_row( $sql ) {
		$this->queries[] = $sql;
		if ( preg_match( "/SELECT hits, expires FROM\s+\S*hsw_throttle\s+WHERE bucket = '((?:[^'\\\\]|\\\\.)*)'/i", $sql, $m ) ) {
			$row = HSW_Prelive_Store::read( 'throttle', stripslashes( $m[1] ) );
			return null === $row ? null : (object) $row;
		}
		return null;
	}

	public function get_var( $sql ) {
		$this->queries[] = $sql;
		if ( false !== stripos( $sql, 'LAST_INSERT_ID()' ) ) {
			return (string) $this->last_insert;
		}
		if ( preg_match( "/SELECT expires FROM\s+\S*hsw_throttle\s+WHERE bucket = '((?:[^'\\\\]|\\\\.)*)'/i", $sql, $m ) ) {
			$row = HSW_Prelive_Store::read( 'throttle', stripslashes( $m[1] ) );
			return $row ? (string) $row['expires'] : null;
		}
		return null;
	}

	public function get_results( $sql ) {
		$this->queries[] = $sql;
		return array();
	}

	public function get_col( $sql ) {
		$this->queries[] = $sql;
		return array();
	}

	public function insert( $table, $data, $format = null ) {
		$this->queries[] = 'INSERT ' . $table;
		return 1;
	}

	public function update( $table, $data, $where, $f = null, $wf = null ) {
		$this->queries[] = 'UPDATE ' . $table;
		return 1;
	}

	public function delete( $table, $where, $format = null ) {
		$this->queries[] = 'DELETE ' . $table;
		return 1;
	}
}

$GLOBALS['wpdb'] = new HSW_Prelive_WPDB();

/* ---------------------------------------------------------------------
 * WordPress functions
 * ------------------------------------------------------------------ */

$GLOBALS['hsw_hooks']     = array();
$GLOBALS['hsw_filters']   = array();
$GLOBALS['hsw_did']       = array();
$GLOBALS['hsw_mail']      = array();
$GLOBALS['hsw_dbdelta']   = array();
$GLOBALS['hsw_redirects'] = array();

function add_action( $hook, $cb, $prio = 10, $args = 1 ) {
	$GLOBALS['hsw_hooks'][ $hook ][] = $cb;
}
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) {
	$GLOBALS['hsw_filters'][ $hook ][] = $cb;
}
function remove_filter( $hook, $cb, $prio = 10 ) {
	if ( ! empty( $GLOBALS['hsw_filters'][ $hook ] ) ) {
		foreach ( $GLOBALS['hsw_filters'][ $hook ] as $i => $existing ) {
			if ( $existing === $cb ) {
				unset( $GLOBALS['hsw_filters'][ $hook ][ $i ] );
			}
		}
	}
}
function apply_filters( $hook, $value, ...$args ) {
	foreach ( (array) ( $GLOBALS['hsw_filters'][ $hook ] ?? array() ) as $cb ) {
		$value = call_user_func_array( $cb, array_merge( array( $value ), $args ) );
	}
	return $value;
}
function do_action( $hook, ...$args ) {
	$GLOBALS['hsw_did'][ $hook ] = ( $GLOBALS['hsw_did'][ $hook ] ?? 0 ) + 1;
	foreach ( (array) ( $GLOBALS['hsw_hooks'][ $hook ] ?? array() ) as $cb ) {
		call_user_func_array( $cb, $args );
	}
}
function did_action( $hook ) {
	return $GLOBALS['hsw_did'][ $hook ] ?? 0;
}

function __( $text, $domain = null ) { return $text; }
function _e( $text, $domain = null ) { echo $text; }
function esc_html__( $text, $domain = null ) { return $text; }
function esc_attr__( $text, $domain = null ) { return $text; }
function esc_html_e( $text, $domain = null ) { echo $text; }
function _x( $text, $ctx = null, $domain = null ) { return $text; }
function _n( $single, $plural, $n, $domain = null ) { return 1 === (int) $n ? $single : $plural; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_url( $url ) { return (string) $url; }
function esc_url_raw( $url ) { return (string) $url; }
function esc_textarea( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
function wp_specialchars_decode( $text, $quote_style = ENT_QUOTES ) { return htmlspecialchars_decode( (string) $text, $quote_style ); }
function sanitize_text_field( $text ) { return trim( preg_replace( '/[\r\n\t]+/', ' ', strip_tags( (string) $text ) ) ); }
function sanitize_textarea_field( $text ) { return trim( (string) $text ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function sanitize_email( $email ) { return trim( (string) $email ); }
function sanitize_user( $user, $strict = false ) { return preg_replace( '/[^a-zA-Z0-9_.@-]/', '', (string) $user ); }
function is_email( $email ) { return (bool) filter_var( (string) $email, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $value ) { return is_string( $value ) ? stripslashes( $value ) : $value; }
function esc_sql( $value ) { return addslashes( (string) $value ); }
function wp_json_encode( $data ) { return json_encode( $data ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_rand( $min = 0, $max = 0 ) { return random_int( (int) $min, (int) $max ); }
function wp_generate_password( $length = 12, $special = true, $extra = false ) {
	$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	$out   = '';
	for ( $i = 0; $i < $length; $i++ ) {
		$out .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
	}
	return $out;
}
function wp_hash_password( $password ) { return password_hash( (string) $password, PASSWORD_BCRYPT, array( 'cost' => 4 ) ); }
function wp_check_password( $password, $hash ) { return password_verify( (string) $password, (string) $hash ); }
function current_time( $type = 'mysql' ) { return 'timestamp' === $type ? time() : gmdate( 'Y-m-d H:i:s' ); }
function wp_salt( $scheme = 'auth' ) { return 'prelive-salt'; }
function wp_hash( $data, $scheme = 'auth' ) { return hash_hmac( 'md5', (string) $data, 'prelive-salt|' . $scheme ); }

function wp_update_user( $data ) {
	$data = (array) $data;
	$uid  = isset( $data['ID'] ) ? (int) $data['ID'] : 0;
	if ( ! $uid || ! get_user_by( 'id', $uid ) ) {
		return new WP_Error( 'invalid_user_id', 'no such user' );
	}
	if ( isset( $data['user_email'] ) ) {
		$other = email_exists( $data['user_email'] );
		if ( $other && (int) $other !== $uid ) {
			return new WP_Error( 'existing_user_email', 'email taken' );
		}
		HSW_Prelive_Store::mutate(
			static function ( &$all ) use ( $uid, $data ) {
				foreach ( $all['users']['all'] as $i => $row ) {
					if ( (int) $row['ID'] === $uid ) {
						$all['users']['all'][ $i ]['user_email'] = (string) $data['user_email'];
					}
				}
			}
		);
	}
	return $uid;
}
function wp_create_nonce( $action = -1 ) { return substr( md5( 'nonce|' . $action ), 0, 10 ); }
function wp_verify_nonce( $nonce, $action = -1 ) { return wp_create_nonce( $action ) === $nonce ? 1 : false; }
function check_ajax_referer( $action = -1, $name = 'nonce', $die = true ) { return true; }
function check_admin_referer( $action = -1, $name = '_wpnonce' ) { return true; }
function wp_safe_redirect( $url, $status = 302 ) {
	$GLOBALS['hsw_redirects'][] = $url;
	throw new HSW_Prelive_Redirect( $url );
}
function wp_validate_redirect( $location, $fallback = '' ) {
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$to   = wp_parse_url( (string) $location, PHP_URL_HOST );
	if ( '' === (string) $location ) {
		return $fallback;
	}
	if ( $to && $host && strtolower( $to ) !== strtolower( $host ) ) {
		return $fallback;
	}
	return (string) $location;
}
function add_query_arg( $args, $url = '', $maybe_url = null ) {
	if ( null !== $maybe_url ) {
		/* add_query_arg( 'key', 'value', $url ) */
		$query = rawurlencode( (string) $args ) . '=' . rawurlencode( (string) $url );
		$url   = (string) $maybe_url;
	} elseif ( is_array( $args ) ) {
		$query = http_build_query( $args );
	} else {
		$query = (string) $args;
	}
	if ( '' === (string) $url ) {
		return $query;
	}
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $query;
}
function home_url( $path = '/' ) { return 'https://shop.example' . $path; }
function site_url( $path = '/' ) { return home_url( $path ); }
function admin_url( $path = '' ) { return 'https://shop.example/wp-admin/' . $path; }
function rest_url( $path = '' ) { return 'https://shop.example/wp-json/' . $path; }
function get_bloginfo( $what = 'name' ) { return 'Shop'; }
function get_option( $name, $default = false ) { return HSW_Prelive_Store::read( 'options', $name, $default ); }
function update_option( $name, $value, $autoload = null ) { HSW_Prelive_Store::write( 'options', $name, $value ); return true; }
function delete_option( $name ) { HSW_Prelive_Store::delete( 'options', $name ); return true; }
function get_transient( $name ) {
	$row = HSW_Prelive_Store::read( 'transients', $name );
	if ( ! is_array( $row ) ) {
		return false;
	}
	if ( isset( $row['expires'] ) && (int) $row['expires'] > 0 && (int) $row['expires'] < time() ) {
		HSW_Prelive_Store::delete( 'transients', $name );
		return false;
	}
	return $row['value'];
}
function set_transient( $name, $value, $ttl = 0 ) {
	HSW_Prelive_Store::write( 'transients', $name, array( 'value' => $value, 'expires' => $ttl ? time() + (int) $ttl : 0 ) );
	return true;
}
function delete_transient( $name ) { HSW_Prelive_Store::delete( 'transients', $name ); return true; }

/* Users ------------------------------------------------------------- */
function hsw_prelive_users() { return HSW_Prelive_Store::read( 'users', 'all', array() ); }
function hsw_prelive_set_users( $users ) { HSW_Prelive_Store::write( 'users', 'all', $users ); }

class WP_User {
	public $ID = 0;
	public $user_login = '';
	public $user_email = '';
	public $roles = array();
	public $first_name = '';
	public $last_name = '';
	public function __construct( $row = array() ) {
		foreach ( (array) $row as $k => $v ) {
			$this->$k = $v;
		}
	}
}

function get_user_by( $field, $value ) {
	$users = hsw_prelive_users();
	foreach ( $users as $user ) {
		if ( 'id' === $field && (int) $user['ID'] === (int) $value ) {
			return new WP_User( $user );
		}
		if ( 'email' === $field && strtolower( $user['user_email'] ) === strtolower( (string) $value ) ) {
			return new WP_User( $user );
		}
		if ( 'login' === $field && $user['user_login'] === (string) $value ) {
			return new WP_User( $user );
		}
	}
	return false;
}
function get_user_meta( $user_id, $key = '', $single = false ) {
	$meta = HSW_Prelive_Store::read( 'usermeta', (string) $user_id, array() );
	if ( '' === $key ) {
		return $meta;
	}
	return $meta[ $key ] ?? '';
}
function update_user_meta( $user_id, $key, $value ) {
	HSW_Prelive_Store::mutate(
		static function ( &$all ) use ( $user_id, $key, $value ) {
			$all['usermeta'][ (string) $user_id ][ $key ] = $value;
		}
	);
	return true;
}
function delete_user_meta( $user_id, $key ) {
	HSW_Prelive_Store::mutate(
		static function ( &$all ) use ( $user_id, $key ) {
			unset( $all['usermeta'][ (string) $user_id ][ $key ] );
		}
	);
	return true;
}
function email_exists( $email ) {
	$user = get_user_by( 'email', $email );
	return $user ? (int) $user->ID : false;
}
function username_exists( $login ) {
	$user = get_user_by( 'login', $login );
	return $user ? (int) $user->ID : false;
}
function wc_create_new_customer( $email, $username = '', $password = '', $args = array() ) {
	$id    = (int) ( HSW_Prelive_Store::read( 'seq', 'user', 0 ) ) + 1;
	HSW_Prelive_Store::write( 'seq', 'user', $id );
	$users   = hsw_prelive_users();
	$users[] = array(
		'ID'         => $id,
		'user_login' => '' !== $username ? $username : 'user' . $id,
		'user_email' => (string) $email,
		'roles'      => array( 'customer' ),
		'first_name' => $args['first_name'] ?? '',
		'last_name'  => $args['last_name'] ?? '',
	);
	hsw_prelive_set_users( $users );
	return $id;
}
function user_can( $user, $cap ) {
	if ( is_object( $user ) && isset( $user->roles ) ) {
		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return true;
		}
		return in_array( $cap, array( 'read' ), true );
	}
	return false;
}
function current_user_can( $cap ) {
	if ( isset( $GLOBALS['hsw_caps'][ $cap ] ) ) {
		return (bool) $GLOBALS['hsw_caps'][ $cap ];
	}
	$user = get_user_by( 'id', get_current_user_id() );
	return $user ? user_can( $user, $cap ) : false;
}
function WC() {
	return isset( $GLOBALS['hsw_wc'] ) ? $GLOBALS['hsw_wc'] : null;
}
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( (array) $defaults, (array) $args );
}
function is_user_logged_in() { return get_current_user_id() > 0; }
function get_current_user_id() { return (int) ( $GLOBALS['hsw_current_user'] ?? 0 ); }
function wp_set_current_user( $id ) { $GLOBALS['hsw_current_user'] = (int) $id; return get_user_by( 'id', $id ); }
function wp_get_current_user() { return get_user_by( 'id', get_current_user_id() ); }
function wp_set_auth_cookie( $id, $remember = false, $secure = false, $token = '' ) {
	$GLOBALS['hsw_auth_cookie'] = (int) $id;
	$token                      = '' !== $token ? (string) $token : 'tok' . bin2hex( random_bytes( 6 ) );
	$GLOBALS['hsw_issued_token'] = $token;
	/* WordPress fires this from set_auth_cookie() — before wp_login. */
	do_action( 'set_auth_cookie', 'cookievalue', 0, 0, (int) $id, $secure ? 'secure_auth' : 'auth', $token );
}
function wp_clear_auth_cookie() { $GLOBALS['hsw_current_user'] = 0; }
function wp_get_session_token() { return (string) ( $GLOBALS['hsw_issued_token'] ?? '' ); }

class WP_Session_Tokens {
	private static $instances = array();
	private $uid;
	private function __construct( $uid ) { $this->uid = (int) $uid; }
	public static function get_instance( $user_id ) {
		$user_id = (int) $user_id;
		if ( ! isset( self::$instances[ $user_id ] ) ) {
			self::$instances[ $user_id ] = new self( $user_id );
		}
		return self::$instances[ $user_id ];
	}
	public function destroy( $token ) {
		$GLOBALS['hsw_destroyed'][ $this->uid ][] = (string) $token;
		return true;
	}
}
function wp_login_url( $redirect = '' ) { return 'https://shop.example/wp-login.php'; }
function is_ssl() { return true; }

function wp_mail( $to, $subject, $message, $headers = array() ) {
	if ( ! empty( $GLOBALS['hsw_mail_fail'] ) ) {
		return false;
	}
	$GLOBALS['hsw_mail'][] = array( 'to' => $to, 'subject' => $subject, 'message' => $message );
	return true;
}

function wc_get_page_permalink( $page ) { return 'https://shop.example/' . $page . '/'; }
function wc_get_page_id( $page ) {
	$map = isset( $GLOBALS['hsw_wc_page_ids'] ) ? (array) $GLOBALS['hsw_wc_page_ids'] : array();
	return isset( $map[ $page ] ) ? (int) $map[ $page ] : 0;
}
function wc_get_checkout_url() { return 'https://shop.example/checkout/'; }
function wc_format_coupon_code( $code ) { return strtolower( (string) $code ); }
function wc_clean( $value ) { return is_string( $value ) ? trim( strip_tags( $value ) ) : $value; }
function wc_price( $amount ) { return number_format( (float) $amount ); }
function wc_add_notice( $message, $type = 'success' ) { $GLOBALS['hsw_notices'][] = array( $message, $type ); }

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
	public function add_data( $data ) { $this->data = $data; }
}
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

class HSW_Prelive_Json extends Exception {
	public $ok;
	public $data;
	public $status;
	public function __construct( $ok, $data, $status ) {
		parent::__construct( 'json' );
		$this->ok     = $ok;
		$this->data   = $data;
		$this->status = $status;
	}
}
class HSW_Prelive_Redirect extends Exception {
	public $url;
	public function __construct( $url ) {
		parent::__construct( 'redirect' );
		$this->url = $url;
	}
}

function wp_send_json_success( $data = null, $status = 200 ) { throw new HSW_Prelive_Json( true, $data, $status ); }
function wp_send_json_error( $data = null, $status = 200 ) { throw new HSW_Prelive_Json( false, $data, $status ); }
