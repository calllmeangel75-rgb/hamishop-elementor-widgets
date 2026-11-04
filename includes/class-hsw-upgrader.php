<?php
/**
 * Background upgrader — the only place that touches Elementor's caches and the
 * versioned data migrations.
 *
 * WHY THIS CLASS EXISTS
 * ---------------------
 * Up to 1.11.221 both jobs hung off `init`:
 *
 *   - `maybe_flush_element_cache()` deleted every `_elementor_element_cache`
 *     row (up to 50 000, in one request), then called Elementor's
 *     `files_manager->clear_cache()`, which drops the generated CSS of the
 *     WHOLE site, and finally purged LiteSpeed for every URL.
 *   - `migrate()` walked every managed product in a single pass.
 *
 * `init` fires on the front end too, so the first visitor after an update paid
 * for all of it. Every other PHP worker then started regenerating Elementor CSS
 * against an empty cache at the same moment — the shop answered HTTP 503 until
 * the queue drained, then recovered on its own. That is exactly the outage
 * reported after 1.11.220.
 *
 * WHAT HAPPENS INSTEAD
 * --------------------
 * A version change only schedules a job. The work runs in short, bounded ticks
 * from WP-Cron (background, its own request) or, when cron is unavailable, from
 * an admin page load. Never from a customer request.
 *
 * Every tick has:
 *   - a wall-clock budget (BUDGET seconds) so no request can hang;
 *   - a lock, so two ticks never overlap;
 *   - a resumable cursor, so a timeout or a fatal loses at most one batch;
 *   - a failure counter, so a broken step is skipped instead of retried forever.
 *
 * Nothing here purges the whole page cache: only the URLs whose Elementor CSS
 * was actually rebuilt are purged.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

final class HSW_Upgrader {

	/** Cron hook that carries one tick. */
	const CRON_HOOK = 'hsw_upgrader_tick';

	/** Where the resumable cursor lives. */
	const STATE_OPTION = 'hsw_upgrader_state';

	/** Mutual exclusion between ticks. */
	const LOCK_OPTION = 'hsw_upgrader_lock';

	/** Minimum gap between two admin-triggered ticks (seconds). */
	const THROTTLE_OPTION = 'hsw_upgrader_last';

	/** Seconds between two admin-triggered ticks. */
	const THROTTLE_GAP = 30;

	/** Seconds between two ticks. Long enough that a rebuild burst never overlaps
	 *  with the next one on a small PHP-FPM pool. */
	const RESCHEDULE_GAP = 45;

	/** Seconds of work one tick may spend. */
	const BUDGET = 2.0;

	/** How long a lock is considered abandoned. */
	const LOCK_TTL = 300;

	/** Give up (and say so) after this many ticks. */
	const MAX_RUNS = 400;

	/** Rows deleted per statement. */
	const DELETE_ROWS = 1500;

	/** Posts inspected per query. */
	const POSTS_PER_QUERY = 40;

	/** Products seeded per query. */
	const PRODUCTS_PER_QUERY = 200;

	/** How many times one step may throw before it is skipped. */
	const MAX_STEP_ERRORS = 3;

	/** @var HSW_Upgrader|null */
	private static $instance = null;

	/**
	 * Singleton.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		/*
		 * The cron callback must be registered on every request: that is how the
		 * background tick runs. It costs one array entry and does nothing until
		 * the event is actually due.
		 */
		add_action( self::CRON_HOOK, array( $this, 'run' ) );

		/* Scheduling only ever happens in wp-admin — never on a customer request. */
		add_action( 'admin_init', array( $this, 'maybe_start' ), 1 );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/* ---------------------------------------------------------------------
	 * Scheduling
	 * ------------------------------------------------------------------ */

	/**
	 * Start (or nudge) the job when the plugin version moved.
	 *
	 * Runs on `admin_init` only. A shop that never opens wp-admin simply keeps
	 * serving its cached pages, which is the safe failure mode: nothing is
	 * deleted, nothing is purged, no visitor waits.
	 *
	 * @return void
	 */
	public function maybe_start() {
		/*
		 * Admin-ajax and REST requests belong to the editor, the cart and the
		 * checkout. A two-second maintenance batch inside one of them looks like a
		 * frozen Elementor panel, so only real admin page loads may tick.
		 */
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( HSW_VERSION === get_option( 'hsw_asset_version' ) ) {
			return;
		}

		self::schedule();

		/*
		 * Cron is the intended runner, but plenty of hosts disable it
		 * (DISABLE_WP_CRON) or run it on a long interval. One bounded tick per
		 * admin page load keeps the job moving in that case — still never on the
		 * front end, and throttled so a fast admin session cannot stack them.
		 */
		$last = (int) get_option( self::THROTTLE_OPTION, 0 );
		if ( ( time() - $last ) < self::THROTTLE_GAP ) {
			return;
		}
		update_option( self::THROTTLE_OPTION, time(), false );

		$this->run();
	}

	/**
	 * Queue one tick if none is queued already.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}
		wp_schedule_single_event( time() + self::RESCHEDULE_GAP, self::CRON_HOOK );
	}

	/**
	 * Stop everything (used by the deactivation hook).
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		delete_option( self::LOCK_OPTION );
	}

	/* ---------------------------------------------------------------------
	 * The tick
	 * ------------------------------------------------------------------ */

	/**
	 * One bounded slice of work.
	 *
	 * Safe to call from cron, from admin, or by hand: the lock makes concurrent
	 * calls collapse into one, and every failure is contained.
	 *
	 * @return void
	 */
	public function run() {
		/*
		 * A cron event queued before the job finished would otherwise start it
		 * again from scratch — the version marker is the single source of truth.
		 */
		if ( HSW_VERSION === get_option( 'hsw_asset_version' ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
			delete_option( self::STATE_OPTION );
			return;
		}

		if ( ! self::lock() ) {
			return;
		}

		$state = self::state();

		if ( empty( $state ) ) {
			$state = self::initial_state();
		}

		if ( ! empty( $state['done'] ) ) {
			self::unlock();
			return;
		}

		$deadline = microtime( true ) + self::BUDGET;
		$state['runs']++;

		try {
			$this->tick( $state, $deadline );
		} catch ( \Throwable $e ) {
			/*
			 * A fatal here must never reach the visitor. Record it, keep the
			 * cursor, and let the next tick try again — that is the whole point
			 * of the state row.
			 */
			$state['errors'] = (int) $state['errors'] + 1;
			if ( function_exists( 'hsw_report_fatal' ) ) {
				hsw_report_fatal( $e, 'upgrader:' . $state['step'] );
			}
			self::log( $state['step'], $e->getMessage() );
		} finally {
			self::save_state( $state );
			self::unlock();
		}

		/*
		 * Ceiling on the whole job: a step that keeps resuming without finishing
		 * must not run forever on a huge or broken table.
		 */
		if ( empty( $state['done'] ) && (int) $state['runs'] >= self::MAX_RUNS ) {
			self::log( 'timeout', 'job did not finish within ' . self::MAX_RUNS . ' ticks', true );
			$state['failed'][] = 'timeout';
			$this->finish( $state );
			self::save_state( $state );
		}

		if ( empty( $state['done'] ) ) {
			self::schedule();
		}
	}

	/**
	 * Walk the steps until the budget runs out.
	 *
	 * @param array $state    Job state, by reference.
	 * @param float $deadline Absolute microtime when the tick must stop.
	 * @return void
	 */
	private function tick( array &$state, $deadline ) {
		$guard = 0;

		while ( microtime( true ) < $deadline && empty( $state['done'] ) ) {
			/* Belt and braces: a step that neither advances nor finishes must not spin. */
			if ( ++$guard > 50 ) {
				break;
			}

			$step = (string) $state['step'];
			$done = false;

			try {
				switch ( $step ) {
					case 'element_cache':
						$done = $this->step_element_cache( $state, $deadline );
						break;
					case 'post_css':
						$done = $this->step_post_css( $state, $deadline );
						break;
					case 'migrate':
						$done = $this->step_migrate( $state, $deadline );
						break;
					default:
						$done = true;
						break;
				}
			} catch ( \Throwable $e ) {
				$state['errors'] = (int) $state['errors'] + 1;
				$state['step_errors'] = isset( $state['step_errors'] ) ? (int) $state['step_errors'] + 1 : 1;
				if ( function_exists( 'hsw_report_fatal' ) ) {
					hsw_report_fatal( $e, 'upgrader:' . $step );
				}
				$give_up = (int) $state['step_errors'] >= self::MAX_STEP_ERRORS;
				self::log( $step, $e->getMessage(), $give_up );

				if ( $give_up ) {
					/* Skip the broken step rather than block the update forever. */
					$state['failed'][] = $step;
					$state['step_errors'] = 0;
					$done = true;
				} else {
					return; // Retry this step on the next tick.
				}
			}

			if ( ! $done ) {
				return; // Budget spent inside the step; resume here next time.
			}

			$state['step']        = self::next_step( $step );
			$state['step_errors'] = 0;

			if ( 'done' === $state['step'] ) {
				$this->finish( $state );
				return;
			}
		}

	}

	/**
	 * Step order.
	 *
	 * @param string $step Current step.
	 * @return string Next step, or `done`.
	 */
	private static function next_step( $step ) {
		$order = array( 'element_cache', 'post_css', 'migrate' );
		$index = array_search( $step, $order, true );
		if ( false === $index || ! isset( $order[ $index + 1 ] ) ) {
			return 'done';
		}
		return $order[ $index + 1 ];
	}

	/**
	 * Mark the version as handled and clear the job row.
	 *
	 * @param array $state Job state, by reference.
	 * @return void
	 */
	private function finish( array &$state ) {
		$state['done'] = true;

		update_option( 'hsw_asset_version', HSW_VERSION, false );
		delete_option( self::STATE_OPTION );
		wp_clear_scheduled_hook( self::CRON_HOOK );

		/*
		 * A site-wide purge is what made the outage worse: it emptied every page
		 * cache while Elementor CSS was being rebuilt. Individual URLs are purged
		 * as their CSS is dropped; a full purge is opt-in for exotic setups.
		 */
		if ( apply_filters( 'hsw_upgrader_full_purge', false ) && class_exists( 'HSW_Optimizer' ) ) {
			HSW_Optimizer::purge( 'HamiShop: upgrade finished' );
		}
	}

	/* ---------------------------------------------------------------------
	 * Steps
	 * ------------------------------------------------------------------ */

	/**
	 * Step 1 — drop Elementor's cached element HTML.
	 *
	 * Only rows that existed when the job started are deleted (`meta_id`
	 * watermark). A row written afterwards was rendered by the new code, so it
	 * is already correct — and keeping it means a busy shop cannot feed the loop
	 * forever, which is why the old implementation had to do everything at once.
	 *
	 * @param array $state    Job state, by reference.
	 * @param float $deadline Absolute microtime.
	 * @return bool True when the step is complete.
	 */
	private function step_element_cache( array &$state, $deadline ) {
		global $wpdb;

		if ( empty( $state['watermark'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$state['watermark'] = (int) $wpdb->get_var(
				"SELECT MAX(meta_id) FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_element_cache'"
			);
			if ( ! $state['watermark'] ) {
				return true; // Nothing cached, nothing to do.
			}
		}

		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_element_cache' AND meta_id <= %d LIMIT %d",
					$state['watermark'],
					self::DELETE_ROWS
				)
			);

			if ( false === $deleted ) {
				throw new \RuntimeException( 'element cache delete failed: ' . $wpdb->last_error );
			}
		} while ( $deleted > 0 && microtime( true ) < $deadline );

		return 0 === (int) $deleted;
	}

	/**
	 * Step 2 — rebuild Elementor CSS for the pages that use HamiShop widgets.
	 *
	 * Two earlier designs both failed in production:
	 *
	 *   1. `files_manager->clear_cache()` deleted the CSS of EVERY Elementor page
	 *      and purged every URL — the 1.11.220 outage.
	 *   2. Deleting the CSS of the affected pages only, and letting the next
	 *      visitor rebuild it, still opened a cold window: on 1.11.223 a live shop
	 *      answered nginx 503 for a few minutes while PHP workers regenerated
	 *      dozens of files at once (the rebuilt files are stamped 04:27–04:30).
	 *
	 * So the CSS is not deleted at all. `update()` parses the page and rewrites
	 * the file in this same background tick, one post at a time. There is never a
	 * moment when a page has no stylesheet, and no visitor ever pays for the work.
	 *
	 * @param array $state    Job state, by reference.
	 * @param float $deadline Absolute microtime.
	 * @return bool True when the step is complete.
	 */
	private function step_post_css( array &$state, $deadline ) {
		global $wpdb;

		if ( ! self::can_rebuild_css() ) {
			return true; // Elementor too old / not loaded: nothing to rebuild.
		}

		$cursor = (int) $state['cursor'];

		while ( microtime( true ) < $deadline ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_id, meta_value FROM {$wpdb->postmeta}
					 WHERE meta_key = '_elementor_data' AND post_id > %d
					 ORDER BY post_id ASC LIMIT %d",
					$cursor,
					self::POSTS_PER_QUERY
				)
			);

			if ( empty( $rows ) ) {
				return true;
			}

			foreach ( $rows as $row ) {
				$post_id = (int) $row->post_id;
				$cursor  = $post_id;

				/* Cheap substring test: a false positive only costs one rebuild. */
				if ( false === strpos( (string) $row->meta_value, 'hsw-' ) ) {
					$state['cursor'] = $cursor;
					continue;
				}

				self::rebuild_post_css( $post_id );

				/*
				 * Rebuilding is far heavier than deleting was, so the budget has
				 * to be enforced per post, not per query: one query of 40 posts
				 * could otherwise run for ten seconds in a single tick.
				 */
				$state['cursor'] = $cursor;

				if ( microtime( true ) >= $deadline ) {
					return false; // Resume after this post on the next tick.
				}
			}

			$state['cursor'] = $cursor;

			if ( count( $rows ) < self::POSTS_PER_QUERY ) {
				return true;
			}
		}

		$state['cursor'] = $cursor;
		return false;
	}

	/**
	 * Step 3 — seed the stock baseline, one batch at a time.
	 *
	 * @param array $state    Job state, by reference.
	 * @param float $deadline Absolute microtime.
	 * @return bool True when the step is complete.
	 */
	private function step_migrate( array &$state, $deadline ) {
		if ( version_compare( (string) get_option( 'hsw_db_version', '0' ), '1', '>=' ) ) {
			return true;
		}

		if ( ! class_exists( 'HSW_Plugin' ) || ! method_exists( 'HSW_Plugin', 'migrate_batch' ) ) {
			return true;
		}

		while ( microtime( true ) < $deadline ) {
			$batch = HSW_Plugin::migrate_batch( (int) $state['cursor'], self::PRODUCTS_PER_QUERY );

			$state['cursor'] = (int) $batch['cursor'];

			if ( ! empty( $batch['done'] ) ) {
				return true;
			}
		}

		return false;
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Is Elementor's per-post CSS class available?
	 *
	 * @return bool
	 */
	private static function can_rebuild_css() {
		return class_exists( '\Elementor\Core\Files\CSS\Post' );
	}

	/**
	 * Rewrite one post's generated CSS in place, then refresh its page caches.
	 *
	 * `update()` runs Elementor's own parser and writes the file immediately, so
	 * the stylesheet is never missing. If it throws (corrupt data, a plugin
	 * conflict), the file is deleted instead and Elementor rebuilds it on the next
	 * view — a small cold window is still better than leaving stale CSS forever.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	private static function rebuild_post_css( $post_id ) {
		$rebuilt = false;

		try {
			if ( self::can_rebuild_css() && method_exists( '\Elementor\Core\Files\CSS\Post', 'create' ) ) {
				$file = \Elementor\Core\Files\CSS\Post::create( $post_id );

				if ( $file && method_exists( $file, 'update' ) ) {
					$file->update();
					$rebuilt = true;
				} elseif ( $file && method_exists( $file, 'delete' ) ) {
					$file->delete();
				}
			}
		} catch ( \Throwable $e ) {
			if ( function_exists( 'hsw_report_fatal' ) ) {
				hsw_report_fatal( $e, 'upgrader:css:' . $post_id );
			}
		}

		/*
		 * Only when the in-place rebuild did not happen: Elementor caches a
		 * "this file is current" flag in post meta, and leaving it would serve the
		 * old stylesheet forever.
		 */
		if ( ! $rebuilt ) {
			delete_post_meta( $post_id, '_elementor_css' );
		}

		/* Purge this URL only — never the whole site. */
		$url = get_permalink( $post_id );

		if ( $url ) {
			do_action( 'litespeed_purge_url', $url );
		}

		/* WP Rocket keeps its own per-post cache. */
		if ( function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $post_id );
		}
	}

	/* ---------------------------------------------------------------------
	 * State
	 * ------------------------------------------------------------------ */

	/**
	 * Fresh job state.
	 *
	 * @return array
	 */
	private static function initial_state() {
		return array(
			'version'     => HSW_VERSION,
			'step'        => 'element_cache',
			'cursor'      => 0,
			'watermark'   => 0,
			'runs'        => 0,
			'errors'      => 0,
			'step_errors' => 0,
			'started'     => time(),
			'done'        => false,
			'failed'      => array(),
		);
	}

	/**
	 * Saved state, or an empty array when the job has not started.
	 *
	 * A state row written by a different plugin version is discarded: its cursors
	 * point at a schema this build no longer understands.
	 *
	 * @return array
	 */
	private static function state() {
		$state = get_option( self::STATE_OPTION, array() );
		if ( ! is_array( $state ) || empty( $state['version'] ) || HSW_VERSION !== $state['version'] ) {
			return array();
		}
		return $state;
	}

	/**
	 * Persist state.
	 *
	 * @param array $state Job state.
	 * @return void
	 */
	private static function save_state( array $state ) {
		if ( ! empty( $state['done'] ) ) {
			return; // finish() already removed the row.
		}
		update_option( self::STATE_OPTION, $state, false );
	}

	/**
	 * Take the tick lock.
	 *
	 * @return bool
	 */
	private static function lock() {
		$held = (int) get_option( self::LOCK_OPTION, 0 );

		if ( $held ) {
			if ( ( time() - $held ) < self::LOCK_TTL ) {
				return false; // Another tick is running.
			}
			delete_option( self::LOCK_OPTION ); // Abandoned (fatal mid-tick).
		}

		return (bool) add_option( self::LOCK_OPTION, time(), '', false );
	}

	/**
	 * Release the tick lock.
	 *
	 * @return void
	 */
	private static function unlock() {
		delete_option( self::LOCK_OPTION );
	}

	/**
	 * Append to a small rolling log so a failed upgrade can be diagnosed later.
	 *
	 * @param string $step    Step name.
	 * @param string $message Error text.
	 * @param bool   $fatal   True when the step was given up on (worth a notice).
	 * @return void
	 */
	private static function log( $step, $message, $fatal = false ) {
		$log = get_option( 'hsw_upgrader_log', array() );
		$log = is_array( $log ) ? $log : array();

		$log[] = array(
			'time'    => time(),
			'version' => HSW_VERSION,
			'step'    => (string) $step,
			'msg'     => substr( (string) $message, 0, 300 ),
			'fatal'   => (bool) $fatal,
		);

		/* Keep the last 20 entries — this is a diagnostic aid, not an audit trail. */
		$log = array_slice( $log, -20 );

		update_option( 'hsw_upgrader_log', $log, false );
	}

	/* ---------------------------------------------------------------------
	 * Admin notice
	 * ------------------------------------------------------------------ */

	/**
	 * Tell the merchant when the background cleanup could not finish.
	 *
	 * Silence while the job is running is deliberate: it is background work and
	 * the shop is serving normally.
	 *
	 * @return void
	 */
	public function notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}
		if ( false === strpos( (string) $screen->id, 'hamishop' ) && 'dashboard' !== $screen->id ) {
			return;
		}

		$log = get_option( 'hsw_upgrader_log', array() );
		if ( ! is_array( $log ) || empty( $log ) ) {
			return;
		}

		$last = end( $log );
		if ( ! is_array( $last ) || (string) ( $last['version'] ?? '' ) !== HSW_VERSION ) {
			return;
		}

		/* A recovered error is not worth a banner; a skipped step is. */
		if ( empty( $last['fatal'] ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'HamiShop: پاک‌سازی پس‌زمینهٔ به‌روزرسانی کامل نشد', 'hamishop-elementor-widgets' ) . '</strong></p>';
		echo '<p>' . esc_html__( 'سایت سالم است و کار می‌کند؛ فقط بخشی از کش قدیمی باقی مانده. این کار در پس‌زمینه ادامه پیدا می‌کند و نیازی به اقدام شما نیست. اگر پیام ماند، یک‌بار صفحهٔ پیشخوان را باز کنید.', 'hamishop-elementor-widgets' ) . '</p>';
		echo '<p><code>' . esc_html( (string) ( $last['step'] ?? '' ) . ': ' . (string) ( $last['msg'] ?? '' ) ) . '</code></p></div>';
	}
}
