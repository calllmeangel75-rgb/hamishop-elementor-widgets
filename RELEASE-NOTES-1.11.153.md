# HamiShop Elementor Widgets 1.11.153 - release notes

## Summary

This release adds the desktop store-header cascade category menu and an optional gradient shine effect for the header logo. The cascade opens horizontally toward the left in RTL, supports one to three configurable category levels, and loads deeper columns and leaf products lazily instead of rendering the whole catalogue tree on every page.

## Changes in 1.11.153

### Cascade store-header category menu

- The root column is server-rendered with the page; child columns are fetched from `/hsw/v1/header-cascade` only when the visitor opens that branch.
- The menu supports 1, 2 or 3 category levels and configurable root category counts.
- Leaf products are optional. When disabled, the cascade ends on the category itself instead of opening a product panel.
- Product panels load 3, 4 or 6 visible products with lazy images and WooCommerce price/CFP markup.
- Columns are cached in the browser for the page lifetime and on the server for five minutes, keyed by the WooCommerce product version and locale.
- Keyboard support includes Enter/Space to open a branch, Escape to close the deepest column, focus transfer into newly opened columns, and focus return to the parent row.
- Outside clicks and hover grace periods close the menu without leaving stale `aria-expanded` states.

### Full menu styling controls

- Added Elementor style sections for the panel and columns, category rows, and product rows.
- Controls cover panel background/border/shadow/radius, column width and max height, header background/title/all link, category normal/hover/open colours, icon/count/chevron colours, row padding/radius/typography, product image size/radius, product row colours, product name/price typography, and empty-state colour.

### Logo shine

- Added an enable/disable gradient shine toggle to the shared brand controls.
- Shine colour and cycle duration are configurable in the header style section.
- The animation is a CSS pseudo-element sweep with no extra DOM node or request, and is disabled under `prefers-reduced-motion`.

### Compatibility and maintenance

- Kept the previous `/hsw/v1/header-products` endpoint and old mega-menu CSS for cached markup/older assets.
- Rebuilt all `.min.css` and `.min.js` assets.
- Updated the POT file and plugin version.
