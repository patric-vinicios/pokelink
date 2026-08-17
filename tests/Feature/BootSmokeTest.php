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

test('the health route responds 200', function () {
    $this->get('/up')->assertOk();
});

test('a visitor is redirected to login', function () {
    $this->get('/')->assertRedirect('/login');
});

test('the login screen renders', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('email', escape: false)
        ->assertSee('password', escape: false);
});

test('an authenticated user reaches the root', function () {
    $this->seed(UserSeeder::class);

    $this->actingAs(User::where('email', 'admin@pokelink.test')->firstOrFail())
        ->get('/')
        ->assertOk();
});

test('outside the local environment the Horizon dashboard requires authentication', function () {
    // In `local` the gate is open, so the evaluator can watch the catalog sync
    // (F06) during first boot without signing in. Everywhere else — including
    // the `testing` environment this assertion runs in — it must not be.
    $this->seed(UserSeeder::class);

    expect(app()->environment('local'))->toBeFalse()
        ->and(Gate::forUser(null)->allows('viewHorizon'))->toBeFalse()
        ->and(Gate::forUser(User::first())->allows('viewHorizon'))->toBeTrue();
});
