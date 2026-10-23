<?php
/**
 * HamiShop account settings screen.
 *
 * One native WordPress admin page (under the HamiShop menu) that owns every
 * account option: layout, sections, menu, support, notifications and colors.
 * No external UI libraries, no jQuery dependency, RTL by default.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

final class HSW_Account_Admin {

	/** @var HSW_Account */
	private $account;

	public function __construct( $account ) {
		$this->account = $account;

		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( HSW_FILE ), array( $this, 'plugin_action_links' ) );

		add_action( 'show_user_profile', array( $this, 'user_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'user_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_user_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user_fields' ) );
	}

	public function menu() {
		add_menu_page(
			'حساب کاربری HamiShop',
			'HamiShop',
			'manage_options',
			'hamishop',
			array( $this, 'render_page' ),
			'dashicons-store',
			56
		);

		/* A visible submenu entry for the account screen. The top-level page
		 * slug stays «hamishop» so old links and the plugin action link keep
		 * working; this second slug is only the friendly menu target. */
		add_submenu_page(
			'hamishop',
			'پنل حساب کاربری',
			'پنل حساب کاربری',
			'manage_options',
			'hamishop-account',
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'hsw_account_group',
			HSW_Account::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->account, 'sanitize_settings' ),
				'default'           => HSW_Account::defaults(),
			)
		);
	}

	public function plugin_action_links( $links ) {
		$url = admin_url( 'admin.php?page=hamishop' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">تنظیمات حساب کاربری</a>' );
		return $links;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s    = $this->account->settings( true );
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		$tabs = array(
			'general'       => 'عمومی',
			'sections'      => 'بخش‌ها و منو',
			'support'       => 'پشتیبانی',
			'notifications' => 'اعلان‌ها',
			'wallet'        => 'کیف پول',
			'auth'          => 'ورود و امنیت',
			'design'        => 'پالت و ظاهر',
			'shortcode'     => 'شورت‌کد و راهنما',
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'general';
		}
		$this->page_styles();
		?>
		<div class="wrap hsw-admin" dir="rtl">
			<div class="hsw-admin-head">
				<div class="hsw-admin-brand"><span class="hsw-admin-logo">HamiShop</span><div><h1>پنل حساب کاربری</h1><p>تنظیمات یکپارچهٔ داشبورد، منو، پشتیبانی، اعلان‌ها و ظاهر حساب مشتریان.</p></div></div>
				<div class="hsw-admin-headactions">
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=hamishop-widgets' ) ); ?>">کنترل ویجت‌ها</a>
				</div>
			</div>

			<nav class="hsw-admin-tabs" role="tablist">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'hamishop', 'tab' => $key ), admin_url( 'admin.php' ) ) ); ?>" class="<?php echo $tab === $key ? 'is-active' : ''; ?>" role="tab"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php settings_errors(); ?>
			<form method="post" action="options.php" class="hsw-admin-form">
				<?php settings_fields( 'hsw_account_group' ); ?>
				<?php if ( 'general' === $tab ) : ?>
					<?php $this->tab_general( $s ); ?>
				<?php elseif ( 'sections' === $tab ) : ?>
					<?php $this->tab_sections( $s ); ?>
				<?php elseif ( 'support' === $tab ) : ?>
					<?php $this->tab_support( $s ); ?>
				<?php elseif ( 'notifications' === $tab ) : ?>
					<?php $this->tab_notifications( $s ); ?>
				<?php elseif ( 'wallet' === $tab ) : ?>
					<?php $this->tab_wallet( $s ); ?>
				<?php elseif ( 'auth' === $tab ) : ?>
					<?php $this->tab_auth( $s ); ?>
				<?php elseif ( 'design' === $tab ) : ?>
					<?php $this->tab_design( $s ); ?>
				<?php endif; ?>
				<?php if ( 'shortcode' !== $tab && 'design' !== $tab ) : ?>
					<p class="hsw-admin-submit"><?php submit_button( 'ذخیره تنظیمات', 'primary', 'submit', false ); ?></p>
				<?php endif; ?>
			</form>
			<?php if ( 'shortcode' === $tab ) : ?>
				<?php $this->tab_shortcode( $s ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function page_styles() {
		?>
		<style>
			.hsw-admin{--hsw-a:#0e2a47;--hsw-b:#f3f6fa;--hsw-line:#e6ebf2;--hsw-muted:#5b6b80;max-width:1280px;padding-bottom:110px}
			.hsw-admin-head{display:flex;align-items:center;justify-content:space-between;gap:16px;background:linear-gradient(135deg,#0b2036,#173b5c);color:#fff;border-radius:18px;padding:22px 24px;margin:12px 0 18px;flex-wrap:wrap}
			.hsw-admin-brand{display:flex;align-items:center;gap:14px}
			.hsw-admin-logo{font-weight:800;font-size:20px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);padding:8px 14px;border-radius:12px}
			.hsw-admin-head h1{color:#fff;margin:0 0 4px;font-size:22px}
			.hsw-admin-head p{margin:0;color:rgba(255,255,255,.75)}
			.hsw-admin-headactions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
			/* WP's default .button is blue-on-white; on this dark header it turned into
			   blue-on-navy and was unreadable. */
			.hsw-admin-headactions .button{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.34);color:#fff;font-weight:700;border-radius:10px;padding:6px 16px;min-height:34px;line-height:1.6;box-shadow:none;text-shadow:none}
			.hsw-admin-headactions .button:hover,.hsw-admin-headactions .button:focus{background:rgba(255,255,255,.24);border-color:rgba(255,255,255,.55);color:#fff}
			.hsw-admin-tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}
			.hsw-admin-tabs a{background:#fff;border:1px solid var(--hsw-line);border-radius:999px;padding:8px 16px;text-decoration:none;color:#1d2327;font-weight:600}
			.hsw-admin-tabs a.is-active{background:var(--hsw-a);border-color:var(--hsw-a);color:#fff}
			.hsw-admin-card{background:#fff;border:1px solid var(--hsw-line);border-radius:16px;padding:20px 22px;margin:0 0 16px;box-shadow:0 1px 2px rgba(15,23,42,.03)}
			.hsw-admin-card h2{margin:0 0 4px;font-size:16px}
			.hsw-admin-card .hsw-admin-desc{color:var(--hsw-muted);margin:0 0 16px}
			.hsw-admin-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 18px}
			.hsw-admin-grid.is-3{grid-template-columns:repeat(3,minmax(0,1fr))}
			.hsw-field label{display:block;font-weight:600;margin-bottom:6px}
			.hsw-field input[type=text],.hsw-field input[type=url],.hsw-field input[type=email],.hsw-field input[type=number],.hsw-field select,.hsw-field textarea{width:100%;max-width:none;border-radius:10px;border-color:#cfd6df}
			.hsw-field textarea{min-height:90px}
			.hsw-field .description{margin-top:5px;color:var(--hsw-muted)}
			.hsw-switch{display:flex;align-items:center;gap:8px;background:#f6f8fb;border:1px solid var(--hsw-line);border-radius:10px;padding:9px 12px;margin:0 0 6px}
			.hsw-switch input{margin:0}
			.hsw-colors{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
			.hsw-color{display:flex;align-items:center;gap:8px;border:1px solid var(--hsw-line);border-radius:10px;padding:8px}
			.hsw-color input[type=color]{width:38px;height:32px;padding:0;border:0;background:none}
			.hsw-color code{font-size:11px;color:var(--hsw-muted)}
			.hsw-admin table.menu-table{width:100%;border-collapse:collapse}
			.hsw-admin table.menu-table th,.hsw-admin table.menu-table td{border-bottom:1px solid var(--hsw-line);padding:10px 8px;text-align:right}
			.hsw-admin table.menu-table input[type=text]{width:100%}
			.hsw-admin table.menu-table input[type=number]{width:80px}
			.hsw-badge{display:inline-block;border-radius:999px;padding:2px 9px;font-size:11px;background:#eef2f7;color:#334155}
			.hsw-badge.is-native{background:#e0f2fe;color:#075985}
			.hsw-badge.is-custom{background:#fef3c7;color:#92400e}
			.hsw-admin-submit{margin:0;position:sticky;bottom:12px;background:rgba(243,246,250,.92);padding:12px 0;backdrop-filter:blur(4px)}
			.hsw-inbound{margin-top:22px;padding:16px 18px;border:1px solid var(--hsw-line);border-radius:14px;background:#f8fafc}
			.hsw-inbound h3{margin:0 0 6px;font-size:14px}
			.hsw-inbound .hsw-admin-desc{margin:0 0 12px}
			.hsw-inbound .form-table{margin:0}
			.hsw-inbound .form-table th{width:140px;font-weight:600;padding:8px 0;font-size:13px}
			.hsw-inbound .form-table td{padding:8px 0}
			.hsw-inbound code{display:inline-block;background:#fff;border:1px solid var(--hsw-line);border-radius:8px;padding:4px 9px;font-size:12px;color:#0e2a47}
			.hsw-inbound .description{color:var(--hsw-muted);margin:6px 0 0}
			.hsw-code{direction:ltr;text-align:left;background:#0f172a;color:#e2e8f0;padding:14px 16px;border-radius:12px;font-family:Consolas,monospace;display:block;margin:8px 0 14px;overflow:auto}
			.hsw-status-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin:0;padding:0;list-style:none}
			.hsw-status-list li{background:var(--hsw-b);border:1px solid var(--hsw-line);border-radius:10px;padding:10px 12px;display:flex;justify-content:space-between;gap:8px}
			.hsw-notice{border-inline-start:4px solid #dba617;background:#fef8e7;padding:10px 14px;border-radius:8px;margin:0 0 14px}
			@media(max-width:900px){.hsw-admin-grid,.hsw-admin-grid.is-3,.hsw-colors,.hsw-status-list{grid-template-columns:1fr}}
		</style>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Field helpers
	 * ------------------------------------------------------------------ */

	private function name( $group, $key = null ) {
		return null === $key ? 'hsw_account_settings[' . $group . ']' : 'hsw_account_settings[' . $group . '][' . $key . ']';
	}

	private function val( $s, $group, $key, $default = '' ) {
		return isset( $s[ $group ][ $key ] ) ? $s[ $group ][ $key ] : $default;
	}

	private function text_field( $s, $group, $key, $label, $desc = '', $type = 'text' ) {
		?>
		<div class="hsw-field">
			<label for="hsw-<?php echo esc_attr( $group . '-' . $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<input type="<?php echo esc_attr( $type ); ?>" id="hsw-<?php echo esc_attr( $group . '-' . $key ); ?>" name="<?php echo esc_attr( $this->name( $group, $key ) ); ?>" value="<?php echo esc_attr( $this->val( $s, $group, $key ) ); ?>">
			<?php if ( $desc ) : ?><p class="description"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
		</div>
		<?php
	}

	private function textarea_field( $s, $group, $key, $label, $desc = '', $lines = false ) {
		$value = $this->val( $s, $group, $key );
		if ( $lines && is_array( $value ) ) {
			$value = implode( "\n", $value );
		}
		?>
		<div class="hsw-field">
			<label for="hsw-<?php echo esc_attr( $group . '-' . $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<textarea id="hsw-<?php echo esc_attr( $group . '-' . $key ); ?>" name="<?php echo esc_attr( $this->name( $group, $key ) ); ?>" rows="5"><?php echo esc_textarea( $value ); ?></textarea>
			<?php if ( $desc ) : ?><p class="description"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
		</div>
		<?php
	}

	private function number_field( $s, $group, $key, $label, $min, $max, $desc = '' ) {
		?>
		<div class="hsw-field">
			<label for="hsw-<?php echo esc_attr( $group . '-' . $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<input type="number" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" id="hsw-<?php echo esc_attr( $group . '-' . $key ); ?>" name="<?php echo esc_attr( $this->name( $group, $key ) ); ?>" value="<?php echo esc_attr( $this->val( $s, $group, $key ) ); ?>">
			<?php if ( $desc ) : ?><p class="description"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
		</div>
		<?php
	}

	private function select_field( $s, $group, $key, $label, $options, $desc = '' ) {
		?>
		<div class="hsw-field">
			<label for="hsw-<?php echo esc_attr( $group . '-' . $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<select id="hsw-<?php echo esc_attr( $group . '-' . $key ); ?>" name="<?php echo esc_attr( $this->name( $group, $key ) ); ?>">
				<?php foreach ( $options as $value => $text ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) $this->val( $s, $group, $key ), (string) $value ); ?>><?php echo esc_html( $text ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ( $desc ) : ?><p class="description"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
		</div>
		<?php
	}

	private function switch_field( $s, $group, $key, $label, $desc = '' ) {
		$value    = 'yes' === $this->val( $s, $group, $key ) ? 'yes' : 'no';
		$name     = $this->name( $group, $key );
		?>
		<label class="hsw-switch">
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="no">
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="yes" <?php checked( $value, 'yes' ); ?>>
			<span><strong><?php echo esc_html( $label ); ?></strong><?php if ( $desc ) : ?><br><small><?php echo esc_html( $desc ); ?></small><?php endif; ?></span>
		</label>
		<?php
	}

	private function color_field( $s, $group, $key, $label ) {
		$value = $this->val( $s, $group, $key );
		?>
		<div class="hsw-color">
			<input type="color" id="hsw-<?php echo esc_attr( $group . '-' . $key ); ?>" name="<?php echo esc_attr( $this->name( $group, $key ) ); ?>" value="<?php echo esc_attr( $value ); ?>">
			<div><label for="hsw-<?php echo esc_attr( $group . '-' . $key ); ?>"><?php echo esc_html( $label ); ?></label><br><code><?php echo esc_html( $value ); ?></code></div>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Tabs
	 * ------------------------------------------------------------------ */

	private function tab_general( $s ) {
		?>
		<div class="hsw-admin-card">
			<h2>فعال‌سازی و چیدمان</h2>
			<p class="hsw-admin-desc">این تنظیمات پایهٔ پنل حساب در شورت‌کد و ویجت المنتور است.</p>
			<div class="hsw-admin-grid">
				<?php $this->switch_field( $s, 'general', 'enabled', 'فعال‌سازی پنل حساب HamiShop', 'در صورت غیرفعال بودن، شورت‌کد به پنل پیش‌فرض ووکامرس برمی‌گردد.' ); ?>
				<?php $this->switch_field( $s, 'general', 'email_verify', 'درخواست تأیید ایمیل از کاربران پیامکی', 'کاربری که با کد پیامکی ثبت‌نام کرده، در پیشخوان و سفارش‌ها پیام «ایمیل خود را تأیید کنید» می‌بیند و می‌تواند ایمیلش را با یک کد ۶ رقمی تأیید و به حساب وصل کند.' ); ?>
				<?php $this->select_field( $s, 'general', 'side_position', 'جای منوی کناری', array( 'right' => 'سمت راست', 'left' => 'سمت چپ' ) ); ?>
				<?php $this->number_field( $s, 'general', 'side_width', 'عرض ستون کناری (پیکسل)', 240, 420 ); ?>
				<?php $this->number_field( $s, 'general', 'gap', 'فاصلهٔ ستون‌ها (پیکسل)', 0, 64 ); ?>
				<?php $this->number_field( $s, 'general', 'max_width', 'حداکثر عرض پنل (پیکسل)', 960, 1600 ); ?>
				<?php $this->number_field( $s, 'general', 'radius', 'گردی گوشه‌ها (پیکسل)', 0, 40 ); ?>
			</div>
		</div>
		<div class="hsw-admin-card">
			<h2>هدر، خوش‌آمد و پروفایل</h2>
			<p class="hsw-admin-desc">در متن خوش‌آمد می‌توانید از جای‌نگهدار <code>{name}</code> برای نام کاربر استفاده کنید.</p>
			<div class="hsw-admin-grid">
				<?php $this->switch_field( $s, 'general', 'show_breadcrumb', 'نمایش مسیر صفحه' ); ?>
				<?php $this->switch_field( $s, 'general', 'show_hero', 'نمایش هدر خوش‌آمد' ); ?>
				<?php $this->switch_field( $s, 'general', 'show_badge', 'نمایش نشان هدر' ); ?>
				<?php $this->text_field( $s, 'general', 'badge_text', 'متن نشان هدر' ); ?>
				<?php $this->text_field( $s, 'general', 'greeting', 'متن خوش‌آمدگویی' ); ?>
				<?php $this->textarea_field( $s, 'general', 'subtitle', 'زیرعنوان هدر' ); ?>
				<?php $this->switch_field( $s, 'general', 'show_catalog_button', 'نمایش دکمهٔ کاتالوگ' ); ?>
				<?php $this->text_field( $s, 'general', 'catalog_label', 'متن دکمهٔ کاتالوگ' ); ?>
				<?php $this->text_field( $s, 'general', 'catalog_url', 'لینک دکمهٔ کاتالوگ', '', 'url' ); ?>
				<?php $this->switch_field( $s, 'general', 'show_profile', 'نمایش کارت پروفایل' ); ?>
				<?php $this->switch_field( $s, 'general', 'show_membership', 'نمایش باشگاه مشتریان' ); ?>
				<?php $this->switch_field( $s, 'general', 'show_menu', 'نمایش منوی حساب' ); ?>
				<?php $this->switch_field( $s, 'general', 'show_logout', 'نمایش گزینهٔ خروج' ); ?>
				<?php $this->text_field( $s, 'general', 'menu_title', 'عنوان منوی حساب' ); ?>
			</div>
		</div>
		<?php
	}

	private function tab_sections( $s ) {
		$native = array( 'dashboard', 'orders', 'downloads', 'edit-address', 'payment-methods', 'edit-account', 'customer-logout' );
		?>
		<div class="hsw-admin-card">
			<h2>بخش‌های داشبورد</h2>
			<p class="hsw-admin-desc">هر بخش را می‌توانید مستقل از دیگر بخش‌ها خاموش کنید.</p>
			<div class="hsw-admin-grid">
				<?php $this->switch_field( $s, 'dashboard', 'show_stats', 'کارت‌های آماری' ); ?>
				<?php $this->switch_field( $s, 'dashboard', 'show_recent_order', 'کارت سفارش اخیر' ); ?>
				<?php $this->switch_field( $s, 'dashboard', 'show_shortcuts', 'میانبرهای پرکاربرد' ); ?>
				<?php $this->switch_field( $s, 'dashboard', 'show_address', 'کارت نشانی پیش‌فرض' ); ?>
				<?php $this->switch_field( $s, 'dashboard', 'stat_orders', 'آمار سفارش‌های جاری' ); ?>
				<?php $this->switch_field( $s, 'dashboard', 'stat_completed', 'آمار سفارش‌های تحویل‌شده' ); ?>
				<?php $this->switch_field( $s, 'dashboard', 'stat_wallet', 'آمار کیف پول' ); ?>
				<?php $this->switch_field( $s, 'dashboard', 'stat_loyalty', 'آمار امتیاز باشگاه' ); ?>
				<?php $this->switch_field( $s, 'dashboard', 'shortcut_support', 'میانبر پشتیبانی' ); ?>
				<?php $this->switch_field( $s, 'dashboard', 'shortcut_returns', 'میانبر درخواست مرجوعی' ); ?>
				<?php $this->switch_field( $s, 'dashboard', 'shortcut_downloads', 'میانبر دانلودها' ); ?>
			</div>
			<?php $this->textarea_field( $s, 'dashboard', 'shortcut_note', 'یادداشت پایین میانبرها' ); ?>
		</div>
		<div class="hsw-admin-card">
			<h2>منوی حساب کاربری</h2>
			<p class="hsw-admin-desc">ترتیب، نام و فعال/غیرفعال بودن هر آیتم را مدیریت کنید. اندپوینت‌های غیرفعال در تنظیمات ووکامرس به‌صورت خودکار حذف می‌شوند.</p>
			<table class="menu-table">
				<thead><tr><th>فعال</th><th>عنوان</th><th>ترتیب</th><th>شناسه</th><th>نوع</th></tr></thead>
				<tbody>
					<?php foreach ( $s['menu']['items'] as $key => $item ) : ?>
						<tr>
							<td><input type="hidden" name="<?php echo esc_attr( $this->name( 'menu', 'items' ) . '[' . $key . '][enabled]' ); ?>" value="no"><input type="checkbox" name="<?php echo esc_attr( $this->name( 'menu', 'items' ) . '[' . $key . '][enabled]' ); ?>" value="yes" <?php checked( 'yes', $item['enabled'] ); ?>></td>
							<td><input type="text" name="<?php echo esc_attr( $this->name( 'menu', 'items' ) . '[' . $key . '][label]' ); ?>" value="<?php echo esc_attr( $item['label'] ); ?>"></td>
							<td><input type="number" min="0" max="1000" name="<?php echo esc_attr( $this->name( 'menu', 'items' ) . '[' . $key . '][order]' ); ?>" value="<?php echo esc_attr( $item['order'] ); ?>"></td>
							<td><code><?php echo esc_html( $key ); ?></code></td>
							<td><span class="hsw-badge <?php echo in_array( $key, $native, true ) ? 'is-native' : 'is-custom'; ?>"><?php echo in_array( $key, $native, true ) ? 'ووکامرس' : 'اختصاصی'; ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="hsw-admin-desc" style="margin-top:14px">اندپوینت‌هایی که افزونه‌های دیگر (مثل دوره‌ساز نیاس) به ووکامرس اضافه می‌کنند، به‌صورت خودکار در منو نمایش داده می‌شوند و محتوای آن‌ها هم از خود ووکامرس/افزونه render می‌شود. برای داشبوردهای بیرونی مثل SpotPlayer از لینک‌های اختصاصی زیر استفاده کنید.</p>
			<?php $this->extra_menu_field( $s ); ?>
		</div>
		<div class="hsw-admin-card">
			<h2>باشگاه مشتریان و امتیاز</h2>
			<p class="hsw-admin-desc">امتیاز هر کاربر از مجموع خریدهای معتبر (total spent ووکامرس) تقسیم بر «مبلغ خرید برای دریافت ۱ امتیاز» محاسبه می‌شود. امتیازها سطح کاربر را تعیین می‌کنند و مشتری می‌تواند آن‌ها را در بخش کیف پول به اعتبار خرید تبدیل کند.</p>
			<div class="hsw-admin-grid">
				<?php $this->switch_field( $s, 'loyalty', 'enabled', 'فعال‌سازی باشگاه مشتریان' ); ?>
				<?php $this->text_field( $s, 'loyalty', 'label', 'نام امتیازها' ); ?>
				<?php $this->number_field( $s, 'loyalty', 'rate', 'مبلغ خرید برای دریافت ۱ امتیاز (تومان)', 1, 10000000, 'مثلاً ۱۰۰۰۰ یعنی هر ۱۰,۰۰۰ تومان خرید = ۱ امتیاز.' ); ?>
				<?php $this->number_field( $s, 'loyalty', 'silver', 'حد امتیاز سطح نقره‌ای', 1, 10000000 ); ?>
				<?php $this->number_field( $s, 'loyalty', 'gold', 'حد امتیاز سطح طلایی', 1, 10000000 ); ?>
			</div>
		</div>
		<?php
	}

	private function extra_menu_field( $s ) {
		$lines = array();
		foreach ( (array) $s['menu']['extra'] as $item ) {
			if ( empty( $item['url'] ) ) {
				continue;
			}
			$lines[] = implode(
				' | ',
				array(
					$item['label'] ?? '',
					$item['url'],
					$item['icon'] ?? 'external',
					$item['order'] ?? 500,
					! empty( $item['new'] ) ? 'new' : '',
				)
			);
		}
		?>
		<div class="hsw-field">
			<label for="hsw-menu-extra">لینک‌های اختصاصی منو</label>
			<textarea id="hsw-menu-extra" name="<?php echo esc_attr( $this->name( 'menu', 'extra' ) ); ?>" rows="5" placeholder="SpotPlayer | https://spotplayer.ir/panel | play | 110 | new"><?php echo esc_textarea( implode( "\n", $lines ) ); ?></textarea>
			<p class="description">هر خط: عنوان | آدرس | آیکون | ترتیب | new (اختیاری). آیکون‌ها: grid, receipt, download, location, heart, wallet, support, bell, credit-card, user, external, play, book, star, video, course.</p>
		</div>
		<?php
	}

	private function tab_support( $s ) {
		?>
		<div class="hsw-admin-card">
			<h2>تیکت‌های پشتیبانی</h2>
			<p class="hsw-admin-desc">تیکت‌ها در منوی HamiShop > تیکت‌های پشتیبانی قابل مدیریت هستند. کاربر فقط تیکت‌های خودش را می‌بیند.</p>
			<div class="hsw-admin-grid">
				<?php $this->switch_field( $s, 'support', 'enabled', 'فعال‌سازی تیکت پشتیبانی' ); ?>
				<?php $this->number_field( $s, 'support', 'max_open', 'حداکثر تیکت باز هر کاربر', 1, 20 ); ?>
				<?php $this->number_field( $s, 'support', 'max_message', 'حداکثر طول پیام (کاراکتر)', 200, 10000 ); ?>
				<?php $this->switch_field( $s, 'support', 'email_notifications', 'ارسال ایمیل به مدیر', 'با هر تیکت جدید یا پاسخ کاربر، یک ایمیل اطلاع‌رسانی ارسال می‌شود.' ); ?>
				<?php $this->text_field( $s, 'support', 'support_email', 'ایمیل پشتیبانی', 'اگر خالی باشد، ایمیل مدیر وردپرس استفاده می‌شود.', 'email' ); ?>
				<?php $this->select_field( $s, 'support', 'priority_default', 'اولویت پیش‌فرض', $s['support']['priorities'] ); ?>
				<?php $this->number_field( $s, 'support', 'retention_days', 'نگهداری تیکت بسته‌شده (روز) — ۰ = هرگز', 0, 3650 ); ?>
			</div>
			<?php $this->textarea_field( $s, 'support', 'departments', 'دپارتمان‌ها', 'هر دپارتمان در یک خط.', true ); ?>

			<div class="hsw-inbound">
			<h3>پاسخ از داخل ایمیل</h3>
			<p class="hsw-admin-desc">
				هر ایمیل اطلاع‌رسانی تیکت با یک «Reply-To» امضاشده فرستاده می‌شود؛ اگر سرویس ایمیل شما پیام‌های آن آدرس را به این وب‌هوک بفرستد، پاسخ شما مستقیماً به تیکت اضافه و به مشتری اطلاع داده می‌شود.
			</p>
			<?php $secret = $this->account->inbound_secret(); ?>
			<?php if ( '' === $secret ) : ?>
				<p class="hsw-admin-desc"><strong>ساخت کلید ناموفق بود؛ یک‌بار همین صفحه را ذخیره کنید.</strong></p>
			<?php else : ?>
				<table class="form-table" role="presentation"><tbody>
					<tr>
						<th scope="row">آدرس وب‌هوک</th>
						<td><code dir="ltr" style="user-select:all"><?php echo esc_html( rest_url( 'hsw/v1/ticket-inbound' ) ); ?></code></td>
					</tr>
					<tr>
						<th scope="row">کلید (secret)</th>
						<td><code dir="ltr" style="user-select:all"><?php echo esc_html( $secret ); ?></code>
							<p class="description">در بدنهٔ درخواست POST به شکل <code dir="ltr">{"secret":"…","to":"…","from":"…","text":"…"}</code> فرستاده شود. فقط ایمیل پشتیبانی، مدیر فروشگاه یا خود مشتری پذیرفته می‌شود.</p>
						</td>
					</tr>
				</tbody></table>
			<?php endif; ?>
			</div>
		</div>
		<div class="hsw-admin-card">
			<h2>وضعیت اندپوینت‌ها</h2>
			<ul class="hsw-status-list">
				<li><span>پشتیبانی</span><code><?php echo esc_html( wc_get_account_endpoint_url( 'support' ) ); ?></code></li>
				<li><span>اعلان‌ها</span><code><?php echo esc_html( wc_get_account_endpoint_url( 'notifications' ) ); ?></code></li>
				<li><span>علاقه‌مندی‌ها</span><code><?php echo esc_html( wc_get_account_endpoint_url( 'wishlist' ) ); ?></code></li>
				<li><span>کیف پول</span><code><?php echo esc_html( wc_get_account_endpoint_url( 'wallet' ) ); ?></code></li>
			</ul>
		</div>
		<?php
	}

	private function tab_notifications( $s ) {
		$statuses = array();
		if ( function_exists( 'wc_get_order_statuses' ) ) {
			foreach ( wc_get_order_statuses() as $key => $label ) {
				$statuses[ 0 === strpos( $key, 'wc-' ) ? substr( $key, 3 ) : $key ] = $label;
			}
		}
		$selected = (array) $this->val( $s, 'notifications', 'order_statuses', array() );
		?>
		<div class="hsw-admin-card">
			<h2>اعلان‌های داخلی</h2>
			<p class="hsw-admin-desc">اعلان‌ها در زنگ بالای پنل و صفحهٔ اعلان‌ها نمایش داده می‌شوند.</p>
			<div class="hsw-admin-grid">
				<?php $this->switch_field( $s, 'notifications', 'enabled', 'فعال‌سازی اعلان‌های داخلی' ); ?>
				<?php $this->switch_field( $s, 'notifications', 'welcome', 'اعلان خوش‌آمد پس از ثبت‌نام' ); ?>
				<?php $this->switch_field( $s, 'notifications', 'ticket_reply', 'اعلان پاسخ جدید پشتیبانی' ); ?>
				<?php $this->number_field( $s, 'notifications', 'max', 'حداکثر تعداد اعلان نگهداری‌شده', 10, 200 ); ?>
			</div>
		</div>
		<div class="hsw-admin-card">
			<h2>اعلان تغییر وضعیت سفارش</h2>
			<p class="hsw-admin-desc">برای هر تغییر وضعیت انتخاب‌شده، یک اعلان داخلی برای مشتری ساخته می‌شود.</p>
			<div class="hsw-admin-grid is-3">
				<?php foreach ( $statuses as $key => $label ) : ?>
					<label class="hsw-check"><input type="checkbox" name="<?php echo esc_attr( $this->name( 'notifications', 'order_statuses' ) ); ?>[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $selected, true ) ); ?>> <?php echo esc_html( $label ); ?></label>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Wallet settings: limits, points rate and the SMS webhook.
	 *
	 * @param array $s Settings.
	 */
	private function tab_wallet( $s ) {
		?>
		<div class="hsw-admin-card">
			<h2>کیف پول</h2>
			<p class="hsw-admin-desc">موجودی هر مشتری در یک متای کاربر نگه داشته می‌شود و تراکنش‌ها در جدول <code>hsw_wallet_ledger</code> ثبت می‌شوند. شارژ دستی و شارژ گروهی مشتریان طلایی از منوی <strong>HamiShop ← کیف پول</strong> انجام می‌شود.</p>

			<?php $this->switch_field( $s, 'wallet', 'enabled', 'فعال بودن کیف پول', 'با خاموش کردن، صفحهٔ کیف پول از منو حذف می‌شود و تبدیل امتیاز هم غیرفعال می‌شود.' ); ?>
			<div class="hsw-admin-grid">
				<?php $this->text_field( $s, 'wallet', 'label', 'نام کیف پول', 'مثلاً «کیف پول» یا «اعتبار حساب».' ); ?>
				<?php $this->number_field( $s, 'wallet', 'min_topup', 'حداقل مبلغ شارژ (تومان)', 0, 10000000 ); ?>
				<?php $this->number_field( $s, 'wallet', 'point_value', 'مبلغ شارژ کیف پول به ازای هر ۱ امتیاز (تومان)', 1, 1000000, 'هنگام تبدیل امتیاز توسط مشتری، هر ۱ امتیاز معادل این مبلغ به کیف پول او اضافه می‌شود.' ); ?>
			</div>
			<?php $this->textarea_field( $s, 'wallet', 'note', 'توضیح زیر موجودی', 'یک جملهٔ کوتاه برای مشتری.', true ); ?>

			<h3 style="margin:22px 0 6px">روش شارژ</h3>
			<p class="hsw-admin-desc">مشتری از صفحهٔ کیف پول، مبلغ را انتخاب می‌کند. در حالت «درگاه»، یک سفارش شارژ ساخته می‌شود و پرداخت با همان درگاهی انجام می‌شود که انتخاب می‌کنید؛ موجودی بلافاصله پس از پرداخت اضافه می‌شود. در حالت «کارت به کارت»، شمارهٔ کارت نمایش داده می‌شود و مشتری شمارهٔ پیگیری را ثبت می‌کند تا شما در <strong>HamiShop ← کیف پول</strong> تایید کنید.</p>
			<?php
			$topup_mode = $this->val( $s, 'wallet', 'topup_mode' );
			$topup_mode = '' !== $topup_mode ? $topup_mode : 'gateway';
			$gateways   = class_exists( 'HSW_Wallet_Topup' ) ? HSW_Wallet_Topup::gateways() : array();
			?>
			<div class="hsw-admin-grid">
				<p>
					<label for="hsw-wallet-topup-mode"><strong>روش شارژ</strong></label><br>
					<select id="hsw-wallet-topup-mode" name="hsw_account[wallet][topup_mode]">
						<option value="gateway" <?php selected( $topup_mode, 'gateway' ); ?>>درگاه پرداخت آنلاین</option>
						<option value="card" <?php selected( $topup_mode, 'card' ); ?>>کارت به کارت</option>
					</select>
				</p>
				<p>
					<label for="hsw-wallet-topup-gateway"><strong>درگاه شارژ</strong></label><br>
					<select id="hsw-wallet-topup-gateway" name="hsw_account[wallet][topup_gateway]">
						<option value="">— خودکار (اولین درگاه فعال) —</option>
						<?php foreach ( $gateways as $gid => $gtitle ) : ?>
							<option value="<?php echo esc_attr( $gid ); ?>" <?php selected( $this->val( $s, 'wallet', 'topup_gateway' ), $gid ); ?>><?php echo esc_html( $gtitle ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php if ( ! $gateways ) : ?>
						<br><small>هیچ درگاه پرداختی فعال نیست؛ در حالت «درگاه» دکمهٔ شارژ پیام خطا می‌دهد.</small>
					<?php endif; ?>
				</p>
			</div>

			<h3 style="margin:22px 0 6px">کارت به کارت</h3>
			<div class="hsw-admin-grid">
				<?php $this->text_field( $s, 'wallet', 'card_number', 'شماره کارت', 'با یا بدون خط تیره؛ روی صفحهٔ کیف پول نمایش داده می‌شود.' ); ?>
				<?php $this->text_field( $s, 'wallet', 'card_owner', 'به نام', 'نام صاحب حساب.' ); ?>
				<?php $this->text_field( $s, 'wallet', 'card_bank', 'بانک', 'مثلاً بانک ملت.' ); ?>
			</div>

			<h3 style="margin:22px 0 6px">پیامک شارژ</h3>
			<p class="hsw-admin-desc">هنگام شارژ گروهی، به هر مشتری یک پیامک اطلاع‌رسانی فرستاده می‌شود. هر پنل پیامکی یا پل بله که یک <code>POST</code> با بدنهٔ <code>{"phone","text","secret"}</code> بپذیرد کار می‌کند؛ اگر آدرس را خالی بگذارید پیامکی ارسال نمی‌شود و شارژ انجام می‌شود.</p>
			<div class="hsw-admin-grid">
				<?php $this->text_field( $s, 'wallet', 'sms_url', 'آدرس وب‌هوک پیامک', 'مثلاً آدرس وبهوک n8n یا پنل پیامکی.', 'url' ); ?>
				<?php $this->text_field( $s, 'wallet', 'sms_secret', 'کلید وب‌هوک (اختیاری)', 'در بدنهٔ درخواست به‌عنوان secret فرستاده می‌شود.' ); ?>
			</div>
		</div>
		<?php
	}

	private function tab_auth( $s ) {
		$secret  = $this->val( $s, 'auth', 'bale_secret' );
		$webhook = $secret ? add_query_arg( array( 'hsw_bale_webhook' => 1, 'secret' => $secret ), home_url( '/' ) ) : '';
		$bot     = $this->val( $s, 'auth', 'bale_bot' );
		?>
		<div class="hsw-admin-card">
			<h2>ورود با کد یکبارمصرف</h2>
			<p class="hsw-admin-desc">ورود و ثبت‌نام با شماره موبایل از طریق بله (رایگان) یا واتساپ. کدها ۶ رقمی، یکبارمصرف و دارای محدودیت تلاش هستند.</p>
			<div class="hsw-admin-grid">
				<?php $this->switch_field( $s, 'auth', 'otp_enabled', 'فعال‌سازی ورود با کد یکبارمصرف', 'در صورت فعال بودن، فرم ورود سریع در کنار فرم رمز عبور نمایش داده می‌شود.' ); ?>
				<?php $this->switch_field( $s, 'auth', 'otp_login', 'اجازهٔ ورود با کد' ); ?>
				<?php $this->switch_field( $s, 'auth', 'otp_register', 'اجازهٔ ثبت‌نام با کد' ); ?>
				<?php $this->switch_field( $s, 'auth', 'otp_2fa', 'امکان ورود دوعاملی برای کاربران' ); ?>
				<?php $this->switch_field( $s, 'auth', 'otp_bale', 'فعال‌سازی کانال بله' ); ?>
				<?php $this->switch_field( $s, 'auth', 'otp_whatsapp', 'فعال‌سازی کانال واتساپ' ); ?>
				<?php $this->select_field( $s, 'auth', 'otp_default_channel', 'کانال پیش‌فرض', array( 'bale' => 'بله', 'whatsapp' => 'واتساپ' ) ); ?>
			</div>
		</div>

		<div class="hsw-admin-card">
			<h2>ربات بله (رایگان)</h2>
			<p class="hsw-admin-desc">یک ربات در بله بسازید، توکن را از BotFather بله دریافت کنید و آدرس وبهوک زیر را در پنل ربات ثبت کنید. کاربر برای دریافت کد باید یک‌بار ربات را باز کند و شماره خود را ارسال کند.</p>
			<div class="hsw-admin-grid">
				<?php $this->text_field( $s, 'auth', 'bale_bot', 'نام کاربری ربات (بدون @)', 'مثلاً hamishop_bot' ); ?>
				<?php $this->text_field( $s, 'auth', 'bale_token', 'توکن ربات بله', 'توکن را در صفحهٔ عمومی قرار ندهید.' ); ?>
				<?php $this->text_field( $s, 'auth', 'bale_secret', 'کلید امنیتی وبهوک' ); ?>
			</div>
			<?php if ( $webhook ) : ?>
				<p><strong>آدرس وبهوک بله:</strong></p>
				<code class="hsw-code"><?php echo esc_html( $webhook ); ?></code>
				<p class="description">این آدرس را در متد setWebhook ربات بله تنظیم کنید. اگر آدرس سایت HTTPS نیست، بله ممکن است وبهوک را نپذیرد.</p>
			<?php else : ?>
				<p class="hsw-notice">پس از ذخیرهٔ تنظیمات، کلید امنیتی و آدرس وبهوک ساخته می‌شود.</p>
			<?php endif; ?>
			<?php if ( $bot ) : ?>
				<p class="description">لینک ربات: <code>https://ble.ir/<?php echo esc_html( $bot ); ?></code></p>
			<?php endif; ?>
		</div>

		<div class="hsw-admin-card">
			<h2>واتساپ</h2>
			<p class="hsw-admin-desc">واتساپ رسمی متا رایگان نیست و به قالب/توکن نیاز دارد. برای استفادهٔ رایگان، یک پل self-hosted (مانند WPPConnect یا Baileys) روی سرور خود راه‌اندازی کنید و آدرس آن را اینجا وارد کنید. پل باید JSON شامل phone و message را بپذیرد و در صورت موفقیت کد 2xx برگرداند.</p>
			<div class="hsw-admin-grid">
				<?php $this->select_field( $s, 'auth', 'whatsapp_provider', 'نوع اتصال', array( 'cloud' => 'WhatsApp Cloud API (متا)', 'bridge' => 'پل self-hosted (رایگان)' ) ); ?>
				<?php $this->text_field( $s, 'auth', 'whatsapp_phone_id', 'Phone Number ID (Cloud API)' ); ?>
				<?php $this->text_field( $s, 'auth', 'whatsapp_token', 'Access Token (Cloud API)' ); ?>
				<?php $this->text_field( $s, 'auth', 'whatsapp_bridge_url', 'آدرس پل واتساپ', '', 'url' ); ?>
				<?php $this->text_field( $s, 'auth', 'whatsapp_bridge_token', 'توکن پل' ); ?>
				<?php $this->text_field( $s, 'auth', 'whatsapp_sender', 'شمارهٔ فرستنده (اختیاری)' ); ?>
			</div>
			<p class="description">در حالت Cloud API، کاربر باید پیش از دریافت پیام با شمارهٔ شما تعامل کرده باشد یا قالب تأییدشده داشته باشید. برای ورود عمومی، پل self-hosted کنترل بیشتری می‌دهد.</p>
		</div>

		<div class="hsw-admin-card">
			<h2>کد و پیام</h2>
			<div class="hsw-admin-grid">
				<?php $this->number_field( $s, 'auth', 'otp_ttl', 'اعتبار کد (ثانیه)', 60, 900 ); ?>
				<?php $this->number_field( $s, 'auth', 'otp_resend', 'فاصلهٔ ارسال دوباره (ثانیه)', 20, 900 ); ?>
				<?php $this->number_field( $s, 'auth', 'otp_max_attempts', 'حداکثر تلاش برای هر کد', 1, 20 ); ?>
			</div>
			<?php $this->textarea_field( $s, 'auth', 'otp_message', 'متن پیام کد', 'از {code} برای کد و {site} برای نام سایت استفاده کنید.' ); ?>
		</div>
		<?php
	}

	private function tab_design( $s ) {
		$palette_url = admin_url( 'admin.php?page=hamishop-palette' );
		$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
		$palette     = class_exists( 'HSW_Palette' ) ? HSW_Palette::get() : array();
		$tokens      = class_exists( 'HSW_Palette' ) ? HSW_Palette::tokens() : array();
		?>
		<div class="hsw-admin-card">
			<h2>پالت رنگی سراسری</h2>
			<p class="hsw-admin-desc">رنگ‌های پنل حساب کاربری از <strong>HamiShop ← پالت رنگی</strong> خوانده می‌شوند؛ با یک‌بار تغییر پالت، فروشگاه و پنل حساب با هم هماهنگ می‌مانند. فیلدهای رنگ جداگانهٔ این تب حذف شدند تا دو پالت ناهماهنگ نداشته باشیم.</p>
			<p style="display:flex;gap:8px;flex-wrap:wrap;margin:14px 0 4px">
				<a class="button button-primary" href="<?php echo esc_url( $palette_url ); ?>">ویرایش پالت رنگی سراسری</a>
				<a class="button" href="<?php echo esc_url( $account_url ); ?>" target="_blank" rel="noopener">مشاهدهٔ پنل حساب</a>
			</p>
		</div>

		<div class="hsw-admin-card">
			<h2>رنگ‌های فعلی</h2>
			<p class="hsw-admin-desc">این مقادیر فقط نمایشی‌اند و از پالت سراسری خوانده شده‌اند.</p>
			<div class="hsw-pal-preview">
				<?php foreach ( $palette as $key => $hex ) : ?>
					<span class="hsw-pal-chip">
						<i style="background:<?php echo esc_attr( $hex ); ?>"></i>
						<b><?php echo esc_html( isset( $tokens[ $key ][0] ) ? $tokens[ $key ][0] : $key ); ?></b>
						<code><?php echo esc_html( $hex ); ?></code>
					</span>
				<?php endforeach; ?>
			</div>
			<p class="description">برای تغییر هر رنگ، روی «ویرایش پالت رنگی سراسری» بزنید؛ همان لحظه در فروشگاه، سبد خرید و پنل حساب اعمال می‌شود.</p>
		</div>
		<style>
			.hsw-pal-preview{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;margin:12px 0 6px}
			.hsw-pal-chip{display:flex;align-items:center;gap:8px;border:1px solid #e6ebf2;border-radius:10px;padding:8px 10px;background:#fff;font-size:12px}
			.hsw-pal-chip i{width:22px;height:22px;flex:0 0 22px;border-radius:6px;border:1px solid rgba(0,0,0,.08)}
			.hsw-pal-chip b{flex:1;min-width:0;font-weight:600;color:#0f2740;font-size:11.5px;line-height:1.5}
			.hsw-pal-chip code{direction:ltr;font-size:10.5px;color:#64748b}
		</style>
		<?php
	}

	private function tab_shortcode( $s ) {
		$account_id  = wc_get_page_id( 'myaccount' );
		$account_url = wc_get_page_permalink( 'myaccount' );
		$content     = $account_id ? get_post_field( 'post_content', $account_id ) : '';
		$native      = $content && has_shortcode( $content, 'woocommerce_my_account' );
		$otp_enabled = 'yes' === $this->val( $s, 'auth', 'otp_enabled' );
		$sms_active  = HSW_Account::instance()->sms_login_active();
		?>
		<div class="hsw-admin-card">
			<h2>شورت‌کد پنل حساب</h2>
			<p class="hsw-admin-desc">این شورت‌کد را در برگهٔ حساب کاربری ووکامرس یا هر برگهٔ دیگری قرار دهید. ویجت «پنل حساب کاربری HamiShop» در المنتور دقیقاً همین خروجی را می‌سازد.</p>
			<code class="hsw-code" id="hsw-account-shortcode">[hamishop_account]</code>
			<button type="button" class="button" data-hsw-copy="#hsw-account-shortcode">کپی شورت‌کد</button>
			<p class="description">پارامترهای اختیاری: <code>layout="left|right"</code>، <code>show_header="yes|no"</code>، <code>show_menu="yes|no"</code>، <code>show_stats="yes|no"</code>، <code>hide="downloads,wallet"</code>، <code>class="..."</code></p>
			<p class="description">با <code>hide</code> می‌توانید هر بخش را مستقیم از شورت‌کد مخفی کنید: <code>downloads</code>، <code>orders</code>، <code>address</code>، <code>wishlist</code>، <code>wallet</code>، <code>support</code>، <code>notifications</code>، <code>payments</code>، <code>account</code>.</p>
			<p class="description">اگر افزونهٔ ورود پیامکی فعال باشد و ورود با کد HamiShop روشن نباشد، فرم ورود و ثبت‌نام این پنل به‌صورت خودکار نمایش داده نمی‌شود و ورود به‌طور کامل به افزونهٔ پیامکی واگذار می‌شود.</p>
			<p class="description">برای فعال‌کردن ورود/ثبت‌نام با بله یا واتساپ به تب <strong>ورود و امنیت</strong> بروید. بله رایگان است؛ برای واتساپ به Meta Cloud API یا پل self-hosted نیاز دارید.</p>
		</div>
		<div class="hsw-admin-card">
			<h2>وضعیت صفحهٔ حساب</h2>
			<ul class="hsw-status-list">
				<li><span>برگهٔ حساب ووکامرس</span><a href="<?php echo esc_url( $account_url ); ?>" target="_blank" rel="noopener">مشاهده</a></li>
				<li><span>شناسهٔ برگه</span><code><?php echo esc_html( HSW_Helpers::fa_num( $account_id ) ); ?></code></li>
				<li><span>وضعیت شورت‌کد</span><code><?php echo $native ? 'شورت‌کد پیش‌فرض ووکامرس' : 'آماده'; ?></code></li>
				<li><span>ورود پیامکی</span><code><?php echo $otp_enabled ? 'HamiShop OTP فعال' : ( $sms_active ? 'فعال — فرم پنل غیرفعال است' : 'غیرفعال / شناسایی نشد' ); ?></code></li>
				<li><span>ورود با بله / واتساپ</span><code><?php echo $otp_enabled ? 'فعال' : 'غیرفعال'; ?></code></li>
				<li><span>تیکت‌های پشتیبانی</span><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=hsw_ticket' ) ); ?>">مدیریت تیکت‌ها</a></li>
			</ul>
			<?php if ( $native ) : ?>
				<p class="hsw-notice">در برگهٔ حساب فعلی، شورت‌کد <code>[woocommerce_my_account]</code> قرار دارد. برای استفاده از طراحی جدید، آن را با <code>[hamishop_account]</code> جایگزین کنید یا برگه را با ویجت المنتور بسازید. تا زمانی که این کار انجام نشود، منوی سفارشی و اندپوینت‌های تیکت/اعلان به صفحهٔ بومی ووکامرس هم اضافه می‌شوند.</p>
				<a class="button button-primary" href="<?php echo esc_url( get_edit_post_link( $account_id ) ); ?>">ویرایش برگهٔ حساب</a>
			<?php endif; ?>
		</div>
		<div class="hsw-admin-card">
			<h2>راهنمای ویجت المنتور</h2>
			<p>در ویرایشگر المنتور، دستهٔ «حامی — پنل حساب کاربری» را باز کنید و ویجت «پنل حساب کاربری HamiShop» را بکشید. تب Content برای هدر، منوی حساب (فعال/غیرفعال کردن دانلودها، سفارش‌ها و...)، بخش‌ها، فرم ورود و پیش‌نمایش است؛ تب Style شامل پالت رنگ، چیدمان و تایپوگرافی است.</p>
			<p>اگر از بلوک شورت‌کد استفاده می‌کنید، برای شخصی‌سازی سریع همان پارامتر <code>hide</code> را به شورت‌کد اضافه کنید؛ برای کنترل کامل بصری، ویجت المنتور را جایگزین بلوک شورت‌کد کنید.</p>
		</div>
		<script>
			document.addEventListener('click', function (e) {
				var btn = e.target.closest('[data-hsw-copy]');
				if (!btn) return;
				var target = document.querySelector(btn.getAttribute('data-hsw-copy'));
				if (!target) return;
				if (navigator.clipboard) navigator.clipboard.writeText(target.textContent.trim());
				btn.textContent = 'کپی شد';
			});
		</script>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * User profile fields
	 * ------------------------------------------------------------------ */

	public function user_fields( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}
		$balance = (float) get_user_meta( $user->ID, '_hsw_wallet_balance', true );
		wp_nonce_field( 'hsw_account_user_' . $user->ID, 'hsw_account_user_nonce' );
		?>
		<h2>حساب HamiShop</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="hsw-wallet-balance">موجودی کیف پول</label></th>
				<td>
					<input type="number" step="0.01" min="0" class="regular-text" id="hsw-wallet-balance" name="hsw_wallet_balance" value="<?php echo esc_attr( number_format( $balance, 2, '.', '' ) ); ?>">
					<p class="description">موجودی کیف پول مشتری در پنل حساب نمایش داده می‌شود. برای تنظیم موجودی از این فیلد استفاده کنید.</p>
				</td>
			</tr>
			<tr>
				<th>اعلان‌ها و تیکت‌ها</th>
				<td>
					<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=hsw_ticket&author=' . $user->ID ) ); ?>">مشاهده تیکت‌های این کاربر</a>
					<p class="description">اعلان‌های داخلی در پروفایل کاربر ذخیره می‌شوند و از صفحهٔ اعلان‌ها قابل مشاهده‌اند.</p>
				</td>
			</tr>
		</table>
		<?php
	}

	public function save_user_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		if ( ! isset( $_POST['hsw_account_user_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hsw_account_user_nonce'] ) ), 'hsw_account_user_' . $user_id ) ) {
			return;
		}
		if ( isset( $_POST['hsw_wallet_balance'] ) ) {
			$balance = (float) wc_format_decimal( wp_unslash( $_POST['hsw_wallet_balance'] ) );
			update_user_meta( $user_id, '_hsw_wallet_balance', max( 0, $balance ) );
		}
	}

}

