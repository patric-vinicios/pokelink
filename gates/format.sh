#!/usr/bin/env bash
# Gate: code style (Laravel Pint, PSR-12). Fails if any file is not
# formatted; run with --fix to rewrite files in place instead of checking.
set -euo pipefail
cd "$(dirname "$0")/.."

if [[ "${1:-}" == "--fix" ]]; then
    docker compose exec -T app vendor/bin/pint
else
    docker compose exec -T app vendor/bin/pint --test
fi
