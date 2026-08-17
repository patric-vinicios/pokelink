#!/usr/bin/env bash
# Gate: static type analysis (Larastan/PHPStan, level 5 — see phpstan.neon).
# Catches type errors, impossible conditions, and undefined properties/methods
# that only Laravel's magic makes look valid at a glance.
set -euo pipefail
cd "$(dirname "$0")/.."

docker compose exec -T app vendor/bin/phpstan analyse --memory-limit=512M
