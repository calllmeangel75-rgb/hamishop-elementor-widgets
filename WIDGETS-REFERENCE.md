# مرجع فنی ویجت‌ها — HamiShop Elementor Widgets

> این فایل از خود کد تولید شده (نسخهٔ **1.11.121**) و برای توسعه‌دهندهٔ وردپرس/المنتور نوشته است:
> هر ویجت چه می‌کند، چه دارایی‌ای لود می‌کند، و مکانیزم پشت آن چیست.

## ۰) نمای کلی

```
Elementor page
 ├─ ویجت «صفحهٔ کامل …» (hsw-*-page)   ← اسکلت صفحه، گرید، هیرو
 └─ ویجت‌های تکی (hsw-cart-items، hsw-product-purchase، …)
        │
        │  get_style_depends() / get_script_depends()
        ▼
HSW_Assets  — فقط register، بدون enqueue سراسری
 ├─ hsw-fonts      پایهٔ مشترک: وزیرمتن + متغیرهای پالت
 └─ hsw-<family>   header · product · cart · checkout · complete ·
                   archive · blog · post · account · loop · footer
        │
        ▼
HSW_Palette → html:root { --hsw-primary … --hsw-cta* --hsw-woo-btn-* --hsw-btn-* --hsw-font }
        │
        ▼
متغیر کامپوننت روی ریشهٔ ویجت → .hsw-c { --hswc-cta: var(--hsw-cta) }
        │
        ▼
المان/دکمه → var(--hsw-cs-cta-bg, var(--hswc-cta, #0f2042))
```

### قاعده‌های ثابت

| # | قاعده | چرا |
|---|---|---|
| ۱ | `HSW_Widget_Base` هیچ استایلی ندارد؛ هر خانواده کلاس پایهٔ خودش را دارد | لوپ/محصول در همهٔ صفحات لود نشود |
| ۲ | هر ویجت فقط دارایی خودش را اعلام می‌کند | ۳–۵ فایل CSS در هر صفحه، نه ۱۱ |
| ۳ | هیچ رنگ/فونتی بدون fallback مصرف نمی‌شود | باگ «دکمهٔ قرمز» و «فونت المنتور» از همین بود |
| ۴ | `!important` فقط برای غلبه بر قالب، نه روی ویژگی‌های کنترل‌شده | کنترل ویجت باید زنده بماند |
| ۵ | هیچ استایل سراسری در `<head>` تزریق نمی‌شود | دارایی هر بخش، در همان بخش |

## ۱) سیستم رنگ

| خانوادهٔ دکمه | توکن پالت | متغیرها |
|---|---|---|
| مسیر خرید | CTA مراحل خرید / هاور / کلیک / متن | `--hsw-cta`, `--hsw-cta-hover`, `--hsw-cta-active`, `--hsw-on-cta` |
| دکمه‌های ووکامرس | دکمه‌های ووکامرس — پس‌زمینه/متن/هاور | `--hsw-woo-btn-bg`, `-c`, `-hover` |
| دکمه‌های معمولی | دکمه‌های معمولی — پس‌زمینه/متن/حاشیه/هاور | `--hsw-btn-bg`, `-c`, `-bd`, `-hover-bg` |

## ۲) آیکون، فونت، تقویم

- **آیکون:** اسپرایت SVG درون‌صفحه‌ای که فقط آیکون‌های همان درخواست را چاپ می‌کند (صفر درخواست شبکه). واریانت توپُر با کلید `name.fill` و `hswFill()` که وجود سمبل را چک می‌کند.
- **فونت:** وزیرمتن بسته‌بندی‌شده (`font-display: swap`) + فالبک `Tahoma, sans-serif`. «ارث‌بری از قالب» فقط با انتخاب صریح در ویجت.
- **تقویم شمسی:** `HSW_Jalali` بدون کتابخانه؛ فیلترها فقط با کلید `hsw_jalali_on` ثبت می‌شوند (`date_i18n`, `get_the_date`, `get_the_modified_date`, `get_the_time`). پنل مدیریت گرگوری می‌ماند.

## ۳) فهرست ویجت‌ها به تفکیک خانواده


### هدر — `hsw-header`

