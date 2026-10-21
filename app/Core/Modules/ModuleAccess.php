<?php

namespace App\Core\Modules;

use App\Models\Account;
use App\Models\AccountModule;
use App\Models\Module;

/**
 * Which modules the organisation can use.
 *
 * There are no plans or limits in the open-source edition: every module enabled
 * on the install is available, and an admin can switch one off for the
 * organisation (AccountModule.enabled = false).
 */
class ModuleAccess
{
    protected array $cache = [];

    /** Module keys enabled on this install. */
    public function getAvailableModuleKeys(Account $account): array
    {
        return $this->cache['available'] ??= Module::where('is_enabled', true)->pluck('key')->values()->all();
    }

    /** Module keys the organisation can use right now. */
    public function getEffectiveModules(Account $account): array
    {
        return $this->cache['effective:'.$account->id] ??= array_values(array_diff(
            $this->getAvailableModuleKeys($account),
            AccountModule::where('account_id', $account->id)->where('enabled', false)->pluck('module_key')->all()
        ));
    }
}
