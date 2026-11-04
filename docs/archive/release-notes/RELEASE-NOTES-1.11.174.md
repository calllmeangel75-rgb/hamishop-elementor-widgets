# HamiShop Elementor Widgets 1.11.174 - release notes

Release date: 2026-10-30

A peer review of the plugin (from a developer friend) guessed at a list of risks.
Most were already handled; four were real, and this release fixes them plus the
documentation drift the review exposed.

## 1. Wallet could go negative under concurrency (real, fixed)

`debit()` read the balance, compared it, and then subtracted unconditionally:

```php
if ( self::balance( $user_id ) < $amount ) { return error; }   // SELECT
self::apply( $user_id, -$amount );                             // UPDATE, no guard
```

Two checkouts paying from the same wallet at the same moment could both pass the
read. The guard now travels into the statement itself:

```sql
UPDATE wp_usermeta
SET meta_value = CAST(meta_value AS DECIMAL(18,2)) + -400.00
WHERE user_id = 20 AND meta_key = '_hsw_wallet_balance'
  AND CAST(meta_value AS DECIMAL(18,2)) >= 400.00
```

Zero affected rows means "insufficient" — there is no window left between the
check and the write. Verified: seed 1000 → debit 400 = 600; debit 5000 →
`hsw_wallet_short` with the balance untouched; debit the exact remaining 600 → 0.

## 2. Balance and statement could disagree (real, fixed)

The ledger insert result was ignored. If the insert failed, the balance had
already moved and the customer's statement was missing a row. The write now
reverses the balance and returns an error instead, so the wallet always holds
exactly what the statement shows.

## 3. Top-up could be credited twice (real, fixed)

`settle()` was check-then-set:

```php
if ( $order->get_meta( META_DONE ) ) { return; }   // check
HSW_Wallet::credit( … );                           // credit
$order->update_meta_data( META_DONE, now );        // set
```

`woocommerce_payment_complete`, `order_status_processing` and
`order_status_completed` all call it, and a retry can overlap another request.
The block is now wrapped in a MySQL named lock (`GET_LOCK('hsw_topup_<id>', 5)`),
the flag is re-read **inside** the lock, and the flag is only set when the credit
actually succeeded. Verified: two consecutive `settle()` calls credit 25000 once.

## 4. The throttle table grew forever (real, fixed)

Every distinct IP and phone left a row behind; nothing pruned them. A daily
`hsw_throttle_prune` event now deletes buckets that expired more than a day ago
(cleared on deactivation and uninstall). Verified: a three-day-old row is gone
after one prune.

## 5. Countdown followed the visitor's clock (real, fixed)

`data-hsw-end` is a server UNIX timestamp, but the script compared it with
`Date.now()` — a device five minutes behind showed five extra minutes. The markup
now also carries `data-hsw-now` (the server's `time()`), and the script keeps one
offset, measuring only elapsed local time.

## What the review got right about the documentation

- The `!important` figure in the README was **314** (measured at 1.11.154); it is
  **316** today.
- The asset reduction table was stale: the sources are **632 KB CSS → 468 KB**
  minified (26 %) and **248 KB JS → 156 KB** (37 %), not 427→314 / 123→76.
- `WIDGETS-REFERENCE.md`, `STAGING-CHECKLIST.md` and one README section still
  said version **1.11.154**; all updated to 1.11.174.
- Widget count: **62 total**, of which **15 "general" widgets are enabled on a
  fresh install** and 47 are page parts (off until you switch them on). That gap
  is the most likely source of the «62 vs fewer» confusion, so it is now stated
  instead of implied.

## What the review got wrong (checked, not changed)

- **HPOS**: `FeaturesUtil::declare_compatibility( 'custom_order_tables', … )`
  exists in `HSW_Plugin`, and orders are read through `$order->get_meta()`.
- **Object cache**: the balance write calls `wp_cache_delete()` **and**
  `clean_user_cache()`, so Redis/Memcached does not serve a stale balance.
- **Cache plugins**: `HSW_Cache_Guard` already sets `DONOTCACHEPAGE`,
  `DONOTCACHEOBJECT`, `DONOTCACHEDB`, `nocache_headers()` and the LiteSpeed
  no-cache action on cart/checkout/pay/complete/account pages and filtered
  archives, at `template_redirect` priority 1.
- **Loop cache staleness**: version bumps on product create/update/delete,
  stock and stock-status (product *and* variation) and `woocommerce_scheduled_sales`,
  plus WooCommerce's own `WC_Cache_Helper` transient version.
- **`ob_start` for font-display**: there is no output-buffer rewrite. `ob_start`
  in the account panel only captures short render fragments.
- **`hsw-fonts.css` is not "just the font"**: it is the 836-line shared base
  (icon sizing, toast, pagination arrows), which is why a widget cannot simply
  drop it. Splitting it is a legitimate future optimisation, not a bug.

## Validation

- PHP lint: 128 files, 0 failures. Harness: 117/117 files, ALL CLEAN.
- `tools/ci-checks.php`: 16/16 after the doc updates.
- Wallet/top-up/throttle checks above run against the live dev site, then the
  wallet balance was restored to its original value (139439).
