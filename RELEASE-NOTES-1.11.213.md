# HamiShop Elementor Widgets v1.11.213

## بهبودها و اصلاحات
- **حفظ ساختار ۱۰۰٪ استاندارد وردپرس در فرم‌های دیدگاه و نظرات (`wp-comments-post.php`) همراه با ارسال پیش‌روندهٔ ایجکسی (Progressive AJAX):**
  - در سورس HTML، ویژگی `action` تگ `<form>` در ویجت تک‌نوشته (`HSW_Widget_Post_Page::render_comments()`) و فرم دیدگاه محصول (`HSW_Product_Base::review_form_html()`) روی همان مسیر استاندارد و بومی وردپرس (`wp-comments-post.php`) باقی می‌ماند تا با تمام افزونه‌های ضداسپم، کپچا و هوک‌های استاندارد وردپرس/ووکامرس صددرصد سازگار باشد.
  - در سمت مرورگر، جاوااسکریپت (`hsw-post.js` و `hsw-product.js`) به‌صورت Progressive Enhancement دیدگاه را از طریق `admin-ajax.php` (`hsw_submit_comment`) ثبت می‌کند تا کاربر بدون رفرش صفحه و بدون برخورد با خطای `403 Forbidden` فایروال وب‌سرور (ModSecurity / Apache) روی `/wp-comments-post.php`، پیام تایید را درجا دریافت کند.
