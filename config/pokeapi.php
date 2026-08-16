<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base URI
    |--------------------------------------------------------------------------
    |
    | The only environment-configurable value in this file. Every other
    | tunable below is a fixed default because F05's acceptance criteria
    | (PRD Section 9) name exact numbers — an env override could silently
    | break one of them.
    |
    */

    'base_uri' => env('POKEAPI_BASE_URI', 'https://pokeapi.co/api/v2'),

    /*
    |--------------------------------------------------------------------------
    | Timeouts (seconds)
    |--------------------------------------------------------------------------
    */

    'connect_timeout' => 5,
    'timeout' => 10,

    /*
    |--------------------------------------------------------------------------
    | Retry
    |--------------------------------------------------------------------------
    |
    | Up to 3 attempts on connection errors and on HTTP 429/500/502/503/504,
    | with backoff of 200ms, 400ms, 800ms between attempts. HTTP 404 is never
    | retried.
    |
    */

    'retry' => [
        'times' => 3,
        'backoff_ms' => [200, 400, 800],
        'retryable_statuses' => [429, 500, 502, 503, 504],
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbound rate limiting
    |--------------------------------------------------------------------------
    |
    | Shared across every method and every caller — the 60/min budget is
    | application-wide, not per-resource.
    |
    */

    'rate_limit' => [
        'key' => 'pokeapi-outbound',
        'max_attempts' => 60,
        'decay_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Response cache
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'store' => 'redis',
        'ttl_hours' => 24,
        'not_found_ttl_minutes' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache-outage log throttle
    |--------------------------------------------------------------------------
    |
    | Deliberately a different store than the response cache above: the whole
    | point of this throttle is to survive the response cache's store being
    | down, so it cannot itself live there.
    |
    */

    'cache_outage_log_store' => 'file',
    'cache_outage_log_throttle_seconds' => 60,

    /*
    |--------------------------------------------------------------------------
    | Circuit breaker
    |--------------------------------------------------------------------------
    |
    | After this many consecutive terminal failures within the failure
    | window, the client short-circuits for the cooldown period and returns
    | Unavailable immediately, without spending retry or rate-limit budget.
    |
    */

    'circuit_breaker' => [
        'failure_threshold' => 5,
        'failure_window_seconds' => 60,
        'cooldown_seconds' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */

    'log_channel' => 'pokeapi',

];
