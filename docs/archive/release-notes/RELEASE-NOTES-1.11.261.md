# 1.11.261 — staging pre-release

- Avoid zero-value ledger entries when an unchanged wallet balance is saved. Existing ledger entries remain untouched; stale balance checks are retained.
- Disposable WP/WC tests cover no-op and real adjustments and stale-form rejection. Real browser testing remains required.
- Staging only. The COD loyalty prototype still has unresolved historical accounting, refunds and concurrency risks. Do not use in production.
