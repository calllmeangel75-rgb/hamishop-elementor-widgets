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

	/** @var array<int,string> Runtime memory cache of evaluated auth status per order id. */
	private static $status_cache = array();

	/** @var array<int,bool> Orders marked verified in memory during this request. */
	private static $verified_memory = array();

	/** @var string|null Last error message from email submission. */
	private static $submission_error = null;

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
		if ( self::is_verified( $order ) ) {
			return self::STATUS_GRANTED;
		}

		// Check if submitting the verification form in this request.
		$post_check = self::check_submission( $order );
		if ( true === $post_check ) {
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
	 * Process verification form submission if present.
	 *
	 * @param WC_Order $order Order.
	 * @return bool|null True if verified, false if failed, null if no submission.
	 */
	public static function check_submission( $order ) {
		$submitted_email = '';
		if ( isset( $_POST['hsw_verify_email'] ) ) {
			$submitted_email = sanitize_email( wp_unslash( $_POST['hsw_verify_email'] ) );
		} elseif ( isset( $_POST['email'] ) ) {
			$submitted_email = sanitize_email( wp_unslash( $_POST['email'] ) );
		}

		if ( '' === $submitted_email ) {
			return null;
		}

		$order_id = (int) $order->get_id();

		// Check nonces (support both HamiShop nonce and WC core nonce).
		$nonce_ok = false;
		if ( ! empty( $_POST['hsw_order_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hsw_order_nonce'] ) ), 'hsw_verify_order_' . $order_id ) ) {
			$nonce_ok = true;
		} elseif ( ! empty( $_POST['check_submission'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['check_submission'] ) ), 'wc_verify_email' ) ) {
			$nonce_ok = true;
		}

		if ( ! $nonce_ok ) {
			self::$submission_error = __( 'نشست امنیتی منقضی شده است. لطفاً صفحه را تازه‌سازی و دوباره امتحان کنید.', 'hamishop-elementor-widgets' );
			return false;
		}

		$order_email = strtolower( trim( (string) $order->get_billing_email() ) );
		$input_email = strtolower( trim( $submitted_email ) );

		if ( '' !== $order_email && hash_equals( $order_email, $input_email ) ) {
			self::mark_verified( $order );
			self::$submission_error = null;
			return true;
		}

		self::$submission_error = __( 'نشانی ایمیل وارد شده با ایمیل صورتحساب این سفارش مطابقت ندارد.', 'hamishop-elementor-widgets' );
		return false;
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

		if ( ! headers_sent() ) {
			$hash   = self::verification_hash( $order );
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
