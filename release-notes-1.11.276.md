## HamiShop Elementor Widgets v1.11.276 - Bugfix Release

### Fixed
- **Thank-you hooks fire exactly once, for every renderer:** `woocommerce_before_thankyou`, `woocommerce_thankyou_{method}` and `woocommerce_thankyou` now go through one shared guard (`HSW_Thankyou`) keyed by order. The complete page and the checkout card can both render in one request without double-counting a purchase; the guard stands down when the thank-you block already started or when WooCommerce's own checkout owns the native `order-received` page. An order with no payment method still fires the public hook.
- **Ticket images follow the configured uploads directory:** storage uses `wp_upload_dir()`, so a site with uploads outside `wp-content` (e.g. `/storage`) keeps screenshots where backups and retention jobs look. Images from 1.11.273–1.11.275 under `wp-content/uploads/` are still served.
- **Safer file handling:** random file-name prefix, `sanitize_file_name()` in the stored name and in `Content-Disposition`, and `nocache_headers()` on the image response.
- **Release notes corrected:** 1.11.273 no longer points at the removed REST route without saying it was superseded; the seven-item accounting is consistent across 1.11.273, 1.11.274 and this release.

### Added
- `tests/qa/ticket-image-wp.php` and `tests/qa/thankyou-hook-wp.php`, both wired into the `wp-integration` CI job.
- `tests/qa/ticket-image-browser-probe.js`, a Chromium probe for owner/staff/other/guest roles on a live site.

### ZIP
Built by the release workflow on the `v1.11.276` tag (`release-downloads/hamishop-elementor-widgets-1.11.276.zip`).
