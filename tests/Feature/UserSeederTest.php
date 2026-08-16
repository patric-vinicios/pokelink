<?php

/*
|--------------------------------------------------------------------------
| F01 — Documented accounts
|--------------------------------------------------------------------------
|
| The README publishes two credential pairs and the acceptance criteria say
| both must log in using only the README, that a restart must not duplicate
| them, and that no plaintext password ever reaches the database.
|
*/

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

const DOCUMENTED_ACCOUNTS = [
    'admin@pokelink.test' => 'password',
    'user@pokelink.test' => 'password',
];

test('o seeder cria exatamente duas contas documentadas', function () {
    $this->seed(UserSeeder::class);

    expect(User::count())->toBe(2);

    foreach (array_keys(DOCUMENTED_ACCOUNTS) as $email) {
        expect(User::where('email', $email)->exists())->toBeTrue();
    }
});

test('as senhas são persistidas como hash bcrypt', function () {
    $this->seed(UserSeeder::class);

    foreach (DOCUMENTED_ACCOUNTS as $email => $plaintext) {
        // Read the raw column, not the model, so no cast can hide the value.
        $stored = DB::table('users')->where('email', $email)->value('password');

        expect($stored)->not->toBe($plaintext)
            ->and($stored)->toStartWith('$2y$')
            ->and(Hash::check($plaintext, $stored))->toBeTrue();
    }
});

test('executar o seeder duas vezes não duplica contas', function () {
    $this->seed(UserSeeder::class);
    $this->seed(UserSeeder::class);

    expect(User::count())->toBe(2)
        ->and(User::where('email', 'admin@pokelink.test')->count())->toBe(1);
});

test('ambas as contas conseguem autenticar com as credenciais do README', function () {
    $this->seed(UserSeeder::class);

    foreach (DOCUMENTED_ACCOUNTS as $email => $password) {
        expect(Auth::attempt(['email' => $email, 'password' => $password]))->toBeTrue();

        Auth::logout();
    }
});

test('o DatabaseSeeder executa sem depender de rede', function () {
    // Seeding runs inside the container entrypoint, before the application
    // serves its first request — a stray HTTP call there would put the whole
    // boot at the mercy of a third party.
    Http::preventStrayRequests();

    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    expect(User::count())->toBe(2);
});
