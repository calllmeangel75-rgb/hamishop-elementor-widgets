# یادداشت‌های انتشار نگارش 1.11.235 (Release Notes v1.11.235)

> **موضوع:** دکمهٔ پرداخت، فرم جستجوی هدر را می‌فرستاد و به `/?s=` می‌رفت. اشتباه من در ۱.۱۱.۲۳۴ بود.

## ۱. چه اتفاقی افتاد

در ۱.۱۱.۲۳۴ برای اینکه دکمه وقتی `#place_order` نیست هم کار کند، این انتخابگر را گذاشتم:

```js
document.querySelector( 'form.checkout, form[name="checkout"], .woocommerce-checkout form' )
```

اشتباه در بخش سوم است: **`woocommerce-checkout` یک کلاس روی `<body>` است**، نه روی فرم. یعنی `.woocommerce-checkout form` با **هر فرمی** در صفحه مطابقت دارد — و `querySelector` **اولین** فرم در DOM را برمی‌گرداند. اولین فرم صفحه، فرم جستجوی هدر است.

نتیجه: `requestSubmit()` روی فرم جستجو → `?s=` (خالی) → `/`.

## ۲. رفع

```js
var form = document.querySelector( 'form.checkout, form[name="checkout"]' );

if ( form ) {
    var looks_like_search    = !! form.querySelector( 'input[name="s"]' );
    var has_checkout_field   = !! form.querySelector( '#place_order, .woocommerce-checkout-payment, input[name="billing_email"], input[name="billing_first_name"]' );

    if ( looks_like_search || ! has_checkout_field ) { form = null; }
}

if ( form && form.requestSubmit ) { form.requestSubmit(); return; }

console.warn( '[HamiShop] Checkout form not found; the order was not placed. Add the «فرم تسویه» widget to this page.' );
```

سه تفاوت مهم:

1. `.woocommerce-checkout form` **حذف شد** — هرگز با کلاس والد، فرم انتخاب نمی‌شود.
2. هر فرمی که `input[name="s"]` داشته باشد یا هیچ فیلد تسویه‌ای نداشته باشد، **رد** می‌شود.
3. اگر فرمی پیدا نشود، **هیچ کاری نمی‌کند** — نه رفرش، نه پرش به جای اشتباه. فقط یک هشدار در Console.

## ۳. گارد در CI

دو بررسی جدید در `tools/ci-checks.php`:

- اگر انتخابگر `.woocommerce-checkout form` برگردد → CI قرمز.
- اگر گارد `input[name="s"]` یا `has_checkout_field` حذف شود → CI قرمز.

## ۴. اگر دکمه هنوز کاری نمی‌کند

یعنی روی صفحهٔ تسویه، ویجت **«فرم تسویه»** وجود ندارد (همان که `[woocommerce_checkout]` را رندر می‌کند و `#place_order` را می‌سازد). در Console این را می‌بینی:

```
[HamiShop] Checkout form not found; the order was not placed.
```

راه‌حل: ویجت «فرم تسویه — حامی» را به همان صفحه اضافه کن. بدون آن، هیچ دکمه‌ای نمی‌تواند سفارش را ثبت کند.

## ۵. فایل‌های تغییر‌یافته

- `assets/js/hsw-checkout.js` + `.min.js`
- `tools/ci-checks.php` — دو گارد جدید
- نسخه، `readme.txt`، `CHANGELOG.md`، POT
