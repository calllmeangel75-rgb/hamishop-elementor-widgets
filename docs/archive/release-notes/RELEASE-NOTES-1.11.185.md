# HamiShop 1.11.185 — رفع باگ خودِ HSW_Optimizer (۱.۱۱.۱۸۴ با لایت‌اسپید کار نمی‌کرد)

## چه اتفاقی افتاده بود
۱.۱۱.۱۸۴ برای خارج‌کردن CSS افزونه از ادغام، مقدار `optm-css_exc` را با
`litespeed_conf_force` به‌صورت **رشته** بازنویسی می‌کرد. با نصب لایت‌اسپید روی
سایت لوکال و خواندن سورس واقعی‌اش (litespeed-cache 7.9.2) مشخص شد آن گزینه
**آرایه** است و خود لایت‌اسپید بلافاصله بعد از فیلتر به آن عضو اضافه می‌کند:

```
src/optimize.cls.php:1037  $excludes = apply_filters('litespeed_optimize_css_excludes', $this->conf(self::O_OPTM_CSS_EXC));
src/optimize.cls.php:1041  $excludes[] = 'litespeed-dummy.css';
src/data.cls.php:655       load_css_exc( array $list_in ): array
```

نتیجهٔ تست واقعی با Combine+Minify روشن:

| | ۱.۱۱.۱۸۴ (باگ) | ۱.۱۱.۱۸۵ |
|---|---|---|
| `debug.log` برای هر صفحه | **۶ بار** `PHP Warning: Array to string conversion` (۷۳۲ بایت به لاگ) | **۰** (هیچ تغییری در لاگ) |
| نوع مقدار `conf('optm-css_exc')` | رشتهٔ بازنویسی‌شده | آرایه، دست‌نخورده |
| نتیجهٔ فیلتر | مؤثر نبود | `['owner-line.css', 'hamishop-elementor-widgets/assets/css']` و `is_array=true` |

## رفع
`HSW_Optimizer` دیگر از `litespeed_conf_force` استفاده نمی‌کند و مستقیماً روی همان
دو فیلتری که لایت‌اسپید در نقطهٔ مصرف می‌زند نشسته است:

- `litespeed_optimize_css_excludes` (اولویت ۲۰، بعد از `Data::load_css_exc` که
  در اولویت ۱۰ فایل `css_excludes.txt` را ادغام می‌کند)
- `litespeed_ucss_exc`

ورودی/خروجی **آرایه** است؛ اگر مقدار رشته باشد (ساختار قدیمی یا فیلتر پلاگین
دیگر) به خط‌ها تقسیم می‌شود، خطوط صاحب سایت حفظ می‌شوند و هرگز تکراری اضافه
نمی‌شود. این فیلترها بدون قید ثبت می‌شوند: اگر لایت‌اسپید نصب نباشد هیچ‌وقت
فراخوانی نمی‌شوند.

همچنین تشخیص فعال‌بودن اصلاح شد: `LSCWP_CTRL` یک **کلید query در router** است
(`router.cls.php:21 const ACTION = 'LSCWP_CTRL'`) و ثابت نیست؛ تشخیص الان
`defined('LSCWP_V') || has_filter('litespeed_conf')` است (روی لوکال:
`LSCWP_V=7.9.2` و `defined('LSCWP_CTRL')=false` — یعنی تشخیص قبلی فقط با
نیمهٔ دوم کار می‌کرد).

بقیهٔ رفتار ۱.۱۱.۱۸۴ دست‌نخورده است: پاک‌سازی خودکار کش
(`litespeed_purge_all` + توابع WP Rocket / W3TC / SiteGround)، خاموش‌کردن
بهینه‌ساز در پیش‌نمایش ویرایشگر (`litespeed_disable_all`)، و اعلان قابل‌بستن در
صفحهٔ HamiShop.

## تست
`tests/qa/prelive-optimizer.php` حالا **۸** سنجه دارد (قرمز ۸/۸ وقتی کلاس وصل
نباشد، سبز ۸/۸ با آن): ثبت دو فیلتر، خروجی آرایه با حفظ خط صاحب سایت،
قابل‌عضوافزودن‌بودن خروجی (`$excludes[] =`)، بدون‌تکراری در اجرای دوباره،
تجزیهٔ ورودی رشته‌ای/CRLF، purge مشروط، خاموشی در preview، و تشخیص نادرست
نبودن `LSCWP_CTRL`.

سایر گیت‌ها: prelive ۳۰/۳۰ · prelive-email ۵/۵ · prelive-0a ۴/۴ · prelive-0b
۳/۳ · prelive-routing ۱۲/۱۲ · harness ALL CLEAN · واحد ۳۳/۰ · `php -l` صفر.

## تنظیمات سایت
برای این تست، Combine+Minify لایت‌اسپید روی سایت لوکال روشن و سپس **به حالت
اولیه برگردانده** شد (اسنپ‌شات از `litespeed.conf.*` گرفته و بازیابی شد) و کش
پاک شد.
