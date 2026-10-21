<?php

namespace App\Services;

use App\Core\Billing\SubscriptionService;
use App\Models\Account;
use App\Models\AccountModule;
use App\Models\Module;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates the single organisation of a ChatDesk open-source install and puts it on
 * the internal unlimited plan (see App\Core\Billing\SubscriptionService).
 */
class AccountProvisioner
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
    ) {}

    public function create(User $owner, string $name, ?string $planKey = null, array $profile = []): Account
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

            $this->subscriptionService->changePlan($account, $this->internalPlan(), $owner);

            // Every bundled module is available to the organisation.
            Module::query()->get()->each(function (Module $module) use ($account) {
                AccountModule::firstOrCreate(
                    ['account_id' => $account->id, 'module_key' => $module->key],
                    ['enabled' => true]
                );
            });

            return $account->fresh(['subscription', 'modules']);
        });
    }

    protected function internalPlan(): Plan
    {
        $plan = Plan::where('key', 'self-hosted')->first();
        if (! $plan) {
            (new \Database\Seeders\PlanSeeder)->run();
            $plan = Plan::where('key', 'self-hosted')->firstOrFail();
        }

        return $plan;
    }

    protected function resolveTimezone(?string $timezone): string
    {
        $timezone = trim((string) ($timezone ?: config('app.timezone', 'UTC')));

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'UTC';
    }
}
