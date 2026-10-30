<?php
/**
 * Store wallet.
 *
 * Design notes — the reason this is a class and not a pile of helpers:
 *
 * 1. **The balance is user meta, not a SUM().** Reading a balance happens on
 *    every account page and every checkout that might offer wallet payment.
 *    `SUM(amount)` over a ledger grows with history; `get_user_meta()` is a
 *    single indexed row that WordPress usually has cached already.
 *
 * 2. **Credits are a single UPDATE, not read-modify-write.** Two simultaneous
 *    credits (customer top-up + admin bonus) must not lose one of them, so the
 *    arithmetic happens inside MySQL:
 *
 *        UPDATE usermeta SET meta_value = meta_value + %f WHERE …
 *
 * 3. **The ledger is append-only and only read when someone looks at it.**
 *    It exists for statements and audits, indexed by (user_id, created_at), and
 *    nothing on a normal page load touches it.
 *
 * 4. **Points are derived, so spending them needs a counter.** `loyalty_points()`
 *    computes points from lifetime spend; converting them to money records how
 *    many were used in `_hsw_points_used` and credits the difference.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Wallet {

	const DB_VERSION   = '5';
	const BALANCE_META = '_hsw_wallet_balance';
	const POINTS_META  = '_hsw_points_used';

	/** @var HSW_Wallet|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'maybe_install' ), 5 );
		add_action( 'admin_menu', array( $this, 'menu' ), 14 );
		add_action( 'admin_post_hsw_wallet_action', array( $this, 'handle_admin_action' ) );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/* ---------------------------------------------------------------------
	 * Customer REST
	 * ------------------------------------------------------------------ */

	public function routes() {
		$auth = function () {
			return is_user_logged_in();
		};

		register_rest_route(
			'hsw/v1',
			'/account/wallet/topup',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_topup' ),
				'permission_callback' => $auth,
				'args'                => array(
					'amount' => array( 'sanitize_callback' => 'absint' ),
					'note'   => array( 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);

		/*
		 * Pay for a top-up through the shop's own gateway. Returns the URL of
		 * the WooCommerce order-pay screen, where the chosen gateway takes over.
		 */
		register_rest_route(
			'hsw/v1',
			'/account/wallet/pay',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_pay' ),
				'permission_callback' => $auth,
				'args'                => array(
					'amount'  => array( 'sanitize_callback' => 'absint' ),
					'gateway' => array( 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);

		register_rest_route(
			'hsw/v1',
			'/account/wallet/convert',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_convert' ),
				'permission_callback' => $auth,
				'args'                => array(
					'points' => array( 'sanitize_callback' => 'absint' ),
				),
			)
		);
	}

	public function rest_topup( $request ) {
		$result = self::request_topup(
			get_current_user_id(),
			(float) $request->get_param( 'amount' ),
			(string) $request->get_param( 'note' )
		);

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 400 );
		}

		return rest_ensure_response(
			array(
				'ok'      => true,
				'id'      => $result,
				'message' => __( 'درخواست شارژ ثبت شد. پس از تایید، موجودی اضافه می‌شود.', 'hamishop-elementor-widgets' ),
			)
		);
	}

	public function rest_pay( $request ) {
		if ( ! class_exists( 'HSW_Wallet_Topup' ) ) {
			return new WP_REST_Response( array( 'message' => __( 'شارژ آنلاین در دسترس نیست.', 'hamishop-elementor-widgets' ) ), 400 );
		}

		$order_id = HSW_Wallet_Topup::create_order(
			get_current_user_id(),
			(float) $request->get_param( 'amount' ),
			(string) $request->get_param( 'gateway' )
		);

		if ( is_wp_error( $order_id ) ) {
			return new WP_REST_Response( array( 'message' => $order_id->get_error_message() ), 400 );
		}

		$order = wc_get_order( $order_id );

		return rest_ensure_response(
			array(
				'ok'       => true,
				'order'    => $order_id,
				'redirect' => $order ? $order->get_checkout_payment_url() : '',
				'message'  => __( 'در حال انتقال به درگاه پرداخت…', 'hamishop-elementor-widgets' ),
			)
		);
	}

	public function rest_convert( $request ) {
		$result = self::convert_points( get_current_user_id(), (int) $request->get_param( 'points' ) );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 400 );
		}

		return rest_ensure_response(
			array(
				'ok'      => true,
				'balance' => $result,
				'message' => __( 'امتیازها به کیف پول منتقل شد.', 'hamishop-elementor-widgets' ),
			)
		);
	}

	/**
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'hsw_wallet_ledger';
	}

	/**
	 * Balance table: one row per user, `user_id` is the primary key.
	 *
	 * The balance used to live in `usermeta`. That table has **no unique index**
	 * on `(user_id, meta_key)`, and `add_user_meta( …, true )` only *checks*
	 * before inserting — so N parallel first-time credits created N rows of the
	 * same key. `get_user_meta()` returns the first one, which meant the customer
	 * saw 100 while the statement said 1000 (reproduced with 10 processes).
	 * A primary key makes the write atomic and the read unambiguous.
	 *
	 * @return string
	 */
	public static function balance_table() {
		global $wpdb;

		return $wpdb->prefix . 'hsw_wallet';
	}

	public function maybe_install() {
		if ( self::DB_VERSION === get_option( 'hsw_wallet_db' ) ) {
			return;
		}

		global $wpdb;
		$table   = self::table();
		$balances = self::balance_table();
		$collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		/*
		 * `ref` used to be `NOT NULL DEFAULT ''` with no unique index, so the
		 * same top-up could be written twice. Before dbDelta adds the unique
		 * key, the column has to be nullable (many rows share ''), the empty
		 * values must become NULL (MySQL allows any number of NULLs in a unique
		 * index) and the existing duplicates must be moved out of the way. The
		 * extra rows are kept — they are part of the money history — but get a
		 * suffixed reference so exactly one row per real reference is claimable.
		 */
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			$wpdb->query( "ALTER TABLE {$table} MODIFY ref varchar(64) NULL DEFAULT NULL" ); // phpcs:ignore WordPress.DB
			$wpdb->query( "UPDATE {$table} SET ref = NULL WHERE ref = ''" ); // phpcs:ignore WordPress.DB
			$wpdb->query( // phpcs:ignore WordPress.DB
				"UPDATE {$table} SET ref = CONCAT(LEFT(ref, 50), '#dup', id)
				 WHERE ref IS NOT NULL
				   AND id NOT IN ( SELECT keep_id FROM ( SELECT MIN(id) keep_id FROM {$table} WHERE ref IS NOT NULL GROUP BY ref ) k )"
			);
		}

		dbDelta(
			"CREATE TABLE {$table} (
				id bigint unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint unsigned NOT NULL DEFAULT 0,
				amount decimal(18,2) NOT NULL DEFAULT 0,
				balance_after decimal(18,2) NOT NULL DEFAULT 0,
				kind varchar(32) NOT NULL DEFAULT 'credit',
				status varchar(16) NOT NULL DEFAULT 'done',
				note text NULL,
				ref varchar(64) NULL DEFAULT NULL,
				actor_id bigint unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY user_time (user_id, created_at),
				KEY status (status),
				UNIQUE KEY uniq_ref (ref)
			) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$balances} (
				user_id bigint unsigned NOT NULL,
				balance decimal(18,2) NOT NULL DEFAULT 0,
				points_used decimal(12,0) NOT NULL DEFAULT 0,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (user_id)
			) {$collate};"
		);

		/* One-time copy of the old points counter into the atomic column. */
		if ( 'yes' !== get_option( 'hsw_points_migrated' ) ) {
			$wpdb->query( // phpcs:ignore WordPress.DB
				"UPDATE {$balances} b
				 JOIN {$wpdb->usermeta} u ON u.user_id = b.user_id AND u.meta_key = '" . esc_sql( self::POINTS_META ) . "'
				 SET b.points_used = CAST(u.meta_value AS DECIMAL(12,0))
				 WHERE b.points_used = 0"
			);
			update_option( 'hsw_points_migrated', 'yes', false );
		}

		dbDelta(
			"CREATE TABLE " . self::backup_table() . " (
				meta_id bigint unsigned NOT NULL,
				user_id bigint unsigned NOT NULL DEFAULT 0,
				meta_value longtext NULL,
				taken_at datetime NOT NULL,
				PRIMARY KEY  (meta_id),
				KEY user_id (user_id)
			) {$collate};"
		);

		/*
		 * Only stamp the schema version when all three tables really exist.
		 * `dbDelta()` swallows server errors (zero-date defaults were rejected
		 * by NO_ZERO_DATE servers with "Invalid default value"), and a stamped
		 * version meant a broken install never retried. Without the stamp the
		 * next request tries again.
		 */
		$installed = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table
			&& $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $balances ) ) === $balances
			&& $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', self::backup_table() ) ) === self::backup_table();

		if ( ! $installed ) {
			error_log( 'HamiShop wallet: table creation failed; will retry on the next request.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			return;
		}

		self::migrate();
		self::register_cli();

		update_option( 'hsw_wallet_db', self::DB_VERSION, false );
	}

	/* ---------------------------------------------------------------------
	 * Migration from the legacy `_hsw_wallet_balance` meta
	 * ------------------------------------------------------------------ */

	/**
	 * Batch size when walking the user table. 200 users per step keeps the
	 * query fast on shared hosting (no `JOIN` on a million rows, no long
	 * `INSERT … SELECT`) and lets a timeout resume where it stopped.
	 */
	const MIGRATION_BATCH = 200;

	/** Option that holds the migration report. */
	const MIGRATION_REPORT = 'hsw_wallet_migration';

	/**
	 * Copy the legacy meta into the balance table.
	 *
	 * Rules, in order of importance:
	 *  - the legacy meta is **never modified or deleted**, so a rollback is
	 *    just dropping the rows of `{prefix}hsw_wallet` (kept for one release);
	 *  - every raw meta row is also copied to `{prefix}hsw_wallet_meta_backup`
	 *    so the exact pre-migration state survives even if something else
	 *    touches `usermeta`;
	 *  - a user that already has a row in the balance table is skipped, which
	 *    makes the migration idempotent: a second run cannot change a balance;
	 *  - when one user has several meta rows, the values are **summed** (the
	 *    statement shows each of them) and the user is listed in the report;
	 *  - a value that cannot be parsed contributes 0 and is reported, it is
	 *    never silently treated as a valid zero.
	 *
	 * @param int  $batch Rows per step.
	 * @param bool $force Ignore the "already done" flag.
	 * @return array{users:int,inserted:int,total_before:float,total_after:float,anomalies:int,done:bool}
	 */
	public static function migrate( $batch = self::MIGRATION_BATCH, $force = false ) {
		global $wpdb;

		$batch   = max( 1, min( 1000, (int) $batch ) );
		$report  = get_option( self::MIGRATION_REPORT, array() );
		$report  = is_array( $report ) ? $report : array();
		$started = ! empty( $report['done'] );

		if ( $started && ! $force ) {
			return array(
				'users'        => (int) ( $report['users'] ?? 0 ),
				'inserted'     => (int) ( $report['inserted'] ?? 0 ),
				'total_before' => (float) ( $report['total_before'] ?? 0 ),
				'total_after'  => (float) ( $report['total_after'] ?? 0 ),
				'anomalies'    => (int) ( $report['anomalies'] ?? 0 ),
				'done'         => true,
			);
		}

		if ( $force ) {
			/* A forced re-run starts from the beginning but still skips users
			 * that already have a balance row, so nothing is added twice. */
			$report  = array( 'last_user' => 0, 'anomaly_list' => array() );
			$started = false;
		}

		$table  = self::balance_table();
		$backup = self::backup_table();
		$state  = $started ? (int) ( $report['last_user'] ?? 0 ) : 0;
		$stats  = array(
			'users'        => (int) ( $report['users'] ?? 0 ),
			'inserted'     => (int) ( $report['inserted'] ?? 0 ),
			'total_before' => (float) ( $report['total_before'] ?? 0 ),
			'total_after'  => (float) ( $report['total_after'] ?? 0 ),
			'anomalies'    => (int) ( $report['anomalies'] ?? 0 ),
			'done'         => true,
			'last_user'    => $state,
			'anomaly_list' => (array) ( $report['anomaly_list'] ?? array() ),
			'ran_at'       => current_time( 'mysql' ),
		);

		do {
			$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB
				$wpdb->prepare(
					"SELECT user_id FROM {$wpdb->usermeta}
					 WHERE meta_key = %s AND user_id > %d
					 GROUP BY user_id ORDER BY user_id ASC LIMIT %d",
					self::BALANCE_META,
					$state,
					$batch
				)
			);

			if ( ! $ids ) {
				break;
			}

			$in  = implode( ',', array_map( 'absint', $ids ) );
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
				"SELECT umeta_id AS meta_id, user_id, meta_value FROM {$wpdb->usermeta}
				 WHERE meta_key = '" . esc_sql( self::BALANCE_META ) . "' AND user_id IN ({$in})
				 ORDER BY user_id ASC, umeta_id ASC"
			);

			$by_user = array();
			foreach ( $rows as $row ) {
				$by_user[ (int) $row->user_id ][] = $row;

				/* Verbatim backup, once per meta row. */
				$wpdb->query( // phpcs:ignore WordPress.DB
					$wpdb->prepare(
						"INSERT IGNORE INTO {$backup} (meta_id, user_id, meta_value, taken_at) VALUES (%d, %d, %s, %s)",
						(int) $row->meta_id,
						(int) $row->user_id,
						(string) $row->meta_value,
						current_time( 'mysql' )
					)
				);
			}

			foreach ( $by_user as $uid => $list ) {
				$values  = array();
				$sum     = 0.0;
				$flags   = array();

				foreach ( $list as $row ) {
					$raw      = (string) $row->meta_value;
					$values[] = $raw;
					$parsed   = self::parse_legacy_amount( $raw );

					if ( null === $parsed ) {
						$flags[] = 'non-numeric';
						continue;
					}
					if ( '' === trim( $raw ) ) {
						$flags[] = 'empty';
					}
					if ( preg_match( '/[۰-۹٠-٩]/u', $raw ) ) {
						$flags[] = 'persian-digits';
					}
					if ( false !== strpos( $raw, '.' ) ) {
						$flags[] = 'decimal';
					}
					$sum += $parsed;
				}

				if ( count( $list ) > 1 ) {
					$flags[] = 'duplicate-rows';
				}
				if ( count( array_unique( $values ) ) > 1 ) {
					/* Different values for the same user: this is exactly the
					 * state the old race left behind, and it must be visible. */
					$flags[] = 'duplicate-differing';
				}

				$sum = round( $sum, 2 );
				$stats['total_before'] += $sum;
				$stats['users']++;

				$inserted = $wpdb->query( // phpcs:ignore WordPress.DB
					$wpdb->prepare(
						"INSERT IGNORE INTO {$table} (user_id, balance, updated_at) VALUES (%d, %f, %s)",
						$uid,
						$sum,
						current_time( 'mysql' )
					)
				);

				if ( $inserted ) {
					$stats['inserted']++;
					$stats['total_after'] += $sum;

					/*
					 * Opening statement line. Without it every migrated user
					 * would look like a gap (`balance` set, ledger empty) and the
					 * gap report would flood. `migrate:<user>` is unique, so a
					 * re-run cannot add a second opening line.
					 */
					if ( abs( $sum ) >= 0.01 ) {
						$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
							self::table(),
							array(
								'user_id'       => $uid,
								'amount'        => $sum,
								'balance_after' => $sum,
								'kind'          => 'migrate',
								'status'        => 'done',
								'note'          => __( 'انتقال موجودی از متای قدیمی کیف پول', 'hamishop-elementor-widgets' ),
								'ref'           => 'migrate:' . $uid,
								'actor_id'      => 0,
								'created_at'    => current_time( 'mysql' ),
							),
							array( '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%d', '%s' )
						);
					}
				} else {
					/* The row already existed (re-run, or a balance was written
					 * after the migration started): trust the table. */
					$current = $wpdb->get_var( $wpdb->prepare( "SELECT balance FROM {$table} WHERE user_id = %d", $uid ) ); // phpcs:ignore WordPress.DB
					$stats['total_after'] += (float) $current;
				}

				if ( $flags ) {
					$stats['anomalies']++;
					if ( count( $stats['anomaly_list'] ) < 500 ) {
						$stats['anomaly_list'][] = array(
							'user_id' => $uid,
							'flags'   => array_values( array_unique( $flags ) ),
							'values'  => $values,
							'used'    => $sum,
						);
					}
				}
			}

			$state = (int) end( $ids );
			$stats['last_user'] = $state;

			/* Persist after every batch: a timeout or a killed request can
			 * resume instead of starting over. */
			update_option( self::MIGRATION_REPORT, $stats, false );
		} while ( count( $ids ) === $batch );

		$stats['done'] = true;
		update_option( self::MIGRATION_REPORT, $stats, false );

		if ( $stats['anomalies'] > 0 ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions
				sprintf(
					'HamiShop wallet migration: %d users carry unusual legacy values (duplicates / empty / non-numeric / persian digits). See option %s.',
					$stats['anomalies'],
					self::MIGRATION_REPORT
				)
			);
		}

		return array(
			'users'        => $stats['users'],
			'inserted'     => $stats['inserted'],
			'total_before' => round( $stats['total_before'], 2 ),
			'total_after'  => round( $stats['total_after'], 2 ),
			'anomalies'    => $stats['anomalies'],
			'done'         => true,
		);
	}

	/**
	 * @return bool
	 */
	public static function migration_done() {
		$report = get_option( self::MIGRATION_REPORT, array() );

		return is_array( $report ) && ! empty( $report['done'] );
	}

	/**
	 * Full migration report (counts + the anomaly list).
	 *
	 * @return array
	 */
	public static function migration_report() {
		$report = get_option( self::MIGRATION_REPORT, array() );

		return is_array( $report ) ? $report : array();
	}

	/**
	 * Turn a stored meta value into a number.
	 *
	 * Persian/Arabic digits and thousands separators are normalised; anything
	 * else that is not numeric returns null so the caller can report it instead
	 * of counting it as zero.
	 *
	 * @param string $raw Raw value.
	 * @return float|null
	 */
	public static function parse_legacy_amount( $raw ) {
		$raw = trim( (string) $raw );

		if ( '' === $raw ) {
			return 0.0;
		}

		$raw = strtr(
			$raw,
			array(
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
				'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
				'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
				'،' => '', ',' => '', ' ' => '',
			)
		);

		if ( ! is_numeric( $raw ) ) {
			return null;
		}

		return (float) $raw;
	}

	/**
	 * @return string
	 */
	public static function backup_table() {
		global $wpdb;

		return $wpdb->prefix . 'hsw_wallet_meta_backup';
	}

	/**
	 * Compare every wallet balance with its ledger and return the differences.
	 *
	 * `balance` is the source of truth for spending; the ledger is the audit
	 * trail. After a live run this is the check that proves the two agree
	 * (`wp hsw wallet report`).
	 *
	 * @param int $limit Max rows to return.
	 * @return array<int,array{user_id:int,balance:float,ledger:float,diff:float}>
	 */
	public static function balance_gaps( $limit = 200 ) {
		global $wpdb;

		$balances = self::balance_table();
		$ledger   = self::table();

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT b.user_id, b.balance, COALESCE(l.total, 0) AS ledger
				 FROM {$balances} b
				 LEFT JOIN (
					SELECT user_id, SUM(amount) AS total FROM {$ledger} WHERE status = 'done' GROUP BY user_id
				 ) l ON l.user_id = b.user_id
				 WHERE ABS(b.balance - COALESCE(l.total, 0)) > 0.009
				 ORDER BY ABS(b.balance - COALESCE(l.total, 0)) DESC
				 LIMIT %d",
				max( 1, min( 2000, (int) $limit ) )
			)
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'user_id' => (int) $row->user_id,
				'balance' => (float) $row->balance,
				'ledger'  => (float) $row->ledger,
				'diff'    => round( (float) $row->balance - (float) $row->ledger, 2 ),
			);
		}

		return $out;
	}

	/**
	 * Close the gaps found by `balance_gaps()`.
	 *
	 * The write order is: move the balance, then insert the ledger row. If PHP
	 * dies between the two (fatal, timeout, OOM, killed request) the customer
	 * keeps the new balance but the statement has no row for it. Repair writes
	 * the missing statement line (`kind = repair`) instead of touching the
	 * money: the movement already happened, and rewriting a balance to match a
	 * statement would take money away from a customer who may already have
	 * spent it.
	 *
	 * Each repair carries `repair:<user>:<gap>` as its reference, so a second
	 * run cannot add a second line for the same gap.
	 *
	 * @param int $limit Max users to repair per call.
	 * @return array<int,array{user_id:int,gap:float,repaired:bool}>
	 */
	public static function repair_gaps( $limit = 200 ) {
		global $wpdb;

		$out = array();

		foreach ( self::balance_gaps( $limit ) as $gap ) {
			$amount = round( (float) $gap['diff'], 2 );

			if ( abs( $amount ) < 0.01 ) {
				continue;
			}

			/*
			 * A POSITIVE gap means the balance moved without a statement line —
			 * the money is real, the repair writes the missing entry.
			 *
			 * A NEGATIVE gap means the statement shows money the balance never
			 * received — a lost credit (for example a top-up marked done by
			 * `decide()` right before the process died). Writing a repair row
			 * there would silently "reconcile" the customer's lost money away.
			 * Those are reported and left for a human: report first, confirm
			 * by hand, then fix with set_balance() if appropriate.
			 */
			if ( $amount < 0 ) {
				$out[] = array(
					'user_id'  => $gap['user_id'],
					'gap'      => $amount,
					'repaired' => false,
				);
				continue;
			}

			/*
			 * Ledger-only: the money already moved, so the repair writes the
			 * missing statement line and leaves the balance alone. A first
			 * version called `apply()`, which moved the balance *and* wrote
			 * the line — on a negative gap that took money from the customer.
			 * Never again. The reference carries the time so a gap of the
			 * same size recurring later cannot collide with the earlier
			 * repair's unique ref (which used to leave the gap unfixable
			 * forever).
			 */
			$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::table(),
				array(
					'user_id'       => $gap['user_id'],
					'amount'        => $amount,
					'balance_after' => $gap['balance'],
					'kind'          => 'repair',
					'status'        => 'done',
					'note'          => __( 'ترمیم خودکار دفتر کیف پول (اختلاف با موجودی)', 'hamishop-elementor-widgets' ),
					'ref'           => 'repair:' . $gap['user_id'] . ':' . number_format( $amount, 2, '.', '' ) . ':' . current_time( 'YmdHis' ),
					'actor_id'      => 0,
					'created_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%d', '%s' )
			);

			$out[] = array(
				'user_id'  => $gap['user_id'],
				'gap'      => $amount,
				'repaired' => (bool) $inserted,
			);
		}

		return $out;
	}

	/**
	 * WP-CLI: `wp hsw wallet migrate` and `wp hsw wallet report`.
	 */
	public static function register_cli() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		WP_CLI::add_command(
			'hsw wallet migrate',
			static function ( $args, $assoc ) {
				$batch = isset( $assoc['batch'] ) ? (int) $assoc['batch'] : self::MIGRATION_BATCH;
				$force = isset( $assoc['force'] );
				$out   = self::migrate( $batch, $force );

				WP_CLI::log( sprintf( 'users=%d inserted=%d before=%.2f after=%.2f anomalies=%d', $out['users'], $out['inserted'], $out['total_before'], $out['total_after'], $out['anomalies'] ) );

				$report = self::migration_report();
				foreach ( (array) ( $report['anomaly_list'] ?? array() ) as $a ) {
					WP_CLI::log( sprintf( 'user %d: %s used=%.2f raw=%s', $a['user_id'], implode( ',', $a['flags'] ), $a['used'], implode( ' | ', $a['values'] ) ) );
				}

				WP_CLI::success( 'migration finished' );
			}
		);

		WP_CLI::add_command(
			'hsw wallet report',
			static function () {
				$gaps = self::balance_gaps();

				if ( ! $gaps ) {
					WP_CLI::success( 'balance and ledger agree for every user' );
					return;
				}

				foreach ( $gaps as $gap ) {
					WP_CLI::warning( sprintf( 'user %d balance=%.2f ledger=%.2f diff=%.2f', $gap['user_id'], $gap['balance'], $gap['ledger'], $gap['diff'] ) );
				}

				WP_CLI::error( sprintf( '%d users differ', count( $gaps ) ), false );
			}
		);

		WP_CLI::add_command(
			'hsw wallet repair',
			static function ( $args, $assoc ) {
				$limit = isset( $assoc['limit'] ) ? (int) $assoc['limit'] : 200;
				$rows  = self::repair_gaps( $limit );

				foreach ( $rows as $row ) {
					WP_CLI::log( sprintf( 'user %d gap=%.2f %s', $row['user_id'], $row['gap'], $row['repaired'] ? 'repaired' : 'FAILED' ) );
				}

				WP_CLI::success( sprintf( '%d gaps closed', count( $rows ) ) );
			}
		);
	}

	/* ---------------------------------------------------------------------
	 * Reading
	 * ------------------------------------------------------------------ */

	/** @var array<int,float> Per-request balance cache. */
	private static $balance_cache = array();

	/**
	 * Current balance. One primary-key read (cached per request).
	 *
	 * @param int $user_id User.
	 * @return float
	 */
	public static function balance( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return 0.0;
		}

		if ( ! isset( self::$balance_cache[ $user_id ] ) ) {
			global $wpdb;
			$table = self::balance_table();
			$value = $wpdb->get_var( $wpdb->prepare( "SELECT balance FROM {$table} WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB

			if ( null === $value ) {
				/*
				 * The table row does not exist. Before the migration finished we
				 * can still adopt the legacy meta (read-only); afterwards the
				 * meta is a frozen snapshot and must not resurrect a balance.
				 */
				$value = self::migration_done() ? 0.0 : self::adopt_legacy_balance( $user_id );
			}

			self::$balance_cache[ $user_id ] = (float) $value;
		}

		return (float) apply_filters( 'hsw_wallet_balance', self::$balance_cache[ $user_id ], $user_id );
	}

	/**
	 * Create the table row from the legacy meta when the table has none yet.
	 *
	 * @param int $user_id User.
	 * @return float
	 */
	private static function adopt_legacy_balance( $user_id ) {
		global $wpdb;

		$total = (float) $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT COALESCE(SUM(CAST(meta_value AS DECIMAL(18,2))),0) FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s",
				$user_id,
				self::BALANCE_META
			)
		);

		$table = self::balance_table();
		$wpdb->query( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"INSERT IGNORE INTO {$table} (user_id, balance, updated_at) VALUES (%d, %f, %s)",
				$user_id,
				$total,
				current_time( 'mysql' )
			)
		);

		$value = $wpdb->get_var( $wpdb->prepare( "SELECT balance FROM {$table} WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB

		return null === $value ? $total : (float) $value;
	}

	/**
	 * Ledger entries, newest first. One indexed query.
	 *
	 * @param int    $user_id User.
	 * @param int    $limit   Rows.
	 * @param string $status  `done` (default), `pending` or `all`.
	 * @return array<int,object>
	 */
	public static function entries( $user_id, $limit = 20, $status = 'done' ) {
		global $wpdb;

		$limit = max( 1, min( 200, absint( $limit ) ) );
		$table = self::table();
		$sql   = "SELECT * FROM {$table} WHERE user_id = %d";

		if ( 'all' !== $status ) {
			$sql .= $wpdb->prepare( ' AND status = %s', (string) $status );
		}

		/* phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- assembled from prepared fragments above. */
		return (array) $wpdb->get_results( $wpdb->prepare( $sql . ' ORDER BY id DESC LIMIT %d', $user_id, $limit ) );
	}

	/**
	 * Pending top-up requests (admin screen).
	 *
	 * @param int $limit Rows.
	 * @return array<int,object>
	 */
	public static function pending( $limit = 50 ) {
		global $wpdb;

		$table = self::table();
		$limit = max( 1, min( 200, absint( $limit ) ) );

		/* phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared */
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'pending' ORDER BY id ASC LIMIT %d",
				$limit
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Writing
	 * ------------------------------------------------------------------ */

	/**
	 * Add money to a wallet.
	 *
	 * The balance update is a single SQL statement so concurrent credits cannot
	 * overwrite each other; the ledger row is written afterwards with the value
	 * the update produced.
	 *
	 * @param int    $user_id User.
	 * @param float  $amount  Positive amount.
	 * @param string $kind    `topup`, `admin`, `points`, `refund`, …
	 * @param string $note    Free text shown in the statement.
	 * @param int    $actor_id Admin who did it (0 = system).
	 * @param string $ref     External reference (order id, request id).
	 * @return float New balance.
	 */
	public static function credit( $user_id, $amount, $kind = 'admin', $note = '', $actor_id = 0, $ref = '' ) {
		$amount = round( (float) $amount, 2 );
		if ( $amount <= 0 ) {
			return self::balance( $user_id );
		}

		return self::apply( absint( $user_id ), $amount, $kind, $note, $actor_id, $ref );
	}

	/**
	 * Take money out. Refuses to go negative.
	 *
	 * @param int    $user_id User.
	 * @param float  $amount  Positive amount.
	 * @param string $kind    Kind.
	 * @param string $note    Note.
	 * @param int    $actor_id Actor.
	 * @param string $ref     Reference.
	 * @return float|WP_Error New balance, or an error when the balance is short.
	 */
	public static function debit( $user_id, $amount, $kind = 'spend', $note = '', $actor_id = 0, $ref = '' ) {
		$amount = round( (float) $amount, 2 );
		if ( $amount <= 0 ) {
			return self::balance( $user_id );
		}

		/*
		 * No read-then-write here on purpose. The old code compared
		 * `balance()` with the amount and then subtracted unconditionally, so
		 * two simultaneous checkouts could both pass the check and drive the
		 * balance negative. The guard now travels into the UPDATE itself
		 * (`WHERE balance >= amount`), which is the only place a race can be
		 * decided.
		 */
		return self::apply( absint( $user_id ), -$amount, $kind, $note, $actor_id, $ref, $amount );
	}

	/**
	 * Set the balance to an exact value (admin edit), atomically.
	 *
	 * The admin screen used to read the balance and then send the difference,
	 * which two managers saving at once could apply twice. One statement here
	 * replaces the whole read-modify-write cycle.
	 *
	 * @param int    $user_id User.
	 * @param float  $target  New balance (>= 0).
	 * @param string $note    Statement note.
	 * @param int    $actor_id Admin.
	 * @return float
	 */
	public static function set_balance( $user_id, $target, $note = '', $actor_id = 0 ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$target  = max( 0, round( (float) $target, 2 ) );
		$table   = self::balance_table();

		/*
		 * Same ceiling apply() enforces for credit/debit (wallet.php:1119).
		 * Without it an admin typo like 1e18 sailed through: the DECIMAL(18,2)
		 * column silently clamped the stored value, the returned value stayed
		 * 1e18 and the ledger row recorded a third number.
		 */
		if ( $target >= 1.0e16 ) {
			return new WP_Error( 'hsw_wallet_amount', __( 'مبلغ وارد‌شده خارج از محدودهٔ مجاز است.', 'hamishop-elementor-widgets' ) );
		}

		/* Portable upsert: UPDATE, then INSERT IGNORE, then UPDATE again. */
		$ok = $wpdb->query( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"UPDATE {$table} SET balance = %f, updated_at = %s WHERE user_id = %d",
				$target,
				current_time( 'mysql' ),
				$user_id
			)
		);

		if ( ! $ok ) {
			$created = $wpdb->query( // phpcs:ignore WordPress.DB
				$wpdb->prepare(
					"INSERT IGNORE INTO {$table} (user_id, balance, updated_at) VALUES (%d, %f, %s)",
					$user_id,
					$target,
					current_time( 'mysql' )
				)
			);

			if ( ! $created ) {
				$wpdb->query( // phpcs:ignore WordPress.DB
					$wpdb->prepare(
						"UPDATE {$table} SET balance = %f, updated_at = %s WHERE user_id = %d",
						$target,
						current_time( 'mysql' ),
						$user_id
					)
				);
			}
		}
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'user_id'       => $user_id,
				'amount'        => 0,
				'balance_after' => $target,
				'kind'          => 'adjust',
				'status'        => 'done',
				'note'          => (string) $note,
				/* NULL, not '': the unique index allows many NULLs but one ''. */
				'ref'           => null,
				'actor_id'      => absint( $actor_id ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		self::$balance_cache[ $user_id ] = $target;
		wp_cache_delete( $user_id, 'user_meta' );
		clean_user_cache( $user_id );

		return $target;
	}

	/**
	 * Move the balance only — no ledger row.
	 *
	 * Kept separate because an approved top-up request already has its own row
	 * (status `pending`); inserting a second one would make the statement show
	 * the same credit twice.
	 *
	 * @param int   $user_id User.
	 * @param float $delta   Signed amount.
	 * @param float|null $min When set, the row is only written if the current
	 *                        balance is at least this much (atomic debit guard).
	 * @return float|false New balance, or false when the guard rejected the write.
	 */
	private static function apply_balance( $user_id, $delta, $min = null ) {
		global $wpdb;

		$table = self::balance_table();

		if ( null === $min ) {
			/*
			 * Credit. Two portable statements instead of
			 * `INSERT … ON DUPLICATE KEY UPDATE … VALUES()`: that syntax is
			 * deprecated in MySQL 8.0.20 and the replacement (`AS new`) is not
			 * understood by MariaDB. `UPDATE` first (the common case), then
			 * `INSERT IGNORE` for the very first credit, then `UPDATE` again if
			 * another request created the row in between.
			 */
			$ok = $wpdb->query( // phpcs:ignore WordPress.DB
				$wpdb->prepare(
					"UPDATE {$table} SET balance = balance + %f, updated_at = %s WHERE user_id = %d",
					$delta,
					current_time( 'mysql' ),
					$user_id
				)
			);

			if ( ! $ok ) {
				$created = $wpdb->query( // phpcs:ignore WordPress.DB
					$wpdb->prepare(
						"INSERT IGNORE INTO {$table} (user_id, balance, updated_at) VALUES (%d, %f, %s)",
						$user_id,
						$delta,
						current_time( 'mysql' )
					)
				);

				if ( ! $created ) {
					/* The row appeared between the two statements. */
					$ok = $wpdb->query( // phpcs:ignore WordPress.DB
						$wpdb->prepare(
							"UPDATE {$table} SET balance = balance + %f, updated_at = %s WHERE user_id = %d",
							$delta,
							current_time( 'mysql' ),
							$user_id
						)
					);
				} else {
					$ok = $created;
				}
			}
		} else {
			/* The guard lives in the WHERE clause: no window between check and write. */
			$ok = $wpdb->query( // phpcs:ignore WordPress.DB
				$wpdb->prepare(
					"UPDATE {$table} SET balance = balance - %f, updated_at = %s
					 WHERE user_id = %d AND balance >= %f",
					abs( (float) $delta ),
					current_time( 'mysql' ),
					$user_id,
					(float) $min
				)
			);
		}

		if ( ! $ok ) {
			/* Row missing (debit) or the guard rejected it: not enough money. */
			return false;
		}

		$new = (float) $wpdb->get_var( $wpdb->prepare( "SELECT balance FROM {$table} WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB

		/*
		 * The legacy `_hsw_wallet_balance` meta is deliberately **not** written
		 * any more: it is the frozen pre-migration snapshot that the rollback
		 * relies on. A mirror was tried and had to be dropped — under parallel
		 * writes it ended with several rows and intermediate values, so it was
		 * never a trustworthy copy of the balance.
		 */
		self::$balance_cache[ $user_id ] = $new;
		wp_cache_delete( $user_id, 'user_meta' );
		clean_user_cache( $user_id );

		return $new;
	}

	/**
	 * Balance + ledger row.
	 *
	 * @param int    $user_id User.
	 * @param float  $delta   Signed amount.
	 * @param string $kind    Kind.
	 * @param string $note    Note.
	 * @param int    $actor_id Actor.
	 * @param string $ref     Reference.
	 * @return float
	 */
	private static function apply( $user_id, $delta, $kind, $note, $actor_id, $ref, $min = null ) {
		global $wpdb;

		if ( ! $user_id ) {
			return 0.0;
		}

		/*
		 * DECIMAL(18,2) holds up to 10^16 − 0.01. Anything above that used to
		 * reach MySQL and either raise a range error (debit, reported as "not
		 * enough money") or be clamped (credit, silently smaller than the
		 * statement). Reject it before it touches the balance.
		 */
		if ( abs( (float) $delta ) >= 1.0e16 ) {
			return new WP_Error( 'hsw_wallet_amount', __( 'مبلغ وارد‌شده خارج از محدودهٔ مجاز است.', 'hamishop-elementor-widgets' ) );
		}

		$balance = self::apply_balance( $user_id, $delta, $min );

		if ( false === $balance ) {
			return new WP_Error( 'hsw_wallet_short', __( 'موجودی کیف پول کافی نیست.', 'hamishop-elementor-widgets' ) );
		}

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'user_id'       => $user_id,
				'amount'        => $delta,
				'balance_after' => $balance,
				'kind'          => substr( sanitize_key( $kind ), 0, 32 ),
				'status'        => 'done',
				'note'          => (string) $note,
				/* NULL, not '': a unique index allows many NULLs but one ''. */
				'ref'           => '' !== (string) $ref ? substr( (string) $ref, 0, 64 ) : null,
				'actor_id'      => absint( $actor_id ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		/*
		 * The balance and the statement must never disagree. If the ledger row
		 * cannot be written, the balance change is reversed so the wallet holds
		 * exactly what the statement shows.
		 *
		 * A duplicate `ref` is not a failure: the unique index on `ref` is what
		 * makes a repeated top-up a no-op. The balance is still rolled back and
		 * the caller gets a distinct error code so it can answer "already done".
		 */
		if ( ! $inserted ) {
			/* Read the error before the rollback query resets `last_error`. */
			$db_error = (string) $wpdb->last_error;

			self::apply_balance( $user_id, -$delta );

			if ( false !== stripos( $db_error, 'Duplicate entry' ) ) {
				return new WP_Error( 'hsw_wallet_duplicate', __( 'این تراکنش قبلاً ثبت شده است.', 'hamishop-elementor-widgets' ) );
			}

			return new WP_Error( 'hsw_wallet_ledger', __( 'ثبت تراکنش کیف پول ناموفق بود؛ مبلغی جابه‌جا نشد.', 'hamishop-elementor-widgets' ) );
		}

		do_action( 'hsw_wallet_changed', $user_id, $delta, $balance, $kind );

		return $balance;
	}

	/**
	 * Customer asks for a top-up (bank transfer, card-to-card, …).
	 *
	 * Recorded as `pending`; no money moves until an admin approves it. This is
	 * deliberate: a self-service credit button without a payment gateway is a
	 * free-money button.
	 *
	 * @param int    $user_id User.
	 * @param float  $amount  Requested amount.
	 * @param string $note    Customer note (tracking number, etc).
	 * @return int|WP_Error Request id.
	 */
	public static function request_topup( $user_id, $amount, $note = '' ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$amount  = round( (float) $amount, 2 );
		$min     = max( 0, (float) HSW_Account::instance()->setting( 'wallet.min_topup', 50000 ) );

		if ( ! $user_id ) {
			return new WP_Error( 'hsw_wallet_user', __( 'ابتدا وارد حساب خود شوید.', 'hamishop-elementor-widgets' ) );
		}
		if ( $amount <= 0 ) {
			return new WP_Error( 'hsw_wallet_amount', __( 'مبلغ را وارد کنید.', 'hamishop-elementor-widgets' ) );
		}
		if ( $min > 0 && $amount < $min ) {
			return new WP_Error(
				'hsw_wallet_min',
				sprintf(
					/* translators: %s: minimum amount. */
					__( 'حداقل مبلغ شارژ %s تومان است.', 'hamishop-elementor-widgets' ),
					number_format( $min )
				)
			);
		}

		/*
		 * One open request per customer, enforced by the unique index on `ref`
		 * rather than by a `SELECT COUNT()`: the check-then-insert let ten
		 * simultaneous clicks create ten pending rows. `pending:<user>` can only
		 * exist once; `decide()` clears it when the request is closed.
		 */
		$open = self::open_request_id( $user_id );
		if ( $open ) {
			return new WP_Error( 'hsw_wallet_open', __( 'یک درخواست شارژ باز دارید؛ تا بررسی آن صبر کنید.', 'hamishop-elementor-widgets' ) );
		}

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'user_id'       => $user_id,
				'amount'        => $amount,
				'balance_after' => 0,
				'kind'          => 'topup',
				'status'        => 'pending',
				'note'          => (string) $note,
				'ref'           => self::open_request_ref( $user_id ),
				'actor_id'      => 0,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( ! $inserted ) {
			/* Lost the race against another click: the unique index refused it. */
			return new WP_Error( 'hsw_wallet_open', __( 'یک درخواست شارژ باز دارید؛ تا بررسی آن صبر کنید.', 'hamishop-elementor-widgets' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Reference that marks the single open request of a user.
	 *
	 * @param int $user_id User.
	 * @return string
	 */
	private static function open_request_ref( $user_id ) {
		return 'pending:' . absint( $user_id );
	}

	/**
	 * Id of the open request, if any.
	 *
	 * @param int $user_id User.
	 * @return int
	 */
	public static function open_request_id( $user_id ) {
		global $wpdb;

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				'SELECT id FROM ' . self::table() . ' WHERE ref = %s LIMIT 1',
				self::open_request_ref( $user_id )
			)
		);
	}

	/**
	 * Admin decision on a pending request.
	 *
	 * @param int    $id      Ledger row id.
	 * @param bool   $approve Credit the money.
	 * @param string $note    Admin note.
	 * @return true|WP_Error
	 */
	public static function decide( $id, $approve, $note = '' ) {
		global $wpdb;

		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $row || 'pending' !== $row->status ) {
			return new WP_Error( 'hsw_wallet_state', __( 'این درخواست دیگر در انتظار نیست.', 'hamishop-elementor-widgets' ) );
		}

		/*
		 * Claim the row before moving money. The old code checked `pending`,
		 * credited, and then wrote `done`; two admins (or a double click in two
		 * tabs) both passed the check and both credited. The WHERE clause makes
		 * only one of them the winner.
		 */
		$claimed = $wpdb->query( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s, actor_id = %d, note = %s, ref = NULL
				 WHERE id = %d AND status = 'pending'",
				$approve ? 'done' : 'rejected',
				get_current_user_id(),
				'' !== $note ? $note : (string) $row->note,
				(int) $row->id
			)
		);

		if ( ! $claimed ) {
			return new WP_Error( 'hsw_wallet_state', __( 'این درخواست دیگر در انتظار نیست.', 'hamishop-elementor-widgets' ) );
		}

		$balance = (float) $row->balance_after;

		if ( $approve ) {
			/* The pending row becomes the receipt: move the money, then record the total. */
			$balance = self::apply_balance( (int) $row->user_id, (float) $row->amount );

			if ( false === $balance ) {
				/* Nothing moved: put the request back (with its claim ref) so the admin can retry. */
				$wpdb->query( // phpcs:ignore WordPress.DB
					$wpdb->prepare(
						"UPDATE {$table} SET status = 'pending', ref = %s WHERE id = %d",
						self::open_request_ref( (int) $row->user_id ),
						(int) $row->id
					)
				);
				return new WP_Error( 'hsw_wallet_amount', __( 'مبلغ این درخواست قابل اعمال نیست.', 'hamishop-elementor-widgets' ) );
			}

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array( 'balance_after' => $balance ),
				array( 'id' => (int) $row->id ),
				array( '%f' ),
				array( '%d' )
			);
		}

		do_action( 'hsw_wallet_request_decided', (int) $row->id, (bool) $approve, (int) $row->user_id );

		return true;
	}

	/* ---------------------------------------------------------------------
	 * Loyalty points → money
	 * ------------------------------------------------------------------ */

	/**
	 * Points the customer can still convert.
	 *
	 * @param int $user_id User.
	 * @return int
	 */
	public static function points_available( $user_id ) {
		$earned = HSW_Account::instance()->loyalty_points( $user_id );
		$used   = self::points_used( $user_id );

		return max( 0, $earned - $used );
	}

	/**
	 * How many points this user already converted.
	 *
	 * Kept in the balance table (`points_used`) rather than in usermeta: the
	 * claim must be a single conditional `UPDATE`, and usermeta has no unique
	 * key to build one on.
	 *
	 * @param int $user_id User.
	 * @return int
	 */
	public static function points_used( $user_id ) {
		global $wpdb;

		$table = self::balance_table();

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT points_used FROM {$table} WHERE user_id = %d", absint( $user_id ) ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Convert points into wallet money.
	 *
	 * @param int $user_id User.
	 * @param int $points  How many points.
	 * @return float|WP_Error New balance.
	 */
	public static function convert_points( $user_id, $points ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$points  = absint( $points );

		if ( ! $user_id || $points < 1 ) {
			return new WP_Error( 'hsw_points_invalid', __( 'تعداد امتیاز را وارد کنید.', 'hamishop-elementor-widgets' ) );
		}

		$available = self::points_available( $user_id );
		if ( $points > $available ) {
			return new WP_Error(
				'hsw_points_short',
				sprintf(
					/* translators: %s: available points. */
					__( 'فقط %s امتیاز قابل تبدیل دارید.', 'hamishop-elementor-widgets' ),
					HSW_Helpers::fa_num( $available )
				)
			);
		}

		$per_point = max( 1, (int) HSW_Account::instance()->setting( 'wallet.point_value', 100 ) );
		$money     = $points * $per_point;
		$table     = self::balance_table();
		$earned    = max( 0, (int) HSW_Account::instance()->loyalty_points( $user_id ) );

		/*
		 * Claim the points with one conditional UPDATE. The previous version
		 * read `_hsw_points_used`, added the amount and then wrote it back, so
		 * two requests could spend the same points; the unique ledger ref that
		 * guarded the payout also refused *legitimate* parallel conversions
		 * (four parallel 100-point requests with 1000 points available paid out
		 * three times instead of four).
		 */
		$wpdb->query( // phpcs:ignore WordPress.DB
			$wpdb->prepare( "INSERT IGNORE INTO {$table} (user_id, balance, points_used, updated_at) VALUES (%d, 0, 0, %s)", $user_id, current_time( 'mysql' ) )
		);

		$claimed = $wpdb->query( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"UPDATE {$table} SET points_used = points_used + %d, updated_at = %s
				 WHERE user_id = %d AND points_used + %d <= %d",
				$points,
				current_time( 'mysql' ),
				$user_id,
				$points,
				$earned
			)
		);

		if ( ! $claimed ) {
			return new WP_Error(
				'hsw_points_short',
				sprintf(
					/* translators: %s: available points. */
					__( 'فقط %s امتیاز قابل تبدیل دارید.', 'hamishop-elementor-widgets' ),
					HSW_Helpers::fa_num( self::points_available( $user_id ) )
				)
			);
		}

		$used_after = self::points_used( $user_id );

		$credited = self::credit(
			$user_id,
			$money,
			'points',
			sprintf(
				/* translators: %s: number of points. */
				__( 'تبدیل %s امتیاز باشگاه به کیف پول', 'hamishop-elementor-widgets' ),
				HSW_Helpers::fa_num( $points )
			)
		);

		if ( is_wp_error( $credited ) ) {
			/* Give the points back: no money moved. */
			$wpdb->query( // phpcs:ignore WordPress.DB
				$wpdb->prepare( "UPDATE {$table} SET points_used = points_used - %d WHERE user_id = %d", $points, $user_id )
			);

			return $credited;
		}

		return $credited;
	}

	/* ---------------------------------------------------------------------
	 * Golden customers (admin)
	 * ------------------------------------------------------------------ */

	/**
	 * Customers ranked by lifetime spend.
	 *
	 * One aggregate query on WooCommerce's stats table (populated by the
	 * Analytics sync) instead of calling `wc_get_customer_total_spent()` per
	 * user, which would be one query per row.
	 *
	 * @param int $limit How many.
	 * @return array<int,array{user_id:int,spent:float,name:string,email:string}>
	 */
	public static function golden_customers( $limit = 50 ) {
		global $wpdb;

		$limit = max( 1, min( 200, absint( $limit ) ) );
		$stats = $wpdb->prefix . 'wc_order_stats';
		$look  = $wpdb->prefix . 'wc_customer_lookup';

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $stats ) );
		if ( ! $exists ) {
			return array();
		}

		/* phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared */
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.user_id, SUM(s.net_total) AS spent
				 FROM {$stats} s
				 INNER JOIN {$look} c ON c.customer_id = s.customer_id
				 WHERE s.parent_id = 0 AND c.user_id > 0 AND s.status IN ( 'wc-completed', 'wc-processing', 'wc-on-hold' )
				 GROUP BY c.user_id
				 ORDER BY spent DESC
				 LIMIT %d",
				$limit
			)
		);

		$out = array();
		foreach ( $rows as $row ) {
			$user = get_user_by( 'id', (int) $row->user_id );
			if ( ! $user ) {
				continue;
			}
			$out[] = array(
				'user_id' => (int) $row->user_id,
				'spent'   => (float) $row->spent,
				'name'    => $user->display_name,
				'email'   => $user->user_email,
				'phone'   => (string) get_user_meta( $user->ID, 'billing_phone', true ),
				'balance' => self::balance( $user->ID ),
			);
		}

		return $out;
	}

	/* ---------------------------------------------------------------------
	 * SMS
	 * ------------------------------------------------------------------ */

	/**
	 * Send one SMS through the configured provider.
	 *
	 * No provider SDK is bundled: Iranian SMS panels each have their own API,
	 * and Bale needs a bot bridge. A single webhook contract covers all of them
	 * — POST {phone, text, secret} — and `hsw_wallet_sms` lets a site swap in
	 * its own implementation without touching this class.
	 *
	 * @param string $phone Recipient.
	 * @param string $text  Message.
	 * @return bool
	 */
	public static function send_sms( $phone, $text ) {
		$phone = preg_replace( '/[^0-9+]/', '', (string) $phone );
		$text  = trim( (string) $text );

		if ( '' === $phone || '' === $text ) {
			return false;
		}

		/**
		 * Short-circuit: return true/false to use your own SMS panel.
		 *
		 * @param null|bool $handled Null to continue with the webhook.
		 * @param string    $phone   Recipient.
		 * @param string    $text    Message.
		 */
		$handled = apply_filters( 'hsw_wallet_sms', null, $phone, $text );
		if ( null !== $handled ) {
			return (bool) $handled;
		}

		$url    = trim( (string) HSW_Account::instance()->setting( 'wallet.sms_url', '' ) );
		$secret = trim( (string) HSW_Account::instance()->setting( 'wallet.sms_secret', '' ) );

		if ( '' === $url ) {
			return false;
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout'  => 8,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json; charset=UTF-8' ),
				'body'     => wp_json_encode(
					array(
						'phone'  => $phone,
						'text'   => $text,
						'secret' => $secret,
					)
				),
			)
		);

		return ! is_wp_error( $response );
	}

	/**
	 * Message sent after a manual credit.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	public static function credit_message( $amount ) {
		return (string) apply_filters(
			'hsw_wallet_credit_message',
			sprintf(
				/* translators: %s: amount in tomans. */
				__( 'میزان %s تومان کیف پول شما شارژ شد.', 'hamishop-elementor-widgets' ),
				HSW_Helpers::fa_num( number_format( (float) $amount ) )
			),
			$amount
		);
	}

	/* ---------------------------------------------------------------------
	 * Admin screen
	 * ------------------------------------------------------------------ */

	public function menu() {
		add_submenu_page(
			'hamishop',
			__( 'کیف پول', 'hamishop-elementor-widgets' ),
			__( 'کیف پول', 'hamishop-elementor-widgets' ),
			'manage_woocommerce',
			'hamishop-wallet',
			array( $this, 'render_page' )
		);
	}

	/**
	 * All admin writes go through one handler with one nonce.
	 */
	public function handle_admin_action() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'hamishop-elementor-widgets' ) );
		}
		check_admin_referer( 'hsw_wallet_action' );

		$action = isset( $_POST['hsw_do'] ) ? sanitize_key( wp_unslash( $_POST['hsw_do'] ) ) : '';
		$back   = admin_url( 'admin.php?page=hamishop-wallet' );
		$msg    = '';

		if ( 'decide' === $action ) {
			$id      = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
			$approve = isset( $_POST['approve'] ) && '1' === (string) $_POST['approve'];
			$result  = self::decide( $id, $approve );

			$msg = is_wp_error( $result ) ? $result->get_error_message() : ( $approve ? 'approved' : 'rejected' );
		}

		if ( 'bulk' === $action ) {
			$ids    = isset( $_POST['users'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['users'] ) ) : array();
			$amount = isset( $_POST['amount'] ) ? (float) wp_unslash( $_POST['amount'] ) : 0;
			$note   = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';
			$sms    = isset( $_POST['send_sms'] );

			if ( $amount <= 0 || ! $ids ) {
				$msg = 'invalid';
			} else {
				$sent = 0;
				foreach ( array_unique( $ids ) as $user_id ) {
					self::credit( $user_id, $amount, 'admin', $note, get_current_user_id() );
					$sent++;

					if ( $sms ) {
						$phone = (string) get_user_meta( $user_id, 'billing_phone', true );
						if ( '' !== $phone ) {
							self::send_sms( $phone, self::credit_message( $amount ) );
						}
					}
				}
				$msg = 'bulk:' . $sent;
			}
		}

		wp_safe_redirect( add_query_arg( 'hsw_msg', rawurlencode( $msg ), $back ) );
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$pending = self::pending();
		$golden  = self::golden_customers( 50 );
		$msg     = isset( $_GET['hsw_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['hsw_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'کیف پول', 'hamishop-elementor-widgets' ); ?></h1>

			<?php if ( 'approved' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'شارژ تایید و به کیف پول مشتری اضافه شد.', 'hamishop-elementor-widgets' ); ?></p></div>
			<?php elseif ( 'rejected' === $msg ) : ?>
				<div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'درخواست رد شد.', 'hamishop-elementor-widgets' ); ?></p></div>
			<?php elseif ( 'invalid' === $msg ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'مبلغ یا مشتری انتخاب نشده است.', 'hamishop-elementor-widgets' ); ?></p></div>
			<?php elseif ( 0 === strpos( $msg, 'bulk:' ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php
					printf(
						/* translators: %s: number of customers. */
						esc_html__( 'کیف پول %s مشتری شارژ شد.', 'hamishop-elementor-widgets' ),
						esc_html( HSW_Helpers::fa_num( (int) substr( $msg, 5 ) ) )
					);
					?>
				</p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'درخواست‌های شارژ در انتظار', 'hamishop-elementor-widgets' ); ?></h2>
			<?php if ( ! $pending ) : ?>
				<p class="description"><?php esc_html_e( 'درخواستی در انتظار نیست.', 'hamishop-elementor-widgets' ); ?></p>
			<?php else : ?>
				<table class="widefat striped" style="max-width:1000px">
					<thead><tr>
						<th><?php esc_html_e( 'مشتری', 'hamishop-elementor-widgets' ); ?></th>
						<th><?php esc_html_e( 'مبلغ (تومان)', 'hamishop-elementor-widgets' ); ?></th>
						<th><?php esc_html_e( 'توضیح مشتری', 'hamishop-elementor-widgets' ); ?></th>
						<th><?php esc_html_e( 'تاریخ', 'hamishop-elementor-widgets' ); ?></th>
						<th><?php esc_html_e( 'اقدام', 'hamishop-elementor-widgets' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $pending as $row ) : ?>
						<?php $user = get_user_by( 'id', (int) $row->user_id ); ?>
						<tr>
							<td>
								<?php echo $user ? esc_html( $user->display_name ) : '—'; ?><br>
								<small><?php echo $user ? esc_html( $user->user_email ) : ''; ?></small>
							</td>
							<td><strong><?php echo esc_html( HSW_Helpers::fa_num( number_format( (float) $row->amount ) ) ); ?></strong></td>
							<td><?php echo esc_html( (string) $row->note ); ?></td>
							<td><?php echo esc_html( $row->created_at ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
									<input type="hidden" name="action" value="hsw_wallet_action">
									<input type="hidden" name="hsw_do" value="decide">
									<input type="hidden" name="request_id" value="<?php echo esc_attr( (int) $row->id ); ?>">
									<?php wp_nonce_field( 'hsw_wallet_action' ); ?>
									<button class="button button-primary" name="approve" value="1"><?php esc_html_e( 'تایید و شارژ', 'hamishop-elementor-widgets' ); ?></button>
									<button class="button" name="approve" value="0"><?php esc_html_e( 'رد', 'hamishop-elementor-widgets' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2 style="margin-top:28px"><?php esc_html_e( 'مشتریان طلایی — شارژ گروهی', 'hamishop-elementor-widgets' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'فهرست بر اساس مجموع خرید (یک کوئری تجمیعی، نه یک کوئری برای هر مشتری). تیک بزنید، مبلغ را وارد کنید و در صورت نیاز پیامک بفرستید.', 'hamishop-elementor-widgets' ); ?>
			</p>

			<?php if ( ! $golden ) : ?>
				<p class="description"><?php esc_html_e( 'هنوز داده‌ای برای رتبه‌بندی نیست (جدول آمار ووکامرس خالی است).', 'hamishop-elementor-widgets' ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="hsw_wallet_action">
					<input type="hidden" name="hsw_do" value="bulk">
					<?php wp_nonce_field( 'hsw_wallet_action' ); ?>

					<table class="widefat striped" style="max-width:1000px">
						<thead><tr>
							<td style="width:28px"><input type="checkbox" onclick="document.querySelectorAll('.hsw-pick').forEach(function(c){c.checked=this.checked}.bind(this))"></td>
							<th><?php esc_html_e( 'مشتری', 'hamishop-elementor-widgets' ); ?></th>
							<th><?php esc_html_e( 'مجموع خرید', 'hamishop-elementor-widgets' ); ?></th>
							<th><?php esc_html_e( 'موجودی فعلی', 'hamishop-elementor-widgets' ); ?></th>
							<th><?php esc_html_e( 'موبایل', 'hamishop-elementor-widgets' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $golden as $row ) : ?>
							<tr>
								<td><input class="hsw-pick" type="checkbox" name="users[]" value="<?php echo esc_attr( $row['user_id'] ); ?>"></td>
								<td><strong><?php echo esc_html( $row['name'] ); ?></strong><br><small><?php echo esc_html( $row['email'] ); ?></small></td>
								<td><?php echo esc_html( HSW_Helpers::fa_num( number_format( $row['spent'] ) ) ); ?></td>
								<td><?php echo esc_html( HSW_Helpers::fa_num( number_format( $row['balance'] ) ) ); ?></td>
								<td dir="ltr"><?php echo esc_html( $row['phone'] ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>

					<table class="form-table" role="presentation"><tbody>
						<tr>
							<th scope="row"><label for="hsw-wallet-amount"><?php esc_html_e( 'مبلغ شارژ هر نفر', 'hamishop-elementor-widgets' ); ?></label></th>
							<td>
								<!-- step is relative to min: min=1 + step=1000 would reject 50000 as "not a valid value". -->
								<input type="number" min="1000" step="1000" id="hsw-wallet-amount" name="amount" class="regular-text" dir="ltr" required>
								<p class="description"><?php esc_html_e( 'تومان. به هر مشتری انتخاب‌شده این مبلغ اضافه می‌شود.', 'hamishop-elementor-widgets' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="hsw-wallet-note"><?php esc_html_e( 'توضیح تراکنش', 'hamishop-elementor-widgets' ); ?></label></th>
							<td><input type="text" id="hsw-wallet-note" name="note" class="regular-text" placeholder="<?php esc_attr_e( 'شارژ هدیه', 'hamishop-elementor-widgets' ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'پیامک', 'hamishop-elementor-widgets' ); ?></th>
							<td>
								<label><input type="checkbox" name="send_sms" value="1" checked> <?php esc_html_e( 'ارسال پیامک اطلاع‌رسانی به هر مشتری', 'hamishop-elementor-widgets' ); ?></label>
								<p class="description"><?php esc_html_e( 'آدرس وب‌هوک پیامک را در «حساب کاربری ← کیف پول» تنظیم کنید. بدون آدرس، پیامکی ارسال نمی‌شود و بقیهٔ کار انجام می‌شود.', 'hamishop-elementor-widgets' ); ?></p>
							</td>
						</tr>
					</tbody></table>

					<p class="submit">
						<button class="button button-primary" type="submit"><?php esc_html_e( 'شارژ مشتریان انتخاب‌شده', 'hamishop-elementor-widgets' ); ?></button>
					</p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}
}
