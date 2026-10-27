# HamiShop Elementor Widgets 1.11.147 — release notes

This is a manual, client-ready release package. It contains the plugin, its
front-end assets, the bundled Vazirmatn subset, the GPL license and the POT
translation template. The license server/updater is intentionally **not** part
of this stage.

## Included in this release

- 62 Elementor widgets, including the composite page, account, cart, checkout,
  payment, blog and archive widgets.
- Global palette and per-widget style kit, with wrapper-level overrides taking
  precedence over the global `html:root` tokens.
- Branding, custom login appearance, security options, request blocker, wallet
  and account/support features already present in the plugin.
- `LICENSE` (GPL-2.0) and `languages/hamishop-elementor-widgets.pot`.

## Fixes completed for 1.11.147

- Wallet top-up mode and gateway selects now save to the registered
  `hsw_account_settings` option (previously they used the legacy
  `hsw_account` name).
- Test-only OTP/2FA/email-verification and support-email settings were turned
  off, and test messaging credentials were removed.
- Security checks confirmed the custom admin path, REST users restriction and
  sanitized external-request blocking work; the customer REST `/users/me`
  endpoint remains available.
- Final wallet, account, support, checkout, invoice and single-post checks were
  run against the live QA site.

## Required site configuration before enabling these features

- **SMTP:** configure and test SMTP before enabling OTP login, 2FA, email
  verification or support email notifications. Password login is enabled and
  works without SMTP.
- **Wallet funding:** the site currently has no enabled non-COD payment
  gateway. Enable a real online gateway for the wallet top-up gateway mode, or
  configure card-to-card details in the account settings. COD is intentionally
  rejected for wallet funding.
- **License/updater:** deferred to the final stage as requested.
