<?php

/*
|--------------------------------------------------------------------------
| F10 — Authorization (IDOR)
|--------------------------------------------------------------------------
|
| Proves every read/write is scoped through the authenticated user rather
| than an identifier from the request, and that FavoritePolicy denies a
| cross-user delete as a second line of defense behind that scoping.
|
*/

use App\Models\Favorite;
use App\Models\Pokemon;
use App\Models\User;
use App\Policies\FavoritePolicy;
use Livewire\Volt\Volt;

test('a user cannot remove another user\'s favorite', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $pokemon = Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);

    $favoriteB = Favorite::factory()->for($userB)->create(['pokemon_number' => $pokemon->number]);

    $this->actingAs($userA);

    // User A's toggle instance is mounted with user B's Pokémon, but the
    // lookup inside toggle() is scoped to auth()->id(), so it never finds
    // user B's row — it creates user A's own row instead.
    Volt::test('pokemon.favorite-toggle', [
        'number' => $pokemon->number,
        'name' => $pokemon->name,
        'favorited' => false,
    ])->call('toggle')->assertSet('favorited', true);

    expect(Favorite::query()->whereKey($favoriteB->id)->exists())->toBeTrue()
        ->and(Favorite::query()->where('user_id', $userA->id)->where('pokemon_number', $pokemon->number)->count())->toBe(1)
        ->and(Favorite::query()->count())->toBe(2);
});

test('the policy denies deleting a favorite that doesn\'t belong to the authenticated user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $pokemon = Pokemon::factory()->create();

    $favoriteB = Favorite::factory()->for($userB)->create(['pokemon_number' => $pokemon->number]);

    $policy = new FavoritePolicy;

    expect($policy->delete($userA, $favoriteB))->toBeFalse()
        ->and($policy->delete($userB, $favoriteB))->toBeTrue();
});

test('a guest trying to favorite is redirected to login preserving the intended url', function () {
    $this->get('/favoritos')->assertRedirect(route('login'));

    expect(Favorite::query()->count())->toBe(0);
});

test('user a never sees user b\'s favorites in their own listing', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $pokemon = Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);

    Favorite::factory()->for($userA)->create(['pokemon_number' => $pokemon->number]);
    Favorite::factory()->for($userB)->create(['pokemon_number' => $pokemon->number]);

    expect(Favorite::query()->where('pokemon_number', $pokemon->number)->count())->toBe(2);

    $response = $this->actingAs($userA)->get('/favoritos');

    $response->assertOk();
    expect(substr_count($response->getContent(), 'wire:key="favorite-pokemon-6"'))->toBe(1);
});
