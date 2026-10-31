<?php
/**
 * Pre-live fix session — executable cases.
 *
 * QA-only; the release ZIP excludes tests/ (.gitattributes export-ignore). Usage:
 *   php tests/qa/prelive.php              # run every case, exit 1 on red
 *   php tests/qa/prelive.php --worker=verify --code=123456 ...   (internal)
 *
 * Every case prints PASS/FAIL next to the code path it exercises, so the
 * before/after claim in the report is a real execution, not a code reading.
 */

require_once __DIR__ . '/prelive-lib.php';

/* One state file shared by the parent and every worker process. */
if ( ! getenv( 'HSW_PRELIVE_STATE' ) ) {
	putenv( 'HSW_PRELIVE_STATE=' . sys_get_temp_dir() . '/hsw_prelive_state.json' );
}

/* ---------------------------------------------------------------------
 * Stubs the auth class needs from its surroundings
 * ------------------------------------------------------------------ */

class HSW_Helpers {
	public static function fa_num( $value ) { return (string) $value; }
	public static function icon( $name, $class = '', $raw = false ) { return ''; }
}

class HSW_Prelive_Account {
	public $settings = array();
	public function setting( $path, $fallback = '' ) {
		return array_key_exists( $path, $this->settings ) ? $this->settings[ $path ] : $fallback;
	}
	public function icon( $name ) { return ''; }
}

$GLOBALS['hsw_account'] = new HSW_Prelive_Account();

require_once dirname( __DIR__, 2 ) . '/includes/class-hsw-throttle.php';
/* HSW_PRELIVE_AUTH=<path> runs the same cases against an older copy (the "before"). */
$hsw_auth_file = getenv( 'HSW_PRELIVE_AUTH' );
require_once $hsw_auth_file ? $hsw_auth_file : dirname( __DIR__, 2 ) . '/includes/class-hsw-account-auth.php';

/* ---------------------------------------------------------------------
 * Harness helpers
 * ------------------------------------------------------------------ */

$GLOBALS['hsw_auth'] = null;

function prelive_auth() {
	if ( null === $GLOBALS['hsw_auth'] ) {
		$GLOBALS['hsw_auth'] = new HSW_Account_Auth( $GLOBALS['hsw_account'] );
	}
	return $GLOBALS['hsw_auth'];
}

function prelive_priv( $method ) {
	$ref = new ReflectionMethod( 'HSW_Account_Auth', $method );
	$ref->setAccessible( true );
	return $ref;
}

function prelive_default_settings() {
	return array(
		'auth.otp_enabled'      => 'yes',
		'auth.otp_email'        => 'yes',
		'auth.otp_login'        => 'yes',
		'auth.otp_register'     => 'yes',
		'auth.otp_sms'          => 'no',
		'auth.otp_2fa'          => 'yes',
		'auth.otp_ttl'          => 300,
		'auth.otp_resend'       => 60,
		'auth.otp_max_attempts' => 5,
		'notifications.enabled' => 'yes',
		'wallet.min_topup'      => 50000,
	);
}

function prelive_reset( $settings = array() ) {
	HSW_Prelive_Store::reset();
	$GLOBALS['hsw_mail']      = array();
	$GLOBALS['hsw_mail_fail'] = false;
	$GLOBALS['hsw_dbdelta']   = array();
	$GLOBALS['hsw_redirects'] = array();
	$GLOBALS['hsw_current_user'] = 0;
	$_POST = array();
	$_GET  = array();

	$GLOBALS['hsw_account']->settings = array_merge( prelive_default_settings(), $settings );

	update_option( 'woocommerce_enable_myaccount_registration', 'yes' );
	$_SERVER['REMOTE_ADDR'] = '203.0.113.9';

	/* Capture the generated code instead of delivering it. */
	add_filter(
		'hsw_otp_skip_delivery',
		static function ( $skip, $channel, $recipient, $code, $user_id = 0 ) {
			$GLOBALS['hsw_last_code']      = $code;
			$GLOBALS['hsw_last_channel']   = $channel;
			$GLOBALS['hsw_last_recipient'] = $recipient;
			return true;
		},
		10,
		5
	);
}

/** Clear the per-phone send cooldown (test "time travel"). */
function prelive_clear_cooldown( $recipient, $channel, $context ) {
	$norm = false !== strpos( $recipient, '@' ) ? strtolower( $recipient ) : prelive_norm_phone( $recipient );
	delete_transient( 'hsw_otp_cool_' . md5( $norm . '|' . $channel . '|' . $context ) );
}

function prelive_norm_phone( $phone ) {
	$digits = preg_replace( '/[^0-9]/', '', (string) $phone );
	if ( 10 === strlen( $digits ) && 0 === strpos( $digits, '9' ) ) {
		$digits = '0' . $digits;
	}
	return $digits;
}

function prelive_mk_user( $email, $roles = array( 'customer' ), $meta = array() ) {
	$id    = (int) ( HSW_Prelive_Store::read( 'seq', 'user', 100 ) ) + 1;
	HSW_Prelive_Store::write( 'seq', 'user', $id );
	$users   = hsw_prelive_users();
	$users[] = array(
		'ID'         => $id,
		'user_login' => 'u' . $id,
		'user_email' => $email,
		'roles'      => $roles,
	);
	hsw_prelive_set_users( $users );
	foreach ( $meta as $k => $v ) {
		update_user_meta( $id, $k, $v );
	}
	return $id;
}

