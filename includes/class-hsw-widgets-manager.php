<?php
/**
 * Widget registry — the single source of truth for all HamiShop widgets.
 *
 * @package HamiShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Widget map + enable/disable handling.
 */
final class HSW_Widgets_Manager {

	/**
	 * All registered widgets.
	 *
	 * @return array key => [ file, class, name (widget id), title ]
	 */
	public static function widgets() {
		return [
			'product_loop'   => [
				'file'  => 'class-hsw-widget-product-loop.php',
				'class' => 'HSW_Widget_Product_Loop',
				'name'  => 'hsw-loop-item',
				'title' => __( 'HamiShop Loop Item', 'hamishop-elementor-widgets' ),
			],
			'shop_archive'   => [
				'file'  => 'class-hsw-widget-shop-archive.php',
				'class' => 'HSW_Widget_Shop_Archive',
				'name'  => 'hsw-shop-archive',
				'title' => __( 'HamiShop Archive', 'hamishop-elementor-widgets' ),
			],
			'single_product' => [
				'file'  => 'class-hsw-widget-single-product.php',
				'class' => 'HSW_Widget_Single_Product',
				'name'  => 'hsw-single-product',
				'title' => __( 'HamiShop Single Product', 'hamishop-elementor-widgets' ),
			],
			'cart'           => [
				'file'  => 'class-hsw-widget-cart.php',
				'class' => 'HSW_Widget_Cart',
				'name'  => 'hsw-cart',
				'title' => __( 'HamiShop Cart', 'hamishop-elementor-widgets' ),
			],
			'checkout'       => [
				'file'  => 'class-hsw-widget-checkout.php',
				'class' => 'HSW_Widget_Checkout',
				'name'  => 'hsw-checkout',
				'title' => __( 'HamiShop Checkout', 'hamishop-elementor-widgets' ),
			],
		];
	}

	/**
	 * Is a widget enabled via settings?
	 *
	 * @param string $key Widget key.
	 * @return bool
	 */
	public static function is_enabled( $key ) {
		// Default: everything enabled.
		return 'yes' === get_option( 'hsw_widget_' . $key, 'yes' );
	}

	/**
	 * Only the enabled widgets (used at registration time).
	 *
	 * @return array
	 */
	public static function enabled_widgets() {
		$out = [];
		foreach ( self::widgets() as $key => $widget ) {
			if ( self::is_enabled( $key ) ) {
				$out[ $key ] = $widget;
			}
		}
		return $out;
	}
}
