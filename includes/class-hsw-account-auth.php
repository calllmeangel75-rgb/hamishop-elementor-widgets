<?php
/**
 * HamiShop account authentication channels.
 *
 * Adds phone OTP login/registration and optional two-factor authentication on
 * top of WooCommerce native account handling. Bale is supported with its free
 * Bot API; WhatsApp supports Meta Cloud API or a self-hosted bridge.
 *
 * All codes are stored as password hashes in short-lived transients and every
 * AJAX endpoint is nonce-protected and rate limited.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

final class HSW_Account_Auth {

	/** @var HSW_Account */
	private $account;

	/** @var bool */
	private $skip_2fa = false;

	public function __construct( $account ) {
		$this->account = $account;

		foreach ( array( 'hsw_otp_send', 'hsw_otp_verify', 'hsw_2fa_verify', 'hsw_2fa_resend' ) as $action ) {
			add_action( 'wp_ajax_nopriv_' . $action, array( $this, 'ajax_' . str_replace( 'hsw_', '', $action ) ) );
			add_action( 'wp_ajax_' . $action, array( $this, 'ajax_' . str_replace( 'hsw_', '', $action ) ) );
		}
		add_action( 'wp_ajax_hsw_2fa_setup_send', array( $this, 'ajax_2fa_setup_send' ) );
		add_action( 'wp_ajax_hsw_2fa_setup_verify', array( $this, 'ajax_2fa_setup_verify' ) );
		add_action( 'wp_ajax_hsw_2fa_disable', array( $this, 'ajax_2fa_disable' ) );

		add_action( 'init', array( $this, 'maybe_handle_telegram_webhook' ), 0 );
		add_action( 'wp_login', array( $this, 'maybe_start_2fa' ), 99, 2 );
	}

	/* ---------------------------------------------------------------------
	 * Settings and channel helpers
	 * ------------------------------------------------------------------ */

	private function s( $path, $fallback = '' ) {
		return $this->account->setting( 'auth.' . $path, $fallback );
	}

	public function enabled() {
		return 'yes' === $this->s( 'otp_enabled', 'no' );
	}

	public function channel_enabled( $channel ) {
		if ( 'sms' === $channel ) {
			return 'yes' === $this->s( 'otp_sms', 'no' ) && $this->third_party_sms_active();
		}
		if ( 'whatsapp' === $channel ) {
			return 'yes' === $this->s( 'otp_whatsapp', 'no' ) && $this->whatsapp_configured();
		}
		if ( 'telegram' === $channel ) {
			return 'yes' === $this->s( 'otp_telegram', 'no' ) && '' !== trim( (string) $this->s( 'telegram_token' ) );
		}
		if ( 'email' === $channel ) {
			/* On by default: no API key, no per-message cost. */
			return 'yes' === $this->s( 'otp_email', 'yes' );
		}
		return false;
	}

	/**
	 * Is a known SMS/OTP plugin active?
	 *
	 * Stores usually already pay for one (Digits, Melipayamak, Kavenegar,
	 * WooCommerce Persian SMS, WP SMS…). When one of them is there, sending
	 * through it is cheaper and the customer's number is already verified by
	 * that plugin, so the `sms` channel is offered first.
	 *
	 * @return bool
	 */
	public function third_party_sms_active() {
		$active = defined( 'DIGITS_VERSION' )
			|| class_exists( 'Melipayamak' )
			|| class_exists( 'Kavenegar' )
			|| function_exists( 'wp_sms_send' )
			|| function_exists( 'PWooSMS' )
			|| class_exists( 'WoocommerceIR_SMS_Helper' );

		return (bool) apply_filters( 'hsw_has_sms_provider', $active );
	}

	private function whatsapp_configured() {
		$provider = (string) $this->s( 'whatsapp_provider', 'cloud' );
		if ( 'bridge' === $provider ) {
			return '' !== trim( (string) $this->s( 'whatsapp_bridge_url' ) );
		}
		return '' !== trim( (string) $this->s( 'whatsapp_token' ) ) && '' !== trim( (string) $this->s( 'whatsapp_phone_id' ) );
	}

	/**
	 * Channels a customer may pick for OTP / 2FA (public alias).
	 *
	 * @return array<string,string>
	 */
	public function enabled_channels() {
		return $this->available_channels();
	}

	/**
	 * Where a 2FA code goes for this channel.
	 *
	 * @param int    $user_id User.
	 * @param string $channel Channel.
	 * @param string $typed   Phone typed in the form (sms only).
	 * @return string Email, phone or chat id.
	 */
	public function two_factor_recipient( $user_id, $channel, $typed = '' ) {
		if ( 'email' === $channel ) {
			$user = get_user_by( 'id', $user_id );
			return $user ? (string) $user->user_email : '';
		}
		if ( 'sms' === $channel ) {
			return '' !== $typed ? $typed : $this->user_phone( $user_id );
		}
		return $this->two_factor_phone( $user_id );
	}

	public function available_channels() {
		$out = array();
		/* SMS first: a third-party plugin already owns the number. */
		if ( $this->channel_enabled( 'sms' ) ) {
			$out['sms'] = __( 'پیامک', 'hamishop-elementor-widgets' );
		}
		if ( $this->channel_enabled( 'email' ) ) {
			$out['email'] = __( 'ایمیل', 'hamishop-elementor-widgets' );
		}
		if ( $this->channel_enabled( 'telegram' ) ) {
			$out['telegram'] = __( 'تلگرام', 'hamishop-elementor-widgets' );
		}
		if ( $this->channel_enabled( 'whatsapp' ) ) {
			$out['whatsapp'] = __( 'واتساپ', 'hamishop-elementor-widgets' );
		}
		return $out;
	}

	private function channel_label( $channel ) {
		$labels = array(
			'sms'      => __( 'پیامک', 'hamishop-elementor-widgets' ),
			'email'    => __( 'ایمیل', 'hamishop-elementor-widgets' ),
			'telegram' => __( 'تلگرام', 'hamishop-elementor-widgets' ),
			'whatsapp' => __( 'واتساپ', 'hamishop-elementor-widgets' ),
		);
		return isset( $labels[ $channel ] ) ? $labels[ $channel ] : $channel;
	}

	private function default_channel() {
		$default  = sanitize_key( (string) $this->s( 'otp_default_channel', 'email' ) );
		$channels = $this->available_channels();
		if ( isset( $channels[ $default ] ) ) {
			return $default;
		}
		/* A third-party SMS plugin wins when it is there. */
		if ( isset( $channels['sms'] ) ) {
			return 'sms';
		}
		$keys = array_keys( $channels );
		return $keys ? $keys[0] : '';
	}

	public function normalize_phone( $phone ) {
		$phone = strtr(
			(string) $phone,
			array(
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
				'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
				'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			)
		);
		$digits = preg_replace( '/[^0-9]/', '', $phone );
		if ( 0 === strpos( $digits, '0098' ) ) {
			$digits = '0' . substr( $digits, 4 );
		} elseif ( 0 === strpos( $digits, '98' ) && 12 === strlen( $digits ) ) {
			$digits = '0' . substr( $digits, 2 );
		} elseif ( 10 === strlen( $digits ) && 0 === strpos( $digits, '9' ) ) {
			$digits = '0' . $digits;
		}
		$digits = preg_match( '/^09[0-9]{9}$/', $digits ) ? $digits : '';
		return (string) apply_filters( 'hsw_normalize_phone', $digits, $phone );
	}

	/**
	 * Normalise a recipient: an email stays an email, a phone becomes digits.
	 *
	 * The OTP store, the rate limits and the transient keys all take this value,
	 * so an email address must not be run through the phone normaliser (which
	 * strips everything but digits and would turn a@b.com into a number).
	 *
	 * @param string $value Raw recipient.
	 * @return string
	 */
	private function norm( $value ) {
		$value = trim( (string) $value );
		if ( false !== strpos( $value, '@' ) ) {
			return strtolower( $value );
		}
		return $this->normalize_phone( $value );
	}

	private function mask_phone( $phone ) {
		$phone = $this->normalize_phone( $phone );
		return $phone ? substr( $phone, 0, 4 ) . '***' . substr( $phone, -3 ) : '';
	}

	/**
	 * The number this account has *proved* it owns.
	 *
	 * Only `_hsw_auth_phone` counts, and it is written exclusively after a code
	 * sent to that number was entered correctly. `billing_phone` is typed by the
	 * customer at checkout and never verified — matching logins against it meant
	 * anyone who entered someone else's number (by typo or on purpose) could sign
	 * in as that person.
	 *
	 * @param int $user_id User ID.
	 * @return string Normalised number, '' when the account has none.
	 */
	private function user_phone( $user_id ) {
		return $this->normalize_phone( (string) get_user_meta( $user_id, '_hsw_auth_phone', true ) );
	}

	/**
	 * Number used for the second factor — its own key, never the login number.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function two_factor_phone( $user_id ) {
		return $this->normalize_phone( (string) get_user_meta( $user_id, '_hsw_2fa_phone', true ) );
	}

	/**
	 * Which account already owns this verified number?
	 *
	 * @param string $phone Normalised number.
	 * @return int User ID, 0 when free.
	 */
	private function phone_owner( $phone ) {
		global $wpdb;

		$phone = $this->normalize_phone( $phone );
		if ( ! $phone ) {
			return 0;
		}

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_hsw_auth_phone' AND meta_value = %s LIMIT 5",
				$phone
			)
		);

		foreach ( $ids as $id ) {
			if ( $this->user_phone( (int) $id ) === $phone ) {
				return (int) $id;
			}
		}

		return 0;
	}

	/**
	 * Bind a verified number to an account, refusing numbers owned elsewhere.
	 *
	 * @param int    $user_id User ID.
	 * @param string $phone   Normalised number.
	 * @return true|WP_Error
	 */
	private function claim_phone( $user_id, $phone ) {
		$phone = $this->normalize_phone( $phone );

		if ( ! $phone ) {
			return new WP_Error( 'hsw_bad_phone', __( 'شماره موبایل معتبر نیست.', 'hamishop-elementor-widgets' ) );
		}

		$owner = $this->phone_owner( $phone );
		if ( $owner && $owner !== (int) $user_id ) {
			return new WP_Error( 'hsw_phone_taken', __( 'این شماره قبلاً روی حساب دیگری تأیید شده است. با پشتیبانی تماس بگیرید.', 'hamishop-elementor-widgets' ) );
		}

		update_user_meta( $user_id, '_hsw_auth_phone', $phone );

		return true;
	}

	/**
	 * @param string $phone Normalised number.
	 * @return WP_User|null
	 */
	private function find_user_by_phone( $phone ) {
		$owner = $this->phone_owner( $phone );

		return $owner ? get_user_by( 'id', $owner ) : null;
	}

	/**
	 * May this account sign in with a one-time code?
	 *
	 * A phone number is a single factor. Staff accounts carry far more than a
	 * customer account, so passwordless entry is refused for them — they keep the
	 * code as a *second* factor after the password instead.
	 *
	 * @param WP_User|null $user User.
	 * @return bool
	 */
	private function otp_allowed( $user ) {
		if ( ! $user instanceof WP_User ) {
			return true;
		}

		if ( user_can( $user, 'manage_options' ) || user_can( $user, 'manage_woocommerce' ) || user_can( $user, 'edit_others_posts' ) ) {
			return (bool) apply_filters( 'hsw_otp_allowed_privileged', false, $user );
		}

		$roles = (array) apply_filters( 'hsw_otp_allowed_roles', array( 'customer', 'subscriber' ), $user );

		return (bool) array_intersect( (array) $user->roles, $roles );
	}

	private function telegram_chat_id( $user_id, $phone = '' ) {
		$chat = get_user_meta( $user_id, '_hsw_telegram_chat_id', true );
		if ( $chat ) {
			return (string) $chat;
		}
		$phone = $this->normalize_phone( $phone ? $phone : $this->user_phone( $user_id ) );
		if ( ! $phone ) {
			return '';
		}
		$contacts = get_option( 'hsw_telegram_contacts', array() );
		return is_array( $contacts ) && ! empty( $contacts[ $phone ] ) ? (string) $contacts[ $phone ] : '';
	}

	/**
	 * Public deep link to the shop bot (`?start=` carries a one-time token).
	 *
	 * @param string $token Optional link token.
	 * @return string
	 */
	public function telegram_link( $token = '' ) {
		$bot = trim( (string) $this->s( 'telegram_bot' ) );
		if ( '' === $bot ) {
			return '';
		}
		$bot = ltrim( preg_replace( '/[^a-zA-Z0-9_]/', '', $bot ), '@' );
		$url = 'https://t.me/' . $bot;
		if ( $token ) {
			$url = add_query_arg( 'start', rawurlencode( $token ), $url );
		}
		return $url;
	}

	private function telegram_link_token( $user_id ) {
		$token = wp_generate_password( 28, false, false );
		set_transient( 'hsw_telegram_link_' . $token, (int) $user_id, 15 * MINUTE_IN_SECONDS );
		return $token;
	}

	/* ---------------------------------------------------------------------
	 * OTP storage and delivery
	 * ------------------------------------------------------------------ */

	private function code_key( $phone, $channel, $context ) {
		return 'hsw_otp_' . md5( $this->norm( $phone ) . '|' . $channel . '|' . $context );
	}

	private function store_code( $phone, $channel, $context, $user_id = 0 ) {
		$phone = $this->norm( $phone );
		$ttl   = max( 60, min( 900, absint( $this->s( 'otp_ttl', 300 ) ) ) );
		$code  = (string) wp_rand( 100000, 999999 );
		$data  = array(
			'hash'     => wp_hash_password( $code ),
			'phone'    => $phone,
			'channel'  => $channel,
			'context'  => $context,
			'user_id'  => (int) $user_id,
			'attempts' => 0,
			'expires'  => time() + $ttl,
		);
		set_transient( $this->code_key( $phone, $channel, $context ), $data, $ttl );
		return $code;
	}

	private function check_code( $phone, $channel, $context, $code ) {
		$key  = $this->code_key( $phone, $channel, $context );
		$data = get_transient( $key );
		if ( ! is_array( $data ) || empty( $data['hash'] ) || empty( $data['expires'] ) || $data['expires'] < time() ) {
			delete_transient( $key );
			return new WP_Error( 'hsw_otp_expired', __( 'کد وارد شده منقضی شده است. لطفاً کد جدید بگیرید.', 'hamishop-elementor-widgets' ) );
		}
		$max = max( 1, min( 20, absint( $this->s( 'otp_max_attempts', 5 ) ) ) );
		if ( (int) $data['attempts'] >= $max ) {
			delete_transient( $key );
			return new WP_Error( 'hsw_otp_attempts', __( 'تعداد تلاش‌ها بیش از حد مجاز است. کد جدید بگیرید.', 'hamishop-elementor-widgets' ) );
		}
		if ( ! wp_check_password( (string) $code, $data['hash'] ) ) {
			$data['attempts'] = (int) $data['attempts'] + 1;
			$remaining        = max( 1, $data['expires'] - time() );
			set_transient( $key, $data, $remaining );
			return new WP_Error( 'hsw_otp_invalid', __( 'کد وارد شده صحیح نیست.', 'hamishop-elementor-widgets' ) );
		}
		delete_transient( $key );
		return $data;
	}

	private function rate_limit( $phone, $channel, $context ) {
		$key = 'hsw_otp_cool_' . md5( $this->norm( $phone ) . '|' . $channel . '|' . $context );
		if ( get_transient( $key ) ) {
			return new WP_Error( 'hsw_otp_cooldown', __( 'کد قبلی هنوز معتبر است. کمی بعد دوباره تلاش کنید.', 'hamishop-elementor-widgets' ) );
		}
		$cooldown = max( 20, min( 900, absint( $this->s( 'otp_resend', 60 ) ) ) );
		set_transient( $key, 1, $cooldown );
		return true;
	}

	private function clear_rate_limit( $phone, $channel, $context ) {
		delete_transient( 'hsw_otp_cool_' . md5( $this->norm( $phone ) . '|' . $channel . '|' . $context ) );
	}

	/**
	 * Per-IP, per-number and per-channel send ceilings.
	 *
	 * The resend cooldown above only stops one number from being hammered; these
	 * counters stop a script from walking through thousands of numbers from one
	 * connection, or from draining the WhatsApp quota in a minute.
	 *
	 * @param string $phone   Normalised number.
	 * @param string $channel Channel.
	 * @return true|WP_Error
	 */
	private function check_send_throttle( $phone, $channel ) {
		$per_ip = HSW_Throttle::hit(
			'otp_send_ip:' . HSW_Throttle::ip(),
			(int) apply_filters( 'hsw_otp_ip_hourly', 20 ),
			HOUR_IN_SECONDS
		);
		if ( ! $per_ip['allowed'] ) {
			return new WP_Error( 'hsw_throttle_ip', __( 'تعداد درخواست کد از این اتصال زیاد بوده است. کمی بعد دوباره تلاش کنید.', 'hamishop-elementor-widgets' ) );
		}

		$per_day = HSW_Throttle::hit(
			'otp_send_day:' . $this->norm( $phone ),
			(int) apply_filters( 'hsw_otp_phone_daily', 10 ),
			DAY_IN_SECONDS
		);
		if ( ! $per_day['allowed'] ) {
			return new WP_Error( 'hsw_throttle_phone', __( 'برای این شماره امروز بیش از حد کد فرستاده شده است. فردا دوباره تلاش کنید.', 'hamishop-elementor-widgets' ) );
		}

		if ( 'whatsapp' === $channel ) {
			$per_minute = HSW_Throttle::hit(
				'otp_wa_minute',
				(int) apply_filters( 'hsw_otp_whatsapp_per_minute', 30 ),
				MINUTE_IN_SECONDS
			);
			if ( ! $per_minute['allowed'] ) {
				return new WP_Error( 'hsw_throttle_wa', __( 'سامانهٔ واتساپ شلوغ است. یک دقیقهٔ دیگر تلاش کنید.', 'hamishop-elementor-widgets' ) );
			}
		}

		return true;
	}

	/**
	 * Wrong codes are counted per number, across every code issued to it.
	 *
	 * The per-code attempt counter resets as soon as a new code is issued, so on
	 * its own it lets an attacker try five guesses on every resend. This counter
	 * spans resends.
	 *
	 * @param string $phone Normalised number.
	 */
	private function note_failed_verify( $phone ) {
		HSW_Throttle::hit(
			'otp_verify_fail:' . $this->norm( $phone ),
			(int) apply_filters( 'hsw_otp_verify_failures', 10 ),
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Is this number locked out after too many wrong codes?
	 *
	 * @param string $phone Normalised number.
	 * @return int Seconds left, 0 when not locked.
	 */
	private function verify_lock_seconds( $phone ) {
		$state = HSW_Throttle::peek( 'otp_verify_fail:' . $this->norm( $phone ) );
		$limit = (int) apply_filters( 'hsw_otp_verify_failures', 10 );

		return ( $state['hits'] >= $limit && $state['retry_after'] > 0 ) ? (int) $state['retry_after'] : 0;
	}

	/**
	 * Clear the failure counter after a successful sign-in.
	 *
	 * @param string $phone Normalised number.
	 */
	private function clear_failed_verify( $phone ) {
		HSW_Throttle::clear( 'otp_verify_fail:' . $this->norm( $phone ) );
	}

	private function message( $code ) {
		$template = (string) $this->s( 'otp_message', 'کد ورود شما به {site}: {code}' );
		return str_replace(
			array( '{code}', '{site}' ),
			array( $code, wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			$template
		);
	}

	private function deliver_code( $channel, $phone, $code, $user_id = 0, $extra = array() ) {
		$skip = (bool) apply_filters( 'hsw_otp_skip_delivery', false, $channel, $phone, $code, $user_id );
		if ( $skip ) {
			return true;
		}

		if ( 'email' === $channel ) {
			if ( ! is_email( $phone ) ) {
				return new WP_Error( 'hsw_email_invalid', __( 'ایمیل وارد‌شده معتبر نیست.', 'hamishop-elementor-widgets' ) );
			}
			return $this->email_send( $phone, $code );
		}

		if ( 'telegram' === $channel ) {
			$chat = $this->telegram_chat_id( $user_id, $phone );
			if ( ! $chat ) {
				return new WP_Error(
					'hsw_telegram_not_connected',
					__( 'برای دریافت کد در تلگرام، ابتدا ربات فروشگاه را باز کنید و شماره موبایل خود را ارسال کنید.', 'hamishop-elementor-widgets' ),
					array( 'link' => $this->telegram_link() )
				);
			}
			return $this->telegram_send( $chat, $this->message( $code ) );
		}

		if ( 'sms' === $channel ) {
			return $this->sms_send( $phone, $code );
		}

		if ( 'whatsapp' === $channel ) {
			return $this->whatsapp_send( $phone, $this->message( $code ) );
		}

		return new WP_Error( 'hsw_otp_channel', __( 'کانال ارسال کد پشتیبانی نمی‌شود.', 'hamishop-elementor-widgets' ) );
	}

	private function telegram_send( $chat_id, $text, $reply_markup = null ) {
		$token = trim( (string) $this->s( 'telegram_token' ) );
		$token = preg_replace( '/[^a-zA-Z0-9:_-]/', '', $token );
		if ( ! $token || ! $chat_id ) {
			return new WP_Error( 'hsw_telegram_config', __( 'تنظیمات ربات تلگرام کامل نیست.', 'hamishop-elementor-widgets' ) );
		}

		$base = untrailingslashit( (string) apply_filters( 'hsw_telegram_api_base', 'https://api.telegram.org' ) );
		$body = array(
			'chat_id' => $chat_id,
			'text'    => $text,
		);
		if ( $reply_markup ) {
			$body['reply_markup'] = $reply_markup;
		}

		$response = wp_remote_post(
			$base . '/bot' . $token . '/sendMessage',
			array(
				'timeout'     => 15,
				'redirection' => 2,
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'        => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			return new WP_Error( 'hsw_telegram_http', __( 'تلگرام پیام را نپذیرفت؛ توکن و اتصال ربات را بررسی کنید.', 'hamishop-elementor-widgets' ) );
		}
		return true;
	}

	/**
	 * Send the 6-digit code by email.
	 *
	 * The From address/name are applied with temporary filters that are removed
	 * immediately after `wp_mail()`, so the rest of the site keeps sending with
	 * its own identity.
	 *
	 * @param string $to   Recipient email.
	 * @param string $code Six-digit code.
	 * @return true|WP_Error
	 */
	private function email_send( $to, $code ) {
		if ( ! is_email( $to ) ) {
			return new WP_Error( 'hsw_email_invalid', __( 'ایمیل وارد‌شده معتبر نیست.', 'hamishop-elementor-widgets' ) );
		}

		$from_email = sanitize_email( (string) $this->s( 'email_from_address', get_option( 'admin_email' ) ) );
		if ( ! $from_email || ! is_email( $from_email ) ) {
			$from_email = (string) get_option( 'admin_email' );
		}
		$from_name = trim( (string) $this->s( 'email_from_name', get_bloginfo( 'name' ) ) );
		if ( '' === $from_name ) {
			$from_name = (string) get_bloginfo( 'name' );
		}

		$ttl  = max( 1, (int) round( max( 60, min( 900, absint( $this->s( 'otp_ttl', 300 ) ) ) ) / 60 ) );
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		$subject = sprintf(
			/* translators: %s: site name. */
			__( 'کد تأیید ورود به %s', 'hamishop-elementor-widgets' ),
			$site
		);

		$html  = '<!DOCTYPE html><html dir="rtl" lang="fa-IR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>';
		$html .= '<body style="margin:0;padding:0;background:#f3f6fa;font-family:Tahoma,Arial,sans-serif">';
		$html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6fa;padding:28px 12px"><tr><td align="center">';
		$html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border:1px solid #e6ebf2;border-radius:16px;overflow:hidden">';
		$html .= '<tr><td style="background:#0e2a47;padding:20px 24px;color:#ffffff;font-size:17px;font-weight:bold">' . esc_html( $site ) . '</td></tr>';
		$html .= '<tr><td style="padding:24px">';
		$html .= '<p style="margin:0 0 14px;color:#334155;font-size:14px;line-height:2">' . esc_html__( 'کد تأیید ورود شما:', 'hamishop-elementor-widgets' ) . '</p>';
		$html .= '<div style="direction:ltr;text-align:center;font-size:34px;font-weight:bold;letter-spacing:10px;color:#0e2a47;background:#f5f8fc;border:1px dashed #cbd5e1;border-radius:12px;padding:16px 8px">' . esc_html( $code ) . '</div>';
		$html .= '<p style="margin:16px 0 0;color:#64748b;font-size:12px;line-height:2">' . esc_html(
			sprintf(
				/* translators: %s: minutes. */
				__( 'این کد تا %s دقیقه معتبر است. اگر شما درخواست نداده‌اید، این ایمیل را نادیده بگیرید.', 'hamishop-elementor-widgets' ),
				HSW_Helpers::fa_num( $ttl )
			)
		) . '</p>';
		$html .= '</td></tr></table></td></tr></table></body></html>';

		$from_cb = static function () use ( $from_email ) {
			return $from_email;
		};
		$name_cb = static function () use ( $from_name ) {
			return $from_name;
		};
		add_filter( 'wp_mail_from', $from_cb, 99 );
		add_filter( 'wp_mail_from_name', $name_cb, 99 );

		$sent = wp_mail(
			$to,
			$subject,
			$html,
			array( 'Content-Type: text/html; charset=UTF-8' )
		);

		remove_filter( 'wp_mail_from', $from_cb, 99 );
		remove_filter( 'wp_mail_from_name', $name_cb, 99 );

		if ( ! $sent ) {
			return new WP_Error( 'hsw_email_failed', __( 'ارسال ایمیل انجام نشد؛ تنظیمات SMTP سایت را بررسی کنید.', 'hamishop-elementor-widgets' ) );
		}
		return true;
	}

	/**
	 * Send the code through whichever SMS plugin the site already runs.
	 *
	 * @param string $phone Normalised phone.
	 * @param string $code  Six-digit code.
	 * @return true|WP_Error
	 */
	private function sms_send( $phone, $code ) {
		$message = $this->message( $code );

		/**
		 * Let a site (or an integration) deliver the SMS.
		 *
		 * Return true on success, WP_Error on failure, null to fall through to
		 * the built-in adapters.
		 */
		$handled = apply_filters( 'hsw_send_sms_otp', null, $phone, $code, $message );
		if ( true === $handled ) {
			return true;
		}
		if ( is_wp_error( $handled ) ) {
			return $handled;
		}

		if ( function_exists( 'wp_sms_send' ) ) {
			$res = wp_sms_send( $phone, $message );
			return ( false === $res ) ? new WP_Error( 'hsw_sms_failed', __( 'ارسال پیامک انجام نشد.', 'hamishop-elementor-widgets' ) ) : true;
		}

		if ( function_exists( 'PWooSMS' ) ) {
			$sms = PWooSMS();
			if ( is_object( $sms ) && method_exists( $sms, 'SendSMS' ) ) {
				$sms->SendSMS( $phone, $message );
				return true;
			}
		}

		return new WP_Error(
			'hsw_sms_missing',
			__( 'افزونهٔ پیامکی فعالی پیدا نشد. کانال ایمیل یا تلگرام را انتخاب کنید.', 'hamishop-elementor-widgets' )
		);
	}

	private function whatsapp_number( $phone ) {
		$phone = $this->normalize_phone( $phone );
		if ( ! $phone ) {
			return '';
		}
		return '98' . substr( $phone, 1 );
	}

	private function whatsapp_send( $phone, $text ) {
		$phone = $this->whatsapp_number( $phone );
		if ( ! $phone ) {
			return new WP_Error( 'hsw_wa_phone', __( 'شماره موبایل معتبر نیست.', 'hamishop-elementor-widgets' ) );
		}

		$provider = (string) $this->s( 'whatsapp_provider', 'cloud' );
		if ( 'bridge' === $provider ) {
			$url = esc_url_raw( (string) $this->s( 'whatsapp_bridge_url' ) );
			if ( ! $url ) {
				return new WP_Error( 'hsw_wa_config', __( 'آدرس پل واتساپ تنظیم نشده است.', 'hamishop-elementor-widgets' ) );
			}
			$response = wp_remote_post(
				$url,
				array(
					'timeout' => 15,
					'headers' => array(
						'Content-Type'  => 'application/json',
						'Authorization' => 'Bearer ' . (string) $this->s( 'whatsapp_bridge_token' ),
					),
					'body'    => wp_json_encode(
						array(
							'phone'   => $phone,
							'message' => $text,
							'sender'  => (string) $this->s( 'whatsapp_sender' ),
						)
					),
				)
			);
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$status = (int) wp_remote_retrieve_response_code( $response );
			return ( $status >= 200 && $status < 300 ) ? true : new WP_Error( 'hsw_wa_http', __( 'ارسال پیام واتساپ ناموفق بود.', 'hamishop-elementor-widgets' ) );
		}

		$token    = trim( (string) $this->s( 'whatsapp_token' ) );
		$phone_id = trim( (string) $this->s( 'whatsapp_phone_id' ) );
		if ( ! $token || ! $phone_id ) {
			return new WP_Error( 'hsw_wa_config', __( 'تنظیمات WhatsApp Cloud API کامل نیست.', 'hamishop-elementor-widgets' ) );
		}
		$response = wp_remote_post(
			'https://graph.facebook.com/v21.0/' . rawurlencode( $phone_id ) . '/messages',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'messaging_product' => 'whatsapp',
						'to'                => $phone,
						'type'              => 'text',
						'text'              => array( 'body' => $text ),
					)
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		return ( $status >= 200 && $status < 300 ) ? true : new WP_Error( 'hsw_wa_http', __( 'ارسال پیام واتساپ ناموفق بود.', 'hamishop-elementor-widgets' ) );
	}

	/* ---------------------------------------------------------------------
	 * AJAX: OTP login/registration
	 * ------------------------------------------------------------------ */

	public function ajax_otp_send() {
		check_ajax_referer( 'hsw_otp', 'nonce' );
		if ( ! $this->enabled() ) {
			wp_send_json_error( array( 'message' => __( 'ورود با کد یکبارمصرف فعال نیست.', 'hamishop-elementor-widgets' ) ), 400 );
		}

		$channel = isset( $_POST['channel'] ) ? sanitize_key( wp_unslash( $_POST['channel'] ) ) : '';
		$context = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : 'login';

		/* Email is a recipient too: the same store, throttles and transients. */
		$raw   = ( 'email' === $channel )
			? ( isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '' )
			: ( isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '' );
		$phone = $this->norm( $raw );

		if ( 'email' === $channel && ! is_email( $phone ) ) {
			wp_send_json_error( array( 'message' => __( 'یک ایمیل معتبر وارد کنید.', 'hamishop-elementor-widgets' ) ), 400 );
		}
		if ( ! $phone ) {
			wp_send_json_error( array( 'message' => __( 'شماره موبایل را به‌صورت ۰۹xxxxxxxxx وارد کنید.', 'hamishop-elementor-widgets' ) ), 400 );
		}
		if ( ! in_array( $context, array( 'login', 'register' ), true ) ) {
			$context = 'login';
		}
		if ( ! $this->channel_enabled( $channel ) ) {
			wp_send_json_error( array( 'message' => __( 'این کانال ورود فعال نیست.', 'hamishop-elementor-widgets' ) ), 400 );
		}
		if ( 'register' === $context && ( 'yes' !== $this->s( 'otp_register', 'yes' ) || 'yes' !== get_option( 'woocommerce_enable_myaccount_registration' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'ثبت‌نام با کد یکبارمصرف غیرفعال است.', 'hamishop-elementor-widgets' ) ), 400 );
		}
		if ( 'login' === $context && 'yes' !== $this->s( 'otp_login', 'yes' ) ) {
			wp_send_json_error( array( 'message' => __( 'ورود با کد یکبارمصرف غیرفعال است.', 'hamishop-elementor-widgets' ) ), 400 );
		}

		$user = ( 'email' === $channel ) ? get_user_by( 'email', $phone ) : $this->find_user_by_phone( $phone );
		if ( 'login' === $context && ! $user ) {
			wp_send_json_error( array( 'message' => 'email' === $channel ? __( 'حسابی با این ایمیل پیدا نشد. از تب ثبت‌نام استفاده کنید.', 'hamishop-elementor-widgets' ) : __( 'حسابی با این شماره پیدا نشد. از تب ثبت‌نام استفاده کنید.', 'hamishop-elementor-widgets' ), 'switch_tab' => 'register' ), 404 );
		}
		if ( 'register' === $context && $user ) {
			wp_send_json_error( array( 'message' => __( 'این حساب قبلاً ثبت‌نام کرده است. از تب ورود استفاده کنید.', 'hamishop-elementor-widgets' ), 'switch_tab' => 'login' ), 409 );
		}

		/* A phone is one factor; staff accounts must not be reachable with it alone. */
		if ( 'login' === $context && $user && ! $this->otp_allowed( $user ) ) {
			wp_send_json_error( array( 'message' => __( 'برای حساب‌های مدیریتی ورود با کد یکبارمصرف فعال نیست. با نام کاربری و رمز عبور وارد شوید.', 'hamishop-elementor-widgets' ) ), 403 );
		}

		$throttle = $this->check_send_throttle( $phone, $channel );
		if ( is_wp_error( $throttle ) ) {
			wp_send_json_error( array( 'message' => $throttle->get_error_message() ), 429 );
		}

		$limit = $this->rate_limit( $phone, $channel, $context );
		if ( is_wp_error( $limit ) ) {
			wp_send_json_error( array( 'message' => $limit->get_error_message() ), 429 );
		}

		$code = $this->store_code( $phone, $channel, $context, $user ? $user->ID : 0 );
		$sent = $this->deliver_code( $channel, $phone, $code, $user ? $user->ID : 0 );
		if ( is_wp_error( $sent ) ) {
			delete_transient( $this->code_key( $phone, $channel, $context ) );
			$this->clear_rate_limit( $phone, $channel, $context );
			$data = array( 'message' => $sent->get_error_message() );
			$link = $sent->get_error_data();
			if ( is_array( $link ) && ! empty( $link['link'] ) ) {
				$data['link']      = esc_url_raw( $link['link'] );
				$data['link_text'] = __( 'باز کردن ربات تلگرام', 'hamishop-elementor-widgets' );
			}
			wp_send_json_error( $data, 400 );
		}

		wp_send_json_success(
			array(
				'message' => __( 'کد تأیید ارسال شد. ایمیل، تلگرام یا پیامک خود را بررسی کنید.', 'hamishop-elementor-widgets' ),
				'expires' => max( 60, min( 900, absint( $this->s( 'otp_ttl', 300 ) ) ) ),
				'channel' => $channel,
			)
		);
	}

	public function ajax_otp_verify() {
		check_ajax_referer( 'hsw_otp', 'nonce' );
		if ( ! $this->enabled() ) {
			wp_send_json_error( array( 'message' => __( 'ورود با کد یکبارمصرف فعال نیست.', 'hamishop-elementor-widgets' ) ), 400 );
		}

		$channel = isset( $_POST['channel'] ) ? sanitize_key( wp_unslash( $_POST['channel'] ) ) : '';
		$context = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : 'login';
		$code    = isset( $_POST['code'] ) ? preg_replace( '/[^0-9]/', '', wp_unslash( $_POST['code'] ) ) : '';

		$raw   = ( 'email' === $channel )
			? ( isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '' )
			: ( isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '' );
		$phone = $this->norm( $raw );
		if ( ! in_array( $context, array( 'login', 'register' ), true ) ) {
			$context = 'login';
		}
		if ( ! $this->channel_enabled( $channel ) ) {
			wp_send_json_error( array( 'message' => __( 'این کانال ورود فعال نیست.', 'hamishop-elementor-widgets' ) ), 400 );
		}
		if ( 'login' === $context && 'yes' !== $this->s( 'otp_login', 'yes' ) ) {
			wp_send_json_error( array( 'message' => __( 'ورود با کد یکبارمصرف غیرفعال است.', 'hamishop-elementor-widgets' ) ), 400 );
		}
		if ( 'register' === $context && ( 'yes' !== $this->s( 'otp_register', 'yes' ) || 'yes' !== get_option( 'woocommerce_enable_myaccount_registration' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'ثبت‌نام با کد یکبارمصرف غیرفعال است.', 'hamishop-elementor-widgets' ) ), 400 );
		}
		if ( ! $phone || ! $code ) {
			wp_send_json_error( array( 'message' => __( 'شناسه (موبایل یا ایمیل) و کد تأیید را کامل وارد کنید.', 'hamishop-elementor-widgets' ) ), 400 );
		}

		$user = ( 'email' === $channel ) ? get_user_by( 'email', $phone ) : $this->find_user_by_phone( $phone );
		if ( 'login' === $context && ! $user ) {
			wp_send_json_error( array( 'message' => __( 'حسابی با این شناسه پیدا نشد.', 'hamishop-elementor-widgets' ), 'switch_tab' => 'register' ), 404 );
		}

		$name  = '';
		$email = '';
		if ( 'register' === $context ) {
			if ( $user ) {
				wp_send_json_error( array( 'message' => __( 'این شماره قبلاً ثبت‌نام کرده است.', 'hamishop-elementor-widgets' ), 'switch_tab' => 'login' ), 409 );
			}
			$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			$email = ( 'email' === $channel ) ? $phone : ( isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '' );
			if ( ! $name || ! $email || ! is_email( $email ) ) {
				wp_send_json_error( array( 'message' => __( 'برای ثبت‌نام، نام و ایمیل معتبر را وارد کنید.', 'hamishop-elementor-widgets' ) ), 400 );
			}
			if ( email_exists( $email ) ) {
				wp_send_json_error( array( 'message' => __( 'این ایمیل قبلاً در سایت ثبت شده است.', 'hamishop-elementor-widgets' ) ), 409 );
			}
		}

		if ( 'login' === $context && $user && ! $this->otp_allowed( $user ) ) {
			wp_send_json_error( array( 'message' => __( 'برای حساب‌های مدیریتی ورود با کد یکبارمصرف فعال نیست. با نام کاربری و رمز عبور وارد شوید.', 'hamishop-elementor-widgets' ) ), 403 );
		}

		$locked = $this->verify_lock_seconds( $phone );
		if ( $locked > 0 ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: minutes left. */
						__( 'به دلیل تلاش‌های ناموفق، ورود با کد برای این شماره تا %s دقیقه قفل است.', 'hamishop-elementor-widgets' ),
						HSW_Helpers::fa_num( (int) ceil( $locked / 60 ) )
					),
				),
				429
			);
		}

		$checked = $this->check_code( $phone, $channel, $context, $code );
		if ( is_wp_error( $checked ) ) {
			if ( 'hsw_otp_invalid' === $checked->get_error_code() ) {
				$this->note_failed_verify( $phone );
			}
			wp_send_json_error( array( 'message' => $checked->get_error_message() ), 400 );
		}

		$this->clear_failed_verify( $phone );

		if ( 'login' === $context ) {
			$this->login_user( $user );
			wp_send_json_success(
				array(
					'message'  => __( 'ورود با موفقیت انجام شد.', 'hamishop-elementor-widgets' ),
					'redirect' => $this->redirect_after_login(),
				)
			);
		}

		$parts = preg_split( '/\s+/u', $name, 2 );
		$first = $parts[0];
		$last  = isset( $parts[1] ) ? $parts[1] : '';
		/*
		 * Username: for an email signup use the local part (not the last 10
		 * characters of the address — every `…@gmail.com` produced the same
		 * `hsw_@gmail.com`), for a phone the last ten digits.
		 */
		if ( 'email' === $channel ) {
			$base = sanitize_user( current( explode( '@', $email ) ), true );
		} else {
			$base = 'hsw_' . substr( $phone, -10 );
		}
		$base  = $base ? $base : 'hsw_user';
		$login = $base;
		$i     = 1;
		while ( username_exists( $login ) ) {
			$login = $base . '_' . $i;
			$i++;
			if ( $i > 20 ) {
				break;
			}
		}
		$password = wp_generate_password( 24, true, true );
		$new_user = wc_create_new_customer(
			$email,
			$login,
			$password,
			array(
				'first_name' => $first,
				'last_name'  => $last,
			)
		);
		if ( is_wp_error( $new_user ) ) {
			wp_send_json_error( array( 'message' => $new_user->get_error_message() ), 400 );
		}

		$user_id = (int) $new_user;
		update_user_meta( $user_id, 'billing_first_name', $first );
		update_user_meta( $user_id, 'billing_last_name', $last );

		if ( 'email' === $channel ) {
			/* The address proved ownership through the code; it is not a phone. */
			update_user_meta( $user_id, '_hsw_email_verified', time() );
		} else {
			update_user_meta( $user_id, 'billing_phone', $phone );
			update_user_meta( $user_id, '_hsw_auth_phone', $phone );
			$chat = $this->telegram_chat_id( 0, $phone );
			if ( $chat ) {
				update_user_meta( $user_id, '_hsw_telegram_chat_id', $chat );
			}
		}

		$user = get_user_by( 'id', $user_id );
		$this->login_user( $user );
		wp_send_json_success(
			array(
				'message'  => __( 'حساب شما ساخته شد و وارد شدید.', 'hamishop-elementor-widgets' ),
				'redirect' => $this->redirect_after_login(),
			)
		);
	}

	/**
	 * Where to go after a successful OTP login.
	 *
	 * The checkout gate sends guests here with `?redirect_to=…`; dropping that
	 * would make the customer start over from the account page.
	 *
	 * @return string
	 */
	private function redirect_after_login() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce checked by the caller.
		$to = isset( $_POST['redirect_to'] ) ? wp_unslash( $_POST['redirect_to'] ) : '';
		// phpcs:enable
		if ( $to ) {
			$safe = wp_validate_redirect( $to, '' );
			if ( $safe ) {
				return $safe;
			}
		}
		return wc_get_page_permalink( 'myaccount' );
	}

	private function login_user( $user ) {
		if ( ! $user instanceof WP_User ) {
			return;
		}
		$this->skip_2fa = true;
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		update_user_meta( $user->ID, '_hsw_last_otp_login', time() );
		do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
		$this->skip_2fa = false;
	}

	/* ---------------------------------------------------------------------
	 * Two-factor authentication
	 * ------------------------------------------------------------------ */

	public function is_2fa_challenge() {
		$token = isset( $_GET['hsw_2fa'] ) ? sanitize_key( wp_unslash( $_GET['hsw_2fa'] ) ) : '';
		if ( ! $token ) {
			return false;
		}
		$data = get_transient( 'hsw_2fa_' . $token );
		return is_array( $data ) && ! empty( $data['user_id'] ) && ! empty( $data['expires'] ) && $data['expires'] >= time();
	}

	public function maybe_start_2fa( $user_login, $user ) {
		if ( $this->skip_2fa || ! $this->enabled() || 'yes' !== $this->s( 'otp_2fa', 'yes' ) || ! $user instanceof WP_User ) {
			return;
		}
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
			return;
		}
		$channel = (string) get_user_meta( $user->ID, '_hsw_2fa_channel', true );
		if ( ! in_array( $channel, array( 'telegram', 'whatsapp', 'email', 'sms' ), true ) || 'yes' !== get_user_meta( $user->ID, '_hsw_2fa_enabled', true ) ) {
			return;
		}
		/* Email needs no phone; the rest do. */
		$phone = $this->two_factor_recipient( $user->ID, $channel );
		if ( ! $phone ) {
			return;
		}
		$challenge = $this->create_2fa_challenge( $user->ID, $channel, $phone );
		if ( is_wp_error( $challenge ) ) {
			wp_clear_auth_cookie();
			wp_safe_redirect( add_query_arg( 'hsw_2fa_error', 'delivery', wc_get_page_permalink( 'myaccount' ) ) );
			exit;
		}
		wp_clear_auth_cookie();
		wp_safe_redirect( add_query_arg( 'hsw_2fa', $challenge, wc_get_page_permalink( 'myaccount' ) ) );
		exit;
	}

	private function create_2fa_challenge( $user_id, $channel, $phone ) {
		$limit = $this->rate_limit( $phone, $channel, '2fa' );
		if ( is_wp_error( $limit ) ) {
			return $limit;
		}
		$token = wp_generate_password( 32, false, false );
		$code  = (string) wp_rand( 100000, 999999 );
		$ttl   = max( 60, min( 900, absint( $this->s( 'otp_ttl', 300 ) ) ) );
		$data  = array(
			'user_id' => (int) $user_id,
			'channel' => $channel,
			'phone'   => $phone,
			'hash'    => wp_hash_password( $code ),
			'attempts' => 0,
			'expires' => time() + $ttl,
		);
		set_transient( 'hsw_2fa_' . $token, $data, $ttl );
		$sent = $this->deliver_code( $channel, $phone, $code, $user_id, array( 'context' => '2fa' ) );
		if ( is_wp_error( $sent ) ) {
			delete_transient( 'hsw_2fa_' . $token );
			$this->clear_rate_limit( $phone, $channel, '2fa' );
			return $sent;
		}
		return $token;
	}

	public function ajax_2fa_verify() {
		check_ajax_referer( 'hsw_2fa', 'nonce' );
		$token = isset( $_POST['token'] ) ? sanitize_key( wp_unslash( $_POST['token'] ) ) : '';
		$code  = isset( $_POST['code'] ) ? preg_replace( '/[^0-9]/', '', wp_unslash( $_POST['code'] ) ) : '';
		$data  = $token ? get_transient( 'hsw_2fa_' . $token ) : false;
		if ( ! is_array( $data ) || empty( $data['expires'] ) || $data['expires'] < time() ) {
			wp_send_json_error( array( 'message' => __( 'درخواست ورود منقضی شده است. دوباره وارد شوید.', 'hamishop-elementor-widgets' ) ), 400 );
		}
		$max = max( 1, min( 20, absint( $this->s( 'otp_max_attempts', 5 ) ) ) );
		if ( (int) $data['attempts'] >= $max ) {
			delete_transient( 'hsw_2fa_' . $token );
			wp_send_json_error( array( 'message' => __( 'تعداد تلاش‌ها بیش از حد مجاز است. دوباره وارد شوید.', 'hamishop-elementor-widgets' ) ), 429 );
		}
		$locked = $this->verify_lock_seconds( (string) $data['phone'] );
		if ( $locked > 0 ) {
			delete_transient( 'hsw_2fa_' . $token );
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: minutes left. */
						__( 'به دلیل تلاش‌های ناموفق، ورود دوعاملی تا %s دقیقه قفل است.', 'hamishop-elementor-widgets' ),
						HSW_Helpers::fa_num( (int) ceil( $locked / 60 ) )
					),
				),
				429
			);
		}
		if ( ! wp_check_password( (string) $code, $data['hash'] ) ) {
			$data['attempts'] = (int) $data['attempts'] + 1;
			set_transient( 'hsw_2fa_' . $token, $data, max( 1, $data['expires'] - time() ) );
			$this->note_failed_verify( (string) $data['phone'] );
			wp_send_json_error( array( 'message' => __( 'کد وارد شده صحیح نیست.', 'hamishop-elementor-widgets' ) ), 400 );
		}
		$user = get_user_by( 'id', (int) $data['user_id'] );
		if ( ! $user ) {
			delete_transient( 'hsw_2fa_' . $token );
			wp_send_json_error( array( 'message' => __( 'حساب کاربری پیدا نشد.', 'hamishop-elementor-widgets' ) ), 404 );
		}
		delete_transient( 'hsw_2fa_' . $token );
		$this->clear_rate_limit( (string) $data['phone'], (string) $data['channel'], '2fa' );
		$this->clear_failed_verify( (string) $data['phone'] );
		$this->login_user( $user );
		wp_send_json_success(
			array(
				'message'  => __( 'ورود دوعاملی تأیید شد.', 'hamishop-elementor-widgets' ),
				'redirect' => wc_get_page_permalink( 'myaccount' ),
			)
		);
	}

	public function ajax_2fa_resend() {
		check_ajax_referer( 'hsw_2fa', 'nonce' );
		$token = isset( $_POST['token'] ) ? sanitize_key( wp_unslash( $_POST['token'] ) ) : '';
		$data  = $token ? get_transient( 'hsw_2fa_' . $token ) : false;
		if ( ! is_array( $data ) || empty( $data['expires'] ) || $data['expires'] < time() ) {
			wp_send_json_error( array( 'message' => __( 'درخواست ورود منقضی شده است. دوباره وارد شوید.', 'hamishop-elementor-widgets' ) ), 400 );
		}
		$limit = $this->rate_limit( $data['phone'], $data['channel'], '2fa' );
		if ( is_wp_error( $limit ) ) {
			wp_send_json_error( array( 'message' => $limit->get_error_message() ), 429 );
		}
		$old_data = $data;
		$code = (string) wp_rand( 100000, 999999 );
		$data['hash']     = wp_hash_password( $code );
		$data['attempts'] = 0;
		$data['expires']  = time() + max( 60, min( 900, absint( $this->s( 'otp_ttl', 300 ) ) ) );
		set_transient( 'hsw_2fa_' . $token, $data, max( 60, $data['expires'] - time() ) );
		$sent = $this->deliver_code( $data['channel'], $data['phone'], $code, (int) $data['user_id'], array( 'context' => '2fa' ) );
		if ( is_wp_error( $sent ) ) {
			set_transient( 'hsw_2fa_' . $token, $old_data, max( 1, (int) $old_data['expires'] - time() ) );
			$this->clear_rate_limit( (string) $data['phone'], (string) $data['channel'], '2fa' );
			wp_send_json_error( array( 'message' => $sent->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'message' => __( 'کد جدید ارسال شد.', 'hamishop-elementor-widgets' ) ) );
	}

	public function ajax_2fa_setup_send() {
		check_ajax_referer( 'hsw_2fa', 'nonce' );
		if ( ! is_user_logged_in() || ! $this->enabled() || 'yes' !== $this->s( 'otp_2fa', 'yes' ) ) {
			wp_send_json_error( array( 'message' => __( 'برای تنظیم ورود دوعاملی باید وارد حساب شوید.', 'hamishop-elementor-widgets' ) ), 403 );
		}
		$user_id = get_current_user_id();
		$channel = isset( $_POST['channel'] ) ? sanitize_key( wp_unslash( $_POST['channel'] ) ) : '';
		if ( ! $this->channel_enabled( $channel ) ) {
			wp_send_json_error( array( 'message' => __( 'این کانال فعال نیست.', 'hamishop-elementor-widgets' ) ), 400 );
		}
		/*
		 * A verified number is preferred, but a customer who has never verified
		 * one may type it here: the code proves ownership and, on success, the
		 * number is claimed as the account's login identity as well.
		 */
		if ( 'email' === $channel ) {
			$user  = get_user_by( 'id', $user_id );
			$phone = $user ? (string) $user->user_email : '';
			if ( ! $phone || ! is_email( $phone ) ) {
				wp_send_json_error( array( 'message' => __( 'برای این حساب ایمیل معتبری ثبت نشده است.', 'hamishop-elementor-widgets' ) ), 400 );
			}
		} else {
			$phone = $this->user_phone( $user_id );
			if ( ! $phone ) {
				$typed = isset( $_POST['phone'] ) ? $this->normalize_phone( wp_unslash( $_POST['phone'] ) ) : '';
				if ( ! $typed ) {
					wp_send_json_error( array( 'message' => __( 'برای فعال‌سازی، شماره موبایل خود را وارد کنید.', 'hamishop-elementor-widgets' ) ), 400 );
				}
				$owner = $this->phone_owner( $typed );
				if ( $owner && $owner !== (int) $user_id ) {
					wp_send_json_error( array( 'message' => __( 'این شماره قبلاً روی حساب دیگری تأیید شده است. با پشتیبانی تماس بگیرید.', 'hamishop-elementor-widgets' ) ), 409 );
				}
				$phone = $typed;
			}
		}
		if ( 'telegram' === $channel && ! $this->telegram_chat_id( $user_id, $phone ) ) {
			$token = $this->telegram_link_token( $user_id );
			wp_send_json_error(
				array(
					'message'   => __( 'ابتدا ربات تلگرام را باز کنید و شماره خود را به آن ارسال کنید.', 'hamishop-elementor-widgets' ),
					'link'      => $this->telegram_link( $token ),
					'link_text' => __( 'اتصال به ربات تلگرام', 'hamishop-elementor-widgets' ),
				),
				400
			);
		}
		$throttle = $this->check_send_throttle( $phone, $channel );
		if ( is_wp_error( $throttle ) ) {
			wp_send_json_error( array( 'message' => $throttle->get_error_message() ), 429 );
		}

		$limit = $this->rate_limit( $phone, $channel, 'setup' );
		if ( is_wp_error( $limit ) ) {
			wp_send_json_error( array( 'message' => $limit->get_error_message() ), 429 );
		}
		$code = (string) wp_rand( 100000, 999999 );
		$data = array(
			'hash'     => wp_hash_password( $code ),
			'channel'  => $channel,
			'phone'    => $phone,
			'attempts' => 0,
			'expires'  => time() + max( 60, min( 900, absint( $this->s( 'otp_ttl', 300 ) ) ) ),
		);
		set_transient( 'hsw_2fa_setup_' . $user_id, $data, max( 60, $data['expires'] - time() ) );
		$sent = $this->deliver_code( $channel, $phone, $code, $user_id, array( 'context' => 'setup' ) );
		if ( is_wp_error( $sent ) ) {
			delete_transient( 'hsw_2fa_setup_' . $user_id );
			$this->clear_rate_limit( $phone, $channel, 'setup' );
			wp_send_json_error( array( 'message' => $sent->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'message' => __( 'کد تأیید ارسال شد.', 'hamishop-elementor-widgets' ) ) );
	}

	public function ajax_2fa_setup_verify() {
		check_ajax_referer( 'hsw_2fa', 'nonce' );
		if ( ! is_user_logged_in() || 'yes' !== $this->s( 'otp_2fa', 'yes' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'hamishop-elementor-widgets' ) ), 403 );
		}
		$user_id = get_current_user_id();
		$data    = get_transient( 'hsw_2fa_setup_' . $user_id );
		$code    = isset( $_POST['code'] ) ? preg_replace( '/[^0-9]/', '', wp_unslash( $_POST['code'] ) ) : '';
		if ( ! is_array( $data ) || empty( $data['expires'] ) || $data['expires'] < time() ) {
			wp_send_json_error( array( 'message' => __( 'کد منقضی شده است. دوباره تلاش کنید.', 'hamishop-elementor-widgets' ) ), 400 );
		}
		$max = max( 1, min( 20, absint( $this->s( 'otp_max_attempts', 5 ) ) ) );
		if ( (int) $data['attempts'] >= $max ) {
			delete_transient( 'hsw_2fa_setup_' . $user_id );
			wp_send_json_error( array( 'message' => __( 'تعداد تلاش‌ها بیش از حد مجاز است. دوباره تلاش کنید.', 'hamishop-elementor-widgets' ) ), 429 );
		}
		if ( ! wp_check_password( (string) $code, $data['hash'] ) ) {
			$data['attempts'] = (int) $data['attempts'] + 1;
			set_transient( 'hsw_2fa_setup_' . $user_id, $data, max( 1, (int) $data['expires'] - time() ) );
			wp_send_json_error( array( 'message' => __( 'کد وارد شده صحیح نیست.', 'hamishop-elementor-widgets' ) ), 400 );
		}
		delete_transient( 'hsw_2fa_setup_' . $user_id );

		/*
		 * Entering a code sent to that number is the proof of ownership, so the
		 * same act claims it as the login identity. A number already verified on
		 * another account is refused here too, not only when it was typed.
		 */
		if ( 'email' !== $data['channel'] ) {
			$claim = $this->claim_phone( $user_id, (string) $data['phone'] );
			if ( is_wp_error( $claim ) ) {
				wp_send_json_error( array( 'message' => $claim->get_error_message() ), 409 );
			}
			update_user_meta( $user_id, '_hsw_2fa_phone', $data['phone'] );
		} else {
			update_user_meta( $user_id, '_hsw_email_verified', time() );
		}

		update_user_meta( $user_id, '_hsw_2fa_enabled', 'yes' );
		update_user_meta( $user_id, '_hsw_2fa_channel', $data['channel'] );
		wp_send_json_success( array( 'message' => __( 'ورود دوعاملی فعال شد.', 'hamishop-elementor-widgets' ), 'reload' => true ) );
	}

	public function ajax_2fa_disable() {
		check_ajax_referer( 'hsw_2fa', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'hamishop-elementor-widgets' ) ), 403 );
		}
		$user_id = get_current_user_id();
		delete_user_meta( $user_id, '_hsw_2fa_enabled' );
		delete_user_meta( $user_id, '_hsw_2fa_channel' );
		delete_user_meta( $user_id, '_hsw_2fa_phone' );
		wp_send_json_success( array( 'message' => __( 'ورود دوعاملی غیرفعال شد.', 'hamishop-elementor-widgets' ), 'reload' => true ) );
	}

	/* ---------------------------------------------------------------------
	 * Bale webhook
	 * ------------------------------------------------------------------ */

	public function maybe_handle_telegram_webhook() {
		if ( empty( $_GET['hsw_telegram_webhook'] ) ) {
			return;
		}

		$secret = (string) $this->s( 'telegram_secret' );
		$given  = isset( $_GET['secret'] ) ? (string) wp_unslash( $_GET['secret'] ) : '';
		$header = isset( $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ) ? (string) wp_unslash( $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ) : '';
		if ( '' === $given && '' !== $header ) {
			$given = $header;
		}
		if ( ! $secret || ! hash_equals( $secret, $given ) ) {
			status_header( 403 );
			nocache_headers();
			echo 'forbidden';
			exit;
		}

		$raw  = file_get_contents( 'php://input' );
		$data = json_decode( (string) $raw, true );
		if ( ! is_array( $data ) ) {
			status_header( 400 );
			nocache_headers();
			echo 'invalid';
			exit;
		}

		$message    = isset( $data['message'] ) && is_array( $data['message'] ) ? $data['message'] : array();
		$chat_id    = isset( $message['chat']['id'] ) ? (string) $message['chat']['id'] : '';
		$chat_type  = isset( $message['chat']['type'] ) ? (string) $message['chat']['type'] : '';
		$from_id    = isset( $message['from']['id'] ) ? (string) $message['from']['id'] : '';
		$text       = isset( $message['text'] ) ? (string) $message['text'] : '';
		$contact    = isset( $message['contact'] ) && is_array( $message['contact'] ) ? $message['contact'] : array();
		$contact_id = isset( $contact['user_id'] ) ? (string) $contact['user_id'] : '';

		$keyboard = array(
			'keyboard'          => array( array( array( 'text' => __( 'ارسال شماره موبایل', 'hamishop-elementor-widgets' ), 'request_contact' => true ) ) ),
			'resize_keyboard'   => true,
			'one_time_keyboard' => true,
		);

		if ( $contact && $chat_id ) {
			/*
			 * A shared contact proves ownership only when the sender shared
			 * *their own* number in a private chat: Telegram sets
			 * `contact.user_id` to the sender's id in exactly that case. A
			 * forwarded contact carries someone else's id (or none), and
			 * accepting it would let an attacker bind any phone number to their
			 * chat and receive that person's login codes.
			 */
			if ( 'private' !== $chat_type || '' === $contact_id || '' === $from_id || $contact_id !== $from_id ) {
				$this->telegram_send(
					$chat_id,
					__( 'برای اتصال، شمارهٔ خودتان را با دکمهٔ «ارسال شماره موبایل» و در گفتگوی خصوصی با ربات بفرستید.', 'hamishop-elementor-widgets' )
				);
			} else {
				$phone = $this->normalize_phone( isset( $contact['phone_number'] ) ? $contact['phone_number'] : '' );

				if ( $phone ) {
					$contacts  = get_option( 'hsw_telegram_contacts', array() );
					$contacts  = is_array( $contacts ) ? $contacts : array();
					$user      = $this->find_user_by_phone( $phone );
					$bound     = isset( $contacts[ $phone ] ) ? (string) $contacts[ $phone ] : '';
					$user_chat = $user ? (string) get_user_meta( $user->ID, '_hsw_telegram_chat_id', true ) : '';

					if ( ( '' !== $bound && $bound !== $chat_id ) || ( '' !== $user_chat && $user_chat !== $chat_id ) ) {
						$this->telegram_send(
							$chat_id,
							__( 'این شماره قبلاً به حساب دیگری متصل شده است. اگر مالک آن هستید، از پنل حساب کاربری دوباره وارد شوید و ربات را از همان‌جا وصل کنید.', 'hamishop-elementor-widgets' )
						);
					} else {
						$contacts[ $phone ] = $chat_id;
						if ( count( $contacts ) > 1000 ) {
							$contacts = array_slice( $contacts, -1000, null, true );
						}
						update_option( 'hsw_telegram_contacts', $contacts, false );

						if ( $user ) {
							update_user_meta( $user->ID, '_hsw_telegram_chat_id', $chat_id );
						}

						$this->telegram_send( $chat_id, __( 'شماره شما با موفقیت به حساب فروشگاه متصل شد.', 'hamishop-elementor-widgets' ) );
					}
				}
			}
		} elseif ( preg_match( '/^\/start(?:\s+(\S+))?/u', $text, $matches ) ) {
			$token   = isset( $matches[1] ) ? sanitize_key( $matches[1] ) : '';
			$user_id = $token ? (int) get_transient( 'hsw_telegram_link_' . $token ) : 0;
			if ( $user_id ) {
				update_user_meta( $user_id, '_hsw_telegram_chat_id', $chat_id );
				delete_transient( 'hsw_telegram_link_' . $token );
				$this->telegram_send( $chat_id, __( 'حساب شما به ربات متصل شد. حالا می‌توانید از ورود با کد تلگرام استفاده کنید.', 'hamishop-elementor-widgets' ) );
			} else {
				$this->telegram_send(
					$chat_id,
					__( 'برای اتصال شماره موبایل، دکمهٔ زیر را بزنید و شماره خود را ارسال کنید.', 'hamishop-elementor-widgets' ),
					$keyboard
				);
			}
		} else {
			$this->telegram_send(
				$chat_id,
				__( 'برای دریافت کد ورود، ابتدا شماره موبایل خود را با دکمهٔ زیر ارسال کنید.', 'hamishop-elementor-widgets' ),
				$keyboard
			);
		}

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( array( 'ok' => true ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Render helpers
	 * ------------------------------------------------------------------ */

	public function otp_box( $context = 'login' ) {
		if ( ! $this->enabled() ) {
			return '';
		}
		$context = 'register' === $context ? 'register' : 'login';
		if ( 'login' === $context && 'yes' !== $this->s( 'otp_login', 'yes' ) ) {
			return '';
		}
		if ( 'register' === $context && ( 'yes' !== $this->s( 'otp_register', 'yes' ) || 'yes' !== get_option( 'woocommerce_enable_myaccount_registration' ) ) ) {
			return '';
		}
		$channels = $this->available_channels();
		if ( empty( $channels ) ) {
			return '';
		}
		$default = $this->default_channel();
		$id      = 'hsw-otp-' . $context . '-' . wp_rand( 1000, 9999 );

		$is_email_default = ( 'email' === $default );
		$html  = '<section class="hsw-ac-otp" data-hsw-otp data-context="' . esc_attr( $context ) . '" data-channel="' . esc_attr( $default ) . '" data-channels="' . esc_attr( implode( ',', array_keys( $channels ) ) ) . '">';
		$html .= '<div class="hsw-ac-otp__head"><span class="hsw-ac-otp__eyebrow">' . esc_html__( 'ورود سریع', 'hamishop-elementor-widgets' ) . '</span><h2>' . esc_html( 'register' === $context ? __( 'ثبت‌نام سریع با کد یکبارمصرف', 'hamishop-elementor-widgets' ) : __( 'ورود با کد یکبارمصرف', 'hamishop-elementor-widgets' ) ) . '</h2><p>' . esc_html__( 'کد تأیید به ایمیل، تلگرام یا پیامک شما ارسال می‌شود؛ نیازی به رمز عبور نیست.', 'hamishop-elementor-widgets' ) . '</p></div>';
		$html .= '<div class="hsw-ac-otp__channels" role="radiogroup" aria-label="' . esc_attr__( 'کانال دریافت کد', 'hamishop-elementor-widgets' ) . '">';
		foreach ( $channels as $key => $label ) {
			$html .= '<button type="button" class="hsw-ac-otp__channel' . ( $key === $default ? ' is-active' : '' ) . '" data-hsw-otp-channel="' . esc_attr( $key ) . '" aria-pressed="' . ( $key === $default ? 'true' : 'false' ) . '">' . $this->account->icon( 'telegram' === $key ? 'send' : ( 'email' === $key ? 'mail' : 'phone' ) ) . esc_html( $label ) . '</button>';
		}
		$html .= '</div>';

		$html .= '<div class="hsw-ac-otp__form" data-hsw-otp-form>';
		if ( 'register' === $context ) {
			$html .= '<div class="hsw-ac-field"><label for="' . esc_attr( $id ) . '-name">' . esc_html__( 'نام و نام خانوادگی', 'hamishop-elementor-widgets' ) . '</label><div class="hsw-ac-input-wrap">' . $this->account->icon( 'user' ) . '<input type="text" id="' . esc_attr( $id ) . '-name" name="name" autocomplete="name" data-hsw-otp-name></div></div>';
			$html .= '<div class="hsw-ac-field"' . ( $is_email_default ? ' hidden' : '' ) . ' data-hsw-otp-regemail-wrap><label for="' . esc_attr( $id ) . '-email">' . esc_html__( 'ایمیل', 'hamishop-elementor-widgets' ) . '</label><div class="hsw-ac-input-wrap">' . $this->account->icon( 'mail' ) . '<input type="email" id="' . esc_attr( $id ) . '-email" name="email" autocomplete="email" data-hsw-otp-email data-hsw-otp-regemail></div></div>';
		}
		$html .= '<div class="hsw-ac-field"' . ( $is_email_default ? ' hidden' : '' ) . ' data-hsw-otp-phone-field><label for="' . esc_attr( $id ) . '-phone">' . esc_html__( 'شماره موبایل', 'hamishop-elementor-widgets' ) . '</label><div class="hsw-ac-input-wrap">' . $this->account->icon( 'phone' ) . '<input type="tel" id="' . esc_attr( $id ) . '-phone" name="phone" inputmode="tel" autocomplete="tel" placeholder="09121234567" dir="ltr" data-hsw-otp-phone></div></div>';
		$html .= '<div class="hsw-ac-field" data-hsw-otp-email-field' . ( $is_email_default ? '' : ' hidden' ) . '><label for="' . esc_attr( $id ) . '-emailid">' . esc_html__( 'ایمیل', 'hamishop-elementor-widgets' ) . '</label><div class="hsw-ac-input-wrap">' . $this->account->icon( 'mail' ) . '<input type="email" id="' . esc_attr( $id ) . '-emailid" name="email_id" autocomplete="email" placeholder="you@example.com" dir="ltr" data-hsw-otp-emailid></div></div>';
		$html .= '<button type="button" class="hsw-ac__btn hsw-ac__btn--primary hsw-ac-otp__send" data-hsw-otp-send>' . esc_html__( 'دریافت کد تأیید', 'hamishop-elementor-widgets' ) . $this->account->icon( 'arrow-left' ) . '</button>';
		$html .= '<div class="hsw-ac-otp__code-step" data-hsw-otp-code-step hidden><label for="' . esc_attr( $id ) . '-code">' . esc_html__( 'کد تأیید ۶ رقمی', 'hamishop-elementor-widgets' ) . '</label><div class="hsw-ac-input-wrap">' . $this->account->icon( 'shield' ) . '<input type="text" id="' . esc_attr( $id ) . '-code" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" dir="ltr" data-hsw-otp-code></div><div class="hsw-ac-otp__actions"><button type="button" class="hsw-ac__btn hsw-ac__btn--primary" data-hsw-otp-verify>' . esc_html__( 'تأیید و ورود', 'hamishop-elementor-widgets' ) . '</button><button type="button" class="hsw-ac-otp__resend" data-hsw-otp-resend>' . esc_html__( 'ارسال دوباره', 'hamishop-elementor-widgets' ) . '</button></div></div>';
		$html .= '<p class="hsw-ac-otp__message" data-hsw-otp-message role="status" aria-live="polite"></p>';
		$html .= '</div></section>';
		return $html;
	}

	public function security_panel( $ctx ) {
		if ( ! $this->enabled() ) {
			return '';
		}
		$channels = $this->available_channels();
		if ( empty( $channels ) ) {
			return '';
		}
		$user_id  = get_current_user_id();
		$enabled  = 'yes' === get_user_meta( $user_id, '_hsw_2fa_enabled', true );
		if ( ! $enabled && 'yes' !== $this->s( 'otp_2fa', 'yes' ) ) {
			return '';
		}
		$active   = (string) get_user_meta( $user_id, '_hsw_2fa_channel', true );
		$verified = $this->user_phone( $user_id );
		/* What the code is sent to: the 2FA number once enabled, else the verified one. */
		$phone    = $enabled ? $this->two_factor_recipient( $user_id, $active ) : $verified;
		$chat     = $this->telegram_chat_id( $user_id, '' !== $verified ? $verified : $phone );
		$default  = isset( $channels[ $active ] ) ? $active : $this->default_channel();
		$link     = ( ! $chat && isset( $channels['telegram'] ) ) ? $this->telegram_link( $this->telegram_link_token( $user_id ) ) : '';

		$html  = '<section class="hsw-ac__card hsw-ac__security" data-hsw-2fa>';
		$html .= '<header class="hsw-ac__card-head"><div><h2 class="hsw-ac__card-title">' . esc_html__( 'امنیت حساب', 'hamishop-elementor-widgets' ) . '</h2><p>' . esc_html__( 'ورود دوعاملی، امنیت حساب شما را بیشتر می‌کند.', 'hamishop-elementor-widgets' ) . '</p></div><span class="hsw-ac__card-head-ic">' . $this->account->icon( 'shield' ) . '</span></header>';

		if ( $enabled ) {
			$where = ( 'email' === $active ) ? $phone : $this->mask_phone( $phone );
			$html .= '<div class="hsw-ac__security-active"><span class="hsw-ac__security-dot"></span><div><strong>' . esc_html__( 'ورود دوعاملی فعال است', 'hamishop-elementor-widgets' ) . '</strong><p>' . esc_html( sprintf( 'کد ورود از طریق %s به %s ارسال می‌شود.', $this->channel_label( $active ), $where ) ) . '</p></div></div>';
			$html .= '<button type="button" class="hsw-ac__btn hsw-ac__btn--ghost" data-hsw-2fa-disable>' . $this->account->icon( 'x' ) . esc_html__( 'غیرفعال کردن', 'hamishop-elementor-widgets' ) . '</button>';
			$html .= '<p class="hsw-ac-otp__message" data-hsw-2fa-message role="status" aria-live="polite"></p>';
			return $html . '</section>';
		}

		$html .= '<p class="hsw-ac__security-desc">' . esc_html__( 'روش دریافت کد را انتخاب کنید؛ پس از تأیید کد، ورود دوعاملی فعال می‌شود.', 'hamishop-elementor-widgets' ) . '</p>';

		/*
		 * No verified number yet: ask for one. Entering the code that arrives on
		 * it proves ownership, so the same step registers it as the account's
		 * login number — that is the migration path for customers whose old
		 * `billing_phone` was never verified.
		 */
		$needs_phone = ( '' === $verified && ( isset( $channels['sms'] ) || isset( $channels['telegram'] ) || isset( $channels['whatsapp'] ) ) );
		if ( $needs_phone ) {
			/* Only the SMS channel needs a number here: email uses the account
			   address, Telegram/WhatsApp use the bot link. */
			$html .= '<div class="hsw-ac-field"' . ( 'sms' !== $default ? ' hidden' : '' ) . ' data-hsw-2fa-phone-wrap><label for="hsw-2fa-phone">' . esc_html__( 'شماره موبایل', 'hamishop-elementor-widgets' ) . '</label>';
			$html .= '<div class="hsw-ac-input-wrap">' . $this->account->icon( 'phone' ) . '<input type="tel" id="hsw-2fa-phone" inputmode="tel" autocomplete="tel" dir="ltr" placeholder="09121234567" data-hsw-2fa-phone></div>';
			$html .= '<p class="hsw-ac__security-note">' . esc_html__( 'این شماره با کد تأیید می‌شود و به‌عنوان شمارهٔ ورود حساب ثبت می‌گردد.', 'hamishop-elementor-widgets' ) . '</p></div>';
		}
		$html .= '<div class="hsw-ac-otp__channels" role="radiogroup" aria-label="' . esc_attr__( 'روش ورود دوعاملی', 'hamishop-elementor-widgets' ) . '">';
		foreach ( $channels as $key => $label ) {
			$html .= '<button type="button" class="hsw-ac-otp__channel' . ( $key === $default ? ' is-active' : '' ) . '" data-hsw-2fa-channel="' . esc_attr( $key ) . '" aria-pressed="' . ( $key === $default ? 'true' : 'false' ) . '">' . $this->account->icon( 'telegram' === $key ? 'send' : ( 'email' === $key ? 'mail' : 'phone' ) ) . esc_html( $label ) . '</button>';
		}
		$html .= '</div>';
		if ( isset( $channels['telegram'] ) ) {
			$html .= '<p class="hsw-ac__security-note">' . ( $chat ? esc_html__( 'ربات تلگرام متصل است.', 'hamishop-elementor-widgets' ) : esc_html__( 'برای تلگرام، ابتدا ربات را باز کنید و شماره خود را ارسال کنید.', 'hamishop-elementor-widgets' ) );
			if ( ! $chat && $link ) {
				$html .= ' <a href="' . esc_url( $link ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'اتصال به ربات تلگرام', 'hamishop-elementor-widgets' ) . '</a>';
			}
			$html .= '</p>';
		}
		$html .= '<div class="hsw-ac-otp__code-step" data-hsw-2fa-code-step hidden><label for="hsw-2fa-setup-code">' . esc_html__( 'کد تأیید', 'hamishop-elementor-widgets' ) . '</label><div class="hsw-ac-input-wrap">' . $this->account->icon( 'shield' ) . '<input type="text" id="hsw-2fa-setup-code" inputmode="numeric" maxlength="6" dir="ltr" data-hsw-2fa-code></div><div class="hsw-ac-otp__actions"><button type="button" class="hsw-ac__btn hsw-ac__btn--primary" data-hsw-2fa-verify>' . esc_html__( 'تأیید و فعال‌سازی', 'hamishop-elementor-widgets' ) . '</button><button type="button" class="hsw-ac-otp__resend" data-hsw-2fa-setup-resend>' . esc_html__( 'ارسال دوباره', 'hamishop-elementor-widgets' ) . '</button></div></div>';
		$html .= '<button type="button" class="hsw-ac__btn hsw-ac__btn--primary" data-hsw-2fa-setup>' . esc_html__( 'فعال‌سازی ورود دوعاملی', 'hamishop-elementor-widgets' ) . '</button>';
		$html .= '<p class="hsw-ac-otp__message" data-hsw-2fa-message role="status" aria-live="polite"></p>';
		return $html . '</section>';
	}

	public function render_2fa_challenge( $opts ) {
		$token = isset( $_GET['hsw_2fa'] ) ? sanitize_key( wp_unslash( $_GET['hsw_2fa'] ) ) : '';
		$data  = $token ? get_transient( 'hsw_2fa_' . $token ) : false;
		if ( ! is_array( $data ) ) {
			return '';
		}
		$raw     = isset( $data['phone'] ) ? (string) $data['phone'] : '';
		$phone   = ( 'email' === ( $data['channel'] ?? '' ) ) ? $raw : $this->mask_phone( $raw );
		$channel = isset( $data['channel'] ) ? $this->channel_label( $data['channel'] ) : '';
		$html  = '<div class="hsw-ac hsw-ac--auth"><div class="hsw-ac-auth hsw-ac-auth--slim"><div class="hsw-ac-auth__panel-wrap"><div class="hsw-ac-auth__panel">';
		$html .= '<div class="hsw-ac-auth__heading"><span class="hsw-ac-auth__eyebrow">' . esc_html__( 'تأیید دوعاملی', 'hamishop-elementor-widgets' ) . '</span><h1>' . esc_html__( 'کد تأیید را وارد کنید', 'hamishop-elementor-widgets' ) . '</h1><p>' . esc_html( sprintf( 'کد ورود از طریق %s به %s ارسال شد.', $channel, $phone ) ) . '</p></div>';
		$html .= '<div class="hsw-ac-otp hsw-ac-otp--challenge" data-hsw-2fa-challenge data-token="' . esc_attr( $token ) . '"><div class="hsw-ac-field"><label for="hsw-2fa-challenge-code">' . esc_html__( 'کد ۶ رقمی', 'hamishop-elementor-widgets' ) . '</label><div class="hsw-ac-input-wrap">' . $this->account->icon( 'shield' ) . '<input type="text" id="hsw-2fa-challenge-code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" dir="ltr" data-hsw-2fa-code></div></div><button type="button" class="hsw-ac__btn hsw-ac__btn--primary" data-hsw-2fa-verify>' . esc_html__( 'تأیید و ورود', 'hamishop-elementor-widgets' ) . '</button><button type="button" class="hsw-ac-otp__resend" data-hsw-2fa-resend>' . esc_html__( 'ارسال دوباره', 'hamishop-elementor-widgets' ) . '</button><p class="hsw-ac-otp__message" data-hsw-2fa-message role="status" aria-live="polite"></p></div>';
		$html .= '<a class="hsw-ac-auth__back" href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">' . $this->account->icon( 'arrow-right' ) . esc_html__( 'بازگشت به ورود', 'hamishop-elementor-widgets' ) . '</a>';
		$html .= '</div></div></div></div>';
		return $html;
	}
}
