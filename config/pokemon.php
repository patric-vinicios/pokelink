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
    | Type badge colors
    |--------------------------------------------------------------------------
    |
    | Maps each of the 18 type slugs above to one of the 8 colors
    | resources/views/components/badge.blade.php (F04) already supports.
    | <x-badge> is a shared primitive other features also use, so this stays
    | a domain-specific map here rather than growing badge's own color list
    | (F08). Some thematically distant types intentionally share a color —
    | pedra, sombrio, aço, and normal all render gray.
    |
    */

    'type_colors' => [
        'normal' => 'gray',
        'fire' => 'red',
        'water' => 'blue',
        'electric' => 'yellow',
        'grass' => 'green',
        'ice' => 'blue',
        'fighting' => 'red',
        'poison' => 'purple',
        'ground' => 'yellow',
        'flying' => 'indigo',
        'psychic' => 'pink',
        'bug' => 'green',
        'rock' => 'gray',
        'ghost' => 'purple',
        'dragon' => 'indigo',
        'dark' => 'gray',
        'steel' => 'gray',
        'fairy' => 'pink',
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

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    |
    | Shared between F07 (search screen) and F08 (results grid, next wave) so
    | both render the same page size against the same query.
    |
    */

    'search' => [
        'per_page' => 20,
    ],

];
