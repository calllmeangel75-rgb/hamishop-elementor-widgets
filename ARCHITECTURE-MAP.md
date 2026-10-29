# نقشهٔ فنی HamiShop Elementor Widgets

> تولید شده از خود کد (v1.11.160). هر عدد از فایل واقعی خوانده شده است.
> برای فهرست کامل ویجت‌ها و مکانیزم‌ها به `WIDGETS-REFERENCE.md` مراجعه کنید.

## ۱) لایه‌ها

```
hamishop-elementor-widgets.php
  └─ HSW_Plugin::init()
       ├─ HSW_Assets  (فقط wp_register_*، بدون enqueue سراسری)
       │    ├─ hsw-fonts   ← پایهٔ مشترک (فونت + متغیرهای پالت)
       │    └─ hsw-<family> (header/product/cart/checkout/complete/archive/blog/post/account/loop/footer)
       ├─ HSW_Widget_Manager  (فهرست ویجت‌ها + روشن/خاموش)
       ├─ HSW_Palette         (توکن‌ها → html:root)
       └─ widgets/*.php       (هر ویجت get_style_depends خودش را اعلام می‌کند)
```

**قاعدهٔ بارگذاری:** `HSW_Widget_Base` هیچ استایلی ندارد؛ هر «خانواده» یک کلاس پایه دارد که CSS خودش را اعلام می‌کند و ویجت‌های فرزند آن را به ارث می‌برند.

## ۲) ماتریس «خانواده → فایل CSS»

| خانواده | کلاس پایه | CSS | حجم (min) |
|---|---|---|---|
| hsw-header | HSW_Header_Base | assets/css/hsw-header.css | 31.8 KB |
| hsw-product | HSW_Product_Base | assets/css/hsw-product.css | 56.6 KB |
| hsw-cart | HSW_Cart_Base | assets/css/hsw-cart.css | 29.7 KB |
| hsw-checkout | HSW_Checkout_Base | assets/css/hsw-checkout.css | 64.1 KB |
| hsw-complete | HSW_Complete_Base | assets/css/hsw-complete.css | 10.2 KB |
| hsw-archive | HSW_Archive_Base | assets/css/hsw-archive.css | 40.3 KB |
| hsw-blog | HSW_Blog_Base | assets/css/hsw-blog.css | 12.3 KB |
| hsw-post | HSW_Post_Base | assets/css/hsw-post.css | 20.6 KB |
| hsw-account | HSW_Account | assets/css/hsw-account.css | 100.7 KB |
| hsw-loop | HSW_Widget_Base(override) | assets/css/hsw-loop.css | 19.4 KB |
| hsw-loop-box | ارثی: hsw-loop | assets/css/hsw-loop-box.css | 0.2 KB |
| hsw-loop-deal | ارثی: hsw-loop | assets/css/hsw-loop-deal.css | 1.1 KB |
| hsw-footer | HSW_Widget_Footer_Sticky | assets/css/hsw-footer.css | 1.4 KB |

## ۳) وضعیت استاندارد المنتور (Repeater + کنترل ریسپانسیو)

