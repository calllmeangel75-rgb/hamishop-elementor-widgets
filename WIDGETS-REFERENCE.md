# مرجع فنی ویجت‌ها — HamiShop Elementor Widgets

> تولید‌شده از خود کد · **نسخهٔ 1.11.149** · 62 ویجت · 20 ویجت با Repeater · 9 ویجت با ≥۲ کنترل ریسپانسیو

## ۰) نمای کلی

```
Elementor page
 ├─ ویجت «صفحهٔ کامل …»  (hsw-*-page)  ← اسکلت + گرید + چیدمان
 └─ ویجت‌های تک‌کاره          (hsw-cart-items، hsw-product-purchase …)
        │
        │  get_style_depends() / get_script_depends()
        ▼
HSW_Assets — فقط register، بدون enqueue سراسری
 ├─ hsw-fonts   پایهٔ مشترک: وزیرمتن + متغیرهای پالت
 └─ hsw-<family> header · product · cart · checkout · complete ·
                  archive · blog · post · account · loop · footer
        ▼
HSW_Palette → html:root { --hsw-primary … --hsw-cta* --hsw-pal-* --hsw-font }
        ▼
پیش‌فرض کامپوننت  →  .hsw-c { --hswc-card: var(--hsw-pal-surface, #fff) }
        ▼
کنترل ویجت         →  {{WRAPPER}} .hsw-c { --hswc-card: <مقدار دستی> }  ← برنده
```

### قاعده‌های ثابت (به‌روزشده)

| # | قاعده | چرا |
|---|---|---|
| ۱ | `HSW_Widget_Base` استایل ندارد؛ هر خانواده کلاس پایه دارد | لود نشدن لوپ/محصول در همهٔ صفحات |
| ۲ | هر ویجت فقط دارایی خودش را اعلام می‌کند | ۳–۵ فایل CSS در هر صفحه |
| ۳ | **پیش‌فرض رنگ‌ها از پالت پیشخوان**؛ کنترل ویجت روی wrapper می‌نویسد و همیشه برنده است | تغییر پالت سراسری + امکان override دستی |
| ۴ | هیچ `var(--x)` بدون fallback | اگر پالت چاپ نشود، رنگ درست بماند |
| ۵ | `!important` فقط برای غلبه بر قالب، نه روی ویژگی‌های کنترل‌شده | کنترل ویجت زنده بماند |
| ۶ | در ویجت‌های «صفحهٔ کامل»، سکشن‌های استایل فرزندان با `style_section_mirror()` کپی می‌شوند | فرزندان در المنتور ثبت نمی‌شوند |
| ۷ | کنترل‌های چیدمان در **تب محتوا**، کنترل‌های ظاهر در **تب استایل** | پنل قابل‌فهم بماند |

## ۱) پالت: پیش‌فرض از پیشخوان، اولویت با ویجت

```
پالت پیشخوان  →  --hsw-pal-surface / -border / -muted / -danger / -warning …
      ↓ fallback در CSS
--hswc-card / --hswk-card / --hswb-card / --hsw-ac-card  (پیش‌فرض هر خانواده)
      ↓ override روی wrapper
کنترل رنگ ویجت  (specificity بالاتر → برنده)
```
سکشن «پالت رنگ ویجت» از **همهٔ** خانواده‌ها حذف شد (سبد، تسویه، پرداخت، حساب، مجله) چون تکرار پالت پیشخوان بود؛ کنترل‌های عنصربه‌عنصر باقی مانده‌اند.

## ۲) آیکون، فونت، تقویم

- **آیکون:** اسپرایت SVG درون‌صفحه‌ای، فقط آیکون‌های همان درخواست؛ صفر درخواست شبکه. واریانت توپُر با کلید `name.fill`.
- **فونت:** وزیرمتن بسته‌بندی‌شده؛ `unicode-range` شامل **لاتین کامل (U+0000-00FF)** + بلوک‌های عربی — پیش‌تر `A-Z/a-z` نداشت و متن لاتین به Tahoma می‌افتاد.
- **تقویم شمسی:** `HSW_Jalali` بدون کتابخانه؛ فیلترهای `date_i18n`، `get_the_date`، `get_the_modified_date`، `get_the_time` فقط با کلید `hsw_jalali_on`.

## ۳) ویجت‌ها به تفکیک خانواده

### هدر — `hsw-header`