| ویجت (slug) | عنوان | فایل | Repeater | سکشن | ریسپانسیو | CSS |
|---|---|---|:--:|:--:|:--:|---|
| `hsw-header-minimal` | هدر مینیمال شیشه‌ای (HamiShop) | class-hsw-widget-header-minimal.php | — | 2 | 0 | ارثی از خانواده |
| `hsw-header-store` | هدر فروشگاهی (HamiShop) | class-hsw-widget-header-store.php | — | 3 | 0 | ارثی از خانواده |

### لوپ محصول — `hsw-loop`

| ویجت (slug) | عنوان | فایل | Repeater | سکشن | ریسپانسیو | CSS |
|---|---|---|:--:|:--:|:--:|---|
| `hsw-best-sellers` | پرفروش‌ترین‌ها (HamiShop) | class-hsw-best-sellers.php | — | 0 | 0 | hsw-fonts, hsw-loop |
| `hsw-popular` | محبوب‌ترین‌ها / منتخب (HamiShop) | class-hsw-popular.php | ✔ | 2 | 0 | hsw-fonts, hsw-loop |
| `hsw-wonder-deals` | پیشنهاد شگفت‌انگیز (HamiShop) | class-hsw-wonder-deals.php | — | 0 | 0 | hsw-fonts, hsw-loop |

### آرشیو فروشگاه — `hsw-archive`

| ویجت (slug) | عنوان | فایل | Repeater | سکشن | ریسپانسیو | CSS |
|---|---|---|:--:|:--:|:--:|---|
| `hsw-archive-breadcrumb` | بردکرامب آرشیو (HamiShop) | class-hsw-widget-archive-breadcrumb.php | — | 2 | 0 | ارثی از خانواده |
| `hsw-archive-filters` | فیلترهای پیشرفته آرشیو (HamiShop) | class-hsw-widget-archive-filters.php | ✔ | 2 | 0 | ارثی از خانواده |
| `hsw-archive-grid` | گرید محصولات آرشیو (HamiShop) | class-hsw-widget-archive-grid.php | — | 2 | 1 | ارثی از خانواده |
| `hsw-archive-guide` | راهنما و سوالات متداول آرشیو (HamiShop) | class-hsw-widget-archive-guide.php | ✔ | 2 | 0 | ارثی از خانواده |
| `hsw-archive-header` | هدر و توضیحات آرشیو (HamiShop) | class-hsw-widget-archive-header.php | ✔ | 2 | 1 | ارثی از خانواده |
| `hsw-archive-page` | صفحه کامل آرشیو — حامی (ویجت جامع) | class-hsw-widget-archive-page.php | — | 2 | 2 | hsw-fonts, hsw-archive, hsw-composite |
| `hsw-archive-pagination` | صفحه‌بندی آرشیو (HamiShop) | class-hsw-widget-archive-pagination.php | — | 2 | 0 | ارثی از خانواده |
| `hsw-archive-toolbar` | نوار مرتب‌سازی و ابزار آرشیو (HamiShop) | class-hsw-widget-archive-toolbar.php | — | 2 | 0 | ارثی از خانواده |

### محصول — `hsw-product`

| ویجت (slug) | عنوان | فایل | Repeater | سکشن | ریسپانسیو | CSS |
|---|---|---|:--:|:--:|:--:|---|
| `hsw-product-accordions` | آکاردئون‌های محصول (HamiShop) | class-hsw-widget-product-accordions.php | ✔ | 2 | 0 | ارثی از خانواده |
| `hsw-product-breadcrumb` | همی‌راه محصول (HamiShop) | class-hsw-widget-product-breadcrumb.php | — | 2 | 1 | ارثی از خانواده |
| `hsw-product-features` | ویژگی‌های شاخص محصول (HamiShop) | class-hsw-widget-product-features.php | ✔ | 2 | 0 | ارثی از خانواده |
| `hsw-product-gallery` | گالری محصول (HamiShop) | class-hsw-widget-product-gallery.php | — | 2 | 1 | ارثی از خانواده |
| `hsw-product-page` | صفحه کامل محصول — حامی (ویجت جامع) | class-hsw-widget-product-page.php | — | 2 | 2 | hsw-fonts, hsw-product, hsw-composite |
| `hsw-product-purchase` | جعبه خرید و قیمت (HamiShop) | class-hsw-widget-product-purchase.php | ✔ | 2 | 1 | ارثی از خانواده |
| `hsw-product-related` | محصولات مرتبط (HamiShop) | class-hsw-widget-product-related.php | — | 1 | 0 | ارثی از خانواده |
| `hsw-product-sticky` | نوار خرید چسبان (HamiShop) | class-hsw-widget-product-sticky.php | — | 2 | 0 | ارثی از خانواده |
| `hsw-product-tabs` | تب‌های محصول (HamiShop) | class-hsw-widget-product-tabs.php | — | 2 | 1 | ارثی از خانواده |
| `hsw-product-title` | عنوان، امتیاز و متای محصول (HamiShop) | class-hsw-widget-product-title.php | — | 2 | 2 | ارثی از خانواده |
| `hsw-product-trust` | نوار اعتماد و ضمانت‌ها (HamiShop) | class-hsw-widget-product-trust.php | ✔ | 2 | 0 | ارثی از خانواده |
| `hsw-product-variants` | انتخاب رنگ و حافظه (HamiShop) | class-hsw-widget-product-variants.php | ✔ | 2 | 0 | ارثی از خانواده |

