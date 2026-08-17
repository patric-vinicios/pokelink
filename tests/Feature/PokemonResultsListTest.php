<?php

/*
|--------------------------------------------------------------------------
| F08 — Results list and pagination
|--------------------------------------------------------------------------
|
| Covers the card grid, skeleton, translated paginator, and page-clamp guard
| that F08 adds inside `pages.pokemon.search` (F07). The filtering logic
| itself (search/type/AND semantics/URL mirroring) is F07's and already
| covered by tests/Feature/PokemonSearchTest.php — untouched here.
|
*/

use App\Models\Pokemon;
use App\Models\Type;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

beforeEach(function () {
    Http::preventStrayRequests();
    resetPokeApiState();

    $this->actingAs(User::factory()->create());
});

test('each card shows the sprite, capitalized name, zero-padded national number, and one badge per type', function () {
    $fire = Type::factory()->create(['slug' => 'fire', 'label_pt' => 'fogo']);
    $flying = Type::factory()->create(['slug' => 'flying', 'label_pt' => 'voador']);

    $charizard = Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);
    $charizard->types()->attach([$fire->id, $flying->id]);

    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('#0006')
        ->assertSee('charizard')
        ->assertSee('Fogo')
        ->assertSee('Voador');
});

test('exactly 20 cards render per page and the summary matches the filter\'s total', function () {
    Pokemon::factory()
        ->count(45)
        ->sequence(fn ($sequence) => ['number' => $sequence->index + 1])
        ->create();

    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('45 Pokémon encontrados')
        ->assertSee('Exibindo')
        ->assertSee('<span class="font-medium">1</span>', false)
        ->assertSee('<span class="font-medium">20</span>', false);

    expect(substr_count($response->getContent(), 'wire:key="pokemon-'))->toBe(20);
});

test('requesting a page past the last one redirects to the last valid page', function () {
    Pokemon::factory()
        ->count(25)
        ->sequence(fn ($sequence) => ['number' => $sequence->index + 1])
        ->create();

    $response = $this->get('/?page=99');

    $response->assertOk();
    expect(substr_count($response->getContent(), 'wire:key="pokemon-'))->toBe(5);

    Volt::test('pages.pokemon.search')
        ->call('gotoPage', 99)
        ->assertSet('paginators.page', 2);
});

test('clicking a card leads to pokemon show with the page and filter context', function () {
    $fire = Type::factory()->create(['slug' => 'fire', 'label_pt' => 'fogo']);
    $charmander = Pokemon::factory()->create(['number' => 4, 'name' => 'charmander', 'slug' => 'charmander']);
    $charmander->types()->attach($fire->id);

    $response = $this->get('/?q=char&tipo=fogo');

    $expectedHref = route('pokemon.show', ['slug' => 'charmander']).'?'.http_build_query([
        'q' => 'char',
        'tipo' => 'fogo',
        'page' => 1,
    ]);

    $response->assertOk()->assertSee($expectedHref);
});

test('a broken sprite url renders the placeholder without breaking the card layout', function () {
    Pokemon::factory()->create(['number' => 1, 'name' => 'bulbasaur', 'slug' => 'bulbasaur']);

    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('x-on:error="broken = true"', false)
        ->assertSee('#0001')
        ->assertSee('bulbasaur');
});

test('skeleton cards occupy the grid during filter and pagination changes', function () {
    Pokemon::factory()
        ->count(5)
        ->sequence(fn ($sequence) => ['number' => $sequence->index + 1])
        ->create();

    $html = Volt::test('pages.pokemon.search')->html();

    expect(substr_count($html, 'animate-pulse'))->toBe(20);
});

test('the grid uses the responsive 4 3 2 1 columns', function () {
    Pokemon::factory()->create(['number' => 1, 'name' => 'bulbasaur', 'slug' => 'bulbasaur']);

    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4', false);
});

test('f07\'s filtered query drives the card count and the paginator\'s page total', function () {
    $fire = Type::factory()->create(['slug' => 'fire', 'label_pt' => 'fogo']);
    $water = Type::factory()->create(['slug' => 'water', 'label_pt' => 'água']);

    Pokemon::factory()
        ->count(8)
        ->sequence(fn ($sequence) => ['number' => $sequence->index + 1])
        ->create()
        ->each(fn (Pokemon $pokemon) => $pokemon->types()->attach($fire->id));

    Pokemon::factory()
        ->count(3)
        ->sequence(fn ($sequence) => ['number' => $sequence->index + 100])
        ->create()
        ->each(fn (Pokemon $pokemon) => $pokemon->types()->attach($water->id));

    $response = $this->get('/?tipo=fogo');

    $response->assertOk()->assertSee('8 Pokémon encontrados');

    expect(substr_count($response->getContent(), 'wire:key="pokemon-'))->toBe(8);
});
