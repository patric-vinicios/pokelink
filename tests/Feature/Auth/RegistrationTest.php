<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

test('the registration screen renders', function () {
    $response = $this->get('/register');

    $response
        ->assertOk()
        ->assertSeeVolt('pages.auth.register');
});

test('a visitor can register with valid data and is automatically authenticated', function () {
    $component = Volt::test('pages.auth.register')
        ->set('form.name', 'Ash Ketchum')
        ->set('form.email', 'ash@pokelink.test')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123');

    $component->call('register');

    $component
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    expect(session('status'))->toBe('Conta criada com sucesso. Bem-vindo(a), Ash Ketchum!');
});

test('the password is persisted as a bcrypt hash and never in plain text', function () {
    Volt::test('pages.auth.register')
        ->set('form.name', 'Misty Waterflower')
        ->set('form.email', 'misty@pokelink.test')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123')
        ->call('register');

    $user = User::where('email', 'misty@pokelink.test')->firstOrFail();

    expect($user->password)->not->toBe('password123');
    expect($user->password)->toStartWith('$2y$');
    expect(Hash::check('password123', $user->password))->toBeTrue();
});

test('an already-registered email is rejected and no second account is created', function () {
    User::factory()->create(['email' => 'duplicado@pokelink.test']);

    $component = Volt::test('pages.auth.register')
        ->set('form.name', 'Segundo Usuário')
        ->set('form.email', 'duplicado@pokelink.test')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123');

    $component->call('register');

    $component->assertHasErrors(['form.email' => 'Este e-mail já está cadastrado.']);

    expect(User::count())->toBe(1);
});

test('a password under 8 characters is rejected with the specific message', function () {
    $component = Volt::test('pages.auth.register')
        ->set('form.name', 'Brock Harrison')
        ->set('form.email', 'brock@pokelink.test')
        ->set('form.password', 'curta')
        ->set('form.password_confirmation', 'curta');

    $component->call('register');

    $component->assertHasErrors(['form.password' => 'A senha deve ter pelo menos 8 caracteres.']);

    $this->assertGuest();
});

test('a mismatched password confirmation is rejected and the password fields are cleared', function () {
    $component = Volt::test('pages.auth.register')
        ->set('form.name', 'Gary Oak')
        ->set('form.email', 'gary@pokelink.test')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password456');

    $component->call('register');

    $component->assertHasErrors(['form.password' => 'A confirmação de senha não confere.']);

    expect($component->get('form.password'))->toBe('');
    expect($component->get('form.password_confirmation'))->toBe('');
    expect($component->get('form.name'))->toBe('Gary Oak');
    expect($component->get('form.email'))->toBe('gary@pokelink.test');
});

test('two rapid submissions with the same email create exactly one user', function () {
    $first = Volt::test('pages.auth.register')
        ->set('form.name', 'Primeiro Usuário')
        ->set('form.email', 'corrida@pokelink.test')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123');
    $first->call('register');

    $second = Volt::test('pages.auth.register')
        ->set('form.name', 'Segundo Usuário')
        ->set('form.email', 'corrida@pokelink.test')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123');
    $second->call('register');

    $first->assertHasNoErrors();
    $second->assertHasErrors(['form.email' => 'Este e-mail já está cadastrado.']);

    expect(User::count())->toBe(1);
});

test('a payload with unexpected fields never reaches the model', function () {
    Volt::test('pages.auth.register')
        ->set('form.name', 'Dawn Berlitz')
        ->set('form.email', 'dawn@pokelink.test')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123')
        ->call('register');

    $user = User::where('email', 'dawn@pokelink.test')->firstOrFail();

    expect($user->getAttributes())
        ->toHaveKeys(['name', 'email', 'password'])
        ->not->toHaveKey('role')
        ->not->toHaveKey('is_admin');
});

test('a database write failure leaves no partial account', function () {
    User::creating(fn () => throw new RuntimeException('falha simulada de escrita'));

    $component = Volt::test('pages.auth.register')
        ->set('form.name', 'Serena Yvonne')
        ->set('form.email', 'serena@pokelink.test')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123');

    $component->call('register');

    $component->assertHasErrors(['form.email' => 'Não foi possível criar sua conta agora. Tente novamente.']);

    $this->assertGuest();
    expect(User::count())->toBe(0);
});
