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

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Alterar senha') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Use uma senha longa e única para manter sua conta segura.') }}
        </p>
    </header>

    <form wire:submit="updatePassword" class="mt-6 space-y-6" x-data>
        <div>
            <x-input-label for="update_password_current_password" :value="__('Senha atual')" />
            <x-text-input wire:model="current_password" id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="off" />
            <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" :value="__('Nova senha')" />
            <x-text-input wire:model="password" id="update_password_password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Confirmar nova senha')" />
            <x-text-input wire:model="password_confirmation" id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button
                x-bind:disabled="!$wire.current_password && !$wire.password && !$wire.password_confirmation"
                wire:loading.attr="disabled"
                wire:target="updatePassword"
            >
                {{ __('Salvar') }}
            </x-primary-button>
        </div>
    </form>
</section>
