# HamiShop Elementor Widgets — Release 1.11.285

**Popular widget padding and empty-state text** — plus a clear answer about the
skeleton's cost.

## Added / Fixed

- **Box gutter control:** «محبوب‌ترین‌ها / منتخب» and «پرفروش‌ترین‌ها» now expose
  a responsive **گاتر کناری کارت** slider in the Style tab. It writes
  `--hsw-loop-box-gutter`; default is 32px desktop and 16px mobile. The CSS
  fallback (`clamp(16px, 3%, 32px)`) keeps existing instances intact.
- **Editable empty state:** the popular widget's
  «محصولی برای نمایش یافت نشد.» message is now a text control.

## Where the other texts live

These already existed and are unchanged:

- **Content → سربرگ بخش:** title, heading tag, subtitle, badge, mobile note,
  desktop/mobile link text and URL.
- **Content → تب‌های فیلتر:** tab labels and sources.
- Product name, price, category and stock are WooCommerce data, not static
  widget strings.

## Skeleton performance

The skeleton is **not part of the initial page paint**. The first tab is rendered
on the server; the skeleton is inserted only when the visitor switches to a tab
that has to fetch `/hsw/v1/loop`. It is a handful of empty `div`s plus one CSS
shimmer animation, with no JavaScript library. The shimmer is disabled under
`prefers-reduced-motion`. If tab switching is slow, the cost is the REST/query
path, not the skeleton markup.

## Build

- Version 1.11.285 in the plugin header, `HSW_VERSION`, `readme.txt` Stable tag,
  changelog and this note.
- ZIP built with `tools/build-release.py`, committed under `release-downloads/`,
  annotated tag `v1.11.285`, and attached to the GitHub release.
