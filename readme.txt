=== HamiShop Elementor Widgets ===
Contributors: hamisys
Tags: elementor, woocommerce, widgets, product loop, rtl, persian
Requires at least: 6.0
Tested up to: 7.1.4
Requires PHP: 7.4
Stable tag: 1.11.223
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

۶۲ ویجت فروشگاهی فارسی و راست‌به‌چپ برای المنتور و ووکامرس: لوپ محصول، صفحه محصول، هدر، سبد، تسویه، آرشیو، مجله و پنل حساب کاربری — با پالت رنگی سراسری، فونت لوکال و بارگذاری شرطی.

== Description ==

HamiShop مجموعه‌ای از ویجت‌های المنتور است که صفحه‌های یک فروشگاه ووکامرس را بدون کدنویسی می‌سازد. هر ویجت یک بخش واقعی از فروشگاه است، نه یک بلوک تزئینی: داده را از خود ووکامرس می‌خواند، با هندلرهای بومی کار می‌کند و در نبود ووکامرس یا المنتور خطا نمی‌دهد.

= هفت خانواده، ۶۲ ویجت =

* **هدر و ناوبری (۳)** — هدر فروشگاهی با نوار اعلان، جستجوی محصول و مگامنو؛ هدر مینیمال شیشه‌ای؛ فوتر چسبان موبایل.
* **لوپ محصولات (۳)** — پیشنهاد شگفت‌انگیز با شمارش معکوس، پرفروش‌ترین‌ها، محبوب‌ترین‌ها/منتخب با تب.
* **صفحه محصول (۱۲)** — گالری، بردکرامب، عنوان و متا، نوار اعتماد، ویژگی‌ها، انتخاب تنوع، جعبه خرید، تب‌ها، آکاردئون، نوار چسبان، محصولات مرتبط و صفحهٔ کامل محصول.
* **سبد خرید و پرداخت (۲۶)** — سبد، تسویه‌حساب، روش‌های پرداخت، فاکتور، تکمیل سفارش و صفحه‌های کامل هر مرحله.
* **آرشیو محصولات (۸)** — هدر آرشیو، فیلترها، نوار ابزار، گرید، راهنما، بردکرامب، صفحه‌بندی و صفحهٔ کامل آرشیو.
* **مجله و وبلاگ (۹)** — هیرو، مقالهٔ ویژه، گرید، تب‌ها، محبوب‌ها، برچسب‌ها، خبرنامه و صفحهٔ کامل نوشته.
* **حساب کاربری (۱)** — پنل کامل مشتری روی اندپوینت‌های واقعی ووکامرس.

= چیزهایی که در همهٔ ویجت‌ها مشترک است =

* **پالت رنگی سراسری:** یک صفحه در پیشخوان (HamiShop ← پالت رنگی) رنگ‌های اصلی، تاکید، مراحل خرید، متن، سطح‌ها، وضعیت‌ها و آیکون‌های هدر را تعیین می‌کند. کنترل رنگی هر ویجت، اگر تنظیم شود، بر پالت اولویت دارد.
* **فونت:** وزیرمتن به‌صورت متغیر و لوکال داخل افزونه است؛ هر فونت دیگری که روی سایت لود شده باشد از فهرست پالت یا با نوشتن نام دقیق آن قابل انتخاب است.
* **آیکون‌ها:** SVG درون‌صفحه (۱۰۱ آیکون) — بدون فونت آیکون و بدون درخواست خارجی. هر صفحه فقط نمادهایی را چاپ می‌کند که همان صفحه رندر کرده است.
* **بارگذاری شرطی:** CSS و JS هر خانواده فقط وقتی لود می‌شوند که ویجتی از آن خانواده در صفحه باشد.
* **بدون فریم‌ورک:** بدون jQuery، بدون Tailwind، بدون CDN. صفر درخواست خارجی.
* **راست‌به‌چپ و فارسی:** ارقام فارسی، تاریخ شمسی (اختیاری) و چیدمان RTL در همهٔ خانواده‌ها.
* **کنترل ویجت‌ها:** صفحهٔ HamiShop ← کنترل ویجت‌ها تعیین می‌کند کدام ویجت در پنل المنتور دیده شود. ۱۵ ویجت اصلی پیش‌فرض روشن‌اند و ۴۷ بخش مستقل پیش‌فرض خاموش.

= سازگاری =

* **Hami Call for Price:** اگر افزونهٔ CFP فعال باشد، محصولات علامت‌خورده در همهٔ سطوح HamiShop به‌جای قیمت و دکمهٔ خرید، متن و اکشن تماس همان افزونه را نشان می‌دهند. هندلرهای ووکامرس دست‌نخورده می‌مانند.
* **افزونه‌های ورود پیامکی/OTP:** اگر افزونهٔ دیگری احراز هویت را در دست بگیرد، فرم ورود HamiShop کنار می‌رود و دخالت نمی‌کند.
* **HPOS ووکامرس:** سازگاری اعلام‌شده؛ همهٔ سفارش‌ها از `wc_get_order()` خوانده می‌شوند.
* **LiteSpeed و بهینه‌سازها:** دارایی‌های افزونه از ادغام و UCSS خارج می‌شوند تا چیدمان و فونت به‌هم نریزد.

