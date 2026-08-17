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

<section class="profile-inline-form profile-inline-form--identity" x-data="{ initialName: @js($name) }">
    <header>
        <div>
            <h3>{{ __('Nome do treinador') }}</h3>
            <p>{{ __('Este é o nome exibido no PokéLink.') }}</p>
        </div>
    </header>

    <form wire:submit="updateProfileInformation" class="profile-form">
        <div class="trainer-field">
            <x-input-label for="name" :value="__('Nome')" />
            <div class="trainer-input-wrap">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                    <circle cx="12" cy="8" r="3.5" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.5 20a6.5 6.5 0 0 1 13 0" />
                </svg>
                <x-text-input wire:model="name" id="name" name="name" type="text" class="trainer-input" required autocomplete="name" />
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div class="profile-form-actions">
            <button
                type="submit"
                class="trainer-primary-button"
                x-bind:disabled="$wire.name === initialName"
                wire:loading.attr="disabled"
                wire:target="updateProfileInformation"
            >
                <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.9">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4 10 4 4 8-8" />
                </svg>
                <span wire:loading.remove wire:target="updateProfileInformation">{{ __('Salvar alterações') }}</span>
                <span wire:loading wire:target="updateProfileInformation">{{ __('Salvando...') }}</span>
            </button>
        </div>
    </form>
</section>
