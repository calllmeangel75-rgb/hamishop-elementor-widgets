<?php
/**
 * Custom Elementor control: async product chooser (select2 + admin-ajax).
 *
 * Works with the free version of Elementor. Values are stored as an array
 * of product IDs (multiple mode) or a single ID string.
 *
 * @package HamiShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Product chooser control.
 */
class HSW_Control_Product_Chooser extends \Elementor\Base_Data_Control {

	/**
	 * Control type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'hsw_products';
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	protected function get_default_settings() {
		return [
			'multiple'    => true,
			'placeholder' => __( 'Search products…', 'hamishop-elementor-widgets' ),
			'options'     => [],
		];
	}

	/**
	 * Escape the saved value.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public function get_style_value( $value ) {
		return $value;
	}

	/**
	 * Markup template for the editor.
	 *
	 * @return void
	 */
	public function content_template() {
		?>
		<div class="hsw-products-control">
			<select class="hsw-products-control__select" multiple></select>
			<div class="hsw-products-control__hint"><?php esc_html_e( 'Type to search products by name or SKU', 'hamishop-elementor-widgets' ); ?></div>
		</div>
		<?php
	}
}
