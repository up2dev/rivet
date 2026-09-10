<?php

return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | This user model.
    |
    */
    'is_logged' => env('IS_LOGGED', false),

    /*
    |--------------------------------------------------------------------------
    | SQL duration reporting
    |--------------------------------------------------------------------------
    |
    | When true, enables Laravel's query log so ResponseService can
    | report 'sql_duration' in every response's metadata. Off by
    | default: this accumulates every query in memory for the request's
    | lifetime, which is unwanted overhead in production and unsafe
    | under Laravel Octane/Swoole. Turn on for local debugging.
    |
    */
    'sql_duration' => env('LOGS_SQL_DURATION', false),
];