function prelive_call( $method, $post = array() ) {
	$_POST = $post;
	try {
		prelive_auth()->$method();
		return array( 'ok' => null, 'data' => null, 'status' => 0, 'returned' => true );
	} catch ( HSW_Prelive_Json $e ) {
		return array( 'ok' => $e->ok, 'data' => $e->data, 'status' => $e->status, 'returned' => false );
	}
}

function prelive_fingerprint( $res ) {
	$data = is_array( $res['data'] ) ? $res['data'] : array( 'raw' => $res['data'] );
	unset( $data['expires'], $data['link'], $data['link_text'] );
	ksort( $data );
	return $res['status'] . '|' . ( $res['ok'] ? 'ok' : 'err' ) . '|' . md5( json_encode( $data ) );
}

function prelive_parallel( $n, $args ) {
	$parts = array( escapeshellarg( PHP_BINARY ), escapeshellarg( __FILE__ ), '--worker=verify' );
	foreach ( array( 'code', 'channel', 'context', 'email', 'phone' ) as $key ) {
		if ( isset( $args[ $key ] ) ) {
			$parts[] = escapeshellarg( '--' . $key . '=' . $args[ $key ] );
		}
	}
	/* Every worker waits for the same instant so the requests really overlap. */
	$at      = microtime( true ) + 1.5;
	$parts[] = '--at=' . $at;
	$cmd     = implode( ' ', $parts );

	$procs  = array();
	$pipes  = array();
	$output = array();
	for ( $i = 0; $i < $n; $i++ ) {
		$procs[ $i ] = proc_open( $cmd, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes[ $i ] );
	}
	foreach ( $procs as $i => $proc ) {
		$out          = trim( (string) stream_get_contents( $pipes[ $i ][1] ) );
		$err          = trim( (string) stream_get_contents( $pipes[ $i ][2] ) );
		$output[ $i ] = '' === $out ? 'EMPTY' : $out;
		if ( '' !== $err && getenv( 'HSW_PRELIVE_DEBUG' ) ) {
			$output[ $i ] .= $err;
		}
		fclose( $pipes[ $i ][1] );
		fclose( $pipes[ $i ][2] );
		proc_close( $proc );
	}
	if ( getenv( 'HSW_PRELIVE_DEBUG' ) ) {
		fwrite( STDERR, 'workers: ' . implode( ' | ', $output ) . "\n" );
	}
	return $output;
}

/* ---------------------------------------------------------------------
 * Worker process (one of the six racing verifications)
 * ------------------------------------------------------------------ */

if ( isset( $argv[1] ) && '--worker=verify' === $argv[1] ) {
	$opt = array();
	foreach ( array_slice( $argv, 2 ) as $arg ) {
		if ( 0 === strpos( $arg, '--' ) && false !== strpos( $arg, '=' ) ) {
			list( $k, $v )   = explode( '=', substr( $arg, 2 ), 2 );
			$opt[ $k ]       = $v;
		}
	}
	$GLOBALS['hsw_account']->settings = prelive_default_settings();
	$_SERVER['REMOTE_ADDR'] = '203.0.113.9';

	if ( isset( $opt['at'] ) ) {
		$wait = (float) $opt['at'] - microtime( true );
		if ( $wait > 0 ) {
			usleep( (int) ( $wait * 1000000 ) );
		}
	}

	$res = prelive_call(
		'ajax_otp_verify',
		array(
			'channel' => $opt['channel'] ?? 'email',
			'context' => $opt['context'] ?? 'login',
			'email'   => $opt['email'] ?? '',
			'phone'   => $opt['phone'] ?? '',
			'code'    => $opt['code'] ?? '',
			'nonce'   => 'x',
		)
	);
	$msg = is_array( $res['data'] ) ? (string) ( $res['data']['message'] ?? '' ) : '';
	if ( getenv( 'HSW_PRELIVE_DEBUG' ) ) {
		fwrite( STDERR, sprintf( '[worker code=%s ok=%s status=%d msg=%s]', $opt['code'] ?? '', var_export( $res['ok'], true ), $res['status'], $msg ) );
	}
	if ( $res['ok'] ) {
		echo 'OK';
	} elseif ( false !== strpos( $msg, 'بیش از حد' ) ) {
		echo 'CAPPED'; // attempt ceiling reached — the guess was never verified.
	} else {
		echo 'INVALID'; // the guess was processed and rejected.
	}
	exit( 0 );
}

/* ---------------------------------------------------------------------
 * Case registry
 * ------------------------------------------------------------------ */

$GLOBALS['hsw_results'] = array();

function case_run( $id, $title, callable $fn ) {
	$only = (string) getenv( 'HSW_PRELIVE_ONLY' );
	if ( '' !== $only && 0 !== strpos( $id, $only ) ) {
		return;
	}
	try {
		$r = $fn();
	} catch ( Throwable $e ) {
		$r = array( false, 'EXCEPTION: ' . $e->getMessage() );
	}
	$GLOBALS['hsw_results'][] = array( $id, $title, (bool) $r[0], (string) $r[1] );
	printf( "[%s] %-6s %s — %s\n", $r[0] ? 'PASS' : 'FAIL', $id, $title, $r[1] );
}

