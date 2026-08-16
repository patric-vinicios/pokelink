<?php

use App\Services\PokeApi\PokeApiClient;
use App\Services\PokeApi\PokeApiStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redis;

function pokeApiTestClient(): PokeApiClient
{
    return new PokeApiClient;
}

function pokeApiLogPath(): string
{
    return storage_path('logs/pokeapi.log');
}

function pokeApiLogLines(string $level): int
{
    $contents = File::exists(pokeApiLogPath()) ? File::get(pokeApiLogPath()) : '';

    return substr_count($contents, ".{$level}:");
}

beforeEach(function () {
    Http::preventStrayRequests();
    Cache::store('redis')->flush();
    Cache::store('file')->forget('pokeapi:cache-degraded-logged');
    RateLimiter::clear(config('pokeapi.rate_limit.key'));
    File::ensureDirectoryExists(dirname(pokeApiLogPath()));
    File::put(pokeApiLogPath(), '');
});

afterEach(function () {
    try {
        Cache::store('redis')->flush();
    } catch (Throwable) {
        // A test may have deliberately broken the redis store mock — nothing to clean up.
    }
});

test('index retorna a listagem paginada e extrai o número nacional da URL', function () {
    Http::fake([
        config('pokeapi.base_uri').'/pokemon*' => Http::response([
            'count' => 2,
            'results' => [
                ['name' => 'bulbasaur', 'url' => 'https://pokeapi.co/api/v2/pokemon/1/'],
                ['name' => 'ivysaur', 'url' => 'https://pokeapi.co/api/v2/pokemon/2/'],
            ],
        ], 200),
    ]);

    $result = pokeApiTestClient()->index();

    expect($result->status)->toBe(PokeApiStatus::Success)
        ->and($result->data()['count'])->toBe(2)
        ->and($result->data()['entries'][0])->toBe([
            'number' => 1,
            'name' => 'bulbasaur',
            'url' => 'https://pokeapi.co/api/v2/pokemon/1/',
        ])
        ->and($result->data()['entries'][1]['number'])->toBe(2);
});

test('typeRoster retorna os membros de um tipo', function () {
    Http::fake([
        config('pokeapi.base_uri').'/type/fire*' => Http::response([
            'name' => 'fire',
            'pokemon' => [
                ['pokemon' => ['name' => 'charmander']],
                ['pokemon' => ['name' => 'vulpix']],
            ],
        ], 200),
    ]);

    $result = pokeApiTestClient()->typeRoster('fire');

    expect($result->successful())->toBeTrue()
        ->and($result->data())->toBe(['type' => 'fire', 'members' => ['charmander', 'vulpix']]);
});

test('pokemonDetail combina o payload base com o texto da espécie em pt-BR', function () {
    Http::fake([
        config('pokeapi.base_uri').'/pokemon/6*' => Http::response([
            'id' => 6,
            'name' => 'charizard',
            'types' => [
                ['slot' => 2, 'type' => ['name' => 'flying']],
                ['slot' => 1, 'type' => ['name' => 'fire']],
            ],
            'abilities' => [
                ['ability' => ['name' => 'blaze'], 'is_hidden' => false],
                ['ability' => ['name' => 'solar-power'], 'is_hidden' => true],
            ],
            'stats' => [
                ['stat' => ['name' => 'hp'], 'base_stat' => 78],
                ['stat' => ['name' => 'attack'], 'base_stat' => 84],
                ['stat' => ['name' => 'defense'], 'base_stat' => 78],
                ['stat' => ['name' => 'special-attack'], 'base_stat' => 109],
                ['stat' => ['name' => 'special-defense'], 'base_stat' => 85],
                ['stat' => ['name' => 'speed'], 'base_stat' => 100],
            ],
            'height' => 17,
            'weight' => 905,
        ], 200),
        config('pokeapi.base_uri').'/pokemon-species/6*' => Http::response([
            'flavor_text_entries' => [
                ['language' => ['name' => 'en'], 'flavor_text' => 'Charizard flies in search of strong opponents.'],
                ['language' => ['name' => 'pt-BR'], 'flavor_text' => "Charizard voa pelo céu\nem busca de oponentes fortes."],
            ],
        ], 200),
    ]);

    $result = pokeApiTestClient()->pokemonDetail(6);
    $data = $result->data();

    expect($result->successful())->toBeTrue()
        ->and($data['number'])->toBe(6)
        ->and($data['name'])->toBe('charizard')
        ->and($data['types'])->toBe(['fire', 'flying'])
        ->and($data['abilities'])->toBe([
            ['name' => 'blaze', 'hidden' => false],
            ['name' => 'solar-power', 'hidden' => true],
        ])
        ->and($data['stats'])->toBe([
            'hp' => 78,
            'attack' => 84,
            'defense' => 78,
            'special_attack' => 109,
            'special_defense' => 85,
            'speed' => 100,
        ])
        ->and($data['height_m'])->toBe(1.7)
        ->and($data['weight_kg'])->toBe(90.5)
        ->and($data['flavor_text'])->toBe('Charizard voa pelo céu em busca de oponentes fortes.')
        ->and($data['sprite_url'])->toBe('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/6.png');
});

