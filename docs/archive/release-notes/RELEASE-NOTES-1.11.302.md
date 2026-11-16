# HamiShop Elementor Widgets — Release 1.11.302

Three shop-owner requests: no «خبرم کن», plain availability wording, and the last two
uncontrollable gaps in the loop widgets.

## Changed

- **«خبرم کن» is gone.** The loop cards (bestsellers, most-popular, featured/deal) rendered a
  «خبرم کن» link for an out-of-stock product, suggesting a back-in-stock notification the shop does
  not offer. The card now shows a disabled «اتمام موجودی» chip (`hsw-loop__btn--oos`,
  `aria-disabled`), and the «متن دکمه خبرم کن» control was removed from the widget panel — a control
  that controlled nothing would be worse than none.
- **Availability wording.** The impossible-combination and out-of-stock messages said «این ترکیب …
  موجود نیست» — customers were thinking about combinations, not about the product. All three
  customer-facing messages (product page note, no-match toast, out-of-stock toast) now read
  **«این محصول در حال حاضر موجود نیست»**, and the widget's `data-hsw-no-match` default follows.

## Added

- **Tab spacing.** «پدینگ کانتینر تبها» (`--hsw-loop-tabs-pad`, default 4px) and «فاصله بین تبها»
  (`--hsw-loop-tabs-gap`, default 2px), both responsive, in *استایل → استایل تبها*. Those two were
  the only internal gaps in the loop widget still hard-coded; section padding, card gutter, card
  padding, header gap and tab padding already had controls.

## Verified

`python tools/build-assets.py`, `php tools/ci-checks.php` (26 checks), `php tests/harness.php`
(118 classes), `php tests/unit/run.php` (56 assertions) all clean; `grep` confirms no
`txt_notify` / `hsw-loop__btn--notify` reference remains.
