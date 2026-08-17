<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user, reject a new
     * password identical to the current one, and invalidate every other
     * active session for that user while keeping this one authenticated.
     */
    public function updatePassword(): void
    {
        $user = Auth::user();

        Gate::authorize('update', $user);

        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            // A wrong current password clears every field; a confirmation
            // mismatch preserves it so the user only retypes the new pair.
            if ($e->validator->errors()->has('current_password')) {
                $this->reset('current_password', 'password', 'password_confirmation');
            } else {
                $this->reset('password', 'password_confirmation');
            }

            throw $e;
        }

        if (Hash::check($validated['password'], $user->password)) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw ValidationException::withMessages([
                'password' => 'A nova senha deve ser diferente da atual.',
            ]);
        }

        // logoutOtherDevices() verifies identity by re-checking the CURRENT
        // password and re-hashes that same value onto the `password` column
        // with a fresh salt — that hash change is what invalidates every
        // other session's stored password reference (auth.session
        // middleware). The actual new password is then persisted as a
        // second, explicit write.
        Auth::logoutOtherDevices($validated['current_password']);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        // The auth.session middleware only refreshes a session's own
        // password-hash marker at the end of a request that passes through
        // it, and this action runs on Livewire's own update endpoint, which
        // doesn't. Without this, the session that just changed its own
        // password would read as stale too and be logged out on its next
        // page load. Mirrors AuthenticateSession::storePasswordHashInSession()
        // using only its public API.
        if (request()->hasSession()) {
            request()->session()->put(
                'password_hash_'.Auth::getDefaultDriver(),
                Auth::guard()->hashPasswordForCookie($user->getAuthPassword())
            );
        }

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('toast', message: 'Senha alterada com sucesso.', type: 'success');
    }
}; ?>

<section class="profile-inline-form profile-inline-form--security">
    <header>
        <div>
            <h3>{{ __('Senha de acesso') }}</h3>
            <p>{{ __('Use uma senha longa e única para manter sua conta segura.') }}</p>
        </div>
    </header>

    <form wire:submit="updatePassword" class="profile-form" x-data>
        <div class="trainer-field">
            <x-input-label for="update_password_current_password" :value="__('Senha atual')" />
            <div class="trainer-input-wrap">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                    <rect x="4" y="10" width="16" height="11" rx="2.5" />
                    <path stroke-linecap="round" d="M8 10V7a4 4 0 0 1 8 0v3" />
                </svg>
                <x-text-input wire:model="current_password" id="update_password_current_password" name="current_password" type="password" class="trainer-input" autocomplete="off" placeholder="Digite sua senha atual" />
            </div>
            <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>

        <div class="trainer-field">
            <x-input-label for="update_password_password" :value="__('Nova senha')" />
            <div class="trainer-input-wrap">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a5 5 0 1 0-4.2 4.9L13 14h2v2h2v2h3l1-1-6.2-6.2A5 5 0 0 0 15 7Z" />
                </svg>
                <x-text-input wire:model="password" id="update_password_password" name="password" type="password" class="trainer-input" autocomplete="new-password" placeholder="Crie uma nova senha" />
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="trainer-field">
            <x-input-label for="update_password_password_confirmation" :value="__('Confirmar nova senha')" />
            <div class="trainer-input-wrap">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" />
                </svg>
                <x-text-input wire:model="password_confirmation" id="update_password_password_confirmation" name="password_confirmation" type="password" class="trainer-input" autocomplete="new-password" placeholder="Repita a nova senha" />
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <p class="profile-password-hint">
            <span class="mini-pokeball" aria-hidden="true"></span>
            Combine letras, números e símbolos para uma senha mais forte.
        </p>

        <div class="profile-form-actions">
            <button
                type="submit"
                class="trainer-primary-button trainer-primary-button--red"
                x-bind:disabled="!$wire.current_password && !$wire.password && !$wire.password_confirmation"
                wire:loading.attr="disabled"
                wire:target="updatePassword"
            >
                <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.9">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.5 9V6.8a4.5 4.5 0 0 1 9 0V9m-10 0h11a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-11a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2Z" />
                </svg>
                <span wire:loading.remove wire:target="updatePassword">{{ __('Atualizar senha') }}</span>
                <span wire:loading wire:target="updatePassword">{{ __('Atualizando...') }}</span>
            </button>
        </div>
    </form>
</section>
