=== HamiShop Elementor Widgets ===
Contributors: hamisys
Tags: elementor, woocommerce, widgets, product loop, rtl, persian
Requires at least: 6.0
Tested up to: 7.1.4
Requires PHP: 7.4
Stable tag: 1.11.253
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

۶۳ ویجت فروشگاهی فارسی و راست‌به‌چپ برای المنتور و ووکامرس: لوپ محصول، صفحه محصول، هدر، سبد، تسویه، آرشیو، مجله و پنل حساب کاربری — با پالت رنگی سراسری، فونت لوکال و بارگذاری شرطی.

== Description ==

HamiShop مجموعه‌ای از ویجت‌های المنتور است که صفحه‌های یک فروشگاه ووکامرس را بدون کدنویسی می‌سازد. هر ویجت یک بخش واقعی از فروشگاه است، نه یک بلوک تزئینی: داده را از خود ووکامرس می‌خواند، با هندلرهای بومی کار می‌کند و در نبود ووکامرس یا المنتور خطا نمی‌دهد.

= هفت خانواده، ۶۳ ویجت =

* **هدر و ناوبری (۴)** — هدر فروشگاهی با نوار اعلان، جستجوی محصول و مگامنو؛ هدر مینیمال شیشه‌ای؛ **هدر GC** با نوار شناور، نشان مدار و پنل‌های کشویی؛ فوتر چسبان موبایل.
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

= 1.11.253 =
* Fix: با خاموش‌کردن کیف پول، مسیرهای REST شارژ/پرداخت/تبدیل امتیاز هم بسته می‌شوند (قبلاً فقط ثابت اضطراری چک می‌شد).
* Fix: پرچم `hsw_free_shipping` روی نرخ ارسال واقعاً ذخیره می‌شود؛ نوشتن روی `meta_data` بی‌اثر بود.
* Fix: خاموش‌کردن یا تغییر سقف ارسال رایگان، کش نرخ ارسال ووکامرس را برای همهٔ نشست‌ها بی‌اعتبار می‌کند.
* Fix: پنل کنترل ویجت‌ها توضیح می‌دهد «خاموش» یعنی حذف از فهرست المنتور، نه توقف بارگذاری فایل یا خاموشی بخش‌های ویجت جامع.

= 1.11.252 =
* Fix: رفع ۱.۱۱.۲۵۱ برای پاک‌سازی کش کار نمی‌کرد — `add_action` فقط یک آرگومان می‌داد و `$option` همیشه خالی بود. حالا با `update_option()` واقعی تست شد: تغییر REST صفر پاک‌سازی.
* Fix: دو صفحهٔ تنظیمات باقی‌مانده (امنیت و مدیریت ویجت) هنوز به slug خام POST می‌کردند؛ حالا هر پنج صفحه یک رفتار دارند.
* Fix: برچسب XML-RPC حالا «وضعیت فیلتر HamiShop» است، نه وضعیت نهایی همهٔ فیلترها.

= 1.11.251 =
* Fix: گارد REST کاربران با سیاست خودش یکی شد — ویرایشگر/نویسنده دیگر روی `/users/me` خطا نمی‌گیرند و مشتری فقط رکورد خودش را می‌بیند.
* Fix: فرم‌های تنظیمات فقط وقتی به آدرس سفارشی پیشخوان POST می‌کنند که گارد واقعاً فعال باشد؛ در حالت ثابت اضطراری یا نبودِ rewrite به `wp-admin` برمی‌گردند.
* Fix: تشخیص بلاک ووکامرس با `has_block('woocommerce/')` هرگز مطابقت نمی‌داد و استایل بلاک‌ها حذف می‌شد؛ حالا فضای‌نام بلاک بررسی می‌شود.
* Fix: هشدار تعارض درگاه دیگر برای دامنه‌ای که در allowlist است نمایش داده نمی‌شود.
* Fix: تغییر تنظیمات غیرظاهری امنیت دیگر کل کش سایت را پاک نمی‌کند.
* Fix: `?author=1` حالا قالب ۴۰۴ را نشان می‌دهد، نه آرشیو نویسنده با کد ۴۰۴.
* Fix: متن XML-RPC وضعیت واقعی فیلتر را نشان می‌دهد و متن صفحهٔ ورود دربارهٔ فونت درست شد.

