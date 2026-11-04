# یادداشت‌های انتشار نگارش 1.11.234 (Release Notes v1.11.234)

> **موضوع:** علت واقعی ۵۰۳ پیدا شد — یک بازگشت بی‌نهایت در تشخیص «سبد دیجیتال».

## ۱. ریشهٔ ۵۰۳

در `HSW_Checkout_Digital`:

```php
add_filter( 'woocommerce_cart_needs_shipping', array( $this, 'filter_cart_needs_shipping' ), 20 );

public static function is_digital() {
    ...
    if ( ! WC()->cart->needs_shipping() ) {   // ← این فیلتر را صدا می‌زند
        return true;
    }
    ...
}

public function filter_cart_needs_shipping( $needs_shipping ) {
    if ( self::is_digital() ) {               // ← و این دوباره همان تابع
        return false;
    }
    return $needs_shipping;
}
```

یعنی:

```
is_digital() → needs_shipping() → فیلتر ما → is_digital() → needs_shipping() → …
```

**بدون پایان.** هر دور یک فریم روی پشته و کمی حافظه؛ تا جایی که درخواست بمیرد.

### چرا این همه چیز را توضیح می‌دهد

| مشاهده | توضیح |
|---|---|
| صفحه‌ها ۲۰۰، ولی «افزودن به سبد خرید» ۵۰۳ | این مسیر `needs_shipping()` را صدا می‌زند؛ صفحه‌های معمولی نه |
| با سبد **خالی** همه‌چیز سالم | `is_digital()` قبل از رسیدن به `needs_shipping()` با `is_empty()` برمی‌گردد |
| در `error_log` هیچ `Fatal` نبود | فروپاشی پشته/حافظه پیش از آنکه PHP بتواند چیزی بنویسد رخ می‌دهد |
| از IP دیگر سایت سالم بود | آن درخواست‌ها سبد نداشتند |
| غیرفعالکردن افزونه مشکل را حل کرد | کلاس از مدار خارج می‌شود |

**اثبات:** یک تست بازتولید نوشتم (`tests/qa/digital-checkout-sim.php`). روی نسخهٔ **بدون** گارد:

```
Fatal error: Allowed memory size of 100663296 bytes exhausted
```

روی نسخهٔ با گارد:

```
[ok] mixed cart is not digital
[ok] needs_shipping was asked once, not in a loop  (1 calls)
[ok] download-only cart is digital
[ok] empty cart is not digital
```

یعنی از هزاران فراخوانی به **۱** رسید.

## ۲. رفع

```php
private static $resolving = false;

public static function is_digital() {
    if ( self::$resolving ) { return false; }   // فراخوانی تودرتو
    self::$resolving = true;
    try {
        return self::resolve_digital();
    } finally {
        self::$resolving = false;
    }
}
```

فراخوانی تودرتو «دیجیتال نیست» جواب می‌دهد و مقدار واقعی فیلتر رد می‌شود؛ فراخوانی بیرونی با جواب کامل تصمیم می‌گیرد.

## ۳. پیام «تحویل دیجیتال» روی سبد مختلط

همین باگ باعث می‌شد سبدی که **یک محصول فیزیکی** دارد هم دیجیتال تشخیص داده شود. با گارد، `needs_shipping()` مقدار درست را برمی‌گرداند و:

- سبد فقط دانلودی/مجازی → پیام دیجیتال نمایش داده می‌شود ✅
- سبد مختلط (فیزیکی + دانلودی) → فرم آدرس کامل، بدون پیام دیجیتال ✅

## ۴. دکمهٔ «ادامه و ورود به مرحله پرداخت»

روی سایت تو `#place_order` وجود نداشت. حالا:

1. اگر `#place_order` بود → همان را می‌زند.
2. وگرنه `form.checkout` یا `form[name="checkout"]` یا `.woocommerce-checkout form` را با `requestSubmit()` می‌فرستد.
3. اگر هیچ‌کدام نبود → **هیچ کاری نمی‌کند** (به‌جای رفرش خود صفحه) و در Console یک هشدار می‌گذارد.

`form.submit()` عمداً استفاده نشده چون اعتبارسنجی ووکامرس و بررسی روش پرداخت را دور می‌زند.

## ۵. تست در CI

`tests/qa/digital-checkout-sim.php` به مرحلهٔ «unit tests» در گردش‌کار اضافه شد؛ از این به بعد اگر کسی این گارد را بردارد، CI قرمز می‌شود.

## ۶. فایل‌های تغییر‌یافته

- `includes/class-hsw-checkout-digital.php` — گارد + `resolve_digital()`
- `assets/js/hsw-checkout.js` + `.min.js` — `placeOrder()` مقاوم‌تر
- `tests/qa/digital-checkout-sim.php` (جدید)
- `.github/workflows/ci.yml` — اجرای تست جدید
- نسخه، `readme.txt`، `CHANGELOG.md`، POT
