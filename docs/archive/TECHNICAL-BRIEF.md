# HamiShop Elementor Widgets — بریف فنی

> **نسخهٔ سند:** 1.11.171 · **نسخهٔ افزونه:** 1.11.170
> این سند «نقطهٔ شروع» برای هر برنامه‌نویسی است که می‌خواهد این افزونه را بخواند، تغییر بدهد یا توسعه دهد.
> جزئیات تک‌تک ویجت‌ها در `WIDGETS-REFERENCE.md` و `WIDGET-GUIDE.md` آمده است؛ اینجا معماری، مدل داده، جریان‌ها و قواعد طراحی توضیح داده می‌شود.

---

## ۱) در یک نگاه

| مورد | مقدار |
|---|---|
| نام / پیشوند | HamiShop Elementor Widgets · پیشوند کد `HSW_` · پیشوند ویجت‌ها `hsw-` |
| نیازمندی‌ها | WordPress ≥ 6.0 · PHP ≥ 7.4 · WooCommerce (تست‌شده تا 11.2.1) · Elementor (تست‌شده تا 4.3.5) |
| اندازه | ۱۲۶ فایل PHP · ~۵۲٬۷۰۰ خط · ۴۹ کلاس · ۶ trait · ۶۲ ویجت |
| دارایی‌ها | ۱۹ فایل CSS (۴۶۷ KB کمینه) · ۹ فایل JS (۱۵۶ KB کمینه) — همه لوکال، بدون CDN |
| دامنهٔ متن | `hamishop-elementor-widgets` (+ سه دامنهٔ ادمین/ترجمهٔ جداگانه) |
| REST | ۱۹ مسیر در namespace `hsw/v1` |
| تست | ۵۱ اسکریپت در `tests/` (هارنس PHP + پروب‌های Playwright) |
| مجوز | GPL-2.0-or-later |

**فلسفهٔ افزونه در یک جمله:** هر چیزی که لازم نیست، بارگذاری نمی‌شود؛ هر رنگ و اندازه‌ای که کاربر نهایی می‌بیند، از یک منبع واحد (پالت سراسری) می‌آید و ویجت می‌تواند آن را برای خودش بازنویسی کند.

---

## ۲) قواعد ثابت (اگر این‌ها را بشکنید، معماری می‌شکند)

1. **بدون enqueue سراسری.** `HSW_Assets` فقط `wp_register_*` می‌کند؛ هر ویجت با `get_style_depends()` / `get_script_depends()` اعلام می‌کند چه می‌خواهد. صفحه‌ای که ویجت فروشگاهی ندارد، CSS فروشگاه را هم نمی‌گیرد.
2. **هیچ کتابخانهٔ بیرونی.** نه jQuery UI، نه Swiper، نه mPDF، نه فونت CDN. JS همه vanilla و CSS همه دست‌نویس است.
3. **توکن قبل از استایل.** رنگ‌ها از `HSW_Palette` به‌صورت CSS Custom Property روی `html:root` منتشر می‌شوند. CSS ویجت‌ها **فقط** `var(--hsw-…)` می‌خوانند؛ هیچ رنگ هاردکد‌شده‌ای در ویجت تازه اضافه نمی‌شود.
4. **آبشار سه‌لایه‌ای استایل:** پالت (سراسری) → کنترل ویجت در المنتور (فقط همان ویجت) → کلاس/متغیر دستی روی المنت. لایهٔ بالاتر همیشه می‌برد.
5. **بدون کوئری بی‌دلیل.** فهرست‌های پرتکرار (لوپ، زیرمنوی هدر) با transient + نسخهٔ کاتالوگ کش می‌شوند و `_prime_post_caches()` می‌شوند.
6. **امنیت پیش‌فرض:** هر endpoint عمومی nonce یا امضای HMAC دارد؛ هر خروجی escape می‌شود؛ هر ورودی قبل از رسیدن به کوئری سانیتایز می‌شود.
7. **فارسی‌محور:** اعداد فارسی، تاریخ شمسی و RTL در سطح helper انجام می‌شود، نه در هر ویجت.

---

## ۳) نقشهٔ پوشه‌ها

