# HamiShop Elementor Widgets 1.11.152 - release notes

## Summary

This patch removes the remaining Hello Elementor `#cc3366` leak from HamiShop hover and focus states. The store-wide safety net in `hsw-fonts.css` was matching descendants of buttons instead of the buttons themselves, so Hello's `button:hover` / `button:focus` rules kept painting the theme pink on components that did not declare an explicit background state.

## Changes in 1.11.152

### Hello Elementor reset fix

- Removed the accidental descendant combinator between `:not(.is-active)` and `:is(:hover, :focus, :focus-visible)` in the store-wide button reset.
- Rewrote the reset at specificity `(0,2,0)` so it still beats Hello's `[type="button"]:hover` `(0,2,0)` rule but stays below component styles that load later.
- Moved the CTA/active-state exclusions into `:where()` so they keep working without raising the reset above component hover rules.
- The reset now targets HamiShop-classed buttons inside `.hsw-a`, `.hsw-c`, `.hsw-k`, `.hsw-loop`, `.hsw-h`, `.hsw-ac`, `.hsw-p`, `.hsw-toast` and `.hsw-modal`.

### Explicit hover and focus states

- Checkout schedule: restored the surface background and text colour on `.hsw-cksched__day` hover/focus, preserved the primary fill for `.is-sel`, and added matching states for `.hsw-cksched__mday`.
- Archive filters: `.hsw-af__catmore` now stays transparent with the archive primary colour and underline on hover/focus.
- Header: `.hsw-hd__iconbtn` (including the mobile search button) now uses the header search background and palette hover colour on hover/focus.
- Header drawer: `.hsw-hd__drawerclose` now has palette-driven hover/focus states.
- Cart drawer: `.hsw-hd__cartclose` now applies the same hover treatment on `:focus-visible` as on `:hover`.

### Release housekeeping

- Bumped the plugin version to `1.11.152`.
- Rebuilt the minified CSS assets.
- Regenerated the POT header for the new version.
- Updated the release builder to include these notes.

## Validation run for 1.11.152

- CSS selector/brace audit and specificity check for the rewritten reset. The compiled selector stays at `(0,2,0)`; Hello's `[type="button"]:hover` is `(0,2,0)` and loses on source order, while component rules such as `.hsw-cksched__day:hover` also `(0,2,0)` win because their stylesheets load after `hsw-fonts`.
- Minified/source parity check for every changed stylesheet.
- PHP syntax check on the main plugin file: no syntax errors.
- POT regeneration: 3,079 entries, 3,079 unique msgids, 0 duplicates.
- Live sync to the local WordPress install (`1.11.152`) and computed-style verification with the real Hello `reset.css` plus the live plugin CSS:
  - plain HSW button: transparent background, inherited text colour (no `rgb(204, 51, 102)`);
  - archive «نمایش همه دسته‌ها»: transparent background, archive primary colour, underline on hover/focus-visible;
  - checkout day/mobile-day: surface background, dark text, selected states keep the primary/dark fill;
  - header mobile search/icon button: search background and palette colour;
  - drawer close: search background and palette colour;
  - cart close: palette-tinted background, palette border/colour, including keyboard focus.
- ZIP content and parity check: recorded in the delivery message after the archive is built.
