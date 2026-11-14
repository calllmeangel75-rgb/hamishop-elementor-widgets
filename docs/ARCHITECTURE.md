# معماری

این سند مکانیزم‌های داخلی افزونه را توضیح می‌دهد: هر بخش «هدف»، «فایل‌ها»، «هوک‌ها و آپشن‌ها» و «تله‌ها» دارد. برای فهرست ویجت‌ها به [`WIDGETS.md`](WIDGETS.md) و برای فرآیند توسعه به [`DEVELOPMENT.md`](DEVELOPMENT.md) مراجعه کنید.

## در یک نگاه

| مورد | مقدار |
|---|---|
| پیشوند | کلاس‌ها `HSW_` · ویجت‌ها `hsw-` · آپشن‌ها `hsw_` · توکن‌ها `--hsw-` |
| نیازمندی‌ها | WordPress ≥ ۶.۰ · PHP ≥ ۷.۴ · WooCommerce · Elementor |
| کد | ۱۳۰ فایل PHP زمان اجرا (۲ ریشه + ۶۳ includes + ۶۴ widgets + ۱ template) · حدود ۶۶٬۰۰۰ خط · ۱۱۹ کلاس · ۶ trait |
| دارایی‌ها | ۱۹ فایل CSS (۴۸۸ KB کمینه از ۶۷۵ KB سورس) · ۹ فایل JS (۱۷۵ KB کمینه از ۲۸۰ KB) |
| REST | ۱۹ مسیر در namespace `hsw/v1` |
| دامنهٔ ترجمه | `hamishop-elementor-widgets` · POT با ۳٬۳۴۸ رشته |
| مجوز | GPL-2.0-or-later |

**فلسفه در یک جمله:** هر چیزی که لازم نیست بارگذاری نمی‌شود؛ هر رنگی که کاربر می‌بیند از یک منبع واحد (پالت) می‌آید و ویجت می‌تواند آن را فقط برای خودش بازنویسی کند.

## قواعد ثابت

اگر یکی از این‌ها شکسته شود، معماری می‌شکند:

1. **بدون enqueue سراسری.** `HSW_Assets` فقط `wp_register_*` می‌کند؛ هر ویجت با `get_style_depends()` / `get_script_depends()` اعلام می‌کند چه می‌خواهد.
2. **هیچ کتابخانهٔ بیرونی.** نه jQuery UI، نه Swiper، نه mPDF، نه فونت CDN.
3. **توکن قبل از استایل.** CSS ویجت‌ها فقط `var(--hsw-…)` می‌خواند؛ رنگ هاردکد‌شده در ویجت تازه اضافه نمی‌شود.
4. **آبشار سه‌لایه‌ای:** پالت (سراسری) → کنترل ویجت در المنتور (فقط همان ویجت) → متغیر دستی روی المنت. لایهٔ بالاتر همیشه می‌برد.
5. **بدون کوئری بی‌دلیل.** فهرست‌های پرتکرار با transient + نسخهٔ کاتالوگ کش و `_prime_post_caches()` می‌شوند.
6. **امنیت پیش‌فرض:** هر endpoint عمومی nonce یا امضای HMAC دارد، هر خروجی escape و هر ورودی سانیتایز می‌شود.
7. **فارسی‌محور:** ارقام فارسی، تاریخ شمسی و RTL در helper انجام می‌شود، نه در هر ویجت.

## نقشهٔ پوشه‌ها

