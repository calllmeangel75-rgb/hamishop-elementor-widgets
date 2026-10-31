<?php
/**
 * Pre-live batch 3 — checkout routing, security cleanups, request blocker.
 *
 * QA-only; the release ZIP excludes tests/ (.gitattributes export-ignore). Run: php tests/qa/prelive-routing.php
 * HSW_PRELIVE_ROUTE / _SEC / _BLOCKER point at older copies to prove the red.
 */

require_once __DIR__ . '/prelive-lib.php';

/* ---- extra stubs this file needs ------------------------------------ */

class WP_Post {
	public $ID = 0;
	public $post_status = 'publish';
	public $post_type = 'page';
}

$GLOBALS['hsw_pages']    = array();
$GLOBALS['hsw_post_meta'] = array();
$GLOBALS['hsw_dequeued']  = array();
$GLOBALS['hsw_has_block'] = false;

function get_page_by_path( $path, $output = OBJECT, $post_type = 'page' ) {
	foreach ( $GLOBALS['hsw_pages'] as $id => $page ) {
		if ( $page['slug'] === $path && 'publish' === $page['status'] ) {
			$post = new WP_Post();
			$post->ID          = (int) $id;
			$post->post_status = 'publish';
			return $post;
		}
	}
	return null;
}
function get_post_status( $id ) {
	return isset( $GLOBALS['hsw_pages'][ $id ] ) ? $GLOBALS['hsw_pages'][ $id ]['status'] : false;
}
function get_post_meta( $id, $key = '', $single = false ) {
	$v = isset( $GLOBALS['hsw_post_meta'][ $id ][ $key ] ) ? $GLOBALS['hsw_post_meta'][ $id ][ $key ] : '';
	return $single ? $v : ( '' === $v ? array() : array( $v ) );
}
function is_page( $id = 0 ) {
	return isset( $GLOBALS['hsw_current_page'] ) && (int) $GLOBALS['hsw_current_page'] === (int) $id;
}
function has_block( $block, $post = null ) {
	return ! empty( $GLOBALS['hsw_has_block'] );
}
function wp_style_is( $handle, $phase = 'enqueued' ) { return true; }
function wp_dequeue_style( $handle ) { $GLOBALS['hsw_dequeued'][] = $handle; }
function wp_dequeue_script( $handle ) { $GLOBALS['hsw_dequeued'][] = $handle; }
function wp_is_application_passwords_available() { return true; }
function is_admin() { return ! empty( $GLOBALS['hsw_is_admin'] ); }
function status_header( $code ) { $GLOBALS['hsw_status'] = (int) $code; }
function get_post_field( $field, $id ) {
	if ( 'post_content' === $field ) {
		return isset( $GLOBALS['hsw_post_meta'][ $id ]['post_content'] ) ? $GLOBALS['hsw_post_meta'][ $id ]['post_content'] : '';
	}
	return '';
}

/* The blocker reads the account settings for its allowlist. */
class HSW_Account {
	private static $i = null;
	public static function instance() {
		if ( null === self::$i ) {
			self::$i = new self();
		}
		return self::$i;
	}
	public function setting( $path, $fallback = '' ) {
		$s = (array) get_option( 'hsw_account_settings', array() );
		list( $group, $key ) = array_pad( explode( '.', $path ), 2, '' );
		return isset( $s[ $group ][ $key ] ) ? $s[ $group ][ $key ] : $fallback;
	}
}

function r_route_file( $env, $file ) {
	$alt = getenv( $env );
	return $alt ? $alt : dirname( __DIR__, 2 ) . '/includes/' . $file;
}

require_once r_route_file( 'HSW_PRELIVE_ROUTE', 'class-hsw-complete-route.php' );
require_once r_route_file( 'HSW_PRELIVE_SEC', 'class-hsw-security.php' );
require_once r_route_file( 'HSW_PRELIVE_BLOCKER', 'class-hsw-request-blocker.php' );

/* ---- helpers --------------------------------------------------------- */

function r_reset() {
	HSW_Prelive_Store::reset();
	$GLOBALS['hsw_pages']      = array();
	$GLOBALS['hsw_post_meta']  = array();
	$GLOBALS['hsw_dequeued']   = array();
	$GLOBALS['hsw_has_block']  = false;
	$GLOBALS['hsw_current_page'] = 0;
	$GLOBALS['hsw_current_user'] = 0;
	$_GET = array();
}

