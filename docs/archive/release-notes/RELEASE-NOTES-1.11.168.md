# HamiShop Elementor Widgets 1.11.168 - release notes

Release date: 2026-10-29

Support ticket flow, end to end. No logic changed in this release — the point
was to stop guessing and prove every step, including the staff side that had
never been exercised before.

## What was exercised

| Step | Where | Result |
|---|---|---|
| Create a ticket (department, priority, captcha) | customer | `#T-13628`, status «در انتظار پاسخ», 1 bubble |
| Customer reply | customer | appended, 2 bubbles, status stays «در انتظار پاسخ» |
| Staff reply | wp-admin ticket screen | saved (`hsw_replied=1`), appears in the thread |
| Status after a staff reply | both sides | «پاسخ داده شده» (answered) |
| Customer sees the answer | customer | 3 bubbles, status badge «پاسخ داده شده» |
| Notification | `/my-account/notifications/` | «پاسخ جدید در تیکت شما» created |
| Close by the customer | customer | asks «بسته شود؟», then status «بسته شده», composer replaced by the closed notice |
| Reopen by staff | wp-admin | status back to «در انتظار پاسخ» |
| Re-close by staff | wp-admin | «بسته شده» |
| Customer after reopen | customer | composer is back and the status reads «در انتظار پاسخ» |
| Console / network | both | no errors, no failed requests |

## Notes

- Closing is intentionally guarded by `window.confirm()`. The first probe run
  "failed" only because Playwright dismisses dialogs by default — the button
  was working; the probe now accepts the dialog and asserts that it appeared.
- A closed ticket rejects further customer replies server-side (with a notice)
  independent of the UI.
- Test hygiene: the QA admin password was temporarily set to a known value and
  then restored to its original hash (verified byte-for-byte in the database);
  helper scripts were removed. Ticket `#T-13628` is left behind as evidence and
  can be deleted from wp-admin.

## Validation

- `tests/support-flow-probe.js` (new): 17 checks across two browser contexts
  (customer `sks`, staff `hsw_test_admin`) — `RESULT: clean`.
- `tests/support-ticket-probe.js` (1.11.163): customer create + reply still clean.
- PHP lint: 122 files, 0 failures. `tests/harness.php`: 74 classes, clean.
