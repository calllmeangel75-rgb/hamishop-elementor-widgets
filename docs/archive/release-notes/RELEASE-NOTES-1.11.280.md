# HamiShop Elementor Widgets — Release 1.11.280

**Loop spacing fix** — the full-bleed wonder-deals shell and the two boxed loop
variants now keep their content away from the section/card edge.

## Fixed

- **«پیشنهاد شگفت‌انگیز» — full-width gutter:** the Elementor shell padding
  control defaults to `0` on the inline sides. A stretched instance therefore
  placed the header and the first/last card against the section edge.
  - The wonder-deals shell now uses
    `padding-inline: clamp(16px, 5%, 96px)`.
  - At 1440px the content starts at `x≈81` while the page container starts at
    `x≈80`; the gradient still reaches both section edges.
- **«محبوب‌ترین‌ها / منتخب» and «پرفروش‌ترین‌ها» — boxed gutter:** both had the
  same `0` inline padding inside their bordered shell. They now use
  `padding-inline: clamp(16px, 3%, 32px)`.
- **Scope:** the rules target only
  `.hsw-loop--hsw-wonder-deals`,
  `.hsw-loop--hsw-popular` and
  `.hsw-loop--hsw-best-sellers`.
  The `.hsw-loop--box`/`.hsw-loop--deal` internals and every other widget are
  unchanged.

## Verification

- 1440px: wonder-deals `padding: 12px 71px`; rail starts at `x=81`.
- 390px: wonder-deals `padding: 16px 18.5px`; rail starts at `x=28.5`.
- Boxed loop at 1440px: `padding: 0px 32px 12px`; rail starts at `x=183` inside
  the card at `x=150`.
- The two boxed widgets keep identical outer dimensions; only their inner
  gutter changes.
- `php tools/ci-checks.php`: all 23 checks pass.

## Build

- Version 1.11.280 in the plugin header, `HSW_VERSION`, `readme.txt` Stable tag,
  changelog and this note.
- ZIP built with `tools/build-release.py` (LF text, deterministic entries),
  committed under `release-downloads/`, annotated tag `v1.11.280`, and attached
  to the GitHub release.
