<?php

/*
|--------------------------------------------------------------------------
| F09 — Pokémon details
|--------------------------------------------------------------------------
|
| Replaces F08's "Em construção" placeholder at /pokemon/{slug} with the
| real detail page: header renders immediately (local catalog or, when the
| slug is missing locally, a synchronous upstream lookup), PokeAPI-only
| fields (abilities, stats, height, weight, flavor text) load lazily via
| wire:init and degrade gracefully when PokeAPI is unavailable.
|
*/

use App\Models\Pokemon;
use App\Models\Type;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

test('the detail page requires authentication', function () {
    $this->get('/pokemon/charizard')->assertRedirect(route('login'));
});

// No global actingAs — the guest-redirect test above needs to run
// unauthenticated, so every other test logs in explicitly instead.
beforeEach(function () {
    Http::preventStrayRequests();
    resetPokeApiState();
});

function fakeCharizardDetail(): void
{
    Http::fake([
        config('pokeapi.base_uri').'/pokemon/6*' => Http::response([
            'id' => 6,
            'name' => 'charizard',
            'types' => [
                ['slot' => 1, 'type' => ['name' => 'fire']],
                ['slot' => 2, 'type' => ['name' => 'flying']],
            ],
            'abilities' => [
                ['ability' => ['name' => 'blaze'], 'is_hidden' => false],
                ['ability' => ['name' => 'solar-power'], 'is_hidden' => true],
            ],
            'stats' => [
                ['stat' => ['name' => 'hp'], 'base_stat' => 78],
                ['stat' => ['name' => 'attack'], 'base_stat' => 84],
                ['stat' => ['name' => 'defense'], 'base_stat' => 55],
                ['stat' => ['name' => 'special-attack'], 'base_stat' => 109],
                ['stat' => ['name' => 'special-defense'], 'base_stat' => 85],
                ['stat' => ['name' => 'speed'], 'base_stat' => 100],
            ],
            'height' => 17,
            'weight' => 905,
        ], 200),
        config('pokeapi.base_uri').'/pokemon-species/6*' => Http::response([
            'flavor_text_entries' => [
                ['language' => ['name' => 'pt-BR'], 'flavor_text' => 'Cospe fogo tão quente que derrete qualquer coisa.'],
            ],
        ], 200),
    ]);
}

test('a local pokemon shows the header immediately without waiting for the detail fetch', function () {
    $this->actingAs(User::factory()->create());

    $fire = Type::factory()->create(['slug' => 'fire', 'label_pt' => 'fogo']);
    $flying = Type::factory()->create(['slug' => 'flying', 'label_pt' => 'voador']);
    $charizard = Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);
    $charizard->types()->attach([$fire->id, $flying->id]);

    // Detail endpoint never faked — a synchronous mount() would blow up.
    Http::fake();

    $response = $this->get('/pokemon/charizard');

    $response->assertOk()
        ->assertSee('#0006')
        ->assertSee('charizard')
        ->assertSee('Fogo')
        ->assertSee('Voador');

    Http::assertNothingSent();
});

test('details load via wire init and fill in abilities, stats, height, and weight', function () {
    Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);
    fakeCharizardDetail();

    $component = Volt::test('pages.pokemon.show', ['slug' => 'charizard'])
        ->call('loadDetail');

    $component->assertSee('Blaze')
        ->assertSee('Solar power')
        ->assertSee('78')
        ->assertSee('84')
        ->assertSee('55')
        ->assertSee('109')
        ->assertSee('85')
        ->assertSee('100')
        ->assertSee('1,7 m')
        ->assertSee('90,5 kg');
});

test('a hidden ability is marked as hidden', function () {
    Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);
    fakeCharizardDetail();

    Volt::test('pages.pokemon.show', ['slug' => 'charizard'])
        ->call('loadDetail')
        ->assertSee('Solar power')
        ->assertSee('(oculta)');
});

test('the stat bars use the 3 color bands', function () {
    Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);
    fakeCharizardDetail();

    $html = Volt::test('pages.pokemon.show', ['slug' => 'charizard'])
        ->call('loadDetail')
        ->html();

    // Defesa = 55 (<60, vermelho), HP = 78 (60-99, amarelo), Velocidade = 100 (>=100, verde).
    expect($html)->toContain('bg-red-500')
        ->toContain('bg-yellow-500')
        ->toContain('bg-green-500');
});

test('a second visit to the same pokemon does not trigger a new upstream call', function () {
    Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);
    fakeCharizardDetail();

    Volt::test('pages.pokemon.show', ['slug' => 'charizard'])->call('loadDetail');
    Volt::test('pages.pokemon.show', ['slug' => 'charizard'])->call('loadDetail');

    Http::assertSentCount(2);
});

