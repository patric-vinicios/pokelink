# Implementation Plan: Pokémon Catalog Sync

**Prerequisites:**
- F05 (`App\Services\PokeApi\PokeApiClient`) merged and available — `index()` and `typeRoster(string $type)` are the only methods this feature calls
- Horizon worker and Redis queue connection already running (F01)
- `database/seeders/DatabaseSeeder.php`'s documented F06 extension-point comment as the dispatch location

---

### Stage 1: Schema and Domain Models

**1. Type Vocabulary Table and Model** - Create the migration and Eloquent model for the 18 canonical Pokémon types, each carrying its PokeAPI slug and pt-BR label as described in the spec's data model. This table has no dependency on the sync job's runtime data — its 18 rows come from local configuration.

**2. Pokémon Catalog Table and Model** - Create the migration and Eloquent model for the local catalog, using the national dex number as the primary key rather than an auto-increment id, per the spec's technical decision on name/slug derivation.

**3. Pokémon-Type Pivot Table and Relationship** - Create the migration for the join table with the composite unique constraint described in the spec, and wire the `belongsToMany` relationship on both models. This table depends on both prior migrations for its foreign keys.

**4. Model Factories** - Add factories for both new models so downstream features (F07, F08, F09, F10) and this feature's own tests can generate catalog data without depending on a real sync.

---

### Stage 2: Sync Job

**5. Sync Configuration** - Add the dedicated configuration file holding the 18 type-label translations, batch size, retry/backoff/timeout values, and schedule timing, following this codebase's established per-integration config-file convention.

**6. Sync Failure Exception** - Add the descriptive exception type the job throws when an upstream call is unavailable, not found, or structurally malformed, so failures surface with the failing endpoint named rather than as a generic error.

**7. Sync Result Value Object** - Add the small immutable object carrying the created/updated/total counts that the job returns, consumed by both the artisan command and any future caller that needs sync outcomes.

**8. Catalog Sync Job — Fetch Phase** - Implement the job's orchestration of the index call and the 18 type-roster calls through `PokeApiClient`, validating that each response is successful and structurally complete before any database write begins, per the spec's failure-mode table.

**9. Catalog Sync Job — Write Phase** - Implement the batched, transactional upsert of `pokemon` rows (with the pre-write existence diff needed for the created/updated split) and `pokemon_type` rows, plus the always-upserted 18 `types` rows, following the spec's batching and idempotency decisions.

**10. Job Retry and Observability Configuration** - Configure the job's `$tries`, `$backoff`, `$timeout`, and Horizon tag, and add the per-batch progress log line described in the spec's Full Scope additions.

---

### Stage 3: Dispatch Surfaces

**11. Seeder Dispatch** - Replace the F01-authored extension-point comment in `DatabaseSeeder` with the actual dispatch call, keeping the seeder itself free of any network-blocking behavior.

**12. Sync Artisan Command** - Implement `php artisan pokemon:sync`, running the job synchronously and printing the summary sentence from the spec's Exposed Interfaces section.

**13. Weekly Schedule Registration** - Register the command on the application's scheduler at the confirmed weekly cadence, and document in the README (or a code comment, per the spec's assumption) that firing it automatically requires an external cron or scheduler-loop process not provisioned by F01.

---

### Stage 4: Verification

**14. Fixture-Backed Test Suite** - Write the feature tests enumerated in the spec's Testing Strategy: exact call-count, idempotent double-run, batching, created/updated split, both failure paths (unavailable and malformed index), the orphaned-member-name edge case, retry/tag configuration, command output, seeder wiring, schedule registration, and type-label completeness.

**15. Manual Verification Against Real Data Shapes** - Confirm the job's fixtures mirror F05's actual `index()`/`typeRoster()` return shapes exactly (not an approximation), satisfying the PRD's Cross-Feature Integration criterion tying F05's real output to F06's tables.
