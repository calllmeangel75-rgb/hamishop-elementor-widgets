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

// Step 4b: Wrong billing email submitted
HSW_Order_Auth::reset();
$_POST = array(
	'hsw_order_nonce'  => wp_create_nonce( 'hsw_verify_order_' . $reg_id ),
	'hsw_verify_email' => 'wrong_address@example.com',
);
$wrong_status = HSW_Order_Auth::check_order_access( $order_reg, $reg_key );
$check( 'Wrong billing email submitted: stays in need_verification', HSW_Order_Auth::STATUS_NEED_VERIFICATION === $wrong_status );
$check( 'Wrong billing email submitted: error message set', null !== HSW_Order_Auth::get_submission_error() );

// Step 4c: Correct billing email submitted
HSW_Order_Auth::reset();
$_POST = array(
	'hsw_order_nonce'  => wp_create_nonce( 'hsw_verify_order_' . $reg_id ),
	'hsw_verify_email' => 'customer_a@example.com',
);
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

// Step 4d: Subsequent page load with verification cookie / session
$_POST = array(); // No POST submission anymore
$cookie_hash = HSW_Order_Auth::verification_hash( $order_reg );
$_COOKIE[ HSW_Order_Auth::COOKIE_PREFIX . $reg_id ] = $cookie_hash;
HSW_Order_Auth::reset(); // Clear memory cache

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
