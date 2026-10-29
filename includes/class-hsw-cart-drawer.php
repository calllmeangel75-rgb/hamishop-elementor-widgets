<?php
/**
 * Header cart sidebar.
 *
 * The header cart icon used to navigate straight to the cart page. This class
 * renders an accessible off-canvas cart instead: line items, quantity
 * controls, the cart subtotal, «مشاهده سبد خرید» and «صورت‌حساب». It is shared
 * by the store and minimal headers, and its body is also registered as a
 * WooCommerce fragment so AJAX add/update requests refresh it in place.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Cart_Drawer {

	/**
	 * Drawer/label arguments with translated defaults.
	 *
	 * @param array $args Caller overrides.
	 * @return array
	 */
	protected static function args( $args = array() ) {
		$defaults = array(
			'id'              => 'hsw-cart-drawer',
			'title'           => __( 'سبد خرید', 'hamishop-elementor-widgets' ),
			'empty_title'     => __( 'سبد خرید شما خالی است', 'hamishop-elementor-widgets' ),
			'empty_text'      => __( 'هنوز محصولی به سبد خرید اضافه نکرده‌اید.', 'hamishop-elementor-widgets' ),
			'shop_text'       => __( 'مشاهده محصولات', 'hamishop-elementor-widgets' ),
			'view_cart_text'  => __( 'مشاهده سبد خرید', 'hamishop-elementor-widgets' ),
			'checkout_text'   => __( 'صورت‌حساب', 'hamishop-elementor-widgets' ),
			'remove_text'     => __( 'حذف', 'hamishop-elementor-widgets' ),
			'count_text'      => __( '{count} کالا', 'hamishop-elementor-widgets' ),
			'total_text'      => __( 'جمع سبد خرید', 'hamishop-elementor-widgets' ),
			'secure_text'     => __( 'پرداخت امن و رمزنگاری‌شده', 'hamishop-elementor-widgets' ),
			'decrease_text'   => __( 'کاهش تعداد', 'hamishop-elementor-widgets' ),
			'increase_text'   => __( 'افزایش تعداد', 'hamishop-elementor-widgets' ),
			'close_text'      => __( 'بستن سبد خرید', 'hamishop-elementor-widgets' ),
			'cart_url'        => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ),
			'checkout_url'    => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' ),
			'shop_url'        => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ),
		);

		$args = wp_parse_args( $args, $defaults );
		if ( '' === trim( (string) $args['shop_url'] ) ) {
			$args['shop_url'] = home_url( '/' );
		}

		return apply_filters( 'hsw_cart_drawer_args', $args );
	}

	/**
	 * Cart item count, safe when WooCommerce is inactive.
	 *
	 * @return int
	 */
	protected static function count() {
		if ( function_exists( 'WC' ) && WC()->cart ) {
			return (int) WC()->cart->get_cart_contents_count();
		}
		return 0;
	}

	/**
	 * Cart count badge markup (also a WooCommerce fragment target).
	 *
	 * @return string
	 */
	public static function count_html() {
		$count = self::count();
		$class = 'hsw-hd__cartpanelcount' . ( $count ? '' : ' is-empty' );

		return '<span class="' . esc_attr( $class ) . '" data-hsw-cart-drawer-count data-count="' . esc_attr( (int) $count ) . '">' . esc_html( HSW_Helpers::fa_num( $count ) ) . '</span>';
	}

	/**
	 * Render one quantity button.
	 *
	 * @param string $direction -1|1.
	 * @param int    $product_id Product ID.
	 * @param int    $variation_id Variation ID.
	 * @param bool   $disabled Disabled state.
	 * @param string $label Accessible label.
	 * @return string
	 */
	protected static function qty_button( $direction, $product_id, $variation_id, $disabled, $label ) {
		return '<button type="button" class="hsw-hd__cartqbtn"'
			. ' data-hsw-cart-qty="' . esc_attr( (int) $direction ) . '"'
			. ' data-product-id="' . esc_attr( (int) $product_id ) . '"'
			. ' data-variation-id="' . esc_attr( (int) $variation_id ) . '"'
			. ' aria-label="' . esc_attr( $label ) . '"'
			. ( $disabled ? ' disabled' : '' ) . '>'
			. HSW_Helpers::icon( (int) $direction > 0 ? 'add' : 'remove' )
			. '</button>';
	}

	/**
	 * Cart body markup. The outer node is the WooCommerce fragment target, so
	 * add/update responses replace the complete body in every header on page.
	 *
	 * @param array $args Drawer arguments.
	 * @return string
	 */
	public static function contents( $args = array() ) {
		$args = self::args( $args );

		$html = '<div class="hsw-hd__cartbody" data-hsw-cart-drawer-body>';

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $html . '</div>';
		}

		$cart  = WC()->cart;
		$count = (int) $cart->get_cart_contents_count();

		if ( $cart->is_empty() ) {
			$html .= '<div class="hsw-hd__cartempty">';
			$html .= '<span class="hsw-hd__cartemptyic">' . HSW_Helpers::icon( 'shopping_bag' ) . '</span>';
			$html .= '<strong>' . esc_html( $args['empty_title'] ) . '</strong>';
			$html .= '<p>' . esc_html( $args['empty_text'] ) . '</p>';
			$html .= '<a class="hsw-hd__cartshop" href="' . esc_url( $args['shop_url'] ) . '">' . esc_html( $args['shop_text'] ) . HSW_Helpers::icon( 'arrow_left_alt' ) . '</a>';
			$html .= '</div>';
			return $html . '</div>';
		}

		$subtotal = HSW_Helpers::price_html_fa( $cart->get_cart_subtotal() );
		$count_tx = str_replace( '{count}', HSW_Helpers::fa_num( $count ), (string) $args['count_text'] );

		$html .= '<div class="hsw-hd__cartsummary">';
		$html .= '<span>' . esc_html( $count_tx ) . '</span>';
		$html .= '<span>' . wp_kses_post( $subtotal ) . '</span>';
		$html .= '</div>';

		$html .= '<ul class="hsw-hd__cartitems">';

		foreach ( $cart->get_cart() as $cart_key => $item ) {
			$product = isset( $item['data'] ) ? $item['data'] : null;
			if ( ! $product instanceof WC_Product ) {
				$product = wc_get_product( isset( $item['product_id'] ) ? $item['product_id'] : 0 );
			}
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			$product_id   = isset( $item['product_id'] ) ? (int) $item['product_id'] : (int) $product->get_id();
			$variation_id = isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0;
			$qty          = max( 1, isset( $item['quantity'] ) ? (int) $item['quantity'] : 1 );
			$permalink    = $product->is_visible() ? $product->get_permalink() : $args['cart_url'];
			$meta         = '';

			if ( function_exists( 'wc_get_formatted_cart_item_data' ) ) {
				$meta = wp_strip_all_tags( (string) wc_get_formatted_cart_item_data( $item, true ) );
				$meta = trim( preg_replace( '/\s+/u', ' ', $meta ) );
			}

			$html .= '<li class="hsw-hd__cartitem" data-hsw-cart-line data-product-id="' . esc_attr( $product_id ) . '" data-variation-id="' . esc_attr( $variation_id ) . '" data-qty="' . esc_attr( $qty ) . '">';
			$html .= '<a class="hsw-hd__cartthumb" href="' . esc_url( $permalink ) . '" tabindex="-1" aria-hidden="true">' . $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy', 'alt' => $product->get_name() ) ) . '</a>';
			$html .= '<div class="hsw-hd__cartinfo">';
			$html .= '<a class="hsw-hd__cartname" href="' . esc_url( $permalink ) . '">' . esc_html( $product->get_name() ) . '</a>';
			if ( '' !== $meta ) {
				$html .= '<span class="hsw-hd__cartmeta">' . esc_html( $meta ) . '</span>';
			}
			$html .= '<div class="hsw-hd__cartrow">';
			$html .= '<div class="hsw-hd__cartqty" role="group" aria-label="' . esc_attr__( 'تعداد', 'hamishop-elementor-widgets' ) . '">';
			/* RTL: the increase button sits on the right, decrease on the left. */
			$html .= self::qty_button( 1, $product_id, $variation_id, false, $args['increase_text'] );
			$html .= '<span class="hsw-hd__cartqtyval" data-hsw-cart-qtyval>' . esc_html( HSW_Helpers::fa_num( $qty ) ) . '</span>';
			$html .= self::qty_button( -1, $product_id, $variation_id, $qty <= 1, $args['decrease_text'] );
			$html .= '</div>';
			$html .= '<span class="hsw-hd__cartlineprice">' . wp_kses_post( HSW_Helpers::price_html_fa( $cart->get_product_subtotal( $product, $qty ) ) ) . '</span>';
			$html .= '<button type="button" class="hsw-hd__cartremove" data-hsw-cart-remove data-product-id="' . esc_attr( $product_id ) . '" data-variation-id="' . esc_attr( $variation_id ) . '" aria-label="' . esc_attr( $args['remove_text'] . ' ' . $product->get_name() ) . '">' . HSW_Helpers::icon( 'delete' ) . '</button>';
			$html .= '</div>';
			$html .= '</div>';
			$html .= '</li>';
		}

		$html .= '</ul>';

		$html .= '<div class="hsw-hd__cartfoot">';
		$html .= '<div class="hsw-hd__carttotalrow"><span>' . esc_html( $args['total_text'] ) . '</span><strong>' . wp_kses_post( $subtotal ) . '</strong></div>';
		$html .= '<a class="hsw-hd__cartcheckout" href="' . esc_url( $args['checkout_url'] ) . '">' . HSW_Helpers::icon( 'lock' ) . '<span>' . esc_html( $args['checkout_text'] ) . '</span>' . HSW_Helpers::icon( 'arrow_left_alt' ) . '</a>';
		$html .= '<a class="hsw-hd__cartview" href="' . esc_url( $args['cart_url'] ) . '">' . HSW_Helpers::icon( 'shopping_cart' ) . '<span>' . esc_html( $args['view_cart_text'] ) . '</span></a>';
		$html .= '<span class="hsw-hd__cartsecure">' . HSW_Helpers::icon( 'verified_user' ) . '<span>' . esc_html( $args['secure_text'] ) . '</span></span>';
		$html .= '</div>';

		$html .= '</div>';

		return apply_filters( 'hsw_cart_drawer_contents', $html, $args );
	}

	/**
	 * Full drawer markup (overlay, panel, header, body).
	 *
	 * @param array $args Drawer arguments.
	 * @return string
	 */
	public static function render( $args = array() ) {
		$args = self::args( $args );

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}

		$id       = sanitize_html_class( (string) $args['id'] );
		$id       = '' !== $id ? $id : 'hsw-cart-drawer';
		$title_id = $id . '-title';

		$html  = '<div class="hsw-hd__cartdrawer" id="' . esc_attr( $id ) . '" data-hsw-cart-drawer aria-hidden="true">';
		$html .= '<div class="hsw-hd__cartoverlay" data-hsw-cart-close></div>';
		$html .= '<aside class="hsw-hd__cartpanel" role="dialog" aria-modal="true" aria-labelledby="' . esc_attr( $title_id ) . '">';
		$html .= '<div class="hsw-hd__carthead">';
		$html .= '<span class="hsw-hd__carttitle" id="' . esc_attr( $title_id ) . '">' . HSW_Helpers::icon( 'shopping_cart' ) . '<span>' . esc_html( $args['title'] ) . '</span>' . self::count_html() . '</span>';
		$html .= '<button type="button" class="hsw-hd__cartclose" data-hsw-cart-close aria-label="' . esc_attr( $args['close_text'] ) . '">' . HSW_Helpers::icon( 'close' ) . '</button>';
		$html .= '</div>';
		$html .= self::contents( $args );
		$html .= '</aside>';
		$html .= '</div>';

		return $html;
	}
}