```
hamishop-elementor-widgets.php   هدر افزونه، بوت، محافظ وابستگی‌ها، هوک فعال/غیرفعال
uninstall.php                    پاک‌سازی کامل هنگام حذف
includes/
  class-hsw-plugin.php           هسته: require کلاس‌ها + ساخت نمونه + ثبت ویجت‌ها
  class-hsw-widget-base.php      پایهٔ همهٔ ویجت‌ها (کنترل مشترک، helper رندر، part_section)
  class-hsw-header-base.php      پایهٔ هدرها
  class-hsw-archive-base.php     پایهٔ آرشیو (پارامترها، filter_url، ترم‌ها)
  class-hsw-product-base.php     پایهٔ صفحهٔ محصول
  class-hsw-{cart,checkout,pay,complete,blog}-base.php
  class-hsw-widget-manager.php   کاتالوگ ۶۴ ویجت + صفحهٔ روشن/خاموش + مهاجرت
  class-hsw-palette.php          تنها منبع توکن رنگ
  class-hsw-assets.php           ثبت شرطی CSS/JS، نسخه‌گذاری، min/dev
  class-hsw-upgrader.php         کار پس‌زمینهٔ آپدیت (کش المنتور + مهاجرت)
  class-hsw-composite.php        رندر یک ویجت به‌عنوان بخشی از ویجت دیگر
  class-hsw-helpers.php          اعداد فارسی، تاریخ، آیکن، preload تصویر
  class-hsw-{security,throttle,request-blocker,cache-guard,login}.php
  class-hsw-account*.php         پنل مشتری (پوسته، احراز هویت، پشتیبانی، ادمین)
  class-hsw-wallet*.php          کیف پول و درگاه داخلی
  class-hsw-invoice.php          فاکتور چاپی A4
  trait-hsw-style-kit.php        helperهای کنترل استایل
widgets/                          ۶۴ ویجت، هر فایل یک کلاس
assets/css|js|fonts|img/          دارایی‌ها + نسخهٔ .min
tools/                            ساخت دارایی، POT، اسپرایت، فونت، ZIP، تولید مستند
tests/                            هارنس PHP + پروب‌های مرورگری
```

## چرخهٔ بوت

```
hamishop-elementor-widgets.php
 ├─ بررسی PHP/WP/WooCommerce/Elementor → نبودِ هرکدام: نوتیس + توقف امن
 ├─ define( HSW_VERSION, HSW_DIR, HSW_URL )
 └─ add_action( 'plugins_loaded', 'hsw_boot', 20 )
      └─ HSW_Plugin::instance()
           ├─ includes()                 require_once همهٔ کلاس‌ها
           ├─ HSW_Account / HSW_Widget_Manager / HSW_Palette / HSW_Security …
           ├─ HSW_Upgrader::instance()   فقط زمان‌بندی؛ کار در پس‌زمینه
           └─ add_action( 'elementor/widgets/register', register_widgets )
```

`register_widgets()` فقط ویجت‌های **فعال** در `hsw_enabled_widgets` را ثبت می‌کند؛ بقیه حتی کلاس‌شان لود نمی‌شود.

> **تله:** کلاس‌هایی که به `\Elementor\Widget_Base` وابسته‌اند (`HSW_Widget_Base`, `HSW_Product_Base`, `HSW_Archive_Base`, …) هرگز نباید در `includes()` لود شوند — المنتور در زمان `plugins_loaded` هنوز کلاس پایه را ندارد و سایت با `Class "HSW_Widget_Base" not found` سفید می‌شود. این‌ها فقط داخل `register_widgets()` لود می‌شوند.

---

## ۱. موتور Composite

**هدف:** یک ویجت «صفحهٔ کامل» بدون تکرار کد، همان بخش‌های مستقل را رندر کند و تنظیماتشان را در یک پنل جمع کند.

**فایل‌ها:** `includes/class-hsw-composite.php` · `includes/class-hsw-widget-base.php`

**چطور کار می‌کند:**

| قطعه | کار |
|---|---|
| `HSW_Composite::part( $class, $settings )` | یک نمونه از ویجت بخش می‌سازد، تنظیمات را تزریق می‌کند، `render_content()` را در بافر می‌گیرد و رشته برمی‌گرداند. هر `Throwable` گرفته می‌شود و صفحه نمی‌شکند. |
| `$widget->hsw_inject_settings( $array )` | تنظیمات تزریق‌شده را روی ویجت می‌گذارد؛ `effective_settings()` بعد از آن همان را برمی‌گرداند. |
| `part_section( $prefix, $label, $class, $ids )` | یک سکشن کنترل در پنل صفحهٔ کامل می‌سازد: سوییچ «نمایش این بخش» + کنترل‌های همان بخش با پیشوند. |
| `part_controls( $prefix, $class, $ids )` | کنترل‌های بخش را از کلاس مقصد می‌خواند و با پیشوند بازتولید می‌کند. |
| `part_settings( $prefix )` | هنگام رندر، کلیدهای دارای پیشوند را برمی‌دارد و پیشوند را حذف می‌کند. |

**هوک/آپشن:** ندارد. کاملاً داخلی است.