| ویجت | عنوان | فایل | Repeater | سکشن | ریسپانسیو | کنترل | CSS |
|---|---|---|:--:|:--:|:--:|:--:|---|
| `hsw-header-minimal` | هدر مینیمال شیشه‌ای (HamiShop) | class-hsw-widget-header-minimal.php | — | 2 | 0 | 4 | ارثی |
| `hsw-header-store` | هدر فروشگاهی (HamiShop) | class-hsw-widget-header-store.php | — | 3 | 0 | 15 | ارثی |

### لوپ محصول — `hsw-loop`

| ویجت | عنوان | فایل | Repeater | سکشن | ریسپانسیو | کنترل | CSS |
|---|---|---|:--:|:--:|:--:|:--:|---|
| `hsw-best-sellers` | پرفروش‌ترین‌ها (HamiShop) | class-hsw-best-sellers.php | — | 0 | 0 | 0 | hsw-fonts, hsw-loop |
| `hsw-popular` | محبوب‌ترین‌ها / منتخب (HamiShop) | class-hsw-popular.php | ✔ | 2 | 0 | 9 | hsw-fonts, hsw-loop |
| `hsw-wonder-deals` | پیشنهاد شگفت‌انگیز (HamiShop) | class-hsw-wonder-deals.php | — | 0 | 0 | 0 | hsw-fonts, hsw-loop |

### آرشیو فروشگاه — `hsw-archive`

| ویجت | عنوان | فایل | Repeater | سکشن | ریسپانسیو | کنترل | CSS |
|---|---|---|:--:|:--:|:--:|:--:|---|
| `hsw-archive-breadcrumb` | بردکرامب آرشیو (HamiShop) | class-hsw-widget-archive-breadcrumb.php | — | 2 | 0 | 7 | ارثی |
| `hsw-archive-filters` | فیلترهای پیشرفته آرشیو (HamiShop) | class-hsw-widget-archive-filters.php | ✔ | 2 | 0 | 18 | ارثی |
| `hsw-archive-grid` | گرید محصولات آرشیو (HamiShop) | class-hsw-widget-archive-grid.php | — | 2 | 1 | 20 | ارثی |
| `hsw-archive-guide` | راهنما و سوالات متداول آرشیو (HamiShop) | class-hsw-widget-archive-guide.php | ✔ | 2 | 0 | 12 | ارثی |
| `hsw-archive-header` | هدر و توضیحات آرشیو (HamiShop) | class-hsw-widget-archive-header.php | ✔ | 2 | 1 | 49 | ارثی |
| `hsw-archive-page` | صفحه کامل آرشیو — حامی (ویجت جامع) | class-hsw-widget-archive-page.php | — | 2 | 2 | 13 | hsw-fonts, hsw-archive, hsw-composite |
| `hsw-archive-pagination` | صفحه‌بندی آرشیو (HamiShop) | class-hsw-widget-archive-pagination.php | — | 2 | 0 | 5 | ارثی |
| `hsw-archive-toolbar` | نوار مرتب‌سازی و ابزار آرشیو (HamiShop) | class-hsw-widget-archive-toolbar.php | — | 2 | 0 | 11 | ارثی |

### محصول — `hsw-product`

