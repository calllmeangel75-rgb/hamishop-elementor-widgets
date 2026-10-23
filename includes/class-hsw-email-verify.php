<?php
/**
 * Email verification for phone-first accounts.
 *
 * Stores that log customers in with a phone OTP still collect an email at
 * registration, but nothing ever proves it belongs to them — so order mail,
 * invoices and password recovery all point at an address nobody has confirmed.
 *
 * This adds the missing step: an account created through the phone OTP gets a
 * quiet banner in the panel (dashboard, orders, account details) asking to
 * confirm the address. Confirming sends a 6-digit code to that mailbox; entering
 * it marks the address verified and links it to the account.
 *
 * Deliberately small: two AJAX actions, two user-meta keys, no new table, no
 * cron, and nothing runs at all once the address is verified.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Email_Verify {

	/** Timestamp of the last successful verification. */
	const META_VERIFIED = '_hsw_email_verified';

	/** Hash of the pending code. */
	const META_CODE = '_hsw_email_code';

	/** When the pending code was issued. */
	const META_CODE_AT = '_hsw_email_code_at';

	/** Address the pending code was sent to. */
	const META_CODE_FOR = '_hsw_email_code_for';

	/** Failed attempts against the current code. */
	const META_TRIES = '_hsw_email_code_tries';

	/** Code lifetime, in seconds. */
	const TTL = 900;

	/** Minimum gap between two sends, in seconds. */
	const RESEND = 60;

	/** Wrong guesses allowed before the code is burnt. */
	const MAX_TRIES = 5;

	/** @var HSW_Email_Verify|null */
	private static $instance = null;

	/** @var HSW_Account */
	private $account;

	/**
	 * @param HSW_Account $account Account service (for icons + settings).
	 */
	public static function instance( $account = null ) {
		if ( null === self::$instance ) {
			self::$instance = new self( $account );
		}
		return self::$instance;
	}

	private function __construct( $account ) {
		$this->account = $account;

		add_action( 'wp_ajax_hsw_email_send', array( $this, 'ajax_send' ) );
		add_action( 'wp_ajax_hsw_email_verify', array( $this, 'ajax_verify' ) );
	}

	/* ---------------------------------------------------------------------
	 * State
	 * ------------------------------------------------------------------ */

	/**
	 * Was this account created through the phone OTP flow?
	 *
	 * `_hsw_auth_phone` is written when the OTP login creates the user, so this
	 * also covers accounts registered before this feature existed.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_phone_account( $user_id ) {
		return '' !== (string) get_user_meta( (int) $user_id, '_hsw_auth_phone', true );
	}

	/**
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_verified( $user_id ) {
		return '' !== (string) get_user_meta( (int) $user_id, self::META_VERIFIED, true );
	}

	/**
	 * Should the panel ask this user to confirm an address?
	 *
	 * Only phone-first accounts are nagged: a customer who registered with a
	 * password already went through WooCommerce's own email confirmation.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function needs_verification( $user_id ) {
		$user_id = (int) $user_id;
		if ( ! $user_id || self::is_verified( $user_id ) ) {
			return false;
		}
		if ( ! self::is_phone_account( $user_id ) ) {
			return false;
		}

		$user = get_user_by( 'id', $user_id );

		return $user && is_email( $user->user_email );
	}

	/**
	 * Is the feature switched on in the account settings?
	 *
	 * @return bool
	 */
	private function enabled() {
		$s = $this->account ? $this->account->settings() : array();
		return ! isset( $s['general']['email_verify'] ) || 'yes' === $s['general']['email_verify'];
	}

	/* ---------------------------------------------------------------------
	 * Markup
	 * ------------------------------------------------------------------ */

	/**
	 * Banner shown while the address is unconfirmed.
	 *
	 * @param array $ctx Account context.
	 * @return string
	 */
	public function banner( $ctx ) {
		if ( ! $this->enabled() ) {
			return '';
		}

		$user_id = isset( $ctx['user_id'] ) ? (int) $ctx['user_id'] : get_current_user_id();
		if ( ! self::needs_verification( $user_id ) ) {
			return '';
		}

		$user  = get_user_by( 'id', $user_id );
		$email = $user ? $user->user_email : '';
		$id    = 'hsw-verify-' . wp_rand( 1000, 9999 );

		$html  = '<section class="hsw-ac__verify" data-hsw-verify>';
		$html .= '<span class="hsw-ac__verify-ic" aria-hidden="true">' . $this->icon( 'mail' ) . '</span>';
		$html .= '<div class="hsw-ac__verify-copy"><strong>' . esc_html__( 'ایمیل شما هنوز تأیید نشده است', 'hamishop-elementor-widgets' ) . '</strong>';
		$html .= '<p>' . esc_html__( 'با تأیید ایمیل، فاکتور و وضعیت سفارش‌ها را هم در صندوق دریافت می‌کنید و بازیابی رمز عبور ممکن می‌شود.', 'hamishop-elementor-widgets' ) . '</p></div>';
		$html .= '<button type="button" class="hsw-ac__btn hsw-ac__btn--primary hsw-ac__verify-open" data-hsw-verify-open>' . esc_html__( 'تأیید ایمیل', 'hamishop-elementor-widgets' ) . '</button>';

		$html .= '<div class="hsw-ac__verify-form" data-hsw-verify-form hidden>';
		$html .= '<div class="hsw-ac-field"><label for="' . esc_attr( $id ) . '-email">' . esc_html__( 'ایمیل شما', 'hamishop-elementor-widgets' ) . '</label>';
		$html .= '<div class="hsw-ac-input-wrap">' . $this->icon( 'mail' ) . '<input type="email" id="' . esc_attr( $id ) . '-email" data-hsw-verify-email dir="ltr" autocomplete="email" value="' . esc_attr( $email ) . '" /></div></div>';
		$html .= '<button type="button" class="hsw-ac__btn hsw-ac__btn--primary hsw-ac__verify-send" data-hsw-verify-send>' . esc_html__( 'ارسال کد تأیید', 'hamishop-elementor-widgets' ) . '</button>';

		$html .= '<div class="hsw-ac__verify-step" data-hsw-verify-step hidden>';
		$html .= '<div class="hsw-ac-field"><label for="' . esc_attr( $id ) . '-code">' . esc_html__( 'کد ۶ رقمی ارسال‌شده', 'hamishop-elementor-widgets' ) . '</label>';
		$html .= '<div class="hsw-ac-input-wrap">' . $this->icon( 'shield' ) . '<input type="text" id="' . esc_attr( $id ) . '-code" data-hsw-verify-code inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" dir="ltr" /></div></div>';
		$html .= '<div class="hsw-ac__verify-actions"><button type="button" class="hsw-ac__btn hsw-ac__btn--primary" data-hsw-verify-confirm>' . esc_html__( 'تأیید ایمیل', 'hamishop-elementor-widgets' ) . '</button>';
		$html .= '<button type="button" class="hsw-ac-otp__resend" data-hsw-verify-resend>' . esc_html__( 'ارسال دوباره', 'hamishop-elementor-widgets' ) . '</button></div>';
		$html .= '</div>';

		$html .= '<p class="hsw-ac__verify-msg" data-hsw-verify-msg role="status" aria-live="polite"></p>';
		$html .= '</div></section>';

		return $html;
	}

	/**
	 * @param string $name Icon name.
	 * @return string
	 */
	private function icon( $name ) {
		if ( $this->account && method_exists( $this->account, 'icon' ) ) {
			return $this->account->icon( $name );
		}
		return '';
	}

	/* ---------------------------------------------------------------------
	 * AJAX
	 * ------------------------------------------------------------------ */

	/**
	 * Send a verification code to the address on file.
	 */
	public function ajax_send() {
		check_ajax_referer( 'hsw_email_verify', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'ابتدا وارد حساب خود شوید.', 'hamishop-elementor-widgets' ) ), 401 );
		}

		$user_id = get_current_user_id();
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'ایمیل واردشده معتبر نیست.', 'hamishop-elementor-widgets' ) ), 400 );
		}

		/* Never let a customer claim an address that already belongs to someone. */
		$owner = email_exists( $email );
		if ( $owner && (int) $owner !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'این ایمیل قبلاً روی حساب دیگری ثبت شده است. ایمیل دیگری وارد کنید.', 'hamishop-elementor-widgets' ) ), 409 );
		}

		$last = (int) get_user_meta( $user_id, self::META_CODE_AT, true );
		if ( $last && ( time() - $last ) < self::RESEND ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: seconds left. */
						__( 'لطفاً %s ثانیه دیگر دوباره تلاش کنید.', 'hamishop-elementor-widgets' ),
						HSW_Helpers::fa_num( self::RESEND - ( time() - $last ) )
					),
				),
				429
			);
		}

		$code = (string) wp_rand( 100000, 999999 );

		update_user_meta( $user_id, self::META_CODE, wp_hash( $code . '|' . $user_id ) );
		update_user_meta( $user_id, self::META_CODE_AT, time() );
		update_user_meta( $user_id, self::META_CODE_FOR, $email );
		update_user_meta( $user_id, self::META_TRIES, 0 );

		$sent = $this->send_code( $email, $code );

		if ( is_wp_error( $sent ) || ! $sent ) {
			delete_user_meta( $user_id, self::META_CODE );
			delete_user_meta( $user_id, self::META_CODE_AT );
			wp_send_json_error(
				array( 'message' => __( 'ارسال ایمیل ناموفق بود. لطفاً بعداً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.', 'hamishop-elementor-widgets' ) ),
				500
			);
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: masked email address. */
					__( 'کد تأیید به %s ارسال شد.', 'hamishop-elementor-widgets' ),
					$this->mask_email( $email )
				),
				'email'   => $email,
			)
		);
	}

	/**
	 * Check the code and link the address to the account.
	 */
	public function ajax_verify() {
		check_ajax_referer( 'hsw_email_verify', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'ابتدا وارد حساب خود شوید.', 'hamishop-elementor-widgets' ) ), 401 );
		}

		$user_id = get_current_user_id();
		$code    = preg_replace( '/\D/', '', (string) ( isset( $_POST['code'] ) ? wp_unslash( $_POST['code'] ) : '' ) );

		if ( strlen( $code ) !== 6 ) {
			wp_send_json_error( array( 'message' => __( 'کد ۶ رقمی را کامل وارد کنید.', 'hamishop-elementor-widgets' ) ), 400 );
		}

		$stored = (string) get_user_meta( $user_id, self::META_CODE, true );
		$issued = (int) get_user_meta( $user_id, self::META_CODE_AT, true );
		$for    = (string) get_user_meta( $user_id, self::META_CODE_FOR, true );
		$tries  = (int) get_user_meta( $user_id, self::META_TRIES, true );

		if ( '' === $stored || ! $issued ) {
			wp_send_json_error( array( 'message' => __( 'کدی برای تأیید وجود ندارد. دوباره درخواست کد بدهید.', 'hamishop-elementor-widgets' ) ), 400 );
		}
		if ( ( time() - $issued ) > self::TTL ) {
			$this->clear_code( $user_id );
			wp_send_json_error( array( 'message' => __( 'کد منقضی شده است. دوباره درخواست کد بدهید.', 'hamishop-elementor-widgets' ) ), 410 );
		}
		if ( $tries >= self::MAX_TRIES ) {
			$this->clear_code( $user_id );
			wp_send_json_error( array( 'message' => __( 'تعداد تلاش‌ها بیش از حد شد. دوباره درخواست کد بدهید.', 'hamishop-elementor-widgets' ) ), 429 );
		}

		if ( ! hash_equals( $stored, wp_hash( $code . '|' . $user_id ) ) ) {
			update_user_meta( $user_id, self::META_TRIES, $tries + 1 );
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: attempts left. */
						__( 'کد نادرست است. %s تلاش باقی مانده.', 'hamishop-elementor-widgets' ),
						HSW_Helpers::fa_num( max( 0, self::MAX_TRIES - $tries - 1 ) )
					),
				),
				400
			);
		}

		/* Link the address to the account. */
		$updated = wp_update_user(
			array(
				'ID'         => $user_id,
				'user_email' => $for,
			)
		);
		if ( is_wp_error( $updated ) ) {
			wp_send_json_error( array( 'message' => $updated->get_error_message() ), 400 );
		}

		update_user_meta( $user_id, 'billing_email', $for );
		update_user_meta( $user_id, self::META_VERIFIED, time() );
		$this->clear_code( $user_id );

		wp_send_json_success(
			array(
				'message' => __( 'ایمیل شما تأیید و به حساب متصل شد.', 'hamishop-elementor-widgets' ),
				'email'   => $for,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * @param int $user_id User ID.
	 */
	private function clear_code( $user_id ) {
		delete_user_meta( $user_id, self::META_CODE );
		delete_user_meta( $user_id, self::META_CODE_AT );
		delete_user_meta( $user_id, self::META_CODE_FOR );
		delete_user_meta( $user_id, self::META_TRIES );
	}

	/**
	 * Plain-text message — no template, no inline CSS, best deliverability.
	 *
	 * @param string $email Recipient.
	 * @param string $code  Six-digit code.
	 * @return bool
	 */
	private function send_code( $email, $code ) {
		$brand   = class_exists( 'HSW_Branding' ) ? HSW_Branding::name() : get_bloginfo( 'name' );
		$subject = sprintf(
			/* translators: %s: store name. */
			__( '%s — کد تأیید ایمیل', 'hamishop-elementor-widgets' ),
			$brand
		);

		$body  = sprintf(
			/* translators: %s: store name. */
			__( 'سلام، برای تأیید ایمیل خود در %s این کد را در پنل حساب کاربری وارد کنید:', 'hamishop-elementor-widgets' ),
			$brand
		) . "\n\n";
		$body .= $code . "\n\n";
		$body .= sprintf(
			/* translators: %d: minutes. */
			__( 'این کد %s دقیقه اعتبار دارد.', 'hamishop-elementor-widgets' ),
			HSW_Helpers::fa_num( (int) round( self::TTL / 60 ) )
		) . "\n";
		$body .= __( 'اگر این درخواست از طرف شما نبوده، این ایمیل را نادیده بگیرید.', 'hamishop-elementor-widgets' ) . "\n";

		return (bool) wp_mail( $email, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
	}

	/**
	 * a***@example.com — enough to recognise, not enough to harvest.
	 *
	 * @param string $email Address.
	 * @return string
	 */
	private function mask_email( $email ) {
		$parts = explode( '@', (string) $email );
		if ( count( $parts ) !== 2 ) {
			return $email;
		}
		$name = function_exists( 'mb_substr' ) ? mb_substr( $parts[0], 0, 1 ) : substr( $parts[0], 0, 1 );
		return $name . '***@' . $parts[1];
	}
}