**تله‌ها:**
- `part()` تنظیمات را با `defaults( $class )` ادغام می‌کند؛ اگر کنترل جدیدی به بخش اضافه شود و در `part_section()` فهرست `$ids` نیاید، در صفحهٔ کامل دیده نمی‌شود.
- خاموش‌کردن یک بخش در **کنترل ویجت‌ها** فقط آن را از پنل المنتور پنهان می‌کند؛ صفحهٔ کامل همچنان آن را رندر می‌کند. عمدی است.
- هر ویجت بخش باید `hsw_inject_settings()` داشته باشد (از `HSW_Widget_Base` ارث می‌برد)؛ وگرنه `part()` رشتهٔ خالی برمی‌گرداند.

---

## ۲. موتور تنوع (Variations)

**هدف:** انتخاب رنگ/حافظه بدون رفرش، با قیمت و موجودی و تصویر درست، هم‌زمان در همهٔ ویجت‌های صفحه.

**فایل‌ها:** `widgets/class-hsw-widget-product-variants.php` · `assets/js/hsw-product.js`

**چطور کار می‌کند:**
- کل محصول به‌صورت JSON در `data-hsw-variations` روی ریشهٔ ویجت چاپ می‌شود؛ ویژگی‌ها در `data-hsw-attrs` و انتخاب فعلی در `data-hsw-color` / `data-hsw-attr`.
- JS بدون رفت‌وبرگشت به سرور، ترکیب انتخاب‌شده را پیدا می‌کند و قیمت، قیمت قبلی، وضعیت موجودی و تصویر گالری را به‌روز می‌کند.
- استپر تعداد هر واریاسیون عدد خودش را در سبد می‌داند (`cart_qty`)؛ با تغییر واریاسیون، استپر باز/بسته و عددش درست می‌شود.
- اگر ویژگی‌های محصول از پیش تعیین نشده باشند، `auto_variation_attributes()` آن‌ها را از خود محصول تشخیص می‌دهد.

**تله‌ها:**
- برای محصول ساده، همان مارک‌آپ با `simple_picker_html()` و بدون ماشین تنوع رندر می‌شود.
- اگر افزونهٔ CFP محصول را «تماس برای قیمت» علامت بزند، به‌جای قیمت و دکمهٔ خرید، متن و اکشن تماس می‌آید (`data-hsw-cfp`).

---

## ۳. بارگذاری شرطی CSS/JS

**هدف:** صفحه‌ای که ویجت فروشگاهی ندارد، یک بایت CSS فروشگاه هم نگیرد.

**فایل‌ها:** `includes/class-hsw-assets.php` · `assets/css/*` · `assets/js/*`

**چطور کار می‌کند:**
- `HSW_Assets` همهٔ فایل‌ها را با `wp_register_style/script` ثبت می‌کند و هیچ‌کدام را enqueue نمی‌کند.
- هر ویجت در `get_style_depends()` / `get_script_depends()` می‌گوید چه می‌خواهد؛ المنتور فقط همان‌ها را در صفحه‌ای که ویجت را دارد لود می‌کند.
- `hsw-fonts` پایه است و هر خانواده به آن وابسته است (یک‌بار در صفحه).
- `asset()` نسخهٔ `.min` را ترجیح می‌دهد مگر `SCRIPT_DEBUG` روشن باشد؛ نسخه‌گذاری با `HSW_VERSION` است.

**تله‌ها:**
- فایل `.min` را دستی ویرایش نکنید؛ با `python tools/build-assets.py` ساخته می‌شود و CI تازه‌بودنش را چک می‌کند.
- `print_layout_guard()` در `wp_head` فقط **هندسهٔ** چند قاعدهٔ حیاتی را تکرار می‌کند (برای CLS)؛ هیچ رنگ یا فونتی در آن نیست و اگر قاعده‌ای عوض شد باید همان‌جا هم به‌روز شود.

---

## ۴. کنترل ویجت‌ها و مهاجرت

**هدف:** ادمین انتخاب کند کدام ویجت در پنل المنتور دیده شود؛ بدون اینکه آپدیت، انتخاب قبلی او را خراب کند.

**فایل‌ها:** `includes/class-hsw-widget-manager.php`

**آپشن‌ها:** `hsw_enabled_widgets` · `hsw_header_widgets_migrated` · `hsw_post_widget_migrated`

