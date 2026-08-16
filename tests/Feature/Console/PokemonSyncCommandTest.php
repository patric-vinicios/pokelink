<?php

use App\Jobs\SyncPokemonCatalog;
use App\Models\Pokemon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::preventStrayRequests();
    resetPokeApiState();
});

test('o comando pokemon:sync imprime o resumo de criados e atualizados', function () {
    fakePokeApiCatalog(entries: [
        ['number' => 1, 'name' => 'bulbasaur'],
        ['number' => 4, 'name' => 'charmander'],
    ]);

    Artisan::call('pokemon:sync');

    expect(Artisan::output())->toContain('2 Pokémon sincronizados (2 criados, 0 atualizados)');
});

test('rodar o comando pela segunda vez não cria duplicatas', function () {
    fakePokeApiCatalog(entries: [
        ['number' => 1, 'name' => 'bulbasaur'],
        ['number' => 4, 'name' => 'charmander'],
    ]);

    Artisan::call('pokemon:sync');
    $firstCount = Pokemon::count();

    Artisan::call('pokemon:sync');

    expect(Pokemon::count())->toBe($firstCount);
    expect(Artisan::output())->toContain('2 Pokémon sincronizados (0 criados, 2 atualizados)');
});

test('o seeder despacha o job de sincronização na fila padrão', function () {
    Queue::fake();

    $this->seed(DatabaseSeeder::class);

    Queue::assertPushed(SyncPokemonCatalog::class);
});

test('a sincronização semanal está registrada no agendador', function () {
    Artisan::call('schedule:list');

    $output = Artisan::output();

    expect($output)->toContain('pokemon:sync');
    expect($output)->toContain('0 3 * * 0');
});

test('as 18 traduções de tipo em pt-BR estão completas', function () {
    $canonicalTypes = [
        'normal', 'fire', 'water', 'electric', 'grass', 'ice', 'fighting',
        'poison', 'ground', 'flying', 'psychic', 'bug', 'rock', 'ghost',
        'dragon', 'dark', 'steel', 'fairy',
    ];

    $labels = config('pokemon.type_labels');

    expect($labels)->toHaveCount(18);
    expect(array_keys($labels))->toEqualCanonicalizing($canonicalTypes);

    foreach ($labels as $slug => $label) {
        expect($label)->toBeString()->not->toBeEmpty();
    }
});
