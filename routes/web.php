<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Shared hosting without symlink support: serve public uploads from
// storage/app/public when public/storage does not exist.
Route::get('/storage/{path}', function (string $path) {
    $root = realpath(storage_path('app/public'));
    $file = realpath($root.DIRECTORY_SEPARATOR.$path);
    abort_unless($root && $file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file), 404);

    return response()->file($file, ['Cache-Control' => 'public, max-age=604800']);
})->where('path', '.*')->name('storage.fallback');

// Landing page
Route::get('/', fn () => redirect()->to(url(auth()->check() ? '/app/dashboard' : '/login')))->name('landing');

Route::prefix('/api/v1/whatsapp')
    ->middleware(['public-api.enabled', 'public-api.auth', 'log.api', 'throttle:60,1'])
    ->name('api.whatsapp.')
    ->group(function () {
        Route::get('/connections', [\App\Http\Controllers\PublicApi\WhatsAppController::class, 'connections'])->name('connections');
        Route::get('/templates', [\App\Http\Controllers\PublicApi\WhatsAppController::class, 'templates'])->name('templates');
        Route::post('/templates/sync', [\App\Http\Controllers\PublicApi\WhatsAppController::class, 'syncTemplates'])->name('templates.sync');
        Route::get('/conversations', [\App\Http\Controllers\PublicApi\WhatsAppController::class, 'conversations'])->name('conversations');
        Route::post('/messages/text', [\App\Http\Controllers\PublicApi\WhatsAppController::class, 'sendText'])->name('messages.text');
        Route::post('/messages/template', [\App\Http\Controllers\PublicApi\WhatsAppController::class, 'sendTemplate'])->name('messages.template');
        Route::post('/messages/media', [\App\Http\Controllers\PublicApi\WhatsAppController::class, 'sendMedia'])->name('messages.media');
        Route::post('/messages/location', [\App\Http\Controllers\PublicApi\WhatsAppController::class, 'sendLocation'])->name('messages.location');
        Route::post('/messages/list', [\App\Http\Controllers\PublicApi\WhatsAppController::class, 'sendList'])->name('messages.list');
        Route::post('/messages/buttons', [\App\Http\Controllers\PublicApi\WhatsAppController::class, 'sendButtons'])->name('messages.buttons');
    });

Route::post('/webhooks/whatsapp/flows/{flow?}/data', [\App\Modules\WhatsApp\Http\Controllers\FlowDataController::class, 'receive'])
    ->middleware(['throttle:120,1', \App\Http\Middleware\LogApiRequests::class])
    ->name('webhooks.whatsapp.flows.data');

// Auth routes
require __DIR__.'/auth.php';

// Dashboard shortcut retained for auth and verification redirects.
Route::middleware(['auth'])->get('/dashboard', function (\Illuminate\Http\Request $request) {
    $user = $request->user();

    $hasAccounts = $user
        ? app(\App\Support\CurrentAccountResolver::class)->hasAnyAccount($user)
        : false;

    return $hasAccounts
        ? redirect()->route('app.dashboard')
        : abort(403, 'Your user is not a member of this organisation. Ask an admin to invite you.');
})->name('dashboard');

// Short, guessable URLs customers (and support docs) use, redirected to the real pages.
// Guests are sent to login first and land on the page afterwards.
Route::middleware(['auth'])->group(function () {
    foreach ([
        '/inbox' => '/app/conversations',
        '/chats' => '/app/conversations',
        '/contacts' => '/app/contacts',
        '/campaigns' => '/app/broadcasts',
        '/broadcasts' => '/app/broadcasts',
        '/templates' => '/app/templates',
        '/settings' => '/app/settings',
        '/team' => '/app/team',
        '/whatsapp' => '/app/connections',
        '/app' => '/app/dashboard',
    ] as $from => $to) {
        Route::get($from, fn () => redirect()->to(url($to)));
    }
});

// CSRF token refresh endpoint (no auth required, but session must be valid)
// This allows refreshing CSRF tokens even when user is not authenticated
Route::get('/csrf-token/refresh', [\App\Http\Controllers\CsrfTokenController::class, 'refresh'])->name('csrf-token.refresh');
Route::get('/cron/run', \App\Http\Controllers\CronRunController::class)
    ->name('cron.run');

