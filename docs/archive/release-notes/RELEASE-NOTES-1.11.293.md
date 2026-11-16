# HamiShop Elementor Widgets — Release 1.11.293

**One-line fix found by the mobile smoke test: an attribute name with a space lost its pick.**

## What happened

`HSW_Simple_Attributes::read_request()` looked up `$_REQUEST['attribute_' . $attr->get_name()]`.
For a simple product whose attribute is named «رنگ جلد» that is `attribute_رنگ جلد` — but PHP
rewrites a space inside an incoming variable name to an underscore, so the request actually
carries `attribute_رنگ_جلد`. `isset()` failed, the pick was skipped, and the cart line showed the
size but no colour. Nothing raised an error, which is why 1.11.291's tests missed it: those used
«رنگ» without a space.

## Fix

The lookup tries every spelling the request can hold — the typed name, the `_` form PHP produces,
and the percent-decoded form — and the value is still validated against the product's own options.

## Verification

- Mobile 390×844 on the staging install: simple product «تست موبایل — دفتر» (two attributes,
  «رنگ جلد» + «سایز»). Both groups render, the buy button unlocks, the request carries both
  attributes, and the cart line reads «تست موبایل — دفتر مشکی A5».
- QA test `tests/qa/product-attribute-keys-wp.php`: 10 checks, including the underscore key.
- Elementor editor smoke test on the same install: the product template opens, clicking the
  composite widget lists sections ۱–۱۰, the purchase repeater and the tab controls render, no
  console or PHP errors.

## Build

- Version 1.11.293 in the plugin header, `HSW_VERSION`, `readme.txt` Stable tag, changelog and this
  note. `.min` assets and the POT rebuilt; ZIP built with `tools/build-release.py`.
