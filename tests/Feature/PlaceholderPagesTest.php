<?php

/*
|--------------------------------------------------------------------------
| F04 — Favoritos and Chat placeholders
|--------------------------------------------------------------------------
|
| F10 and F12 replace these pages with real content in later waves. Until
| then, every navigation destination must resolve to a working, authenticated
| page instead of a routing error.
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

test('a página de chat exige autenticação', function () {
    $this->get('/chat')->assertRedirect(route('login'));
});

test('um usuário autenticado vê o placeholder de chat', function () {
    $html = $this->actingAs(User::factory()->create())
        ->get('/chat')
        ->assertOk()
        ->assertSee('Em construção')
        ->getContent();

    expect(navLinkIsActive($html, 'Chat'))->toBeTrue();
});
