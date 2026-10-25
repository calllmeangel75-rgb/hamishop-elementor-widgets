<?php
/**
 * Custom wp-login screen (opt-in) — dark acrylic design.
 *
 * Off by default. When HamiShop → امنیت → «صفحهٔ ورود سفارشی» is on, the
 * WordPress logo is replaced by the shop logo and the form becomes a tall
 * acrylic card on a dark chromatic background. Everything is printed inline on
 * wp-login.php only: no CDN, no Tailwind, no extra request.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Login {

	public function __construct() {
		if ( 'yes' !== ( HSW_Security::get()['login_style'] ?? 'no' ) ) {
			return;
		}

		/* login_head runs after wp-login.css is printed, so our rules win ties. */
		add_action( 'login_head', array( $this, 'styles' ), 99 );
		add_action( 'login_header', array( $this, 'topbar' ) );
		add_filter( 'login_headerurl', array( $this, 'home_url' ) );
		add_filter( 'login_headertext', array( $this, 'site_name' ) );
		add_filter( 'login_message', array( $this, 'brand_block' ) );
	}

	public function home_url() {
		return home_url( '/' );
	}

	public function site_name() {
		return get_bloginfo( 'name' );
	}

	/**
	 * Top bar: brand badge on the right, back link + language chip on the left.
	 */
	public function topbar() {
		$name = class_exists( 'HSW_Branding' ) ? HSW_Branding::name() : get_bloginfo( 'name' );
		$logo = class_exists( 'HSW_Branding' ) ? HSW_Branding::logo_html( 'hsw-login__toplogo' ) : '';
		$mark = '' !== $logo ? $logo : '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9h16l-1 11H5L4 9Z"></path><path d="M8 9V7a4 4 0 0 1 8 0v2"></path></svg>';

		echo '<div class="hsw-login__top">';
		echo '<div class="hsw-login__topright"><span class="hsw-login__topmark">' . $mark . '</span><span class="hsw-login__topname">' . esc_html( $name ) . '</span><span class="hsw-login__topbadge">پنل مدیریت</span></div>';
		echo '<div class="hsw-login__topleft">';
		echo '<a class="hsw-login__toplink" href="' . esc_url( home_url( '/' ) ) . '"><span>بازگشت به سایت</span><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"></path><path d="m11 18-6-6 6-6"></path></svg></a>';
		echo '<span class="hsw-login__topchip"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M3 12h18M12 3c2.5 3 2.5 15 0 18M12 3c-2.5 3-2.5 15 0 18"></path></svg>فارسی / EN</span>';
		echo '</div></div>';
	}

	/**
	 * Brand block above the form: logo badge, title, subtitle, notice.
	 *
	 * @param string $message Existing message.
	 * @return string
	 */
	public function brand_block( $message ) {
		$name = class_exists( 'HSW_Branding' ) ? HSW_Branding::name() : get_bloginfo( 'name' );
		$logo = class_exists( 'HSW_Branding' ) ? HSW_Branding::logo_html( 'hsw-login__logoimg' ) : '';
		$tag  = class_exists( 'HSW_Branding' ) ? HSW_Branding::tagline() : '';

		$mark = '' !== $logo
			? $logo
			: '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9h16l-1 11H5L4 9Z"></path><path d="M8 9V7a4 4 0 0 1 8 0v2"></path></svg>';

		$out  = '<div class="hsw-login__head">';
		$out .= '<span class="hsw-login__badge">' . $mark . '</span>';
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading the screen only.
		$titles = array(
			'lostpassword' => 'بازیابی رمز عبور',
			'rp'           => 'تعیین رمز عبور جدید',
			'resetpass'    => 'تعیین رمز عبور جدید',
			'register'     => 'ساخت حساب کاربری',
		);
		$title = isset( $titles[ $action ] ) ? $titles[ $action ] : 'ورود به داشبورد پنل';
		$out .= '<div class="hsw-login__title">' . esc_html( $title ) . '</div>';
		$out .= '<div class="hsw-login__sub">' . esc_html( '' !== trim( (string) $tag ) ? $tag : $name ) . '</div>';
		$out .= '</div>';

		$out .= '<div class="hsw-login__notice">';
		$out .= '<span class="hsw-login__noticemark"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 5 6v6c0 4 3 7 7 9 4-2 7-5 7-9V6l-7-3Z"></path><path d="m9 12 2 2 4-4"></path></svg></span>';
		$out .= '<span>پروتکل حفاظت امنیتی و احراز هویت فعال است</span>';
		$out .= '<span class="hsw-login__info">i</span>';
		$out .= '</div>';

		return $out . $message;
	}

	/**
	 * Inline styles for the login screen.
	 */
	public function styles() {
		$font = HSW_URL . 'assets/fonts/vazirmatn.woff2';
		?>
		<style id="hsw-login-css">
		@font-face{font-family:"Vazirmatn HSW";src:url("<?php echo esc_url( $font ); ?>") format("woff2-variations");font-weight:100 900;font-style:normal;font-display:swap}
		html body.login,html body.login *{font-family:"Vazirmatn HSW",Vazirmatn,"Segoe UI",Tahoma,sans-serif!important}

		body.login{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px 16px;color:#dfe2f1;background:#070913;background-attachment:fixed}
		body.login::before{content:"";position:fixed;inset:0;pointer-events:none;z-index:0;
			background:
				radial-gradient(560px 560px at 28% 2%, rgba(99,102,241,.32), transparent 62%),
				radial-gradient(520px 520px at 94% 46%, rgba(6,182,212,.24), transparent 64%),
				radial-gradient(620px 620px at 10% 96%, rgba(124,58,237,.26), transparent 66%),
				radial-gradient(320px 320px at 4% 26%, rgba(244,63,94,.12), transparent 70%);
			animation:hsw-login-glow 18s ease-in-out infinite alternate}
		@keyframes hsw-login-glow{from{opacity:.85;transform:translate3d(0,0,0)}to{opacity:1;transform:translate3d(0,-12px,0)}}

		.hsw-login__top{position:relative;z-index:3;width:100%;max-width:760px;display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 auto 26px}
		.hsw-login__topright{display:flex;align-items:center;gap:8px}
		.hsw-login__topmark{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.22);overflow:hidden;color:#c0c1ff}
		.hsw-login__toplogo{max-width:18px;max-height:18px;object-fit:contain}
		.hsw-login__topname{font-weight:800;font-size:13px;color:#fff}
		.hsw-login__topbadge{font-size:10px;padding:3px 8px;border-radius:999px;background:rgba(76,215,246,.1);border:1px solid rgba(76,215,246,.28);color:#4cd7f6}
		.hsw-login__topleft{display:flex;align-items:center;gap:8px}
		.hsw-login__toplink,.hsw-login__topchip{display:inline-flex;align-items:center;gap:6px;font-size:11px;color:#c7c4d7;text-decoration:none;padding:7px 11px;border-radius:11px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1)}
		.hsw-login__toplink:hover,.hsw-login__topchip:hover{color:#fff;border-color:rgba(255,255,255,.22)}

		#login{position:relative;z-index:2;width:100%;max-width:352px;margin:0;padding:34px 30px 28px;
			background:linear-gradient(180deg,rgba(30,36,64,.72),rgba(15,20,34,.94));
			border:1px solid rgba(255,255,255,.1);border-radius:28px;
			backdrop-filter:blur(26px) saturate(170%);-webkit-backdrop-filter:blur(26px) saturate(170%);
			box-shadow:0 30px 70px -20px rgba(0,0,0,.85), inset 0 1px 1px rgba(255,255,255,.16);
			animation:hsw-login-in .5s cubic-bezier(.22,.61,.36,1) both}
		@keyframes hsw-login-in{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
		#login h1,#login h1 a{display:none!important}

		.hsw-login__head{display:flex;flex-direction:column;align-items:center;text-align:center;margin:0 0 20px}
		.hsw-login__badge{width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:18px;background:rgba(255,255,255,.92);border:1px solid rgba(255,255,255,.35);box-shadow:0 0 0 6px rgba(255,255,255,.06),0 10px 30px -10px rgba(99,102,241,.9);overflow:hidden;color:#3b3bd6}
		.hsw-login__logoimg{max-width:34px;max-height:34px;object-fit:contain}
		.hsw-login__title{font-size:21px;font-weight:800;color:#fff;line-height:1.6;margin:0 0 6px}
		.hsw-login__sub{font-size:11.5px;color:#b9bccd;line-height:1.95;max-width:260px;margin:0 auto}

		.hsw-login__notice{display:flex;align-items:center;gap:8px;margin:0 0 26px;padding:12px 12px;border-radius:11px;background:rgba(255,255,255,.035);border:1px solid rgba(255,255,255,.07);border-inline-end:2px solid #4cd7f6;color:#c7c4d7;font-size:10.5px;line-height:1.8}
		.hsw-login__noticemark{color:#4cd7f6;display:inline-flex;flex:0 0 auto}
		.hsw-login__info{margin-inline-start:auto;width:15px;height:15px;border-radius:50%;flex:0 0 15px;display:inline-flex;align-items:center;justify-content:center;font-size:9px;font-weight:800;color:#9aa0b4;border:1px solid rgba(255,255,255,.22)}

		#loginform,#registerform,#lostpasswordform{background:transparent!important;border:0!important;box-shadow:none!important;padding:0!important;margin:0}
		/* The login_form hook prints before the submit row; flex order puts the
		   divider/tiles/status back under the button. */
		#loginform{display:flex!important;flex-direction:column}
		.hsw-login__divider{order:1}
		.hsw-login__tiles{order:2}
		.hsw-login__status{order:3}
		#loginform p.submit{order:0}
		#login form p{position:relative;margin:0 0 22px;display:flex;flex-direction:column;gap:9px}
		#login form p.forgetmenot,#login form p.submit{display:block}
		.login label{color:#e7e9f5!important;font-size:12px!important;font-weight:600;margin:0;display:block;line-height:1.6}
		.login form .forgetmenot{margin:0 0 22px}
		.login .forgetmenot label{font-size:11px!important;font-weight:500;color:#b9bccd!important}

		.login input[type=text],.login input[type=password],.login input[type=email]{width:100%;height:48px;padding:0 44px 0 14px;border-radius:11px;font-size:12.5px;background-color:rgba(255,255,255,.04)!important;border:1px solid rgba(255,255,255,.12)!important;color:#fff!important;box-shadow:inset 0 1px 1px rgba(255,255,255,.06)!important;transition:border-color .18s,box-shadow .18s,background-color .18s}
		.login input[type=text]:focus,.login input[type=password]:focus,.login input[type=email]:focus{background-color:rgba(255,255,255,.07)!important;border-color:rgba(99,102,241,.75)!important;box-shadow:0 0 0 3px rgba(99,102,241,.22)!important;outline:none}

		/* Field icons: background-image (mask rendered as a grey square). */
		#loginform .user-login input{background-image:url("data:image/svg+xml;charset=utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='1.7' stroke-linecap='round'%3E%3Ccircle cx='12' cy='12' r='4'/%3E%3Cpath d='M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8'/%3E%3C/svg%3E")!important;background-repeat:no-repeat!important;background-position:right 14px center!important;background-size:17px!important}
		#loginform .user-pass input{background-image:url("data:image/svg+xml;charset=utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='1.7' stroke-linecap='round'%3E%3Crect x='4' y='10' width='16' height='11' rx='2'/%3E%3Cpath d='M8 10V7a4 4 0 0 1 8 0v3'/%3E%3C/svg%3E")!important;background-repeat:no-repeat!important;background-position:right 14px center!important;background-size:17px!important}
		.login input::placeholder{color:#8a8f9c}
		.wp-pwd button.wp-hide-pw{color:#94a3b8!important;border:0;background:transparent!important;height:32px;top:7px}

		.wp-core-ui .button-primary,.wp-core-ui .button-primary:focus{width:100%;height:48px;border:0!important;border-radius:11px!important;font-size:13px;font-weight:800;background:linear-gradient(135deg,#6366f1 0%,#06b6d4 100%)!important;color:#fff!important;text-shadow:none!important;box-shadow:0 10px 26px -6px rgba(99,102,241,.55), inset 0 1px 0 rgba(255,255,255,.35)!important;transition:filter .18s,transform .15s}
		.wp-core-ui .button-primary:hover{filter:brightness(1.08);transform:translateY(-1px)}

		.login input[type=checkbox]{width:16px;height:16px;border-radius:4px;border:1px solid rgba(255,255,255,.28);background:rgba(255,255,255,.08);box-shadow:none}
		.login input[type=checkbox]:checked{background:#6366f1;border-color:#6366f1}

		.hsw-login__divider{position:relative;display:flex;align-items:center;justify-content:center;margin:24px 0 18px}
		.hsw-login__divider::before{content:"";position:absolute;inset-inline:0;top:50%;height:1px;background:rgba(255,255,255,.08)}
		.hsw-login__divider span{position:relative;padding:0 10px;font-size:10px;color:#8a8f9c;background:#0f1422;border-radius:999px;border:1px solid rgba(255,255,255,.06)}
		.hsw-login__tiles{display:grid;grid-template-columns:1fr 1fr;gap:10px}
		.hsw-login__tile{display:flex;align-items:center;justify-content:center;gap:8px;height:46px;border-radius:11px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);color:#dfe2f1;font-size:10.5px;font-weight:600;cursor:pointer;transition:background-color .15s,border-color .15s}
		.hsw-login__tile:hover{background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.2)}
		.hsw-login__tile svg{color:#c0c1ff;flex:0 0 auto}
		.hsw-login__status{display:flex;align-items:center;gap:8px;margin:18px 0 0;padding:10px 12px;border-radius:10px;background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.06);font-size:9.5px;color:#8a8f9c}
		.hsw-login__dot{width:7px;height:7px;border-radius:50%;background:#34d399;flex:0 0 7px;box-shadow:0 0 0 3px rgba(52,211,153,.15)}
		.hsw-login__lat{margin-inline-start:auto;font-family:ui-monospace,Menlo,monospace;letter-spacing:.3px}

		.login #nav,.login #backtoblog{padding:0;margin:14px 0 0;text-align:center}
		.login #nav a,.login #backtoblog a,.login .privacy-policy-page-link a{color:#4cd7f6!important;font-size:11px;text-decoration:none}
		.login .message,.login .notice,.login .success,.login #login_error{background:rgba(255,255,255,.05)!important;border:1px solid rgba(255,255,255,.1)!important;border-inline-start:3px solid #06b6d4!important;border-radius:11px;color:#dfe2f1!important;box-shadow:none!important;margin:0 0 16px;font-size:11px}
		.login #login_error{border-inline-start-color:#f43f5e!important}

		body.login .language-switcher{display:none}

		@media (max-width:520px){
			.hsw-login__top{max-width:372px;margin-bottom:16px}
			.hsw-login__topchip{display:none}
			#login{padding:24px 20px 20px;border-radius:24px}
		}
		@media (prefers-reduced-motion:reduce){#login,body.login::before{animation:none!important}}
		</style>
		<?php
	}
}
