<?php

namespace App\Services\PokeApi;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * The only class in the codebase allowed to reach pokeapi.co. Every call goes
 * through a Redis read-through cache, a bounded retry with backoff, an
 * application-wide outbound rate limit, and a circuit breaker — and returns
 * one of three explicit outcomes (PokeApiStatus) instead of throwing.
 */
class PokeApiClient
{
    private const CIRCUIT_FAILURES_KEY = 'pokeapi:circuit:failures';

    private const CIRCUIT_OPEN_KEY = 'pokeapi:circuit:open';

    private const CACHE_DEGRADED_LOG_KEY = 'pokeapi:cache-degraded-logged';

    /** @var array<string, int> */
    private const CACHE_SCHEMA_VERSIONS = [
        'pokemon' => 2,
        'pokemon-species' => 2,
    ];

    public function index(): PokeApiResult
    {
        return $this->fetch(
            resource: 'index',
            identifier: 'all',
            path: '/pokemon',
            query: ['limit' => 100000, 'offset' => 0],
            transform: fn (array $body): array => [
                'count' => $body['count'] ?? count($body['results'] ?? []),
                'entries' => collect($body['results'] ?? [])
                    ->map(fn (array $entry): array => [
                        'number' => $this->extractNumberFromUrl($entry['url'] ?? ''),
                        'name' => $entry['name'] ?? null,
                        'url' => $entry['url'] ?? null,
                    ])
                    ->all(),
            ],
        );
    }

    public function typeRoster(string $type): PokeApiResult
    {
        return $this->fetch(
            resource: 'type',
            identifier: $type,
            path: "/type/{$type}",
            transform: fn (array $body): array => [
                'type' => $body['name'] ?? $type,
                'members' => collect($body['pokemon'] ?? [])
                    ->map(fn (array $entry) => $entry['pokemon']['name'] ?? null)
                    ->filter()
                    ->values()
                    ->all(),
            ],
        );
    }

    public function pokemonDetail(int|string $identifier): PokeApiResult
    {
        $identifier = (string) $identifier;

        $base = $this->fetch(
            resource: 'pokemon',
            identifier: $identifier,
            path: "/pokemon/{$identifier}",
            transform: fn (array $body): array => $this->normalizePokemon($body),
        );

        if (! $base->successful()) {
            return $base;
        }

        $species = $this->fetch(
            resource: 'pokemon-species',
            identifier: $identifier,
            path: "/pokemon-species/{$identifier}",
            transform: fn (array $body): array => $this->normalizeSpecies($body),
        );

        $data = $base->data() ?? [];
        $speciesData = $species->successful() ? ($species->data() ?? []) : [];

        foreach (['flavor_text', 'genus'] as $field) {
            if (filled($speciesData[$field] ?? null)) {
                $data[$field] = $speciesData[$field];
            }
        }

        $evolutionChainNumber = $speciesData['evolution_chain_number'] ?? null;

        if (is_int($evolutionChainNumber)) {
            $evolution = $this->fetch(
                resource: 'evolution-chain',
                identifier: (string) $evolutionChainNumber,
                path: "/evolution-chain/{$evolutionChainNumber}",
                transform: fn (array $body): array => [
                    'evolutions' => $this->normalizeEvolutionChain($body['chain'] ?? []),
                ],
            );

            if ($evolution->successful()) {
                $data['evolutions'] = $evolution->data()['evolutions'] ?? [];
            }
        }

        return new PokeApiResult(PokeApiStatus::Success, $data, 'pokemon', $identifier);
    }

    /**
     * Serves a resource from cache when present, otherwise runs it through
     * the circuit breaker / rate limiter / retry pipeline and caches the
     * outcome. Never lets an exception escape.
     *
     * @param  (\Closure(array): array)|null  $transform
     */
    private function fetch(string $resource, string $identifier, string $path, array $query = [], ?\Closure $transform = null): PokeApiResult
    {
        $cacheVersion = self::CACHE_SCHEMA_VERSIONS[$resource] ?? null;
        $versionSegment = $cacheVersion !== null ? ":v{$cacheVersion}" : '';
        $cacheKey = "pokeapi:{$resource}{$versionSegment}:{$identifier}";

        $cached = $this->cacheGet($cacheKey);

        if ($cached !== null) {
            return PokeApiResult::fromArray($cached);
        }

        if ($this->circuitOpen()) {
            return new PokeApiResult(PokeApiStatus::Unavailable, null, $resource, $identifier);
        }

        return $this->performRequest($resource, $identifier, $path, $query, $transform, $cacheKey);
    }