```
hamishop-elementor-widgets.php   ← هدر افزونه، بوت، محافظ وابستگی‌ها، هوک فعال/غیرفعال
uninstall.php                    ← پاک‌سازی کامل هنگام حذف افزونه
includes/
  class-hsw-plugin.php           ← هسته: require همهٔ کلاس‌ها + ساخت نمونه‌ها + ثبت ویجت‌ها
  class-hsw-widget-base.php      ← پایهٔ همهٔ ویجت‌ها (کنترل‌های مشترک، helperهای رندر)
  class-hsw-header-base.php      ← پایهٔ هدرها (برند/منو/جستجو/دراور/شیت موبایل)
  class-hsw-archive-base.php     ← پایهٔ آرشیو (پارامترها، filter_url، ترم‌ها، شمارش)
  class-hsw-product-base.php     ← پایهٔ صفحهٔ محصول
  class-hsw-cart-base.php / checkout-base / pay-base / complete-base / blog-base
  class-hsw-widget-manager.php   ← کاتالوگ ۶۲ ویجت + صفحهٔ روشن/خاموش + فیچرها
  class-hsw-palette.php          ← تنها منبع توکن‌های رنگ (html:root)
  class-hsw-assets.php           ← ثبت شرطی CSS/JS، نسخه‌گذاری، min/dev
  class-hsw-helpers.php          ← اعداد فارسی، تاریخ، لوپ، آیکن‌ها، preload تصویر
  class-hsw-jalali.php           ← تبدیل شمسی بدون کتابخانه
  class-hsw-icon-*.php           ← زیرمجموعهٔ آیکن SVG (فقط آیکن‌های مصرف‌شده)
  class-hsw-products.php         ← فهرست‌های سبک محصول (ID + کش)
  class-hsw-archive-query.php    ← ساخت WP_Query فیلترها
  class-hsw-rest.php / checkout-rest.php / checkout-complete.php  ← REST
  class-hsw-cart-actions.php / cart-drawer.php                    ← AJAX سبد
  class-hsw-account*.php         ← پنل مشتری (پوسته، احراز هویت، پشتیبانی، ادمین)
  class-hsw-wallet*.php          ← کیف پول و درگاه داخلی
  class-hsw-free-shipping.php / coupon-messages.php / composite.php
  class-hsw-security.php / request-blocker.php / throttle.php / cache-guard.php / login.php
  class-hsw-invoice.php          ← فاکتور چاپی A4 + برچسب پستی
  class-hsw-telegram-poller.php  ← ورکر Polling تلگرام (برای هاست‌های بدون وبهوک)
  trait-hsw-style-kit.php        ← helperهای کنترل استایل (گروه‌ها، سایز، رنگ)
widgets/                          ← ۶۲ ویجت؛ هر فایل یک کلاس
assets/css|js|fonts|img/          ← دارایی‌ها + نسخهٔ `.min` (ساختهٔ tools/build-assets.py)
tools/                            ← ساخت دارایی، POT، اسپرایت آیکن، فونت، ZIP انتشار
tests/                            ← هارنس PHP + پروب‌های Playwright
languages/                        ← فایل POT
```

---

## ۴) چرخهٔ بوت

```
hamishop-elementor-widgets.php
 ├─ بررسی PHP/WP/WooCommerce/Elementor → نبودِ هرکدام: نوتیس + توقف امن
 ├─ define( HSW_VERSION, HSW_DIR, HSW_URL )
 └─ add_action( 'plugins_loaded', 'hsw_boot', 20 )
      └─ HSW_Plugin::instance()
           ├─ includes()                 ← require_once همهٔ کلاس‌ها (بدون اتولودر سنگین)
           ├─ new HSW_Helpers / HSW_Products
           ├─ HSW_Account::instance()     ← پوستهٔ پنل مشتری + auth + support + admin
           ├─ new HSW_Widget_Manager       ← کاتالوگ + روشن/خاموش
           ├─ HSW_Palette / Branding / Security / Cache_Guard / Throttle / Wallet
           ├─ new HSW_404 / Pagination / Rebrand / Request_Blocker / Login
           ├─ new HSW_Telegram_Poller      ← تنها یک isset($_GET) وقتی خاموش است
           ├─ new HSW_Invoice              ← دکمه‌های ادمین + مسیر چاپ
           ├─ new HSW_Rest / HSW_Assets
           └─ add_action( 'elementor/widgets/register', register_widgets )
```

`register_widgets()` فقط ویجت‌های **فعال** در `hsw_enabled_widgets` را ثبت می‌کند؛ بقیه حتی کلاس‌شان لود نمی‌شود.

---

## ۵) موجودی کلاس‌ها (به تفکیک مسئولیت)

