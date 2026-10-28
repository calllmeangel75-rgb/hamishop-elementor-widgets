# HamiShop Elementor Widgets 1.11.157 - release notes

Release date: 2026-10-28

## Changes in 1.11.157

- Added a **source selector** to «ویژگی‌های شاخص کالا»: automatic (WooCommerce attributes + short description), WooCommerce attributes only, short-description table/text only, or manual repeater only.
- Added a **row limit** control where `0` means «همه» (all rows). The default is now all rows instead of the previous six-row cap.
- Added a dedicated parser for HTML tables in the short description. Header rows are skipped, each two-column row becomes one `نام: مقدار` feature, and table cells are no longer split into separate bullets.
- Kept the bullet/line fallback for short descriptions that are not tables, and kept the manual repeater as the fallback when the selected source has no data.
- Reordered the product tabs to **گارانتی و خدمات → مشخصات فنی (WooCommerce Attributes) → بررسی تخصصی کالا → دیدگاه‌ها**.
- The first visible tab is now the active tab, so the tab strip and the rendered panel cannot get out of sync when tabs are disabled.
- Fixed the cart drawer jump: the scroll-lock compensation now keeps the page content's x/width unchanged when the scrollbar disappears, the panel no longer scales, and focus restoration no longer scrolls the page.
- Removed the `scale(.985)` from the closed cart panel. The panel now animates only with `transform`/`opacity`, preserving its width and avoiding a visible edge shift while opening or closing.
- In the RTL store the cart drawer enters left-to-right and leaves right-to-left along the same path, with the existing soft opacity fade.
- Cart focus restoration now uses `preventScroll`, so closing the drawer does not scroll the page to the header.
- Updated the plugin version, readme stable tag/changelog, translation template, minified assets, and release archive metadata.

## Validation run for 1.11.157

- PHP lint passed for all modified PHP files (`class-hsw-product-base.php`, the features widget, the tabs widget, and the main plugin file); `node --check` passed for the header script.
- The source/limit matrix was exercised against real products:
  - Product `13609`: `auto` = 6 rows, `short_description` = 6, `attributes` = 0, `manual` = 0; `short_description` with limit 2 = 2; the table parser alone = 6.
  - Product `روغن اصل Syrup`: `auto` = 5 rows, `attributes` = 2, `short_description` = 5, `manual` = 0; `attributes` with limit 2 = 2.
- Live product `13609` verified: the desktop feature list renders all six table rows (`کد نقشه` through `تعداد رنگ`), with no header row.
- Live product `روغن اصل Syrup` verified: the tab strip is ordered **گارانتی و خدمات → مشخصات فنی → بررسی تخصصی کالا → دیدگاه‌ها**, the first tab is active, and clicking «مشخصات فنی» shows the real WooCommerce attributes (`جنس`, `کشور تولید کننده`, `رنگ`).
- Cart drawer verified in the RTL storefront: closed panel `x=-420`, width `420`, opacity `0`; open panel `x=0`, width `420`, opacity `1`; the page body/main stay at `x=0`, width `851` while the scrollbar is locked (`padding-right: 15px`), and focus moves to the close button and back to the cart opener. The headless tab throttled transition time, so final states were confirmed by finishing the active animations; the transition itself is transform/opacity only, with no scale and no width change.
- Browser console reported no errors after the cart and tab checks.
- Minified CSS/JS and the POT file (3145 strings) were rebuilt and synced; the release ZIP contains the expected plugin files and no development files.
