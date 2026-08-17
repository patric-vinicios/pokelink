# Implementation Plan: Automated Test Suite

**Prerequisites:**
- Running application stack (`docker compose up -d`), specifically the `app` container, since `php artisan test` must run inside it against the project's PHP/Pest install
- No new packages, environment variables, or configuration files — `pestphp/pest`, `phpunit.xml`, and `tests/Pest.php` are already in place from prior waves

### Stage 1: Coverage Verification

**1. Baseline suite run** - Run the full Pest suite inside the application container and record the current test count, file count, and total duration, confirming they already clear the PRD's numeric thresholds before any change is made.

**2. Traceability confirmation** - Walk the capability-to-test mapping in the spec's Testing Strategy section against the current state of the repository, confirming each referenced test file and test name still exists and still covers the PRD bullet it's mapped to. Flag anything that has drifted since the spec was written.

### Stage 2: Naming and Documentation Remediation

**3. Rename leftover English test names** - In the authentication feature test file, replace the 5 Laravel Breeze-default test descriptions with the pt-BR sentences specified in the spec, without changing any assertion, setup, or dataset.

**4. Refresh the README's testing section** - Rewrite the delivery documentation's testing section to state the suite's actual final numbers and drop the forward-looking placeholder language written before this feature existed, adding a filtered-run usage example.

### Stage 3: Final Verification

**5. Full suite re-run** - Run the complete suite again after both edits and confirm it still passes in full, with no regression in test count, file count, or duration, and that the renamed tests behave identically to before.

**6. Acceptance closeout** - Scan the suite for any remaining non-pt-BR test description and confirm the README numbers match the final run, closing every F13 acceptance bullet in PRD Section 9 against a concrete, current piece of evidence.