/* Constructed once, exactly like HSW_Plugin does at boot: registering hooks is
 * part of what the cases below assert. */
prelive_auth();

/* =====================================================================
 * ITEM 1 — the OTP attempt/claim bucket must follow the code, not the slot
 * ===================================================================== */

case_run(
	'1.1',
	'two consecutive logins with two different codes both succeed',
	static function () {
		prelive_reset();
		prelive_mk_user( 'a@example.com' );

		$r1 = prelive_call( 'ajax_otp_send', array( 'channel' => 'email', 'context' => 'login', 'email' => 'a@example.com', 'nonce' => 'x' ) );
		$c1 = $GLOBALS['hsw_last_code'] ?? '';
		$v1 = prelive_call( 'ajax_otp_verify', array( 'channel' => 'email', 'context' => 'login', 'email' => 'a@example.com', 'code' => $c1, 'nonce' => 'x' ) );

		prelive_clear_cooldown( 'a@example.com', 'email', 'login' );
		$r2 = prelive_call( 'ajax_otp_send', array( 'channel' => 'email', 'context' => 'login', 'email' => 'a@example.com', 'nonce' => 'x' ) );
		$c2 = $GLOBALS['hsw_last_code'] ?? '';
		$v2 = prelive_call( 'ajax_otp_verify', array( 'channel' => 'email', 'context' => 'login', 'email' => 'a@example.com', 'code' => $c2, 'nonce' => 'x' ) );

		$ok = $v1['ok'] && $v2['ok'];
		return array( $ok, sprintf( 'first=%s second=%s (%s)', $v1['ok'] ? 'ok' : 'FAIL', $v2['ok'] ? 'ok' : 'FAIL', is_array( $v2['data'] ) ? ( $v2['data']['message'] ?? '' ) : '' ) );
	}
);

case_run(
	'1.2',
	'five wrong guesses then a freshly issued code is accepted',
	static function () {
		prelive_reset();
		prelive_mk_user( 'b@example.com' );

		prelive_call( 'ajax_otp_send', array( 'channel' => 'email', 'context' => 'login', 'email' => 'b@example.com', 'nonce' => 'x' ) );
		for ( $i = 0; $i < 5; $i++ ) {
			prelive_call( 'ajax_otp_verify', array( 'channel' => 'email', 'context' => 'login', 'email' => 'b@example.com', 'code' => '000000', 'nonce' => 'x' ) );
		}
		prelive_clear_cooldown( 'b@example.com', 'email', 'login' );
		prelive_call( 'ajax_otp_send', array( 'channel' => 'email', 'context' => 'login', 'email' => 'b@example.com', 'nonce' => 'x' ) );
		$code = $GLOBALS['hsw_last_code'] ?? '';
		$v    = prelive_call( 'ajax_otp_verify', array( 'channel' => 'email', 'context' => 'login', 'email' => 'b@example.com', 'code' => $code, 'nonce' => 'x' ) );

		$msg = is_array( $v['data'] ) ? ( $v['data']['message'] ?? '' ) : '';
		return array( (bool) $v['ok'], sprintf( 'new code accepted=%s (%s)', $v['ok'] ? 'yes' : 'no', $msg ) );
	}
);

case_run(
	'1.3',
	'6 parallel verifications of one code: at most 1 succeeds',
	static function () {
		prelive_reset();
		prelive_mk_user( 'c@example.com' );
		prelive_call( 'ajax_otp_send', array( 'channel' => 'email', 'context' => 'login', 'email' => 'c@example.com', 'nonce' => 'x' ) );
		$code = $GLOBALS['hsw_last_code'] ?? '';

		$raw = prelive_parallel(
			6,
			array(
				'method'  => 'verify',
				'code'    => $code,
				'channel' => 'email',
				'context' => 'login',
				'email'   => 'c@example.com',
			)
		);
		$ok  = count( array_filter( $raw, static function ( $line ) { return 0 === strpos( $line, 'OK' ); } ) );
		/* Acceptance: a code can never be redeemed twice under concurrency (<= 1). */
		return array( $ok <= 1, sprintf( 'successes=%d (must be <= 1) raw=%s', $ok, implode( ',', array_map( static function ( $l ) { return substr( $l, 0, 12 ); }, $raw ) ) ) );
	}
);

case_run(
	'1.4',
	'6 parallel wrong guesses: every attempt is counted and the answers are uniform',
	static function () {
		prelive_reset();
		prelive_mk_user( 'd@example.com' );
		prelive_call( 'ajax_otp_send', array( 'channel' => 'email', 'context' => 'login', 'email' => 'd@example.com', 'nonce' => 'x' ) );

		$answers = prelive_parallel(
			6,
			array(
				'method'  => 'verify',
				'code'    => '999999',
				'channel' => 'email',
				'context' => 'login',
				'email'   => 'd@example.com',
			)
		);

		/*
		 * The answer text is deliberately uniform now (it must not reveal whether an
		 * account exists), so the property is measured on the counter itself: the
		 * atomic bucket must have seen all six guesses.
		 */
		$state = HSW_Prelive_Store::snapshot();
		$hits  = 0;
		foreach ( (array) ( $state['throttle'] ?? array() ) as $key => $row ) {
			if ( 0 === strpos( $key, 'otp_attempt:' ) ) {
				$hits = max( $hits, (int) $row['hits'] );
			}
		}
		$uniform = 1 === count( array_unique( array_map( static function ( $l ) { return substr( $l, 0, 7 ); }, $answers ) ) );

		return array( $hits >= 6 && $uniform, sprintf( 'attempt-counter hits=%d (want >=6), uniform answers=%s', $hits, $uniform ? 'yes' : 'no' ) );
	}
);

