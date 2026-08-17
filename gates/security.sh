#!/usr/bin/env bash
# Gate: known-vulnerability audit of installed Composer packages.
# Runs via a disposable composer:2 container, same pattern the README uses
# for the Node build — the app image ships vendor/ but not the composer
# binary itself. Defense-in-depth alongside roave/security-advisories
# (already required as a dev dependency), which blocks installing a known-
# vulnerable version in the first place; this catches advisories published
# *after* install.
set -euo pipefail
cd "$(dirname "$0")/.."

docker run --rm -v "$PWD":/app -w /app composer:2 audit --locked --no-interaction