### سبد خرید — `hsw-cart`

| ویجت (slug) | عنوان | فایل | Repeater | سکشن | ریسپانسیو | CSS |
|---|---|---|:--:|:--:|:--:|---|
| `hsw-cart-banner` | بنر ارسال رایگان سبد (HamiShop) | class-hsw-widget-cart-banner.php | — | 2 | 0 | ارثی از خانواده |
| `hsw-cart-coupon` | کد تخفیف سبد خرید (HamiShop) | class-hsw-widget-cart-coupon.php | — | 2 | 1 | ارثی از خانواده |
| `hsw-cart-items` | اقلام سبد خرید (HamiShop) | class-hsw-widget-cart-items.php | — | 3 | 0 | ارثی از خانواده |
| `hsw-cart-page` | صفحه کامل سبد خرید — حامی (ویجت جامع) | class-hsw-widget-cart-page.php | — | 1 | 0 | hsw-fonts, hsw-cart, hsw-composite |
| `hsw-cart-summary` | خلاصه فاکتور سبد خرید (HamiShop) | class-hsw-widget-cart-summary.php | ✔ | 2 | 1 | ارثی از خانواده |
| `hsw-cart-top` | هدر و مراحل سبد خرید (HamiShop) | class-hsw-widget-cart-top.php | ✔ | 2 | 0 | ارثی از خانواده |

### تسویه و پرداخت — `hsw-checkout`

| ویجت (slug) | عنوان | فایل | Repeater | سکشن | ریسپانسیو | CSS |
|---|---|---|:--:|:--:|:--:|---|
| `hsw-checkout-address` | آدرس تحویل سفارش (HamiShop) | class-hsw-widget-checkout-address.php | — | 2 | 0 | ارثی از خانواده |
| `hsw-checkout-items` | اقلام مرسوله (HamiShop) | class-hsw-widget-checkout-items.php | — | 2 | 0 | ارثی از خانواده |
| `hsw-checkout-note` | یادداشت سفارش و فاکتور (HamiShop) | class-hsw-widget-checkout-note.php | — | 2 | 0 | ارثی از خانواده |
| `hsw-checkout-page` | صفحه کامل تسویه حساب — حامی (ویجت جامع) | class-hsw-widget-checkout-page.php | — | 2 | 1 | hsw-fonts, hsw-checkout, hsw-composite |
| `hsw-checkout-schedule` | زمان و روز تحویل (HamiShop) | class-hsw-widget-checkout-schedule.php | ✔ | 2 | 0 | ارثی از خانواده |
| `hsw-checkout-shipping` | انتخاب روش ارسال (HamiShop) | class-hsw-widget-checkout-shipping.php | ✔ | 2 | 0 | ارثی از خانواده |
| `hsw-checkout-summary` | خلاصه پرداخت اطلاعات ارسال (HamiShop) | class-hsw-widget-checkout-summary.php | ✔ | 2 | 0 | ارثی از خانواده |
| `hsw-checkout-top` | هدر و مراحل اطلاعات ارسال (HamiShop) | class-hsw-widget-checkout-top.php | ✔ | 2 | 0 | ارثی از خانواده |
| `hsw-pay-coupon` | کد تخفیف و کارت هدیه پرداخت (HamiShop) | class-hsw-widget-pay-coupon.php | — | 2 | 0 | ارثی از خانواده |
| `hsw-pay-methods` | روش‌های پرداخت (HamiShop) | class-hsw-widget-pay-methods.php | ✔ | 2 | 0 | ارثی از خانواده |
| `hsw-pay-page` | صفحه کامل پرداخت — حامی (ویجت جامع) | class-hsw-widget-pay-page.php | — | 1 | 0 | hsw-fonts, hsw-checkout, hsw-composite |
| `hsw-pay-recall` | مشخصات تحویل سفارش (HamiShop) | class-hsw-widget-pay-recall.php | — | 2 | 0 | ارثی از خانواده |
| `hsw-pay-summary` | خلاصه فاکتور و پرداخت نهایی (HamiShop) | class-hsw-widget-pay-summary.php | ✔ | 2 | 0 | ارثی از خانواده |
| `hsw-pay-top` | هدر و مراحل پرداخت (HamiShop) | class-hsw-widget-pay-top.php | ✔ | 2 | 0 | ارثی از خانواده |