function r_page( $id, $slug, $elementor_data = '' ) {
	$GLOBALS['hsw_pages'][ $id ] = array( 'slug' => $slug, 'status' => 'publish' );
	if ( '' !== $elementor_data ) {
		$GLOBALS['hsw_post_meta'][ $id ]['_elementor_data'] = $elementor_data;
	}
	return $id;
}

function r_priv( $class, $method ) {
	$ref = new ReflectionMethod( $class, $method );
	$ref->setAccessible( true );
	return $ref;
}

$GLOBALS['hsw_results'] = array();
function r_case( $id, $title, callable $fn ) {
	try {
		$r = $fn();
	} catch ( Throwable $e ) {
		$r = array( false, 'EXCEPTION: ' . $e->getMessage() );
	}
	$GLOBALS['hsw_results'][] = $r;
	printf( "[%s] %-5s %s — %s\n", $r[0] ? 'PASS' : 'FAIL', $id, $title, $r[1] );
}

/* =====================================================================
 * 12 — checkout routing
 * ===================================================================== */

r_case(
	'12.1',
	'a page called /payment/ with no HamiShop widget must not take the route',
	static function () {
		r_reset();
		r_page( 501, 'payment' );
		$id = r_priv( 'HSW_Complete_Route', 'resolve_page_id' )->invoke( null, 'hsw_pay_page_id', 'payment' );
		return array( 0 === (int) $id, 'resolved=' . var_export( $id, true ) );
	}
);

r_case(
	'12.2',
	'the same page carrying the pay widget IS taken',
	static function () {
		r_reset();
		$json = json_encode( array( array( 'widgetType' => 'hsw-pay-page', 'id' => 'abc', 'settings' => array() ) ) );
		r_page( 502, 'payment', $json );
		$id = r_priv( 'HSW_Complete_Route', 'resolve_page_id' )->invoke( null, 'hsw_pay_page_id', 'payment' );
		return array( 502 === (int) $id, 'resolved=' . var_export( $id, true ) );
	}
);

r_case(
	'12.3',
	'a cart with no shipping requirement is not bounced for a missing address',
	static function () {
		r_reset();
		r_page( 503, 'payment', json_encode( array( array( 'widgetType' => 'hsw-pay-page' ) ) ) );
		$GLOBALS['hsw_current_page'] = 503;

		$cart = new class() {
			public function is_empty() { return false; }
			public function needs_shipping() { return false; }
		};
		$GLOBALS['hsw_wc'] = new class() {
			public $cart;
			public $customer;
		};
		$GLOBALS['hsw_wc']->cart     = $cart;
		$GLOBALS['hsw_wc']->customer = new class() {
			public function get_shipping_city() { return ''; }
			public function get_shipping_address_1() { return ''; }
			public function get_billing_city() { return ''; }
			public function get_billing_address_1() { return ''; }
		};

		$route = new HSW_Complete_Route();
		$redirected = false;
		try {
			$route->guard_pay_address();
		} catch ( HSW_Prelive_Redirect $e ) {
			$redirected = true;
		}
		return array( ! $redirected, $redirected ? 'bounced to checkout' : 'stayed on the pay page' );
	}
);

r_case(
	'12.4',
	'guest checkout allowed by WooCommerce is not forced into login',
	static function () {
		r_reset();
		update_option( 'woocommerce_enable_guest_checkout', 'yes' );
		$GLOBALS['hsw_wc'] = new class() {
			public $cart;
			public function checkout() {
				return new class() {
					public function is_registration_required() {
						return ! empty( $GLOBALS['hsw_registration_required'] );
					}
				};
			}
		};
		$GLOBALS['hsw_wc']->cart = new class() {
			public function is_empty() { return false; }
		};
		$GLOBALS['hsw_registration_required'] = false;
		$GLOBALS['hsw_pages'][ 504 ] = array( 'slug' => 'checkout', 'status' => 'publish' );
		$GLOBALS['hsw_current_page']  = 504;
		$GLOBALS['hsw_wc_page_ids']   = array( 'checkout' => 504 );

		$route = new HSW_Complete_Route();
		$redirected = false;
		try {
			$route->guard_checkout_login();
		} catch ( HSW_Prelive_Redirect $e ) {
			$redirected = true;
		}
		return array( ! $redirected, $redirected ? 'guest bounced to login' : 'guest passed through' );
	}
);

