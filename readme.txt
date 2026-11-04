=== HamiShop Elementor Widgets ===
Contributors: hamisys
Tags: elementor, woocommerce, widgets, product loop, rtl, persian
Requires at least: 6.0
Tested up to: 7.1.4
Requires PHP: 7.4
Stable tag: 1.11.239
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

= 1.11.239 =
* Fix: The ticket CSV counted only approved replies, while `get_comments()` — used by the ticket list — defaults to `status => all`, which WordPress turns into `comment_approved = '0' OR comment_approved = '1'`. A ticket with a reply waiting for moderation therefore showed a lower number in the CSV than in the admin list. The query now matches `IN ('0', '1')`; spam and trash are excluded by both.
* Verified against a ticket holding one approved, one pending, one spam and one trash reply: `get_comments()` = 4, `reply_counts()` = 4.
* Two checks in `tools/ci-checks.php` fail if the approved-only filter comes back.

= 1.11.238 =
* Fix: The transient cleanup used `LIKE '_transient_hsw_lp_%'` with an unescaped `_`. In SQL that is a single-character wildcard, so the pattern matched more option names than intended — and because it started with a wildcard, MySQL could not use the `option_name` index and scanned the whole table. It is built with `$wpdb->esc_like()` now.
* Fix: «مشتریان طلایی» checked that `wc_order_stats` exists but then joined `wc_customer_lookup` anyway. A site missing the second table got a SQL error instead of an empty list. Both are checked.
* Fix: Best sellers decided HPOS from the existence of the `wc_orders` table. A shop that switched HPOS off keeps that table, and the list came back empty. It now asks WooCommerce (`OrderUtil::custom_orders_table_usage_is_enabled()`), with the table check kept as a fallback.
* Fix: The ticket CSV export ran one `get_comments( count => true )` per ticket. It is one grouped query in chunks of 500 now; the file is byte-for-byte the same.
* Fix: The price-slider bounds stayed cached for 12 hours after a product changed, so a product priced above the old maximum could not be reached. The transient is cleared on product save.
* Perf: The wallet top-up sum is memoised per request — the wallet screen asked for it twice and walked every order of the customer both times.

= 1.11.237 =
* Fix: «تحویل دیجیتال سفارش» no longer appears on a cart that holds a physical product. `is_digital()` returned true whenever `WC()->cart->needs_shipping()` was false — which WooCommerce reports for every cart on a shop with no shipping method configured. It now decides from the cart items only.
* Fix: The checkout CTA could still reload the page it was on when the customer already had a saved address and the «next step» link pointed at the checkout itself. The self-navigation guard now runs on every path.
* The digital-checkout regression test now covers the shipping-disabled shop and uses a real `WC_Product` stub.

= 1.11.236 =
* Fix: The widget's own «لینک مرحله بعد» now counts as a next step. 1.11.233 only looked at «صفحهٔ پرداخت», so a shop that set the link on the summary widget had it ignored and the order was placed instead of navigating to the pay step.

= 1.11.235 =
* Fix: Clicking «ادامه و ورود به مرحله پرداخت» could submit the header search form and land on `/?s=` — `.woocommerce-checkout` is a body class, so the fallback selector matched the wrong form. The fallback now uses WooCommerce's own form markers only, rejects anything containing a search field, and does nothing when no checkout form is found.

== Upgrade Notice ==

= 1.11.222 =
نسخهٔ ۱.۱۱.۲۲۱ را نصب نکنید: مسیر آپدیت قدیمی را دارد و می‌تواند چند دقیقه سایت را ۵۰۳ کند. مستقیم ۱.۱۱.۲۲۲ یا بالاتر بروید.
