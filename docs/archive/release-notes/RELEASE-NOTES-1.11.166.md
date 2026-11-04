# HamiShop Elementor Widgets 1.11.166 - release notes

Release date: 2026-10-29

Follow-up to 1.11.165. The style cascade you described was already in place, but
two gaps meant the dashboard palette could not fully own the loop widgets — and
one gap made filled buttons unreadable for light palette picks. Both are fixed;
the cascade is now covered by a probe.

## The cascade as it stands (measured, not assumed)

| Layer | Source | Wins by |
|---|---|---|
| 1. Default for every widget | HamiShop → پالت رنگی (`hsw_palette` option), printed as `html:root{…}` | base token values |
| 2. This widget only | Elementor → widget Style tab (preset, colours, dimensions) | declarations on `{{WRAPPER}} .hsw-loop` |
| 3. This element | Elementor typography/colour of the widget or a child | direct CSS property on that element |

Measured on `/hsw-loop-test/` after setting the dashboard accent to `#00a86b`:

- `html:root` → `--hsw-accent: #00a86b`
- widget **1** (saved with `hsw-loop--preset-red`) → `--hsw-accent: #d7263d`, price
  `rgb(215, 38, 61)` → **the widget's own preset wins**, the palette is ignored;
- widgets **2 and 3** (no preset) → `--hsw-accent: #00a86b`, price
  `rgb(0, 168, 107)` → **the palette wins**;
- adding `--hsw-loop-accent` inline (what a style control writes) → purple,
  above both;
- changing `--hsw-font` at the root → widget font follows; setting
  `font-family` on the widget (Typography control) → that wins.

## Fix 1: the palette now publishes every token the loop family reads

`hsw-loop.css` had started using `--hsw-pal-star`, `-border-strong`, `-ink`,
`-ink-soft` and `-on-accent` with local fallbacks. Those tokens were never
printed by `HSW_Palette`, so changing the palette silently did nothing for
stars, scrollbar/ranks, dark badges and on-accent text — the literal fallback
always won.

`HSW_Palette::css()` now derives them from the existing palette entries:

- `--hsw-pal-star` ← `warning` mixed toward the amber star tone,
- `--hsw-pal-border-strong` ← `border` + `text` 28%,
- `--hsw-pal-ink` ← `text`, `--hsw-pal-ink-soft` ← `text` + `surface` 26%,
- `--hsw-pal-on-accent` / `-on-success` / `-on-warning` / `-on-danger`
  ← computed readable text colour.

No new row in the palette screen: the owner still tunes five base colours and the
rest follows, which keeps the "one screen is the source" rule.

## Fix 2: text on filled colours stays readable

`--hsw-on-primary` was hard-coded `#ffffff`. A light primary (or a light accent
for the `on-accent` tokens) made buttons and badges unreadable — white on yellow
is ≈1.6:1.

New `HSW_Palette::contrast_text()` picks `#ffffff` or `#111827` by WCAG relative
luminance. The policy is deliberately conservative so the current design does not
move:

- white is kept while it reaches **3:1** (large-text AA);
- only below that — a yellow/white fill — the ink colour is used.

Verified: `#fc593a` → `#ffffff`, `#2563eb` → `#ffffff`, `#7c3aed` → `#ffffff`,
`#ffd400` → `#111827`, `#ffffff` → `#111827`, `#111827` → `#ffffff`.

## Validation run

- `tests/palette-cascade-probe.js` (new, run against a real palette change:
  accent written to `#00a86b`, then restored):
  - palette publishes every loop token ✔
  - dashboard accent reaches a plain widget ✔ (`rgb(0, 168, 107)`)
  - a widget preset keeps priority ✔ (`rgb(215, 38, 61)` while the palette is green)
  - a style control on the widget wins over both ✔ (`rgb(124, 58, 237)`)
  - changing palette tokens repaints price **and** card surface ✔
  - palette font drives the widget, widget typography wins ✔
  - no console errors ✔ — `RESULT: clean`
- Palette restored to its previous value after the test (`#fc593a`).
- PHP lint: 122 files, 0 failures. `tests/harness.php`: 74 classes, clean.
- The loop/archive/header/checkout probes from 1.11.165 were re-run earlier in
  the same code state and stayed clean; this change touches only the palette.

## Note for the store owner

If a widget should follow the dashboard palette, set its **پالت آماده** control
to «خودکار (پالت سراسری حامی)». A saved preset (قرمز/آبی/سبز) is a widget-level
decision and intentionally overrides the palette for that widget only.
