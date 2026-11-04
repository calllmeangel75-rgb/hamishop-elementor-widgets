# یادداشت‌های انتشار نگارش 1.11.241 (Release Notes v1.11.241)

> **موضوع:** سه پنل که فونت خودشان را روی وزیرمتن قفل کرده بودند، حالا فونت پالت را می‌خوانند. با پالت پیش‌فرض، خروجی هیچ تغییری ندارد.

## ایراد

فونت پالت (`--hsw-font`) از تنظیمات «پالت رنگی» می‌آید و همهٔ پنل‌ها آن را می‌خوانند:

```css
font-family: var(--hsw-font, 'Vazirmatn HSW'), Tahoma, sans-serif;
```

سه جا این کار را نمی‌کردند و فونت را دستی روی وزیرمتن قفل کرده بودند:

| فایل | انتخابگر | اثر |
|---|---|---|
| `assets/css/hsw-account.css:34` | `.hsw-ac` | پنل حساب کاربری |
| `assets/css/hsw-post.css:26` | `.hsw-pp` | صفحهٔ نوشتهٔ بلاگ |
| `assets/css/hsw-product.css:2220` | `.hsw-sheet, .hsw-sheet *` | شیت محصول (با `!important`) |

اندازه‌گیری روی دو سایت زنده نشان داد این قفل واقعاً اثر دارد:

```
new.mrdastbaaf.com  →  --hsw-font: "iryekan"
gilaclick.ir        →  --hsw-font: "peyda"
```

یعنی روی هر دو سایت، پنل حساب و صفحهٔ نوشته با فونتی متفاوت از بقیهٔ سایت رندر می‌شدند.

## رفع

هر سه به همان الگوی بقیهٔ فایل‌ها برگشتند:

```css
font-family: var(--hsw-font, 'Vazirmatn HSW'), Tahoma, sans-serif;
```

`!important` در `hsw-product.css` دست‌نخورده ماند؛ فقط خانوادهٔ فونت از پالت خوانده می‌شود.

## اثبات — با Chrome روی لوکال، فونت محاسبه‌شده نه متن CSS

**پالت پیش‌فرض (وزیرمتن) — هیچ تغییری نکرد:**

```
before:  .hsw-ac -> "Vazirmatn HSW", Tahoma, sans-serif   [--hsw-font: "Vazirmatn HSW"]
after:   .hsw-ac -> "Vazirmatn HSW", Tahoma, sans-serif   [--hsw-font: "Vazirmatn HSW"]
```

**پالت روی `Tahoma` — هر سه پنل تبعیت کردند:**

```
.hsw-ac                -> Tahoma, Tahoma, sans-serif   [--hsw-font: "Tahoma"]
.hsw-ac-auth__panel    -> Tahoma, Tahoma, sans-serif   [--hsw-font: "Tahoma"]
.hsw-pp                -> Tahoma, Tahoma, sans-serif   [--hsw-font: "Tahoma"]
```

پالت لوکال بعد از تست به مقدار قبلی برگشت (`Vazirmatn HSW`، ۶۲۲ بایت، همان اندازهٔ بکاپ).

## چه کسی چه چیزی می‌بیند

| سایت | اثر |
|---|---|
| پالت = وزیرمتن (پیش‌فرض) | هیچ تغییری. صفر |
| پالت = فونت دیگر | پنل حساب، صفحهٔ نوشته و شیت محصول با همان فونت بقیهٔ سایت رندر می‌شوند |

## نصب

فایل `hamishop-elementor-widgets-1.11.241.zip` را از بخش Assets بردارید و از پیشخوان ← افزونه‌ها ← افزودن ← بارگذاری افزونه نصب کنید. بعد LiteSpeed → Purge All و `Ctrl+F5` (CSS تغییر کرده).
