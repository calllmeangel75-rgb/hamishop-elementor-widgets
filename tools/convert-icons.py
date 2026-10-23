#!/usr/bin/env python3
"""One-shot: turn `<i class="hsw-ic">name</i>` markup into HSW_Helpers::icon()."""
import io, os, re, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
APPLY = "--apply" in sys.argv

PAT = re.compile(
    r'<(?P<tag>i|span|em)(?P<pre>[^<>]*?)class="(?P<cls>[^"]*)"(?P<post>[^<>]*)>(?P<name>[a-z0-9_]+)</(?P=tag)>'
)

total = 0
skipped = []

for base in ("widgets", "includes"):
    for fn in sorted(os.listdir(os.path.join(ROOT, base))):
        if not fn.endswith(".php"):
            continue
        path = os.path.join(ROOT, base, fn)
        s = io.open(path, encoding="utf-8").read()
        out = []
        pos = 0
        changed = 0
        for m in PAT.finditer(s):
            cls = m.group("cls")
            if "hsw-ic" not in cls:
                continue
            if "<?php" in cls or "<?php" in m.group("pre") or "<?php" in m.group("post"):
                skipped.append((fn, s[m.start():m.end()][:80]))
                continue
            parts = [c for c in cls.split() if c and c not in ("hsw-ic", "is-fill", "hsw-ic--fill")]
            fill = "is-fill" in cls or "hsw-ic--fill" in cls
            extra = " ".join(parts)
            call = "<?php echo HSW_Helpers::icon( '%s'%s%s ); ?>" % (
                m.group("name"),
                ", '%s'" % extra if extra else "",
                ", true" if fill else "",
            )
            out.append(s[pos:m.start()])
            out.append(call)
            pos = m.end()
            changed += 1
            total += 1
        out.append(s[pos:])
        new = "".join(out)
        if changed and APPLY:
            io.open(path, "w", encoding="utf-8", newline="").write(new)
        if changed:
            print("%-45s %d" % (fn, changed))

print("converted:", total, "skipped:", len(skipped))
for fn, snippet in skipped:
    print("  SKIP", fn, snippet)
