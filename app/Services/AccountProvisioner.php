<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountModule;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates the single organisation of a ChatDesk install with every module switched on.
 */
class AccountProvisioner
{
    public function create(User $owner, string $name, array $profile = []): Account
    {
        return DB::transaction(function () use ($owner, $name, $profile) {
            $account = Account::create([
                'name' => $name,
                'slug' => Account::generateSlug($name),
                'workspace_type' => $profile['workspace_type'] ?? 'business',
                'industry' => $profile['industry'] ?? null,
                'timezone' => $this->resolveTimezone($profile['timezone'] ?? null),
                'owner_id' => $owner->id,
            ]);

            Module::query()->pluck('key')->each(fn (string $key) => AccountModule::firstOrCreate(
                ['account_id' => $account->id, 'module_key' => $key],
                ['enabled' => true]
            ));

            return $account->fresh(['modules']);
        });
    }

    protected function resolveTimezone(?string $timezone): string
    {
        $timezone = trim((string) ($timezone ?: config('app.timezone', 'UTC')));

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'UTC';
    }
}