| لایه | کلاس‌ها | مسئولیت |
|---|---|---|
| هسته | `HSW_Plugin`, `HSW_Assets`, `HSW_Widget_Manager`, `HSW_Palette`, `HSW_Branding` | بوت، ثبت شرطی دارایی، کاتالوگ ویجت، توکن رنگ، هویت فروشگاه |
| پایهٔ ویجت | `HSW_Widget_Base`, `HSW_Header_Base`, `HSW_Archive_Base`, `HSW_Product_Base`, `HSW_Cart_Base`, `HSW_Checkout_Base`, `HSW_Pay_Base`, `HSW_Complete_Base`, `HSW_Blog_Base` | کنترل‌های مشترک، helperهای رندر، اعلام CSS/JS |
| کاتالوگ | `HSW_Products`, `HSW_Archive_Query`, `HSW_Header_Cascade` | فهرست ID با کش، WP_Query فیلترها، شمارش ترم‌ها با زیرشاخه |
| تجارت | `HSW_Cart_Actions`, `HSW_Cart_Drawer`, `HSW_Checkout_Rest`, `HSW_Checkout_Complete`, `HSW_Free_Shipping`, `HSW_Coupon_Messages`, `HSW_Composite` | AJAX سبد، دراور، REST تسویه، تکمیل سفارش، ارسال رایگان، پیام کوپن، محصول ترکیبی |
| پنل مشتری | `HSW_Account` (+ `_Auth`, `_Admin`, `_Support`), `HSW_Wallet`, `HSW_Wallet_Pay`, `HSW_Wallet_Topup`, `HSW_Email_Verify` | پوستهٔ حساب، OTP/2FA/تلگرام، تیکت، کیف پول و درگاه داخلی، تأیید ایمیل |
| امنیت | `HSW_Security`, `HSW_Request_Blocker`, `HSW_Throttle`, `HSW_Cache_Guard`, `HSW_Login` | مسیر مخفی ادمین، بلاک درخواست مخرب، محدودیت نرخ، دور زدن کش، صفحهٔ ورود برندشده |
| محتوا | `HSW_Jalali`, `HSW_404`, `HSW_Pagination`, `HSW_Rebrand`, `HSW_Helpers` | تاریخ شمسی، ۴۰۴ اختصاصی، صفحه‌بندی، وایت‌لیبل، helperهای عمومی |
| ابزار ادمین | `HSW_Invoice`, `HSW_Telegram_Poller` | فاکتور چاپی، ورکر تلگرام |
| traitها | `HSW_Style_Kit`, `HSW_Product_Page_Style(+parts 1..4)` | ساخت کنترل‌های استایل بدون تکرار |

---

## ۶) سیستم ویجت

**سه نوع ویجت وجود دارد:**

1. **ویجت صفحهٔ کامل** (`hsw-product-page`, `hsw-cart-page`, `hsw-checkout-page`, `hsw-pay-page`, `hsw-complete-page`, `hsw-archive-page`, `hsw-blog-page`, `hsw-post-page`): یک صفحه را با تمام بخش‌ها می‌سازد.
2. **ویجت بخش** (`hsw-product-gallery`, `hsw-cart-summary`, `hsw-archive-filters`, …): یک بلوک از صفحه؛ برای وقتی کاربر چیدمان را خودش می‌سازد.
3. **ویجت سراسری** (`hsw-header-store`, `hsw-header-minimal`, `hsw-footer-sticky`): معمولاً در قالب هدر/فوتر المنتور.

**الگوی ساخت یک ویجت جدید:**

```php
class HSW_Widget_My_Thing extends HSW_Product_Base {   // یا Widget_Base ساده
	public function get_name() { return 'hsw-my-thing'; }
	public function get_title() { return __( '…', 'hamishop-elementor-widgets' ); }
	public function get_style_depends()  { return array( 'hsw-product' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$this->content_brand();          // کنترل‌های آمادهٔ پایه
		$this->start_controls_section( 'sec_x', array( 'label' => __( '…', 'hamishop-elementor-widgets' ) ) );
		$this->a_color( 'x_bg', __( 'پس‌زمینه', … ), '{{WRAPPER}} .hsw-x', 'background-color' );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->effective_settings();   // تنظیمات ذخیره‌شده + پیش‌فرض‌ها
		echo $this->open_root( 'thing', $s );
		echo '<div class="hsw-x">' . esc_html( $s['title'] ?? '' ) . '</div>';
		echo '</div>';
	}
}
```
سپس: فایل در `widgets/`، ثبت در `HSW_Widget_Manager::catalog()`، افزودن به `HSW_Plugin::register_widgets()`، و اجرای `python tools/build-assets.py` + `python tools/build-pot.py`.

