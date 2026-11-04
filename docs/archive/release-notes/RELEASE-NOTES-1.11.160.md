# HamiShop Elementor Widgets 1.11.160 - release notes

Release date: 2026-10-29

This release closes the product-loop rebuild that was in the working tree: the
three loop widgets now share one card engine, one query layer and one feedback
module, and the two mobile defects found in the layout audit are fixed.

## Changes in 1.11.160

### One card engine for the three loop widgets
- `HSW_Widget_Base` owns the card markup (`card_html()`), the media/price/rating/stock helpers and the renderer args, so «پیشنهاد شگفت‌انگیز», «پرفروش‌ترین‌ها» and «محبوب‌ترین‌ها» render byte-identical cards and REST fragments match the server output.
- Card structure is fixed: image → category → title → rating → stock bar → price → action; variants only add a class.
- `HSW_Helpers::image()` uses `srcset`/`sizes`, lazy loading and `fetchpriority` for the first card; the CSS is split into the shared `hsw-loop.css` plus two small variant sheets (`hsw-loop-box.css`, `hsw-loop-deal.css`) so a page downloads only the variant it renders.
- Dedicated `hsw_card` image size (420×420 crop) is registered; attachments created before this version fall back to `woocommerce_thumbnail` instead of the full-size original.

### Product query layer (`HSW_Products`)
- Results are cached as lean ID lists (object cache + transient) and loaded back with one `update_post_caches()` pass instead of a second `WP_Query`.
- Cache keys include the normalized args and a catalogue version that advances on product save/delete, stock change and sale scheduling; the TTL is clamped to the nearest running sale end so a deal rail cannot outlive its discount.
- Rating/popular and best-seller ordering read `wc_product_meta_lookup` and `wc_order_product_lookup` (HPOS aware, legacy fallback) instead of sorting postmeta; «بیشترین تخفیف» orders by the real discount percentage and counts the best-discounted variation.
- `related_category_ids()` walks from the product's own category to the parent one level at a time until it finds other products.

### REST and front-end behaviour
- `/hsw/v1/loop` is signed with an HMAC bound to the post + widget, refuses a type mismatch, answers `304` on a matching ETag and sends public cache headers; editors always get a fresh, `no-store` response.
- New `/hsw/v1/qty-nonce` endpoint keeps the cart stepper working on pages served from a full-page cache.
- Lazy tabs fetch only on click (no idle prefetch), show a skeleton sized from the real card, cache the fragment in memory and re-sync the arrows afterwards.
- `hsw-loop.js` is registered with `strategy: defer`.
- Add-to-cart feedback is unified in `hsw-feedback.js`: the modal or the one-line notice, never both; the widget setting «سبک بازخورد» picks between them and WooCommerce fragments keep the header badge and drawer in sync.

### Fixes
- **PHP warnings on every cached loop.** `HSW_Products::load_ids()` handed raw IDs to `update_post_caches()`, which expects post objects: every request that used a cached list logged 34 `Attempt to read property "ID" on int` warnings and one `WP_Object_Cache::add` notice for the empty cache key. It now primes through `_prime_post_caches()` (core's ID-based helper) and skips products that are no longer published.
- **Mobile price overflow.** On a ~150px card the nowrap price was painted under the labelled button (`min-inline-size: 0` let the price box shrink to 19px). The foot now wraps, the price box is `min-content`, and the button shrink-wraps below it.
- **Mobile dead height.** `contain-intrinsic-size: auto 360px` reserved 382px for cards whose real height is 277px, and the stretching rail turned that difference into a ~105px blank strip under «محبوب‌ترین‌ها». The reserved size is now 460px on desktop and 250px under 768px.

## Validation run for 1.11.160

- PHP lint: 122 files, 0 failures.
- `tests/harness.php`: all 73 plugin classes declared, 0 compile-level failures.
- `debug.log` is empty after loading the homepage, `/shop/`, the loop test page, a product page and `/cart/` (it previously collected 34 warnings per request from the cached loop list).
- Layout probe (`tests/loop-layout-probe.js`, new) on `http://localhost/sks/hsw-loop-test/`:
  - 390px: overlap 0 on all 22 cards, rail slack 4px (was 105px + 6 overlapping cards).
  - 779px: overlap 0, rail slack 4px.
  - 1440px: overlap 0, rail slack 4px.
- Live behaviour at 1440px and 390px: arrows scroll one card <768 / two cards ≥768 with correct `disabled` states in RTL; tab switching issues `GET /wp-json/hsw/v1/loop?...` → 200 and swaps exactly the expected cards; returning to the first tab is served from memory; add-to-cart returns 200, shows the configured feedback, updates the badge and restores the button; console and network clean (no errors, no response ≥400).
- Phones tested with the probe at 390×844 and 779×900; the mobile screenshots confirm price and action on separate lines with no clipping.

## Known data limitations (not plugin defects)

- Test products without a featured image render the placeholder tile (`13155`, `13169`, `13167`, …). Adding a featured image removes it.
- The «پرفروش‌ترین‌ها» rail on the test site contains products created for tests only, so several cards show placeholders and a rank without a real image.
