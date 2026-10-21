<?php

namespace Tests;

use App\Models\Account;
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
    protected function createAccount(): Account
    {
        return Account::factory()->create()->fresh();
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
