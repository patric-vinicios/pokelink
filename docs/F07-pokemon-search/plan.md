# Implementation Plan: Pokémon Search

**Prerequisites:**
- F06 (`App\Models\Pokemon`, `App\Models\Type`, `pokemon`/`types`/`pokemon_type` tables) merged and available
- F04 (application shell, `card`/`badge`/`empty-state` primitives, `route('dashboard')` naming convention) merged and available
- `livewire/volt` already installed and in use for full-page components (`pages.auth.login`, `pages.auth.register`)

---

### Stage 1: Search Screen

**1. Volt Search Component and Route** - Replace the `/` route's placeholder view with a Volt page component that owns the name and type filter state, both mirrored into the URL query string, and reads exclusively from F06's local catalog tables. Keep the existing `dashboard` route name intact so every current reference to it keeps working.

**2. Filter Query and Pagination Reset** - Implement the combined name/type filtering query with AND semantics and ordering by national number, wired to Livewire's pagination trait so that changing either filter returns the user to the first page, per the spec's component contract.

**3. Clear Filters and Result Count** - Add the one-click control that resets both filters and the page together, and the summary line reporting the total number of matches for the active filters, per the spec's rendered-states table.

---

### Stage 2: Non-Happy-Path States

**4. Empty-Catalog and No-Match States** - Implement the two distinct empty conditions described in the spec: the catalog having no rows yet (auto-refreshing sync-in-progress message) versus the active filters simply matching nothing (named empty state with a clear-filters action), driven by the independent signal the spec specifies rather than inferring one from the other.

**5. Minimal Results Display** - Render the matched Pokémon using the existing card and badge primitives with enough content (sprite, name, number, types) to be a genuinely usable search screen, deliberately stopping short of the polished grid behavior the spec assigns to a later feature.

---

### Stage 3: Cleanup and Verification

**6. Retire the Placeholder Dashboard View** - Remove the Blade view the new Volt route supersedes, and confirm no other reference to it remains.

**7. Test Suite** - Write the feature tests enumerated in the spec's Testing Strategy: case/accent-insensitive partial matching, type filtering, combined AND semantics, pagination reset on filter change, the clear-filters round-trip, URL reload restoring the identical result set, network-blocked operation, the empty-catalog state, and the type-select population and name-fragment lookup checks that exercise real data written by F06's sync.
