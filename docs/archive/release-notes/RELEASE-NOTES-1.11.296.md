# HamiShop Elementor Widgets — Release 1.11.296

**Replies are answers, not reviews.**

## What was wrong

WooCommerce threads a shop's answer under the customer's review (`comment_parent`), but the widget
printed every approved comment as its own card. A «Support» answer therefore appeared as a separate
review — usually **above** the review it answered, because the list is ordered newest first — with
its own avatar and nothing saying it was a reply. The header count also included it («۲ بازخورد»
for one review + one answer).

## Fixed

- `review_tree()` groups replies under their top-level review (walking up if a reply was itself
  answered), newest review first and replies oldest first.
- Replies render **inside** the parent card with a thread line and a smaller box, tagged
  «پاسخ پشتیبانی» when the author is an administrator/shop manager, «پاسخ» otherwise.
- Count and average rating use top-level reviews only (`top_level_reviews()`), so «(۲ نظر)» and
  «بر اساس … بازخورد» match the number of real reviews.
- The standalone accordion widget shares `review_cards_html()`, so it inherits the same threading.

## Verification (staging install, temporary plugin swap)

Product with: review 4★ (عرفان), shop reply from an administrator (Support), review 5★ (سارا),
customer reply (علی). Rendered: **2 review cards**, review → nested staff reply, review → nested
customer reply; tags «پاسخ پشتیبانی» / «پاسخ»; count «(۲ نظر)» and «بر اساس ۲ بازخورد» (replies not
counted). Screenshot checked at 1200px.

`php tools/ci-checks.php` 24/24, `tests/harness.php` clean, attribute QA test 10/10.

## Build

- Version 1.11.296 in the plugin header, `HSW_VERSION`, `readme.txt` Stable tag, changelog and this
  note. `.min` assets and the POT rebuilt; ZIP built with `tools/build-release.py`.
