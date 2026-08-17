<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Page size
    |--------------------------------------------------------------------------
    */

    'per_page' => 20,

    /*
    |--------------------------------------------------------------------------
    | Navigation badge cap
    |--------------------------------------------------------------------------
    |
    | Any count above this renders as "{cap}+" instead of the exact number.
    |
    */

    'badge_cap' => 99,

    /*
    |--------------------------------------------------------------------------
    | Sort options
    |--------------------------------------------------------------------------
    |
    | Keys are the #[Url]-bound `sort` value; labels populate the /favoritos
    | sort <select>, in this same order.
    |
    */

    'sort_options' => [
        'recent' => 'Mais recentes',
        'name' => 'Nome (A-Z)',
        'number' => 'Número',
    ],

];
