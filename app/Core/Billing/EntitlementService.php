<?php

namespace App\Core\Billing;

use App\Models\Account;
use App\Models\BillingEvent;

class EntitlementService
{
    public function __construct(
        protected PlanResolver $planResolver,
        protected UsageService $usageService
    ) {}

    /**
     * Assert that a module is enabled for the account.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function assertModuleEnabled(Account $account, string $moduleKey): void
    {
        // Open-source edition: no plan entitlements.
    }

    /**
     * Assert that account is within a limit.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function assertWithinLimit(Account $account, string $limitKey, int $intendedIncrement = 1): void
    {
        // Open-source edition: no plan limits.
    }

    /**
     * Check if account can create an agent.
     */
    public function canCreateAgent(Account $account): bool
    {
        return true; // Open-source edition: no plan limits.
    }

    /**
     * Check if account can create a WhatsApp connection.
     */
    public function canCreateConnection(Account $account): bool
    {
        return true; // Open-source edition: no plan limits.
    }

    /**
     * Check if account can send a message.
     */
    public function canSendMessage(Account $account): bool
    {
        return true; // Open-source edition: no plan limits.
    }

    /**
     * Check if account can send a template.
     */
    public function canSendTemplate(Account $account): bool
    {
        return true; // Open-source edition: no plan limits.
    }
}
