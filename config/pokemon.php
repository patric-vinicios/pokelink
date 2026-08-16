<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Type labels
    |--------------------------------------------------------------------------
    |
    | The 18 canonical PokeAPI type slugs mapped to their pt-BR label. F05's
    | typeRoster() only returns the English slug and member list — not a
    | localized name — and fetching a 19th call per type to get one would
    | break F06's exact-19-upstream-calls acceptance criterion. Pokémon's
    | types are a fixed, rarely-changing enumeration, so a static map is the
    | only source that fits inside the call budget. Also the iteration order
    | for the sync job's 18 typeRoster() calls.
    |
    */

    'type_labels' => [
        'normal' => 'normal',
        'fire' => 'fogo',
        'water' => 'água',
        'electric' => 'elétrico',
        'grass' => 'planta',
        'ice' => 'gelo',
        'fighting' => 'lutador',
        'poison' => 'venenoso',
        'ground' => 'terrestre',
        'flying' => 'voador',
        'psychic' => 'psíquico',
        'bug' => 'inseto',
        'rock' => 'pedra',
        'ghost' => 'fantasma',
        'dragon' => 'dragão',
        'dark' => 'sombrio',
        'steel' => 'aço',
        'fairy' => 'fada',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync job tuning
    |--------------------------------------------------------------------------
    */

    'sync' => [
        'batch_size' => 500,
        'tries' => 3,
        'backoff_seconds' => [10, 30, 60],
        'timeout_seconds' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Weekly refresh schedule
    |--------------------------------------------------------------------------
    |
    | Registered in routes/console.php. Firing it automatically requires an
    | external cron or `schedule:work` process — F01's docker-compose stack
    | does not provision one; `php artisan pokemon:sync` is the documented
    | manual fallback. See docs/F06-pokemon-catalog-sync/spec.md.
    |
    */

    'schedule' => [
        'day' => 0, // Sunday
        'time' => '03:00',
    ],

];