    /**
     * @param  (\Closure(array): array)|null  $transform
     */
    private function performRequest(string $resource, string $identifier, string $path, array $query, ?\Closure $transform, string $cacheKey): PokeApiResult
    {
        $attemptsAllowed = (int) config('pokeapi.retry.times');
        $backoffs = config('pokeapi.retry.backoff_ms');
        $retryableStatuses = config('pokeapi.retry.retryable_statuses');
        $budgetSeconds = (int) config('pokeapi.timeout');

        for ($attempt = 1; $attempt <= $attemptsAllowed; $attempt++) {
            if (! $this->waitForRateLimitWindow($budgetSeconds)) {
                $this->logFailure($path, 'rate_limited', $attempt, 0);
                $this->registerFailure();

                return new PokeApiResult(PokeApiStatus::Unavailable, null, $resource, $identifier);
            }

            RateLimiter::hit(config('pokeapi.rate_limit.key'), (int) config('pokeapi.rate_limit.decay_seconds'));

            $start = microtime(true);

            try {
                $response = Http::baseUrl(config('pokeapi.base_uri'))
                    ->connectTimeout((int) config('pokeapi.connect_timeout'))
                    ->timeout($budgetSeconds)
                    ->get($path, $query);
            } catch (ConnectionException) {
                $elapsedMs = $this->elapsedMs($start);
                $this->logFailure($path, 'connection_error', $attempt, $elapsedMs);
                $this->registerFailure();

                if ($attempt < $attemptsAllowed) {
                    $this->sleepMs($backoffs[$attempt - 1] ?? (int) end($backoffs));

                    continue;
                }

                return new PokeApiResult(PokeApiStatus::Unavailable, null, $resource, $identifier);
            }

            $elapsedMs = $this->elapsedMs($start);

            if ($response->status() === 404) {
                $result = new PokeApiResult(PokeApiStatus::NotFound, null, $resource, $identifier);
                $this->cachePut($cacheKey, $result, now()->addMinutes((int) config('pokeapi.cache.not_found_ttl_minutes')));
                $this->registerSuccess();

                return $result;
            }

            if ($response->successful()) {
                $decoded = $response->json();

                if (! is_array($decoded)) {
                    $this->logMalformed($path, $attempt, (string) $response->body());
                    $this->registerFailure();

                    return new PokeApiResult(PokeApiStatus::Unavailable, null, $resource, $identifier);
                }

                $data = $transform ? $transform($decoded) : $decoded;
                $result = new PokeApiResult(PokeApiStatus::Success, $data, $resource, $identifier);
                $this->cachePut($cacheKey, $result, now()->addHours((int) config('pokeapi.cache.ttl_hours')));
                $this->registerSuccess();

                return $result;
            }

            $status = $response->status();
            $isLastAttempt = $attempt === $attemptsAllowed;
            $this->logFailure($path, (string) $status, $attempt, $elapsedMs, ($status === 429 && $isLastAttempt) ? 'error' : 'warning');
            $this->registerFailure();

            if (! in_array($status, $retryableStatuses, true)) {
                return new PokeApiResult(PokeApiStatus::Unavailable, null, $resource, $identifier);
            }

            if (! $isLastAttempt) {
                $this->sleepMs($backoffs[$attempt - 1] ?? (int) end($backoffs));

                continue;
            }

            return new PokeApiResult(PokeApiStatus::Unavailable, null, $resource, $identifier);
        }

        return new PokeApiResult(PokeApiStatus::Unavailable, null, $resource, $identifier);
    }

    /**
     * Waits out the rate-limit window when the wait fits inside the request's
     * time budget. Returns false when the client should give up without ever
     * reaching the network.
     */
    private function waitForRateLimitWindow(int $budgetSeconds): bool
    {
        $key = config('pokeapi.rate_limit.key');
        $maxAttempts = (int) config('pokeapi.rate_limit.max_attempts');

        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return true;
        }

