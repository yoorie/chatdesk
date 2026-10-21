<?php

namespace Tests;

use App\Core\Billing\SubscriptionService;
use App\Models\Account;
use App\Models\AccountUsage;
use App\Models\Plan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Error blades (@vite) must render in feature tests without a frontend build.
        $this->withoutVite();
    }

    /**
     * Act as account owner.
     */
    protected function actingAsAccountOwner(Account $account): User
    {
        $user = $account->owner;
        $this->actingAs($user);
        $this->withSession(['current_account_id' => $account->id]);

        return $user;
    }

    /**
     * Create account with plan.
     */
    protected function createAccountWithPlan(string $planKey, string $status = 'active'): Account
    {
        $plan = Plan::where('key', $planKey)->firstOrFail();
        $account = Account::factory()->create();

        $subscriptionService = app(SubscriptionService::class);

        if ($status === 'trialing' && $plan->trial_days > 0) {
            $subscriptionService->startTrial($account, $plan);
        } else {
            $subscriptionService->changePlan($account, $plan);
        }

        return $account->fresh();
    }

    /**
     * Set account usage for a period.
     */
    protected function setUsage(Account $account, string $period, int $messagesSent = 0, int $templateSends = 0, int $aiCredits = 0): AccountUsage
    {
        return AccountUsage::updateOrCreate(
            [
                'account_id' => $account->id,
                'period' => $period,
            ],
            [
                'messages_sent' => $messagesSent,
                'template_sends' => $templateSends,
                'ai_credits_used' => $aiCredits,
            ]
        );
    }

    /**
     * Set test time for month boundaries.
     */
    protected function setTestTime(string $dateTime): void
    {
        Carbon::setTestNow(Carbon::parse($dateTime));
    }

    /**
     * Reset test time.
     */
    protected function resetTestTime(): void
    {
        Carbon::setTestNow();
    }
}
