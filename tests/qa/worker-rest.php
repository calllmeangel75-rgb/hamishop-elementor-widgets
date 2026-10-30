<?php
/**
 * QA worker: one real REST request through the plugin's endpoint.
 *
 *   php worker-rest.php <method> <route> <query-string params> <user_id> <start>
 *
 * Params are passed as `a=1&b=2` on purpose: on Windows `escapeshellarg()`
 * strips double quotes, which would destroy a JSON argument.
 *
 * `rest_get_server()` fires `rest_api_init`, so the route, its argument schema
 * and its permission callback all run exactly as they do over HTTP.
 */
require 'C:/xampp/htdocs/sks/wp-load.php';

$method = (string) ( $argv[1] ?? 'POST' );
$route  = (string) ( $argv[2] ?? '/hsw/v1/account/wallet/topup' );
$params = array();
parse_str( (string) ( $argv[3] ?? '' ), $params );
$uid   = absint( $argv[4] ?? 0 );
$start = (float) ( $argv[5] ?? 0 );

$wait = $start - microtime( true );
if ( $wait > 0 ) {
	usleep( (int) round( $wait * 1000000 ) );
}

/* Loyalty points come from lifetime spend; give the QA user a fixed number. */
add_filter(
	'hsw_account_loyalty_points',
	static function () {
		return 1000;
	}
);

wp_set_current_user( $uid );

$server  = rest_get_server();
$request = new WP_REST_Request( $method, $route );
$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
$request->set_body_params( $params );

$t0  = microtime( true );
$res = rest_do_request( $request );
$data = $res->get_data();

echo wp_json_encode(
	array(
		'pid'    => getmypid(),
		'status' => $res->get_status(),
		'ok'     => $res->get_status() < 400,
		'code'   => is_array( $data ) && isset( $data['code'] ) ? $data['code'] : '',
		'data'   => is_array( $data ) ? array_intersect_key( $data, array( 'ok' => 1, 'id' => 1, 'balance' => 1, 'message' => 1 ) ) : $data,
		'ms'     => (int) round( ( microtime( true ) - $t0 ) * 1000 ),
	)
) . "\n";
