<?php
/**
 * Disposable WP/WC regression: what a one-time code is allowed to prove.
 * Creates users, changes the account settings and the Telegram contact map.
 * Never run on a real shop.
 *
 * Covers two defects in the code-login module:
 *  1. Enabling Telegram two-factor with a *typed* number stored that number as
 *     the account's verified login number, although the code had only reached
 *     the account's own Telegram chat. Anyone could reserve a stranger's
 *     number; the owner could no longer register it and an SMS login with it
 *     opened the stranger's account.
 *  2. The five-tries limit of the Telegram deep-link login was a
 *     read-modify-write on a transient, so interleaved requests each got a
 *     guess and the right code was still accepted afterwards.
 *
 * Telegram is never contacted: `pre_http_request` answers for it and records
 * the message, which is how the test reads the code "from the chat".
 */
if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'HSW_QA_DISPOSABLE' ) ) { fwrite( STDERR, "Disposable CLI install required.\n" ); exit( 2 ); }
register_shutdown_function( static function () { $e = error_get_last(); if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { echo '::error::' . str_replace( "\n", ' ', $e['message'] ) . PHP_EOL; } } );
$path = getenv( 'HSW_WP_LOAD' );
if ( ! $path || ! is_file( $path ) ) { fwrite( STDERR, "HSW_WP_LOAD required.\n" ); exit( 2 ); }
$_SERVER['REMOTE_ADDR'] = '10.' . random_int( 1, 250 ) . '.' . random_int( 1, 250 ) . '.' . random_int( 1, 250 );
require $path;
if ( ! function_exists( 'wp_get_environment_type' ) || 'production' === wp_get_environment_type() || ! class_exists( 'HSW_Account' ) || ! class_exists( 'HSW_Account_Auth' ) || ! class_exists( 'HSW_Throttle' ) ) { fwrite( STDERR, "Refusing: disposable non-production install with the plugin active is required.\n" ); exit( 2 ); }
require_once ABSPATH . 'wp-admin/includes/user.php';

$account = HSW_Account::instance();
$auth    = $account->auth;
if ( ! $auth instanceof HSW_Account_Auth ) { fwrite( STDERR, "Auth module unavailable.\n" ); exit( 2 ); }

class HSW_QA_Auth_Die extends Exception {}
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', static function () { return static function () { throw new HSW_QA_Auth_Die(); }; } );
add_filter( 'send_auth_cookies', '__return_false' );
add_filter( 'pre_wp_mail', '__return_true' );
add_filter( 'hsw_otp_ip_hourly', static function () { return 100000; } );

