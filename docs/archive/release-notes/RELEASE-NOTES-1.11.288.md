# HamiShop Elementor Widgets — Release 1.11.288

**Emergency fix for the Elementor editor white screen.**

## Root cause

When the wonder-deals editor-only CSS was removed in 1.11.286, the
`HSW_Assets::editor_style()` method was left with only one argument:

```php
wp_add_inline_style(
    'wp-admin',
);
```

`wp_add_inline_style()` requires two arguments. The callback was still attached
to `elementor/editor/before_enqueue_scripts`, so opening any Elementor editor
threw an `ArgumentCountError` and WordPress showed the fatal-error screen.

## Fixed

- Removed the now-empty `HSW_Assets::editor_style()` method.
- Removed its `elementor/editor/before_enqueue_scripts` hook.
- No editor-only CSS remains after the wonder-deals deletion.
- No widget, card, loop, or frontend style was changed.

## Verification

- `php -l includes/class-hsw-assets.php`: clean.
- The editor hook no longer exists; frontend asset registration is unchanged.
- `php tools/ci-checks.php`: 23 checks pass.
- `php tests/harness.php`: all plugin files load.

## Build

- Version 1.11.288 in the plugin header, `HSW_VERSION`, `readme.txt` Stable tag,
  changelog and this note.
- ZIP built with `tools/build-release.py`, committed under `release-downloads/`,
  annotated tag `v1.11.288`, and attached to the GitHub release.
