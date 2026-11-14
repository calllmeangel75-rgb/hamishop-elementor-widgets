<?php
/**
 * Order access authorization and verification for completion views.
 *
 * Implements WooCommerce core's privacy standard for order-received screens:
 *   - Guest orders (customer_id = 0) are viewable with a valid order key.
 *   - Registered customer orders (customer_id > 0):
 *       - Logged-in owner & shop managers: granted immediately.
 *       - Other logged-in users: strictly forbidden (no leaks across accounts).
 *       - Logged-out visitors (guests): requires authentication or billing
 *         email verification, so order links emailed to customers still work
 *         after session expiry without leaking data to arbitrary link holders.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Order_Auth {

	const STATUS_GRANTED           = 'granted';
	const STATUS_NEED_VERIFICATION = 'need_verification';
	const STATUS_FORBIDDEN         = 'forbidden';
	const STATUS_DENIED            = 'denied';

	const COOKIE_PREFIX            = 'hsw_verify_order_';

	/** Wrong billing-email attempts allowed per order + IP inside the window. */
	const THROTTLE_LIMIT  = 5;

	/** One shared window for both verification buckets, in seconds. */
	const THROTTLE_WINDOW = 600;

	/** Ceiling for all IPs trying one order, so the per-IP bucket cannot be farmed. */
	const ORDER_THROTTLE_LIMIT = 25;

	/** @var bool Guard against registering the request hook twice. */
	private static $hooked = false;

	/** @var array<int,string> Runtime memory cache of evaluated auth status per order id. */
	private static $status_cache = array();

	/** @var array<int,bool> Orders marked verified in memory during this request. */
	private static $verified_memory = array();

	/** @var string|null Last error message from email submission. */
	private static $submission_error = null;

	/**
	 * Register the request-time verification handler.
	 *
	 * The form used to be processed while the widget rendered, which is too late
	 * to set a cookie reliably (output may already have started) and impossible
	 * to answer with a real 429. `template_redirect` runs before the template is
	 * selected and before any output.
	 */
	public static function init() {
		if ( self::$hooked ) {
			return;
		}
		self::$hooked = true;
		add_action( 'template_redirect', array( __CLASS__, 'handle_verification_request' ), 99 );
	}

	/**
	 * Process a POSTed verification form on template_redirect.
	 *
	 * Success: set the cookie, then redirect (PRG) to the same URL so a refresh
	 * does not resubmit the email. Failure: keep the request and let the widget
	 * render the stored error. Over the limit: a real 429 with Retry-After.
	 *
	 * @return void
	 */
	public static function handle_verification_request() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : '';
		if ( 'POST' !== $method || ! isset( $_POST['hsw_verify_email'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- presence only; process_submission() verifies the nonce.
			return;
		}

		$order = self::request_order();
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$result = self::process_submission( $order );

		if ( 'success' === $result['status'] ) {
			$url = self::current_url();
			if ( '' !== $url && ! headers_sent() ) {
				wp_safe_redirect( $url, 303 );
				exit;
			}
			return;
		}

		if ( 'throttled' === $result['status'] ) {
			status_header( 429 );
			header( 'Retry-After: ' . (int) $result['retry_after'] );
			if ( function_exists( 'nocache_headers' ) ) {
				nocache_headers();
			}
			if ( function_exists( 'wp_die' ) ) {
				wp_die(
					esc_html( $result['message'] ),
					esc_html__( 'تلاش بیش از حد', 'hamishop-elementor-widgets' ),
					array( 'response' => 429 )
				);
			}
			exit;
		}
	}

	/**
	 * Resolve the order named by the current request.
	 *
	 * The verification form posts back to its own URL, so the order id is in
	 * `?order=` on the standalone page or in the `order-received` query var on
	 * WooCommerce's endpoint.
	 *
	 * @return WC_Order|false
	 */
	private static function request_order() {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return false;
		}

		$order_id = 0;
		if ( isset( $_GET['order'] ) && is_scalar( $_GET['order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_id = absint( wp_unslash( $_GET['order'] ) );
		} elseif ( function_exists( 'get_query_var' ) ) {
			$order_id = absint( get_query_var( 'order-received' ) );
		}

		$order = $order_id > 0 ? wc_get_order( $order_id ) : false;

		return $order instanceof WC_Order ? $order : false;
	}

	/**
	 * Current front-end URL, without the POST body.
	 *
	 * @return string
	 */
	private static function current_url() {
		$host = isset( $_SERVER['HTTP_HOST'] ) ? (string) wp_unslash( $_SERVER['HTTP_HOST'] ) : '';
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( '' === $host || '' === $uri ) {
			return '';
		}

		$scheme = ( function_exists( 'is_ssl' ) && is_ssl() ) ? 'https' : 'http';

		return esc_url_raw( $scheme . '://' . $host . $uri );
	}

	/**
	 * Evaluate access authorization for viewing an order.
	 *
	 * @param WC_Order|mixed $order Order object.
	 * @param string|null    $key   Order key from request (null reads from $_GET['key']).
	 * @return string One of the STATUS_* constants.
	 */
	public static function check_order_access( $order, $key = null ) {
		if ( ! $order instanceof WC_Order ) {
			return self::STATUS_DENIED;
		}

		$order_id = (int) $order->get_id();
		if ( $order_id <= 0 ) {
			return self::STATUS_DENIED;
		}

		if ( null === $key ) {
			$key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		$key = is_string( $key ) ? trim( $key ) : '';

		$customer_id = (int) $order->get_customer_id();

		/* 1. Missing or empty order key. */
		if ( '' === $key ) {
			if ( is_user_logged_in() ) {
				$current_user_id = (int) get_current_user_id();
				if ( $customer_id > 0 && $current_user_id === $customer_id ) {
					return self::STATUS_GRANTED;
				}
				if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_shop_orders' ) ) {
					return self::STATUS_GRANTED;
				}
			}
			return self::STATUS_DENIED;
		}

		/* 2. Key is supplied: check cryptographic validity. */
		if ( ! $order->key_is_valid( $key ) ) {
			// Even with a wrong key in URL, if the logged-in owner is viewing, allow it.
			if ( is_user_logged_in() && $customer_id > 0 && (int) get_current_user_id() === $customer_id ) {
				return self::STATUS_GRANTED;
			}
			return self::STATUS_DENIED;
		}

		/* 3. Key is valid! Check ownership rules. */

		// Case A: Guest checkout (no WP user account).
		if ( 0 === $customer_id ) {
			return self::STATUS_GRANTED;
		}

		// Case B: Registered customer checkout.
		if ( is_user_logged_in() ) {
			$current_user_id = (int) get_current_user_id();
			// Owner matches.
			if ( $current_user_id === $customer_id ) {
				return self::STATUS_GRANTED;
			}
			// Shop manager / admin.
			if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_shop_orders' ) ) {
				return self::STATUS_GRANTED;
			}
			// Different logged-in customer: strictly forbidden.
			return self::STATUS_FORBIDDEN;
		}

		// Case C: Logged-out visitor (guest) viewing registered customer's order.
		// Respect WooCommerce core filter: woocommerce_order_email_verification_required.
		$required = apply_filters(
			'woocommerce_order_email_verification_required',
			true,
			$order,
			'order-received'
		);
		if ( ! $required ) {
			return self::STATUS_GRANTED;
		}

		// Check if already verified in session, recent checkout, or secure cookie.
		// The POST itself is handled earlier, on template_redirect, and redirects
		// here with the cookie already set.
		if ( self::is_verified( $order ) ) {
			return self::STATUS_GRANTED;
		}

		return self::STATUS_NEED_VERIFICATION;
	}

	/**
	 * Quick boolean check: is viewing this order granted right now?
	 *
	 * @param WC_Order|mixed $order Order.
	 * @param string|null    $key   Key.
	 * @return bool
	 */
	public static function is_authorized( $order, $key = null ) {
		return self::STATUS_GRANTED === self::check_order_access( $order, $key );
	}

	/**
	 * Routing guard: may the completion router redirect this request to /complete/?
	 *
	 * Other customers are forbidden; guests with valid keys and owners are routed.
	 *
	 * @param WC_Order $order Order.
	 * @param string|null $key Key.
	 * @return bool
	 */
	public static function can_route( $order, $key = null ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}
		if ( null === $key ) {
			$key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		$status = self::check_order_access( $order, $key );
		// Granted and need_verification are routed to /complete/ (so /complete/ can show verification form).
		// Forbidden and denied are NOT routed (let WC handle them).
		return in_array( $status, array( self::STATUS_GRANTED, self::STATUS_NEED_VERIFICATION ), true );
	}

	/**
	 * Check whether the order has already been verified in this session or cookie.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function is_verified( $order ) {
		$order_id = (int) $order->get_id();
		if ( ! empty( self::$verified_memory[ $order_id ] ) ) {
			return true;
		}

		// WooCommerce checkout session grace period.
		if ( function_exists( 'WC' ) && WC()->session ) {
			if ( (int) WC()->session->get( 'order_awaiting_payment' ) === $order_id ) {
				return true;
			}
			if ( WC()->session->get( 'wc_verify_email_' . $order_id ) ) {
				return true;
			}
			if ( WC()->session->get( 'hsw_verify_order_' . $order_id ) ) {
				return true;
			}
		}

		// Secure cookie check.
		$cookie_name = self::COOKIE_PREFIX . $order_id;
		if ( isset( $_COOKIE[ $cookie_name ] ) ) {
			$val      = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );
			$expected = self::verification_hash( $order );
			if ( '' !== $val && hash_equals( $expected, $val ) ) {
				self::$verified_memory[ $order_id ] = true;
				return true;
			}
		}

		return false;
	}

	/**
	 * Validate and process the verification form for one order.
	 *
	 * Only the dedicated `hsw_verify_email` field is accepted. A generic
	 * `email` field belongs to unrelated forms that can share the same URL
	 * (newsletter, comments) and must never activate this path.
	 *
	 * @param WC_Order $order Order.
	 * @return array{status:string,message:string,retry_after:int} status is
	 *         `success`, `error`, `ignored`, or `throttled`.
	 */
	public static function process_submission( $order ) {
		$result = array(
			'status'      => 'ignored',
			'message'     => '',
			'retry_after' => 0,
		);

		if ( ! $order instanceof WC_Order ) {
			return $result;
		}

		if ( ! isset( $_POST['hsw_verify_email'] ) ) {
			return $result;
		}

		$submitted_email = sanitize_email( wp_unslash( $_POST['hsw_verify_email'] ) );
		$order_id        = (int) $order->get_id();

		// Check nonces (support both HamiShop nonce and WC core nonce).
		$nonce_ok = false;
		if ( ! empty( $_POST['hsw_order_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hsw_order_nonce'] ) ), 'hsw_verify_order_' . $order_id ) ) {
			$nonce_ok = true;
		} elseif ( ! empty( $_POST['check_submission'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['check_submission'] ) ), 'wc_verify_email' ) ) {
			$nonce_ok = true;
		}

		if ( ! $nonce_ok ) {
			return self::fail( __( 'نشست امنیتی منقضی شده است. لطفاً صفحه را تازه‌سازی و دوباره امتحان کنید.', 'hamishop-elementor-widgets' ) );
		}

		/*
		 * Check the counters before looking at the submitted address. Once the
		 * bucket is full, every further attempt is 429 — including a correct
		 * address. Otherwise an attacker could keep guessing through the window,
		 * and the customer would see a success that the next request loses again.
		 * `peek()` is read-only: this check does not count as an attempt.
		 */
		if ( class_exists( 'HSW_Throttle' ) ) {
			$ip         = HSW_Throttle::ip();
			$per_ip_peek = HSW_Throttle::peek( 'verify_order:' . $order_id . ':' . $ip );
			$all_peek    = HSW_Throttle::peek( 'verify_order_all:' . $order_id );

			if ( ( (int) $per_ip_peek['hits'] >= self::THROTTLE_LIMIT && (int) $per_ip_peek['retry_after'] > 0 )
				|| ( (int) $all_peek['hits'] >= self::ORDER_THROTTLE_LIMIT && (int) $all_peek['retry_after'] > 0 ) ) {
				$retry = max( (int) $per_ip_peek['retry_after'], (int) $all_peek['retry_after'], 1 );
				self::$submission_error = __( 'تلاش‌های ناموفق بیش از حد مجاز بود. لطفاً کمی بعد دوباره امتحان کنید.', 'hamishop-elementor-widgets' );

				$result['status']      = 'throttled';
				$result['message']     = self::$submission_error;
				$result['retry_after'] = $retry;
				return $result;
			}
		}

		$order_email = strtolower( trim( (string) $order->get_billing_email() ) );
		$input_email = strtolower( trim( (string) $submitted_email ) );

		/*
		 * An order with no billing email can never be verified. Say so plainly
		 * instead of showing a form that is guaranteed to fail.
		 */
		if ( '' === $order_email ) {
			return self::fail( __( 'برای این سفارش ایمیل صورتحساب ثبت نشده است. برای مشاهدهٔ جزئیات، وارد حساب کاربری خود شوید.', 'hamishop-elementor-widgets' ) );
		}

		/*
		 * A correct email is the proof itself while the bucket is still open.
		 * Only failures are counted; success clears both counters so the next
		 * verification starts from zero.
		 */
		if ( hash_equals( $order_email, $input_email ) ) {
			self::clear_throttles( $order );
			self::mark_verified( $order );
			self::$submission_error = null;

			$result['status'] = 'success';
			return $result;
		}

		if ( ! class_exists( 'HSW_Throttle' ) ) {
			return self::fail( __( 'نشانی ایمیل وارد شده با ایمیل صورتحساب این سفارش مطابقت ندارد.', 'hamishop-elementor-widgets' ) );
		}

		$ip     = HSW_Throttle::ip();
		$bucket = 'verify_order:' . $order_id . ':' . $ip;
		$per_ip = HSW_Throttle::hit( $bucket, self::THROTTLE_LIMIT, self::THROTTLE_WINDOW );
		$all    = HSW_Throttle::hit( 'verify_order_all:' . $order_id, self::ORDER_THROTTLE_LIMIT, self::THROTTLE_WINDOW );

		if ( empty( $per_ip['allowed'] ) || empty( $all['allowed'] ) ) {
			$retry = max( (int) $per_ip['retry_after'], (int) $all['retry_after'], 1 );
			self::$submission_error = __( 'تلاش‌های ناموفق بیش از حد مجاز بود. لطفاً کمی بعد دوباره امتحان کنید.', 'hamishop-elementor-widgets' );

			$result['status']      = 'throttled';
			$result['message']     = self::$submission_error;
			$result['retry_after'] = $retry;
			return $result;
		}

		return self::fail( __( 'نشانی ایمیل وارد شده با ایمیل صورتحساب این سفارش مطابقت ندارد.', 'hamishop-elementor-widgets' ) );
	}

	/**
	 * Record a non-throttled failure and return its result.
	 *
	 * @param string $message Error message.
	 * @return array{status:string,message:string,retry_after:int}
	 */
	private static function fail( $message ) {
		self::$submission_error = $message;

		return array(
			'status'      => 'error',
			'message'     => $message,
			'retry_after' => 0,
		);
	}

	/**
	 * Remove both verification buckets for an order after a correct email.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	private static function clear_throttles( $order ) {
		if ( ! class_exists( 'HSW_Throttle' ) ) {
			return;
		}

		$order_id = (int) $order->get_id();
		HSW_Throttle::clear( 'verify_order_all:' . $order_id );

		/*
		 * The per-IP bucket key includes the IP. Clearing the current IP's bucket
		 * is enough for the customer who just proved the email; other addresses
		 * keep their own counters and cannot use this success to reset theirs.
		 */
		HSW_Throttle::clear( 'verify_order:' . $order_id . ':' . HSW_Throttle::ip() );
	}

	/**
	 * Does this order have a billing email that can be verified?
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function has_billing_email( $order ) {
		return $order instanceof WC_Order && '' !== trim( (string) $order->get_billing_email() );
	}

	/**
	 * Mark an order as verified in memory, session, and cookie.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public static function mark_verified( $order ) {
		$order_id = (int) $order->get_id();
		self::$verified_memory[ $order_id ] = true;

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'hsw_verify_order_' . $order_id, true );
			WC()->session->set( 'wc_verify_email_' . $order_id, true );
		}

		$hash = self::verification_hash( $order );

		/* Make the cookie visible to the rest of this request as well. */
		$_COOKIE[ self::COOKIE_PREFIX . $order_id ] = $hash;

		if ( headers_sent( $file, $line ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'HamiShop: verification cookie not sent, output started at %s:%d', $file, $line ) );
			return;
		}

		$expire = time() + ( 2 * DAY_IN_SECONDS );
		$secure = is_ssl();
		setcookie(
			self::COOKIE_PREFIX . $order_id,
			$hash,
			array(
				'expires'  => $expire,
				'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
				'secure'   => $secure,
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Generate verification hash for an order.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function verification_hash( $order ) {
		$key   = (string) $order->get_order_key();
		$email = strtolower( trim( (string) $order->get_billing_email() ) );
		return wp_hash( 'hsw_verify_' . $order->get_id() . '_' . $key . '_' . $email );
	}

	/**
	 * Get the last submission error message, if any.
	 *
	 * @return string|null
	 */
	public static function get_submission_error() {
		return self::$submission_error;
	}

	/**
	 * Reset state. Tests only.
	 */
	public static function reset() {
		self::$status_cache      = array();
		self::$verified_memory   = array();
		self::$submission_error  = null;
	}
}
