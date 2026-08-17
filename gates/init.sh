#!/usr/bin/env bash
# Boots the full PokéLink stack from a clean clone: creates .env if missing,
# builds/starts all six services, and blocks until the app container reports
# healthy — which only happens after the entrypoint finishes migrating and
# seeding the database (see docker-compose.yml and docker/php/entrypoint.sh).
# Safe to re-run any time; `docker compose up -d --wait` only touches what
# changed.
set -euo pipefail
cd "$(dirname "$0")/.."

if [[ ! -f .env ]]; then
    echo "Creating .env from .env.example..."
    cp .env.example .env
fi

port() { grep -m1 "^${1}=" .env 2>/dev/null | cut -d= -f2-; }
APP_PORT_VAL=$(port APP_PORT); APP_PORT_VAL=${APP_PORT_VAL:-8000}
REVERB_PORT_VAL=$(port REVERB_PORT); REVERB_PORT_VAL=${REVERB_PORT_VAL:-8080}

echo "Starting containers (first boot takes ~3-5 min: image build, migrate, seed)..."
echo

# UID/GID are readonly shell variables in bash, so they can't be assigned
# with `UID=... command` — `env` sets them in the child process's
# environment directly, bypassing the shell entirely. Matches the README's
# "container runs as the host UID" note.
if ! env UID="$(id -u)" GID="$(id -g)" docker compose up -d --wait; then
    echo
    echo "Stack failed to become healthy. Common causes:"
    echo "  - a host port is already in use: check 'docker compose logs', then edit"
    echo "    APP_PORT / FORWARD_DB_PORT / FORWARD_REDIS_PORT / REVERB_PORT in .env"
    echo "  - a migration failed: docker compose logs app"
    exit 1
fi

cat <<EOF

PokéLink is up.
  App           http://localhost:${APP_PORT_VAL}
  Health check  http://localhost:${APP_PORT_VAL}/up
  Horizon       http://localhost:${APP_PORT_VAL}/horizon
  Reverb (ws)   ws://localhost:${REVERB_PORT_VAL}/app/pokelink-key

  ADMIN  admin@pokelink.test / password
  USER   user@pokelink.test / password
EOF