**چطور کار می‌کند:**
- `catalog()` هفت گروه را تعریف می‌کند؛ هر گروه `general` (ویجت اصلی) و `parts` (بخش‌های مستقل) دارد.
- `defaults()` همهٔ `general`ها را برمی‌گرداند (۱۶ مورد)؛ `parts` (۴۷ مورد) پیش‌فرض خاموش‌اند.
- دو مهاجرت یک‌باره وجود دارد: `maybe_add_header_widgets()` دو هدر را به نصب‌های قدیمی اضافه می‌کند و `maybe_add_post_widget()` ویجت نوشتهٔ تکی را. هر دو با آپشن پرچم محافظت شده‌اند و **فقط یک‌بار** اجرا می‌شوند.

**تله‌ها:**
- اگر مهاجرت را دوباره فعال کنید، انتخاب آگاهانهٔ کاربر (خاموش‌کردن یک ویجت) دوباره روشن می‌شود. پرچم را دست نزنید.
- ویجت جدیدی که اضافه می‌کنید، در نصب‌های موجود **خودبه‌خود روشن نمی‌شود**؛ یا باید در `defaults()` باشد یا یک مهاجرت یک‌باره مثل دو مورد بالا بگیرد.

---

## ۵. پالت رنگی و توکن‌ها

**هدف:** یک منبع رنگ برای کل افزونه، با امکان بازنویسی در سطح ویجت.

**فایل‌ها:** `includes/class-hsw-palette.php` · `assets/css/hsw-header.css` · `assets/css/hsw-fonts.css`

**آپشن:** `hsw_palette` (۲۷ توکن رنگ + `font` + `font_source`)

**چطور کار می‌کند:**

```
HSW_Palette::css()  →  html:root { --hsw-primary, --hsw-accent, --hsw-pal-surface, … }
ویجت (المنتور)       →  {{WRAPPER}} .hsw-x { --hsw-hd-primary-user: #b51746 }
المنت دستی           →  style="--hsw-primary:#0a0"          ← همیشه برنده
```

- پالت روی `html:root` چاپ می‌شود (specificity بالاتر از `:root` معمولی).
- CSS ویجت‌ها همیشه `var(--x, fallback)` می‌خواند تا نصب قدیمی نشکند.
- `contrast_text()` رنگ متن روی پر‌رنگ را حساب می‌کند تا پالت روشن، متن ناخوانا نسازد.

**تله‌ها:**
- **المنتور پیش‌فرض کنترل‌ها را در CSS صفحه چاپ می‌کند.** اگر یک کنترل رنگی `default` داشته باشد، همان مقدار روی خود ویجت نوشته می‌شود و پالت دیگر به آن نمی‌رسد. برای همین کنترل‌های رنگی هدر بدون `default` هستند و به کانال `-user` می‌نویسند.
- CSS تولیدشدهٔ المنتور (و نسخهٔ ترکیبی LiteSpeed) ممکن است مقدار قدیمی را نگه دارد؛ به همین دلیل توکن‌های هدر در `hsw-header.css` با `!important` تعریف شده‌اند و از نام‌های مستعار `--hsw-pal-primary*` می‌خوانند.
- `--hsw-surface` در خانوادهٔ محصول معنای «پس‌زمینهٔ صفحه» دارد؛ برای همین سطح‌های پالت نام جداگانهٔ `--hsw-pal-surface` گرفته‌اند.

---

## ۶. مسیر خرید و ریدایرکت

**هدف:** صفحهٔ «تکمیل خرید» یک صفحهٔ واقعی المنتور باشد و همهٔ مسیرهای بازگشت درگاه به آن برسند.

**فایل‌ها:** `includes/class-hsw-complete-route.php` · `includes/class-hsw-checkout-complete.php` · `includes/class-hsw-checkout-rest.php`

**آپشن‌ها:** `hsw_complete_page_id` · `hsw_pay_page_id`

**چطور کار می‌کند:**
- فیلتر `woocommerce_get_checkout_order_received_url` تنها نقطه‌ای است که همهٔ ریدایرکت‌ها (هستهٔ ووکامرس، بازگشت درگاه‌ها، لینک‌های پرداخت، Store API) از آن می‌گذرند؛ همین یک فیلتر کافی است.
- اگر صفحه در تنظیمات ووکامرس انتخاب نشده باشد، `resolve_page_id()` سراغ اسلاگ `/complete/` یا `/payment/` می‌رود.
- درخواست‌هایی که هنوز به `/checkout/order-received/…` برسند با `template_redirect` هدایت می‌شوند.
- دو گارد روی `template_redirect`: `guard_pay_address` (ورود مستقیم به پرداخت بدون آدرس → بازگشت به تسویه) و `guard_checkout_login` (مهمان با سبد پر → حساب کاربری با `redirect_to`).

