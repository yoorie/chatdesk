<?php

namespace App\Http\Middleware;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        // The platform shell does not use workspace navigation or the workspace switcher.
        $platformView = $request->routeIs('platform.*')
            || ($request->routeIs('profile.*') && $user?->isSuperAdmin());
        $account = $platformView ? null : ($request->attributes->get('account') ?? current_account());

        $accounts = [];
        $navigation = [];
        $lockedNavigation = [];

        $accountRole = null;

        if ($user && ! $platformView) {
            $accounts = $user->accounts()->get()->merge($user->ownedAccounts()->get())->unique('id')->values();

            if ($account) {
                // Resolve current user's role for this account (owner, admin, or member/chat-agent)
                if ((int) $account->owner_id === (int) $user->id) {
                    $accountRole = 'owner';
                } else {
                    $pivot = $account->users()->where('user_id', $user->id)->first()?->pivot;
                    $accountRole = $pivot ? $pivot->role : null;
                }
                // Platform admins have no pivot but can access; treat as full access (no account_role restriction)

                $moduleRegistry = app(\App\Core\Modules\ModuleRegistry::class);
                $navigation = $moduleRegistry->getNavigationForAccount($account);

                // Chat agents (role === 'member') only see Inbox + AI Assistant
                if ($accountRole === 'member') {
                    $navigation = array_values(array_filter($navigation, function ($item) {
                        $href = $item['href'] ?? '';

                        return in_array($href, [
                            'app.whatsapp.conversations.index',
                            'app.ai.index',
                            'app.ai',
                            'app.notifications.index',
                        ], true);
                    }));
                    if (empty($navigation)) {
                        $navigation = [
                            ['label' => 'Inbox', 'href' => 'app.whatsapp.conversations.index', 'icon' => 'Inbox', 'group' => 'messaging'],
                        ];
                    }
                } else {
                    // Add static navigation items for non-members (owner, admin, platform admin)
                    $staticNav = [
                        [
                            'label' => 'Team',
                            'href' => 'app.team.index',
                            'icon' => 'Users',
                            'group' => 'core'],
                        [
                            'label' => 'Activity Logs',
                            'href' => 'app.activity-logs',
                            'icon' => 'Activity',
                            'group' => 'core'],
                        [
                            'label' => 'API & webhooks',
                            'href' => 'app.developer.index',
                            'icon' => 'Code2',
                            'group' => 'core'],
                        [
                            'label' => 'Notifications',
                            'href' => 'app.notifications.index',
                            'icon' => 'Bell',
                            'group' => 'core'],
                        [
                            'label' => 'Settings',
                            'href' => 'app.settings',
                            'icon' => 'Settings',
                            'group' => 'other']];

                    $navigation = array_merge($navigation, $staticNav);
                    $lockedNavigation = $this->lockedNavigation($account, $moduleRegistry);
                }
            }
        }

        $brandingService = app(\App\Services\BrandingService::class);

        $impersonatorId = $request->session()->get('impersonator_id');
        $impersonator = $impersonatorId ? User::find($impersonatorId) : null;

        // Check if profile is complete
        $isProfileComplete = $user ? (
            ! empty($user->name) &&
            ! empty($user->email)
        ) : true;

        $notificationSummary = ['unread' => 0, 'critical' => 0, 'latest' => []];
        $inboxSummary = ['unread' => 0];
        if ($user?->isSuperAdmin()) {
            $base = \App\Models\AppNotification::where('scope', 'platform')->whereNull('read_at');
            $counts = (clone $base)->selectRaw("COUNT(*) as unread, SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical")->first();
            $notificationSummary = [
                'unread' => (int) $counts->unread,
                'critical' => (int) $counts->critical,
                'latest' => \App\Models\AppNotification::where('scope', 'platform')
                    ->latest()
                    ->limit(5)
                    ->get(['id', 'title', 'body', 'severity', 'action_url', 'created_at'])
                    ->map(fn ($notification) => [
                        'id' => $notification->id,
                        'title' => $notification->title,
                        'body' => $notification->body,
                        'severity' => $notification->severity,
                        'action_url' => $notification->action_url,
                        'created_at' => $notification->created_at?->toIso8601String(),
                    ])
                    ->all(),
            ];
        } elseif ($account) {
            $base = \App\Models\AppNotification::where('scope', 'workspace')->where('account_id', $account->id)->whereNull('read_at');
            $counts = (clone $base)->selectRaw("COUNT(*) as unread, SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical")->first();
            $notificationSummary = [
                'unread' => (int) $counts->unread,
                'critical' => (int) $counts->critical,
                'latest' => \App\Models\AppNotification::where('scope', 'workspace')
                    ->where('account_id', $account->id)
                    ->latest()
                    ->limit(5)
                    ->get(['id', 'title', 'body', 'severity', 'action_url', 'created_at'])
                    ->map(fn ($notification) => [
                        'id' => $notification->id,
                        'title' => $notification->title,
                        'body' => $notification->body,
                        'severity' => $notification->severity,
                        'action_url' => $notification->action_url,
                        'created_at' => $notification->created_at?->toIso8601String(),
                    ])
                    ->all(),
            ];
        }

        if ($account) {
            $inboxSummary = [
                'unread' => \App\Modules\WhatsApp\Models\WhatsAppMessage::where('account_id', $account->id)
                    ->where('direction', 'inbound')
                    ->whereNull('read_at')
                    ->count(),
            ];
        }

        $workspacePermissions = [];
        if ($user && $account) {
            $permissionService = app(\App\Services\WorkspacePermissionService::class);
            $workspacePermissions = $permissionService->permissionsFor($user, $account);
        }

        return [
            ...parent::share($request),
            // Current CSRF token on every Inertia response (including partial reloads),
            // so the client can refresh <meta name="csrf-token"> after the session token
            // is rotated (login, logout, session regenerate) without a full page load.
            'csrf_token' => \Inertia\Inertia::always(fn () => csrf_token()),
            // Public website CMS (menus, announcement, landing sections). Cached.
            'cms' => fn () => $platformView ? null : app(\App\Services\Cms\CmsService::class)->publicData(),
            'demo' => config('demo.enabled') ? [
                'enabled' => true,
                'message' => config('demo.message'),
                'admin' => ['email' => config('demo.admin_email'), 'password' => config('demo.admin_password')],
                'owner' => ['email' => config('demo.user_email'), 'password' => config('demo.user_password')],
                'agent' => ['email' => config('demo.agent_email'), 'password' => config('demo.agent_password')],
                'user' => ['email' => config('demo.user_email'), 'password' => config('demo.user_password')],
            ] : null,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => \App\Support\CustomerMessage::humanize($request->session()->get('error')),
                'warning' => \App\Support\CustomerMessage::humanize($request->session()->get('warning')),
                'info' => $request->session()->get('info'),
                'status' => $request->session()->get('status'),
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'is_super_admin' => $user->isSuperAdmin(),
                    'notify_assignment_enabled' => $user->notify_assignment_enabled ?? true,
                    'notify_mention_enabled' => $user->notify_mention_enabled ?? true,
                    'notify_sound_enabled' => $user->notify_sound_enabled ?? true,
                    'notify_billing_enabled' => $user->notify_billing_enabled ?? true,
                    'notify_waba_enabled' => $user->notify_waba_enabled ?? true,
                    'notify_automation_enabled' => $user->notify_automation_enabled ?? true,
                    'notify_leads_enabled' => $user->notify_leads_enabled ?? true,
                    'notify_templates_enabled' => $user->notify_templates_enabled ?? true,
                    'notify_email_enabled' => $user->notify_email_enabled ?? true,
                    'notify_in_app_enabled' => $user->notify_in_app_enabled ?? true,
                    'quiet_hours_enabled' => $user->quiet_hours_enabled ?? false,
                    'quiet_hours_start' => $user->quiet_hours_start,
                    'quiet_hours_end' => $user->quiet_hours_end,
                    'two_factor_enabled' => $user->two_factor_enabled_at !== null,
                    'force_password_reset_at' => $user->force_password_reset_at?->toIso8601String(),
                    'ai_suggestions_enabled' => $user->ai_suggestions_enabled ?? false,
                ] : null,
                'profile_complete' => $isProfileComplete],
            'account' => $account,
            'workspace' => $account,
            'account_role' => $accountRole ?? null,
            'workspace_role' => $accountRole ?? null,
            'accounts' => $accounts,
            'workspaces' => $accounts,
            'navigation' => $navigation,
            // Plan-gated features, shown locked with an upgrade hint instead of disappearing.
            'locked_navigation' => $lockedNavigation,
            'notification_summary' => $notificationSummary,
            'inbox_summary' => $inboxSummary,
            'workspace_permissions' => $workspacePermissions,
            'branding' => $brandingService->getAll(),
            'impersonation' => [
                'active' => (bool) $impersonatorId,
                'impersonator' => $impersonator ? [
                    'id' => $impersonator->id,
                    'name' => $impersonator->name,
                    'email' => $impersonator->email] : null,
                'user_id' => $request->session()->get('impersonated_user_id'),
                'account_id' => $request->session()->get('impersonated_account_id')],
            'ai' => [
                'enabled' => $this->toBoolean(PlatformSetting::get('ai.enabled', false)),
                'provider' => PlatformSetting::get('ai.provider', 'openai')],
            'analyticsSettings' => [
                'google_analytics_enabled' => PlatformSetting::get('analytics.google_analytics_enabled', false),
                'google_analytics_id' => PlatformSetting::get('analytics.google_analytics_id'),
                'mixpanel_enabled' => PlatformSetting::get('analytics.mixpanel_enabled', false),
                'mixpanel_token' => PlatformSetting::get('analytics.mixpanel_token'),
            ],
            'compliance' => [
                'terms_url' => PlatformSetting::get('compliance.terms_url'),
                'privacy_url' => PlatformSetting::get('compliance.privacy_url'),
                'cookie_policy_url' => PlatformSetting::get('compliance.cookie_policy_url'),
                'gdpr_enabled' => PlatformSetting::get('compliance.gdpr_enabled', false),
                'cookie_consent_required' => PlatformSetting::get('compliance.cookie_consent_required', false),
            ],
            'supportSettings' => [
                'ticket_support_enabled' => $this->toBoolean(PlatformSetting::get('support.ticket_support_enabled', true)),
            ],
            'features' => [
                'analytics' => $this->toBoolean(PlatformSetting::get('features.analytics', true)),
                'public_api' => $this->toBoolean(PlatformSetting::get('features.public_api', false)),
                'webhooks' => $this->toBoolean(PlatformSetting::get('features.webhooks', true)),
            ],
            'pusherConfig' => (function () use ($request) {
                if (! $request->user()) {
                    return ['pusherKey' => '', 'pusherCluster' => ''];
                }

                $settingsService = app(\App\Services\PlatformSettingsService::class);

                return [
                    'pusherKey' => $settingsService->getNonBlank('pusher.key', config('broadcasting.connections.pusher.key')),
                    'pusherCluster' => $settingsService->getNonBlank('pusher.cluster', config('broadcasting.connections.pusher.options.cluster'))];
            })(),
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url()]];
    }

    protected function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
        }

        return (bool) $value;
    }

    /**
     * Navigation items for modules the workspace's plan does not include, with the
     * cheapest public plan that unlocks each one. Modules the workspace switched
     * off itself (Tools page) are not listed.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function lockedNavigation($account, \App\Core\Modules\ModuleRegistry $moduleRegistry): array
    {
        try {
            $available = app(\App\Core\Billing\PlanResolver::class)->getAvailableModuleKeys($account);
            $platformEnabled = \App\Models\Module::where('is_enabled', true)->pluck('key')->all();
            $analyticsEnabled = app(\App\Services\PlatformSettingsService::class)->isFeatureEnabled('analytics');
            $plans = \App\Models\Plan::query()
                ->where('is_active', true)
                ->where('is_public', true)
                ->orderBy('sort_order')
                ->orderBy('price_monthly')
                ->get(['key', 'name', 'modules', 'sort_order', 'price_monthly']);

            $items = [];
            foreach ($moduleRegistry->all() as $module) {
                $key = $module['key'] ?? null;
                if (! $key
                    || in_array($key, ['billing', 'support', 'core.dashboard'], true)
                    || in_array($key, $available, true)
                    || ! in_array($key, $platformEnabled, true)
                    || empty($module['nav'])
                    || ($key === 'analytics' && ! $analyticsEnabled)) {
                    continue;
                }

                $plan = $plans->first(fn ($plan) => in_array($key, $plan->modules ?? [], true));
                if (! $plan) {
                    continue;
                }

                foreach ($module['nav'] as $navItem) {
                    $items[] = [
                        'label' => $navItem['label'] ?? '',
                        'href' => $navItem['href'] ?? '',
                        'icon' => $navItem['icon'] ?? 'Lock',
                        'required_plan' => $plan->name,
                        'required_plan_key' => $plan->key,
                    ];
                }
            }

            return $items;
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }
}
