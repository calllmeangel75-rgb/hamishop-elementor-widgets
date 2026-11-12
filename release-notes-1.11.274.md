## HamiShop Elementor Widgets v1.11.274 — Bugfix Release

### Fixed
- **Ticket images returned 403 for all users (critical):** Moved image serving from REST `hsw/v1/tickets/{id}/image` to `admin-post.php?action=hsw_ticket_image`, so WordPress cookie authentication is respected (no nonce needed for `` tags or emailed links). Ticket owner and `manage_woocommerce` staff can view the image; all others get 403. Signature comparison now uses `hash_equals` (was `===`). Staff capability narrowed from `edit_posts` to `manage_woocommerce`. Link expires after 7 days; direct file URLs in `wp-content/uploads/hsw-ticket-images/` remain blocked by `.htaccess`.
- **Public `woocommerce_thankyou` hook not firing on `/complete/`:** Added `do_action( 'woocommerce_thankyou', $order_id )` after method-specific hooks in `HSW_Complete_Base::gateway_notes()` and `HSW_Checkout_Base::render_order_received()`, protected by a static guard so it fires exactly once per request per order and does not double-fire on WooCommerce's native order-received page. Analytics plugins (GA4, Meta Pixel, …) now receive purchase events.

### Minor
- Cleaned garbled characters in `readme.txt` changelog.
- Clarified release notes: fixes claims 1–2 of 7 reported by the shop owner (claims 3–5 fixed in v1.11.273; claim 6: QA scripts pending execution on a disposable WP install).

### ZIP
`hamishop-elementor-widgets-1.11.274.zip` (1.28 MB, 195 files)