**قواعد رندر:** همهٔ ویجت‌ها `effective_settings()` دارند (ذخیره‌شده ← پیش‌فرض ← پالت)، در ادیتور `is_demo()` دادهٔ نمونه نشان می‌دهند و در فرانت‌اند اگر زمینه فراهم نباشد (مثلاً صفحهٔ محصول نبودن) چیزی رندر نمی‌کنند.

---

## ۷) توکن‌ها و آبشار استایل

```
HSW_Palette::css()  →  html:root { --hsw-primary, --hsw-accent, --hsw-surface, … }
                     →  --hsw-loop-accent, --hsw-pal-on-accent, --hsw-pal-ink …  (تاریخ‌گذاری‌شده برای لوپ)
ویجت (Elementor)     →  {{WRAPPER}} { --hsw-loop-accent: #7c3aed }   ← فقط همان ویجت
المنت دستی           →  style="--hsw-loop-accent:#0a0"              ← همیشه برنده
```

CSS ویجت‌ها همیشه با `var(--x, fallback)` نوشته می‌شود تا نصب‌های قدیمی که توکن جدید ندارند نشکنند. قاعدهٔ `:where()` برای صفر کردن specificity پایه به‌کار رفته تا کنترل ویجت بدون `!important` برنده شود.

**استثناها که آگاهانه `!important` دارند:** فقط جاهایی که خود المنتور با CSS per-element (چهار کلاس) رقابت می‌کند؛ مثل `justify-content` هدر در موبایل. هر مورد در کد کامنت دارد.

**فاکتور چاپی:** CSS جداگانهٔ `hsw-invoice.css` که خودش توکن تعریف می‌کند (`--hsw-inv-*`) و فقط روی مسیر `?hsw_invoice=` لود می‌شود؛ عمداً به CSS سایت وابسته نیست تا قالب نتواند برگهٔ چاپ را به‌هم بزند.

---

## ۸) دارایی‌ها و بارگذاری شرطی

| خانواده | CSS (min) | JS (min) | ویجت‌ها |
|---|---|---|---|
| fonts (پایه) | 15.6 KB | — | همه (فقط یک‌بار) |
| header | 52.7 KB | 26.0 KB | 2 |
| product | 59.4 KB | 30.0 KB | 13 |
| archive | 41.3 KB | 16.9 KB | 8 |
| cart | 29.9 KB | 3.0 KB | 6 |
| checkout | 64.4 KB | 18.0 KB | 8 |
| pay | — (روی checkout سوار است) | — | 6 |
| complete | 10.2 KB | — | 6 |
| loop | 21.1 KB (+deal 1.4) | 8.9 KB | 3 |
| blog / post | 13.6 / 20.6 KB | — / 5.7 KB | 8 + 1 |
| account | 100.4 KB | 37.8 KB | 1 (+ پنل) |
| invoice | 11.7 KB | — | مسیر چاپ |
| ادمین | 11.2 + 8 + 3 KB | — | فقط صفحات HamiShop |

`tools/build-assets.py` نسخهٔ `.min` را می‌سازد و `SCRIPT_DEBUG` تعیین می‌کند کدام لود شود. نسخه‌گذاری با `HSW_VERSION` است تا کش خراب نشود.

---

## ۹) مدل داده

**گزینه‌ها (`wp_options`)** — همه با پیشوند `hsw_`:
`hsw_account_settings` (تنظیمات پنل مشتری: OTP، تلگرام، واتساپ، کیف پول، پشتیبانی)، `hsw_palette`, `hsw_brand`, `hsw_enabled_widgets`, `hsw_security`, `hsw_invoice_enabled`, `hsw_invoice_mono`, `hsw_tg_offset`, `hsw_tg_poll_ready(_hash)`, `hsw_telegram_contacts`, `hsw_db_version`, `hsw_loop_version`, `hsw_oos_last`, `hsw_jalali_on`, `hsw_free_shipping_on|min`, `hsw_custom_404_enabled` + `hsw_404_*`, `hsw_complete_page_id`, `hsw_express_slug`, `hsw_add_feedback`, `hsw_asset_version`, `hsw_htaccess_backup`, `hsw_last_error` و پرچم‌های مهاجرت.

