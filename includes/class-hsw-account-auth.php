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

		add_action( 'init', array( $this, 'maybe_handle_bale_webhook' ), 0 );
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
		if ( 'bale' === $channel ) {
			return 'yes' === $this->s( 'otp_bale', 'no' ) && '' !== trim( (string) $this->s( 'bale_token' ) );
		}
		if ( 'whatsapp' === $channel ) {
			return 'yes' === $this->s( 'otp_whatsapp', 'no' ) && $this->whatsapp_configured();
		}
		return false;
	}

	private function whatsapp_configured() {
		$provider = (string) $this->s( 'whatsapp_provider', 'cloud' );
		if ( 'bridge' === $provider ) {
			return '' !== trim( (string) $this->s( 'whatsapp_bridge_url' ) );
		}
		return '' !== trim( (string) $this->s( 'whatsapp_token' ) ) && '' !== trim( (string) $this->s( 'whatsapp_phone_id' ) );
	}

	public function available_channels() {
		$out = array();
		if ( $this->channel_enabled( 'bale' ) ) {
			$out['bale'] = __( 'بله', 'hamishop-elementor-widgets' );
		}
		if ( $this->channel_enabled( 'whatsapp' ) ) {
			$out['whatsapp'] = __( 'واتساپ', 'hamishop-elementor-widgets' );
		}
		return $out;
	}

	private function channel_label( $channel ) {
		return 'bale' === $channel ? __( 'بله', 'hamishop-elementor-widgets' ) : __( 'واتساپ', 'hamishop-elementor-widgets' );
	}

	private function default_channel() {
		$default = sanitize_key( (string) $this->s( 'otp_default_channel', 'bale' ) );
		$channels = $this->available_channels();
		if ( isset( $channels[ $default ] ) ) {
			return $default;
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

	private function mask_phone( $phone ) {
		$phone = $this->normalize_phone( $phone );
		return $phone ? substr( $phone, 0, 4 ) . '***' . substr( $phone, -3 ) : '';
	}

	private function find_user_by_phone( $phone ) {
		global $wpdb;
		$phone = $this->normalize_phone( $phone );
		if ( ! $phone ) {
			return null;
		}
		$tail = substr( $phone, -10 );
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key IN ('billing_phone','_hsw_auth_phone','_hsw_2fa_phone') AND meta_value LIKE %s LIMIT 5",
				'%' . $wpdb->esc_like( $tail ) . '%'
			)
		);
		foreach ( $rows as $user_id ) {
			$user = get_user_by( 'id', (int) $user_id );
			if ( $user && $this->user_phone( $user->ID ) === $phone ) {
				return $user;
			}
		}
		return null;
	}

	private function user_phone( $user_id ) {
		$phone = get_user_meta( $user_id, 'billing_phone', true );
		if ( ! $phone ) {
			$phone = get_user_meta( $user_id, '_hsw_auth_phone', true );
		}
		if ( ! $phone ) {
			$phone = get_user_meta( $user_id, '_hsw_2fa_phone', true );
		}
		return $this->normalize_phone( $phone );
	}

	private function bale_chat_id( $user_id, $phone = '' ) {
		$chat = get_user_meta( $user_id, '_hsw_bale_chat_id', true );
		if ( $chat ) {
			return (string) $chat;
		}
		$phone = $this->normalize_phone( $phone ? $phone : $this->user_phone( $user_id ) );
		if ( ! $phone ) {
			return '';
		}
		$contacts = get_option( 'hsw_bale_contacts', array() );
		return is_array( $contacts ) && ! empty( $contacts[ $phone ] ) ? (string) $contacts[ $phone ] : '';
	}

	public function bale_link( $token = '' ) {
		$bot = trim( (string) $this->s( 'bale_bot' ) );
		if ( '' === $bot ) {
			return '';
		}
		$bot = preg_replace( '/[^a-zA-Z0-9_]/', '', $bot );
		$url = 'https://ble.ir/' . $bot;
		if ( $token ) {
			$url = add_query_arg( 'start', rawurlencode( $token ), $url );
		}
		return $url;
	}

	private function bale_link_token( $user_id ) {
		$token = wp_generate_password( 28, false, false );
		set_transient( 'hsw_bale_link_' . $token, (int) $user_id, 15 * MINUTE_IN_SECONDS );
		return $token;
	}

	/* ---------------------------------------------------------------------
	 * OTP storage and delivery
	 * ------------------------------------------------------------------ */

	private function code_key( $phone, $channel, $context ) {
		return 'hsw_otp_' . md5( $this->normalize_phone( $phone ) . '|' . $channel . '|' . $context );
	}

	private function store_code( $phone, $channel, $context, $user_id = 0 ) {
		$phone = $this->normalize_phone( $phone );
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
		$key = 'hsw_otp_cool_' . md5( $this->normalize_phone( $phone ) . '|' . $channel . '|' . $context );
		if ( get_transient( $key ) ) {
			return new WP_Error( 'hsw_otp_cooldown', __( 'کد قبلی هنوز معتبر است. کمی بعد دوباره تلاش کنید.', 'hamishop-elementor-widgets' ) );
		}
		$cooldown = max( 20, min( 900, absint( $this->s( 'otp_resend', 60 ) ) ) );
		set_transient( $key, 1, $cooldown );
		return true;
	}

	private function clear_rate_limit( $phone, $channel, $context ) {
		delete_transient( 'hsw_otp_cool_' . md5( $this->normalize_phone( $phone ) . '|' . $channel . '|' . $context ) );
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

		if ( 'bale' === $channel ) {
			$chat = $this->bale_chat_id( $user_id, $phone );
			if ( ! $chat ) {
				return new WP_Error(
					'hsw_bale_not_connected',
					__( 'برای دریافت کد در بله، ابتدا ربات فروشگاه را باز کنید و شماره موبایل خود را ارسال کنید.', 'hamishop-elementor-widgets' ),
					array( 'link' => $this->bale_link() )
				);
			}
			return $this->bale_send( $chat, $this->message( $code ) );
		}

		if ( 'whatsapp' === $channel ) {
			return $this->whatsapp_send( $phone, $this->message( $code ) );
		}

		return new WP_Error( 'hsw_otp_channel', __( 'کانال ارسال کد پشتیبانی نمی‌شود.', 'hamishop-elementor-widgets' ) );
	}

	private function bale_send( $chat_id, $text, $reply_markup = null ) {
		$token = trim( (string) $this->s( 'bale_token' ) );
		$token = preg_replace( '/[^a-zA-Z0-9:_-]/', '', $token );
		if ( ! $token || ! $chat_id ) {
			return new WP_Error( 'hsw_bale_config', __( 'تنظیمات ربات بله کامل نیست.', 'hamishop-elementor-widgets' ) );
		}
		$body = array(
			'chat_id' => $chat_id,
			'text'    => $text,
		);
		if ( $reply_markup ) {
			$body['reply_markup'] = $reply_markup;
		}
		$response = wp_remote_post(
			'https://tapi.bale.ai/bot' . $token . '/sendMessage',
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
			return new WP_Error( 'hsw_bale_http', __( 'ارسال پیام بله ناموفق بود.', 'hamishop-elementor-widgets' ) );
		}
		return true;
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

		$phone   = $this->normalize_phone( isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '' );
		$channel = isset( $_POST['channel'] ) ? sanitize_key( wp_unslash( $_POST['channel'] ) ) : '';
		$context = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : 'login';
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

		$user = $this->find_user_by_phone( $phone );
		if ( 'login' === $context && ! $user ) {
			wp_send_json_error( array( 'message' => __( 'حسابی با این شماره پیدا نشد. از تب ثبت‌نام استفاده کنید.', 'hamishop-elementor-widgets' ), 'switch_tab' => 'register' ), 404 );
		}
		if ( 'register' === $context && $user ) {
			wp_send_json_error( array( 'message' => __( 'این شماره قبلاً ثبت‌نام کرده است. از تب ورود استفاده کنید.', 'hamishop-elementor-widgets' ), 'switch_tab' => 'login' ), 409 );
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
				$data['link_text'] = __( 'باز کردن ربات بله', 'hamishop-elementor-widgets' );
			}
			wp_send_json_error( $data, 400 );
		}

		wp_send_json_success(
			array(
				'message' => __( 'کد تأیید ارسال شد. پیام‌های بله یا واتساپ خود را بررسی کنید.', 'hamishop-elementor-widgets' ),
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

		$phone   = $this->normalize_phone( isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '' );
		$channel = isset( $_POST['channel'] ) ? sanitize_key( wp_unslash( $_POST['channel'] ) ) : '';
		$context = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : 'login';
		$code    = isset( $_POST['code'] ) ? preg_replace( '/[^0-9]/', '', wp_unslash( $_POST['code'] ) ) : '';
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
			wp_send_json_error( array( 'message' => __( 'شماره موبایل و کد تأیید را کامل وارد کنید.', 'hamishop-elementor-widgets' ) ), 400 );
		}

		$user = $this->find_user_by_phone( $phone );
		if ( 'login' === $context && ! $user ) {
			wp_send_json_error( array( 'message' => __( 'حسابی با این شماره پیدا نشد.', 'hamishop-elementor-widgets' ), 'switch_tab' => 'register' ), 404 );
		}

		$name  = '';
		$email = '';
		if ( 'register' === $context ) {
			if ( $user ) {
				wp_send_json_error( array( 'message' => __( 'این شماره قبلاً ثبت‌نام کرده است.', 'hamishop-elementor-widgets' ), 'switch_tab' => 'login' ), 409 );
			}
			$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			if ( ! $name || ! $email || ! is_email( $email ) ) {
				wp_send_json_error( array( 'message' => __( 'برای ثبت‌نام، نام و ایمیل معتبر را وارد کنید.', 'hamishop-elementor-widgets' ) ), 400 );
			}
			if ( email_exists( $email ) ) {
				wp_send_json_error( array( 'message' => __( 'این ایمیل قبلاً در سایت ثبت شده است.', 'hamishop-elementor-widgets' ) ), 409 );
			}
		}

		$checked = $this->check_code( $phone, $channel, $context, $code );
		if ( is_wp_error( $checked ) ) {
			wp_send_json_error( array( 'message' => $checked->get_error_message() ), 400 );
		}

		if ( 'login' === $context ) {
			$this->login_user( $user );
			wp_send_json_success(
				array(
					'message'  => __( 'ورود با موفقیت انجام شد.', 'hamishop-elementor-widgets' ),
					'redirect' => wc_get_page_permalink( 'myaccount' ),
				)
			);
		}

		$parts = preg_split( '/\s+/u', $name, 2 );
		$first = $parts[0];
		$last  = isset( $parts[1] ) ? $parts[1] : '';
		$base  = 'hsw_' . substr( $phone, -10 );
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
		update_user_meta( $user_id, 'billing_phone', $phone );
		update_user_meta( $user_id, 'billing_first_name', $first );
		update_user_meta( $user_id, 'billing_last_name', $last );
		update_user_meta( $user_id, '_hsw_auth_phone', $phone );
		$chat = $this->bale_chat_id( 0, $phone );
		if ( $chat ) {
			update_user_meta( $user_id, '_hsw_bale_chat_id', $chat );
		}

		$user = get_user_by( 'id', $user_id );
		$this->login_user( $user );
		wp_send_json_success(
			array(
				'message'  => __( 'حساب شما ساخته شد و وارد شدید.', 'hamishop-elementor-widgets' ),
				'redirect' => wc_get_page_permalink( 'myaccount' ),
			)
		);
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
		if ( ! in_array( $channel, array( 'bale', 'whatsapp' ), true ) || 'yes' !== get_user_meta( $user->ID, '_hsw_2fa_enabled', true ) ) {
			return;
		}
		$phone = $this->user_phone( $user->ID );
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
		if ( ! wp_check_password( (string) $code, $data['hash'] ) ) {
			$data['attempts'] = (int) $data['attempts'] + 1;
			set_transient( 'hsw_2fa_' . $token, $data, max( 1, $data['expires'] - time() ) );
			wp_send_json_error( array( 'message' => __( 'کد وارد شده صحیح نیست.', 'hamishop-elementor-widgets' ) ), 400 );
		}
		$user = get_user_by( 'id', (int) $data['user_id'] );
		if ( ! $user ) {
			delete_transient( 'hsw_2fa_' . $token );
			wp_send_json_error( array( 'message' => __( 'حساب کاربری پیدا نشد.', 'hamishop-elementor-widgets' ) ), 404 );
		}
		delete_transient( 'hsw_2fa_' . $token );
		$this->clear_rate_limit( (string) $data['phone'], (string) $data['channel'], '2fa' );
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
		$phone = $this->user_phone( $user_id );
		if ( ! $phone ) {
			wp_send_json_error( array( 'message' => __( 'ابتدا شماره موبایل خود را در اطلاعات حساب ثبت کنید.', 'hamishop-elementor-widgets' ), 'edit_account' => wc_get_account_endpoint_url( 'edit-account' ) ), 400 );
		}
		if ( 'bale' === $channel && ! $this->bale_chat_id( $user_id, $phone ) ) {
			$token = $this->bale_link_token( $user_id );
			wp_send_json_error(
				array(
					'message'   => __( 'ابتدا ربات بله را باز کنید و شماره خود را به آن ارسال کنید.', 'hamishop-elementor-widgets' ),
					'link'      => $this->bale_link( $token ),
					'link_text' => __( 'اتصال به ربات بله', 'hamishop-elementor-widgets' ),
				),
				400
			);
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
		update_user_meta( $user_id, '_hsw_2fa_enabled', 'yes' );
		update_user_meta( $user_id, '_hsw_2fa_channel', $data['channel'] );
		update_user_meta( $user_id, '_hsw_2fa_phone', $data['phone'] );
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

	public function maybe_handle_bale_webhook() {
		if ( empty( $_GET['hsw_bale_webhook'] ) ) {
			return;
		}
		$secret = (string) $this->s( 'bale_secret' );
		$given  = isset( $_GET['secret'] ) ? (string) wp_unslash( $_GET['secret'] ) : '';
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

		$message = isset( $data['message'] ) && is_array( $data['message'] ) ? $data['message'] : array();
		$chat_id = isset( $message['chat']['id'] ) ? (string) $message['chat']['id'] : '';
		$text    = isset( $message['text'] ) ? (string) $message['text'] : '';
		$contact = isset( $message['contact'] ) && is_array( $message['contact'] ) ? $message['contact'] : array();

		if ( $contact && $chat_id ) {
			$phone = $this->normalize_phone( isset( $contact['phone_number'] ) ? $contact['phone_number'] : '' );
			if ( $phone ) {
				$contacts = get_option( 'hsw_bale_contacts', array() );
				$contacts = is_array( $contacts ) ? $contacts : array();
				$contacts[ $phone ] = $chat_id;
				if ( count( $contacts ) > 1000 ) {
					$contacts = array_slice( $contacts, -1000, null, true );
				}
				update_option( 'hsw_bale_contacts', $contacts, false );
				$user = $this->find_user_by_phone( $phone );
				if ( $user ) {
					update_user_meta( $user->ID, '_hsw_bale_chat_id', $chat_id );
				}
				$this->bale_send( $chat_id, __( 'شماره شما با موفقیت به حساب فروشگاه متصل شد.', 'hamishop-elementor-widgets' ) );
			}
		} elseif ( preg_match( '/^\/start(?:\s+(\S+))?/u', $text, $matches ) ) {
			$token = isset( $matches[1] ) ? sanitize_key( $matches[1] ) : '';
			$user_id = $token ? (int) get_transient( 'hsw_bale_link_' . $token ) : 0;
			if ( $user_id ) {
				update_user_meta( $user_id, '_hsw_bale_chat_id', $chat_id );
				delete_transient( 'hsw_bale_link_' . $token );
				$this->bale_send( $chat_id, __( 'حساب شما به ربات متصل شد. حالا می‌توانید از ورود دوعاملی بله استفاده کنید.', 'hamishop-elementor-widgets' ) );
			} else {
				$this->bale_send(
					$chat_id,
					__( 'برای اتصال شماره موبایل، دکمهٔ زیر را بزنید و شماره خود را ارسال کنید.', 'hamishop-elementor-widgets' ),
					array(
						'keyboard'          => array( array( array( 'text' => __( 'ارسال شماره موبایل', 'hamishop-elementor-widgets' ), 'request_contact' => true ) ) ),
						'resize_keyboard'   => true,
						'one_time_keyboard' => true,
					)
				);
			}
		} else {
			$this->bale_send(
				$chat_id,
				__( 'برای دریافت کد ورود، ابتدا شماره موبایل خود را با دکمهٔ زیر ارسال کنید.', 'hamishop-elementor-widgets' ),
				array(
					'keyboard'          => array( array( array( 'text' => __( 'ارسال شماره موبایل', 'hamishop-elementor-widgets' ), 'request_contact' => true ) ) ),
					'resize_keyboard'   => true,
					'one_time_keyboard' => true,
				)
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

		$html  = '<section class="hsw-ac-otp" data-hsw-otp data-context="' . esc_attr( $context ) . '" data-channels="' . esc_attr( implode( ',', array_keys( $channels ) ) ) . '">';
		$html .= '<div class="hsw-ac-otp__head"><span class="hsw-ac-otp__eyebrow">' . esc_html__( 'ورود سریع', 'hamishop-elementor-widgets' ) . '</span><h2>' . esc_html( 'register' === $context ? __( 'ثبت‌نام با شماره موبایل', 'hamishop-elementor-widgets' ) : __( 'ورود با کد یکبارمصرف', 'hamishop-elementor-widgets' ) ) . '</h2><p>' . esc_html__( 'کد تأیید به بله یا واتساپ شما ارسال می‌شود؛ نیازی به رمز عبور نیست.', 'hamishop-elementor-widgets' ) . '</p></div>';
		$html .= '<div class="hsw-ac-otp__channels" role="radiogroup" aria-label="' . esc_attr__( 'کانال دریافت کد', 'hamishop-elementor-widgets' ) . '">';
		foreach ( $channels as $key => $label ) {
			$html .= '<button type="button" class="hsw-ac-otp__channel' . ( $key === $default ? ' is-active' : '' ) . '" data-hsw-otp-channel="' . esc_attr( $key ) . '" aria-pressed="' . ( $key === $default ? 'true' : 'false' ) . '">' . $this->account->icon( 'bale' === $key ? 'send' : 'phone' ) . esc_html( $label ) . '</button>';
		}
		$html .= '</div>';

		$html .= '<div class="hsw-ac-otp__form" data-hsw-otp-form>';
		if ( 'register' === $context ) {
			$html .= '<div class="hsw-ac-field"><label for="' . esc_attr( $id ) . '-name">' . esc_html__( 'نام و نام خانوادگی', 'hamishop-elementor-widgets' ) . '</label><div class="hsw-ac-input-wrap">' . $this->account->icon( 'user' ) . '<input type="text" id="' . esc_attr( $id ) . '-name" name="name" autocomplete="name" data-hsw-otp-name></div></div>';
			$html .= '<div class="hsw-ac-field"><label for="' . esc_attr( $id ) . '-email">' . esc_html__( 'ایمیل', 'hamishop-elementor-widgets' ) . '</label><div class="hsw-ac-input-wrap">' . $this->account->icon( 'mail' ) . '<input type="email" id="' . esc_attr( $id ) . '-email" name="email" autocomplete="email" data-hsw-otp-email></div></div>';
		}
		$html .= '<div class="hsw-ac-field"><label for="' . esc_attr( $id ) . '-phone">' . esc_html__( 'شماره موبایل', 'hamishop-elementor-widgets' ) . '</label><div class="hsw-ac-input-wrap">' . $this->account->icon( 'phone' ) . '<input type="tel" id="' . esc_attr( $id ) . '-phone" name="phone" inputmode="tel" autocomplete="tel" placeholder="09121234567" dir="ltr" data-hsw-otp-phone></div></div>';
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
		$phone    = $this->user_phone( $user_id );
		$chat     = $this->bale_chat_id( $user_id, $phone );
		$default  = isset( $channels[ $active ] ) ? $active : $this->default_channel();
		$link     = ( ! $chat && isset( $channels['bale'] ) ) ? $this->bale_link( $this->bale_link_token( $user_id ) ) : '';

		$html  = '<section class="hsw-ac__card hsw-ac__security" data-hsw-2fa>';
		$html .= '<header class="hsw-ac__card-head"><div><h2 class="hsw-ac__card-title">' . esc_html__( 'امنیت حساب', 'hamishop-elementor-widgets' ) . '</h2><p>' . esc_html__( 'ورود دوعاملی، امنیت حساب شما را بیشتر می‌کند.', 'hamishop-elementor-widgets' ) . '</p></div><span class="hsw-ac__card-head-ic">' . $this->account->icon( 'shield' ) . '</span></header>';

		if ( $enabled ) {
			$html .= '<div class="hsw-ac__security-active"><span class="hsw-ac__security-dot"></span><div><strong>' . esc_html__( 'ورود دوعاملی فعال است', 'hamishop-elementor-widgets' ) . '</strong><p>' . esc_html( sprintf( 'کد ورود از طریق %s به %s ارسال می‌شود.', $this->channel_label( $active ), $this->mask_phone( $phone ) ) ) . '</p></div></div>';
			$html .= '<button type="button" class="hsw-ac__btn hsw-ac__btn--ghost" data-hsw-2fa-disable>' . $this->account->icon( 'x' ) . esc_html__( 'غیرفعال کردن', 'hamishop-elementor-widgets' ) . '</button>';
			$html .= '<p class="hsw-ac-otp__message" data-hsw-2fa-message role="status" aria-live="polite"></p>';
			return $html . '</section>';
		}

		$html .= '<p class="hsw-ac__security-desc">' . esc_html__( 'روش دریافت کد را انتخاب کنید؛ پس از تأیید کد، ورود دوعاملی فعال می‌شود.', 'hamishop-elementor-widgets' ) . '</p>';
		$html .= '<div class="hsw-ac-otp__channels" role="radiogroup" aria-label="' . esc_attr__( 'روش ورود دوعاملی', 'hamishop-elementor-widgets' ) . '">';
		foreach ( $channels as $key => $label ) {
			$html .= '<button type="button" class="hsw-ac-otp__channel' . ( $key === $default ? ' is-active' : '' ) . '" data-hsw-2fa-channel="' . esc_attr( $key ) . '" aria-pressed="' . ( $key === $default ? 'true' : 'false' ) . '">' . $this->account->icon( 'bale' === $key ? 'send' : 'phone' ) . esc_html( $label ) . '</button>';
		}
		$html .= '</div>';
		if ( isset( $channels['bale'] ) ) {
			$html .= '<p class="hsw-ac__security-note">' . ( $chat ? esc_html__( 'ربات بله متصل است.', 'hamishop-elementor-widgets' ) : esc_html__( 'برای بله، ابتدا ربات را باز کنید و شماره خود را ارسال کنید.', 'hamishop-elementor-widgets' ) );
			if ( ! $chat && $link ) {
				$html .= ' <a href="' . esc_url( $link ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'اتصال به ربات بله', 'hamishop-elementor-widgets' ) . '</a>';
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
		$phone = $this->mask_phone( isset( $data['phone'] ) ? $data['phone'] : '' );
		$channel = isset( $data['channel'] ) ? $this->channel_label( $data['channel'] ) : '';
		$html  = '<div class="hsw-ac hsw-ac--auth"><div class="hsw-ac-auth hsw-ac-auth--slim"><div class="hsw-ac-auth__panel-wrap"><div class="hsw-ac-auth__panel">';
		$html .= '<div class="hsw-ac-auth__heading"><span class="hsw-ac-auth__eyebrow">' . esc_html__( 'تأیید دوعاملی', 'hamishop-elementor-widgets' ) . '</span><h1>' . esc_html__( 'کد تأیید را وارد کنید', 'hamishop-elementor-widgets' ) . '</h1><p>' . esc_html( sprintf( 'کد ورود از طریق %s به %s ارسال شد.', $channel, $phone ) ) . '</p></div>';
		$html .= '<div class="hsw-ac-otp hsw-ac-otp--challenge" data-hsw-2fa-challenge data-token="' . esc_attr( $token ) . '"><div class="hsw-ac-field"><label for="hsw-2fa-challenge-code">' . esc_html__( 'کد ۶ رقمی', 'hamishop-elementor-widgets' ) . '</label><div class="hsw-ac-input-wrap">' . $this->account->icon( 'shield' ) . '<input type="text" id="hsw-2fa-challenge-code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" dir="ltr" data-hsw-2fa-code></div></div><button type="button" class="hsw-ac__btn hsw-ac__btn--primary" data-hsw-2fa-verify>' . esc_html__( 'تأیید و ورود', 'hamishop-elementor-widgets' ) . '</button><button type="button" class="hsw-ac-otp__resend" data-hsw-2fa-resend>' . esc_html__( 'ارسال دوباره', 'hamishop-elementor-widgets' ) . '</button><p class="hsw-ac-otp__message" data-hsw-2fa-message role="status" aria-live="polite"></p></div>';
		$html .= '<a class="hsw-ac-auth__back" href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">' . $this->account->icon( 'arrow-right' ) . esc_html__( 'بازگشت به ورود', 'hamishop-elementor-widgets' ) . '</a>';
		$html .= '</div></div></div></div>';
		return $html;
	}
}
