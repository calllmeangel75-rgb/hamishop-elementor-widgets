# HamiShop Elementor Widgets — Release 1.11.291

**Product page: the attribute picker now speaks WooCommerce.** Five defects, all
reproduced on a real staging install, all fixed and re-tested on the same pages.

## What was wrong

### 1. Custom attributes never matched a variation (critical)

`WC_Product_Variation::get_attributes()` is keyed by `sanitize_title()` — `Color`
becomes `color`, `رنگ جلد` becomes `%d8%b1%d9%86%da%af-%d8%ac%d9%84%d8%af` — while
the picker published the label the shop owner typed (`Color`, `رنگ جلد`). The JS
matcher compares the two maps literally, treated every unknown key as a wildcard,
and fell back to `list[0]`.

Measured on a 4-variation product (S/M × Black/White, prices 200k–290k):
clicking all four buttons kept the price at `۲۰۰,۰۰۰` and every add posted
`variation_id=13945` — the first variation.

### 2. A colour picker whose name contains a space vanished (critical)

Decoding the stored key gives `رنگ-جلد` (hyphen) while the typed name is
`رنگ جلد` (space), so `find_attribute()` returned nothing, `attribute_terms()`
was empty and the attribute was dropped from both the colour role *and* the chip
groups: a notebook with «سایز» + «رنگ جلد» showed only «سایز», so no cover could
ever be chosen.

### 3. A simple product with two attributes could not be bought (critical)

`paintPick()` cleared every button that was not part of the group being answered,
so answering «نوک» erased «رنگ». The gate then reported a missing answer forever:
the buy button stayed `is-locked` and no request was sent.

### 4. The simple-product choice was dropped by the cart (high)

The picker posted `data-hsw-pick-value` = `sanitize_title( option )`. For Persian
options that is itself percent-encoded (`%d8%a2%d8%a8%db%8c`), and
`sanitize_text_field()` — used by `HSW_Simple_Attributes::read_request()` —
removes percent octets as an obfuscation attempt. The colour arrived as `''` and
was skipped; the next attribute (ASCII, `0-7`) still worked, which is why the
cart line showed «0-7» but no colour.

### 5. Grouped and external products rendered a dead button (high)

Both types got our `data-hsw-add` button. WooCommerce refuses to add a grouped
product directly and an external product never enters the cart, so the button
either errored or did nothing, and the external product's own URL/button text was
ignored.

## Fixes

- `attr_key()` returns the key WooCommerce stores; the picker (`color`, `storage`,
  every chip group) and the variation payload both use it, and the payload also
  keeps the taxonomy and readable spellings for back-compat.
- `normalize_attribute_key()` compares the `sanitize_title()` form, so
  `رنگ جلد` / `رنگ-جلد` / `%d8%b1…` are one attribute.
- `paintPick()` only repaints the group being answered.
- The simple picker posts the option as typed; `read_request()` runs
  `rawurldecode()` before `wc_clean()` and resolves taxonomy slugs by slug, by
  sanitized slug and by name.
- `matchVariation()` returns nothing when nothing matches; the price shows `—`,
  the buy button locks, and the click answers «این ترکیب از ویژگی‌ها موجود نیست.».
- Grouped/external: the purchase box prints `woocommerce_template_single_add_to_cart()`
  (child table / outbound button), and the sticky bar stays hidden for them.
- Portability defaults: no city, no registry programme, generic section and
  widget names; the mobile-accordion controls are gone from the composite (that
  part has not rendered since the tab strip took over).

## Verification (real staging install, plugin swapped to this build)

| Page | Before | After |
|---|---|---|
| Notebook, «سایز» + «رنگ جلد» (custom, Persian) | only «سایز»; price never changed; added A5/مشکی | both groups; A4→۱۷۵٬۰۰۰، قرمز→۲۲۵٬۰۰۰، B5→۲۹۰٬۰۰۰; posted `variation_id=13938` = B5/قرمز |
| `Size` + `Color` (custom, Latin) | price fixed; added S/Black | White→۲۳۰٬۰۰۰، M→۲۹۰٬۰۰۰; posted `13948` = M/White |
| Missing combination (کوچک/پارچه) | would add the first variation | price `—`, button locked, toast, **no request** |
| Simple, «رنگ» + «نوک» | button locked forever | unlocks; cart line «آبی • 0.7» |
| Grouped | our dead buy button | WooCommerce grouped table, no sticky bar |
| External | our dead buy button | WooCommerce outbound button, no sticky bar |
| `pa_color` variable (taxonomy) | worked | still works (آبی ۷۹۸٬۶۰۰ / قرمز ۶۶٬۵۵۰, correct `variation_id`) |

No console errors on any of those pages.

## Build

- Version 1.11.291 in the plugin header, `HSW_VERSION`, `readme.txt` Stable tag,
  changelog and this note.
- `php tools/ci-checks.php` 24/24, `tests/harness.php` 126 files / 118 classes,
  `tests/unit/run.php` 56 assertions, and the new
  `tests/qa/product-attribute-keys-wp.php` all pass.
- `.min` assets and the POT rebuilt; ZIP built with `tools/build-release.py`.
