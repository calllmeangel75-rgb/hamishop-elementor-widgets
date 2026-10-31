<?php
/**
 * External request blocker («حالت نت ملی»).
 *
 * When the national/international link is down, every server-side call to an
 * update server, licence API or CDN makes the admin wait for a timeout. Turning
 * this on short-circuits those calls with a WP_Error instead.
 *
 * Off by default: `init()` registers no hook at all until the switch is on, so
 * an inactive site pays nothing.
 *
 * Settings live in HamiShop → امنیت → «بهینه‌سازی» (`hsw_security` array).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Request_Blocker {

	/**
	 * Default hosts that only serve updates, licences, telemetry or CDNs.
	 *
	 * @return string[]
	 */
	public static function default_hosts() {
		return array(
			'elementor.com',
			'github.com',
			'githubusercontent.com',
			'yoast.com',
			'yoa.st',
			'wordpress.org',
			'w.org',
			'unyson.io',
			'siteorigin.com',
			'woocommerce.com',
			'mixpanel.com',
			'googleapis.com',
			'gstatic.com',
			'jsdelivr.net',
			'cdnjs.cloudflare.com',
			'yektanet.com',
		);
	}

	/**
	 * Register the filter only when the switch is on.
	 */
	public function __construct() {
		$s = HSW_Security::get();
		if ( 'yes' !== ( $s['block_external'] ?? 'no' ) ) {
			return;
		}
		add_filter( 'pre_http_request', array( __CLASS__, 'maybe_block' ), 10, 3 );
	}

	/**
	 * Enabled?
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$s = HSW_Security::get();
		return 'yes' === ( $s['block_external'] ?? 'no' );
	}

	/**
	 * Default hosts + the extra domains the shop owner typed (one per line).
	 *
	 * @return string[]
	 */
	public static function blocked_hosts() {
		$hosts = self::default_hosts();

		/*
		 * The saved value is an array of lines (sanitize_hosts() returns one), but
		 * an option written by an older build or by hand can still be a string.
		 * Casting an array with (string) raised an array-to-string notice and
		 * silently dropped every domain the shop owner typed.
		 */
		$raw = HSW_Security::get()['block_hosts'] ?? '';
		if ( is_array( $raw ) ) {
			$extra = array_filter( array_map( 'trim', $raw ) );
		} else {
			$split = preg_split( '/\R+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY );
			$extra = is_array( $split ) ? $split : array();
		}
		if ( $extra ) {
			$hosts = array_merge( $hosts, $extra );
		}

		/* Never lock the site out of itself. */
		$own = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( '' !== $own ) {
			$hosts[] = '';
			$hosts   = array_values( array_diff( $hosts, array( $own ) ) );
		}

		$hosts = array_unique( array_map( 'strtolower', array_map( 'trim', (array) apply_filters( 'hsw_blocked_hosts', $hosts ) ) ) );

		return array_values( array_filter( $hosts ) );
	}

	/**
	 * Hosts that must never be blocked: the shop itself and the endpoints the
	 * shop configured for its own payments and messaging.
	 *
	 * Blocking them turns «net melli» mode into a broken checkout or a shop that
	 * can no longer reach its own SMS / Telegram / WhatsApp bridge.
	 *
	 * @return string[]
	 */
	public static function allowed_hosts() {
		$hosts = array();

		$own = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( '' !== $own ) {
			$hosts[] = $own;
		}

		/* Telegram (the API base is filterable) and the WhatsApp Cloud endpoint. */
		$hosts[] = strtolower( (string) wp_parse_url( (string) apply_filters( 'hsw_telegram_api_base', 'https://api.telegram.org' ), PHP_URL_HOST ) );
		$hosts[] = 'graph.facebook.com';

		if ( class_exists( 'HSW_Account' ) && method_exists( 'HSW_Account', 'instance' ) ) {
			$account  = HSW_Account::instance();
			$configured = array(
				$account->setting( 'auth.whatsapp_bridge_url', '' ),
				$account->setting( 'wallet.sms_url', '' ),
			);
			foreach ( $configured as $url ) {
				$host = strtolower( (string) wp_parse_url( trim( (string) $url ), PHP_URL_HOST ) );
				if ( '' !== $host ) {
					$hosts[] = $host;
				}
			}
		}

		$hosts = array_values( array_filter( array_unique( $hosts ) ) );

		/* The domains of the gateways that are switched on, when WooCommerce runs. */
		if ( class_exists( 'HSW_Security' ) && method_exists( 'HSW_Security', 'gateway_hosts' ) ) {
			foreach ( HSW_Security::gateway_hosts() as $gateway_host ) {
				$hosts[] = $gateway_host;
			}
			$hosts = array_values( array_unique( $hosts ) );
		}

		return (array) apply_filters( 'hsw_allowed_hosts', $hosts );
	}

	/**
	 * Does the host (or a parent domain) match the block list?
	 *
	 * @param string $url Request URL.
	 * @return bool
	 */
	public static function host_is_blocked( $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( '' === $host ) {
			return false;
		}

		$own = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( '' !== $own && ( $host === $own || substr( $host, -strlen( '.' . $own ) ) === '.' . $own ) ) {
			return false;
		}

		/* The shop's own services win over any block entry, defaults included. */
		foreach ( self::allowed_hosts() as $allowed ) {
			if ( '' === $allowed ) {
				continue;
			}
			if ( $host === $allowed || substr( $host, -strlen( '.' . $allowed ) ) === '.' . $allowed ) {
				return false;
			}
		}

		foreach ( self::blocked_hosts() as $blocked ) {
			if ( '' === $blocked ) {
				continue;
			}
			if ( $host === $blocked || substr( $host, -strlen( '.' . $blocked ) ) === '.' . $blocked ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Short-circuit a blocked request.
	 *
	 * @param false|array|WP_Error $pre  Pre-empted value.
	 * @param array                $args Request args (unused).
	 * @param string               $url  Request URL.
	 * @return false|array|WP_Error
	 */
	public static function maybe_block( $pre, $args, $url ) {
		if ( false !== $pre ) {
			return $pre;
		}
		if ( self::host_is_blocked( $url ) ) {
			return new WP_Error(
				'hsw_http_blocked',
				/* translators: %s: request URL. */
				sprintf( __( 'درخواست خارجی توسط «قفل درخواست‌های خارجی» مسدود شد: %s', 'hamishop-elementor-widgets' ), $url )
			);
		}
		return $pre;
	}

	/**
	 * Clean a textarea of domains: one per line, no scheme, no path.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_hosts( $value ) {
		$clean = array();
		foreach ( preg_split( '/\R+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY ) as $line ) {
			$line = strtolower( trim( $line ) );
			$line = preg_replace( '#^https?://#', '', $line );
			$line = preg_replace( '#[/:?].*$#', '', $line );
			if ( '' !== $line && preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}$/', $line ) ) {
				$clean[] = $line;
			}
		}
		return implode( "\n", array_unique( $clean ) );
	}
}