r_case(
	'12.5',
	'when login IS required, the return target survives in both params',
	static function () {
		r_reset();
		update_option( 'woocommerce_enable_guest_checkout', 'no' );
		$GLOBALS['hsw_wc'] = new class() {
			public $cart;
			public function checkout() {
				return new class() {
					public function is_registration_required() {
						return ! empty( $GLOBALS['hsw_registration_required'] );
					}
				};
			}
		};
		$GLOBALS['hsw_wc']->cart = new class() {
			public function is_empty() { return false; }
		};
		$GLOBALS['hsw_registration_required'] = true;
		$GLOBALS['hsw_pages'][ 505 ] = array( 'slug' => 'checkout', 'status' => 'publish' );
		$GLOBALS['hsw_current_page'] = 505;
		$GLOBALS['hsw_wc_page_ids']  = array( 'checkout' => 505 );

		$route = new HSW_Complete_Route();
		$url   = '';
		try {
			$route->guard_checkout_login();
		} catch ( HSW_Prelive_Redirect $e ) {
			$url = $e->url;
		}
		$ok = '' !== $url && false !== strpos( $url, 'redirect_to=' ) && false !== strpos( $url, 'redirect=' );
		return array( $ok, $ok ? $url : 'url=' . ( '' === $url ? 'NO REDIRECT' : $url ) );
	}
);

/* =====================================================================
 * 13 — security cleanups
 * ===================================================================== */

r_case(
	'13.1',
	'font-swap returns the original HTML when PCRE fails',
	static function () {
		r_reset();
		$html = '<style>' . str_repeat( 'a{font-display:block}', 4000 ) . '</style><p>body</p>';
		$old  = ini_set( 'pcre.backtrack_limit', '100' );
		$out  = HSW_Security::instance()->rewrite_font_display( $html );
		ini_set( 'pcre.backtrack_limit', $old );
		return array( is_string( $out ) && $out === $html, 'returned ' . ( is_string( $out ) ? strlen( $out ) . ' bytes' : gettype( $out ) ) );
	}
);

r_case(
	'13.2',
	'an editor (edit_posts) keeps the REST user list, a subscriber does not',
	static function () {
		r_reset();
		update_option( 'hsw_security', array( 'block_rest_users' => 'yes' ) );
		$sec = HSW_Security::instance();

		$GLOBALS['hsw_caps'] = array( 'list_users' => false, 'edit_posts' => true );
		$with_edit = $sec->filter_rest_users( array( '/wp/v2/users' => array(), '/wp/v2/posts' => array() ) );

		$GLOBALS['hsw_caps'] = array( 'list_users' => false, 'edit_posts' => false );
		$subscriber = $sec->filter_rest_users( array( '/wp/v2/users' => array(), '/wp/v2/posts' => array() ) );

		$ok = isset( $with_edit['/wp/v2/users'] ) && ! isset( $subscriber['/wp/v2/users'] );
		return array( $ok, sprintf( 'editor keeps users=%s, subscriber keeps=%s', isset( $with_edit['/wp/v2/users'] ) ? 'yes' : 'no', isset( $subscriber['/wp/v2/users'] ) ? 'YES' : 'no' ) );
	}
);

r_case(
	'13.3',
	'/?author=1 is closed to guests when user listing is blocked',
	static function () {
		r_reset();
		update_option( 'hsw_security', array( 'block_rest_users' => 'yes' ) );
		$_GET['author'] = '1';
		$GLOBALS['hsw_current_user'] = 0;
		$GLOBALS['hsw_query_author'] = 1;

		$blocked = false;
		unset( $GLOBALS['hsw_status'] );
		HSW_Security::instance()->maybe_block_author_query();
		$blocked = 404 === (int) ( $GLOBALS['hsw_status'] ?? 0 );
		return array( $blocked, 'status=' . var_export( $GLOBALS['hsw_status'] ?? null, true ) );
	}
);

