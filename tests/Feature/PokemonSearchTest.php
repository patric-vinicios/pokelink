<?php

/*
|--------------------------------------------------------------------------
| F07 — Pokémon search
|--------------------------------------------------------------------------
|
| The `pages.pokemon.search` Volt component mounted at `/`: debounced name
| search, single-select type filter, both mirrored into the URL, pagination
| reset on filter change, and the two non-happy-path states the PRD calls
| out — an empty catalog (sync still running) and a filter matching nothing.
|
| Name-search fixtures use ASCII-safe fragments: SQLite (this suite's test
| connection) case-folds LIKE only for ASCII, unlike MySQL's
| utf8mb4_unicode_ci (production). Accent-insensitivity is a property of
| that production column collation, not of code this feature adds.
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

test('a busca por nome é case e acento insensível e retorna correspondências parciais', function () {
    Pokemon::factory()->create(['number' => 4, 'name' => 'charmander', 'slug' => 'charmander']);
    Pokemon::factory()->create(['number' => 1, 'name' => 'bulbasaur', 'slug' => 'bulbasaur']);

    Volt::test('pages.pokemon.search')
        ->set('search', 'HAR')
        ->assertSee('charmander')
        ->assertDontSee('bulbasaur');
});

test('selecionar um tipo restringe os resultados ao pivot', function () {
    $fire = Type::factory()->create(['slug' => 'fire', 'label_pt' => 'fogo']);
    $water = Type::factory()->create(['slug' => 'water', 'label_pt' => 'água']);

    $charmander = Pokemon::factory()->create(['number' => 4, 'name' => 'charmander', 'slug' => 'charmander']);
    $charmander->types()->attach($fire->id);

    $squirtle = Pokemon::factory()->create(['number' => 7, 'name' => 'squirtle', 'slug' => 'squirtle']);
    $squirtle->types()->attach($water->id);

    Volt::test('pages.pokemon.search')
        ->set('type', 'fogo')
        ->assertSee('charmander')
        ->assertDontSee('squirtle');
});

test('nome e tipo combinados usam semântica AND', function () {
    $fire = Type::factory()->create(['slug' => 'fire', 'label_pt' => 'fogo']);
    $water = Type::factory()->create(['slug' => 'water', 'label_pt' => 'água']);

    $matchesBoth = Pokemon::factory()->create(['number' => 4, 'name' => 'charmander', 'slug' => 'charmander']);
    $matchesBoth->types()->attach($fire->id);

    $matchesNameOnly = Pokemon::factory()->create(['number' => 5, 'name' => 'charmeleon', 'slug' => 'charmeleon']);
    $matchesNameOnly->types()->attach($water->id);

    $matchesTypeOnly = Pokemon::factory()->create(['number' => 7, 'name' => 'squirtle', 'slug' => 'squirtle']);
    $matchesTypeOnly->types()->attach($fire->id);

    $matchesNeither = Pokemon::factory()->create(['number' => 1, 'name' => 'bulbasaur', 'slug' => 'bulbasaur']);
    $matchesNeither->types()->attach($water->id);

    Volt::test('pages.pokemon.search')
        ->set('search', 'char')
        ->set('type', 'fogo')
        ->assertSee('charmander')
        ->assertDontSee('charmeleon')
        ->assertDontSee('squirtle')
        ->assertDontSee('bulbasaur');
});

test('qualquer mudança de filtro reseta a paginação para a página 1', function () {
    Pokemon::factory()
        ->count(25)
        ->sequence(fn ($sequence) => ['number' => $sequence->index + 1])
        ->create();

    $component = Volt::test('pages.pokemon.search')->call('gotoPage', 2);

    expect($component->get('paginators.page'))->toBe(2);

    $component->set('search', 'a');

    expect($component->get('paginators.page'))->toBe(1);

    $component->call('gotoPage', 2)->set('type', '');

    expect($component->get('paginators.page'))->toBe(1);
});

test('uma busca sem correspondência mostra o estado vazio nomeando o termo e o botão limpar filtros', function () {
    Pokemon::factory()->create(['number' => 1, 'name' => 'bulbasaur', 'slug' => 'bulbasaur']);

    Volt::test('pages.pokemon.search')
        ->set('search', 'xyz')
        ->assertSee("Nenhum Pokémon encontrado para 'xyz'.")
        ->assertSee('Limpar filtros');
});

test('limpar filtros reseta busca, tipo e página em um único round-trip', function () {
    $fire = Type::factory()->create(['slug' => 'fire', 'label_pt' => 'fogo']);
    Pokemon::factory()
        ->count(25)
        ->sequence(fn ($sequence) => [
            'number' => $sequence->index + 1,
            'name' => 'lava-'.$sequence->index,
            'slug' => 'lava-'.$sequence->index,
        ])
        ->create()
        ->each(fn (Pokemon $pokemon) => $pokemon->types()->attach($fire->id));

    $component = Volt::test('pages.pokemon.search')
        ->set('search', 'a')
        ->set('type', 'fogo')
        ->call('gotoPage', 2);

    expect($component->get('search'))->toBe('a')
        ->and($component->get('type'))->toBe('fogo')
        ->and($component->get('paginators.page'))->toBe(2);

    $component->call('clearFilters');

    expect($component->get('search'))->toBe('')
        ->and($component->get('type'))->toBe('')
        ->and($component->get('paginators.page'))->toBe(1);
});

test('os filtros ativos são refletidos na url e recarregar restaura o mesmo resultado', function () {
    $fire = Type::factory()->create(['slug' => 'fire', 'label_pt' => 'fogo']);
    $water = Type::factory()->create(['slug' => 'water', 'label_pt' => 'água']);

    $charmander = Pokemon::factory()->create(['number' => 4, 'name' => 'charmander', 'slug' => 'charmander']);
    $charmander->types()->attach($fire->id);

    $charizard = Pokemon::factory()->create(['number' => 6, 'name' => 'charizard', 'slug' => 'charizard']);
    $charizard->types()->attach($fire->id);

    $squirtle = Pokemon::factory()->create(['number' => 7, 'name' => 'squirtle', 'slug' => 'squirtle']);
    $squirtle->types()->attach($water->id);

    $this->get('/?q=char&tipo=fogo')
        ->assertOk()
        ->assertSee('charmander')
        ->assertSee('charizard')
        ->assertDontSee('squirtle');
});

test('com acesso de rede bloqueado, busca, filtro de tipo e paginação continuam funcionando', function () {
    $fire = Type::factory()->create(['slug' => 'fire', 'label_pt' => 'fogo']);
    Pokemon::factory()
        ->count(25)
        ->sequence(fn ($sequence) => ['number' => $sequence->index + 1])
        ->create()
        ->each(fn (Pokemon $pokemon) => $pokemon->types()->attach($fire->id));

    Volt::test('pages.pokemon.search')
        ->set('search', 'a')
        ->set('type', 'fogo')
        ->call('gotoPage', 2)
        ->assertHasNoErrors();

    Http::assertNothingSent();
});

test('com o catálogo vazio, a área de busca mostra o estado de sincronização', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Catálogo sincronizando... isso leva menos de um minuto.')
        ->assertSee('wire:poll.5s', false);
});

test('o select de tipo é populado com os 18 tipos e seus rótulos em pt-BR', function () {
    fakePokeApiCatalog(entries: [
        ['number' => 1, 'name' => 'bulbasaur'],
    ]);

    runPokemonSync();

    $component = Volt::test('pages.pokemon.search');

    expect(substr_count($component->html(), '<option value="'))->toBe(19);

    collect(config('pokemon.type_labels'))
        ->each(fn (string $label) => $component->assertSee($label));
});

test('um pokémon presente na tabela local é encontrável por um fragmento do nome', function () {
    fakePokeApiCatalog(entries: [
        ['number' => 1, 'name' => 'bulbasaur'],
        ['number' => 4, 'name' => 'charmander'],
    ]);

    runPokemonSync();

    Volt::test('pages.pokemon.search')
        ->set('search', 'bulba')
        ->assertSee('bulbasaur')
        ->assertDontSee('charmander');
});
