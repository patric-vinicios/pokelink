<?php

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
    }

    /**
     * Update the display name for the currently authenticated user. The
     * e-mail address is never part of this form's validated or fillable set.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        Gate::authorize('update', $user);

        $validated = $this->validate((new UpdateProfileRequest)->rules());

        $user->update($validated);

        $this->dispatch('profile-updated', name: $user->name);
        $this->dispatch('toast', message: 'Perfil atualizado.', type: 'success');
    }
}; ?>

<section x-data="{ initialName: @js($name) }">
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Dados da conta') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Atualize o nome exibido na plataforma.') }}
        </p>
    </header>

    <form wire:submit="updateProfileInformation" class="mt-6 space-y-6">
        <div>
            <x-input-label for="name" :value="__('Nome')" />
            <x-text-input wire:model="name" id="name" name="name" type="text" class="mt-1 block w-full" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('E-mail')" />
            <x-text-input :value="auth()->user()->email" id="email" type="email" class="mt-1 block w-full bg-gray-100 text-gray-500" disabled />
            <p class="mt-1 text-sm text-gray-500">{{ __('O e-mail não pode ser alterado.') }}</p>
        </div>

        <div>
            <x-input-label :value="__('Conta criada em')" />
            <p class="mt-1 text-sm text-gray-700">{{ auth()->user()->created_at->format('d/m/Y') }}</p>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button
                x-bind:disabled="$wire.name === initialName"
                wire:loading.attr="disabled"
                wire:target="updateProfileInformation"
            >
                {{ __('Salvar') }}
            </x-primary-button>
        </div>
    </form>
</section>
