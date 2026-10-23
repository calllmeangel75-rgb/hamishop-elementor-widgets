<?php
/**
 * Store branding.
 *
 * The plugin ships with a few visible brand mentions (account panel logo,
 * welcome notification, support signature). Every store needs its own name and
 * logo there, so they are read from one option (`hsw_brand`) instead of being
 * hard-coded.
 *
 * Defaults fall back to the WordPress site title and the theme's custom logo,
 * so a fresh install already shows the store's own brand without touching a
 * single setting.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Branding {

	const OPTION = 'hsw_brand';

	/** @var HSW_Branding|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 12 );
		add_action( 'admin_init', array( $this, 'maybe_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_print_footer_scripts', array( $this, 'picker_script' ), 99 );
	}

	/* ---------------------------------------------------------------------
	 * Values
	 * ------------------------------------------------------------------ */

	/**
	 * Defaults — the site's own identity.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'name'    => get_bloginfo( 'name' ),
			'tagline' => get_bloginfo( 'description' ),
			'logo_id' => (int) get_theme_mod( 'custom_logo' ),
			'logo_url' => '',
		);
	}

	/**
	 * Stored branding merged over the defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::defaults(), $saved );
	}

	/**
	 * Brand name shown in the UI.
	 *
	 * @return string
	 */
	public static function name() {
		$b    = self::get();
		$name = trim( (string) $b['name'] );
		return '' !== $name ? $name : get_bloginfo( 'name' );
	}

	/**
	 * Short description / slogan.
	 *
	 * @return string
	 */
	public static function tagline() {
		$b = self::get();
		return trim( (string) $b['tagline'] );
	}

	/**
	 * Logo URL, or an empty string when the store has none.
	 *
	 * @param string $size Image size.
	 * @return string
	 */
	public static function logo_url( $size = 'medium' ) {
		$b = self::get();

		if ( ! empty( $b['logo_id'] ) ) {
			$url = wp_get_attachment_image_url( (int) $b['logo_id'], $size );
			if ( $url ) {
				return $url;
			}
		}
		if ( ! empty( $b['logo_url'] ) ) {
			return (string) $b['logo_url'];
		}
		return '';
	}

	/**
	 * `<img>` for the brand logo, or '' when there is none.
	 *
	 * @param string $class Extra class.
	 * @param string $alt   Alt text (defaults to the brand name).
	 * @return string
	 */
	public static function logo_html( $class = '', $alt = '' ) {
		$url = self::logo_url();
		if ( '' === $url ) {
			return '';
		}
		$alt = '' !== $alt ? $alt : self::name();

		return '<img class="hsw-brand-logo ' . esc_attr( $class ) . '" src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="eager" decoding="async" />';
	}

	/**
	 * Logo + name pair, used by the account panel and the auth screen.
	 *
	 * @param string $markup_class Wrapper class.
	 * @return string
	 */
	public static function mark_html( $markup_class = '' ) {
		$logo = self::logo_html( 'hsw-brand-logo--mark' );

		if ( '' !== $logo ) {
			return '<span class="' . esc_attr( $markup_class ) . '">' . $logo . '</span>';
		}

		// No logo: fall back to the first letter of the name in a rounded tile.
		$initial = function_exists( 'mb_substr' ) ? mb_substr( self::name(), 0, 1 ) : substr( self::name(), 0, 1 );

		return '<span class="' . esc_attr( $markup_class ) . '"><span class="hsw-brand-initial">' . esc_html( $initial ) . '</span></span>';
	}

	/* ---------------------------------------------------------------------
	 * Admin
	 * ------------------------------------------------------------------ */

	public function menu() {
		add_submenu_page(
			'hamishop',
			__( 'برند و لوگو', 'hamishop-elementor-widgets' ),
			__( 'برند و لوگو', 'hamishop-elementor-widgets' ),
			'manage_options',
			'hamishop-brand',
			array( $this, 'render_page' )
		);
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'hamishop-brand' ) ) {
			return;
		}
		wp_enqueue_media();
	}

	/**
	 * Media picker wiring — printed in the footer, after `wp-media` exists.
	 */
	public function picker_script() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'hamishop-brand' ) ) {
			return;
		}
		?>
		<script>
		( function () {
			var btn = document.getElementById( 'hsw-brand-pick' );
			var clear = document.getElementById( 'hsw-brand-clear' );
			var input = document.getElementById( 'hsw-brand-logo-id' );
			var preview = document.getElementById( 'hsw-brand-preview' );
			if ( ! btn || ! window.wp || ! wp.media ) { return; }

			var frame = null;
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				if ( ! frame ) {
					frame = wp.media( {
						title: 'انتخاب لوگو',
						button: { text: 'استفاده از این تصویر' },
						library: { type: 'image' },
						multiple: false
					} );
					frame.on( 'select', function () {
						var att = frame.state().get( 'selection' ).first().toJSON();
						input.value = att.id;
						var url = ( att.sizes && att.sizes.medium ) ? att.sizes.medium.url : att.url;
						preview.innerHTML = '<img src="' + url + '" alt="" />';
						preview.classList.remove( 'is-empty' );
					} );
				}
				frame.open();
			} );

			if ( clear ) {
				clear.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					input.value = '';
					preview.innerHTML = '';
					preview.classList.add( 'is-empty' );
				} );
			}
		} )();
		</script>
		<?php
	}

	/**
	 * Persist the branding (nonce + capability checked).
	 */
	public function maybe_save() {
		if ( ! isset( $_POST['hsw_brand_nonce'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! check_admin_referer( 'hsw_save_brand', 'hsw_brand_nonce' ) ) {
			return;
		}

		$in = isset( $_POST['hsw_brand'] ) ? (array) wp_unslash( $_POST['hsw_brand'] ) : array();

		update_option(
			self::OPTION,
			array(
				'name'     => sanitize_text_field( $in['name'] ?? '' ),
				'tagline'  => sanitize_text_field( $in['tagline'] ?? '' ),
				'logo_id'  => absint( $in['logo_id'] ?? 0 ),
				'logo_url' => esc_url_raw( $in['logo_url'] ?? '' ),
			),
			false
		);

		add_settings_error( 'hsw_brand', 'saved', __( 'برند ذخیره شد.', 'hamishop-elementor-widgets' ), 'updated' );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$b   = self::get();
		$url = self::logo_url( 'medium' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'برند و لوگو', 'hamishop-elementor-widgets' ); ?></h1>
			<p class="description" style="max-width:760px">
				<?php esc_html_e( 'نام و لوگوی فروشگاه شما در پنل حساب کاربری، صفحهٔ ورود و اعلان‌های داخل افزونه استفاده می‌شود. اگر خالی بگذارید، نام و لوگوی خود قالب وردپرس نمایش داده می‌شود.', 'hamishop-elementor-widgets' ); ?>
			</p>

			<?php settings_errors( 'hsw_brand' ); ?>

			<form method="post">
				<?php wp_nonce_field( 'hsw_save_brand', 'hsw_brand_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row"><label for="hsw-brand-name"><?php esc_html_e( 'نام برند', 'hamishop-elementor-widgets' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="hsw-brand-name" name="hsw_brand[name]"
								value="<?php echo esc_attr( $b['name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hsw-brand-tagline"><?php esc_html_e( 'شعار کوتاه', 'hamishop-elementor-widgets' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="hsw-brand-tagline" name="hsw_brand[tagline]"
								value="<?php echo esc_attr( $b['tagline'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'description' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'لوگو', 'hamishop-elementor-widgets' ); ?></th>
						<td>
							<input type="hidden" id="hsw-brand-logo-id" name="hsw_brand[logo_id]" value="<?php echo esc_attr( (int) $b['logo_id'] ); ?>" />
							<div id="hsw-brand-preview" class="hsw-brand-preview<?php echo $url ? '' : ' is-empty'; ?>">
								<?php if ( $url ) : ?>
									<img src="<?php echo esc_url( $url ); ?>" alt="" />
								<?php endif; ?>
							</div>
							<p>
								<button type="button" class="button" id="hsw-brand-pick"><?php esc_html_e( 'انتخاب از کتابخانه', 'hamishop-elementor-widgets' ); ?></button>
								<button type="button" class="button-link" id="hsw-brand-clear"><?php esc_html_e( 'حذف', 'hamishop-elementor-widgets' ); ?></button>
							</p>
							<p class="description"><?php esc_html_e( 'اگر لوگویی انتخاب نکنید، لوگوی سفارشی قالب استفاده می‌شود.', 'hamishop-elementor-widgets' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hsw-brand-logo-url"><?php esc_html_e( 'یا آدرس تصویر لوگو', 'hamishop-elementor-widgets' ); ?></label></th>
						<td>
							<input type="url" class="regular-text" id="hsw-brand-logo-url" name="hsw_brand[logo_url]"
								value="<?php echo esc_attr( $b['logo_url'] ); ?>" placeholder="https://" dir="ltr" />
							<p class="description"><?php esc_html_e( 'فقط وقتی استفاده می‌شود که لوگویی از کتابخانه انتخاب نشده باشد.', 'hamishop-elementor-widgets' ); ?></p>
						</td>
					</tr>
					</tbody>
				</table>

				<p class="submit">
					<?php submit_button( __( 'ذخیره برند', 'hamishop-elementor-widgets' ), 'primary', 'submit', false ); ?>
				</p>
			</form>
		</div>
		<style>
			.hsw-brand-preview { display: flex; align-items: center; justify-content: center; width: 180px; height: 90px; margin-bottom: 10px; padding: 10px; border: 1px dashed #c3c4c7; border-radius: 10px; background: #fff; }
			.hsw-brand-preview.is-empty { background: repeating-linear-gradient(45deg, #f6f7f7, #f6f7f7 8px, #fff 8px, #fff 16px); }
			.hsw-brand-preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
		</style>
		<?php
	}
}
