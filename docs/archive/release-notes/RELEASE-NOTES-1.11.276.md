# HamiShop Elementor Widgets — Release 1.11.276

**Bugfix release** — completes the 1.11.274 fixes and adds the regression coverage
that was missing from it.

## Fixed

- **Thank-you hooks fire once, for every renderer.** `HSW_Thankyou` is now the
  only place that fires `woocommerce_before_thankyou`, `woocommerce_thankyou_{method}`
  and `woocommerce_thankyou`. The guard is keyed by order and shared by
  `HSW_Complete_Base::gateway_notes()` and `HSW_Checkout_Base::render_order_received()`,
  so both screens can render in the same request without counting a purchase
  twice. When the thank-you block has already started (`did_action()`), or when
  WooCommerce's own checkout shortcode/block owns the native `order-received`
  page, the guard stands down and leaves the hooks to core.
- **An order without a payment method still announces the purchase.** The old
  `gateway_notes()` returned before reaching the public hook when
  `get_payment_method()` was empty; only the gateway hook is now skipped.
- **Ticket images honour a custom uploads location.** Uploads are resolved with
  `wp_upload_dir()`, so sites that move `wp-content` or `uploads` (the test shop
  keeps uploads in `/storage`) no longer write screenshots to a folder that no
  backup or retention job scans. Images written by 1.11.273–1.11.275 under
  `wp-content/uploads/` are still found and served.
- **Stored file names are unguessable and safe in headers.** New uploads get a
  random 12-character prefix and `sanitize_file_name()`, and the download
  response builds `Content-Disposition` from the sanitized name instead of
  `esc_html()` (which does not protect a header).
- **Image responses are explicitly uncacheable.** `nocache_headers()` replaces
  the previous `Cache-Control: private, max-age=…`, so LiteSpeed and any other
  page/CDN cache cannot keep a private screenshot after the link expires.
- **Release-note accounting corrected.** 1.11.273 now records that members 6
  and 7 of the seven reported items belong to later releases, and its
  REST-route sentence is marked as superseded by 1.11.274; 1.11.274 no longer
  renumbers the two acceptance bugs as claims 1–2.

## Added

- `tests/qa/ticket-image-wp.php` — owner and staff allowed; other customer,
  guest, bad signature, expired link (`> 7 days`), wrong post type, missing
  meta and missing file all rejected; legacy `wp-content/uploads` path still
  resolves; the generated URL must use `admin-post.php`, not REST.
- `tests/qa/thankyou-hook-wp.php` — the hook pair fires exactly once with the
  order id, the second render in the same request is a no-op, a different order
  fires again, the guard stands down when the block already started, and an
  order with no gateway still fires the public hook.
- `tests/qa/ticket-image-browser-probe.js` — Chromium probe for a live site:
  owner decodes the image, staff gets 200, another customer and a guest get
  403, tampered and stale links get 403, and the response is `no-store`.
- Both PHP tests run in the `wp-integration` CI job.

## Notes

- No database schema, option or stored-order changes.
- Direct access to `hsw-ticket-images/` is still denied by `.htaccess` on
  Apache. nginx ignores `.htaccess`; the file now says so and gives the
  equivalent `location` rule for hosts that need it. The signed link plus the
  unguessable file name remain the primary gate.
- The live browser probe needs real accounts and is run manually against the
  staging shop, not in CI (same rule as the other `tests/*.js` probes).
