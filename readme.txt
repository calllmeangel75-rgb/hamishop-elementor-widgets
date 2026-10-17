=== HamiShop Elementor Widgets ===
Contributors: hamisys
Tags: elementor, woocommerce, widgets, shop, product, cart, checkout, rtl, persian
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

سبک‌ترین ویجت‌های فروشگاهی ووکامرس برای المنتور — کاملاً فارسی و RTL.

== Description ==

HamiShop Elementor Widgets adds five performance-focused WooCommerce widgets to Elementor:

* **HamiShop Loop Item** — one fully-customizable product card. Source: current loop product, hand-picked products (AJAX search), or a single product. Every section (badge, image, categories, title, rating, excerpt, price, add-to-cart) can be toggled and styled.
* **HamiShop Archive** — products grid for Elementor Product Archive templates (current archive query) or a custom query anywhere (categories, tags, on-sale, featured). Numbered pagination, "Load More" (REST, no page reload), category pills, result count and ordering.
* **HamiShop Single Product** — complete product page: gallery with thumbnails (Elementor lightbox), badges, title, rating, price, excerpt, meta, native WooCommerce add-to-cart form, custom tabs (description / attributes / reviews), related & upsell grids.
* **HamiShop Cart** — WooCommerce's battle-tested cart with section toggles (coupon, totals, cross-sells) and deep styling of the native markup.
* **HamiShop Checkout** — WooCommerce's native checkout with coupon & order-notes toggles and full styling (two-column or one-column layout).

Performance first:

* CSS/JS load **only** on pages where a HamiShop widget is present.
* Zero jQuery, zero icon fonts, zero external requests on the frontend. Inline SVG icons + text stars.
* Products picker control is AJAX — the editor never loads your whole catalog.
* Optional Persian digits conversion and a WooCommerce block-CSS remover.
* RTL-safe logical CSS properties everywhere.

= Requirements =

* WordPress 6.0+
* WooCommerce 7.0+
* Elementor 3.5+

== Installation ==

1. Upload the `hamishop-elementor-widgets` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Open Elementor and look for the **HamiShop — WooCommerce** category.
4. Configure extras in **Settings → HamiShop**.

== Frequently Asked Questions ==

= Does it work with Elementor free? =
Yes. The products picker control is built-in — no Elementor Pro required. (Product Archive / Single Product templates via Theme Builder need Elementor Pro, exactly like any theme-builder usage.)

= Will it slow down my shop? =
No — assets are conditionally loaded and tiny. The frontend JS is ~2.3KB minified, and most styling is generated inline by Elementor.

= Are the cart/checkout safe? =
Yes — they render WooCommerce's own markup, so coupons, AJAX updates, shipping calculation and all payment gateways keep working.

== Changelog ==

= 1.0.0 =
* Initial release: Loop Item, Shop Archive, Single Product, Cart and Checkout widgets, AJAX products picker, Load More via REST, Persian digits, RTL-first styling.
