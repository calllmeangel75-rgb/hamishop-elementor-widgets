# HamiShop Elementor Widgets 1.11.167 - release notes

Release date: 2026-10-29

Shop archive filters, desktop and mobile: does «حذف فیلتر» remove a single
filter, and does «پاک‌سازی» really clear everything? Both were tested live, and
the test found one real bug on the way.

## The bug: picking a category wiped the other filters

The category rows in the sidebar / mobile sheet are **navigation links**, not
form inputs, and their URL came straight from `get_term_link()`. So:

1. turn on «فقط کالاهای موجود» → `?instock=1`
2. click a category in the tree → `/product-category/dry-run/` — **`instock` is gone**

Every other filter (attribute chips, price range) disappeared the same way,
because a term link knows nothing about query args.

`cat_link()` now builds the row URL with `HSW_Archive_Base::filter_url()`, which
keeps the current parameters and only clears `product_cat` /
`filter_product_cat` (the target path already fixes the category):

```
/product-category/dry-run/?filter_pa_color=black&instock=1
```

## What was verified (live, 1440 and 390)

| Action | Result |
|---|---|
| Apply «فقط کالاهای موجود» | `?instock=1` |
| Add an attribute chip | `?filter_pa_color=black&instock=1` (one value each) |
| Active tags row | one tag per filter (2), plus «پاک‌کردن» |
| Remove **one** tag | that parameter disappears, the other stays (`{instock:1}`) |
| Pick a category with filters active | category URL **keeps** both filters |
| «پاک‌کردن» (tags row) | URL has no filter params, tags row is gone, and the counter returns to «نمایش ۱ تا ۱۲ از ۲۵ کالا» — the unfiltered count |
| Sidebar «حذف همه» | same clean state |
| Mobile sheet: turn a filter off | `{}` — removed, not duplicated |
| Mobile sheet: two filters + «اعمال» | `?filter_pa_color=black&instock=1` (each once) |
| Mobile sheet «حذف همه» | all filter params gone |
| Console / network | no errors, no failed requests on either viewport |

## Notes

- «حذف همه» deliberately keeps non-filter parameters (`orderby`, `view`), so the
  chosen sort/view survives a reset. Only filter keys are removed.
- Category selection is navigation: it lands on the category archive with the
  remaining filters appended. That is also why a category cannot be removed by
  the «×» tag — its URL *is* the archive; use «حذف همه» to return to `/shop/`.
- The fix touches only `class-hsw-widget-archive-filters.php`; the filter/clear
  JS from 1.11.161 is unchanged.

## Validation

- `tests/archive-filter-remove-probe.js` (new): the 13 checks above on both
  viewports — `RESULT: clean`.
- `tests/archive-filter-probe.js` (1.11.161): still clean (single value per name).
- PHP lint: 122 files, 0 failures. `tests/harness.php`: 74 classes, clean.
- `debug.log` empty during the runs.
