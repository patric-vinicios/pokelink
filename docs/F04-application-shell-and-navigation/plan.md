# Implementation Plan: Application Shell and Navigation

**Prerequisites:**
- F02 (Authentication and Session Management) merged — `auth` middleware, Breeze/Livewire Volt scaffold, database sessions
- Tailwind CSS and Alpine.js already available through the existing Vite pipeline — no new frontend dependency
- Existing Breeze primitives (`primary-button`, `secondary-button`, `danger-button`, `text-input`, `input-label`, `input-error`, `modal`, `dropdown`) stay as they are; this feature does not modify them

---

### Stage 1: Shell Layout

**1. Application Shell Layout** - Rebuild the shell in place at the view the existing `<x-app-layout>` class component already renders. Add the navigation mount point, the capped main content area, and the footer to the full HTML document Breeze scaffolded there.

**2. Application Version Configuration** - Add a configuration value for the application version, sourced from a new environment variable with a working default, following the existing `app.name`/`APP_NAME` pattern. Extend the environment contract file with the new key so the existing configuration-completeness test keeps passing.

**3. Footer and Global Loading Indicator** - Render the footer inside the shell component, showing the configured version. Add a global loading indicator driven by Livewire's round-trip lifecycle, appearing only past the 200 ms threshold the spec defines.

---

### Stage 2: Navigation

**4. Navigation Destinations** - Replace the single Breeze "Dashboard" link in the navigation component with the four Portuguese destinations the spec defines, on both the desktop link row and the existing responsive hamburger panel.

**5. Active State Highlighting** - Implement the per-destination route-matching rule from the spec, including the forward-looking grouping that keeps Início active on Pokémon detail routes a later feature will add.

**6. Toast Notification System** - Add the toast container component and its client-side queue behavior as described in the spec, mounted once inside the shell so any current or future Livewire component can dispatch a notification through it.

---

### Stage 3: Shared UI Primitives

**7. Card Primitive** - Add the generic content-card component described in the spec's component overview, ready for later features to compose their own layouts on top of.

**8. Badge Primitive** - Add the colored label component with its configurable color prop, ready for later features' type and status indicators.

**9. Empty State Primitive** - Add the component combining an illustration slot, a message, and an optional action slot, used immediately by this feature's own placeholder pages and later by search and favorites empty states.

---

### Stage 4: Routing and Placeholder Pages

**10. Route Table Update** - Rename the profile route's path and name to match the spec, and add the two new routes for the destinations owned by later features, all behind the existing `auth` middleware.

**11. Meu Perfil Page Migration** - Move the existing profile view to its new file and route without altering its form content or behavior.

**12. Favoritos and Chat Placeholders** - Add the two placeholder pages described in the spec, built on the empty-state primitive, so every navigation destination resolves to a working page for the remaining waves.

**13. Início Placeholder Content** - Translate the root page's visible header and body text to Portuguese, consistent with the rest of the shell, ahead of F07 replacing its content entirely.

**14. Cross-Feature Test Path Fixes** - Update the two pre-existing authentication test files that hardcode the old profile path so the suite keeps passing after the rename, and extend the guest-redirect coverage to the two newly-added protected routes as described in the spec.
