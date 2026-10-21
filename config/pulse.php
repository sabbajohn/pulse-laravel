<?php

return [
    'enabled' => env('PULSE_ENABLED', true),

    'base_url' => env('PULSE_BASE_URL', 'https://pulse.seu-dominio.com'),

    'api_token' => env('PULSE_API_TOKEN'),

    'timeout' => (int) env('PULSE_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Dynamic credentials resolver
    |--------------------------------------------------------------------------
    |
    | For multi-tenant apps, set this to an invokable class, Class@method, or
    | callable that returns ['base_url' => ..., 'api_token' => ..., 'timeout' => ...].
    | Returning null falls back to the static config above.
    |
    */
    'credentials_resolver' => null,

    'options' => [],

    'provisioning' => [
        'enabled' => (bool) env('PULSE_PROVISIONING_ENABLED', false),
        'integration' => env('PULSE_PROVISIONING_INTEGRATION', 'default'),
        'base_url' => env('PULSE_PROVISIONING_BASE_URL', env('PULSE_BASE_URL')),
        'partner_token' => env('PULSE_PARTNER_TOKEN'),
        'timeout' => (int) env('PULSE_PROVISIONING_TIMEOUT', 15),
    ],

    'route_prefix' => env('PULSE_ROUTE_PREFIX', 'pulse'),

    'middleware' => array_values(array_filter(explode(',', env('PULSE_MIDDLEWARE', 'web,auth')))),

    'allowed_proxy_prefixes' => [
        'health',
        'stats',
        'emails',
        'composer',
        'templates',
        'campaigns',
        'contacts',
        'audiences',
        'audience',
        'automations',
        'calendar',
        'whatsapp',
        'smtp/test',
        'websocket/info',
    ],
];
