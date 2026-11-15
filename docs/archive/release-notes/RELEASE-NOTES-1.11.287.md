# HamiShop Elementor Widgets — Release 1.11.287

**Hotfix for the popular widget white screen.**

## Fixed

- `empty_text` in `HSW_Widget_Popular` was registered after `controls_header()`
  had already closed its Content section. Elementor then threw:

  ```text
  HSW_Widget_Popular::handle_control_position:
  Cannot add a control outside of a section
  ```

  The control now lives in its own **«حالت خالی»** section.
- `tests/qa/loop-button-controls-wp.php` now asserts that `empty_text` belongs
  to `sec_empty_state`. A control added outside a section will fail the
  `wp-integration` CI job instead of reaching a site.

## Verification

- `php -l widgets/class-hsw-popular.php`: clean.
- `tests/qa/loop-button-controls-wp.php`: all checks pass, including
  `Popular empty_text lives inside its own section`.
- No other widget or shared loop file was changed.

## Build

- Version 1.11.287 in the plugin header, `HSW_VERSION`, `readme.txt` Stable tag,
  changelog and this note.
- ZIP built with `tools/build-release.py`, committed under `release-downloads/`,
  annotated tag `v1.11.287`, and attached to the GitHub release.
