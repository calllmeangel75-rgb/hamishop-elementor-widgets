# HamiShop Elementor Widgets — Release 1.11.290

**GC header: the colour borrowing is now named, and each piece is settable.**

## Why

The GC header only had nine palette colours, and every component borrowed one
inline: the CTA took `ink` for its background, `glow` for its icon and
`primary-dark` on hover; the circuit mark took `ink` for the body, `primary` for
the trace and `glow` for the node; the cart badge took `glow` + `ink`. Nothing in
the panel said so, so «why is this button black when my palette says green?» had
no answer short of reading the stylesheet.

## Added

- **Role tokens** in `assets/css/hsw-header-gc.css`:
  `--hsw-gc-cta-bg`, `--hsw-gc-cta-bg-hover`, `--hsw-gc-cta-fg`,
  `--hsw-gc-cta-icon`, `--hsw-gc-mark-body`, `--hsw-gc-mark-trace`,
  `--hsw-gc-mark-glow`, `--hsw-gc-badge-bg`, `--hsw-gc-badge-fg`.
  Each reads `var(--…-user, <the token it used before>)`, so the documented
  order (widget control → site palette → literal) still holds.
- **Three control groups** in the GC style tab, all empty by default:
  «دکمهٔ اصلی (CTA)» (background, hover, text, icon), «نشان مدار GC» (body,
  trace, node + halo) and «نشانگر تعداد سبد» (background, number).
  An existing header keeps rendering exactly what it rendered before.
- A CI check (`tools/ci-checks.php`) that fails when the stylesheet reads a
  `-user` channel that no Elementor control writes — the exact way a colour
  silently becomes custom-CSS-only.
- `docs/ARCHITECTURE.md` §۵ now describes the role layer.

## Fixed

- The icon-based mark (`.hsw-gc__mark.hsw-ic`) was sized by `--hsw-gc-mark`
  alone, so the 96px maximum could still push it outside the 66px bar. It is now
  capped by the bar height, like the uploaded logo.
- The mobile drawer's dark button follows the CTA tokens instead of repeating
  `ink` / `#fff` / `glow`, so one CTA colour change covers both.

## Verification

Same markup rendered twice at 1440px — once with the 1.11.289 stylesheet, once
with this one — and 17 computed colour probes compared (mark body/trace/halo/
node, CTA box/icon/label, action button box/icon, badge, brand name, tagline,
bar background, drawer dark button + icon, drawer sheet): **all identical**.

Then each new channel was set on its own and the computed colours re-read:
every channel moved exactly its own element and nothing else, except the two
shared roles that are shared on purpose (the drawer repeats the CTA; node and
halo are one glow role). The CTA hover channel was checked with a real hover.

Icon mark with `--hsw-gc-mark: 96px` / `--hsw-gc-h: 66px`: `56×56`, inside the
bar.

`php -l`, `php tests/harness.php` (126/126 files, 118 classes), `php tools/ci-checks.php`
(24 checks) and `php tests/unit/run.php` (56 assertions) are clean.

## Build

- Version 1.11.290 in the plugin header, `HSW_VERSION`, `readme.txt` Stable tag,
  changelog and this note.
- `.min` assets and the POT rebuilt; ZIP built with `tools/build-release.py`.