        $availableIn = RateLimiter::availableIn($key);

        if ($availableIn > $budgetSeconds) {
            return false;
        }

        $this->sleepMs($availableIn * 1000);

        return true;
    }

    private function circuitOpen(): bool
    {
        try {
            return (bool) $this->cacheStore()->get(self::CIRCUIT_OPEN_KEY, false);
        } catch (Throwable $e) {
            $this->logCacheDegraded($e);

            return false;
        }
    }

    private function registerFailure(): void
    {
        try {
            $store = $this->cacheStore();
            $windowSeconds = (int) config('pokeapi.circuit_breaker.failure_window_seconds');
            $threshold = (int) config('pokeapi.circuit_breaker.failure_threshold');

            if (! $store->has(self::CIRCUIT_FAILURES_KEY)) {
                $store->put(self::CIRCUIT_FAILURES_KEY, 0, $windowSeconds);
            }

            $failures = $store->increment(self::CIRCUIT_FAILURES_KEY);

            if ($failures === $threshold) {
                $store->put(self::CIRCUIT_OPEN_KEY, true, (int) config('pokeapi.circuit_breaker.cooldown_seconds'));

                Log::channel(config('pokeapi.log_channel'))->error('Circuito do PokeAPI aberto após falhas consecutivas', [
                    'failures' => $failures,
                    'cooldown_seconds' => config('pokeapi.circuit_breaker.cooldown_seconds'),
                ]);
            }
        } catch (Throwable $e) {
            $this->logCacheDegraded($e);
        }
    }

    private function registerSuccess(): void
    {
        try {
            $this->cacheStore()->forget(self::CIRCUIT_FAILURES_KEY);
        } catch (Throwable $e) {
            $this->logCacheDegraded($e);
        }
    }

    private function cacheGet(string $key): ?array
    {
        try {
            return $this->cacheStore()->get($key);
        } catch (Throwable $e) {
            $this->logCacheDegraded($e);

            return null;
        }
    }

    private function cachePut(string $key, PokeApiResult $result, \DateTimeInterface $expiresAt): void
    {
        try {
            $this->cacheStore()->put($key, $result->toArray(), $expiresAt);
        } catch (Throwable $e) {
            $this->logCacheDegraded($e);
        }
    }

    private function cacheStore(): Repository
    {
        return Cache::store(config('pokeapi.cache.store'));
    }

    private function logCacheDegraded(Throwable $e): void
    {
        $throttleStore = Cache::store(config('pokeapi.cache_outage_log_store'));

        if ($throttleStore->has(self::CACHE_DEGRADED_LOG_KEY)) {
            return;
        }

        $throttleStore->put(self::CACHE_DEGRADED_LOG_KEY, true, (int) config('pokeapi.cache_outage_log_throttle_seconds'));

        Log::channel(config('pokeapi.log_channel'))->warning('Cache do PokeAPI indisponível — seguindo direto para o upstream', [
            'error' => $e->getMessage(),
        ]);
    }

    private function logFailure(string $endpoint, string $status, int $attempt, int $elapsedMs, string $level = 'warning'): void
    {
        Log::channel(config('pokeapi.log_channel'))->log($level, 'Falha ao consultar o PokeAPI', [
            'endpoint' => $endpoint,
            'status' => $status,
            'attempt' => $attempt,
            'elapsed_ms' => $elapsedMs,
        ]);
    }

    private function logMalformed(string $endpoint, int $attempt, string $body): void
    {
        Log::channel(config('pokeapi.log_channel'))->warning('Resposta malformada do PokeAPI', [
            'endpoint' => $endpoint,
            'attempt' => $attempt,
            'body_excerpt' => mb_substr($body, 0, 500),
        ]);
    }

    private function elapsedMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }

    private function sleepMs(int $ms): void
    {
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }

    private function extractNumberFromUrl(string $url): ?int
    {
        if (preg_match('#/(\d+)/?$#', rtrim($url, '/'), $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function normalizePokemon(array $body): array
    {
        $number = $body['id'] ?? null;

        return [
            'number' => $number,
            'name' => $body['name'] ?? null,
            'sprite_url' => $number !== null ? $this->officialArtworkUrl((int) $number) : null,
            'types' => collect($body['types'] ?? [])
                ->sortBy('slot')
                ->map(fn (array $entry) => $entry['type']['name'] ?? null)
                ->filter()
                ->values()
                ->all(),
            'abilities' => collect($body['abilities'] ?? [])
                ->map(fn (array $entry): array => [
                    'name' => $entry['ability']['name'] ?? null,
                    'hidden' => (bool) ($entry['is_hidden'] ?? false),
                ])
                ->all(),
            'stats' => $this->normalizeStats($body['stats'] ?? []),
            'height_m' => isset($body['height']) ? round($body['height'] / 10, 1) : null,
            'weight_kg' => isset($body['weight']) ? round($body['weight'] / 10, 1) : null,
            'base_experience' => isset($body['base_experience']) ? (int) $body['base_experience'] : null,
            'moves' => collect($body['moves'] ?? [])
                ->map(fn (array $entry) => $entry['move']['name'] ?? null)
                ->filter()
                ->take(24)
                ->values()
                ->all(),
        ];
    }

    private function normalizeStats(array $stats): array
    {
        $map = [
            'hp' => 'hp',
            'attack' => 'attack',
            'defense' => 'defense',
            'special-attack' => 'special_attack',
            'special-defense' => 'special_defense',
            'speed' => 'speed',
        ];

        $normalized = [];

        foreach ($stats as $entry) {
            $name = $entry['stat']['name'] ?? null;

            if ($name !== null && isset($map[$name])) {
                $normalized[$map[$name]] = $entry['base_stat'] ?? null;
            }
        }

        return $normalized;
    }

    private function normalizeSpecies(array $body): array
    {
        return [
            'flavor_text' => $this->extractLocalizedValue($body['flavor_text_entries'] ?? [], 'flavor_text'),
            'genus' => $this->extractLocalizedValue($body['genera'] ?? [], 'genus'),
            'evolution_chain_number' => $this->extractNumberFromUrl($body['evolution_chain']['url'] ?? ''),
        ];
    }

    private function extractLocalizedValue(array $entries, string $field): ?string
    {
        foreach (['pt-BR', 'en'] as $preferredLanguage) {
            foreach ($entries as $entry) {
                $language = $entry['language']['name'] ?? null;

                if ($language === $preferredLanguage && filled($entry[$field] ?? null)) {
                    return trim(preg_replace('/\s+/', ' ', (string) $entry[$field]));
                }
            }
        }

        return null;
    }

    /**
     * @return list<array{number: int|null, name: string, min_level: int|null, trigger: string|null, sprite_url: string|null}>
     */
    private function normalizeEvolutionChain(array $root): array
    {
        $stages = [];

        $walk = function (array $node, ?array $evolutionDetail = null) use (&$stages, &$walk): void {
            $number = $this->extractNumberFromUrl($node['species']['url'] ?? '');

            $stages[] = [
                'number' => $number,
                'name' => (string) ($node['species']['name'] ?? ''),
                'min_level' => isset($evolutionDetail['min_level']) ? (int) $evolutionDetail['min_level'] : null,
                'trigger' => $evolutionDetail['trigger']['name'] ?? null,
                'sprite_url' => $number !== null ? $this->officialArtworkUrl($number) : null,
            ];

            foreach ($node['evolves_to'] ?? [] as $next) {
                $walk($next, $next['evolution_details'][0] ?? null);
            }
        };

        if ($root !== []) {
            $walk($root);
        }

        return array_values(array_filter($stages, fn (array $stage): bool => $stage['name'] !== ''));
    }

    private function officialArtworkUrl(int $number): string
    {
        // jsdelivr mirrors the same PokeAPI/sprites GitHub repo through an
        // actual CDN. raw.githubusercontent.com serves the identical files
        // but isn't meant for hotlinked traffic at this volume and
        // rate-limits hard (503 "too many requests") once a page renders a
        // full grid of sprites.
        return "https://cdn.jsdelivr.net/gh/PokeAPI/sprites@master/sprites/pokemon/other/official-artwork/{$number}.png";
    }
}
