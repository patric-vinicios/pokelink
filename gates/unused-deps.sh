#!/usr/bin/env bash
# Gate: Composer packages required in composer.json but never referenced in
# code (composer-unused). False positives are common for packages used only
# via config/service-provider auto-discovery — check before removing.
#
# laravel/reverb is excluded: it's driven entirely by `php artisan
# reverb:start` (docker-compose.yml's reverb service command) and
# config/reverb.php, never a `use Laravel\Reverb\...` statement anywhere in
# app/, so composer-unused's static scan can never see it as used.
set -euo pipefail
cd "$(dirname "$0")/.."

docker compose exec -T app vendor/bin/composer-unused --excludePackage=laravel/reverb
