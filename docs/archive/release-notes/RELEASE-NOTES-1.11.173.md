# HamiShop Elementor Widgets 1.11.173 - release notes

Release date: 2026-10-30

The minimal glass header was the least editable widget in the plugin. Three
complaints, three causes, all fixed — and the two headers now share the same
style vocabulary.

## 1. «شعار / زیرنویس برند» did nothing

The control saved, `brand_html()` printed `.hsw-hd__brandtag`, and then the
stylesheet threw it away:

```css
.hsw-hd__pill .hsw-hd__brandtag { display: none; }   /* ← the whole story */
```

It now renders under the brand name (small, second line) and has its own
**colour** and **typography** controls. Nothing else about the content control
changed, so an existing widget starts showing the tagline it already had.

## 2. Icons were hard-coded

`person`, `shopping_cart`, `menu`, `search` and the CTA arrow were literals in the
templates. Four new pickers in **محتوا → حساب، سبد و پشتیبانی → آیکن دکمه‌ها**:

| Control | Default | Applies to |
|---|---|---|
| آیکن منوی همبرگری | `menu` | drawer trigger, both headers |
| آیکن دکمهٔ جستجو | `search` | search toggle, both headers |
| آیکن حساب کاربری | `person` | the account button + drawer profile |
| آیکن سبد خرید | `shopping_cart` | the cart button (badge untouched) |
| آیکن دکمهٔ اقدام | `arrow_left_alt` | CTA (minimal only) |

The list comes from the plugin's own sprite, not Font Awesome — an `ICONS`
control would offer glyphs the renderer cannot draw. `icon_of()` validates the
saved value, so an icon removed from the sprite later falls back instead of
printing the fallback dot.

The shared list in `HSW_Widget_Base::icon_options()` grew by 20 entries (menu,
person, search, storefront, support_agent, wallet, receipt, percent …) with
Persian labels, so the loop widgets' pickers became more useful too.

> A first attempt declared a non-static `icon_options()` on `HSW_Header_Base`
> and the harness caught it as a fatal (`cannot make static method non static`)
> before it reached a browser. That is exactly what the 117/117 harness is for.

## 3. Styles that could not be changed

New controls in the shared **استایل هدر** section (both headers):

- رنگ لینک‌های منو + رنگ هاور
- اندازهٔ دکمه‌های آیکنی (ریسپانسیو، `--hsw-hd-act-size`)
- گردی دکمه‌های آیکنی (`--hsw-hd-act-radius`)
- استایل کامل **دکمهٔ اقدام**: پس‌زمینه، رنگ متن، هاور و گردی (با `a_button()`؛ قبلاً فقط از رنگ اصلی ارث می‌برد)

Pill section (minimal): background, border colour, border width, gap, minimum
height, vertical and horizontal padding. Everything goes through CSS variables,
and the mobile rules now read the same variables instead of hard-coding
`52px / 8px / 6px`:

```css
.hsw-hd__pill { min-height: var(--hsw-hd-pill-h, 58px); gap: var(--hsw-hd-pill-gap, 14px); … }
```

## Testing it without the Elementor editor

Editor sessions are not scriptable, so `tests/fixture-minimal-page.php` creates a
real Elementor page (`/qa-header-minimal/`) with the widget and a set of known
settings, then `tests/header-minimal-probe.js` reads the computed styles back:

| Check (× 1440 and 390) | Result |
|---|---|
| tagline printed and visible | ok |
| burger / search / account / cart / CTA icons follow the controls | ok |
| `--hsw-hd-act-size: 44px` → measured 44px | ok |
| pill gap 20px, min-height 68px, padding 16px | ok |
| pill background `#ffffff` | ok |
| nav link colour + CTA colour apply | ok |
| no console errors | ok |

`tests/header-mobile-probe.js` (store header rhythm) still passes, so the icon
size variable did not disturb the evenly-spaced phone row.

## Files touched

`includes/class-hsw-header-base.php` (pickers, icon_of, style controls),
`includes/class-hsw-widget-base.php` (bigger shared icon list),
`widgets/class-hsw-widget-header-minimal.php` (CTA icon + pill controls),
`widgets/class-hsw-widget-header-store.php` (burger/search icons),
`assets/css/hsw-header.css` (tagline visible, pill/action variables),
plus the two probes and the fixture.

## Still open

The store header keeps its own mega-menu style section; the drawer (`hsw-hd__drawer*`)
has no per-widget controls yet — it inherits the header palette. Server-side PDF
and PHP unit tests remain as listed in `TECHNICAL-BRIEF.md` §16.
