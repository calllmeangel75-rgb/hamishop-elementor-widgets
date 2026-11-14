# HamiShop Elementor Widgets — Release 1.11.284

**Offer rail arrows and mobile layout** — no new assets, no extra libraries.

## Changed

- **Arrows:** the previous single-chevron circles are now 42px pill buttons with
  two chevrons (`<<` / `>>`). The second chevron has a soft opacity pulse; the
  animation is removed under `prefers-reduced-motion`. Hover lifts the button
  slightly and the active state keeps the vertical centering.
- **Mobile layout:** the campaign card is no longer a tall stacked block above
  the rail. On screens up to 767px it becomes a compact header:
  - title/subtitle/badge on the right;
  - countdown on the left;
  - the large CTA is hidden;
  - product cards cap at 240px;
  - a «مشاهده همه» card is appended to the end of the rail.
- **Timer icon bug:** the campaign countdown SVG now has an explicit 14px box.
  Without it, the browser's default SVG width could stretch the lead card and
  push the mobile header out of the shell.

## Verification

- Static RTL render at 390px: lead header height dropped to ~129px and the rail
  starts at y≈162; card width is 240px.
- `prefers-reduced-motion: reduce` disables the chevron pulse.
- `php -l` clean; `php tools/ci-checks.php` 23 checks pass.
- No new CSS/JS files; the existing `hsw-offer.css` / `hsw-offer.js` remain
  conditional.

## Build

- Version 1.11.284 in the plugin header, `HSW_VERSION`, `readme.txt` Stable tag,
  changelog and this note.
- ZIP built with `tools/build-release.py`, committed under `release-downloads/`,
  annotated tag `v1.11.284`, and attached to the GitHub release.
