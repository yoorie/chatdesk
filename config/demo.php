<?php

return [
    // Demo mode for public preview sites. Keep false for real installs.
    'enabled' => (bool) env('DEMO_MODE', false),

    // Credentials shown on the login page and recreated by `php artisan demo:reset`.
    // Use these only on a preview server. The same password is shared on purpose.
    'admin_email' => env('DEMO_ADMIN_EMAIL', 'admin@demo.chatdesk.test'),
    'admin_password' => env('DEMO_ADMIN_PASSWORD', 'demo1234'),
    'user_email' => env('DEMO_USER_EMAIL', 'owner@demo.chatdesk.test'),
    'user_password' => env('DEMO_USER_PASSWORD', 'demo1234'),
    'agent_email' => env('DEMO_AGENT_EMAIL', 'agent@demo.chatdesk.test'),
    'agent_password' => env('DEMO_AGENT_PASSWORD', 'demo1234'),

    // Cron expression for the automatic reset (needs the normal schedule:run cron).
    'reset_cron' => env('DEMO_RESET_CRON', '0 * * * *'),

    // Write requests still allowed in demo mode (route paths, fnmatch patterns).
    'allowed_writes' => ['login', 'logout', 'broadcasting/auth', 'two-factor-challenge', 'csrf-token', 'locale*', 'theme*', 'app/conversations/*/read'],

    'message' => 'Disabled in demo',
];
