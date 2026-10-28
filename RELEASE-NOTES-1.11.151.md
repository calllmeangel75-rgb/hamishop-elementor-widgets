# HamiShop Elementor Widgets 1.11.151 - release notes

## Summary

This release removes the duplicate «پالت رنگ ویجت» section from the Elementor Style tab of every HamiShop widget. HamiShop → پالت رنگی is now the single source for the general widget colours. Per-element colour controls such as the discount badge and buttons remain available.

## Changes in 1.11.151

### One colour source

- Removed the shared `elementor/element/after_section_end` palette injection from `HSW_Plugin`.
- Removed `palette_map()`, `ctrl_palette()`, `maybe_add_palette()` and the `$hsw_palette_added` guard from `HSW_Widget_Base`.
- Removed the now-unused palette overrides from the account, blog, cart and checkout bases, plus the archive palette controls.
- The palette section is gone from the header, product, archive, cart, checkout, pay, complete, blog, post, account and footer widget families.
- The global palette CSS still prints the same tokens (`--hsw-primary`, `--hsw-accent`, `--hsw-pal-*`, status colours and the font) on `html:root`.
- Per-element style controls (for example the discount/featured badge background and text colour, purchase buttons, cart rows and article cards) are unchanged and still override the local tokens.

### Existing templates

- Saved `pal_*` settings are **not deleted** from `_elementor_data`; they simply stop generating CSS because their controls are no longer registered.
- Elementor's generated post CSS is cleared once per plugin version. This prevents an old cached rule such as `--hsw-primary:#FFFFFF` from continuing to override the dashboard palette after the controls disappear.
- The clear runs alongside the existing per-version element-cache flush; it is a one-time cache regeneration, not a data reset.

### Docs and translation template

- Updated the admin palette note to explain that the dashboard palette is the only source and that per-element controls still win.
- Updated `README.md`, `WIDGETS-REFERENCE.md`, `WIDGET-GUIDE.md`, `STAGING-CHECKLIST.md` and `readme.txt`.
- Regenerated the POT after removing the palette-control strings.

## Validation run for 1.11.151

- PHP lint: all changed PHP files pass (`hamishop-elementor-widgets.php`, `class-hsw-plugin.php`, `class-hsw-widget-base.php`, `class-hsw-archive-base.php`, `class-hsw-blog-base.php`, `class-hsw-cart-base.php`, `class-hsw-checkout-base.php`, `class-hsw-palette.php`, `class-hsw-account-admin.php`, `class-hsw-widget-account.php`).
- Hook audit: `has_action( 'elementor/element/after_section_end', [ '\HSW_Widget_Base', 'maybe_add_palette' ] )` is false; `palette_map()`, `ctrl_palette()` and `maybe_add_palette()` no longer exist on the base class.
- Control registration audit on the live site: 15 registered HamiShop widget types, 4,330 controls, **zero** sections whose label contains «پالت رنگ» and **zero** control IDs starting with `pal_`. Key counts: product page 448 controls, header 263, archive page 285, cart page 315, footer sticky 240, account 271.
- Code search: no `palette_map`, `ctrl_palette`, `maybe_add_palette`, `sec_palette` or `pal_*` references remain under `includes/` or `widgets/`.
- POT: 3,079 unique entries, 0 duplicate msgids, `1.11.151` header. The exact label «پالت رنگ ویجت» is gone. «رنگ متن» and «رنگ حاشیه‌ها» remain only because they are still used by per-element controls.
- Live front-end checks:
  - Product page, shop/archive, cart and account load without console errors.
  - Global tokens resolve to the dashboard palette: primary `#4d85ff`, accent `#fc593a`, text `#191c1e`, border `#e1e2e4`, success `#0f9f6e`, warning `#b45309`, danger `#ba1a1a`.
  - The footer widget's computed `--hsw-primary` is `#4d85ff`, not the old saved white override.
  - Regenerated `post-13551.css` contains no palette variables; the two footer templates still contain their saved `pal_*` values in the database, but those values are inactive.
  - `debug.log` is 0 bytes after the checks.
- ZIP content and parity check: recorded in the delivery message after the archive is built.

## Notes

- Saved `pal_*` values, Elementor data and template content were not modified.
- A site that previously relied on a per-widget general palette override will now follow the dashboard palette. Re-apply any intentional differences with the remaining per-element controls.
- GitHub push remains pending until credentials are available.
