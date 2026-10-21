<?php

namespace App\Core\Billing;

use App\Models\Account;

/**
 * ChatDesk open-source edition: internal unlimited mode.
 *
 * Every feature bundled in this edition is available and nothing is metered or
 * capped. The methods stay so module code can keep asking "may I?" in one place.
 */
class EntitlementService
{
    public function assertModuleEnabled(Account $account, string $moduleKey): void
    {
        // No plan entitlements in the open-source edition.
    }

    public function assertWithinLimit(Account $account, string $limitKey, int $intendedIncrement = 1): void
    {
        // No plan limits in the open-source edition.
    }

    public function canCreateAgent(Account $account): bool
    {
        return true;
    }

    public function canCreateConnection(Account $account): bool
    {
        return true;
    }

    public function canSendMessage(Account $account): bool
    {
        return true;
    }

    public function canSendTemplate(Account $account): bool
    {
        return true;
    }
}
