# HamiShop 1.11.175 — آماده‌سازی لایو: کیف پول، مهاجرت دسته‌ای، بازگشت وجه

این نسخه مسیرهای مالی را برای انتشار روی سایت واقعی آماده می‌کند. هر مورد با
تست خودکار در `tests/unit` یا `tests/qa` همراه است.

## ۱) مهاجرت موجودی از `usermeta` به جدول `hsw_wallet`

`HSW_Wallet::migrate( $batch = 200, $force = false )`

- **دسته‌ای:** ۲۰۰ کاربر در هر مرحله؛ وضعیت در آپشن `hsw_wallet_migration`
  (`last_user`) ذخیره می‌شود، پس با timeout یا kill، اجرای بعدی از همان‌جا ادامه
  می‌دهد. `maybe_install()` هم آن را روی `init` صدا می‌زند.
- **idempotent:** فقط کاربری که در جدول موجودی **ردیف ندارد** درج می‌شود
  (`INSERT IGNORE`). اجرای دوباره (حتی `--force`) هیچ عددی را عوض نمی‌کند.
- **متای قدیمی دست‌نخورده می‌ماند** (برای rollback) و هر ردیف خام هم در جدول
  `{prefix}hsw_wallet_meta_backup` کپی می‌شود.
- **ردیف‌های تکراری:** مقادیر **جمع** می‌شوند (صورتحساب همان را نشان داده) و کاربر
  در گزارش با برچسب `duplicate-rows` / `duplicate-differing` و **مقادیر خام** لیست
  می‌شود؛ انتخاب بی‌صدا وجود ندارد. `error_log()` هم یک خط با تعداد موارد می‌نویسد.
- **مقادیر مشکل‌دار:** خالی (`empty`)، غیرعددی (`non-numeric`)، ارقام فارسی
  (`persian-digits`، نرمال و شمرده می‌شوند)، اعشاری (`decimal`).
- **خط افتتاحیه:** برای هر کاربر مهاجرت‌شده یک ردیف دفتر با
  `kind = migrate` و `ref = migrate:<user>` نوشته می‌شود تا «موجودی بدون گردش»
  به‌عنوان اختلاف گزارش نشود.

### ابزارها

```
wp hsw wallet migrate [--batch=200] [--force]
wp hsw wallet report      # جمع دفتر در مقابل موجودی هر کاربر
wp hsw wallet repair      # اختلاف‌ها را با یک ردیف دفتر (بدون جابه‌جایی پول) می‌بندد
```

همان منطق در PHP: `migrate()`, `migration_report()`, `balance_gaps()`,
`repair_gaps()`.

> `repair` فقط **ردیف دفتر** اضافه می‌کند و هرگز موجودی را تغییر نمی‌دهد. نسخهٔ
> اولیهٔ این تابع پول را جابه‌جا می‌کرد؛ روی دادهٔ تستی همین‌جا کشف و اصلاح شد.

## ۲) بازگشت وجه (وجود نداشت)

پرداخت با کیف پول، سفارش را بدهکار می‌کرد و **هیچ مسیری برای برگشت پول نبود**.
`HSW_Wallet_Refunds` اضافه شد:

- `woocommerce_order_status_cancelled` / `_refunded` → بازگشت باقی‌ماندهٔ پرداخت
  کیف پولی (`ref = refund:<order>:full`).
- `woocommerce_order_refunded` → هر ریفاند ووکامرس با
  `ref = refund:<order>:<refund_id>` و سقف «مبلغ پرداخت‌شده − قبلاً برگشته».
- مبلغ پرداخت‌شده در متای `_hsw_wallet_paid` ثبت می‌شود (نه از total قابل ویرایش).

## ۳) موارد باز قبلی

