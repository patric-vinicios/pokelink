#!/usr/bin/env bash
# Gate: architecture boundaries (Deptrac — see deptrac.yaml). Enforces that
# only app/Jobs may depend on the PokeAPI client and that Models stay a leaf
# with no dependency on any other app/ layer.
set -euo pipefail
cd "$(dirname "$0")/.."

docker compose exec -T app vendor/bin/deptrac analyse --no-interaction
