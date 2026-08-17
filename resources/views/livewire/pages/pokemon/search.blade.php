<?php

use App\Models\Favorite;
use App\Models\Pokemon;
use App\Models\Type;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'tipo')]
    public string $type = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingType(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'type');
        $this->resetPage();
    }

    #[Computed]
    public function results()
    {
        $query = fn () => Pokemon::query()
            ->with('types')
            ->select('pokemon.*')
            ->addSelect(['favorited' => Favorite::query()
                ->selectRaw('1')
                ->whereColumn('pokemon_number', 'pokemon.number')
                ->where('user_id', auth()->id())
                ->limit(1),
            ])
            ->when($this->search !== '', fn ($query) => $query->where(
                'name',
                'like',
                '%'.addcslashes($this->search, '\\%_').'%'
            ))
            ->when($this->type !== '', fn ($query) => $query->whereHas(
                'types',
                fn ($typeQuery) => $typeQuery->where('label_pt', $this->type)
            ))
            ->orderBy('number');

        $results = $query()->paginate(config('pokemon.search.per_page'));

        if ($results->lastPage() >= 1 && $results->currentPage() > $results->lastPage()) {
            $this->setPage($results->lastPage());

            $results = $query()->paginate(config('pokemon.search.per_page'));
        }

        return $results;
    }

    #[Computed]
    public function types()
    {
        return Type::query()->orderBy('label_pt')->get();
    }

    #[Computed]
    public function catalogCount(): int
    {
        return Pokemon::query()->count();
    }

    #[Computed]
    public function favoriteCount(): int
    {
        return Favorite::query()->where('user_id', auth()->id())->count();
    }

    #[Computed]
    public function catalogEmpty(): bool
    {
        return Pokemon::query()->doesntExist();
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->type !== '';
    }

    #[Computed]
    public function noMatchMessage(): string
    {
        return $this->search !== ''
            ? "Nenhum Pokémon encontrado para '{$this->search}'."
            : 'Nenhum Pokémon encontrado para o tipo selecionado.';
    }
}; ?>

<x-pokedex.hero
    eyebrow="Bem-vindo(a) à sua Pokédex"
    title="Explore sua"
    highlight="Pokédex"
    stats-label="Resumo do catálogo"
>
    <x-slot name="copy">
        Descubra, conheça e colecione todos<br class="hidden sm:block">
        os Pokémon do mundo!
    </x-slot>

    <x-slot name="stats">
        <x-pokedex.stat
            tone="red"
            :value="number_format($this->catalogCount, 0, ',', '.')"
            label="Pokémon encontrados"
        >
            <x-slot name="icon">
                <span class="mini-pokeball mini-pokeball--white"></span>
            </x-slot>
        </x-pokedex.stat>

        <x-pokedex.stat
            tone="blue"
            :value="$this->types->count()"
            label="Tipos de Pokémon"
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
            tone="gold"
            :value="$this->favoriteCount"
            label="Favoritos marcados"
        >
            <x-slot name="icon">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3Z" />
                </svg>
            </x-slot>
        </x-pokedex.stat>

        <x-pokedex.stat
            tone="purple"
            :value="config('pokemon.search.per_page')"
            label="Pokémon por página"
        >
            <x-slot name="icon">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z" />
                    <circle cx="12" cy="10" r="2" />
                </svg>
            </x-slot>
        </x-pokedex.stat>
    </x-slot>

        <section class="catalog-filters" aria-label="Filtros do catálogo">
            <div class="catalog-search-field">
                <label for="search">Buscar por nome</label>
                <div class="catalog-input-wrap">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="11" cy="11" r="6" />
                        <path d="m16 16 4 4" />
                    </svg>
                    <input
                        wire:model.live.debounce.300ms="search"
                        id="search"
                        type="search"
                        placeholder="Ex.: pikachu, eevee, mew..."
                        autocomplete="off"
                        autofocus
                    />
                </div>
            </div>

            <x-pokedex.type-filter
                :types="$this->types"
                :selected="$this->type"
            >
                <x-slot name="action">
                    @if ($this->hasActiveFilters)
                        <button type="button" wire:click="clearFilters">Limpar filtros</button>
                    @endif
                </x-slot>
            </x-pokedex.type-filter>
        </section>

        @if ($this->catalogEmpty)
            <section class="catalog-state-card" wire:poll.5s>
                <x-empty-state message="Catálogo sincronizando... isso leva menos de um minuto.">
                    <x-slot name="icon">
                        <span class="catalog-sync-ball" aria-hidden="true"></span>
                    </x-slot>
                </x-empty-state>
            </section>
        @elseif ($this->results->isEmpty())
            <section class="catalog-state-card">
                <x-empty-state :message="$this->noMatchMessage">
                    <x-slot name="action">
                        <button type="button" class="catalog-clear-button" wire:click="clearFilters">
                            Limpar filtros
                        </button>
                    </x-slot>
                </x-empty-state>
            </section>
        @else
            <section class="catalog-results">
                <header class="catalog-results-toolbar">
                    <p>
                        <span class="mini-pokeball" aria-hidden="true"></span>
                        <strong>{{ number_format($this->results->total(), 0, ',', '.') }} Pokémon encontrados</strong>
                    </p>
                </header>

                <div wire:loading>
                    <div class="pokemon-grid grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        @for ($i = 0; $i < 20; $i++)
                            <x-pokemon-card-skeleton wire:key="skeleton-{{ $i }}" />
                        @endfor
                    </div>
                </div>

                <div wire:loading.remove>
                    <div class="pokemon-grid grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        @foreach ($this->results as $pokemon)
                            <x-pokemon-card
                                wire:key="pokemon-{{ $pokemon->number }}"
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
                                        :favorited="(bool) $pokemon->favorited"
                                        variant="icon"
                                        :key="'favorite-toggle-'.$pokemon->number"
                                    />
                                </x-slot>
                            </x-pokemon-card>
                        @endforeach
                    </div>

                    <div class="catalog-pagination">
                        {{ $this->results->links() }}
                    </div>
                </div>
            </section>
        @endif
</x-pokedex.hero>
