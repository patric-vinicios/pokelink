# Technical Specification: Pokémon Details Modal

## 1. Technical Overview

Pokémon details are displayed in a global Livewire/Volt modal without leaving the catalog. A result
card dispatches `open-pokemon` directly to `pokemon.detail-modal`; the component resolves the local
catalog row immediately and lazily requests PokeAPI-only fields through `PokeApiClient`.

The former standalone detail page no longer exists. Authenticated requests to the legacy
`/pokemon/{slug}` URL redirect to `/?pokemon={slug}`, where the same modal opens on initial render.
This keeps bookmarks and shared links working while maintaining one detail implementation.

### Included

- A single global `pokemon.detail-modal` component mounted by the authenticated app layout
- Full-card buttons that open the modal without changing the current catalog URL, filters, page, or
  scroll position
- Immediate local header data: artwork, name, national number, types, and favorite state
- Lazy abilities, base stats, height, weight, and pt-BR flavor text
- Loading, unavailable, retry, not-found, missing-field, and broken-artwork states
- Focusable dialog semantics, Escape/backdrop/close-button dismissal, and catalog scroll locking
- Backward-compatible authenticated redirects from `/pokemon/{slug}`

### Excluded

- A standalone Pokémon detail screen
- A second modal instance per card
- Direct PokeAPI access outside `PokeApiClient`
- Persistence of detail payloads outside the existing Redis response cache

## 2. Architecture

| Layer | Component | Responsibility |
|---|---|---|
| Catalog card | `resources/views/components/pokemon-card.blade.php` | Dispatches the selected slug to the global modal |
| App shell | `resources/views/layouts/app.blade.php` | Mounts exactly one detail-modal instance |
| Detail UI | `resources/views/livewire/pokemon/detail-modal.blade.php` | Owns selection, local lookup, lazy detail loading, retry, and rendered states |
| Shared dialog | `resources/views/components/modal.blade.php` | Focus handling, backdrop, transitions, stacking, and scroll locking |
| Legacy route | `routes/web.php` | Redirects `/pokemon/{slug}` to the catalog query deep link |
| Data service | `App\Services\PokeApi\PokeApiClient` | Supplies resilient and cached upstream detail data |

```mermaid
flowchart LR
    Card[Catalog card] -->|dispatchTo open-pokemon slug| Modal[Global detail modal]
    DeepLink[/pokemon/slug] -->|redirect| Query[/?pokemon=slug]
    Query --> Modal
    Modal --> Local[(Pokemon + Types)]
    Modal -->|wire:init loadDetail| Client[PokeApiClient]
    Client --> Cache[(Redis cache)]
    Client --> API[PokeAPI]
```

## 3. Component Contract

### Events

| Event | Payload | Consumer | Result |
|---|---|---|---|
| `open-pokemon` | `{ slug: string }` | `pokemon.detail-modal` | Resets stale state, resolves the local row, and dispatches `open-modal` |
| `open-modal` | `pokemon-details` | Shared Alpine modal | Shows the dialog and locks catalog scrolling |
| `close-modal` | `pokemon-details` | Shared Alpine modal | Hides the dialog and restores catalog scrolling |

### Public actions and state

| Member | Behavior |
|---|---|
| `mount()` | Reads the optional `pokemon` query parameter and prepares an initially open modal |
| `openPokemon(string $slug)` | Prepares a new selection and opens the existing modal instance |
| `loadDetail()` | Loads PokeAPI-only fields once per selection; invoked by `wire:init` |
| `retry()` | Clears the unavailable state and repeats `loadDetail()` |
| `favorited()` | Resolves the authenticated user's current favorite state for the selected number |
| `statRows()` | Maps the six base stats to labels, percentages, and three color bands |

Every call to `prepare()` resets all Pokémon-specific state before applying the next local row. This
prevents data from one card flashing or leaking into the next modal opening.

## 4. Rendered States

| State | Rendering |
|---|---|
| Local row found, detail pending | Local identity and artwork plus a detail skeleton |
| Detail loaded | Abilities, stat bars, height, weight, and optional flavor text |
| Detail service unavailable | Local identity remains visible with a retry action |
| Pokémon absent locally, upstream succeeds | Header and details are populated from the upstream payload |
| Pokémon absent locally and upstream returns 404 | Dedicated “Pokémon não encontrado.” state |
| Pokémon absent locally and upstream unavailable | Unavailable state, never a false not-found message |
| Empty abilities or stats | “Informação indisponível.” inside the affected section |
| Artwork request fails | Inline placeholder; the dialog layout remains stable |

## 5. Routing and Navigation

| Path | Middleware | Response |
|---|---|---|
| `/` | `auth`, `auth.session` | Catalog; `?pokemon={slug}` opens the modal initially |
| `/pokemon/{slug}` | `auth`, `auth.session` | Redirect to `/?pokemon={slug}` |

Card interactions do not call `route('pokemon.show')` and do not mutate the browser URL. Search text,
type filters, pagination, and the scroll position therefore remain intact when the dialog closes.

## 6. Data and Resilience

The modal reads identity data from `pokemon`, `types`, and `pokemon_type`. It never writes those tables.
Only `PokeApiClient::pokemonDetail()` may request upstream fields. Its existing timeout, retry, rate
limit, circuit breaker, and Redis cache behavior remains the resilience boundary.

The local row is deliberately rendered before the upstream round trip. A cache miss or PokeAPI outage
must not block the modal from opening or remove data already available in MySQL.

## 7. Accessibility and Responsive Behavior

- The content uses `role="dialog"`, `aria-modal="true"`, and an `aria-labelledby` title.
- The close button has an explicit Portuguese accessible name.
- Keyboard focus is trapped while open and Escape closes the modal.
- The backdrop closes the modal, and the dialog is stacked above the fixed navigation.
- At widths below 700px, summary and information panels collapse to one column and the dialog body
  becomes vertically scrollable within the viewport.

## 8. Testing Strategy

`tests/Feature/PokemonShowTest.php` covers authentication, legacy redirects, query deep links, local and
upstream resolution, lazy detail loading, caching, retries, not-found states, missing fields, stat bands,
and broken artwork. `tests/Feature/PokemonResultsListTest.php` asserts that cards dispatch the modal event
instead of linking to a detail page.

`tests/Browser/pokemon-detail-modal.spec.js` exercises the real browser flow: card click, visible dialog,
unchanged URL, catalog scroll lock, async detail rendering, close behavior, and reuse for another card.