= دربارهٔ عملکرد =

افزونه دارایی‌های خودش را سبک و شرطی نگه می‌دارد، اما امتیاز Lighthouse یک عدد ثابت نیست: به قالب، محتوا، سرعت سرور و افزونه‌های دیگر بستگی دارد. جزئیات و روش اندازه‌گیری در مستندات مخزن آمده است.

== Installation ==

1. ZIP آمادهٔ نصب را از صفحهٔ Releases بردارید (فایل `hamishop-elementor-widgets-<version>.zip`). از گزینهٔ «Download ZIP» در صفحهٔ کد استفاده نکنید؛ آن فایل سورس است و ساختار پوشهٔ نصبی را ندارد.
2. پیشخوان ← افزونه‌ها ← افزودن ← بارگذاری افزونه ← همان ZIP را انتخاب کنید.
3. WooCommerce و Elementor باید نصب و فعال باشند.
4. در المنتور، دستهٔ «حامی» را باز کنید و ویجت موردنظر را بکشید.
5. رنگ‌ها و فونت را یک‌بار در HamiShop ← پالت رنگی تنظیم کنید.

== Frequently Asked Questions ==

= آیا به قالب خاصی وابسته است؟ =

نه. با هر قالبی که المنتور را پشتیبانی کند کار می‌کند؛ استایل‌ها داخل محدودهٔ خود ویجت هستند و به قالب نشت نمی‌کنند.

= ویجت‌های صفحه محصول را چطور اضافه کنم؟ =

یا از ویجت‌های تک‌بخشی استفاده کنید، یا ویجت «صفحهٔ کامل محصول» را بکشید و همهٔ بخش‌ها را با یک ویجت بگیرید.

= چرا بعضی ویجت‌ها در پنل المنتور نیستند؟ =

بخش‌های مستقل (۴۷ مورد) پیش‌فرض خاموش‌اند تا پنل شلوغ نشود. از HamiShop ← کنترل ویجت‌ها روشنشان کنید.

= داده‌های من با آپدیت از بین می‌رود؟ =

نه. ویجت‌ها، تنظیمات صفحه‌ها و پالت رنگی جای دیگری ذخیره می‌شوند و آپدیت فقط کد را عوض می‌کند. پاک‌سازی کش‌ها در پس‌زمینه و مرحله‌ای انجام می‌شود.

== Changelog ==

نسخه‌های جدیدتر در این فایل؛ تاریخچهٔ کامل ۲۴۰ نسخه در `CHANGELOG.md` مخزن.

= 1.11.223 =
* Feature: The palette font list now also reads the WordPress font library's `custom` and `default` origins and the Elementor kit's `custom_typography`, so fonts added through the Site Editor finally show up.
* Feature: A free-text font field next to the list. A variable font that a theme loads with its own @font-face is registered in no option at all, so no list can enumerate it — typing the exact family name now works.

= 1.11.222 =
* Fix: Updates no longer take the site down. The Elementor cache flush and the stock migration used to run on `init` — i.e. inside the first visitor's request — and could return HTTP 503 for several minutes on a shared host.
* Fix: The element-cache flush is now a background job with a 2s budget, a lock and a resumable cursor. It runs from WP-Cron, or from an admin page load when cron is unavailable. Never from a customer request.
* Fix: Only pages that actually contain HamiShop widgets have their Elementor CSS rebuilt, one batch at a time, instead of `files_manager->clear_cache()` wiping the whole site.
* Fix: Page caches are purged per affected URL. The site-wide purge is now opt-in (`hsw_upgrader_full_purge`).
* Fix: The stock migration is batched (200 products per query) and resumes after a timeout or a fatal instead of walking 2000 products in one request.
* Fix: A failed step is logged, retried twice, then skipped — the site keeps working and the merchant gets a notice on the HamiShop dashboard.

= 1.11.221 =
* Fix: The header now obeys the site palette. Eleven colour controls carried hard-coded defaults that Elementor printed into the page CSS, so a saved header kept its blue/grey values and the palette never reached it.
* Fix: Header font now follows HamiShop -> palette font (`--hsw-font`) instead of a hard-coded Vazirmatn stack.
* Fix: Top bar, nav row, mega panel and cart drawer surfaces follow the palette instead of literal white/navy values.
* Fix: Header text on primary fills uses the palette's computed `on-primary` colour, so a light primary no longer yields unreadable white text.
* Fix: The per-widget font mode «فونت طرح (وزیرمتن لوکال)» was a no-op; it now forces the bundled Vazirmatn.
* Note: Per-widget header colours are now opt-in (empty = palette). A header coloured by hand before this version must be re-picked once.

