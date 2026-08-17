#!/usr/bin/env bash
# Full teardown, the opposite of init.sh: stops and removes every container
# and published port, plus every named volume (mysql, redis, vendor, build,
# storage). Destructive — the database and all other state are gone after
# this runs. The next gates/init.sh starts from a completely clean slate.
set -euo pipefail
cd "$(dirname "$0")/.."

docker compose down --volumes --remove-orphans
