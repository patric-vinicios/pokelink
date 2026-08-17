<?php

use App\Models\Favorite;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

new class extends Component
{
    public int $number;

    public string $name;

    public bool $favorited = false;

    public string $variant = 'icon';

    public function mount(
        int $number,
        string $name,
        bool $favorited,
        string $variant = 'icon',
    ): void {
        $this->number = $number;
        $this->name = $name;
        $this->favorited = $favorited;
        $this->variant = $variant;
    }

    public function toggle(): void
    {
        $favorite = Favorite::query()
            ->where('user_id', auth()->id())
            ->where('pokemon_number', $this->number)
            ->first();

        if ($favorite !== null) {
            Gate::authorize('delete', $favorite);
        }

        try {
            if ($favorite !== null) {
                $favorite->delete();
                $this->favorited = false;
                $this->dispatch('toast', message: 'Removido dos favoritos.', type: 'success');
                $this->dispatch('favorite-removed');
            } else {
                Favorite::query()->firstOrCreate([
                    'user_id' => auth()->id(),
                    'pokemon_number' => $this->number,
                ]);
                $this->favorited = true;
                $this->dispatch('toast', message: 'Adicionado aos favoritos.', type: 'success');
            }
        } catch (Throwable) {
            $this->favorited = $favorite !== null;
            $this->dispatch('toast', message: 'Não foi possível salvar o favorito. Tente novamente.', type: 'error');

            return;
        }

        $this->dispatch('favorite-toggled');

    }
}; ?>

<div x-data="{ filled: @entangle('favorited') }">
    @if ($variant === 'icon')
        <button
            type="button"
            wire:click.stop="toggle"
            x-on:click.stop="filled = ! filled"
            wire:loading.attr="disabled"
            aria-label="{{ $favorited ? 'Remover dos favoritos' : 'Favoritar' }}"
            aria-pressed="{{ $favorited ? 'true' : 'false' }}"
            class="pokemon-favorite-button rounded-full bg-white/90 p-1.5 opacity-0 shadow transition group-hover:opacity-100 group-focus-within:opacity-100 focus:opacity-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            :class="{ 'opacity-100 pokemon-favorite-button--active': filled }"
        >
            <svg x-show="filled" @unless ($favorited) x-cloak @endunless class="pokemon-favorite-heart--filled h-5 w-5 text-red-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M11.645 20.91a.75.75 0 0 0 .71 0l.117-.064C17.74 17.95 21 14.65 21 10.5A5.25 5.25 0 0 0 12 6.82 5.25 5.25 0 0 0 3 10.5c0 4.15 3.26 7.45 8.528 10.346l.117.064Z" />
            </svg>
            <svg x-show="! filled" @if ($favorited) x-cloak @endif class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z" />
            </svg>
        </button>
    @else
        <x-secondary-button type="button" wire:click.stop="toggle" x-on:click.stop="filled = ! filled" wire:loading.attr="disabled">
            <span x-text="filled ? 'Remover dos favoritos' : 'Favoritar'"></span>
        </x-secondary-button>
    @endif
</div>
