# HamiShop Elementor Widgets — Release 1.11.303

The CSS rebuild step after an update was transferring Elementor blobs in batches.

## What Query Monitor showed

While updating 1.11.301 → 1.11.302, `HSW_Upgrader->step_post_css()` ran this on `admin_init`:

```sql
SELECT post_id, meta_value FROM wp_postmeta
WHERE meta_key = '_elementor_data' AND post_id > 5334
ORDER BY post_id ASC LIMIT 40
```

Two problems, both structural:

- **`ORDER BY post_id` on a `meta_key` filter** forces a filesort of every Elementor row on the
  site, for every batch — the row count in EXPLAIN is the whole `_elementor_data` population, not 40.
- **`meta_value` is the serialised page tree.** Up to 40 of them per batch means megabytes of
  LONGTEXT handed to PHP at once, per tick, on the admin page the owner is waiting on.

## Fixed

The step now walks `meta_id` and asks MySQL the question instead of PHP:

```sql
SELECT meta_id, post_id FROM wp_postmeta
WHERE meta_key = '_elementor_data' AND meta_id > %d
ORDER BY meta_id ASC LIMIT 40

SELECT post_id, LENGTH(meta_value) AS bytes FROM wp_postmeta
WHERE meta_id IN ( … ) AND meta_value LIKE '%hsw-%'
```

- `meta_id` order matches the `meta_key` index, so the batch query is a range scan that never
  touches a blob; the cursor advances with the batch, so a page without HamiShop costs nothing.
- The «does this page use HamiShop?» test (`LIKE '%hsw-%'`) and the oversized-page guard
  (`LENGTH(meta_value)`) are evaluated by the database; only matching ids travel back.
- A job already running before this release carried a `post_id` in `cursor`; on the first tick it is
  converted with one `MAX(meta_id)` lookup, so nothing is re-scanned from the beginning.

## Measured (local MariaDB, 1,987 Elementor rows of 40 KB)

| Query | Rows to client | Bytes sent | Time |
| --- | --- | --- | --- |
| old `post_id` / `meta_value` | 40 rows + blobs | **1,600,792 B** | 2.7 ms |
| new `meta_id` ids-only | 40 ids | **778 B** | 1.0 ms |

`php tools/ci-checks.php` now has check 6g, which fails if a blob batch query or a `post_id` cursor
comes back. 27 checks, harness (118 classes) and 56 unit assertions: all clean.
