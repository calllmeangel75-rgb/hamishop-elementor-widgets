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
			'cta'          => array( __( 'CTA مراحل خرید', 'hamishop-elementor-widgets' ), '#0f2042', __( 'دکمه‌های «ادامه و پرداخت» در سبد خرید، تسویه حساب و پرداخت', 'hamishop-elementor-widgets' ) ),
			'cta_hover'    => array( __( 'CTA مراحل خرید (هاور)', 'hamishop-elementor-widgets' ), '#1c3566', __( 'رنگ دکمه‌های خرید هنگام نگه‌داشتن ماوس', 'hamishop-elementor-widgets' ) ),
			'cta_active'   => array( __( 'CTA مراحل خرید (کلیک)', 'hamishop-elementor-widgets' ), '#0a1730', __( 'رنگ دکمه‌های خرید در لحظهٔ کلیک', 'hamishop-elementor-widgets' ) ),
			'text'         => array( __( 'رنگ متن', 'hamishop-elementor-widgets' ), '#191c1e', __( 'متن اصلی', 'hamishop-elementor-widgets' ) ),
			'muted'        => array( __( 'متن کم‌رنگ', 'hamishop-elementor-widgets' ), '#75777f', __( 'توضیحات، متادیتا', 'hamishop-elementor-widgets' ) ),
			'border'       => array( __( 'رنگ حاشیه', 'hamishop-elementor-widgets' ), '#e1e2e4', __( 'خطوط و کادرها', 'hamishop-elementor-widgets' ) ),
			'surface'      => array( __( 'پس‌زمینه کارت', 'hamishop-elementor-widgets' ), '#ffffff', __( 'کارت‌ها و باکس‌ها', 'hamishop-elementor-widgets' ) ),
			'surface_alt'  => array( __( 'پس‌زمینه ملایم', 'hamishop-elementor-widgets' ), '#f3f4f6', __( 'نوارها و بخش‌های فرورفته', 'hamishop-elementor-widgets' ) ),
			'success'      => array( __( 'موفق / موجود', 'hamishop-elementor-widgets' ), '#0f9f6e', '' ),
			'warning'      => array( __( 'هشدار / امتیاز', 'hamishop-elementor-widgets' ), '#b45309', '' ),
			'danger'       => array( __( 'خطا / ناموجود', 'hamishop-elementor-widgets' ), '#ba1a1a', '' ),
			/*
			 * The account panel used to keep its own two hero colours; they live
			 * here now so one palette paints the storefront *and* the account.
			 */
			'header_icon_color' => array( __( 'رنگ آیکون دکمه‌های هدر', 'hamishop-elementor-widgets' ), '#0f172a', __( 'سبد خرید، حساب کاربری و منوی همبرگری', 'hamishop-elementor-widgets' ) ),
			'header_icon_bg'    => array( __( 'پس‌زمینه دکمه‌های هدر', 'hamishop-elementor-widgets' ), '#f8fafc', __( 'دکمه‌های گرد سبد خرید، حساب کاربری و منو', 'hamishop-elementor-widgets' ) ),
			'hero_a'       => array( __( 'گرادیان هدر حساب (شروع)', 'hamishop-elementor-widgets' ), '#0b2036', __( 'رنگ اول گرادیان هدر پنل حساب کاربری', 'hamishop-elementor-widgets' ) ),
			'hero_b'       => array( __( 'گرادیان هدر حساب (پایان)', 'hamishop-elementor-widgets' ), '#173b5c', __( 'رنگ دوم گرادیان هدر پنل حساب کاربری', 'hamishop-elementor-widgets' ) ),
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
		/* Global font family — the plugin's Vazirmatn by default. */
		$out['font'] = 'Vazirmatn HSW';
		return $out;
	}

	/**
	 * Font families the shop owner can pick: the plugin's own Vazirmatn plus
	 * whatever the theme/Elementor already loaded (block theme font library),
	 * and anything added through the `hsw_font_choices` filter.
	 *
	 * @return array<string,string>
	 */
	/**
	 * Turn the saved family into a valid CSS value: a stack stays as-is, a bare
	 * name with spaces gets quoted (`Vazirmatn HSW` → `"Vazirmatn HSW"`).
	 *
	 * @param string $font Saved value.
	 * @return string
	 */
	public static function font_css( $font ) {
		$font = trim( (string) $font );
		if ( '' === $font ) {
			return 'inherit';
		}
		if ( false !== strpos( $font, ',' ) || '"' === $font[0] || "'" === $font[0] ) {
			return $font;
		}
		return '"' . $font . '"';
	}

	public static function font_choices() {
		$choices = array(
			''              => __( 'پیش‌فرض قالب', 'hamishop-elementor-widgets' ),
			'Vazirmatn HSW' => __( 'وزیرمتن (افزونه)', 'hamishop-elementor-widgets' ),
		);

		if ( function_exists( 'wp_get_global_settings' ) ) {
			$settings = wp_get_global_settings();
			$families = $settings['typography']['fontFamilies']['theme'] ?? array();
			foreach ( (array) $families as $family ) {
				$name = is_array( $family ) ? ( $family['fontFamily'] ?? '' ) : (string) $family;
				$slug = is_array( $family ) ? ( $family['name'] ?? $name ) : $name;
				$name = trim( (string) $name );
				if ( '' !== $name ) {
					$choices[ $name ] = (string) $slug;
				}
			}
		}

		/*
		 * Elementor keeps its uploaded fonts in this option (each entry has a
		 * `name`), and they are NOT part of wp_get_global_settings().
		 */
		$elementor = get_option( 'elementor_fonts_manager_fonts', array() );
		if ( is_array( $elementor ) ) {
			foreach ( $elementor as $font ) {
				$name = is_array( $font ) ? ( $font['name'] ?? '' ) : (string) $font;
				$name = trim( (string) $name );
				if ( '' !== $name && ! isset( $choices[ $name ] ) ) {
					$choices[ $name ] = $name;
				}
			}
		}

		/* Elementor kit typography (custom + system families). */
		$kit_id = (int) get_option( 'elementor_active_kit' );
		if ( $kit_id ) {
			$kit = get_post_meta( $kit_id, '_elementor_page_settings', true );
			$families = is_array( $kit ) ? ( $kit['system_typography'] ?? array() ) : array();
			foreach ( (array) $families as $row ) {
				$name = is_array( $row ) ? ( $row['typography_font_family'] ?? '' ) : '';
				$name = trim( (string) $name );
				if ( '' !== $name && ! isset( $choices[ $name ] ) ) {
					$choices[ $name ] = $name;
				}
			}
		}

		return (array) apply_filters( 'hsw_font_choices', $choices );
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

		/* The font is a family name, not a colour — keep it out of the hex loop. */
		if ( isset( $saved['font'] ) && is_string( $saved['font'] ) ) {
			$out['font'] = sanitize_text_field( $saved['font'] );
		}

		foreach ( $out as $key => $default ) {
			if ( 'font' === $key ) {
				continue;
			}
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

			/* Purchase-step CTAs: one colour for cart → checkout → pay. */
			'--hsw-cta'                => $p['cta'],
			'--hsw-cta-hover'          => $p['cta_hover'],
			'--hsw-cta-active'         => $p['cta_active'],
			'--hsw-on-cta'             => '#ffffff',

			'--hsw-pal-surface'        => $p['surface'],
			'--hsw-pal-surface-alt'    => $p['surface_alt'],
			'--hsw-pal-border'         => $p['border'],
			'--hsw-pal-muted'          => $p['muted'],
			'--hsw-pal-success'        => $p['success'],
			'--hsw-pal-warning'        => $p['warning'],
			'--hsw-pal-danger'         => $p['danger'],

			/* Functional header buttons (cart / account / burger). */
			'--hsw-hd-act-icon'        => $p['header_icon_color'],
			'--hsw-hd-act-bg'          => $p['header_icon_bg'],

			/* Derived tints so families do not need their own maths. */
			'--hsw-pal-primary-50'     => 'color-mix(in srgb, ' . $p['primary'] . ', #fff 92%)',
			'--hsw-pal-primary-100'    => 'color-mix(in srgb, ' . $p['primary'] . ', #fff 84%)',
			'--hsw-pal-primary-light'  => 'color-mix(in srgb, ' . $p['primary'] . ', #fff 94%)',
			'--hsw-pal-primary-ring'   => 'color-mix(in srgb, ' . $p['primary'] . ', transparent 72%)',
			'--hsw-pal-accent-soft'    => 'color-mix(in srgb, ' . $p['accent'] . ', #fff 86%)',

			/* Global font for every HamiShop widget. */
			'--hsw-font'               => self::font_css( $p['font'] ?? '' ),
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
		/*
		 * Native <input type="color"> instead of iris: the picker is the OS's
		 * own, so this page loads no extra script and no popover to fight.
		 * One local stylesheet, one local font file — no CDN, no framework.
		 */
		wp_enqueue_style(
			'hsw-admin-ui',
			HSW_URL . 'assets/css/hsw-admin-ui.css',
			array(),
			(string) @filemtime( HSW_DIR . 'assets/css/hsw-admin-ui.css' )
		);
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
			/* token key → CSS custom property used by the preview column */
			var MAP = {
				primary: '--pal-primary', primary_dark: '--pal-primary-dark', accent: '--pal-accent',
				hover: '--pal-hover', cta: '--pal-cta', cta_hover: '--pal-cta-hover',
				cta_active: '--pal-cta-active', text: '--pal-text', muted: '--pal-muted',
				border: '--pal-border', surface: '--pal-surface', surface_alt: '--pal-surface-alt',
				success: '--pal-success', warning: '--pal-warning', danger: '--pal-danger',
				header_icon_color: '--pal-hic', header_icon_bg: '--pal-hib',
				hero_a: '--pal-hero-a', hero_b: '--pal-hero-b'
			};

			var root = document.getElementById( 'hsw-pal-root' );
			if ( ! root ) { return; }

			function hex( value ) {
				value = ( value || '' ).trim();
				return /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test( value ) ? value : '';
			}

			function paint( key, value ) {
				if ( ! MAP[ key ] || ! hex( value ) ) { return; }
				root.style.setProperty( MAP[ key ], value );
				var chip = document.querySelector( '[data-chip="' + key + '"]' );
				if ( chip ) { chip.style.background = value; }
			}

			function syncFromText( input ) {
				var key = input.getAttribute( 'data-key' );
				var value = hex( input.value );
				if ( ! value ) { return; }
				paint( key, value );
				var pick = document.querySelector( '[data-pick="' + key + '"]' );
				if ( pick ) { pick.value = value; }
			}

			document.addEventListener( 'input', function ( e ) {
				var el = e.target;
				if ( ! el || ! el.classList ) { return; }

				if ( el.classList.contains( 'hsw-color-field' ) ) {
					syncFromText( el );
					return;
				}

				if ( el.classList.contains( 'hsw-pal__pick' ) ) {
					var key = el.getAttribute( 'data-pick' );
					var field = document.querySelector( '.hsw-color-field[data-key="' + key + '"]' );
					if ( field ) { field.value = el.value; }
					paint( key, el.value );
				}
			} );

			/* Typing a short hex (#abc) only resolves on blur/Enter. */
			document.addEventListener( 'change', function ( e ) {
				if ( e.target && e.target.classList && e.target.classList.contains( 'hsw-color-field' ) ) {
					syncFromText( e.target );
				}
			} );

			document.querySelectorAll( '.hsw-color-field' ).forEach( syncFromText );
		} )();
		</script>
		<?php
	}

	/**
	 * Persist the palette (nonce + capability checked).
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
			$defaults            = self::defaults();
			$defaults['_saved']  = time();
			update_option( self::OPTION, $defaults );
			add_settings_error( 'hsw_palette', 'reset', __( 'پالت به مقدار پیش‌فرض بازگشت.', 'hamishop-elementor-widgets' ), 'updated' );
			return;
		}

		$incoming = isset( $_POST['hsw_palette'] ) && is_array( $_POST['hsw_palette'] ) ? wp_unslash( $_POST['hsw_palette'] ) : array();

		foreach ( self::defaults() as $key => $default ) {
			$value = isset( $incoming[ $key ] ) ? sanitize_hex_color( (string) $incoming[ $key ] ) : '';
			$clean[ $key ] = $value ? $value : $default;
		}

		$clean['font']   = isset( $incoming['font'] ) ? sanitize_text_field( (string) $incoming['font'] ) : '';
		$clean['_saved'] = time();
		update_option( self::OPTION, $clean );
		add_settings_error( 'hsw_palette', 'saved', __( 'پالت رنگی ذخیره شد.', 'hamishop-elementor-widgets' ), 'updated' );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$p     = self::get();
		$raw   = get_option( self::OPTION, array() );
		$saved = ( is_array( $raw ) && isset( $raw['_saved'] ) ) ? (int) $raw['_saved'] : 0;

		$groups = array(
			__( 'برند و تعامل', 'hamishop-elementor-widgets' )        => array( 'primary', 'primary_dark', 'accent', 'hover' ),
			__( 'مراحل خرید', 'hamishop-elementor-widgets' )          => array( 'cta', 'cta_hover', 'cta_active' ),
			__( 'متن و سطوح', 'hamishop-elementor-widgets' )          => array( 'text', 'muted', 'border', 'surface', 'surface_alt' ),
			__( 'وضعیت‌ها', 'hamishop-elementor-widgets' )            => array( 'success', 'warning', 'danger' ),
			__( 'هدر و پنل حساب', 'hamishop-elementor-widgets' )      => array( 'header_icon_color', 'header_icon_bg', 'hero_a', 'hero_b' ),
		);
		$tokens = self::tokens();
		?>
		<div class="wrap hsw-pal" id="hsw-pal-root">
			<div class="hsw-pal__head">
				<div>
					<h1 class="hsw-pal__title"><?php esc_html_e( 'پالت رنگی HamiShop', 'hamishop-elementor-widgets' ); ?></h1>
					<p class="hsw-pal__sub"><?php esc_html_e( 'این رنگ‌ها به همهٔ ویجت‌های HamiShop اعمال می‌شوند. اگر برای یک ویجت خاص رنگ جداگانه‌ای در تنظیمات المنتور انتخاب کرده باشید، همان رنگ اولویت دارد.', 'hamishop-elementor-widgets' ); ?></p>
				</div>
				<div class="hsw-pal__meta">
					<span class="hsw-pal__pill"><?php esc_html_e( 'سازگار با قالب و ویجت‌های المنتور', 'hamishop-elementor-widgets' ); ?></span>
					<?php if ( $saved ) : ?>
						<span class="hsw-pal__saved">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: date/time. */
									__( 'آخرین ذخیره‌سازی: %s', 'hamishop-elementor-widgets' ),
									date_i18n( 'j F Y — H:i', $saved )
								)
							);
							?>
						</span>
					<?php endif; ?>
				</div>
			</div>

			<?php settings_errors( 'hsw_palette' ); ?>

			<div class="hsw-pal__note">
				<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				<div>
					<p><?php esc_html_e( 'هر رنگ را از دکمهٔ گزینش رنگ یا با وارد کردن کد HEX تغییر دهید؛ ستون کنار، همان لحظه نشان می‌دهد هر رنگ کجای سایت استفاده می‌شود.', 'hamishop-elementor-widgets' ); ?></p>
					<p><?php esc_html_e( 'پیش‌نمایش این صفحه فقط نمایشی است و هیچ تغییری در فروشگاه ایجاد نمی‌کند تا زمانی که «ذخیره پالت» را بزنید.', 'hamishop-elementor-widgets' ); ?></p>
				</div>
			</div>

			<form method="post" action="<?php echo esc_url( self::form_action( 'hamishop-palette' ) ); ?>">
				<?php wp_nonce_field( 'hsw_save_palette', 'hsw_palette_nonce' ); ?>

				<div class="hsw-pal__grid">
					<div class="hsw-pal__card">
						<div class="hsw-pal__card-head">
							<h2><span class="dashicons dashicons-art" aria-hidden="true"></span><?php esc_html_e( 'تنظیم متغیرهای رنگی', 'hamishop-elementor-widgets' ); ?></h2>
							<span class="hsw-pal__count"><?php echo esc_html( sprintf( __( '%s رنگ', 'hamishop-elementor-widgets' ), HSW_Helpers::fa_num( count( $tokens ) ) ) ); ?></span>
						</div>

						<div class="hsw-pal__rows">
							<div class="hsw-pal__row">
								<div class="hsw-pal__label">
									<strong><?php esc_html_e( 'فونت پیش‌فرض ویجت‌ها', 'hamishop-elementor-widgets' ); ?></strong>
									<small><?php esc_html_e( 'هر فونت که روی سایت بارگذاری شده باشد اینجا فهرست می‌شود و روی همهٔ ویجت‌های HamiShop اعمال می‌گردد. کنترل فونت هر ویجت، در صورت تنظیم، اولویت دارد.', 'hamishop-elementor-widgets' ); ?></small>
								</div>
								<div class="hsw-pal__field">
									<select class="hsw-pal__select" name="hsw_palette[font]">
										<?php foreach ( self::font_choices() as $family => $label ) : ?>
											<option value="<?php echo esc_attr( $family ); ?>" <?php selected( (string) ( $p['font'] ?? '' ), (string) $family ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
							</div>

							<?php foreach ( $tokens as $key => $def ) : ?>
								<?php list( $label, $default, $help ) = $def; ?>
								<div class="hsw-pal__row">
									<div class="hsw-pal__label">
										<strong><?php echo esc_html( $label ); ?></strong>
										<?php if ( '' !== $help ) : ?>
											<small><?php echo esc_html( $help ); ?></small>
										<?php endif; ?>
									</div>
									<div class="hsw-pal__field">
										<input
											type="color"
											class="hsw-pal__pick"
											value="<?php echo esc_attr( $p[ $key ] ); ?>"
											data-pick="<?php echo esc_attr( $key ); ?>"
											aria-label="<?php echo esc_attr( $label ); ?>"
										/>
										<input
											type="text"
											class="hsw-color-field"
											id="hsw-pal-<?php echo esc_attr( $key ); ?>"
											name="hsw_palette[<?php echo esc_attr( $key ); ?>]"
											value="<?php echo esc_attr( $p[ $key ] ); ?>"
											data-key="<?php echo esc_attr( $key ); ?>"
											data-default-color="<?php echo esc_attr( $default ); ?>"
											dir="ltr"
											spellcheck="false"
											autocomplete="off"
										/>
									</div>
								</div>
							<?php endforeach; ?>
						</div>

						<div class="hsw-pal__actions">
							<div style="display:flex;gap:10px;align-items:center">
								<?php submit_button( __( 'ذخیره پالت', 'hamishop-elementor-widgets' ), 'primary', 'submit', false ); ?>
								<button type="submit" name="hsw_reset" value="1" class="button button-secondary"
									onclick="return confirm('<?php echo esc_js( __( 'پالت به پیش‌فرض بازگردد؟', 'hamishop-elementor-widgets' ) ); ?>');">
									<?php esc_html_e( 'بازنشانی به پیش‌فرض', 'hamishop-elementor-widgets' ); ?>
								</button>
							</div>
							<span class="hsw-pal__hint"><?php esc_html_e( 'تغییرات پس از ذخیره، بلافاصله در فروشگاه اعمال می‌شود.', 'hamishop-elementor-widgets' ); ?></span>
						</div>
					</div>

					<div class="hsw-pal__side">
						<div class="hsw-pal__card">
							<div class="hsw-pal__card-head">
								<h2><span class="dashicons dashicons-grid-view" aria-hidden="true"></span><?php esc_html_e( 'هر رنگ کجا دیده می‌شود', 'hamishop-elementor-widgets' ); ?></h2>
							</div>
							<div class="hsw-pal__map">
								<?php foreach ( $groups as $group => $keys ) : ?>
									<div class="hsw-pal__group">
										<h3><?php echo esc_html( $group ); ?></h3>
										<div class="hsw-pal__chips">
											<?php foreach ( $keys as $key ) : ?>
												<?php if ( ! isset( $tokens[ $key ] ) ) : ?>
													<?php continue; ?>
												<?php endif; ?>
												<span class="hsw-pal__chip">
													<i data-chip="<?php echo esc_attr( $key ); ?>" style="background:<?php echo esc_attr( $p[ $key ] ); ?>"></i>
													<?php echo esc_html( $tokens[ $key ][0] ); ?>
												</span>
											<?php endforeach; ?>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="hsw-pal__card">
							<div class="hsw-pal__card-head">
								<h2><span class="dashicons dashicons-visibility" aria-hidden="true"></span><?php esc_html_e( 'پیش‌نمایش زنده', 'hamishop-elementor-widgets' ); ?></h2>
								<span class="hsw-pal__count"><?php esc_html_e( 'نمایشی', 'hamishop-elementor-widgets' ); ?></span>
							</div>

							<div class="hsw-pal__live">
								<p class="hsw-pl-caption"><?php esc_html_e( 'دکمه‌ها و لینک‌ها', 'hamishop-elementor-widgets' ); ?></p>
								<div style="display:flex;gap:8px;flex-wrap:wrap">
									<span class="hsw-pl-btn hsw-pl-btn--primary"><?php esc_html_e( 'دکمهٔ اصلی', 'hamishop-elementor-widgets' ); ?></span>
									<span class="hsw-pl-btn hsw-pl-btn--cta"><?php esc_html_e( 'ادامه و پرداخت', 'hamishop-elementor-widgets' ); ?></span>
									<span class="hsw-pl-btn hsw-pl-btn--ghost"><?php esc_html_e( 'دکمهٔ فرعی', 'hamishop-elementor-widgets' ); ?></span>
								</div>

								<div class="hsw-pl-divider"></div>

								<p class="hsw-pl-caption"><?php esc_html_e( 'تب‌های صفحهٔ محصول', 'hamishop-elementor-widgets' ); ?></p>
								<div class="hsw-pl-tabs">
									<span class="is-active"><?php esc_html_e( 'مشخصات', 'hamishop-elementor-widgets' ); ?></span>
									<span><?php esc_html_e( 'نظرات', 'hamishop-elementor-widgets' ); ?></span>
									<span><?php esc_html_e( 'ارسال', 'hamishop-elementor-widgets' ); ?></span>
								</div>

								<div class="hsw-pl-divider"></div>

								<p class="hsw-pl-caption"><?php esc_html_e( 'وضعیت‌ها و برچسب‌ها', 'hamishop-elementor-widgets' ); ?></p>
								<div class="hsw-pl-badges">
									<span class="hsw-pl-badge hsw-pl-badge--ok"><?php esc_html_e( 'موجود', 'hamishop-elementor-widgets' ); ?></span>
									<span class="hsw-pl-badge hsw-pl-badge--warn"><?php esc_html_e( '۳ روز مانده', 'hamishop-elementor-widgets' ); ?></span>
									<span class="hsw-pl-badge hsw-pl-badge--err"><?php esc_html_e( 'ناموجود', 'hamishop-elementor-widgets' ); ?></span>
									<span class="hsw-pl-badge hsw-pl-badge--accent"><?php esc_html_e( '۲۰٪ تخفیف', 'hamishop-elementor-widgets' ); ?></span>
								</div>

								<div class="hsw-pl-divider"></div>

								<p class="hsw-pl-caption"><?php esc_html_e( 'کارت محصول', 'hamishop-elementor-widgets' ); ?></p>
								<div class="hsw-pl-card">
									<div class="hsw-pl-card__media"></div>
									<span class="hsw-pl-card__name"><?php esc_html_e( 'دستگاه فشارسنج دیجیتال بازویی', 'hamishop-elementor-widgets' ); ?></span>
									<span class="hsw-pl-card__meta"><?php esc_html_e( 'گارانتی ۱۸ ماهه · ارسال سریع', 'hamishop-elementor-widgets' ); ?></span>
									<div class="hsw-pl-card__foot">
										<span class="hsw-pl-card__price"><del>۲,۸۵۰,۰۰۰</del>۲,۲۸۰,۰۰۰</span>
										<span class="hsw-pl-btn hsw-pl-btn--cta" style="height:30px;padding:0 10px"><?php esc_html_e( 'خرید', 'hamishop-elementor-widgets' ); ?></span>
									</div>
								</div>

								<div class="hsw-pl-divider"></div>

								<p class="hsw-pl-caption"><?php esc_html_e( 'آیکون‌های هدر و هدر پنل حساب', 'hamishop-elementor-widgets' ); ?></p>
								<div class="hsw-pl-icons">
									<span class="hsw-pl-icon"><span class="dashicons dashicons-search"></span></span>
									<span class="hsw-pl-icon"><span class="dashicons dashicons-admin-users"></span></span>
									<span class="hsw-pl-icon"><span class="dashicons dashicons-cart"></span></span>
									<span class="hsw-pl-icon"><span class="dashicons dashicons-menu"></span></span>
								</div>
								<div class="hsw-pl-hero"><?php esc_html_e( 'سلام، به حساب کاربری خوش آمدید', 'hamishop-elementor-widgets' ); ?></div>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
		<?php
	}
}
