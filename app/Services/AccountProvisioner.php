<?php

namespace App\Services;

use App\Core\Billing\PlanResolver;
use App\Core\Billing\SubscriptionService;
use App\Models\Account;
use App\Models\AccountModule;
use App\Models\Module;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccountProvisioner
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected PlanResolver $planResolver,
        protected PlatformSettingsService $settingsService
    ) {}

    public function publicPlans(): Collection
    {
        return Plan::where('is_public', true)
            ->where('is_active', true)
            ->where('key', '!=', 'enterprise')
            ->orderBy('sort_order')
            ->orderBy('price_monthly')
            ->get();
    }

    public function defaultPlanKey(): string
    {
        $candidates = array_filter([
            session('selected_plan_key'),
            config('billing.default_plan_key', 'starter'),
            'starter',
        ]);

        foreach ($candidates as $candidate) {
            $plan = Plan::where('key', $candidate)
                ->where('is_active', true)
                ->where('is_public', true)
                ->where('key', '!=', 'enterprise')
                ->first();

            if ($plan) {
                return $plan->key;
            }
        }

        return Plan::where('is_active', true)
            ->where('is_public', true)
            ->where('key', '!=', 'enterprise')
            ->whereNotNull('price_monthly')
            ->where('price_monthly', '>', 0)
            ->orderBy('sort_order')
            ->value('key')
            ?? Plan::where('is_active', true)->where('key', '!=', 'enterprise')->orderBy('sort_order')->value('key')
            ?? 'starter';
    }

    public function create(User $owner, string $name, ?string $planKey = null, array $profile = []): Account
    {
        return DB::transaction(function () use ($owner, $name, $planKey, $profile) {
            $account = Account::create([
                'name' => $name,
                'slug' => Account::generateSlug($name),
                'workspace_type' => $profile['workspace_type'] ?? 'business',
                'industry' => $profile['industry'] ?? null,
                'timezone' => $this->resolveWorkspaceTimezone($profile['timezone'] ?? null),
                'owner_id' => $owner->id,
            ]);

            Module::where('is_core', true)->get()->each(function (Module $module) use ($account) {
                AccountModule::firstOrCreate(
                    [
                        'account_id' => $account->id,
                        'module_key' => $module->key,
                    ],
                    ['enabled' => true]
                );
            });

            $plan = $this->resolvePlan($planKey ?? $this->defaultPlanKey(), $account, $owner);

            if ($plan->trial_days > 0 && $this->subscriptionService->canStartSelfServiceTrial($account, $owner)) {
                $this->subscriptionService->startTrial($account, $plan, $owner);
            } else {
                $this->subscriptionService->changePlan($account, $plan, $owner, null, [
                    'skip_proration' => true,
                    'source' => 'workspace_creation',
                    'trial_skipped' => $plan->trial_days > 0,
                    'suppress_trial' => $plan->trial_days > 0,
                ]);
            }

            $this->provisionAvailableModules($account->fresh('subscription'));

            return $account->fresh(['subscription', 'modules']);
        });
    }

    protected function provisionAvailableModules(Account $account): void
    {
        // Open-source edition: every bundled module is available to the organisation.
        $moduleKeys = array_unique(array_merge(
            $this->planResolver->getAvailableModuleKeys($account),
            Module::query()->pluck('key')->all()
        ));

        foreach ($moduleKeys as $moduleKey) {
            AccountModule::firstOrCreate(
                [
                    'account_id' => $account->id,
                    'module_key' => $moduleKey,
                ],
                ['enabled' => true]
            );
        }
    }

    protected function resolveWorkspaceTimezone(?string $timezone): string
    {
        $timezone = trim((string) ($timezone ?: config('app.timezone', 'UTC')));

        if (in_array($timezone, timezone_identifiers_list(), true)) {
            return $timezone;
        }

        return 'Asia/Kolkata';
    }

    public function serializePlan(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'key' => $plan->key,
            'name' => $plan->name,
            'description' => $plan->description,
            'price_monthly' => $plan->price_monthly,
            'price_yearly' => $plan->price_yearly,
            'currency' => strtoupper((string) ($plan->currency ?: $this->settingsService->get('payment.default_currency', 'INR'))),
            'trial_days' => $plan->trial_days ?? 0,
            'limits' => $plan->limits ?? [],
            'modules' => $plan->modules ?? [],
            'features' => $plan->publicFeatures(),
        ];
    }

    protected function resolvePlan(string $planKey, Account $account, User $owner): Plan
    {
        $plan = Plan::where('key', $planKey)
            ->where('is_active', true)
            ->where('is_public', true)
            ->where('key', '!=', 'enterprise')
            ->first();

        if (! $plan) {
            $plan = Plan::where('key', config('billing.default_plan_key', 'starter'))
                ->where('is_active', true)
                ->where('key', '!=', 'enterprise')
                ->first();
        }

        if (! $plan) {
            $plan = Plan::where('is_active', true)
                ->where('is_public', true)
                ->where('key', '!=', 'enterprise')
                ->whereNotNull('price_monthly')
                ->where('price_monthly', '>', 0)
                ->orderBy('sort_order')
                ->first();
        }

        if (! $plan) {
            $plan = Plan::where('is_active', true)
                ->where('is_public', true)
                ->where('key', '!=', 'enterprise')
                ->orderBy('price_monthly')
                ->orderBy('sort_order')
                ->first();
        }

        if (! $plan) {
            $plan = Plan::where('is_active', true)
                ->where('key', '!=', 'enterprise')
                ->orderBy('sort_order')
                ->first();
        }

        if ($plan) {
            return $plan;
        }

        Log::warning('No plan found during workspace creation, creating default starter plan', [
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'selected_plan_key' => $planKey,
        ]);

        return Plan::create([
            'key' => 'starter',
            'name' => 'Starter',
            'description' => 'For small teams',
            'price_monthly' => 99900,
            'price_yearly' => 999000,
            'currency' => strtoupper((string) ($this->settingsService->get('payment.default_currency', 'INR') ?: 'INR')),
            'is_active' => true,
            'is_public' => true,
            'trial_days' => 7,
            'sort_order' => 1,
            'limits' => [
                'whatsapp_connections' => 1,
                'agents' => 3,
                'messages_monthly' => 5000,
                'template_sends_monthly' => 1000,
                'ai_credits_monthly' => 0,
                'retention_days' => 90,
            ],
            'modules' => ['whatsapp.cloud', 'templates', 'contacts', 'broadcasts'],
        ]);
    }
}
