<?php
/**
 * REST routes:
 *  - /hsw/v1/search  : admin product search for the chooser control.
 *  - /hsw/v1/loop    : server-rendered cards fragment (tab switching).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Rest {

	const NS = 'hsw/v1';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes() {
		register_rest_route(
			self::NS,
			'/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'search' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);

		register_rest_route(
			self::NS,
			'/loop',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'loop_fragment' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function can_edit() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * WooCommerce-style product search for select2.
	 */
	public function search( $request ) {
		$term  = sanitize_text_field( (string) $request->get_param( 'term' ) );
		$limit = min( 30, max( 5, (int) $request->get_param( 'limit' ) ) );

		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				's'              => $term,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$out = array();
		foreach ( $query->posts as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}
			$out[] = array(
				'id'   => $product->get_id(),
				'text' => sprintf( '%s (#%d)', wp_strip_all_tags( $product->get_name() ), $product->get_id() ),
			);
		}
		return rest_ensure_response( $out );
	}

	/**
	 * Render a cards fragment for tab switching.
	 * Params: type (deal|best|popular), source, per_page, term_id, nonce_widget (widget signature hash).
	 */
	public function loop_fragment( $request ) {
		$type     = in_array( $request->get_param( 'type' ), array( 'deal', 'best', 'popular' ), true ) ? $request->get_param( 'type' ) : 'deal';
		$source   = sanitize_key( (string) $request->get_param( 'source' ) );
		$per_page = min( 24, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$term_id  = (int) $request->get_param( 'term_id' );
		$sig      = (string) $request->get_param( 'sig' );

		// Signature = md5( AUTH_SALT + widget_key ) — prevents arbitrary fragment injection.
		if ( ! hash_equals( md5( wp_salt( 'auth' ) . '|hsw|' . $type ), (string) $sig ) ) {
			return new WP_Error( 'hsw_bad_sig', 'invalid signature', array( 'status' => 400 ) );
		}

		$allowed_sources = array( 'sale', 'best_selling', 'rating', 'newest', 'discount', 'featured' );
		if ( ! in_array( $source, $allowed_sources, true ) ) {
			return new WP_Error( 'hsw_bad_source', 'invalid source', array( 'status' => 400 ) );
		}

		$products = HSW_Plugin::instance()->products->query(
			array(
				'source'  => $source,
				'count'   => $per_page,
				'term_id' => $term_id,
			)
		);

		$html = HSW_Widget_Base::cards_html( $type, $products, array() );

		return rest_ensure_response(
			array(
				'html'  => $html,
				'found' => count( $products ),
			)
		);
	}
}