**جدول‌ها:**
| جدول | کاربرد |
|---|---|
| `{prefix}hsw_throttle` | شمارنده‌های نرخ (OTP، ورود) — نوشتن اتمیک |
| `{prefix}hsw_wallet` | موجودی هر کاربر، `PRIMARY KEY (user_id)` + `points_used`. منبع حقیقت؛ متای `_hsw_wallet_balance` فقط snapshot پیش از مهاجرت است. |
| `{prefix}hsw_wallet_meta_backup` | کپی خام هر ردیف `_hsw_wallet_balance` پیش از مهاجرت (برای rollback/بازبینی) |
| `{prefix}hsw_wallet_ledger` | دفتر تراکنش‌های کیف پول، با `UNIQUE KEY (ref)` برای idempotency (هنگام حذف افزونه **عمداً** پاک نمی‌شود) |

**پست‌تایپ:** `hsw_ticket` (تیکت پشتیبانی، خصوصی، بدون آرشیو عمومی) + کامنت‌های `hsw_ticket_reply`.
**متا:** `_hsw_ticket_status`, `_hsw_ticket_priority`, `_hsw_ticket_department`, `_hsw_ticket_unread_*`, `_hsw_telegram_chat_id`, `_hsw_phone`, `_hsw_2fa_*`, `_hsw_email_verified`, `_hsw_initial_stock`.
**ترنزینت‌ها:** `hsw_lp_*` (فهرست محصول)، `hsw_otp_*`، `hsw_telegram_link_*`، `hsw_tg_lock`، `hsw_cap_*`، کش هدر.
**کران:** `hsw_ticket_cleanup` (روزانه)، `hsw_tg_poll` (هر دقیقه، فقط وقتی Polling روشن است). `hsw_daily_cleanup` / `hsw_wallet_db` فقط برای نصب‌های قدیمی در uninstall پاک می‌شوند.

---

## ۱۰) جریان‌های کلیدی

### ۱۰.۱ ویجت لوپ (شگفت‌انگیز/پرفروش/محبوب)
```
render → settings_to_args() → HSW_Products::load_ids( args )
        ├─ transient hsw_lp_<hash>؟ → استفاده
        └─ نه؟ wc_get_products(fields=ids, status=publish) → _prime_post_caches() → ذخیره
      → واکر کارت (بدون کوئری اضافه) → preload یک تصویر + sizes داینامیک
      → تایمر (data-hsw-end) + استوک بار (baseline از _hsw_initial_stock)
```

### ۱۰.۲ آرشیو و فیلترها
```
?s=…&filter_pa_color=black&instock=1
  ├─ HSW_Archive_Base::current_params()  ← سانیتایز همهٔ $_GET، حذف hsw_*
  ├─ HSW_Archive_Query → WP_Query (tax_query + meta_query + price)
  ├─ HSW_Archive_Base::filter_url( changes, base )  ← ساخت همهٔ لینک‌ها (چیپ، ×، حذف همه، دسته)
  └─ لینک دسته = get_term_link + پارامترهای فعال (فیلترها در ناوبری گم نمی‌شوند)
```

### ۱۰.۳ تسویه حساب و پرداخت
```
checkout (`/checkout/` REST hsw/v1/checkout/*)
  ├─ address / shipping / schedule / note / coupon / payment  (هر کدام nonce + session)
  └─ POST /checkout/complete → WC()->checkout()->create_order() → process_payment()
        ├─ درگاه بانکی → redirect
        └─ کیف پول/COD → mark paid → خالی‌کردن سبد → redirect /order-complete/?order=…
/order-complete/ (مسیر اختصاصی) → HSW_Complete_Base + ویجت‌های کامل
```

### ۱۰.۴ پنل مشتری
```
/my-account/  → HSW_Account (پوستهٔ کامل، جایگزین تمپلیت ووکامرس)
  ├─ کیف پول: topup (درگاه/کارت‌به‌کارت) → ledger → pay
  ├─ پشتیبانی: ticket → reply/close → اعلان + ایمیل (+ inbound webhook)
  ├─ ورود: OTP ایمیل/تلگرام/واتساپ/پیامک + 2FA + تأیید ایمیل
  └─ سفارش‌ها: view-order → دکمهٔ «چاپ فاکتور / برچسب پستی»
```

### ۱۰.۵ فاکتور چاپی
```
?hsw_invoice=<id>&key=<nonce>
  ├─ authorize(): مالک سفارش یا edit_shop_orders/manage_woocommerce
  ├─ document(): لوگو + متا + چارت پستی + اقلام + جمع + حروف + مهر/امضا
  ├─ تراکم خودکار: >10 dense، >18 tight، >32 ultra  → همیشه ۱ برگهٔ A4
  └─ بدون نوشتن فایل: خروجی HTML + یک CSS؛ PDF با Save as PDF مرورگر
```

