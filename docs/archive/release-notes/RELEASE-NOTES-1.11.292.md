# HamiShop Elementor Widgets — Release 1.11.292

**Product page: the two strips are now removable, and the variation photo reaches the gallery.**

## Fixed

- **Assurance rows / trust signals are fully removable.** «خطوط اطمینان» (`assure_items`) and
  «سیگنال‌های اعتماد» (`trust_items`) are repeaters, but an emptied repeater fell back to the
  design defaults, so a shop could reword the lines and never delete them. A saved empty repeater
  now renders nothing; the defaults still show when the control was never touched.
- **Variation image swap.** `applyVariant()` searched `.hsw-pgal__img` inside the purchase root,
  while the gallery is a separate widget root on the same page — the lookup always returned null,
  so choosing a colour never changed the photo. It is document-wide now, and a variation without
  its own photo falls back to `data-hsw-main`, which the thumbnail click keeps up to date.
- **Payload size.** `WC_Product_Variation::get_image_id()` silently returns the parent product's
  image, so every variation row carried the same ~95-byte URL. Only a variation's own
  `_thumbnail_id` is published; the gallery keeps showing the parent photo through the same
  fallback. A 40-variation product ships ~3.8 KB less HTML.

## Verification

- Rendered a variable product with three variations: first two have their own photos, the third has
  none. Clicking «مشکی» → its own photo, «آبی» → its own photo, «قرمز» → the product photo.
  Clicking a thumbnail and then «قرمز» keeps the thumbnail's photo. No console errors.
- Rendered the purchase box and trust strip with: no saved settings (defaults: 2 / 3 rows), a saved
  empty repeater (0 rows), and one saved row (1 row).
- `php tools/ci-checks.php` 24/24, `tests/harness.php` 126 files / 118 classes,
  `tests/unit/run.php` 56 assertions, `tests/qa/product-attribute-keys-wp.php` 9 checks.

## Build

- Version 1.11.292 in the plugin header, `HSW_VERSION`, `readme.txt` Stable tag, changelog and this
  note. `.min` assets and the POT rebuilt; ZIP built with `tools/build-release.py`.
