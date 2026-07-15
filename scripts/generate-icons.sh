#!/usr/bin/env bash
# Rasterize the app icons from the SVG masters into public/icons/.
# Pipeline: SVG --(qlmanage)--> 1024 PNG --(sips)--> every size. macOS-only
# (qlmanage + sips are built in), which is fine — this app is served from a Mac.
#
# Re-run this whenever resources/icon/*.svg changes.
set -euo pipefail
cd "$(dirname "$0")/.."

SRC="resources/icon/icon.svg"
SRC_MASK="resources/icon/icon-maskable.svg"
SRC_SQUARE="resources/icon/icon-square.svg"
OUT="public/icons"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
mkdir -p "$OUT"

rasterize() { # svg -> 1024 master png
  qlmanage -t -s 1024 -o "$TMP" "$1" >/dev/null 2>&1
  echo "$TMP/$(basename "$1").png"
}

MASTER="$(rasterize "$SRC")"
MASTER_MASK="$(rasterize "$SRC_MASK")"
MASTER_SQUARE="$(rasterize "$SRC_SQUARE")"

emit() { # master size outname
  sips -z "$2" "$2" "$1" --out "$OUT/$3" >/dev/null 2>&1
  echo "  $OUT/$3 (${2}x${2})"
}

echo "Standard icons (squircle, transparent corners — for the manifest):"
emit "$MASTER" 1024 icon-1024.png
emit "$MASTER" 512  icon-512.png
emit "$MASTER" 192  icon-192.png

echo "Square icons (solid corners — iOS/favicons apply their own mask):"
emit "$MASTER_SQUARE" 180 apple-touch-icon.png
emit "$MASTER_SQUARE" 32  favicon-32.png
emit "$MASTER_SQUARE" 16  favicon-16.png

echo "Maskable icons (full-bleed, safe-zone glyph — for Android adaptive):"
emit "$MASTER_MASK" 512 icon-512-maskable.png
emit "$MASTER_MASK" 192 icon-192-maskable.png

echo "Done."
