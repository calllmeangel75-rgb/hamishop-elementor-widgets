#!/usr/bin/env python3
"""Generate docs/WIDGETS.md from the widget catalog + the widget sources.

The catalog (`tools/dump-catalog.php`) is the single source of truth for which
widget exists, in which group, and whether it is enabled by default. The control
labels and the page sections are read from the code, so the document cannot
drift from the implementation.

Usage: python tools/gen-widget-docs.py

The catalog is read by running `php tools/dump-catalog.php`, so there is no
intermediate file to keep in sync or to commit.
"""

import glob
import io
import json
import os
import re
import subprocess
import sys

OUT = "docs/WIDGETS.md"
DUMP = os.path.join(os.path.dirname(os.path.abspath(__file__)), "dump-catalog.php")

HEAD = """# کاتالوگ ویجت‌ها

این فایل از روی کد ساخته می‌شود (`tools/gen-widget-docs.py`) و منبع رسمی فهرست ویجت‌هاست. اگر ویجتی اضافه یا حذف شد، اول کاتالوگ `includes/class-hsw-widget-manager.php` را به‌روز کنید و بعد این فایل را بازتولید کنید.

**۶۲ ویجت در ۷ خانواده:** ۱۵ ویجت «اصلی» که پیش‌فرض در پنل المنتور دیده می‌شوند و ۴۷ «بخش مستقل» که پیش‌فرض خاموش‌اند و از **HamiShop ← کنترل ویجت‌ها** روشن می‌شوند.

## قاعده‌های ثابت

- **slug** هر ویجت با `hsw-` شروع می‌شود و بخشی از قرارداد عمومی است؛ تغییرش صفحه‌های ساخته‌شده را می‌شکند.
- ویجت‌های «صفحهٔ کامل» (Composite) بخش‌های داخلی خود را مستقل از تنظیم پنل رندر می‌کنند؛ خاموش‌بودن یک part فقط آن را از پنل المنتور پنهان می‌کند.
- هر ویجت در تب **Style** یک بخش «سازگاری با قالب (فونت)» دارد: فونت پالت / فونت طرح / فونت سفارشی / ارث‌بری از قالب.
- رنگ‌های عمومی از **HamiShop ← پالت رنگی** می‌آیند؛ کنترل رنگی همان ویجت، اگر مقدار داشته باشد، اولویت دارد.

"""


def clean(text):
    return re.sub(r"\s+", " ", (text or "")).strip()


def widget_files():
    """slug → path, read from each widget's get_name()."""
    out = {}
    for path in glob.glob("widgets/*.php"):
        src = io.open(path, encoding="utf-8", errors="ignore").read()
        m = re.search(r"return\s+'(hsw-[a-z0-9\-]+)'\s*;", src)
        if m:
            out[m.group(1)] = path
    return out


def individual_controls(src):
    out = []
    for m in re.finditer(
        r"add_control\(\s*\n?\s*'([a-z0-9_]+)',\s*\n?\s*array\((.*?)\n\s*\)\s*\);",
        src,
        re.S,
    ):
        body = m.group(2)
        lab = re.search(r"'label'\s*=>\s*__\(\s*'([^']+)'", body)
        if lab:
            out.append(lab.group(1))
    return out


def part_sections(src):
    """Composite pages: part_section( prefix, __( 'label' ), Class, [...] )."""
    out = []
    for m in re.finditer(r"part_section\(\s*'[a-z0-9_]+'\s*,\s*__\(\s*'([^']+)'", src):
        out.append(m.group(1))
    return out


def control_groups(src):
    """Shared builders a loop widget composes (controls_source, controls_header…)."""
    out = []
    for m in re.finditer(r"\$this->(controls_[a-z_]+)\(", src):
        name = m.group(1)
        if name not in out:
            out.append(name)
    return out


def load_catalog():
    """Run the PHP dumper and parse its JSON."""
    try:
        raw = subprocess.run(
            ["php", DUMP], capture_output=True, text=True, check=True
        ).stdout
    except (OSError, subprocess.CalledProcessError) as exc:
        sys.exit("could not run php tools/dump-catalog.php: %s" % exc)

    return json.loads(raw)


def main():
    groups = load_catalog()
    files = widget_files()

    total_gen = 0
    total_part = 0

    for g in groups:
        for it in g["items"]:
            path = files.get(it["slug"])
            it["controls"], it["sections"], it["groups"] = [], [], []
            if path:
                src = io.open(path, encoding="utf-8", errors="ignore").read()
                it["controls"] = individual_controls(src)
                it["sections"] = part_sections(src)
                it["groups"] = control_groups(src)

    out = [
        HEAD,
        "## نمای کلی\n",
        "| خانواده | دستهٔ المنتور | اصلی | بخش | مجموع |",
        "|---|---|---:|---:|---:|",
    ]

    for g in groups:
        gen = sum(1 for i in g["items"] if i["kind"] == "general")
        part = len(g["items"]) - gen
        total_gen += gen
        total_part += part
        out.append(
            "| %s | `%s` | %d | %d | **%d** |"
            % (g["label"], g["category"], gen, part, len(g["items"]))
        )

    out.append(
        "| **مجموع** | — | **%d** | **%d** | **%d** |\n"
        % (total_gen, total_part, total_gen + total_part)
    )

    for g in groups:
        out.append("## %s\n" % g["label"])
        out.append("%s\n" % clean(g["description"]))

        for it in g["items"]:
            kind = "اصلی" if it["kind"] == "general" else "بخش"
            label = clean(it["label"]) or it["slug"]
            desc = clean(it.get("desc", "")) or "—"

            out.append("### %s — `%s`\n" % (label, it["slug"]))
            out.append("**%s** · %s\n" % (kind, desc))

            if it["sections"]:
                out.append("بخش‌های صفحه (به ترتیب):\n")
                for i, sec in enumerate(it["sections"], 1):
                    # The label often already carries "۱. " — drop it so the list
                    # numbering is not doubled.
                    label = re.sub(r"^[۰-۹0-9]+\s*[.\-)]\s*", "", clean(sec))
                    out.append("%d. %s" % (i, label))
                out.append("")

            if it["groups"]:
                pretty = "، ".join("`%s`" % x for x in it["groups"])
                out.append("گروه‌های کنترل مشترک: %s\n" % pretty)

            if it["controls"]:
                shown = it["controls"][:6]
                out.append("نمونهٔ کنترل‌ها: %s\n" % "، ".join(clean(c) for c in shown))

        out.append("")

    out.append(
        "---\n\n"
        "> ستون‌های این سند از کد خوانده می‌شوند: «بخش‌های صفحه» از فراخوانی‌های `part_section()`، "
        "«گروه‌های کنترل» از `controls_*()` و «نمونهٔ کنترل‌ها» از `add_control()`ها. "
        "برای دیدن همهٔ کنترل‌ها، فایل ویجت یا تب Content/Style در المنتور را ببینید. "
        "قرارداد markup و data-attributeهای JS در `docs/archive/WIDGET-GUIDE.md` نگه‌داری می‌شود.\n"
    )

    io.open(OUT, "w", encoding="utf-8").write("\n".join(out).rstrip() + "\n")
    print("wrote", OUT)


if __name__ == "__main__":
    main()
