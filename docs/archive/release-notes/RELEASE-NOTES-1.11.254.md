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

---

## ۶. تست در CI (بعد از انتشار ۲۵۴)

`wp-integration` به workflow اضافه شد: سرویس MySQL 8، نصب واقعی وردپرس + ووکامرس + المنتور با wp-cli، اتصال افزونه، و اجرای تست.

```
gh run 42162556643 — 14 job
  success  WordPress + WooCommerce (integration)
  PASS  first save of the switch bumps the version
  … ۸ بررسی، همه PASS
```

## ۷. دقت نام hash

نام `session key` گمراه‌کننده بود. تابع `WC_Shipping::get_package_hash()` **private** است و نشست CLI اصلاً چیزی ذخیره نمی‌کند. تست به `derived cache key` تغییر نام داد و در docblock نوشت: «همان فرمول روی همان ورودی‌ها؛ ثابت می‌کند نسخه بخشی از کلید است، نه اینکه ووکامرس نوشته است.»

## ۸. تست نشست واقعی

یک نشست مرورگری، بدون تغییر سبد، با تغییر option از داخل همان صفحه:

| مرحله | برچسب نرخ | ردیف خلاصه | shipping_total |
|---|---|---|---|
| خاموش (option حذف‌شده) | «نرخ ثابت ۲۵,۰۰۰» | «رایگان» | 0 |
| اولین فعال‌سازی (add_option) | «ارسال رایگان» | «رایگان» | 0 |
| خاموش | «نرخ ثابت ۲۵,۰۰۰» | «رایگان» | 0 |
| روشن، سقف بالاتر از سبد | «نرخ ثابت ۲۵,۰۰۰» | «رایگان» | 0 |
| روشن، سقف پایین | «ارسال رایگان» | «رایگان» | 0 |

**فهرست نرخ‌ها درست به‌روز می‌شود؛ جمع سبد در همان رندر عقب می‌ماند.** با `calculate_totals()` صریح، وضعیت هم‌سان می‌شود: `shipping_total=25000`, `cart_total=146000` و ردیف خلاصه هم ۲۵,۰۰۰ نشان می‌دهد.

**همین عقب‌ماندگی با تغییر خالص ووکامرس هم دیده شد:** تغییر هزینهٔ نرخ ثابت به ۳۰,۰۰۰ (بدون دخالت افزونه) نه برچسب را عوض کرد نه خلاصه را — چون انتخاب مشتری (`flat_rate:1`) ثابت مانده و هش سبد تغییر نمی‌کند. پس این کش جمع سبد ووکامرس است، نه چیزی که کلید افزونه ساخته باشد.

**تست‌نشده:** مالیات (فروشگاه `woocommerce_calc_taxes = no` دارد؛ برای تست باید روشن شود)، پرداخت واقعی، و دو نشست هم‌زمان.
