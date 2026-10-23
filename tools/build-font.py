#!/usr/bin/env python3
"""
Rebuild the bundled Persian font.

The plugin needs one thing from Vazirmatn: Persian text at 400–800 weight, plus
the Latin letters and digits that appear inside Persian UI ("IP68", "Dry-run",
"۱۲,۱۰۰,۰۰۰"). Everything else in the upstream variable font — Latin Extended,
symbols, currency signs, the whole Greek/Cyrillic set — was being downloaded by
every visitor for nothing.

This keeps the variable weight axis (one file, every weight, no per-weight
requests) and drops the codepoints the plugin never renders. 108 KB -> 64 KB.

Usage:
    python tools/build-font.py            # writes assets/fonts/vazirmatn.woff2
    python tools/build-font.py --check    # prints the size only
"""
import os
import sys

from fontTools import subset
from fontTools.ttLib import TTFont

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, "tools/_vazirmatn-src.woff2")
OUT = os.path.join(ROOT, "assets/fonts/vazirmatn.woff2")

# Persian/Arabic block, its presentation forms, Latin basic, and the
# punctuation Persian text actually uses.
RANGES = [
    (0x0020, 0x007E),  # latin basic
    (0x00A0, 0x00BF),  # latin-1 punctuation (NBSP, ×, ÷, ¼ …)
    (0x0600, 0x06FF),  # arabic
    (0x0750, 0x077F),  # arabic supplement
    (0x200C, 0x200F),  # ZWNJ / ZWJ / LRM / RLM
    (0x2010, 0x2027),  # dashes, quotes, ellipsis, bullet
    (0x2030, 0x205E),  # per-mille, guillemets, fraction slash
    (0x20AC, 0x20AC),  # euro
    (0xFB50, 0xFDFF),  # arabic presentation forms A (incl. ﷼)
    (0xFE70, 0xFEFC),  # arabic presentation forms B
]

if not os.path.exists(SRC):
    sys.exit("source font missing: tools/_vazirmatn-src.woff2")

text = "".join("".join(chr(c) for c in range(a, b + 1)) for a, b in RANGES)

opts = subset.Options()
opts.flavor = "woff2"
opts.desubroutinize = True
opts.hinting = False
opts.layout_features = ["kern", "liga", "calt", "mark", "mkmk", "init", "medi", "fina", "isol", "rlig", "ccmp"]
opts.name_IDs = ["*"]
opts.notdef_outline = True

font = TTFont(SRC)
sub = subset.Subsetter(options=opts)
sub.populate(text=text)
sub.subset(font)
font.flavor = "woff2"

print("source %d KB" % (os.path.getsize(SRC) // 1024))
if "--check" in sys.argv:
    sys.exit(0)

font.save(OUT)
print("written %d KB" % (os.path.getsize(OUT) // 1024))
