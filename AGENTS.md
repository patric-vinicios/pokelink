# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

PokéLink — a Laravel 12 + Livewire/Volt app: authenticated Pokémon catalog with live search, per-user
favorites, and real-time chat (Reverb). The whole stack (app, nginx, MySQL, Redis, Horizon worker,
Reverb) boots from a single `docker compose up -d`; there is no supported local (non-Docker) dev path.

## Commands

All commands run inside the `app` container. There is no host PHP/Node/MySQL/Redis dependency.

```bash
gates/init.sh                                 # boot the full stack: .env, containers, migrate+seed, waits healthy
gates/down.sh                                 # full teardown: containers, ports, and every named volume (DB included)

docker compose logs -f app                    # follow entrypoint/boot logs
docker compose exec app php artisan test                    # full suite
docker compose exec app php artisan test --filter=Favorite  # one group/class/test name
docker compose exec app vendor/bin/pint                     # lint/format (Laravel Pint)
docker compose exec app php artisan tinker
docker compose exec app php artisan migrate:status
docker compose exec mysql mysql -u pokelink -ppokelink pokelink
```

JS/CSS changes require a rebuild — there is no Node service in the stack:

```bash
docker run --rm -v "$PWD":/app -w /app -u $(id -u):$(id -g) node:20-alpine npm run build
```

Test suite: Pest, SQLite in-memory (`phpunit.xml`), `BROADCAST_CONNECTION=null`, `CACHE_STORE=array`.
No test touches the network — every PokeAPI call is `Http::fake()`.

## Lifecycle scripts

`gates/init.sh` and `gates/down.sh` are the inverse of each other, both idempotent to re-run:

- `gates/init.sh` — creates `.env` from `.env.example` if missing, starts every service (`docker compose
  up -d --wait`, using `env UID=... GID=...` rather than exporting `UID`/`GID`, since those are readonly
  in bash), and blocks until `app` reports healthy — which only happens once the entrypoint finishes
  migrating and seeding. Prints the same endpoints/credentials table as the README on success.
- `gates/down.sh` — `docker compose down --volumes --remove-orphans`: stops and removes every container,
  releases every published port, and destroys every named volume (`mysql`, `redis`, `vendor`, `build`,
  `storage` — the database included). The next `gates/init.sh` starts from a fully clean slate.

Neither is part of `gates/all.sh` — that runs quality checks only, never boots or tears down the stack.

## Quality gates

