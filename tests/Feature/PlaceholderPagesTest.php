<?php

/*
|--------------------------------------------------------------------------
| F04 — Favoritos placeholder
|--------------------------------------------------------------------------
|
| F10 replaces this page with real content in a later wave. Until then, the
| destination must resolve to a working, authenticated page instead of a
| routing error. F12 already replaced /chat — see tests/Feature/Chat/*.
|
*/

use App\Models\User;

test('a página de favoritos exige autenticação', function () {
    $this->get('/favoritos')->assertRedirect(route('login'));
});

test('um usuário autenticado vê o placeholder de favoritos', function () {
    $html = $this->actingAs(User::factory()->create())
        ->get('/favoritos')
        ->assertOk()
        ->assertSee('Em construção')
        ->getContent();

    expect(navLinkIsActive($html, 'Favoritos'))->toBeTrue();
});
