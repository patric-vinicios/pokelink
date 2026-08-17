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

        // A page requested beyond the last valid one (stale bookmark, direct
        // URL edit) clamps to the last valid page instead of rendering an
        // empty grid — repaginating is the only way to fetch that page's
        // rows, since the first paginate() call already fetched the wrong
        // page's (empty) items.
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

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Início
        </h2>
    </x-slot>

    <div class="space-y-6">
        <x-card>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <x-input-label for="search" value="Buscar por nome" />
                    <x-text-input
                        wire:model.live.debounce.300ms="search"
                        id="search"
                        type="text"
                        class="block mt-1 w-full"
                        placeholder="Ex.: char"
                        autofocus
                    />
                </div>

                <div class="sm:w-56">
                    <x-input-label for="type" value="Tipo" />
                    <select
                        wire:model.live="type"
                        id="type"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">Todos os tipos</option>
                        @foreach ($this->types as $option)
                            <option value="{{ $option->label_pt }}">{{ ucfirst($option->label_pt) }}</option>
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

        @if ($this->catalogEmpty)
            <x-card wire:poll.5s>
                <x-empty-state message="Catálogo sincronizando... isso leva menos de um minuto.">
                    <x-slot name="icon">
                        <svg class="h-10 w-10 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
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
                <p class="mb-4 text-sm text-gray-600">{{ $this->results->total() }} Pokémon encontrados</p>

                <div wire:loading>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        @for ($i = 0; $i < 20; $i++)
                            <x-pokemon-card-skeleton wire:key="skeleton-{{ $i }}" />
                        @endfor
                    </div>
                </div>

                <div wire:loading.remove>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        @foreach ($this->results as $pokemon)
                            <x-pokemon-card
                                wire:key="pokemon-{{ $pokemon->number }}"
                                :number="$pokemon->number"
                                :slug="$pokemon->slug"
                                :name="$pokemon->name"
                                :types="$pokemon->types"
                                :sprite="$pokemon->sprite_url"
                                :search="$this->search"
                                :type="$this->type"
                                :page="$this->results->currentPage()"
                            >
                                <x-slot name="favorite">
                                    <livewire:pokemon.favorite-toggle
                                        :number="$pokemon->number"
                                        :name="$pokemon->name"
                                        :favorited="(bool) $pokemon->favorited"
                                        variant="icon"
                                        :confirm-removal="false"
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