case_run(
	'1.3b',
	'the same code cannot be redeemed twice sequentially',
	static function () {
		prelive_reset();
		prelive_mk_user( 'c2@example.com' );
		prelive_call( 'ajax_otp_send', array( 'channel' => 'email', 'context' => 'login', 'email' => 'c2@example.com', 'nonce' => 'x' ) );
		$code = $GLOBALS['hsw_last_code'] ?? '';
		$v1   = prelive_call( 'ajax_otp_verify', array( 'channel' => 'email', 'context' => 'login', 'email' => 'c2@example.com', 'code' => $code, 'nonce' => 'x' ) );
		$v2   = prelive_call( 'ajax_otp_verify', array( 'channel' => 'email', 'context' => 'login', 'email' => 'c2@example.com', 'code' => $code, 'nonce' => 'x' ) );
		return array( (bool) $v1['ok'] && ! $v2['ok'], sprintf( 'first=%s second=%s', $v1['ok'] ? 'ok' : 'fail', $v2['ok'] ? 'ok' : 'rejected' ) );
	}
);

/* =====================================================================
 * ITEM 2 — account enumeration
 * ===================================================================== */

case_run(
	'2.1',
	'send-code response is identical for existing / unknown / admin account',
	static function () {
		prelive_reset();
		prelive_mk_user( 'known@example.com' );
		prelive_mk_user( 'admin@example.com', array( 'administrator' ) );

		$known = prelive_call( 'ajax_otp_send', array( 'channel' => 'email', 'context' => 'login', 'email' => 'known@example.com', 'nonce' => 'x' ) );
		prelive_clear_cooldown( 'unknown@example.com', 'email', 'login' );
		$unknown = prelive_call( 'ajax_otp_send', array( 'channel' => 'email', 'context' => 'login', 'email' => 'unknown@example.com', 'nonce' => 'x' ) );
		prelive_clear_cooldown( 'admin@example.com', 'email', 'login' );
		$admin = prelive_call( 'ajax_otp_send', array( 'channel' => 'email', 'context' => 'login', 'email' => 'admin@example.com', 'nonce' => 'x' ) );

		$f1 = prelive_fingerprint( $known );
		$f2 = prelive_fingerprint( $unknown );
		$f3 = prelive_fingerprint( $admin );
		$ok = ( $f1 === $f2 && $f2 === $f3 );
		return array( $ok, sprintf( 'known=%s unknown=%s admin=%s', $known['status'], $unknown['status'], $admin['status'] ) );
	}
);

case_run(
	'2.2',
	'no switch_tab/hint keys leak in any send response',
	static function () {
		prelive_reset();
		$u = prelive_call( 'ajax_otp_send', array( 'channel' => 'email', 'context' => 'login', 'email' => 'nobody@example.com', 'nonce' => 'x' ) );
		$data = is_array( $u['data'] ) ? $u['data'] : array();
		$leak = array_intersect( array_keys( $data ), array( 'switch_tab' ) );
		return array( ! $leak, empty( $leak ) ? 'no tab hint' : 'leaked: ' . implode( ',', $leak ) );
	}
);

case_run(
	'2.3',
	'send throttle runs even when the account does not exist',
	static function () {
		prelive_reset();
		$got429 = false;
		for ( $i = 0; $i < 25; $i++ ) {
			$r = prelive_call( 'ajax_otp_send', array( 'channel' => 'email', 'context' => 'login', 'email' => 'nobody' . $i . '@example.com', 'nonce' => 'x' ) );
			if ( 429 === $r['status'] ) {
				$got429 = true;
				break;
			}
		}
		return array( $got429, $got429 ? 'throttled at request ' . ( $i + 1 ) : '25 unknown-account requests, never throttled' );
	}
);

/* =====================================================================
 * ITEM 3 — phone signup must not adopt an unverified e-mail
 * ===================================================================== */

case_run(
	'3.1',
	'phone signup with a victim e-mail does not create an account on that e-mail',
	static function () {
		prelive_reset( array( 'auth.otp_sms' => 'yes', 'auth.otp_email' => 'no' ) );
		add_filter( 'hsw_has_sms_provider', static function () { return true; } );
		add_filter( 'hsw_send_sms_otp', static function () { return true; } );
		prelive_mk_user( 'victim@example.com' );

		prelive_call( 'ajax_otp_send', array( 'channel' => 'sms', 'context' => 'register', 'phone' => '09121234567', 'nonce' => 'x' ) );
		$code = $GLOBALS['hsw_last_code'] ?? '';
		$r    = prelive_call(
			'ajax_otp_verify',
			array(
				'channel' => 'sms',
				'context' => 'register',
				'phone'   => '09121234567',
				'code'    => $code,
				'name'    => 'Ali Test',
				'email'   => 'victim@example.com',
				'nonce'   => 'x',
			)
		);

		$victim = get_user_by( 'email', 'victim@example.com' );
		$ok     = $r['ok'] && ( ! $victim || 'Ali Test' !== ( $victim->first_name ?? '' ) );
		$new    = get_user_by( 'login', 'hsw_1234567' ) ?: get_user_by( 'login', 'hsw_9121234567' );
		return array(
			$ok,
			sprintf(
				'signup=%s victim_untouched=%s',
				$r['ok'] ? 'ok' : 'fail:' . ( is_array( $r['data'] ) ? ( $r['data']['message'] ?? '' ) : '' ),
				$victim ? 'no (account exists)' : 'yes'
			)
		);
	}
);

