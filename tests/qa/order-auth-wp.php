<?php
/**
 * Regression test for Order Access Authorization & Thankyou Hook Security.
 *
 * Verifies the 4 critical access control requirements:
 *   1) Owner logged in: access granted immediately, thankyou hook fires.
 *   2) Guest order with valid key: access granted immediately, thankyou hook fires.
 *   3) Other customer logged in: access strictly forbidden, details hidden, thankyou hook blocked.
 *   4) Emailed link opened after logout:
 *      - Initially requires verification, details hidden, thankyou hook blocked.
 *      - Wrong email submitted: rejected with error, details hidden, thankyou hook blocked.
 *      - Correct billing email submitted: verified, access granted, thankyou hook fires.
 *      - Subsequent request with cookie/session: access remains granted.
 *
 * @package HamiShop_Elementor_Widgets
 */

if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'HSW_QA_DISPOSABLE' ) ) {
	exit( 2 );
}
require getenv( 'HSW_WP_LOAD' );
if ( 'production' === wp_get_environment_type() ) {
	exit( 2 );
}
if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
	fwrite( STDERR, "Elementor is not active; this test needs it.\n" );
	exit( 2 );
}
/* wp_delete_user() lives in the admin includes, not in wp-load.php. */
if ( ! function_exists( 'wp_delete_user' ) ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
}

if ( ! class_exists( 'HSW_Order_Auth' ) ) {
	require_once dirname( __DIR__, 2 ) . '/includes/class-hsw-order-auth.php';
}
if ( ! class_exists( 'HSW_Throttle' ) ) {
	require_once dirname( __DIR__, 2 ) . '/includes/class-hsw-throttle.php';
}
HSW_Throttle::instance()->maybe_install();

global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->prefix}hsw_throttle WHERE bucket LIKE 'verify_order%'" ); // phpcs:ignore WordPress.DB
if ( ! class_exists( 'HSW_Thankyou' ) ) {
	require_once dirname( __DIR__, 2 ) . '/includes/class-hsw-thankyou.php';
}

/*
 * The widget base classes are normally required lazily from
 * HSW_Plugin::register_widgets() (only when Elementor registers widgets).
 * This CLI test does not trigger that hook, so load the full inheritance
 * chain explicitly, in order: Widget_Base -> Cart_Base -> Checkout_Base ->
 * Pay_Base -> Complete_Base. Requiring only the last file fatals with
 * "Class HSW_Pay_Base not found".
 */
require_once dirname( __DIR__, 2 ) . '/includes/trait-hsw-style-kit.php';

$hsw_chain = array(
	'class-hsw-widget-base.php',
	'class-hsw-cart-base.php',
	'class-hsw-checkout-base.php',
	'class-hsw-pay-base.php',
	'class-hsw-complete-base.php',
);
foreach ( $hsw_chain as $hsw_file ) {
	require_once dirname( __DIR__, 2 ) . '/includes/' . $hsw_file;
}
if ( ! class_exists( 'HSW_Complete_Base' ) ) {
	echo '::error::FAIL HSW_Complete_Base did not load' . PHP_EOL;
	exit( 1 );
}
if ( ! class_exists( 'HSW_Widget_Complete_Hero' ) ) {
	require_once dirname( __DIR__, 2 ) . '/widgets/class-hsw-widget-complete-hero.php';
}

$failed = 0;
$check  = static function ( $name, $ok, $detail = '' ) use ( &$failed ) {
	echo ( $ok ? 'PASS ' : '::error::FAIL ' ) . $name . ( '' !== $detail ? " ({$detail})" : '' ) . PHP_EOL;
	if ( ! $ok ) {
		++$failed;
	}
};

/* Concrete test double extending HSW_Complete_Base */
class HSW_Test_Complete_Suite extends HSW_Complete_Base {
	public function get_name() { return 'hsw-test-complete'; }
	public function test_completion_order() { return $this->completion_order(); }
	public function test_complete_state() { return $this->complete_state(); }
	public function test_completion_data() { return $this->completion_data(); }
}

