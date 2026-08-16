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

test('o sync faz exatamente 19 chamadas ao PokeAPI', function () {
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

test('o sync cria os registros de pokemon e tipos a partir do catálogo', function () {
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

test('executar o sync duas vezes não duplica pokemon nem vínculos de tipo', function () {
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

test('o sync divide as escritas em lotes de 500', function () {
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

test('o job retorna quantos pokemon foram criados e quantos foram atualizados', function () {
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

test('uma falha de disponibilidade lança PokeApiSyncException com o endpoint', function () {
    Http::fake([
        'https://pokeapi.co/api/v2/pokemon?*' => Http::response(status: 500),
    ]);

    expect(fn () => runPokemonSync())
        ->toThrow(PokeApiSyncException::class, 'index');

    expect(Pokemon::count())->toBe(0);
});

test('um índice sem a chave results falha rápido em vez de gravar linhas malformadas', function () {
    Http::fake([
        'https://pokeapi.co/api/v2/pokemon?*' => Http::response(['count' => 0]),
    ]);

    expect(fn () => runPokemonSync())
        ->toThrow(PokeApiSyncException::class);

    expect(Pokemon::count())->toBe(0);
});

test('um nome de membro sem correspondência no índice é ignorado e registrado', function () {
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

test('o job carrega tries, backoff e timeout conforme a especificação', function () {
    $job = new SyncPokemonCatalog;

    expect($job->tries)->toBe(3);
    expect($job->backoff)->toBe([10, 30, 60]);
    expect($job->timeout)->toBe(300);
});

test('o job está marcado com a tag pokemon-sync', function () {
    $job = new SyncPokemonCatalog;

    expect($job->tags())->toBe(['pokemon-sync']);
});
