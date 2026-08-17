#!/usr/bin/env bash
# Gate: full Pest/PHPUnit suite. Pass extra args through to `artisan test`,
# e.g. `gates/test.sh --filter=Favorite`.
# No coverage flag: the app image has neither pcov nor xdebug installed.
set -euo pipefail
cd "$(dirname "$0")/.."

docker compose exec -T app php artisan test "$@"
