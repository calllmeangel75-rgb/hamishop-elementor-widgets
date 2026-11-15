<?php
/**
 * Cart/order storage for the simple-product attribute picker.
 *
 * A simple product has no variations, so WooCommerce has nowhere to keep the
 * colour the customer picked. These hooks put it where the shop actually looks:
 * the cart line, the order line item (admin, e-mails, invoice).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Simple_Attributes {

	const KEY = 'hsw_picked';

	public function __construct() {
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 4 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'order_line_item' ), 10, 4 );
	}

	/**
	 * Read `attribute_pa_color=black` off the add-to-cart request.
	 *
	 * WooCommerce ignores those keys for a simple product — they only pick a
	 * variation — so nothing else consumes them.
	 *
	 * @param array $data         Cart item data.
	 * @param int   $product_id   Product id.
	 * @param int   $variation_id Variation id.
	 * @param int   $quantity     Quantity.
	 * @return array
	 */
	public function add_cart_item_data( $data, $product_id, $variation_id, $quantity ) {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product || ! $product->is_type( 'simple' ) ) {
			return $data;
		}

		$picked = $this->read_request( $product );
		if ( $picked ) {
			$data[ self::KEY ] = $picked;
		}

		return $data;
	}

	/**
	 * Validated picks from the request: only attributes the product really has,
	 * only terms that belong to it.
	 *
	 * `sanitize_text_field()` cannot be used here: it strips percent-encoded
	 * octets, which is exactly how a Persian term slug looks
	 * (`%d8%a2%d8%a8%db%8c`), so the colour arrived as an empty string and the
	 * pick silently disappeared while the next attribute still worked.
	 *
	 * @param WC_Product $product Product.
	 * @return array<string,array{label:string,value:string,slug:string}>
	 */
	protected function read_request( $product ) {
		$picked = array();

		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! $attribute instanceof WC_Product_Attribute ) {
				continue;
			}
			if ( ! $attribute->get_visible() || $attribute->get_variation() ) {
				continue;
			}

			$taxonomy = (string) $attribute->get_name();
			$key      = 'attribute_' . $taxonomy;

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, validated below.
			if ( ! isset( $_REQUEST[ $key ] ) ) {
				continue;
			}

			/*
			 * Decode before cleaning: a Persian term slug is stored percent-encoded
			 * (`%d8%a2…`) and `sanitize_text_field()` drops those octets as an
			 * obfuscation attempt, which turned the answer into an empty string.
			 */
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw = wc_clean( rawurldecode( (string) wp_unslash( $_REQUEST[ $key ] ) ) );
			if ( '' === (string) $raw ) {
				continue;
			}

			if ( $attribute->is_taxonomy() ) {
				$term = get_term_by( 'slug', $raw, $taxonomy );
				if ( ! $term instanceof WP_Term ) {
					$term = get_term_by( 'slug', sanitize_title( rawurldecode( $raw ) ), $taxonomy );
				}
				if ( ! $term instanceof WP_Term ) {
					$term = get_term_by( 'name', $raw, $taxonomy );
				}
				if ( ! $term instanceof WP_Term ) {
					continue;
				}
				$picked[ $taxonomy ] = array(
					'label' => wc_attribute_label( $taxonomy, $product ),
					'value' => $term->name,
					'slug'  => $term->slug,
				);
			} else {
				$probe = sanitize_title( rawurldecode( (string) $raw ) );
				$match = '';
				foreach ( (array) $attribute->get_options() as $option ) {
					$option = (string) $option;
					if ( sanitize_title( $option ) === $probe || $option === $raw ) {
						$match = $option;
						break;
					}
				}
				if ( '' === $match ) {
					continue;
				}
				$picked[ $taxonomy ] = array(
					'label' => (string) $attribute->get_name(),
					'value' => $match,
					'slug'  => sanitize_title( $match ),
				);
			}
		}

		return $picked;
	}

	/**
	 * Show the pick under the item name in the cart, checkout and order preview.
	 *
	 * @param array $item_data Existing rows.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public function item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item[ self::KEY ] ) || ! is_array( $cart_item[ self::KEY ] ) ) {
			return $item_data;
		}

		foreach ( $cart_item[ self::KEY ] as $row ) {
			if ( empty( $row['value'] ) ) {
				continue;
			}
			$item_data[] = array(
				'key'   => isset( $row['label'] ) ? $row['label'] : '',
				'value' => $row['value'],
			);
		}

		return $item_data;
	}

	/**
	 * Copy the pick onto the order line item (admin, e-mails, invoice).
	 *
	 * @param WC_Order_Item_Product $item          Order item.
	 * @param string                $cart_item_key Cart key.
	 * @param array                 $values        Cart item values.
	 * @param WC_Order              $order         Order.
	 * @return void
	 */
	public function order_line_item( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values[ self::KEY ] ) || ! is_array( $values[ self::KEY ] ) ) {
			return;
		}

		foreach ( $values[ self::KEY ] as $taxonomy => $row ) {
			if ( empty( $row['value'] ) ) {
				continue;
			}
			$item->add_meta_data( isset( $row['label'] ) ? $row['label'] : $taxonomy, $row['value'], true );
		}
	}
}