| ویجت | Repeater | سکشن | کنترل ریسپانسیو | CSS |
|---|---|---|---|---|
| `hsw-best-sellers` | — | 0 | 0 | hsw-fonts,hsw-loop-box |
| `hsw-popular` | ✔ | 2 | 0 | hsw-fonts,hsw-loop-box |
| `hsw-account` | — | 8 | 0 | hsw-fonts,hsw-account |
| `hsw-archive-breadcrumb` | — | 2 | 0 | ارثی: hsw-archive |
| `hsw-archive-filters` | ✔ | 2 | 0 | ارثی: hsw-archive |
| `hsw-archive-grid` | — | 2 | 1 | ارثی: hsw-archive |
| `hsw-archive-guide` | ✔ | 2 | 0 | ارثی: hsw-archive |
| `hsw-archive-header` | ✔ | 2 | 1 | ارثی: hsw-archive |
| `hsw-archive-page` | — | 2 | 2 | hsw-fonts,hsw-archive,hsw-composite |
| `hsw-archive-pagination` | — | 2 | 0 | ارثی: hsw-archive |
| `hsw-archive-toolbar` | — | 2 | 0 | ارثی: hsw-archive |
| `hsw-blog-featured` | — | 1 | 0 | ارثی: hsw-blog |
| `hsw-blog-grid` | — | 2 | 2 | ارثی: hsw-blog |
| `hsw-blog-hero` | — | 2 | 0 | ارثی: hsw-blog |
| `hsw-blog-newsletter` | — | 1 | 0 | ارثی: hsw-blog |
| `hsw-blog-page` | — | 2 | 1 | hsw-fonts,hsw-blog,hsw-composite |
| `hsw-blog-popular` | — | 1 | 0 | ارثی: hsw-blog |
| `hsw-blog-tabs` | — | 1 | 0 | ارثی: hsw-blog |
| `hsw-blog-tags` | — | 1 | 0 | ارثی: hsw-blog |
| `hsw-cart-banner` | — | 2 | 0 | ارثی: hsw-cart |
| `hsw-cart-coupon` | — | 2 | 1 | ارثی: hsw-cart |
| `hsw-cart-items` | — | 3 | 0 | ارثی: hsw-cart |
| `hsw-cart-page` | — | 1 | 0 | hsw-fonts,hsw-cart,hsw-composite |
| `hsw-cart-summary` | ✔ | 2 | 1 | ارثی: hsw-cart |
| `hsw-cart-top` | ✔ | 2 | 0 | ارثی: hsw-cart |
| `hsw-checkout-address` | — | 2 | 0 | ارثی: hsw-checkout |
| `hsw-checkout-items` | — | 2 | 0 | ارثی: hsw-checkout |
| `hsw-checkout-note` | — | 2 | 0 | ارثی: hsw-checkout |
| `hsw-checkout-page` | — | 2 | 1 | hsw-fonts,hsw-checkout,hsw-composite |
| `hsw-checkout-schedule` | ✔ | 2 | 0 | ارثی: hsw-checkout |
| `hsw-checkout-shipping` | ✔ | 2 | 0 | ارثی: hsw-checkout |
| `hsw-checkout-summary` | ✔ | 2 | 0 | ارثی: hsw-checkout |
| `hsw-checkout-top` | ✔ | 2 | 0 | ارثی: hsw-checkout |
| `hsw-complete-actions` | — | 2 | 0 | ارثی: hsw-checkout + hsw-complete |
| `hsw-complete-delivery` | — | 2 | 0 | ارثی: hsw-checkout + hsw-complete |
| `hsw-complete-hero` | — | 2 | 0 | ارثی: hsw-checkout + hsw-complete |
| `hsw-complete-order` | — | 2 | 0 | ارثی: hsw-checkout + hsw-complete |
| `hsw-complete-page` | — | 1 | 0 | hsw-fonts,hsw-checkout,hsw-complete,hsw-composite |
| `hsw-complete-steps` | ✔ | 2 | 0 | ارثی: hsw-checkout + hsw-complete |
| `hsw-footer-sticky` | ✔ | 4 | 7 | hsw-fonts,hsw-footer |
| `hsw-header-minimal` | — | 2 | 0 | ارثی: hsw-header |
| `hsw-header-store` | — | 3 | 0 | ارثی: hsw-header |
| `hsw-pay-coupon` | — | 2 | 0 | ارثی: hsw-checkout |
| `hsw-pay-methods` | ✔ | 2 | 0 | ارثی: hsw-checkout |
| `hsw-pay-page` | — | 1 | 0 | hsw-fonts,hsw-checkout,hsw-composite |
| `hsw-pay-recall` | — | 2 | 0 | ارثی: hsw-checkout |
| `hsw-pay-summary` | ✔ | 2 | 0 | ارثی: hsw-checkout |
| `hsw-pay-top` | ✔ | 2 | 0 | ارثی: hsw-checkout |
| `hsw-post-page` | — | 3 | 2 | hsw-fonts,hsw-post |
| `hsw-product-accordions` | ✔ | 2 | 0 | ارثی: hsw-product |
| `hsw-product-breadcrumb` | — | 2 | 1 | ارثی: hsw-product |
| `hsw-product-features` | ✔ | 2 | 0 | ارثی: hsw-product |
| `hsw-product-gallery` | — | 2 | 1 | ارثی: hsw-product |
| `hsw-product-page` | — | 2 | 2 | hsw-fonts,hsw-product,hsw-composite |
| `hsw-product-purchase` | ✔ | 2 | 1 | ارثی: hsw-product |
| `hsw-product-related` | — | 1 | 0 | ارثی: hsw-product |
| `hsw-product-sticky` | — | 2 | 0 | ارثی: hsw-product |
| `hsw-product-tabs` | — | 2 | 1 | ارثی: hsw-product |
| `hsw-product-title` | — | 2 | 2 | ارثی: hsw-product |
| `hsw-product-trust` | ✔ | 2 | 0 | ارثی: hsw-product |
| `hsw-product-variants` | ✔ | 2 | 0 | ارثی: hsw-product |
| `hsw-wonder-deals` | — | 0 | 0 | hsw-fonts,hsw-loop-deal |

