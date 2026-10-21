<?php

namespace App\Core\Billing;

use App\Models\Account;
use App\Models\Plan;

class PlanResolver
{
    protected array $planCache = [];

    protected array $availableModulesCache = [];

    protected array $effectiveModulesCache = [];

    protected array $limitsCache = [];

    /**
     * Get the plan for a account.
     */
    public function getAccountPlan(Account $account): ?Plan
    {
        $cacheKey = $this->accountCacheKey($account, 'plan');
        if (array_key_exists($cacheKey, $this->planCache)) {
            return $this->planCache[$cacheKey];
        }

        $account->loadMissing('subscription.plan');
        $subscription = $account->subscription;

        if (! $subscription) {
            $defaultPlanKey = config('billing.default_plan_key', 'starter');

            return $this->planCache[$cacheKey] = Plan::where('key', $defaultPlanKey)
                ->where('is_active', true)
                ->first()
                ?? Plan::where('key', 'starter')->where('is_active', true)->first()
                ?? Plan::where('is_active', true)
                    ->where('is_public', true)
                    ->whereNotNull('price_monthly')
                    ->where('price_monthly', '>', 0)
                    ->orderBy('sort_order')
                    ->first()
                ?? Plan::where('is_active', true)->orderBy('sort_order')->first();
        }

        return $this->planCache[$cacheKey] = $subscription->plan;
    }

    /**
     * Get effective modules for a account.
     *
     * Returns array of module keys that are enabled for this account.
     * Order: plan modules + addon modules → intersect with account toggles
     */
    public function getEffectiveModules(Account $account): array
    {
        $cacheKey = $this->accountCacheKey($account, 'effective_modules');
        if (array_key_exists($cacheKey, $this->effectiveModulesCache)) {
            return $this->effectiveModulesCache[$cacheKey];
        }

        $availableModules = $this->getAvailableModuleKeys($account);
        $disabledModules = \App\Models\AccountModule::where('account_id', $account->id)
            ->where('enabled', false)
            ->pluck('module_key')
            ->all();

        return $this->effectiveModulesCache[$cacheKey] = array_values(array_diff($availableModules, $disabledModules));
    }

    /**
     * Get modules available to the account before account-level enable/disable toggles.
     */
    public function getAvailableModuleKeys(Account $account): array
    {
        $cacheKey = $this->accountCacheKey($account, 'available_modules');
        if (array_key_exists($cacheKey, $this->availableModulesCache)) {
            return $this->availableModulesCache[$cacheKey];
        }

        $plan = $this->getAccountPlan($account);
        $modules = $plan ? ($plan->modules ?? []) : [];

        $account->loadMissing('addons.addon');
        foreach ($account->addons as $accountAddon) {
            $modules = array_merge($modules, $accountAddon->addon?->modules_delta ?? []);
        }

        $coreModules = \App\Models\Module::where('is_core', true)
            ->pluck('key')
            ->all();

        $platformEnabledModules = \App\Models\Module::where('is_enabled', true)
            ->pluck('key')
            ->all();

        return $this->availableModulesCache[$cacheKey] = array_values(array_intersect(
            array_unique(array_merge($modules, $coreModules)),
            $platformEnabledModules
        ));
    }

    /**
     * Get effective limits for a account.
     *
     * Returns array of limit keys with their values.
     * Base plan limits + addon limits_delta * quantity
     */
    public function getEffectiveLimits(Account $account): array
    {
        $cacheKey = $this->accountCacheKey($account, 'limits');
        if (array_key_exists($cacheKey, $this->limitsCache)) {
            return $this->limitsCache[$cacheKey];
        }

        $plan = $this->getAccountPlan($account);

        if (! $plan) {
            return $this->limitsCache[$cacheKey] = [];
        }

        // Start with plan limits
        $limits = $plan->limits ?? [];

        // Add limits from active addons
        $addons = $account->addons()->with('addon')->get();
        foreach ($addons as $accountAddon) {
            $addonLimits = $accountAddon->addon->limits_delta ?? [];
            $quantity = $accountAddon->quantity;

            foreach ($addonLimits as $key => $value) {
                if (isset($limits[$key])) {
                    // If limit is -1 (unlimited), keep it
                    if ($limits[$key] === -1) {
                        continue;
                    }
                    $limits[$key] = ($limits[$key] ?? 0) + ($value * $quantity);
                } else {
                    $limits[$key] = $value * $quantity;
                }
            }
        }

        return $this->limitsCache[$cacheKey] = $limits;
    }

    public function forget(Account|int $account): void
    {
        $accountId = $account instanceof Account ? $account->id : $account;
        foreach (['planCache', 'availableModulesCache', 'effectiveModulesCache', 'limitsCache'] as $property) {
            foreach (array_keys($this->{$property}) as $key) {
                if (str_starts_with((string) $key, $accountId.':')) {
                    unset($this->{$property}[$key]);
                }
            }
        }
    }

    protected function accountCacheKey(Account $account, string $scope): string
    {
        return implode(':', [
            $account->id,
            $scope,
            optional($account->updated_at)->getTimestamp() ?: '0',
            optional($account->subscription?->updated_at)->getTimestamp() ?: '0',
        ]);
    }
}