### ۱۰.۶ ورکر تلگرام (بدون وبهوک)
```
cron (هر دقیقه) یا GET /?hsw_tg_poll=1&secret=…
  ├─ lock ۴۵ ثانیه‌ای (transient)
  ├─ deleteWebhook یک‌بار per token (وگرنه getUpdates → 409)
  ├─ اولین اجرا: offset=-1 → فقط جدیدترین (صف قدیمی پردازش نمی‌شود)
  ├─ getUpdates?offset&limit=10&timeout=0
  ├─ هر update → HSW_Account_Auth::handle_telegram_update()  ← همان متد وبهوک
  └─ ذخیرهٔ offset در آپشن غیراوتولود
```

---

## ۱۱) امنیت

| موضوع | پیاده‌سازی |
|---|---|
| Nonce | همهٔ فرم‌ها/دکمه‌های تغییر‌دهنده؛ فاکتور: `hsw_invoice_<id>` |
| دسترسی | `current_user_can()` برای ادمین، مالکیت سفارش برای مشتری، `check_ajax_referer()` برای AJAX |
| ورودی | `sanitize_*` + `wp_unslash` + `absint` + allow-list برای کلیدها (مثل `filter_*`) |
| خروجی | `esc_html/esc_attr/esc_url/wp_kses_post` — بدون استثنای بدون کامنت phpcs |
| REST | `permission_callback` + nonce؛ مسیرهای عمومی فقط خواندنی و کش‌شده |
| HMAC | لینک inbound تیکت با تگ امضاشده (`ticket-inbound`) |
| نرخ | `HSW_Throttle` (جدول اتمیک): OTP per-IP/per-phone، تلاش کد، واتساپ |
| ادمین | مسیر مخفی + قاعدهٔ `htaccess` + بکاپ هنگام فعال‌سازی، پاک‌سازی هنگام غیرفعال‌سازی |
| کش | `DONOTCACHEPAGE` روی همهٔ مسیرهای اختصاصی (فاکتور، ورکر تلگرام، وبهوک) |
| فایل | هیچ آپلود/نوشتن فایل در مسیرهای پرداخت، فاکتور و تلگرام |

---

## ۱۲) کارایی (اندازه‌گیری‌شده، نه ادعا)

| سنجه | مقدار | زمینه |
|---|---|---|
| CLS صفحهٔ لوپ | **0.000** | بعد از `font-display: optional` (قبلاً ۰٫۱۶۷–۰٫۱۸۴) |
| CSS لوپ | 21.1 KB → **4.3 KB gzip** | فقط در صفحه‌ای که ویجت لوپ دارد |
| JS لوپ | 8.9 KB → **3.2 KB gzip** | همان |
| کوئری هر ویجت لوپ (گرم) | **۲ تا ۳** | کش فهرست ID + `_prime_post_caches` |
| صفحهٔ `/shop/` | **۱۹۷ کوئری** با و بدون ورکر تلگرام | تفاوت صفر |
| فاکتور | **۱ صفحه** A4، ۸۵۲ KB PDF | سفارش‌های ۱، ۲۲ و ۲۵ قلمی |
| نوشتن روی دیسک در فاکتور | **۰ فایل** | شمارش `uploads` قبل/بعد: ۳۴۸۴ → ۳۴۸۴ |
| ورکر تلگرام (خاموش) | ۱ `isset($_GET)` | بدون کوئری و بدون درخواست |

منابع سرعت: کش فهرست با نسخهٔ کاتالوگ، بدون jQuery در مسیرهای اصلی، اسپرایت SVG یک‌بار در صفحه و فقط آیکن‌های مصرف‌شده، `srcset/sizes` داینامیک و `fetchpriority` برای تصویر اول، فونت لوکال با `font-display: optional`.

---

## ۱۳) فارسی و بین‌المللی‌سازی

