# HamiShop Elementor Widgets 1.11.149 - release notes

## Summary

This release turns the composite product page’s Style tab into a complete, per-section control surface. It also fixes the desktop in-cart quantity stepper width and adds low-stock presentation.

## Changes in 1.11.149

### Product page live-cart stepper

- In-cart stepper is 30% wide on desktop (>= 992px).
- Tablet (768–991.98px) keeps 100% width. On phone (< 768px) the visible control is the fixed sticky bar; its stepper fills the side slot (100%).
- A responsive `--hsw-stepper-w` control can override each breakpoint where the purchase box is visible.

### Detailed product-page Style sections

The composite product page now has sections that are hidden automatically when the matching content part is disabled:

- Gallery: card/frame box, image width, aspect ratio, fit, thumbnail size/gap/ring, badges, authentication chip, arrows.
- Title, rating and reviews: title typography/colour/alignment/max-width, meta, star size/colour, review link normal/hover, recommend text.
- Price: main/unit/old price, discount pill, divider, call-for-price.
- Stock and badges: in-stock, low-stock and out-of-stock pill colours, border, radius, padding; out-of-stock buy button.
- Description and information: panel box, text/links, specification table rows and review cards.
- Features/specs: desktop list and mobile cards, icons, typography, gaps.
- Variations: colour swatches, chips, selected/hover/disabled states.
- Quantity and add-to-cart: stepper width/height, quantity buttons, button normal/hover/focus/disabled/busy/added states, icon size, trust text.
- Messages: WooCommerce success/error/info notices and sticky-bar toast.
- Tabs: container, nav, tab normal/hover/active states, review cards.
- Related products: heading, responsive card width/gap, card box, media, name/price, CTA normal/hover, badge, arrows.
- Trust strip: box, icon, title/subtitle, dividers.

Every new control is empty by default. The plugin palette (`HamiShop` admin palette, printed as CSS variables on `html:root`) remains the default source. A filled per-widget control writes to the Elementor wrapper, so it overrides the palette only for that widget.

### Behaviour and markup

- `HSW_Helpers::wc_notices()` now prints and drains the WooCommerce notice queue at the top of the composite product page, so coupon/cart messages are not lost.
- Title stock pill receives `is-in-stock`, `is-oos` or `is-low-stock`.
- Low stock uses `wc_get_low_stock_amount()` and shows the remaining quantity.
- Shared notice styles use `--hsw-notice-*` variables while keeping the existing theme-reset rules.

## Validation run for 1.11.149

- PHP lint: all changed PHP files pass.
- Control registration audit: 1,155 controls, 0 duplicate IDs; the 11 content-dependent detail sections carry the expected `*_on` condition. Messages is unconditional because WooCommerce notices are always rendered.
- Minified asset rebuild: `hsw-fonts`, `hsw-product` and `hsw-composite` regenerated.
- POT: 3,090 unique entries, no duplicate msgids, 1.11.149 header. `msgfmt`/`polib` are not installed, so no compile check was possible.
- Live checks on product 13111 (simple) and 13169 (variable).
- Desktop 1200px: composite 623.875px, in-cart stepper 172.156px (27.59% of the outer box; 30% of the content box). Tablet 800px: stepper 667px of 717px (100% of the content box). Mobile 375px uses the sticky bar; its stepper fills 164px of the 164px side slot.
- One H1 on both products; no console errors; no failed network requests.
- Notices: success, error and info render inside `.hsw-notices`; error uses the danger token, success uses the success token, info uses `--hsw-primary`. The standard WooCommerce notice callback is skipped only on product pages that actually contain the composite; a footer fallback keeps the queue from being lost.
- Low stock: product 13111 with quantity 1 and the WooCommerce threshold produced `is-low-stock`, the text «تنها ۱ عدد باقی مانده», and the amber pill.
- Out of stock: the same product produced `is-oos`, the red pill and the muted disabled buy button.
- Per-widget override: setting `t_name_c` to `#ff0000` on the product template generated red H1/H2 colour, then the template was restored and the title returned to the palette colour.
- Test product stock data and template settings were restored after the checks.
- Packaged ZIP: 178 files under one top-level folder; extracted files match the repository and key files match the live plugin. No tests, tools, backups or temporary QA files are included.

## Notes

- Elementor Global Colors/Fonts are not automatically the plugin palette. The plugin palette remains the source of truth; a user can still choose an Elementor global colour inside any control.
- Lighthouse/PageSpeed claims remain environment-dependent; no fixed score is claimed.
- GitHub push remains pending until credentials are available.
