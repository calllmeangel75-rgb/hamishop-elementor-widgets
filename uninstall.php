<?php
/**
 * Uninstall cleanup.
 *
 * Runs only when the plugin is deleted from the Plugins screen. Deactivation is
 * handled separately (`register_deactivation_hook`) because it must not destroy
 * data — but a delete should leave nothing behind: options, transients, user
 * meta, tickets and the `.htaccess` block the security module wrote.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove everything this plugin created for the current site.
 *
 * @return void
 */
function hsw_uninstall_site() {
	global $wpdb;

	/* ------------------------------------------------------------------
	 * Options
	 * --------------------------------------------------------------- */
	/*
	 * Every option the plugin reads, including the ones added after this file
	 * was first written (invoice, telegram polling, 404 page, story flags) and
	 * the legacy `hsw_bale_contacts` name that older installs may still carry.
	 */
	$options = array(
		'hsw_account_settings',
		'hsw_account_rewrite_hash',
		'hsw_add_feedback',
		'hsw_asset_version',
		'hsw_bale_contacts',
		'hsw_brand',
		'hsw_complete_page_id',
		'hsw_custom_404_enabled',
		'hsw_db_version',
		'hsw_enabled_widgets',
		'hsw_express_slug',
		'hsw_free_shipping_min',
		'hsw_free_shipping_on',
		'hsw_header_widgets_migrated',
		'hsw_htaccess_backup',
		'hsw_invoice_enabled',
		'hsw_invoice_mono',
		'hsw_jalali_on',
		'hsw_last_error',
		'hsw_loop_version',
		'hsw_oos_last',
		'hsw_palette',
		'hsw_post_widget_migrated',
		'hsw_security',
		'hsw_telegram_contacts',
		'hsw_tg_offset',
		'hsw_tg_poll_ready',
		'hsw_tg_poll_ready_hash',
		'hsw_throttle_db',
		'hsw_wallet_db',
		'hsw_wallet_migration',
		'hsw_points_migrated',
		'hsw_404_cta',
		'hsw_404_home',
		'hsw_404_search_ph',
		'hsw_404_text',
		'hsw_404_title',
	);
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	/* ------------------------------------------------------------------
	 * Transients — OTP codes, rate limits, captcha challenges, caches.
	 * --------------------------------------------------------------- */
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_hsw\\_%' OR option_name LIKE '\\_transient\\_timeout\\_hsw\\_%'"
	);

	/* ------------------------------------------------------------------
	 * User meta — phone binding, OTP state, email verification, tickets.
	 * --------------------------------------------------------------- */
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '\\_hsw\\_%'"
	);

	/* ------------------------------------------------------------------
	 * Tickets and their replies.
	 * --------------------------------------------------------------- */
	$ticket_ids = get_posts(
		array(
			'post_type'        => 'hsw_ticket',
			'post_status'      => 'any',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => true,
		)
	);
	foreach ( $ticket_ids as $ticket_id ) {
		wp_delete_post( (int) $ticket_id, true );
	}

	/* Any leftover ticket meta on posts that were already removed. */
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\\_hsw\\_ticket\\_%'"
	);

	/* ------------------------------------------------------------------
	 * The throttle table (login counters).
	 *
	 * The wallet ledger (`{prefix}hsw_wallet_ledger`) and the balance table
	 * (`{prefix}hsw_wallet`) are deliberately kept: they are a financial
	 * record of customer credit, and deleting a plugin should not erase money
	 * that was already paid in.
	 * --------------------------------------------------------------- */
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}hsw_throttle" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	/* ------------------------------------------------------------------
	 * The admin-URL rewrite block.
	 * --------------------------------------------------------------- */
	if ( ! class_exists( 'HSW_Security' ) ) {
		$security = dirname( __FILE__ ) . '/includes/class-hsw-security.php';
		if ( is_readable( $security ) ) {
			require_once $security;
		}
	}
	if ( class_exists( 'HSW_Security' ) ) {
		HSW_Security::remove_rewrite();
	}

	/* ------------------------------------------------------------------
	 * Scheduled events (if any were registered by an older version).
	 * --------------------------------------------------------------- */
	$crons = array( 'hsw_daily_cleanup', 'hsw_ticket_cleanup', 'hsw_tg_poll', 'hsw_throttle_prune' );
	foreach ( $crons as $cron ) {
		wp_clear_scheduled_hook( $cron );
	}
}

if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $sites as $site_id ) {
		switch_to_blog( (int) $site_id );
		hsw_uninstall_site();
		restore_current_blog();
	}
} else {
	hsw_uninstall_site();
}
