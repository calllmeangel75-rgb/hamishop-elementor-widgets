# HamiShop Elementor Widgets — Release 1.11.283

**Full colour customisation for «آفر حامی‌شاپ»** — no CSS editing is required
for the widget's visible palette.

## Added

- **Campaign card colours:**
  - title, subtitle;
  - badge background/text/icon;
  - lead icon background/colour;
  - timer background/text/label;
  - CTA background/text and hover background/text;
  - shell text and empty-state text.
- **Product card colours:**
  - card border;
  - media background (normal/hover);
  - old price;
  - discount badge background/text;
  - divider;
  - stock track and stock gradient start/end.
- **Note badge colours:** green, amber, blue and red variants, each with
  background and text controls.
- **Arrow colours:** background, icon, border, hover background and hover icon.
- **Heading tag control:** campaign title can be `h2`, `h3`, `div` or `span`.
  Default stays `h2` for the correct heading outline.

All new controls are native Elementor colour controls in the **Style** tab and
write CSS custom properties on the widget root. Defaults preserve the original
look. Decorative translucent glows and shadow alpha stay in the stylesheet
because they are not brand colours and changing them would change the design's
depth rather than its palette.

## Verification

- `php tools/ci-checks.php`: 23 checks pass.
- `php -l` on the widget class: clean.
- Render test: the heading tag control produces `<h3 class="hsw-offer__lead-title">`
  when selected; the default remains `h2`.
- CI main/tag status must be green before delivery.

## Build

- Version 1.11.283 in the plugin header, `HSW_VERSION`, `readme.txt` Stable tag,
  changelog and this note.
- ZIP built with `tools/build-release.py`, committed under `release-downloads/`,
  annotated tag `v1.11.283`, and attached to the GitHub release.