/* Telegram stand-in: accept every call, remember what was sent where. */
$sent = array();
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) use ( &$sent ) {
		if ( false === strpos( (string) $url, 'api.telegram.org' ) ) {
			return $pre;
		}
		$body = json_decode( isset( $args['body'] ) ? (string) $args['body'] : '', true );
		if ( is_array( $body ) && isset( $body['chat_id'], $body['text'] ) ) {
			$sent[] = array( 'chat' => (string) $body['chat_id'], 'text' => (string) $body['text'] );
		}
		return array( 'headers' => array(), 'body' => '{"ok":true,"result":[]}', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
	},
	10,
	3
);
$last_code = static function () use ( &$sent ) {
	$last = end( $sent );
	$text = $last ? strtr( $last['text'], array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9' ) ) : '';
	return preg_match( '/(?<![0-9])([0-9]{6})(?![0-9])/', $text, $m ) ? $m[1] : '';
};
$last_chat = static function () use ( &$sent ) { $last = end( $sent ); return $last ? $last['chat'] : ''; };

$saved = array(
	HSW_Account::OPTION                         => get_option( HSW_Account::OPTION, null ),
	'hsw_telegram_contacts'                     => get_option( 'hsw_telegram_contacts', null ),
	'woocommerce_enable_myaccount_registration' => get_option( 'woocommerce_enable_myaccount_registration', null ),
);
$settings         = is_array( $saved[ HSW_Account::OPTION ] ) ? $saved[ HSW_Account::OPTION ] : array();
$settings['auth'] = array_merge(
	isset( $settings['auth'] ) && is_array( $settings['auth'] ) ? $settings['auth'] : array(),
	array( 'otp_enabled' => 'yes', 'otp_login' => 'yes', 'otp_register' => 'yes', 'otp_2fa' => 'yes', 'otp_max_attempts' => 5, 'telegram_token' => '123456:QA-TOKEN', 'telegram_bot' => 'hsw_qa_bot', 'telegram_polling' => 'no', 'otp_message' => 'QA {code}' )
);
update_option( HSW_Account::OPTION, $settings, false );
update_option( 'woocommerce_enable_myaccount_registration', 'yes' );
$account->settings( true );

$users   = array();
$logins  = array();
$make    = static function ( $meta = array() ) use ( &$users ) {
	$login = 'hsw_proof_' . strtolower( wp_generate_password( 10, false ) );
	$id    = wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password( 24 ), 'user_email' => $login . '@example.invalid', 'role' => 'customer' ) );
	if ( is_wp_error( $id ) ) { fwrite( STDERR, "Could not create a user.\n" ); exit( 2 ); }
	foreach ( $meta as $key => $value ) { update_user_meta( $id, $key, $value ); }
	$users[] = (int) $id;
	return (int) $id;
};
$phone   = static function () { return '0912' . str_pad( (string) random_int( 0, 9999999 ), 7, '0', STR_PAD_LEFT ); };
$chat    = static function () { return '5' . str_pad( (string) random_int( 0, 999999999 ), 9, '0', STR_PAD_LEFT ); };
$contact = static function ( $number, $chat_id ) {
	$map = get_option( 'hsw_telegram_contacts', array() );
	$map = is_array( $map ) ? $map : array();
	$map[ $number ] = (string) $chat_id;
	update_option( 'hsw_telegram_contacts', $map, false );
};

register_shutdown_function( static function () use ( &$users, &$logins, $saved, $account ) {
	wp_set_current_user( 0 );
	foreach ( $logins as $login ) { $id = username_exists( $login ); if ( $id ) { $users[] = (int) $id; } }
	foreach ( array_unique( $users ) as $id ) { delete_transient( 'hsw_2fa_setup_' . $id ); wp_delete_user( $id ); }
	foreach ( $saved as $name => $value ) { if ( null === $value ) { delete_option( $name ); } else { update_option( $name, $value, false ); } }
	$account->settings( true );
} );

/*
 * A die() inside the code under test (wp_die(), wp_send_json(), a redirect
 * followed by exit) ends the script with status 0, and the step would read as
 * a pass with half of the assertions never reached. Registered after the
 * clean-up above, so that one still runs first.
 */
$done = false;
register_shutdown_function( static function () use ( &$done ) { if ( ! $done ) { echo '::error::FAIL the script stopped before its last assertion' . PHP_EOL; exit( 1 ); } } );

$failed = 0;
$ran    = 0;
$check  = static function ( $name, $ok ) use ( &$failed, &$ran ) { ++$ran; echo ( $ok ? 'PASS ' : '::error::FAIL ' ) . $name . PHP_EOL; if ( ! $ok ) { ++$failed; } };
$ajax   = static function ( $method, $nonce, $post = array() ) use ( $auth ) {
	$_POST          = $post;
	$_POST['nonce'] = wp_create_nonce( $nonce );
	$_REQUEST       = $_POST;
	ob_start();
	try { $auth->$method(); } catch ( HSW_QA_Auth_Die $e ) { unset( $e ); }
	$data = json_decode( (string) ob_get_clean(), true );
	return is_array( $data ) ? $data : array();
};
/* Enable Telegram two-factor as `$user_id`, optionally typing a number; returns [send, verify] answers. */
$enable = static function ( $user_id, $typed = '' ) use ( $ajax, $last_code, &$sent ) {
	wp_set_current_user( $user_id );
	$sent   = array();
	$post   = array( 'channel' => 'telegram' );
	if ( '' !== $typed ) { $post['phone'] = $typed; }
	$send   = $ajax( 'ajax_2fa_setup_send', 'hsw_2fa', $post );
	$verify = $ajax( 'ajax_2fa_setup_verify', 'hsw_2fa', array( 'code' => $last_code() ) );
	wp_set_current_user( 0 );
	return array( $send, $verify );
};
$meta = static function ( $user_id, $key ) { return (string) get_user_meta( $user_id, $key, true ); };

