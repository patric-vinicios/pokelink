<?php

/*
|--------------------------------------------------------------------------
| F10 — Navigation badge
|--------------------------------------------------------------------------
|
| Covers the live favorite-count badge next to "Favoritos" in the shell nav
| (F04): the count, its "99+" cap, and its reaction to the favorite-toggled
| event dispatched anywhere else on the page.
|
*/

use App\Models\Favorite;
use App\Models\Pokemon;
use App\Models\User;
use Livewire\Volt\Volt;

test('a barra de navegacao mostra a contagem atual de favoritos', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Pokemon::factory()
        ->count(3)
        ->sequence(fn ($sequence) => ['number' => $sequence->index + 1])
        ->create()
        ->each(fn (Pokemon $pokemon) => Favorite::factory()->for($user)->create(['pokemon_number' => $pokemon->number]));

    Volt::test('layout.navigation')->assertSee('3');
});

test('a contagem satura em 99 mais', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Pokemon::factory()
        ->count(150)
        ->sequence(fn ($sequence) => ['number' => $sequence->index + 1])
        ->create()
        ->each(fn (Pokemon $pokemon) => Favorite::factory()->for($user)->create(['pokemon_number' => $pokemon->number]));

    Volt::test('layout.navigation')->assertSee('99+');
});

test('a contagem de favoritos atualiza quando o evento favorite-toggled e recebido', function () {
    $user = User::factory()->create();
    $pokemon = Pokemon::factory()->create(['number' => 1]);

    $this->actingAs($user);

    $component = Volt::test('layout.navigation');
    $component->assertDontSee('>1<', false);

    Favorite::factory()->for($user)->create(['pokemon_number' => $pokemon->number]);

    $component->dispatch('favorite-toggled')->assertSee('1');
});

test('sem favoritos nenhum badge e exibido', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $html = Volt::test('layout.navigation')->html();

    expect($html)->not->toContain('bg-indigo-100 text-indigo-800');
});
