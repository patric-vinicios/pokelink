# ADR 0001 — Scaffold on Laravel 12 instead of Laravel 11

- **Status:** Accepted
- **Date:** 2026-08-16
- **Deciders:** Project owner
- **Scope:** F01 Application Environment and Delivery (affects every feature F02–F13)
- **Supersedes:** The `Laravel 11` pin stated in `docs/prd.md` §6 F01 and `docs/F01-application-environment-and-delivery/spec.md` §1

---

## Context and Problem Statement

The PRD and the F01 technical specification both pin the framework to **Laravel 11** ("`app` (PHP 8.3-FPM with Laravel 11)", PRD §6 F01). The first implementation step of the F01 plan is `composer create-project laravel/laravel:^11.0`.

That command now fails. Laravel 11 reached the end of its security-support window in March 2026, and Composer 2.10's advisory policy refuses to load any affected release by default:

```
Problem 1
  - Root composer.json requires laravel/framework ^11.31, found laravel/framework[v11.31.0, ..., v11.55.1]
    but these were not loaded, because they are affected by security advisories
    ("PKSA-m5cs-t1y6-qpcs", "PKSA-3r5d-mb8f-1qw9", "PKSA-mdq4-51ck-6kdq",
     "PKSA-8qx3-n5y5-vvnd", "PKSA-q46n-4fdk-zjr4", "PKSA-qzrn-rnz3-85w1")
```

The advisories cover the entire installable range. The `laravel/laravel` v11.6.1 skeleton requires `laravel/framework: ^11.31`, so there is no unaffected 11.x release to fall back to — the whole constraint window is blocked.

F01 therefore cannot be implemented as specified without an explicit decision about the framework version.

## Decision Drivers

- **The stack must boot from a clean clone with one command.** A dependency resolution that fails on the evaluator's machine defeats the entire purpose of F01 (PRD §4, "Guarantee a reproducible environment").
- **The audience is an evaluating engineer who reads before clicking** (PRD §3). Anything an evaluator would flag in the first five minutes — including `composer audit` output — is a product defect, not a footnote.
- **Deviation from the spec has a cost.** Every downstream feature (F02–F13) was specified against Laravel 11 conventions; the cheaper the migration, the less of the spec has to be re-read.
- **PHP 8.3-FPM is fixed.** The PRD names the runtime image explicitly, and the Dockerfile, extension set, and Compose service list are all written against it.
- **Breeze on the Livewire stack is fixed.** F01's spec §3 makes the Breeze scaffold the reason the login screen answers at boot; F02–F04 build on it.

## Considered Options

1. **Laravel 12** — latest release in active security support
2. **Laravel 13** — current major release
3. **Force Laravel 11** — disable Composer's advisory policy and install the EOL framework anyway

## Decision Outcome

**Chosen: Option 1 — Laravel 12** (`laravel/framework ^12.0`, resolving to v12.66.0).

Laravel 12 is the smallest deviation that produces an installable, non-vulnerable stack. It runs on the `php:8.3-fpm` base the spec already fixes (`php: ^8.2`), Breeze v2.4.2 supports it (`illuminate/* ^11.0|^12.0|^13.0`), and its skeleton and conventions are close enough to Laravel 11's that the F01 spec's component overview, entrypoint contract, and data model all hold unchanged.

### Consequences

**Positive**

- `composer create-project` resolves cleanly; `composer audit` reports no advisories on the shipped lockfile.
- The framework stays in security support for the delivery and evaluation window (Laravel 12 security fixes run to approximately February 2027).
- The default skeleton migrations F01 relies on — `users`/`sessions`/`password_reset_tokens`, `cache`/`cache_locks`, `jobs`/`job_batches`/`failed_jobs` — are unchanged from Laravel 11, so spec §6 Data Model needs no revision.
- `bootstrap/app.php` middleware and routing conventions, `config/` layout, and the `/up` health route are all carried over from Laravel 11, so spec §5 Exposed Interfaces needs no revision.

**Negative**

- The PRD and the F01 spec now state a version the code does not use. Both documents are amended to reference Laravel 12 and link to this ADR.
- Any Laravel 11-specific guidance consulted while implementing F02–F13 must be checked against the 12.x upgrade notes.

**Neutral**

- The delivered `README.md` names Laravel 12 and links here, so the evaluator sees the reasoning rather than an unexplained mismatch with the PRD.

## Pros and Cons of the Options

### Option 1 — Laravel 12 (chosen)

`laravel/framework ^12.0` → v12.66.0, requires `php ^8.2`.

- **Good:** Installs cleanly; no advisories block resolution.
- **Good:** In active security support through the evaluation window.
- **Good:** Runs on the specced `php:8.3-fpm` base without touching the Dockerfile's extension set.
- **Good:** Closest to the Laravel 11 conventions the whole spec set was written against — the migration surface is effectively "change one version constraint".
- **Good:** Breeze v2.4.2, Horizon 5.48, Reverb 1.11, and Livewire 3 are all supported and widely exercised together on this major.
- **Bad:** Shorter remaining support runway than Laravel 13.
- **Bad:** Not the newest major, which an evaluator might read as a dated choice.

### Option 2 — Laravel 13

`laravel/framework ^13.17` → v13.25.0, requires `php ^8.3`.

- **Good:** Longest support runway; unambiguously current.
- **Good:** Still satisfied by the specced `php:8.3-fpm` base — `^8.3` is met exactly.
- **Good:** Breeze v2.4.2 supports it (`illuminate/* ... |^13.0`).
- **Bad:** Largest drift from the Laravel 11 conventions every downstream spec assumes, so F02–F13 carry more re-verification.
- **Bad:** Pins the runtime to the floor of its PHP range — a future `php:8.2` fallback is no longer available.
- **Bad:** Livewire 4 is the pairing on this major; the Breeze/Livewire/Reverb/Horizon combination has less accumulated field use than on 12.x.

### Option 3 — Force Laravel 11

Keep `^11.31` and disable the policy:

```json
"config": { "policy": { "advisories": { "block": false } } }
```

- **Good:** The only option that matches the PRD and spec literally; zero documentation drift.
- **Bad:** Ships a framework with six known unpatched advisories, which is the opposite of what PRD §2 claims the product demonstrates.
- **Bad:** `composer audit` reports them immediately — an evaluator probing the edges (PRD §3) finds this fast.
- **Bad:** Requires a permanent, visible opt-out of a security control in the delivered `composer.json`, which itself invites the question the ADR would then have to answer anyway.
- **Bad:** No upstream fixes are coming; the framework is past end of security support.

## More Information

- Verified on Composer 2.10.2 / PHP 8.3.33 (the scaffolding container used to build the project, the host has no PHP toolchain).
- Blocked advisory IDs, for the record: `PKSA-m5cs-t1y6-qpcs`, `PKSA-3r5d-mb8f-1qw9`, `PKSA-mdq4-51ck-6kdq`, `PKSA-8qx3-n5y5-vvnd`, `PKSA-q46n-4fdk-zjr4`, `PKSA-qzrn-rnz3-85w1`. Details at <https://packagist.org/security-advisories/>.
- Revisit trigger: if Laravel 12 leaves security support before this project is delivered, re-open as a follow-up ADR for a 13.x migration.
