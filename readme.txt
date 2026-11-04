=== HamiShop Elementor Widgets ===
Contributors: hamisys
Tags: elementor, woocommerce, widgets, product loop, rtl, persian
Requires at least: 6.0
Tested up to: 7.1.4
Requires PHP: 7.4
Stable tag: 1.11.229
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

= 1.11.229 =
* Fix (CLS): the subcategory pill row no longer wraps. When the theme font finished loading the row grew from 33px to 74px and pushed the page down — a single 0.2327 layout shift, which was the entire CLS of the shop page.
* Fix (a11y): the category tree's items now carry `role="treeitem"` and `aria-level`, so `role="tree"` finally has the children it requires.
* Fix (a11y): the closed mobile drawer is `inert`, so its links are no longer focusable inside an `aria-hidden` container.
* Fix (a11y): small text (price labels, search select, guide answers, stock notes) uses AA-safe palette variants derived at render time, so a muted colour that lands at 4.39:1 on the merchant's surface is darkened to 4.5:1 instead of failing.

= 1.11.228 =
* Feature: Empty product categories get a real empty state — icon, title, description and a «بازگشت به فروشگاه» button — instead of one grey line, and it is now visible on mobile too. Every string and colour is a control in the archive grid widget.
* Feature: A second title is used automatically when the page is empty because of filters or search, so the message never claims the category has nothing when it was a filter that hid it.
* Feature: «پیش‌نمایش حالت خالی در ویرایشگر» — swaps the demo cards for the empty block so you can style it in Elementor.
* Feature: Header chips hide empty categories by default (`pills_hide_empty`). The filter sidebar already did.

= 1.11.227 =
* Fix: The mobile «فیلتر پیشرفته» sheet never appeared on the archive composite. The column that holds the filters widget was hidden with `display: none` below 768px — and the bottom sheet lives inside that column, so it opened at 0×0. The desktop sidebar boxes are hidden instead; the column still collapses to zero height, so the layout-shift fix is unchanged.
* Note: 1.11.226 fixed a different, real problem (LiteSpeed delaying our scripts). Both were needed.

= 1.11.226 =
* Fix: The mobile «فیلتر پیشرفته» button did nothing on sites where LiteSpeed's Delay JS rewrites scripts to `type="litespeed/javascript"`. The first tap was spent loading the script instead of opening the sheet.
* Fix: Our script tags now carry `data-no-optimize` / `data-no-delay`, so LiteSpeed and WP Rocket leave them out of JS delay.
* Fix: A small inline handler opens and closes the bottom sheets before the main bundle arrives, so the button works even when a page optimizer still delays it.

= 1.11.225 =
* Fix: Header menus no longer drop their third level. `wp_nav_menu` was called with `depth => 2`, so a sub-sub-item such as «ابزار قالی‌بافی و پرداخت فرش → …» never rendered and there was nothing in the panel to explain why. Depth is now unlimited.
* Fix: A nested sub-menu opens beside its parent instead of on top of the list it belongs to, with the caret pointing at the side the flyout opens on.

== Upgrade Notice ==

= 1.11.222 =
نسخهٔ ۱.۱۱.۲۲۱ را نصب نکنید: مسیر آپدیت قدیمی را دارد و می‌تواند چند دقیقه سایت را ۵۰۳ کند. مستقیم ۱.۱۱.۲۲۲ یا بالاتر بروید.
