# Implementation Plan: Results List and Pagination

**Prerequisites:**
- F07 (`pages.pokemon.search` Volt component, its `results`/`types`/`catalogEmpty`/`hasActiveFilters` computed properties, and the `?q=&tipo=&page=` URL contract) merged and available
- F04 (application shell, `card`/`badge`/`empty-state` primitives, the placeholder-route precedent for destinations owned by a later wave) merged and available
- `config/pokemon.php`'s existing `type_labels` and `search.per_page` keys, already established by F06/F07

---

### Stage 1: Shared Card Components and Configuration

**1. Type Color Configuration** - Add the type-to-color mapping to the Pokémon configuration file, covering all 18 canonical type slugs and reusing the existing badge component's supported colors, per the spec's technical decision.

**2. Result Card Component** - Build the reusable card component that renders a Pokémon's sprite, capitalized name, zero-padded national number, and colored type badges, wrapped in a focusable link to the detail route and carrying the current page and filter state, with a reserved empty slot for the future favorite control.

**3. Card Skeleton Component** - Build the loading-placeholder component matching the real card's dimensions, used to keep the grid's height stable during round-trips.

---

### Stage 2: Detail Route Placeholder

**4. Placeholder Route and View** - Register the Pokémon detail route and render a minimal "under construction" placeholder page for it, following the same pattern already used for the Favoritos and Chat destinations, so the result card's click-through target is a working page ahead of the feature that will replace it.

---

### Stage 3: Results Grid, Pagination, and Page Guard

**5. Responsive Results Grid** - Replace the search screen's minimal result markup with the responsive card grid built from the new card component, preserving the existing filtering and query logic untouched.

**6. Loading Skeleton Toggle** - Wire the skeleton component into the results area so it displays in place of the grid during any filter or pagination round-trip, and swaps back once the round-trip completes.

**7. Translated Pagination View** - Publish and translate the pagination view to Portuguese, covering the result-count summary and the numbered/ellipsis navigation controls, and register it as the application's default pagination view.

**8. Out-of-Range Page Guard** - Extend the existing results query so that a requested page beyond the last available one is clamped to the last valid page instead of rendering an empty grid, working identically for a direct page load and a Livewire interaction.

---

### Stage 4: Verification

**9. Test Suite — Results and Pagination** - Write the feature tests enumerated in the spec's Testing Strategy for the results grid: card content, per-page count and result-count summary, the page-clamp guard, click-through link and its carried context, the sprite fallback, the skeleton toggle, and the responsive column classes.

**10. Test Suite — Detail Placeholder** - Write the feature tests for the placeholder detail route: guest redirect, authenticated render, and the active-navigation state it inherits from the existing shell.
