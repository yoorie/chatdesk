<?php

namespace App\Http\Middleware;

use App\Models\AccountRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictChatAgentAccess
{
    /**
     * Routes (or route name prefixes) that chat agents (role member) are allowed to access.
     */
    protected array $allowedForChatAgent = [
        'app.dashboard',
        'app.whatsapp.conversations',
        'app.whatsapp.inbox',
        'app.ai',
        'app.accounts.switch',
        'app.notifications',
    ];

    protected array $routePermissions = [
        'app.whatsapp.conversations' => 'inbox',
        'app.dashboard' => 'inbox',
        'app.whatsapp.inbox' => 'inbox',
        'app.contacts' => 'contacts.view',
        'app.segments' => 'contacts.view',
        'app.whatsapp.templates' => 'templates',
        'app.broadcasts' => 'campaigns',
        'app.chatbots' => 'automation',
        'app.analytics' => 'analytics',
        'app.billing' => 'billing',
        'app.settings' => 'settings',
        'app.workspaces' => 'settings',
        'app.team' => 'team',
        'app.ai' => 'inbox',
        'app.notifications' => 'inbox',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $account = $request->attributes->get('account') ?? current_account();

        if (! $user || ! $account) {
            return $next($request);
        }

        // Platform admins have full access
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Owner has full access
        if ((int) $account->owner_id === (int) $user->id) {
            return $next($request);
        }

        $pivot = $account->users()->where('user_id', $user->id)->first()?->pivot;
        $role = $pivot?->role ?? null;

        // Owner/admin have full access; member and custom roles are permission-scoped.
        if ($role === 'admin') {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if (! $routeName) {
            return $next($request);
        }

        if ($role === 'member') {
            foreach ($this->allowedForChatAgent as $allowed) {
                if ($routeName === $allowed || str_starts_with($routeName, $allowed.'.')) {
                    return $next($request);
                }
            }

            return redirect()->route('app.whatsapp.conversations.index')
                ->with('error', 'You do not have permission to access that page. Chat agents can only access Inbox and AI Assistant.');
        }

        $permissions = AccountRole::where('account_id', $account->id)
            ->where('key', $role)
            ->value('permissions') ?? [];

        if (is_string($permissions)) {
            $permissions = json_decode($permissions, true) ?: [];
        }

        foreach ($this->routePermissions as $prefix => $permission) {
            if (($routeName === $prefix || str_starts_with($routeName, $prefix.'.')) && (
                in_array($permission, $permissions, true)
                || in_array(str($permission)->before('.')->toString(), $permissions, true)
            )) {
                return $next($request);
            }
        }

        return redirect()->route('app.whatsapp.conversations.index')
            ->with('error', 'Your role does not have permission to access that page.');
    }
}
