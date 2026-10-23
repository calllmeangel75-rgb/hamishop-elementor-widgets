<?php
/**
 * Coupon messages, in the store's own voice.
 *
 * WooCommerce's stock strings are two problems at once: they read like a
 * database error («کوپن "x" قابل اعمال نیست زیرا وجود ندارد») and they never
 * quote the code the customer actually typed, so someone who mistyped `offr`
 * cannot see what the shop tried to look up.
 *
 * Everything here goes through the two official filters
 * (`woocommerce_coupon_error` / `woocommerce_coupon_message`), which means:
 *  - no template override, no JS patch,
 *  - every message keeps working in the cart, the checkout and the AJAX
 *    coupon endpoint at once,
 *  - and another plugin can still change it afterwards.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Coupon_Messages {

	/** @var HSW_Coupon_Messages|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'woocommerce_coupon_error', array( $this, 'filter_error' ), 10, 3 );
		add_filter( 'woocommerce_coupon_message', array( $this, 'filter_message' ), 10, 3 );
	}

	/**
	 * The code as the customer typed it.
	 *
	 * `$coupon` is a WC_Coupon object when WooCommerce found one. For
	 * "does not exist" it is an empty WC_Coupon, so the code is taken from the
	 * request instead — that is exactly the case where the customer needs to
	 * see their own typo.
	 *
	 * @param mixed $coupon WC_Coupon|string|mixed.
	 * @return string
	 */
	private function code( $coupon ) {
		if ( is_object( $coupon ) && method_exists( $coupon, 'get_code' ) ) {
			$code = (string) $coupon->get_code();
			if ( '' !== $code ) {
				return $code;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- reading the value WooCommerce itself just processed.
		$posted = isset( $_POST['coupon_code'] ) ? wp_unslash( $_POST['coupon_code'] ) : ( isset( $_GET['coupon_code'] ) ? wp_unslash( $_GET['coupon_code'] ) : '' );

		return is_string( $posted ) ? sanitize_text_field( $posted ) : '';
	}

	/**
	 * Replace an error message with the store's own wording.
	 *
	 * @param string $error    Original message.
	 * @param int    $error_code WC_COUPON::E_* constant.
	 * @param mixed  $coupon   Coupon object (may be empty).
	 * @return string
	 */
	public function filter_error( $error, $error_code, $coupon ) {
		$code = $this->code( $coupon );

		/* Without a code there is nothing useful to say; keep WooCommerce's text. */
		if ( '' === $code ) {
			return $error;
		}

		/*
		 * A coupon that does not exist is the one case where WooCommerce's own
		 * error code is unreliable: depending on the cart contents it reports
		 * "not applicable to products" (105) instead of "does not exist" (100),
		 * which tells the customer nothing about their typo. `get_id()` is the
		 * truth, so it wins.
		 */
		if ( is_object( $coupon ) && method_exists( $coupon, 'get_id' ) && ! $coupon->get_id() ) {
			$missing = sprintf(
				/* translators: %s: coupon code entered by the customer. */
				__( 'کد تخفیف «%s» قابل استفاده نیست و وجود ندارد.', 'hamishop-elementor-widgets' ),
				$code
			);

			/** This filter is documented below. */
			return (string) apply_filters( 'hsw_coupon_error_message', $missing, 100, $code );
		}

		$messages = array(
			// Coupon lookup failed / filtered out by another plugin.
			3    => __( 'کد تخفیف «%s» قابل استفاده نیست و وجود ندارد.', 'hamishop-elementor-widgets' ),
			100  => __( 'کد تخفیف «%s» قابل استفاده نیست و وجود ندارد.', 'hamishop-elementor-widgets' ),
			4    => __( 'کد تخفیف «%s» قابل استفاده نیست و حذف شد.', 'hamishop-elementor-widgets' ),
			5    => __( 'کد تخفیف «%s» مخصوص حساب دیگری است و برای شما قابل استفاده نیست.', 'hamishop-elementor-widgets' ),
			101  => __( 'کد تخفیف «%s» به حداقل مبلغ خرید این سبد نیاز دارد.', 'hamishop-elementor-widgets' ),
			102  => __( 'کد تخفیف «%s» برای مبلغ این سبد قابل استفاده نیست.', 'hamishop-elementor-widgets' ),
			103  => __( 'کد تخفیف «%s» منقضی شده است.', 'hamishop-elementor-widgets' ),
			104  => __( 'کد تخفیف «%s» به سقف تعداد استفاده رسیده است.', 'hamishop-elementor-widgets' ),
			105  => __( 'کد تخفیف «%s» برای کالاهای این سبد معتبر نیست.', 'hamishop-elementor-widgets' ),
			106  => __( 'کد تخفیف «%s» برای دسته‌بندی‌های این سبد معتبر نیست.', 'hamishop-elementor-widgets' ),
			107  => __( 'کد تخفیف «%s» برای این سبد قابل اعمال نیست.', 'hamishop-elementor-widgets' ),
			1    => __( 'کد تخفیف «%s» قبلاً روی این سبد اعمال شده است.', 'hamishop-elementor-widgets' ),
			2    => __( 'کد تخفیف را وارد کنید.', 'hamishop-elementor-widgets' ),
			6    => __( 'کد تخفیف «%s» قبلاً استفاده شده و فقط یک‌بار برای هر خریدار معتبر است.', 'hamishop-elementor-widgets' ),
		);

		if ( ! isset( $messages[ (int) $error_code ] ) ) {
			return $error;
		}

		$text = $messages[ (int) $error_code ];

		/* The "please enter a code" message has no placeholder. */
		$message = false === strpos( $text, '%s' ) ? $text : sprintf( $text, $code );

		/**
		 * Final say on a coupon error message.
		 *
		 * @param string $message Formatted message.
		 * @param int    $error_code WC error constant.
		 * @param string $code    Coupon code.
		 */
		return (string) apply_filters( 'hsw_coupon_error_message', $message, (int) $error_code, $code );
	}

	/**
	 * Same voice for the success message.
	 *
	 * @param string $message    Original message.
	 * @param int    $msg_code   WC_COUPON::WC_COUPON_MESSAGE_* constant.
	 * @param mixed  $coupon     Coupon object.
	 * @return string
	 */
	public function filter_message( $message, $msg_code, $coupon ) {
		$code = $this->code( $coupon );

		if ( '' === $code ) {
			return $message;
		}

		$messages = array(
			1 => __( 'کد تخفیف «%s» با موفقیت اعمال شد.', 'hamishop-elementor-widgets' ),
			2 => __( 'کد تخفیف «%s» حذف شد.', 'hamishop-elementor-widgets' ),
		);

		if ( ! isset( $messages[ (int) $msg_code ] ) ) {
			return $message;
		}

		$text = sprintf( $messages[ (int) $msg_code ], $code );

		/**
		 * Final say on a coupon success message.
		 *
		 * @param string $text Formatted message.
		 * @param int    $msg_code Message constant.
		 * @param string $code Coupon code.
		 */
		return (string) apply_filters( 'hsw_coupon_success_message', $text, (int) $msg_code, $code );
	}
}
