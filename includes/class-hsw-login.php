<?php
/**
 * Custom wp-login screen (opt-in).
 *
 * Off by default. When the switch in HamiShop → امنیت is on, the WordPress logo
 * is gone and the form becomes a glass card on an animated blue gradient. The
 * CSS is printed inline on the login page only — no extra request, no CDN, and
 * the plugin's own Vazirmatn is used for Persian text.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Login {

	/**
	 * Hook only when the option is on.
	 */
	public function __construct() {
		if ( 'yes' !== ( HSW_Security::get()['login_style'] ?? 'no' ) ) {
			return;
		}

		add_action( 'login_enqueue_scripts', array( $this, 'styles' ), 20 );
		add_filter( 'login_headerurl', array( $this, 'home_url' ) );
		add_filter( 'login_headertext', array( $this, 'site_name' ) );
		add_filter( 'login_message', array( $this, 'brand_block' ) );
	}

	/**
	 * Point the (hidden) logo link at the shop.
	 *
	 * @return string
	 */
	public function home_url() {
		return home_url( '/' );
	}

	/**
	 * @return string
	 */
	public function site_name() {
		return get_bloginfo( 'name' );
	}

	/**
	 * Brand line above the form — the WordPress logo is hidden, so the shop
	 * name takes its place.
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
			: '<svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9h16l-1 11H5L4 9Z"></path><path d="M8 9V7a4 4 0 0 1 8 0v2"></path></svg>';

		$block  = '<div class="hsw-login__head">';
		$block .= '<span class="hsw-login__badge"><span class="hsw-login__badgeinner">' . $mark . '</span></span>';
		$block .= '<div class="hsw-login__title">' . esc_html( 'ورود به پنل مدیریت' ) . '</div>';
		$block .= '<p class="hsw-login__sub">' . esc_html( '' !== trim( (string) $tag ) ? $tag : $name ) . '</p>';
		$block .= '</div>';
		$block .= '<div class="hsw-login__notice"><span class="hsw-login__dot"></span><span>' . esc_html__( 'اتصال امن و رمزنگاری‌شده برقرار است', 'hamishop-elementor-widgets' ) . '</span></div>';

		return $block . $message;
	}

	/**
	 * Inline styles for the login screen.
	 */
	public function styles() {
		$font = HSW_URL . 'assets/fonts/vazirmatn.woff2';
		$hero_a = '#0b2036';
		$hero_b = '#173b5c';
		$accent = '#3b82f6';

		if ( class_exists( 'HSW_Palette' ) ) {
			$p      = HSW_Palette::get();
			$hero_a = $p['hero_a'] ?? $hero_a;
			$hero_b = $p['hero_b'] ?? $hero_b;
			$accent = $p['primary'] ?? $accent;
		}
		?>
		<style id="hsw-login-css">
		@font-face{font-family:"Vazirmatn HSW";src:url("<?php echo esc_url( $font ); ?>") format("woff2-variations");font-weight:100 900;font-display:swap}
		body.login{
			display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:32px 18px;
			font-family:"Vazirmatn HSW",Vazirmatn,Tahoma,sans-serif;color:#dfe2f1;
			background:#070913;background-attachment:fixed;
		}
		/* Chromatic liquid-light orbs. */
		body.login::before{
			content:"";position:fixed;inset:0;pointer-events:none;z-index:0;
			background:
				radial-gradient(580px 580px at 30% 0%, rgba(99,102,241,.30), transparent 62%),
				radial-gradient(520px 520px at 92% 45%, rgba(6,182,212,.24), transparent 64%),
				radial-gradient(600px 600px at 12% 95%, rgba(124,58,237,.24), transparent 66%),
				radial-gradient(320px 320px at 6% 25%, rgba(244,63,94,.12), transparent 70%);
			animation:hsw-login-glow 16s ease-in-out infinite alternate;
		}
		@keyframes hsw-login-glow{from{opacity:.85;transform:translate3d(0,0,0)}to{opacity:1;transform:translate3d(0,-14px,0)}}

		#login{position:relative;z-index:2;width:100%;max-width:420px;margin:0;padding:34px 30px 28px;
			background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);border-radius:26px;
			backdrop-filter:blur(28px) saturate(180%);-webkit-backdrop-filter:blur(28px) saturate(180%);
			box-shadow:0 25px 60px -15px rgba(0,0,0,.7), inset 0 1px 1px 0 rgba(255,255,255,.22);
			animation:hsw-login-in .5s cubic-bezier(.22,.61,.36,1) both;
		}
		@keyframes hsw-login-in{from{opacity:0;transform:translateY(16px) scale(.99)}to{opacity:1;transform:none}}

		#login h1,#login h1 a{display:none!important}

		/* Brand block: logo badge + title + subtitle + security notice. */
		.hsw-login__head{display:flex;flex-direction:column;align-items:center;text-align:center;margin:0 0 20px}
		.hsw-login__badge{position:relative;display:flex;align-items:center;justify-content:center;width:66px;height:66px;margin-bottom:16px}
		.hsw-login__badge::before{content:"";position:absolute;inset:-3px;border-radius:50%;
			background:linear-gradient(135deg,#6366f1,#06b6d4);filter:blur(10px);opacity:.55}
		.hsw-login__badgeinner{position:relative;width:64px;height:64px;border-radius:50%;display:flex;align-items:center;justify-content:center;
			background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.25);
			box-shadow:inset 0 1px 1px rgba(255,255,255,.3),0 0 25px rgba(6,182,212,.35);overflow:hidden;color:#fff}
		.hsw-login__logoimg{max-width:38px;max-height:38px;object-fit:contain;filter:drop-shadow(0 2px 10px rgba(255,255,255,.35))}
		.hsw-login__title{margin:0 0 6px;font-size:23px;font-weight:800;color:#fff;line-height:1.5;letter-spacing:-.2px}
		.hsw-login__sub{margin:0;font-size:13px;color:#c7c4d7;line-height:1.9}

		.hsw-login__notice{position:relative;display:flex;align-items:center;gap:8px;margin:0 0 22px;padding:10px 12px;border-radius:12px;
			background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-inline-start:2px solid #4cd7f6;
			color:#c7c4d7;font-size:11px;line-height:1.7}
		.hsw-login__dot{width:8px;height:8px;border-radius:50%;background:#34d399;flex:0 0 8px;box-shadow:0 0 0 3px rgba(52,211,153,.18)}

		#loginform,#registerform,#lostpasswordform{background:transparent!important;border:0!important;box-shadow:none!important;padding:0!important;margin:0}
		#login form p{margin:0 0 16px;display:flex;flex-direction:column;gap:7px}
		#login form p.forgetmenot,#login form p.submit{display:block}
		.login label{color:#dfe2f1!important;font-size:13px!important;font-weight:500;margin:0;display:block}

		.login input[type=text],.login input[type=password],.login input[type=email]{
			width:100%;height:48px;padding:0 14px;border-radius:12px;font-size:13px;
			background:rgba(255,255,255,.04)!important;border:1px solid rgba(255,255,255,.12)!important;
			color:#fff!important;box-shadow:inset 0 1px 1px 0 rgba(255,255,255,.06)!important;
			transition:border-color .18s,box-shadow .18s,background-color .18s;
		}
		.login input[type=text]:focus,.login input[type=password]:focus,.login input[type=email]:focus{
			background:rgba(255,255,255,.08)!important;border-color:rgba(99,102,241,.7)!important;
			box-shadow:0 0 0 3px rgba(99,102,241,.25)!important;outline:none;
		}
		.login input::placeholder{color:#8a8f9c}
		.wp-pwd button.wp-hide-pw{color:#94a3b8!important;height:34px;border:0;background:transparent!important}

		.wp-core-ui .button-primary,.wp-core-ui .button-primary:focus{
			width:100%;height:48px;border:0!important;border-radius:12px!important;font-size:13.5px;font-weight:700;letter-spacing:.2px;
			background:linear-gradient(135deg,#6366f1 0%,#06b6d4 100%)!important;color:#fff!important;text-shadow:none!important;
			box-shadow:0 8px 24px -4px rgba(99,102,241,.5), inset 0 1px 0 rgba(255,255,255,.35)!important;
			transition:filter .18s,transform .15s,box-shadow .2s;
		}
		.wp-core-ui .button-primary:hover{filter:brightness(1.1);transform:translateY(-1px);
			box-shadow:0 12px 30px -2px rgba(99,102,241,.65), inset 0 1px 0 rgba(255,255,255,.45)!important}

		.login .forgetmenot{display:flex;align-items:center;gap:7px}
		.login .forgetmenot label{color:#c7c4d7!important;font-size:12px!important;margin:0}
		.login input[type=checkbox]{width:17px;height:17px;border-radius:5px;border:1px solid rgba(255,255,255,.25);
			background:rgba(255,255,255,.08);box-shadow:none}
		.login input[type=checkbox]:checked{background:#6366f1;border-color:#6366f1}
		.login .submit{margin:6px 0 0}

		.login #nav,.login #backtoblog{padding:0;margin:16px 0 0;text-align:center}
		.login #nav a,.login #backtoblog a,.login .privacy-policy-page-link a{color:#4cd7f6!important;font-size:12px;text-decoration:none}
		.login #nav a:hover,.login #backtoblog a:hover{color:#acedff!important}

		.login .message,.login .notice,.login .success,.login #login_error{
			background:rgba(255,255,255,.05)!important;border:1px solid rgba(255,255,255,.1)!important;
			border-inline-start:3px solid #06b6d4!important;border-radius:12px;
			color:#dfe2f1!important;box-shadow:none!important;margin:0 0 18px;font-size:12px;
		}
		.login #login_error{border-inline-start-color:#f43f5e!important}

		body.login .language-switcher{position:fixed;top:18px;inset-inline-start:18px;margin:0;z-index:3}
		body.login .language-switcher select{
			height:36px;border-radius:12px;padding:0 12px;font-size:12px;
			background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#dfe2f1;
		}
		body.login .language-switcher select option{color:#0f172a}

		@media (max-width:420px){
			#login{padding:26px 20px;border-radius:22px}
			.hsw-login__title{font-size:20px}
		}
		@media (prefers-reduced-motion:reduce){
			#login,body.login::before{animation:none!important}
		}
		</style>
		<?php
	}
}
