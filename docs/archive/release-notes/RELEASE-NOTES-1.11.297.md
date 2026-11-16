# HamiShop Elementor Widgets — Release 1.11.297

**Invoice digits, notification reads, article breadcrumb.**

## Fixed

### Invoice: the postal code printed backwards
The recipient's postal code is drawn as ten boxes. A `flex` row inherits the RTL direction, so the
first digit sat on the right and a clerk reading left-to-right saw `1234567899` as `9987654321`.
The strip now carries `dir="ltr"` and `direction: ltr`, so the boxes fill left → right.

### Notifications: clicking one did not clear it
- Only «خواندن همه» existed. Opening a single notification navigated away and left the dot, the bell
  count and the sidebar badge untouched.
- The list markup now carries `data-hsw-notif="<id>"`, the REST route accepts an optional `id` and
  marks only that row, and the click handler fires a `keepalive` request and navigates immediately
  (the endpoint takes ~2 s on a shared host; the customer should not wait for a badge).
- The notifications page no longer marks **everything** read after rendering. That silent write was
  why the page showed «۲ خوانده‌نشده» over notifications the server had already cleared — and why the
  bell kept its count until a reload. Reads are now: open one (that one), or press «خواندن همه».

### Articles: a «مجله» crumb that led to the shop front
A site without a «posts page» has no magazine index; `blog_url()` falls back to the home page, so the
breadcrumb read «خانه › مجله › دسته» with two steps pointing at `/`. The «مجله» step is now printed
only when it leads somewhere else.

## Verified (staging install, temporary plugin swap)

- Invoice for a real order with postcode `0123456789`: ten boxes, left edges ascending, reading
  `۰۱۲۳۴۵۶۷۸۹`; screenshot checked.
- Notifications: a test customer with two unread items. Before: bell `۲`, sidebar `۲`, pill
  «۲ خوانده‌نشده». Clicking the first item navigated to the orders page and the next page showed bell
  `۱`, sidebar `۱`; user-meta showed item 1 `read`, item 2 still unread. The REST response was
  `{"ok":true,"unread":1}`.
- Article page (desktop 1440 and mobile 390): the table of contents scrolls to the heading and marks
  it active, breadcrumb now «خانه › راهنمای خرید», no console errors, no horizontal overflow.

## Build

- Version 1.11.297 in the plugin header, `HSW_VERSION`, `readme.txt` Stable tag, changelog and this
  note. `.min` assets and the POT rebuilt; ZIP built with `tools/build-release.py`.
