#!/usr/bin/env bash
# Gate: code-quality score (PHP Insights) — style, architecture, complexity
# and design smells beyond what Pint's formatter checks.
set -euo pipefail
cd "$(dirname "$0")/.."

docker compose exec -T app vendor/bin/phpinsights analyse app --no-interaction
