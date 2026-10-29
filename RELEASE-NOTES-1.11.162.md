# HamiShop Elementor Widgets 1.11.162 - release notes

Release date: 2026-10-29

The purchase flow is now verified end to end on the live test store, and three
display/data defects found by that test are fixed.

## Verified purchase flow

`/cart/` → `/checkout/` → `/payment/` → order placed through REST →
`/order-complete/?order=…&key=…`.

- Desktop (1440×1100) and mobile (390×844, paying from the sticky bar) both
  completed with **zero console errors and no failed request**:
  orders `13616` (desktop) and `13617` (mobile).
- Each order carries: status `در حال انجام`, the address typed on the checkout
  page (billing **and** shipping), the chosen shipping rate, the delivery
  day + slot (`_hsw_delivery_day`, `_hsw_delivery_slot`), the customer note,
  the gateway (`cod`) and `_hsw_placed_via = hamishop-pay`.
- The completion page renders the order number, status, date, items, totals and
  delivery block; `/my-account/orders/` and `/my-account/view-order/{id}/`
  render the same order.
- The header badge reported `1` after adding one item and `0` after the order,
  i.e. the cart is emptied by the flow.

## Fixes

### Thank-you page printed a Gregorian date
The completion hero used `wc_format_datetime()`, which always prints the
Gregorian calendar: «اکتبر 29, 2026» on a store whose account pages, tickets and
invoices are Jalali. New shared `HSW_Helpers::format_date()` resolves in three
steps — wp-parsidate's `parsidate()` when active, HamiShop's own opt-in Jalali
converter, otherwise the site locale — and always returns Persian digits.
`HSW_Account::format_date()` now delegates to it, so every date in the plugin
goes through one place.

### Account amounts printed Latin digits
The order list, order details, invoice table, wallet summary and wishlist used
raw `get_formatted_order_total()` / `wc_price()` output. They now pass through
`HSW_Helpers::price_html_fa()`, and `HSW_Account::localize_price_html()` uses the
same text-node-only converter (a blanket `fa_num()` would rewrite
`?add-to-cart=` attributes).

### Cart could survive a successful payment
`HSW_Checkout_Complete` relied on the gateway calling `WC()->cart->empty_cart()`
inside `process_payment()`. Most do, but an offline gateway that forgets would
leave the items in the cart after the redirect, and a refresh could place a
second order for the same items. The endpoint now empties the cart and clears
`order_awaiting_payment` itself once the gateway reports success.

## Validation run for 1.11.162

- PHP lint: 122 files, 0 failures. `tests/harness.php`: 74 classes, clean.
- `tests/checkout-flow-probe.js` (new):
  - desktop run → order `13616`, badge `1` → `0`, `ERRORS: none`;
  - `HSW_MOBILE=1` run (390×844, `.hsw-psum__cta--m`) → order `13617`,
    `ERRORS: none`;
  - the probe asserts the badge equals `1` after adding exactly one item, so a
    cart/counter regression fails the run.
- `debug.log` empty after both runs.
- Account audit (`/my-account/orders/`, `/my-account/view-order/13617/`,
  `/my-account/`): every `.amount`/total element reports **no ASCII digits**
  (`latinDigitAmounts: []`), e.g. `۱,۳۳۰,۷۵۸ تومان`.
- Completion page: `تاریخ ثبت ۷ آبان ۱۴۰۵، ۱۸:۰۷`, order `#۱۳۶۱۴`.
- Earlier probes still pass: `loop-layout-probe.js` (390/779/1440),
  `archive-filter-probe.js`, `stage2-probe.js`.

## Notes for the store owner

- Guest checkout stays disabled (store decision): `/checkout/` redirects to the
  account login and returns to checkout afterwards with the cart intact.
- The test orders `13614`–`13617` and the leftover cart items are test data;
  they can be deleted from WooCommerce → Orders before going live.
