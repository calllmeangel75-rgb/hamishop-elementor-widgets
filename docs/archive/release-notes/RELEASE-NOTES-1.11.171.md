# HamiShop Elementor Widgets 1.11.171 - release notes

Release date: 2026-10-30

Documentation, cleanup and one audit pass. No feature changed behaviour, so this
release is safe to drop on top of 1.11.170.

## Documentation

- **`TECHNICAL-BRIEF.md` (new)** — the reference document for any programmer
  joining the project: the seven design invariants, directory map, boot sequence,
  class inventory by responsibility, the widget system (including the exact steps
  to add one), the token cascade, the asset matrix with real sizes, the data
  model (`hsw_*` options, two tables, one post type, transients, crons), six
  request flows as diagrams (loop, archive, checkout, account, invoice, telegram
  poller), the security matrix, measured performance, i18n rules, the test
  toolbox, extension points, and an honest list of gaps.
- **`MARKETING.md` rewritten** — personas, per-stage sales arguments, proof
  numbers, objection handling, a 10-minute demo script, packaging suggestions,
  keyword/channel list, and an explicit "claims we must not make" table.
- **`README.md`** — two factual corrections: the font paragraph (the base font
  style ships with every widget at ~15 KB min, it is not "inherited with
  nothing loaded") and the Lighthouse paragraph. Stale section version removed.

## Uninstall completeness

`uninstall.php` was written before the invoice, telegram poller, 404 page and
free-shipping modules existed, so deleting the plugin left them behind. It now
removes 22 more options (`hsw_invoice_enabled`, `hsw_invoice_mono`,
`hsw_tg_offset`, `hsw_tg_poll_ready(_hash)`, `hsw_telegram_contacts`,
`hsw_db_version`, `hsw_oos_last`, `hsw_jalali_on`, `hsw_free_shipping_*`,
`hsw_custom_404_enabled` + `hsw_404_*`, `hsw_wallet_db`, `hsw_htaccess_backup`,
`hsw_complete_page_id`, `hsw_loop_version`, `hsw_add_feedback`) and clears the
`hsw_tg_poll` cron.

The wallet ledger table is **kept on purpose** — it is the record of customer
credit; deleting a plugin must not erase money that was already paid in. That
decision is now written down in the file itself.

## Dead code removed

A token-level scan (definition vs. references, then a manual pass for dynamic
calls) found seven methods with no caller at all:

| File | Method |
|---|---|
| `class-hsw-account.php` | `order_tone()` |
| `class-hsw-pay-base.php` | `chosen_gateway_id()`, `coupons_total()`, `demo_wallet_balance()`, `demo_wallet_balance_m()`, `demo_free_note()`, `demo_free_note_m()` |
| `class-hsw-telegram-poller.php` | `deactivate()` (the deactivation hook clears the cron directly) |

Deliberately **kept**, because they only *look* unused:

- `ajax_otp_send` / `ajax_otp_verify` / `ajax_2fa_*` — registered dynamically by
  `add_action( 'wp_ajax_' . $action, array( $this, 'ajax_' . … ) )`.
- `HSW_Wallet_Gateway::payment_fields()` — WooCommerce calls it.
- `HSW_Style_Kit::style_size_var()` — documented widget-authoring API in
  `README.md`.
- `enabled_channels()`, `mark_html()`, `sanitize_slug()`, `current_url()`,
  `color_field()` — small helpers with public/private visibility that a child
  theme or widget author may call; removing them buys little and risks breakage.

## Copy fixes

- Plugin description in the plugins list had two typos («محصولل»، «سسبک»).
- `README.md` font and Lighthouse paragraphs contradicted the code.

## Verification

- Smoke run over 26 surfaces (home, shop, category, search, cart, checkout,
  complete, 404, six account screens, plugin settings ×3, ticket list, order
  list, order edit): every status as expected, no PHP error in any body,
  `debug.log` line count unchanged (delta 0).
- PHP lint: 126 files, 0 failures. `tests/harness.php`: ALL CLEAN.
- Related probes still green: invoice (19 checks), header mobile, archive
  filters, support flow, loop spec, palette cascade.

## What this release does **not** fix (known, on purpose)

Documented in `TECHNICAL-BRIEF.md` §16: no CI pipeline, no PHP unit tests for
pure helpers (Jalali/price/stock), server-side PDF is intentionally out of scope,
the icon set is a curated subset, category is a navigation link rather than a
removable chip, wallet top-up stays closed when no gateway is enabled, and the
bundled font CSS always ships with a widget.