- `HSW_Helpers::fa_num()` — ارقام فارسی؛ `price_num()` داخل قیمت؛ `price_html_fa()` فقط **text node** را تبدیل می‌کند تا HTML `wc_price` نشکند.
- `HSW_Helpers::format_date()` — زنجیرهٔ `wp-parsidate` → `HSW_Jalali` → `wp_date`؛ هیچ‌وقت تاریخ میلادی خام چاپ نمی‌شود.
- `HSW_Jalali` تبدیل بدون کتابخانه انجام می‌دهد (بدون فایل اضافه).
- همهٔ رشته‌ها با دامنهٔ `hamishop-elementor-widgets`؛ `python tools/build-pot.py` فایل POT را از کد بازمی‌سازد (۳٫۲۷۸ رشته).
- جهت‌ها با ویژگی‌های منطقی (`margin-inline`, `inset-inline`) نوشته شده‌اند تا RTL/LTR هر دو درست باشند.

---

## ۱۴) تست و ابزار

```bash
# لینت کل افزونه
for f in $(find . -name '*.php'); do php -l "$f"; done

# هارنس PHP: همهٔ فایل‌ها به‌ترتیب وردپرس لود می‌شوند (یک gate برای fatalهای سطح declaration)
php tests/harness.php          # → files loaded: 117/117 · ALL CLEAN

# بررسی‌های ساختاری (نسخه، دامنهٔ ترجمه، دیباگ، دارایی‌ها، کاتالوگ، مستندات)
php tools/ci-checks.php        # → 16 checks, all passed

# بازسازی POT و نسخهٔ .min
python tools/build-pot.py
python tools/build-assets.py   # نیازمند: pip install csscompressor rjsmin

# بستهٔ انتشار
python tools/build-release.py  # → hamishop-elementor-widgets-<ver>.zip
```

### CI (GitHub Actions) — `.github/workflows/ci.yml`

روی هر push به `main` و هر PR اجرا می‌شود؛ روی تگ `v*` بستهٔ ZIP را هم به‌عنوان artifact می‌سازد. چهار gate:

| Job | چه می‌کند | چرا |
|---|---|---|
| `lint` | `php -l` روی همهٔ فایل‌ها، روی **PHP 7.4، 8.0، 8.1، 8.2، 8.3** | ما «PHP ≥ 7.4» ادعا می‌کنیم؛ یک `str_contains` یا `match` در ۷٫۴ می‌شکند |
| `harness` | `php tests/harness.php` روی ۷٫۴ و ۸٫۲ | fatal‌های سطح declaration (extends/implements/visibility) که lint نمی‌بیند |
| `checks` | `php tools/ci-checks.php` | نسخه‌ها هم‌خوان باشند، دامنهٔ ترجمه غلط نباشد، `var_dump` نماند، هر ویجت در کاتالوگ باشد |
| `generated` | `build-assets.py` + `build-pot.py` و `git status` باید خالی بماند | `.min` و POT کهنه نباید merge شوند |

> پروب‌های Playwright در CI اجرا **نمی‌شوند**؛ آن‌ها به یک وردپرس زنده با ووکامرس نیاز دارند و محلی روی XAMPP اجرا می‌شوند.

**پروب‌های Playwright** (نیازمند `npm i playwright` و XAMPP روشن): هر پروب یک قابلیت را با عدد اثبات می‌کند.

| پروب | چه چیزی را تضمین می‌کند |
|---|---|
| `header-mobile-probe.js` | فاصله‌های یکسان و هم‌ترازی هر دو هدر در ۳۶۰/۳۹۰ |
| `archive-filter-probe.js` / `archive-filter-remove-probe.js` | حذف تکی، حذف همه، ترکیب فیلترها، حفظ فیلتر در ناوبری دسته |
| `loop-spec-probe.js` | preload، sizes، تایمر، دارک، CLS<0.02، رویداد `added_to_cart` |
| `loop-layout-probe.js` | چیدمان کارت‌ها در ۳ عرض |
| `checkout-flow-probe.js` | سبد → تسویه → پرداخت → تکمیل، موبایل و دسکتاپ |
| `support-flow-probe.js` | تیکت: ساخت، پاسخ مشتری، پاسخ پشتیبان، بستن، بازگشایی |
| `invoice-print-probe.js` | فاکتور: یک صفحه، چارت پستی، دسترسی ۴۰۳، دکمهٔ ادمین |
| `wallet-topup-probe.js` / `wallet-pay-probe.js` | شارژ و پرداخت کیف پول |
| `header-counts-probe.js` | شمارش ترم‌ها با زیرشاخه |
| `palette-cascade-probe.js` | آبشار پالت → ویجت → المنت |

---

## ۱۵) نقاط توسعه

