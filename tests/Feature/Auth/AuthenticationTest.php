<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Livewire\Volt\Volt;

test('the login screen renders the volt component', function () {
    $response = $this->get('/login');

    $response
        ->assertOk()
        ->assertSeeVolt('pages.auth.login');
});

test('a user authenticates with valid credentials via the login form', function () {
    $user = User::factory()->create();

    $component = Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password');

    $component->call('login');

    $component
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('a user does not authenticate with an invalid password', function () {
    $user = User::factory()->create();

    $component = Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'wrong-password');

    $component->call('login');

    $component
        ->assertHasErrors()
        ->assertNoRedirect();

    $this->assertGuest();
});

test('the navigation bar renders for an authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertSeeVolt('layout.navigation');
});

test('an authenticated user can log out', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Volt::test('layout.navigation');

    $component->call('logout');

    $component
        ->assertHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
});

test('a guest accessing a protected route is redirected to login with a message', function (string $uri) {
    $response = $this->get($uri);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status', 'Faça login para continuar.');
})->with([
    '/',
    '/perfil',
    '/favoritos',
    '/chat',
]);

test('a successful login restores the originally intended url', function () {
    $user = User::factory()->create();

    $this->get('/perfil')->assertRedirect(route('login'));

    $component = Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password');

    $component->call('login');

    $component->assertRedirect('/perfil');

    $this->assertAuthenticated();
});

test('login with a wrong password and login with a nonexistent email return the same generic message', function () {
    $user = User::factory()->create();

    $wrongPassword = Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'wrong-password');
    $wrongPassword->call('login');

    $unknownEmail = Volt::test('pages.auth.login')
        ->set('form.email', 'nao-existe@pokelink.test')
        ->set('form.password', 'password');
    $unknownEmail->call('login');

    $wrongPassword->assertHasErrors(['form.email' => trans('auth.failed')]);
    $unknownEmail->assertHasErrors(['form.email' => trans('auth.failed')]);

    $this->assertGuest();
});

test('the sixth login attempt within a minute is blocked with the remaining time', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'wrong-password')
            ->call('login');
    }

    $sixthAttempt = Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'wrong-password');

    $sixthAttempt->call('login');

    $sixthAttempt
        ->assertHasErrors('form.email')
        ->assertSee('Muitas tentativas. Tente novamente em')
        ->assertSee('segundos.');

    $this->assertGuest();
});

test('an authenticated user visiting login or register is redirected', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get('/login')->assertRedirect(route('dashboard'));
    $this->get('/register')->assertRedirect(route('dashboard'));
});

test('logout invalidates the session and the back button does not re-show authenticated content', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Volt::test('layout.navigation')->call('logout');

    $this->assertGuest();

    $this->get('/')->assertRedirect(route('login'));
});

test('remember-me extends the session cookie\'s duration', function () {
    $user = User::factory()->create();

    Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password')
        ->set('form.remember', true)
        ->call('login');

    $this->assertAuthenticated();

    $recallerName = Auth::guard('web')->getRecallerName();

    $rememberCookie = collect(Cookie::getQueuedCookies())
        ->first(fn ($cookie) => $cookie->getName() === $recallerName);

    expect($rememberCookie)->not->toBeNull();
    expect($rememberCookie->getExpiresTime())->toBeGreaterThan(now()->addDays(29)->timestamp);
});