test('pokemonDetail funciona sem texto de sabor quando a espécie não tem entrada pt-BR', function () {
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
        config('pokeapi.base_uri').'/pokemon-species/1*' => Http::response([
            'flavor_text_entries' => [
                ['language' => ['name' => 'en'], 'flavor_text' => 'A strange seed was planted on its back.'],
            ],
        ], 200),
    ]);

    $result = pokeApiTestClient()->pokemonDetail(1);

    expect($result->successful())->toBeTrue()
        ->and($result->data())->not->toHaveKey('flavor_text');
});

test('pokemonDetail não chama a espécie quando o payload base não é encontrado', function () {
    Http::fake([
        config('pokeapi.base_uri').'/pokemon/9999*' => Http::response(null, 404),
        config('pokeapi.base_uri').'/pokemon-species/9999*' => Http::response(['flavor_text_entries' => []], 200),
    ]);

    $result = pokeApiTestClient()->pokemonDetail(9999);

    expect($result->status)->toBe(PokeApiStatus::NotFound);
    Http::assertSentCount(1);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'pokemon-species'));
});

test('uma falha de conexão é tentada 3 vezes com o backoff configurado e depois reportada como indisponível', function () {
    $attempts = 0;

    // A thrown exception short-circuits the fake handler's promise chain before
    // Http::fake's own recorder runs, so assertSentCount can't see these — count
    // invocations directly instead.
    Http::fake(function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('Connection could not be established');
    });

    $result = pokeApiTestClient()->typeRoster('fire');

    expect($result->status)->toBe(PokeApiStatus::Unavailable)
        ->and($attempts)->toBe(3);
    expect(pokeApiLogLines('WARNING'))->toBe(3);
});

test('um 404 não é retentado', function () {
    Http::fake(['*' => Http::response(null, 404)]);

    $result = pokeApiTestClient()->typeRoster('inexistente');

    expect($result->status)->toBe(PokeApiStatus::NotFound);
    Http::assertSentCount(1);
});

test('um 500 é retentado e reportado indisponível após esgotar as tentativas', function () {
    Http::fake(['*' => Http::response(null, 500)]);

    $result = pokeApiTestClient()->typeRoster('fire');

    expect($result->status)->toBe(PokeApiStatus::Unavailable);
    Http::assertSentCount(3);
    expect(pokeApiLogLines('WARNING'))->toBe(3);
});

test('um 429 esgotado registra a tentativa final como erro', function () {
    Http::fake(['*' => Http::response(null, 429)]);

    $result = pokeApiTestClient()->typeRoster('fire');

    expect($result->status)->toBe(PokeApiStatus::Unavailable);
    Http::assertSentCount(3);
    expect(pokeApiLogLines('WARNING'))->toBe(2)
        ->and(pokeApiLogLines('ERROR'))->toBe(1);
});

test('uma resposta bem-sucedida é escrita no cache e a segunda chamada não faz requisição', function () {
    Http::fake(['*' => Http::response(['name' => 'fire', 'pokemon' => []], 200)]);

    $client = pokeApiTestClient();
    $first = $client->typeRoster('fire');
    $second = $client->typeRoster('fire');

    expect($first->successful())->toBeTrue()
        ->and($second->successful())->toBeTrue();
    Http::assertSentCount(1);

    $fullKey = Cache::store('redis')->getPrefix().'pokeapi:type:fire';
    $ttl = Redis::connection('cache')->ttl($fullKey);
    expect($ttl)->toBeGreaterThan(3600);
});

