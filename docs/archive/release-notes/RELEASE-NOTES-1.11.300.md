# HamiShop Elementor Widgets — Release 1.11.300

**An opened notification is counted before the next page paints.**

## What was wrong

Clicking a notification navigated immediately and sent the read request with `keepalive`. On a busy
host the request took about two seconds, longer than the navigation: the destination page was
rendered from the old meta and the bell still showed the previous count. The customer opened a
notification and the badge did not change.

The first attempt at a fix appended `?hsw_read=<id>` to the link, but assigning `location.href` does
not cancel the anchor's own navigation, so the anchor's URL — without the marker — won and the
server never saw it.

## Fixed

- The notification link carries `hsw_read=<id>`, and `HSW_Account::mark_opened_notification()` runs on
  `template_redirect` (priority 5), so the destination request marks that notification read **before**
  the bell is printed. Marking an already-read item is a no-op, so the parameter is harmless on a
  reload.
- The click handler calls `event.preventDefault()` before navigating, so the marker survives.
- Notifications without a link still use the REST route and patch the badge in place.
- The order screen (HPOS) printed the invoice buttons twice — once from
  `woocommerce_admin_order_data_after_billing_address` and once from the legacy metabox that was also
  registered on the HPOS screen. The metabox is now registered only when orders live in posts.

## Verified

- Local staging (temporary plugin swap), a customer with two notifications (one unread):
  - open the notifications page: bell `۱`, one unread row.
  - click the row: the URL becomes `…/my-account/orders/?hsw_read=<id>`, and the destination page
    renders bell `0`. User meta confirms the item is `read`.
  - the bell dropdown does the same.
- Live site (WooCommerce 11.3 + HPOS, shop manager account): the two duplicate button groups are gone
  from the order screen; invoice and postal label still open with the expected content.

## Build

Version 1.11.300 in the header, `HSW_VERSION`, `readme.txt`, changelog and this note; `.min` rebuilt.
`tools/ci-checks.php` check **6f** guards both halves of the notification marker (26 checks).