/* =====================================================================
 * ITEM 4 — case-sensitive tokens must survive sanitize_key()
 * ===================================================================== */

case_run(
	'4.1',
	'telegram link token survives the key sanitiser used by the webhook',
	static function () {
		prelive_reset();
		$uid   = prelive_mk_user( 'tg@example.com' );
		$token = prelive_priv( 'telegram_link_token' )->invoke( prelive_auth(), $uid );
		$parsed = sanitize_key( $token );
		return array(
			$parsed === $token && (bool) get_transient( 'hsw_telegram_link_' . $parsed ),
			sprintf( 'token=%s parsed=%s', strlen( $token ) . ' chars', $parsed === $token ? 'unchanged' : 'MUTATED' )
		);
	}
);

case_run(
	'4.2',
	'2FA challenge token survives is_2fa_challenge() and render',
	static function () {
		prelive_reset();
		$uid   = prelive_mk_user( 'tf@example.com', array( 'customer' ), array( '_hsw_auth_phone' => '' ) );
		$token = prelive_priv( 'create_2fa_challenge' )->invoke( prelive_auth(), $uid, 'email', 'tf@example.com' );
		if ( is_wp_error( $token ) ) {
			return array( false, 'challenge not created: ' . $token->get_error_message() );
		}
		$_GET['hsw_2fa'] = $token;
		$challenge       = prelive_auth()->is_2fa_challenge();
		$html            = prelive_auth()->render_2fa_challenge( array() );
		unset( $_GET['hsw_2fa'] );
		return array(
			$challenge && '' !== $html,
			sprintf( 'is_2fa_challenge=%s render=%s', $challenge ? 'yes' : 'no', '' !== $html ? 'yes' : 'empty' )
		);
	}
);

/* =====================================================================
 * ITEM 5 — 2FA hardening (disable needs proof, independent of OTP switch)
 * ===================================================================== */

case_run(
	'5.1',
	'disabling 2FA without password or code is refused',
	static function () {
		prelive_reset();
		$uid = prelive_mk_user( '2fa@example.com', array( 'customer' ), array( '_hsw_2fa_enabled' => 'yes', '_hsw_2fa_channel' => 'email' ) );
		wp_set_current_user( $uid );
		$r = prelive_call( 'ajax_2fa_disable', array( 'nonce' => 'x' ) );
		$still = 'yes' === get_user_meta( $uid, '_hsw_2fa_enabled', true );
		return array( ! $r['ok'] && $still, sprintf( 'response=%s, flag kept=%s', $r['ok'] ? 'success' : 'error', $still ? 'yes' : 'no' ) );
	}
);

case_run(
	'5.2',
	'2FA still challenges when OTP login is switched off',
	static function () {
		prelive_reset( array( 'auth.otp_enabled' => 'no' ) );
		$uid = prelive_mk_user( '2fb@example.com', array( 'customer' ), array( '_hsw_2fa_enabled' => 'yes', '_hsw_2fa_channel' => 'email', '_hsw_2fa_phone' => 'x@example.com' ) );
		$user = get_user_by( 'id', $uid );
		$redirected = false;
		$seen       = '';
		try {
			prelive_auth()->maybe_start_2fa( $user->user_login, $user );
			$seen = 'no redirect';
		} catch ( HSW_Prelive_Redirect $e ) {
			$seen       = $e->url;
			$redirected = false !== strpos( $e->url, 'hsw_2fa=' );
		}
		return array( $redirected, $redirected ? 'challenge issued' : 'no challenge — 2FA silently off (' . $seen . ')' );
	}
);

case_run(
	'5.3',
	'resend is capped at 3 per token and does not extend the expiry',
	static function () {
		prelive_reset();
		$uid   = prelive_mk_user( '2fc@example.com', array( 'customer' ), array( '_hsw_auth_phone' => '' ) );
		$token = prelive_priv( 'create_2fa_challenge' )->invoke( prelive_auth(), $uid, 'email', '2fc@example.com' );
		if ( is_wp_error( $token ) ) {
			return array( false, 'no challenge' );
		}
		$before = (int) ( get_transient( 'hsw_2fa_' . $token )['expires'] ?? 0 );
		$codes  = array();
		for ( $i = 0; $i < 4; $i++ ) {
			delete_transient( 'hsw_otp_cool_' . md5( '2fc@example.com|email|2fa' ) );
			$r       = prelive_call( 'ajax_2fa_resend', array( 'token' => $token, 'nonce' => 'x' ) );
			$codes[] = $r['ok'] ? 'ok' : ( 429 === $r['status'] ? '429' : 'err' );
		}
		$after = (int) ( get_transient( 'hsw_2fa_' . $token )['expires'] ?? 0 );
		$ok    = ( 'ok' === (string) ( $codes[0] ?? '' ) && '429' === (string) ( $codes[3] ?? '' ) && $after <= $before );
		return array( $ok, 'resends=' . implode( ',', $codes ) . ' expires ' . ( $after <= $before ? 'fixed' : 'EXTENDED' ) );
	}
);

