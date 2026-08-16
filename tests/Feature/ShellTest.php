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

test('a barra de navegação exibe os quatro destinos', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertSee('Início')
        ->assertSee('Favoritos')
        ->assertSee('Chat')
        ->assertSee('Meu Perfil');
});

test('início fica destacado na página inicial', function () {
    $html = $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->getContent();

    expect(navLinkIsActive($html, 'Início'))->toBeTrue()
        ->and(navLinkIsActive($html, 'Favoritos'))->toBeFalse()
        ->and(navLinkIsActive($html, 'Chat'))->toBeFalse()
        ->and(navLinkIsActive($html, 'Meu Perfil'))->toBeFalse();
});

test('meu perfil fica destacado em /perfil', function () {
    $html = $this->actingAs(User::factory()->create())
        ->get('/perfil')
        ->assertOk()
        ->getContent();

    expect(navLinkIsActive($html, 'Meu Perfil'))->toBeTrue()
        ->and(navLinkIsActive($html, 'Início'))->toBeFalse();
});

test('o rodapé exibe a versão configurada da aplicação', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertSee(config('app.version'));
});

test('a barra de carregamento global está presente no shell', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertSee('wire:loading.delay');
});