| ویجت | عنوان | فایل | Repeater | سکشن | ریسپانسیو | کنترل | CSS |
|---|---|---|:--:|:--:|:--:|:--:|---|
| `hsw-product-accordions` | آکاردئون‌های محصول (HamiShop) | class-hsw-widget-product-accordions.php | ✔ | 2 | 0 | 13 | ارثی |
| `hsw-product-breadcrumb` | همی‌راه محصول (HamiShop) | class-hsw-widget-product-breadcrumb.php | — | 2 | 1 | 6 | ارثی |
| `hsw-product-features` | ویژگی‌های شاخص محصول (HamiShop) | class-hsw-widget-product-features.php | ✔ | 2 | 0 | 9 | ارثی |
| `hsw-product-gallery` | گالری محصول (HamiShop) | class-hsw-widget-product-gallery.php | — | 2 | 1 | 18 | ارثی |
| `hsw-product-page` | صفحه کامل محصول — حامی (ویجت جامع) | class-hsw-widget-product-page.php | — | 2 | 2 | 27 | hsw-fonts, hsw-product, hsw-composite |
| `hsw-product-purchase` | جعبه خرید و قیمت (HamiShop) | class-hsw-widget-product-purchase.php | ✔ | 2 | 1 | 14 | ارثی |
| `hsw-product-related` | محصولات مرتبط (HamiShop) | class-hsw-widget-product-related.php | — | 1 | 0 | 6 | ارثی |
| `hsw-product-sticky` | نوار خرید چسبان (HamiShop) | class-hsw-widget-product-sticky.php | — | 2 | 0 | 10 | ارثی |
| `hsw-product-tabs` | تب‌های محصول (HamiShop) | class-hsw-widget-product-tabs.php | — | 2 | 1 | 21 | ارثی |
| `hsw-product-title` | عنوان، امتیاز و متای محصول (HamiShop) | class-hsw-widget-product-title.php | — | 2 | 2 | 15 | ارثی |
| `hsw-product-trust` | نوار اعتماد و ضمانت‌ها (HamiShop) | class-hsw-widget-product-trust.php | ✔ | 2 | 0 | 2 | ارثی |
| `hsw-product-variants` | انتخاب رنگ و حافظه (HamiShop) | class-hsw-widget-product-variants.php | ✔ | 2 | 0 | 14 | ارثی |

### سبد خرید — `hsw-cart`

| ویجت | عنوان | فایل | Repeater | سکشن | ریسپانسیو | کنترل | CSS |
|---|---|---|:--:|:--:|:--:|:--:|---|
| `hsw-cart-banner` | بنر ارسال رایگان سبد (HamiShop) | class-hsw-widget-cart-banner.php | — | 2 | 0 | 15 | ارثی |
| `hsw-cart-coupon` | کد تخفیف سبد خرید (HamiShop) | class-hsw-widget-cart-coupon.php | — | 2 | 1 | 23 | ارثی |
| `hsw-cart-items` | اقلام سبد خرید (HamiShop) | class-hsw-widget-cart-items.php | — | 4 | 6 | 53 | ارثی |
| `hsw-cart-page` | صفحه کامل سبد خرید — حامی (ویجت جامع) | class-hsw-widget-cart-page.php | — | 1 | 0 | 12 | hsw-fonts, hsw-cart, hsw-composite |
| `hsw-cart-summary` | خلاصه فاکتور سبد خرید (HamiShop) | class-hsw-widget-cart-summary.php | ✔ | 2 | 1 | 47 | ارثی |
| `hsw-cart-top` | هدر و مراحل سبد خرید (HamiShop) | class-hsw-widget-cart-top.php | ✔ | 2 | 0 | 30 | ارثی |

### تسویه و پرداخت — `hsw-checkout`

| ویجت | عنوان | فایل | Repeater | سکشن | ریسپانسیو | کنترل | CSS |
|---|---|---|:--:|:--:|:--:|:--:|---|
| `hsw-checkout-address` | آدرس تحویل سفارش (HamiShop) | class-hsw-widget-checkout-address.php | — | 2 | 0 | 25 | ارثی |
| `hsw-checkout-items` | اقلام مرسوله (HamiShop) | class-hsw-widget-checkout-items.php | — | 2 | 0 | 7 | ارثی |
| `hsw-checkout-note` | یادداشت سفارش و فاکتور (HamiShop) | class-hsw-widget-checkout-note.php | — | 2 | 0 | 12 | ارثی |
| `hsw-checkout-page` | صفحه کامل تسویه حساب — حامی (ویجت جامع) | class-hsw-widget-checkout-page.php | — | 2 | 1 | 13 | hsw-fonts, hsw-checkout, hsw-composite |
| `hsw-checkout-schedule` | زمان و روز تحویل (HamiShop) | class-hsw-widget-checkout-schedule.php | ✔ | 2 | 0 | 19 | ارثی |
| `hsw-checkout-shipping` | انتخاب روش ارسال (HamiShop) | class-hsw-widget-checkout-shipping.php | ✔ | 2 | 0 | 16 | ارثی |
| `hsw-checkout-summary` | خلاصه پرداخت اطلاعات ارسال (HamiShop) | class-hsw-widget-checkout-summary.php | ✔ | 2 | 0 | 36 | ارثی |
| `hsw-checkout-top` | هدر و مراحل اطلاعات ارسال (HamiShop) | class-hsw-widget-checkout-top.php | ✔ | 2 | 0 | 23 | ارثی |
| `hsw-pay-coupon` | کد تخفیف و کارت هدیه پرداخت (HamiShop) | class-hsw-widget-pay-coupon.php | — | 2 | 0 | 14 | ارثی |
| `hsw-pay-methods` | روش‌های پرداخت (HamiShop) | class-hsw-widget-pay-methods.php | ✔ | 2 | 0 | 25 | ارثی |
| `hsw-pay-page` | صفحه کامل پرداخت — حامی (ویجت جامع) | class-hsw-widget-pay-page.php | — | 1 | 0 | 7 | hsw-fonts, hsw-checkout, hsw-composite |
| `hsw-pay-recall` | مشخصات تحویل سفارش (HamiShop) | class-hsw-widget-pay-recall.php | — | 2 | 0 | 20 | ارثی |
| `hsw-pay-summary` | خلاصه فاکتور و پرداخت نهایی (HamiShop) | class-hsw-widget-pay-summary.php | ✔ | 2 | 0 | 40 | ارثی |
| `hsw-pay-top` | هدر و مراحل پرداخت (HamiShop) | class-hsw-widget-pay-top.php | ✔ | 2 | 0 | 35 | ارثی |

