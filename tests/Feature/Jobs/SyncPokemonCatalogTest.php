<?php

use App\Exceptions\PokeApiSyncException;
use App\Jobs\SyncPokemonCatalog;
use App\Models\Pokemon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// pokeApiIndexBody(), pokeApiTypeBody(), and fakePokeApiCatalog() are defined
// in tests/Pest.php — shared with tests/Feature/Console/PokemonSyncCommandTest.php.

beforeEach(function () {
    Http::preventStrayRequests();
    resetPokeApiState();
});

test('the sync makes exactly 19 calls to PokeAPI', function () {
    fakePokeApiCatalog(
        entries: [
            ['number' => 1, 'name' => 'bulbasaur'],
            ['number' => 4, 'name' => 'charmander'],
        ],
        typeMembers: [
            'grass' => ['bulbasaur'],
            'poison' => ['bulbasaur'],
            'fire' => ['charmander'],
        ],
    );

    runPokemonSync();

    Http::assertSentCount(19);
});

test('the sync creates the pokemon and type records from the catalog', function () {
    fakePokeApiCatalog(
        entries: [
            ['number' => 1, 'name' => 'bulbasaur'],
            ['number' => 4, 'name' => 'charmander'],
        ],
        typeMembers: [
            'grass' => ['bulbasaur'],
            'poison' => ['bulbasaur'],
            'fire' => ['charmander'],
        ],
    );

    runPokemonSync();

    expect(Pokemon::count())->toBe(2);
    expect(DB::table('types')->count())->toBe(18);
    expect(DB::table('pokemon_type')->count())->toBe(3);

    $bulbasaur = Pokemon::query()->findOrFail(1);
    expect($bulbasaur->name)->toBe('bulbasaur');
    expect($bulbasaur->slug)->toBe('bulbasaur');
    expect($bulbasaur->types()->pluck('slug')->sort()->values()->all())->toBe(['grass', 'poison']);

    $charmander = Pokemon::query()->findOrFail(4);
    expect($charmander->types()->pluck('slug')->all())->toBe(['fire']);
});

test('running the sync twice does not duplicate pokemon or type links', function () {
    fakePokeApiCatalog(
        entries: [
            ['number' => 1, 'name' => 'bulbasaur'],
            ['number' => 4, 'name' => 'charmander'],
        ],
        typeMembers: [
            'grass' => ['bulbasaur'],
            'fire' => ['charmander'],
        ],
    );

    runPokemonSync();
    $firstPokemonCount = Pokemon::count();
    $firstPivotCount = DB::table('pokemon_type')->count();

    runPokemonSync();

    expect(Pokemon::count())->toBe($firstPokemonCount);
    expect(DB::table('pokemon_type')->count())->toBe($firstPivotCount);
    expect(DB::table('types')->count())->toBe(18);
});

test('the sync splits writes into batches of 500', function () {
    Log::spy();

    $entries = collect(range(1, 501))
        ->map(fn (int $number) => ['number' => $number, 'name' => "pokemon-{$number}"])
        ->all();

    fakePokeApiCatalog(entries: $entries);

    runPokemonSync();

    expect(Pokemon::count())->toBe(501);

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message, array $context) => $message === 'Lote de pokemon sincronizado' && $context['tabela'] === 'pokemon')
        ->twice();
});

test('the job returns how many pokemon were created and how many were updated', function () {
    fakePokeApiCatalog(entries: [
        ['number' => 1, 'name' => 'bulbasaur'],
        ['number' => 4, 'name' => 'charmander'],
    ]);

    $first = runPokemonSync();

    expect($first->created)->toBe(2);
    expect($first->updated)->toBe(0);
    expect($first->total)->toBe(2);

    $second = runPokemonSync();

    expect($second->created)->toBe(0);
    expect($second->updated)->toBe(2);
    expect($second->total)->toBe(2);
});

test('an availability failure throws PokeApiSyncException with the endpoint', function () {
    Http::fake([
        'https://pokeapi.co/api/v2/pokemon?*' => Http::response(status: 500),
    ]);

    expect(fn () => runPokemonSync())
        ->toThrow(PokeApiSyncException::class, 'index');

    expect(Pokemon::count())->toBe(0);
});

test('an index missing the results key fails fast instead of writing malformed rows', function () {
    Http::fake([
        'https://pokeapi.co/api/v2/pokemon?*' => Http::response(['count' => 0]),
    ]);

    expect(fn () => runPokemonSync())
        ->toThrow(PokeApiSyncException::class);

    expect(Pokemon::count())->toBe(0);
});

test('a member name with no match in the index is skipped and logged', function () {
    Log::spy();

    fakePokeApiCatalog(
        entries: [
            ['number' => 1, 'name' => 'bulbasaur'],
        ],
        typeMembers: [
            'grass' => ['bulbasaur', 'ivysaur'],
        ],
    );

    runPokemonSync();

    expect(DB::table('pokemon_type')->count())->toBe(1);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context) => $message === 'Membro do tipo sem correspondência no índice' && $context['nome'] === 'ivysaur')
        ->once();
});

test('the job loads tries, backoff, and timeout per the spec', function () {
    $job = new SyncPokemonCatalog;

    expect($job->tries)->toBe(3);
    expect($job->backoff)->toBe([10, 30, 60]);
    expect($job->timeout)->toBe(300);
});

test('the job is tagged with pokemon-sync', function () {
    $job = new SyncPokemonCatalog;

    expect($job->tags())->toBe(['pokemon-sync']);
});
