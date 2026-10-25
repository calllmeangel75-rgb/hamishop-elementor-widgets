<?php
/**
 * Cart actions WooCommerce does not provide natively.
 *
 * - «خالی کردن سبد» (POST hsw_empty_cart).
 * - A tiny wc-ajax endpoint that sets the quantity of the cart line belonging
 *   to a product (`hsw_set_qty`). The product-page stepper used to be purely
 *   visual: pressing + or − only changed the number on screen, so the customer
 *   could "pick 3" and add 1. `quantity = 0` removes the line, which is what
 *   the bin button does.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Cart_Actions {

	public function __construct() {
		add_action( 'wp_loaded', array( $this, 'handle_empty_cart' ), 30 );
		add_action( 'wc_ajax_hsw_set_qty', array( $this, 'ajax_set_qty' ) );
		add_action( 'wc_ajax_nopriv_hsw_set_qty', array( $this, 'ajax_set_qty' ) );
	}

	/**
	 * Empty the cart from the widget button (nonce-verified POST).
	 */
	public function handle_empty_cart() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified below.
		if ( empty( $_POST['hsw_empty_cart'] ) ) {
			return;
		}
		$nonce = isset( $_POST['hsw_empty_cart_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hsw_empty_cart_nonce'] ) ) : '';
		// phpcs:enable
		if ( ! wp_verify_nonce( $nonce, 'hsw-empty-cart' ) ) {
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		WC()->cart->empty_cart();
		wc_add_notice( apply_filters( 'hsw_cart_emptied_message', __( 'سبد خرید خالی شد.', 'hamishop-elementor-widgets' ) ), 'notice' );

		wp_safe_redirect( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' ) );
		exit;
	}

	/**
	 * Set (or remove) the cart quantity of one product.
	 *
	 * The line is located by product/variation id instead of a cart key: the
	 * stepper is rendered before the customer ever added the item, so it cannot
	 * know the key, and the key changes when WooCommerce merges two lines.
	 */
	public function ajax_set_qty() {
		check_ajax_referer( 'hsw-cart-qty', 'security' );

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => 'cart_unavailable' ), 400 );
		}

		$product_id   = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( wp_unslash( $_POST['variation_id'] ) ) : 0;
		$quantity     = isset( $_POST['quantity'] ) ? (int) wp_unslash( $_POST['quantity'] ) : 0;

		$cart = WC()->cart;
		$key  = '';

		foreach ( $cart->get_cart() as $cart_item_key => $item ) {
			if ( (int) $item['product_id'] !== $product_id ) {
				continue;
			}
			if ( $variation_id && (int) $item['variation_id'] !== $variation_id ) {
				continue;
			}
			$key = $cart_item_key;
			break;
		}

		if ( '' === $key ) {
			wp_send_json_error( array( 'message' => 'not_in_cart' ), 404 );
		}

		/* ---- Server-side ceiling: product rules win over the client ---- */
		$item      = $cart->get_cart_item( $key );
		$product   = $item ? $item['data'] : null;
		$max       = 999;
		$remaining = 999;
		$in_stock  = true;

		if ( $product instanceof WC_Product ) {
			$in_stock  = $product->is_in_stock() || $product->backorders_allowed();
			$max       = (int) $product->get_max_purchase_quantity(); // -1 = unlimited
			$sold_one  = $product->is_sold_individually();
			$stock     = $product->get_stock_quantity();
			$limit     = ( $max > 0 ) ? $max : 999;
			if ( null !== $stock && ! $product->backorders_allowed() ) {
				$limit = min( $limit, (int) $stock );
			}
			if ( $sold_one ) {
				$limit = 1;
			}
			$max       = $limit;
			$remaining = max( 0, $limit );
		}

		if ( ! $in_stock ) {
			wp_send_json_error(
				array(
					'message'   => __( 'این محصول موجود نیست.', 'hamishop-elementor-widgets' ),
					'quantity'  => 0,
					'max'       => 0,
					'remaining' => 0,
				),
				409
			);
		}

		$quantity = max( 0, min( $max, $quantity ) );

		/* WooCommerce's own validation (stock, sold individually, plugins). */
		$passed = apply_filters( 'woocommerce_update_cart_validation', true, $key, $item ? $item : array(), $quantity );
		if ( ! $passed ) {
			$notices = function_exists( 'wc_get_notices' ) ? wc_get_notices( 'error' ) : array();
			$msg     = $notices ? wp_strip_all_tags( (string) ( $notices[0]['notice'] ?? '' ) ) : '';
			wp_send_json_error(
				array(
					'message'   => '' !== $msg ? $msg : __( 'تعداد درخواستی مجاز نیست.', 'hamishop-elementor-widgets' ),
					'quantity'  => (int) ( $item['quantity'] ?? 0 ),
					'max'       => $max,
					'remaining' => $remaining,
				),
				400
			);
		}

		if ( 0 === $quantity ) {
			$cart->remove_cart_item( $key );
		} else {
			$cart->set_quantity( $key, $quantity, true );
		}

		/* set_quantity() may merge lines, so re-read the real state. */
		$new_qty = 0;
		foreach ( $cart->get_cart() as $item ) {
			if ( (int) $item['product_id'] === $product_id && ( ! $variation_id || (int) $item['variation_id'] === $variation_id ) ) {
				$new_qty = (int) $item['quantity'];
				break;
			}
		}

		/* Same fragment map WooCommerce returns from add_to_cart. */
		$fragments = apply_filters( 'woocommerce_add_to_cart_fragments', array() );

		wp_send_json_success(
			array(
				'quantity'  => $new_qty,
				'max'       => $max,
				'remaining' => $remaining,
				'message'   => '',
				'in_cart'   => $new_qty > 0,
				'count'     => (int) $cart->get_cart_contents_count(),
				'fragments' => is_array( $fragments ) ? $fragments : array(),
			)
		);
	}
}
