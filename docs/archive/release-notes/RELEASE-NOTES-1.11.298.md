# HamiShop Elementor Widgets — Release 1.11.298

**The postal label is a shop document, not part of the customer invoice.**

## What changed

The invoice sheet used to end with a «مشخصات مرسولهٔ پستی» block: sender and recipient
boxes, the ten-box postcode, item count and weight. The same sheet was printed by the customer from
«سفارش‌های من» and by the shop from the order screen — so a customer printing a receipt also got a
shipping label, and the label carried the invoice's items and totals.

Now there are two documents behind the same nonce-protected route:

| Who | Where | Button | Sheet |
| --- | --- | --- | --- |
| Customer | «سفارش‌های من» → order view | «چاپ فاکتور» | invoice only, no postal chart |
| Shop manager | order screen (HPOS and legacy) | «چاپ فاکتور» | invoice only |
| Shop manager | order screen (HPOS and legacy) | «برچسب پستی» | the label alone: brand, «برچسب پستی», sender, recipient, postcode, weight |

`HSW_Invoice::url()` takes a third argument (`invoice` / `postal`); the route reads `part`.
`authorize()` rejects `part=postal` unless the caller can `edit_shop_orders` or
`manage_woocommerce`, so editing the URL does not hand a customer the label.

## Verified (staging, temporary plugin swap)

- Customer order view: link text «چاپ فاکتور», URL carries `part=invoice`; opening it returns 200 with
  items and totals and **no** `.hsw-inv__postal`. Swapping `part=postal` returns **403** with the
  «برای مشاهدهٔ این فاکتور دسترسی ندارید» page.
- Order screen buttons via `hpos_button()`: «چاپ فاکتور» → `part=invoice`, «برچسب پستی» →
  `part=postal`.
- `document( $order, 'invoice' )` has items and totals and no postal block;
  `document( $order, 'postal' )` has the postal block and no items/totals, title «برچسب پستی».
- Postcode boxes still `dir="ltr"` on the label, so `1234567899` reads left to right.

## Site-wide smoke pass (staging `/sks`)

Desktop 1440 and mobile 390: home, shop archive, simple and variable product, cart, checkout,
single post, category archive, search, 404, and every account endpoint (dashboard, orders,
downloads, addresses, wishlist, wallet, support, notifications, payment methods, account details)
— all HTTP 200, no horizontal overflow, no JavaScript errors from the plugin. The only failing
request on the site is `plugins/rasad-main/collect.php` (a third-party plugin), unrelated to this
release.

## Build

Version 1.11.298 in the plugin header, `HSW_VERSION`, `readme.txt` Stable tag, changelog and this
note. Assets and POT rebuilt; ZIP from `tools/build-release.py`.