| مورد | رفع |
|---|---|
| TOCTOU در `request_topup` (ده کلیک = ده درخواست pending) | ردیف pending با `ref = pending:<user>` و ایندکس یکتا؛ `decide()` هنگام بستن، `ref` را NULL می‌کند |
| آینهٔ `_hsw_wallet_balance` | حذف شد (نوشتن و خواندن). متا فقط snapshot پیش از مهاجرت است؛ grep: هیچ کد افزونه‌ای آن را نمی‌خواند |
| `VALUES()` در `ON DUPLICATE KEY UPDATE` | حذف شد؛ الگوی `UPDATE` → `INSERT IGNORE` → `UPDATE` که هم MySQL 8.0.20+ و هم MariaDB را پوشش می‌دهد |
| امتیاز باشگاه | شمارنده از متا به ستون `points_used` جدول موجودی منتقل شد و با یک `UPDATE … WHERE points_used + n <= earned` اتمیک claim می‌شود |
| پرداخت تکراری سفارش با کیف پول | `ref = spend:<order>` + بررسی هر دو قالب قدیم/جدید؛ کلیک دوم همان موفقیت را برمی‌گرداند |

## ۴) تست‌ها

| فایل | چه چیزی |
|---|---|
| `tests/unit/run.php` | ۳۳ سنجه: ۷۳٬۴۱۳ روز تقویم جلالی، قاعدهٔ کبیسهٔ ۳۳ ساله، `number_words` |
| `tests/qa/migration-5000.php` | ۵۰۰۰ کاربر ساختگی + ۲۵۰ ردیف مشکل‌دار؛ ۲۱ سنجه: جمع‌ها، گزارش، پشتیبان، idempotency، resume، gap/repair |
| `tests/qa/wallet-paths.php` | ۲۵ سنجه موازی: ۱۰ درخواست شارژ، ۶ تأیید، دو کلیک پرداخت، ۵ لغو، ریفاند جزئی، ۳ تسویه، crash + repair |
| `tests/qa/rest-parallel.php` | ۸ سنجه از طریق endpoint واقعی REST (topup و convert) |
| `tests/qa/sql-compat.php` | همان دستورهای SQL روی یک سرور مستقل (MariaDB محلی، MySQL 8 در CI) |
| `tests/qa/hpos.php` | روشن/خاموش کردن HPOS با بکاپ آپشن‌ها برای اجرای تست |

## ۵) نتیجهٔ اجرا (این نسخه)

```
migration-5000 : PASS=21 FAIL=0   (5003 کاربر، ۱۴.۷۹ ثانیه، ۲۰۰ نفر در هر دسته)
wallet-paths   : PASS=25 FAIL=0
rest-parallel  : PASS=8  FAIL=0
sql-compat     : PASS=12 FAIL=0   (MariaDB 10.4.32)
unit           : 33/33
HPOS روشن      : wallet-paths 25/25 · rest 8/8
```

## ۶) برنامهٔ لایو (خلاصه)

1. **بکاپ:** دیتابیس کامل + پوشهٔ `wp-content/uploads` + فایل افزونهٔ نسخهٔ فعلی.
2. **فایل‌ها:** آپلود ZIP 1.11.175 (فقط کد؛ جدول‌ها ساخته می‌شوند).
3. **مهاجرت:** با اولین بازدید ادمین/`init` خودکار اجرا می‌شود؛ برای سایت بزرگ‌تر
   از چند هزار کاربر: `wp hsw wallet migrate --batch=200`.
4. **تأیید:** `wp hsw wallet report` باید «همه برابر» بدهد؛
   `SELECT COUNT(*) FROM wp_hsw_wallet` و جمع آن را با خروجی مهاجرت
   (`before` = `after`) مقایسه کنید. آپشن `hsw_wallet_migration` فهرست موارد مشکوک
   را دارد.
5. **آزمون دود:** یک کاربر تستی، شارژ ۵۰٬۰۰۰ → تأیید ادمین → پرداخت سفارش →
   لغو سفارش (باید برگردد) → گزارش اختلاف.
6. **برگشت:** `DELETE FROM wp_hsw_wallet` (متا دست‌نخورده است؛ نسخهٔ قبلی دوباره
   از متا می‌خواند) و بازگرداندن فایل‌های نسخهٔ قبل. جدول `hsw_wallet_ledger` و
   `hsw_wallet_meta_backup` را پاک نکنید؛ سابقهٔ مالی‌اند.
