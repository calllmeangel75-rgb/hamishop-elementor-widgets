# HamiShop Elementor Widgets — Release 1.11.294

**Five fixes from a real shop owner's test of the product page.**

## Fixed

- **An out-of-stock variation said nothing.** Selecting a colour whose variation is
  `outofstock` disabled the buy button, but `pointer-events: none` swallowed the click and no
  message appeared. The button stays visually disabled yet clickable: an inline line under the
  buy row («این ترکیب از ویژگی‌ها فعلاً موجود نیست.») appears with the stock state, and a click
  explains itself with a toast. Nothing is sent to `add_to_cart`.
- **«نقره‌ای» / «نقره ای» rendered as a text chip.** `normalize_color_key()` collapsed `-`, `_` and
  the Arabic tatweel but not the ZWNJ, so a term typed with a space never matched the map. Every
  joiner (ZWNJ, ZWJ, NBSP, LRM/RLM) now becomes a space, and the matcher also compares the
  space-free form. Silver, gold, beige and the rest get their swatch.
- **Variation photos were missing from the gallery carousel.** `gallery_ids()` only collected the
  featured image and the product gallery, so a shop that uploads one photo per variation saw the
  same two photos in the thumbnails. Variation `_thumbnail_id`s are appended (once each), which
  also puts them in the zoom list and lets the swap show a photo the visitor can pick from.
- **The assurance strip could not be removed.** Emptying the repeater now renders nothing, but a
  shop that wants no strip at all gets a switch: «نمایش خطوط اطمینان» → «حذف کامل» (single widget
  and full-page composite).
- **The «عنوان محصول» colour/typography in the composite did nothing.** Both controls targeted
  `.hsw-ptitle__h`, which does not exist; the markup renders `.hsw-ptitle__h1` (desktop) and
  `.hsw-ptitle__h2` (mobile). The selectors point at both.

## Verification (staging install, temporary plugin swap)

- Variable product, three colours, one `outofstock`, photos only on variations: silver now gets a
  `#c0c0c0` swatch; selecting it shows the note, the click shows a toast and **no request**;
  switching back to orange hides the note and adds variation `14066`.
- Gallery: 3 thumbnails and a 3-entry `data-hsw-images` list from variation photos only.
- Purchase box: `show_assure` untouched/`yes` → strip with 2 rows; `no` → no strip markup at all,
  even with rows saved.
- Title: the real element is `.hsw-ptitle__h1`; a rule on that class changes the computed colour
  (verified red), so the control now lands.
- `php tools/ci-checks.php` 24/24, harness clean, no console or PHP errors.

## Build

- Version 1.11.294 in the plugin header, `HSW_VERSION`, `readme.txt` Stable tag, changelog and this
  note. `.min` assets and the POT rebuilt; ZIP built with `tools/build-release.py`.
