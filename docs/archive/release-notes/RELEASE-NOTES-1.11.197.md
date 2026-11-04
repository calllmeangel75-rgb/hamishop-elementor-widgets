# HamiShop 1.11.197 — چرا صفحهٔ محصول NO_LCP می‌داد

صفحه سالم رندر می‌شد، ولی Lighthouse می‌گفت `NO_LCP`. علتش یک خط CSS بود.

## علت: `scroll-behavior: smooth` روی ترک‌های scroll-snap

نوار تب‌های محصول، کاروسل محصولات مرتبط، بندانگشت‌های گالری و ریل کاروسل‌ها
همه این دو خط را با هم داشتند:

```css
scroll-behavior: smooth;
scroll-snap-type: x mandatory;
```

مرورگر هنگام چیدمان، موقعیت اسنپ را **خودش تنظیم می‌کند**. با `smooth`، آن
تنظیم تبدیل به یک **اسکرول انیمیشنی در حین بارگذاری** می‌شود — و Chrome از آن
لحظه دیگر هیچ کاندید LCP ثبت نمی‌کند.

شاهدش، ردیابی خودِ Chrome است:

| صفحه | رویدادهای Candidate | نتیجه |
|---|---|---|
| آرشیو | `largestContentfulPaint::Candidate` ×۲ | LCP دارد |
| محصول | **هیچ** — فقط Invalidate | NO_LCP |

و با حذف فقط همان یک خط:

```
.hsw-ptabs__nav,.hsw-prel__track{scroll-snap-type:none!important}
  → [{"tag":"IMG","cls":"hsw-pgal__img skip-savvy"}]   ← LCP برگشت
```

## رفع

`scroll-behavior: smooth` از هر چهار ترک حذف شد؛ `scroll-snap-type` سر جایش
ماند (چون رفتار دلخواه کاربر است). جاوااسکریپت خودش همه‌جا صریح می‌گوید
`behavior: 'smooth'`:

- `hsw-loop.js` → `scrollTo({ behavior: motion })`
- `hsw-product.js` → `scrollBy({ behavior: 'smooth' })` و
  `scrollIntoView({ behavior: 'smooth' })`

پس فقط اسنپِ هنگام بارگذاری تغییر کرد — که دقیقاً همان چیزی است که می‌خواستیم.

نتیجهٔ اندازه‌گیری محلی روی صفحهٔ محصول: LCP از **خالی** به
`IMG.hsw-pgal__img` (۷۱۶۱۰ پیکسل) رسید.

## ضمناً: بندانگشت‌های گالری

بندانگشت‌ها با تگ خام `<img src>` چاپ می‌شدند — بدون `srcset` و `sizes` — و
فایل ۶۰۰×۶۰۰ (`woocommerce_single`) را برای یک جعبهٔ ۶۴ پیکسلی دانلود می‌کردند:
**۲۹٫۴KB** طبق Lighthouse. حالا مثل بقیهٔ تصاویر افزونه از
`wp_get_attachment_image()` با `sizes` رد می‌شوند، پس مرورگر کاندید ۳۰۰w را
انتخاب می‌کند.

نکته: `data-hsw-src` (که با کلیک، تصویر اصلی را عوض می‌کند) دست‌نخورده مانده و
همان نسخهٔ بزرگ را می‌گیرد — فقط تصویر کوچکِ نمایشی عوض شده.

## تست

دو سنجهٔ جدید:

- `P15` — هیچ قاعده‌ای که `scroll-snap-type` دارد نباید `scroll-behavior: smooth`
  داشته باشد (اگر کسی دوباره اضافه کند، قرمز می‌شود).
- `P16` — بندانگشت‌های گالری از `wp_get_attachment_image` با `sizes` استفاده کنند.

هر دو روی نسخهٔ ۱.۱۱.۱۹۶ قرمز بودند.

## گیت

lint تمیز · ci-checks ۱۶/۱۶ · prelive ۳۴/۳۴ · email ۵/۵ · 0a ۴/۴ · 0b ۳/۳ ·
routing ۱۹/۱۹ · optimizer ۹/۹ · p1 ۱۲/۱۲ · brand ۱۴/۱۴ · wallet ۵/۵ ·
**perf ۱۶/۱۶** · unit ۳۳/۰ · harness clean.

اسکرین‌شات صفحهٔ محصول بعد از تغییر: گالری، بندانگشت‌ها با رینگ فعال، فلش‌ها و
نوار خرید چسبان همه سالم.
