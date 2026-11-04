# HamiShop Elementor Widgets 1.11.150 - release notes

## Summary

This follow-up adds full typography controls for the product page’s «بررسی تخصصی کالا» tab content and makes the header cart drawer open and close with a softer, more polished slide.

## Changes in 1.11.150

### Product review-tab typography

- The composite product page’s «تب‌ها و بخش‌های توضیحات» Style section now includes:
  - panel text typography;
  - dedicated «بررسی تخصصی» paragraph typography;
  - panel heading typography;
  - panel and review text colours.
- Elementor’s typography group controls expose font family, size, weight, line height, letter spacing and the other standard options.
- The dedicated review selector (`.hsw-ptabs__review, .hsw-ptabs__review p`) wins over the hard-coded `14px/24px` paragraph rule, so changing the font actually affects the editorial review text.
- Existing «متن معرفی و اطلاعات» controls and their saved values remain valid. The new controls are empty by default and therefore do not override the palette or existing settings.

### Cart drawer motion

- The drawer now stays visible until the closing animation ends instead of being hidden immediately.
- Panel slide lengthened from 0.28s to 0.52s with a soft `cubic-bezier(.22, .61, .36, 1)` ease-out, plus a subtle scale-in from the screen edge.
- The overlay fades over 0.38s and the header/body content fades and slides into place with a short delay.
- The scrollbar width is reserved before scroll locking, so the page underneath no longer reflows when the drawer opens.
- `prefers-reduced-motion: reduce` disables these transitions.
- Focus management, Escape handling, outside-click closing and scroll-lock behaviour remain the same.

## Validation run for 1.11.150

- PHP lint: `hamishop-elementor-widgets.php` and the changed product-page style trait both pass.
- JS syntax: `node --check assets/js/hsw-header.js` passes.
- Control registration audit: the composite product-page widget registers 450 top-level controls with 0 duplicate IDs; the new `tb_content_h`, `tb_content_note`, `tb_panel_t_h`, `tb_review_t_h`, `tb_h2_t_h` controls are present.
- Minified asset rebuild: `hsw-header.css` and `hsw-header.js` regenerated as `.min.css`/`.min.js`; no unitless custom-property warnings.
- POT: 3,096 unique entries, no duplicate msgids, `1.11.150` header. `msgfmt`/`polib` are not installed, so no compile check was possible.
- Live typography check on product 13111 / template 13266 (two composite instances): the review paragraphs computed `Tahoma`, `21px`, weight `600`, line-height `44.1px`, colour `rgb(194, 24, 91)`; the panel computed `17px/28px`; the panel heading computed `26px/38px`. The controls therefore override the hard-coded `14px/24px` paragraph rule.
- The template was restored from its pre-test `_elementor_data` backup; the review paragraph returned to `14px/24px` and the palette colour. No product stock changes were needed.
- Live drawer motion check: panel transition `0.52s cubic-bezier(.22, .61, .36, 1)`, content transition `0.46s`, overlay `0.38s`, close visibility delay `0.52s`. The panel was still moving at 60/300ms and settled by 650ms; on close it remained visible at 300ms and was hidden by 650ms.
- Scrollbar compensation: opening the drawer kept the body at `605px` and the test container at `585px` while the scrollbar disappeared; `padding-right` resolved to `15px`. No reflow of the page underneath.
- `prefers-reduced-motion: reduce` rule is present and disables the drawer/overlay/panel/content transitions.
- No console errors on the product page after the checks.
- CLI smoke check: the `hsw-product-page` widget registers the new controls; the active template is restored.
- ZIP content and parity check: 178 files, all under one top-level `hamishop-elementor-widgets/` folder; no tests, tools, backups or QA files; the main plugin, readme, changed PHP/CSS/JS, POT, docs and release notes match both the repository and the live plugin. The final archive hash is recorded in the delivery message rather than inside the archive itself.

## Notes

- The new typography controls live in the detailed tabs Style section; the existing general panel/heading controls in «متن معرفی و اطلاعات» are unchanged.
- GitHub push remains pending until credentials are available.