= 1.11.220 =
* Fix: Digital and downloadable products checkout flow reliability — accurately detects downloadable products even when merchant leaves "Virtual" checkbox unticked in WooCommerce, preventing shipping and delivery address fields from showing.
* Fix: Prevent checkout summary button blocking digital orders with missing address error when only name and phone are needed.
* Feature: Modernize checkout input fields with crisp leading micro-icons (name, phone, email, state, city, address, postcode).
* Feature: Modernize digital delivery notice banner and customer credentials card with edit drawer.
* Feature: Support optional email field in digital checkout for order download links.

= 1.11.219 =
* Feature: Modernize checkout address fields, select dropdowns, labels, and textarea with sleek elevation, rounded borders, and smooth focus glow.
* Feature: Modernize discount coupon forms in cart, checkout, and pay pages with unified capsule input-groups and chip-styled applied coupon tags.
* Fix: Address submit button (`.hsw-ckaddr__fsubmit`) background transparency override by button reset rule in checkout CSS.
* Feature: High-specificity Elementor style controls for address submit button normal and hover background/text colors (`fsubmit_bg`, `fsubmit_c`, `fsubmit_bg_hover`, `fsubmit_c_hover`).
* Feature: Add customizable button text control (`fsubmit_label`) for address submit button in both standalone and composite checkout widgets.
* Fix: Rebuild minified cart and checkout stylesheets.

= 1.11.218 =
* Fix: رفع خطای `403 Forbidden` روی `wp-comments-post.php` هنگام ثبت نظر در مقالات (`.hsw-pp__form`) و ثبت دیدگاه و امتیاز در صفحه محصول (`.hsw-review-form`) برای کاربران مهمان و مشتریان واردشده؛ ارسال نظرات اکنون به‌صورت ایجکسی از طریق `admin-ajax.php` (`hsw_submit_comment`) همراه با پیام وضعیت درجا و پشتیبان `admin-post.php` انجام می‌شود.
* Fix: خنثی‌سازی کامل رنگ صورتی هاور قالب Hello Elementor (`#c36`) روی دکمه‌های ثبت دیدگاه و ثبت نظر (`.hsw-pp__submit`، `.hsw-pp__seclink`، `.hsw-review-submit`).
* UI: تغییر آیکن دکمه‌های سبد خرید در گرید آرشیو محصولات (`.hsw-ag__cta.hsw-d` و `.hsw-ag__cta--m.hsw-m`) به آیکن خطی و تمیز اختصاصی `shopping_cart` (همسان با کارت‌های لوپ).

= 1.11.211 =
* Fix: دریافت آنی پیام‌های ربات تلگرام (`poll_now`) بدون نادیده‌گرفتن اولین آپدیت (`offset = 0`) و بدون حذف پیام‌های در صف هنگام `deleteWebhook`؛ نمایش لینک مستقیم اتصال ربات تلگرام در فرم ورود و ثبت‌نام و پشتیبانی از اتصال توکنی `/start <token>` در تأیید دوعاملی.
* Fix: اعمال مستقیم رنگ عنوان هدر (`.hsw-loop__title`)، زیرعنوان و نام محصول (`.hsw-loop__name`) در کروسل‌های لوپ (شگفت‌انگیز، پرفروش‌ترین‌ها و محبوب‌ترین‌ها) و خنثی‌سازی کامل رنگ صورتی پیش‌فرض قالب Hello Elementor (`rgb(204, 51, 102)` / `#c36`) در تمام ویجت‌ها.
* New: نمایش توضیحات دسته‌بندی نوشته‌ها (`term_description`) در پایین صفحه آرشیو وبلاگ و گرید مقالات (همراه با تشخیص خودکار آرشیو دستهٔ وردپرس و کنترل‌های استایل اختصاصی).
* SEO: حذف تگ‌های `<a href="#...">` از فهرست مطالب سایدبار تک‌نوشته (`.hsw-pp__side` / `.hsw-pp__sidelink`) و تغییر پیش‌فرض فهرست مطالب درون‌متنی به حالت بدون لینک لنگر (`button` + `data-nosnippet`) برای جلوگیری از هدررفت بودجه خزش (Crawl Budget).
* Fix: تولید خودکار تاریخ‌های واقعی شمسی (`HSW_Jalali`) در ویجت انتخاب زمان تحویل تسویه‌حساب با امکان غیرفعال‌سازی کامل بخش، و خواندن زمان انتخابی واقعی از سشن ووکامرس در صفحه پرداخت.
* UI: یکپارچه‌سازی آیکن (`add_shopping_cart`)، ابعاد و استایل دکمه‌های سبد خرید آرشیو محصولات (`.hsw-ag__cta.hsw-d` و `.hsw-ag__cta--m.hsw-m`) بین محصولات ساده و متغیر.

== Upgrade Notice ==

= 1.11.222 =
نسخهٔ ۱.۱۱.۲۲۱ را نصب نکنید: مسیر آپدیت قدیمی را دارد و می‌تواند چند دقیقه سایت را ۵۰۳ کند. مستقیم ۱.۱۱.۲۲۲ یا بالاتر بروید.
