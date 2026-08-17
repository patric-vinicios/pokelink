<?php

/*
|--------------------------------------------------------------------------
| F10 — Favorite toggle
|--------------------------------------------------------------------------
|
| Covers the reusable livewire:pokemon.favorite-toggle component: idempotent
| add/remove, the optimistic-revert-on-failure path, and consistency between
| the card (icon) and detail-page (button) variants for the same Pokémon.
| Slot integration itself (search.blade.php/show.blade.php seeding the
| `favorited` prop) is covered by PokemonResultsListTest and PokemonShowTest.
|
*/

use App\Models\Favorite;
use App\Models\Pokemon;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->pokemon = Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);

    $this->actingAs($this->user);
});

test('clicar na estrela de um card cria exatamente uma linha e preenche a estrela', function () {
    Volt::test('pokemon.favorite-toggle', [
        'number' => $this->pokemon->number,
        'name' => $this->pokemon->name,
        'favorited' => false,
    ])
        ->call('toggle')
        ->assertSet('favorited', true)
        ->assertDispatched('toast', message: 'Adicionado aos favoritos.', type: 'success');

    expect(Favorite::query()->where('user_id', $this->user->id)->where('pokemon_number', $this->pokemon->number)->count())->toBe(1);
});

test('clicar duas vezes na mesma estrela deixa exatamente uma linha no banco', function () {
    Volt::test('pokemon.favorite-toggle', [
        'number' => $this->pokemon->number,
        'name' => $this->pokemon->name,
        'favorited' => false,
    ])->call('toggle');

    expect(Favorite::query()->where('user_id', $this->user->id)->count())->toBe(1);

    Volt::test('pokemon.favorite-toggle', [
        'number' => $this->pokemon->number,
        'name' => $this->pokemon->name,
        'favorited' => true,
    ])->call('toggle');

    expect(Favorite::query()->where('user_id', $this->user->id)->count())->toBe(0);
});

test('favoritar o mesmo pokemon duas vezes seguidas sem recarregar continua idempotente', function () {
    $component = Volt::test('pokemon.favorite-toggle', [
        'number' => $this->pokemon->number,
        'name' => $this->pokemon->name,
        'favorited' => false,
    ]);

    $component->call('toggle')->assertSet('favorited', true);
    expect(Favorite::query()->where('user_id', $this->user->id)->count())->toBe(1);

    $component->call('toggle')->assertSet('favorited', false);
    expect(Favorite::query()->where('user_id', $this->user->id)->count())->toBe(0);
});

test('o estado de favorito e consistente entre o card e a pagina de detalhes', function () {
    Volt::test('pokemon.favorite-toggle', [
        'number' => $this->pokemon->number,
        'name' => $this->pokemon->name,
        'favorited' => false,
        'variant' => 'icon',
    ])->call('toggle');

    $favorited = Favorite::query()
        ->where('user_id', $this->user->id)
        ->where('pokemon_number', $this->pokemon->number)
        ->exists();

    Volt::test('pokemon.favorite-toggle', [
        'number' => $this->pokemon->number,
        'name' => $this->pokemon->name,
        'favorited' => $favorited,
        'variant' => 'button',
    ])->assertSet('favorited', true);
});

test('uma falha de escrita reverte o estado e mostra a mensagem de erro', function () {
    // A pokemon_number with no matching catalog row violates the favorites
    // table's foreign key, producing a genuine QueryException on write —
    // without touching schema state the way dropping a table would.
    Volt::test('pokemon.favorite-toggle', [
        'number' => 999999,
        'name' => 'fantasma',
        'favorited' => false,
    ])
        ->call('toggle')
        ->assertSet('favorited', false)
        ->assertDispatched('toast', message: 'Não foi possível salvar o favorito. Tente novamente.', type: 'error');

    expect(Favorite::query()->where('pokemon_number', 999999)->exists())->toBeFalse();
});

test('remover um favorito dispara o evento favorite-removed e adicionar dispara apenas favorite-toggled', function () {
    $component = Volt::test('pokemon.favorite-toggle', [
        'number' => $this->pokemon->number,
        'name' => $this->pokemon->name,
        'favorited' => false,
    ]);

    $component->call('toggle')
        ->assertDispatched('favorite-toggled')
        ->assertNotDispatched('favorite-removed');

    $component->call('toggle')
        ->assertDispatched('favorite-toggled')
        ->assertDispatched('favorite-removed');
});
