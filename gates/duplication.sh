#!/usr/bin/env bash
# Gate: copy/paste detection (PHPCPD) across app/.
set -euo pipefail
cd "$(dirname "$0")/.."

docker compose exec -T app vendor/bin/phpcpd app