### تکمیل خرید — `hsw-complete`

| ویجت | عنوان | فایل | Repeater | سکشن | ریسپانسیو | کنترل | CSS |
|---|---|---|:--:|:--:|:--:|:--:|---|
| `hsw-complete-actions` | دکمه‌های تکمیل خرید (HamiShop) | class-hsw-widget-complete-actions.php | — | 2 | 0 | 22 | ارثی |
| `hsw-complete-delivery` | اطلاعات تحویل تکمیل خرید (HamiShop) | class-hsw-widget-complete-delivery.php | — | 2 | 0 | 17 | ارثی |
| `hsw-complete-hero` | سربرگ تکمیل خرید (HamiShop) | class-hsw-widget-complete-hero.php | — | 2 | 0 | 31 | ارثی |
| `hsw-complete-order` | جزئیات سفارش تکمیل خرید (HamiShop) | class-hsw-widget-complete-order.php | — | 2 | 0 | 29 | ارثی |
| `hsw-complete-page` | صفحه کامل تکمیل خرید — حامی (ویجت جامع) | class-hsw-widget-complete-page.php | — | 1 | 0 | 7 | hsw-fonts, hsw-checkout, hsw-complete, hsw-composite |
| `hsw-complete-steps` | مراحل بعدی تکمیل خرید (HamiShop) | class-hsw-widget-complete-steps.php | ✔ | 2 | 0 | 8 | ارثی |

### حساب کاربری — `hsw-account`

| ویجت | عنوان | فایل | Repeater | سکشن | ریسپانسیو | کنترل | CSS |
|---|---|---|:--:|:--:|:--:|:--:|---|
| `hsw-account` | پنل حساب کاربری HamiShop | class-hsw-widget-account.php | — | 15 | 20 | 58 | hsw-fonts, hsw-account |

### مجله — `hsw-blog`

| ویجت | عنوان | فایل | Repeater | سکشن | ریسپانسیو | کنترل | CSS |
|---|---|---|:--:|:--:|:--:|:--:|---|
| `hsw-blog-featured` | وبلاگ: مقاله ویژه (HamiShop) | class-hsw-widget-blog-featured.php | — | 1 | 0 | 6 | ارثی |
| `hsw-blog-grid` | وبلاگ: شبکه مقالات (HamiShop) | class-hsw-widget-blog-grid.php | — | 2 | 2 | 11 | ارثی |
| `hsw-blog-hero` | وبلاگ: سربرگ مجله (HamiShop) | class-hsw-widget-blog-hero.php | — | 2 | 0 | 14 | ارثی |
| `hsw-blog-newsletter` | وبلاگ: خبرنامه (HamiShop) | class-hsw-widget-blog-newsletter.php | — | 1 | 0 | 9 | ارثی |
| `hsw-blog-page` | صفحه کامل مجله — حامی (ویجت جامع) | class-hsw-widget-blog-page.php | — | 9 | 19 | 61 | hsw-fonts, hsw-blog, hsw-composite |
| `hsw-blog-popular` | وبلاگ: پربازدیدترین‌ها (HamiShop) | class-hsw-widget-blog-popular.php | — | 1 | 0 | 6 | ارثی |
| `hsw-blog-tabs` | وبلاگ: تب دسته‌ها و مرتب‌سازی (HamiShop) | class-hsw-widget-blog-tabs.php | — | 1 | 0 | 5 | ارثی |
| `hsw-blog-tags` | وبلاگ: برچسب‌های داغ (HamiShop) | class-hsw-widget-blog-tags.php | — | 1 | 0 | 4 | ارثی |

