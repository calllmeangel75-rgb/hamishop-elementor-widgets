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

		/*
		 * Sender identity for every email WordPress sends — order mail, password
		 * resets, our own verification codes. Previously this needed a snippet in
		 * the child theme (`wp_mail_from` / `wp_mail_from_name`); now it is a
		 * setting. Both filters stay inert while their field is empty.
		 */
		add_filter( 'wp_mail_from', array( $this, 'filter_from_email' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'filter_from_name' ) );
	}

	/**
	 * @param string $email Original from address.
	 * @return string
	 */
	public function filter_from_email( $email ) {
		$b     = self::get();
		$value = trim( (string) $b['from_email'] );
		return is_email( $value ) ? $value : $email;
	}

	/**
	 * @param string $name Original from name.
	 * @return string
	 */
	public function filter_from_name( $name ) {
		$b     = self::get();
		$value = trim( (string) $b['from_name'] );
		return '' !== $value ? $value : $name;
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
			'name'        => get_bloginfo( 'name' ),
			'tagline'     => get_bloginfo( 'description' ),
			'logo_id'     => (int) get_theme_mod( 'custom_logo' ),
			'logo_url'    => '',
			'from_email'  => '',
			'from_name'   => '',
			/*
			 * Seller details printed on the invoice. Defaults come from the
			 * WooCommerce store address so an existing shop needs no setup;
			 * every one of them can be overridden below.
			 */
			'store_address' => self::default_store_address(),
			'store_phone'   => '',
			'store_postal'  => (string) get_option( 'woocommerce_store_postcode', '' ),
			'store_email'   => (string) get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) ),
			'stamp_url'     => '',
			/* White-label switches — off by default. */
			'rebrand'        => 'no',
			'no_block_editor' => 'no',
		);
	}

	/**
	 * WooCommerce store address as plain text lines.
	 *
	 * @return string
	 */
	public static function default_store_address() {
		$parts = array_filter(
			array(
				(string) get_option( 'woocommerce_store_address', '' ),
				(string) get_option( 'woocommerce_store_address_2', '' ),
				(string) get_option( 'woocommerce_store_city', '' ),
				(string) get_option( 'woocommerce_store_postcode', '' ),
			)
		);

		return implode( '، ', $parts );
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

		/*
		 * The branding screen promises the theme logo is used when no custom
		 * logo is selected. `defaults()` only fills that in on a fresh option;
		 * saving the form with an empty logo stores `logo_id = 0`, which would
		 * otherwise erase the theme logo until the option is deleted.
		 */
		$theme_logo = (int) get_theme_mod( 'custom_logo' );
		if ( $theme_logo ) {
			$url = wp_get_attachment_image_url( $theme_logo, $size );
			if ( $url ) {
				return $url;
			}
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

		/* Same shell as the palette screen: local CSS, local font, no CDN. */
		wp_enqueue_style(
			'hsw-admin-ui',
			HSW_URL . 'assets/css/hsw-admin-ui.css',
			array(),
			(string) @filemtime( HSW_DIR . 'assets/css/hsw-admin-ui.css' )
		);
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

	/**
	 * Form action for this screen.
	 *
	 * With the custom dashboard slug the guard rewrites the request to
	 * /wp-admin/…, and a POST there is redirected and lost. Posting to the
	 * public slug path keeps the save working in both setups.
	 *
	 * @param string $page Page slug.
	 * @return string
	 */
	protected static function form_action( $page ) {
		if ( class_exists( 'HSW_Security' ) ) {
			$security = HSW_Security::get();
			$slug     = isset( $security['admin_slug'] ) ? trim( (string) $security['admin_slug'] ) : '';
			if ( '' !== $slug ) {
				return home_url( '/' . $slug . '/admin.php?page=' . $page );
			}
		}
		return '';
	}

	public function maybe_save() {
		if ( ! isset( $_POST['hsw_brand_nonce'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! check_admin_referer( 'hsw_save_brand', 'hsw_brand_nonce' ) ) {
			return;
		}

		if ( isset( $_POST['hsw_brand_reset'] ) ) {
			delete_option( self::OPTION );
			add_settings_error( 'hsw_brand', 'reset', __( 'برند به مقدار پیش‌فرض بازگشت.', 'hamishop-elementor-widgets' ), 'updated' );
			return;
		}

		$in = isset( $_POST['hsw_brand'] ) ? (array) wp_unslash( $_POST['hsw_brand'] ) : array();

		update_option(
			self::OPTION,
			array(
				'name'       => sanitize_text_field( $in['name'] ?? '' ),
				'tagline'    => sanitize_text_field( $in['tagline'] ?? '' ),
				'logo_id'    => absint( $in['logo_id'] ?? 0 ),
				'logo_url'   => esc_url_raw( $in['logo_url'] ?? '' ),
				'from_email' => sanitize_email( $in['from_email'] ?? '' ),
				'from_name'  => sanitize_text_field( $in['from_name'] ?? '' ),
				'store_address' => sanitize_textarea_field( $in['store_address'] ?? '' ),
				'store_phone'   => sanitize_text_field( $in['store_phone'] ?? '' ),
				'store_postal'  => preg_replace( '/\D/', '', (string) ( $in['store_postal'] ?? '' ) ),
				'store_email'   => sanitize_email( $in['store_email'] ?? '' ),
				'stamp_url'     => esc_url_raw( $in['stamp_url'] ?? '' ),
				'rebrand'        => ( 'yes' === ( $in['rebrand'] ?? '' ) ) ? 'yes' : 'no',
				'no_block_editor' => ( 'yes' === ( $in['no_block_editor'] ?? '' ) ) ? 'yes' : 'no',
			),
			false
		);

		add_settings_error( 'hsw_brand', 'saved', __( 'برند ذخیره شد.', 'hamishop-elementor-widgets' ), 'updated' );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$b    = self::get();
		$url  = self::logo_url( 'medium' );
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		?>
		<div class="wrap hsw-pal" id="hsw-brand-root">
			<div class="hsw-pal__head">
				<div>
					<h1 class="hsw-pal__title"><?php esc_html_e( 'برند و لوگو', 'hamishop-elementor-widgets' ); ?></h1>
					<p class="hsw-pal__sub"><?php esc_html_e( 'نام و لوگوی فروشگاه شما در پنل حساب کاربری، صفحهٔ ورود و اعلان‌های داخل افزونه استفاده می‌شود. اگر خالی بگذارید، نام و لوگوی خود قالب وردپرس نمایش داده می‌شود.', 'hamishop-elementor-widgets' ); ?></p>
				</div>
				<div class="hsw-pal__meta">
					<span class="hsw-pal__pill"><?php esc_html_e( 'فعال در پنل کاربری', 'hamishop-elementor-widgets' ); ?></span>
				</div>
			</div>

			<?php settings_errors( 'hsw_brand' ); ?>

			<form method="post" action="<?php echo esc_url( self::form_action( 'hamishop-brand' ) ); ?>">
				<?php wp_nonce_field( 'hsw_save_brand', 'hsw_brand_nonce' ); ?>

				<div class="hsw-pal__grid">
					<div style="display:flex;flex-direction:column;gap:18px">

						<div class="hsw-pal__card">
							<div class="hsw-pal__card-head">
								<h2><span class="dashicons dashicons-badge" aria-hidden="true"></span><?php esc_html_e( 'هویت و نماد بصری برند', 'hamishop-elementor-widgets' ); ?></h2>
							</div>

							<div class="hsw-pal__rows">
								<div class="hsw-pal__row">
									<div class="hsw-pal__label">
										<strong><?php esc_html_e( 'نام برند', 'hamishop-elementor-widgets' ); ?></strong>
										<small><?php esc_html_e( 'عنوان اصلی کسب‌وکار که در هدر پنل کاربری، پیامک‌ها و پیش‌فاکتورها ظاهر می‌شود.', 'hamishop-elementor-widgets' ); ?></small>
									</div>
									<div class="hsw-pal__field">
										<input class="hsw-pal__input" type="text" id="hsw-brand-name" name="hsw_brand[name]"
											value="<?php echo esc_attr( $b['name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
									</div>
								</div>

								<div class="hsw-pal__row">
									<div class="hsw-pal__label">
										<strong><?php esc_html_e( 'شعار کوتاه', 'hamishop-elementor-widgets' ); ?></strong>
										<small><?php esc_html_e( 'عبارت تکمیلی یا پیام خوش‌آمدگویی زیر عنوان فروشگاه در صفحهٔ ورود و ثبت‌نام.', 'hamishop-elementor-widgets' ); ?></small>
									</div>
									<div class="hsw-pal__field">
										<input class="hsw-pal__input" type="text" id="hsw-brand-tagline" name="hsw_brand[tagline]"
											value="<?php echo esc_attr( $b['tagline'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'description' ) ); ?>" />
									</div>
								</div>

								<div class="hsw-pal__row">
									<div class="hsw-pal__label">
										<strong><?php esc_html_e( 'لوگو', 'hamishop-elementor-widgets' ); ?></strong>
										<small><?php esc_html_e( 'اگر لوگویی انتخاب نکنید، لوگوی سفارشی قالب استفاده می‌شود.', 'hamishop-elementor-widgets' ); ?></small>
									</div>
									<div class="hsw-pal__field">
										<div class="hsw-brand__logo">
											<input type="hidden" id="hsw-brand-logo-id" name="hsw_brand[logo_id]" value="<?php echo esc_attr( (int) $b['logo_id'] ); ?>" />
											<div id="hsw-brand-preview" class="hsw-brand__preview<?php echo $url ? '' : ' is-empty'; ?>">
												<?php if ( $url ) : ?>
													<img src="<?php echo esc_url( $url ); ?>" alt="" />
												<?php else : ?>
													<span class="hsw-brand__preview-empty"><?php esc_html_e( 'لوگویی انتخاب نشده است', 'hamishop-elementor-widgets' ); ?></span>
												<?php endif; ?>
											</div>
											<div class="hsw-brand__actions">
												<button type="button" class="button" id="hsw-brand-pick">
													<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
													<?php esc_html_e( 'انتخاب از کتابخانه', 'hamishop-elementor-widgets' ); ?>
												</button>
												<button type="button" class="button button-link-delete" id="hsw-brand-clear">
													<span class="dashicons dashicons-trash" aria-hidden="true"></span>
													<?php esc_html_e( 'حذف', 'hamishop-elementor-widgets' ); ?>
												</button>
											</div>
										</div>
									</div>
								</div>

								<div class="hsw-pal__row">
									<div class="hsw-pal__label">
										<strong><?php esc_html_e( 'یا آدرس تصویر لوگو', 'hamishop-elementor-widgets' ); ?></strong>
										<small><?php esc_html_e( 'فقط وقتی استفاده می‌شود که لوگویی از کتابخانه انتخاب نشده باشد.', 'hamishop-elementor-widgets' ); ?></small>
									</div>
									<div class="hsw-pal__field">
										<input class="hsw-pal__input" type="url" id="hsw-brand-logo-url" name="hsw_brand[logo_url]"
											value="<?php echo esc_attr( $b['logo_url'] ); ?>" placeholder="https://" dir="ltr" />
									</div>
								</div>
							</div>
						</div>

						<div class="hsw-pal__card">
							<div class="hsw-pal__card-head">
								<h2><span class="dashicons dashicons-email" aria-hidden="true"></span><?php esc_html_e( 'ایمیل فرستنده', 'hamishop-elementor-widgets' ); ?></h2>
								<span class="hsw-pal__count"><?php esc_html_e( 'اثر بر همهٔ ایمیل‌ها', 'hamishop-elementor-widgets' ); ?></span>
							</div>

							<div class="hsw-pal__rows">
								<div class="hsw-pal__row" style="display:block">
									<p class="hsw-pal__sub" style="max-width:none">
										<?php
										printf(
											/* translators: %s: current from address. */
											esc_html__( 'آدرس و نامی که وردپرس با آن ایمیل می‌فرستد. به‌صورت پیش‌فرض %s است که بسیاری از سرورها آن را اسپم می‌کنند؛ اینجا آدرس واقعی فروشگاه را بگذارید. این تنظیم روی همهٔ ایمیل‌های سایت اثر دارد (سفارش، بازیابی رمز، کد تأیید ایمیل).', 'hamishop-elementor-widgets' ),
											'<code dir="ltr">wordpress@' . esc_html( $host ) . '</code>'
										);
										?>
									</p>
								</div>

								<div class="hsw-pal__row">
									<div class="hsw-pal__label">
										<strong><?php esc_html_e( 'آدرس فرستنده', 'hamishop-elementor-widgets' ); ?></strong>
										<small><?php esc_html_e( 'خالی بگذارید تا مقدار پیش‌فرض وردپرس استفاده شود؛ برای تحویل بهتر باید روی دامنهٔ همین سایت باشد.', 'hamishop-elementor-widgets' ); ?></small>
									</div>
									<div class="hsw-pal__field">
										<input class="hsw-pal__input" type="email" id="hsw-brand-from-email" name="hsw_brand[from_email]"
											value="<?php echo esc_attr( $b['from_email'] ); ?>" placeholder="no-reply@<?php echo esc_attr( $host ); ?>" dir="ltr" />
									</div>
								</div>

								<div class="hsw-pal__row">
									<div class="hsw-pal__label">
										<strong><?php esc_html_e( 'نام فرستنده', 'hamishop-elementor-widgets' ); ?></strong>
										<small><?php esc_html_e( 'خالی بگذارید تا نام برند بالا استفاده شود.', 'hamishop-elementor-widgets' ); ?></small>
									</div>
									<div class="hsw-pal__field">
										<input class="hsw-pal__input" type="text" id="hsw-brand-from-name" name="hsw_brand[from_name]"
											value="<?php echo esc_attr( $b['from_name'] ); ?>" placeholder="<?php echo esc_attr( self::name() ); ?>" />
									</div>
								</div>
							</div>
						</div>

						<div class="hsw-pal__card">
							<div class="hsw-pal__card-head">
								<h2><span class="dashicons dashicons-media-text" aria-hidden="true"></span><?php esc_html_e( 'اطلاعات فروشنده (فاکتور)', 'hamishop-elementor-widgets' ); ?></h2>
								<span class="hsw-pal__count"><?php esc_html_e( 'قالب فاکتور رسمی', 'hamishop-elementor-widgets' ); ?></span>
							</div>

							<div class="hsw-pal__rows">
								<div class="hsw-pal__row" style="display:block">
									<p class="hsw-pal__sub" style="max-width:none">
										<?php esc_html_e( 'این اطلاعات در ستون «فروشنده» فاکتور چاپی چاپ می‌شود. اگر خالی بگذارید، نشانی از تنظیمات ووکامرس و ایمیل از آدرس فرستندهٔ فروشگاه خوانده می‌شود.', 'hamishop-elementor-widgets' ); ?>
									</p>
								</div>

								<div class="hsw-pal__row">
									<div class="hsw-pal__label">
										<strong><?php esc_html_e( 'نشانی فروشگاه', 'hamishop-elementor-widgets' ); ?></strong>
										<small><?php esc_html_e( 'آدرس دقیق ثبتی، کد پستی و مشخصات دفتر مرکزی برای سربرگ فاکتور.', 'hamishop-elementor-widgets' ); ?></small>
									</div>
									<div class="hsw-pal__field">
										<textarea class="hsw-pal__textarea" rows="3" id="hsw-brand-store-address" name="hsw_brand[store_address]" placeholder="<?php echo esc_attr( self::default_store_address() ); ?>"><?php echo esc_textarea( $b['store_address'] ); ?></textarea>
									</div>
								</div>

								<div class="hsw-pal__row">
									<div class="hsw-pal__label">
										<strong><?php esc_html_e( 'تلفن فروشگاه', 'hamishop-elementor-widgets' ); ?></strong>
										<small><?php esc_html_e( 'شماره‌های تماس اداری یا پشتیبانی مشتریان روی فاکتورها.', 'hamishop-elementor-widgets' ); ?></small>
									</div>
									<div class="hsw-pal__field">
										<input class="hsw-pal__input" type="text" id="hsw-brand-store-phone" name="hsw_brand[store_phone]"
											value="<?php echo esc_attr( $b['store_phone'] ); ?>" dir="ltr" placeholder="021-00000000" />
									</div>
								</div>

								<div class="hsw-pal__row">
									<div class="hsw-pal__label">
										<strong><?php esc_html_e( 'کد پستی فروشگاه', 'hamishop-elementor-widgets' ); ?></strong>
										<small><?php esc_html_e( 'در «فرستنده»ی برچسب پستی فاکتور چاپ می‌شود. پیش‌فرض از کد پستی فروشگاه ووکامرس خوانده می‌شود.', 'hamishop-elementor-widgets' ); ?></small>
									</div>
									<div class="hsw-pal__field">
										<input class="hsw-pal__input" type="text" id="hsw-brand-store-postal" name="hsw_brand[store_postal]"
											value="<?php echo esc_attr( $b['store_postal'] ); ?>" dir="ltr" inputmode="numeric" maxlength="10" placeholder="1234567890" />
									</div>
								</div>

								<div class="hsw-pal__row">
									<div class="hsw-pal__label">
										<strong><?php esc_html_e( 'ایمیل فروشگاه', 'hamishop-elementor-widgets' ); ?></strong>
										<small><?php esc_html_e( 'پست الکترونیکی امور مالی برای پرسش‌های حسابداری خریداران.', 'hamishop-elementor-widgets' ); ?></small>
									</div>
									<div class="hsw-pal__field">
										<input class="hsw-pal__input" type="email" id="hsw-brand-store-email" name="hsw_brand[store_email]"
											value="<?php echo esc_attr( $b['store_email'] ); ?>" dir="ltr" />
									</div>
								</div>

								<div class="hsw-pal__row">
									<div class="hsw-pal__label">
										<strong><?php esc_html_e( 'تصویر مهر فروشگاه', 'hamishop-elementor-widgets' ); ?></strong>
										<small><?php esc_html_e( 'اختیاری. اگر خالی باشد، جای مهر در فاکتور خالی می‌ماند.', 'hamishop-elementor-widgets' ); ?></small>
									</div>
									<div class="hsw-pal__field">
										<input class="hsw-pal__input" type="url" id="hsw-brand-stamp" name="hsw_brand[stamp_url]"
											value="<?php echo esc_attr( $b['stamp_url'] ); ?>" placeholder="https://" dir="ltr" />
									</div>
								</div>
							</div>
						</div>
						<div class="hsw-pal__card">
							<div class="hsw-pal__card-head">
								<h2><span class="dashicons dashicons-translation" aria-hidden="true"></span><?php esc_html_e( 'تنظیمات نام حامی', 'hamishop-elementor-widgets' ); ?></h2>
								<span class="hsw-pal__count"><?php esc_html_e( 'اختیاری', 'hamishop-elementor-widgets' ); ?></span>
							</div>

							<div class="hsw-pal__rows">
								<div class="hsw-pal__row">
									<div class="hsw-pal__label">
										<strong><?php esc_html_e( 'تغییر نام‌ها به نام فروشگاه', 'hamishop-elementor-widgets' ); ?></strong>
										<small><?php esc_html_e( 'در متن‌های ترجمه‌شده، «ووکامرس» به «فروشگاه» و در پیشخوان «وردپرس» به «پنل مدیریت» تبدیل می‌شود.', 'hamishop-elementor-widgets' ); ?></small>
									</div>
									<div class="hsw-pal__field">
										<label class="hsw-brand__switch">
											<input type="hidden" name="hsw_brand[rebrand]" value="no" />
											<input type="checkbox" name="hsw_brand[rebrand]" value="yes" <?php checked( 'yes', $b['rebrand'] ); ?> />
											<span class="hsw-brand__track" aria-hidden="true"></span>
										</label>
									</div>
								</div>

								<div class="hsw-pal__row">
									<div class="hsw-pal__label">
										<strong><?php esc_html_e( 'غیرفعال‌کردن ویرایشگر بلوک (گوتنبرگ)', 'hamishop-elementor-widgets' ); ?></strong>
										<small><?php esc_html_e( 'نوشته‌ها، برگه‌ها و محصولات با ویرایشگر کلاسیک باز می‌شوند. ویرایشگر المنتور مستقل است و دست‌نخورده می‌ماند.', 'hamishop-elementor-widgets' ); ?></small>
									</div>
									<div class="hsw-pal__field">
										<label class="hsw-brand__switch">
											<input type="hidden" name="hsw_brand[no_block_editor]" value="no" />
											<input type="checkbox" name="hsw_brand[no_block_editor]" value="yes" <?php checked( 'yes', $b['no_block_editor'] ); ?> />
											<span class="hsw-brand__track" aria-hidden="true"></span>
										</label>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="hsw-pal__side">
						<div class="hsw-pal__card">
							<div class="hsw-pal__card-head">
								<h2><span class="dashicons dashicons-visibility" aria-hidden="true"></span><?php esc_html_e( 'پیش‌نمایش برند', 'hamishop-elementor-widgets' ); ?></h2>
								<span class="hsw-pal__count"><?php esc_html_e( 'نمایشی', 'hamishop-elementor-widgets' ); ?></span>
							</div>
							<div class="hsw-pal__live">
								<p class="hsw-pl-caption"><?php esc_html_e( 'جایی که برند دیده می‌شود', 'hamishop-elementor-widgets' ); ?></p>
								<div class="hsw-pl-card" style="gap:10px">
									<div style="display:flex;align-items:center;gap:10px">
										<span class="hsw-brand__mark" id="hsw-brand-mark">
											<?php if ( $url ) : ?>
												<img src="<?php echo esc_url( $url ); ?>" alt="" />
											<?php else : ?>
												<span class="dashicons dashicons-store"></span>
											<?php endif; ?>
										</span>
										<span style="display:flex;flex-direction:column;gap:2px;min-width:0">
											<strong id="hsw-brand-mark-name" style="font-size:12.5px;color:var(--pal-text);line-height:1.7"><?php echo esc_html( self::name() ); ?></strong>
											<small id="hsw-brand-mark-tag" style="font-size:10.5px;color:var(--pal-muted)"><?php echo esc_html( self::tagline() ); ?></small>
										</span>
									</div>
									<div class="hsw-pl-divider"></div>
									<p class="hsw-pl-caption" style="margin:0">
										<?php esc_html_e( 'در صفحهٔ ورود و پنل حساب کاربری همین نام و لوگو نمایش داده می‌شود. تغییرات پس از ذخیره اعمال می‌شوند.', 'hamishop-elementor-widgets' ); ?>
									</p>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="hsw-pal__actions hsw-pal__actions--sticky">
					<div style="display:flex;gap:10px;align-items:center">
						<?php submit_button( __( 'ذخیره برند', 'hamishop-elementor-widgets' ), 'primary', 'submit', false ); ?>
						<button type="submit" name="hsw_brand_reset" value="1" class="button button-secondary"
							onclick="return confirm('<?php echo esc_js( __( 'برند به پیش‌فرض بازگردد؟', 'hamishop-elementor-widgets' ) ); ?>');">
							<?php esc_html_e( 'بازنشانی به پیش‌فرض', 'hamishop-elementor-widgets' ); ?>
						</button>
					</div>
					<span class="hsw-pal__hint"><?php esc_html_e( 'خالی گذاشتن هر فیلد یعنی استفاده از مقدار پیش‌فرض وردپرس/قالب.', 'hamishop-elementor-widgets' ); ?></span>
				</div>
			</form>
		</div>
		<?php
	}
}
