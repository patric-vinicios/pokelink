<?php

use App\Jobs\SyncPokemonCatalog;
use App\Jobs\SyncPokemonCatalogStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// No RefreshDatabase: this suite (App\Services\PokeApi and friends) never
// touches the database, but still needs the full container for Http::fake(),
// Cache::, RateLimiter::, and Log::.
pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Whether the navigation link wrapping $label carries the active-state class
 * from resources/views/components/nav-link.blade.php (F04).
 */
function navLinkIsActive(string $html, string $label): bool
{
    return (bool) preg_match(
        '/<a[^>]*class="[^"]*border-indigo-400[^"]*"[^>]*>\s*'.preg_quote($label, '/').'\s*<\/a>/s',
        $html
    );
}

/*
|--------------------------------------------------------------------------
| F06 — PokeAPI catalog fixtures
|--------------------------------------------------------------------------
|
| Shared by tests/Feature/Jobs/SyncPokemonCatalogTest.php and
| tests/Feature/Console/PokemonSyncCommandTest.php. Defined here rather than
| in either file so both can rely on them regardless of test discovery order.
| Shapes mirror F05's real PokeApiClient::index()/typeRoster() request URLs
| and PokeAPI's actual response body, not an approximation.
|
*/

/**
 * @param  list<array{number: int, name: string}>  $entries
 * @return array{count: int, results: list<array{name: string, url: string}>}
 */
function pokeApiIndexBody(array $entries): array
{
    return [
        'count' => count($entries),
        'results' => collect($entries)
            ->map(fn (array $entry) => [
                'name' => $entry['name'],
                'url' => "https://pokeapi.co/api/v2/pokemon/{$entry['number']}/",
            ])
            ->all(),
    ];
}

/**
 * @param  list<string>  $members
 * @return array{name: string, pokemon: list<array{pokemon: array{name: string}}>}
 */
function pokeApiTypeBody(string $type, array $members = []): array
{
    return [
        'name' => $type,
        'pokemon' => collect($members)
            ->map(fn (string $name) => ['pokemon' => ['name' => $name]])
            ->all(),
    ];
}

/**
 * Fakes the index endpoint plus all 18 configured type rosters (empty by
 * default), overriding only the given type slugs with real members.
 *
 * @param  list<array{number: int, name: string}>  $entries
 * @param  array<string, list<string>>  $typeMembers
 */
function fakePokeApiCatalog(array $entries, array $typeMembers = []): void
{
    $fakes = [
        'https://pokeapi.co/api/v2/pokemon?*' => Http::response(pokeApiIndexBody($entries)),
    ];

    foreach (array_keys(config('pokemon.type_labels')) as $slug) {
        $fakes["https://pokeapi.co/api/v2/type/{$slug}*"] = Http::response(
            pokeApiTypeBody($slug, $typeMembers[$slug] ?? [])
        );
    }

    Http::fake($fakes);
}

/**
 * Runs SyncPokemonCatalog synchronously the same way SyncPokemonCommand does
 * — a direct container call to handle(), not ::dispatchSync() (which, for a
 * ShouldQueue job, still routes through the queue dispatcher and discards
 * both the real return value and thrown exceptions).
 */
function runPokemonSync(): SyncPokemonCatalogStats
{
    return app()->call([new SyncPokemonCatalog, 'handle']);
}

/**
 * PokeApiClient caches every resource in the `redis` store for 24h, keyed
 * only by resource+identifier — not per-test. Without resetting it, one
 * test's fixture silently answers the next test's Http::fake() setup via a
 * cache hit. Mirrors the beforeEach in tests/Unit/Services/PokeApiClientTest.php.
 */
function resetPokeApiState(): void
{
    Cache::store('redis')->flush();
    Cache::store('file')->forget('pokeapi:cache-degraded-logged');
    RateLimiter::clear(config('pokeapi.rate_limit.key'));
}

/*
|--------------------------------------------------------------------------
| F12 — real channel authorization in tests
|--------------------------------------------------------------------------
|
| BROADCAST_CONNECTION is forced to "null" in phpunit.xml, and the null
| broadcaster's auth() is a no-op that never runs a channel's authorization
| callback — every /broadcasting/auth request would otherwise succeed
| regardless of the policy. Switching the default connection alone is not
| enough either: routes/channels.php already ran once at boot against the
| (cached) null broadcaster instance, so its Broadcast::channel()
| registrations live there, not on a freshly resolved "reverb" instance.
| Re-requiring the routes file re-registers them against the new default.
|
*/
function useReverbBroadcaster(): void
{
    config(['broadcasting.default' => 'reverb']);
    require base_path('routes/channels.php');
}