case_run(
	'5.4',
	'enabling 2FA sends a security e-mail to the account address',
	static function () {
		prelive_reset();
		$uid = prelive_mk_user( '2fd@example.com' );
		wp_set_current_user( $uid );
		$send = prelive_call( 'ajax_2fa_setup_send', array( 'channel' => 'email', 'nonce' => 'x' ) );
		$code = $GLOBALS['hsw_last_code'] ?? '';
		$GLOBALS['hsw_mail'] = array();
		$ver  = prelive_call( 'ajax_2fa_setup_verify', array( 'code' => $code, 'nonce' => 'x' ) );
		$mails = array_filter(
			$GLOBALS['hsw_mail'],
			static function ( $m ) { return '2fd@example.com' === $m['to']; }
		);
		$enabled = 'yes' === get_user_meta( $uid, '_hsw_2fa_enabled', true );
		return array( $send['ok'] && $ver['ok'] && $enabled && count( $mails ) >= 1, sprintf( 'enabled=%s mails=%d', $enabled ? 'yes' : 'no', count( $mails ) ) );
	}
);

/* =====================================================================
 * P0-1 — 2FA fail-open must not cover the cooldown
 * ===================================================================== */

function prelive_two_factor_admin( $email = 'adm@example.com' ) {
	$uid = prelive_mk_user(
		$email,
		array( 'administrator' ),
		array( '_hsw_2fa_enabled' => 'yes', '_hsw_2fa_channel' => 'email' )
	);
	wp_set_current_user( $uid );
	return get_user_by( 'id', $uid );
}

function prelive_login_attempt( $user ) {
	$url = null;
	try {
		prelive_auth()->maybe_start_2fa( $user->user_login, $user );
	} catch ( HSW_Prelive_Redirect $e ) {
		$url = $e->url;
	}
	return $url;
}

case_run(
	'6.1',
	'admin logs in twice within the cooldown: second login is NOT authenticated',
	static function () {
		prelive_reset();
		$user = prelive_two_factor_admin();

		$first = prelive_login_attempt( $user ); // challenge issued, cooldown set

		/* the real flow sets the auth cookie before wp_login fires */
		wp_set_current_user( $user->ID );
		$second = prelive_login_attempt( $user );

		$still_in = get_current_user_id() > 0;
		$reason   = '' === (string) $second ? 'no redirect' : ( false !== strpos( (string) $second, 'hsw_2fa_error=cooldown' ) ? 'cooldown' : 'other' );

		return array(
			! $still_in && 'cooldown' === $reason,
			sprintf( 'first=%s second=%s authenticated=%s', null === $first ? 'none' : 'challenge', $reason, $still_in ? 'YES' : 'no' )
		);
	}
);

case_run(
	'6.2',
	'transport failure + default filter: privileged user is blocked',
	static function () {
		prelive_reset();
		$GLOBALS['hsw_filters']['hsw_otp_skip_delivery'] = array();
		$GLOBALS['hsw_mail_fail']                        = true;
		$user = prelive_two_factor_admin();

		$url      = prelive_login_attempt( $user );
		$still_in = get_current_user_id() > 0;

		return array(
			! $still_in && false !== strpos( (string) $url, 'hsw_2fa_error=delivery' ),
			sprintf( 'redirect=%s authenticated=%s', false === strpos( (string) $url, 'hsw_2fa_error=' ) ? 'none' : 'delivery', $still_in ? 'YES' : 'no' )
		);
	}
);

case_run(
	'6.3',
	'transport failure + hsw_2fa_fail_open_privileged=true: privileged user may continue',
	static function () {
		prelive_reset();
		$GLOBALS['hsw_filters']['hsw_otp_skip_delivery'] = array();
		$GLOBALS['hsw_mail_fail']                        = true;
		add_filter( 'hsw_2fa_fail_open_privileged', static function () { return true; } );
		$user = prelive_two_factor_admin( 'adm3@example.com' );

		$url      = prelive_login_attempt( $user );
		$still_in = get_current_user_id() > 0;

		return array( null === $url && $still_in, sprintf( 'redirect=%s authenticated=%s', null === $url ? 'none' : 'yes', $still_in ? 'yes' : 'no' ) );
	}
);

/* =====================================================================
 * P0-6 — the disable-2FA proof needs an attempt limit
 * ===================================================================== */

function prelive_user_with_password( $email, $plain ) {
	$uid = prelive_mk_user( $email, array( 'customer' ), array( '_hsw_2fa_enabled' => 'yes', '_hsw_2fa_channel' => 'email' ) );
	HSW_Prelive_Store::mutate(
		static function ( &$all ) use ( $uid, $plain ) {
			foreach ( $all['users']['all'] as $i => $row ) {
				if ( (int) $row['ID'] === $uid ) {
					$all['users']['all'][ $i ]['user_pass'] = wp_hash_password( $plain );
				}
			}
		}
	);
	wp_set_current_user( $uid );
	return $uid;
}

