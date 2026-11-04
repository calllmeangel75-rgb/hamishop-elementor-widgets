# یادداشت‌های انتشار نگارش 1.11.240 (Release Notes v1.11.240)

> **موضوع:** «فونت پایه ویجت» که در همهٔ ویجت‌ها دیده می‌شد ولی فقط در چهار ویجت کار می‌کرد، به بقیه هم وصل شد. خروجی صفحات با تنظیمات پیش‌فرض بایت‌به‌بایت یکسان ماند — اندازه‌گیری شده، نه فرض.

## ایراد: کنترلی که در ۹۰٪ ویجت‌ها هیچ کاری نمی‌کرد

بخش «سازگاری با قالب (فونت)» از طریق `elementor/element/after_section_end` به **همهٔ** ویجت‌های HamiShop اضافه می‌شود (`HSW_Assets::append_common_section`). چهار گزینه دارد: پالت، طرح، سفارشی، ارث‌بری.

اما کلاسی که این گزینه را اجرا می‌کند فقط در **چهار** ویجت صدا زده می‌شد:

```
widgets/class-hsw-best-sellers.php
widgets/class-hsw-popular.php
widgets/class-hsw-widget-post-page.php
widgets/class-hsw-wonder-deals.php
```

در بقیه — حساب کاربری، سبد، تسویه، هدر، آرشیو، محصول، بلاگ، فوتر — انتخاب ادمین بی‌اثر بود.

## رفع

یک قلاب مرکزی روی `elementor/frontend/widget/before_render` که کلاس را روی **wrapper** خود ویجت می‌گذارد. wrapper تنها عنصری است که همهٔ ویجت‌ها مشترک دارند.

```php
public function apply_font_mode( $widget ) {
    $mode = (string) $widget->get_settings_for_display( 'hsw_font_mode' );
    if ( 'inherit' === $mode ) { $widget->add_render_attribute( '_wrapper', 'class', 'hsw-font-inherit' ); }
    elseif ( 'design' === $mode ) { $widget->add_render_attribute( '_wrapper', 'class', 'hsw-font-design' ); }
}
```

`palette` (پیش‌فرض) هیچ کلاسی اضافه نمی‌کند.

## اثبات ۱ — با تنظیمات پیش‌فرض، خروجی عوض نمی‌شود

HTML سه صفحه پیش و پس از تغییر گرفته و مقایسه شد (nonce، نسخهٔ فایل و timestamp کش نرمال شد):

```
home           IDENTICAL ✓
shop           IDENTICAL ✓
my-account     IDENTICAL ✓
```

## اثبات ۲ — گزینه حالا واقعاً کار می‌کند

روی `localhost/sks` ویجت `hsw-account` (که هرگز این کلاس را نمی‌داد) روی «فونت طرح» تنظیم شد:

```html
<div class="hsw-font-design elementor-element elementor-element-f660bb8 elementor-widget-hsw-account" …>
```

پیش از تغییر، همین ویجت هیچ کلاسی نمی‌گرفت.

## همچنین

- **فیلتر خروجی برای Google Fonts:** `filter_google_fonts()` روی آرشیو و حساب، چاپ فونت‌های Google المنتور را قطع می‌کند. اگر عنصری در همان صفحه فونت Google داشته باشد، به فونت سیستم برمی‌گردد. رفتار پیش‌فرض عوض نشد؛ فقط راه خروج گذاشته شد:
  ```php
  add_filter( 'hsw_disable_elementor_google_fonts', '__return_false' );
  ```
- **تصحیح مستندات `preload_font()`:** کامنت می‌گفت فونت فقط وقتی preload می‌شود که stylesheet واقعاً enqueue شده باشد، «پس صفحهٔ بدون ویجت چیزی دانلود نمی‌کند». این درست نیست: در زمان `wp_head` هنوز هیچ ویجتی رندر نشده، و `hsw-fonts` روی هر درخواست front-end register می‌شود — یعنی فونت عملاً روی همهٔ صفحات preload می‌شود. متن اصلاح شد. **رفتار عوض نشد**، چون سخت‌گیرکردنش یعنی حدس‌زدن دربارهٔ اینکه صفحه ویجت دارد یا نه، و حدس اشتباه همان layout-shift ای را برمی‌گرداند که این preload برای جلوگیری از آن نوشته شده.

## آنچه عمداً انجام نشد

| مورد | دلیل |
|---|---|
| فونت پنل حساب از `--hsw-font` بخواند | روی هر دو سایت شما پالت فونت وزیرمتن نیست (`iryekan` و `peyda`). این تغییر ظاهر پنل حساب را عوض می‌کند؛ باید خودتان بخواهید |
| حذف `!important` از حالت‌های فونت | کامنت خود کد می‌گوید بدون آن، گزینه بی‌اثر می‌شود. نیاز به بازطراحی specificity دارد، نه حذف |
| سخت‌گیرکردن preload فونت | ریسک برگشتن CLS؛ توضیح بالا |
| رنگ‌های ثابت بلاگ و تسویه | هیچ نقص تأییدشده‌ای ندارد؛ تغییر کورکورانهٔ رنگ = ریسک ظاهری |

## نصب

فایل `hamishop-elementor-widgets-1.11.240.zip` را از بخش Assets بردارید و از پیشخوان ← افزونه‌ها ← افزودن ← بارگذاری افزونه نصب کنید.