// App routes (requires auth + account + account active + account subscribed)
// restrict.chat.agent: chat agents (role member) only see Inbox; others have full access
Route::middleware(['auth', 'account.resolve', 'account.active', 'account.subscribed', 'restrict.chat.agent'])->prefix('/app')->name('app.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Human-friendly aliases that match sidebar labels and support docs.
    // url() keeps the subfolder. Route::redirect() strips it and sends people to the domain root.
    Route::get('/campaigns', fn () => redirect()->to(url('/app/broadcasts')));
    Route::get('/waba-account', fn () => redirect()->to(url('/app/connections')));
    Route::get('/home', fn () => redirect()->to(url('/app/dashboard')));
    Route::get('/inbox', fn () => redirect()->to(url('/app/conversations')));
    Route::get('/chats', fn () => redirect()->to(url('/app/conversations')));
    Route::get('/whatsapp', fn () => redirect()->to(url('/app/connections')));
    Route::get('/whatsapp-account', fn () => redirect()->to(url('/app/connections')));
    Route::get('/connect-whatsapp', fn () => redirect()->to(url('/app/connections')));
    Route::get('/segments', fn () => redirect()->to(url('/app/contacts/segments')));
    Route::get('/tags', fn () => redirect()->to(url('/app/contacts/tags')));
    Route::get('/api', fn () => redirect()->to(url('/app/developer')));
    Route::get('/settings/team', fn () => redirect()->to(url('/app/team')));
    Route::get('/settings/whatsapp', fn () => redirect()->to(url('/app/connections')));
    Route::get('/settings/developer', fn () => redirect()->to(url('/app/developer')));
    Route::get('/settings/api', fn () => redirect()->to(url('/app/developer')));
    Route::get('/settings/activity', fn () => redirect()->to(url('/app/activity-logs')));
    foreach (['workspace', 'profile', 'security', 'notifications', 'inbox'] as $settingsTab) {
        Route::get('/settings/'.$settingsTab, fn () => redirect()->to(url('/app/settings?tab='.$settingsTab)));
    }

    // Tools
    Route::get('/developer', [\App\Http\Controllers\DeveloperController::class, 'index'])->name('developer.index');
    Route::post('/developer/api-keys', [\App\Http\Controllers\DeveloperController::class, 'storeKey'])->name('developer.keys.store');
    Route::delete('/developer/api-keys/{key}', [\App\Http\Controllers\DeveloperController::class, 'destroyKey'])->name('developer.keys.destroy');
    Route::post('/developer/webhooks', [\App\Http\Controllers\DeveloperController::class, 'storeWebhook'])->name('developer.webhooks.store');
    Route::patch('/developer/webhooks/{endpoint}', [\App\Http\Controllers\DeveloperController::class, 'updateWebhook'])->name('developer.webhooks.update');
    Route::post('/developer/webhooks/{endpoint}/test', [\App\Http\Controllers\DeveloperController::class, 'testWebhook'])->name('developer.webhooks.test');
    Route::delete('/developer/webhooks/{endpoint}', [\App\Http\Controllers\DeveloperController::class, 'destroyWebhook'])->name('developer.webhooks.destroy');
    Route::get('/search', \App\Http\Controllers\GlobalSearchController::class)->name('search');
    Route::get('/notifications', [\App\Http\Controllers\NotificationCenterController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationCenterController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\NotificationCenterController::class, 'markRead'])->name('notifications.read');

    Route::get('/quick-replies', [\App\Http\Controllers\QuickReplyController::class, 'index'])->name('quick-replies.index');
    Route::post('/quick-replies', [\App\Http\Controllers\QuickReplyController::class, 'store'])->name('quick-replies.store');
    Route::patch('/quick-replies/{quickReply}', [\App\Http\Controllers\QuickReplyController::class, 'update'])->name('quick-replies.update');
    Route::post('/quick-replies/{quickReply}/toggle', [\App\Http\Controllers\QuickReplyController::class, 'toggle'])->name('quick-replies.toggle');
    Route::delete('/quick-replies/{quickReply}', [\App\Http\Controllers\QuickReplyController::class, 'destroy'])->name('quick-replies.destroy');

    // Load module routes (WhatsApp, Chatbots, etc.)
    if (file_exists(__DIR__.'/../app/Modules/WhatsApp/routes/web.php')) {
        require __DIR__.'/../app/Modules/WhatsApp/routes/web.php';
    }
    if (file_exists(__DIR__.'/../app/Modules/Broadcasts/routes/web.php')) {
        require __DIR__.'/../app/Modules/Broadcasts/routes/web.php';
    }
    if (file_exists(__DIR__.'/../app/Modules/Contacts/routes/web.php')) {
        require __DIR__.'/../app/Modules/Contacts/routes/web.php';
    }

    if (file_exists(__DIR__.'/../app/Modules/Chatbots/routes/web.php')) {
        require __DIR__.'/../app/Modules/Chatbots/routes/web.php';
    }

    // Team management
    Route::prefix('/team')->name('team.')->group(function () {
        Route::get('/', [\App\Http\Controllers\TeamController::class, 'index'])->name('index');
        Route::post('/invite', [\App\Http\Controllers\TeamController::class, 'invite'])->middleware('throttle:10,1')->name('invite');
        Route::post('/roles', [\App\Http\Controllers\TeamController::class, 'storeRole'])->name('roles.store');
        Route::patch('/roles/{role}', [\App\Http\Controllers\TeamController::class, 'updateRoleDefinition'])->name('roles.update');
        Route::delete('/roles/{role}', [\App\Http\Controllers\TeamController::class, 'deleteRole'])->name('roles.delete');
        Route::post('/invites/{invitation}/resend', [\App\Http\Controllers\TeamController::class, 'resendInvite'])->middleware('throttle:10,1')->name('invites.resend');
        Route::delete('/invites/{invitation}', [\App\Http\Controllers\TeamController::class, 'revokeInvite'])->name('invites.revoke');
        Route::post('/{user}/update-role', [\App\Http\Controllers\TeamController::class, 'updateRole'])->name('update-role');
        Route::delete('/{user}/remove', [\App\Http\Controllers\TeamController::class, 'remove'])->name('remove');
    });

    // Activity Logs
    Route::get('/activity-logs', [\App\Http\Controllers\ActivityLogController::class, 'index'])->name('activity-logs');
});

