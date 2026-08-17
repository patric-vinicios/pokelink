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

test('the pokemon:sync command prints the created/updated summary', function () {
    fakePokeApiCatalog(entries: [
        ['number' => 1, 'name' => 'bulbasaur'],
        ['number' => 4, 'name' => 'charmander'],
    ]);

    Artisan::call('pokemon:sync');

    expect(Artisan::output())->toContain('2 Pokémon sincronizados (2 criados, 0 atualizados)');
});

test('running the command a second time creates no duplicates', function () {
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

test('the seeder dispatches the sync job on the default queue', function () {
    Queue::fake();

    $this->seed(DatabaseSeeder::class);

    Queue::assertPushed(SyncPokemonCatalog::class);
});

test('the weekly sync is registered on the scheduler', function () {
    Artisan::call('schedule:list');

    $output = Artisan::output();

    expect($output)->toContain('pokemon:sync');
    expect($output)->toContain('0 3 * * 0');
});

test('the 18 pt-BR type translations are complete', function () {
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
