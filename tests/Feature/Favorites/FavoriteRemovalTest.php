<?php

/*
|--------------------------------------------------------------------------
| F10 — Removal confirmation
|--------------------------------------------------------------------------
|
| Covers the confirm-gated toggle instance rendered on /favoritos: the star
| opens a modal instead of mutating anything, and only the modal's own
| confirm action (toggle()) actually removes the row.
|
*/

use App\Models\Favorite;
use App\Models\Pokemon;
use App\Models\User;
use Livewire\Volt\Volt;

test('removing asks for confirmation before writing any change', function () {
    $user = User::factory()->create();
    $pokemon = Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);

    $this->actingAs($user);

    $favorite = Favorite::factory()->for($user)->create(['pokemon_number' => $pokemon->number]);

    Volt::test('pokemon.favorite-toggle', [
        'number' => $pokemon->number,
        'name' => $pokemon->name,
        'favorited' => true,
        'variant' => 'icon',
        'confirmRemoval' => true,
    ])
        ->call('requestRemoval')
        ->assertDispatched('open-modal', "remove-favorite-{$pokemon->number}")
        ->assertNotDispatched('toast');

    expect(Favorite::query()->whereKey($favorite->id)->exists())->toBeTrue();
});

test('confirming removal deletes the row and dispatches the removal event', function () {
    $user = User::factory()->create();
    $pokemon = Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);

    $this->actingAs($user);

    Favorite::factory()->for($user)->create(['pokemon_number' => $pokemon->number]);

    Volt::test('pokemon.favorite-toggle', [
        'number' => $pokemon->number,
        'name' => $pokemon->name,
        'favorited' => true,
        'variant' => 'icon',
        'confirmRemoval' => true,
    ])
        ->call('toggle')
        ->assertSet('favorited', false)
        ->assertDispatched('favorite-removed')
        ->assertDispatched('close-modal', "remove-favorite-{$pokemon->number}");

    expect(Favorite::query()->where('user_id', $user->id)->where('pokemon_number', $pokemon->number)->exists())->toBeFalse();
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

    // Simulates the confirm-gated toggle's own successful removal, followed
    // by the favorite-removed event it dispatches on success.
    $favorites->first()->delete();
    $component->dispatch('favorite-removed');

    $component->assertSet('paginators.page', 1);
});

test('canceling the confirmation does not change the state', function () {
    $user = User::factory()->create();
    $pokemon = Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);

    $this->actingAs($user);

    $favorite = Favorite::factory()->for($user)->create(['pokemon_number' => $pokemon->number]);

    Volt::test('pokemon.favorite-toggle', [
        'number' => $pokemon->number,
        'name' => $pokemon->name,
        'favorited' => true,
        'confirmRemoval' => true,
    ])->call('requestRemoval');

    expect(Favorite::query()->whereKey($favorite->id)->exists())->toBeTrue();
});
