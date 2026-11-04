# HamiShop Elementor Widgets v1.11.212

## تغییرات و بهبودهای نسخه ۱.۱۱.۲۱۲

1. **رفع خطای `403 Forbidden` در ثبت نظر مقالات و دیدگاه محصولات (`wp-comments-post.php`):**
   - ثبت دیدگاه در مقالات (`.hsw-pp__form`) و ثبت نظر و امتیاز در صفحه محصول تکی (`.hsw-review-form`) اکنون به‌صورت ایجکسی (`admin-ajax.php?action=hsw_submit_comment`) و بدون رفرش یا هدایت به آدرس مسدودشدهٔ `wp-comments-post.php` انجام می‌شود (همراه با fallback روی `admin-post.php` برای حالت بدون جاوااسکریپت).
   - نمایش پیام موفقیت یا خطا به‌صورت درجا در فرم برای کاربران مهمان و مشتریان واردشده، به همراه ذخیرهٔ دقیق امتیاز ستاره‌ای محصول و کوکی‌های استاندارد وردپرس.

2. **رفع تغییر رنگ دکمه‌های بخش نظرات در هاور (Hello Elementor `#c36`):**
   - خنثی‌سازی کامل استایل `[type=submit]:hover, [type=button]:hover { background-color: #c36 }` قالب Hello Elementor روی دکمه‌های `.hsw-pp__submit`، `.hsw-pp__seclink`، `.hsw-pp__iconbtn` و `.hsw-review-submit`.

3. **اصلاح آیکن سبد خرید در گرید آرشیو محصولات:**
   - جایگزینی آیکن `add_shopping_cart` با آیکن خطی، ظریف و اختصاصی `shopping_cart` در تمامی دکمه‌های `.hsw-ag__cta.hsw-d` و `.hsw-ag__cta--m.hsw-m` (و بازنشانی ایجکس در `hsw-archive.js`).