test('with pokeapi unavailable the local data remains and the try-again button works', function () {
    Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);

    // loadDetail() exhausts 3 retries against /pokemon/6 (all 500) before
    // giving up; retry() then tries again and this time succeeds. A single
    // Http::fake() with a sequence avoids the earlier wildcard rule from a
    // second Http::fake() call lingering and shadowing the new one.
    Http::fake([
        config('pokeapi.base_uri').'/pokemon/6*' => Http::sequence()
            ->push(null, 500)
            ->push(null, 500)
            ->push(null, 500)
            ->push([
                'id' => 6,
                'name' => 'charizard',
                'types' => [['slot' => 1, 'type' => ['name' => 'fire']]],
                'abilities' => [['ability' => ['name' => 'blaze'], 'is_hidden' => false]],
                'stats' => [['stat' => ['name' => 'hp'], 'base_stat' => 78]],
                'height' => 17,
                'weight' => 905,
            ], 200),
        config('pokeapi.base_uri').'/pokemon-species/6*' => Http::response(['flavor_text_entries' => []], 200),
    ]);

    $component = Volt::test('pages.pokemon.show', ['slug' => 'charizard'])
        ->call('loadDetail');

    $component->assertSee('charizard')
        ->assertSee('#0006')
        ->assertSee('Não foi possível carregar os detalhes agora.')
        ->assertSee('Tentar novamente');

    $component->call('retry')
        ->assertDontSee('Não foi possível carregar os detalhes agora.')
        ->assertSee('Blaze');
});

test('a slug missing locally still attempts the upstream lookup before concluding it doesn\'t exist', function () {
    $this->actingAs(User::factory()->create());

    Http::fake([
        config('pokeapi.base_uri').'/pokemon/charizard*' => Http::response([
            'id' => 6,
            'name' => 'charizard',
            'types' => [['slot' => 1, 'type' => ['name' => 'fire']]],
            'abilities' => [['ability' => ['name' => 'blaze'], 'is_hidden' => false]],
            'stats' => [['stat' => ['name' => 'hp'], 'base_stat' => 78]],
            'height' => 17,
            'weight' => 905,
        ], 200),
        config('pokeapi.base_uri').'/pokemon-species/charizard*' => Http::response(['flavor_text_entries' => []], 200),
    ]);

    $response = $this->get('/pokemon/charizard');

    $response->assertOk()
        ->assertSee('#0006')
        ->assertSee('charizard')
        ->assertSee('Fogo')
        ->assertSee('Blaze');
});

test('a slug missing both locally and upstream shows the pokemon-not-found page', function () {
    $this->actingAs(User::factory()->create());

    Http::fake(['*' => Http::response(null, 404)]);

    $response = $this->get('/pokemon/inexistente');

    $response->assertOk()
        ->assertSee('Pokémon não encontrado.')
        ->assertSee('Buscar Pokémon');

    expect(navLinkIsActive($response->getContent(), 'Início'))->toBeTrue();
});

test('a slug missing locally with upstream unavailable shows the warning, not the not-found page', function () {
    $this->actingAs(User::factory()->create());

    Http::fake(['*' => Http::response(null, 500)]);

    $response = $this->get('/pokemon/charizard');

    $response->assertOk()
        ->assertDontSee('Pokémon não encontrado.')
        ->assertSee('Não foi possível carregar os detalhes agora.')
        ->assertSee('Tentar novamente');
});

test('a payload with no stats or abilities shows unavailable info for that section', function () {
    Pokemon::factory()->create(['number' => 1, 'name' => 'bulbasaur', 'slug' => 'bulbasaur']);

    Http::fake([
        config('pokeapi.base_uri').'/pokemon/1*' => Http::response([
            'id' => 1,
            'name' => 'bulbasaur',
            'types' => [],
            'abilities' => [],
            'stats' => [],
            'height' => 7,
            'weight' => 69,
        ], 200),
        config('pokeapi.base_uri').'/pokemon-species/1*' => Http::response(['flavor_text_entries' => []], 200),
    ]);

    $html = Volt::test('pages.pokemon.show', ['slug' => 'bulbasaur'])
        ->call('loadDetail')
        ->html();

    expect($html)->toContain('Habilidades')
        ->toContain('Estatísticas base')
        ->and(substr_count($html, 'Informação indisponível.'))->toBe(2);
});

test('going back to results rebuilds the origin page and filters', function () {
    $this->actingAs(User::factory()->create());

    Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);
    Http::fake();

    $response = $this->get('/pokemon/charizard?q=char&tipo=fogo&page=2');

    $expected = route('dashboard').'?'.http_build_query(['q' => 'char', 'tipo' => 'fogo', 'page' => '2']);

    $response->assertOk()->assertSee($expected);
});

test('arriving directly via url with no origin context leads back home', function () {
    $this->actingAs(User::factory()->create());

    Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);
    Http::fake();

    $response = $this->get('/pokemon/charizard');

    $response->assertOk()->assertSee(route('dashboard'));
});

test('a broken sprite url renders the placeholder without breaking the layout', function () {
    $this->actingAs(User::factory()->create());

    Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);
    Http::fake();

    $response = $this->get('/pokemon/charizard');

    $response->assertOk()->assertSee('x-on:error="broken = true"', false);
});

test('the detail page keeps "Início" highlighted in the navigation', function () {
    $this->actingAs(User::factory()->create());

    Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);
    Http::fake();

    $html = $this->get('/pokemon/charizard')->assertOk()->getContent();

    expect(navLinkIsActive($html, 'Início'))->toBeTrue();
});
