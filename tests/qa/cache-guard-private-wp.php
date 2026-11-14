<?php
/**
 * Regression test for private-page cache detection.
 *
 * The setup screen creates /complete/ and /payment/ with shortcodes, not
 * Elementor data. The cache guard only knew Elementor widget names, so a guest
 * could receive a cached completion page (and its order key) from LiteSpeed or
 * any other page cache. This test exercises the exact pages the setup screen
 * creates and the `?order=…&key=…` request shape.
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

if ( ! class_exists( 'HSW_Cache_Guard' ) ) {
	require_once dirname( __DIR__, 2 ) . '/includes/class-hsw-cache-guard.php';
}
if ( ! class_exists( 'HSW_Complete_Route' ) ) {
	require_once dirname( __DIR__, 2 ) . '/includes/class-hsw-complete-route.php';
}

$failed = 0;
$check  = static function ( $name, $ok, $detail = '' ) use ( &$failed ) {
	echo ( $ok ? 'PASS ' : '::error::FAIL ' ) . $name . ( '' !== $detail ? " ({$detail})" : '' ) . PHP_EOL;
	if ( ! $ok ) {
		++$failed;
	}
};

/**
 * Call the private detector the way template_redirect does.
 *
 * @return bool
 */
$private_view = static function () {
	static $method = null;
	if ( null === $method ) {
		$method = new ReflectionMethod( 'HSW_Cache_Guard', 'is_private_view' );
		$method->setAccessible( true );
	}

	return (bool) $method->invoke( HSW_Cache_Guard::instance() );
};

/**
 * Pretend WordPress resolved a singular page request.
 *
 * @param int $post_id Page id.
 * @return void
 */
$visit_page = static function ( $post_id ) {
	global $wp_query;
	$wp_query                   = new WP_Query();
	$wp_query->queried_object   = get_post( $post_id );
	$wp_query->queried_object_id = (int) $post_id;
	$wp_query->is_page          = true;
	$wp_query->is_singular      = true;
	$wp_query->is_home          = false;
	$wp_query->is_404           = false;
	$wp_query->query_vars['order-received'] = '';
};

$made = array();
$make_page = static function ( $title, $content, $marker = '' ) use ( &$made ) {
	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $content,
		)
	);
	if ( $id && '' !== $marker ) {
		update_post_meta( $id, HSW_Complete_Route::META, $marker );
	}
	if ( $id ) {
		$made[] = (int) $id;
	}
	return (int) $id;
};

/* Exactly what HSW_Setup_Pages::create() writes: marker + shortcode. */
$complete_id = $make_page( 'Cache QA complete', '[hamishop_complete]', 'complete' );
$payment_id  = $make_page( 'Cache QA payment', '[hamishop_payment]', 'payment' );
$account_id  = $make_page( 'Cache QA account', '[hamishop_account]' );
$plain_id    = $make_page( 'Cache QA plain', 'Just an ordinary page.' );
$elementor_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Cache QA elementor',
		'post_content' => '',
	)
);
if ( $elementor_id ) {
	update_post_meta( $elementor_id, '_elementor_data', '[{"widgetType":"hsw-complete-page"}]' );
	$made[] = (int) $elementor_id;
}

$_GET = array();

$visit_page( $complete_id );
$check( 'setup-pages complete page is private', true === $private_view() );
$check( 'setup-pages complete page is recognized as ours', true === HSW_Complete_Route::page_is_ours( $complete_id, 'complete' ) );

$visit_page( $payment_id );
$check( 'setup-pages payment page is private', true === $private_view() );
$check( 'setup-pages payment page is recognized as ours', true === HSW_Complete_Route::page_is_ours( $payment_id, 'payment' ) );

$visit_page( $account_id );
$check( 'account shortcode page is private', true === $private_view() );

$visit_page( $elementor_id );
$check( 'Elementor completion widget remains private', true === $private_view() );

$visit_page( $plain_id );
$check( 'plain page is not private', false === $private_view() );

/* A completion URL is private even before its page content is inspected. */
$_GET = array( 'order' => '123', 'key' => 'wc_order_abcdef' );
$visit_page( $plain_id );
$check( 'order + key request is private on any page', true === $private_view() );

$_GET = array( 'order' => '123' );
$visit_page( $plain_id );
$check( 'order without key is not enough', false === $private_view() );

$_GET = array( 'key' => 'wc_order_abcdef' );
$visit_page( $plain_id );
$check( 'key without order is not enough', false === $private_view() );

foreach ( $made as $id ) {
	wp_delete_post( $id, true );
}

echo PHP_EOL . 'Cache Guard QA Results: ' . ( 0 === $failed ? 'ALL PASSED' : "{$failed} FAILED" ) . PHP_EOL;
exit( $failed > 0 ? 1 : 0 );
