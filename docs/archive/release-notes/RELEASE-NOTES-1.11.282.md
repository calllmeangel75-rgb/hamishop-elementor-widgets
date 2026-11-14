# HamiShop Elementor Widgets — Release 1.11.282

**Performance pass for the new offer widget** — LCP image priority, cheaper shell
paint, and an idle countdown.

## Changed

- **First card image priority:** the first card in «آفر حامی‌شاپ» is rendered with
  `loading="eager"` and `fetchpriority="high"`. Remaining cards stay
  `loading="lazy"`. The first card is the likely LCP element in a horizontal
  campaign rail, so lazy-loading it delayed LCP.
- **Remove the filtered glow layers:** the two decorative shell circles used
  `filter: blur(48px)`. They are now radial gradients with no filter, avoiding a
  large filtered paint layer without changing the visual.
- **Timer idle work:** the shared one-second countdown stops on
  `visibilitychange` while the tab is hidden and resumes when visible.

## Notes

- The widget still uses no Swiper, no jQuery, no Tailwind, no external requests,
  and one interval for all timers.
- CSS/JS remain conditional (`get_style_depends` / `get_script_depends`); pages
  without the widget do not download them.
- Lighthouse 100 is not guaranteed by the widget alone: Elementor, the theme,
  WooCommerce, fonts, images, server and cache configuration all affect the
  score. Measure the actual page after installing 1.11.282.

## Build

- Version 1.11.282 in the plugin header, `HSW_VERSION`, `readme.txt` Stable tag,
  changelog and this note.
- ZIP built with `tools/build-release.py`, committed under `release-downloads/`,
  annotated tag `v1.11.282`, and attached to the GitHub release.
