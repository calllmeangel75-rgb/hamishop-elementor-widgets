# HamiShop Elementor Widgets 1.11.163 - release notes

Release date: 2026-10-29

Priority 2 (account panel, wallet, support) has now been exercised on the live
test store. One real dead end in the wallet top-up UI was found and fixed; the
ticket flow was verified end to end.

## What was verified

| Screen | Result |
|---|---|
| `/my-account/` (dashboard) | loads, menu + stats render, amounts Persian, no console error |
| `/my-account/orders/` | 10 orders, Jalali dates, Persian totals |
| `/my-account/view-order/{id}/` | invoice, address, totals, payment block, Persian digits |
| `/my-account/edit-address/billing/` | native countries/states form renders; the address saved during checkout is present |
| `/my-account/edit-account/` | native form (name, display name, email, password fields) renders |
| `/my-account/wallet/` | balance `۱۳۹,۴۳۹ تومان`, 3 ledger rows, no console error |
| `/my-account/support/` (+ `/new/`) | chat list, new-ticket form, ticket thread |
| `/my-account/notifications/`, `/wishlist/` | load, no console error |
| `/my-account/` logged out | login form + register tab, 390px wide without horizontal overflow |

## Fix: wallet top-up dead end when no online gateway exists

`HSW_Wallet_Topup::gateways()` blocks `cod` on purpose (COD moves the order to
`processing`, and this plugin treats that status as paid — so COD would credit a
wallet for free). On a store whose only gateway is COD the wallet page still
rendered the top-up form, and pressing it returned
«درگاه پرداخت انتخاب‌شده در دسترس نیست. لطفاً درگاه دیگری انتخاب کنید.» — which is
wrong when the shop has **no** eligible gateway at all.

- `HSW_Wallet_Topup::create_order()` now distinguishes the two cases and returns
  «در حال حاضر هیچ درگاه پرداخت آنلاینی روی سایت فعال نیست…» for an empty list.
- The wallet panel does not render the top-up form when no gateway can charge;
  it shows the explanatory note next to the balance and the ledger instead.
- A store with a real PSP is unaffected: one gateway is picked automatically,
  several gateways still render the picker.

## What was not testable on this store

- **Online top-up and wallet payment** need a virtual gateway (Zarinpal, IDPay,
  …). With only COD enabled, the wallet can still be credited by the shop owner
  (admin panel / confirmed bank transfer), and the ledger from the earlier real
  top-up (`+ ۲۰۰,۰۰۰` for order #13490) confirms the credit path works.
- **Password reset e-mail** depends on a working mail transport on the server.

## Validation run for 1.11.163

- PHP lint: 122 files, 0 failures. `tests/harness.php`: 74 classes, clean.
- `tests/wallet-topup-probe.js` (new): reads the balance, submits the top-up
  form and reports the REST answer; before the fix it produced
  `http 400 /wp-json/hsw/v1/account/wallet/pay` with the misleading message.
- `tests/support-ticket-probe.js` (new): creates a ticket (captcha solved by
  reading the Persian question), opens
  `/my-account/support/{id}/`, posts a customer reply and confirms the bubble
  renders — tickets `13618`/`13619`, `errors: none`.
- `debug.log` empty after the account, wallet and ticket runs.
- Account audit: every `.amount`/total element returned `latinDigitAmounts: []`.

## Test data created

- Orders `13616`, `13617` (purchase-flow probe), tickets `13618`, `13619`,
  wallet ledger rows from the earlier tests. All can be deleted before launch.
