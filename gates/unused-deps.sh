#!/usr/bin/env bash
# Gate: Composer packages required in composer.json but never referenced in
# code (composer-unused). False positives are common for packages used only
# via config/service-provider auto-discovery — check before removing.
set -euo pipefail
cd "$(dirname "$0")/.."

docker compose exec -T app vendor/bin/composer-unused
