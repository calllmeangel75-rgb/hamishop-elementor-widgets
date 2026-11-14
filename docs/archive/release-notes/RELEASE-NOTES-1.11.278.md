# HamiShop Elementor Widgets — Release 1.11.278

**Cache, verification and release-integrity release** — `/complete/` is no longer
served from a page cache, the billing-email form is processed before output with
a real throttle, and the ZIP is reproducible with LF line endings.

## Fixed

- **Private route pages are always uncacheable (`HSW_Cache_Guard`):** The cache
  guard only knew Elementor widget names (`hsw-complete-`, `hsw-checkout-`, …).
  The pages created by **HamiShop → Setup** use shortcodes, so a guest could get
  a cached `/complete/` response — including another visitor's order key in the
  URL — from LiteSpeed, WP Rocket or a CDN.
  - Shortcode needles added: `[hamishop_complete`, `[hamishop_payment`,
    `[hamishop_pay`, `[hamishop_account`, `[hsw_account`.
  - A request with both `?order=` and `?key=` is private on any page.
  - Result: `Cache-Control: no-store, private` and `X-HSW-Private: 1` for
    guests, owners and shop managers, on every request.
- **Billing-email verification moved to `template_redirect`:** The form was
  processed while the completion widget rendered. Output could already have
  started, so `setcookie()` silently failed on some hosts, and a real 429 was
  impossible. The POST is now handled on `template_redirect` priority 99:
  - success → `303 See Other` (PRG) to the same URL and the verification cookie
    (`HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS, 2 days);
  - failure → the error is stored and the page renders it without a cookie.
- **Verification throttle (`HSW_Throttle`):** bucket
  `verify_order:{order_id}:{ip}` allows 5 failed attempts per 600 seconds; the
  sixth returns HTTP 429 with `Retry-After`. A per-order bucket
  (`verify_order_all:{order_id}`, 25 failures / 600 seconds) stops an attacker
  from rotating IPs. Only failures count; a correct email clears both the
  shared bucket and the current IP's bucket.
- **Real client IP behind Nginx → LiteSpeed:** `HSW_Throttle::ip()` trusted only
  Cloudflare/ArvanCloud ranges. When Nginx forwards to LiteSpeed on the same
  host, `REMOTE_ADDR` is loopback, so every customer shared one bucket and five
  bad guesses from one person locked the shop. Loopback (`127.0.0.0/8`,
  `::1/128`) is now trusted by default; other proxies are added through
  `hsw_trusted_proxies`. Two forwarded client IPs produce two independent
  buckets (covered by the regression test).
- **Only `hsw_verify_email` is accepted:** the generic `email` field belongs to
  newsletters/comments on the same URL and no longer activates verification.
- **Orders without a billing email:** email verification can never succeed.
  The completion card now says «برای مشاهده وارد حساب کاربری شوید» with a login
  link and does not render the email form.
- **Documentation:** the widget count is consistently **63** (16 main widgets +
  47 parts); «اصلاح Bug باگ» was removed from the readme.

## Build

- `tools/build-release.py` normalizes text files to LF and writes deterministic
  ZIP entries (fixed timestamp, sorted paths), so the archive can be rebuilt
  byte-for-byte from a checkout.
- The 1.11.278 ZIP is committed under `release-downloads/` and attached to the
  annotated `v1.11.278` GitHub release with the same SHA-256.
- Root-level `release-notes-1.11.274.md` and `release-notes-1.11.276.md` were
  removed; the maintained copies live in `docs/archive/release-notes/`.

## Tests

- `tests/qa/order-auth-wp.php` covers attempts 1–6, an independent second IP,
  bucket clearing after success, the `hsw_verify_email`-only rule, empty billing
  email, and the Secure/HttpOnly cookie path.
- `tests/qa/cache-guard-private-wp.php` covers the complete/payment/account
  shortcode pages, Elementor data, a plain page, and `?order=&key=`.
- Both are wired into the `wp-integration` CI job.