`gates/*.sh` — one script per check, all runnable standalone or together via `gates/all.sh` (runs every
gate, doesn't stop at the first failure, prints a pass/fail summary). Each wraps a dev dependency already
in `composer.json`'s `require-dev` (installed inside the `app` image, not on the host):

| Script | Tool | Config |
|---|---|---|
| `gates/format.sh [--fix]` | Pint (style/PSR-12) | none (defaults) |
| `gates/static-analysis.sh` | Larastan/PHPStan, level 5 | `phpstan.neon` |
| `gates/lint.sh` | PHP Insights (quality score) | `phpinsights.php` |
| `gates/architecture.sh` | Deptrac (layer boundaries) | `deptrac.yaml` |
| `gates/duplication.sh` | PHPCPD (`systemsdk/phpcpd` fork — upstream `sebastian/phpcpd` is abandoned and fatals against this project's Symfony Console version) | none |
| `gates/security.sh` | `composer audit`, via a disposable `composer:2` container (the `app` image has no composer binary) | none |
| `gates/unused-deps.sh` | `composer-unused` | none |
| `gates/test.sh [-- artisan-test-args]` | Pest/PHPUnit | `phpunit.xml` |

`deptrac.yaml` encodes the PokeAPI resilience-layer rule above as an enforced boundary: only `app/Jobs`
may depend on `app/Services/PokeApi`, and `app/Models` is a leaf. It only covers `app/` — Volt page
classes live inline in `.blade.php` files under `resources/views/livewire/`, which Deptrac's directory
collector doesn't parse.

`roave/security-advisories` is required (dev, `dev-latest`) as a second security layer: it blocks
`composer require`/`update` from ever installing a version with a known advisory, rather than only
catching it after the fact like `gates/security.sh` does.

The `app` image ships neither `pcov` nor `xdebug`, so `gates/test.sh` runs without `--coverage`.

## CI

`.github/workflows/` — one workflow per gate (`test`, `format`, `static-analysis`, `lint`,
`architecture`, `duplication`, `unused-deps`, `security`) plus `boot`, all independent and running in
parallel on every PR and on push to `main`. None of them use `docker compose` or `gates/*.sh` directly —
those assume a long-lived local dev stack. Instead:

- Every gate except `security` builds the `app` image via the shared `.github/actions/build-app-image`
  composite action (target `final`, cached across all workflows with `cache-from`/`cache-to:
  type=gha,scope=pokelink-app`), then runs `docker run --rm -e CONTAINER_ROLE=ci pokelink-app:ci
  <command>`. `CONTAINER_ROLE=ci` makes `docker/php/entrypoint.sh` skip the MySQL wait and
  migrate/seed/optimize steps (those only run for `CONTAINER_ROLE=app`), going straight to the check —
  most gates are pure static analysis and never touch a database at all.
- `test` is the one exception that needs a real dependency: PokeApiClient's response cache explicitly
  targets the `redis` store (`config/pokeapi.php`), which bypasses `phpunit.xml`'s `CACHE_STORE=array`
  default. The job runs a `redis:7-alpine` container named `redis` on a dedicated Docker network so it
  resolves at the same hostname `.env.example` already points at (`REDIS_HOST=redis`) — no MySQL
  container, since the DB itself is in-memory SQLite per `phpunit.xml`.
- `security` needs neither image nor database — same disposable `composer:2` container as
  `gates/security.sh`, reading only `composer.lock`.
- `boot` is the odd one out: it's the only workflow that runs `gates/init.sh` and `gates/down.sh` as-is,
  booting the real 6-service Compose stack end to end and curling `/up` — the only CI check that exercises
  mysql, nginx, Horizon, and Reverb at all, validating the README's "one command" boot promise directly
  rather than any one layer of it.

## Architecture

**Boot sequence (docker-compose.yml):** `mysql`+`redis` healthy → `app` entrypoint (env/key, wait-for-db,
migrate+seed, `storage:link`, `optimize`) → `app` marked healthy → `web`, `queue` (Horizon), `reverb`
start. Only `app` ever runs migrations. `app`/`queue`/`reverb` share one image, differing by command and
`CONTAINER_ROLE`. Two separate markers matter: `storage/app/.pokelink-installed` (on the named volume —
survives `restart`, driving idempotent re-migration; wiped by `down -v`) vs. an in-container readiness
marker (never survives a recreate — what the healthcheck polls).

**Request layer:** routes are Livewire Volt pages (`Volt::route`) rather than controllers for the four
main screens (`routes/web.php`): `/` search, `/pokemon/{slug}` details, `/favoritos`, plus plain Blade
views for `/chat` and `/perfil`. All of them sit behind `auth` + `auth.session` (the latter invalidates
other sessions on password change by comparing the session's stored password hash on every request, not
just `/perfil`). Volt component classes live under `resources/views/livewire/pages/...`; supporting
Livewire components (chat, favorite-toggle, profile forms) live under `resources/views/livewire/...`
with matching classes-in-blade (Volt) or `app/Livewire/**` (traditional Forms/Actions).

**PokeAPI resilience layer** (`app/Services/PokeApi/PokeApiClient.php`, config in `config/pokeapi.php`):
the only code allowed to reach the network. Layers, in order: outbound rate limit (60/min, shared across
all callers) → retry (3 attempts, 200/400/800ms backoff, on connection errors + 429/500/502/503/504,
never on 404) → circuit breaker (5 failures/60s window trips a 30s cooldown, short-circuits without
spending retry/rate-limit budget) → response cache (`redis` store, 24h TTL, 5min TTL for not-found).
Cache-outage logging uses a *different* store (`file`) so it survives the response cache's store being
down. Everything downstream of F06 (search, details, favorites) reads only from MySQL — never from this
client directly — so the app stays 100% functional with PokeAPI unreachable.

**Catalog sync** (`app/Jobs/SyncPokemonCatalog.php`, queued on Horizon): the one job that populates
`pokemon`/`types`/`pokemon_type` from PokeAPI, via `PokeApiClient::index()`/`typeRoster()`. Type slugs,
pt-BR labels, and badge colors are a static map in `config/pokemon.php` (`type_labels`/`type_colors`) —
deliberately not fetched, to keep the sync job's upstream call count exact (index + 18 typeRoster calls).
Runs manually (`php artisan pokemon:sync`) or on a weekly schedule registered in `routes/console.php`;
nothing in the compose stack runs `schedule:work`, so the manual command is the documented fallback.

**Chat** (`app/Models/Conversation`/`Message`, `app/Events/MessageSent`, Reverb): private channel per
conversation (`conversation.{id}`, authorized via `ConversationPolicy::view` in `routes/channels.php`)
plus a presence channel (`online`) for the roster. Tests that need real channel-authorization behavior
must call the `useReverbBroadcaster()` helper (`tests/Pest.php`) — `phpunit.xml` forces
`BROADCAST_CONNECTION=null` globally (whose `auth()` is a no-op), and `routes/channels.php` only
re-registers its callbacks against the live broadcaster when explicitly re-required.

**Config-driven behavior, not hardcoding:** page sizes, badge caps, sort options (`config/pokemon.php`,
`config/favorites.php`), message length limits tied to the `messages.body` column width, and every
PokeAPI tunable above are read from `config/*.php`, each annotated with *why* the value is fixed rather
than env-overridable (PRD acceptance criteria pin exact numbers for several of them). Prefer extending
these config files over introducing magic numbers.

## Spec-driven workflow

This repo is built feature-by-feature against `docs/prd.md`. Each feature `FNN-slug` has its own
`docs/FNN-slug/{spec.md,plan.md}` (spec = architecture/contracts/data model/testing strategy; plan =
ordered high-level implementation steps referencing the spec — see
`.claude/skills/implement-feature/SKILL.md` for the exact document contract). Cross-cutting architecture
decisions that deviate from the PRD/specs are recorded as ADRs in `docs/adr/`. When implementing or
modifying a feature, read its `spec.md`/`plan.md` first — comments throughout the codebase (routes,
config, tests) routinely cite the `FNN` feature that motivated a given constraint.

`docs/prd.md` §7 lists what's explicitly out of scope for the product (admin panel, email password
reset, cloud deploy, CI/CD, i18n) — don't reintroduce these without checking with the user first.
