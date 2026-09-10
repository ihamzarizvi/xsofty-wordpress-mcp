#!/usr/bin/env bash
set -euo pipefail

VERSION="${1:-0.3.2}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE="$ROOT/plugin/xsofty-wordpress-mcp"
OUTPUT="$ROOT/release/xsofty-wordpress-mcp-$VERSION.zip"
STAGE="$(mktemp -d "${TMPDIR:-/tmp}/xsofty-mcp-release.XXXXXX")"
trap 'rm -rf "$STAGE"' EXIT

mkdir -p "$STAGE/xsofty-wordpress-mcp" "$ROOT/release"
cp -R "$SOURCE/." "$STAGE/xsofty-wordpress-mcp/"
rm -f "$OUTPUT"
(
  cd "$STAGE"
  zip -qr "$OUTPUT" xsofty-wordpress-mcp
)
unzip -t "$OUTPUT" >/dev/null
printf 'RELEASE_BUILD_PASS %s\n' "$OUTPUT"
