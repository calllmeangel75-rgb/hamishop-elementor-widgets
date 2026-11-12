# HamiShop Elementor Widgets — Release 1.11.274

**Bugfix release** — fixes for two critical bugs reported against v1.11.273 on a real staging site.

## Fixed

- **Ticket images returned 403 for all users (Claim 1):** The image-serving route was moved from `REST hsw/v1/tickets/{id}/image` to `admin-post.php?action=hsw_ticket_image`, so WordPress cookie authentication is respected (no nonce required). The ticket owner and staff (`manage_woocommerce`) can view the image; all others get 403. Signature comparison now uses `hash_equals` instead of `===`. Staff capability narrowed from `edit_posts` to `manage_woocommerce`. Link expires after 7 days.
- **Public `woocommerce_thankyou` hook not firing on `/complete/` (Claim 2):** Added `do_action( 'woocommerce_thankyou', $order_id )` after method-specific hooks in `HSW_Complete_Base::gateway_notes()` and `HSW_Checkout_Base::render_order_received()`, protected by a static guard so it fires exactly once per request per order and does not double-fire on WooCommerce's native order-received page.

## Notes

- Fixes claims 1–2 of 7 reported by the shop owner (claims 3–5 were fixed in v1.11.273; claim 6: QA scripts pending execution on a disposable WP install).
- Wallet arithmetic and the ledger are unchanged.
- Regenerated `.pot` file for generated-files-in-sync CI check (no new translatable strings introduced).