/* ---------------------------------------------------------------------
 * 1. Telegram two-factor: a typed number is not a proved number
 * ------------------------------------------------------------------ */

/* 1a. An account with a linked chat types a number that has nothing to do with it. */
$victim        = $phone();
$attacker_chat = $chat();
$attacker      = $make( array( '_hsw_telegram_chat_id' => $attacker_chat ) );
list( $send, $verify ) = $enable( $attacker, $victim );
$check( 'typed foreign number: the code goes to the account\'s own chat', ! empty( $send['success'] ) && $last_chat() === $attacker_chat );
$check( 'typed foreign number: two-factor is still switched on', ! empty( $verify['success'] ) && 'yes' === $meta( $attacker, '_hsw_2fa_enabled' ) && 'telegram' === $meta( $attacker, '_hsw_2fa_channel' ) );
$check( 'typed foreign number: it does not become the verified login number', '' === $meta( $attacker, '_hsw_auth_phone' ) );
$check( 'typed foreign number: it does not become the two-factor number', '' === $meta( $attacker, '_hsw_2fa_phone' ) );
$check( 'typed foreign number: the second factor is the linked chat', 'tg:' . $attacker_chat === $auth->two_factor_recipient( $attacker, 'telegram' ) );

/* 1b. The number is tied — by Telegram — to somebody else's chat. */
$victim2      = $phone();
$victim2_chat = $chat();
$contact( $victim2, $victim2_chat );
$squatter_chat = $chat();
$squatter      = $make( array( '_hsw_telegram_chat_id' => $squatter_chat ) );
list( , $verify ) = $enable( $squatter, $victim2 );
$check( 'number tied to another chat: not claimed by an account with its own chat', ! empty( $verify['success'] ) && '' === $meta( $squatter, '_hsw_auth_phone' ) && '' === $meta( $squatter, '_hsw_2fa_phone' ) );

/* 1c. The honest path: Telegram tied this number to this very chat. */
$own      = $phone();
$own_chat = $chat();
$contact( $own, $own_chat );
$owner = $make( array( '_hsw_telegram_chat_id' => $own_chat ) );
list( , $verify ) = $enable( $owner, $own );
$check( 'number shared from the same chat: claimed as before', ! empty( $verify['success'] ) && $own === $meta( $owner, '_hsw_auth_phone' ) && $own === $meta( $owner, '_hsw_2fa_phone' ) );

/* 1d. The honest path without a linked account: the code travels to the chat Telegram vouches for. */
$fresh      = $phone();
$fresh_chat = $chat();
$contact( $fresh, $fresh_chat );
$newcomer = $make();
list( $send, $verify ) = $enable( $newcomer, $fresh );
$check( 'number shared with the bot, account not linked yet: code goes to that chat and the number is claimed', ! empty( $send['success'] ) && ! empty( $verify['success'] ) && $fresh === $meta( $newcomer, '_hsw_auth_phone' ) );

/* 1e. An already proved number stays the second-factor number. */
$proved      = $phone();
$proved_user = $make( array( '_hsw_auth_phone' => $proved, '_hsw_telegram_chat_id' => $chat() ) );
list( , $verify ) = $enable( $proved_user, $victim );
$check( 'account with a proved number: keeps it, ignores the typed one', ! empty( $verify['success'] ) && $proved === $meta( $proved_user, '_hsw_auth_phone' ) && $proved === $meta( $proved_user, '_hsw_2fa_phone' ) );