r_case(
	'13.3b',
	'the pretty /author/name/ archive is NOT killed (SEO links keep working)',
	static function () {
		r_reset();
		update_option( 'hsw_security', array( 'block_rest_users' => 'yes' ) );
		$_GET = array();
		$GLOBALS['hsw_current_user'] = 0;
		unset( $GLOBALS['hsw_status'] );

		$GLOBALS['wp_query'] = new class() {
			public $query_vars = array( 'author_name' => 'ai' );
		};

		HSW_Security::instance()->maybe_block_author_query();
		$safe = ! isset( $GLOBALS['hsw_status'] );
		unset( $GLOBALS['wp_query'] );
		return array( $safe, 'status=' . var_export( $GLOBALS['hsw_status'] ?? null, true ) );
	}
);

r_case(
	'13.4',
	'WooCommerce block styles are kept when the page actually has a block',
	static function () {
		r_reset();
		$GLOBALS['hsw_has_block'] = true;
		$GLOBALS['hsw_pages'][ 506 ] = array( 'slug' => 'blog', 'status' => 'publish' );
		$GLOBALS['hsw_current_page'] = 506;

		HSW_Security::instance()->clean_assets();
		$kept = ! in_array( 'wc-blocks-style', $GLOBALS['hsw_dequeued'], true );
		return array( $kept, 'dequeued=' . implode( ',', $GLOBALS['hsw_dequeued'] ) );
	}
);

/* =====================================================================
 * 14 — request blocker must never lock the shop out of its own services
 * ===================================================================== */

r_case(
	'14.1',
	'configured SMS/Telegram endpoints stay reachable in national-net mode',
	static function () {
		r_reset();
		update_option( 'hsw_security', array( 'block_external' => 'yes', 'block_hosts' => array( 'api.telegram.org', 'sms.example.ir' ) ) );
		update_option( 'hsw_account_settings', array( 'auth' => array( 'telegram_token' => '123:abc', 'whatsapp_bridge_url' => 'https://wa.example.ir/hooks' ), 'wallet' => array( 'sms_url' => 'https://sms.example.ir/api' ) ) );

		$blocked_telegram = HSW_Request_Blocker::host_is_blocked( 'https://api.telegram.org/bot123/sendMessage' );
		$blocked_sms      = HSW_Request_Blocker::host_is_blocked( 'https://sms.example.ir/api' );
		$blocked_wa       = HSW_Request_Blocker::host_is_blocked( 'https://wa.example.ir/hooks' );
		$blocked_default  = HSW_Request_Blocker::host_is_blocked( 'https://cdn.jsdelivr.net/npm/x.js' );

		$ok = ! $blocked_telegram && ! $blocked_sms && ! $blocked_wa && $blocked_default;
		return array( $ok, sprintf( 'telegram=%s sms=%s wa=%s jsdelivr=%s allow=%s', var_export( $blocked_telegram, true ), var_export( $blocked_sms, true ), var_export( $blocked_wa, true ), var_export( $blocked_default, true ), implode( '|', HSW_Request_Blocker::allowed_hosts() ) ) );
	}
);

r_case(
	'14.2',
	'a domain the owner typed really blocks, with no PHP notice',
	static function () {
		r_reset();
		update_option( 'hsw_security', array( 'block_external' => 'yes', 'block_hosts' => array( 'evil.example', 'ads.example.net' ) ) );

		$notices = array();
		set_error_handler(
			static function ( $no, $msg ) use ( &$notices ) {
				$notices[] = $msg;
				return true;
			}
		);
		$blocked = HSW_Request_Blocker::host_is_blocked( 'https://evil.example/pixel' );
		$blocked2 = HSW_Request_Blocker::host_is_blocked( 'https://sub.ads.example.net/pixel' );
		restore_error_handler();

		$ok = $blocked && $blocked2 && ! $notices;
		return array( $ok, sprintf( 'evil=%s sub-of-list=%s notices=%d%s', var_export( $blocked, true ), var_export( $blocked2, true ), count( $notices ), $notices ? ' (' . $notices[0] . ')' : '' ) );
	}
);

$failed = 0;
foreach ( $GLOBALS['hsw_results'] as $r ) {
	$failed += $r[0] ? 0 : 1;
}
printf( "\nrouting/security: %d cases, %d failed\n", count( $GLOBALS['hsw_results'] ), $failed );
exit( $failed ? 1 : 0 );
