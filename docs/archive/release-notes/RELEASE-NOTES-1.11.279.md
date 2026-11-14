# HamiShop Elementor Widgets — Release 1.11.279

**Throttle ordering fix** — a full verification bucket now blocks the request
before the submitted address is compared, so a correct email cannot bypass the
limit.

## Fixed

- **`HSW_Order_Auth::process_submission()` checks the counters first.**
  Both `verify_order:{order_id}:{ip}` and `verify_order_all:{order_id}` are read
  with `HSW_Throttle::peek()`. `peek()` is read-only: this check never counts as
  an attempt.
  - If either bucket has reached its limit and its window is still open, the
    result is `throttled` with the larger `Retry-After`, **even when the email is
    correct**. The completion handler answers HTTP 429.
  - Failed attempts still increment the counters exactly where they did before.
  - A correct email inside an open window clears the shared bucket and the
    current IP's bucket as before.
- **Regression test updated (`tests/qa/order-auth-wp.php`):**
  - Five wrong emails → error.
  - Sixth wrong email → throttled.
  - Correct email on the same exhausted IP → throttled (and no cookie).
  - A second IP is independent: its wrong attempt is an error; its correct email
    succeeds and clears the shared + second-IP buckets, while the first IP stays
    locked.
  - After the first IP's window is simulated as expired, its correct email is
    accepted again.

## Build

- Version 1.11.279 in the plugin header, `HSW_VERSION`, `readme.txt` Stable tag,
  changelog and this note.
- ZIP built by `tools/build-release.py` with LF text files and deterministic
  entries, committed under `release-downloads/`.
- Annotated tag `v1.11.279`; release asset is the same ZIP. SHA-256 recorded from
  the committed file and recomputed from the downloaded release asset.
