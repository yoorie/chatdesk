<?php

return [
    // Shared hosting has no Node.js process, so server-side rendering is off by
    // default. On a VPS you may run `php artisan inertia:start-ssr` and set true.
    'ssr' => [
        'enabled' => (bool) env('INERTIA_SSR_ENABLED', false),
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),
    ],
];
