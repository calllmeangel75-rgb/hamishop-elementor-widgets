<?php
/**
 * Email verification for phone signups — executable cases.
 *
 * QA-only; the release ZIP excludes tests/ (.gitattributes export-ignore). The controller under test is HSW_Email_Verify: its own AJAX
 * actions (`hsw_email_send` / `hsw_email_verify`) and its own nonce, which is
 * the whole point — it must never touch the OTP login flow.
 *
 *   php tests/qa/prelive-email.php
 *   HSW_PRELIVE_EV=<path>  php tests/qa/prelive-email.php   # run against an older copy
 */

require_once __DIR__ . '/prelive-lib.php';

class HSW_Helpers {
	public static function fa_num( $value ) { return (string) $value; }
	public static function icon( $name, $class = '', $raw = false ) { return ''; }
}

class HSW_Prelive_Account {
	public $settings = array( 'general' => array( 'email_verify' => 'yes' ) );
	public function setting( $path, $fallback = '' ) { return $fallback; }
	public function settings() { return $this->settings; }
	public function icon( $name ) { return ''; }
}

$GLOBALS['hsw_account'] = new HSW_Prelive_Account();

$file = getenv( 'HSW_PRELIVE_EV' );
require_once $file ? $file : dirname( __DIR__, 2 ) . '/includes/class-hsw-email-verify.php';

$GLOBALS['hsw_ev'] = HSW_Email_Verify::instance( $GLOBALS['hsw_account'] );

function ev_reset() {
	HSW_Prelive_Store::reset();
	$GLOBALS['hsw_mail']         = array();
	$GLOBALS['hsw_mail_fail']    = false;
	$GLOBALS['hsw_current_user'] = 0;
	$_POST                       = array();
	update_option( 'woocommerce_enable_myaccount_registration', 'yes' );
}

function ev_user( $email, $meta = array() ) {
	$id = prelive_uid();
	$users   = hsw_prelive_users();
	$users[] = array( 'ID' => $id, 'user_login' => 'u' . $id, 'user_email' => $email, 'roles' => array( 'customer' ) );
	HSW_Prelive_Store::write( 'users', 'all', $users );
	foreach ( $meta as $k => $v ) {
		update_user_meta( $id, $k, $v );
	}
	wp_set_current_user( $id );
	return $id;
}

function prelive_uid() {
	$id = (int) ( HSW_Prelive_Store::read( 'seq', 'user', 50 ) ) + 1;
	HSW_Prelive_Store::write( 'seq', 'user', $id );
	return $id;
}

function ev_call( $method, $post = array() ) {
	$_POST = $post;
	try {
		$GLOBALS['hsw_ev']->$method();
		return array( 'ok' => null, 'data' => null, 'status' => 0 );
	} catch ( HSW_Prelive_Json $e ) {
		return array( 'ok' => $e->ok, 'data' => $e->data, 'status' => $e->status );
	}
}

function ev_last_code() {
	$mails = (array) $GLOBALS['hsw_mail'];
	$last  = end( $mails );
	if ( ! $last ) {
		return '';
	}
	return preg_match( '/\b(\d{6})\b/', (string) $last['message'], $m ) ? $m[1] : '';
}

$GLOBALS['hsw_results'] = array();
function ev_case( $id, $title, callable $fn ) {
	try {
		$r = $fn();
	} catch ( Throwable $e ) {
		$r = array( false, 'EXCEPTION: ' . $e->getMessage() );
	}
	$GLOBALS['hsw_results'][] = $r;
	printf( "[%s] %-5s %s — %s\n", $r[0] ? 'PASS' : 'FAIL', $id, $title, $r[1] );
}

/* ------------------------------------------------------------------ */

ev_case(
	'11.1',
	'placeholder detection: @noreply.invalid yes, a real address no',
	static function () {
		$p = HSW_Email_Verify::is_placeholder( '09121234567@noreply.invalid' );
		$n = HSW_Email_Verify::is_placeholder( 'ali@example.com' );
		return array( true === $p && false === $n, 'placeholder=' . var_export( $p, true ) . ' real=' . var_export( $n, true ) );
	}
);

ev_case(
	'11.2',
	'sending a code to the placeholder address is refused',
	static function () {
		ev_reset();
		$uid = ev_user( '09120000000@noreply.invalid', array( '_hsw_auth_phone' => '09120000000' ) );
		$r   = ev_call( 'ajax_send', array( 'email' => '09120000000@noreply.invalid', 'nonce' => 'x' ) );
		return array( 400 === (int) $r['status'] && ! $r['ok'], sprintf( 'status=%d message=%s', $r['status'], is_array( $r['data'] ) ? ( $r['data']['message'] ?? '' ) : '' ) );
	}
);

ev_case(
	'11.3',
	'the banner offers the pending address, not the placeholder',
	static function () {
		ev_reset();
		$uid = ev_user( '09121111111@noreply.invalid', array( '_hsw_auth_phone' => '09121111111', '_hsw_pending_email' => 'ali@example.com' ) );
		$html = $GLOBALS['hsw_ev']->banner( array( 'user_id' => $uid ) );
		$has_pending = false !== strpos( $html, 'value="ali@example.com"' );
		$has_placeholder = false !== strpos( $html, 'noreply.invalid' );
		return array( $has_pending && ! $has_placeholder, sprintf( 'pending_prefilled=%s placeholder_shown=%s', $has_pending ? 'yes' : 'no', $has_placeholder ? 'YES' : 'no' ) );
	}
);

ev_case(
	'11.4',
	'send -> verify links the real address and clears the pending one',
	static function () {
		ev_reset();
		$uid = ev_user( '09122222222@noreply.invalid', array( '_hsw_auth_phone' => '09122222222', '_hsw_pending_email' => 'ali@example.com' ) );

		$send = ev_call( 'ajax_send', array( 'email' => 'ali@example.com', 'nonce' => 'x' ) );
		$code = ev_last_code();
		$ver  = ev_call( 'ajax_verify', array( 'code' => $code, 'nonce' => 'x' ) );

		$user    = get_user_by( 'id', $uid );
		$pending = (string) get_user_meta( $uid, '_hsw_pending_email', true );
		$ok      = $send['ok'] && $ver['ok'] && $user && 'ali@example.com' === $user->user_email && '' === $pending && '' !== (string) get_user_meta( $uid, '_hsw_email_verified', true );

		return array( $ok, sprintf( 'send=%s verify=%s email=%s pending=%s', $send['ok'] ? 'ok' : 'fail', $ver['ok'] ? 'ok' : 'fail', $user ? $user->user_email : '-', '' === $pending ? 'cleared' : $pending ) );
	}
);

ev_case(
	'11.5',
	'the new-account e-mail is dropped for a placeholder recipient',
	static function () {
		ev_reset();
		$suppressed = apply_filters( 'woocommerce_email_recipient_customer_new_account', '09123333333@noreply.invalid', null );
		$kept       = apply_filters( 'woocommerce_email_recipient_customer_new_account', 'ali@example.com', null );
		return array( '' === $suppressed && 'ali@example.com' === $kept, sprintf( 'placeholder="%s" real="%s"', $suppressed, $kept ) );
	}
);

$failed = 0;
foreach ( $GLOBALS['hsw_results'] as $r ) {
	$failed += $r[0] ? 0 : 1;
}
printf( "\nemail-verify: %d cases, %d failed\n", count( $GLOBALS['hsw_results'] ), $failed );
exit( $failed ? 1 : 0 );
