# HamiShop Elementor Widgets 1.11.164 - release notes

Release date: 2026-10-29

Priority 3 leftovers: two small defects that made the shop look unreliable.
(The empty homepage sections are Elementor content, not plugin output — see the
end of this note.)

## Fix: category counts disagreed between header and archive

`$term->count` only holds the products assigned to a term **itself**. The header
drawer and the desktop cascade menu printed that number, while the archive
sidebar (and the category page itself) includes subcategory products:

| Category | Header (before) | Archive (before) | Now |
|---|---|---|---|
| دستکش و ماسک | ۵ | ۷ | ۷ / ۷ |
| ابزار | ۲ | ۳ | ۳ / ۳ |
| شاخه مستقل | ۱ | ۲ | ۲ / ۲ |

- New `HSW_Header_Cascade::term_total_count()` returns the term plus every
  descendant from the cached taxonomy hierarchy (`get_term_children()`, no extra
  query per row).
- The mobile drawer (`drawer_categories_html()`), the cascade row and the
  archive sidebar now print the same number, so a shopper who taps «۷» really
  gets seven products.

## Fix: browser-default focus ring over plugin notices

WooCommerce focuses the notice it renders after a cart update (screen-reader
announcement). The browser then painted its own orange outline
(`rgb(229, 151, 0)`) on top of the full-width green box.

- `.hsw-notices` now styles `:focus` / `:focus-visible` for
  `woocommerce-message|info|error` with `2px solid` in that notice's own accent
  colour. The ring stays visible (it is a real focus indicator) but belongs to
  the palette.

## Validation run for 1.11.164

- `tests/header-counts-probe.js` (new): compares every category the header and
  the archive sidebar both show, then updates a cart line and asserts the notice
  ring — `RESULT: clean`.
- `tests/loop-layout-probe.js`, `tests/archive-filter-probe.js`,
  `tests/stage2-probe.js`: all still `RESULT: clean`.
- PHP lint: 122 files, 0 failures. `tests/harness.php`: 74 classes, clean.
- Manual check: drawer `{"دستکش و ماسک":"۷","ابزار":"۳","شاخه مستقل":"۲"}`,
  notice outline `solid 2px rgb(15, 159, 110)`, no console errors.

## Not a plugin defect: empty homepage sections

`/` has an Elementor section with a background image and an empty
`elementor-widget-wrap` (~400px tall), and the «خواندنی‌ها» section has a heading
with no posts widget inside. Both come from the page content itself
(Elementor editor → section / posts widget), not from the plugin; filling or
deleting those two sections in the editor completes the homepage.
