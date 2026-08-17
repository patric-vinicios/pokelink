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

    public bool $confirmRemoval = false;

    public function mount(
        int $number,
        string $name,
        bool $favorited,
        string $variant = 'icon',
        bool $confirmRemoval = false,
    ): void {
        $this->number = $number;
        $this->name = $name;
        $this->favorited = $favorited;
        $this->variant = $variant;
        $this->confirmRemoval = $confirmRemoval;
    }

    /**
     * Bound to the star instead of toggle() when this instance requires
     * confirmation and is currently favorited — opens the modal instead of
     * mutating anything.
     */
    public function requestRemoval(): void
    {
        $this->dispatch('open-modal', "remove-favorite-{$this->number}");
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
            $this->dispatch('toast', message: 'Não foi possível salvar o favorito. Tente novamente.', type: 'error');

            return;
        }

        $this->dispatch('favorite-toggled');

        if ($this->confirmRemoval) {
            $this->dispatch('close-modal', "remove-favorite-{$this->number}");
        }
    }
}; ?>

<div x-data="{ filled: @entangle('favorited').defer }">
    @if ($variant === 'icon')
        <button
            type="button"
            wire:click="{{ $confirmRemoval && $favorited ? 'requestRemoval' : 'toggle' }}"
            @unless ($confirmRemoval && $favorited) x-on:click="filled = ! filled" @endunless
            wire:loading.attr="disabled"
            aria-label="{{ $favorited ? 'Remover dos favoritos' : 'Favoritar' }}"
            class="rounded-full bg-white/90 p-1.5 opacity-0 shadow transition group-hover:opacity-100 group-focus-within:opacity-100 focus:opacity-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            :class="{ 'opacity-100': filled }"
        >
            <svg x-show="filled" @unless ($favorited) x-cloak @endunless class="h-5 w-5 text-yellow-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.006z" clip-rule="evenodd" />
            </svg>
            <svg x-show="! filled" @if ($favorited) x-cloak @endif class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
            </svg>
        </button>
    @else
        <x-secondary-button type="button" wire:click="toggle" x-on:click="filled = ! filled" wire:loading.attr="disabled">
            <span x-text="filled ? 'Remover dos favoritos' : 'Favoritar'"></span>
        </x-secondary-button>
    @endif

    @if ($confirmRemoval)
        <x-modal :name="'remove-favorite-'.$number" focusable>
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Remover {{ ucfirst($name) }} dos favoritos?
                </h2>

                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button type="button" x-on:click="show = false">
                        Cancelar
                    </x-secondary-button>

                    <x-danger-button type="button" wire:click="toggle" x-on:click="show = false">
                        Remover
                    </x-danger-button>
                </div>
            </div>
        </x-modal>
    @endif
</div>
