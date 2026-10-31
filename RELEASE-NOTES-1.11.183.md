# HamiShop 1.11.183 — فونت وزیرمتن، رنگ صورتی المنتور و شاین لوگو

سه مشکل استایل که روی سایت لایو گزارش شد، هر سه با شاهد از خود سایت
(`hamishop.gilaclick.ir`) پیدا و رفع شدند.

## ۱) «گاهی فونت بهم می‌خوره، با تغییر ریسپانسیو درست می‌شه»
در CSS سروشدهٔ لایو خوانده شد:

```
@font-face{font-family:'Vazirmatn HSW';
  src:url(/Modoul/.../vazirmatn.woff2) format('woff2-variations');
  font-display:optional; …}
```
و فایل فونت هم سالم است (`status=200 type=font/woff2 size=65980`) — پس مشکل از
خود فایل نیست. دو علت در خود `@font-face` بود:

- **`font-display: optional`** عامل اصلی است. در این حالت مرورگر فقط یک پنجرهٔ
  ~۱۰۰ میلی‌ثانیه‌ای به فونت می‌دهد؛ اگر برسد، فونت کاربری می‌شود و اگر نرسد،
  **برای کل همان صفحه‌نمایش روی Tahoma می‌ماند و هیچ‌وقت swap نمی‌کند**.
  تغییر اندازهٔ پنجره / حالت ریسپانسیو یک re-layout اجباری می‌کند و همان لحظه
  فونت کش‌شده اعمال می‌شود — دقیقاً همان «خودش درست می‌شه» که گزارش شد.
- **`format('woff2-variations')`** مقدار آزمایشی و منسوخ است؛ مقدار فرمتِ
  ناشناخته باعث می‌شود موتور `src` را رد کند. به `format('woff2')` تبدیل شد
  (محور متغیر از قبل با `font-weight: 100 900` اعلام شده).

رفع: `font-display: swap` + **preload** فایل فونت.

- `HSW_Assets::preload_font()` لینک
  `<link rel="preload" as="font" type="font/woff2" crossorigin>` را در `wp_head`
  (اولویت ۴، قبل از استایل‌ها) چاپ می‌کند — و فقط وقتی که `hsw-fonts` واقعاً
  enqueue شده باشد، پس صفحات بی‌ویجت HamiShop چیزی اضافه دانلود نمی‌کنند.
  تأیید روی لوکال: لینک در head ظاهر شد و CSS سروشده حالا
  `format('woff2'); … font-display:swap` است.
- با `hsw_preload_font` (false) و برگرداندن مقدار `optional` در CSS می‌توان به
  رفتار قبلی برگشت.

**نکته:** دلیل `optional` بودن، CLS اندازه‌گرفته‌شدهٔ ۰٫۱۸ در ۳۹۰px بود. با
preload معمولاً فونت قبل از اولین paint می‌رسد، ولی عدد CLS را باید دوباره در
Lighthouse روی لایو اندازه گرفت (اینجا تست نشده).

## ۲) صورتی معروف المنتور (rgb(204,51,102)) روی دکمهٔ بستن سبد و تب‌های حساب
دنبال `#cc3366` در CSS افزونه گشتیم: **صفر** مورد. در CSS ترکیب‌شدهٔ لایو هم
صفر، و رنگ Primary سراسری المنتورِ آن سایت `#134686` (آبی) است. علت واقعی:

```
[type=submit]:focus,[type=submit]:hover,button:focus,button:hover{background-color:#c36;color:#fff}
```

`#c36` همان `rgb(204,51,102)` است و این قاعدهٔ **تم Hello Elementor** روی
`button` خام (بدون کلاس) نوشته شده — پس هر `<button>` ما را می‌گیرد:

- دکمهٔ `hsw-hd__cartclose` وقتی درِ سبد باز می‌شود: اسکریپت روی آن `focus()`
  می‌کند؛ فوکوسِ برنامه‌نویسی‌شده `:focus-visible` را فعال **نمی‌کند**، و قاعدهٔ
  ما فقط `:focus-visible` داشت → صورتی.
- `hsw-ac__member-tabs`: برای این دکمه‌ها هیچ قاعدهٔ hover/focus نداشتیم.

رفع: افزودن `:focus` کنار `:focus-visible` برای `hsw-hd__cartclose`،
`hsw-hd__drawerclose` و `hsw-hd__iconbtn`، و قاعدهٔ hover/focus برای
`.hsw-ac__member-tabs button` (با حفظ پس‌زمینهٔ سفید تب فعال).

اگر جای دیگری هم همین صورتی را دیدی (دکمه‌ای که مال افزونهٔ ما نیست)، این را در
«CSS اضافی» قالب بگذار تا قاعدهٔ خام تم خنثی شود:

```css
button:focus:not(.hsw-ic),button:hover:not(.hsw-ic){background-color:inherit;color:inherit}
```

## ۳) شاین لوگو قرمز بود
```
color-mix(in srgb, var(--hsw-accent, #fc593a) 42%, #fff) 54%
```
`--hsw-accent` در محدودهٔ هدر تعریف **نشد** و به مقدار `#fc593a` (نارنجی‌قرمز)
برمی‌گشت — پس انتهای هر سوییپ یک نوار قرمز بود. حالا سوییپ فقط سفیدِ شفاف است
(۳۰٪ → ۴۸٪ → ۶۶٪) و درخشش با `blur(2px)` + `drop-shadow(0 0 10px …)` ساخته
می‌شود؛ رنگ قابل تنظیم (`رنگ درخشش`) مثل قبل کار می‌کند.

## تست و گیت
`php -l` صفر خطا · `prelive` ۳۰/۳۰ · `prelive-email` ۵/۵ · `prelive-0a` ۴/۴ ·
`prelive-0b` ۳/۳ · `prelive-routing` ۱۲/۱۲ · `harness` ALL CLEAN · واحد ۳۳/۰ ·
`ci-checks` ۱۶/۱۶ · `build-assets` و `build-pot` بازتولید شدند.

## برای سایت لایو
بعد از آپدیت، **کش LiteSpeed را خالی کن** (CSS ترکیب‌شدهٔ
`/Public/litespeed/css/…` وگرنه همان فایل قدیمی را می‌رساند).
