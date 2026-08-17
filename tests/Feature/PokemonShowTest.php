<?php

/*
|--------------------------------------------------------------------------
| F09 — Pokémon details modal
|--------------------------------------------------------------------------
|
| Pokémon details stay inside the catalog. Card interactions target one
| global modal and legacy /pokemon/{slug} links redirect to that same modal.
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
            'base_experience' => 267,
            'moves' => [
                ['move' => ['name' => 'flamethrower']],
                ['move' => ['name' => 'dragon-claw']],
            ],
        ], 200),
        config('pokeapi.base_uri').'/pokemon-species/6*' => Http::response([
            'flavor_text_entries' => [
                ['language' => ['name' => 'pt-BR'], 'flavor_text' => 'Cospe fogo tão quente que derrete qualquer coisa.'],
            ],
            'genera' => [
                ['language' => ['name' => 'en'], 'genus' => 'Flame Pokémon'],
            ],
            'evolution_chain' => ['url' => 'https://pokeapi.co/api/v2/evolution-chain/2/'],
        ], 200),
        config('pokeapi.base_uri').'/evolution-chain/2*' => Http::response([
            'chain' => [
                'species' => ['name' => 'charmander', 'url' => 'https://pokeapi.co/api/v2/pokemon-species/4/'],
                'evolves_to' => [[
                    'species' => ['name' => 'charmeleon', 'url' => 'https://pokeapi.co/api/v2/pokemon-species/5/'],
                    'evolution_details' => [['min_level' => 16, 'trigger' => ['name' => 'level-up']]],
                    'evolves_to' => [[
                        'species' => ['name' => 'charizard', 'url' => 'https://pokeapi.co/api/v2/pokemon-species/6/'],
                        'evolution_details' => [['min_level' => 36, 'trigger' => ['name' => 'level-up']]],
                        'evolves_to' => [],
                    ]],
                ]],
            ],
        ], 200),
    ]);
}

function charizardDetailModal()
{
    return Volt::test('pokemon.detail-modal')
        ->dispatch('open-pokemon', slug: 'charizard');
}

test('legacy detail urls still require authentication', function () {
    $this->get('/pokemon/charizard')->assertRedirect(route('login'));
});

test('legacy detail urls redirect to the catalog modal instead of rendering a page', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/pokemon/charizard')
        ->assertRedirect(route('dashboard', ['pokemon' => 'charizard']));
});

test('opening a local pokemon shows its header immediately without an upstream request', function () {
    $this->actingAs(User::factory()->create());

    $fire = Type::factory()->create(['slug' => 'fire', 'label_pt' => 'fogo']);
    $flying = Type::factory()->create(['slug' => 'flying', 'label_pt' => 'voador']);
    $charizard = Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);
    $charizard->types()->attach([$fire->id, $flying->id]);
    Http::fake();

    charizardDetailModal()
        ->assertSet('slug', 'charizard')
        ->assertSet('number', 6)
        ->assertSet('foundLocally', true)
        ->assertSee('#0006')
        ->assertSee('Charizard')
        ->assertSee('Fogo')
        ->assertSee('Voador')
        ->assertDispatched('open-modal', 'pokemon-details');

    Http::assertNothingSent();
});

test('the catalog query string opens the same modal for backward-compatible links', function () {
    $this->actingAs(User::factory()->create());
    Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);
    Http::fake();

    $this->get('/?pokemon=charizard')
        ->assertOk()
        ->assertSee('data-pokemon-detail-modal', false)
        ->assertSee('style="display: block;"', false)
        ->assertSee('#0006')
        ->assertSee('Charizard');

    Http::assertNothingSent();
});

test('details load lazily inside the modal', function () {
    $this->actingAs(User::factory()->create());
    Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);
    fakeCharizardDetail();

    charizardDetailModal()
        ->call('loadDetail')
        ->assertSee('Blaze')
        ->assertSee('Solar power')
        ->assertSee('(oculta)')
        ->assertSee('1,7 m')
        ->assertSee('90,5 kg')
        ->assertSee('267')
        ->assertSee('Flame Pokémon')
        ->assertSee('Água')
        ->assertSee('Pedra')
        ->assertSee('Elétrico')
        ->assertDontSee('Gelo')
        ->assertDontSee('Terrestre')
        ->assertSee('Charmander')
        ->assertSee('Charmeleon')
        ->assertSee('Flamethrower')
        ->assertSee('Cospe fogo tão quente que derrete qualquer coisa.');
});

test('stat bars preserve the three value color bands', function () {
    $this->actingAs(User::factory()->create());
    Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);
    fakeCharizardDetail();

    $html = charizardDetailModal()->call('loadDetail')->html();

    expect($html)->toContain('data-band="low"')
        ->toContain('data-band="medium"')
        ->toContain('data-band="high"');
});

test('cached details avoid duplicate upstream calls when the modal is reopened', function () {
    $this->actingAs(User::factory()->create());
    Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);
    fakeCharizardDetail();

    charizardDetailModal()->call('loadDetail');
    charizardDetailModal()->call('loadDetail');

    Http::assertSentCount(3);
});

test('local data remains visible and detail loading can be retried after an outage', function () {
    $this->actingAs(User::factory()->create());
    Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);

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

    $component = charizardDetailModal()->call('loadDetail');

    $component->assertSee('Charizard')
        ->assertSee('#0006')
        ->assertSee('Não foi possível carregar os detalhes agora.')
        ->assertSee('Tentar novamente')
        ->call('retry')
        ->assertDontSee('Não foi possível carregar os detalhes agora.')
        ->assertSee('Blaze');
});

test('a pokemon absent from the local catalog can be loaded upstream in the modal', function () {
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

    charizardDetailModal()
        ->call('loadDetail')
        ->assertSee('#0006')
        ->assertSee('Charizard')
        ->assertSee('Fogo')
        ->assertSee('Blaze');
});

test('a missing pokemon has an explicit modal state', function () {
    $this->actingAs(User::factory()->create());
    Http::fake(['*' => Http::response(null, 404)]);

    Volt::test('pokemon.detail-modal')
        ->dispatch('open-pokemon', slug: 'inexistente')
        ->call('loadDetail')
        ->assertSee('Pokémon não encontrado.');
});

test('empty abilities and stats have explicit unavailable states', function () {
    $this->actingAs(User::factory()->create());
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

    $html = Volt::test('pokemon.detail-modal')
        ->dispatch('open-pokemon', slug: 'bulbasaur')
        ->call('loadDetail')
        ->html();

    expect($html)->toContain('Habilidades')
        ->toContain('Estatísticas base')
        ->and(substr_count($html, 'Informação indisponível.'))->toBeGreaterThanOrEqual(2);
});

test('the modal keeps a broken sprite from disrupting the layout', function () {
    $this->actingAs(User::factory()->create());
    Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);
    Http::fake();

    charizardDetailModal()
        ->assertSee('x-on:error="broken = true"', false)
        ->assertSee('aria-label="Fechar detalhes"', false);
});
