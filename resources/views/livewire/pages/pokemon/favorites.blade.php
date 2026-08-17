<?php

use App\Models\Type;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'ordenar')]
    public string $sort = 'recent';

    #[Url(as: 'tipo')]
    public string $type = '';

    public function mount(): void
    {
        $this->clearUnavailableType();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->clearUnavailableType();
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->sort = 'recent';
        $this->type = '';
        $this->resetPage();
    }

    /** Keep the selected filter valid after an item leaves the collection. */
    #[On('favorite-removed')]
    public function refreshResults(): void
    {
        if ($this->clearUnavailableType()) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function results()
    {
        $sort = $this->sort;

        $query = fn () => auth()->user()->favorites()
            ->with('types')
            ->when($this->search !== '', fn ($query) => $query->where(
                'pokemon.name',
                'like',
                '%'.addcslashes($this->search, '\\%_').'%'
            ))
            ->when($this->type !== '', fn ($query) => $query->whereHas(
                'types',
                fn ($typeQuery) => $typeQuery->where('label_pt', $this->type)
            ))
            ->when($sort === 'name', fn ($query) => $query->orderBy('pokemon.name'))
            ->when($sort === 'number', fn ($query) => $query->orderBy('pokemon.number'))
            ->when(
                ! in_array($sort, ['name', 'number'], true),
                fn ($query) => $query->orderByDesc('favorites.created_at')
            );

        $results = $query()->paginate(config('favorites.per_page'));

        // Same beyond-last-page clamp guard as F08's search.blade.php.
        if ($results->lastPage() >= 1 && $results->currentPage() > $results->lastPage()) {
            $this->setPage($results->lastPage());

            $results = $query()->paginate(config('favorites.per_page'));
        }

        return $results;
    }

    #[Computed]
    public function hasAnyFavorites(): bool
    {
        return $this->favoriteCount > 0;
    }

    #[Computed]
    public function favoriteCount(): int
    {
        return auth()->user()->favorites()->count();
    }

    #[Computed]
    public function favoriteTypeCount(): int
    {
        return Type::query()
            ->join('pokemon_type', 'types.id', '=', 'pokemon_type.type_id')
            ->join('favorites', 'favorites.pokemon_number', '=', 'pokemon_type.pokemon_number')
            ->where('favorites.user_id', auth()->id())
            ->distinct()
            ->count('types.id');
    }

    #[Computed]
    public function latestFavoriteNumber(): string
    {
        $number = auth()->user()->favorites()
            ->orderByDesc('favorites.created_at')
            ->value('pokemon.number');

        return $number === null
            ? '—'
            : '#'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    #[Computed]
    public function types()
    {
        return Type::query()
            ->select('types.*')
            ->join('pokemon_type', 'types.id', '=', 'pokemon_type.type_id')
            ->join('favorites', 'favorites.pokemon_number', '=', 'pokemon_type.pokemon_number')
            ->where('favorites.user_id', auth()->id())
            ->distinct()
            ->orderBy('types.label_pt')
            ->get();
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->type !== '' || $this->sort !== 'recent';
    }

    #[Computed]
    public function noMatchMessage(): string
    {
        return $this->search !== ''
            ? "Nenhum Pokémon encontrado para '{$this->search}'."
            : 'Nenhum Pokémon favorito encontrado para o tipo selecionado.';
    }

    private function clearUnavailableType(): bool
    {
        if ($this->type === '' || $this->types->contains('label_pt', $this->type)) {
            return false;
        }

        $this->type = '';

        return true;
    }
}; ?>

<x-pokedex.hero
    class="favorites-page"
    eyebrow="Sua coleção pessoal"
    title="Seus Pokémon"
    highlight="favoritos"
    :inline-title="true"
    :stats-columns="3"
    stats-label="Resumo dos favoritos"
>
    <x-slot name="copy">
        Monte sua seleção preferida e acesse rapidamente<br class="hidden sm:block">
        seus companheiros mais especiais.
    </x-slot>

    <x-slot name="stats">
        <x-pokedex.stat
            tone="gold"
            :value="$this->favoriteCount"
            label="Favoritos"
        >
            <x-slot name="icon">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3Z" />
                </svg>
            </x-slot>
        </x-pokedex.stat>

        <x-pokedex.stat
            tone="blue"
            :value="$this->favoriteTypeCount"
            label="Tipos diferentes"
        >
            <x-slot name="icon">
                <svg viewBox="0 0 24 24" fill="none">
                    <rect x="4" y="4" width="6" height="6" rx="1" />
                    <rect x="14" y="4" width="6" height="6" rx="1" />
                    <rect x="4" y="14" width="6" height="6" rx="1" />
                    <rect x="14" y="14" width="6" height="6" rx="1" />
                </svg>
            </x-slot>
        </x-pokedex.stat>

        <x-pokedex.stat
            tone="purple"
            :value="$this->latestFavoriteNumber"
            label="Último adicionado"
        >
            <x-slot name="icon">
                <span class="mini-pokeball mini-pokeball--white"></span>
            </x-slot>
        </x-pokedex.stat>
    </x-slot>

    <div class="trainer-page-content favorites-page-content">
        @if ($this->hasAnyFavorites)
            <section class="catalog-filters favorites-filters" aria-label="Filtros dos favoritos">
                <div class="catalog-search-field">
                    <label for="favorites-search">Buscar por nome</label>
                    <div class="catalog-input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="11" cy="11" r="6" />
                            <path d="m16 16 4 4" />
                        </svg>
                        <input
                            wire:model.live.debounce.300ms="search"
                            id="favorites-search"
                            type="search"
                            placeholder="Ex.: char"
                            autocomplete="off"
                        />
                    </div>
                </div>

                <x-pokedex.type-filter
                    :types="$this->types"
                    :selected="$this->type"
                    id="favorites-type"
                    label="Filtrar por tipo"
                >
                    <x-slot name="action">
                        @if ($this->hasActiveFilters)
                            <a href="{{ route('favoritos') }}" wire:navigate>Limpar filtros</a>
                        @endif
                    </x-slot>
                </x-pokedex.type-filter>
            </section>
        @endif

        @if (! $this->hasAnyFavorites)
            <section class="trainer-panel trainer-empty-state">
                <div class="trainer-empty-pokeball" aria-hidden="true"><span></span></div>
                <span class="trainer-page-eyebrow">Sua jornada começa aqui</span>
                <h2>Você ainda não favoritou nenhum Pokémon.</h2>
                <p>Explore a Pokédex e toque no coração para montar sua coleção.</p>
                <a href="{{ route('dashboard') }}" wire:navigate class="trainer-primary-link">
                    Buscar Pokémon
                    <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m7 4 6 6-6 6" />
                    </svg>
                </a>
            </section>
        @elseif ($this->results->isEmpty())
            <section class="trainer-panel trainer-empty-state trainer-empty-state--compact">
                <div class="trainer-empty-search" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <circle cx="10.5" cy="10.5" r="6.5" />
                        <path stroke-linecap="round" d="m20 20-4.5-4.5" />
                    </svg>
                </div>
                <h2>{{ $this->noMatchMessage }}</h2>
                <p>Tente outro termo ou volte para a coleção completa.</p>
                <a href="{{ route('favoritos') }}" wire:navigate class="trainer-secondary-button">
                    Limpar filtros
                </a>
            </section>
        @else
            <section class="favorites-collection" aria-label="Pokémon favoritos">
                <div class="favorites-collection-heading">
                    <div>
                        <span class="trainer-page-eyebrow">Pokédex pessoal</span>
                        <h2>Seus Pokémon</h2>
                    </div>

                    <div class="favorites-sort-control">
                        <label for="favorites-sort">Ordenar por</label>
                        <div class="favorites-sort-select">
                            <select wire:model.live="sort" id="favorites-sort">
                                @foreach (config('favorites.sort_options') as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="m6 8 4 4 4-4" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div wire:loading wire:target="search, type, sort, clearFilters, gotoPage, previousPage, nextPage">
                    <div class="pokemon-grid grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        @for ($i = 0; $i < 20; $i++)
                            <x-pokemon-card-skeleton wire:key="favorites-skeleton-{{ $i }}" />
                        @endfor
                    </div>
                </div>

                <div wire:loading.remove wire:target="search, type, sort, clearFilters, gotoPage, previousPage, nextPage">
                    <div class="pokemon-grid grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        @foreach ($this->results as $pokemon)
                            <x-pokemon-card
                                wire:key="favorite-pokemon-{{ $pokemon->number }}"
                                wire:transition.opacity.duration.150ms
                                :number="$pokemon->number"
                                :slug="$pokemon->slug"
                                :name="$pokemon->name"
                                :types="$pokemon->types"
                                :sprite="$pokemon->sprite_url"
                            >
                                <x-slot name="favorite">
                                    <livewire:pokemon.favorite-toggle
                                        :number="$pokemon->number"
                                        :name="$pokemon->name"
                                        :favorited="true"
                                        variant="icon"
                                        :key="'favorite-toggle-'.$pokemon->number"
                                    />
                                </x-slot>
                            </x-pokemon-card>
                        @endforeach
                    </div>

                    <div class="mt-6">
                        {{ $this->results->links() }}
                    </div>
                </div>
            </section>
        @endif
    </div>
</x-pokedex.hero>
