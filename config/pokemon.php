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
    | Defensive type matchups
    |--------------------------------------------------------------------------
    |
    | Type effectiveness is a fixed part of the battle rules, just like the
    | translated type labels above. Keeping the compact defensive chart here
    | avoids one extra upstream request per Pokémon type whenever a modal is
    | opened. Dual-type multipliers are calculated at render time.
    |
    */

    'type_matchups' => [
        'normal' => ['weak_to' => ['fighting'], 'resists' => [], 'immune_to' => ['ghost']],
        'fire' => ['weak_to' => ['water', 'ground', 'rock'], 'resists' => ['fire', 'grass', 'ice', 'bug', 'steel', 'fairy'], 'immune_to' => []],
        'water' => ['weak_to' => ['electric', 'grass'], 'resists' => ['fire', 'water', 'ice', 'steel'], 'immune_to' => []],
        'electric' => ['weak_to' => ['ground'], 'resists' => ['electric', 'flying', 'steel'], 'immune_to' => []],
        'grass' => ['weak_to' => ['fire', 'ice', 'poison', 'flying', 'bug'], 'resists' => ['water', 'electric', 'grass', 'ground'], 'immune_to' => []],
        'ice' => ['weak_to' => ['fire', 'fighting', 'rock', 'steel'], 'resists' => ['ice'], 'immune_to' => []],
        'fighting' => ['weak_to' => ['flying', 'psychic', 'fairy'], 'resists' => ['bug', 'rock', 'dark'], 'immune_to' => []],
        'poison' => ['weak_to' => ['ground', 'psychic'], 'resists' => ['grass', 'fighting', 'poison', 'bug', 'fairy'], 'immune_to' => []],
        'ground' => ['weak_to' => ['water', 'grass', 'ice'], 'resists' => ['poison', 'rock'], 'immune_to' => ['electric']],
        'flying' => ['weak_to' => ['electric', 'ice', 'rock'], 'resists' => ['grass', 'fighting', 'bug'], 'immune_to' => ['ground']],
        'psychic' => ['weak_to' => ['bug', 'ghost', 'dark'], 'resists' => ['fighting', 'psychic'], 'immune_to' => []],
        'bug' => ['weak_to' => ['fire', 'flying', 'rock'], 'resists' => ['grass', 'fighting', 'ground'], 'immune_to' => []],
        'rock' => ['weak_to' => ['water', 'grass', 'fighting', 'ground', 'steel'], 'resists' => ['normal', 'fire', 'poison', 'flying'], 'immune_to' => []],
        'ghost' => ['weak_to' => ['ghost', 'dark'], 'resists' => ['poison', 'bug'], 'immune_to' => ['normal', 'fighting']],
        'dragon' => ['weak_to' => ['ice', 'dragon', 'fairy'], 'resists' => ['fire', 'water', 'electric', 'grass'], 'immune_to' => []],
        'dark' => ['weak_to' => ['fighting', 'bug', 'fairy'], 'resists' => ['ghost', 'dark'], 'immune_to' => ['psychic']],
        'steel' => ['weak_to' => ['fire', 'fighting', 'ground'], 'resists' => ['normal', 'grass', 'ice', 'flying', 'psychic', 'bug', 'rock', 'dragon', 'steel', 'fairy'], 'immune_to' => ['poison']],
        'fairy' => ['weak_to' => ['poison', 'steel'], 'resists' => ['fighting', 'bug', 'dark'], 'immune_to' => ['dragon']],
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
