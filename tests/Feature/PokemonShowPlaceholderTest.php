<?php

/*
|--------------------------------------------------------------------------
| F08 — Pokémon detail placeholder
|--------------------------------------------------------------------------
|
| /pokemon/{slug} exists solely so the result card's click-through target
| (F08) is a working page. F09 (wave 6) replaces this with the real detail
| page. Mirrors tests/Feature/PlaceholderPagesTest.php's pattern for
| /favoritos (F04).
|
*/

use App\Models\User;

test('a página de detalhes exige autenticação', function () {
    $this->get('/pokemon/charmander')->assertRedirect(route('login'));
});

test('um usuário autenticado vê o placeholder de detalhes', function () {
    $html = $this->actingAs(User::factory()->create())
        ->get('/pokemon/charmander')
        ->assertOk()
        ->assertSee('Em construção')
        ->getContent();

    expect(navLinkIsActive($html, 'Início'))->toBeTrue();
});