**تله‌ها:**
- خالی‌گذاشتن آپشن یعنی «رفتار بومی ووکامرس»؛ عمداً هیچ مقدار پیش‌فرضی نوشته نمی‌شود.
- هر تغییر در این مسیر باید با `tests/checkout-flow-probe.js` روی سایت واقعی تست شود؛ در CI پوشش ندارد.

---

## ۷. امنیت

**فایل‌ها:** `includes/class-hsw-security.php` · `class-hsw-throttle.php` · `class-hsw-request-blocker.php` · `class-hsw-cache-guard.php` · `class-hsw-login.php`

| موضوع | پیاده‌سازی |
|---|---|
| Nonce | همهٔ فرم‌ها و دکمه‌های تغییر‌دهنده؛ فاکتور: `hsw_invoice_<id>` |
| دسترسی | `current_user_can()` برای ادمین، مالکیت سفارش برای مشتری، `check_ajax_referer()` برای AJAX |
| ورودی | `sanitize_*` + `wp_unslash` + `absint` + allow-list برای کلیدهایی مثل `filter_*` |
| خروجی | `esc_html/esc_attr/esc_url/wp_kses_post` |
| REST | `permission_callback` + nonce؛ مسیرهای عمومی فقط خواندنی |
| نرخ | `HSW_Throttle` روی جدول `{prefix}hsw_throttle` — نوشتن اتمیک |
| آدرس ادمین | اسلاگ مخفی + قاعدهٔ `.htaccess` با بکاپ در `hsw_htaccess_backup` |
| کاربران | `rest_endpoints` / `rest_pre_dispatch` مسیر `/wp/v2/users` را برای غیرمجاز می‌بندد |
| XML-RPC | `xmlrpc_enabled` خاموش + حذف `X-Pingback` |
| کش | `DONOTCACHEPAGE` روی مسیرهای اختصاصی (فاکتور، ورکر تلگرام، وبهوک) |

**تله‌ها:**
- **IP واقعی پشت پروکسی:** `HSW_Throttle::ip()` مقدار `REMOTE_ADDR` را تنها وقتی با هدرهای `CF-Connecting-IP` / `X-Real-IP` / `X-Forwarded-For` جایگزین می‌کند که آدرس اتصال در فهرست پروکسی‌های مورد اعتماد باشد. لوپ‌بک (`127.0.0.0/8` و `::1/128`) پیش‌فرض مورد اعتماد است تا Nginx هم‌میزبان → LiteSpeed/PHP-FPM IP کلاینت را از `X-Forwarded-For` بگیرد؛ بدون آن همهٔ مشتری‌ها یک باکت مشترک داشتند و پنج تلاش ناموفق یک نفر کل فروشگاه را قفل می‌کرد. اگر پروکسی روی ماشین دیگری است، محدودهٔ آن را با فیلتر `hsw_trusted_proxies` اضافه کنید؛ هدرهای forwarding را از آدرس‌های ناشناس باور نکنید. فرم تأیید ایمیل سفارش با باکت `verify_order:{order_id}:{ip}` (۵ خطا / ۶۰۰ ثانیه) و سقف سراسری `verify_order_all:{order_id}` (۲۵ خطا / ۶۰۰ ثانیه) محافظت می‌شود و فقط خطاها شمرده می‌شوند.
- `guard_admin_url()` روی `init:1` اجرا می‌شود ولی برای مسیرهای معمولی **قبل از** خواندن `.htaccess` خارج می‌شود؛ اگر شرط‌ها را جابه‌جا کنید، هر درخواست یک خواندن فایل اضافه می‌گیرد.
- قاعدهٔ `.htaccess` فقط با فعال/غیرفعال‌سازی افزونه نوشته و پاک می‌شود؛ تغییر اسلاگ بدون ذخیرهٔ مجدد تنظیمات، قاعده را به‌روز نمی‌کند.

---

## ۸. تأیید ایمیل و OTP

**فایل‌ها:** `includes/class-hsw-email-verify.php` · `class-hsw-account-auth.php` · `class-hsw-account.php`

**آپشن:** `hsw_account_settings` (کانال‌ها، نرخ‌ها، متن‌ها)