**فیلترهای عمومی (نمونه):**
`hsw_loop_cache_ttl`, `hsw_archive_query_allow`, `hsw_archive_price_step`, `hsw_normalize_phone`, `hsw_otp_allowed_roles`, `hsw_otp_ip_hourly`, `hsw_otp_phone_daily`, `hsw_send_sms_otp`, `hsw_telegram_api_base`, `hsw_account_support_email`, `hsw_wallet_balance`, `hsw_cart_drawer_args`, `hsw_coupon_error_message`, `hsw_free_shipping_enabled`, `hsw_font_choices`, `hsw_pay_slot_text`, `hsw_product_sheet`, `hsw_blocked_hosts`.

**اکشن‌ها:** `hsw_wallet_changed`, `hsw_wallet_request_decided`, `hsw_checkout_note_saved`, `hsw_checkout_payment_saved`, `hsw_checkout_schedule_saved` + هوک‌های ووکامرس.

**افزودن یک فیچر با سوییچ:** الگوی فعلی سه‌گانه است: (۱) ثبت گزینه در `HSW_Widget_Manager::register_settings()`، (۲) کارت در صفحهٔ «کنترل ویجت‌ها»، (۳) بررسی `enabled()` در کلاس مربوطه به‌شکلی که وقتی خاموش است هزینهٔ صفر داشته باشد.

**نکته برای توسعهٔ تلگرام:** امروز نقطهٔ توسعه، متد `handle_telegram_update()` است؛ برای منطق چندمرحله‌ای/AI پیشنهاد می‌شود یک `do_action( 'hsw_telegram_update', $update, $chat_id )` و جدول state اضافه شود (هنوز پیاده نشده).

---

## ۱۶) محدودیت‌ها و کارهای باز (صادقانه)

1. **بدون تست واحد PHP** — پوشش فعلی «compile + یکپارچه با مرورگر» است؛ CI از v1.11.172 هست، اما برای helperهای خالص (Jalali، price، stock) تست واحد ارزش دارد.
2. **PDF سمت سرور نیست** — عمدی است (بدون کتابخانهٔ ۵ مگابایتی). اگر روزی «ارسال PDF در ایمیل» لازم شد، باید mPDF سبک‌شده یا سرویس بیرونی تصمیم‌گیری شود.
3. **آیکن‌ها زیرمجموعه‌اند** — آیکن خارج از مجموعه با نقطه جایگزین می‌شود و `error_log` می‌دهد؛ افزودن آیکن = ویرایش `tools/_icons.txt` + `build-sprite.py`.
4. **آرشیو تک‌دسته‌ای** — لینک دسته یک ناوبری است، پس در تگ‌های «×» نمی‌آید (بازگشت با «حذف همه»).
5. **کیف پول بدون درگاه فعال** — اگر تنها COD فعال باشد، شارژ عمداً بسته است.
6. **فونت پیش‌فرض** — فونت همراه افزونه است؛ اگر قالب فونت دیگری بدهد، `hsw-fonts` همچنان لود می‌شود (۱۵ KB) — قابل بهینه‌سازی با یک سوییچ «فونت قالب».

---

## ۱۷) چک‌لیست انتشار

```
[ ] version در hamishop-elementor-widgets.php و readme.txt (Stable tag)
[ ] RELEASE-NOTES-<ver>.md نوشته شد
[ ] CHANGELOG در readme.txt
[ ] php -l روی همهٔ فایل‌ها: 0 failure
[ ] php tests/harness.php → files loaded: N/N · ALL CLEAN
[ ] php tools/ci-checks.php → 16 checks, all passed
[ ] python tools/build-assets.py (min تازه)
[ ] python tools/build-pot.py
[ ] پروب‌های مرتبط روی سایت تست: RESULT: clean
[ ] کپی به نصب زنده + بازدید یک صفحهٔ اصلی
[ ] debug.log: بدون خط جدید
[ ] git commit + tag + push
[ ] python tools/build-release.py → ZIP
```

---

## ۱۸) منابع دیگر

| فایل | برای چه کسی |
|---|---|
| `README.md` | راهنمای کامل فارسی: نصب، تنظیمات، همهٔ قابلیت‌ها |
| `WIDGET-GUIDE.md` | راهنمای گام‌به‌گام ساخت صفحه با هر ویجت |
| `WIDGETS-REFERENCE.md` | فهرست مختصر همهٔ ویجت‌ها + کلاس‌ها |
| `MARKETING.md` | برای تیم بازاریابی و فروش |
| `STAGING-CHECKLIST.md` | قبل از لانچ |
| `RELEASE-NOTES-*.md` | تاریخ تغییرات با اندازه‌گیری هر نسخه |
