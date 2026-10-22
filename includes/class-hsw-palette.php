<?php
/**
 * Site-wide colour palette.
 *
 * One option (`hsw_palette`) owns the shared design tokens. It is printed as
 * CSS custom properties on `html:root` (specificity 0,1,1 — one step above the
 * plain `:root` blocks inside the family stylesheets, so it wins regardless of
 * stylesheet order), and every widget family points its own local tokens at
 * these globals with its previous hard-coded value as the fallback.
 *
 * Result: pick a colour once in HamiShop → پالت رنگی and every widget follows,
 * while a per-widget Elementor style override still wins because those are
 * applied on the widget wrapper (deeper in the tree).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Palette {

	const OPTION = 'hsw_palette';

	/** @var HSW_Palette|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 11 );
		add_action( 'admin_init', array( $this, 'maybe_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_print_footer_scripts', array( $this, 'init_script' ), 99 );
		add_action( 'wp_head', array( $this, 'print_css' ), 5 );
		add_action( 'admin_head', array( $this, 'print_css' ), 5 );
	}

	/* ---------------------------------------------------------------------
	 * Tokens
	 * ------------------------------------------------------------------ */

	/**
	 * Token definitions: key => [label, default, description].
	 *
	 * Defaults reproduce the blue storefront palette the site already used,
	 * so activating the feature changes nothing until a colour is picked.
	 *
	 * @return array<string,array{0:string,1:string,2:string}>
	 */
	public static function tokens() {
		return array(
			'primary'      => array( __( 'رنگ اصلی', 'hamishop-elementor-widgets' ), '#2563eb', __( 'دکمه‌ها، لینک‌ها، آیتم فعال', 'hamishop-elementor-widgets' ) ),
			'primary_dark' => array( __( 'رنگ اصلی تیره', 'hamishop-elementor-widgets' ), '#1d4ed8', __( 'حالت هاور و سربرگ‌های تیره', 'hamishop-elementor-widgets' ) ),
			'accent'       => array( __( 'رنگ تاکید', 'hamishop-elementor-widgets' ), '#fc593a', __( 'برچسب تخفیف، CTA خرید', 'hamishop-elementor-widgets' ) ),
			'hover'        => array( __( 'رنگ هاور', 'hamishop-elementor-widgets' ), '#1d4ed8', __( 'حالت هاور تب‌ها، منوها و آیتم‌های کلیک‌شدنی', 'hamishop-elementor-widgets' ) ),
			'text'         => array( __( 'رنگ متن', 'hamishop-elementor-widgets' ), '#191c1e', __( 'متن اصلی', 'hamishop-elementor-widgets' ) ),
			'muted'        => array( __( 'متن کم‌رنگ', 'hamishop-elementor-widgets' ), '#75777f', __( 'توضیحات، متادیتا', 'hamishop-elementor-widgets' ) ),
			'border'       => array( __( 'رنگ حاشیه', 'hamishop-elementor-widgets' ), '#e1e2e4', __( 'خطوط و کادرها', 'hamishop-elementor-widgets' ) ),
			'surface'      => array( __( 'پس‌زمینه کارت', 'hamishop-elementor-widgets' ), '#ffffff', __( 'کارت‌ها و باکس‌ها', 'hamishop-elementor-widgets' ) ),
			'surface_alt'  => array( __( 'پس‌زمینه ملایم', 'hamishop-elementor-widgets' ), '#f3f4f6', __( 'نوارها و بخش‌های فرورفته', 'hamishop-elementor-widgets' ) ),
			'success'      => array( __( 'موفق / موجود', 'hamishop-elementor-widgets' ), '#0f9f6e', '' ),
			'warning'      => array( __( 'هشدار / امتیاز', 'hamishop-elementor-widgets' ), '#b45309', '' ),
			'danger'       => array( __( 'خطا / ناموجود', 'hamishop-elementor-widgets' ), '#ba1a1a', '' ),
		);
	}

	/**
	 * Default palette.
	 *
	 * @return array<string,string>
	 */
	public static function defaults() {
		$out = array();
		foreach ( self::tokens() as $key => $def ) {
			$out[ $key ] = $def[1];
		}
		return $out;
	}

	/**
	 * Saved palette merged over the defaults.
	 *
	 * @return array<string,string>
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$out   = self::defaults();

		foreach ( $out as $key => $default ) {
			if ( isset( $saved[ $key ] ) && is_string( $saved[ $key ] ) ) {
				$color = sanitize_hex_color( $saved[ $key ] );
				if ( $color ) {
					$out[ $key ] = $color;
				}
			}
		}
		return $out;
	}

	/**
	 * CSS custom properties for the current palette.
	 *
	 * Two groups on purpose:
	 *  - `--hsw-primary*` / `--hsw-on-*` reuse the vocabulary the product and
	 *    loop stylesheets already define on `:root`, so those families follow
	 *    the palette without touching their stylesheets.
	 *  - `--hsw-pal-*` carries surface/border/muted/semantic colours. Those get
	 *    a distinct name because `--hsw-surface` already means "page
	 *    background" inside the product family and must not be redefined.
	 *
	 * @return string
	 */
	public function css() {
		$p = self::get();

		$vars = array(
			'--hsw-primary'            => $p['primary'],
			'--hsw-primary-dark'       => $p['primary_dark'],
			'--hsw-primary-container'  => $p['primary_dark'],
			'--hsw-secondary-container' => $p['accent'],
			'--hsw-accent'             => $p['accent'],
			'--hsw-hover'              => $p['hover'],
			'--hsw-on-primary'         => '#ffffff',
			'--hsw-on-surface'         => $p['text'],

			'--hsw-pal-surface'        => $p['surface'],
			'--hsw-pal-surface-alt'    => $p['surface_alt'],
			'--hsw-pal-border'         => $p['border'],
			'--hsw-pal-muted'          => $p['muted'],
			'--hsw-pal-success'        => $p['success'],
			'--hsw-pal-warning'        => $p['warning'],
			'--hsw-pal-danger'         => $p['danger'],

			/* Derived tints so families do not need their own maths. */
			'--hsw-pal-primary-50'     => 'color-mix(in srgb, ' . $p['primary'] . ', #fff 92%)',
			'--hsw-pal-primary-100'    => 'color-mix(in srgb, ' . $p['primary'] . ', #fff 84%)',
			'--hsw-pal-primary-light'  => 'color-mix(in srgb, ' . $p['primary'] . ', #fff 94%)',
			'--hsw-pal-primary-ring'   => 'color-mix(in srgb, ' . $p['primary'] . ', transparent 72%)',
			'--hsw-pal-accent-soft'    => 'color-mix(in srgb, ' . $p['accent'] . ', #fff 86%)',
		);

		$decls = '';
		foreach ( $vars as $name => $value ) {
			$decls .= $name . ':' . $value . ';';
		}

		return 'html:root{' . $decls . '}';
	}

	/**
	 * Print the palette in <head> (front-end and admin, so the Elementor
	 * editor preview and the settings screen both match the site).
	 */
	public function print_css() {
		echo "\n<style id=\"hsw-palette\">" . $this->css() . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from sanitized hex colours only.
	}

	/* ---------------------------------------------------------------------
	 * Admin
	 * ------------------------------------------------------------------ */

	public function menu() {
		add_submenu_page(
			'hamishop',
			__( 'پالت رنگی', 'hamishop-elementor-widgets' ),
			__( 'پالت رنگی', 'hamishop-elementor-widgets' ),
			'manage_options',
			'hamishop-palette',
			array( $this, 'render_page' )
		);
	}

	public function assets( $hook ) {
		if ( 'hamishop_page_hamishop-palette' !== $hook && false === strpos( (string) $hook, 'hamishop-palette' ) ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
	}

	/**
	 * Bootstrap wp-color-picker (iris) after its script has printed.
	 *
	 * The init must live in the footer: `wp-color-picker` is enqueued normally
	 * and therefore prints after the page body, so an inline <script> inside
	 * the form would run while `jQuery.fn.wpColorPicker` is still undefined.
	 */
	public function init_script() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'hamishop-palette' ) ) {
			return;
		}
		?>
		<script>
		( function () {
			if ( ! window.jQuery || ! jQuery.fn.wpColorPicker ) { return; }
			jQuery( '.hsw-color-field' ).wpColorPicker();
		} )();
		</script>
		<?php
	}

	/**
	 * Persist the palette (nonce + capability checked).
	 */
	public function maybe_save() {
		if ( ! isset( $_POST['hsw_palette_nonce'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! check_admin_referer( 'hsw_save_palette', 'hsw_palette_nonce' ) ) {
			return;
		}

		$clean = array();

		if ( isset( $_POST['hsw_reset'] ) ) {
			update_option( self::OPTION, self::defaults() );
			add_settings_error( 'hsw_palette', 'reset', __( 'پالت به مقدار پیش‌فرض بازگشت.', 'hamishop-elementor-widgets' ), 'updated' );
			return;
		}

		$incoming = isset( $_POST['hsw_palette'] ) && is_array( $_POST['hsw_palette'] ) ? wp_unslash( $_POST['hsw_palette'] ) : array();

		foreach ( self::defaults() as $key => $default ) {
			$value = isset( $incoming[ $key ] ) ? sanitize_hex_color( (string) $incoming[ $key ] ) : '';
			$clean[ $key ] = $value ? $value : $default;
		}

		update_option( self::OPTION, $clean );
		add_settings_error( 'hsw_palette', 'saved', __( 'پالت رنگی ذخیره شد.', 'hamishop-elementor-widgets' ), 'updated' );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$p = self::get();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'پالت رنگی HamiShop', 'hamishop-elementor-widgets' ); ?></h1>
			<p style="max-width:760px">
				<?php esc_html_e( 'این رنگ‌ها به همهٔ ویجت‌های HamiShop اعمال می‌شوند. اگر برای یک ویجت خاص رنگ جداگانه‌ای در تنظیمات المنتور انتخاب کرده باشید، همان رنگ اولویت دارد.', 'hamishop-elementor-widgets' ); ?>
			</p>

			<?php settings_errors( 'hsw_palette' ); ?>

			<form method="post">
				<?php wp_nonce_field( 'hsw_save_palette', 'hsw_palette_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tbody>
					<?php foreach ( self::tokens() as $key => $def ) : ?>
						<?php list( $label, $default, $help ) = $def; ?>
						<tr>
							<th scope="row">
								<label for="hsw-pal-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
							</th>
							<td>
								<input
									type="text"
									class="hsw-color-field"
									id="hsw-pal-<?php echo esc_attr( $key ); ?>"
									name="hsw_palette[<?php echo esc_attr( $key ); ?>]"
									value="<?php echo esc_attr( $p[ $key ] ); ?>"
									data-default-color="<?php echo esc_attr( $default ); ?>"
								/>
								<?php if ( '' !== $help ) : ?>
									<p class="description"><?php echo esc_html( $help ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<p class="submit" style="display:flex;gap:10px;align-items:center">
					<?php submit_button( __( 'ذخیره پالت', 'hamishop-elementor-widgets' ), 'primary', 'submit', false ); ?>
					<button type="submit" name="hsw_reset" value="1" class="button button-secondary"
						onclick="return confirm('<?php echo esc_js( __( 'پالت به پیش‌فرض بازگردد؟', 'hamishop-elementor-widgets' ) ); ?>');">
						<?php esc_html_e( 'بازنشانی به پیش‌فرض', 'hamishop-elementor-widgets' ); ?>
					</button>
				</p>
			</form>

			<hr />
			<h2><?php esc_html_e( 'پیش‌نمایش', 'hamishop-elementor-widgets' ); ?></h2>
			<div class="hsw-pal-preview" style="display:flex;flex-wrap:wrap;gap:10px;max-width:760px">
				<?php foreach ( self::tokens() as $key => $def ) : ?>
					<div style="flex:1 1 120px;border:1px solid <?php echo esc_attr( $p['border'] ); ?>;border-radius:10px;overflow:hidden;background:#fff">
						<div style="height:56px;background:<?php echo esc_attr( $p[ $key ] ); ?>"></div>
						<div style="padding:6px 8px;font-size:11px;color:#444">
							<strong style="display:block"><?php echo esc_html( $def[0] ); ?></strong>
							<code style="font-size:10px"><?php echo esc_html( $p[ $key ] ); ?></code>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
