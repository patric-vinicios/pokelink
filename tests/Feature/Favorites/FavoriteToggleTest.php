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

test('clicking a card\'s star creates exactly one row and fills the star', function () {
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

test('clicking the same star twice leaves exactly one row in the database', function () {
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

test('favoriting the same pokemon twice in a row without reloading stays idempotent', function () {
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

test('favorite state is consistent between the card and the detail page', function () {
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

test('a write failure reverts the state and shows the error message', function () {
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

test('removing a favorite dispatches the favorite-removed event and adding dispatches only favorite-toggled', function () {
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
