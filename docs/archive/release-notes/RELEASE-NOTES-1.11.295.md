# HamiShop Elementor Widgets — Release 1.11.295

**Reviews: migrated comments are reviews too.**

## What was wrong

`reviews_list()` asked the database for `type => 'review'`. WooCommerce stores new reviews with
that `comment_type`, but a review migrated from an older install (or imported by a plugin) keeps an
empty `comment_type`. The list was then empty while the header said «بر اساس ۲ بازخورد» — the exact
shape of the report «دیدگاه‌های صفحه محصول نمایش داده نمی‌شوند».

Two related counters had the same blind spot:

- the rating chip used WooCommerce's stored `get_review_count()` / `get_average_rating()`, which an
  import can leave at zero;
- «درصد رضایت خریداران» filtered the same way, so imported ratings never counted.

## Fixed

- One shared query (`review_comments()`) reads the product's approved comments and keeps
  `review`, `comment` and empty types, skipping order notes and other comment types. The list, the
  count and the rating chips all use it.
- `review_stats()` falls back to the real comments only when WooCommerce's counter is zero, so no
  shop pays an extra query in the normal case.
- The empty state now distinguishes the two situations: «دیدگاه‌ها ثبت شده‌اند و در انتظار تأیید
  مدیر هستند.» instead of «هنوز دیدگاهی ثبت نشده است.» when reviews are waiting for moderation.
- The header count is computed from the rendered reviews rather than the lookup table.

## Verification (staging install, temporary plugin swap)

Product with three comments — one modern review (type `review`, 5★), one legacy review (empty type,
4★), one pending — rendered: **2 cards**, «(۲ نظر)», «بر اساس ۲ بازخورد», pending comment hidden.
A product with only a pending review rendered the new moderation message and zero cards.

`php tools/ci-checks.php` 24/24, harness clean, no console or PHP errors.

## Note for cached sites

WP Rocket / LiteSpeed page cache holds the old HTML. Purge it (and Elementor's CSS cache) after the
update, otherwise a product page keeps showing the previous review block.

## Build

- Version 1.11.295 in the plugin header, `HSW_VERSION`, `readme.txt` Stable tag, changelog and this
  note. `.min` assets and the POT rebuilt; ZIP built with `tools/build-release.py`.
