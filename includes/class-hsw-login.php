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
		$tag  = class_exists( 'HSW_Branding' ) ? HSW_Branding::tagline() : get_bloginfo( 'description' );

		$block  = '<div class="hsw-login__brand"><span class="hsw-login__mark" aria-hidden="true">';
		$block .= '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9h16l-1 11H5L4 9Z"></path><path d="M8 9V7a4 4 0 0 1 8 0v2"></path></svg>';
		$block .= '</span><strong>' . esc_html( $name ) . '</strong>';
		if ( '' !== trim( (string) $tag ) ) {
			$block .= '<small>' . esc_html( $tag ) . '</small>';
		}
		$block .= '</div>';

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
			display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px;
			font-family:"Vazirmatn HSW",Vazirmatn,Tahoma,sans-serif;
			background:
				radial-gradient(900px 520px at 12% 6%, color-mix(in srgb, <?php echo esc_attr( $accent ); ?> 30%, transparent), transparent 62%),
				radial-gradient(760px 480px at 88% 14%, rgba(34,211,238,.22), transparent 64%),
				linear-gradient(140deg, <?php echo esc_attr( $hero_a ); ?>, <?php echo esc_attr( $hero_b ); ?> 58%, #050d18);
			background-attachment:fixed;
		}
		/* Floating light orbs. */
		body.login::before,
		body.login::after{
			content:"";position:fixed;border-radius:50%;filter:blur(70px);pointer-events:none;z-index:0;
		}
		body.login::before{width:340px;height:340px;background:<?php echo esc_attr( $accent ); ?>;opacity:.45;top:-90px;inset-inline-start:-60px;animation:hsw-login-float 14s ease-in-out infinite}
		body.login::after{width:420px;height:420px;background:#22d3ee;opacity:.28;bottom:-150px;inset-inline-end:-90px;animation:hsw-login-float 18s ease-in-out infinite reverse}
		@keyframes hsw-login-float{0%,100%{transform:translate3d(0,0,0)}50%{transform:translate3d(0,-26px,0)}}

		#login{position:relative;z-index:2;width:100%;max-width:372px;margin:0;padding:30px 26px 26px;
			background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.16);border-radius:24px;
			backdrop-filter:blur(22px);-webkit-backdrop-filter:blur(22px);
			box-shadow:0 24px 80px rgba(0,0,0,.38),0 0 40px rgba(59,130,246,.16);
			animation:hsw-login-in .5s cubic-bezier(.22,.61,.36,1) both;
		}
		@keyframes hsw-login-in{from{opacity:0;transform:translateY(14px) scale(.99)}to{opacity:1;transform:none}}

		/* No WordPress logo. */
		#login h1,#login h1 a{display:none!important}

		/* The language switcher is a flex child of body and used to push the
		   card off-centre; pin it to the corner instead. */
		body.login .language-switcher{position:fixed;top:16px;inset-inline-start:16px;margin:0;z-index:3}
		body.login .language-switcher select{
			height:34px;border-radius:9px;padding:0 10px;
			background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.22);color:#fff;font-size:12px;
		}
		body.login .language-switcher select option{color:#0f172a}

		.hsw-login__brand{display:flex;flex-direction:column;align-items:center;gap:4px;margin:0 0 26px!important;text-align:center}
		.hsw-login__mark{width:46px;height:46px;border-radius:14px;display:flex;align-items:center;justify-content:center;
			background:linear-gradient(135deg,<?php echo esc_attr( $accent ); ?>,#22d3ee);color:#fff;
			box-shadow:0 10px 24px -12px rgba(59,130,246,.9);margin-bottom:6px}
		.hsw-login__brand strong{color:#fff;font-size:17px;font-weight:800;line-height:1.6}
		.hsw-login__brand small{color:rgba(255,255,255,.6);font-size:11.5px}

		#loginform,#registerform,#lostpasswordform{background:transparent!important;border:0!important;box-shadow:none!important;padding:0!important;margin:0}
		#login form p{margin:0 0 14px;display:flex;flex-direction:column;gap:6px}
		#login form p.forgetmenot,#login form p.submit{display:block}
		.login label{color:rgba(255,255,255,.82)!important;font-size:12px!important;font-weight:600;margin-bottom:6px;display:block}
		.login input[type=text],.login input[type=password],.login input[type=email]{
			width:100%;height:44px;padding:0 12px;border-radius:11px;
			background:rgba(255,255,255,.1)!important;border:1px solid rgba(255,255,255,.22)!important;
			color:#fff!important;font-size:13px;box-shadow:none!important;margin:0!important;
		}
		.login input[type=text]:focus,.login input[type=password]:focus,.login input[type=email]:focus{
			border-color:rgba(255,255,255,.6)!important;background:rgba(255,255,255,.16)!important;outline:none
		}
		.login input::placeholder{color:rgba(255,255,255,.45)}

		.wp-core-ui .button-primary,.wp-core-ui .button-primary:focus{
			width:100%;height:44px;border:0!important;border-radius:11px!important;font-size:13px;font-weight:700;
			background:linear-gradient(135deg,<?php echo esc_attr( $accent ); ?>,#22d3ee)!important;
			box-shadow:0 14px 30px -14px rgba(59,130,246,.9)!important;text-shadow:none!important;
			transition:transform .15s,box-shadow .2s;
		}
		.wp-core-ui .button-primary:hover{transform:translateY(-1px);box-shadow:0 18px 36px -16px rgba(59,130,246,1)!important}

		.login .forgetmenot{display:flex;align-items:center;gap:6px}
		.login .forgetmenot label{color:rgba(255,255,255,.75)!important;margin:0;font-weight:500}
		.login input[type=checkbox]{border:1px solid rgba(255,255,255,.4);background:rgba(255,255,255,.1)}
		.login .submit{margin:4px 0 0}

		.login #nav,.login #backtoblog{padding:0;margin:14px 0 0;text-align:center}
		.login #nav a,.login #backtoblog a,.login .privacy-policy-page-link a{color:rgba(255,255,255,.72)!important;font-size:12px}
		.login #nav a:hover,.login #backtoblog a:hover{color:#fff!important}

		.login .message,.login .notice,.login .success,.login #login_error{
			background:rgba(255,255,255,.1)!important;border:1px solid rgba(255,255,255,.18)!important;
			border-inline-start:3px solid <?php echo esc_attr( $accent ); ?>!important;border-radius:10px;
			color:#fff!important;box-shadow:none!important;margin:0 0 16px;font-size:12px;
		}
		.login #login_error{border-inline-start-color:#f87171!important}

		@media (max-width:420px){
			#login{padding:24px 18px;border-radius:20px}
		}
		@media (prefers-reduced-motion:reduce){
			#login,body.login::before,body.login::after{animation:none!important}
		}
		</style>
		<?php
	}
}
