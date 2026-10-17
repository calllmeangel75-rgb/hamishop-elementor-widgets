<?php
/**
 * Dependency checks for WooCommerce and Elementor.
 *
 * @package HamiShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Checks required plugins.
 */
class HSW_Dependencies {

	/**
	 * Whether all dependencies pass.
	 *
	 * @return bool
	 */
	public static function passes() {
		return empty( self::missing() );
	}

	/**
	 * List of missing/insufficient dependencies.
	 *
	 * @return string[] Human readable names.
	 */
	public static function missing() {
		$missing = [];

		if ( ! class_exists( 'WooCommerce' ) ) {
			$missing[] = __( 'WooCommerce', 'hamishop-elementor-widgets' );
		}

		if ( ! did_action( 'elementor/loaded' ) ) {
			$missing[] = __( 'Elementor', 'hamishop-elementor-widgets' );
		}

		return $missing;
	}

	/**
	 * Render the admin notice for missing plugins.
	 *
	 * @return void
	 */
	public static function missing_notice() {
		$missing = self::missing();
		if ( empty( $missing ) ) {
			return;
		}

		$list = esc_html( implode( '، ', $missing ) );
		?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: %s: missing plugin names */
					esc_html__( 'افزونه «ویجت‌های فروشگاهی حامی‌شاپ» برای کار کردن به این افزونه‌ها نیاز دارد: %s', 'hamishop-elementor-widgets' ),
					'<strong>' . $list . '</strong>'
				);
				?>
			</p>
		</div>
		<?php
	}
}
