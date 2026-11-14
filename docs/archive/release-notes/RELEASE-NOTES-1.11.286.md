# HamiShop Elementor Widgets — Release 1.11.286

**Removed the «پیشنهاد شگفت‌انگیز (HamiShop)» widget** — `hsw-wonder-deals` is no
longer registered, shipped or styled.

## Removed

- `widgets/class-hsw-wonder-deals.php`
- `assets/css/hsw-loop-deal.css` and `.min.css`
- the `hsw-loop-deal` asset registration in `HSW_Assets`
- the editor-only `.elementor-widget-hsw-wonder-deals` rule
- the widget catalog entry in `HSW_Widget_Manager`
- the file/class entries in `HSW_Plugin::register_widgets()`
- the REST `deal` loop type and `hsw-wonder-deals` type mapping
- the obsolete lead-button browser probe and references in the loop button test
- the wonder-deals-specific lead-button CSS from `hsw-loop.css`

## Kept unchanged

- `hsw-best-sellers`
- `hsw-popular`
- `hsw-offer`
- shared loop card/rail/timer engine and every other widget

## Migration

Pages that still contain the removed widget will show Elementor's missing-widget
placeholder. Replace it with «پرفروش‌ترین‌ها»، «محبوب‌ترین‌ها / منتخب» or
«آفر حامی‌شاپ».

## Counts

Widget count is now **63** (16 general + 47 parts). README, readme.txt, WIDGETS,
ARCHITECTURE and MARKETING were updated together; the widget-docs generator also
now emits 63/16.

## Build

- Version 1.11.286 in the plugin header, `HSW_VERSION`, `readme.txt` Stable tag,
  changelog and this note.
- ZIP built with `tools/build-release.py`, committed under `release-downloads/`,
  annotated tag `v1.11.286`, and attached to the GitHub release.
