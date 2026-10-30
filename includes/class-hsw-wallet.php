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

	const DB_VERSION   = '1';
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

	public function maybe_install() {
		if ( self::DB_VERSION === get_option( 'hsw_wallet_db' ) ) {
			return;
		}

		global $wpdb;
		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint unsigned NOT NULL DEFAULT 0,
				amount decimal(18,2) NOT NULL DEFAULT 0,
				balance_after decimal(18,2) NOT NULL DEFAULT 0,
				kind varchar(32) NOT NULL DEFAULT 'credit',
				status varchar(16) NOT NULL DEFAULT 'done',
				note text NULL,
				ref varchar(64) NOT NULL DEFAULT '',
				actor_id bigint unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				KEY user_time (user_id, created_at),
				KEY status (status)
			) {$collate};"
		);

		update_option( 'hsw_wallet_db', self::DB_VERSION, false );
	}

	/* ---------------------------------------------------------------------
	 * Reading
	 * ------------------------------------------------------------------ */

	/**
	 * Current balance. One cached meta read.
	 *
	 * @param int $user_id User.
	 * @return float
	 */
	public static function balance( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return 0.0;
		}

		return (float) apply_filters( 'hsw_wallet_balance', (float) get_user_meta( $user_id, self::BALANCE_META, true ), $user_id );
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
	 * Shared write path.
	 *
	 * @param int    $user_id User.
	 * @param float  $delta   Signed amount.
	 * @param string $kind    Kind.
	 * @param string $note    Note.
	 * @param int    $actor_id Actor.
	 * @param string $ref     Reference.
	 * @return float
	 */
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

		/*
		 * Atomic: the arithmetic happens on the stored value, so two requests
		 * running at the same time cannot both read the old balance.
		 */
		$sql  = "UPDATE {$wpdb->usermeta}
				 SET meta_value = CAST(meta_value AS DECIMAL(18,2)) + %f
				 WHERE user_id = %d AND meta_key = %s";
		$args = array( $delta, $user_id, self::BALANCE_META );

		if ( null !== $min ) {
			/* The guard lives in the WHERE clause: no window between check and write. */
			$sql   .= ' AND CAST(meta_value AS DECIMAL(18,2)) >= %f';
			$args[] = (float) $min;
		}

		$updated = $wpdb->query( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB

		if ( ! $updated ) {
			if ( null !== $min ) {
				/* Either the guard rejected it or the row does not exist yet. */
				return false;
			}
			/* First ever transaction: the meta row does not exist yet. */
			add_user_meta( $user_id, self::BALANCE_META, $delta, true );
		}

		wp_cache_delete( $user_id, 'user_meta' );
		clean_user_cache( $user_id );

		return self::balance( $user_id );
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
				'ref'           => substr( (string) $ref, 0, 64 ),
				'actor_id'      => absint( $actor_id ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		/*
		 * The balance and the statement must never disagree. If the ledger row
		 * cannot be written, the balance change is reversed so the wallet holds
		 * exactly what the statement shows.
		 */
		if ( ! $inserted ) {
			self::apply_balance( $user_id, -$delta );
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

		/* One open request per customer keeps the admin queue honest. */
		$open = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table() . " WHERE user_id = %d AND status = 'pending' AND kind = 'topup'",
				$user_id
			)
		);
		if ( $open > 0 ) {
			return new WP_Error( 'hsw_wallet_open', __( 'یک درخواست شارژ باز دارید؛ تا بررسی آن صبر کنید.', 'hamishop-elementor-widgets' ) );
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'user_id'       => $user_id,
				'amount'        => $amount,
				'balance_after' => 0,
				'kind'          => 'topup',
				'status'        => 'pending',
				'note'          => (string) $note,
				'ref'           => '',
				'actor_id'      => 0,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
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

		$balance = (float) $row->balance_after;

		if ( $approve ) {
			/* The pending row becomes the receipt: move the money, then close it. */
			$balance = self::apply_balance( (int) $row->user_id, (float) $row->amount );
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'status'        => $approve ? 'done' : 'rejected',
				'balance_after' => $balance,
				'actor_id'      => get_current_user_id(),
				'note'          => '' !== $note ? $note : (string) $row->note,
			),
			array( 'id' => (int) $row->id ),
			array( '%s', '%f', '%d', '%s' ),
			array( '%d' )
		);

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
		$used   = (int) get_user_meta( absint( $user_id ), self::POINTS_META, true );

		return max( 0, $earned - $used );
	}

	/**
	 * Convert points into wallet money.
	 *
	 * @param int $user_id User.
	 * @param int $points  How many points.
	 * @return float|WP_Error New balance.
	 */
	public static function convert_points( $user_id, $points ) {
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

		$used = (int) get_user_meta( $user_id, self::POINTS_META, true );
		update_user_meta( $user_id, self::POINTS_META, $used + $points );

		return self::credit(
			$user_id,
			$money,
			'points',
			sprintf(
				/* translators: %s: number of points. */
				__( 'تبدیل %s امتیاز باشگاه به کیف پول', 'hamishop-elementor-widgets' ),
				HSW_Helpers::fa_num( $points )
			)
		);
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
