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

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Deliberately empty: this API is not meant to be called directly from a
    // browser on a third-party origin. Clients are server-to-server
    // integrations or mobile apps using a Sanctum Bearer token, not
    // browser-side JS running on an arbitrary origin. If a dedicated browser
    // frontend for OnTimePay is added later, its specific origin (e.g.
    // 'https://app.ontimepay.com') should be added here explicitly — never
    // revert to '*', since that would let any website make requests to this
    // API on behalf of a logged-in browser user.
    'allowed_origins' => [],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
