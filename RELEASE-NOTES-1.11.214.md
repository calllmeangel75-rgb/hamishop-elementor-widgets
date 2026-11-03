# HamiShop Elementor Widgets v1.11.214

## رفع باگ‌ها
- **رفع خطای رندر ویجت صفحهٔ کامل وبلاگ (`HSW_Widget_Blog_Page`):**
  - اضافه شدن متد کمکی `opt()` به کلاس پایهٔ `HSW_Widget_Base` برای دسترسی یکپارچه در تمام خانواده‌های ویجت و رفع خطای `Call to undefined method HSW_Widget_Blog_Page::opt()`.
  - اصلاح بررسی وضعیت کلید `bgrid_show_cat_desc` در `HSW_Widget_Blog_Page::render()` از طریق `effective_settings()` تا خاموش یا روشن کردن نمایش توضیحات دسته‌بندی در پایین آرشیو وبلاگ به‌درستی عمل کند.
