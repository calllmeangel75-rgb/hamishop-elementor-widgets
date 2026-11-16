# HamiShop Elementor Widgets — Release 1.11.301

Two visual states that were one step behind the data.

## Fixed

- **«خواندن همه» left the bell number.** The handler removed the unread dots, the sidebar badge, the
  pill and its own button, but never touched `.hsw-ac__bell-count` — the badge only disappeared after a
  reload. It now calls the same `hswNotificationCounts(0)` the single-item path uses.
- **A variation without a photo jumped back to the product's first image.** `data-hsw-main` (the photo
  a photo-less variation returns to) was only updated by thumbnail clicks; applying a variation's own
  photo did not update it, so «نارنجی/۱TB → نارنجی/۵۱۲» (the second variation has no image) switched
  the gallery to the product main photo. Applying a variation image now records it as the current one.

## Verified

Live product page (`/?p=1784`, variations 1789 with an image and 1797 without), with the new
`hsw-product.min.js` injected over the installed one: orange/1TB shows `aaebc82…`, then orange/512
keeps `aaebc82…` (before: it switched to `864037c…`).

Live account page with the new `hsw-account.min.js`: notifications page bell present with 1 unread →
press «خواندن همه» → bell element and unread rows both 0 without a reload.

## Build

Version 1.11.301 in the header, `HSW_VERSION`, `readme.txt`, changelog and this note; `.min` rebuilt.
