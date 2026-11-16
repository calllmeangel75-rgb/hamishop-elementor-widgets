# HamiShop Elementor Widgets — Release 1.11.299

**A variant that does not exist can no longer be "added".**

## What was wrong

Reported from a live install (WooCommerce 11.3, variable product with three attributes and
desktop + mobile copies of the same picker):

1. Choosing a colour with no matching combination correctly showed «—» and locked the button.
2. Clicking a chip in the **mobile copy** of the picker (for a value that was already selected in
   the desktop copy) cleared the lock while the price still read «—».
3. Clicking the now-unlocked «خرید آنلاین» posted `wc-ajax=add_to_cart` **without `variation_id`**.
   WooCommerce refused it, but the page followed the refusal's `product_url` — a silent reload —
   and a success toast («محصول با موفقیت به سبد خرید اضافه شد») could be on screen while the cart
   stayed empty.

## Fixed

- **A miss always locks the buy button.** `refreshVariant()` used to pass
  `!match && selectionComplete(...)` as the "dead" flag, so when the two picker copies disagreed
  about completeness the button unlocked with no price. It now passes `'nomatch'` or `'incomplete'`
  and `applyVariant()` locks in both cases; the flags travel as
  `data-hsw-unavailable="1" | "incomplete"` so the click can explain the right reason
  («این ترکیب از ویژگی‌ها موجود نیست» versus «ابتدا همهٔ ویژگی‌های محصول را انتخاب کنید»).
- **A refused add is explained, not redirected.** `addToCart()` now treats
  `res.error` / `res.success === false` as a failure: WooCommerce's own message is shown as an error
  toast, nothing is flashed as added, and the page stays where it is.
- The no-match toast now reads the product's own `data-hsw-no-match` text (the lookup missed the
  picker root, which carries `data-hsw-variants`, not `data-hsw-product`).

## Verified

- On the live product page, with the new `hsw-product.min.js` injected over the installed one:
  - silver + 1TB (no such combination): price «—», button `is-locked` + `aria-disabled="1"`; clicking
    the duplicate mobile chip leaves it locked; clicking the button sends **no request** and shows
    «این ترکیب از ویژگی‌ها موجود نیست.».
  - silver + 512 (a real combination): price ۶۱۵,۰۰۰,۰۰۰, button unlocked, request
    `product_id=1788&variation_id=1788&quantity=1`, cart count becomes ۱.
- `tools/ci-checks.php` gained check **6e** so a future edit that reintroduces the redirect or drops
  the lock fails CI (25 checks).

## Not tested on this release (needs a login)

Account panel, notifications and the customer invoice could not be exercised on the live site: no
test customer credentials were available and `wp user create` is switched off there. Those three
were verified on the local staging install in 1.11.297 / 1.11.298.

## Build

Version 1.11.299 in the header, `HSW_VERSION`, `readme.txt`, changelog and this note; `.min` rebuilt.