= 1.11.250 =
* Fix: قانون `.hsw-c .hsw-csummary__cta:hover` (specificity بیشتر) رنگ hover پالت را دوباره بر رنگ انتخابی ویجت غالب می‌کرد؛ حالا همان زنجیرهٔ متغیرها را دارد.
* Fix: حالت `:active` دکمهٔ سبد و نوار پایین و CTA تسویه هم از رنگ انتخابی مشتق می‌شود.
* Fix: دکمهٔ موبایل سبد پایهٔ خود را از `--hswc-cta` می‌خواند و کنترل رنگش هیچ اثری نداشت.

= 1.11.249 =
* Fix: ذخیرهٔ پالت با فونت معتبر دیگر خطای «رنگ معتبر نبود» نمی‌دهد (حلقه فقط روی نقش‌های رنگی می‌گردد).
* Fix: `contrast_text()` رنگ واقعیِ بازگشتی را می‌سنجد؛ `#777777` حالا `#000000` با ۴٫۶۹:۱ می‌گیرد، نه `#111827` با ۳٫۹۶:۱.
* Fix: حالت‌های «فونت طرح» و «ارث‌بری» دیگر به قانون عمومی فونت پالت نمی‌بازند (بلوک بعد از آن منتقل شد)؛ Typography المنتور همچنان مقدم است.
* Fix: متن دکمهٔ خرید در hover از رنگ متن پالت پیروی می‌کند، نه `#fff` سخت‌کد.
* Fix: CTA سبد و تسویه در hover رنگ انتخابی خودشان را نگه می‌دارند و به رنگ hover پالت نمی‌پرند.

= 1.11.248 =
* Fix: رنگ عنوان هدر حساب کاربری دیگر با `!important` قفل نشده — کنترل «رنگ متن» المنتور اثر می‌گذارد و پیش‌فرض سفید می‌ماند.
* Fix: کنترل پس‌زمینهٔ هدر حساب حالا گرادیان را جایگزین می‌کند؛ قبلاً رنگ پشت گرادیان پنهان بود و هیچ اثری نداشت.
* Fix: حالت‌های «فونت طرح» و «ارث‌بری» دیگر روی فرزندان `!important` نمی‌گذارند؛ Typography صریح المنتور مقدم است.
* Fix: `readable_on()` روی پس‌زمینهٔ تیره به سمت سفید می‌رود (قبلاً متن را تیره‌تر می‌کرد تا محو شود) و `contrast_text()` بیشترین کنتراست را انتخاب می‌کند، نه آستانهٔ ۳:۱.
* Fix: رنگ نامعتبر در پالت دیگر بی‌صدا به پیش‌فرض برنمی‌گردد؛ مقدار قبلی حفظ و فیلدهای ردشده نام‌برده می‌شوند.
* Fix: نام فونت دستی قبل از چاپ در `<style>` پاک‌سازی می‌شود (نقل‌قول و آکولاد حذف).

= 1.11.247 =
* Fix: اگر ذخیرهٔ روش ارسال خطا بدهد، دکمهٔ «ادامه» دیگر رد نمی‌شود — روی همان صفحه می‌ماند و پیام خطا نمایش داده می‌شود (قبلاً با نرخ قبلی ادامه می‌داد).
* Fix: الزام قوانین یک سیاست واحد دارد؛ لینک سفارشی فقط نمایش است و الزام از تنظیمات فروشگاه یا فیلتر `hsw_terms_required` می‌آید.
* Fix: صفحهٔ بی‌ربطی که در تنظیمات مسیر خرید انتخاب شده بود، از طریق `wp_robots` noindex می‌گرفت در حالی که متای سئوی آن دست‌نخورده بود.
* Fix: مارکر قدیمی بدون محتوا دیگر صفحه را وصل نمی‌کند؛ و صفحهٔ درست با slug متفاوت حالا خودکار پیدا می‌شود.

