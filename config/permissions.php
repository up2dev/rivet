<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Additionnal
    |--------------------------------------------------------------------------
    |
    | This implement additional permissions to create
    |
    */
    'additional' => [],

    /*
    |--------------------------------------------------------------------------
    | Route exceptions
    |--------------------------------------------------------------------------
    |
    | This implement the routes to ignore in permissions
    |
    */
    'route_exceptions' => [
        'auth:*',
        'auth/login:*',
        'auth/refresh:*',
        'auth/logout:*',
        'auth/user/email/{token}:*',
        'auth/user/login:*',
        'auth/pwd/forgot:*',
        'auth/pwd/renew:*',
        'auth/pwd/{token}:*'
    ]
];
