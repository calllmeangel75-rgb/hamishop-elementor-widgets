<?php
/**
 * REST API routes — used by the "Load More" pagination.
 *
 * Security model: the widget stores its exact query + card configuration in
 * a short-lived transient; the client only sends back an opaque key plus the
 * page number. Nothing user-controlled ever reaches WP_Query directly.
 *
 * @package HamiShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller.
 */
final class HSW_Rest {

	const NAMESPACE_V1 = 'hamishop/v1';
	const TTL          = 600; // 10 minutes.

	/**
	 * Bootstrap.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/load-more',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'handle_load_more' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'k'    => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
					'page' => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	/**
	 * Build the transient key for a payload.
	 *
	 * @param array $payload Payload array.
	 * @return string
	 */
	public static function key_for( array $payload ) {
		return 'hsw_lm_' . substr( md5( (string) wp_json_encode( $payload ) ), 0, 20 );
	}

	/**
	 * Store a payload for later load-more requests.
	 *
	 * @param array $payload Payload.
	 * @return string Transient key (without prefix).
	 */
	public static function store( array $payload ) {
		$raw = self::key_for( $payload );
		set_transient( 'hsw_lm_' . $raw, $payload, self::TTL );
		return $raw;
	}

	/**
	 * Handle GET /load-more.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_load_more( $request ) {
		$key  = (string) $request->get_param( 'k' );
		$page = max( 1, (int) $request->get_param( 'page' ) );

		$payload = get_transient( 'hsw_lm_' . $key );

		if ( ! is_array( $payload ) || empty( $payload['query_args'] ) ) {
			return new \WP_Error( 'hsw_expired', __( 'This listing has expired, please reload the page.', 'hamishop-elementor-widgets' ), [ 'status' => 404 ] );
		}

		$max_pages = isset( $payload['max_pages'] ) ? (int) $payload['max_pages'] : 1;
		if ( $page > $max_pages ) {
			return new \WP_Error( 'hsw_no_more', __( 'No more products', 'hamishop-elementor-widgets' ), [ 'status' => 404 ] );
		}

		$query_args          = $payload['query_args'];
		$query_args['paged'] = $page;

		$query = new \WP_Query( $query_args );

		ob_start();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$product = wc_get_product( get_the_ID() );
				if ( $product && $product->is_visible() ) {
					HSW_Card_Renderer::render( $product, $payload['card_args'] );
				}
			}
			wp_reset_postdata();
		}

		$html = ob_get_clean();

		return rest_ensure_response(
			[
				'html'      => $html,
				'page'      => $page,
				'max_pages' => $max_pages,
				'has_more'  => $page < $max_pages,
			]
		);
	}
}
