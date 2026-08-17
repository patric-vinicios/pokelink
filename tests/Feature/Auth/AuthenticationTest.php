<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Livewire\Volt\Volt;

test('a tela de login renderiza o componente volt', function () {
    $response = $this->get('/login');

    $response
        ->assertOk()
        ->assertSeeVolt('pages.auth.login');
});

test('um usuário se autentica com credenciais válidas pelo formulário de login', function () {
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

test('um usuário não se autentica com senha inválida', function () {
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

test('a barra de navegação renderiza para um usuário autenticado', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertSeeVolt('layout.navigation');
});

test('um usuário autenticado consegue fazer logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Volt::test('layout.navigation');

    $component->call('logout');

    $component
        ->assertHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
});

test('um convidado que acessa uma rota protegida é redirecionado para o login com uma mensagem', function (string $uri) {
    $response = $this->get($uri);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status', 'Faça login para continuar.');
})->with([
    '/',
    '/perfil',
    '/favoritos',
    '/chat',
]);

test('o login bem-sucedido restaura a url originalmente pretendida', function () {
    $user = User::factory()->create();

    $this->get('/perfil')->assertRedirect(route('login'));

    $component = Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password');

    $component->call('login');

    $component->assertRedirect('/perfil');

    $this->assertAuthenticated();
});

test('login com senha errada e login com e-mail inexistente retornam a mesma mensagem genérica', function () {
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

test('a sexta tentativa de login em um minuto é bloqueada com o tempo restante', function () {
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

test('um usuário autenticado que acessa login ou register é redirecionado', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get('/login')->assertRedirect(route('dashboard'));
    $this->get('/register')->assertRedirect(route('dashboard'));
});

test('logout invalida a sessão e o botão voltar não reexibe conteúdo autenticado', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Volt::test('layout.navigation')->call('logout');

    $this->assertGuest();

    $this->get('/')->assertRedirect(route('login'));
});

test('lembrar-me estende a duração do cookie de sessão', function () {
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
