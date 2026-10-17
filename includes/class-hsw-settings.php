<?php
/**
 * Lightweight admin settings page (Settings → HamiShop).
 *
 * @package HamiShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings page.
 */
final class HSW_Settings {

	/**
	 * Bootstrap.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'register' ] );
	}

	/**
	 * Add the submenu page.
	 *
	 * @return void
	 */
	public static function menu() {
		add_submenu_page(
			'options-general.php',
			__( 'HamiShop Settings', 'hamishop-elementor-widgets' ),
			__( 'HamiShop', 'hamishop-elementor-widgets' ),
			'manage_options',
			'hamishop',
			[ __CLASS__, 'render' ]
		);
	}

	/**
	 * Register settings + fields.
	 *
	 * @return void
	 */
	public static function register() {
		$widget_toggles = [
			'hsw_widget_product_loop'   => __( 'Loop Item', 'hamishop-elementor-widgets' ),
			'hsw_widget_shop_archive'   => __( 'Shop Archive', 'hamishop-elementor-widgets' ),
			'hsw_widget_single_product' => __( 'Single Product', 'hamishop-elementor-widgets' ),
			'hsw_widget_cart'           => __( 'Cart', 'hamishop-elementor-widgets' ),
			'hsw_widget_checkout'       => __( 'Checkout', 'hamishop-elementor-widgets' ),
		];

		register_setting( 'hsw_group', 'hsw_persian_numbers', [
			'type'              => 'string',
			'sanitize_callback' => [ __CLASS__, 'sanitize_yesno' ],
			'default'           => 'yes',
		] );

		register_setting( 'hsw_group', 'hsw_remove_wc_blocks_css', [
			'type'              => 'string',
			'sanitize_callback' => [ __CLASS__, 'sanitize_yesno' ],
			'default'           => 'no',
		] );

		foreach ( $widget_toggles as $option => $label ) {
			register_setting( 'hsw_group', $option, [
				'type'              => 'string',
				'sanitize_callback' => [ __CLASS__, 'sanitize_yesno' ],
				'default'           => 'yes',
			] );
		}

		// Sections.
		add_settings_section( 'hsw_section_widgets', __( 'Widgets', 'hamishop-elementor-widgets' ), '__return_false', 'hamishop' );
		foreach ( $widget_toggles as $option => $label ) {
			add_settings_field( $option, $label, [ __CLASS__, 'field_toggle' ], 'hamishop', 'hsw_section_widgets', [ 'option' => $option ] );
		}

		add_settings_section( 'hsw_section_perf', __( 'Performance', 'hamishop-elementor-widgets' ), '__return_false', 'hamishop' );
		add_settings_field( 'hsw_persian_numbers', __( 'Persian digits in prices & numbers', 'hamishop-elementor-widgets' ), [ __CLASS__, 'field_toggle' ], 'hamishop', 'hsw_section_perf', [ 'option' => 'hsw_persian_numbers' ] );
		add_settings_field( 'hsw_remove_wc_blocks_css', __( 'Remove WooCommerce block CSS on shop-free pages', 'hamishop-elementor-widgets' ), [ __CLASS__, 'field_toggle' ], 'hamishop', 'hsw_section_perf', [ 'option' => 'hsw_remove_wc_blocks_css' ] );
	}

	/**
	 * Yes/No sanitizer.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function sanitize_yesno( $value ) {
		return ( 'yes' === $value ) ? 'yes' : 'no';
	}

	/**
	 * One checkbox field.
	 *
	 * @param array $args Args with 'option'.
	 * @return void
	 */
	public static function field_toggle( $args ) {
		$option = isset( $args['option'] ) ? $args['option'] : '';
		$value  = get_option( $option, 'yes' );
		?>
		<label class="hsw-switch">
			<input type="checkbox" value="yes" name="<?php echo esc_attr( $option ); ?>" <?php checked( 'yes', $value ); ?> />
			<span class="hsw-switch__track" aria-hidden="true"></span>
		</label>
		<?php
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap hsw-settings-wrap">
			<h1><?php esc_html_e( 'HamiShop — Elementor WooCommerce Widgets', 'hamishop-elementor-widgets' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'hsw_group' );
				do_settings_sections( 'hamishop' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