/* The verification card lives in the hero widget, not in the shared base. */
class HSW_Test_Complete_Hero_Suite extends HSW_Widget_Complete_Hero {
	public function get_name() { return 'hsw-test-complete-hero'; }
	public function test_render_verification_prompt() {
		ob_start();
		$this->render_verification_prompt();
		return (string) ob_get_clean();
	}
}

// Ensure session exists
if ( function_exists( 'WC' ) && WC() && ! WC()->session ) {
	WC()->initialize_session();
}

/* -------------------------------------------------------------------------
 * Setup test orders and users
 * ---------------------------------------------------------------------- */
$user_a_id = wp_create_user( 'hsw_auth_test_user_a', 'PassWord123!@#', 'customer_a@example.com' );
$user_b_id = wp_create_user( 'hsw_auth_test_user_b', 'PassWord123!@#', 'customer_b@example.com' );

// Order 1: Registered to User A
$order_reg = wc_create_order( array( 'customer_id' => $user_a_id ) );
$order_reg->set_billing_email( 'customer_a@example.com' );
$order_reg->set_billing_first_name( 'User' );
$order_reg->set_billing_last_name( 'A' );
$order_reg->set_payment_method( 'cod' );
$order_reg->save();
$reg_id  = (int) $order_reg->get_id();
$reg_key = (string) $order_reg->get_order_key();

// Order 2: Guest order (customer_id = 0)
$order_guest = wc_create_order( array( 'customer_id' => 0 ) );
$order_guest->set_billing_email( 'guest_shopper@example.com' );
$order_guest->set_billing_first_name( 'Guest' );
$order_guest->set_payment_method( 'cod' );
$order_guest->save();
$guest_id  = (int) $order_guest->get_id();
$guest_key = (string) $order_guest->get_order_key();

/* Hook observation for thankyou */
$thankyou_fired = array();
add_action( 'woocommerce_thankyou', static function ( $id ) use ( &$thankyou_fired ) {
	$thankyou_fired[] = (int) $id;
}, 10, 1 );

/* =========================================================================
 * 1) Owner logged in: access granted immediately
 * ====================================================================== */
HSW_Order_Auth::reset();
HSW_Thankyou::reset();
$thankyou_fired = array();
wp_set_current_user( $user_a_id );
$_GET = array( 'order' => (string) $reg_id, 'key' => $reg_key );
$_POST = array();

$status = HSW_Order_Auth::check_order_access( $order_reg, $reg_key );
$check( 'Owner logged in: auth status is granted', HSW_Order_Auth::STATUS_GRANTED === $status );
$check( 'Owner logged in: is_authorized returns true', true === HSW_Order_Auth::is_authorized( $order_reg, $reg_key ) );

$suite = new HSW_Test_Complete_Suite( array( 'id' => 'test' ), array() );
$resolved = $suite->test_completion_order();
$check( 'Owner logged in: completion view resolves order', $resolved instanceof WC_Order && (int) $resolved->get_id() === $reg_id );
$check( 'Owner logged in: complete_state is order', 'order' === $suite->test_complete_state() );

// Thankyou fires
HSW_Thankyou::fire( $resolved );
$check( 'Owner logged in: thankyou hook fires with order id', array( $reg_id ) === $thankyou_fired );

/* =========================================================================
 * 2) Guest order with valid key: access granted immediately
 * ====================================================================== */
HSW_Order_Auth::reset();
HSW_Thankyou::reset();
$thankyou_fired = array();
wp_set_current_user( 0 ); // logged out
$_GET = array( 'order' => (string) $guest_id, 'key' => $guest_key );
$_POST = array();

$guest_status = HSW_Order_Auth::check_order_access( $order_guest, $guest_key );
$check( 'Guest order with valid key: auth status is granted', HSW_Order_Auth::STATUS_GRANTED === $guest_status );
$check( 'Guest order with valid key: is_authorized returns true', true === HSW_Order_Auth::is_authorized( $order_guest, $guest_key ) );

