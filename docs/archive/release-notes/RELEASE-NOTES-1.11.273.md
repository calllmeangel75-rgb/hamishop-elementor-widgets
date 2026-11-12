# HamiShop Elementor Widgets — Release 1.11.273

**Security hardening release** — fixes for claims 1–5 of the 7 claims reported by the shop owner. Claim 6 (regression scripts on a disposable WordPress install) and claim 7 (the two bugs found in acceptance testing) are recorded in the 1.11.274 and 1.11.276 notes.

## Security

- **AJAX comment recursion guard (Claim 1):** The `wp_die_ajax_handler`/`wp_die_handler` filter in `submit_comment()` now removes itself before answering (`wp_send_json_error()`), so a third-party `wp_die()` during comment submission can no longer recurse until PHP memory is exhausted. Non-buyer product reviews with "verified owners only" enabled return a single clean JSON error and save no review.
- **OTP/IP throttle behind non-Cloudflare proxies (Claim 2):** ArvanCloud edge ranges (Iran / Middle East) added to the default `hsw_trusted_proxies` list, so each visitor behind a CDN/IP proxy gets a distinct throttle bucket instead of sharing the 20-requests-per-hour OTP cap with every other visitor behind the same proxy. Extensible via the `hsw_trusted_proxies` filter.
- **Wallet top-up gateway allowlist (Claim 3):** New `hsw_wallet_topup_allowed_gateways` filter. When empty (default), the previous blocklist-only behavior applies (`cod` and `hsw_wallet` always blocked). When set, only listed gateways may fund a top-up.
- **Ticket images outside public Media Library (Claim 4):** Ticket screenshots are stored under `wp-content/uploads/hsw-ticket-images/<ticket>/` with `.htaccess` denying direct access, and served only through a signed, capability-guarded link (HMAC signature, valid 7 days). *Superseded in 1.11.274:* the link was moved from `GET hsw/v1/tickets/{id}/image?ts&sig` to `admin-post.php?action=hsw_ticket_image`, because a REST request carrying only cookies is treated as anonymous and an `<img>` cannot send a nonce.
- **Inbound email webhook (Claim 5):** Secret is read from the `X-HSW-Inbound-Secret` header (`secret` query param kept as fallback). The `$from` sender comparison is now case-insensitive via `strtolower`.

## Fixed

- Product review from a non-buyer with "verified owners only" enabled returns a single clean JSON error and saves no review (previously exhausted PHP memory).

## Notes

- Wallet arithmetic and the ledger are unchanged.
- Regression coverage: `tests/qa/topup-payment-guard-wp.php`, `tests/qa/auth-code-proof-wp.php` (must be run on a disposable WP/WC/Elementor install with `HSW_QA_DISPOSABLE=1`).
- Tested on disposable installs and in CI, not with a real bank gateway.
