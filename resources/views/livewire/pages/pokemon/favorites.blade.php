<?php

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

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'sort');
        $this->resetPage();
    }

    /**
     * No body needed — forces a fresh results() evaluation whenever any
     * favorite-toggle instance on this page reports a removal.
     */
    #[On('favorite-removed')]
    public function refreshResults(): void
    {
        //
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
        return auth()->user()->favorites()->exists();
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->sort !== 'recent';
    }

    #[Computed]
    public function noMatchMessage(): string
    {
        return "Nenhum Pokémon encontrado para '{$this->search}'.";
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Favoritos
        </h2>
    </x-slot>

    <div class="space-y-6">
        @if ($this->hasAnyFavorites)
            <x-card>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <x-input-label for="favorites-search" value="Buscar por nome" />
                        <x-text-input
                            wire:model.live.debounce.300ms="search"
                            id="favorites-search"
                            type="text"
                            class="block mt-1 w-full"
                            placeholder="Ex.: char"
                        />
                    </div>

                    <div class="sm:w-56">
                        <x-input-label for="favorites-sort" value="Ordenar por" />
                        <select
                            wire:model.live="sort"
                            id="favorites-sort"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            @foreach (config('favorites.sort_options') as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($this->hasActiveFilters)
                        <div>
                            <x-secondary-button wire:click="clearFilters">
                                Limpar filtros
                            </x-secondary-button>
                        </div>
                    @endif
                </div>
            </x-card>
        @endif

        @if (! $this->hasAnyFavorites)
            <x-card>
                <x-empty-state message="Você ainda não favoritou nenhum Pokémon.">
                    <x-slot name="action">
                        <a href="{{ route('dashboard') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                            Buscar Pokémon
                        </a>
                    </x-slot>
                </x-empty-state>
            </x-card>
        @elseif ($this->results->isEmpty())
            <x-card>
                <x-empty-state :message="$this->noMatchMessage">
                    <x-slot name="action">
                        <x-secondary-button wire:click="clearFilters">
                            Limpar filtros
                        </x-secondary-button>
                    </x-slot>
                </x-empty-state>
            </x-card>
        @else
            <div>
                <div wire:loading>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        @for ($i = 0; $i < 20; $i++)
                            <x-pokemon-card-skeleton wire:key="favorites-skeleton-{{ $i }}" />
                        @endfor
                    </div>
                </div>

                <div wire:loading.remove>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        @foreach ($this->results as $pokemon)
                            <x-pokemon-card
                                wire:key="favorite-pokemon-{{ $pokemon->number }}"
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
                                        :confirm-removal="true"
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
            </div>
        @endif
    </div>
</div>
