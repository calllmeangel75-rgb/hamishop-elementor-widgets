# HamiShop Elementor Widgets 1.11.165 - release notes

Release date: 2026-10-29

Scope: the three loop widgets (پیشنهاد شگفت‌انگیز / پرفروش‌ترین‌ها / محبوب‌ترین‌ها)
were brought back in line with the design brief. The work stayed inside the
loop family and its direct helpers; nothing else in the plugin changed
behaviour. All of it is on branch `loop-spec`, merged into `main` after the
validation run below.

## 1. Honest stock bar

`HSW_Helpers::stock_progress()` used `get_total_sales()` as "sold out of the
initial quantity". `total_sales` counts the product's **whole lifetime** (often
years before this plugin), so the percentage was fiction — and it was the
default mode.

- New `_hsw_initial_stock` meta per product, written the first time a managed
  product's stock is observed and raised on a restock (`woocommerce_product_set_stock`,
  `woocommerce_variation_set_stock`, `woocommerce_update_product`).
- `sold` mode now needs that baseline and returns **no bar** without it, instead
  of inventing one.
- The control default is `low` («remaining until threshold»), which is always
  safe, and its description explains what `sold` actually measures.
- One-time migration `hsw_db_version = 1` seeds the baseline for existing
  managed products and drops the loop transients once (both in
  `HSW_Plugin::migrate()`, guarded so it never runs per request).

## 2. WooCommerce add-to-cart contract

`hsw-loop.js` triggered only `wc_fragment_refresh`. Themes and plugins listen
for `added_to_cart(fragments, cart_hash, $button)`; without it the mini-cart,
counter and analytics stayed stale after an add from a loop card. It is now
triggered with the real fragments and cart hash (the archive widget already did
this).

## 3. Images: right candidate, one preload

- `sizes` is derived from the same numbers that write `--hsw-loop-cards`
  (`(max-width: 767px) calc(100vw / 2.2), (min-width: 768px) and (max-width: 1024px) calc(100vw / 3), calc(100vw / 4)`),
  so the browser no longer picks a desktop-width candidate for a phone.
- The first above-the-fold image now emits one
  `<link rel="preload" as="image" imagesrcset imagesizes>`.
  *Limitation:* Elementor renders widgets after `wp_head`, so a head-level
  preload would need an extra product query before the page exists. The tag is
  emitted immediately before the `<img>`; a static flag keeps it to **one per
  request** even with several loop widgets. Measured on the test page: 1.

## 4. CLS = 0 (was 0.18)

The layout shift came from the plugin's own font: `font-display: swap` rendered
the first paint with Tahoma and re-flowed the header/rail when Vazirmatn
arrived. Measured on `/hsw-loop-test/` at 390×844:

| Run | CLS |
|---|---|
| before (`swap`) | **0.167 – 0.184** |
| after (`optional`) | **0.000** |
| font blocked (control) | 0.000 |

Shift sources were `.hsw-loop--box` (section +30px), `.hsw-loop__rail` and
`.hsw-loop__icon`. With `optional` the browser never swaps after the block
period; the file is local and cached, so the brand font is still used — a slow
first load keeps the fallback for that page view instead of shifting.

## 5. Timer end states

New control «رفتار پس از پایان زمان»:

- `hide_timer` (default) — the pill disappears, nothing else moves;
- `hide_block` — the whole widget leaves the flow;
- `ended_note` — an editable «این پیشنهاد به پایان رسید» replaces the digits.

`finishTimer()` runs once per timer (`data-hsw-done`), keeps `role="status"` on
the note, and PHP already renders the ended note server-side when the deadline
has passed — a dead `00:00:00` can no longer be painted.

## 6. Dark palette + contrast

- `data-hsw-theme="light|dark|auto"` on each widget root, driven by the new
  «حالت روشن/تاریک» control. `dark` remaps the same token names (surfaces, text,
  borders, timer, tabs, stock track, skeleton); `auto` follows
  `prefers-color-scheme`. Default is `light`, so the store's current look is
  unchanged.
- Deal gradient scrim (token + control, default 20%): white text on the lightest
  stop of the palette measured **3.43:1** (`#4d85ff`) and **3.17:1** (`#fc593a`).
  At 20% it is **5.07:1** (AA for normal text); the control allows 0–50%.
