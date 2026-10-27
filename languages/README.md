# HamiShop Elementor Widgets — translations

The plugin text domain is `hamishop-elementor-widgets`. The template is
`hamishop-elementor-widgets.pot`; it is generated from the PHP sources with:

```bash
python tools/build-pot.py
```

To translate:

1. Copy the POT to `<locale>.po`, for example `fa_IR.po`.
2. Translate the `msgstr` fields.
3. Compile it to `<locale>.mo` with Poedit, `msgfmt`, or WP-CLI:

   ```bash
   wp i18n make-mo languages/
   ```

4. Place both files in this directory. WordPress loads them from the plugin
   `Domain Path: /languages`.

The bundled Persian interface is the source language; the POT is provided for
site-specific wording and for future locales.
