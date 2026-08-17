# Implementation Plan: Pokémon Details

**Prerequisites:**
- F05 (`PokeApiClient::pokemonDetail()` and its `Success`/`NotFound`/`Unavailable` outcome contract) merged and available
- F08 (the `pokemon.show` placeholder route/view to be replaced, the `?q=&tipo=&page=` URL contract its cards already append, and the `<x-pokemon-card>` silhouette-fallback pattern to mirror) merged and available
- `config('pokemon.type_labels')` and `config('pokemon.type_colors')`, already established by F06/F08

---

### Stage 1: Route and Component Resolution

**1. Detail Route** - Replace F08's placeholder `Route::view` registration for `/pokemon/{slug}` with a Volt route pointing at the new page component, keeping the same route name and middleware so F08's existing card links keep working unchanged.

**2. Local-First Slug Resolution** - Build the component's mount logic that looks up the slug against the local catalog, populating the header state from that row when found. When no local row exists, run the synchronous upstream fallback lookup and resolve mount-time state for all three outcomes: found upstream, confirmed not found, or upstream unavailable.

**3. Header State Normalization** - Normalize the header's type badges into one consistent shape regardless of whether they came from the local catalog's related types or from an upstream-only payload's raw type names, so the rendered header markup never needs to branch on data origin.

---

### Stage 2: Lazy Detail Loading

**4. Detail Fetch Action** - Add the component action that fetches the PokeAPI-only fields (abilities, base stats, height, weight, species text) for a locally-found Pokémon, triggered automatically after the page's first render rather than blocking it.

**5. Loading Skeleton** - Render a placeholder in the detail panel's position while the fetch action is in flight, swapping to real content once it resolves, consistent with the loading pattern already used for the results grid.

**6. Retry Action** - Add the action wired to the "Tentar novamente" button that re-runs the same detail fetch without a full page reload, for use whenever the fetch action fails.

---

### Stage 3: Detail Rendering

**7. Base Stat Bars** - Render the six base stats as labeled, color-banded bars scaled against their maximum possible value, falling back to an "unavailable" message for the whole section when the payload carries no stat data.

**8. Abilities List** - Render the Pokémon's abilities, marking any hidden ability distinctly, with the same per-section fallback message when the payload carries no ability data.

**9. Physical Details and Flavor Text** - Render the already-converted height and weight values and the Portuguese species description when present, alongside the sprite, name, and national number already shown in the header.

**10. Reserved Favorite Slot** - Add an empty, clearly marked placeholder beside the Pokémon's name for the favorite toggle a later feature will fill in, without implementing any toggle behavior here.

---

### Stage 4: Error States and Return Navigation

**11. Unavailable and Not-Found States** - Render the retry-capable warning block for every case where the upstream lookup itself fails (with or without local header data present) and the dedicated "não encontrado" state for a slug confirmed absent both locally and upstream, both inside the normal authenticated shell.

**12. Broken Sprite Fallback** - Apply the same inline silhouette fallback used by the results grid's cards to this page's larger artwork image.

**13. Return-to-Results Link** - Build the "Voltar aos resultados" link so it reconstructs the exact search term, type filter, and page number the user arrived from, falling back to the plain search page when there is no such context in the URL.

---

### Stage 5: Verification

**14. Test Suite — Detail Page** - Write the feature tests enumerated in the spec's Testing Strategy, covering guest access, the local-first and upstream-only resolution paths, the lazy detail load and its retry, every rendered state (loading, loaded, unavailable, not-found, missing-section), the return-navigation link, and the sprite fallback.

**15. Remove Superseded Placeholder Coverage** - Delete the placeholder route's test file and view now that this feature's own suite and component fully replace what they covered.