**چطور کار می‌کند:**
- کد یک‌بارمصرف برای ایمیل، تلگرام/بله، واتساپ یا پیامک تولید می‌شود؛ کد **هش‌شده** و **موقت** ذخیره می‌شود، نه خام.
- محدودیت تلاش و نرخ ارسال روی جدول `hsw_throttle` اعمال می‌شود (per-IP و per-phone).
- ورود دوعاملی اختیاری است و بعد از رمز عبور می‌آید.
- اگر افزونهٔ ورود پیامکی دیگری فعال باشد و OTP حامی روشن نباشد، فرم ورود HamiShop کنار می‌رود و دخالت نمی‌کند.

**تله‌ها:**
- ترنزینت‌های `hsw_otp_*` و `hsw_telegram_link_*` باید منقضی شوند؛ پاک‌سازی روزانه انجام می‌شود.
- ورکر تلگرام روی هاست‌های بدون وبهوک با کران هر دقیقه کار می‌کند و قفل ۴۵ ثانیه‌ای دارد (`hsw_tg_lock`).

---

## ۹. تیکت و پشتیبانی

**فایل‌ها:** `includes/class-hsw-account-support.php` · `class-hsw-account-admin.php`

**داده:** پست‌تایپ `hsw_ticket` (خصوصی، بدون آرشیو عمومی) + کامنت‌های `hsw_ticket_reply` + متاهای `_hsw_ticket_status`, `_hsw_ticket_priority`, `_hsw_ticket_department`, `_hsw_ticket_unread_*`

**چطور کار می‌کند:**
- مشتری از پنل تیکت می‌زند (با پیوست و کپچا)؛ پاسخ مشتری و ادمین هر دو کامنت می‌شوند.
- اعلان داخلی + ایمیل؛ پاسخ از داخل ایمیل با لینک امضاشده (`ticket-inbound` با HMAC) ثبت می‌شود.
- وضعیت تیکت با AJAX برمی‌گردد و چیپ بالای ترید بدون رفرش به‌روز می‌شود.
- کران روزانهٔ `hsw_ticket_cleanup` تیکت‌های بستهٔ قدیمی را پاک می‌کند.

**تله‌ها:**
- لینک inbound بدون تگ امضا نباید کار کند؛ کلید امضا را در دیتابیس نگه دارید و عوض نکنید.
- خروجی CSV مخصوص اکسل است (BOM دارد)؛ تغییرش فایل را در اکسل خراب می‌کند.

---

## ۱۰. فونت و آیکون لوکال

**فونت:** وزیرمتن متغیر، زیرمجموعه‌شده و داخل افزونه (`assets/fonts/`). بازسازی با `python tools/build-font.py` از فایل کامل `tools/_vazirmatn-src.woff2` (که در ریپو نیست و دانلود می‌شود). `font-display: optional` است تا CLS صفر بماند.

**آیکون:** SVG درون‌صفحه، نه فونت آیکون. `HSW_Helpers::icon()` نماد را ثبت می‌کند و `print_sprite()` در `wp_footer` فقط همان نمادهای مصرف‌شده را چاپ می‌کند.

- مجموعه در `includes/class-hsw-icon-paths.php` است (۱۰۱ نماد).
- افزودن آیکون: نام را در `tools/_icons.txt` بگذارید و `python tools/build-sprite.py` را اجرا کنید.
- آیکون خارج از مجموعه با یک نقطهٔ جایگزین رندر می‌شود و در `error_log` ثبت می‌شود.

**تله‌ها:**
- اسپرایت فقط یک‌بار در صفحه چاپ می‌شود؛ اگر ویجتی بعد از `wp_footer` رندر شود (مثلاً در یک درخواست AJAX که قالب کامل ندارد)، باید اسپرایت را خودش چاپ کند.
- `hsw-fonts` همیشه لود می‌شود (حدود ۱۶ KB) چون چیدمان فارسی و ارقام به آن وابسته است.

---

## ۱۱. Hami Call for Price

**هدف:** محصولات «تماس برای قیمت» در همهٔ سطوح HamiShop رفتار CFP بگیرند، بدون دست‌زدن به هندلرهای ووکامرس.

**فایل‌ها:** `includes/class-hsw-helpers.php` (تشخیص) · ویجت‌های لوپ/محصول/آرشیو

