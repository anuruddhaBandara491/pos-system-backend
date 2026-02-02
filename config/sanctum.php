<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from these domains / hosts will receive stateful API authentication
    | cookies. For a typical API-only backend used by desktop Electron clients,
    | leave this empty or configure your frontend domains as needed.
    |
    */
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,127.0.0.1,app://electron')),

    /*
    |--------------------------------------------------------------------------
    | Expiration
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. For desktop clients, tokens can have longer expiry.
    | Set to null for no expiration, or an integer for minutes.
    |
    */
    'expiration' => env('SANCTUM_EXPIRATION', 43200), // 30 days for desktop

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware are used by Sanctum to authenticate and protect stateful
    | requests. For an API-only backend, this can be left empty or you may add
    | custom middleware as needed.
    |
    */
    'middleware' => [
        // For API-only backend, typically no CSRF or cookie middleware needed.
        // Add custom middleware here if required.
    ],
];

