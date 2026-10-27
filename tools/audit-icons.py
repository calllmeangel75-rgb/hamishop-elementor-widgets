#!/usr/bin/env python3
"""
Audit every icon name the plugin can render, split by renderer.

Sources scanned:
  * helper calls       HSW_Helpers::icon( 'name' [, class [, fill]] )
  * helper echo        HSW_Helpers::the_icon( 'name' [, class [, fill]] )
  * short echo         the_icon( 'name' [, class [, fill]] )
  * icon arrays        'icon' => 'name'
  * raw markup         <i class="hsw-ic [is-fill]">name</i>
  * selectable lists   icon_choices() / icon_options()

Runtime sources checked:
  * includes/class-hsw-icon-set.php       names accepted by icon_name()
  * includes/class-hsw-icon-paths.php     Material outlines (960 grid)
  * includes/class-hsw-icon-raw.php       Iconsax-style stroke art (24 grid)
  * includes/class-hsw-icon-extra.php     hand-picked outlines outside the font subset
  * tools/_icons.txt                      source list for the next font/sprite rebuild

The account widget draws its own inline SVG paths; those are checked against the
keys in includes/class-hsw-account.php instead of the font sprite.

Usage:
    python tools/audit-icons.py            # report, exit 1 when something is missing
    python tools/audit-icons.py --write    # merge missing font names into _icons.txt
"""
import io
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
NAMES_FILE = os.path.join(ROOT, "tools", "_icons.txt")
SET_FILE = os.path.join(ROOT, "includes", "class-hsw-icon-set.php")
PATHS_FILE = os.path.join(ROOT, "includes", "class-hsw-icon-paths.php")
RAW_FILE = os.path.join(ROOT, "includes", "class-hsw-icon-raw.php")
EXTRA_FILE = os.path.join(ROOT, "includes", "class-hsw-icon-extra.php")
HELPERS = os.path.join(ROOT, "includes", "class-hsw-helpers.php")
SVG_OWNER = os.path.join(ROOT, "includes", "class-hsw-account.php")

# Admin-only icon families that never touch the bundled sprite.
FOREIGN = ("dashicons-", "eicon-", "fas ", "far ")

RE_THE = re.compile(
    r"(?:HSW_Helpers::)?the_icon\(\s*'([a-z0-9_\-]+)'\s*"
    r"(?:,\s*'([^']*)'\s*)?(?:,\s*(true|false)\s*)?\)"
)
RE_CALL = re.compile(
    r"HSW_Helpers::icon\(\s*'([a-z0-9_\-]+)'\s*"
    r"(?:,\s*'([^']*)'\s*)?(?:,\s*(true|false)\s*)?\)"
)
RE_ARRAY = re.compile(r"'icon'\s*=>\s*'([a-z0-9_\-]+)'")
RE_RAW = re.compile(r'class="hsw-ic([^"]*)"[^>]*>\s*([a-z0-9_\-]+)\s*<')
RE_DYN = re.compile(r'class="hsw-ic"[^>]*>\s*<\?php')
RE_DYN_CALL = re.compile(r"(?:HSW_Helpers::)?(?:the_)?icon\(\s*\$")
RE_CHOICES = re.compile(
    r"function\s+(?:icon_choices|icon_options)\s*\(\s*\)\s*\{.*?return\s+array\s*\((.*?)\);",
    re.S,
)
RE_CHOICE_KEY = re.compile(r"'([a-z0-9_\-]+)'\s*=>")
RE_ALIAS = re.compile(r"'([a-z0-9_\-]+)'\s*=>\s*'([a-z0-9_\-]+)'\s*,")
RE_RUNTIME = re.compile(r"\$runtime_icons\s*=\s*array\((.*?)\);", re.S)
RE_LIST_NAME = re.compile(r"'([a-z0-9_\-]+)'\s*,")
RE_SET_NAME = re.compile(r"^\s*'([a-z0-9_\-]+)',\s*$", re.M)
RE_PATH_KEY = re.compile(r"^\s*'([^']+)'\s*=>", re.M)
RE_RAW_KEY = re.compile(r"^\s*'([^']+)'\s*=>\s*array\(", re.M)


def read(path):
    return io.open(path, encoding="utf-8", errors="replace").read()


def php_files():
    for base in ("includes", "widgets"):
        for dirpath, _dirs, files in os.walk(os.path.join(ROOT, base)):
            for f in files:
                if f.endswith(".php"):
                    yield os.path.join(dirpath, f)


def set_names():
    return set(RE_SET_NAME.findall(read(SET_FILE)))


def path_keys():
    return set(RE_PATH_KEY.findall(read(PATHS_FILE)))


def raw_keys():
    return set(RE_RAW_KEY.findall(read(RAW_FILE)))


def extra_data():
    names = set()
    paths = set()
    if os.path.exists(EXTRA_FILE):
        text = read(EXTRA_FILE)
        names = set(RE_SET_NAME.findall(text))
        paths = set(RE_PATH_KEY.findall(text))
    return names, paths


