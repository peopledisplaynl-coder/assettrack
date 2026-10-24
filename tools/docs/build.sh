#!/usr/bin/env bash
# Builds the user guide: docs/handleiding/handleiding.md -> PDF (Chromium) and Word (pandoc).
# Needs pandoc, python3 + python-docx and the Playwright Chromium.
set -euo pipefail
export LC_ALL=C.UTF-8 LANG=C.UTF-8
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DOC="$ROOT/docs/handleiding"
TMP="$ROOT/tests/.tmp/docs"; mkdir -p "$TMP"
VERSION=$(sed -n "s/.*APP_VERSION', *'\([^']*\)'.*/\1/p" "$ROOT/includes/version.php")
SUB="Gebruikershandleiding — versie $VERSION"

[ -f "$DOC/reference.docx" ] || python3 "$ROOT/tools/docs/make-reference.py" "$DOC/reference.docx"

pandoc "$DOC/handleiding.md" --metadata subtitle="$SUB" --resource-path="$DOC" \
  --reference-doc="$DOC/reference.docx" --toc --toc-depth=2 --metadata toc-title=Inhoud -o "$DOC/AssetTrack_Handleiding.docx"

pandoc "$DOC/handleiding.md" --metadata subtitle="$SUB" --resource-path="$DOC" -s --toc --toc-depth=2 \
  --css "$DOC/handleiding.css" --embed-resources --metadata pagetitle="AssetTrack handleiding" \
  -o "$TMP/handleiding.html"
node "$ROOT/tools/docs/pdf.mjs" "$TMP/handleiding.html" "$DOC/AssetTrack_Handleiding.pdf" "AssetTrack $VERSION — Gebruikershandleiding"
echo "Klaar: $DOC/AssetTrack_Handleiding.pdf en .docx"