$suite = new HSW_Test_Complete_Suite( array( 'id' => 'test' ), array() );
$resolved_guest = $suite->test_completion_order();
$check( 'Guest order: completion view resolves order', $resolved_guest instanceof WC_Order && (int) $resolved_guest->get_id() === $guest_id );

// Thankyou fires
HSW_Thankyou::fire( $resolved_guest );
$check( 'Guest order: thankyou hook fires with order id', array( $guest_id ) === $thankyou_fired );

// Invalid key check for guest order
$bad_guest_status = HSW_Order_Auth::check_order_access( $order_guest, 'wc_order_wrong_key' );
$check( 'Guest order with invalid key: auth status is denied', HSW_Order_Auth::STATUS_DENIED === $bad_guest_status );

/* =========================================================================
 * 3) Other customer logged in: strictly forbidden
 * ====================================================================== */
HSW_Order_Auth::reset();
HSW_Thankyou::reset();
$thankyou_fired = array();
wp_set_current_user( $user_b_id ); // Customer B
$_GET = array( 'order' => (string) $reg_id, 'key' => $reg_key );
$_POST = array();

$other_status = HSW_Order_Auth::check_order_access( $order_reg, $reg_key );
$check( 'Other customer logged in: auth status is forbidden', HSW_Order_Auth::STATUS_FORBIDDEN === $other_status );
$check( 'Other customer logged in: is_authorized returns false', false === HSW_Order_Auth::is_authorized( $order_reg, $reg_key ) );
$check( 'Other customer logged in: can_route returns false', false === HSW_Order_Auth::can_route( $order_reg, $reg_key ) );

$suite = new HSW_Test_Complete_Suite( array( 'id' => 'test' ), array() );
$resolved_other = $suite->test_completion_order();
$check( 'Other customer logged in: completion view returns false (no leak)', false === $resolved_other );
$check( 'Other customer logged in: completion data is null', null === $suite->test_completion_data() );

// Thankyou must NOT fire for Customer B
HSW_Thankyou::fire( $order_reg );
$check( 'Other customer logged in: thankyou hook is blocked', array() === $thankyou_fired );

/* =========================================================================
 * 4) Emailed order link opened after logout (Guest viewing registered order)
 * ====================================================================== */

// Step 4a: Opened without verification
HSW_Order_Auth::reset();
HSW_Thankyou::reset();
$thankyou_fired = array();
wp_set_current_user( 0 ); // Logged out
$_GET = array( 'order' => (string) $reg_id, 'key' => $reg_key );
$_POST = array();
$_COOKIE = array();

$unverified_status = HSW_Order_Auth::check_order_access( $order_reg, $reg_key );
$check( 'Emailed link after logout: requires email verification', HSW_Order_Auth::STATUS_NEED_VERIFICATION === $unverified_status );
$check( 'Emailed link after logout: is_authorized returns false', false === HSW_Order_Auth::is_authorized( $order_reg, $reg_key ) );
$check( 'Emailed link after logout: can_route returns true (routed to complete)', true === HSW_Order_Auth::can_route( $order_reg, $reg_key ) );

$suite = new HSW_Test_Complete_Suite( array( 'id' => 'test' ), array() );
$resolved_unverified = $suite->test_completion_order();
$check( 'Emailed link after logout: completion order returns false', false === $resolved_unverified );
$check( 'Emailed link after logout: complete_state is verify', 'verify' === $suite->test_complete_state() );
$check( 'Emailed link after logout: completion data is null (details hidden)', null === $suite->test_completion_data() );

// Thankyou must NOT fire
HSW_Thankyou::fire( $order_reg );
$check( 'Emailed link unverified: thankyou hook is blocked', array() === $thankyou_fired );

/*
 * Step 4b: failures are counted per order + IP. Five wrong emails answer with
 * an error; the sixth is throttled. A different IP starts from zero.
 */
HSW_Order_Auth::reset();
$_SERVER['REMOTE_ADDR']      = '203.0.113.10';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '';
$nonce = wp_create_nonce( 'hsw_verify_order_' . $reg_id );

