# HamiShop Elementor Widgets 1.11.148 - release notes

This is a manual, client-ready release package. It contains the plugin, its
front-end assets, the bundled Vazirmatn subset, the GPL license, the POT
translation template and the updated documentation. The license server/updater
is intentionally **not** part of this stage.

## Included in this release

- 62 Elementor widgets, including the composite page, account, cart, checkout,
  payment, blog and archive widgets.
- Global palette and per-widget style kit, with wrapper-level overrides taking
  precedence over the global `html:root` tokens.
- Branding, custom login appearance, security options, request blocker, wallet
  and account/support features already present in the plugin.
- `LICENSE` (GPL-2.0) and `languages/hamishop-elementor-widgets.pot`.

## Changes in 1.11.148

### Header cart sidebar

- The cart icon in both the store header and the minimal header now opens an
  accessible off-canvas cart instead of navigating away.
- The sidebar shows line items, thumbnails, variation/meta text, quantity
  controls, remove actions, the cart subtotal, «مشاهده سبد خرید» and
  «صورت‌حساب», plus a designed empty state.
- It closes on the overlay, the close button or Escape; it traps focus while
  open, restores focus on close, and locks body scrolling.
- Cart body and count are registered as WooCommerce fragments, so AJAX add,
  quantity and remove actions refresh the sidebar without a reload.
- Drawer quantity/remove actions notify the product purchase widget, keeping
  its CTA and quantity stepper in sync when the last line is removed.

### Product purchase and variations

- On desktop, adding a product to the cart now hides «خرید آنلاین» and reveals
  the existing +1/delete stepper, matching the mobile purchase flow.
- Simple products no longer render a nonfunctional colour picker. Their colour
  attribute remains visible in the technical specifications table.
- Variable products only render a picker for attributes that are actually used
  to build variations; non-variation attributes stay in the specs table.

### Product features

- «ویژگی‌های شاخص» now merges product attributes with labelled fields from the
  short description instead of stopping at the first source.
- Up to six unique rows are rendered, deduplicated by normalized label and by
  value, so «جنس» and «کشور تولید کننده» cannot be printed twice.

### Documentation

- The absolute Lighthouse 100 claims were replaced with a realistic statement:
  final scores depend on the theme, content, server and other plugins. The
  plugin only keeps its own CSS/JS local, conditional and framework-free.

## Validation run for 1.11.148

- Product 13111 (simple): five unique feature rows, no fake swatches, colour in
  specs, add-to-cart, desktop CTA-to-stepper swap, drawer update, quantity
  change, removal and product-state sync.
- Product 13169 (variable): colour swatches, red variation price change,
  variable add-to-cart, drawer variation metadata and removal sync.
- Minimal header rendered outside the editor with an empty and a populated
  cart; both include the shared drawer and the checkout/view-cart actions.
- Cart and checkout pages: drawer presence, populated contents, links, close
  behaviour and scroll lock.
- Full PHP lint and the project test harness pass; no browser console errors
  were reported during the final checks.

## Required site configuration before enabling these features

- **SMTP:** configure and test SMTP before enabling OTP login, 2FA, email
  verification or support email notifications. Password login is enabled and
  works without SMTP.
- **Wallet funding:** the site currently has no enabled non-COD payment
  gateway. Enable a real online gateway for the wallet top-up gateway mode, or
  configure card-to-card details in the account settings. COD is intentionally
  rejected for wallet funding.
- **License/updater:** deferred to the final stage as requested.
