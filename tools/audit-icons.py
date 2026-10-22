#!/usr/bin/env python3
"""
Audit every icon name the plugin can render, split by renderer.

Two renderers exist:
  * HSW_Helpers::icon()  -> Material Symbols ligature font  (needs a glyph)
  * HSW_Account::icon()  -> inline SVG paths                (needs a path key)

Three markup sources are scanned:
  1. raw markup      <i class="hsw-ic" aria-hidden="true">name</i>
  2. helper calls    HSW_Helpers::icon( 'name' )
  3. icon arrays     'icon' => 'name'

Usage:
    python tools/audit-icons.py            # report
    python tools/audit-icons.py --write    # merge missing font names into _icons.txt
"""
import io
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
NAMES_FILE = os.path.join(ROOT, "tools", "_icons.txt")

# Admin-only icon families that never touch the bundled font.
FOREIGN = ("dashicons-", "eicon-", "fas ", "far ")

RE_RAW = re.compile(r'class="hsw-ic"[^>]*>\s*([a-z0-9_\-]+)\s*<')
RE_CALL = re.compile(r"HSW_Helpers::icon\(\s*'([a-z0-9_\-]+)'")
RE_ARRAY = re.compile(r"'icon'\s*=>\s*'([a-z0-9_\-]+)'")
RE_DYN = re.compile(r'class="hsw-ic"[^>]*>\s*<\?php')

# Selectable icon lists: every key can end up in saved Elementor data, so all of
# them must ship a glyph even when the default layout never renders them.
RE_CHOICES = re.compile(
    r"function\s+(?:icon_choices|icon_options)\s*\(\s*\)\s*\{.*?return\s+array\s*\((.*?)\);",
    re.S,
)
RE_CHOICE_KEY = re.compile(r"'([a-z0-9_\-]+)'\s*=>")

SVG_OWNER = "includes/class-hsw-account.php"
SVG_KEYS = re.compile(r"^\s*'([a-z0-9\-]+)'\s*=>\s*'<", re.M)
HELPERS = "includes/class-hsw-helpers.php"
RE_ALIAS = re.compile(r"'([a-z0-9_\-]+)'\s*=>\s*'([a-z0-9_\-]+)'\s*,")


def php_files():
    for base in ("includes", "widgets"):
        for dirpath, _dirs, files in os.walk(os.path.join(ROOT, base)):
            for f in files:
                if f.endswith(".php"):
                    yield os.path.join(dirpath, f)


def svg_keys():
    text = io.open(os.path.join(ROOT, SVG_OWNER), encoding="utf-8", errors="replace").read()
    start = text.index("public function icon(")
    return set(SVG_KEYS.findall(text[start:]))


def alias_map():
    """Friendly name -> real glyph, as declared in HSW_Helpers::icon_name()."""
    text = io.open(os.path.join(ROOT, HELPERS), encoding="utf-8", errors="replace").read()
    start = text.index("public static function icon_name(")
    end = text.index("HSW_Icon_Set::has", start)
    return dict(RE_ALIAS.findall(text[start:end]))


def main():
    subset = set(io.open(NAMES_FILE, encoding="utf-8").read().split())
    svg = svg_keys()
    aliases = alias_map()

    font_used = {}
    svg_used = {}
    dynamic = []

    for path in php_files():
        rel = os.path.relpath(path, ROOT).replace("\\", "/")
        text = io.open(path, encoding="utf-8", errors="replace").read()
        for lineno, line in enumerate(text.splitlines(), 1):
            hit = False
            for rx in (RE_RAW, RE_CALL, RE_ARRAY):
                for m in rx.finditer(line):
                    name = m.group(1)
                    if name.startswith(FOREIGN):
                        continue
                    where = "%s:%d" % (rel, lineno)
                    # account.php renders through inline SVG; account-auth/support
                    # delegate to $this->account->icon().
                    if rel.startswith("includes/class-hsw-account"):
                        svg_used.setdefault(name, []).append(where)
                    else:
                        font_used.setdefault(name, []).append(where)
                    hit = True
            if not hit and RE_DYN.search(line):
                dynamic.append("%s:%d" % (rel, lineno))

        # Keys of the Elementor icon dropdowns (selectable -> must exist).
        for block in RE_CHOICES.findall(text):
            for name in RE_CHOICE_KEY.findall(block):
                if name.startswith(FOREIGN):
                    continue
                font_used.setdefault(name, []).append("%s:choices" % rel)

    missing_font = sorted(n for n in font_used if n not in subset and n not in aliases)
    aliased = sorted(n for n in font_used if n not in subset and n in aliases)
    missing_svg = sorted(n for n in svg_used if n not in svg)

    print("font names used  : %2d   (glyphs in subset: %d)" % (len(font_used), len(subset)))
    print("svg  names used  : %2d   (paths in account : %d)" % (len(svg_used), len(svg)))
    print("FONT names with no glyph : %d" % len(missing_font))
    for n in missing_font:
        print("   FONT-MISSING  %-24s %s" % (n, font_used[n][0]))
    if aliased:
        print("resolved through the alias map : %d" % len(aliased))
        for n in aliased:
            print("   ALIASED       %-24s -> %-20s %s" % (n, aliases[n], font_used[n][0]))
    print("SVG names with no path   : %d" % len(missing_svg))
    for n in missing_svg:
        print("   SVG-MISSING   %-24s %s" % (n, svg_used[n][0]))

    if dynamic:
        print("\ndynamic name expressions (verify by hand): %d" % len(dynamic))
        for d in dynamic:
            print("   DYN  " + d)

    unused = sorted(subset - set(font_used))
    print("\nsubset entries not referenced anywhere: %d" % len(unused))
    print("   " + " ".join(unused))

    if "--write" in sys.argv and missing_font:
        merged = sorted(subset | set(missing_font))
        io.open(NAMES_FILE, "w", encoding="utf-8").write(" ".join(merged))
        print("\nwrote %d names to tools/_icons.txt" % len(merged))


if __name__ == "__main__":
    main()