- Deal subtitle and timer label lost their 82%/86% transparency (opaque
  `--hsw-loop-on-accent`).
- 18 hard-coded hex values in `hsw-loop.css` became tokens
  (`--hsw-loop-on-accent`, `--hsw-pal-star/warning/border-strong/ink/ink-soft`);
  the remaining literals are `var()` fallbacks only.

## Measurements (this machine, XAMPP, mobile 390×844)

| Metric | Value | How |
|---|---|---|
| CLS | **0.000** | Playwright `layout-shift` observer, buffered |
| LCP | ~4.1 s (localhost, uncached, 390px) | `largest-contentful-paint` observer, element = first card image |
| DB queries per widget (warm) | wonder-deals **3**, best-sellers **2**, popular **2** | temporary mu-plugin counting `$wpdb->num_queries` around each widget render |
| DB queries per widget (first run) | 3 / 2 / 2 | same, first request after the migration |
| Total page queries | 162 (vs 174 on the very first uncached run) | same helper |
| Shared CSS | 21.1 KB min / **~4.3 KB gzip** | byte count + gzip |
| Variant CSS | deal 1.4 KB, box 0.2 KB min | byte count |
| Loop JS | 8.9 KB min / **~3.2 KB gzip** | byte count + gzip |

Lighthouse itself was **not** run (no Lighthouse CLI in this environment);
Performance score, TBT and LCP-in-field are therefore untested and must be
measured on staging before launch.

## Validation run

- `tests/loop-spec-probe.js` (new): one preload per page ✔, `imagesrcset` ✔,
  dynamic `sizes` ✔, exactly one eager image per widget and the rest lazy ✔,
  `data-hsw-theme` on all three roots ✔, opaque deal subtitle ✔,
  `added_to_cart` fired once with a cart hash ✔, dark palette swaps
  (`--hsw-loop-bg: #14161c`) ✔, all three timer end states ✔, CLS < 0.02 ✔,
  no console errors ✔ — `RESULT: clean`.
- Regression probes: `loop-layout-probe.js` (390/779/1440), `header-counts-probe.js`,
  `archive-filter-probe.js`, `stage2-probe.js` — all `RESULT: clean`.
- PHP lint: 122 files, 0 failures. `tests/harness.php`: 74 classes, clean.
- `debug.log` empty after the runs; the temporary query-count mu-plugin was
  removed afterwards.

## Deliberately not changed (with reasons)

- **Per-group «reset to default» button.** The plugin already ships the
  `loop_theme_reset` switcher, which resets the palette and radius group to the
  theme/global palette, and Elementor offers a reset icon on every single
  control. A group-wide button needs an editor-JS command built on Elementor's
  internal panel APIs; the brief forbids pretending compatibility, so it was
  left out rather than risk breaking the editor on an Elementor update.
- **Head-level font image preload / Lighthouse tuning.** Requires either a
  second product query before `wp_head` or a build step outside the plugin.
- **Idle prefetch of Popular tabs.** The brief marks it optional; tabs still
  fetch on first intent only, so no request happens on first paint.

## Files touched

- `includes/class-hsw-helpers.php` — stock modes, baseline helpers, image
  preload, `price_html_fa`/`format_date` unchanged.
- `includes/class-hsw-plugin.php` — `hsw_db_version` migration + stock hooks.
- `includes/class-hsw-widget-base.php` — sizes, preload flag, timer end states,
  dark-mode attribute, scrim + colour-mode controls, `sold` default off.
- `widgets/class-hsw-wonder-deals.php`, `class-hsw-best-sellers.php`,
  `class-hsw-popular.php` — `data-hsw-theme` on the root, timer args.
- `assets/js/hsw-loop.js` — `added_to_cart`, `finishTimer()`.
- `assets/css/hsw-loop.css`, `assets/css/hsw-loop-deal.css`,
  `assets/css/hsw-fonts.css` — tokens, dark palette, ended states, scrim,
  `font-display: optional`.
- `tests/loop-spec-probe.js` — new.
- Version/docs/POT and the minified assets.
