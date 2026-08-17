<?php

use App\Models\User;
use App\Policies\UpdateProfilePolicy;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

test('the profile page shows name, email, and account creation date', function () {
    $user = User::factory()->create([
        'name' => 'Ash Ketchum',
        'email' => 'ash@pokelink.test',
    ]);

    $this->actingAs($user);

    $this->get('/perfil')
        ->assertOk()
        ->assertSeeVolt('profile.update-profile-information-form')
        ->assertSeeVolt('profile.update-password-form')
        ->assertSee('Ash Ketchum')
        ->assertSee('ash@pokelink.test')
        ->assertSee($user->created_at->format('d/m/Y'));
});

test('the email is displayed as read-only', function () {
    $user = User::factory()->create(['email' => 'ash@pokelink.test']);

    $this->actingAs($user);

    $component = Volt::test('profile.update-profile-information-form');

    $component
        ->assertDontSee('wire:model="email"', false)
        ->assertSee('ash@pokelink.test')
        ->assertSee('O e-mail não pode ser alterado.');
});

test('the navigation bar is wired to the profile-updated event from the profile form', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/perfil')
        ->assertOk()
        ->assertSee('x-on:profile-updated.window', false);
});

test('a user can update their own name', function () {
    $user = User::factory()->create(['name' => 'Nome Antigo']);

    $this->actingAs($user);

    $component = Volt::test('profile.update-profile-information-form')
        ->set('name', 'Nome Novo')
        ->call('updateProfileInformation');

    $component->assertHasNoErrors();
    $component->assertDispatched('profile-updated', name: 'Nome Novo');
    $component->assertDispatched('toast', message: 'Perfil atualizado.', type: 'success');

    expect($user->refresh()->name)->toBe('Nome Novo');
});

test('an invalid name is rejected by validation', function () {
    $user = User::factory()->create(['name' => 'Nome Original']);

    $this->actingAs($user);

    Volt::test('profile.update-profile-information-form')
        ->set('name', 'a')
        ->call('updateProfileInformation')
        ->assertHasErrors(['name']);

    expect($user->refresh()->name)->toBe('Nome Original');
});

test('a request cannot change another user\'s name or email', function () {
    $user = User::factory()->create();
    $other = User::factory()->create(['name' => 'Outro Usuário', 'email' => 'outro@pokelink.test']);

    $this->actingAs($user);

    Volt::test('profile.update-profile-information-form')
        ->set('name', 'Nome Alterado')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    $other->refresh();

    expect($other->name)->toBe('Outro Usuário')
        ->and($other->email)->toBe('outro@pokelink.test');
});

test('the policy denies updating a user other than the authenticated one', function () {
    $policy = new UpdateProfilePolicy;
    $user = User::factory()->create();
    $other = User::factory()->create();

    expect($policy->update($user, $other))->toBeFalse()
        ->and($policy->update($user, $user))->toBeTrue();
});

test('a correct current password allows the password change', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $this->actingAs($user);

    $component = Volt::test('profile.update-password-form')
        ->set('current_password', 'password')
        ->set('password', 'nova-senha-123')
        ->set('password_confirmation', 'nova-senha-123')
        ->call('updatePassword');

    $component->assertHasNoErrors();
    $component->assertDispatched('toast', message: 'Senha alterada com sucesso.', type: 'success');
    $component->assertSet('current_password', '')
        ->assertSet('password', '')
        ->assertSet('password_confirmation', '');

    $newHash = $user->refresh()->password;

    expect($newHash)->not->toBe(Hash::make('password'))
        ->and(Hash::check('nova-senha-123', $newHash))->toBeTrue();
});

test('an incorrect current password is rejected with a pt-BR message and nothing is written', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);
    $originalHash = $user->password;

    $this->actingAs($user);

    $component = Volt::test('profile.update-password-form')
        ->set('current_password', 'senha-errada')
        ->set('password', 'nova-senha-123')
        ->set('password_confirmation', 'nova-senha-123')
        ->call('updatePassword');

    $component->assertHasErrors(['current_password' => 'A senha atual está incorreta.']);
    $component->assertSet('current_password', '')
        ->assertSet('password', '')
        ->assertSet('password_confirmation', '');

    expect($user->refresh()->password)->toBe($originalHash);
});

test('a new password equal to the current one is rejected', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);
    $originalHash = $user->password;

    $this->actingAs($user);

    $component = Volt::test('profile.update-password-form')
        ->set('current_password', 'password')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('updatePassword');

    $component->assertHasErrors(['password' => 'A nova senha deve ser diferente da atual.']);
    $component->assertSet('current_password', '')
        ->assertSet('password', '')
        ->assertSet('password_confirmation', '');

    expect($user->refresh()->password)->toBe($originalHash);
});

test('a mismatched password confirmation is rejected while preserving the typed current password', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $this->actingAs($user);

    $component = Volt::test('profile.update-password-form')
        ->set('current_password', 'password')
        ->set('password', 'nova-senha-123')
        ->set('password_confirmation', 'nao-confere')
        ->call('updatePassword');

    $component->assertHasErrors(['password']);
    $component->assertSet('current_password', 'password')
        ->assertSet('password', '')
        ->assertSet('password_confirmation', '');
});

/*
|--------------------------------------------------------------------------
| Other-session invalidation
|--------------------------------------------------------------------------
|
| Livewire's testing harness (Volt::test) calls component methods directly,
| bypassing the HTTP kernel entirely, so a literal two-concurrent-browser
| simulation isn't reachable from here. This test covers the half of the
| guarantee that a real HTTP round-trip can prove: a session's stored
| password reference, established through the real auth.session middleware,
| is rejected on its next protected-route request once the password changes.
|
*/

test('after a password change, a previously authenticated session is logged out when accessing a protected route', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $this->actingAs($user);

    // Establishes this test session's password-hash marker via the real
    // auth.session middleware, exactly as a browser tab would on first load.
    $this->get('/perfil')->assertOk();

    Volt::test('profile.update-password-form')
        ->set('current_password', 'password')
        ->set('password', 'nova-senha-123')
        ->set('password_confirmation', 'nova-senha-123')
        ->call('updatePassword')
        ->assertHasNoErrors();

    // The marker stored above still references the old hash; auth.session
    // rejects it against the now-changed password on the very next request.
    $response = $this->get('/perfil');

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});