for ( $attempt = 1; $attempt <= 5; $attempt++ ) {
	$_POST = array(
		'hsw_order_nonce'  => $nonce,
		'hsw_verify_email' => 'wrong_' . $attempt . '@example.com',
	);
	$result = HSW_Order_Auth::process_submission( $order_reg );
	$check( "Wrong billing email {$attempt}: rejected without a cookie", 'error' === $result['status'] && null !== HSW_Order_Auth::get_submission_error() );
}
$check( 'Wrong billing email 1-5: no verification cookie was set', ! isset( $_COOKIE[ HSW_Order_Auth::COOKIE_PREFIX . $reg_id ] ) );

$_POST = array(
	'hsw_order_nonce'  => $nonce,
	'hsw_verify_email' => 'wrong_6@example.com',
);
$sixth = HSW_Order_Auth::process_submission( $order_reg );
$check( 'Wrong billing email 6: throttled', 'throttled' === $sixth['status'] );
$check( 'Wrong billing email 6: Retry-After window is positive', (int) $sixth['retry_after'] > 0 );

/* Another IP is counted independently (the bucket key includes the client IP). */
$_SERVER['REMOTE_ADDR'] = '203.0.113.11';
$_POST = array(
	'hsw_order_nonce'  => $nonce,
	'hsw_verify_email' => 'wrong_other_ip@example.com',
);
$other_ip = HSW_Order_Auth::process_submission( $order_reg );
$check( 'A different IP is not locked by the first IP', 'error' === $other_ip['status'] );

/*
 * The signed-in browser behind Nginx -> LiteSpeed connects from loopback and
 * carries the real client in X-Forwarded-For. Two different clients must map
 * to two different buckets, not one shared proxy bucket.
 */
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7';
$ip_one = HSW_Throttle::ip();
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.8';
$ip_two = HSW_Throttle::ip();
$check( 'Throttle: loopback proxy forwards real client IP', '198.51.100.7' === $ip_one && '198.51.100.8' === $ip_two );
$check( 'Throttle: two clients get independent buckets', $ip_one !== $ip_two );

/*
 * Only hsw_verify_email activates this path. A generic email field belongs to
 * other forms on the same URL and must be ignored.
 */
HSW_Order_Auth::reset();
$_SERVER['REMOTE_ADDR'] = '203.0.113.11';
$_POST = array( 'email' => 'customer_a@example.com' );
$generic = HSW_Order_Auth::process_submission( $order_reg );
$check( 'Generic email field is ignored', 'ignored' === $generic['status'] );
$check( 'Generic email field cannot authorize the order', HSW_Order_Auth::STATUS_NEED_VERIFICATION === HSW_Order_Auth::check_order_access( $order_reg, $reg_key ) );

/*
 * Step 4c: the correct email succeeds even after the first IP burned its
 * five attempts, sets the cookie, and clears the shared and per-IP counters.
 */
HSW_Order_Auth::reset();
$_SERVER['REMOTE_ADDR'] = '203.0.113.11';
$_POST = array(
	'hsw_order_nonce'  => $nonce,
	'hsw_verify_email' => 'customer_a@example.com',
);
$correct = HSW_Order_Auth::process_submission( $order_reg );
$check( 'Correct billing email: process_submission succeeds', 'success' === $correct['status'] );
$check( 'Correct billing email: verification cookie is present', isset( $_COOKIE[ HSW_Order_Auth::COOKIE_PREFIX . $reg_id ] ) );
$check( 'Correct billing email: cookie value matches the order hash', ( $_COOKIE[ HSW_Order_Auth::COOKIE_PREFIX . $reg_id ] ?? '' ) === HSW_Order_Auth::verification_hash( $order_reg ) );

$global_left = HSW_Throttle::peek( 'verify_order_all:' . $reg_id );
$ip_left     = HSW_Throttle::peek( 'verify_order:' . $reg_id . ':203.0.113.11' );
$check( 'Correct billing email clears the per-order bucket', 0 === (int) $global_left['hits'] );
$check( 'Correct billing email clears this IP bucket', 0 === (int) $ip_left['hits'] );

