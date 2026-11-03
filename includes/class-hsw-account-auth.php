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

	/** @var string Session token WordPress issued in this request. */
	private $issued_token = '';

	/** @var int */
	private $issued_user_id = 0;

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
		/*
		 * The session cookie is written *before* `wp_login` fires, so the token has
		 * to be captured there: clearing the cookie afterwards only hides it from
		 * the browser, while the session row in usermeta stays valid — the first
		 * Set-Cookie header of the response still carried a working cookie.
		 */
		add_action( 'set_auth_cookie', array( $this, 'remember_issued_token' ), 10, 6 );

		/*
		 * `wp_login` never fires on XML-RPC and on Application Password requests,
		 * so the second factor above is simply not consulted there. Both doors are
		 * closed by default; a shop that needs them can opt out with a filter.
		 */
		add_filter( 'xmlrpc_enabled', array( $this, 'filter_xmlrpc_enabled' ) );
		add_filter( 'wp_is_application_passwords_available_for_user', array( $this, 'filter_app_password_for_user' ), 10, 2 );
	}

	/**
	 * XML-RPC is off unless the shop opts back in.
	 *
	 * @param bool $enabled Current value.
	 * @return bool
	 */
	public function filter_xmlrpc_enabled( $enabled ) {
		if ( ! apply_filters( 'hsw_disable_xmlrpc', true ) ) {
			return $enabled;
		}

		return false;
	}

	/**
	 * Application Passwords are refused for accounts that carry a second factor
	 * or a shop-wide capability.
	 *
	 * @param bool         $available Current value.
	 * @param WP_User|mixed $user     Account being checked.
	 * @return bool
	 */
	public function filter_app_password_for_user( $available, $user = null ) {
		if ( ! $user instanceof WP_User ) {
			return $available;
		}

		$protected = 'yes' === (string) get_user_meta( $user->ID, '_hsw_2fa_enabled', true )
			|| user_can( $user, 'manage_options' )
			|| user_can( $user, 'manage_woocommerce' );

		if ( ! $protected ) {
			return $available;
		}

		if ( apply_filters( 'hsw_app_passwords_for_privileged', false, $user ) ) {
			return $available;
		}

		return false;
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
		$phone = $this->two_factor_phone( $user_id );
		if ( ! $phone ) {
			$phone = $this->user_phone( $user_id );
		}
		if ( ! $phone && 'telegram' === $channel ) {
			$chat = $this->telegram_chat_id( $user_id );
			if ( $chat ) {
				return 'tg:' . $chat;
			}
		}
		return $phone;
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
		if ( 0 === strpos( $value, 'tg:' ) ) {
			return 'tg:' . preg_replace( '/[^0-9-]/', '', substr( $value, 3 ) );
		}
		if ( false !== strpos( $value, '@' ) ) {
			return strtolower( $value );
		}
		return $this->normalize_phone( $value );
	}

	private function mask_phone( $phone ) {
		if ( 0 === strpos( (string) $phone, 'tg:' ) ) {
			return __( 'حساب تلگرام متصل‌شده', 'hamishop-elementor-widgets' );
		}
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

	private function telegram_chat_id( $user_id, $phone = '', $allow_poll = true ) {
		$chat = $user_id ? get_user_meta( $user_id, '_hsw_telegram_chat_id', true ) : '';
		if ( $chat ) {
			return (string) $chat;
		}
		$norm_phone = $this->normalize_phone( $phone ? $phone : ( $user_id ? $this->user_phone( $user_id ) : '' ) );
		if ( $norm_phone ) {
			$contacts = get_option( 'hsw_telegram_contacts', array() );
			if ( is_array( $contacts ) && ! empty( $contacts[ $norm_phone ] ) ) {
				return (string) $contacts[ $norm_phone ];
			}
		}
		if ( $allow_poll && class_exists( 'HSW_Telegram_Poller' ) ) {
			HSW_Telegram_Poller::poll_now();
			return $this->telegram_chat_id( $user_id, $phone, false );
		}
		return '';
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

	/**
	 * A per-challenge token that survives every reader.
	 *
	 * `wp_generate_password()` mixes upper and lower case, while every reader
	 * used `sanitize_key()` (which lowercases) — so a shell of a 28-char token
	 * never matched the transient it was stored under and the 2FA screen /
	 * Telegram link silently never worked. Tokens are hex now and are read
	 * through this one parser.
	 *
	 * @param mixed $value Raw token from $_GET/$_POST.
	 * @return string Token, or '' when the shape is wrong.
	 */
	private function parse_token( $value ) {
		$token = (string) wp_unslash( (string) $value );

		return preg_match( '/^[a-f0-9]{28,64}$/', $token ) ? $token : '';
	}

	private function telegram_link_token( $user_id ) {
		$token = bin2hex( random_bytes( 14 ) ); // 28 hex chars — the documented shape.
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

		/*
		 * Attempt counting lives in the atomic throttle table, not in the
		 * transient. The old read-modify-write on the transient let a burst of
		 * parallel requests all read "0 attempts" and all get a guess (six
		 * parallel wrong codes against max_attempts=5 all processed —
		 * reproduced). The table upsert is a single atomic statement.
		 */
		$max = max( 1, min( 20, absint( $this->s( 'otp_max_attempts', 5 ) ) ) );
		/*
		 * The counter is keyed by the code that is on screen, not by the slot
		 * (recipient|channel|context). Keyed by the slot, the claim bucket stayed
		 * "used" for the whole TTL, so a second login inside five minutes was
		 * answered with "this code has expired" and five wrong guesses locked the
		 * customer out even after a fresh code was issued. `otp_verify_fail`
		 * (per number, across resends) still bounds the guessing.
		 */
		$bind = md5( $key . '|' . (string) $data['hash'] );
		$att  = HSW_Throttle::hit(
			'otp_attempt:' . $bind,
			$max,
			max( 60, (int) $data['expires'] - time() )
		);
		if ( ! $att['allowed'] ) {
			delete_transient( $key );
			return new WP_Error( 'hsw_otp_attempts', __( 'تعداد تلاش‌ها بیش از حد مجاز است. کد جدید بگیرید.', 'hamishop-elementor-widgets' ) );
		}

		if ( ! wp_check_password( (string) $code, $data['hash'] ) ) {
			return new WP_Error( 'hsw_otp_invalid', __( 'کد وارد شده صحیح نیست.', 'hamishop-elementor-widgets' ) );
		}

		/*
		 * Single-use claim: an atomic one-row upsert in the throttle table is
		 * the lock — not the transient delete. Two parallel verifications of
		 * the same code both read the transient and both pass the hash check
		 * (reproduced 4/4); only the first claim upsert wins, the loser is
		 * rejected. This stays correct on object-cache backends too, where
		 * `delete_transient()` can report success without a real delete.
		 */
		$claim = HSW_Throttle::hit(
			'otp_claim:' . $bind,
			1,
			max( 60, (int) $data['expires'] - time() )
		);
		if ( ! $claim['allowed'] ) {
			return new WP_Error( 'hsw_otp_expired', __( 'کد وارد شده منقضی شده است. لطفاً کد جدید بگیرید.', 'hamishop-elementor-widgets' ) );
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
				$link_token = $user_id > 0 ? $this->telegram_link_token( $user_id ) : '';
				return new WP_Error(
					'hsw_telegram_not_connected',
					__( 'برای دریافت کد در تلگرام، ابتدا ربات فروشگاه را باز کنید و شماره موبایل خود را ارسال کنید، سپس دوباره روی دریافت کد کلیک کنید.', 'hamishop-elementor-widgets' ),
					array( 'link' => $this->telegram_link( $link_token ) )
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

		/*
		 * Throttle BEFORE any account lookup. Running it after the "no such
		 * account" exit meant an unknown address never consumed a slot, so the
		 * 20/hour ceiling could be walked around with made-up addresses.
		 */
		$throttle = $this->check_send_throttle( $phone, $channel );
		if ( is_wp_error( $throttle ) ) {
			wp_send_json_error( array( 'message' => $throttle->get_error_message() ), 429 );
		}

		$user = ( 'email' === $channel ) ? get_user_by( 'email', $phone ) : $this->find_user_by_phone( $phone );

		/*
		 * If the customer selected Telegram and this number is not yet linked to
		 * the bot (even after an on-demand getUpdates poll), show the bot link
		 * immediately in both login and register flows. Answering identically for
		 * any unlinked phone number keeps account enumeration impossible while
		 * preventing the user from waiting for a code the bot cannot deliver.
		 */
		if ( 'telegram' === $channel && ! $this->telegram_chat_id( $user ? $user->ID : 0, $phone ) ) {
			wp_send_json_error(
				array(
					'message'   => __( 'برای دریافت کد در تلگرام، ابتدا ربات فروشگاه را باز کنید و شماره موبایل خود را ارسال کنید، سپس دوباره روی دریافت کد کلیک کنید.', 'hamishop-elementor-widgets' ),
					'link'      => esc_url_raw( $this->telegram_link() ),
					'link_text' => __( 'باز کردن ربات تلگرام', 'hamishop-elementor-widgets' ),
				),
				400
			);
		}

		/*
		 * Account enumeration: "exists", "does not exist" and "is an admin" used to
		 * answer 200 / 404 / 403 with different copy and a `switch_tab` hint. They
		 * now answer identically; the code is simply not sent when there is nobody
		 * to sign in, and the same bcrypt work is done so the timing matches.
		 */
		if ( 'login' === $context && ( ! $user || ! $this->otp_allowed( $user ) ) ) {
			wp_hash_password( wp_generate_password( 12 ) );
			wp_send_json_success( $this->send_ack( $channel ) );
		}

		$limit = $this->rate_limit( $phone, $channel, $context );
		if ( is_wp_error( $limit ) ) {
			/*
			 * A cooldown answer must look exactly like a sent code: a 429 here told
			 * an attacker "this identifier just received something".
			 */
			wp_send_json_success( $this->send_ack( $channel ) );
		}

		$code = $this->store_code( $phone, $channel, $context, $user ? $user->ID : 0 );
		$sent = $this->deliver_code( $channel, $phone, $code, $user ? $user->ID : 0 );
		if ( is_wp_error( $sent ) ) {
			delete_transient( $this->code_key( $phone, $channel, $context ) );
			$this->clear_rate_limit( $phone, $channel, $context );

			/* Surface bot-connection or gateway-configuration errors so the user can act. */
			if ( 'register' === $context || in_array( $sent->get_error_code(), array( 'hsw_telegram_not_connected', 'hsw_telegram_config', 'hsw_telegram_http', 'hsw_telegram_send' ), true ) ) {
				$data = array( 'message' => $sent->get_error_message() );
				$link = $sent->get_error_data();
				if ( is_array( $link ) && ! empty( $link['link'] ) ) {
					$data['link']      = esc_url_raw( $link['link'] );
					$data['link_text'] = __( 'باز کردن ربات تلگرام', 'hamishop-elementor-widgets' );
				}
				wp_send_json_error( $data, 400 );
			}

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'HamiShop OTP: %s delivery failed (%s) in the %s flow.', $channel, $sent->get_error_code(), $context ) );
			wp_send_json_success( $this->send_ack( $channel ) );
		}

		wp_send_json_success( $this->send_ack( $channel ) );
	}

	/**
	 * A unique, deliberately undeliverable address for a phone-only signup.
	 *
	 * @param string $phone Normalised phone.
	 * @return string
	 */
	private function placeholder_email( $phone ) {
		$local = preg_replace( '/[^0-9]/', '', (string) $phone );
		$local = '' !== $local ? $local : 'user' . wp_rand( 1000, 9999 );
		$host  = (string) apply_filters( 'hsw_signup_placeholder_domain', 'noreply.invalid' );
		$email = $local . '@' . $host;

		$i = 1;
		while ( email_exists( $email ) && $i < 5 ) {
			$email = $local . '-' . $i . '@' . $host;
			$i++;
		}

		return (string) apply_filters( 'hsw_signup_placeholder_email', $email, $phone );
	}

	/**
	 * The one answer "send a code" may give, whatever the account state is.
	 *
	 * @param string $channel Channel that was asked for.
	 * @return array
	 */
	private function send_ack( $channel ) {
		return array(
			'message' => __( 'اگر حسابی با این مشخصات وجود داشته باشد و کانال انتخاب‌شده متصل باشد، کد تأیید ارسال شد.', 'hamishop-elementor-widgets' ),
			'expires' => max( 60, min( 900, absint( $this->s( 'otp_ttl', 300 ) ) ) ),
			'channel' => $channel,
		);
	}

	/**
	 * The one answer a failed verification may give.
	 *
	 * "expired", "wrong", "too many attempts" and "no such account" all answer
	 * with this sentence and a 400, so the response cannot be used to learn
	 * whether an account exists.
	 *
	 * @return string
	 */
	private function verify_fail_message() {
		return __( 'کد وارد شده صحیح نیست یا منقضی شده است. اگر کدی دریافت نکردید، دوباره درخواست کنید.', 'hamishop-elementor-widgets' );
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
		if ( 'login' === $context && ( ! $user || ! $this->otp_allowed( $user ) ) ) {
			/* Same work and same words as a wrong code — no account oracle. */
			$this->check_code( $phone, $channel, $context, $code );
			wp_send_json_error( array( 'message' => $this->verify_fail_message() ), 400 );
		}

		$name  = '';
		$email = '';
		if ( 'register' === $context ) {
			$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			$email = ( 'email' === $channel ) ? $phone : ( isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '' );
			if ( ! $name || ! $email || ! is_email( $email ) ) {
				wp_send_json_error( array( 'message' => __( 'برای ثبت‌نام، نام و ایمیل معتبر را وارد کنید.', 'hamishop-elementor-widgets' ) ), 400 );
			}
			/*
			 * "already registered" / `email_exists()` move below the code check:
			 * answered up front they told anyone which addresses have an account.
			 */
		}

		$locked = $this->verify_lock_seconds( $phone );
		if ( $locked > 0 ) {
			/* The lock is per number, not per account — but the answer stays generic. */
			wp_send_json_error( array( 'message' => $this->verify_fail_message() ), 400 );
		}

		$checked = $this->check_code( $phone, $channel, $context, $code );
		if ( is_wp_error( $checked ) ) {
			if ( 'hsw_otp_invalid' === $checked->get_error_code() ) {
				$this->note_failed_verify( $phone );
			}
			wp_send_json_error( array( 'message' => $this->verify_fail_message() ), 400 );
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

		/* Only now — after the code has proved ownership of the identifier. */
		if ( $user ) {
			wp_send_json_error( array( 'message' => __( 'این حساب قبلاً ثبت‌نام کرده است.', 'hamishop-elementor-widgets' ) ), 409 );
		}
		if ( 'email' === $channel && email_exists( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'این ایمیل قبلاً در سایت ثبت شده است.', 'hamishop-elementor-widgets' ) ), 409 );
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
			/* The code arrived at this address, so it really belongs to them. */
			$account_email = $email;
			$base          = sanitize_user( current( explode( '@', $email ) ), true );
		} else {
			/*
			 * A phone proves the number, never the mailbox. Storing the typed address
			 * here let anyone claim a stranger's e-mail at signup — and with it every
			 * guest order and saved address that address already had. The account gets
			 * a unique placeholder and the typed address is kept as unverified.
			 */
			$account_email = $this->placeholder_email( $phone );
			$base          = 'hsw_' . substr( $phone, -10 );
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
			$account_email,
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
			if ( '' !== $email ) {
				/* Typed, never proved: kept for the e-mail verification step only. */
				update_user_meta( $user_id, '_hsw_pending_email', $email );
			}
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

	/**
	 * Remember the session token WordPress just minted for this request.
	 *
	 * @param string $cookie     Cookie value.
	 * @param int    $expire     Expiry timestamp.
	 * @param int    $expiration Expiration.
	 * @param int    $user_id    User.
	 * @param string $scheme     Cookie scheme.
	 * @param string $token      Session token (the part that matters).
	 */
	public function remember_issued_token( $cookie = '', $expire = 0, $expiration = 0, $user_id = 0, $scheme = '', $token = '' ) {
		$this->issued_token   = (string) $token;
		$this->issued_user_id = (int) $user_id;
	}

	/**
	 * Kill the session that was just created, before redirecting to the challenge.
	 *
	 * @param int $user_id User the session belongs to.
	 */
	private function destroy_issued_session( $user_id ) {
		$token = (string) $this->issued_token;
		if ( '' === $token || (int) $this->issued_user_id !== (int) $user_id ) {
			$from_cookie = function_exists( 'wp_get_session_token' ) ? (string) wp_get_session_token() : '';
			$token       = '' !== $from_cookie ? $from_cookie : '';
		}

		if ( '' === $token ) {
			return;
		}

		if ( class_exists( 'WP_Session_Tokens' ) ) {
			WP_Session_Tokens::get_instance( $user_id )->destroy( $token );
			return;
		}

		/* Fallback for a trimmed core: drop every session of this user. */
		if ( function_exists( 'wp_destroy_all_sessions' ) ) {
			wp_destroy_all_sessions( $user_id );
		}
	}

	public function is_2fa_challenge() {
		$token = isset( $_GET['hsw_2fa'] ) ? $this->parse_token( $_GET['hsw_2fa'] ) : '';
		if ( ! $token ) {
			return false;
		}
		$data = get_transient( 'hsw_2fa_' . $token );
		return is_array( $data ) && ! empty( $data['user_id'] ) && ! empty( $data['expires'] ) && $data['expires'] >= time();
	}

	public function maybe_start_2fa( $user_login, $user ) {
		/*
		 * Deliberately NOT gated on otp_enabled: switching OTP *login* off used to
		 * silently drop the second factor for everyone who had enabled it.
		 */
		if ( defined( 'HSW_DISABLE_2FA' ) && HSW_DISABLE_2FA ) {
			return;
		}
		if ( $this->skip_2fa || 'yes' !== $this->s( 'otp_2fa', 'yes' ) || ! $user instanceof WP_User ) {
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
			$code = (string) $challenge->get_error_code();

			/*
			 * Fail-open is for a broken transport ONLY. It used to cover every
			 * WP_Error — including "a code was sent 30 seconds ago" — so anyone with
			 * the admin password could log in twice and walk past the second factor.
			 * Cooldown / throttle / configuration errors now block.
			 */
			$transport = (bool) preg_match( '/^hsw_(email_failed|sms_failed|sms_missing|telegram_http|wa_http|wa_phone)$/', $code );

			if ( $transport ) {
				$privileged = user_can( $user, 'manage_options' ) || user_can( $user, 'manage_woocommerce' );
				if ( $privileged && apply_filters( 'hsw_2fa_fail_open_privileged', false, $user, $challenge ) ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'HamiShop 2FA: delivery failed for a privileged account and fail-open is enabled; letting the login continue.' );
					return;
				}
			}

			wp_clear_auth_cookie();
			$this->destroy_issued_session( $user->ID );
			wp_safe_redirect(
				add_query_arg(
					'hsw_2fa_error',
					$transport ? 'delivery' : 'cooldown',
					wc_get_page_permalink( 'myaccount' )
				)
			);
			exit;
		}
		wp_clear_auth_cookie();
		$this->destroy_issued_session( $user->ID );
		wp_safe_redirect( add_query_arg( 'hsw_2fa', $challenge, wc_get_page_permalink( 'myaccount' ) ) );
		exit;
	}

	private function create_2fa_challenge( $user_id, $channel, $phone ) {
		$limit = $this->rate_limit( $phone, $channel, '2fa' );
		if ( is_wp_error( $limit ) ) {
			return $limit;
		}
		$token = bin2hex( random_bytes( 16 ) ); // 32 hex chars — survives parse_token() untouched.
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
		$token = isset( $_POST['token'] ) ? $this->parse_token( $_POST['token'] ) : '';
		$code  = isset( $_POST['code'] ) ? preg_replace( '/[^0-9]/', '', wp_unslash( $_POST['code'] ) ) : '';
		$data  = $token ? get_transient( 'hsw_2fa_' . $token ) : false;
		if ( ! is_array( $data ) || empty( $data['expires'] ) || $data['expires'] < time() ) {
			wp_send_json_error( array( 'message' => __( 'درخواست ورود منقضی شده است. دوباره وارد شوید.', 'hamishop-elementor-widgets' ) ), 400 );
		}
		$max = max( 1, min( 20, absint( $this->s( 'otp_max_attempts', 5 ) ) ) );
		/* Same per-code binding as check_code(): a resent code gets a fresh budget. */
		$bind = md5( (string) $token . '|' . (string) $data['hash'] );
		/* Atomic attempt counter (same reasoning as check_code()). */
		$att = HSW_Throttle::hit(
			'2fa_attempt:' . $bind,
			$max,
			max( 60, (int) $data['expires'] - time() )
		);
		if ( ! $att['allowed'] ) {
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
			$this->note_failed_verify( (string) $data['phone'] );
			wp_send_json_error( array( 'message' => __( 'کد وارد شده صحیح نیست.', 'hamishop-elementor-widgets' ) ), 400 );
		}
		/* Atomic single-use claim (same reasoning as check_code()). */
		$claim = HSW_Throttle::hit(
			'2fa_claim:' . $bind,
			1,
			max( 60, (int) $data['expires'] - time() )
		);
		if ( ! $claim['allowed'] ) {
			wp_send_json_error( array( 'message' => __( 'درخواست ورود منقضی شده است. دوباره وارد شوید.', 'hamishop-elementor-widgets' ) ), 400 );
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
		$token = isset( $_POST['token'] ) ? $this->parse_token( $_POST['token'] ) : '';
		$data  = $token ? get_transient( 'hsw_2fa_' . $token ) : false;
		if ( ! is_array( $data ) || empty( $data['expires'] ) || $data['expires'] < time() ) {
			wp_send_json_error( array( 'message' => __( 'درخواست ورود منقضی شده است. دوباره وارد شوید.', 'hamishop-elementor-widgets' ) ), 400 );
		}
		/* Same ceilings as a first send: per IP, per number, and per channel. */
		$throttle = $this->check_send_throttle( (string) $data['phone'], (string) $data['channel'] );
		if ( is_wp_error( $throttle ) ) {
			wp_send_json_error( array( 'message' => $throttle->get_error_message() ), 429 );
		}
		$limit = $this->rate_limit( $data['phone'], $data['channel'], '2fa' );
		if ( is_wp_error( $limit ) ) {
			wp_send_json_error( array( 'message' => $limit->get_error_message() ), 429 );
		}
		/* Three resends per challenge, counted atomically. */
		$resends = HSW_Throttle::hit( '2fa_resend:' . md5( (string) $token ), 3, max( 60, (int) $data['expires'] - time() ) );
		if ( ! $resends['allowed'] ) {
			wp_send_json_error( array( 'message' => __( 'برای این ورود به اندازهٔ کافی کد فرستاده شده است؛ دوباره وارد شوید.', 'hamishop-elementor-widgets' ) ), 429 );
		}
		$old_data = $data;
		$code = (string) wp_rand( 100000, 999999 );
		$data['hash']     = wp_hash_password( $code );
		$data['attempts'] = 0;
		/* The expiry is NOT pushed back: a resend must not extend the session. */
		set_transient( 'hsw_2fa_' . $token, $data, max( 1, (int) $data['expires'] - time() ) );
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
		if ( ! is_user_logged_in() || 'yes' !== $this->s( 'otp_2fa', 'yes' ) || ( defined( 'HSW_DISABLE_2FA' ) && HSW_DISABLE_2FA ) ) {
			wp_send_json_error( array( 'message' => __( 'برای تنظیم ورود دوعاملی باید وارد حساب شوید.', 'hamishop-elementor-widgets' ) ), 403 );
		}
		$user_id = get_current_user_id();
		/*
		 * P1-4: replacing a 2FA setup that is already ON must prove the password.
		 * A code delivered to the new channel is not proof — claiming that channel
		 * is exactly what this request does, and for SMS/WhatsApp a customer may
		 * type a number that was never attached to the account. Without this check
		 * an open session is enough to move the second factor to somebody else.
		 */
		if ( 'yes' === (string) get_user_meta( $user_id, '_hsw_2fa_enabled', true ) ) {
			$proof = $this->verify_2fa_reconfig_proof( $user_id );
			if ( is_wp_error( $proof ) ) {
				wp_send_json_error( array( 'message' => $proof->get_error_message() ), 403 );
			}
		}
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
		} elseif ( 'telegram' === $channel ) {
			$phone = $this->user_phone( $user_id );
			$typed = isset( $_POST['phone'] ) ? $this->normalize_phone( wp_unslash( $_POST['phone'] ) ) : '';
			if ( ! $phone && $typed ) {
				$owner = $this->phone_owner( $typed );
				if ( $owner && $owner !== (int) $user_id ) {
					wp_send_json_error( array( 'message' => __( 'این شماره قبلاً روی حساب دیگری تأیید شده است. با پشتیبانی تماس بگیرید.', 'hamishop-elementor-widgets' ) ), 409 );
				}
				$phone = $typed;
			}
			$chat = $this->telegram_chat_id( $user_id, $phone );
			if ( ! $chat ) {
				$token = $this->telegram_link_token( $user_id );
				wp_send_json_error(
					array(
						'message'   => __( 'ابتدا ربات تلگرام را باز کنید، دکمه Start (و ارسال شماره موبایل) را بزنید و سپس دوباره روی فعال‌سازی کلیک کنید.', 'hamishop-elementor-widgets' ),
						'link'      => $this->telegram_link( $token ),
						'link_text' => __( 'اتصال به ربات تلگرام', 'hamishop-elementor-widgets' ),
					),
					400
				);
			}
			if ( ! $phone ) {
				$contacts = get_option( 'hsw_telegram_contacts', array() );
				if ( is_array( $contacts ) ) {
					$matched_phone = array_search( (string) $chat, array_map( 'strval', $contacts ), true );
					if ( is_string( $matched_phone ) && '' !== $matched_phone ) {
						$phone = $matched_phone;
					}
				}
			}
			if ( ! $phone ) {
				$phone = 'tg:' . $chat;
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
		/*
		 * Attempts are counted in the atomic table, not in the transient: the old
		 * read-modify-write lost every parallel increment, so a burst of guesses
		 * against the setup code was never capped.
		 */
		$bind = md5( 'hsw_2fa_setup_' . $user_id . '|' . (string) $data['hash'] );
		$att  = HSW_Throttle::hit(
			'2fa_setup_attempt:' . $bind,
			$max,
			max( 60, (int) $data['expires'] - time() )
		);
		if ( ! $att['allowed'] ) {
			delete_transient( 'hsw_2fa_setup_' . $user_id );
			wp_send_json_error( array( 'message' => __( 'تعداد تلاش‌ها بیش از حد مجاز است. دوباره تلاش کنید.', 'hamishop-elementor-widgets' ) ), 429 );
		}
		if ( ! wp_check_password( (string) $code, $data['hash'] ) ) {
			wp_send_json_error( array( 'message' => __( 'کد وارد شده صحیح نیست.', 'hamishop-elementor-widgets' ) ), 400 );
		}
		delete_transient( 'hsw_2fa_setup_' . $user_id );

		/*
		 * Entering a code sent to that number is the proof of ownership, so the
		 * same act claims it as the login identity. A number already verified on
		 * another account is refused here too, not only when it was typed.
		 */
		if ( 'email' !== $data['channel'] ) {
			$setup_phone = (string) $data['phone'];
			if ( '' !== $setup_phone && 0 !== strpos( $setup_phone, 'tg:' ) ) {
				$claim = $this->claim_phone( $user_id, $setup_phone );
				if ( is_wp_error( $claim ) ) {
					wp_send_json_error( array( 'message' => $claim->get_error_message() ), 409 );
				}
				update_user_meta( $user_id, '_hsw_2fa_phone', $setup_phone );
			}
		} else {
			update_user_meta( $user_id, '_hsw_email_verified', time() );
		}

		update_user_meta( $user_id, '_hsw_2fa_enabled', 'yes' );
		update_user_meta( $user_id, '_hsw_2fa_channel', $data['channel'] );
		$this->notify_security_change( $user_id, __( 'ورود دوعاملی برای حساب شما فعال شد.', 'hamishop-elementor-widgets' ) );
		wp_send_json_success( array( 'message' => __( 'ورود دوعاملی فعال شد.', 'hamishop-elementor-widgets' ), 'reload' => true ) );
	}

	public function ajax_2fa_disable() {
		check_ajax_referer( 'hsw_2fa', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'hamishop-elementor-widgets' ) ), 403 );
		}
		$user_id = get_current_user_id();

		/* Turning the second factor off is exactly what a stolen session wants. */
		$proof = $this->verify_disable_proof( $user_id );
		if ( is_wp_error( $proof ) ) {
			$status = 'hsw_2fa_disable_locked' === $proof->get_error_code() ? 429 : 403;
			wp_send_json_error( array( 'message' => $proof->get_error_message() ), $status );
		}

		delete_user_meta( $user_id, '_hsw_2fa_enabled' );
		delete_user_meta( $user_id, '_hsw_2fa_channel' );
		delete_user_meta( $user_id, '_hsw_2fa_phone' );
		$this->notify_security_change( $user_id, __( 'ورود دوعاملی برای حساب شما غیرفعال شد.', 'hamishop-elementor-widgets' ) );
		wp_send_json_success( array( 'message' => __( 'ورود دوعاملی غیرفعال شد.', 'hamishop-elementor-widgets' ), 'reload' => true ) );
	}

	/**
	 * Proof of possession for changing an already-active 2FA setup: the password.
	 *
	 * Same five-tries-per-quarter limit as switching 2FA off, in the atomic
	 * counter, and the same reason: a session by itself must not be able to try.
	 *
	 * @param int $user_id User.
	 * @return true|WP_Error
	 */
	private function verify_2fa_reconfig_proof( $user_id ) {
		$attempts = HSW_Throttle::hit( '2fa_reconfig:' . (int) $user_id, 5, 15 * MINUTE_IN_SECONDS );
		if ( ! $attempts['allowed'] ) {
			return new WP_Error(
				'hsw_2fa_reconfig_locked',
				sprintf(
					/* translators: %s: minutes. */
					__( 'تلاش‌های ناموفق زیاد بود؛ تا %s دقیقه دیگر نمی‌توانید ورود دوعاملی را تغییر دهید.', 'hamishop-elementor-widgets' ),
					HSW_Helpers::fa_num( (int) ceil( max( 1, (int) $attempts['retry_after'] ) / 60 ) )
				)
			);
		}

		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$user     = get_user_by( 'id', (int) $user_id );

		if ( '' === $password || ! $user || ! wp_check_password( $password, $user->user_pass, (int) $user_id ) ) {
			return new WP_Error( 'hsw_2fa_reconfig_proof', __( 'برای تغییر ورود دوعاملی، رمز عبور حساب را وارد کنید.', 'hamishop-elementor-widgets' ) );
		}

		return true;
	}

	/**
	 * Proof of possession for "switch 2FA off": the current password, or a code
	 * that was just delivered to the 2FA channel.
	 *
	 * @param int $user_id User.
	 * @return true|WP_Error
	 */
	private function verify_disable_proof( $user_id ) {
		/*
		 * A session alone must not be enough, and a session alone must not be able
		 * to *try* either: five wrong proofs per user and quarter of an hour, in
		 * the atomic counter (a transient read-modify-write loses parallel hits).
		 */
		$attempts = HSW_Throttle::hit( '2fa_disable:' . (int) $user_id, 5, 15 * MINUTE_IN_SECONDS );
		if ( ! $attempts['allowed'] ) {
			return new WP_Error(
				'hsw_2fa_disable_locked',
				sprintf(
					/* translators: %s: minutes. */
					__( 'تلاش‌های ناموفق زیاد بود؛ تا %s دقیقه دیگر نمی‌توانید ورود دوعاملی را غیرفعال کنید.', 'hamishop-elementor-widgets' ),
					HSW_Helpers::fa_num( (int) ceil( max( 1, (int) $attempts['retry_after'] ) / 60 ) )
				)
			);
		}

		$code = isset( $_POST['code'] ) ? preg_replace( '/[^0-9]/', '', wp_unslash( $_POST['code'] ) ) : '';
		if ( '' !== $code ) {
			$data = get_transient( 'hsw_2fa_setup_' . $user_id );
			if ( is_array( $data ) && ! empty( $data['hash'] ) && wp_check_password( $code, $data['hash'] ) ) {
				delete_transient( 'hsw_2fa_setup_' . $user_id );
				return true;
			}
			return new WP_Error( 'hsw_2fa_proof', __( 'کد وارد شده صحیح نیست یا منقضی شده است.', 'hamishop-elementor-widgets' ) );
		}

		/* A shop that cannot ask for a password can opt out explicitly. */
		if ( 'none' === (string) apply_filters( 'hsw_2fa_disable_proof', 'password' ) ) {
			return true;
		}

		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$user     = get_user_by( 'id', $user_id );
		if ( '' === $password || ! $user || ! wp_check_password( $password, (string) ( $user->user_pass ?? '' ) ) ) {
			return new WP_Error( 'hsw_2fa_proof', __( 'برای غیرفعال‌سازی، رمز عبور فعلی یا کد تأیید را وارد کنید.', 'hamishop-elementor-widgets' ) );
		}

		return true;
	}

	/**
	 * Tell the account owner by e-mail that a security setting moved.
	 *
	 * @param int    $user_id User.
	 * @param string $what    Human sentence.
	 */
	private function notify_security_change( $user_id, $what ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user || ! is_email( (string) $user->user_email ) ) {
			return;
		}
		wp_mail(
			$user->user_email,
			sprintf(
				/* translators: %s: site name. */
				__( 'تغییر امنیتی حساب در %s', 'hamishop-elementor-widgets' ),
				get_bloginfo( 'name' )
			),
			$what . "\n" . home_url( '/' )
		);
	}

	/* ---------------------------------------------------------------------
	 * Bale webhook
	 * ------------------------------------------------------------------ */

	public function maybe_handle_telegram_webhook() {
		if ( empty( $_GET['hsw_telegram_webhook'] ) ) {
			return;
		}

		/*
		 * Polling owns the updates when it is on. Telegram keeps retrying a
		 * webhook it cannot reach, so both paths could otherwise process the
		 * same message twice (two "connected" replies, two chat bindings).
		 */
		if ( 'yes' === $this->s( 'telegram_polling', 'no' ) ) {
			status_header( 200 );
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			echo wp_json_encode( array( 'ok' => true, 'mode' => 'polling' ) );
			exit;
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

		$this->handle_telegram_update( $data );

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( array( 'ok' => true ) );
		exit;
	}

	/**
	 * Apply one Telegram update.
	 *
	 * Both transports feed this method — the webhook above and the long-polling
	 * worker (`HSW_Telegram_Poller`), which exists for hosts where Telegram
	 * cannot reach the site. Keeping the rules in one place is what stops the
	 * two paths from answering differently, and the contact-ownership check
	 * below must never be re-implemented elsewhere.
	 *
	 * @param array $data Decoded update.
	 */
	public function handle_telegram_update( $data ) {
		if ( ! is_array( $data ) ) {
			return;
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
			$token   = isset( $matches[1] ) ? $this->parse_token( $matches[1] ) : '';
			$user_id = $token ? (int) get_transient( 'hsw_telegram_link_' . $token ) : 0;
			if ( $user_id ) {
				update_user_meta( $user_id, '_hsw_telegram_chat_id', $chat_id );
				$verified_phone = $this->user_phone( $user_id );
				if ( $verified_phone ) {
					$contacts = get_option( 'hsw_telegram_contacts', array() );
					$contacts = is_array( $contacts ) ? $contacts : array();
					$contacts[ $verified_phone ] = $chat_id;
					update_option( 'hsw_telegram_contacts', $contacts, false );
				}
				delete_transient( 'hsw_telegram_link_' . $token );
				$this->telegram_send(
					$chat_id,
					__( 'حساب شما به ربات متصل شد. برای تکمیل اتصال شماره موبایل، دکمهٔ «ارسال شماره موبایل» را هم بزنید.', 'hamishop-elementor-widgets' ),
					$keyboard
				);
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
			$html .= '<div class="hsw-ac-field"><label for="' . esc_attr( $id ) . '-name">' . esc_html__( 'نام و نام خانوادگی', 'hamishop-elementor-widgets' ) . '</label><div class="hsw-ac-input-wrap">' . $this->account->icon( 'user' ) . '<input type="text" id="' . esc_attr( $id ) . '-name" name="name" autocomplete="name" placeholder="' . esc_attr__( 'مثلاً: جواد نکویی', 'hamishop-elementor-widgets' ) . '" data-hsw-otp-name></div></div>';
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
		/* Independent of the OTP *login* switch, like maybe_start_2fa(). */
		if ( 'yes' !== $this->s( 'otp_2fa', 'yes' ) || ( defined( 'HSW_DISABLE_2FA' ) && HSW_DISABLE_2FA ) ) {
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
			/*
			 * Switching the second factor off needs proof of possession (password or
			 * a fresh code); a session cookie alone used to be enough.
			 */
			$html .= '<div class="hsw-ac-field"><label for="hsw-2fa-off-pass">' . esc_html__( 'برای غیرفعال‌سازی، رمز عبور فعلی', 'hamishop-elementor-widgets' ) . '</label><div class="hsw-ac-input-wrap">' . $this->account->icon( 'shield' ) . '<input type="password" id="hsw-2fa-off-pass" autocomplete="current-password" dir="ltr" data-hsw-2fa-pass></div></div>';
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
		if ( $enabled ) {
			/*
			 * P1-4: moving an active second factor to another channel (or another
			 * typed phone number) needs the password, not only the session.
			 */
			$html .= '<div class="hsw-ac-field"><label for="hsw-2fa-reconfig-pass">' . esc_html__( 'برای تغییر، رمز عبور فعلی', 'hamishop-elementor-widgets' ) . '</label><div class="hsw-ac-input-wrap">' . $this->account->icon( 'shield' ) . '<input type="password" id="hsw-2fa-reconfig-pass" autocomplete="current-password" dir="ltr" data-hsw-2fa-reconfig-pass></div></div>';
		}
		$html .= '<div class="hsw-ac-otp__code-step" data-hsw-2fa-code-step hidden><label for="hsw-2fa-setup-code">' . esc_html__( 'کد تأیید', 'hamishop-elementor-widgets' ) . '</label><div class="hsw-ac-input-wrap">' . $this->account->icon( 'shield' ) . '<input type="text" id="hsw-2fa-setup-code" inputmode="numeric" maxlength="6" dir="ltr" data-hsw-2fa-code></div><div class="hsw-ac-otp__actions"><button type="button" class="hsw-ac__btn hsw-ac__btn--primary" data-hsw-2fa-verify>' . esc_html__( 'تأیید و فعال‌سازی', 'hamishop-elementor-widgets' ) . '</button><button type="button" class="hsw-ac-otp__resend" data-hsw-2fa-setup-resend>' . esc_html__( 'ارسال دوباره', 'hamishop-elementor-widgets' ) . '</button></div></div>';
		$html .= '<button type="button" class="hsw-ac__btn hsw-ac__btn--primary" data-hsw-2fa-setup>' . esc_html__( 'فعال‌سازی ورود دوعاملی', 'hamishop-elementor-widgets' ) . '</button>';
		$html .= '<p class="hsw-ac-otp__message" data-hsw-2fa-message role="status" aria-live="polite"></p>';
		return $html . '</section>';
	}

	public function render_2fa_challenge( $opts ) {
		$token = isset( $_GET['hsw_2fa'] ) ? $this->parse_token( $_GET['hsw_2fa'] ) : '';
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
