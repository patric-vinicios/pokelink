# Implementation Plan: Favorites

**Prerequisites:**
- F06 (`pokemon`/`types`/`pokemon_type` tables and models) merged and available
- F08 (`<x-pokemon-card>`'s reserved `favorite` slot, the translated paginator, the page-clamp guard pattern) merged and available
- F09 (`pages.pokemon.show`'s reserved `data-favorite-slot` container) merged and available
- F04 primitives already in place: `<x-modal>`, `<x-badge>`, `<x-empty-state>`, the `toast`/`open-modal`/`close-modal` browser-event conventions, and `navigation.blade.php`'s existing nav-link structure

---

### Stage 1: Data Layer

**1. Favorites Migration** - Create the pivot table linking users to Pokémon, with a composite unique constraint guaranteeing at most one favorite per user/Pokémon pair, following the same foreign-key naming this codebase already uses for the Pokémon side of a pivot.

**2. Favorite Model and User Relationship** - Add the pivot model and wire the `favorites()` many-to-many relationship onto `User`, pointed at `Pokemon` through the new table.

**3. Favorite Policy** - Add the authorization policy gating the removal action, checking that the acting user owns the favorite being deleted, as a second line of defense behind the already user-scoped query.

**4. Favorites Configuration and Factory** - Add the feature-local config file (page size, badge cap, sort options) and the model factory used by this feature's own tests.

---

### Stage 2: Reusable Toggle Component

**5. Favorite Toggle Component** - Build the reusable toggle usable in two visual variants (an icon-only star and a labeled button), accepting the target Pokémon's identity and its seeded favorited state, and performing the idempotent add/remove write scoped to the authenticated user.

**6. Optimistic Feedback and Failure Revert** - Wire the instant visual flip on click, reconciled against the server's actual outcome once the write completes, so a failed write visibly reverts and surfaces a retry message instead of leaving the interface in a state the database doesn't have.

**7. Cross-Component Notifications** - Have every successful toggle announce itself so other, independently-rendered parts of the page (the navigation badge, and — for removals — the favorites page's own list) can react without a full page reload.

---

### Stage 3: Slot Integration and Favorites Page

**8. Result Card Slot** - Fill F08's reserved favorite slot on the search page's cards with the icon-variant toggle, computing each row's initial favorited state efficiently for the whole page of results at once.

**9. Detail Page Slot** - Replace F09's reserved placeholder container with the button-variant toggle, seeded from the detail page's own already-resolved Pokémon identity.

**10. Favorites Page** - Build the `/favoritos` route as a real page listing the authenticated user's collection with the same card component the search grid uses, including the name filter, the sort control, and pagination — replacing F04's placeholder entirely.

**11. Removal Confirmation** - Wire the favorites page's own toggle instances to require a confirmation step before a removal is written, fading the affected card out of the grid once confirmed and keeping the pagination valid if that was the page's last item.

**12. Navigation Badge** - Add the live favorite-count indicator next to the Favoritos destination in both the desktop and mobile navigation, capped at the configured maximum and updating whenever a toggle fires anywhere on the page.

---

### Stage 4: Verification

**13. Toggle and Cross-Slot Test Suite** - Write the feature tests covering idempotent add/remove, the optimistic-revert-on-failure path, and consistency between the card and detail-page instances of the toggle for the same Pokémon.

**14. Favorites Page Test Suite** - Write the feature tests covering listing, ordering, filtering, sorting, pagination, and both empty states.

**15. Removal and Authorization Test Suite** - Write the feature tests covering the confirmation flow, the page-clamp after removing a page's last item, cross-user data isolation, and the policy's denial of a cross-user removal attempt.

**16. Navigation Badge Test Suite** - Write the feature tests covering the badge's count, its cap, and its live update from a toggle fired elsewhere on the page.

**17. Remove Superseded Placeholder Coverage** - Delete the placeholder route's view and test file now that this feature's own suite and components fully replace what they covered.
