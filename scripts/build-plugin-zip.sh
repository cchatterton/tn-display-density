#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="tn-display-density"
DIST_DIR="dist"

cd "$(dirname "$0")/.."
rm -rf "$DIST_DIR/$PLUGIN_SLUG"
rm -f "$PLUGIN_SLUG.zip"
mkdir -p "$DIST_DIR"
cp -R "$PLUGIN_SLUG" "$DIST_DIR/$PLUGIN_SLUG"
find "$DIST_DIR/$PLUGIN_SLUG" -name '.DS_Store' -delete

cd "$DIST_DIR"
rm -f "$PLUGIN_SLUG.zip"
zip -qr "$PLUGIN_SLUG.zip" "$PLUGIN_SLUG"
cp "$PLUGIN_SLUG.zip" "../$PLUGIN_SLUG.zip"