// Profile routes (password.update is defined in auth.php)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Broadcasting auth - custom route to ensure channels are loaded
Route::middleware(['web', 'auth'])->post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
    $user = $request->user();
    $channelName = $request->input('channel_name');

    if (! $user) {
        return response()->json(['error' => 'Unauthenticated'], 403);
    }

    // Ensure channels are loaded - Broadcast::channel() registers them on the default broadcaster
    // The channels file should be loaded via withBroadcasting() or we need to load it here
    // Since we disabled withBroadcasting(), we need to load channels manually
    static $channelsLoaded = false;
    if (! $channelsLoaded) {
        require __DIR__.'/../routes/channels.php';
        $channelsLoaded = true;
    }

    // Get the broadcaster instance - channels are registered on the default connection
    // Broadcast::channel() registers channels on the broadcaster instance returned by connection()
    $broadcastManager = app('Illuminate\Broadcasting\BroadcastManager');
    $broadcaster = $broadcastManager->connection();

    // Get channels from the broadcaster instance (channels are stored in the broadcaster, not the manager)
    $channels = $broadcaster->getChannels();
    $normalizedChannel = method_exists($broadcaster, 'normalizeChannelName')
        ? $broadcaster->normalizeChannelName($channelName)
        : str_replace('private-', '', $channelName);

    \Log::debug('Broadcast auth attempt', [
        'user_id' => $user->id,
        'channel' => $channelName,
        'normalized_channel' => $normalizedChannel,
        'channels_count' => $channels->count(),
        'channel_patterns' => $channels->keys()->toArray(),
    ]);

    $hasAccountAccess = static function (int|string $accountId) use ($user): bool {
        $account = \App\Models\Account::find($accountId);

        if (! $account) {
            return false;
        }

        return $user->canAccessAccount($account);
    };

    $fallbackAuth = static function () use ($request, $user, $broadcaster, $normalizedChannel, $hasAccountAccess) {
        if (preg_match('/^App\.Models\.User\.(\d+)$/', $normalizedChannel, $matches)) {
            return (int) $matches[1] === (int) $user->id
                ? $broadcaster->validAuthenticationResponse($request, true)
                : null;
        }

        if (preg_match('/^account\.(\d+)\.whatsapp\.inbox$/', $normalizedChannel, $matches)) {
            return $hasAccountAccess($matches[1])
                ? $broadcaster->validAuthenticationResponse($request, true)
                : null;
        }

        if (preg_match('/^account\.(\d+)\.whatsapp\.conversation\.(\d+)$/', $normalizedChannel, $matches)) {
            $conversation = \App\Modules\WhatsApp\Models\WhatsAppConversation::find($matches[2]);
            $allowed = $conversation
                && account_ids_match($conversation->account_id, $matches[1])
                && $hasAccountAccess($matches[1]);

            return $allowed
                ? $broadcaster->validAuthenticationResponse($request, true)
                : null;
        }

        return null;
    };

    // Prefer Laravel's normal channel authorization. The fallback covers the
    // same registered private channels when route caching or broadcaster state
    // prevents the framework matcher from finding a loaded pattern.
    try {
        return $broadcaster->auth($request);
    } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
        $fallbackResponse = $fallbackAuth();

        if ($fallbackResponse !== null) {
            \Log::info('Broadcast auth granted by account-scoped fallback', [
                'user_id' => $user->id,
                'channel' => $channelName,
                'normalized_channel' => $normalizedChannel,
            ]);

            return $fallbackResponse;
        }

        \Log::warning('Broadcast auth denied - no matching channel', [
            'user_id' => $user->id,
            'channel' => $channelName,
            'normalized_channel' => $normalizedChannel,
            'registered_patterns' => $channels->keys()->toArray(),
        ]);
        throw $e;
    }
})->name('broadcasting.auth');

