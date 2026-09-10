<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Paginator limit
    |--------------------------------------------------------------------------
    |
    | This limit is used by the response service to limit the results of
    | a query.
    |
    */
    'limit' => env('PAGINATOR_LIMIT', 12),

    /*
    |--------------------------------------------------------------------------
    | Paginator page
    |--------------------------------------------------------------------------
    |
    | This page is used by the response service combine with the limit to
    | display the results starting from a calculated offset.
    |
    */
    'page' => env('PAGINATOR_PAGE', 1)
];