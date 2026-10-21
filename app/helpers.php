<?php

if (! function_exists('module_enabled')) {
    /**
     * Check if a module is enabled for a account.
     */
    function module_enabled($account, string $moduleKey): bool
    {
        if (! $account) {
            return false;
        }

        $candidateKeys = [$moduleKey];

        // First check: module must be enabled at platform level
        $module = \App\Models\Module::whereIn('key', $candidateKeys)
            ->where('is_enabled', true)
            ->first();
        if (! $module || ! $module->is_enabled) {
            return false;
        }

        $effectiveModules = app(\App\Core\Modules\ModuleAccess::class)->getEffectiveModules($account);

        return count(array_intersect($candidateKeys, $effectiveModules)) > 0;
    }
}

if (! function_exists('current_account')) {
    /**
     * Get the current account from session.
     */
    function current_account()
    {
        $request = request();
        $resolved = $request->attributes->get('account');
        if ($resolved instanceof \App\Models\Account) {
            return $resolved;
        }

        $user = auth()->user();
        if (! $user) {
            $accountId = session('current_account_id');

            return $accountId ? \App\Models\Account::find($accountId) : null;
        }

        $account = app(\App\Support\CurrentAccountResolver::class)
            ->resolve($user, session('current_account_id'));

        if (! $account) {
            // No workspace of their own: keep the old behaviour so access checks further
            // down (ResolveAccount, policies) deny with 403 instead of a confusing 404.
            $accountId = session('current_account_id');

            return $accountId ? \App\Models\Account::find($accountId) : null;
        }

        if (! account_ids_match(session('current_account_id'), $account->id)) {
            session(['current_account_id' => $account->id]);
        }

        return $account;
    }
}

if (! function_exists('is_super_admin')) {
    /**
     * Check if the current user is a super admin (platform owner).
     */
    function is_super_admin(): bool
    {
        $user = auth()->user();

        return $user && $user->isSuperAdmin();
    }
}

if (! function_exists('is_platform_admin')) {
    /**
     * Alias for is_super_admin (backward compatibility).
     */
    function is_platform_admin(): bool
    {
        return is_super_admin();
    }
}

if (! function_exists('account_ids_match')) {
    /**
     * Compare account ids safely (string vs int).
     */
    function account_ids_match($left, $right): bool
    {
        if ($left === null || $right === null) {
            return false;
        }

        if (is_numeric($left) && is_numeric($right)) {
            return (int) $left === (int) $right;
        }

        return (string) $left === (string) $right;
    }
}

if (! function_exists('brand_name')) {
    /**
     * White-label product name (Admin > Settings > Branding, falls back to APP_NAME).
     */
    function brand_name(): string
    {
        static $resolving = false;
        if ($resolving) {
            return (string) config('app.name', 'ChatDesk');
        }
        $resolving = true;
        try {
            $name = app(\App\Services\BrandingService::class)->getPlatformName();
        } catch (\Throwable $e) {
            $name = '';
        } finally {
            $resolving = false;
        }

        return trim((string) $name) !== '' ? (string) $name : (string) config('app.name', 'ChatDesk');
    }
}