= 1.11.246 =
* New: «رفتار خرید» در ویجت جعبه خرید — دو حالت. «استپر» همان رفتار فعلی است (استپر بعد از افزودن ظاهر می‌شود و سبد را به‌روز می‌کند). «کنار دکمهٔ خرید» استپر را همیشه کنار «خرید آنلاین» می‌گذارد و هیچ درخواستی به سرور نمی‌فرستد؛ تعداد انتخابی فقط با زدن دکمهٔ خرید به سبد اضافه می‌شود.
* New: در حالت کنار دکمه، استپر از ۱ شروع می‌شود (معنی «چند تا اضافه کنم»)، سبد خرید آیکون حذف ندارد، و در تعداد ۱ دکمهٔ منفی سر جایش می‌ماند و فقط کلیک را رد می‌کند.

= 1.11.245 =
* New: «راه‌اندازی مسیر خرید» در پنل «کنترل ویجت‌ها» — وضعیت واقعی صفحهٔ پرداخت و صفحهٔ تکمیل خرید را نشان می‌دهد و با یک دکمه، فقط صفحه‌هایی را می‌سازد که وجود ندارند.
* New: صفحه‌ای که از قبل وجود دارد فقط «وصل» می‌شود، نه بازسازی؛ و صفحه‌ای که نشانی /payment/ یا /complete/ را در اختیار دارد ولی مال حامیشاپ نیست، هرگز دست نمی‌خورد (صفحه با نشانی /hamishop-payment/ ساخته می‌شود).
* New: شورت‌کدهای `[hamishop_payment]` و `[hamishop_complete]` — صفحه در هر قالبی رندر می‌شود و با ذخیرهٔ مجدد المنتور از بین نمی‌رود.
* New: هر دو صفحهٔ مسیر خرید `noindex, nofollow` می‌شوند، مثل صفحهٔ سبد و تسویهٔ خود ووکامرس.
* Fix: صفحهٔ سبد پس از لغو پرداخت در درگاه خراب می‌شد — سبد خالی بود ولی سفارش در انتظار در نشست مانده بود، صفحه گرید «خالی» را انتخاب می‌کرد و ویجت اقلام ردیف سفارش را داخلش می‌کشید (بدون جمع کل، کوپن و دکمه). ویجت‌های سبد دیگر هرگز سفارش را به‌جای سبد رندر نمی‌کنند؛ مرحلهٔ پرداخت دست‌نخورده است.
* Fix: وعدهٔ ارسال رایگان با خاموش‌بودن کلید حذف شد — بنر سبد و راهنمای خلاصه تسویه حالا `promise_threshold()` را می‌خوانند که بدون کلید، صفر برمی‌گرداند.
* Fix: هزینهٔ صفر ارسال دیگر «رایگان» تفسیر نمی‌شود. پنج حالت صریح (رایگان / هزینه‌دار / در انتظار / نیازی نیست / در دسترس نیست) و یک منبع واحد در `shipping_state()`؛ مقصد بدون نرخ حالا «روش ارسال در دسترس نیست» می‌گوید.
* Fix: متن پیش‌فرض «رایگان (خرید بالای ۵۰۰ هزار)» و «… ۵۰۰ تومان» حذف شد و ویجت‌های ذخیره‌شده با همان متن، «رایگان» نشان می‌دهند.
* Fix: نبودن چک‌باکس قوانین دیگر «پذیرش» حساب نمی‌شود؛ الزام از تنظیمات خود فروشگاه پیروی می‌کند و کنترل غایب، خطای پیکربندی است.
* Fix: خطای ذخیرهٔ روش ارسال بی‌صدا نادیده گرفته نمی‌شود — انتخاب به حالت تأییدشده برمی‌گردد و پیام نشان داده می‌شود.
* Fix: ردیف «کسر از موجودی کیف پول» و کلید بی‌اثر آن حذف شد؛ کارت کیف پول می‌ماند ولی دیگر کسری وعده نمی‌دهد.
* Fix: `hsw_pay_page_id` هنگام حذف افزونه پاک نمی‌شد.

