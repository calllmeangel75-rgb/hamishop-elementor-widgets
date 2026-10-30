<?php
/**
 * HamiShop customer account service.
 *
 * One optimized, WooCommerce-native account experience:
 * - dashboard design (RTL, responsive, zero external requests),
 * - real WooCommerce endpoints and forms,
 * - custom login/register screens processed by WooCommerce itself,
 * - support tickets, internal notifications, wishlist and wallet,
 * - admin settings + Elementor widget + shortcode.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

final class HSW_Account {

	const OPTION  = 'hsw_account_settings';
	const VERSION = '1.0.0';

	/** @var HSW_Account|null */
	private static $instance = null;

	/** @var array */
	private $settings = array();

	/** @var HSW_Account_Support|null */
	public $support = null;

	/** @var HSW_Account_Auth|null */
	public $auth = null;

	/** @var HSW_Account_Admin|null */
	public $admin = null;

	/** @var HSW_Email_Verify|null */
	public $email_verify = null;

	/** @var bool */
	private $tokens_printed = false;

	/** @var bool */
	private $notices_prepared = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->settings = $this->load_settings();

		if ( class_exists( 'HSW_Account_Support' ) ) {
			$this->support = new HSW_Account_Support( $this );
		}
		if ( class_exists( 'HSW_Account_Auth' ) ) {
			$this->auth = new HSW_Account_Auth( $this );
		}
		if ( class_exists( 'HSW_Email_Verify' ) ) {
			$this->email_verify = HSW_Email_Verify::instance( $this );
		}
		if ( is_admin() && class_exists( 'HSW_Account_Admin' ) ) {
			$this->admin = new HSW_Account_Admin( $this );
		}

		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ), 20 );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		add_filter( 'woocommerce_get_query_vars', array( $this, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'filter_native_menu' ), 30, 2 );

		add_action( 'woocommerce_account_notifications_endpoint', array( $this, 'render_notifications_endpoint' ) );
		add_action( 'woocommerce_account_wishlist_endpoint', array( $this, 'render_wishlist_endpoint' ) );
		add_action( 'woocommerce_account_wallet_endpoint', array( $this, 'render_wallet_endpoint' ) );

		add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status_changed' ), 20, 4 );
		add_action( 'woocommerce_created_customer', array( $this, 'on_customer_created' ), 20, 3 );

		add_action( 'update_option_' . self::OPTION, array( $this, 'on_settings_updated' ) );

		$titles = array(
			'support'       => 'پشتیبانی',
			'notifications' => 'اعلان‌ها',
			'wishlist'      => 'علاقه‌مندی‌ها',
			'wallet'        => 'کیف پول',
		);
		foreach ( $titles as $endpoint => $title ) {
			add_filter(
				'woocommerce_endpoint_' . $endpoint . '_title',
				function () use ( $title ) {
					return $title;
				}
			);
		}
	}

	/* ---------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	public static function defaults() {
		return array(
			'version' => defined( 'HSW_VERSION' ) ? HSW_VERSION : self::VERSION,
			'general' => array(
				'enabled'              => 'yes',
				'side_position'        => 'right',
				'side_width'           => 320,
				'gap'                  => 24,
				'max_width'            => 1280,
				'radius'               => 20,
				'show_breadcrumb'      => 'yes',
				'show_hero'            => 'yes',
				'show_badge'           => 'yes',
				'badge_text'           => 'حساب کاربری فعال',
				'greeting'             => 'سلام، {name} عزیز خوش آمدید ',
				'subtitle'             => 'از این پنل می‌توانید سفارش‌ها، آدرس‌ها و اطلاعات حساب خود را مدیریت کنید.',
				'show_catalog_button'  => 'yes',
				'catalog_label'        => 'مشاهده کاتالوگ',
				'catalog_url'          => '',
				'show_profile'         => 'yes',
				'show_membership'      => 'yes',
				'show_menu'            => 'yes',
				'show_logout'          => 'yes',
				'menu_title'           => 'حساب کاربری من',
				'email_verify'         => 'yes',
			),
			'dashboard' => array(
				'show_stats'          => 'yes',
				'stat_orders'         => 'yes',
				'stat_completed'      => 'yes',
				'stat_wallet'         => 'yes',
				'stat_loyalty'        => 'yes',
				'show_recent_order'   => 'yes',
				'show_shortcuts'      => 'yes',
				'show_address'        => 'yes',
				'shortcut_support'    => 'yes',
				'shortcut_returns'    => 'yes',
				'shortcut_downloads'  => 'yes',
				'shortcut_note'       => 'برای پیگیری سریع‌تر، شماره سفارش را در تیکت پشتیبانی بنویسید.',
			),
			'menu' => array(
				'extra' => array(),
				'items' => array(
					'dashboard'        => array( 'enabled' => 'yes', 'label' => 'پیشخوان', 'order' => 10 ),
					'orders'           => array( 'enabled' => 'yes', 'label' => 'سفارش‌ها', 'order' => 20 ),
					'downloads'        => array( 'enabled' => 'yes', 'label' => 'دانلودها', 'order' => 30 ),
					'edit-address'     => array( 'enabled' => 'yes', 'label' => 'آدرس‌ها', 'order' => 40 ),
					'wishlist'         => array( 'enabled' => 'yes', 'label' => 'علاقه‌مندی‌ها', 'order' => 50 ),
					'wallet'           => array( 'enabled' => 'yes', 'label' => 'کیف پول', 'order' => 60 ),
					'support'          => array( 'enabled' => 'yes', 'label' => 'پشتیبانی', 'order' => 70 ),
					'notifications'    => array( 'enabled' => 'yes', 'label' => 'اعلان‌ها', 'order' => 80 ),
					'payment-methods'  => array( 'enabled' => 'yes', 'label' => 'روش‌های پرداخت', 'order' => 90 ),
					'edit-account'     => array( 'enabled' => 'yes', 'label' => 'اطلاعات حساب', 'order' => 100 ),
					'customer-logout'  => array( 'enabled' => 'yes', 'label' => 'خروج از حساب', 'order' => 1000 ),
				),
			),
			'support' => array(
				'enabled'            => 'yes',
				'departments'        => array( 'پشتیبانی فنی', 'پیگیری سفارش', 'مالی و پرداخت' ),
				'priorities'         => array( 'low' => 'کم', 'normal' => 'معمولی', 'high' => 'فوری' ),
				'priority_default'   => 'normal',
				'max_open'           => 5,
				'max_message'        => 3000,
				'email_notifications'=> 'yes',
				'support_email'      => '',
				/* Closed tickets older than this many days are deleted daily. */
				'retention_days'     => 365,
				/* Secret for the inbound-mail webhook (generated on first save). */
				'inbound_secret'     => '',
			),
			'notifications' => array(
				'enabled'        => 'yes',
				'order_statuses' => array( 'processing', 'completed', 'on-hold', 'cancelled', 'refunded', 'failed' ),
				'welcome'        => 'yes',
				'ticket_reply'   => 'yes',
				'max'            => 50,
			),
			'wallet' => array(
				'enabled'    => 'yes',
				'label'      => 'کیف پول',
				'note'       => 'برای شارژ کیف پول یا پیگیری تراکنش‌ها با پشتیبانی تماس بگیرید.',
				'min_topup'  => 50000,
				'point_value' => 100,
				'sms_url'    => '',
				'sms_secret' => '',
				'topup_mode'    => 'gateway',
				'topup_gateway' => '',
				'card_number'   => '',
				'card_owner'    => '',
				'card_bank'     => '',
			),
			'loyalty' => array(
				'enabled' => 'yes',
				'rate'    => 10000,
				'label'   => 'امتیاز باشگاه',
				'silver'  => 500,
				'gold'    => 2000,
			),
			'auth' => array(
				'login_title'       => 'ورود به حساب کاربری',
				'login_subtitle'    => 'برای مدیریت سفارش‌ها و اطلاعات حساب وارد شوید.',
				'register_title'    => 'ساخت حساب جدید',
				'register_subtitle' => 'در چند ثانیه عضو شوید و خریدهایتان را پیگیری کنید.',
				'brand_title'       => 'خرید آسان، پیگیری شفاف',
				'brand_subtitle'    => 'همه‌چیز دربارهٔ سفارش‌ها، آدرس‌ها و پشتیبانی در یک پنل.',
				'show_benefits'     => 'yes',
				'benefits'          => array( 'پیگیری لحظه‌ای سفارش‌ها', 'مدیریت آسان آدرس‌ها', 'پشتیبانی مستقیم و سریع' ),
				'show_register'     => 'yes',
				'otp_enabled'       => 'no',
				'otp_login'         => 'yes',
				'otp_register'      => 'yes',
				'otp_2fa'           => 'yes',
				'otp_sms'           => 'no',
				'otp_email'         => 'yes',
				'otp_telegram'      => 'no',
				'otp_whatsapp'      => 'no',
				'otp_default_channel' => 'email',
				'email_from_address' => '',
				'email_from_name'   => '',
				'otp_ttl'           => 300,
				'otp_resend'        => 60,
				'otp_max_attempts'  => 5,
				'otp_message'       => "کد ورود شما به {site}: {code}\nاین کد را در اختیار دیگران قرار ندهید.",
				'telegram_token'    => '',
				'telegram_bot'      => '',
				'telegram_secret'   => '',
				'telegram_polling'  => 'no',
				'whatsapp_provider' => 'cloud',
				'whatsapp_token'    => '',
				'whatsapp_phone_id' => '',
				'whatsapp_bridge_url' => '',
				'whatsapp_bridge_token' => '',
				'whatsapp_sender'   => '',
			),
			'design' => array(
				'primary'      => '#0e2a47',
				'primary_dark' => '#071a2f',
				'accent'       => '#f97316',
				'bg'           => '#f3f6fa',
				'card'         => '#ffffff',
				'ink'          => '#0f172a',
				'muted'        => '#5b6b80',
				'line'         => '#e6ebf2',
				'success'      => '#0f9f6e',
				'warning'      => '#b45309',
				'danger'       => '#d92d3a',
				'hero_a'       => '#0b2036',
				'hero_b'       => '#173b5c',
			),
		);
	}

	private function load_settings() {
		$saved = get_option( self::OPTION, array() );
		return self::merge_defaults( is_array( $saved ) ? $saved : array() );
	}

	public static function merge_defaults( $saved ) {
		$defaults = self::defaults();
		$saved    = is_array( $saved ) ? $saved : array();

		foreach ( $defaults as $group => $values ) {
			if ( 'version' === $group ) {
				continue;
			}
			if ( ! isset( $saved[ $group ] ) || ! is_array( $saved[ $group ] ) ) {
				continue;
			}
			if ( 'menu' === $group ) {
				$items = isset( $saved[ $group ]['items'] ) && is_array( $saved[ $group ]['items'] ) ? $saved[ $group ]['items'] : array();
				foreach ( $defaults[ $group ]['items'] as $key => $item ) {
					if ( isset( $items[ $key ] ) && is_array( $items[ $key ] ) ) {
						$defaults[ $group ]['items'][ $key ] = array_merge( $item, $items[ $key ] );
					}
				}
				$defaults[ $group ]['extra'] = isset( $saved[ $group ]['extra'] ) && is_array( $saved[ $group ]['extra'] ) ? $saved[ $group ]['extra'] : array();
				continue;
			}
			$defaults[ $group ] = array_merge( $values, $saved[ $group ] );
		}

		return $defaults;
	}

	public function settings( $refresh = false ) {
		if ( $refresh ) {
			$this->settings = $this->load_settings();
		}
		return $this->settings;
	}

	public function setting( $path, $fallback = null ) {
		$value = $this->settings;
		foreach ( explode( '.', (string) $path ) as $part ) {
			if ( ! is_array( $value ) || ! array_key_exists( $part, $value ) ) {
				return $fallback;
			}
			$value = $value[ $part ];
		}
		return $value;
	}

	public function on_settings_updated() {
		$this->settings( true );
		delete_option( 'hsw_account_rewrite_hash' );
	}

	/**
	 * Shared secret for the inbound-email webhook, created on first use.
	 *
	 * The sanitiser only mints one when the settings form is saved, which left
	 * existing installs with an empty secret and a "save this page once" notice.
	 * Generating it on first read means the address works immediately.
	 *
	 * @return string
	 */
	public function inbound_secret() {
		$secret = (string) $this->setting( 'support.inbound_secret', '' );
		if ( '' !== $secret ) {
			return $secret;
		}

		$all = $this->settings();
		if ( ! isset( $all['support'] ) || ! is_array( $all['support'] ) ) {
			return '';
		}

		$secret = wp_generate_password( 32, false, false );
		$all['support']['inbound_secret'] = $secret;
		update_option( self::OPTION, $all, false );
		$this->settings( true );

		return $secret;
	}

	public function sanitize_settings( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$out      = self::merge_defaults( $this->settings( true ) );

		if ( isset( $input['general'] ) && is_array( $input['general'] ) ) {
			$g = $input['general'];
			$out['general']['enabled']       = $this->yes_no( $g['enabled'] ?? $out['general']['enabled'] );
			$out['general']['side_position'] = ( isset( $g['side_position'] ) && 'left' === $g['side_position'] ) ? 'left' : 'right';
			$out['general']['side_width']    = $this->clean_int( $g['side_width'] ?? $out['general']['side_width'], 240, 420, 320 );
			$out['general']['gap']           = $this->clean_int( $g['gap'] ?? $out['general']['gap'], 0, 64, 24 );
			$out['general']['max_width']     = $this->clean_int( $g['max_width'] ?? $out['general']['max_width'], 960, 1600, 1280 );
			$out['general']['radius']        = $this->clean_int( $g['radius'] ?? $out['general']['radius'], 0, 40, 20 );
			foreach ( array( 'show_breadcrumb', 'show_hero', 'show_badge', 'show_catalog_button', 'show_profile', 'show_membership', 'show_menu', 'show_logout' ) as $key ) {
				$out['general'][ $key ] = $this->yes_no( $g[ $key ] ?? $out['general'][ $key ] );
			}
			$out['general']['badge_text']    = $this->clean_text( $g['badge_text'] ?? '', $out['general']['badge_text'] );
			$out['general']['greeting']      = $this->clean_text( $g['greeting'] ?? '', $out['general']['greeting'] );
			$out['general']['subtitle']      = $this->clean_textarea( $g['subtitle'] ?? '', $out['general']['subtitle'] );
			$out['general']['catalog_label'] = $this->clean_text( $g['catalog_label'] ?? '', $out['general']['catalog_label'] );
			$out['general']['catalog_url']   = $this->clean_url( $g['catalog_url'] ?? '', $out['general']['catalog_url'] );
			$out['general']['menu_title']    = $this->clean_text( $g['menu_title'] ?? '', $out['general']['menu_title'] );
		}

		if ( isset( $input['dashboard'] ) && is_array( $input['dashboard'] ) ) {
			$d = $input['dashboard'];
			foreach ( array( 'show_stats', 'stat_orders', 'stat_completed', 'stat_wallet', 'stat_loyalty', 'show_recent_order', 'show_shortcuts', 'show_address', 'shortcut_support', 'shortcut_returns', 'shortcut_downloads' ) as $key ) {
				$out['dashboard'][ $key ] = $this->yes_no( $d[ $key ] ?? $out['dashboard'][ $key ] );
			}
			$out['dashboard']['shortcut_note'] = $this->clean_textarea( $d['shortcut_note'] ?? '', $out['dashboard']['shortcut_note'] );
		}

		if ( isset( $input['menu']['items'] ) && is_array( $input['menu']['items'] ) ) {
			$m = $input['menu']['items'];
			foreach ( $out['menu']['items'] as $key => $item ) {
				if ( ! isset( $m[ $key ] ) || ! is_array( $m[ $key ] ) ) {
					continue;
				}
				$row = $m[ $key ];
				$out['menu']['items'][ $key ]['enabled'] = $this->yes_no( $row['enabled'] ?? 'no' );
				$out['menu']['items'][ $key ]['label']   = $this->clean_text( $row['label'] ?? '', $item['label'] );
				$out['menu']['items'][ $key ]['order']   = $this->clean_int( $row['order'] ?? $item['order'], 0, 1000, $item['order'] );
			}
		}

		if ( isset( $input['menu']['extra'] ) ) {
			$out['menu']['extra'] = $this->parse_extra_menu( $input['menu']['extra'] );
		}

		if ( isset( $input['support'] ) && is_array( $input['support'] ) ) {
			$s = $input['support'];
			$out['support']['enabled']             = $this->yes_no( $s['enabled'] ?? $out['support']['enabled'] );
			$out['support']['departments']         = $this->clean_lines( $s['departments'] ?? '', $out['support']['departments'] );
			$out['support']['priority_default']    = isset( $s['priority_default'], $out['support']['priorities'][ $s['priority_default'] ] ) ? $s['priority_default'] : $out['support']['priority_default'];
			$out['support']['max_open']            = $this->clean_int( $s['max_open'] ?? $out['support']['max_open'], 1, 20, 5 );
			$out['support']['max_message']         = $this->clean_int( $s['max_message'] ?? $out['support']['max_message'], 200, 10000, 3000 );
			$out['support']['email_notifications'] = $this->yes_no( $s['email_notifications'] ?? $out['support']['email_notifications'] );
			$out['support']['support_email']       = sanitize_email( $s['support_email'] ?? '' );
			$out['support']['retention_days']      = $this->clean_int( $s['retention_days'] ?? $out['support']['retention_days'], 0, 3650, 365 );
			$out['support']['inbound_secret']      = sanitize_text_field( wp_unslash( (string) ( $s['inbound_secret'] ?? $out['support']['inbound_secret'] ) ) );
			if ( '' === $out['support']['inbound_secret'] ) {
				$out['support']['inbound_secret'] = wp_generate_password( 32, false, false );
			}
		}

		if ( isset( $input['notifications'] ) && is_array( $input['notifications'] ) ) {
			$n = $input['notifications'];
			$out['notifications']['enabled']      = $this->yes_no( $n['enabled'] ?? $out['notifications']['enabled'] );
			$out['notifications']['welcome']      = $this->yes_no( $n['welcome'] ?? $out['notifications']['welcome'] );
			$out['notifications']['ticket_reply'] = $this->yes_no( $n['ticket_reply'] ?? $out['notifications']['ticket_reply'] );
			$out['notifications']['max']          = $this->clean_int( $n['max'] ?? $out['notifications']['max'], 10, 200, 50 );
			$statuses = isset( $n['order_statuses'] ) && is_array( $n['order_statuses'] ) ? $n['order_statuses'] : array();
			$known    = function_exists( 'wc_get_order_statuses' ) ? array_keys( wc_get_order_statuses() ) : array();
			$known    = array_map(
				function ( $status ) {
					return 0 === strpos( $status, 'wc-' ) ? substr( $status, 3 ) : $status;
				},
				$known
			);
			$known    = array_values( array_unique( array_merge( array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' ), $known ) ) );
			$out['notifications']['order_statuses'] = array_values( array_intersect( $known, array_map( 'sanitize_key', $statuses ) ) );
		}

		if ( isset( $input['wallet'] ) && is_array( $input['wallet'] ) ) {
			$w = $input['wallet'];
			$out['wallet']['enabled'] = $this->yes_no( $w['enabled'] ?? $out['wallet']['enabled'] );
			$out['wallet']['label']   = $this->clean_text( $w['label'] ?? '', $out['wallet']['label'] );
			$out['wallet']['note']    = $this->clean_textarea( $w['note'] ?? '', $out['wallet']['note'] );
			$out['wallet']['min_topup']   = max( 0, absint( $w['min_topup'] ?? $out['wallet']['min_topup'] ) );
			$out['wallet']['point_value'] = max( 1, absint( $w['point_value'] ?? $out['wallet']['point_value'] ) );
			$out['wallet']['sms_url']     = esc_url_raw( (string) ( $w['sms_url'] ?? $out['wallet']['sms_url'] ) );
			$out['wallet']['sms_secret']  = sanitize_text_field( (string) ( $w['sms_secret'] ?? $out['wallet']['sms_secret'] ) );
			$out['wallet']['topup_mode']    = ( 'card' === ( $w['topup_mode'] ?? '' ) ) ? 'card' : 'gateway';
			$out['wallet']['topup_gateway'] = sanitize_text_field( (string) ( $w['topup_gateway'] ?? '' ) );
			$out['wallet']['card_number']   = sanitize_text_field( (string) ( $w['card_number'] ?? '' ) );
			$out['wallet']['card_owner']    = sanitize_text_field( (string) ( $w['card_owner'] ?? '' ) );
			$out['wallet']['card_bank']     = sanitize_text_field( (string) ( $w['card_bank'] ?? '' ) );
		}

		if ( isset( $input['loyalty'] ) && is_array( $input['loyalty'] ) ) {
			$l = $input['loyalty'];
			$out['loyalty']['enabled'] = $this->yes_no( $l['enabled'] ?? $out['loyalty']['enabled'] );
			$out['loyalty']['rate']    = $this->clean_int( $l['rate'] ?? $out['loyalty']['rate'], 1, 10000000, 10000 );
			$out['loyalty']['label']   = $this->clean_text( $l['label'] ?? '', $out['loyalty']['label'] );
			$out['loyalty']['silver']  = $this->clean_int( $l['silver'] ?? $out['loyalty']['silver'], 1, 10000000, 500 );
			$out['loyalty']['gold']    = $this->clean_int( $l['gold'] ?? $out['loyalty']['gold'], 1, 10000000, 2000 );
			if ( $out['loyalty']['gold'] <= $out['loyalty']['silver'] ) {
				$out['loyalty']['gold'] = $out['loyalty']['silver'] + 1;
			}
		}

		if ( isset( $input['auth'] ) && is_array( $input['auth'] ) ) {
			$a = $input['auth'];
			foreach ( array( 'login_title', 'login_subtitle', 'register_title', 'register_subtitle', 'brand_title', 'brand_subtitle' ) as $key ) {
				$out['auth'][ $key ] = $this->clean_text( $a[ $key ] ?? '', $out['auth'][ $key ] );
			}
			$out['auth']['show_benefits'] = $this->yes_no( $a['show_benefits'] ?? $out['auth']['show_benefits'] );
			$out['auth']['benefits']      = $this->clean_lines( $a['benefits'] ?? '', $out['auth']['benefits'] );
			$out['auth']['show_register'] = $this->yes_no( $a['show_register'] ?? $out['auth']['show_register'] );

			foreach ( array( 'otp_enabled', 'otp_login', 'otp_register', 'otp_2fa', 'otp_sms', 'otp_email', 'otp_telegram', 'otp_whatsapp' ) as $key ) {
				$out['auth'][ $key ] = $this->yes_no( $a[ $key ] ?? $out['auth'][ $key ] );
			}
			$out['auth']['otp_default_channel'] = in_array( ( $a['otp_default_channel'] ?? '' ), array( 'sms', 'email', 'telegram', 'whatsapp' ), true ) ? $a['otp_default_channel'] : $out['auth']['otp_default_channel'];
			$out['auth']['otp_ttl']             = $this->clean_int( $a['otp_ttl'] ?? $out['auth']['otp_ttl'], 60, 900, 300 );
			$out['auth']['otp_resend']          = $this->clean_int( $a['otp_resend'] ?? $out['auth']['otp_resend'], 20, 900, 60 );
			$out['auth']['otp_max_attempts']    = $this->clean_int( $a['otp_max_attempts'] ?? $out['auth']['otp_max_attempts'], 1, 20, 5 );
			$out['auth']['otp_message']         = $this->clean_textarea( $a['otp_message'] ?? '', $out['auth']['otp_message'] );
			$out['auth']['email_from_address']  = sanitize_email( wp_unslash( (string) ( $a['email_from_address'] ?? '' ) ) );
			if ( '' === $out['auth']['email_from_address'] ) {
				$out['auth']['email_from_address'] = (string) get_option( 'admin_email' );
			}
			$out['auth']['email_from_name']     = sanitize_text_field( wp_unslash( (string) ( $a['email_from_name'] ?? '' ) ) );
			if ( '' === $out['auth']['email_from_name'] ) {
				$out['auth']['email_from_name'] = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			}
			$out['auth']['telegram_token']      = sanitize_text_field( wp_unslash( (string) ( $a['telegram_token'] ?? '' ) ) );
			$out['auth']['telegram_bot']        = ltrim( preg_replace( '/[^a-zA-Z0-9_]/', '', sanitize_text_field( wp_unslash( (string) ( $a['telegram_bot'] ?? '' ) ) ) ), '@' );
			$out['auth']['telegram_secret']     = sanitize_text_field( wp_unslash( (string) ( $a['telegram_secret'] ?? '' ) ) );
			if ( '' === $out['auth']['telegram_secret'] ) {
				$out['auth']['telegram_secret'] = wp_generate_password( 40, false, false );
			}
			$out['auth']['telegram_polling']    = $this->yes_no( $a['telegram_polling'] ?? $out['auth']['telegram_polling'] );
			$out['auth']['whatsapp_provider'] = in_array( ( $a['whatsapp_provider'] ?? '' ), array( 'cloud', 'bridge' ), true ) ? $a['whatsapp_provider'] : 'cloud';
			$out['auth']['whatsapp_token']    = sanitize_text_field( wp_unslash( (string) ( $a['whatsapp_token'] ?? '' ) ) );
			$out['auth']['whatsapp_phone_id'] = sanitize_text_field( wp_unslash( (string) ( $a['whatsapp_phone_id'] ?? '' ) ) );
			$out['auth']['whatsapp_bridge_url']   = esc_url_raw( wp_unslash( (string) ( $a['whatsapp_bridge_url'] ?? '' ) ) );
			$out['auth']['whatsapp_bridge_token'] = sanitize_text_field( wp_unslash( (string) ( $a['whatsapp_bridge_token'] ?? '' ) ) );
			$out['auth']['whatsapp_sender']       = sanitize_text_field( wp_unslash( (string) ( $a['whatsapp_sender'] ?? '' ) ) );
		}

		if ( isset( $input['design'] ) && is_array( $input['design'] ) ) {
			$design = $input['design'];
			foreach ( $defaults['design'] as $key => $fallback ) {
				$out['design'][ $key ] = $this->clean_color( $design[ $key ] ?? '', $out['design'][ $key ] );
			}
		}

		$out['version'] = defined( 'HSW_VERSION' ) ? HSW_VERSION : self::VERSION;
		return $out;
	}

	/**
	 * Parse the simple one-line-per-item custom menu field.
	 * Format: label | url | icon | order | new
	 */
	private function parse_extra_menu( $value ) {
		$lines = is_array( $value ) ? $value : preg_split( '/\r\n|\r|\n/', (string) $value );
		$out   = array();
		$index = 0;
		$icons = array( 'grid', 'receipt', 'download', 'location', 'heart', 'wallet', 'support', 'bell', 'credit-card', 'user', 'external', 'play', 'book', 'star', 'video', 'course' );

		foreach ( (array) $lines as $line ) {
			if ( is_array( $line ) ) {
				$parts = $line;
			} else {
				$line = trim( (string) $line );
				if ( '' === $line ) {
					continue;
				}
				$parts = array_map( 'trim', explode( '|', $line ) );
			}
			$label = isset( $parts[0] ) ? sanitize_text_field( $parts[0] ) : '';
			$url   = isset( $parts[1] ) ? esc_url_raw( $parts[1] ) : '';
			if ( ! $url && isset( $parts[1] ) ) {
				$raw = trim( (string) $parts[1] );
				if ( $raw && '/' === $raw[0] ) {
					$url = esc_url_raw( home_url( $raw ) );
				} elseif ( $raw && ! preg_match( '#^[a-z][a-z0-9+.-]*:#i', $raw ) ) {
					$url = esc_url_raw( 'https://' . $raw );
				}
			}
			if ( ! $url ) {
				continue;
			}
			$icon = isset( $parts[2] ) ? sanitize_key( $parts[2] ) : 'external';
			$icon = in_array( $icon, $icons, true ) ? $icon : 'external';
			$order = isset( $parts[3] ) ? $this->clean_int( $parts[3], 0, 1000, 500 + $index ) : 500 + $index;
			$new   = isset( $parts[4] ) && in_array( strtolower( (string) $parts[4] ), array( 'new', 'blank', '_blank', '1', 'yes' ), true );
			$out[] = array(
				'key'    => 'custom_' . ( $index + 1 ),
				'label'  => $label ? $label : wp_parse_url( $url, PHP_URL_HOST ),
				'url'    => $url,
				'icon'   => $icon,
				'order'  => $order,
				'new'    => $new,
			);
			$index++;
		}
		return $out;
	}

	private function yes_no( $value ) {
		return ( 'yes' === $value || '1' === $value || 1 === $value || true === $value || 'on' === $value ) ? 'yes' : 'no';
	}

	private function clean_text( $value, $fallback ) {
		$value = sanitize_text_field( wp_unslash( (string) $value ) );
		return '' !== $value ? $value : $fallback;
	}

	private function clean_textarea( $value, $fallback ) {
		$value = sanitize_textarea_field( wp_unslash( (string) $value ) );
		return '' !== $value ? $value : $fallback;
	}

	private function clean_url( $value, $fallback ) {
		$value = esc_url_raw( wp_unslash( (string) $value ) );
		return $value ? $value : $fallback;
	}

	private function clean_color( $value, $fallback ) {
		$color = sanitize_hex_color( (string) $value );
		return $color ? $color : $fallback;
	}

	private function clean_int( $value, $min, $max, $fallback ) {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}
		return max( $min, min( $max, absint( $value ) ) );
	}

	private function clean_lines( $value, $fallback ) {
		$lines = is_array( $value ) ? $value : preg_split( '/\r\n|\r|\n/', (string) $value );
		$lines = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $lines ) );
		$lines = array_values( array_filter( array_unique( $lines ) ) );
		return $lines ? array_slice( $lines, 0, 20 ) : $fallback;
	}

	/* ---------------------------------------------------------------------
	 * Endpoints, menu and assets
	 * ------------------------------------------------------------------ */

	public function query_vars( $vars ) {
		$vars = is_array( $vars ) ? $vars : array();
		foreach ( array( 'support', 'notifications', 'wishlist', 'wallet' ) as $key ) {
			if ( empty( $vars[ $key ] ) ) {
				$vars[ $key ] = $key;
			}
		}
		return $vars;
	}

	public function filter_native_menu( $items, $endpoints = array() ) {
		if ( ! is_array( $items ) || 'yes' !== $this->setting( 'general.enabled', 'yes' ) ) {
			return $items;
		}

		$settings = $this->settings();
		$known    = array_keys( $settings['menu']['items'] );
		foreach ( $known as $key ) {
			if ( ! isset( $items[ $key ] ) ) {
				continue;
			}
			if ( 'yes' !== $settings['menu']['items'][ $key ]['enabled'] ) {
				unset( $items[ $key ] );
				continue;
			}
			if ( ! empty( $settings['menu']['items'][ $key ]['label'] ) ) {
				$items[ $key ] = $settings['menu']['items'][ $key ]['label'];
			}
		}
		if ( 'yes' !== $settings['general']['show_logout'] ) {
			unset( $items['customer-logout'] );
		}

		$custom = array(
			'support'       => 'yes' === $settings['support']['enabled'],
			'notifications' => 'yes' === $settings['notifications']['enabled'],
			'wishlist'      => true,
			'wallet'        => 'yes' === $settings['wallet']['enabled'],
		);
		foreach ( $custom as $key => $available ) {
			if ( ! $available || 'yes' !== $settings['menu']['items'][ $key ]['enabled'] ) {
				continue;
			}
			if ( ! isset( $items[ $key ] ) ) {
				$items[ $key ] = $settings['menu']['items'][ $key ]['label'];
			}
		}

		$order = array();
		foreach ( array_keys( $items ) as $key ) {
			$order[ $key ] = $this->menu_order( $key );
		}
		uksort(
			$items,
			function ( $a, $b ) use ( $order ) {
				$oa = isset( $order[ $a ] ) ? $order[ $a ] : 500;
				$ob = isset( $order[ $b ] ) ? $order[ $b ] : 500;
				if ( $oa === $ob ) {
					return strcmp( $a, $b );
				}
				return $oa < $ob ? -1 : 1;
			}
		);

		return $items;
	}

	private function menu_order( $key ) {
		$items = $this->setting( 'menu.items', array() );
		if ( isset( $items[ $key ]['order'] ) ) {
			return (int) $items[ $key ]['order'];
		}
		return 'customer-logout' === $key ? 1000 : 500;
	}

	public function maybe_flush_rewrites() {
		if ( ! function_exists( 'flush_rewrite_rules' ) ) {
			return;
		}
		$hash = md5( ( defined( 'HSW_VERSION' ) ? HSW_VERSION : self::VERSION ) . '|' . implode( ',', array_keys( $this->query_vars( array() ) ) ) );
		if ( get_option( 'hsw_account_rewrite_hash' ) === $hash ) {
			return;
		}
		flush_rewrite_rules( false );
		update_option( 'hsw_account_rewrite_hash', $hash, false );
	}

	public function register_shortcodes() {
		add_shortcode( 'hamishop_account', array( $this, 'shortcode' ) );
		add_shortcode( 'hsw_account', array( $this, 'shortcode' ) );
	}

	public function shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'layout'       => '',
				'show_header'  => '',
				'show_menu'    => '',
				'show_stats'   => '',
				'hide'         => '',
				'class'        => '',
			),
			$atts,
			'hamishop_account'
		);

		$args = array();
		if ( 'left' === $atts['layout'] || 'right' === $atts['layout'] ) {
			$args['general']['side_position'] = $atts['layout'];
		}
		$map = array(
			'show_header' => 'show_hero',
			'show_menu'   => 'show_menu',
		);
		foreach ( $map as $attr => $setting ) {
			if ( 'yes' === $atts[ $attr ] || 'no' === $atts[ $attr ] ) {
				$args['general'][ $setting ] = $atts[ $attr ];
			}
		}
		if ( 'yes' === $atts['show_stats'] || 'no' === $atts['show_stats'] ) {
			$args['dashboard']['show_stats'] = $atts['show_stats'];
		}
		if ( ! empty( $atts['hide'] ) && is_string( $atts['hide'] ) ) {
			$defaults = self::defaults();
			$known    = array_keys( $defaults['menu']['items'] );
			$aliases  = array(
				'address'   => 'edit-address',
				'addresses' => 'edit-address',
				'payments'  => 'payment-methods',
				'payment'   => 'payment-methods',
				'account'   => 'edit-account',
				'profile'   => 'edit-account',
				'logout'    => 'customer-logout',
			);
			foreach ( explode( ',', $atts['hide'] ) as $key ) {
				$key = sanitize_key( trim( $key ) );
				$key = isset( $aliases[ $key ] ) ? $aliases[ $key ] : $key;
				if ( in_array( $key, $known, true ) ) {
					$args['menu']['items'][ $key ]['enabled'] = 'no';
				}
			}
		}
		if ( ! empty( $atts['class'] ) ) {
			$args['extra_class'] = sanitize_html_class( $atts['class'] );
		}
		$args['context'] = 'shortcode';

		return $this->render( $args );
	}

	private function is_account_context() {
		return function_exists( 'is_account_page' ) && is_account_page();
	}

	private function page_has_shortcode() {
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_post( get_queried_object_id() );
		if ( ! $post ) {
			return false;
		}
		return has_shortcode( $post->post_content, 'hamishop_account' ) || has_shortcode( $post->post_content, 'hsw_account' );
	}

	private function page_has_account_widget() {
		if ( ! is_singular() || ! function_exists( 'get_post_meta' ) ) {
			return false;
		}
		$post = get_post( get_queried_object_id() );
		if ( ! $post ) {
			return false;
		}
		$data = (string) get_post_meta( $post->ID, '_elementor_data', true );
		return false !== strpos( $data, 'hsw-account' );
	}

	public function maybe_enqueue_assets() {
		if ( ! wp_style_is( 'hsw-account', 'registered' ) ) {
			return;
		}
		if ( $this->is_account_context() || $this->page_has_shortcode() || $this->page_has_account_widget() ) {
			// Keep the account UI zero-external-request: native emoji detection can
			// pull SVG images from s.w.org when a greeting or note contains emoji.
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			$this->enqueue_assets();
		}
	}

	public function enqueue_assets() {
		if ( wp_style_is( 'hsw-account', 'registered' ) ) {
			wp_enqueue_style( 'hsw-account' );
			if ( ! wp_style_is( 'hsw-account', 'done' ) ) {
				wp_add_inline_style( 'hsw-account', $this->tokens_css() );
			}
		}
		if ( wp_script_is( 'hsw-account', 'registered' ) ) {
			wp_enqueue_script( 'hsw-account' );
		}
	}

	public function tokens_css() {
		$s = $this->settings();
		$d = $s['design'];
		$g = $s['general'];

		/*
		 * ONE palette for the whole plugin. The account panel used to carry its
		 * own 13 colours (HamiShop → پنل حساب کاربری → رنگ و چیدمان), which
		 * drifted away from HamiShop → پالت رنگی. Every colour now comes from
		 * HSW_Palette; `design` is only a legacy fallback for the moment the
		 * palette class is unavailable.
		 */
		$p = class_exists( 'HSW_Palette' ) ? HSW_Palette::get() : array();
		$c = array(
			'primary'      => isset( $p['primary'] ) ? $p['primary'] : $d['primary'],
			'primary_dark' => isset( $p['primary_dark'] ) ? $p['primary_dark'] : $d['primary_dark'],
			'accent'       => isset( $p['accent'] ) ? $p['accent'] : $d['accent'],
			'bg'           => isset( $p['surface_alt'] ) ? $p['surface_alt'] : $d['bg'],
			'card'         => isset( $p['surface'] ) ? $p['surface'] : $d['card'],
			'ink'          => isset( $p['text'] ) ? $p['text'] : $d['ink'],
			'muted'        => isset( $p['muted'] ) ? $p['muted'] : $d['muted'],
			'line'         => isset( $p['border'] ) ? $p['border'] : $d['line'],
			'success'      => isset( $p['success'] ) ? $p['success'] : $d['success'],
			'warning'      => isset( $p['warning'] ) ? $p['warning'] : $d['warning'],
			'danger'       => isset( $p['danger'] ) ? $p['danger'] : $d['danger'],
			'hero_a'       => isset( $p['hero_a'] ) ? $p['hero_a'] : $d['hero_a'],
			'hero_b'       => isset( $p['hero_b'] ) ? $p['hero_b'] : $d['hero_b'],
		);

		$vars = array(
			'--hsw-ac-primary'      => $c['primary'],
			'--hsw-ac-primary-dark' => $c['primary_dark'],
			'--hsw-ac-accent'       => $c['accent'],
			'--hsw-ac-bg'           => $c['bg'],
			'--hsw-ac-card'         => $c['card'],
			'--hsw-ac-ink'          => $c['ink'],
			'--hsw-ac-muted'        => $c['muted'],
			'--hsw-ac-line'         => $c['line'],
			'--hsw-ac-success'      => $c['success'],
			'--hsw-ac-warning'      => $c['warning'],
			'--hsw-ac-danger'       => $c['danger'],
			'--hsw-ac-hero-a'       => $c['hero_a'],
			'--hsw-ac-hero-b'       => $c['hero_b'],
			'--hsw-ac-side'         => $g['side_width'] . 'px',
			'--hsw-ac-gap'          => $g['gap'] . 'px',
			'--hsw-ac-max'          => $g['max_width'] . 'px',
			'--hsw-ac-radius'       => $g['radius'] . 'px',
		);
		$css = '.hsw-ac{';
		foreach ( $vars as $name => $value ) {
			$css .= $name . ':' . esc_attr( $value ) . ';';
		}
		$css .= '}';
		return $css;
	}

	private function maybe_inline_tokens() {
		if ( $this->tokens_printed ) {
			return '';
		}
		$this->tokens_printed = true;
		if ( wp_style_is( 'hsw-account', 'done' ) ) {
			return '';
		}
		return '<style id="hsw-ac-tokens">' . $this->tokens_css() . '</style>';
	}

	private function resolve_args( $args ) {
		$base = $this->settings();
		foreach ( array( 'general', 'dashboard', 'support', 'notifications', 'wallet', 'loyalty', 'auth', 'design', 'menu' ) as $group ) {
			if ( ! isset( $args[ $group ] ) || ! is_array( $args[ $group ] ) ) {
				continue;
			}
			if ( isset( $base[ $group ] ) && is_array( $base[ $group ] ) ) {
				if ( 'menu' === $group && isset( $args[ $group ]['items'] ) && is_array( $args[ $group ]['items'] ) ) {
					foreach ( $args[ $group ]['items'] as $key => $item ) {
						if ( is_array( $item ) && isset( $base[ $group ]['items'][ $key ] ) ) {
							$base[ $group ]['items'][ $key ] = array_replace( $base[ $group ]['items'][ $key ], $item );
						}
					}
					unset( $args[ $group ]['items'] );
				}
				$base[ $group ] = array_replace( $base[ $group ], $args[ $group ] );
			}
		}
		$base['editor']      = ! empty( $args['editor'] );
		$base['preview']     = isset( $args['preview'] ) ? sanitize_key( $args['preview'] ) : 'dashboard';
		$base['context']     = isset( $args['context'] ) ? sanitize_key( $args['context'] ) : 'widget';
		$base['extra_class'] = isset( $args['extra_class'] ) ? sanitize_html_class( $args['extra_class'] ) : '';
		$base['inline_vars'] = isset( $args['inline_vars'] ) && is_array( $args['inline_vars'] ) ? $args['inline_vars'] : array();
		return $base;
	}

	/* ---------------------------------------------------------------------
	 * Entry point and shell
	 * ------------------------------------------------------------------ */

	public function render( $args = array() ) {
		if ( ! function_exists( 'WC' ) || ! function_exists( 'wc_get_page_permalink' ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="hsw-ac hsw-ac--notice"><p>برای استفاده از پنل حساب کاربری، افزونه ووکامرس باید فعال باشد.</p></div>';
			}
			return '';
		}

		$opts = $this->resolve_args( $args );
		if ( 'yes' !== $opts['general']['enabled'] ) {
			return do_shortcode( '[woocommerce_my_account]' );
		}

		$this->enqueue_assets();
		$this->prepare_notices();

		try {
			ob_start();
			if ( $this->is_lost_password() || ( ! empty( $opts['editor'] ) && 'auth' === $opts['preview'] ) ) {
				echo $this->render_auth_view( true, $opts );
			} elseif ( ! is_user_logged_in() ) {
				echo $this->render_auth_view( false, $opts );
			} else {
				echo $this->render_account_view( $opts );
			}
			$html = (string) ob_get_clean();
		} catch ( \Throwable $e ) {
			if ( function_exists( 'hsw_report_fatal' ) ) {
				hsw_report_fatal( $e, 'render:account' );
			}
			$html = '<div class="hsw-ac hsw-ac--notice"><p>نمایش پنل حساب کاربری با خطا مواجه شد.</p></div>';
		}

		return $this->maybe_inline_tokens() . $html;
	}

	private function prepare_notices() {
		if ( $this->notices_prepared || ! function_exists( 'wc_add_notice' ) ) {
			return;
		}
		$this->notices_prepared = true;

		if ( ! is_user_logged_in() ) {
			$message = apply_filters( 'woocommerce_my_account_message', '' );
			if ( ! empty( $message ) ) {
				wc_add_notice( $message );
			}
		}

		if ( isset( $_GET['password-reset'] ) ) {
			wc_add_notice( esc_html__( 'رمز عبور شما با موفقیت تغییر کرد.', 'hamishop-elementor-widgets' ), 'success' );
		}

		if ( is_user_logged_in() && 'customer-logout' === $this->current_endpoint() ) {
			$message = sprintf(
				/* translators: %s: logout URL */
				esc_html__( 'از حساب خود خارج شده‌اید. %s', 'hamishop-elementor-widgets' ),
				'<a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">' . esc_html__( 'ورود دوباره', 'hamishop-elementor-widgets' ) . '</a>'
			);
			wc_add_notice( $message, 'success' );
		}
	}

	public function notices_html() {
		if ( ! function_exists( 'wc_print_notices' ) ) {
			return '';
		}
		ob_start();
		wc_print_notices();
		return trim( (string) ob_get_clean() );
	}

	public function is_lost_password() {
		global $wp;
		return isset( $wp->query_vars['lost-password'] );
	}

	public function current_endpoint() {
		global $wp;

		if ( function_exists( 'WC' ) && WC()->query && method_exists( WC()->query, 'get_current_endpoint' ) ) {
			$endpoint = WC()->query->get_current_endpoint();
			if ( $endpoint ) {
				return $endpoint;
			}
		}

		// WooCommerce only knows its own endpoints. Course builders and other
		// plugins often register a WordPress endpoint without adding it to
		// WC_Query. Treat any account-menu key present in the request as the
		// current endpoint so their native endpoint hook can render.
		if ( function_exists( 'wc_get_account_menu_items' ) ) {
			foreach ( array_keys( (array) wc_get_account_menu_items() ) as $key ) {
				if ( isset( $wp->query_vars[ $key ] ) ) {
					return (string) $key;
				}
			}
		}

		$keys = array( 'support', 'notifications', 'wishlist', 'wallet', 'orders', 'view-order', 'downloads', 'edit-address', 'payment-methods', 'edit-account', 'lost-password' );
		foreach ( $keys as $key ) {
			if ( isset( $wp->query_vars[ $key ] ) ) {
				return $key;
			}
		}
		return 'dashboard';
	}

	private function endpoint_value( $key ) {
		global $wp;
		return isset( $wp->query_vars[ $key ] ) ? $wp->query_vars[ $key ] : '';
	}

	private function render_account_view( $opts ) {
		$ctx     = $this->build_context( $opts );
		$content = $this->render_endpoint_content( $ctx );
		$html    = $this->render_shell( $ctx, $content );

		if ( 'notifications' === $ctx['endpoint'] ) {
			$this->mark_all_notifications_read( $ctx['user_id'] );
		}
		return $html;
	}

	private function build_context( $opts ) {
		$user_id = get_current_user_id();
		$user    = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			$user = wp_get_current_user();
		}

		$endpoint       = $this->current_endpoint();
		$support_value  = (string) $this->endpoint_value( 'support' );
		$is_dashboard   = in_array( $endpoint, array( '', 'dashboard' ), true );
		$menu_key       = $this->menu_key_for_endpoint( $endpoint );
		$notifications  = 'yes' === $opts['notifications']['enabled'] ? $this->notifications( $user_id ) : array();
		$unread         = $this->unread_notifications_count( $user_id );
		$wallet_balance = 'yes' === $opts['wallet']['enabled'] ? $this->wallet_balance( $user_id ) : 0;
		$loyalty        = $this->loyalty_data( $user_id, $opts );

		$ctx = array(
			'opts'                 => $opts,
			'user_id'              => $user_id,
			'user'                 => $user,
			'endpoint'             => $endpoint,
			'menu_key'             => $menu_key,
			'is_dashboard'         => $is_dashboard,
			'support_value'        => $support_value,
			'notifications'        => $notifications,
			'unread_notifications' => $unread,
			'wallet_balance'       => $wallet_balance,
			'loyalty'              => $loyalty,
			'downloads_count'      => $this->downloads_count( $user_id ),
			'open_tickets'         => $this->support ? $this->support->open_ticket_count( $user_id ) : 0,
			'account_url'          => wc_get_page_permalink( 'myaccount' ),
			'shop_url'             => wc_get_page_permalink( 'shop' ),
			'support_url'          => wc_get_account_endpoint_url( 'support' ),
			'notifications_url'    => wc_get_account_endpoint_url( 'notifications' ),
			'view_order_id'        => absint( $this->endpoint_value( 'view-order' ) ),
		);

		$ctx['stats']           = $is_dashboard ? $this->stats( $ctx ) : array();
		$ctx['menu']            = $this->menu_items( $ctx );
		$ctx['current_label']   = $this->endpoint_label( $ctx );
		$ctx['current_subtitle']= $this->endpoint_subtitle( $ctx );
		$ctx['root_class']      = $this->root_class( $ctx );
		$ctx['recent_order']    = $is_dashboard ? $this->recent_order( $user_id ) : null;
		$ctx['address']         = $this->address_data( $user_id );

		return $ctx;
	}

	private function menu_key_for_endpoint( $endpoint ) {
		$map = array(
			'view-order'                => 'orders',
			'order-pay'                 => 'orders',
			'order-received'            => 'orders',
			'add-payment-method'        => 'payment-methods',
			'delete-payment-method'     => 'payment-methods',
			'set-default-payment-method'=> 'payment-methods',
			'lost-password'             => 'dashboard',
		);
		return isset( $map[ $endpoint ] ) ? $map[ $endpoint ] : $endpoint;
	}

	private function endpoint_label( $ctx ) {
		$endpoint = $ctx['endpoint'];
		if ( 'dashboard' === $endpoint ) {
			return $this->setting( 'menu.items.dashboard.label', 'پیشخوان' );
		}

		if ( 'support' === $endpoint ) {
			$value = $ctx['support_value'];
			if ( 'new' === $value ) {
				return 'تیکت جدید';
			}
			if ( absint( $value ) ) {
				return 'تیکت #T-' . HSW_Helpers::fa_num( absint( $value ) );
			}
			return $this->setting( 'menu.items.support.label', 'پشتیبانی' );
		}

		if ( 'view-order' === $endpoint && $ctx['view_order_id'] ) {
			$order = wc_get_order( $ctx['view_order_id'] );
			if ( $order ) {
				return sprintf( 'سفارش #%s', HSW_Helpers::fa_num( $order->get_order_number() ) );
			}
		}

		if ( function_exists( 'WC' ) && WC()->query && method_exists( WC()->query, 'get_endpoint_title' ) ) {
			$title = WC()->query->get_endpoint_title( $endpoint );
			if ( $title ) {
				return $title;
			}
		}

		if ( isset( $ctx['menu'][ $ctx['menu_key'] ]['label'] ) ) {
			return $ctx['menu'][ $ctx['menu_key'] ]['label'];
		}
		return 'حساب کاربری';
	}

	private function endpoint_subtitle( $ctx ) {
		$map = array(
			'dashboard'        => '',
			'orders'           => 'سفارش‌های خود را مشاهده، پیگیری و مدیریت کنید.',
			'view-order'       => 'جزئیات کامل سفارش، اقلام و وضعیت پرداخت.',
			'downloads'        => 'فایل‌های خریداری‌شده همیشه در دسترس شما هستند.',
			'edit-address'     => 'آدرس‌های صورتحساب و تحویل سفارش را مدیریت کنید.',
			'payment-methods'  => 'روش‌های پرداخت ذخیره‌شده و کارت‌های خود را مدیریت کنید.',
			'edit-account'     => 'نام، ایمیل و اطلاعات ورود خود را به‌روز کنید.',
			'support'          => 'تیکت‌های پشتیبانی را ثبت و گفتگوها را پیگیری کنید.',
			'notifications'    => 'آخرین رویدادهای حساب و سفارش‌های شما.',
			'wishlist'         => 'محصولاتی که در این مرورگر ذخیره کرده‌اید.',
			'wallet'           => 'موجودی کیف پول و امتیازهای باشگاه مشتریان.',
			'customer-logout'  => 'خروج امن از حساب کاربری.',
		);
		return isset( $map[ $ctx['endpoint'] ] ) ? $map[ $ctx['endpoint'] ] : 'جزئیات حساب کاربری شما.';
	}

	private function root_class( $ctx ) {
		$classes = array( 'hsw-ac', 'hsw-ac--account', 'hsw-ac--' . sanitize_html_class( $ctx['endpoint'] ) );
		if ( ! empty( $ctx['opts']['extra_class'] ) ) {
			$classes[] = $ctx['opts']['extra_class'];
		}
		return implode( ' ', array_unique( $classes ) );
	}

	private function menu_items( $ctx ) {
		if ( ! function_exists( 'wc_get_account_menu_items' ) ) {
			return array();
		}
		$raw = wc_get_account_menu_items();
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw as $key => $label ) {
			if ( 'customer-logout' === $key && 'yes' !== $ctx['opts']['general']['show_logout'] ) {
				continue;
			}
			if ( isset( $ctx['opts']['menu']['items'][ $key ]['enabled'] ) && 'yes' !== $ctx['opts']['menu']['items'][ $key ]['enabled'] ) {
				continue;
			}
			$badge = '';
			if ( 'orders' === $key && ! empty( $ctx['stats']['orders_current'] ) ) {
				$badge = HSW_Helpers::fa_num( $ctx['stats']['orders_current'] );
			} elseif ( 'notifications' === $key && $ctx['unread_notifications'] ) {
				$badge = HSW_Helpers::fa_num( $ctx['unread_notifications'] );
			} elseif ( 'support' === $key && ! empty( $ctx['open_tickets'] ) ) {
				$badge = HSW_Helpers::fa_num( $ctx['open_tickets'] );
			}

			$out[ $key ] = array(
				'key'        => $key,
				'label'      => $label,
				'url'        => $this->menu_url( $key ),
				'icon'       => $this->menu_icon( $key ),
				'badge'      => $badge,
				'wish_count' => ( 'wishlist' === $key ),
				'active'     => ( $key === $ctx['menu_key'] ),
				'logout'     => ( 'customer-logout' === $key ),
				'order'      => $this->menu_order( $key ),
				'new'        => false,
			);
		}

		// Extra dashboard links (for example SpotPlayer or a custom endpoint
		// exposed by a course-builder plugin). These are deliberately not
		// forced through WooCommerce so external dashboards work too.
		foreach ( (array) $ctx['opts']['menu']['extra'] as $extra ) {
			if ( empty( $extra['url'] ) || empty( $extra['key'] ) ) {
				continue;
			}
			$key = sanitize_key( $extra['key'] );
			if ( isset( $out[ $key ] ) ) {
				continue;
			}
			$out[ $key ] = array(
				'key'        => $key,
				'label'      => isset( $extra['label'] ) ? $extra['label'] : $key,
				'url'        => $extra['url'],
				'icon'       => isset( $extra['icon'] ) ? $extra['icon'] : 'external',
				'badge'      => '',
				'wish_count' => false,
				'active'     => false,
				'logout'     => false,
				'order'      => isset( $extra['order'] ) ? (int) $extra['order'] : 500,
				'new'        => ! empty( $extra['new'] ),
			);
		}

		uasort(
			$out,
			function ( $a, $b ) {
				$oa = isset( $a['order'] ) ? (int) $a['order'] : 500;
				$ob = isset( $b['order'] ) ? (int) $b['order'] : 500;
				if ( $oa === $ob ) {
					return strcmp( $a['key'], $b['key'] );
				}
				return $oa < $ob ? -1 : 1;
			}
		);
		return $out;
	}

	private function menu_url( $key ) {
		if ( 'dashboard' === $key ) {
			return wc_get_page_permalink( 'myaccount' );
		}
		return wc_get_account_endpoint_url( $key );
	}

	private function menu_icon( $key ) {
		$map = array(
			'dashboard'       => 'grid',
			'orders'          => 'receipt',
			'view-order'      => 'receipt',
			'downloads'       => 'download',
			'edit-address'    => 'location',
			'payment-methods' => 'credit-card',
			'edit-account'    => 'user',
			'customer-logout' => 'logout',
			'support'         => 'support',
			'notifications'   => 'bell',
			'wishlist'        => 'heart',
			'wallet'          => 'wallet',
		);
		return isset( $map[ $key ] ) ? $map[ $key ] : 'grid';
	}

	private function render_shell( $ctx, $content ) {
		$opts = $ctx['opts'];
		$side = '';
		if ( 'yes' === $opts['general']['show_profile'] ) {
			$side .= $this->profile_card( $ctx );
		}
		if ( 'yes' === $opts['general']['show_menu'] ) {
			$side .= $this->menu_card( $ctx );
		}

		$main  = $this->notices_html();
		$main .= $this->hero( $ctx );
		$main .= $content;

		$breadcrumb = 'yes' === $opts['general']['show_breadcrumb'] ? $this->breadcrumb( $ctx ) : '';
		$classes    = $ctx['root_class'];
		if ( ! empty( $opts['editor'] ) ) {
			$classes .= ' hsw-ac--editor';
		}

		$style = '';
		foreach ( (array) $opts['inline_vars'] as $name => $value ) {
			if ( ! preg_match( '/^--hsw-ac-[a-z0-9-]+$/', (string) $name ) ) {
				continue;
			}
			$style .= $name . ':' . sanitize_text_field( (string) $value ) . ';';
		}

		return '<div class="' . esc_attr( $classes ) . '" data-side="' . esc_attr( $opts['general']['side_position'] ) . '"' . ( $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . '>'
			. '<div class="hsw-ac__wrap">'
			. $breadcrumb
			. '<div class="hsw-ac__grid">'
			. '<aside id="hsw-ac-side" class="hsw-ac__side" aria-label="' . esc_attr__( 'حساب کاربری من', 'hamishop-elementor-widgets' ) . '">' . $side . '</aside>'
			. '<div class="hsw-ac__main">' . $main . '</div>'
			. '</div></div></div>';
	}

	private function breadcrumb( $ctx ) {
		$home    = home_url( '/' );
		$account = $ctx['account_url'];
		$label   = $ctx['current_label'];
		$chev    = $this->icon( 'chevron-left', 'hsw-ac__crumb-sep' );

		$html  = '<nav class="hsw-ac__crumbs" aria-label="' . esc_attr__( 'مسیر صفحه', 'hamishop-elementor-widgets' ) . '">';
		/* Mobile: this button opens the account drawer; the path itself is hidden. */
		$html .= '<button type="button" class="hsw-ac__menubtn" data-hsw-ac-menu aria-expanded="false" aria-controls="hsw-ac-side" aria-label="' . esc_attr__( 'منوی حساب کاربری', 'hamishop-elementor-widgets' ) . '">' . HSW_Helpers::icon( 'menu', 'hsw-ac__menubtn-ic' ) . '</button>';
		$html .= '<span class="hsw-ac__path">';
		$html .= '<a href="' . esc_url( $home ) . '">' . esc_html__( 'صفحه اصلی', 'hamishop-elementor-widgets' ) . '</a>';
		$html .= $chev . '<a href="' . esc_url( $account ) . '">' . esc_html__( 'حساب کاربری من', 'hamishop-elementor-widgets' ) . '</a>';
		$html .= $chev . '</span>';
		$html .= '<span aria-current="page">' . esc_html( $label ) . '</span>';
		$html .= '</nav>';
		return $html;
	}

	private function hero( $ctx ) {
		$opts = $ctx['opts'];
		if ( 'yes' !== $opts['general']['show_hero'] ) {
			return '';
		}

		$is_dashboard = $ctx['is_dashboard'];
		$title        = $is_dashboard ? str_replace( '{name}', $ctx['user']->display_name, $opts['general']['greeting'] ) : $ctx['current_label'];
		$subtitle     = $is_dashboard ? $opts['general']['subtitle'] : $ctx['current_subtitle'];
		$badge        = 'yes' === $opts['general']['show_badge'] ? $opts['general']['badge_text'] : '';
		$bell         = $this->notifications_bell( $ctx );

		$actions = '';
		if ( $is_dashboard && 'yes' === $opts['general']['show_catalog_button'] ) {
			$url = $opts['general']['catalog_url'] ? $opts['general']['catalog_url'] : $ctx['shop_url'];
			if ( $url ) {
				$actions .= '<a class="hsw-ac__btn hsw-ac__btn--light" href="' . esc_url( $url ) . '">'
					. $this->icon( 'shopping-bag' ) . '<span>' . esc_html( $opts['general']['catalog_label'] ) . '</span></a>';
			}
		} elseif ( ! $is_dashboard ) {
			$actions .= '<a class="hsw-ac__btn hsw-ac__btn--ghost-light" href="' . esc_url( $ctx['account_url'] ) . '">'
				. $this->icon( 'arrow-right' ) . '<span>' . esc_html__( 'بازگشت به پیشخوان', 'hamishop-elementor-widgets' ) . '</span></a>';
		}

		return '<section class="hsw-ac__hero" aria-labelledby="hsw-ac-hero-title">'
			/*
			 * The decorative glow and the ::before ring stick out past the hero
			 * edges, so they live in their own clipped layer. The hero itself
			 * must stay overflow:visible, otherwise it also clips the bell
			 * dropdown that hangs below it.
			 */
			. '<div class="hsw-ac__hero-deco" aria-hidden="true"><div class="hsw-ac__hero-glow"></div></div>'
			. '<div class="hsw-ac__hero-content">'
			. ( $badge ? '<span class="hsw-ac__hero-badge">' . $this->icon( 'check-circle' ) . esc_html( $badge ) . '</span>' : '' )
			. '<h1 id="hsw-ac-hero-title" class="hsw-ac__hero-title">' . esc_html( $title ) . '</h1>'
			. '<p class="hsw-ac__hero-sub">' . esc_html( $subtitle ) . '</p>'
			. '</div>'
			. '<div class="hsw-ac__hero-actions">' . $bell . $actions . '</div>'
			. '</section>';
	}

	private function notifications_bell( $ctx ) {
		if ( 'yes' !== $ctx['opts']['notifications']['enabled'] ) {
			return '';
		}
		$unread = (int) $ctx['unread_notifications'];
		$items  = array_slice( $ctx['notifications'], 0, 5 );
		$html   = '<details class="hsw-ac__bell">';
		$html  .= '<summary aria-label="' . esc_attr__( 'اعلان‌ها', 'hamishop-elementor-widgets' ) . '">'
			. $this->icon( 'bell' )
			. ( $unread ? '<span class="hsw-ac__bell-count">' . esc_html( HSW_Helpers::fa_num( $unread ) ) . '</span>' : '' )
			. '</summary>';
		$html  .= '<div class="hsw-ac__bell-panel">';
		$html  .= '<div class="hsw-ac__bell-head"><strong>' . esc_html__( 'اعلان‌ها', 'hamishop-elementor-widgets' ) . '</strong><a href="' . esc_url( $ctx['notifications_url'] ) . '">' . esc_html__( 'مشاهده همه', 'hamishop-elementor-widgets' ) . '</a></div>';
		if ( $items ) {
			$html .= '<ul class="hsw-ac__bell-list">';
			foreach ( $items as $item ) {
				$url = $item['url'] ? $item['url'] : $ctx['notifications_url'];
				$html .= '<li' . ( $item['read'] ? '' : ' class="is-unread"' ) . '>'
					. '<a href="' . esc_url( $url ) . '">'
					. '<span class="hsw-ac__bell-ic">' . $this->icon( $this->notification_icon( $item['type'] ) ) . '</span>'
					. '<span class="hsw-ac__bell-txt"><strong>' . esc_html( $item['title'] ) . '</strong><small>' . esc_html( $item['text'] ) . '</small></span>'
					. '</a></li>';
			}
			$html .= '</ul>';
		} else {
			$html .= '<p class="hsw-ac__bell-empty">' . esc_html__( 'اعلان جدیدی ندارید.', 'hamishop-elementor-widgets' ) . '</p>';
		}
		$html .= '</div></details>';
		return $html;
	}

	private function profile_card( $ctx ) {
		$user  = $ctx['user'];
		$name  = $user->display_name ? $user->display_name : $user->user_login;
		$phone = get_user_meta( $ctx['user_id'], 'billing_phone', true );
		$email = $user->user_email;
		$edit  = wc_get_account_endpoint_url( 'edit-account' );
		$id    = wp_unique_id( 'hsw-ac-member-' );

		$html  = '<section class="hsw-ac__card hsw-ac__profile" aria-labelledby="hsw-ac-profile-name">';
		$html .= '<div class="hsw-ac__profile-top">';
		$html .= '<div class="hsw-ac__avatar">' . $this->avatar_html( $ctx['user_id'], $name, 96 ) . '</div>';
		$html .= '<div class="hsw-ac__profile-id"><div class="hsw-ac__name-row"><h2 id="hsw-ac-profile-name">' . esc_html( $name ) . '</h2>'
			. '<span class="hsw-ac__verified" title="' . esc_attr__( 'حساب فعال', 'hamishop-elementor-widgets' ) . '">' . $this->icon( 'check-circle' ) . '</span></div>';
		if ( $phone ) {
			$html .= '<a class="hsw-ac__contact" href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . $this->icon( 'phone' ) . '<span dir="ltr">' . esc_html( $phone ) . '</span></a>';
		} elseif ( $email ) {
			$html .= '<a class="hsw-ac__contact" href="mailto:' . esc_attr( $email ) . '">' . $this->icon( 'mail' ) . '<span>' . esc_html( $email ) . '</span></a>';
		}
		$html .= '</div>';
		$html .= '<a class="hsw-ac__iconbtn" href="' . esc_url( $edit ) . '" aria-label="' . esc_attr__( 'ویرایش اطلاعات حساب', 'hamishop-elementor-widgets' ) . '">' . $this->icon( 'settings' ) . '</a>';
		$html .= '</div>';

		if ( 'yes' === $ctx['opts']['general']['show_membership'] && 'yes' === $ctx['opts']['loyalty']['enabled'] && ! empty( $ctx['loyalty'] ) ) {
			$loyalty = $ctx['loyalty'];
			$html   .= '<div class="hsw-ac__member" data-hsw-member>';
			$html   .= '<div class="hsw-ac__member-tabs" role="tablist" aria-label="' . esc_attr__( 'باشگاه مشتریان', 'hamishop-elementor-widgets' ) . '">';
			$html   .= '<button type="button" class="is-active" role="tab" aria-selected="true" aria-controls="' . esc_attr( $id . '-club' ) . '" data-hsw-member-tab="club">' . esc_html__( 'باشگاه مشتریان', 'hamishop-elementor-widgets' ) . '</button>';
			$html   .= '<button type="button" role="tab" aria-selected="false" aria-controls="' . esc_attr( $id . '-loyal' ) . '" data-hsw-member-tab="loyal">' . esc_html__( 'کاربر وفادار', 'hamishop-elementor-widgets' ) . '</button>';
			$html   .= '</div>';
			$html   .= '<div class="hsw-ac__member-panel" id="' . esc_attr( $id . '-club' ) . '" role="tabpanel" data-hsw-member-panel="club">'
				. '<div class="hsw-ac__member-points"><strong>' . esc_html( HSW_Helpers::fa_num( $loyalty['points'] ) ) . '</strong><span>' . esc_html( $ctx['opts']['loyalty']['label'] ) . '</span></div>'
				. '<div class="hsw-ac__member-tier"><span>' . esc_html( $loyalty['tier'] ) . '</span><small>' . esc_html( $loyalty['progress_text'] ) . '</small></div>'
				. '<div class="hsw-ac__progress" aria-hidden="true"><span style="width:' . esc_attr( $loyalty['progress'] ) . '%"></span></div>'
				. '<p class="hsw-ac__member-note">' . esc_html( sprintf( 'هر %s تومان خرید معتبر = ۱ امتیاز. امتیازها سطح شما را تعیین می‌کنند و فعلاً به کیف پول تبدیل نمی‌شوند.', HSW_Helpers::fa_num( (int) $ctx['opts']['loyalty']['rate'] ) ) ) . '</p>'
				. '</div>';
			$html   .= '<div class="hsw-ac__member-panel" id="' . esc_attr( $id . '-loyal' ) . '" role="tabpanel" data-hsw-member-panel="loyal" hidden>'
				. '<div class="hsw-ac__member-points"><strong>' . esc_html( HSW_Helpers::fa_num( $loyalty['completed'] ) ) . '</strong><span>' . esc_html__( 'سفارش تحویل‌شده', 'hamishop-elementor-widgets' ) . '</span></div>'
				. '<p class="hsw-ac__member-note">' . esc_html( sprintf( 'عضو از %s', $loyalty['since'] ) ) . '</p>'
				. '</div>';
			$html .= '</div>';
		}

		$html .= '</section>';
		return $html;
	}

	private function avatar_html( $user_id, $name, $size = 96 ) {
		$url = get_avatar_url( $user_id, array( 'size' => $size * 2 ) );
		if ( $url && $this->is_local_url( $url ) ) {
			return '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $name ) . '" width="' . (int) $size . '" height="' . (int) $size . '" loading="lazy" decoding="async">';
		}
		$initial = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1, 'UTF-8' ) : substr( $name, 0, 1 );
		return '<span class="hsw-ac__avatar-letter" aria-hidden="true">' . esc_html( $initial ) . '</span>';
	}

	private function is_local_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return true;
		}
		$home = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		return $host === $home;
	}

	private function menu_card( $ctx ) {
		if ( empty( $ctx['menu'] ) ) {
			return '';
		}
		$title = $ctx['opts']['general']['menu_title'];
		$html  = '<nav class="hsw-ac__card hsw-ac__menu" aria-label="' . esc_attr__( 'منوی حساب کاربری', 'hamishop-elementor-widgets' ) . '">';
		$html .= '<div class="hsw-ac__menu-head">' . $this->icon( 'grid' ) . '<h2>' . esc_html( $title ) . '</h2></div>';
		$html .= '<ul class="hsw-ac__menu-list">';
		foreach ( $ctx['menu'] as $item ) {
			$classes = 'hsw-ac__menu-item';
			if ( $item['active'] ) {
				$classes .= ' is-active';
			}
			if ( $item['logout'] ) {
				$classes .= ' is-logout';
			}
			$badge = '';
			if ( $item['wish_count'] ) {
				$badge = '<span class="hsw-ac__badge" data-hsw-wish-count hidden></span>';
			} elseif ( '' !== (string) $item['badge'] ) {
				$badge = '<span class="hsw-ac__badge">' . esc_html( $item['badge'] ) . '</span>';
			}
			$html .= '<li><a class="' . esc_attr( $classes ) . '" href="' . esc_url( $item['url'] ) . '"' . ( $item['active'] ? ' aria-current="page"' : '' ) . ( ! empty( $item['new'] ) ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>'
				. '<span class="hsw-ac__menu-ic">' . $this->icon( $item['icon'] ) . '</span>'
				. '<span class="hsw-ac__menu-label">' . esc_html( $item['label'] ) . '</span>'
				. $badge
				. '</a></li>';
		}
		$html .= '</ul></nav>';
		return $html;
	}

	private function stats( $ctx ) {
		return array(
			'orders_current' => $this->count_customer_orders( $ctx['user_id'], array( 'pending', 'processing', 'on-hold' ) ),
			'completed'      => $this->count_customer_orders( $ctx['user_id'], array( 'completed' ) ),
			'wallet'         => $ctx['wallet_balance'],
			'loyalty'        => isset( $ctx['loyalty']['points'] ) ? (int) $ctx['loyalty']['points'] : 0,
		);
	}

	private function count_customer_orders( $user_id, $statuses ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}
		$result = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'status'      => $statuses,
				'limit'       => 1,
				'paginate'    => true,
				'return'      => 'ids',
			)
		);
		if ( is_object( $result ) && isset( $result->total ) ) {
			return (int) $result->total;
		}
		return is_array( $result ) ? count( $result ) : 0;
	}

	private function downloads_count( $user_id ) {
		if ( ! function_exists( 'wc_get_customer_available_downloads' ) ) {
			return 0;
		}
		$downloads = wc_get_customer_available_downloads( $user_id );
		return is_array( $downloads ) ? count( $downloads ) : 0;
	}

	private function recent_order( $user_id ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return null;
		}
		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => 1,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);
		return ! empty( $orders ) && $orders[0] instanceof WC_Order ? $orders[0] : null;
	}

	private function dashboard_cards( $ctx ) {
		$opts  = $ctx['opts'];
		$html  = '';
		$stats = 'yes' === $opts['dashboard']['show_stats'] ? $this->render_stats( $ctx ) : '';
		$html .= $stats;

		if ( 'yes' === $opts['dashboard']['show_recent_order'] ) {
			$html .= $this->recent_order_card( $ctx );
		}

		$shortcuts = 'yes' === $opts['dashboard']['show_shortcuts'] ? $this->shortcuts_card( $ctx ) : '';
		$address   = 'yes' === $opts['dashboard']['show_address'] ? $this->address_card( $ctx ) : '';
		if ( $shortcuts || $address ) {
			$html .= '<div class="hsw-ac__bottom">' . $shortcuts . $address . '</div>';
		}

		ob_start();
		do_action( 'woocommerce_account_dashboard' );
		do_action( 'woocommerce_before_my_account' );
		do_action( 'woocommerce_after_my_account' );
		$extra = trim( (string) ob_get_clean() );
		if ( $extra ) {
			$html .= '<section class="hsw-ac__card hsw-ac__extra">' . $extra . '</section>';
		}
		return $html;
	}

	private function render_stats( $ctx ) {
		$opts  = $ctx['opts'];
		$stats = $ctx['stats'];
		$cards = array();

		if ( 'yes' === $opts['dashboard']['stat_orders'] && 'yes' === $opts['menu']['items']['orders']['enabled'] ) {
			$cards[] = $this->stat_card(
				array(
					'icon'  => 'package',
					'tone'  => 'warning',
					'label' => 'سفارش‌های جاری',
					'value' => HSW_Helpers::fa_num( $stats['orders_current'] ),
					'sub'   => 'سفارش در جریان',
					'url'   => wc_get_account_endpoint_url( 'orders' ),
				)
			);
		}
		if ( 'yes' === $opts['dashboard']['stat_completed'] ) {
			$cards[] = $this->stat_card(
				array(
					'icon'  => 'check-circle',
					'tone'  => 'success',
					'label' => 'تحویل‌شده',
					'value' => HSW_Helpers::fa_num( $stats['completed'] ),
					'sub'   => 'سفارش موفق',
					'url'   => wc_get_account_endpoint_url( 'orders' ),
				)
			);
		}
		if ( 'yes' === $opts['dashboard']['stat_wallet'] && 'yes' === $opts['wallet']['enabled'] ) {
			$cards[] = $this->stat_card(
				array(
					'icon'  => 'wallet',
					'tone'  => 'info',
					'label' => 'موجودی کیف پول',
					'value' => wp_kses_post( $this->price_html( $stats['wallet'] ) ),
					'sub'   => 'موجودی قابل استفاده',
					'url'   => wc_get_account_endpoint_url( 'wallet' ),
				)
			);
		}
		if ( 'yes' === $opts['dashboard']['stat_loyalty'] && 'yes' === $opts['loyalty']['enabled'] ) {
			$cards[] = $this->stat_card(
				array(
					'icon'  => 'gift',
					'tone'  => 'accent',
					'label' => $opts['loyalty']['label'],
					'value' => HSW_Helpers::fa_num( $stats['loyalty'] ),
					'sub'   => 'امتیاز وفاداری',
					'url'   => '',
				)
			);
		}

		if ( empty( $cards ) ) {
			return '';
		}
		return '<section class="hsw-ac__stats" aria-label="' . esc_attr__( 'خلاصه حساب', 'hamishop-elementor-widgets' ) . '">' . implode( '', $cards ) . '</section>';
	}

	private function stat_card( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'icon'  => 'grid',
				'tone'  => 'info',
				'label' => '',
				'value' => '',
				'sub'   => '',
				'url'   => '',
			)
		);
		$tag  = $args['url'] ? 'a' : 'div';
		$attr = $args['url'] ? ' href="' . esc_url( $args['url'] ) . '"' : '';
		$html = '<' . $tag . ' class="hsw-ac__stat hsw-ac__stat--' . esc_attr( $args['tone'] ) . '"' . $attr . '>';
		$html .= '<span class="hsw-ac__stat-icon">' . $this->icon( $args['icon'] ) . '</span>';
		$html .= '<span class="hsw-ac__stat-body"><span class="hsw-ac__stat-label">' . esc_html( $args['label'] ) . '</span>'
			. '<strong class="hsw-ac__stat-value">' . $args['value'] . '</strong>'
			. '<small class="hsw-ac__stat-sub">' . esc_html( $args['sub'] ) . '</small></span>';
		$html .= '</' . $tag . '>';
		return $html;
	}

	private function recent_order_card( $ctx ) {
		$order = $ctx['recent_order'];
		if ( ! $order ) {
			return '<section class="hsw-ac__card hsw-ac__empty hsw-ac__recent-empty">'
				. '<span class="hsw-ac__empty-ic">' . $this->icon( 'package' ) . '</span>'
				. '<h2>' . esc_html__( 'هنوز سفارشی ثبت نشده است', 'hamishop-elementor-widgets' ) . '</h2>'
				. '<p>' . esc_html__( 'اولین سفارش خود را ثبت کنید تا وضعیت آن را از همین‌جا پیگیری کنید.', 'hamishop-elementor-widgets' ) . '</p>'
				. ( $ctx['shop_url'] ? '<a class="hsw-ac__btn hsw-ac__btn--primary" href="' . esc_url( $ctx['shop_url'] ) . '">' . esc_html__( 'شروع خرید', 'hamishop-elementor-widgets' ) . $this->icon( 'arrow-left' ) . '</a>' : '' )
				. '</section>';
		}

		$status      = $order->get_status();
		$status_name = wc_get_order_status_name( $status );
		$view_url    = $order->get_view_order_url();
		$date        = $order->get_date_created();
		$date_text   = $date ? $this->format_order_date( $date->getTimestamp() ) : '';
		$steps       = $this->order_steps( $order );
		$tracking    = $this->order_tracking( $order );
		$items       = $order->get_items();
		$count       = $order->get_item_count();

		$html  = '<section class="hsw-ac__card hsw-ac__recent" aria-labelledby="hsw-ac-recent-title">';
		$html .= '<header class="hsw-ac__recent-head">';
		$html .= '<div><div class="hsw-ac__recent-kicker"><span class="hsw-ac__status-dot is-' . esc_attr( $this->order_status_tone( $status ) ) . '"></span><h2 id="hsw-ac-recent-title">' . esc_html__( 'سفارش اخیر', 'hamishop-elementor-widgets' ) . '</h2><span class="hsw-ac__status-text">' . esc_html( $status_name ) . '</span></div>';
		$html .= '<p class="hsw-ac__recent-meta">#' . esc_html( HSW_Helpers::fa_num( $order->get_order_number() ) ) . ' • ' . esc_html( sprintf( 'ثبت شده در %s', $date_text ) ) . '</p></div>';
		$html .= '<div class="hsw-ac__recent-links">'
			. '<a class="is-active" href="' . esc_url( $view_url ) . '">' . esc_html__( 'جزئیات کامل', 'hamishop-elementor-widgets' ) . '</a>'
			. '<a href="' . esc_url( $view_url ) . '">' . esc_html__( 'فاکتور خرید', 'hamishop-elementor-widgets' ) . '</a>'
			. '</div>';
		$html .= '</header>';

		if ( $steps['cancelled'] ) {
			$html .= '<div class="hsw-ac__order-alert">' . $this->icon( 'alert' ) . '<p>' . esc_html( sprintf( 'این سفارش در وضعیت «%s» قرار دارد.', $status_name ) ) . '</p><a href="' . esc_url( $view_url ) . '">' . esc_html__( 'مشاهده جزئیات', 'hamishop-elementor-widgets' ) . '</a></div>';
		} else {
			$html .= '<ol class="hsw-ac__timeline">';
			foreach ( $steps['steps'] as $step ) {
				$html .= '<li class="is-' . esc_attr( $step['state'] ) . '">'
					. '<span class="hsw-ac__timeline-ic">' . $this->icon( $step['icon'] ) . '</span>'
					. '<strong>' . esc_html( $step['label'] ) . '</strong>'
					. '<small>' . esc_html( $step['date'] ) . '</small>'
					. '</li>';
			}
			$html .= '</ol>';
		}

		$html .= '<div class="hsw-ac__order-summary">';
		$html .= '<div class="hsw-ac__order-total"><span>' . esc_html__( 'مبلغ کل پرداختی', 'hamishop-elementor-widgets' ) . '</span><strong>' . wp_kses_post( $this->localize_price_html( $order->get_formatted_order_total() ) ) . '</strong></div>';
		$html .= '<div class="hsw-ac__order-items"><span>' . esc_html( sprintf( 'اقلام سفارش (%s کالا)', HSW_Helpers::fa_num( $count ) ) ) . '</span><div class="hsw-ac__order-thumbs">';
		$shown = 0;
		foreach ( $items as $item ) {
			$product = $item->get_product();
			if ( ! $product || $shown >= 3 ) {
				continue;
			}
			$html .= '<a href="' . esc_url( $product->get_permalink() ) . '" title="' . esc_attr( $product->get_name() ) . '">' . $product->get_image( 'woocommerce_thumbnail', array( 'class' => 'hsw-ac__order-thumb', 'loading' => 'lazy', 'decoding' => 'async' ) ) . '</a>';
			$shown++;
		}
		$html .= '</div></div>';
		$html .= '<div class="hsw-ac__order-track">';
		if ( $tracking['code'] || $tracking['url'] ) {
			if ( $tracking['code'] ) {
				$html .= '<span class="hsw-ac__track-code">' . esc_html__( 'کد رهگیری:', 'hamishop-elementor-widgets' ) . ' <b dir="ltr">' . esc_html( $tracking['code'] ) . '</b></span>';
			}
			$url = $tracking['url'] ? $tracking['url'] : $view_url;
			$html .= '<a class="hsw-ac__track-link" href="' . esc_url( $url ) . '"' . ( $tracking['url'] ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>' . esc_html__( 'پیگیری مرسوله', 'hamishop-elementor-widgets' ) . $this->icon( 'arrow-left' ) . '</a>';
		} else {
			$html .= '<a class="hsw-ac__track-link" href="' . esc_url( $view_url ) . '">' . esc_html__( 'جزئیات و پیگیری', 'hamishop-elementor-widgets' ) . $this->icon( 'arrow-left' ) . '</a>';
		}
		$html .= '</div></div></section>';

		return $html;
	}

	private function order_steps( $order ) {
		$status = $order->get_status();
		$cancelled = in_array( $status, array( 'cancelled', 'refunded', 'failed' ), true );
		$active = 0;
		if ( in_array( $status, array( 'pending', 'on-hold' ), true ) ) {
			$active = 0;
		} elseif ( 'processing' === $status ) {
			$active = 2;
		} elseif ( 'shipped' === $status || $order->get_meta( '_hsw_shipped_date' ) ) {
			$active = 3;
		} elseif ( 'completed' === $status ) {
			$active = 4;
		} elseif ( $cancelled ) {
			$active = -1;
		}

		$created = $order->get_date_created();
		$paid    = $order->get_date_paid();
		$modified= $order->get_date_modified();
		$done    = $order->get_date_completed();
		$shipped = $order->get_meta( '_hsw_shipped_date' ) ? $order->get_meta( '_hsw_shipped_date' ) : '';

		$definitions = array(
			array( 'label' => 'ثبت سفارش', 'icon' => 'receipt', 'date' => $created ? $created->getTimestamp() : 0 ),
			array( 'label' => 'تایید پرداخت', 'icon' => 'credit-card', 'date' => $paid ? $paid->getTimestamp() : 0 ),
			array( 'label' => 'پردازش و بسته‌بندی', 'icon' => 'package', 'date' => $modified ? $modified->getTimestamp() : 0 ),
			array( 'label' => 'تحویل به پست', 'icon' => 'truck', 'date' => is_numeric( $shipped ) ? (int) $shipped : 0 ),
			array( 'label' => 'تحویل خریدار', 'icon' => 'home', 'date' => $done ? $done->getTimestamp() : 0 ),
		);

		$steps = array();
		foreach ( $definitions as $index => $definition ) {
			$state = $cancelled ? 'pending' : ( $index < $active ? 'done' : ( $index === $active ? 'active' : 'pending' ) );
			$date  = '';
			if ( 'done' === $state && $definition['date'] ) {
				$date = $this->format_order_date( $definition['date'] );
			} elseif ( 'active' === $state ) {
				$date = $definition['date'] ? $this->format_order_date( $definition['date'] ) : 'در حال انجام';
			} else {
				$date = 'در انتظار';
			}
			$steps[] = array(
				'label' => $definition['label'],
				'icon'  => $definition['icon'],
				'date'  => $date,
				'state' => $state,
			);
		}

		return array( 'steps' => $steps, 'cancelled' => $cancelled );
	}

	private function order_status_tone( $status ) {
		if ( in_array( $status, array( 'completed' ), true ) ) {
			return 'success';
		}
		if ( in_array( $status, array( 'cancelled', 'refunded', 'failed' ), true ) ) {
			return 'danger';
		}
		if ( in_array( $status, array( 'processing', 'on-hold' ), true ) ) {
			return 'warning';
		}
		return 'info';
	}

	private function format_order_date( $timestamp, $with_time = false ) {
		if ( ! $timestamp ) {
			return '';
		}
		$format = get_option( 'date_format' );
		if ( $with_time ) {
			$format .= ' ' . get_option( 'time_format' );
		}
		return $this->format_date( $timestamp, $format );
	}

	private function order_tracking( $order ) {
		$code = (string) $order->get_meta( '_tracking_number' );
		if ( ! $code ) {
			$code = (string) $order->get_meta( '_tracking_code' );
		}
		$url = (string) $order->get_meta( '_tracking_url' );
		if ( ! $url ) {
			$url = (string) $order->get_meta( '_hsw_tracking_url' );
		}

		$shipments = $order->get_meta( '_wc_shipment_tracking_items' );
		if ( ! $code && is_array( $shipments ) && ! empty( $shipments[0] ) ) {
			$first = $shipments[0];
			$code  = isset( $first['tracking_number'] ) ? (string) $first['tracking_number'] : '';
			$url   = isset( $first['tracking_url'] ) ? (string) $first['tracking_url'] : $url;
		}

		return array(
			'code' => $code,
			'url'  => $url ? esc_url_raw( $url ) : '',
		);
	}

	private function shortcuts_card( $ctx ) {
		$opts = $ctx['opts'];
		$tiles = array();

		if ( 'yes' === $opts['dashboard']['shortcut_support'] && 'yes' === $opts['support']['enabled'] ) {
			$tiles[] = array(
				'icon'  => 'support',
				'title' => 'پشتیبانی و تیکت',
				'sub'   => 'پاسخ‌گویی سریع',
				'url'   => $ctx['support_url'],
				'tone'  => 'info',
			);
		}
		if ( 'yes' === $opts['dashboard']['shortcut_returns'] && 'yes' === $opts['support']['enabled'] ) {
			$tiles[] = array(
				'icon'  => 'refresh',
				'title' => 'درخواست مرجوعی',
				'sub'   => 'ثبت درخواست بازگشت',
				'url'   => trailingslashit( $ctx['support_url'] ) . 'new/',
				'tone'  => 'warning',
			);
		}
		if ( 'yes' === $opts['dashboard']['shortcut_downloads'] && 'yes' === $opts['menu']['items']['downloads']['enabled'] ) {
			$tiles[] = array(
				'icon'  => 'download',
				'title' => 'دانلودها',
				'sub'   => $ctx['downloads_count'] ? sprintf( '%s فایل آماده دانلود', HSW_Helpers::fa_num( $ctx['downloads_count'] ) ) : 'فایل‌های خریداری‌شده',
				'url'   => wc_get_account_endpoint_url( 'downloads' ),
				'tone'  => 'success',
			);
		}

		if ( empty( $tiles ) ) {
			return '';
		}

		$html  = '<section class="hsw-ac__card hsw-ac__shortcuts" aria-labelledby="hsw-ac-shortcuts-title">';
		$html .= '<header class="hsw-ac__card-head"><h2 id="hsw-ac-shortcuts-title" class="hsw-ac__card-title">' . esc_html__( 'میانبرهای پرکاربرد', 'hamishop-elementor-widgets' ) . '</h2><span class="hsw-ac__card-head-ic">' . $this->icon( 'spark' ) . '</span></header>';
		$html .= '<div class="hsw-ac__shortcut-grid">';
		foreach ( $tiles as $tile ) {
			$html .= '<a class="hsw-ac__shortcut hsw-ac__shortcut--' . esc_attr( $tile['tone'] ) . '" href="' . esc_url( $tile['url'] ) . '">'
				. '<span class="hsw-ac__shortcut-ic">' . $this->icon( $tile['icon'] ) . '</span>'
				. '<span class="hsw-ac__shortcut-txt"><strong>' . esc_html( $tile['title'] ) . '</strong><small>' . esc_html( $tile['sub'] ) . '</small></span>'
				. $this->icon( 'arrow-left', 'hsw-ac__shortcut-arrow' )
				. '</a>';
		}
		$html .= '</div>';
		if ( ! empty( $opts['dashboard']['shortcut_note'] ) ) {
			$html .= '<p class="hsw-ac__note">' . $this->icon( 'info' ) . '<span>' . esc_html( $opts['dashboard']['shortcut_note'] ) . '</span></p>';
		}
		$html .= '</section>';
		return $html;
	}

	private function address_data( $user_id ) {
		$formatted = '';
		if ( function_exists( 'wc_get_account_formatted_address' ) ) {
			$formatted = wc_get_account_formatted_address( 'shipping' );
			if ( ! $formatted ) {
				$formatted = wc_get_account_formatted_address( 'billing' );
			}
		}

		$first  = get_user_meta( $user_id, 'shipping_first_name', true );
		$last   = get_user_meta( $user_id, 'shipping_last_name', true );
		$phone  = get_user_meta( $user_id, 'billing_phone', true );
		$post   = get_user_meta( $user_id, 'shipping_postcode', true );
		if ( ! $first && ! $last ) {
			$first = get_user_meta( $user_id, 'billing_first_name', true );
			$last  = get_user_meta( $user_id, 'billing_last_name', true );
		}
		if ( ! $post ) {
			$post = get_user_meta( $user_id, 'billing_postcode', true );
		}
		$name = trim( $first . ' ' . $last );
		if ( ! $name ) {
			$user = get_user_by( 'id', $user_id );
			$name = $user ? $user->display_name : '';
		}

		// The card prints the customer name above the address, so drop the
		// duplicate name line that WooCommerce prepends to formatted addresses.
		$formatted_name = esc_html( $name );
		if ( $formatted && $formatted_name && 0 === strpos( $formatted, $formatted_name ) ) {
			$formatted = preg_replace( '/^' . preg_quote( $formatted_name, '/' ) . '\s*(?:<br\s*\/?>)?\s*/iu', '', $formatted );
		}

		return array(
			'formatted' => $formatted,
			'name'      => $name,
			'phone'     => $phone,
			'postcode'  => $post,
			'edit_url'  => wc_get_account_endpoint_url( 'edit-address' ),
		);
	}

	private function address_card( $ctx ) {
		$address = $ctx['address'];
		$html    = '<section class="hsw-ac__card hsw-ac__address" aria-labelledby="hsw-ac-address-title">';
		$html   .= '<header class="hsw-ac__card-head"><h2 id="hsw-ac-address-title" class="hsw-ac__card-title">' . esc_html__( 'نشانی پیش‌فرض تحویل سفارش', 'hamishop-elementor-widgets' ) . '</h2><span class="hsw-ac__card-head-ic">' . $this->icon( 'location' ) . '</span></header>';

		if ( empty( $address['formatted'] ) ) {
			$html .= '<div class="hsw-ac__address-empty"><p>' . esc_html__( 'هنوز آدرسی ثبت نکرده‌اید.', 'hamishop-elementor-widgets' ) . '</p><a class="hsw-ac__btn hsw-ac__btn--ghost" href="' . esc_url( $address['edit_url'] ) . '">' . $this->icon( 'plus' ) . esc_html__( 'افزودن آدرس', 'hamishop-elementor-widgets' ) . '</a></div>';
		} else {
			$html .= '<div class="hsw-ac__address-body">';
			$html .= '<div class="hsw-ac__address-badge">' . $this->icon( 'check' ) . esc_html__( 'پیش‌فرض', 'hamishop-elementor-widgets' ) . '</div>';
			$html .= '<div class="hsw-ac__address-person"><strong>' . esc_html( $address['name'] ) . '</strong>' . ( $address['phone'] ? '<span dir="ltr">' . esc_html( $address['phone'] ) . '</span>' : '' ) . '</div>';
			$html .= '<div class="hsw-ac__address-lines">' . wp_kses_post( $address['formatted'] ) . '</div>';
			if ( $address['postcode'] && false === strpos( wp_strip_all_tags( $address['formatted'] ), $address['postcode'] ) ) {
				$html .= '<p class="hsw-ac__address-post">' . esc_html__( 'کد پستی:', 'hamishop-elementor-widgets' ) . ' <span dir="ltr">' . esc_html( $address['postcode'] ) . '</span></p>';
			}
			$html .= '</div>';
			$html .= '<a class="hsw-ac__address-edit" href="' . esc_url( $address['edit_url'] ) . '">' . $this->icon( 'edit' ) . esc_html__( 'ویرایش آدرس', 'hamishop-elementor-widgets' ) . '</a>';
		}
		$html .= '</section>';
		return $html;
	}

	/**
	 * Modern order history. The native WooCommerce order actions and URLs are
	 * preserved; only the list presentation is ours.
	 */
	private function orders_content( $ctx ) {
		if ( ! function_exists( 'wc_get_orders' ) || ! function_exists( 'wc_get_account_orders_actions' ) ) {
			return $this->disabled_card( __( 'سفارش‌ها', 'hamishop-elementor-widgets' ) );
		}

		$paged  = max( 1, absint( get_query_var( 'paged' ) ) );
		$result = wc_get_orders(
			array(
				'customer' => $ctx['user_id'],
				'limit'    => 8,
				'page'     => $paged,
				'paginate' => true,
				'orderby'  => 'date',
				'order'    => 'DESC',
			)
		);
		$orders = $result && isset( $result->orders ) ? (array) $result->orders : array();
		$pages  = $result && isset( $result->max_num_pages ) ? (int) $result->max_num_pages : 1;

		$html  = '<section class="hsw-ac__orders" aria-labelledby="hsw-ac-orders-title">';
		$html .= '<header class="hsw-ac__section-head"><div><h2 id="hsw-ac-orders-title">' . esc_html__( 'سفارش‌های من', 'hamishop-elementor-widgets' ) . '</h2><p>' . esc_html__( 'وضعیت، اقلام و جزئیات هر سفارش را از اینجا پیگیری کنید.', 'hamishop-elementor-widgets' ) . '</p></div><span class="hsw-ac__count-pill">' . esc_html( HSW_Helpers::fa_num( $result && isset( $result->total ) ? $result->total : count( $orders ) ) ) . ' ' . esc_html__( 'سفارش', 'hamishop-elementor-widgets' ) . '</span></header>';

		if ( empty( $orders ) ) {
			$html .= '<div class="hsw-ac__card hsw-ac__empty hsw-ac__orders-empty">'
				. '<span class="hsw-ac__empty-ic">' . $this->icon( 'package' ) . '</span>'
				. '<h3>' . esc_html__( 'هنوز سفارشی ثبت نکرده‌اید', 'hamishop-elementor-widgets' ) . '</h3>'
				. '<p>' . esc_html__( 'اولین خرید خود را ثبت کنید تا وضعیت آن را در همین بخش ببینید.', 'hamishop-elementor-widgets' ) . '</p>'
				. ( $ctx['shop_url'] ? '<a class="hsw-ac__btn hsw-ac__btn--primary" href="' . esc_url( $ctx['shop_url'] ) . '">' . esc_html__( 'رفتن به فروشگاه', 'hamishop-elementor-widgets' ) . $this->icon( 'arrow-left' ) . '</a>' : '' )
				. '</div>';
			return $html . '</section>';
		}

		$html .= '<div class="hsw-ac__order-list">';
		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			$html .= $this->order_card( $order );
		}
		$html .= '</div>';

		if ( $pages > 1 ) {
			$html .= '<nav class="hsw-ac__pagination" aria-label="' . esc_attr__( 'صفحه‌بندی سفارش‌ها', 'hamishop-elementor-widgets' ) . '">';
			for ( $page = 1; $page <= $pages; $page++ ) {
				$html .= '<a class="' . ( $page === $paged ? 'is-active' : '' ) . '" href="' . esc_url( wc_get_endpoint_url( 'orders', $page ) ) . '"' . ( $page === $paged ? ' aria-current="page"' : '' ) . '>' . esc_html( HSW_Helpers::fa_num( $page ) ) . '</a>';
			}
			$html .= '</nav>';
		}

		return $html . '</section>';
	}

	private function order_card( $order ) {
		$status      = $order->get_status();
		$status_name = wc_get_order_status_name( $status );
		$status_tone = $this->order_status_tone( $status );
		$created     = $order->get_date_created();
		$date        = $created ? $this->format_order_date( $created->getTimestamp() ) : '';
		$items       = $order->get_items();
		$count       = 0;
		foreach ( $items as $item ) {
			$count += (int) $item->get_quantity();
		}
		$actions = wc_get_account_orders_actions( $order );
		$actions = is_array( $actions ) ? $actions : array();
		$icons   = array(
			'view'   => 'eye',
			'pay'    => 'credit-card',
			'cancel' => 'x',
		);

		$html  = '<article class="hsw-ac__order-card hsw-ac__order-card--' . esc_attr( $status_tone ) . '">';
		$html .= '<header class="hsw-ac__order-card-head">';
		$html .= '<div class="hsw-ac__order-id"><span class="hsw-ac__order-kicker">' . esc_html__( 'سفارش', 'hamishop-elementor-widgets' ) . '</span><strong>#' . esc_html( HSW_Helpers::fa_num( $order->get_order_number() ) ) . '</strong><span class="hsw-ac__order-date">' . esc_html( $date ) . '</span></div>';
		$html .= '<span class="hsw-ac__status-pill is-' . esc_attr( $status_tone ) . '"><i aria-hidden="true"></i>' . esc_html( $status_name ) . '</span>';
		$html .= '</header>';

		$html .= '<div class="hsw-ac__order-card-body"><div class="hsw-ac__order-thumbs">';
		$shown = 0;
		foreach ( $items as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			$html .= '<a href="' . esc_url( $product->get_permalink() ) . '" title="' . esc_attr( $product->get_name() ) . '">' . $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy', 'decoding' => 'async' ) ) . '</a>';
			$shown++;
			if ( $shown >= 3 ) {
				break;
			}
		}
		if ( 0 === $shown ) {
			$html .= '<span class="hsw-ac__order-thumb-empty">' . $this->icon( 'package' ) . '</span>';
		}
		if ( count( $items ) > 3 ) {
			$html .= '<span class="hsw-ac__order-thumb-more">+' . esc_html( HSW_Helpers::fa_num( count( $items ) - 3 ) ) . '</span>';
		}
		$html .= '</div><div class="hsw-ac__order-summary-line"><span>' . esc_html( sprintf( '%s کالا', HSW_Helpers::fa_num( $count ) ) ) . '</span><strong>' . wp_kses_post( $this->localize_price_html( $order->get_formatted_order_total() ) ) . '</strong></div></div>';

		if ( ! empty( $actions ) ) {
			$html .= '<footer class="hsw-ac__order-actions">';
			foreach ( $actions as $key => $action ) {
				$url   = isset( $action['url'] ) ? $action['url'] : '';
				$label = isset( $action['name'] ) ? $action['name'] : $key;
				if ( ! $url ) {
					continue;
				}
				$icon  = isset( $icons[ $key ] ) ? $icons[ $key ] : 'arrow-left';
				$class = 'pay' === $key ? ' hsw-ac__btn--primary' : ' hsw-ac__btn--ghost';
				$html .= '<a class="hsw-ac__btn' . $class . '" href="' . esc_url( $url ) . '">' . $this->icon( $icon ) . esc_html( $label ) . '</a>';
			}
			$html .= '</footer>';
		}

		return $html . '</article>';
	}

	/**
	 * Order view as a printable invoice.
	 *
	 * WooCommerce's own view-order template is a plain table with no branding
	 * and no way to print, which is not what a customer wants when they need a
	 * receipt for an expense claim. This renders the same data as a document:
	 * store mark, invoice meta, both parties, line items, totals, and a print
	 * button whose stylesheet strips the whole account shell.
	 *
	 * @param array $ctx Render context.
	 * @return string
	 */
	private function view_order_content( $ctx ) {
		$order_id = isset( $ctx['view_order_id'] ) ? absint( $ctx['view_order_id'] ) : 0;
		$order    = $order_id ? wc_get_order( $order_id ) : null;

		if ( ! $order || (int) $order->get_customer_id() !== (int) $ctx['user_id'] ) {
			return '<section class="hsw-ac__card hsw-ac__empty"><span class="hsw-ac__empty-ic">' . $this->icon( 'package' ) . '</span><h2>' . esc_html__( 'سفارش پیدا نشد', 'hamishop-elementor-widgets' ) . '</h2><p>' . esc_html__( 'این سفارش در حساب شما وجود ندارد.', 'hamishop-elementor-widgets' ) . '</p><a class="hsw-ac__btn hsw-ac__btn--primary" href="' . esc_url( wc_get_account_endpoint_url( 'orders' ) ) . '">' . esc_html__( 'بازگشت به سفارش‌ها', 'hamishop-elementor-widgets' ) . '</a></section>';
		}

		$brand   = class_exists( 'HSW_Branding' ) ? HSW_Branding::get() : array();
		$name    = class_exists( 'HSW_Branding' ) ? HSW_Branding::name() : get_bloginfo( 'name' );
		$logo    = class_exists( 'HSW_Branding' ) ? HSW_Branding::logo_html( 'hsw-inv__logo' ) : '';
		$currency = $order->get_currency();

		$created = $order->get_date_created();
		$ts      = $created ? $created->getTimestamp() : 0;
		$date    = $ts ? $this->format_date( $ts, 'j F Y' ) : '';
		$year    = $ts ? $this->format_date( $ts, 'Y' ) : '';
		$number  = $order->get_order_number();

		/* Invoice number follows the reference document: year-order. */
		$invoice_no = ( '' !== $year ? $year . '-' : '' ) . $number;

		$billing_name = trim( $order->get_formatted_billing_full_name() );
		$phone        = (string) $order->get_billing_phone();
		$email        = (string) $order->get_billing_email();

		$seller_addr  = trim( (string) ( $brand['store_address'] ?? '' ) );
		$seller_phone = trim( (string) ( $brand['store_phone'] ?? '' ) );
		$seller_mail  = trim( (string) ( $brand['store_email'] ?? '' ) );
		$stamp        = trim( (string) ( $brand['stamp_url'] ?? '' ) );

		$payment = (string) $order->get_payment_method_title();
		if ( '' === trim( $payment ) ) {
			$payment = __( 'ثبت نشده', 'hamishop-elementor-widgets' );
		}

		$paid_line = '';
		if ( $order->is_paid() && $order->get_date_paid() ) {
			$paid_line = sprintf(
				/* translators: %s: payment date. */
				__( 'پرداخت شده در %s', 'hamishop-elementor-widgets' ),
				$this->format_date( $order->get_date_paid()->getTimestamp() )
			);
		} elseif ( $order->is_paid() ) {
			$paid_line = __( 'پرداخت شده', 'hamishop-elementor-widgets' );
		} else {
			$paid_line = wc_get_order_status_name( $order->get_status() );
		}

		$html  = '<section class="hsw-ac__invoice" id="hsw-invoice">';

		/* ---------- title + brand ---------- */
		$html .= '<header class="hsw-inv__top">';
		$html .= '<div class="hsw-inv__title-block">';
		$html .= '<h2 class="hsw-inv__title">' . esc_html__( 'فاکتور فروش', 'hamishop-elementor-widgets' ) . '</h2>';
		$html .= '<dl class="hsw-inv__meta">';
		$html .= '<div><dt>' . esc_html__( 'شماره فاکتور', 'hamishop-elementor-widgets' ) . '</dt><dd dir="ltr">' . esc_html( HSW_Helpers::fa_num( $invoice_no ) ) . '</dd></div>';
		$html .= '<div><dt>' . esc_html__( 'تاریخ صدور', 'hamishop-elementor-widgets' ) . '</dt><dd>' . esc_html( HSW_Helpers::fa_num( $date ) ) . '</dd></div>';
		$html .= '<div><dt>' . esc_html__( 'شماره سفارش', 'hamishop-elementor-widgets' ) . '</dt><dd>' . esc_html( HSW_Helpers::fa_num( $number ) ) . '</dd></div>';
		$html .= '</dl></div>';

		$html .= '<div class="hsw-inv__brand">' . $logo;
		$html .= '<div class="hsw-inv__brandtext"><strong>' . esc_html( $name ) . '</strong>';
		$html .= '<span dir="ltr">' . esc_html( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) . '</span>';
		$html .= '</div></div>';
		$html .= '</header>';

		/* ---------- buyer / seller ---------- */
		$html .= '<div class="hsw-inv__parties">';

		$html .= '<section class="hsw-inv__party"><h2>' . esc_html__( 'خریدار', 'hamishop-elementor-widgets' ) . '</h2><dl>';
		$html .= '<div><dt>' . esc_html__( 'نام', 'hamishop-elementor-widgets' ) . '</dt><dd>' . esc_html( '' !== $billing_name ? $billing_name : '—' ) . '</dd></div>';
		$html .= '<div><dt>' . esc_html__( 'تلفن', 'hamishop-elementor-widgets' ) . '</dt><dd dir="ltr">' . esc_html( HSW_Helpers::fa_num( $phone ) ) . '</dd></div>';
		$html .= '<div><dt>' . esc_html__( 'ایمیل', 'hamishop-elementor-widgets' ) . '</dt><dd dir="ltr">' . esc_html( $email ) . '</dd></div>';
		$html .= '</dl></section>';

		$html .= '<section class="hsw-inv__party"><h2>' . esc_html__( 'فروشنده', 'hamishop-elementor-widgets' ) . '</h2><dl>';
		$html .= '<div><dt>' . esc_html__( 'نام', 'hamishop-elementor-widgets' ) . '</dt><dd>' . esc_html( $name ) . '</dd></div>';
		if ( '' !== $seller_addr ) {
			$html .= '<div><dt>' . esc_html__( 'نشانی', 'hamishop-elementor-widgets' ) . '</dt><dd>' . esc_html( $seller_addr ) . '</dd></div>';
		}
		if ( '' !== $seller_phone ) {
			$html .= '<div><dt>' . esc_html__( 'تلفن', 'hamishop-elementor-widgets' ) . '</dt><dd dir="ltr">' . esc_html( HSW_Helpers::fa_num( $seller_phone ) ) . '</dd></div>';
		}
		if ( '' !== $seller_mail ) {
			$html .= '<div><dt>' . esc_html__( 'ایمیل', 'hamishop-elementor-widgets' ) . '</dt><dd dir="ltr">' . esc_html( $seller_mail ) . '</dd></div>';
		}
		$html .= '</dl></section>';
		$html .= '</div>';

		/* ---------- items ---------- */
		$html .= '<table class="hsw-inv__items"><thead><tr>';
		$html .= '<th class="hsw-inv__c-num">' . esc_html__( 'ردیف', 'hamishop-elementor-widgets' ) . '</th>';
		$html .= '<th class="hsw-inv__c-name">' . esc_html__( 'شرح کالا یا خدمت', 'hamishop-elementor-widgets' ) . '</th>';
		$html .= '<th class="hsw-inv__c-qty">' . esc_html__( 'تعداد', 'hamishop-elementor-widgets' ) . '</th>';
		$html .= '<th class="hsw-inv__c-price">' . esc_html__( 'بهای واحد', 'hamishop-elementor-widgets' ) . '</th>';
		$html .= '<th class="hsw-inv__c-total">' . esc_html__( 'مبلغ کل', 'hamishop-elementor-widgets' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		$i = 0;
		foreach ( $order->get_items() as $item ) {
			$i++;
			$qty  = (int) $item->get_quantity();
			$line = (float) $item->get_total();
			$unit = $qty > 0 ? $line / $qty : $line;

			$meta = wc_display_item_meta( $item, array( 'echo' => false, 'before' => '', 'separator' => ' · ', 'after' => '' ) );
			$meta = trim( wp_strip_all_tags( (string) $meta ) );

			$html .= '<tr>';
			$html .= '<td class="hsw-inv__c-num">' . esc_html( HSW_Helpers::fa_num( $i ) ) . '</td>';
			$html .= '<td class="hsw-inv__c-name"><span class="hsw-inv__pname">' . esc_html( $item->get_name() ) . '</span>';
			if ( '' !== $meta ) {
				$html .= '<span class="hsw-inv__pmeta">' . esc_html( $meta ) . '</span>';
			}
			$html .= '</td>';
			$html .= '<td class="hsw-inv__c-qty">' . esc_html( HSW_Helpers::fa_num( $qty ) ) . '</td>';
			$html .= '<td class="hsw-inv__c-price">' . wp_kses_post( $this->localize_price_html( wc_price( $unit, array( 'currency' => $currency ) ) ) ) . '</td>';
			$html .= '<td class="hsw-inv__c-total">' . wp_kses_post( $this->localize_price_html( wc_price( $line, array( 'currency' => $currency ) ) ) ) . '</td>';
			$html .= '</tr>';
		}
		$html .= '</tbody></table>';

		/* ---------- totals ---------- */
		$rows   = array();
		$rows[] = array( __( 'جمع کل', 'hamishop-elementor-widgets' ), (float) $order->get_subtotal() );

		$discount = (float) $order->get_discount_total();
		if ( $discount > 0 ) {
			$rows[] = array( __( 'تخفیف', 'hamishop-elementor-widgets' ), -$discount );
		}

		$ship_total = (float) $order->get_shipping_total();
		if ( $ship_total > 0 ) {
			$rows[] = array( __( 'هزینه ارسال', 'hamishop-elementor-widgets' ), $ship_total );
		}

		foreach ( $order->get_fees() as $fee ) {
			$rows[] = array( $fee->get_name(), (float) $fee->get_total() );
		}

		$tax = (float) $order->get_total_tax();
		if ( $tax > 0 ) {
			$rows[] = array( __( 'مالیات', 'hamishop-elementor-widgets' ), $tax );
		}

		$html .= '<div class="hsw-inv__settle">';

		$html .= '<table class="hsw-inv__totals"><tbody>';
		foreach ( $rows as $row ) {
			$value  = (float) $row[1];
			$amount = $value < 0
				? '− ' . $this->localize_price_html( wc_price( abs( $value ), array( 'currency' => $currency ) ) )
				: ( 0.0 === $value ? esc_html__( 'رایگان', 'hamishop-elementor-widgets' ) : $this->localize_price_html( wc_price( $value, array( 'currency' => $currency ) ) ) );
			$html  .= '<tr><th>' . esc_html( $row[0] ) . '</th><td>' . wp_kses_post( $amount ) . '</td></tr>';
		}
		$html .= '<tr class="hsw-inv__grand"><th>' . esc_html__( 'مبلغ پرداخت‌شده', 'hamishop-elementor-widgets' ) . '</th><td>' . wp_kses_post( $this->localize_price_html( $order->get_formatted_order_total() ) ) . '</td></tr>';
		$html .= '</tbody></table>';

		$words = HSW_Helpers::number_words( (float) $order->get_total() );

		$html .= '<div class="hsw-inv__paidbox">';
		$html .= '<span>' . esc_html__( 'مبلغ پرداخت‌شده توسط خریدار:', 'hamishop-elementor-widgets' ) . '</span>';
		$html .= '<strong>' . wp_kses_post( $this->localize_price_html( $order->get_formatted_order_total() ) ) . '</strong>';
		$html .= '<p class="hsw-inv__words">' . esc_html( $words . ' ' . ( function_exists( 'get_woocommerce_currency' ) && 'IRT' === get_woocommerce_currency() ? __( 'تومان', 'hamishop-elementor-widgets' ) : get_woocommerce_currency() ) ) . '</p>';
		$html .= '</div>';

		$html .= '</div>';

		$html .= '<dl class="hsw-inv__paymeta">';
		$html .= '<div><dt>' . esc_html__( 'روش پرداخت:', 'hamishop-elementor-widgets' ) . '</dt><dd>' . esc_html( $payment ) . '</dd></div>';
		$html .= '<div><dt>' . esc_html__( 'وضعیت:', 'hamishop-elementor-widgets' ) . '</dt><dd>' . esc_html( $paid_line ) . '</dd></div>';
		$html .= '</dl>';

		/* ---------- stamp + signature ---------- */
		/* Signature on the reading side, stamp opposite — as in the reference. */
		$html .= '<div class="hsw-inv__sign">';
		$html .= '<span class="hsw-inv__signline">' . esc_html__( 'امضای خریدار', 'hamishop-elementor-widgets' ) . '</span>';
		if ( '' !== $stamp ) {
			$html .= '<img class="hsw-inv__stamp" src="' . esc_url( $stamp ) . '" alt="" />';
		} else {
			$html .= '<span class="hsw-inv__stamp hsw-inv__stamp--empty" aria-hidden="true"></span>';
		}
		$html .= '</div>';

		/* ---------- footer ---------- */
		$html .= '<footer class="hsw-inv__foot">';
		$html .= '<p class="hsw-inv__footnote">' . esc_html__( 'این فاکتور بابت فروش کالا یا خدمات صادر شده و پرداخت آن به معنای پذیرش قوانین فروشگاه است.', 'hamishop-elementor-widgets' ) . '</p>';
		$html .= '<div class="hsw-inv__footrow"><span dir="ltr">' . esc_html( $order->get_view_order_url() ) . '</span><span>۱ / ۱</span></div>';
		$html .= '</footer>';

		$html .= '<div class="hsw-inv__actions no-print">';
		/*
		 * «چاپ» opens the standalone sheet rather than printing this card: the
		 * sheet is a one-page A4 document with the postal block, and printing it
		 * never drags the account shell into the paper.
		 */
		if ( class_exists( 'HSW_Invoice' ) && HSW_Invoice::enabled() ) {
			$html .= '<a class="hsw-inv__print" data-hsw-print-link target="_blank" rel="noopener" href="' . esc_url( HSW_Invoice::url( $order->get_id() ) ) . '">' . $this->icon( 'print' ) . esc_html__( 'چاپ فاکتور / برچسب پستی', 'hamishop-elementor-widgets' ) . '</a>';
		} else {
			$html .= '<button type="button" class="hsw-inv__print" data-hsw-print>' . $this->icon( 'print' ) . esc_html__( 'چاپ فاکتور', 'hamishop-elementor-widgets' ) . '</button>';
		}
		$html .= '<a class="hsw-ac__btn hsw-ac__btn--ghost" href="' . esc_url( wc_get_account_endpoint_url( 'orders' ) ) . '">' . esc_html__( 'بازگشت به سفارش‌ها', 'hamishop-elementor-widgets' ) . '</a>';
		$html .= '</div>';

		return $html . '</section>';
	}

	/**
	 * Address list page. The native billing/shipping forms are still rendered
	 * by WooCommerce when the customer opens an address editor.
	 */
	private function addresses_content( $ctx ) {
		$types = array(
			'billing' => __( 'آدرس صورتحساب', 'hamishop-elementor-widgets' ),
		);
		if ( function_exists( 'wc_shipping_enabled' ) && wc_shipping_enabled() && ( ! function_exists( 'wc_ship_to_billing_address_only' ) || ! wc_ship_to_billing_address_only() ) ) {
			$types['shipping'] = __( 'آدرس حمل و نقل', 'hamishop-elementor-widgets' );
		}

		$html  = '<section class="hsw-ac__addresses" aria-labelledby="hsw-ac-addresses-title">';
		$html .= '<header class="hsw-ac__section-head"><div><h2 id="hsw-ac-addresses-title">' . esc_html__( 'آدرس‌های من', 'hamishop-elementor-widgets' ) . '</h2><p>' . esc_html__( 'آدرس پیش‌فرض صورتحساب و تحویل سفارش را مدیریت کنید.', 'hamishop-elementor-widgets' ) . '</p></div><span class="hsw-ac__card-head-ic">' . $this->icon( 'location' ) . '</span></header>';
		$html .= '<div class="hsw-ac__addr-grid">';

		foreach ( $types as $type => $label ) {
			$formatted = function_exists( 'wc_get_account_formatted_address' ) ? wc_get_account_formatted_address( $type ) : '';
			$edit_url  = wc_get_endpoint_url( 'edit-address', $type, wc_get_page_permalink( 'myaccount' ) );
			$has       = '' !== trim( wp_strip_all_tags( (string) $formatted ) );

			/*
			 * WooCommerce lets a customer ship to billing without ever filling a
			 * separate shipping address. The card used to say "nothing saved"
			 * even though every order went to that same address.
			 */
			$borrowed = false;
			if ( ! $has && 'shipping' === $type ) {
				$billing = function_exists( 'wc_get_account_formatted_address' ) ? wc_get_account_formatted_address( 'billing' ) : '';
				if ( '' !== trim( wp_strip_all_tags( (string) $billing ) ) ) {
					$formatted = $billing;
					$has       = true;
					$borrowed  = true;
				}
			}

			$html .= '<article class="hsw-ac__card hsw-ac__addr-card' . ( $has ? '' : ' is-empty' ) . '">';
			$html .= '<header class="hsw-ac__addr-head"><div><span class="hsw-ac__addr-kicker">' . esc_html__( 'آدرس', 'hamishop-elementor-widgets' ) . '</span><h3>' . esc_html( $label ) . '</h3></div>' . ( $has ? '<span class="hsw-ac__addr-badge">' . $this->icon( 'check' ) . esc_html__( 'ثبت شده', 'hamishop-elementor-widgets' ) . '</span>' : '' ) . '</header>';

			if ( $has ) {
				if ( $borrowed ) {
					$html .= '<p class="hsw-ac__addr-note">' . $this->icon( 'info' ) . esc_html__( 'تحویل به همان آدرس صورتحساب', 'hamishop-elementor-widgets' ) . '</p>';
				}
				$html .= '<address class="hsw-ac__addr-body">' . wp_kses_post( $formatted ) . '</address>';
				$html .= '<a class="hsw-ac__addr-edit" href="' . esc_url( $edit_url ) . '">' . $this->icon( 'edit' ) . esc_html__( 'ویرایش آدرس', 'hamishop-elementor-widgets' ) . '</a>';
			} else {
				$html .= '<div class="hsw-ac__addr-empty"><span class="hsw-ac__empty-ic">' . $this->icon( 'location' ) . '</span><p>' . esc_html__( 'هنوز آدرسی برای این بخش ثبت نشده است.', 'hamishop-elementor-widgets' ) . '</p><a class="hsw-ac__btn hsw-ac__btn--primary" href="' . esc_url( $edit_url ) . '">' . $this->icon( 'plus' ) . esc_html__( 'افزودن آدرس', 'hamishop-elementor-widgets' ) . '</a></div>';
			}
			$html .= '</article>';
		}

		$html .= '</div></section>';
		return $html;
	}

	/**
	 * Account details page: profile summary + native WooCommerce edit form.
	 */
	private function account_content( $ctx ) {
		$user  = $ctx['user'] instanceof WP_User ? $ctx['user'] : get_user_by( 'id', $ctx['user_id'] );
		$name  = $user ? $user->display_name : '';
		$email = $user ? $user->user_email : '';
		$phone = $user ? (string) get_user_meta( $user->ID, 'billing_phone', true ) : '';
		$since = $user ? $this->format_date( strtotime( $user->user_registered . ' UTC' ), 'F Y' ) : '';

		ob_start();
		echo '<div class="hsw-ac__wc-wrap"><div class="woocommerce">';
		do_action( 'woocommerce_account_content' );
		echo '</div></div>';
		$native = (string) ob_get_clean();

		$html  = '<section class="hsw-ac__account-page">';
		$html .= '<div class="hsw-ac__account-aside"><article class="hsw-ac__card hsw-ac__account-profile">';
		$html .= '<div class="hsw-ac__account-avatar">' . $this->avatar_html( $ctx['user_id'], $name, 88 ) . '</div>';
		$html .= '<h2>' . esc_html( $name ) . '</h2><span class="hsw-ac__verified">' . $this->icon( 'check-circle' ) . esc_html__( 'حساب فعال', 'hamishop-elementor-widgets' ) . '</span>';
		$html .= '<div class="hsw-ac__account-meta">';
		if ( $email ) {
			$html .= '<a href="mailto:' . esc_attr( $email ) . '">' . $this->icon( 'mail' ) . '<span dir="ltr">' . esc_html( $email ) . '</span></a>';
		}
		if ( $phone ) {
			$html .= '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . $this->icon( 'phone' ) . '<span dir="ltr">' . esc_html( $phone ) . '</span></a>';
		}
		if ( $since ) {
			$html .= '<span>' . $this->icon( 'calendar' ) . esc_html( sprintf( 'عضو از %s', $since ) ) . '</span>';
		}
		$html .= '</div></article>';
		if ( $this->auth && method_exists( $this->auth, 'security_panel' ) ) {
			$html .= $this->auth->security_panel( $ctx );
		}
		$html .= '</div>';
		$html .= '<article class="hsw-ac__card hsw-ac__account-form"><header class="hsw-ac__card-head"><div><h2 class="hsw-ac__card-title">' . esc_html__( 'اطلاعات حساب', 'hamishop-elementor-widgets' ) . '</h2><p>' . esc_html__( 'نام، ایمیل و اطلاعات ورود خود را به‌روز کنید.', 'hamishop-elementor-widgets' ) . '</p></div><span class="hsw-ac__card-head-ic">' . $this->icon( 'user' ) . '</span></header>' . $native . '</article>';
		$html .= '</section>';
		return $html;
	}

	private function loyalty_data( $user_id, $opts ) {
		$points = 'yes' === $opts['loyalty']['enabled'] ? $this->loyalty_points( $user_id, $opts ) : 0;
		$silver = max( 1, (int) $opts['loyalty']['silver'] );
		$gold   = max( $silver + 1, (int) $opts['loyalty']['gold'] );
		$tier   = 'عضو تازه';
		$min    = 0;
		$next   = $silver;
		if ( $points >= $gold ) {
			$tier = 'مشتری ویژه';
			$min  = $gold;
			$next = 0;
		} elseif ( $points >= $silver ) {
			$tier = 'کاربر وفادار';
			$min  = $silver;
			$next = $gold;
		}
		$progress = 0;
		$progress_text = '';
		if ( $next ) {
			$progress = (int) round( ( ( $points - $min ) / max( 1, $next - $min ) ) * 100 );
			$progress = max( 0, min( 100, $progress ) );
			$progress_text = sprintf( '%s امتیاز تا %s', HSW_Helpers::fa_num( max( 0, $next - $points ) ), $points >= $silver ? 'مشتری ویژه' : 'کاربر وفادار' );
		} else {
			$progress = 100;
			$progress_text = 'بالاترین سطح باشگاه';
		}

		$user = get_user_by( 'id', $user_id );
		$registered = $user ? strtotime( get_gmt_from_date( $user->user_registered ) . ' UTC' ) : 0;
		$since = $registered ? $this->format_date( $registered, 'F Y' ) : '';

		return array(
			'points'        => $points,
			'tier'          => $tier,
			'progress'      => $progress,
			'progress_text' => $progress_text,
			'completed'     => $this->count_customer_orders( $user_id, array( 'completed' ) ),
			'since'         => $since,
		);
	}

	private function render_endpoint_content( $ctx ) {
		$endpoint = $ctx['endpoint'];

		/*
		 * Phone-first accounts never proved their email. The banner rides on the
		 * three screens a customer actually visits (dashboard, orders, account
		 * details) and disappears for good once the address is confirmed.
		 */
		$verify = $this->email_verify ? $this->email_verify->banner( $ctx ) : '';

		if ( 'dashboard' === $endpoint ) {
			return '<div class="hsw-ac__content">' . $verify . $this->dashboard_cards( $ctx ) . '</div>';
		}
		if ( 'customer-logout' !== $endpoint && ! $this->endpoint_enabled_for_context( $endpoint, $ctx ) ) {
			return '<div class="hsw-ac__content">' . $this->disabled_card( $ctx['current_label'] ) . '</div>';
		}
		if ( 'support' === $endpoint ) {
			return '<div class="hsw-ac__content">' . ( $this->support ? $this->support->render_content( $ctx ) : $this->disabled_card( 'پشتیبانی' ) ) . '</div>';
		}
		if ( 'notifications' === $endpoint ) {
			return '<div class="hsw-ac__content">' . $this->notifications_content( $ctx ) . '</div>';
		}
		if ( 'wishlist' === $endpoint ) {
			return '<div class="hsw-ac__content">' . $this->wishlist_content( $ctx ) . '</div>';
		}
		if ( 'wallet' === $endpoint ) {
			return '<div class="hsw-ac__content">' . $this->wallet_content( $ctx ) . '</div>';
		}
		if ( 'orders' === $endpoint ) {
			return '<div class="hsw-ac__content">' . $verify . $this->orders_content( $ctx ) . '</div>';
		}
		if ( 'view-order' === $endpoint && ! empty( $ctx['view_order_id'] ) ) {
			return '<div class="hsw-ac__content">' . $this->view_order_content( $ctx ) . '</div>';
		}
		if ( 'edit-address' === $endpoint && '' === trim( (string) $this->endpoint_value( 'edit-address' ) ) ) {
			return '<div class="hsw-ac__content">' . $this->addresses_content( $ctx ) . '</div>';
		}
		if ( 'edit-account' === $endpoint ) {
			return '<div class="hsw-ac__content">' . $verify . $this->account_content( $ctx ) . '</div>';
		}
		if ( 'customer-logout' === $endpoint ) {
			$html  = '<section class="hsw-ac__card hsw-ac__empty">';
			$html .= '<span class="hsw-ac__empty-ic">' . $this->icon( 'logout' ) . '</span>';
			$html .= '<h2>' . esc_html__( 'خروج از حساب کاربری', 'hamishop-elementor-widgets' ) . '</h2>';
			$html .= '<p>' . esc_html__( 'برای ورود دوباره باید نام کاربری و رمز عبور خود را وارد کنید.', 'hamishop-elementor-widgets' ) . '</p>';
			$html .= '<a class="hsw-ac__btn hsw-ac__btn--primary" href="' . esc_url( wc_logout_url( wc_get_page_permalink( 'myaccount' ) ) ) . '">' . $this->icon( 'logout' ) . esc_html__( 'خروج امن', 'hamishop-elementor-widgets' ) . '</a>';
			$html .= '</section>';
			return '<div class="hsw-ac__content">' . $html . '</div>';
		}

		ob_start();
		echo '<div class="hsw-ac__content hsw-ac__wc-wrap"><div class="woocommerce">';
		do_action( 'woocommerce_account_content' );
		echo '</div></div>';
		return (string) ob_get_clean();
	}

	private function endpoint_enabled_for_context( $endpoint, $ctx ) {
		if ( in_array( $endpoint, array( 'dashboard', 'customer-logout' ), true ) ) {
			return true;
		}

		$map = array(
			'view-order'                 => 'orders',
			'order-pay'                  => 'orders',
			'order-received'             => 'orders',
			'add-payment-method'         => 'payment-methods',
			'delete-payment-method'      => 'payment-methods',
			'set-default-payment-method' => 'payment-methods',
		);
		$key = isset( $map[ $endpoint ] ) ? $map[ $endpoint ] : $endpoint;

		if ( isset( $ctx['opts']['menu']['items'][ $key ]['enabled'] ) && 'yes' !== $ctx['opts']['menu']['items'][ $key ]['enabled'] ) {
			return false;
		}
		if ( 'support' === $key && 'yes' !== $ctx['opts']['support']['enabled'] ) {
			return false;
		}
		if ( 'notifications' === $key && 'yes' !== $ctx['opts']['notifications']['enabled'] ) {
			return false;
		}
		if ( 'wallet' === $key && 'yes' !== $ctx['opts']['wallet']['enabled'] ) {
			return false;
		}

		return $this->endpoint_enabled( $endpoint );
	}

	public function endpoint_enabled( $endpoint ) {
		if ( in_array( $endpoint, array( 'dashboard', 'customer-logout' ), true ) ) {
			return true;
		}
		$map = array(
			'view-order'                 => 'orders',
			'order-pay'                  => 'orders',
			'order-received'             => 'orders',
			'add-payment-method'         => 'payment-methods',
			'delete-payment-method'      => 'payment-methods',
			'set-default-payment-method' => 'payment-methods',
		);
		$key = isset( $map[ $endpoint ] ) ? $map[ $endpoint ] : $endpoint;

		if ( 'support' === $key && 'yes' !== $this->setting( 'support.enabled', 'yes' ) ) {
			return false;
		}
		if ( 'notifications' === $key && 'yes' !== $this->setting( 'notifications.enabled', 'yes' ) ) {
			return false;
		}
		if ( 'wallet' === $key && 'yes' !== $this->setting( 'wallet.enabled', 'yes' ) ) {
			return false;
		}

		if ( function_exists( 'wc_get_account_menu_items' ) ) {
			$items = wc_get_account_menu_items();
			return is_array( $items ) && isset( $items[ $key ] );
		}
		return false;
	}

	private function disabled_card( $label ) {
		$account = wc_get_page_permalink( 'myaccount' );
		return '<section class="hsw-ac__card hsw-ac__empty">'
			. '<span class="hsw-ac__empty-ic">' . $this->icon( 'lock' ) . '</span>'
			. '<h2>' . esc_html( sprintf( 'بخش «%s» غیرفعال است', $label ) ) . '</h2>'
			. '<p>' . esc_html__( 'این بخش توسط مدیر فروشگاه غیرفعال شده است. برای ادامه به پیشخوان بازگردید.', 'hamishop-elementor-widgets' ) . '</p>'
			. '<a class="hsw-ac__btn hsw-ac__btn--primary" href="' . esc_url( $account ) . '">' . $this->icon( 'arrow-right' ) . esc_html__( 'بازگشت به پیشخوان', 'hamishop-elementor-widgets' ) . '</a>'
			. '</section>';
	}

	public function render_notifications_endpoint( $value = '' ) {
		if ( ! is_user_logged_in() || ! $this->endpoint_enabled( 'notifications' ) ) {
			return;
		}
		$ctx = $this->build_context( $this->resolve_args( array() ) );
		echo $this->notices_html();
		echo $this->notifications_content( $ctx );
		$this->mark_all_notifications_read( get_current_user_id() );
	}

	public function render_wishlist_endpoint( $value = '' ) {
		if ( ! is_user_logged_in() || ! $this->endpoint_enabled( 'wishlist' ) ) {
			return;
		}
		$ctx = $this->build_context( $this->resolve_args( array() ) );
		echo $this->notices_html();
		echo $this->wishlist_content( $ctx );
	}

	public function render_wallet_endpoint( $value = '' ) {
		if ( ! is_user_logged_in() || ! $this->endpoint_enabled( 'wallet' ) ) {
			return;
		}
		$ctx = $this->build_context( $this->resolve_args( array() ) );
		echo $this->notices_html();
		echo $this->wallet_content( $ctx );
	}

	private function notifications_content( $ctx ) {
		$items  = $ctx['notifications'];
		$unread = (int) $ctx['unread_notifications'];
		$html   = '<section class="hsw-ac__card hsw-ac__notifications" aria-labelledby="hsw-ac-notifications-title">';
		$html  .= '<header class="hsw-ac__page-head"><div><h2 id="hsw-ac-notifications-title">' . esc_html__( 'اعلان‌ها', 'hamishop-elementor-widgets' ) . '</h2><p>' . esc_html__( 'آخرین رویدادهای حساب و سفارش‌های شما.', 'hamishop-elementor-widgets' ) . '</p></div>';
		if ( $unread ) {
			$html .= '<div class="hsw-ac__page-actions">';
			$html .= '<span class="hsw-ac__count-pill" data-hsw-unread-pill>' . esc_html( sprintf( '%s خوانده‌نشده', HSW_Helpers::fa_num( $unread ) ) ) . '</span>';
			$html .= '<button type="button" class="hsw-ac__btn hsw-ac__btn--ghost hsw-ac__notif-readall" data-hsw-readall>' . $this->icon( 'check' ) . esc_html__( 'خواندن همه', 'hamishop-elementor-widgets' ) . '</button>';
			$html .= '</div>';
		}
		$html .= '</header>';

		if ( $items ) {
			$html .= '<ul class="hsw-ac__notif-list">';
			foreach ( $items as $item ) {
				$url = $item['url'] ? $item['url'] : '';
				$html .= '<li class="hsw-ac__notif' . ( $item['read'] ? '' : ' is-unread' ) . '">'
					. '<span class="hsw-ac__notif-ic is-' . esc_attr( $item['type'] ) . '">' . $this->icon( $this->notification_icon( $item['type'] ) ) . '</span>'
					. '<div class="hsw-ac__notif-body"><div class="hsw-ac__notif-title"><strong>' . esc_html( $item['title'] ) . '</strong>' . ( $item['read'] ? '' : '<span class="hsw-ac__unread-dot" aria-label="' . esc_attr__( 'خوانده‌نشده', 'hamishop-elementor-widgets' ) . '"></span>' ) . '</div>'
					. '<p>' . esc_html( $item['text'] ) . '</p>'
					. '<time datetime="' . esc_attr( gmdate( 'c', $item['time'] ) ) . '">' . esc_html( $this->format_notification_time( $item['time'] ) ) . '</time></div>';
				if ( $url ) {
					$html .= '<a class="hsw-ac__notif-link" href="' . esc_url( $url ) . '">' . $this->icon( 'arrow-left' ) . '<span class="screen-reader-text">' . esc_html__( 'مشاهده', 'hamishop-elementor-widgets' ) . '</span></a>';
				}
				$html .= '</li>';
			}
			$html .= '</ul>';
		} else {
			$html .= '<div class="hsw-ac__empty hsw-ac__empty--inline"><span class="hsw-ac__empty-ic">' . $this->icon( 'bell' ) . '</span><h3>' . esc_html__( 'اعلانی ندارید', 'hamishop-elementor-widgets' ) . '</h3><p>' . esc_html__( 'رویدادهای مهم حساب شما اینجا نمایش داده می‌شوند.', 'hamishop-elementor-widgets' ) . '</p></div>';
		}
		$html .= '</section>';
		return $html;
	}

	private function localize_price_html( $html ) {
		/* Text-node only: keeps `?add-to-cart=` and data attributes intact. */
		return HSW_Helpers::price_html_fa( $html );
	}

	private function price_html( $amount ) {
		return $this->localize_price_html( wc_price( $amount ) );
	}

	public function format_date( $timestamp, $format = 'j F Y، H:i' ) {
		/* One formatter for the whole plugin: wp-parsidate → HamiShop Jalali →
		   site locale (see HSW_Helpers::format_date). */
		return HSW_Helpers::format_date( $timestamp, $format );
	}

	private function format_notification_time( $timestamp ) {
		if ( ! $timestamp ) {
			return '';
		}
		return $this->format_date( $timestamp );
	}

	private function wishlist_content( $ctx ) {
		$html  = '<section class="hsw-ac__card hsw-ac__wishlist-page" data-hsw-wishlist aria-labelledby="hsw-ac-wishlist-title">';
		$html .= '<header class="hsw-ac__page-head"><div><h2 id="hsw-ac-wishlist-title">' . esc_html__( 'علاقه‌مندی‌ها', 'hamishop-elementor-widgets' ) . '</h2><p>' . esc_html__( 'محصولاتی که در این مرورگر ذخیره کرده‌اید.', 'hamishop-elementor-widgets' ) . '</p></div><button type="button" class="hsw-ac__btn hsw-ac__btn--ghost hsw-ac__wish-clear" data-hsw-wish-clear hidden>' . $this->icon( 'trash' ) . esc_html__( 'پاک کردن همه', 'hamishop-elementor-widgets' ) . '</button></header>';
		$html .= '<div class="hsw-ac__wish-grid" data-hsw-wish-grid><div class="hsw-ac__empty hsw-ac__empty--inline"><span class="hsw-ac__empty-ic">' . $this->icon( 'heart' ) . '</span><h3>' . esc_html__( 'در حال بارگذاری علاقه‌مندی‌ها…', 'hamishop-elementor-widgets' ) . '</h3></div></div>';
		$html .= '<noscript><p class="hsw-ac__note">' . $this->icon( 'info' ) . esc_html__( 'برای مشاهده علاقه‌مندی‌ها، جاوااسکریپت مرورگر را فعال کنید.', 'hamishop-elementor-widgets' ) . '</p></noscript>';
		$html .= '<div class="hsw-ac__wish-empty" data-hsw-wish-empty hidden><span class="hsw-ac__empty-ic">' . $this->icon( 'heart' ) . '</span><h3>' . esc_html__( 'لیست علاقه‌مندی‌های شما خالی است', 'hamishop-elementor-widgets' ) . '</h3><p>' . esc_html__( 'با کلیک روی قلب محصولات، آن‌ها را برای خرید بعدی ذخیره کنید.', 'hamishop-elementor-widgets' ) . '</p><a class="hsw-ac__btn hsw-ac__btn--primary" href="' . esc_url( $ctx['shop_url'] ) . '">' . esc_html__( 'مشاهده فروشگاه', 'hamishop-elementor-widgets' ) . $this->icon( 'arrow-left' ) . '</a></div>';
		$html .= '</section>';
		return $html;
	}

	private function wallet_content( $ctx ) {
		$user_id = (int) $ctx['user_id'];
		$balance = $this->wallet_balance( $user_id );
		$spent   = function_exists( 'wc_get_customer_total_spent' ) ? (float) wc_get_customer_total_spent( $user_id ) : 0;
		$points  = isset( $ctx['loyalty']['points'] ) ? (int) $ctx['loyalty']['points'] : 0;
		$label   = (string) $this->setting( 'wallet.label', 'کیف پول' );
		$min     = (int) $this->setting( 'wallet.min_topup', 50000 );
		$loyalty = ( 'yes' === $ctx['opts']['loyalty']['enabled'] );
		$per_pt  = (int) $this->setting( 'wallet.point_value', 100 );

		$html  = '<div class="hsw-ac__wallet-grid">';
		$html .= '<section class="hsw-ac__wallet-main" aria-labelledby="hsw-ac-wallet-title">';
		$html .= '<div class="hsw-ac__wallet-ic">' . $this->icon( 'wallet' ) . '</div>';
		$html .= '<div class="hsw-ac__wallet-copy"><span>' . esc_html( $label ) . '</span><h2 id="hsw-ac-wallet-title" data-hsw-wallet-balance>' . wp_kses_post( $this->price_html( $balance ) ) . '</h2><p>' . esc_html( $this->setting( 'wallet.note', '' ) ) . '</p></div>';
		$html .= '</section>';

		$html .= '<div class="hsw-ac__wallet-mini-grid">';
		$html .= '<div class="hsw-ac__card hsw-ac__wallet-mini"><span class="hsw-ac__wallet-mini-ic is-success">' . $this->icon( 'shopping-bag' ) . '</span><div><span>' . esc_html__( 'مجموع خرید', 'hamishop-elementor-widgets' ) . '</span><strong>' . wp_kses_post( $this->price_html( $spent ) ) . '</strong></div></div>';
		if ( $loyalty ) {
			$html .= '<div class="hsw-ac__card hsw-ac__wallet-mini"><span class="hsw-ac__wallet-mini-ic is-accent">' . $this->icon( 'gift' ) . '</span><div><span>' . esc_html( $ctx['opts']['loyalty']['label'] ) . '</span><strong>' . esc_html( HSW_Helpers::fa_num( $points ) ) . '</strong></div></div>';
		}
		$html .= '</div>';

		/* ---------- top-up + points ---------- */
		$html .= '<div class="hsw-ac__wallet-actions">';

		$topup   = class_exists( 'HSW_Wallet_Topup' ) ? HSW_Wallet_Topup::settings() : array( 'mode' => 'card', 'gateway' => '', 'card' => '', 'owner' => '', 'bank' => '', 'min' => $min );
		$online  = ( 'gateway' === $topup['mode'] );
		$cards   = ( 'card' === $topup['mode'] );
		$options = $online ? HSW_Wallet_Topup::gateways() : array();

		$html .= '<section class="hsw-ac__card hsw-ac__wallet-panel" data-hsw-wallet-panel>';
		$html .= '<h3>' . $this->icon( 'add' ) . esc_html__( 'شارژ کیف پول', 'hamishop-elementor-widgets' ) . '</h3>';

		if ( $online ) {
			$html .= '<p class="hsw-ac__wallet-hint">' . esc_html__( 'مبلغ را وارد کنید و از طریق درگاه پرداخت کنید؛ موجودی پس از تأیید پرداخت اضافه می‌شود.', 'hamishop-elementor-widgets' ) . '</p>';
		} else {
			$html .= '<p class="hsw-ac__wallet-hint">' . esc_html__( 'مبلغ را واریز کنید، شمارهٔ پیگیری را ثبت کنید تا پس از تایید پشتیبانی موجودی اضافه شود.', 'hamishop-elementor-widgets' ) . '</p>';
		}

		if ( $cards && ( '' !== trim( (string) $topup['card'] ) || '' !== trim( (string) $topup['owner'] ) ) ) {
			$html .= '<div class="hsw-ac__wallet-card">';
			if ( '' !== trim( (string) $topup['card'] ) ) {
				$html .= '<div><span>' . esc_html__( 'شماره کارت', 'hamishop-elementor-widgets' ) . '</span><strong dir="ltr">' . esc_html( HSW_Helpers::fa_num( $topup['card'] ) ) . '</strong></div>';
			}
			if ( '' !== trim( (string) $topup['owner'] ) ) {
				$html .= '<div><span>' . esc_html__( 'به نام', 'hamishop-elementor-widgets' ) . '</span><strong>' . esc_html( $topup['owner'] ) . '</strong></div>';
			}
			if ( '' !== trim( (string) $topup['bank'] ) ) {
				$html .= '<div><span>' . esc_html__( 'بانک', 'hamishop-elementor-widgets' ) . '</span><strong>' . esc_html( $topup['bank'] ) . '</strong></div>';
			}
			$html .= '</div>';
		}

		/*
		 * Online mode with no usable gateway has no button to press: COD is
		 * blocked on purpose, and a form that always fails is worse than a
		 * clear note. The form is only rendered when the shop can charge.
		 */
		$can_charge = ! $online || count( $options ) > 0;

		if ( $can_charge ) {
			$html .= '<form class="hsw-ac__wallet-form" ' . ( $online ? 'data-hsw-wallet-pay' : 'data-hsw-wallet-topup' ) . '>';
			$html .= '<label class="hsw-ac__wallet-label" for="hsw-wallet-amount">' . esc_html__( 'مبلغ (تومان)', 'hamishop-elementor-widgets' ) . '</label>';
			$html .= '<input class="hsw-ac__wallet-input" id="hsw-wallet-amount" name="amount" type="text" inputmode="numeric" value="' . esc_attr( HSW_Helpers::fa_num( number_format( max( (int) $topup['min'], 100000 ) ) ) ) . '" dir="ltr">';

			if ( $online && count( $options ) > 1 ) {
				$html .= '<label class="hsw-ac__wallet-label" for="hsw-wallet-gateway">' . esc_html__( 'درگاه پرداخت', 'hamishop-elementor-widgets' ) . '</label>';
				$html .= '<select class="hsw-ac__wallet-input" id="hsw-wallet-gateway" name="gateway">';
				foreach ( $options as $gid => $gtitle ) {
					$html .= '<option value="' . esc_attr( $gid ) . '"' . selected( $gid, (string) $topup['gateway'], false ) . '>' . esc_html( $gtitle ) . '</option>';
				}
				$html .= '</select>';
			}

			if ( ! $online ) {
				$html .= '<label class="hsw-ac__wallet-label" for="hsw-wallet-note">' . esc_html__( 'شماره پیگیری / توضیح', 'hamishop-elementor-widgets' ) . '</label>';
				$html .= '<input class="hsw-ac__wallet-input" id="hsw-wallet-note" name="note" type="text">';
			}

			$html .= '<p class="hsw-ac__wallet-msg" data-hsw-wallet-msg role="alert" hidden></p>';
			$html .= '<button type="submit" class="hsw-ac__btn hsw-ac__btn--primary">'
				. $this->icon( 'wallet' )
				. ( $online ? esc_html__( 'پرداخت و شارژ', 'hamishop-elementor-widgets' ) : esc_html__( 'ثبت فیش و درخواست شارژ', 'hamishop-elementor-widgets' ) )
				. '</button>';
			$html .= '</form>';
		}

		if ( $online && 0 === count( $options ) ) {
			$html .= '<p class="hsw-ac__wallet-empty">' . esc_html__( 'هیچ درگاه پرداخت آنلاینی روی سایت فعال نیست؛ برای شارژ کیف پول با پشتیبانی تماس بگیرید.', 'hamishop-elementor-widgets' ) . '</p>';
		}

		$html .= '</section>';

		if ( $loyalty ) {
			$available = class_exists( 'HSW_Wallet' ) ? HSW_Wallet::points_available( $user_id ) : 0;
			$html     .= '<section class="hsw-ac__card hsw-ac__wallet-panel">';
			$html     .= '<h3>' . $this->icon( 'gift' ) . esc_html__( 'تبدیل امتیاز به موجودی', 'hamishop-elementor-widgets' ) . '</h3>';
			$html     .= '<p class="hsw-ac__wallet-hint">' . esc_html(
				sprintf(
					/* translators: 1: points per unit, 2: unit amount. */
					__( 'هر امتیاز %1$s تومان است. امتیاز قابل تبدیل شما: %2$s', 'hamishop-elementor-widgets' ),
					HSW_Helpers::fa_num( number_format( $per_pt ) ),
					HSW_Helpers::fa_num( $available )
				)
			) . '</p>';

			if ( $available > 0 ) {
				$html .= '<form class="hsw-ac__wallet-form" data-hsw-wallet-convert>';
				$html .= '<label class="hsw-ac__wallet-label" for="hsw-wallet-points">' . esc_html__( 'تعداد امتیاز', 'hamishop-elementor-widgets' ) . '</label>';
				$html .= '<input class="hsw-ac__wallet-input" id="hsw-wallet-points" name="points" type="text" inputmode="numeric" value="' . esc_attr( HSW_Helpers::fa_num( $available ) ) . '" dir="ltr">';
				$html .= '<p class="hsw-ac__wallet-msg" data-hsw-wallet-msg role="alert" hidden></p>';
				$html .= '<button type="submit" class="hsw-ac__btn hsw-ac__btn--primary">' . $this->icon( 'savings' ) . esc_html__( 'تبدیل به کیف پول', 'hamishop-elementor-widgets' ) . '</button>';
				$html .= '</form>';
			} else {
				$html .= '<p class="hsw-ac__wallet-empty">' . esc_html__( 'هنوز امتیاز قابل تبدیلی ندارید.', 'hamishop-elementor-widgets' ) . '</p>';
			}
			$html .= '</section>';
		}

		$html .= '</div>';

		/* ---------- statement ---------- */
		$entries = class_exists( 'HSW_Wallet' ) ? HSW_Wallet::entries( $user_id, 10 ) : array();

		$html .= '<section class="hsw-ac__card hsw-ac__wallet-ledger">';
		$html .= '<h3>' . $this->icon( 'receipt' ) . esc_html__( 'آخرین تراکنش‌ها', 'hamishop-elementor-widgets' ) . '</h3>';

		if ( ! $entries ) {
			$html .= '<p class="hsw-ac__wallet-empty">' . esc_html__( 'هنوز تراکنشی ثبت نشده است.', 'hamishop-elementor-widgets' ) . '</p>';
		} else {
			$html .= '<table class="hsw-ac__ledger"><thead><tr>';
			$html .= '<th>' . esc_html__( 'تاریخ', 'hamishop-elementor-widgets' ) . '</th>';
			$html .= '<th>' . esc_html__( 'شرح', 'hamishop-elementor-widgets' ) . '</th>';
			$html .= '<th>' . esc_html__( 'مبلغ', 'hamishop-elementor-widgets' ) . '</th>';
			$html .= '<th>' . esc_html__( 'موجودی', 'hamishop-elementor-widgets' ) . '</th>';
			$html .= '</tr></thead><tbody>';

			foreach ( $entries as $row ) {
				$amount = (float) $row->amount;
				$when   = strtotime( (string) $row->created_at );
				$html  .= '<tr>';
				$html  .= '<td class="hsw-ac__ledger-date">' . esc_html( $when ? $this->format_date( $when, 'j F Y' ) : '—' ) . '</td>';
				$html  .= '<td>' . esc_html( '' !== (string) $row->note ? (string) $row->note : $this->ledger_kind_label( (string) $row->kind ) ) . '</td>';
				$html  .= '<td class="hsw-ac__ledger-amount ' . ( $amount < 0 ? 'is-out' : 'is-in' ) . '">' . ( $amount < 0 ? '− ' : '+ ' ) . wp_kses_post( $this->price_html( abs( $amount ) ) ) . '</td>';
				$html  .= '<td class="hsw-ac__ledger-balance">' . wp_kses_post( $this->price_html( (float) $row->balance_after ) ) . '</td>';
				$html  .= '</tr>';
			}
			$html .= '</tbody></table>';
		}

		$html .= '</section></div>';

		return $html;
	}

	/**
	 * Human label for a ledger row kind.
	 *
	 * @param string $kind Kind key.
	 * @return string
	 */
	private function ledger_kind_label( $kind ) {
		$map = array(
			'topup'  => __( 'شارژ کیف پول', 'hamishop-elementor-widgets' ),
			'admin'  => __( 'شارژ توسط پشتیبانی', 'hamishop-elementor-widgets' ),
			'points' => __( 'تبدیل امتیاز', 'hamishop-elementor-widgets' ),
			'spend'  => __( 'پرداخت از کیف پول', 'hamishop-elementor-widgets' ),
			'refund' => __( 'بازگشت وجه', 'hamishop-elementor-widgets' ),
		);

		return isset( $map[ $kind ] ) ? $map[ $kind ] : __( 'تراکنش', 'hamishop-elementor-widgets' );
	}

	/* ---------------------------------------------------------------------
	 * Notifications, wallet and loyalty
	 * ------------------------------------------------------------------ */

	public function notifications( $user_id ) {
		$list = get_user_meta( $user_id, '_hsw_notifications', true );
		if ( ! is_array( $list ) ) {
			return array();
		}
		$out = array();
		foreach ( $list as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$out[] = array(
				'id'    => sanitize_key( $item['id'] ?? '' ),
				'type'  => sanitize_key( $item['type'] ?? 'system' ),
				'title' => sanitize_text_field( $item['title'] ?? '' ),
				'text'  => sanitize_textarea_field( $item['text'] ?? '' ),
				'url'   => esc_url_raw( $item['url'] ?? '' ),
				'time'  => absint( $item['time'] ?? 0 ),
				'read'  => ! empty( $item['read'] ),
			);
		}
		usort(
			$out,
			function ( $a, $b ) {
				return $b['time'] <=> $a['time'];
			}
		);
		return $out;
	}

	public function add_notification( $user_id, $args ) {
		$user_id = absint( $user_id );
		if ( ! $user_id || 'yes' !== $this->setting( 'notifications.enabled', 'yes' ) ) {
			return false;
		}
		$args = wp_parse_args(
			$args,
			array(
				'type'  => 'system',
				'title' => '',
				'text'  => '',
				'url'   => '',
			)
		);
		$title = sanitize_text_field( $args['title'] );
		if ( '' === $title ) {
			return false;
		}

		$list = get_user_meta( $user_id, '_hsw_notifications', true );
		$list = is_array( $list ) ? $list : array();
		array_unshift(
			$list,
			array(
				'id'    => wp_generate_uuid4(),
				'type'  => sanitize_key( $args['type'] ),
				'title' => $title,
				'text'  => sanitize_textarea_field( $args['text'] ),
				'url'   => esc_url_raw( $args['url'] ),
				'time'  => time(),
				'read'  => false,
			)
		);
		$max  = max( 10, absint( $this->setting( 'notifications.max', 50 ) ) );
		$list = array_slice( $list, 0, $max );
		update_user_meta( $user_id, '_hsw_notifications', $list );
		return true;
	}

	public function unread_notifications_count( $user_id ) {
		$count = 0;
		foreach ( $this->notifications( $user_id ) as $item ) {
			if ( empty( $item['read'] ) ) {
				$count++;
			}
		}
		return $count;
	}

	public function mark_all_notifications_read( $user_id ) {
		$list = get_user_meta( $user_id, '_hsw_notifications', true );
		if ( ! is_array( $list ) ) {
			return;
		}
		$changed = false;
		foreach ( $list as $index => $item ) {
			if ( is_array( $item ) && empty( $item['read'] ) ) {
				$list[ $index ]['read'] = true;
				$changed = true;
			}
		}
		if ( $changed ) {
			update_user_meta( $user_id, '_hsw_notifications', $list );
		}
	}

	private function notification_icon( $type ) {
		$map = array(
			'order'   => 'package',
			'ticket'  => 'support',
			'welcome' => 'spark',
			'wallet'  => 'wallet',
			'account' => 'user',
			'system'  => 'bell',
		);
		return isset( $map[ $type ] ) ? $map[ $type ] : 'bell';
	}

	public function on_order_status_changed( $order_id, $from, $to, $order ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}
		$user_id = $order->get_user_id();
		$statuses = $this->setting( 'notifications.order_statuses', array() );
		if ( ! $user_id || ! in_array( $to, (array) $statuses, true ) ) {
			return;
		}
		$status = wc_get_order_status_name( $to );
		$this->add_notification(
			$user_id,
			array(
				'type'  => 'order',
				'title' => sprintf( 'وضعیت سفارش #%s تغییر کرد', $order->get_order_number() ),
				'text'  => sprintf( 'سفارش شما اکنون «%s» است.', $status ),
				'url'   => $order->get_view_order_url(),
			)
		);
	}

	public function on_customer_created( $customer_id, $new_customer_data = array(), $password_generated = false ) {
		if ( 'yes' !== $this->setting( 'notifications.welcome', 'yes' ) ) {
			return;
		}
		$this->add_notification(
			$customer_id,
			array(
				'type'  => 'welcome',
				'title' => sprintf( 'به %s خوش آمدید', class_exists( 'HSW_Branding' ) ? HSW_Branding::name() : get_bloginfo( 'name' ) ),
				'text'  => 'حساب کاربری شما ساخته شد. از این پس می‌توانید سفارش‌ها و اطلاعات خود را از این پنل مدیریت کنید.',
				'url'   => wc_get_page_permalink( 'myaccount' ),
			)
		);
	}

	public function wallet_balance( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		/* The wallet table is the only source; the legacy meta is a frozen snapshot. */
		$balance = class_exists( 'HSW_Wallet' ) ? HSW_Wallet::balance( $user_id ) : 0.0;
		return (float) apply_filters( 'hsw_account_wallet_balance', $balance, $user_id );
	}

	public function loyalty_points( $user_id = 0, $opts = null ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$rate    = (int) ( is_array( $opts ) ? $opts['loyalty']['rate'] : $this->setting( 'loyalty.rate', 10000 ) );
		$rate    = max( 1, $rate );
		$spent   = function_exists( 'wc_get_customer_total_spent' ) ? (float) wc_get_customer_total_spent( $user_id ) : 0;
		$points  = (int) floor( $spent / $rate );
		return (int) apply_filters( 'hsw_account_loyalty_points', $points, $user_id, $spent );
	}

	/* ---------------------------------------------------------------------
	 * Wishlist REST
	 * ------------------------------------------------------------------ */

	public function register_rest_routes() {
		register_rest_route(
			'hsw/v1',
			'/account/wishlist',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_wishlist' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'ids' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'hsw/v1',
			'/account/notifications/read',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_notifications_read' ),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);
	}

	/**
	 * Mark every notification read.
	 *
	 * One meta write, no per-item update: the whole list lives in a single
	 * user-meta row, so "read all" is the same cost as "read one".
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_notifications_read() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new WP_Error( 'hsw_auth', __( 'ابتدا وارد حساب خود شوید.', 'hamishop-elementor-widgets' ), array( 'status' => 401 ) );
		}

		$list = get_user_meta( $user_id, '_hsw_notifications', true );
		$list = is_array( $list ) ? $list : array();

		foreach ( $list as $index => $item ) {
			if ( is_array( $item ) ) {
				$list[ $index ]['read'] = true;
			}
		}

		update_user_meta( $user_id, '_hsw_notifications', $list );

		return rest_ensure_response(
			array(
				'ok'     => true,
				'unread' => 0,
			)
		);
	}

	public function rest_wishlist( $request ) {
		$raw = (string) $request->get_param( 'ids' );
		$ids = preg_split( '/[,\s]+/', $raw );
		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
		$ids = array_slice( $ids, 0, 50 );

		$html      = '';
		$count     = 0;
		$valid_ids = array();
		foreach ( $ids as $id ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : false;
			if ( ! $product || 'publish' !== get_post_status( $id ) ) {
				continue;
			}
			/* Public route: never expose hidden or password-protected products. */
			if ( ! $product->is_visible() || '' !== (string) get_post_field( 'post_password', $id ) ) {
				continue;
			}
			$valid_ids[] = $id;
			$html       .= $this->wishlist_card_html( $product );
			$count++;
		}
		return rest_ensure_response(
			array(
				'html'  => $html,
				'count' => $count,
				'ids'   => $valid_ids,
			)
		);
	}

	private function wishlist_card_html( $product ) {
		$html  = '<article class="hsw-ac__wish">';
		$html .= '<a class="hsw-ac__wish-media" href="' . esc_url( $product->get_permalink() ) . '">' . $product->get_image( 'woocommerce_thumbnail', array( 'class' => 'hsw-ac__wish-img', 'loading' => 'lazy', 'decoding' => 'async' ) ) . '</a>';
		$html .= '<div class="hsw-ac__wish-body">';
		$html .= '<h3><a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a></h3>';
		$html .= '<div class="hsw-ac__wish-price">' . wp_kses_post( $this->localize_price_html( $product->get_price_html() ) ) . '</div>';
		$html .= '<div class="hsw-ac__wish-actions"><a class="hsw-ac__btn hsw-ac__btn--ghost" href="' . esc_url( $product->get_permalink() ) . '">' . esc_html__( 'مشاهده محصول', 'hamishop-elementor-widgets' ) . '</a>';
		$html .= '<button type="button" class="hsw-ac__wish-remove" data-hsw-wish-remove="' . esc_attr( $product->get_id() ) . '" aria-label="' . esc_attr__( 'حذف از علاقه‌مندی‌ها', 'hamishop-elementor-widgets' ) . '">' . $this->icon( 'trash' ) . '</button></div>';
		$html .= '</div></article>';
		return $html;
	}

	/* ---------------------------------------------------------------------
	 * Authentication
	 * ------------------------------------------------------------------ */

	/**
	 * Whether authentication is handled by an SMS/OTP login plugin.
	 *
	 * The check is deliberately conservative: SMS notification plugins are
	 * ignored, while Digits/OTP/mobile-login integrations are detected by
	 * plugin slug or their public API. A site can override the result with
	 * the `hsw_sms_login_active` filter.
	 */
	public function sms_login_active() {
		$active  = false;
		$plugins = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$network = (array) get_site_option( 'active_sitewide_plugins', array() );
			$plugins = array_merge( $plugins, array_keys( $network ) );
		}

		$pattern = '/(digits|otp[-_ ]?(?:login|auth|sms|woocommerce|account|user|mobile|phone)|(?:woocommerce|wp|wordpress)[-_ ]?otp|sms[-_ ]?otp|otp[-_ ]?sms|login[-_ ]?(?:with[-_ ]?)?(?:sms|otp|mobile|phone)|(?:sms|mobile|phone)[-_ ]?(?:login|auth|authentication)|(?:ورود|ثبت\s*نام|لاگین).{0,16}پیامک|پیامک.{0,16}(?:ورود|ثبت\s*نام|لاگین))/iu';
		foreach ( $plugins as $plugin ) {
			if ( ! is_string( $plugin ) ) {
				continue;
			}
			$haystack = $plugin;
			$file     = WP_PLUGIN_DIR . '/' . $plugin;
			if ( is_readable( $file ) ) {
				$headers  = get_file_data( $file, array( 'name' => 'Plugin Name' ) );
				$haystack .= ' ' . ( isset( $headers['name'] ) ? $headers['name'] : '' );
			}
			if ( preg_match( $pattern, $haystack ) ) {
				$active = true;
				break;
			}
		}

		if ( ! $active ) {
			foreach ( array( 'Digits', 'DIGITS_OTP', 'WP_OTP_Login', 'WooCommerce_OTP_Login', 'SMS_Login', 'OTP_Login', 'Mobile_Login' ) as $class ) {
				if ( class_exists( $class ) ) {
					$active = true;
					break;
				}
			}
		}

		if ( ! $active ) {
			foreach ( array( 'digits_login_form', 'sms_login_form', 'otp_login_form', 'wp_sms_login_form', 'woocommerce_sms_login_form' ) as $function ) {
				if ( function_exists( $function ) ) {
					$active = true;
					break;
				}
			}
		}

		if ( ! $active ) {
			foreach ( array( 'digits_login', 'digits_otp', 'sms_login', 'otp_login', 'wp_sms_login', 'woocommerce_sms_login' ) as $shortcode ) {
				if ( shortcode_exists( $shortcode ) ) {
					$active = true;
					break;
				}
			}
		}

		return (bool) apply_filters( 'hsw_sms_login_active', $active );
	}

	private function delegated_auth_output( $opts ) {
		if ( ! empty( $opts['editor'] ) ) {
			return '<div class="hsw-ac hsw-ac--auth hsw-ac--editor"><div class="hsw-ac__editor-note">' . $this->icon( 'info' ) . '<span>' . esc_html__( 'افزونه ورود پیامکی فعال است؛ فرم ورود و ثبت‌نام این پنل نمایش داده نمی‌شود و ورود به‌صورت کامل توسط افزونه پیامکی مدیریت می‌شود.', 'hamishop-elementor-widgets' ) . '</span></div></div>';
		}

		$shortcode = (string) apply_filters( 'hsw_delegated_auth_shortcode', '' );
		if ( '' !== trim( $shortcode ) ) {
			$html = trim( (string) do_shortcode( $shortcode ) );
			if ( '' !== $html ) {
				return $html;
			}
		}

		$candidates = (array) apply_filters(
			'hsw_sms_login_shortcodes',
			array( 'sms_login', 'otp_login', 'digits_login', 'woocommerce_sms_login', 'wp_sms_login', 'mobile_login', 'phone_login', 'digits_otp' )
		);
		foreach ( $candidates as $candidate ) {
			$candidate = sanitize_key( $candidate );
			if ( '' === $candidate || ! shortcode_exists( $candidate ) ) {
				continue;
			}
			$html = trim( (string) do_shortcode( '[' . $candidate . ']' ) );
			if ( '' !== $html ) {
				return $html;
			}
		}

		if ( function_exists( 'WC' ) && shortcode_exists( 'woocommerce_my_account' ) ) {
			$html = trim( (string) do_shortcode( '[woocommerce_my_account]' ) );
			if ( '' !== $html ) {
				return $html;
			}
		}

		return '<div class="hsw-ac hsw-ac--notice"><p>' . esc_html__( 'ورود و ثبت‌نام از طریق افزونه ورود پیامکی انجام می‌شود.', 'hamishop-elementor-widgets' ) . '</p></div>';
	}

	private function render_auth_view( $lost, $opts ) {
		if ( $this->auth && $this->auth->is_2fa_challenge() && ! $lost ) {
			return $this->auth->render_2fa_challenge( $opts );
		}
		if ( ( ! $this->auth || ! $this->auth->enabled() ) && $this->sms_login_active() ) {
			return $this->delegated_auth_output( $opts );
		}

		$lost          = (bool) $lost;
		$show_register = ! $lost && 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) && 'yes' === $opts['auth']['show_register'];
		$active        = 'login';
		if ( $show_register && ( isset( $_POST['register'] ) || ( isset( $_GET['hsw_tab'] ) && 'register' === $_GET['hsw_tab'] ) ) ) {
			$active = 'register';
		}
		$notices = $this->notices_html();

		$html  = '<div class="hsw-ac hsw-ac--auth' . ( ! empty( $opts['editor'] ) ? ' hsw-ac--editor' : '' ) . '" data-hsw-auth data-active="' . esc_attr( $active ) . '">';
		$html .= '<div class="hsw-ac-auth">';
		$html .= $this->auth_brand_panel( $opts );
		$html .= '<div class="hsw-ac-auth__panel-wrap">';
		if ( ! empty( $opts['editor'] ) ) {
			$html .= '<div class="hsw-ac__editor-note">' . $this->icon( 'info' ) . '<span>' . esc_html__( 'پیش‌نمایش فرم ورود؛ در سایت واقعی، کاربران واردشده پنل حساب را می‌بینند.', 'hamishop-elementor-widgets' ) . '</span></div>';
		}
		$html .= $notices;
		if ( isset( $_GET['hsw_2fa_error'] ) ) {
			$html .= '<div class="hsw-ac-auth__notice is-error">' . $this->icon( 'info' ) . '<span>' . esc_html__( 'ارسال کد دوعاملی ناموفق بود. لطفاً دوباره وارد شوید یا با پشتیبانی تماس بگیرید.', 'hamishop-elementor-widgets' ) . '</span></div>';
		}
		$html .= '<div class="hsw-ac-auth__panel">';
		$html .= '<a class="hsw-ac-auth__home" href="' . esc_url( home_url( '/' ) ) . '">' . $this->icon( 'arrow-right' ) . esc_html__( 'بازگشت به فروشگاه', 'hamishop-elementor-widgets' ) . '</a>';

		if ( $lost ) {
			$html .= '<div class="hsw-ac-auth__heading"><span class="hsw-ac-auth__eyebrow">' . esc_html__( 'بازیابی حساب', 'hamishop-elementor-widgets' ) . '</span><h1>' . esc_html__( 'بازیابی رمز عبور', 'hamishop-elementor-widgets' ) . '</h1><p>' . esc_html__( 'ایمیل یا نام کاربری خود را وارد کنید تا لینک تنظیم رمز عبور برایتان ارسال شود.', 'hamishop-elementor-widgets' ) . '</p></div>';
			$html .= '<div class="hsw-ac-auth__native woocommerce">';
			if ( class_exists( 'WC_Shortcode_My_Account' ) && method_exists( 'WC_Shortcode_My_Account', 'lost_password' ) && ( isset( $_GET['reset-link-sent'] ) || isset( $_GET['show-reset-form'] ) ) ) {
				ob_start();
				WC_Shortcode_My_Account::lost_password();
				$html .= ob_get_clean();
			} else {
				$html .= $this->lost_password_form();
			}
			$html .= '</div>';
			$html .= '<a class="hsw-ac-auth__back" href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">' . $this->icon( 'arrow-right' ) . esc_html__( 'بازگشت به ورود', 'hamishop-elementor-widgets' ) . '</a>';
		} else {
			$html .= '<div class="hsw-ac-auth__heading"'
				. ' data-login-title="' . esc_attr( $opts['auth']['login_title'] ) . '"'
				. ' data-login-sub="' . esc_attr( $opts['auth']['login_subtitle'] ) . '"'
				. ' data-register-title="' . esc_attr( $opts['auth']['register_title'] ) . '"'
				. ' data-register-sub="' . esc_attr( $opts['auth']['register_subtitle'] ) . '">'
				. '<span class="hsw-ac-auth__eyebrow">' . esc_html__( 'حساب کاربری', 'hamishop-elementor-widgets' ) . '</span>'
				. '<h1>' . esc_html( 'register' === $active ? $opts['auth']['register_title'] : $opts['auth']['login_title'] ) . '</h1>'
				. '<p>' . esc_html( 'register' === $active ? $opts['auth']['register_subtitle'] : $opts['auth']['login_subtitle'] ) . '</p></div>';

			if ( $show_register ) {
				$html .= '<div class="hsw-ac-auth__tabs" role="tablist" aria-label="' . esc_attr__( 'ورود یا ثبت‌نام', 'hamishop-elementor-widgets' ) . '">';
				$html .= '<button type="button" role="tab" class="' . ( 'login' === $active ? 'is-active' : '' ) . '" aria-selected="' . ( 'login' === $active ? 'true' : 'false' ) . '" data-hsw-auth-tab="login">' . $this->icon( 'lock' ) . esc_html__( 'ورود', 'hamishop-elementor-widgets' ) . '</button>';
				$html .= '<button type="button" role="tab" class="' . ( 'register' === $active ? 'is-active' : '' ) . '" aria-selected="' . ( 'register' === $active ? 'true' : 'false' ) . '" data-hsw-auth-tab="register">' . $this->icon( 'user' ) . esc_html__( 'ثبت‌نام', 'hamishop-elementor-widgets' ) . '</button>';
				$html .= '</div>';
			}

			$otp_on = ( $this->auth && $this->auth->enabled() );

			/*
			 * One form at a time. The password form used to sit *under* the OTP
			 * box, so both were visible and the panel read like two sign-ups.
			 * OTP is the primary path now; the classic form hides behind a
			 * switcher and comes back with one click.
			 */
			$html .= '<div class="hsw-ac-auth__forms">';
			$html .= '<div class="hsw-ac-auth__form-panel' . ( 'login' === $active ? ' is-active' : '' ) . '" data-hsw-auth-panel="login" role="tabpanel">';
			if ( $otp_on ) {
				$html .= '<div class="hsw-ac-auth__mode is-active" data-hsw-auth-mode="otp">' . $this->auth->otp_box( 'login' );
				$html .= '<button type="button" class="hsw-ac-auth__switch" data-hsw-auth-mode-switch="password">' . $this->icon( 'lock' ) . esc_html__( 'ورود با رمز عبور ثابت', 'hamishop-elementor-widgets' ) . $this->icon( 'arrow-left' ) . '</button></div>';
				$html .= '<div class="hsw-ac-auth__mode" data-hsw-auth-mode="password" hidden>' . $this->auth_login_form();
				$html .= '<button type="button" class="hsw-ac-auth__switch" data-hsw-auth-mode-switch="otp">' . $this->icon( 'shield' ) . esc_html__( 'ورود سریع با کد یکبارمصرف', 'hamishop-elementor-widgets' ) . $this->icon( 'arrow-left' ) . '</button></div>';
			} else {
				$html .= $this->auth_login_form();
			}
			$html .= '</div>';
			if ( $show_register ) {
				$html .= '<div class="hsw-ac-auth__form-panel' . ( 'register' === $active ? ' is-active' : '' ) . '" data-hsw-auth-panel="register" role="tabpanel">';
				if ( $otp_on ) {
					$html .= '<div class="hsw-ac-auth__mode is-active" data-hsw-auth-mode="otp">' . $this->auth->otp_box( 'register' );
					$html .= '<button type="button" class="hsw-ac-auth__switch" data-hsw-auth-mode-switch="password">' . $this->icon( 'lock' ) . esc_html__( 'ثبت‌نام با رمز عبور ثابت', 'hamishop-elementor-widgets' ) . $this->icon( 'arrow-left' ) . '</button></div>';
					$html .= '<div class="hsw-ac-auth__mode" data-hsw-auth-mode="password" hidden>' . $this->auth_register_form();
					$html .= '<button type="button" class="hsw-ac-auth__switch" data-hsw-auth-mode-switch="otp">' . $this->icon( 'shield' ) . esc_html__( 'ثبت‌نام سریع با کد یکبارمصرف', 'hamishop-elementor-widgets' ) . $this->icon( 'arrow-left' ) . '</button></div>';
				} else {
					$html .= $this->auth_register_form();
				}
				$html .= '</div>';
			}
			$html .= '</div>';

			if ( ! $show_register ) {
				$html .= '<p class="hsw-ac-auth__disabled">' . esc_html__( 'ثبت‌نام در این فروشگاه غیرفعال است.', 'hamishop-elementor-widgets' ) . '</p>';
			}
		}

		$html .= '</div></div></div></div>';
		return $html;
	}

	private function auth_brand_panel( $opts ) {
		$brand_title = $opts['auth']['brand_title'];
		$brand_sub   = $opts['auth']['brand_subtitle'];
		$benefits    = $opts['auth']['benefits'];
		$brand_logo  = class_exists( 'HSW_Branding' ) ? HSW_Branding::logo_html( 'hsw-ac-auth__logo-img' ) : '';
		$brand_name  = class_exists( 'HSW_Branding' ) ? HSW_Branding::name() : get_bloginfo( 'name' );
		$brand_mark  = '' !== $brand_logo
			? '<span class="hsw-ac-auth__logo-mark hsw-ac-auth__logo-mark--img">' . $brand_logo . '</span>'
			: '<span class="hsw-ac-auth__logo-mark">' . $this->icon( 'shopping-bag' ) . '</span>';
		$html        = '<aside class="hsw-ac-auth__brand">';
		$html       .= '<div class="hsw-ac-auth__brand-glow" aria-hidden="true"></div>';
		$html       .= '<a class="hsw-ac-auth__logo" href="' . esc_url( home_url( '/' ) ) . '">' . $brand_mark . '<span>' . esc_html( $brand_name ) . '</span></a>';
		$html       .= '<div class="hsw-ac-auth__brand-copy"><span class="hsw-ac-auth__eyebrow">' . esc_html__( 'تجربه خرید بهتر', 'hamishop-elementor-widgets' ) . '</span><h2>' . esc_html( $brand_title ) . '</h2><p>' . esc_html( $brand_sub ) . '</p></div>';
		if ( 'yes' === $opts['auth']['show_benefits'] && ! empty( $benefits ) ) {
			$html .= '<ul class="hsw-ac-auth__benefits">';
			foreach ( $benefits as $benefit ) {
				$html .= '<li>' . $this->icon( 'check-circle' ) . '<span>' . esc_html( $benefit ) . '</span></li>';
			}
			$html .= '</ul>';
		}
		$html .= '<div class="hsw-ac-auth__brand-foot"><span>' . esc_html__( 'پشتیبانی واقعی، پاسخگویی سریع', 'hamishop-elementor-widgets' ) . '</span><span class="hsw-ac-auth__brand-dots" aria-hidden="true"><i></i><i></i><i></i></span></div>';
		$html .= '</aside>';
		return $html;
	}

	private function lost_password_form() {
		$account = wc_get_page_permalink( 'myaccount' );
		ob_start();
		?>
		<form method="post" class="woocommerce-ResetPassword lost_reset_password hsw-ac-form hsw-ac-lost-form" action="<?php echo esc_url( $account ); ?>">
			<?php do_action( 'woocommerce_before_lost_password_form' ); ?>
			<div class="hsw-ac-field">
				<label for="user_login"><?php esc_html_e( 'نام کاربری یا ایمیل', 'hamishop-elementor-widgets' ); ?></label>
				<div class="hsw-ac-input-wrap"><?php echo $this->icon( 'user' ); ?>
					<input class="woocommerce-Input woocommerce-Input--text input-text" type="text" name="user_login" id="user_login" autocomplete="username" required aria-required="true" placeholder="<?php esc_attr_e( 'مثلاً 0912... یا name@example.com', 'hamishop-elementor-widgets' ); ?>">
				</div>
			</div>
			<?php do_action( 'woocommerce_lostpassword_form' ); ?>
			<input type="hidden" name="wc_reset_password" value="true">
			<?php wp_nonce_field( 'lost_password', 'woocommerce-lost-password-nonce' ); ?>
			<button type="submit" class="hsw-ac__btn hsw-ac__btn--primary hsw-ac-auth__submit"><?php esc_html_e( 'ارسال لینک بازیابی', 'hamishop-elementor-widgets' ); ?><?php echo $this->icon( 'arrow-left' ); ?></button>
		</form>
		<?php do_action( 'woocommerce_after_lost_password_form' ); ?>
		<?php
		return (string) ob_get_clean();
	}

	private function auth_login_form() {
		$account = wc_get_page_permalink( 'myaccount' );
		ob_start();
		?>
		<form class="woocommerce-form woocommerce-form-login hsw-ac-form" method="post" action="<?php echo esc_url( $account ); ?>" novalidate>
			<?php do_action( 'woocommerce_login_form_start' ); ?>
			<div class="hsw-ac-field">
				<label for="hsw-ac-username"><?php esc_html_e( 'نام کاربری یا ایمیل', 'hamishop-elementor-widgets' ); ?></label>
				<div class="hsw-ac-input-wrap"><?php echo $this->icon( 'user' ); ?>
					<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="hsw-ac-username" autocomplete="username" required value="<?php echo ( ! empty( $_POST['username'] ) && is_string( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>">
				</div>
			</div>
			<div class="hsw-ac-field">
				<label for="hsw-ac-password"><?php esc_html_e( 'رمز عبور', 'hamishop-elementor-widgets' ); ?></label>
				<div class="hsw-ac-input-wrap"><?php echo $this->icon( 'lock' ); ?>
					<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="hsw-ac-password" autocomplete="current-password" required>
					<button type="button" class="hsw-ac-pass-toggle" data-hsw-pass-toggle aria-pressed="false" aria-label="<?php esc_attr_e( 'نمایش رمز عبور', 'hamishop-elementor-widgets' ); ?>"><?php echo $this->icon( 'eye' ); ?></button>
				</div>
			</div>
			<?php do_action( 'woocommerce_login_form' ); ?>
			<div class="hsw-ac-auth__row">
				<label class="hsw-ac-check"><input type="checkbox" name="rememberme" value="forever"><span><?php esc_html_e( 'مرا به خاطر بسپار', 'hamishop-elementor-widgets' ); ?></span></label>
				<a class="hsw-ac-auth__link" href="<?php echo esc_url( wc_lostpassword_url() ); ?>"><?php esc_html_e( 'رمز عبور را فراموش کرده‌اید؟', 'hamishop-elementor-widgets' ); ?></a>
			</div>
			<input type="hidden" name="redirect" value="<?php echo esc_url( $account ); ?>">
			<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
			<button type="submit" class="hsw-ac__btn hsw-ac__btn--primary hsw-ac-auth__submit" name="login" value="<?php esc_attr_e( 'ورود', 'hamishop-elementor-widgets' ); ?>"><?php esc_html_e( 'ورود به حساب', 'hamishop-elementor-widgets' ); ?><?php echo $this->icon( 'arrow-left' ); ?></button>
			<?php do_action( 'woocommerce_login_form_end' ); ?>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	private function auth_register_form() {
		$account      = wc_get_page_permalink( 'myaccount' );
		$generate_user = 'yes' === get_option( 'woocommerce_registration_generate_username' );
		$generate_pass = 'yes' === get_option( 'woocommerce_registration_generate_password' );
		ob_start();
		?>
		<form method="post" class="woocommerce-form woocommerce-form-register register hsw-ac-form" action="<?php echo esc_url( $account ); ?>" novalidate>
			<?php do_action( 'woocommerce_register_form_start' ); ?>
			<?php if ( ! $generate_user ) : ?>
				<div class="hsw-ac-field">
					<label for="hsw-ac-reg-username"><?php esc_html_e( 'نام کاربری', 'hamishop-elementor-widgets' ); ?></label>
					<div class="hsw-ac-input-wrap"><?php echo $this->icon( 'user' ); ?><input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="hsw-ac-reg-username" autocomplete="username" required></div>
				</div>
			<?php endif; ?>
			<div class="hsw-ac-field">
				<label for="hsw-ac-reg-email"><?php esc_html_e( 'ایمیل', 'hamishop-elementor-widgets' ); ?></label>
				<div class="hsw-ac-input-wrap"><?php echo $this->icon( 'mail' ); ?><input type="email" class="woocommerce-Input woocommerce-Input--email input-text" name="email" id="hsw-ac-reg-email" autocomplete="email" required></div>
			</div>
			<?php if ( ! $generate_pass ) : ?>
				<div class="hsw-ac-field">
					<label for="hsw-ac-reg-password"><?php esc_html_e( 'رمز عبور', 'hamishop-elementor-widgets' ); ?></label>
					<div class="hsw-ac-input-wrap"><?php echo $this->icon( 'lock' ); ?><input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="hsw-ac-reg-password" autocomplete="new-password" required><button type="button" class="hsw-ac-pass-toggle" data-hsw-pass-toggle aria-pressed="false" aria-label="<?php esc_attr_e( 'نمایش رمز عبور', 'hamishop-elementor-widgets' ); ?>"><?php echo $this->icon( 'eye' ); ?></button></div>
				</div>
			<?php endif; ?>
			<?php do_action( 'woocommerce_register_form' ); ?>
			<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
			<button type="submit" class="hsw-ac__btn hsw-ac__btn--primary hsw-ac-auth__submit" name="register" value="<?php esc_attr_e( 'ثبت‌نام', 'hamishop-elementor-widgets' ); ?>"><?php esc_html_e( 'ساخت حساب کاربری', 'hamishop-elementor-widgets' ); ?><?php echo $this->icon( 'arrow-left' ); ?></button>
			<?php do_action( 'woocommerce_register_form_end' ); ?>
			<?php if ( get_privacy_policy_url() ) : ?>
				<p class="hsw-ac-auth__privacy"><?php echo wp_kses_post( wc_get_privacy_policy_text( 'registration' ) ); ?></p>
			<?php endif; ?>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/* ---------------------------------------------------------------------
	 * Icons (inline SVG, zero external requests)
	 * ------------------------------------------------------------------ */

	public function icon( $name, $class = '' ) {
		$paths = array(
			'grid'         => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
			'receipt'      => '<path d="M6 2h12v20l-3-2-3 2-3-2-3 2V2Z"/><path d="M9 7h6M9 11h6M9 15h4"/>',
			'download'     => '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>',
			'location'     => '<path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
			'heart'        => '<path d="M12.62 20.81c-.34.12-.9.12-1.24 0C8.48 19.82 2 15.69 2 8.69 2 5.6 4.49 3.1 7.56 3.1c1.82 0 3.43.88 4.44 2.24 1.01-1.36 2.62-2.24 4.44-2.24C19.51 3.1 22 5.6 22 8.69c0 7-6.48 11.13-9.38 12.12Z"></path>',
			'wallet'       => '<path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/><path d="M3 7V5a2 2 0 0 1 2-2h12"/><path d="M16 12h5"/>',
			'user'         => '<path d="M12 12C14.7614 12 17 9.76142 17 7C17 4.23858 14.7614 2 12 2C9.23858 2 7 4.23858 7 7C7 9.76142 9.23858 12 12 12Z"/> <path d="M20.5901 22C20.5901 18.13 16.7402 15 12.0002 15C7.26015 15 3.41016 18.13 3.41016 22"/>',
			'logout'       => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
			'support'      => '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><path d="M4 14h3v5H5a1 1 0 0 1-1-1v-4Z"/><path d="M20 14h-3v5h2a1 1 0 0 0 1-1v-4Z"/><path d="M9 20a3 3 0 0 0 3 2h1"/>',
			'bell'         => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
			'chevron-left' => '<path d="m15 18-6-6 6-6"/>',
			'chevron-right'=> '<path d="m9 18 6-6-6-6"/>',
			'check'        => '<path d="m5 12 4 4L19 6"/>',
			'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
			'truck'        => '<path d="M3 7h11v10H3z"/><path d="M14 10h4l3 3v4h-7"/><circle cx="7" cy="19" r="2"/><circle cx="18" cy="19" r="2"/>',
			'package'      => '<path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/><path d="m4.5 7.5 7.5 4.5 7.5-4.5"/><path d="M12 12v9"/>',
			'credit-card'  => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/><path d="M7 15h3"/>',
			'home'         => '<path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/>',
			'clock'        => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			'shield'       => '<path d="M12 3 5 6v6c0 4 3 7 7 9 4-2 7-5 7-9V6l-7-3Z"/><path d="m9 12 2 2 4-4"/>',
			'arrow-left'   => '<path d="M19 12H5"/><path d="m11 18-6-6 6-6"/>',
			'arrow-right'  => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
			'edit'         => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/>',
			'plus'         => '<path d="M12 5v14M5 12h14"/>',
			'x'            => '<path d="M18 6 6 18M6 6l12 12"/>',
			'search'       => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
			'ticket'       => '<path d="M4 7a2 2 0 0 0 2-2h12a2 2 0 0 0 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 0-2 2H6a2 2 0 0 0-2-2v-3a2 2 0 0 0 0-4V7Z"/><path d="M13 5v2M13 17v2"/>',
			'gift'         => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v8h14v-8"/><path d="M12 8v12"/><path d="M12 8C9 8 7 7 7 5a2 2 0 0 1 4 0c0 1 1 3 1 3Zm0 0c3 0 5-1 5-3a2 2 0 0 0-4 0c0 1-1 3-1 3Z"/>',
			'star'         => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.2 6.4 20.2l1.1-6.2L3 9.6l6.2-.9L12 3Z"/>',
			'eye'          => '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
			'eye-off'      => '<path d="m3 3 18 18"/><path d="M10.6 6.2A10.8 10.8 0 0 1 12 6c6.5 0 10 6 10 6a17.7 17.7 0 0 1-2.3 3.1"/><path d="M6.2 6.2C3.5 8.3 2 12 2 12s3.5 6 10 6a10.6 10.6 0 0 0 4.1-.8"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
			'mail'         => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
			'phone'        => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.6 2.6a2 2 0 0 1-.5 2.1L8 9.6a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.5c.8.3 1.7.5 2.6.6a2 2 0 0 1 1.7 2Z"/>',
			'calendar'     => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
			'print'        => '<path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M6 17h12v4H6z"/>',
			'external'     => '<path d="M14 3h7v7"/><path d="m10 14 11-11"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/>',
			'spark'        => '<path d="m12 2 1.8 5.2L19 9l-5.2 1.8L12 16l-1.8-5.2L5 9l5.2-1.8L12 2Z"/><path d="m19 15 .8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15Z"/>',
			'refresh'      => '<path d="M21 12a9 9 0 1 1-2.6-6.4"/><path d="M21 3v6h-6"/>',
			'trash'        => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 15H6L5 6"/><path d="M10 11v6M14 11v6"/>',
			'lock'         => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
			'shopping-bag' => '<path d="M6 7h12l1 14H5L6 7Z"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/>',
			'alert'        => '<path d="M10.3 3.9 2.2 18a2 2 0 0 0 1.7 3h16.2a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
			'image'        => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m21 15-5-5-9 9"/>',
			'info'         => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
			'menu'         => '<path d="M4 6h16M4 12h16M4 18h16"/>',
			'settings'     => '<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"></path><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"></path>',
			'copy'         => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/>',
		);
		$path = isset( $paths[ $name ] ) ? $paths[ $name ] : $paths['grid'];
		return '<svg class="hsw-ac-ic' . ( $class ? ' ' . esc_attr( $class ) : '' ) . '" viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
	}

}
