# یادداشت‌های انتشار نگارش 1.11.226 (Release Notes v1.11.226)

> **موضوع:** دکمهٔ «فیلتر پیشرفته» در موبایل کار نمی‌کرد — علت: LiteSpeed JS را Delay می‌کرد.

## ۱. نشانه

در `new.mrdastbaaf.com`، روی موبایل، زدن دکمهٔ «فیلتر پیشرفته» هیچ کاری نمی‌کرد: شیت پایین صفحه باز نمی‌شد.

## ۲. علت (با شاهد از HTML زنده)

اسکریپت افزونه در HTML صفحه این‌طور چاپ می‌شد:

```html
<script id="hsw-archive-js" type="litespeed/javascript"
        data-src=".../hsw-archive.min.js?ver=1.11.224">
```

`type="litespeed/javascript"` یعنی LiteSpeed قابلیت **Delay JS** را روی این فایل اعمال کرده است: فایل تا اولین تعامل کاربر دانلود **نمی‌شود**. اولین تپ روی دکمه، صرفِ دانلود اسکریپت می‌شود، نه باز کردن شیت. روی دسکتاپ ممکن است با حرکت ماوس از قبل لود شده باشد؛ روی گوشی دکمه «مرده» به نظر می‌رسد.

در همان صفحه ۸ اسکریپت از ۱۷ اسکریپت به همین شکل Delay شده بودند، از جمله jQuery و هر سه فایل HSW.

## ۳. چرا لیست Exclude کافی نبود

`HSW_Optimizer` فیلترهای `litespeed_optimize_js_excludes` و `litespeed_optm_js_defer_exc` را پر می‌کند — این‌ها روی **ادغام** و **defer** اثر دارند. تصمیم **Delay** روی خودِ تگ گرفته می‌شود، نه روی لیست.

## ۴. راه‌حل (سه لایه)

### الف) تگ اسکریپت‌های خودمان علامت‌گذاری می‌شود
`HSW_Assets::guard_script_tag()` روی `script_loader_tag` می‌نشیند و فقط برای هندل‌های `hsw-*` این‌ها را اضافه می‌کند:

```html
data-no-optimize="1" data-no-delay="1"
```

- `data-no-optimize` → LiteSpeed این عنصر را کامل رد می‌کند (ادغام، UCSS و Delay از یک شرط رد می‌شوند).
- `data-no-delay` → WP Rocket «Delay JavaScript execution» را رد می‌کند.
- **`data-no-defer` عمداً اضافه نشد**: اسکریپت‌ها باید defer بمانند و آن ویژگی باعث می‌شد LiteSpeed خودِ defer را بردارد.

### ب) هندلر inline در فوتر
یک اسکریپت ۶۲۰ بایتی (`print_sheet_boot`) در `wp_footer` با اولویت ۵ — یعنی **قبل** از باندل اصلی — چاپ می‌شود:

- همان چهار ویژگی opt-out را دارد، پس خودش هم Delay نمی‌شود؛
- فقط کلاس‌هایی را عوض می‌کند که شیت از قبل استفاده می‌کند (`is-open`، `aria-hidden`، `hsw-noscroll`)؛
- فقط وقتی چاپ می‌شود که باندل آرشیو در صفحه enqueue شده باشد.

اگر باندل بعداً لود شود، هندلر خودش هم اجرا می‌شود و نتیجه یکسان است (هر دو عملیات idempotent).

### ج) تنظیم سمت سایت (اختیاری، برای اطمینان بیشتر)
در LiteSpeed → Page Optimization → Tune → **JS Delayed** → URI Excludes این را اضافه کنید:

```
hamishop-elementor-widgets
```

## ۵. تأیید

با Chrome headless در عرض ۳۹۰px، صفحه‌ای که **باندل اصلی در آن لود نمی‌شود** (شبیه‌سازی دقیق حالت Delay):

```
before click:                        open=false aria=true  noscroll=false
after click (no bundle loaded):      open=true  aria=false noscroll=true
after close:                         open=false aria=true  noscroll=false
```

یعنی دکمه بدون وجود باندل هم شیت را باز و دکمهٔ بستن هم آن را می‌بندد. سینتکس JS با `node --check` تأیید شد.

## ۶. فایل‌های تغییر‌یافته

- `includes/class-hsw-assets.php` — `guard_script_tag()`، `print_sheet_boot()`
- `hamishop-elementor-widgets.php` (نسخه) · `readme.txt` · `CHANGELOG.md` · `languages/*.pot`

## ۷. اگر باز هم دکمه کار نکرد

1. در DevTools → Network، بعد از لود صفحه، آیا `hsw-archive.min.js` دانلود شده یا هنوز `type="litespeed/javascript"` است؟
2. در Console خطای JS هست؟
3. آیا تگ `hsw-sheet-boot` در انتهای صفحه هست؟

اگر هر سه مورد سالم بود، مشکل جای دیگری است و باید همان سه خروجی را ببینم.