### نوشتهٔ تکی — `hsw-post`

| ویجت | عنوان | فایل | Repeater | سکشن | ریسپانسیو | کنترل | CSS |
|---|---|---|:--:|:--:|:--:|:--:|---|
| `hsw-post-page` | مجله: صفحه کامل نوشته (HamiShop) | class-hsw-widget-post-page.php | — | 5 | 11 | 59 | hsw-fonts, hsw-post |

### فوتر — `hsw-footer`

| ویجت | عنوان | فایل | Repeater | سکشن | ریسپانسیو | کنترل | CSS |
|---|---|---|:--:|:--:|:--:|:--:|---|
| `hsw-footer-sticky` | فوتر چسبان (HamiShop) | class-hsw-widget-footer-sticky.php | ✔ | 4 | 7 | 19 | hsw-fonts, hsw-footer |

## ۴) مکانیزم‌ها

| مکانیزم | خلاصهٔ فنی |
|---|---|
| **شمارندهٔ سبد** | `get_cart_contents_count()` در `data-count` + `woocommerce_add_to_cart_fragments`؛ در حالت کش (نبود کوکی) صفر می‌شود. |
| **تغییر تعداد (AJAX)** | `hsw-cart-qty` → `HSW_Cart_Actions::ajax_set_qty` با نانس؛ سقف از `get_max_purchase_quantity` + موجودی + `sold_individually`. |
| **«سود شما از خرید»** | `HSW_Helpers::savings()` = Σ(قیمت اصلی − قیمت فروش)×تعداد + کوپن. ارسال/کارمزد دخالت ندارند. |
| **علاقه‌مندی** | `[data-hsw-wish]` → `localStorage` + `hswFill()` به `#hsw-i-favorite.fill`. |
| **فیلترهای آرشیو** | یک `<form>` با **دو نسخه** از هر فیلد؛ قبل از submit نسخهٔ نامرئی `disabled` می‌شود تا هر نام یک‌بار ارسال شود. **فیلتر قیمت دو دستگیره** با `left/right` فیزیکی و آگاهی از RTL. |
| **فیلتر قیمت (جزئیات)** | `data-hsw-priceminrange` + `data-hsw-pricemax`؛ ورودی عددی `step=any` بدون min/max؛ مقدار تایپ‌شده فقط همان فیلد را clamp می‌کند؛ اسلایدر گام `step/10`. |
| **شیت‌های پایین‌صفحه** | `hsw-sheet` فقط با JS ساخته می‌شود؛ `hsw-asheet`/`hsw-agd__modal` با `HSW_Assets::sheet_guard()` قبل از اولین رنگ مخفی می‌مانند. |
| **نوار خرید چسبان** | استپر «− ۱ +»؛ در تعداد ۱ سطل زباله؛ `z-index: 90` تا زیر هدر/منوی موبایل بماند. |
| **نوار پیشرفت مقاله** | نسبت اسکرول بدنه؛ اگر بدنه کوتاه‌تر از صفحه باشد به اسکرول سند فالبک می‌کند. |
| **کشوی حساب (موبایل)** | زیر ۱۰۲۴px سایدبار off-canvas با اسکریم، Esc و `aria-expanded`. |
| **ارسال رایگان (اختیاری)** | `hsw_free_shipping_on=yes` → `woocommerce_package_rates` (۲۰) بالای آستانه صفر می‌کند. |
| **تسویه: امنیت و نرخ** | `woocommerce_before_checkout_process` + `woocommerce_checkout_process` قبل از `create_order`؛ `terms` واقعی؛ نرخ ۲۰/۶۰s (IP+session) + IP-only ×۵؛ کوپن ۱۰/۶۰s + قفل ۱۰ خطا/۱۰ دقیقه. |
| **چیدمان سبد/تسویه/مجله** | `@media (min-width:1024px)` روی `.hsw-cp--cart`/`--checkout`/`--blog` → `grid-template-columns: minmax(0,1fr) var(--hsw-cp-side, …)` + سایدبار sticky. |
| **مجله: ترتیب صفحه** | هیرو → تب‌ها → مقالهٔ ویژه (تمام‌عرض) → دو ستون (کارت‌ها | سایدبار). ویژه: متن چپ، تصویر راست ۵۸٪ با `order`. |
| **حساب کاربری** | کارت‌ها با یک مجموعه سلکتور مشترک (آمار/سفارش/اطلاعات)؛ منو با حالت `is-active`؛ همه ریسپانسیو. |
| **فهرست مطالب نوشته** | `href="#id"` فقط fragment → آدرس کامل تولید نمی‌شود و صفحهٔ جدا ایندکس نمی‌شود؛ حالت `slug` هم fragment است. |

