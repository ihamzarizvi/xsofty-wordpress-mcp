#!/usr/bin/env bash
set -euo pipefail

PHP_BIN="${PHP_BIN:-php}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

linted=0
while IFS= read -r file; do
  "$PHP_BIN" -l "$file" >/dev/null
  linted=$((linted + 1))
done < <(git ls-files '*.php')

tested=0
for test_file in tests/test-*.php; do
  "$PHP_BIN" -d display_errors=1 "$test_file"
  tested=$((tested + 1))
done

if command -v node >/dev/null 2>&1; then
  node --check plugin/xsofty-wordpress-mcp/assets/admin.js
fi

printf 'SOURCE_GATE_PASS php_linted=%s tests=%s\n' "$linted" "$tested"
