#!/usr/bin/env python3
"""
Remove the per-suite Material Symbols `@font-face` copies and the dead scoped
`.hsw-X .hsw-ic` base rules.

Both now live once, in assets/css/hsw-fonts.css (+ a PHP-printed @font-face with
a filemtime cache-buster). The scoped variants were matching nothing because the
markup never emits `.hsw-c` / `.hsw-k` / `.hsw-p` / `.hsw-b` / `.hsw-h`, which is
why icon names rendered as English text outside the archive.

Usage:
    python tools/dedupe-icon-css.py [--dry]
"""
import glob
import io
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SKIP = {"hsw-fonts.css", "hsw-account.css", "hsw-composite.css"}

RE_FACE = re.compile(r"[ \t]*@font-face\s*\{[^}]*Material Symbols Outlined[^}]*\}\n*", re.S)
# `.hsw-c .hsw-ic {` / `.hsw-ic {` — only the base block, identified by the
# font-variation-settings declaration inside it.
RE_BASE = re.compile(r"[ \t]*\.hsw(?:-[a-z]+)? \.hsw-ic\s*\{[^}]*font-variation-settings[^}]*\}\n*", re.S)
RE_BASE_PLAIN = re.compile(r"[ \t]*^\.hsw-ic\s*\{[^}]*font-variation-settings[^}]*\}\n*", re.S | re.M)


def main():
    dry = "--dry" in sys.argv
    total = 0
    for path in sorted(glob.glob(os.path.join(ROOT, "assets", "css", "*.css"))):
        name = os.path.basename(path)
        if name.endswith(".min.css") or name in SKIP:
            continue
        src = io.open(path, encoding="utf-8").read()
        out, n = RE_FACE.subn("", src)
        out, m = RE_BASE.subn("", out)
        out, k = RE_BASE_PLAIN.subn("", out)
        # Collapse the blank-line runs the deletions leave behind.
        out = re.sub(r"\n{3,}", "\n\n", out).lstrip("\n")
        if n or m or k:
            saved = len(src) - len(out)
            print("%-22s font-face=%d scoped=%d plain=%d  -%d bytes" % (name, n, m, k, saved))
            total += saved
            if not dry:
                io.open(path, "w", encoding="utf-8", newline="\n").write(out)
        else:
            print("%-22s unchanged" % name)
    print("total removed: %d bytes" % total)


if __name__ == "__main__":
    main()
