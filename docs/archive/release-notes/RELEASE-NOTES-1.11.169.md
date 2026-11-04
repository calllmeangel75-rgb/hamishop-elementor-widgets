# HamiShop Elementor Widgets 1.11.169 - release notes

Release date: 2026-10-30

Two unrelated asks in one release: the phone header rhythm (both headers) and a
Telegram polling worker for hosts where the webhook cannot reach PHP.

## 1. Phone header: one gap size, one baseline

The mobile row was spaced by three different numbers at once — 8px between the
brand and the burger, the widget's own 40px between the action icons, and a
zero-width search wrapper that swallowed the free space. Result: the icon side
looked packed, the logo side airy, and the two headers drifted apart.

Below 900px the action clusters now flatten (`display: contents`), so their
children become items of the row itself and `space-evenly` hands every item the
same gap — including the edges.

Measured (store header / minimal pill, both headers):

| Viewport | Store gaps | Pill gaps | Vertical spread |
|---|---|---|---|
| 390px | 15 / 15 / 15 / 15 | 24 / 24 / 24 | 0px |
| 360px | 12 / 12 / 12 / 12 | 18 / 18 / 18 | 0px |

One `!important` is used on `justify-content` only: Elementor prints a
per-element rule from the widget's align control
(`@media (max-width:767px) .elementor-… .hsw-hd__maininner{justify-content:center}`)
that no sane selector beats. It is documented in place.

The brand wrapper is capped at `min(58vw, 220px)` so a long site name cannot
push the icons off the row, and every target is a uniform 40px (36px ≤ 420px)
with the stray `8px 16px` inner padding removed.

## 2. Logo shine, now a gradient

The sweep was a flat white band. It now has two tones — the palette accent tints
the trailing half — so it reads as a gradient and follows
**HamiShop → پالت رنگی** instead of always being white.

New control under the brand section: **«گرادیانت روی نام برند»** (off by
default). When the logo is a wordmark rather than an image, this paints the
brand name with primary → accent → primary and a slow drift. It sits behind a
switch because a gradient wordmark is a brand decision, and behind
`@supports (-webkit-background-clip: text)` so the name is never invisible.

> Minifier note: `@supports (a) or (b)` gets minified to `or(b)`, which parses as
> a function call and silently kills the whole block. The guard uses one prefixed
> condition for that reason.

## 3. Telegram polling worker

`HSW_Telegram_Poller`, for the case the host described: `getWebhookInfo` says
`Connection timed out`, nothing reaches PHP, yet `getUpdates` and `sendMessage`
work fine from the same host.

**Endpoints and scheduling**

- `GET /?hsw_tg_poll=1&secret=<telegram_secret>` runs one pass and answers JSON.
  Wrong secret → `403`, no WordPress template is loaded (`init` priority 1) and
  the response is never cached (`DONOTCACHEPAGE` + `nocache_headers`).
- WP-Cron event `hsw_tg_poll` every minute, registered only while the switch is
  on. Because WP-Cron needs traffic, the settings screen also prints the
  DirectAdmin cron line: `curl -s "<worker url>" >/dev/null` (or `wp-cron.php`).
- Deactivation clears the schedule; turning the switch off clears it from the
  admin.

**Per run**

1. `deleteWebhook?drop_pending_updates=true` once per token (a lingering webhook
   makes `getUpdates` answer `409 Conflict`); the token hash is the cache key, so
   pasting a new token re-prepares automatically.
2. First run asks `getUpdates?offset=-1&limit=1` to learn the newest update id, so
   a backlog queued while polling was off is skipped instead of answered.
3. `getUpdates?offset=<n>&limit=10&timeout=0&allowed_updates=["message"]` — one
   short request, no long polling.
4. Each update goes to `HSW_Account_Auth::handle_telegram_update()`, the same
   method the webhook uses, so the contact-ownership rules exist once.
5. Offset is stored in `hsw_tg_offset` (autoload **off**) after the batch.

**Guards**

- 45s transient lock → a cron run and a manual hit never overlap (`skipped: busy`).
- Transport failure leaves the offset untouched and always releases the lock.
- With polling on, the webhook endpoint answers `{ok:true,mode:"polling"}` and
  does nothing, so a retried webhook cannot double-process a message.

## Cost when it is off

`maybe_serve()` returns after one `isset($_GET)`; scheduling does nothing except
in the admin. Measured on `/shop/` with and without the class registered:
197 queries in both runs (variance only), no extra queries, no extra HTTP.

## Validation

- `tests/header-mobile-probe.js` (new): 14 checks × 2 viewports — gaps equal,
  one baseline, no overflow, shine class + animation, our assets 200.
- Polling loop with a stubbed Bot API: offset `103` after processing 101/102;
  second run asks `103` (no duplicates); backlog skip uses `offset=-1`;
  `limit/timeout = 10/0`; network failure keeps the offset and frees the lock;
  a locked worker returns `busy`.
- Live worker URL: `403` on a wrong secret, JSON error (not a fatal) with a fake
  token, `{ok:true}` shape on success.
- Admin screen renders the new switch and worker block with no PHP notice.
- PHP lint: 123 files, 0 failures. `tests/harness.php`: ALL CLEAN.