def svg_keys():
    text = read(SVG_OWNER)
    start = text.index("public function icon(")
    return set(re.findall(r"^\s*'([a-z0-9\-]+)'\s*=>\s*'<", text[start:], re.M))


def alias_map():
    """Friendly name -> real glyph, as declared in HSW_Helpers::icon_name()."""
    text = read(HELPERS)
    start = text.index("public static function icon_name(")
    end = text.index("HSW_Icon_Set::has", start)
    return dict(RE_ALIAS.findall(text[start:end]))


def add(used, name, where, fill=False):
    if name.startswith(FOREIGN):
        return
    item = used.setdefault(name, {"where": [], "fill": False})
    item["where"].append(where)
    if fill:
        item["fill"] = True


def main():
    subset = set(read(NAMES_FILE).split())
    names = set_names()
    extra_names, extra_paths = extra_data()
    paths = path_keys()
    raw = raw_keys()
    svg = svg_keys()
    aliases = alias_map()

    font_used = {}
    svg_used = {}
    dynamic = []

    for path in php_files():
        rel = os.path.relpath(path, ROOT).replace("\\", "/")
        text = read(path)
        own_svg = rel.startswith("includes/class-hsw-account")
        for lineno, line in enumerate(text.splitlines(), 1):
            where = "%s:%d" % (rel, lineno)
            for m in RE_THE.finditer(line):
                cls = m.group(2) or ""
                add(font_used, m.group(1), where, m.group(3) == "true" or "is-fill" in cls)
            for m in RE_CALL.finditer(line):
                cls = m.group(2) or ""
                add(font_used, m.group(1), where, m.group(3) == "true" or "is-fill" in cls)
            for m in RE_ARRAY.finditer(line):
                add(svg_used if own_svg else font_used, m.group(1), where)
            for m in RE_RAW.finditer(line):
                add(font_used, m.group(2), where, "is-fill" in (m.group(1) or ""))
            if (
                rel != "includes/class-hsw-helpers.php"
                and "function icon(" not in line
                and "function the_icon(" not in line
                and (RE_DYN.search(line) or RE_DYN_CALL.search(line))
            ):
                dynamic.append(where)

        for block in RE_RUNTIME.findall(text):
            for name in RE_LIST_NAME.findall(block):
                add(font_used, name, "%s:runtime" % rel)

        for block in RE_CHOICES.findall(text):
            for name in RE_CHOICE_KEY.findall(block):
                add(svg_used if own_svg else font_used, name, "%s:choices" % rel)

    allowed = names | extra_names
    drawable = paths | raw | extra_paths

    missing_base = []
    missing_draw = []
    fill_fallback = []
    for name in sorted(font_used):
        resolved = aliases.get(name, name)
        if resolved not in allowed:
            missing_base.append((name, resolved, font_used[name]["where"][0]))
            continue
        if resolved not in drawable:
            missing_draw.append((name, resolved, font_used[name]["where"][0]))
            continue
        if font_used[name]["fill"]:
            key = resolved + ".fill"
            if key not in drawable:
                fill_fallback.append((name, key, font_used[name]["where"][0]))

    missing_svg = sorted(n for n in svg_used if n not in svg)

    print("font icons used : %2d   (set: %d, extra: %d, paths: %d, raw: %d)" % (
        len(font_used), len(names), len(extra_names), len(paths), len(raw)))
    print("svg icons used  : %2d   (account paths: %d)" % (len(svg_used), len(svg)))
    print("font bases missing from set + extra : %d" % len(missing_base))
    for n, r, w in missing_base:
        print("   FONT-MISSING  %-24s -> %-24s %s" % (n, r, w))
    print("font bases present but not drawable : %d" % len(missing_draw))
    for n, r, w in missing_draw:
        print("   PATH-MISSING  %-24s -> %-24s %s" % (n, r, w))
    print("fill variants using the base outline: %d" % len(fill_fallback))
    for n, k, w in fill_fallback:
        print("   FILL-FALLBACK %-24s %s" % (k, w))
    print("svg names with no account path       : %d" % len(missing_svg))
    for n in missing_svg:
        print("   SVG-MISSING   %-24s %s" % (n, svg_used[n][0]))

    if dynamic:
        print("\ndynamic name expressions (verify by hand): %d" % len(dynamic))
        for d in dynamic:
            print("   DYN  " + d)

    set_not_subset = sorted(names - subset - raw)
    if set_not_subset:
        print("\nruntime set names outside tools/_icons.txt (raw/extra icons are expected):")
        print("   " + " ".join(set_not_subset))

    unused = sorted(subset - set(font_used))
    if unused:
        print("\nfont-subset entries not referenced by a literal call: %d" % len(unused))
        print("   " + " ".join(unused))

    if "--write" in sys.argv and missing_base:
        merged = sorted(subset | set(n for n, _r, _w in missing_base))
        io.open(NAMES_FILE, "w", encoding="utf-8").write(" ".join(merged))
        print("\nwrote %d names to tools/_icons.txt; rebuild the sprite next" % len(merged))
        return 0

    return 1 if (missing_base or missing_draw or missing_svg) else 0


if __name__ == "__main__":
    sys.exit(main())