### تکمیل خرید — `hsw-complete`

| ویجت (slug) | عنوان | فایل | Repeater | سکشن | ریسپانسیو | CSS |
|---|---|---|:--:|:--:|:--:|---|
| `hsw-complete-actions` | دکمه‌های تکمیل خرید (HamiShop) | class-hsw-widget-complete-actions.php | — | 2 | 0 | ارثی از خانواده |
| `hsw-complete-delivery` | اطلاعات تحویل تکمیل خرید (HamiShop) | class-hsw-widget-complete-delivery.php | — | 2 | 0 | ارثی از خانواده |
| `hsw-complete-hero` | سربرگ تکمیل خرید (HamiShop) | class-hsw-widget-complete-hero.php | — | 2 | 0 | ارثی از خانواده |
| `hsw-complete-order` | جزئیات سفارش تکمیل خرید (HamiShop) | class-hsw-widget-complete-order.php | — | 2 | 0 | ارثی از خانواده |
| `hsw-complete-page` | صفحه کامل تکمیل خرید — حامی (ویجت جامع) | class-hsw-widget-complete-page.php | — | 1 | 0 | hsw-fonts, hsw-checkout, hsw-complete, hsw-composite |
| `hsw-complete-steps` | مراحل بعدی تکمیل خرید (HamiShop) | class-hsw-widget-complete-steps.php | ✔ | 2 | 0 | ارثی از خانواده |

### حساب کاربری — `hsw-account`

| ویجت (slug) | عنوان | فایل | Repeater | سکشن | ریسپانسیو | CSS |
|---|---|---|:--:|:--:|:--:|---|
| `hsw-account` | پنل حساب کاربری HamiShop | class-hsw-widget-account.php | — | 8 | 0 | hsw-fonts, hsw-account |

### مجله — `hsw-blog`

| ویجت (slug) | عنوان | فایل | Repeater | سکشن | ریسپانسیو | CSS |
|---|---|---|:--:|:--:|:--:|---|
| `hsw-blog-featured` | وبلاگ: مقاله ویژه (HamiShop) | class-hsw-widget-blog-featured.php | — | 1 | 0 | ارثی از خانواده |
| `hsw-blog-grid` | وبلاگ: شبکه مقالات (HamiShop) | class-hsw-widget-blog-grid.php | — | 2 | 2 | ارثی از خانواده |
| `hsw-blog-hero` | وبلاگ: سربرگ مجله (HamiShop) | class-hsw-widget-blog-hero.php | — | 2 | 0 | ارثی از خانواده |
| `hsw-blog-newsletter` | وبلاگ: خبرنامه (HamiShop) | class-hsw-widget-blog-newsletter.php | — | 1 | 0 | ارثی از خانواده |
| `hsw-blog-page` | صفحه کامل مجله — حامی (ویجت جامع) | class-hsw-widget-blog-page.php | — | 2 | 1 | hsw-fonts, hsw-blog, hsw-composite |
| `hsw-blog-popular` | وبلاگ: پربازدیدترین‌ها (HamiShop) | class-hsw-widget-blog-popular.php | — | 1 | 0 | ارثی از خانواده |
| `hsw-blog-tabs` | وبلاگ: تب دسته‌ها و مرتب‌سازی (HamiShop) | class-hsw-widget-blog-tabs.php | — | 1 | 0 | ارثی از خانواده |
| `hsw-blog-tags` | وبلاگ: برچسب‌های داغ (HamiShop) | class-hsw-widget-blog-tags.php | — | 1 | 0 | ارثی از خانواده |

