<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

test('a tela de registro renderiza', function () {
    $response = $this->get('/register');

    $response
        ->assertOk()
        ->assertSeeVolt('pages.auth.register');
});

test('um visitante pode se registrar com dados válidos e é autenticado automaticamente', function () {
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

test('a senha é persistida como hash bcrypt e nunca em texto puro', function () {
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

test('um e-mail já cadastrado é rejeitado e nenhuma segunda conta é criada', function () {
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

test('uma senha com menos de 8 caracteres é rejeitada com a mensagem específica', function () {
    $component = Volt::test('pages.auth.register')
        ->set('form.name', 'Brock Harrison')
        ->set('form.email', 'brock@pokelink.test')
        ->set('form.password', 'curta')
        ->set('form.password_confirmation', 'curta');

    $component->call('register');

    $component->assertHasErrors(['form.password' => 'A senha deve ter pelo menos 8 caracteres.']);

    $this->assertGuest();
});

test('a confirmação de senha divergente é rejeitada e os campos de senha são limpos', function () {
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

test('duas submissões rápidas com o mesmo e-mail criam exatamente um usuário', function () {
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

test('um payload com campos inesperados nunca alcança o modelo', function () {
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

test('uma falha ao gravar no banco não deixa conta parcial', function () {
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
