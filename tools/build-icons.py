#!/usr/bin/env python3
"""
Rebuild the bundled Material Symbols subset.

The icon font is a ligature font: the markup is `<span class="hsw-ic">star</span>`
and the font turns the word into a glyph. A subset must therefore keep BOTH the
latin letters (the ligature input) and the icon glyphs (the output) — passing
only `--text` keeps 28 glyphs and every icon turns into raw text.

Usage:
    python tools/build-icons.py <path-to-MaterialSymbolsOutlined.woff2>

Get the full variable font from
https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200
and download the fonts.gstatic.com woff2 it points to (~4 MB).
"""
import io
import os
import subprocess
import sys

from fontTools.ttLib import TTFont

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
NAMES = os.path.join(ROOT, "tools", "_icons.txt")
OUT = os.path.join(ROOT, "assets", "fonts", "material-symbols.woff2")


def main(src):
    names = io.open(NAMES, encoding="utf-8").read().split()
    full = TTFont(src)
    order = set(full.getGlyphOrder())

    keep = []
    for n in names:
        for cand in (n, n + ".fill"):
            if cand in order:
                keep.append(cand)

    alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_ -"
    cmd = [
        sys.executable, "-m", "fontTools.subset", src,
        "--glyphs=" + ",".join(keep),
        "--text=" + " ".join(names) + " " + alphabet,
        "--layout-features=rclt,rlig",
        "--no-layout-closure",
        "--no-hinting",
        "--glyph-names",
        "--flavor=woff2",
        "--drop-tables+=DSIG",
        "--output-file=" + OUT,
    ]
    r = subprocess.run(cmd, capture_output=True, text=True)
    if r.returncode:
        raise SystemExit(r.stderr[-500:])

    g = TTFont(OUT)
    print(f"icons requested: {len(names)}  glyphs kept: {len(g.getGlyphOrder())}  "
          f"size: {os.path.getsize(OUT)/1024:.1f}KB")
    print("axes:", [(a.axisTag, a.minValue, a.maxValue) for a in g["fvar"].axes])


if __name__ == "__main__":
    if len(sys.argv) < 2:
        raise SystemExit("usage: build-icons.py <full-woff2>")
    main(sys.argv[1])
