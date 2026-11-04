# HamiShop Elementor Widgets 1.11.154 - release notes

Release date: 2026-10-28

## Changes in 1.11.154

- Added a multi-select **«انتخاب دسته‌بندی‌های سربرگ»** control to the store header widget. It lists every product category, including child and empty categories, and preserves the order in which terms are selected.
- When no category is selected, the header now falls back to **5 top-level shop categories** by default. The existing count control was renamed to «تعداد دسته‌بندی پیش‌فرض» and now includes a 5-category option; saved 4/6/8/10/12 values remain valid.
- Explicit selection wins over the automatic count. Selected empty categories are rendered with their zero count instead of being filtered out.
- The same selection is used by the mobile drawer category list, keeping desktop and mobile headers consistent.
- The cascade root column title is now «دسته‌بندی‌های فروشگاه».
- Fixed a PHP warning in the archive grid demo-card branch (`$img2_html` was only initialized in the live-product branch).
- Updated the plugin version, readme stable tag/changelog, translation template, and release archive metadata.

## Validation run for 1.11.154

- PHP lint passed for the modified PHP files.
- Unit checks for default fallback, explicit selection (including all eight requested categories overriding the default count), child-category selection, empty-category selection, option labels, and root HTML passed.
- Live Elementor data was temporarily switched to a five-category selection and verified on the desktop header and mobile drawer; the original Elementor data was then restored byte-for-byte.
- Default fallback verified: 5 categories (`Dry-run`, `شاخه مستقل`, `دارو`, `دستکش و ماسک`, `ابزار`).
- Cascade open/close, focus transfer, Escape handling, `aria-expanded` reset, and sticky-header busy state were rechecked after the change.
- Browser console and WordPress debug log checked; no HSW errors.
