#!/usr/bin/env python3
"""
Build the minified CSS/JS that ships to production.

WordPress enqueues `.min.css` / `.min.js` unless SCRIPT_DEBUG is on, so the
readable sources stay in the repo while the browser downloads the small files.

Usage:  python tools/build-assets.py
"""
import glob
import io
import os
import re
import sys

import csscompressor
import rjsmin

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# csscompressor rewrites `0px` to `0`. That is fine for a normal property but
# silently breaks a custom property used inside calc(): `calc(-100% - 0)` is a
# type error, so the browser drops the whole declaration. Write `calc(0px)`
# when the value feeds a calc().
UNITLESS_CUSTOM_PROP = re.compile(r"--[a-z0-9-]+\s*:\s*0\s*[;}]")

warnings = []


def build(pattern, minify, label):
    total_in = total_out = 0
    for src in sorted(glob.glob(os.path.join(ROOT, pattern))):
        if src.endswith(".min.css") or src.endswith(".min.js"):
            continue
        raw = io.open(src, encoding="utf-8", newline="").read()
        out = minify(raw)
        if src.endswith(".css"):
            for m in UNITLESS_CUSTOM_PROP.finditer(out):
                warnings.append("%s: %s" % (os.path.basename(src), m.group(0)))
        dst = src.rsplit(".", 1)[0] + ".min." + src.rsplit(".", 1)[1]
        io.open(dst, "w", encoding="utf-8", newline="").write(out)
        a, b = len(raw.encode()), len(out.encode())
        total_in += a
        total_out += b
        print(f"  {os.path.basename(src):28} {a/1024:7.1f}KB -> {b/1024:6.1f}KB")
    print(f"{label}: {total_in/1024:.0f}KB -> {total_out/1024:.0f}KB "
          f"({100 - total_out * 100 / max(total_in, 1):.0f}% smaller)")


if __name__ == "__main__":
    print("CSS")
    build("assets/css/*.css", lambda s: csscompressor.compress(s), "CSS total")
    print("JS")
    build("assets/js/*.js", lambda s: rjsmin.jsmin(s), "JS total")

    if warnings:
        print("\nWARNING — custom property minified to a unitless 0.")
        print("If it is used inside calc(), that declaration is now invalid:")
        for w in warnings:
            print("   " + w)