case_run(
	'7.1',
	'six wrong passwords: the sixth answers 429',
	static function () {
		prelive_reset();
		prelive_user_with_password( '2fe@example.com', 'secret123' );
		$statuses = array();
		for ( $i = 0; $i < 6; $i++ ) {
			$r          = prelive_call( 'ajax_2fa_disable', array( 'password' => 'wrong' . $i, 'nonce' => 'x' ) );
			$statuses[] = (int) $r['status'];
		}
		return array( 429 === $statuses[5], 'statuses=' . implode( ',', $statuses ) );
	}
);

case_run(
	'7.2',
	'the correct password is still refused while locked out',
	static function () {
		prelive_reset();
		$uid = prelive_user_with_password( '2ff@example.com', 'secret123' );
		for ( $i = 0; $i < 5; $i++ ) {
			prelive_call( 'ajax_2fa_disable', array( 'password' => 'wrong' . $i, 'nonce' => 'x' ) );
		}
		$r     = prelive_call( 'ajax_2fa_disable', array( 'password' => 'secret123', 'nonce' => 'x' ) );
		$still = 'yes' === get_user_meta( $uid, '_hsw_2fa_enabled', true );
		return array( 429 === (int) $r['status'] && $still, sprintf( 'status=%d still_enabled=%s', $r['status'], $still ? 'yes' : 'no' ) );
	}
);

case_run(
	'7.3',
	'the correct password inside the limit really disables 2FA (real wp_check_password)',
	static function () {
		prelive_reset();
		$uid = prelive_user_with_password( '2fg@example.com', 'secret123' );
		prelive_call( 'ajax_2fa_disable', array( 'password' => 'nope', 'nonce' => 'x' ) );
		$r     = prelive_call( 'ajax_2fa_disable', array( 'password' => 'secret123', 'nonce' => 'x' ) );
		$after = 'yes' === get_user_meta( $uid, '_hsw_2fa_enabled', true );
		return array( $r['ok'] && ! $after, sprintf( 'ok=%s still_enabled=%s', $r['ok'] ? 'yes' : 'no', $after ? 'yes' : 'no' ) );
	}
);

/* =====================================================================
 * P0-7 — XML-RPC / Application Passwords skip wp_login entirely
 * ===================================================================== */

case_run(
	'8.1',
	'xmlrpc_enabled is forced off by default',
	static function () {
		prelive_reset();
		$v = apply_filters( 'xmlrpc_enabled', true );
		return array( false === $v, 'value=' . var_export( $v, true ) );
	}
);

case_run(
	'8.2',
	'hsw_disable_xmlrpc=false lets the shop keep XML-RPC',
	static function () {
		prelive_reset();
		add_filter( 'hsw_disable_xmlrpc', static function () { return false; } );
		$v = apply_filters( 'xmlrpc_enabled', true );
		return array( true === $v, 'value=' . var_export( $v, true ) );
	}
);

case_run(
	'8.3',
	'application passwords refused for a 2FA account, kept for a plain customer',
	static function () {
		prelive_reset();
		$two   = prelive_mk_user( 'ap@example.com', array( 'customer' ), array( '_hsw_2fa_enabled' => 'yes' ) );
		$plain = prelive_mk_user( 'plain@example.com', array( 'customer' ) );
		$admin = prelive_mk_user( 'ap-admin@example.com', array( 'administrator' ) );

		$a = apply_filters( 'wp_is_application_passwords_available_for_user', true, get_user_by( 'id', $two ) );
		$b = apply_filters( 'wp_is_application_passwords_available_for_user', true, get_user_by( 'id', $plain ) );
		$c = apply_filters( 'wp_is_application_passwords_available_for_user', true, get_user_by( 'id', $admin ) );

		return array( false === $a && true === $b && false === $c, sprintf( '2fa=%s plain=%s admin=%s', var_export( $a, true ), var_export( $b, true ), var_export( $c, true ) ) );
	}
);

case_run(
	'8.4',
	'hsw_app_passwords_for_privileged=true restores them',
	static function () {
		prelive_reset();
		add_filter( 'hsw_app_passwords_for_privileged', static function () { return true; } );
		$two = prelive_mk_user( 'ap2@example.com', array( 'customer' ), array( '_hsw_2fa_enabled' => 'yes' ) );
		$a   = apply_filters( 'wp_is_application_passwords_available_for_user', true, get_user_by( 'id', $two ) );
		return array( true === $a, 'value=' . var_export( $a, true ) );
	}
);

/* =====================================================================
 * P0-2 — the session must not survive the 2FA gate
 * ===================================================================== */

case_run(
	'9.1',
	'the session token issued at wp_login is destroyed before the challenge',
	static function () {
		prelive_reset();
		$user = prelive_two_factor_admin( 'sess@example.com' );
		$GLOBALS['hsw_destroyed'] = array();

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID ); // WordPress mints the cookie *before* wp_login
		$token = (string) ( $GLOBALS['hsw_issued_token'] ?? '' );

		$url       = prelive_login_attempt( $user );
		$destroyed = (array) ( $GLOBALS['hsw_destroyed'][ $user->ID ] ?? array() );

		return array(
			'' !== $token && in_array( $token, $destroyed, true ) && 0 === get_current_user_id(),
			sprintf( 'token=%s destroyed=%s still_logged_in=%s', '' === $token ? 'none' : 'yes', in_array( $token, $destroyed, true ) ? 'yes' : 'NO', get_current_user_id() ? 'YES' : 'no' )
		);
	}
);

