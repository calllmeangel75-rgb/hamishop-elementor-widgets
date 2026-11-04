=== HamiShop Elementor Widgets ===
Contributors: hamisys
Tags: elementor, woocommerce, widgets, product loop, rtl, persian
Requires at least: 6.0
Tested up to: 7.1.4
Requires PHP: 7.4
Stable tag: 1.11.225
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

= 1.11.225 =
* Fix: Header menus no longer drop their third level. `wp_nav_menu` was called with `depth => 2`, so a sub-sub-item such as «ابزار قالی‌بافی و پرداخت فرش → …» never rendered and there was nothing in the panel to explain why. Depth is now unlimited.
* Fix: A nested sub-menu opens beside its parent instead of on top of the list it belongs to, with the caret pointing at the side the flyout opens on.

= 1.11.224 =
* Fix: The upgrade job no longer deletes Elementor CSS and waits for a visitor to rebuild it. On a live shop that cold window saturated PHP-FPM and nginx answered 503 for several minutes (reported on 1.11.223).
* Fix: Each affected page's CSS is now rewritten in place, one post per step, inside the background tick — the stylesheet is never missing.
* Fix: The per-tick time budget is enforced per post, not per query, so a batch of 40 pages can no longer run for ten seconds in one request.
* Fix: WP Rocket's per-post cache is purged for the pages that were rebuilt.

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

== Upgrade Notice ==

= 1.11.222 =
نسخهٔ ۱.۱۱.۲۲۱ را نصب نکنید: مسیر آپدیت قدیمی را دارد و می‌تواند چند دقیقه سایت را ۵۰۳ کند. مستقیم ۱.۱۱.۲۲۲ یا بالاتر بروید.
