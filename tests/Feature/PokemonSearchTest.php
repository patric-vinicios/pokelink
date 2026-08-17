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

test('name search is case- and accent-insensitive and returns partial matches', function () {
    Pokemon::factory()->create(['number' => 4, 'name' => 'charmander', 'slug' => 'charmander']);
    Pokemon::factory()->create(['number' => 1, 'name' => 'bulbasaur', 'slug' => 'bulbasaur']);

    Volt::test('pages.pokemon.search')
        ->set('search', 'HAR')
        ->assertSee('charmander')
        ->assertDontSee('bulbasaur');
});

test('selecting a type restricts results to the pivot', function () {
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

test('name and type combined use AND semantics', function () {
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

test('any filter change resets pagination to page 1', function () {
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

test('a search with no matches shows the empty state naming the term and the clear-filters button', function () {
    Pokemon::factory()->create(['number' => 1, 'name' => 'bulbasaur', 'slug' => 'bulbasaur']);

    Volt::test('pages.pokemon.search')
        ->set('search', 'xyz')
        ->assertSee("Nenhum Pokémon encontrado para 'xyz'.")
        ->assertSee('Limpar filtros');
});

test('clearing filters resets search, type, and page in a single round-trip', function () {
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

test('active filters are reflected in the url and reloading restores the same result', function () {
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

test('with network access blocked, search, type filter, and pagination keep working', function () {
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

test('with an empty catalog, the search area shows the syncing state', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Catálogo sincronizando... isso leva menos de um minuto.')
        ->assertSee('wire:poll.5s', false);
});

test('the results toolbar omits inactive sorting and view controls', function () {
    Pokemon::factory()->create();

    Volt::test('pages.pokemon.search')
        ->assertDontSee('Ordenar por:')
        ->assertDontSee('Visualização em grade selecionada');
});

test('the type select is populated with the 18 types and their pt-BR labels', function () {
    fakePokeApiCatalog(entries: [
        ['number' => 1, 'name' => 'bulbasaur'],
    ]);

    runPokemonSync();

    $component = Volt::test('pages.pokemon.search');

    expect(substr_count($component->html(), '<option value="'))->toBe(19);

    collect(config('pokemon.type_labels'))
        ->each(fn (string $label) => $component->assertSee($label));
});

test('the type filter renders the official icon for each of the 18 types', function () {
    collect(config('pokemon.type_labels'))
        ->each(fn (string $label, string $slug) => Type::factory()->create([
            'slug' => $slug,
            'label_pt' => $label,
        ]));

    $component = Volt::test('pages.pokemon.search');

    collect(config('pokemon.type_labels'))->keys()->each(function (string $slug) use ($component) {
        $sourcePath = "images/icons/types/{$slug}.svg";
        $relativePath = "images/icons/types/glyphs/{$slug}.svg";

        expect(public_path($sourcePath))->toBeFile();
        expect(public_path($relativePath))->toBeFile();
        $component->assertSee(asset($relativePath), false);
    });
});

test('a pokémon present in the local table is findable by a name fragment', function () {
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
