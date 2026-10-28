#!/usr/bin/env python3
"""Build the gettext POT template for HamiShop Elementor Widgets.

The plugin ships Persian UI strings, but the POT keeps every translation call
in a standard template so a translator can replace them without editing PHP.
Only calls whose text domain is ``hamishop-elementor-widgets`` are included.

Usage:
    python tools/build-pot.py
"""

from __future__ import annotations

import io
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DOMAIN = "hamishop-elementor-widgets"
OUT = os.path.join(ROOT, "languages", DOMAIN + ".pot")

FUNCTIONS = {
    "__": (0, 1, None),
    "_e": (0, 1, None),
    "esc_html__": (0, 1, None),
    "esc_html_e": (0, 1, None),
    "esc_attr__": (0, 1, None),
    "esc_attr_e": (0, 1, None),
    "esc_html_x": (0, 2, None),
    "esc_attr_x": (0, 2, None),
    "_x": (0, 2, None),
    "_ex": (0, 2, None),
    "_n": (0, 3, 1),
    "_nx": (0, 4, 1),
    "_n_noop": (0, 2, 1),
    "_nx_noop": (0, 3, 1),
}

NAME_RE = re.compile(r"(?<![A-Za-z0-9_>:\$])([A-Za-z_][A-Za-z0-9_]*)\s*\(")


def php_files():
    yield os.path.join(ROOT, "hamishop-elementor-widgets.php")
    for folder in ("includes", "widgets", "templates"):
        base = os.path.join(ROOT, folder)
        if not os.path.isdir(base):
            continue
        for dirpath, _dirs, files in os.walk(base):
            for name in sorted(files):
                if name.endswith(".php"):
                    yield os.path.join(dirpath, name)


def parse_call(src: str, open_index: int):
    """Return raw argument strings for a call, or None when malformed."""
    args = []
    start = open_index + 1
    depth = 1
    quote = ""
    escaped = False
    for i in range(start, len(src)):
        ch = src[i]
        if quote:
            if escaped:
                escaped = False
            elif ch == "\\":
                escaped = True
            elif ch == quote:
                quote = ""
            continue
        if ch in ("'", '"'):
            quote = ch
        elif ch in "([{":
            depth += 1
        elif ch in ")]}":
            depth -= 1
            if depth == 0:
                args.append(src[start:i])
                return args
        elif ch == "," and depth == 1:
            args.append(src[start:i])
            start = i + 1
    return None


def literal(arg: str):
    """Decode a simple PHP string literal; return None for variables/expressions."""
    arg = arg.strip()
    if len(arg) < 2 or arg[0] not in ("'", '"') or arg[-1] != arg[0]:
        return None
    quote = arg[0]
    body = arg[1:-1]
    out = []
    i = 0
    while i < len(body):
        ch = body[i]
        if ch == "\\" and i + 1 < len(body):
            nxt = body[i + 1]
            if quote == '"':
                mapping = {
                    "n": "\n",
                    "r": "\r",
                    "t": "\t",
                    "v": "\v",
                    "e": "\x1b",
                    "f": "\f",
                    "\\": "\\",
                    '"': '"',
                    "$": "$",
                }
                if nxt in mapping:
                    out.append(mapping[nxt])
                    i += 2
                    continue
            elif nxt == quote or nxt == "\\":
                out.append(nxt)
                i += 2
                continue
        out.append(ch)
        i += 1
    return "".join(out)


def q(value: str):
    return (
        value.replace("\\", "\\\\")
        .replace('"', '\\"')
        .replace("\n", "\\n")
        .replace("\r", "\\r")
        .replace("\t", "\\t")
    )


def main():
    entries = {}
    for path in php_files():
        if not os.path.isfile(path):
            continue
        src = io.open(path, encoding="utf-8", errors="replace").read()
        for match in NAME_RE.finditer(src):
            func = match.group(1)
            if func not in FUNCTIONS:
                continue
            args = parse_call(src, match.end() - 1)
            if not args:
                continue
            text_index, domain_index, plural_index = FUNCTIONS[func]
            if len(args) <= domain_index:
                continue
            domain = literal(args[domain_index])
            if domain != DOMAIN:
                continue
            text = literal(args[text_index])
            if text is None:
                continue
            plural = literal(args[plural_index]) if plural_index is not None and len(args) > plural_index else None
            context = None
            if func in ("_x", "_ex", "esc_html_x", "esc_attr_x"):
                context = literal(args[1]) if len(args) > 1 else None
            elif func in ("_nx", "_nx_noop"):
                context = literal(args[3]) if len(args) > 3 else None
            key = (context or "", text, plural)
            refs = entries.setdefault(key, [])
            rel = os.path.relpath(path, ROOT).replace("\\", "/")
            line = src.count("\n", 0, match.start()) + 1
            refs.append("%s:%d" % (rel, line))

    os.makedirs(os.path.dirname(OUT), exist_ok=True)
    lines = [
        'msgid ""',
        'msgstr ""',
        '"Project-Id-Version: HamiShop Elementor Widgets 1.11.151\\n"',
        '"Report-Msgid-Bugs-To: https://hamisys.ir/\\n"',
        '"POT-Creation-Date: 2026-10-28 00:00+0000\\n"',
        '"PO-Revision-Date: 2026-10-28 00:00+0000\\n"',
        '"Last-Translator: HamiSys\\n"',
        '"Language-Team: Persian\\n"',
        '"Language: fa_IR\\n"',
        '"MIME-Version: 1.0\\n"',
        '"Content-Type: text/plain; charset=UTF-8\\n"',
        '"Content-Transfer-Encoding: 8bit\\n"',
        '"Plural-Forms: nplurals=2; plural=(n > 1);\\n"',
        '"X-Generator: HamiShop build-pot.py\\n"',
        '"X-Domain: %s\\n"' % DOMAIN,
        "",
    ]
    for (context, text, plural), refs in sorted(entries.items(), key=lambda item: (item[0][0], item[0][1], item[0][2] or "")):
        for ref in refs:
            lines.append("#: " + ref)
        if context:
            lines.append('msgctxt "' + q(context) + '"')
        lines.append('msgid "' + q(text) + '"')
        if plural is not None:
            lines.append('msgid_plural "' + q(plural) + '"')
            lines.append('msgstr[0] ""')
            lines.append('msgstr[1] ""')
        else:
            lines.append('msgstr ""')
        lines.append("")

    with io.open(OUT, "w", encoding="utf-8", newline="\n") as handle:
        handle.write("\n".join(lines))
    print("Wrote %s (%d strings)" % (OUT, len(entries)))


if __name__ == "__main__":
    main()
