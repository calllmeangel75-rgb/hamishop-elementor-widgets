# انتشار ۱.۱۱.۲۵۴ — اولین ذخیرهٔ ارسال رایگان

تاریخ: ۲۰۲۶-۱۱-۰۶

---

## ۱. ادعا درست بود

`add_option()` و `update_option()` دو hook جدا دارند. در ۲۵۳ فقط `update_option_{$option}` وصل شده بود، که تنها وقتی اجرا می‌شود که option **از قبل وجود داشته باشد** و مقدارش عوض شود. اولین ذخیرهٔ یک option که هنوز ساخته نشده از مسیر `add_option()` می‌رود.

بازتولید با مسیر واقعی وردپرس، قبل از رفع:

```
option حذف‌شده، اولین update_option( 'hsw_free_shipping_on', 'yes' )
  hookها      ["add:on"]
  نسخه bump    false      ← شکاف

option موجود، 'yes' → 'no'
  hookها      ["update:on"]
  نسخه bump    true
```

## ۲. رفع

هر دو hook برای هر دو option ثبت می‌شوند:

```php
add_action( 'add_option_' . $hsw_option, … );
add_action( 'update_option_' . $hsw_option, … );
```

## ۳. تست ماندگار در مخزن

`tests/qa/free-shipping-cache.php` — هشت بررسی، روی نصب واقعی وردپرس + ووکامرس:

```
HSW_WP_LOAD=/path/to/wp-load.php php tests/qa/free-shipping-cache.php
```

```
PASS  first save of the switch bumps the version
PASS  first save of the threshold bumps the version
PASS  switch on → off bumps the version
PASS  switch off → on bumps the version
PASS  threshold change bumps the version
PASS  unchanged save does not bump the version
PASS  the session cache key changes for the same package
PASS  a different package has a different key
```

**اعتبار تست:** با برداشتن hook `add_option_*`، دقیقاً همان دو بررسی اول fail می‌شوند — پس تست واقعاً همان شکاف را می‌گیرد، نه فقط تابع را صدا می‌زند.

## ۴. آنچه این تست پوشش نمی‌دهد

- **نشست مرورگری واقعی** با نرخ ذخیره‌شده و انتخاب‌شده: تست کلید کش را می‌سازد، ولی مرورگر و `WC()->session` واقعی اجرا نمی‌شود.
- **مالیات و نرخ انتخاب‌شده**: بعد از bump، انتخاب مشتری حفظ می‌شود ولی این رفتار در تست اندازه‌گیری نشده.
- **نشست دوم**: کلید مشترک است، پس هر دو نشست با یک bump باطل می‌شوند؛ ولی دو نشست هم‌زمان شبیه‌سازی نشد.

## ۵. یادآوری دربارهٔ پنل ویجت‌ها

سه خط اضافه‌شده در ۲۵۳ شفاف‌سازی رابط است، نه تغییر رفتار: توضیح می‌دهد «خاموش» چه معنایی دارد، ولی ثابت نمی‌کند صفحه‌ای که از ویجت خاموش‌شده استفاده کرده سالم می‌ماند. آن تست هنوز انجام نشده.
