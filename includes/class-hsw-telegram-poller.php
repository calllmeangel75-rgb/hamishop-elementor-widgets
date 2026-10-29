<?php
/**
 * Telegram long-polling worker.
 *
 * Some shared hosts can talk to the Bot API but cannot receive Telegram's
 * webhook: `getWebhookInfo` reports `Connection timed out`, nothing reaches
 * PHP, while `getUpdates` works fine from the same host. This worker covers
 * exactly that case — it pulls the updates instead of waiting for a push.
 *
 * Design constraints (shared hosting, no SSH, no root):
 *   - one short `getUpdates` per run, `timeout=0`, at most 10 updates;
 *   - the offset lives in a non-autoloaded option so a message is never
 *     processed twice;
 *   - a 45s lock stops two workers (cron + a manual hit) from overlapping;
 *   - with the switch off nothing runs: `maybe_serve()` returns on a single
 *     `$_GET` check and the schedule is cleaned up in the admin only.
 *
 * The message rules themselves are not duplicated here: every update is handed
 * to `HSW_Account_Auth::handle_telegram_update()`, the same method the webhook
 * uses.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Telegram_Poller {

	const CRON_HOOK   = 'hsw_tg_poll';
	const OFFSET_KEY  = 'hsw_tg_offset';
	const LOCK_KEY    = 'hsw_tg_lock';
	const READY_KEY   = 'hsw_tg_poll_ready';

	/** Seconds a run may keep the lock. */
	const LOCK_TTL = 45;

	public function __construct() {
		/* Priority 1: answer the worker URL before anything renders. */
		add_action( 'init', array( $this, 'maybe_serve' ), 1 );
		add_action( self::CRON_HOOK, array( $this, 'cron_run' ) );
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'init', array( $this, 'maybe_schedule' ), 20 );
	}

	/* ---------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	private function setting( $key, $fallback = '' ) {
		$account = HSW_Account::instance();
		return $account ? $account->setting( 'auth.' . $key, $fallback ) : $fallback;
	}

	private function token() {
		return trim( (string) $this->setting( 'telegram_token' ) );
	}

	public function enabled() {
		return '' !== $this->token() && 'yes' === $this->setting( 'telegram_polling', 'no' );
	}

	/* ---------------------------------------------------------------------
	 * Worker URL: /?hsw_tg_poll=1&secret=…
	 * ------------------------------------------------------------------ */

	public function maybe_serve() {
		if ( empty( $_GET['hsw_tg_poll'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- secret-guarded worker endpoint.
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();

		$secret = (string) $this->setting( 'telegram_secret' );
		$given  = isset( $_GET['secret'] ) ? (string) wp_unslash( $_GET['secret'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $secret || ! hash_equals( $secret, $given ) ) {
			status_header( 403 );
			echo 'forbidden';
			exit;
		}

		$result = $this->run();

		status_header( 200 );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( $result );
		exit;
	}

	public function cron_run() {
		$this->run();
	}

	/* ---------------------------------------------------------------------
	 * Execution
	 * ------------------------------------------------------------------ */

	/**
	 * One polling pass.
	 *
	 * @return array Status summary (also used as the worker's JSON body).
	 */
	public function run() {
		if ( ! $this->enabled() ) {
			return array(
				'ok'    => false,
				'error' => 'disabled',
			);
		}

		if ( false !== get_transient( self::LOCK_KEY ) ) {
			return array(
				'ok'      => true,
				'skipped' => 'busy',
			);
		}
		set_transient( self::LOCK_KEY, time(), self::LOCK_TTL );

		$out = $this->poll();

		delete_transient( self::LOCK_KEY );

		return $out;
	}

	private function poll() {
		/* A registered webhook makes getUpdates answer 409, so drop it once. */
		if ( ! $this->prepare_endpoint() ) {
			return array(
				'ok'    => false,
				'error' => 'deleteWebhook failed',
			);
		}

		$offset = (int) get_option( self::OFFSET_KEY, 0 );

		/*
		 * First run: ask for the newest update only. Without this the bot would
		 * digest up to 100 old messages that queued while polling was off.
		 */
		if ( 0 === $offset ) {
			$latest = $this->api( 'getUpdates', array( 'offset' => -1, 'limit' => 1 ) );
			if ( isset( $latest['result'][0]['update_id'] ) ) {
				$offset = (int) $latest['result'][0]['update_id'] + 1;
				update_option( self::OFFSET_KEY, $offset, false );
			}
		}

		$response = $this->api(
			'getUpdates',
			array(
				'offset'          => $offset,
				'limit'           => 10,
				'timeout'         => 0,
				'allowed_updates' => wp_json_encode( array( 'message' ) ),
			)
		);

		if ( ! is_array( $response ) ) {
			return array(
				'ok'    => false,
				'error' => 'request failed',
			);
		}
		if ( empty( $response['ok'] ) ) {
			return array(
				'ok'    => false,
				'error' => isset( $response['description'] ) ? (string) $response['description'] : 'not ok',
			);
		}

		$updates   = isset( $response['result'] ) && is_array( $response['result'] ) ? $response['result'] : array();
		$processed = 0;
		$highest   = $offset;
		$auth      = HSW_Account::instance()->auth;

		foreach ( $updates as $update ) {
			if ( ! is_array( $update ) ) {
				continue;
			}
			$id = isset( $update['update_id'] ) ? (int) $update['update_id'] : 0;

			if ( $auth && method_exists( $auth, 'handle_telegram_update' ) ) {
				$auth->handle_telegram_update( $update );
				$processed++;
			}
			if ( $id >= $highest ) {
				$highest = $id + 1;
			}
		}

		if ( $highest > $offset ) {
			update_option( self::OFFSET_KEY, $highest, false );
		}

		return array(
			'ok'        => true,
			'processed' => $processed,
			'offset'    => $highest,
		);
	}

	/**
	 * `deleteWebhook` once per token. The token hash doubles as the cache key,
	 * so pasting a new bot token re-prepares the endpoint automatically.
	 */
	private function prepare_endpoint() {
		$hash = md5( $this->token() );

		if ( 'yes' === get_option( self::READY_KEY ) && $hash === (string) get_option( self::READY_KEY . '_hash' ) ) {
			return true;
		}

		delete_option( self::OFFSET_KEY );

		$deleted = $this->api( 'deleteWebhook', array( 'drop_pending_updates' => 'true' ) );
		if ( ! is_array( $deleted ) || empty( $deleted['ok'] ) ) {
			return false;
		}

		update_option( self::READY_KEY, 'yes', false );
		update_option( self::READY_KEY . '_hash', $hash, false );

		return true;
	}

	/**
	 * Bot API call over WordPress's HTTP API, which uses cURL when it is
	 * available. Short timeout: a run must never hold a PHP worker longer
	 * than a shared host likes.
	 *
	 * @param string $method API method.
	 * @param array  $args   Query arguments.
	 * @return array|null Decoded body or null on transport failure.
	 */
	protected function api( $method, $args = array() ) {
		$url = 'https://api.telegram.org/bot' . $this->token() . '/' . $method;
		if ( $args ) {
			$url = add_query_arg( array_map( 'strval', $args ), $url );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 0,
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$json = json_decode( (string) $body, true );

		return is_array( $json ) ? $json : null;
	}

	/* ---------------------------------------------------------------------
	 * Scheduling
	 * ------------------------------------------------------------------ */

	public function cron_schedules( $schedules ) {
		if ( ! isset( $schedules['hsw_minute'] ) ) {
			$schedules['hsw_minute'] = array(
				'interval' => 60,
				'display'  => __( 'هر دقیقه (HamiShop)', 'hamishop-elementor-widgets' ),
			);
		}
		return $schedules;
	}

	public function maybe_schedule() {
		if ( $this->enabled() ) {
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time() + 30, 'hsw_minute', self::CRON_HOOK );
			}
			return;
		}

		/* Cleanup only in the admin: visitors must not pay for a check. */
		if ( is_admin() && wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
			delete_option( self::READY_KEY );
			delete_option( self::READY_KEY . '_hash' );
		}
	}
}