= 1.11.244 =
* New: «هدر GC» — a third header widget. A rounded bar floats over the page and docks into a full-width blurred bar on scroll; the brand mark is a «G» drawn as a circuit trace that draws itself; a gradient underline follows the pointer; dropdown panels stagger their rows in. The mark, brand text, every menu row, six panel slots (icon + title + description + footer), the cart badge, the account button and the mobile drawer are all controls.
* New: «رفتار با اسکرول» — سه حالت: چسبان (پیش‌فرض)، پنهان‌شدن با اسکرول پایین و بازگشت فوری با اسکرول بالا، یا درون جریان صفحه.
* New: نشان برند به‌صورت خودکار همان لوگوی سایت است؛ لازم نیست دوباره آپلود شود. نشان مدار GC گزینه و جایگزین است.
* Changed: پنل‌های هدر GC حالا چهار فهرست جدا هستند («پنل ۱» تا «پنل ۴») — هر ردیف همان‌جایی است که دیده می‌شود، بدون همگام نگه‌داشتن شمارهٔ پنل در دو فهرست.
* Changed: دکمهٔ علاقه‌مندی از آیتم‌های سبد خرید حذف شد؛ کاری انجام نمی‌داد و در سبد خرید معنا ندارد.
* New: کلید «نمایش عنوان بالای کارت» در زمان‌بندی تحویل، و متن پیش‌فرض «روز انتخاب تحویل به پست».
* Fix: متن دکمهٔ «ادامه و پرداخت» در تسویه حساب سیاه روی زمینهٔ سرمه‌ای بود (کنتراست ۱٫۱:۱). قاعدهٔ `.hsw-k a { color: inherit }` با specificity (0,1,1) از رنگ خودِ دکمه (0,1,0) قوی‌تر بود؛ حالا `:where()` شده و دکمه رنگ پالت را می‌گیرد (۹٫۱:۱).
* Fix: کلید عنوان زمان‌بندی خاموش نمی‌شد — `opt()` رشتهٔ خالی را «تنظیم‌نشده» می‌گیرد و پیش‌فرض را برمی‌گرداند، که همان مقدار حالت خاموش سوئیچ است.
* Fix: «هدر GC» never reached the Elementor panel — the file was required by `register_widgets()` but missing from the class list that follows it, and that list skips unknown names silently. A new check now fails the build when a widget class is not in it.
* Fix: the blog page widget threw `Call to undefined method HSW_Widget_Blog_Page::is_demo()`; the method existed on the archive, cart and product bases but not on the blog base, so the description box never rendered. The blog grid threw the same way over `HSW_Composite::is_active()`, which was never written — it now uses `is_composite_part()`.
* Fix: the harness now audits method resolution (`$this->x()` and `HSW_*::x()`), so an undefined method fails the build instead of surfacing as an admin banner on a customer's site.
* Fix: the drawer's dark button was dark-on-dark (`#172b25` on `#06271f`); `.hsw-gc a { color: inherit }` outranked the button rule. Every anchor colour rule now carries the `.hsw-gc` prefix — measured white on `#06271f` after.
* Fix: a long store name pushed the page 172px sideways at 320px and 102px at 390px (`flex-shrink: 0` on the brand). It now truncates with an ellipsis — overflow measured 0 at 320/360/390/414/768/1024/1100/1280/1440.

