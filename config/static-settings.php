<?php

return [
    // File reads avoid a database cache lookup. Use a shared Redis store on multiple nodes.
    'store' => env('STATIC_SETTINGS_CACHE_STORE', 'file'),
];
