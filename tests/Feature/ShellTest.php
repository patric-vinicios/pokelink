<?php

/*
|--------------------------------------------------------------------------
| F04 — Application shell and navigation
|--------------------------------------------------------------------------
|
| The shell every authenticated page renders inside: the four navigation
| destinations, route-based active highlighting, the footer version, and the
| global Livewire loading bar.
|
*/

use App\Models\User;

test('the navigation bar shows the four destinations', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertSee('Início')
        ->assertSee('Favoritos')
        ->assertSee('Chat')
        ->assertSee('Meu Perfil');
});

test('"Início" is highlighted on the home page', function () {
    $html = $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->getContent();

    expect(navLinkIsActive($html, 'Início'))->toBeTrue()
        ->and(navLinkIsActive($html, 'Favoritos'))->toBeFalse()
        ->and(navLinkIsActive($html, 'Chat'))->toBeFalse()
        ->and(navLinkIsActive($html, 'Meu Perfil'))->toBeFalse();
});

test('"Meu Perfil" is highlighted on /perfil', function () {
    $html = $this->actingAs(User::factory()->create())
        ->get('/perfil')
        ->assertOk()
        ->getContent();

    expect(navLinkIsActive($html, 'Meu Perfil'))->toBeTrue()
        ->and(navLinkIsActive($html, 'Início'))->toBeFalse();
});

test('the footer shows the app\'s configured version', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertSee(config('app.version'));
});

test('the global loading bar is present in the shell', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertSee('wire:loading.delay');
});
