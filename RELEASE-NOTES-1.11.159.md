# HamiShop Elementor Widgets 1.11.159 - release notes

Release date: 2026-10-29

## Changes in 1.11.159

- Reordered the product tabs to **بررسی تخصصی کالا → گارانتی و خدمات → مشخصات فنی → دیدگاه و نظرات خریداران**. The first visible tab (review) is active by default, and each tab still toggles the matching panel.
- Moved the mobile «تب‌های بیشتر» chevron 10px higher (`.hsw-ptabs__more svg`), as requested.
- Changed the related-products default source to **هم‌دسته (با بازگشت به دسته والد)**. The query starts with the product's direct `product_cat` terms, excludes the current product, and only walks up one level at a time when the current level has no other products.
- Removed the minimum-count behavior from related products. The count control now accepts `1`, and a category with only one or two available products renders exactly those products instead of hiding the section.
- Kept «محصولات مرتبط ووکامرس» as an explicit source option for stores that want WooCommerce's native related selection.
- Extended the mobile cart-sheet related-products fallback to the same category helper, so it also climbs to the parent category when WooCommerce has no related product.
- Excluded the current product from the «پرفروش‌ترین‌ها» source as well.
- Fixed the cart-drawer close button colour: it now uses the header text token (`--hsw-hd-text`) instead of inheriting the product-page `--hsw-on-surface-variant` value.
- Swapped the cart-drawer quantity buttons so the increase button sits on the right and decrease on the left in RTL (`hsw-hd__cartqty`).
- Fixed the mobile cart drawer stacking: while open, the header stacking context is raised to `10021`, above the mobile sticky purchase bar (`9995`), so the drawer body and checkout button are no longer covered. The raised layer stays through the closing animation.
- Confirmed that the «موجود در انبار تهران» and «گارانتی ۱۸ ماهه» lines below the buy button are editable: they are the `assure_items` repeater of the purchase widget (Elementor → صفحه کامل محصول → ۶. جعبه خرید → خطوط اطمینان).
- Updated the plugin version, readme stable tag/changelog, translation template, minified assets, release notes, and release archive metadata.

## Validation run for 1.11.159

- PHP lint passed for all modified PHP files (`class-hsw-products.php`, `class-hsw-rest.php`, the related widget, and the tabs widget).
- Live product `13609` (four-season product):
  - Tab strip order verified as **بررسی تخصصی کالا → گارانتی و خدمات → مشخصات فنی → دیدگاه‌ها و نظرات خریداران**.
  - The review tab is active on load; clicking warranty, specs, comments, and review switches both the active tab and the matching panel.
  - Related products rendered 7 cards, all from the product's `Dry-run` category.
  - The purchase assurance rows render «گارانتی ۱۸ ماهه همراه با کد فعال‌سازی رجیستری» and «موجود در انبار تهران - آماده پردازش و ارسال».
- Live product `13155` (child category «دستکش های A»): related products rendered 4 cards from the same category tree, including the parent-category products.
- Live product `13169` (2-product category «ابزار»): related products rendered exactly 1 card; no minimum of 2 was enforced.
- Live product `13159` (category tree with no other products): the related section is hidden because there is nothing to show; no empty shell is rendered.
- Mobile viewport test at 390px: the «more tabs» chevron is visible, and its computed transform is `matrix(1, 0, 0, 1, 0, -10)` — exactly 10px higher.
- Mobile cart drawer at 390px:
  - Opening the drawer raises the header to `z-index: 10021`; the sticky purchase bar stays at `9995` and no longer covers the panel.
  - `elementFromPoint` at the checkout button centre returns the checkout link, and points inside the sticky-bar area return drawer content while the drawer is open.
  - Closing the drawer keeps the raised layer during the slide-out and returns the header to `900` after the closing transition.
  - The close button computes to `rgb(15, 23, 42)` (`--hsw-hd-text`) instead of `rgb(69, 70, 78)`.
  - The quantity stepper renders the increase button to the right of the decrease button in RTL.
- REST fallback `/wp-json/hsw/v1/related?product=13169&limit=8` returns the single available same-category product.
- Browser console on the live product page reported no warnings or errors.
- Minified CSS/JS and the POT file (3146 strings) were rebuilt and synced; the release ZIP contains the expected plugin files and no development files.
