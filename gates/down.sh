#!/usr/bin/env bash
# Full teardown, the opposite of init.sh: stops and removes every container
# and published port, every named volume (mysql, redis, vendor, build,
# storage), and the built pokelink-app image itself — so the next
# gates/init.sh rebuilds from scratch instead of reusing a stale cached
# image, the same gap gates/init.sh's own .env reconciliation just closed on
# the config side. Third-party base images (mysql, redis, nginx) are left
# alone: removing those would just make the next init.sh re-pull them from
# the registry for no project-state benefit.
# Destructive — the database and all other state are gone after this runs.
set -euo pipefail
cd "$(dirname "$0")/.."

docker compose down --volumes --remove-orphans
docker image rm pokelink-app:latest 2>/dev/null || true
