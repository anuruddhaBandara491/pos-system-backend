<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'health', 'version'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:3000'),
        'http://localhost:8080',     // Electron development
        'http://127.0.0.1:8080',     // Electron development (127.0.0.1)
        'file://*',                  // Electron file protocol (if using preload)
        // Production Electron origins (uncomment when deploying)
        // 'app://electron',
        // 'app://localhost',
    ],

    'allowed_origins_patterns' => [
        '#^https://localhost:\d+$#',  // HTTPS localhost any port
        '#^http://localhost:\d+$#',   // HTTP localhost any port
        '#^app://.*#',                // Electron protocol
    ],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'X-CSRF-TOKEN'],

    'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-Version'],

    'max_age' => 0,

    'supports_credentials' => true,

];