// Settings route (unified settings page)
Route::middleware(['auth', 'account.resolve'])->prefix('/app')->name('app.')->group(function () {
    Route::get('/settings', [\App\Http\Controllers\SettingsController::class, 'index'])->name('settings');
    Route::patch('/settings/workspace', [\App\Http\Controllers\SettingsController::class, 'updateWorkspace'])->name('settings.workspace');
    Route::post('/settings/workspace', [\App\Http\Controllers\SettingsController::class, 'updateWorkspace'])->name('settings.workspace.upload');
    Route::post('/settings/inbox', [\App\Http\Controllers\SettingsController::class, 'updateInbox'])->name('settings.inbox');
    Route::post('/settings/notifications', [\App\Http\Controllers\SettingsController::class, 'updateNotifications'])->name('settings.notifications');
});

Route::any('/webhooks/whatsapp/debug/{any?}', fn () => response()->json(['success' => false, 'error' => 'Not found'], 404))
    ->where('any', '.*');

// Webhook routes (public, no auth, but with security middleware)
// Note: These routes are excluded from CSRF, rate limiting, and maintenance mode
Route::prefix('/webhooks/whatsapp')
    ->middleware([
        \App\Http\Middleware\LogMetaWhatsAppWebhook::class,
        \App\Http\Middleware\EnsureWebhooksEnabled::class,
        \App\Modules\WhatsApp\Http\Middleware\WebhookSecurity::class,
        \App\Http\Middleware\LogApiRequests::class,
    ])
    ->name('webhooks.whatsapp.')
    ->group(function () {
        Route::get('/', [\App\Modules\WhatsApp\Http\Controllers\WebhookController::class, 'verifyCentral'])->name('central.verify');
        Route::post('/', [\App\Modules\WhatsApp\Http\Controllers\WebhookController::class, 'receiveCentral'])->name('central.receive');

        // Test endpoint to verify webhook route is accessible (no route binding)
        Route::get('/test', function () {
            abort_if(app()->isProduction(), 404);

            \Log::channel('whatsapp')->info('Webhook test endpoint hit', [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return response()->json([
                'status' => 'ok',
                'message' => 'Webhook endpoint is accessible',
                'timestamp' => now()->toIso8601String(),
            ]);
        })->name('test');

        Route::get('/{connection}', [\App\Modules\WhatsApp\Http\Controllers\WebhookController::class, 'verify'])->name('verify');
        Route::post('/{connection}', [\App\Modules\WhatsApp\Http\Controllers\WebhookController::class, 'receive'])->name('receive');
    });