case_run(
	'9.2',
	'the OTP login path (skip_2fa) keeps its session',
	static function () {
		prelive_reset();
		$user = prelive_two_factor_admin( 'sess2@example.com' );
		$GLOBALS['hsw_destroyed'] = array();

		$ref = new ReflectionMethod( 'HSW_Account_Auth', 'login_user' );
		$ref->setAccessible( true );
		$ref->invoke( prelive_auth(), $user );

		$destroyed = (array) ( $GLOBALS['hsw_destroyed'][ $user->ID ] ?? array() );
		return array( empty( $destroyed ) && get_current_user_id() === (int) $user->ID, sprintf( 'destroyed=%d logged_in=%s', count( $destroyed ), get_current_user_id() === (int) $user->ID ? 'yes' : 'no' ) );
	}
);

/* =====================================================================
 * P0-4 — one answer for every account state
 * ===================================================================== */

case_run(
	'10.1',
	'send twice for existing / unknown / admin: byte-identical responses',
	static function () {
		prelive_reset();
		prelive_mk_user( 'exist@example.com' );
		prelive_mk_user( 'boss@example.com', array( 'administrator' ) );

		$seen = array();
		foreach ( array( 'exist@example.com', 'missing@example.com', 'boss@example.com' ) as $email ) {
			for ( $i = 0; $i < 2; $i++ ) {
				$r      = prelive_call( 'ajax_otp_send', array( 'channel' => 'email', 'context' => 'login', 'email' => $email, 'nonce' => 'x' ) );
				$seen[] = prelive_fingerprint( $r ) . '#' . $r['status'];
			}
		}

		$unique = array_unique( $seen );
		return array( 1 === count( $unique ), sprintf( 'distinct responses=%d (%s)', count( $unique ), implode( ' | ', array_map( static function ( $s ) { return substr( $s, 0, 3 ); }, $unique ) ) ) );
	}
);

case_run(
	'10.2',
	'delivery failure in the login flow answers the same ack',
	static function () {
		prelive_reset();
		prelive_mk_user( 'deliver@example.com' );
		$ok = prelive_call( 'ajax_otp_send', array( 'channel' => 'email', 'context' => 'login', 'email' => 'deliver@example.com', 'nonce' => 'x' ) );

		prelive_clear_cooldown( 'deliver@example.com', 'email', 'login' );
		$GLOBALS['hsw_filters']['hsw_otp_skip_delivery'] = array();
		$GLOBALS['hsw_mail_fail']                        = true;
		$send_failed                                     = prelive_call( 'ajax_otp_send', array( 'channel' => 'email', 'context' => 'login', 'email' => 'deliver@example.com', 'nonce' => 'x' ) );

		return array(
			$ok['ok'] && $send_failed['ok'] && prelive_fingerprint( $ok ) === prelive_fingerprint( $send_failed ),
			sprintf( 'sent=%s failed=%s', $ok['status'], $send_failed['status'] )
		);
	}
);

case_run(
	'10.3',
	'verify: wrong code / expired code / unknown account answer identically',
	static function () {
		prelive_reset();
		prelive_mk_user( 'v@example.com' );

		prelive_call( 'ajax_otp_send', array( 'channel' => 'email', 'context' => 'login', 'email' => 'v@example.com', 'nonce' => 'x' ) );
		$wrong = prelive_call( 'ajax_otp_verify', array( 'channel' => 'email', 'context' => 'login', 'email' => 'v@example.com', 'code' => '000001', 'nonce' => 'x' ) );

		/* drop the stored code, then present a well-formed one: "expired" */
		delete_transient( 'hsw_otp_' . md5( 'v@example.com|email|login' ) );
		$expired = prelive_call( 'ajax_otp_verify', array( 'channel' => 'email', 'context' => 'login', 'email' => 'v@example.com', 'code' => '123456', 'nonce' => 'x' ) );
		$missing = prelive_call( 'ajax_otp_verify', array( 'channel' => 'email', 'context' => 'login', 'email' => 'ghost@example.com', 'code' => '123456', 'nonce' => 'x' ) );

		$prints = array( prelive_fingerprint( $wrong ), prelive_fingerprint( $expired ), prelive_fingerprint( $missing ) );
		return array( 1 === count( array_unique( $prints ) ), sprintf( 'statuses=%d/%d/%d distinct=%d', $wrong['status'], $expired['status'], $missing['status'], count( array_unique( $prints ) ) ) );
	}
);

/* =====================================================================
 * Runner
 * ------------------------------------------------------------------ */

$failed = 0;
foreach ( $GLOBALS['hsw_results'] as $row ) {
	if ( ! $row[2] ) {
		$failed++;
	}
}
printf( "\n%d cases, %d failed\n", count( $GLOBALS['hsw_results'] ), $failed );
exit( $failed > 0 ? 1 : 0 );
