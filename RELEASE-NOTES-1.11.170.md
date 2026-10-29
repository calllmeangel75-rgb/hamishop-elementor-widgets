# HamiShop Elementor Widgets 1.11.170 - release notes

Release date: 2026-10-30

Printable invoice and postal label: one A4 sheet, generated on demand, for both
the shop manager and the customer.

## One document, two doors

| Who | Where | What they get |
|---|---|---|
| Shop manager | order edit screen (HPOS and legacy post editor) | «چاپ فاکتور و برچسب پستی» |
| Customer | «سفارش‌های من» → order view → «چاپ فاکتور / برچسب پستی» | the same sheet, only their own orders |

Both point at `/?hsw_invoice=<order>&key=<nonce>`, render a standalone page
(no theme, no account shell) and open the browser print dialog — «Save as PDF»
turns it into a file.

## No storage, no PDF library

The page is built per request and never written to disk. Verified: the
`wp-content/uploads` file count was 3484 before the run and 3484 after, and the
print route only emits HTML plus one stylesheet. No temporary PDFs, no queue, no
extra PHP extension. That is the same property the other invoice plugin promised,
without shipping mPDF/TCPDF (~5MB) into a plugin whose whole point is staying
light.

## Single sheet

Item count picks a density tier; the sheet is a flex column with the signatures
pinned to the bottom, so the totals sit under the last row and the blank space
lands where a clerk stamps.

| Items | Tier | What changes |
|---|---|---|
| ≤ 10 | normal | name and variant on two lines |
| 11–18 | `hsw-inv--dense` | smaller type and padding |
| 19–32 | `hsw-inv--tight` | one line per item (ellipsis), tighter postal block |
| > 32 | `hsw-inv--ultra` | one more notch down |

Measured with Chromium's own PDF pipeline (`/Type /Page` count):

| Order | Rows | Tier | Sheet | Pages |
|---|---|---|---|---|
| #13493 | 1 | normal | 277mm | **1** |
| #13630 | 22 | tight | 277mm | **1** |
| #13631 | 25 | tight | 277mm | **1** |
| forced ultra | 25 | ultra | 277mm | **1** |

## Postal chart

A bordered «مشخصات مرسولهٔ پستی» block with the two parties exactly as the post
counter expects them: sender name, address, postal code and phone above; the
recipient in larger type with the address in one line, a **ten-box postal code**
row (empty boxes when the order has none, so they can be written by hand), item
count, weight (summed from the products' own weights) and, for cash-on-delivery,
the amount to collect. It prints on the same sheet, not a second one.

## Branding and colour

Logo, address, phone and stamp come from **HamiShop ← برند و لوگو**; a new
«کد پستی فروشگاه» field was added there (defaults to WooCommerce's store
postcode). The sheet is black on white with a single accent — the palette's
primary colour for the title, the rule under the header, the table head and the
postal-code boxes. A second switch, «سیاه و سفید خالص», removes even that.

## Access

- Nonce `hsw_invoice_<id>` **and** either order ownership or
  `edit_shop_orders` / `manage_woocommerce`.
- Verified: bad nonce → 403, guest → 403, another logged-in customer → 403,
  staff link → 200.
- The response is never cached (`DONOTCACHEPAGE` + `nocache_headers`), and the
  route answers at `template_redirect` priority 0, before the theme runs.

## Feature switch

«کنترل ویجت‌ها → فاکتور چاپی و برچسب پستی» with two switches: enable (default
on) and pure monochrome. Off means no button anywhere; the class still loads but
registers nothing beyond two hooks.

## Validation

- `tests/invoice-print-probe.js` (new): 19 checks — button, signed URL, sheet,
  logo, both postal parties, ten postal boxes, items/total, amount in words,
  density tier, **one A4 page**, bad nonce, guest, other customer, staff button
  and staff link. `RESULT: clean`.
- Admin screen: the «فاکتور چاپی و برچسب پستی» card renders with both switches
  and no PHP notice.
- PHP lint: 0 failures. `tests/harness.php`: ALL CLEAN.

## Test data

Orders `#13630` (22 items) and `#13631` (25 items) were created for the page
tests and are left in place so the sheet can be inspected; delete them from
WooCommerce whenever you like.