### نوشتهٔ تکی — `hsw-post`

| ویجت (slug) | عنوان | فایل | Repeater | سکشن | ریسپانسیو | CSS |
|---|---|---|:--:|:--:|:--:|---|
| `hsw-post-page` | مجله: صفحه کامل نوشته (HamiShop) | class-hsw-widget-post-page.php | — | 3 | 2 | hsw-fonts, hsw-post |

### فوتر — `hsw-footer`

| ویجت (slug) | عنوان | فایل | Repeater | سکشن | ریسپانسیو | CSS |
|---|---|---|:--:|:--:|:--:|---|
| `hsw-footer-sticky` | فوتر چسبان (HamiShop) | class-hsw-widget-footer-sticky.php | ✔ | 4 | 7 | hsw-fonts, hsw-footer |

## ۴) مکانیزم‌ها

| مکانیزم | خلاصهٔ فنی |
|---|---|
| **شمارندهٔ سبد** | `get_cart_contents_count()` در `data-count` و متن فارسی در badge؛ `woocommerce_add_to_cart_fragments` آن را به‌روز می‌کند. `hsw-header.js` روی `hsw_added_to_cart` و `added_to_cart` گوش می‌دهد و در حالت کش (نبود کوکی `woocommerce_items_in_cart`) صفر می‌کند. |
| **تغییر تعداد (AJAX)** | `hsw-cart-qty` → `HSW_Cart_Actions::ajax_set_qty`: سقف از `get_max_purchase_quantity` + موجودی + `sold_individually` + فیلتر `woocommerce_update_cart_validation`. پاسخ `{quantity, max, remaining, message}`؛ در 403 یک‌بار نانس از `GET /wp-json/hsw/v1/qty-nonce` تازه می‌شود. |
| **«سود شما از خرید»** | `HSW_Helpers::savings()` = `Σ(قیمت اصلی − قیمت فروش) × تعداد + تخفیف کوپن`. ارسال/کارمزد/مالیات دخالت ندارند. «قیمت پایه اقلام» از `get_regular_price()` تا `پایه − سود = جمع اقلام` برقرار بماند. |
| **علاقه‌مندی** | `[data-hsw-wish]` → `localStorage` (`WISH_KEY`) → `is-active` + `hswFill()` به `#hsw-i-favorite.fill`. اگر سمبل توپُر نباشد، خطی می‌ماند (نه ناپدید). |
| **فیلترهای آرشیو** | یک `<form class="hsw-af__form">` با دو نسخه از هر فیلد (سایدبار دسکتاپ + شیت موبایل). قبل از submit نسخهٔ نامرئی `disabled` می‌شود تا هر نام یک‌بار ارسال شود. دسکتاپ: debounce ۶۵۰ms؛ موبایل: دکمهٔ «اعمال فیلترها». فیلتر قیمت را `WC_Query::price_filter_post_clauses` روی کوئری اصلی اعمال می‌کند. |
| **شیت‌های پایین‌صفحه** | `hsw-sheet` فقط با JS و لحظهٔ باز شدن ساخته می‌شود؛ `hsw-asheet`/`hsw-agd__modal` در HTML هستند و با `HSW_Assets::sheet_guard()` (که فقط ویجت‌های صاحب شیت یک‌بار چاپش می‌کنند) قبل از اولین رنگ مخفی می‌مانند — با `:not(.is-open)` تا باز شدن را نشکند. |
| **نوار خرید چسبان** | استپر «− ۱ +»؛ در تعداد ۱ جای «−» سطل زباله می‌آید و `is-min` بعد از هر AJAX در JS سوییچ می‌شود. عرض ۵۰٪ استپر / ۴۵٪ قیمت؛ استپر سمت شروع. `z-index: 90` تا زیر هدر و منوی موبایل بماند. |
| **نوار پیشرفت مقاله** | `[data-hsw-pp-progress]` با نسبت اسکرول بدنهٔ مقاله؛ اگر بدنه کوتاه‌تر از صفحه باشد به اسکرول سند فالبک می‌کند. کارت محصول مقاله: متای `_hsw_product_id` → نام دستهٔ محصول → برچسب محصول → در نبود تطبیق مخفی. |
| **کشوی حساب (موبایل)** | زیر ۱۰۲۴px نوار `.hsw-ac__crumbs` فقط نقطهٔ آخر را نشان می‌دهد؛ دکمهٔ منو سایدبار را off-canvas باز می‌کند (اسکریم، Esc، کلیک بیرون، `aria-expanded`) و نوار `sticky` است. |
| **تسویه: امنیت و نرخ** | `POST /hsw/v1/checkout/complete` قبل از `create_order()` هوک‌های `woocommerce_before_checkout_process` و `woocommerce_checkout_process` را اجرا می‌کند و خطاهای `wc_notice` را ۴۰۰ برمی‌گرداند (کپچا/ضدتقلب). `terms` از ورودی خوانده می‌شود و فقط با `wc_terms_and_conditions_checkbox_enabled()` اجباری است. نرخ: complete ۲۰/۶۰s برای IP+session + شمارندهٔ IP-only ×۵ (CGNAT)، coupon ۱۰/۶۰s + قفل پس از ۱۰ خطا/۱۰ دقیقه؛ فیلتر `hsw_checkout_rate_limit`، پاسخ ۴۲۹ با `retry_after`. |
| **ارسال رایگان (اختیاری)** | پیش‌فرض **خاموش**. با `hsw_free_shipping_on = yes` فیلتر `woocommerce_package_rates` (اولویت ۲۰) بالای آستانه همهٔ نرخ‌ها را صفر می‌کند و شرط وزن/کلاس ارسال ووکامرس را دور می‌زند — به همین دلیل پیش‌فرض خاموش است. |

