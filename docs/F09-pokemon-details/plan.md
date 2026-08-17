# Implementation Plan: Pokémon Details Modal

1. **Replace page navigation with an in-place interaction** — completed
   - Convert the card's primary interaction from an anchor to an accessible full-card button.
   - Dispatch the selected slug to one targeted Livewire component.

2. **Create the global detail modal** — completed
   - Move local lookup, PokeAPI detail loading, retry, stats, and failure states into
     `pokemon.detail-modal`.
   - Mount one instance in the authenticated app layout.
   - Preserve the existing favorite toggle inside the detail header.

3. **Remove the standalone detail screen** — completed
   - Delete `pages.pokemon.show`.
   - Keep `/pokemon/{slug}` as an authenticated compatibility redirect to the catalog query deep link.

4. **Add responsive and accessible presentation** — completed
   - Add desktop/mobile modal layout, backdrop, focus handling, close controls, and scroll locking.
   - Keep loading and error states within stable dialog dimensions.

5. **Verify behavior** — completed
   - Update F08/F09 feature coverage for modal dispatch and legacy redirects.
   - Add Playwright coverage for unchanged URL, async details, close behavior, and modal reuse.
   - Run the complete PHP suite, asset build, formatter, and visual regression tests.
