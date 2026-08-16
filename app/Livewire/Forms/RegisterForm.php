<?php

namespace App\Livewire\Forms;

use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Form;
use Throwable;

class RegisterForm extends Form
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Delegates to StoreUserRequest so the Form Request stays the single
     * source of truth for the rules, even though it is never routed to.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return (new StoreUserRequest)->rules();
    }

    /**
     * Validate, create the account inside a transaction, and authenticate
     * the new user.
     */
    public function register(): void
    {
        try {
            $validated = $this->validate();
        } catch (ValidationException $e) {
            $this->reset('password', 'password_confirmation');

            throw $e;
        }

        try {
            $user = DB::transaction(function () use ($validated) {
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                ]);

                event(new Registered($user));

                return $user;
            });
        } catch (Throwable) {
            $this->reset('password', 'password_confirmation');

            throw ValidationException::withMessages([
                'form.email' => 'Não foi possível criar sua conta agora. Tente novamente.',
            ]);
        }

        Auth::login($user);
    }
}
