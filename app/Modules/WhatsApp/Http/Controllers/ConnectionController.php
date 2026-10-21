<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Core\Billing\EntitlementService;
use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppMetaEventLog;
use App\Modules\WhatsApp\Services\ConnectionService;
use App\Modules\WhatsApp\Services\MetaGraphService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class ConnectionController extends Controller
{
    public function __construct(
        protected ConnectionService $connectionService,
        protected EntitlementService $entitlementService,
        protected MetaGraphService $metaGraphService
    ) {}

    /**
     * Display a listing of connections.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();

        $connections = WhatsAppConnection::where('account_id', $account->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($connection) {
                return [
                    'id' => $connection->id,
                    'slug' => $connection->slug ?? (string) $connection->id,
                    'name' => $connection->name,
                    'waba_id' => $connection->waba_id,
                    'meta_business_id' => $connection->meta_business_id,
                    'meta_waba_name' => $connection->meta_waba_name,
                    'meta_account_review_status' => $connection->meta_account_review_status,
                    'meta_business_verification_status' => $connection->meta_business_verification_status,
                    'meta_timezone_id' => $connection->meta_timezone_id,
                    'meta_template_namespace' => $connection->meta_template_namespace,
                    'meta_subscribed_apps' => $connection->meta_subscribed_apps ?? [],
                    'phone_number_id' => $connection->phone_number_id,
                    'business_phone' => $connection->business_phone,
                    'meta_verified_name' => $connection->meta_verified_name,
                    'phone_number_status' => $connection->phone_number_status,
                    'quality_rating' => $connection->quality_rating,
                    'code_verification_status' => $connection->code_verification_status,
                    'business_category' => $connection->business_category ?? 'Retail',
                    'business_username' => $connection->business_username,
                    'business_about' => filled($connection->business_about) ? $connection->business_about : 'Turn conversations into conversions - WhatsApp marketing made simple.',
                    'business_address' => $connection->business_address,
                    'business_description' => $connection->business_description,
                    'business_email' => $connection->business_email,
                    'business_websites' => $connection->business_websites ?? [],
                    'business_vertical' => $connection->business_vertical ?? 'RETAIL',
                    'profile_picture_url' => $connection->profile_picture_url,
                    'profile_picture_handle' => $connection->profile_picture_handle,
                    'profile_synced_at' => $connection->profile_synced_at?->toIso8601String(),
                    'profile_sync_error' => $connection->profile_sync_error,
                    'setup_method' => $connection->setup_method ?? 'embedded',
                    'connection_mode' => $connection->connection_mode ?? 'cloud_api',
                    'coexistence_status' => $connection->coexistence_status,
                    'coexistence_metadata' => $connection->coexistence_metadata ?? [],
                    'coexistence_last_checked_at' => $connection->coexistence_last_checked_at?->toIso8601String(),
                    'coexistence_last_error' => $connection->coexistence_last_error,
                    'webhook_url' => $this->connectionService->getWebhookUrl($connection),
                    'webhook_verify_token' => $connection->webhook_verify_token,
                    'webhook_subscribed' => $connection->webhook_subscribed,
                    'webhook_last_received_at' => $connection->webhook_last_received_at?->toIso8601String(),
                    'webhook_last_error' => $connection->webhook_last_error,
                    'is_active' => $connection->is_active,
                    'api_version' => $connection->api_version,
                    'throughput_cap_per_minute' => $connection->throughput_cap_per_minute,
                    'quiet_hours_start' => $connection->quiet_hours_start,
                    'quiet_hours_end' => $connection->quiet_hours_end,
                    'quiet_hours_timezone' => $connection->quiet_hours_timezone,
                    'meta_event_logs' => $this->recentMetaEventLogs($connection),
                    'created_at' => $connection->created_at->toIso8601String()];
            });

        return Inertia::render('WhatsApp/Connections/Index', [
            'account' => $account,
            'connections' => $connections,
            'canCreate' => Gate::allows('create', WhatsAppConnection::class)
                && $this->entitlementService->canCreateConnection($account),
            'centralWebhook' => [
                'url' => route('webhooks.whatsapp.central.receive'),
                'verify_token' => WebhookController::centralVerifyToken(),
            ],
            'defaultApiVersion' => config('whatsapp.meta.api_version', 'v25.0')]);
    }

    protected function recentMetaEventLogs(WhatsAppConnection $connection): array
    {
        if (! Schema::hasTable('whatsapp_meta_event_logs')) {
            return [];
        }

        return WhatsAppMetaEventLog::where('whatsapp_connection_id', $connection->id)
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'field', 'event_type', 'status', 'message', 'received_at', 'created_at'])
            ->map(fn (WhatsAppMetaEventLog $log) => [
                'id' => $log->id,
                'field' => $log->field,
                'event_type' => $log->event_type,
                'status' => $log->status,
                'message' => $log->message,
                'received_at' => ($log->received_at ?? $log->created_at)?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * Store a newly created connection.
     */
    /**
     * Open-source edition: connect a WhatsApp Cloud API number manually with
     * the Phone Number ID, WABA ID and a permanent access token from Meta.
     * See docs/connect-whatsapp.md.
     */
    public function storeManual(Request $request)
    {
        $account = $request->attributes->get('account') ?? current_account();
        Gate::authorize('create', WhatsAppConnection::class);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'phone_number_id' => 'required|string|max:64|regex:/^[0-9]+$/',
            'waba_id' => 'required|string|max:64|regex:/^[0-9]+$/',
            'access_token' => 'required|string|min:20|max:2048',
            'business_phone' => 'nullable|string|max:32',
        ]);

        $existing = WhatsAppConnection::where('account_id', $account->id)
            ->where('phone_number_id', $validated['phone_number_id'])
            ->first();

        $this->connectionService->ensurePhoneAvailable($account, $validated['phone_number_id'], $validated['business_phone'] ?? null, $existing);

        $phoneDetails = [];
        try {
            $phoneDetails = $this->metaGraphService->getPhoneNumberDetails($validated['phone_number_id'], $validated['access_token']);
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->info('Manual connection: phone lookup failed, saving anyway', ['error' => $e->getMessage()]);
        }

        $businessPhone = ($validated['business_phone'] ?? null) ?: ($phoneDetails['display_phone_number'] ?? null);
        $data = [
            'name' => ($validated['name'] ?? null) ?: ($businessPhone ? "WhatsApp {$businessPhone}" : 'WhatsApp'),
            'waba_id' => $validated['waba_id'],
            'phone_number_id' => $validated['phone_number_id'],
            'business_phone' => $businessPhone,
            'meta_verified_name' => $phoneDetails['verified_name'] ?? null,
            'phone_number_status' => $phoneDetails['status'] ?? null,
            'quality_rating' => $phoneDetails['quality_rating'] ?? null,
            'access_token' => $validated['access_token'],
            'setup_method' => 'manual',
            'connection_mode' => 'cloud_api',
        ];

        if ($existing) {
            $this->connectionService->update($existing, $data);
        } else {
            $this->connectionService->create($account, $data);
        }

        return redirect()->route('app.whatsapp.connections.index')
            ->with('success', 'WhatsApp number saved. Now add the webhook URL and verify token shown below in your Meta app.');
    }

    protected function coexistenceStatus(string $connectionMode, array $phoneDetails): ?string
    {
        if ($connectionMode !== 'coexistence') {
            return null;
        }

        $status = strtolower((string) ($phoneDetails['status'] ?? ''));
        if ($status === '' || in_array($status, ['connected', 'active'], true)) {
            return 'connected';
        }

        return 'needs_attention';
    }

    protected function coexistenceMetadata(array $sessionInfo, array $phoneDetails): array
    {
        return [
            'source' => 'embedded_signup',
            'session_event' => $sessionInfo['event'] ?? null,
            'phone_platform_type' => $phoneDetails['platform_type'] ?? null,
            'phone_name_status' => $phoneDetails['name_status'] ?? null,
            'phone_messaging_limit_tier' => $phoneDetails['messaging_limit_tier'] ?? null,
            'phone_throughput' => $phoneDetails['throughput'] ?? null,
        ];
    }

    /**
     * Resolve connection from route parameter (handles both string ID and model instance).
     */
    protected function resolveConnection($connection, $account): WhatsAppConnection
    {
        // Always resolve fresh from database with account scoping for security
        // Even if route binding provided an instance, we verify it belongs to the account
        $connectionValue = $connection instanceof WhatsAppConnection ? ($connection->slug ?? $connection->id) : $connection;

        // Try to resolve by slug first, then by ID (for backward compatibility)
        // Always scope by account for security
        $resolved = WhatsAppConnection::where('account_id', $account->id)
            ->where(function ($query) use ($connectionValue) {
                $query->where('slug', $connectionValue);
                // Also try as ID if the value is numeric
                if (is_numeric($connectionValue)) {
                    $query->orWhere('id', $connectionValue);
                }
            })
            ->first();

        if (! $resolved) {
            abort(404, 'Connection not found in this account.');
        }

        return $resolved;
    }

    protected function fetchWabaDetailsSafely(?string $wabaId, ?string $accessToken): array
    {
        if (! $wabaId || ! $accessToken) {
            return [];
        }

        try {
            return $this->metaGraphService->getWabaDetails($wabaId, $accessToken);
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->warning('WABA details lookup failed', [
                'waba_id' => $wabaId,
                'error' => $e->getMessage()]);

            return [];
        }
    }

    protected function syncBusinessProfileFromMeta(WhatsAppConnection $connection): void
    {

        if (! $connection->phone_number_id || ! $connection->access_token) {
            return;
        }

        try {
            $profile = $this->metaGraphService->getBusinessProfile($connection->phone_number_id, $connection->access_token);
            $phoneDetails = $this->metaGraphService->getPhoneNumberDetails($connection->phone_number_id, $connection->access_token);
            $wabaDetails = $this->fetchWabaDetailsSafely($connection->waba_id, $connection->access_token);
            $subscribedApps = [];
            if ($connection->waba_id) {
                try {
                    $subscribedApps = $this->metaGraphService->listSubscribedApps($connection->waba_id, $connection->access_token);
                } catch (\Throwable $e) {
                    Log::channel('whatsapp')->warning('Subscribed apps lookup failed', [
                        'waba_id' => $connection->waba_id,
                        'error' => $e->getMessage()]);
                }
            }

            $connection->forceFill([
                'business_phone' => $phoneDetails['display_phone_number'] ?? $connection->business_phone,
                'meta_waba_name' => $wabaDetails['name'] ?? $connection->meta_waba_name,
                'meta_account_review_status' => $wabaDetails['account_review_status'] ?? $connection->meta_account_review_status,
                'meta_business_verification_status' => $wabaDetails['business_verification_status'] ?? $connection->meta_business_verification_status,
                'meta_timezone_id' => isset($wabaDetails['timezone_id']) ? (string) $wabaDetails['timezone_id'] : $connection->meta_timezone_id,
                'meta_template_namespace' => $wabaDetails['message_template_namespace'] ?? $connection->meta_template_namespace,
                'meta_subscribed_apps' => $subscribedApps ?: $connection->meta_subscribed_apps,
                'meta_verified_name' => $phoneDetails['verified_name'] ?? $connection->meta_verified_name,
                'phone_number_status' => $phoneDetails['status'] ?? $connection->phone_number_status,
                'quality_rating' => $phoneDetails['quality_rating'] ?? $connection->quality_rating,
                'code_verification_status' => $phoneDetails['code_verification_status'] ?? $connection->code_verification_status,
                'webhook_subscribed' => $subscribedApps ? true : $connection->webhook_subscribed,
                'coexistence_status' => ($connection->connection_mode ?? null) === 'coexistence' ? $this->coexistenceStatus('coexistence', $phoneDetails) : $connection->coexistence_status,
                'coexistence_metadata' => ($connection->connection_mode ?? null) === 'coexistence' ? array_merge($connection->coexistence_metadata ?? [], $this->coexistenceMetadata([], $phoneDetails)) : $connection->coexistence_metadata,
                'coexistence_last_checked_at' => ($connection->connection_mode ?? null) === 'coexistence' ? now() : $connection->coexistence_last_checked_at,
                'coexistence_last_error' => null,
                'business_about' => $profile['about'] ?? $connection->business_about,
                'business_address' => $profile['address'] ?? $connection->business_address,
                'business_description' => $profile['description'] ?? $connection->business_description,
                'business_email' => $profile['email'] ?? $connection->business_email,
                'business_websites' => $profile['websites'] ?? $connection->business_websites,
                'business_vertical' => $profile['vertical'] ?? $connection->business_vertical,
                'profile_picture_url' => $profile['profile_picture_url'] ?? $connection->profile_picture_url,
                'profile_synced_at' => now(),
                'profile_sync_error' => null,
            ])->save();
        } catch (\Throwable $e) {
            $connection->forceFill([
                'profile_sync_error' => $e->getMessage(),
                'coexistence_last_error' => ($connection->connection_mode ?? null) === 'coexistence' ? $e->getMessage() : $connection->coexistence_last_error,
            ])->save();
        }
    }

    /**
     * Update the specified connection.
     */
    public function update(Request $request, $connection)
    {
        $account = $request->attributes->get('account') ?? current_account();
        $connection = $this->resolveConnection($connection, $account);

        Gate::authorize('update', $connection);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'waba_id' => 'nullable|string|max:255',
            'phone_number_id' => 'required|string|max:255',
            'business_phone' => 'nullable|string|max:255',
            'business_category' => 'nullable|string|max:255',
            'business_username' => 'nullable|string|max:128',
            'business_about' => 'nullable|string|max:139',
            'business_address' => 'nullable|string|max:256',
            'business_description' => 'nullable|string|max:512',
            'business_email' => 'nullable|email|max:128',
            'business_websites' => 'nullable|array|max:2',
            'business_websites.*' => 'nullable|url|max:256',
            'business_vertical' => 'nullable|string|max:80',
            'profile_picture_handle' => 'nullable|string|max:2048',
            'profile_picture_file' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
            'sync_to_meta' => 'nullable|boolean',
            'access_token' => 'nullable|string', // Optional on update
            'api_version' => 'nullable|string|max:10',
            'throughput_cap_per_minute' => 'nullable|integer|min:1|max:1000',
            'quiet_hours_start' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'quiet_hours_end' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'quiet_hours_timezone' => 'nullable|timezone']);

        $profilePictureFile = $request->file('profile_picture_file');
        unset($validated['profile_picture_file']);
        $syncToMeta = (bool) ($validated['sync_to_meta'] ?? false);
        unset($validated['sync_to_meta']);
        $this->normalizeBusinessUsername($validated);
        $this->normalizeBusinessProfileText($validated);

        $businessWebsites = collect($validated['business_websites'] ?? [])
            ->filter(fn ($url) => is_string($url) && trim($url) !== '')
            ->values()
            ->all();
        $validated['business_websites'] = $businessWebsites;

        try {
            $profilePictureHandle = $validated['profile_picture_handle'] ?? null;
            if ($syncToMeta && ! $connection->access_token) {
                throw new \RuntimeException('No active Meta access token is stored for this connection. Reconnect the WABA before syncing the profile to Meta.');
            }

            if ($syncToMeta && $profilePictureFile) {
                $profilePictureHandle = $this->metaGraphService->uploadProfilePicture($profilePictureFile, $connection->access_token);
                $validated['profile_picture_handle'] = $profilePictureHandle;
            }

            if ($syncToMeta && $connection->access_token) {
                $this->updateBusinessProfileWithFallback($connection, [
                    'about' => $validated['business_about'] ?? null,
                    'address' => $validated['business_address'] ?? null,
                    'description' => $validated['business_description'] ?? null,
                    'email' => $validated['business_email'] ?? null,
                    'websites' => $businessWebsites,
                    'vertical' => $validated['business_vertical'] ?? null,
                    'profile_picture_handle' => $profilePictureHandle,
                ]);

                $validated['profile_synced_at'] = now();
                $validated['profile_sync_error'] = null;
            }
        } catch (\Throwable $e) {
            $validated['profile_sync_error'] = $this->friendlyBusinessProfileSyncError($e, $connection);
            $this->connectionService->update($connection, $validated);

            return redirect()->route('app.whatsapp.connections.index')
                ->with('error', ('Your profile is saved in '.brand_name().', but WhatsApp did not accept the update: ').\App\Support\CustomerMessage::humanize($validated['profile_sync_error']));
        }

        $this->connectionService->update($connection, $validated);

        return redirect()->route('app.whatsapp.connections.index')->with('success', $syncToMeta ? 'Profile saved and published to WhatsApp.' : 'Business profile saved locally.');
    }

    protected function friendlyBusinessProfileSyncError(\Throwable $e, ?WhatsAppConnection $connection = null): string
    {
        $message = $e->getMessage();

        if (str_contains($message, '(#200)') || str_contains(strtolower($message), 'permission')) {
            $scopeHint = $this->businessProfilePermissionHint($connection);

            return 'Meta rejected the profile update because the connected token can read this WABA but cannot edit the phone profile. '.$scopeHint;
        }

        return $message;
    }

    protected function businessProfilePermissionHint(?WhatsAppConnection $connection): string
    {
        if (! $connection?->access_token) {
            return 'Reconnect the WABA or configure a platform system-user token assigned to this WABA/phone number.';
        }

        try {
            $debug = $this->metaGraphService->debugToken($connection->access_token);
            $scopes = collect($debug['scopes'] ?? [])->filter()->values();
            $granularScopes = collect($debug['granular_scopes'] ?? []);
            $hasBusinessManagement = $scopes->contains('business_management');
            $hasWabaManagement = $granularScopes->contains(function ($scope) use ($connection) {
                return ($scope['scope'] ?? null) === 'whatsapp_business_management'
                    && in_array((string) $connection->waba_id, array_map('strval', $scope['target_ids'] ?? []), true);
            });

            if (! $hasBusinessManagement) {
                return brand_name().' requested business_management during Embedded Signup, but Meta did not grant it to the returned token. Enable/grant business_management for the Meta app/config or add a platform system-user token with business_management and whatsapp_business_management access to this WABA.';
            }

            if (! $hasWabaManagement) {
                return 'The token is missing whatsapp_business_management for this specific WABA. Reconnect with the correct business portfolio or assign the system user to this WABA in Meta Business Settings.';
            }
        } catch (\Throwable $debugError) {
            Log::channel('whatsapp')->warning('Business profile permission diagnostic failed', [
                'connection_id' => $connection->id,
                'error' => $debugError->getMessage(),
            ]);
        }

        return 'Reconnect may not fix this if Meta keeps returning a read-only token. Add a platform system-user token assigned to this WABA/phone number, or update the profile directly in WhatsApp Manager.';
    }

    protected function updateBusinessProfileWithFallback(WhatsAppConnection $connection, array $profile): array
    {
        try {
            return $this->metaGraphService->updateBusinessProfile($connection->phone_number_id, $connection->access_token, $profile);
        } catch (\Throwable $connectionTokenError) {
            $platformToken = PlatformSetting::get('whatsapp.system_user_token', config('whatsapp.meta.system_user_token'));
            if (! $platformToken || hash_equals((string) $connection->access_token, (string) $platformToken)) {
                throw $connectionTokenError;
            }

            try {
                return $this->metaGraphService->updateBusinessProfile($connection->phone_number_id, $platformToken, $profile);
            } catch (\Throwable $platformTokenError) {
                throw new \RuntimeException(
                    $connectionTokenError->getMessage().' Platform system-user fallback also failed: '.$platformTokenError->getMessage(),
                    0,
                    $connectionTokenError
                );
            }
        }
    }

    protected function normalizeBusinessUsername(array &$validated): void
    {
        if (! array_key_exists('business_username', $validated)) {
            return;
        }

        $username = trim((string) ($validated['business_username'] ?? ''));
        $username = ltrim($username, '@');
        $validated['business_username'] = $username !== '' ? $username : null;
    }

    protected function normalizeBusinessProfileText(array &$validated): void
    {
        foreach (['business_about', 'business_address', 'business_description', 'business_email'] as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            $value = trim((string) ($validated[$field] ?? ''));
            $validated[$field] = $value !== '' ? $value : null;
        }
    }

    public function syncMeta(Request $request, $connection)
    {
        $account = $request->attributes->get('account') ?? current_account();
        $connection = $this->resolveConnection($connection, $account);

        Gate::authorize('update', $connection);

        $this->syncBusinessProfileFromMeta($connection);

        return redirect()->route('app.whatsapp.connections.index')->with('success', 'Details refreshed from WhatsApp.');
    }

    public function destroy(Request $request, $connection)
    {
        $account = $request->attributes->get('account') ?? current_account();
        $connection = $this->resolveConnection($connection, $account);

        Gate::authorize('delete', $connection);

        $unsubscribeError = null;
        // Unsubscribing removes the platform Meta app from the whole WABA, which would cut
        // webhooks for every other number/workspace still connected to that WABA.
        $wabaStillInUse = $connection->waba_id && WhatsAppConnection::query()
            ->where('waba_id', $connection->waba_id)
            ->where('is_active', true)
            ->whereKeyNot($connection->getKey())
            ->exists();
        if (! $wabaStillInUse && $connection->waba_id && $connection->access_token && $connection->webhook_subscribed) {
            try {
                $this->metaGraphService->unsubscribeAppFromWaba($connection->waba_id, $connection->access_token);
            } catch (\Throwable $e) {
                $unsubscribeError = $e->getMessage();
                Log::channel('whatsapp')->warning('WABA webhook unsubscribe failed during disconnect', [
                    'account_id' => $account?->id,
                    'connection_id' => $connection->id,
                    'waba_id' => $connection->waba_id,
                    'error' => $unsubscribeError,
                ]);
            }
        }

        $connectionName = $connection->name;
        $connection->delete();

        if ($unsubscribeError) {
            return redirect()->route('app.whatsapp.connections.index')
                ->with('warning', "{$connectionName} is disconnected from ".brand_name().'. WhatsApp may keep sending updates for a short while; you do not need to do anything.');
        }

        return redirect()->route('app.whatsapp.connections.index')->with('success', "{$connectionName} is disconnected.");
    }

    public function subscribeWebhook(Request $request, $connection)
    {
        $account = $request->attributes->get('account') ?? current_account();
        $connection = $this->resolveConnection($connection, $account);

        Gate::authorize('update', $connection);

        if (! $connection->waba_id || ! $connection->access_token) {
            return redirect()->route('app.whatsapp.connections.index')
                ->with('error', 'Reconnect your WhatsApp account first, then turn on message updates.');
        }

        try {
            $this->metaGraphService->subscribeAppToWabaWithCallback(
                $connection->waba_id,
                $connection->access_token,
                route('webhooks.whatsapp.central.receive'),
                WebhookController::centralVerifyToken()
            );

            $subscribedApps = $this->metaGraphService->listSubscribedApps($connection->waba_id, $connection->access_token);

            $connection->forceFill([
                'webhook_subscribed' => count($subscribedApps) > 0,
                'meta_subscribed_apps' => $subscribedApps,
                'webhook_last_error' => null,
            ])->save();

            return redirect()->route('app.whatsapp.connections.index')->with('success', 'Message updates are on. New messages will arrive in your inbox.');
        } catch (\Throwable $e) {
            $connection->forceFill([
                'webhook_last_error' => $e->getMessage(),
            ])->save();

            return redirect()->route('app.whatsapp.connections.index')->with('error', 'We could not turn on message updates: '.\App\Support\CustomerMessage::humanize($e->getMessage()));
        }
    }

    public function unsubscribeWebhook(Request $request, $connection)
    {
        $account = $request->attributes->get('account') ?? current_account();
        $connection = $this->resolveConnection($connection, $account);

        Gate::authorize('update', $connection);

        if (! $connection->waba_id || ! $connection->access_token) {
            return redirect()->route('app.whatsapp.connections.index')
                ->with('error', 'Reconnect your WhatsApp account first.');
        }

        try {
            $this->metaGraphService->unsubscribeAppFromWaba($connection->waba_id, $connection->access_token);

            $connection->forceFill([
                'webhook_subscribed' => false,
                'meta_subscribed_apps' => [],
            ])->save();

            return redirect()->route('app.whatsapp.connections.index')->with('success', 'Message updates are off.');
        } catch (\Throwable $e) {
            $connection->forceFill([
                'webhook_last_error' => $e->getMessage(),
            ])->save();

            return redirect()->route('app.whatsapp.connections.index')->with('error', 'We could not turn off message updates: '.\App\Support\CustomerMessage::humanize($e->getMessage()));
        }
    }

    public function rotateVerifyToken(Request $request, $connection)
    {
        $account = $request->attributes->get('account') ?? current_account();
        $connection = $this->resolveConnection($connection, $account);

        Gate::authorize('update', $connection);

        $this->connectionService->rotateVerifyToken($connection);

        return redirect()->route('app.whatsapp.connections.index')->with('success', 'Verification token changed.');
    }
}
