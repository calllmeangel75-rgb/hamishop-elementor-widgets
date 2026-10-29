# HamiShop Elementor Widgets 1.11.161 - release notes

Release date: 2026-10-29

Four defects found by the live audit of the storefront are fixed here: two of
them changed what a shopper actually received (filtered results, working
links), one broke taps on mobile and one made prices look inconsistent.

## Fixes

### Archive filters submitted every field twice
- The filter form carries two copies of each control — the desktop sidebar
  (`.hsw-af__side`) and the mobile sheet (`.hsw-asheet--filter`) — inside one
  `<form>`. `pruneHiddenCopy()` disabled the copy that was not visible, but a
  later pass re-enabled every *checked* checkbox, which resurrected the hidden
  copy. Every name was then submitted twice and PHP kept the last value, i.e.
  the stale sheet value.
- The pruned copy is now marked (`data-hsw-pruned`) and skipped by the later
  checkbox passes, so exactly one copy submits.
- Clearing every filter no longer leaves `shop/?` in the address bar.

### WooCommerce price markup printed Latin digits
- `wc_price()`, `get_cart_subtotal()` and `get_price_html()` output Latin
  digits, while every HamiShop component prints Persian ones. The header cart
  drawer, related products, the header cascade menu and the post-add sheet were
  therefore mixed within the same viewport.
- New `HSW_Helpers::price_html_fa()` converts digits in text nodes only —
  a blanket `fa_num()` would have rewritten `?add-to-cart=13107` inside an
  attribute and broken the link.
- `hsw-feedback.js` converts the mini-cart fragment it parses for the dialog.

### Mobile menu drawer was covered by the chat FAB
- `.hsw-hd__drawer` lives inside the fixed header, so its `z-index: 10000` is
  trapped in the header stacking context; a third-party chat button at `99998`
  covered `.hsw-hd__drawercart` and swallowed the tap.
- Opening either the menu drawer or the cart drawer now lifts the header to
  `100050` (JS adds `is-drawer-open`), kept through the closing animation, and
  the cart drawer keeps the same raised layer.

### Mobile shortcut bar items were dead links
- The saved rows carried the placeholder `#` (or no URL at all), so all five
  tabs on the bar did nothing.
- The renderer now falls back to the default shortcut for that position
  (home, shop, cart, wishlist, account) whenever the saved URL is empty or `#`.

## Validation run for 1.11.161

- PHP lint: 122 files, 0 failures. `tests/harness.php`: 74 classes, clean.
- `tests/archive-filter-probe.js` (new), against `/shop/`:
  - desktop: toggling «فقط کالاهای موجود» → `?instock=1` (one occurrence), off → `/shop/`, category checkbox → exactly one `filter_*` parameter with no repeated value.
  - mobile: opening the sheet, toggling the same switch and pressing «اعمال فیلترها» → `?instock=1` (one occurrence).
  - before the fix the same probe produced `?instock=1&instock=1`.
- `tests/stage2-probe.js` (new):
  - header `z-index` while the menu drawer is open is `100050`, the chat FAB stays at `99998`, and `elementFromPoint` over the drawer's cart CTA returns the drawer (not the FAB).
  - cart drawer subtotal renders `۸۲۲,۲۳۹ تومان` (no ASCII digits).
  - related products render `۱۲۱,۰۰۰ تومان` (no ASCII digits).
- `debug.log` stays empty after the homepage, `/shop/`, the loop test page, a product page and `/cart/`.
- `tests/loop-layout-probe.js` still reports `RESULT: clean` at 390/779/1440.

## Known limitation

- The hero/footer content of the demo homepage (an empty Elementor section and
  a «خواندنی‌ها» heading without posts) belongs to the page content, not to the
  plugin; it needs to be filled in the Elementor editor.
