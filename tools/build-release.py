#!/usr/bin/env python3
"""Build the clean, installable HamiShop plugin ZIP.

Usage:
    python tools/build-release.py            # reads Version from the plugin header
    python tools/build-release.py 1.11.159   # explicit version

The archive contains one top-level folder and never includes tests, tools,
backups, VCS data or temporary files. The output is written next to the repo:

    ../hamishop-elementor-widgets-<version>.zip
"""

from __future__ import annotations

import os
import re
import sys
import zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SLUG = "hamishop-elementor-widgets"
MAIN = os.path.join(ROOT, SLUG + ".php")

TOP_LEVEL_FILES = (
    SLUG + ".php",
    "uninstall.php",
    "readme.txt",
    "README.md",
    "LICENSE",
    "WIDGET-GUIDE.md",
    "WIDGETS-REFERENCE.md",
    "ARCHITECTURE-MAP.md",
    "STAGING-CHECKLIST.md",
    "RELEASE-NOTES-1.11.159.md",
)
TOP_LEVEL_DIRS = ("assets", "includes", "widgets", "templates", "languages")
EXCLUDED_NAMES = {".DS_Store", "Thumbs.db", "__pycache__"}
EXCLUDED_SUFFIXES = (".pyc", ".log", ".zip", ".bak", ".tmp")


def version_from_header():
    text = open(MAIN, encoding="utf-8").read()
    match = re.search(r"^\s*\*\s*Version:\s*([0-9.]+)", text, re.MULTILINE)
    if not match:
        raise SystemExit("Could not find Version in " + MAIN)
    return match.group(1)


def add_file(archive, path, arcname):
    if not os.path.isfile(path):
        raise SystemExit("Missing required release file: " + path)
    archive.write(path, arcname, compress_type=zipfile.ZIP_DEFLATED)


def release_files(version):
    """Top-level files for this release, with the matching release note.

    The note name used to be hard-coded to one old version, so a ZIP built for
    a newer version shipped yesterday's notes (or none at all).
    """
    notes = "RELEASE-NOTES-%s.md" % version
    files = tuple(f for f in TOP_LEVEL_FILES if not f.startswith("RELEASE-NOTES-"))
    return files + (notes,)


def main():
    version = sys.argv[1] if len(sys.argv) > 1 else version_from_header()
    if not re.fullmatch(r"[0-9]+\.[0-9]+\.[0-9]+", version):
        raise SystemExit("Invalid version: " + version)

    out = os.path.join(os.path.dirname(ROOT), "%s-%s.zip" % (SLUG, version))
    top = SLUG + "/"
    written = 0

    with zipfile.ZipFile(out, "w", compression=zipfile.ZIP_DEFLATED) as archive:
        for name in release_files(version):
            path = os.path.join(ROOT, name)
            if name.startswith("RELEASE-NOTES-") and not os.path.isfile(path):
                continue
            add_file(archive, path, top + name)
            written += 1

        for folder in TOP_LEVEL_DIRS:
            base = os.path.join(ROOT, folder)
            if not os.path.isdir(base):
                continue
            for dirpath, dirs, files in os.walk(base):
                dirs[:] = sorted(d for d in dirs if d not in EXCLUDED_NAMES)
                for name in sorted(files):
                    if name in EXCLUDED_NAMES or name.endswith(EXCLUDED_SUFFIXES):
                        continue
                    path = os.path.join(dirpath, name)
                    rel = os.path.relpath(path, ROOT).replace("\\", "/")
                    add_file(archive, path, top + rel)
                    written += 1

    size = os.path.getsize(out)
    print("Built %s" % out)
    print("%d files, %.2f MB" % (written, size / (1024 * 1024)))
    print("Version: %s" % version)

    # A quick structural sanity check: one top-level directory, no tests/tools.
    with zipfile.ZipFile(out) as archive:
        names = archive.namelist()
        bad = [n for n in names if not n.startswith(top) or "/tests/" in n or "/tools/" in n]
        if bad:
            raise SystemExit("Archive contains unexpected entries: %s" % bad[:5])
        required = [top + SLUG + ".php", top + "LICENSE", top + "languages/" + SLUG + ".pot"]
        missing = [n for n in required if n not in names]
        if missing:
            raise SystemExit("Archive is missing: %s" % missing)
    return 0


if __name__ == "__main__":
    sys.exit(main())
