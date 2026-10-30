<?php
/**
 * Atomic counters for the login throttles.
 *
 * OTP endpoints are the one place in this plugin an anonymous visitor can make
 * the site send a message to an arbitrary phone number. Transients are not good
 * enough for that: `get_transient()` + `set_transient()` is a read-modify-write
 * race, so a burst of parallel requests all read "0 attempts" and all get
 * through.
 *
 * A single-row upsert is atomic in MySQL, so the counters live in a tiny table:
 *
 *   INSERT … ON DUPLICATE KEY UPDATE hits = IF(expires > NOW, hits + 1, 1)
 *
 * A persistent object cache would also work (`wp_cache_incr`), but it is not
 * guaranteed to be atomic on every backend and it disappears on flush — the
 * table is one indexed write on a login attempt, which is cheap enough to be
 * worth the certainty.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Throttle {

	const DB_VERSION = '1';

	/** Daily cleanup hook for expired buckets. */
	const PRUNE_HOOK = 'hsw_throttle_prune';

	/** @var HSW_Throttle|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'maybe_install' ), 5 );
		add_action( self::PRUNE_HOOK, array( __CLASS__, 'prune' ) );
		add_action( 'init', array( $this, 'maybe_schedule' ), 22 );
	}

	/**
	 * One daily event. The table is keyed by bucket, so it does not grow per
	 * request — but every distinct IP or phone leaves a row behind forever,
	 * which on a busy shop is a slow leak. Rows older than a day are useless:
	 * every window in this class is measured in minutes or hours.
	 */
	public function maybe_schedule() {
		if ( wp_next_scheduled( self::PRUNE_HOOK ) ) {
			return;
		}
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::PRUNE_HOOK );
	}

	/**
	 * Delete buckets that expired more than a day ago.
	 *
	 * @return int Rows removed.
	 */
	public static function prune() {
		global $wpdb;

		$table = self::table();
		$cut   = time() - DAY_IN_SECONDS;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE expires < %d", $cut ) );

		return (int) $deleted;
	}

	/**
	 * @return string
	 */
	private static function table() {
		global $wpdb;

		return $wpdb->prefix . 'hsw_throttle';
	}

	/**
	 * Create the table once per schema version.
	 */
	public function maybe_install() {
		if ( self::DB_VERSION === get_option( 'hsw_throttle_db' ) ) {
			return;
		}

		global $wpdb;
		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			"CREATE TABLE {$table} (
				bucket varchar(191) NOT NULL,
				hits int unsigned NOT NULL DEFAULT 0,
				expires bigint unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (bucket),
				KEY expires (expires)
			) {$collate};"
		);

		update_option( 'hsw_throttle_db', self::DB_VERSION, false );
	}

	/**
	 * Count one hit and report whether the caller is still under the limit.
	 *
	 * @param string $bucket Logical counter name, e.g. `send_ip:1.2.3.4`.
	 * @param int    $limit  Maximum hits allowed inside the window.
	 * @param int    $window Window length in seconds.
	 * @return array{allowed:bool,hits:int,retry_after:int}
	 */
	public static function hit( $bucket, $limit, $window ) {
		global $wpdb;

		$bucket  = substr( (string) $bucket, 0, 191 );
		$limit   = max( 1, (int) $limit );
		$window  = max( 1, (int) $window );
		$now     = time();
		$expires = $now + $window;
		$table   = self::table();

		/* phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared */
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (bucket, hits, expires) VALUES (%s, 1, %d)
				 ON DUPLICATE KEY UPDATE
				   hits = IF(expires > %d, hits + 1, 1),
				   expires = IF(expires > %d, expires, %d)",
				$bucket,
				$expires,
				$now,
				$now,
				$expires
			)
		);

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT hits, expires FROM {$table} WHERE bucket = %s", $bucket )
		);

		$hits    = $row ? (int) $row->hits : 1;
		$expiry  = $row ? (int) $row->expires : $expires;
		$allowed = $hits <= $limit;

		/* 1-in-50 chance: drop expired rows so the table cannot grow forever. */
		if ( 1 === wp_rand( 1, 50 ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE expires < %d", $now ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		return array(
			'allowed'     => $allowed,
			'hits'        => $hits,
			'retry_after' => $allowed ? 0 : max( 1, $expiry - $now ),
		);
	}

	/**
	 * Current counter without incrementing it.
	 *
	 * @param string $bucket Counter name.
	 * @return array{hits:int,retry_after:int}
	 */
	public static function peek( $bucket ) {
		global $wpdb;
		$table = self::table();
		$now   = time();

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT hits, expires FROM {$table} WHERE bucket = %s", substr( (string) $bucket, 0, 191 ) )
		);

		if ( ! $row || (int) $row->expires < $now ) {
			return array(
				'hits'        => 0,
				'retry_after' => 0,
			);
		}

		return array(
			'hits'        => (int) $row->hits,
			'retry_after' => max( 0, (int) $row->expires - $now ),
		);
	}

	/**
	 * Reset a counter (successful verification, or an admin unlock).
	 *
	 * @param string $bucket Counter name.
	 */
	public static function clear( $bucket ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'bucket' => substr( (string) $bucket, 0, 191 ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Client IP, trusting forwarding headers only from a known proxy.
	 *
	 * `REMOTE_ADDR` is the only value an attacker cannot forge. A CDN sets
	 * `CF-Connecting-IP` / `X-Forwarded-For`, but those headers are also trivially
	 * spoofable when the request does *not* come through the CDN — so they are
	 * read only when the connecting address is inside a published CDN range.
	 *
	 * @return string
	 */
	public static function ip() {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';

		if ( self::is_trusted_proxy( $remote ) ) {
			foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR' ) as $header ) {
				if ( empty( $_SERVER[ $header ] ) ) {
					continue;
				}
				$value = (string) wp_unslash( $_SERVER[ $header ] );
				/* X-Forwarded-For is a list; the first entry is the client. */
				$first = trim( explode( ',', $value )[0] );
				if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
					return $first;
				}
			}
		}

		return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '0.0.0.0';
	}

	/**
	 * Is this address a CDN / reverse-proxy we accept headers from?
	 *
	 * Defaults to Cloudflare's published IPv4 ranges (stable, published at
	 * cloudflare.com/ips-v4). Extend with the `hsw_trusted_proxies` filter.
	 *
	 * @param string $ip Address.
	 * @return bool
	 */
	private static function is_trusted_proxy( $ip ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return false;
		}

		$ranges = (array) apply_filters(
			'hsw_trusted_proxies',
			array(
				'173.245.48.0/20',
				'103.21.244.0/22',
				'103.22.200.0/22',
				'103.31.4.0/22',
				'141.101.64.0/18',
				'108.162.192.0/18',
				'190.93.240.0/20',
				'188.114.96.0/20',
				'197.234.240.0/22',
				'198.41.128.0/17',
				'162.158.0.0/15',
				'104.16.0.0/13',
				'104.24.0.0/14',
				'172.64.0.0/13',
				'131.0.72.0/22',
			)
		);

		$ip_long = ip2long( $ip );

		foreach ( $ranges as $cidr ) {
			$parts = explode( '/', (string) $cidr );
			if ( 2 !== count( $parts ) ) {
				continue;
			}
			$net   = ip2long( $parts[0] );
			$bits  = (int) $parts[1];
			if ( false === $net || $bits < 0 || $bits > 32 ) {
				continue;
			}
			$mask = -1 << ( 32 - $bits );
			if ( ( $ip_long & $mask ) === ( $net & $mask ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A limit from the auth settings with a hard-coded fallback.
	 *
	 * @param string $key     Setting key inside the auth group.
	 * @param int    $default Fallback.
	 * @return int
	 */
	public static function setting( $key, $default ) {
		$account = class_exists( 'HSW_Account' ) ? HSW_Account::instance() : null;
		$value   = $account ? absint( $account->setting( 'auth.' . $key, $default ) ) : 0;

		return $value > 0 ? $value : (int) $default;
	}
}