## ۵) الگوهای تداخل واقعی

| # | الگو | نشانه | رفع |
|---|---|---|---|
| ۱ | `var(--x)` بدون fallback | رنگ/فونت از قالب | مقدار جانشین concrete |
| ۲ | `font-family: var(--hsw-font, inherit)` | تایپوگرافی سراسری المنتور | فالبک `"Vazirmatn HSW", Tahoma` |
| ۳ | `:not(.a)` روی خانوادهٔ دکمه | واریانت دیگر ناپیدا | هدف‌گیری صریح |
| ۴ | `!important` روی ویژگی مالکیت‌دار | کنترل مرده | حذف کنترل‌شده + اسنپ‌شات |
| ۵ | `z-index` بالا در ریشه | منوی موبایل زیر فوتر | ۸۰۰۰ → ۹۰ |
| ۶ | نام تکراری فیلد در یک `<form>` | فیلتر اعمال نمی‌شود | غیرفعال‌کردن نسخهٔ نامرئی |
| ۷ | **کلاس اشتباه ریشه** (`hsw-bhero` در حالی که ریشه `hsw-blog-hero` است) | کنترل بی‌اثر | هدف‌گیری هر دو کلاس |
| ۸ | **کلاس اشتباه حالت فعال** (`is-active` در حالی که `is-on` است) | رنگ حالت فعال اعمال نمی‌شود | هر دو کلاس |
| ۹ | **تصادم نام کلاس** (ریل قیمت = ریل سوییچ) | کامپوننت دیگر خراب می‌شود | نام اختصاصی (`__pricetrack`) |
| ۱۰ | `background-color` زیر گرادیان | رنگ دیده نمی‌شود | `background: {{VALUE}}` |

## ۶) پروتکل تأیید هر تغییر

```
1) خواندن کد / grep → منبع واقعی، نه حدس
2) اسنپ‌شات computed style با Playwright (قبل)
3) تغییر + python tools/build-assets.py
4) اسنپ‌شات (بعد) و diff → صفر تفاوت یا فقط مورد هدف
5) curl صفحات کلیدی → 200 و صفر Fatal
6) کنترل‌های المنتور: get_controls() با Performance::set_use_style_controls(true)
   و شمارش بر اساس id (برچسب‌ها خالی برمی‌گردند)
```

**تله‌های اندازه‌گیری که واقعاً گول زدند:**
1. `get_controls()` در حالت پیش‌فرض کنترل‌های تب استایل را حذف می‌کند.
2. برچسب سکشن‌ها در خروجی خالی است → فقط `id` قابل اتکاست.
3. `a_typo()` شناسه را `{id}_typo_typography` می‌سازد و group shadow را `{id}_box_shadow`.

## ۷) چک‌لیست افزودن ویجت

1. ارث از کلاس پایهٔ خانواده.
2. `get_name()` با پیشوند `hsw-`، `get_title()` فارسی.
3. سکشن محتوا (Repeater + لینک بومی + آیکون) و سکشن استایل (رنگ/تایپ/فاصله/چیدمان) با `add_responsive_control`.
4. رنگ از `$this->a_color()` با fallback پالت؛ `!important` روی ویژگی کنترل‌شده نگذار.
5. CSS در فایل خانواده؛ نام کلاس جدید را با `grep` چک کن که تصادم نداشته باشد.
6. اگر ویجت فرزند است، برای استایلش `style_section_mirror()` در ویجت صفحه اضافه کن.
7. اسنپ‌شات قبل/بعد و ثبت نتیجه.