**چطور کار می‌کند:**
- تشخیص از API عمومی افزونهٔ CFP انجام می‌شود، نه از نام کلاس یا آپشن اختصاصی.
- در لوپ، آرشیو، جعبهٔ خرید، نوار چسبان و انتخاب تنوع: قیمت، قیمت قبلی، درصد تخفیف، تعداد و دکمهٔ خرید آنلاین حذف می‌شوند و متن/دکمهٔ تماس می‌آید (`data-hsw-cfp`).
- هندلرهای `woocommerce_add_to_cart` و فرم‌های بومی دست‌نخورده می‌مانند.

**تله:** اگر CFP غیرفعال شود، هیچ کدی نباید به آن ارجاع بدهد؛ تشخیص باید همیشه در زمان اجرا و با `function_exists`/`class_exists` باشد.

---

## ۱۲. مدل داده

**آپشن‌های اصلی (`wp_options`):** `hsw_palette`, `hsw_enabled_widgets`, `hsw_account_settings`, `hsw_brand`, `hsw_security`, `hsw_invoice_enabled`, `hsw_invoice_mono`, `hsw_db_version`, `hsw_asset_version`, `hsw_complete_page_id`, `hsw_pay_page_id`, `hsw_free_shipping_on|min`, `hsw_jalali_on`, `hsw_custom_404_enabled` + `hsw_404_*`, `hsw_tg_offset`, `hsw_last_error` و پرچم‌های مهاجرت.

**جدول‌ها:**

| جدول | کاربرد |
|---|---|
| `{prefix}hsw_throttle` | شمارندهٔ نرخ (OTP، ورود) — نوشتن اتمیک |
| `{prefix}hsw_wallet` | موجودی هر کاربر؛ منبع حقیقت |
| `{prefix}hsw_wallet_meta_backup` | کپی خام متای قبلی، برای rollback |
| `{prefix}hsw_wallet_ledger` | دفتر تراکنش با `UNIQUE KEY (ref)` برای idempotency — هنگام حذف افزونه **عمداً** پاک نمی‌شود |

**کران:** `hsw_ticket_cleanup` (روزانه) · `hsw_tg_poll` (هر دقیقه، فقط با Polling روشن) · `hsw_throttle_prune` (روزانه) · `hsw_upgrader_tick` (موقت، فقط هنگام آپدیت).

---

## ۱۳. نقاط توسعه

**فیلترها:** `hsw_loop_cache_ttl`, `hsw_archive_query_allow`, `hsw_archive_price_step`, `hsw_normalize_phone`, `hsw_otp_allowed_roles`, `hsw_otp_ip_hourly`, `hsw_otp_phone_daily`, `hsw_send_sms_otp`, `hsw_telegram_api_base`, `hsw_account_support_email`, `hsw_wallet_balance`, `hsw_cart_drawer_args`, `hsw_coupon_error_message`, `hsw_free_shipping_enabled`, `hsw_font_choices`, `hsw_pay_slot_text`, `hsw_product_sheet`, `hsw_blocked_hosts`, `hsw_upgrader_full_purge`.

**اکشن‌ها:** `hsw_wallet_changed`, `hsw_wallet_request_decided`, `hsw_checkout_note_saved`, `hsw_checkout_payment_saved`, `hsw_checkout_schedule_saved`, `hsw_optimizer_purged`.

**افزودن یک قابلیت با سوییچ:** (۱) ثبت گزینه در `HSW_Widget_Manager::register_settings()`، (۲) کارت در صفحهٔ کنترل ویجت‌ها، (۳) بررسی `enabled()` در کلاس مربوطه به‌شکلی که وقتی خاموش است هزینهٔ صفر داشته باشد.

## ۱۴. محدودیت‌های شناخته‌شده

1. **بدون تست واحد PHP** — پوشش فعلی «کامپایل + یکپارچه با مرورگر» است.
2. **PDF سمت سرور نیست** — عمدی است؛ فاکتور HTML است و با Save as PDF چاپ می‌شود.
3. **آیکون‌ها زیرمجموعه‌اند** — آیکون تازه نیاز به بازسازی اسپرایت دارد.
4. **آرشیو تک‌دسته‌ای** — لینک دسته در چیپ‌های «×» نمی‌آید (بازگشت با «حذف همه»).
5. **کیف پول بدون درگاه فعال** — اگر تنها COD فعال باشد، شارژ عمداً بسته است.
