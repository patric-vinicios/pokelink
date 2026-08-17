<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Message body limit
    |--------------------------------------------------------------------------
    |
    | Matches the `messages.body` column width (database/migrations/
    | 2026_08_17_000002_create_messages_table.php) so the validation rule and
    | the schema can never disagree.
    |
    */

    'max_message_length' => 2000,

    /*
    |--------------------------------------------------------------------------
    | Page sizes
    |--------------------------------------------------------------------------
    */

    'user_list_page_size' => 30,
    'history_page_size' => 30,

    /*
    |--------------------------------------------------------------------------
    | Unread badge cap
    |--------------------------------------------------------------------------
    |
    | Any count above this renders as "{cap}+" instead of the exact number.
    |
    */

    'unread_badge_cap' => 99,

];