/* 1f. A code issued before the rule existed must not slip a number through the verify step. */
$late      = $make( array( '_hsw_telegram_chat_id' => $chat() ) );
$late_code = '314159';
set_transient( 'hsw_2fa_setup_' . $late, array( 'hash' => wp_hash_password( $late_code ), 'channel' => 'telegram', 'phone' => $phone(), 'attempts' => 0, 'expires' => time() + 300 ), 300 );
wp_set_current_user( $late );
$verify = $ajax( 'ajax_2fa_setup_verify', 'hsw_2fa', array( 'code' => $late_code ) );
wp_set_current_user( 0 );
$check( 'pending code with an unproved number: confirmed without claiming it', ! empty( $verify['success'] ) && '' === $meta( $late, '_hsw_auth_phone' ) && '' === $meta( $late, '_hsw_2fa_phone' ) );

/* ---------------------------------------------------------------------
 * 2. Telegram deep-link login: five tries, however the requests interleave
 * ------------------------------------------------------------------ */

/* One session = one code. The chat belongs to a known customer, so a right answer is a login. */
$login_chat = $chat();
$customer   = $make( array( '_hsw_telegram_chat_id' => $login_chat ) );
$logins[]   = 'tg_' . $login_chat;
$open = static function () use ( $ajax, $auth, $login_chat, $last_code, &$sent ) {
	delete_transient( 'hsw_tgs_' . md5( (string) $_SERVER['REMOTE_ADDR'] ) );
	wp_set_current_user( 0 );
	$start = $ajax( 'ajax_tg_start', 'hsw_otp', array( 'context' => 'login' ) );
	$sid   = isset( $start['data']['sid'] ) ? (string) $start['data']['sid'] : '';
	$sent  = array();
	$auth->handle_telegram_update( array( 'message' => array( 'chat' => array( 'id' => $login_chat, 'type' => 'private' ), 'from' => array( 'id' => $login_chat, 'first_name' => 'QA' ), 'text' => '/start lg_' . $sid ) ) );
	return array( $sid, $last_code() );
};
$wrong = static function ( $code ) { return (string) ( ( (int) $code + 1 ) % 900000 + 100000 ); };
$try   = static function ( $sid, $code ) use ( $ajax ) {
	wp_set_current_user( 0 );
	$answer = $ajax( 'ajax_tg_verify', 'hsw_otp', array( 'sid' => $sid, 'code' => $code ) );
	$user   = get_current_user_id();
	wp_set_current_user( 0 );
	return ! empty( $answer['success'] ) ? $user : 0;
};

list( $sid, $code ) = $open();
$check( 'telegram login: the bot answers /start with a six-digit code', 32 === strlen( $sid ) && 6 === strlen( $code ) );
for ( $i = 0; $i < 4; $i++ ) { $try( $sid, $wrong( $code ) ); }
$check( 'telegram login: the right code on the fifth try signs the customer in', $customer === $try( $sid, $code ) );
$check( 'telegram login: a used code cannot be replayed', 0 === $try( $sid, $code ) );

list( $sid, $code ) = $open();
for ( $i = 0; $i < 5; $i++ ) { $try( $sid, $wrong( $code ) ); }
$check( 'telegram login: five wrong codes close the session', 0 === $try( $sid, $code ) );

/*
 * Interleaving: every request has read the session before any of them has
 * counted its attempt — what a burst of parallel requests does. The filter runs
 * at the end of get_transient(), so the next "request" starts exactly there.
 */
list( $sid, $code ) = $open();
$depth      = 0;
$bad        = $wrong( $code );
$interleave = static function ( $value ) use ( &$depth, $try, $sid, $bad ) {
	if ( $depth < 11 ) {
		++$depth;
		$try( $sid, $bad );
	}
	return $value;
};
add_filter( 'transient_hsw_tgl_' . $sid, $interleave );
$try( $sid, $bad );
remove_filter( 'transient_hsw_tgl_' . $sid, $interleave );
$check( 'telegram login: twelve interleaved wrong codes were all issued', 11 === $depth );
$check( 'telegram login: the limit holds when requests interleave', 0 === $try( $sid, $code ) );

/* A skipped block must not read as a pass either. */
if ( 16 !== $ran ) { echo '::error::FAIL expected 16 assertions, ' . $ran . ' ran' . PHP_EOL; ++$failed; }
$done = true;
exit( $failed ? 1 : 0 );
