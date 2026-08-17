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

test('um usuario nao consegue remover o favorito de outro usuario', function () {
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

test('a politica nega a exclusao de um favorito que nao pertence ao usuario autenticado', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $pokemon = Pokemon::factory()->create();

    $favoriteB = Favorite::factory()->for($userB)->create(['pokemon_number' => $pokemon->number]);

    $policy = new FavoritePolicy;

    expect($policy->delete($userA, $favoriteB))->toBeFalse()
        ->and($policy->delete($userB, $favoriteB))->toBeTrue();
});

test('um convidado que tenta favoritar e redirecionado para o login preservando a url pretendida', function () {
    $this->get('/favoritos')->assertRedirect(route('login'));

    expect(Favorite::query()->count())->toBe(0);
});

test('usuario a nunca ve os favoritos do usuario b na propria listagem', function () {
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