= 1.11.243 =
* Fix: The article page scrolled sideways on a phone (+52px at 390px). The prev/next grid used `1fr`, which cannot shrink below its content, so a long neighbouring title pushed the column to 492px inside a 342px box. Now `minmax(0, 1fr)` plus `min-width: 0` on the item — measured at 320/360/390/768/1024/1280.
* Fix: The 62px bottom padding that clears the fixed dock was unconditional, so a widget with the dock switched off still had dead space on mobile. It is scoped to `hsw-pp--dock`, which is only printed when the dock renders.
* Fix: With no product in the article, the dock's «ثبت دیدگاه» button still linked to `#hsw-comments` even when that section was not printed. Same guard as the «نظر» button.

= 1.11.242 =
* Fix (security): The post widget rendered a draft or private post when its numeric «شناسه نوشته» field pointed at one, and a password-protected post rendered in full because the body bypassed `get_the_content()`. Non-published posts now require `edit_post` capability, and `post_password_required()` prints WordPress's own password form instead.
* Fix: On a single post, «مجله», «بازگشت به مجله», «مشاهده همه» and the breadcrumb category all linked back to the article being read — `blog_base_url()` took the current request path as the base. It uses the posts page there now, and the category link goes to the real category archive.
* Fix: Turning «فهرست مطالب» off hid the in-article list but not the sidebar one. Both follow the option now.
* Fix: The mobile dock's «نظر» button linked to `#hsw-comments` even when that section was not printed. It is only a link when the anchor exists.
* Fix: The sidebar heading said «پربازدیدترین مقالات» while the query sorts by comment count. Renamed to «پربحث‌ترین مقالات».

= 1.11.241 =
* Fix: The account panel, the blog post panel and the product sheet hard-coded the bundled Vazirmatn instead of reading `--hsw-font`, so on a shop whose palette font is something else those three screens rendered in a different face from the rest of the site. They use the palette font now, like every other stylesheet.
* No change when the palette font is the default: measured in Chrome, `.hsw-ac` computes to the same font before and after. With the palette set to another family, all three follow it.
* The `!important` on the product sheet is untouched — only the family now comes from the palette.

= 1.11.240 =
* Fix: «فونت پایه ویجت» was offered on every widget's Style tab but only four widgets applied it — on account, cart, checkout, header, archive, product, blog and footer it did nothing. The class now goes on the Elementor wrapper through `elementor/frontend/widget/before_render`. `palette` (the default) adds no class, so pages that never touched the control render byte-identical HTML (verified by diffing home, shop and account before/after).
* New: `add_filter( 'hsw_disable_elementor_google_fonts', '__return_false' );` keeps Elementor's Google Fonts on archive and account pages. The suppression is unchanged by default.
* Docs: the `preload_font()` comment claimed the font is only preloaded on pages that use it. It is preloaded on every front-end page, because `hsw-fonts` is registered on every request and no widget has rendered yet at `wp_head`. Comment corrected; behaviour untouched.

= 1.11.239 =
* Fix: The ticket CSV counted only approved replies, while `get_comments()` — used by the ticket list — defaults to `status => all`, which WordPress turns into `comment_approved = '0' OR comment_approved = '1'`. A ticket with a reply waiting for moderation therefore showed a lower number in the CSV than in the admin list. The query now matches `IN ('0', '1')`; spam and trash are excluded by both.
* Verified against a ticket holding one approved, one pending, one spam and one trash reply: `get_comments()` = 4, `reply_counts()` = 4.
* Two checks in `tools/ci-checks.php` fail if the approved-only filter comes back.

== Upgrade Notice ==

= 1.11.222 =
نسخهٔ ۱.۱۱.۲۲۱ را نصب نکنید: مسیر آپدیت قدیمی را دارد و می‌تواند چند دقیقه سایت را ۵۰۳ کند. مستقیم ۱.۱۱.۲۲۲ یا بالاتر بروید.