**جمع:** 62 ویجت · Repeater دارد: **20** · حداقل ۲ کنترل ریسپانسیو: **9**

## ۴) نقاط تداخل شناخته‌شده

| # | الگو | ریسک | نمونهٔ واقعی |
|---|---|---|---|
| ۱ | `var(--x)` بدون fallback | رنگ/فونت ارثی از قالب یا المنتور | دکمهٔ قرمز (Secondary المنتور) → ۱.۱۱.۱۰۳ |
| ۲ | `font-family: var(--hsw-font, inherit)` | تایپوگرافی سراسری المنتور برنده می‌شود | ۱.۱۱.۱۰۵ |
| ۳ | `:not(.a)` روی خانوادهٔ دکمه | واریانت‌های دیگر را می‌بلعد (specificity +1) | `.hsw-ac__btn:not(--primary)` → ۱.۱۱.۱۰۸ |
| ۴ | `!important` روی ویژگی‌هایی که کنترل المنتور مالک آن است | کنترل ویجت مرده می‌شود | `font-size !important` روی سه آیکون محصول → ۱.۱۱.۹۰ |
| ۵ | `z-index` بالا در سطح ریشه | منو/شیت زیرش می‌رود یا رویش می‌افتد | فوتر ۸۰۰۰ → ۹۰ در ۱.۱۱.۱۰۱ |
| ۶ | `display:none` برای مخفی‌سازی وابسته به کلاس JS | کلاس کهنه می‌ماند | bin در تعداد ۶ → ۱.۱۱.۹۵ |

## ۵) اولویت رفع باگ (پیشنهاد)

1. **ریسپانسیو استایل**: فقط ۲ ویجت از ۶۲ حداقل دو کنترل ریسپانسیو دارند؛ بقیه فاصله/تراز ثابت دارند.
2. **بازبینی `!important`**: با grep قابل شمارش است؛ هر مورد باید یا کنترل داشته باشد یا حذف شود.
3. **یکسان‌سازی fallback متغیرها**: شمارش دقیق (بدون فایل‌های min):
   - کل ارجاع‌های `var(--hsw…)` بدون fallback: **۲۳۴۶** — ولی بیشترشان متغیرهای
     کامپوننتی (`--hswc-`, `--hswk-`, `--hswa-`) هستند که در همان فایل روی ریشهٔ
     کامپوننت تعریف می‌شوند و امن‌اند.
   - ارجاع به توکن‌های **سراسری** (`--hsw-…`) بدون fallback: **۱۰۷۱** — این‌ها
     همان دسته‌ای هستند که باگ رنگ/فونت را ساختند و باید یکی‌یکی fallback بگیرند.
4. **آزمون دود اتوماتیک**: اسکریپت Playwright که برای هر ویجت، ۳ کنترل کلیدی را ست و computed style را مقایسه کند.
