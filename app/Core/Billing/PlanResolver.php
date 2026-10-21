<?php

namespace App\Core\Billing;

use App\Models\Account;
use App\Models\AccountModule;
use App\Models\Module;
use App\Models\Plan;

/**
 * ChatDesk open-source edition: internal unlimited mode.
 *
 * The organisation always runs on the internal "self-hosted" plan. Every module
 * the install has enabled is available and every limit is -1 (unlimited).
 * Admins can still switch modules off for the organisation (AccountModule).
 */
class PlanResolver
{
    /** Limit keys the app checks; all unlimited (-1) in this edition. */
    public const LIMIT_KEYS = [
        'agents',
        'whatsapp_connections',
        'messages_monthly',
        'template_sends_monthly',
        'ai_credits_monthly',
        'retention_days',
    ];

    protected array $planCache = [];

    protected array $availableModulesCache = [];

    protected array $effectiveModulesCache = [];

    public function getAccountPlan(Account $account): ?Plan
    {
        $cacheKey = $this->accountCacheKey($account, 'plan');
        if (array_key_exists($cacheKey, $this->planCache)) {
            return $this->planCache[$cacheKey];
        }

        $account->loadMissing('subscription.plan');

        return $this->planCache[$cacheKey] = $account->subscription?->plan
            ?? Plan::where('key', config('billing.default_plan_key', 'self-hosted'))->first();
    }

    /** Enabled module keys for the organisation. */
    public function getEffectiveModules(Account $account): array
    {
        $cacheKey = $this->accountCacheKey($account, 'effective_modules');
        if (array_key_exists($cacheKey, $this->effectiveModulesCache)) {
            return $this->effectiveModulesCache[$cacheKey];
        }

        $disabledModules = AccountModule::where('account_id', $account->id)
            ->where('enabled', false)
            ->pluck('module_key')
            ->all();

        return $this->effectiveModulesCache[$cacheKey] = array_values(
            array_diff($this->getAvailableModuleKeys($account), $disabledModules)
        );
    }

    /** Every module enabled on this install. */
    public function getAvailableModuleKeys(Account $account): array
    {
        $cacheKey = $this->accountCacheKey($account, 'available_modules');
        if (array_key_exists($cacheKey, $this->availableModulesCache)) {
            return $this->availableModulesCache[$cacheKey];
        }

        return $this->availableModulesCache[$cacheKey] = Module::where('is_enabled', true)
            ->pluck('key')
            ->values()
            ->all();
    }

    /** All limits are unlimited (-1). */
    public function getEffectiveLimits(Account $account): array
    {
        return array_fill_keys(self::LIMIT_KEYS, -1);
    }

    public function forget(Account|int $account): void
    {
        $accountId = $account instanceof Account ? $account->id : $account;
        foreach (['planCache', 'availableModulesCache', 'effectiveModulesCache'] as $property) {
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
        ]);
    }
}
