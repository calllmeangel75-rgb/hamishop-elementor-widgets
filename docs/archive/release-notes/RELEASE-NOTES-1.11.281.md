# HamiShop Elementor Widgets — Release 1.11.281

**New standalone campaign widget** — «آفر حامی‌شاپ» (`hsw-offer`) implements the
red campaign design independently of the existing loop engine.

## Added

- **`HSW_Widget_Offer` / `hsw-offer`:**
  - campaign lead card: badge, two-line title, subtitle, gift icon, CTA;
  - campaign countdown and a compact countdown on each product card;
  - up to **8** products per widget;
  - automatic source of sale products, or manual product IDs (max 8);
  - discount percentage from the **lowest variation** regular/price pair;
  - minimum discount filter;
  - sorting by discount, nearest sale end, newest, best-selling or random;
  - stock/sold meter from managed stock plus total sales, low-stock labels and
    real-signal top badges;
  - RTL-first horizontal rail with previous/next controls;
  - page-cache-safe timers: PHP prints only the end timestamp, the browser
    computes the remaining time (Persian digits);
  - one timer interval for all instances; Elementor editor re-init supported.
- New assets: `assets/css/hsw-offer.css` (+ `.min.css`) and
  `assets/js/hsw-offer.js` (+ `.min.js`). The widget declares its own
  `get_style_depends()` / `get_script_depends()`, so pages without it do not
  download them.
- Registered in `HSW_Widget_Manager`, `HSW_Plugin::register_widgets()`, the
  asset registry, the catalog and the docs.
- **Isolation:** the new sheet does not import or override `hsw-loop.css`,
  `hsw-loop-deal.css` or `hsw-loop-box.css`. Existing loop widgets keep their
  own markup, classes, spacing and JS.

## Notes

- The stock meter is data-driven: when WooCommerce does not manage stock for a
  product, the bar is omitted instead of showing a fabricated percentage.
- The campaign timer falls back to the nearest product sale end when no explicit
  campaign end is set.
- Products without a sale price are excluded; manual IDs are filtered through
  the same rule.
- Widget count is now **64** (17 general + 47 parts).

## Build

- Version 1.11.281 in the plugin header, `HSW_VERSION`, `readme.txt` Stable tag,
  changelog and this note.
- ZIP built with `tools/build-release.py` (LF text, deterministic entries),
  committed under `release-downloads/`, annotated tag `v1.11.281`, and attached
  to the GitHub release.
