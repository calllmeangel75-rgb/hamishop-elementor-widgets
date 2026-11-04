# HamiShop Elementor Widgets 1.11.172 - release notes

Release date: 2026-10-30

Continuous integration, and two findings from the audit that made the old test
setup look greener than it was.

## The harness was testing half the plugin

`tests/harness.php` loads files in WordPress order from a hand-written list. That
list was written when the plugin was smaller and was never extended, so **42 of
106 files were silently never loaded**: the invoice, the telegram poller, both
header widgets, the whole account panel, security, wallet, branding, the icon
files and the product page style traits.

`HARNESS RESULT: ALL CLEAN` was therefore true and misleading at the same time.

The harness now has a second pass: every `includes/*.php` and `widgets/*.php` on
disk is loaded too, dependency-first (traits → `*-base.php` → the rest), and the
run ends with a coverage line and a hard failure if anything was skipped:

```
=== files loaded: 117/117 ===
=== 108 HSW classes declared ===
HARNESS RESULT: ALL CLEAN
```

Requires switched to `require_once` (the product-page traits are pulled in by a
widget as well, and a second `require` used to be a fatal), and three WordPress
functions used at file scope (`did_action`, `add_action`, `add_filter`) got
minimal stubs — the wallet gateway deliberately asks `did_action()` at load time.

## `.github/workflows/ci.yml`

| Job | Runs on | What it proves |
|---|---|---|
| `lint` | PHP 7.4, 8.0, 8.1, 8.2, 8.3 | every file compiles on every version we claim to support |
| `harness` | PHP 7.4, 8.2 | every file loads and every declaration resolves |
| `checks` | PHP 8.2 | the structural invariants below |
| `generated` | Python 3.12 | `.min` assets and the POT are rebuilt from source, and nothing changes |
| `package` | tags `v*` only | the ZIP is built by `tools/build-release.py` and uploaded as an artifact |

No services, no database, no secrets: the whole workflow is PHP + Python.

## `tools/ci-checks.php` — 16 checks, no Composer

These are the mistakes a linter cannot see:

1. **Version consistency** — plugin header, `HSW_VERSION` define, `readme.txt`
   `Stable tag`, a matching `RELEASE-NOTES-<ver>.md`, and a changelog entry.
2. **Text domain** — every `__()/_e()/esc_html__()/esc_attr__()/_n()/_x()` call
   must use `hamishop-elementor-widgets`. A typo'd domain renders fine and
   silently never translates (this class of bug was found by hand twice in this
   project).
3. **Debug leftovers** — `var_dump`, `print_r`, `xdebug_break`, `console.log`.
4. **Minified twins** — every `assets/{css,js}/*.css|js` has a `.min` sibling.
5. **Widget registration** — every file in `widgets/` has a catalog entry and is
   required by `HSW_Plugin` (a widget file nobody registers is dead weight that
   still ships).
6. **Repository hygiene** — no `node_modules`, `package.json`, `tests/_tmp-*`.
7. **Docs present** — README, technical brief, marketing, widget guide, widgets
   reference, staging checklist.

## Running it locally

```bash
php tools/ci-checks.php     # 16 checks, all passed
php tests/harness.php       # files loaded: 117/117 · ALL CLEAN
```

README now shows the CI badge and a four-row table explaining the gates; the
technical brief documents them in §14 and the "no CI" gap in §16 is gone.

## Verification

- `php tools/ci-checks.php` → 16 checks, all passed (Windows and expected on
  Linux; the checker normalises path separators).
- `php tests/harness.php` → 117/117 files, 108 classes, ALL CLEAN.
- Rebuilding assets and the POT leaves `git status` empty (idempotent), which is
  what the `generated` job asserts.
- PHP lint: 127 files, 0 failures.

## Still open

Server-side PDF, PHP unit tests for the pure helpers (Jalali, price, stock), and
the README/WIDGET-GUIDE copy edit — see `TECHNICAL-BRIEF.md` §16.