## ۵) الگوهای تداخل واقعی

| # | الگو | نشانه | رفع |
|---|---|---|---|
| ۱ | `var(--x)` بدون fallback | رنگ/فونت از قالب یا المنتور | مقدار جانشین concrete (۱.۱۱.۱۰۳، ۱.۱۱.۱۰۵) |
| ۲ | `font-family: var(--hsw-font, inherit)` | تایپوگرافی سراسری المنتور برنده | فالبک `"Vazirmatn HSW", Tahoma` |
| ۳ | `:not(.a)` روی خانوادهٔ دکمه | واریانت هیرو ناپیدا | هدف‌گیری صریح واریانت (۱.۱۱.۱۰۸) |
| ۴ | `!important` روی ویژگی مالکیت‌دار المنتور | کنترل ویجت مرده | حذف کنترل‌شده + اسنپ‌شات (۱.۱۱.۱۱۰) |
| ۵ | `z-index` بالا در سطح ریشه | منوی موبایل زیر فوتر | ۸۰۰۰ → ۹۰ (۱.۱۱.۱۰۱) |
| ۶ | نام تکراری فیلد در یک `<form>` | فیلتر اعمال نمی‌شود | غیرفعال‌کردن نسخهٔ نامرئی (۱.۱۱.۱۱۴) |

## ۶) پروتکل تأیید هر تغییر

```
1) خواندن کد / grep  → پیدا کردن منبع واقعی، نه حدس
2) اسنپ‌شات computed style با Playwright (قبل)
3) تغییر + python tools/build-assets.py
4) اسنپ‌شات (بعد) و diff → صفر تفاوت یا فقط مورد هدف
5) curl روی صفحات کلیدی → 200 و صفر Fatal
6) برای کنترل‌های المنتور: dump با get_controls()، نه «صفحه باز شد»
```

## ۷) چک‌لیست افزودن ویجت جدید

1. از کلاس پایهٔ خانواده ارث ببر (`HSW_Product_Base`, `HSW_Cart_Base`, …).
2. `get_name()` با پیشوند `hsw-`، `get_title()` فارسی.
3. سکشن محتوا (Repeater + لینک بومی + آیکون اختیاری) و سکشن استایل (رنگ/تایپوگرافی/فاصله/چیدمان) با `add_responsive_control` جایی که اندازه دارد.
4. رنگ‌ها را از `$this->a_color()` بگیر (fallback خودکار)؛ `!important` روی ویژگی‌های کنترل‌شده نگذار.
5. CSS در فایل خانواده؛ فایل جدید فقط برای خانوادهٔ تازه.
6. اگر شیت/مودال داری، `HSW_Assets::sheet_guard()` را قبل از مارک‌آپ چاپ کن.
7. اسنپ‌شات قبل/بعد و ثبت نتیجه.
