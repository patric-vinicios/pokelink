<?php

/*
|--------------------------------------------------------------------------
| F10 — Immediate favorite removal
|--------------------------------------------------------------------------
|
| Covers the immediate toggle rendered on /favoritos: clicking the filled
| heart deletes the row without opening a modal and refreshes the grid.
|
*/

use App\Models\Favorite;
use App\Models\Pokemon;
use App\Models\User;
use Livewire\Volt\Volt;

test('clicking a filled heart removes the favorite immediately without opening a modal', function () {
    $user = User::factory()->create();
    $pokemon = Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);

    $this->actingAs($user);

    Favorite::factory()->for($user)->create(['pokemon_number' => $pokemon->number]);

    Volt::test('pokemon.favorite-toggle', [
        'number' => $pokemon->number,
        'name' => $pokemon->name,
        'favorited' => true,
        'variant' => 'icon',
    ])
        ->call('toggle')
        ->assertSet('favorited', false)
        ->assertDispatched('favorite-removed')
        ->assertDispatched('toast', message: 'Removido dos favoritos.', type: 'success')
        ->assertNotDispatched('open-modal');

    expect(Favorite::query()->where('user_id', $user->id)->where('pokemon_number', $pokemon->number)->exists())->toBeFalse();
});

test('the favorites page renders an immediate toggle without confirmation markup', function () {
    $user = User::factory()->create();
    $pokemon = Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);

    $this->actingAs($user);

    Favorite::factory()->for($user)->create(['pokemon_number' => $pokemon->number]);

    $response = $this->get('/favoritos');

    $response->assertOk()
        ->assertSee('wire:click.stop="toggle"', false)
        ->assertDontSee("Remover {$pokemon->name} dos favoritos?");
});

test('removing the only item on a page redirects to the last valid page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $pokemon = Pokemon::factory()
        ->count(21)
        ->sequence(fn ($sequence) => ['number' => $sequence->index + 1])
        ->create();

    // Default sort is most-recently-favorited first, so index 0 (favorited
    // earliest) is the sole item on page 2.
    $favorites = $pokemon->values()->map(fn (Pokemon $p, int $i) => Favorite::factory()->for($user)->create([
        'pokemon_number' => $p->number,
        'created_at' => now()->addSeconds($i),
    ]));

    $component = Volt::test('pages.pokemon.favorites')->call('gotoPage', 2);
    $component->assertSet('paginators.page', 2);

    // Simulates the immediate toggle's successful removal, followed by the
    // favorite-removed event it dispatches on success.
    $favorites->first()->delete();
    $component->dispatch('favorite-removed');

    $component->assertSet('paginators.page', 1);
});
