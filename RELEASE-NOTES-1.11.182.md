# HamiShop 1.11.182 — هارنس پیش از لایو به ریپو اضافه شد (کد افزونه بدون تغییر)

## چه تغییر کرد
فقط `tests/qa/` — فایل‌های `prelive*.php` که در چهار دور رفع (۱.۱۱.۱۷۸ تا
۱.۱۱.۱۸۱) به‌عنوان شاهد قرمز→سبز استفاده شدند، حالا در ریپو ثبت شدند. تا وقتی
قانون «هیچ کامیتی» روی دور رفع حاکم بود، این فایل‌ها track نشده نگه داشته
شدند؛ حالا که کار منتشر شده، شاهد هم باید در مخزن باشد.

- `prelive-lib.php` — لایهٔ استاب: options/usermeta/transient در یک JSON store
  با `flock`، `wpdb` جعلی که upsert تراتل را با `LAST_INSERT_ID` شبیه‌سازی
  می‌کند، ضبط `wp_mail`/redirect/JSON و جاسوسی `dbDelta`.
- `prelive.php` (۳۰) · `prelive-0a.php` (۴) · `prelive-0b.php` (۳) ·
  `prelive-email.php` (۵) · `prelive-routing.php` (۱۲) — احراز هویت، کلید
  OTP، دامنهٔ fail-open دومرحله‌ای، نابودی سشن، شمارش حساب، سوئیچ خاموشی کیف
  پول، نصب Opt-in جداول، تأیید ایمیل، مسیریابی تسویه و لیست سفید «نت ملی».
- `prelive-db.php` — اثبات‌های **دیتابیس واقعی** (۳۰ hit موازی با limit 1 و 5،
  جدول حذف‌شده، ۱۰ claim موازی) با `HSW_QA_DSN/HSW_QA_USER/HSW_QA_PASS`.

هر سوئیت خط شاهد خودش را چاپ می‌کند و با `HSW_PRELIVE_*` می‌توان کلاس قدیمی
را جایگزین کرد تا قرمزبودن دوباره قابل تکرار باشد.

## چرا روی کیفیت انتشار اثر ندارد
- `tests/` در `.gitattributes` با `export-ignore` است؛ **در ZIP انتشار نمی‌آید**
  (`build-release.py` هم tests و tools را حذف می‌کند).
- CI این فایل‌ها را اجرا نمی‌کند؛ فقط `php -l` روی همهٔ فایل‌ها (این‌ها هم) در
  PHP 7.4 تا 8.3 و چک‌های `ci-checks.php` سبز هستند.
- هیچ فایل PHP در `includes/`، `widgets/`، `assets/` یا فایل اصلی افزونه در این
  نسخه تغییر نکرده است.

## وضعیت تست
`prelive` ۳۰/۳۰ · `prelive-email` ۵/۵ · `prelive-0a` ۴/۴ · `prelive-0b` ۳/۳ ·
`prelive-routing` ۱۲/۱۲ · `harness` ALL CLEAN · تست واحد ۳۳/۰ ·
`ci-checks` ۱۶/۱۶ · `php -l` صفر خطا.
