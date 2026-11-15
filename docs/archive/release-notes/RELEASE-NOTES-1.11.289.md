# HamiShop Elementor Widgets — Release 1.11.289

**GC header logo overflow fix.**

## Fixed

- The GC header's image/icon mark is now capped by the header height:
  `min(var(--hsw-gc-mark), calc(var(--hsw-gc-h) - 10px))`.
  A logo chosen at the old 96px maximum can no longer extend above or below the
  66px bar.
- Uploaded logo width is capped at `min(mark * 6, 40vw, 260px)` and the image
  keeps its aspect ratio with `object-fit: contain`.
- The «اندازهٔ نشان» control now explains that the rendered height is limited by
  the header, so the setting no longer promises a size that cannot fit.

## Verification

- Static 1440px render with `--hsw-gc-mark: 96px` and a 500×180 logo:
  image height `56px`, header height `66px`; no overflow.
- `php -l` clean.
- No other header/cart/menu behavior changed.

## Build

- Version 1.11.289 in the plugin header, `HSW_VERSION`, `readme.txt` Stable tag,
  changelog and this note.
- ZIP built with `tools/build-release.py`, committed under `release-downloads/`,
  annotated tag `v1.11.289`, and attached to the GitHub release.