test('um resultado não encontrado é cacheado por 5 minutos, não 24 horas', function () {
    Http::fake(['*' => Http::response(null, 404)]);

    $client = pokeApiTestClient();
    $client->typeRoster('inexistente');
    $second = $client->typeRoster('inexistente');

    expect($second->status)->toBe(PokeApiStatus::NotFound);
    Http::assertSentCount(1);

    $fullKey = Cache::store('redis')->getPrefix().'pokeapi:type:inexistente';
    $ttl = Redis::connection('cache')->ttl($fullKey);
    expect($ttl)->toBeGreaterThan(0)->toBeLessThanOrEqual(300);
});

test('um corpo malformado é tratado como indisponível e nada é escrito no cache', function () {
    Http::fake(['*' => Http::response('isto não é json', 200)]);

    $result = pokeApiTestClient()->typeRoster('fire');

    expect($result->status)->toBe(PokeApiStatus::Unavailable);
    Http::assertSentCount(1);
    expect(Cache::store('redis')->has('pokeapi:type:fire'))->toBeFalse();
});

test('nenhuma tentativa ultrapassa o orçamento de tempo configurado', function () {
    expect(config('pokeapi.connect_timeout'))->toBe(5)
        ->and(config('pokeapi.timeout'))->toBe(10)
        ->and(config('pokeapi.retry.times'))->toBe(3)
        ->and(config('pokeapi.retry.backoff_ms'))->toBe([200, 400, 800]);
});

test('com Redis indisponível a requisição ainda é bem-sucedida e a falha de cache é logada no máximo uma vez por minuto', function () {
    $realFileStore = Cache::store('file');

    Cache::shouldReceive('store')
        ->with('redis')
        ->andThrow(new RuntimeException('Redis indisponível'));
    Cache::shouldReceive('store')
        ->with('file')
        ->andReturn($realFileStore);

    Http::fake(['*' => Http::sequence()
        ->push(['name' => 'fire', 'pokemon' => []], 200)
        ->push(['name' => 'water', 'pokemon' => []], 200)
        ->push(['name' => 'grass', 'pokemon' => []], 200),
    ]);

    $client = pokeApiTestClient();

    expect($client->typeRoster('fire')->successful())->toBeTrue()
        ->and($client->typeRoster('water')->successful())->toBeTrue()
        ->and($client->typeRoster('grass')->successful())->toBeTrue();

    Http::assertSentCount(3);
    expect(substr_count(File::get(pokeApiLogPath()), 'Cache do PokeAPI indisponível'))->toBe(1);
});

test('após atingir o limite de falhas consecutivas o circuito abre e a próxima chamada não tenta a rede', function () {
    Cache::store('redis')->put('pokeapi:circuit:failures', 4, 60);

    Http::fake(['*' => Http::response(null, 500)]);

    $client = pokeApiTestClient();
    $first = $client->typeRoster('fire');

    expect($first->status)->toBe(PokeApiStatus::Unavailable);
    Http::assertSentCount(3);
    expect(pokeApiLogLines('ERROR'))->toBe(1);

    $second = $client->typeRoster('water');

    expect($second->status)->toBe(PokeApiStatus::Unavailable);
    Http::assertSentCount(3);
});

test('o circuito fecha novamente após o fim do cooldown', function () {
    Cache::store('redis')->put('pokeapi:circuit:open', true, 1);
    sleep(2);

    Http::fake(['*' => Http::response(['name' => 'fire', 'pokemon' => []], 200)]);

    $result = pokeApiTestClient()->typeRoster('fire');

    expect($result->successful())->toBeTrue();
    Http::assertSentCount(1);
});

test('o rate limiter aguarda a janela quando a espera cabe no orçamento da requisição', function () {
    RateLimiter::shouldReceive('tooManyAttempts')->once()->andReturn(true);
    RateLimiter::shouldReceive('availableIn')->once()->andReturn(1);
    RateLimiter::shouldReceive('hit')->once();

    Http::fake(['*' => Http::response(['name' => 'fire', 'pokemon' => []], 200)]);

    $start = microtime(true);
    $result = pokeApiTestClient()->typeRoster('fire');
    $elapsed = microtime(true) - $start;

    expect($result->successful())->toBeTrue()
        ->and($elapsed)->toBeGreaterThanOrEqual(1.0);
    Http::assertSentCount(1);
});

test('o rate limiter reporta indisponível quando a espera excederia o orçamento', function () {
    RateLimiter::shouldReceive('tooManyAttempts')->once()->andReturn(true);
    RateLimiter::shouldReceive('availableIn')->once()->andReturn(11);

    Http::fake();

    $result = pokeApiTestClient()->typeRoster('fire');

    expect($result->status)->toBe(PokeApiStatus::Unavailable);
    Http::assertNothingSent();
});