$correct_status = HSW_Order_Auth::check_order_access( $order_reg, $reg_key );
$check( 'Correct billing email submitted: auth status becomes granted', HSW_Order_Auth::STATUS_GRANTED === $correct_status );
$check( 'Correct billing email submitted: is_authorized returns true', true === HSW_Order_Auth::is_authorized( $order_reg, $reg_key ) );

$suite = new HSW_Test_Complete_Suite( array( 'id' => 'test' ), array() );
$resolved_verified = $suite->test_completion_order();
$check( 'Correct billing email submitted: completion view resolves order', $resolved_verified instanceof WC_Order && (int) $resolved_verified->get_id() === $reg_id );
$check( 'Correct billing email submitted: complete_state is order', 'order' === $suite->test_complete_state() );
$check( 'Correct billing email submitted: completion data is present', is_array( $suite->test_completion_data() ) );

// Thankyou hook now fires!
HSW_Thankyou::fire( $resolved_verified );
$check( 'Correct billing email submitted: thankyou hook fires once', array( $reg_id ) === $thankyou_fired );

/* An order without a billing email can never be verified; do not show a form. */
$no_email = wc_create_order( array( 'customer_id' => 0 ) );
$no_email->set_billing_email( '' );
$no_email->save();
$check( 'Order with empty billing email: has_billing_email is false', false === HSW_Order_Auth::has_billing_email( $no_email ) );
HSW_Order_Auth::reset();
$_POST = array(
	'hsw_order_nonce'  => wp_create_nonce( 'hsw_verify_order_' . $no_email->get_id() ),
	'hsw_verify_email' => 'any@example.com',
);
$empty_email = HSW_Order_Auth::process_submission( $no_email );
$check( 'Order with empty billing email: any email fails with a login hint', 'error' === $empty_email['status'] && false !== strpos( HSW_Order_Auth::get_submission_error(), 'وارد حساب کاربری' ) );

/* The verification view must hide the email form and offer the login route. */
$suite_no_email = new HSW_Test_Complete_Hero_Suite( array( 'id' => 'test' ), array() );
foreach ( array( 'raw_order_cache' => $no_email, 'auth_status_cache' => HSW_Order_Auth::STATUS_NEED_VERIFICATION ) as $property => $value ) {
	$reflection = new ReflectionProperty( 'HSW_Complete_Base', $property );
	$reflection->setAccessible( true );
	$reflection->setValue( $suite_no_email, $value );
}
$no_email_html = $suite_no_email->test_render_verification_prompt();
$check( 'Empty billing email: login hint is rendered', false !== strpos( $no_email_html, 'وارد حساب کاربری' ) );
$check( 'Empty billing email: email form is not rendered', false === strpos( $no_email_html, 'hsw_verify_email' ) );
wp_delete_post( $no_email->get_id(), true );

// Step 4d: Subsequent page load with the verification cookie.
$_POST = array(); // No POST submission anymore
HSW_Order_Auth::reset(); // Clear memory cache; the cookie remains.

$cookie_status = HSW_Order_Auth::check_order_access( $order_reg, $reg_key );
$check( 'Subsequent reload with cookie: auth status remains granted', HSW_Order_Auth::STATUS_GRANTED === $cookie_status );
$check( 'Subsequent reload with cookie: is_authorized returns true', true === HSW_Order_Auth::is_authorized( $order_reg, $reg_key ) );

/* Clean up test users & orders */
wp_delete_post( $reg_id, true );
wp_delete_post( $guest_id, true );
wp_delete_user( $user_a_id );
wp_delete_user( $user_b_id );

echo PHP_EOL . "Order Auth QA Results: " . ( 0 === $failed ? "ALL PASSED" : "{$failed} FAILED" ) . PHP_EOL;
exit( $failed > 0 ? 1 : 0 );
