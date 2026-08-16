<?php

/*
|--------------------------------------------------------------------------
| F01 — Boot smoke
|--------------------------------------------------------------------------
|
| The happy path only: the health route answers, guests are sent to login, the
| login screen renders, and a seeded account reaches the root. F02 owns the
| behaviour behind these routes (throttling, generic failures, intended URL).
|
*/

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Gate;

test('a rota de health responde 200', function () {
    $this->get('/up')->assertOk();
});

test('um visitante é redirecionado para o login', function () {
    $this->get('/')->assertRedirect('/login');
});

test('a tela de login renderiza', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('email', escape: false)
        ->assertSee('password', escape: false);
});

test('um usuário autenticado alcança a raiz', function () {
    $this->seed(UserSeeder::class);

    $this->actingAs(User::where('email', 'admin@pokelink.test')->firstOrFail())
        ->get('/')
        ->assertOk();
});

test('fora do ambiente local o dashboard do Horizon exige autenticação', function () {
    // In `local` the gate is open, so the evaluator can watch the catalog sync
    // (F06) during first boot without signing in. Everywhere else — including
    // the `testing` environment this assertion runs in — it must not be.
    $this->seed(UserSeeder::class);

    expect(app()->environment('local'))->toBeFalse()
        ->and(Gate::forUser(null)->allows('viewHorizon'))->toBeFalse()
        ->and(Gate::forUser(User::first())->allows('viewHorizon'))->toBeTrue();
});
