<?php

namespace App\Core\Billing;

use App\Models\Account;
use App\Models\AccountUsage;
use Carbon\Carbon;

class UsageService
{
    /**
     * Get current period string (e.g., "2026-01").
     */
    public function getCurrentPeriod(?string $timezone = null): string
    {
        $now = $timezone ? Carbon::now($timezone) : Carbon::now();

        return $now->format('Y-m');
    }

    /**
     * Get or create usage record for current period.
     */
    public function getCurrentUsage(Account $account): AccountUsage
    {
        return $this->reconcileMessageUsage($account, $this->usageRecord($account));
    }

    protected function usageRecord(Account $account): AccountUsage
    {
        return AccountUsage::firstOrCreate(
            [
                'account_id' => $account->id,
                'period' => $this->getCurrentPeriod(),
            ],
            [
                'messages_sent' => 0,
                'template_sends' => 0,
                'ai_credits_used' => 0,
                'ai_requests' => 0,
                'ai_estimated_tokens' => 0,
                'ai_estimated_cost_minor' => 0,
                'razorpay_payment_links_created' => 0,
                'razorpay_payment_links_paid' => 0,
                'meta_conversations_free_used' => 0,
                'meta_conversations_paid' => 0,
                'meta_conversations_marketing' => 0,
                'meta_conversations_utility' => 0,
                'meta_conversations_authentication' => 0,
                'meta_conversations_service' => 0,
                'meta_estimated_cost_minor' => 0,
                'storage_bytes' => 0,
            ]
        );
    }

    protected function reconcileMessageUsage(Account $account, AccountUsage $usage): AccountUsage
    {
        [$start, $end] = $this->periodBounds($usage->period);

        $actualMessagesSent = \App\Modules\WhatsApp\Models\WhatsAppMessage::where('account_id', $account->id)
            ->where('direction', 'outbound')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $updates = [];
        if ($actualMessagesSent !== (int) $usage->messages_sent) {
            $updates['messages_sent'] = $actualMessagesSent;
        }

        $actualTemplateSends = \App\Modules\WhatsApp\Models\WhatsAppTemplateSend::where('account_id', $account->id)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        if ($actualTemplateSends !== (int) $usage->template_sends) {
            $updates['template_sends'] = $actualTemplateSends;
        }

        if ($updates !== []) {
            $usage->forceFill($updates)->save();
            $usage->refresh();
        }

        return $usage;
    }

    protected function periodBounds(string $period): array
    {
        try {
            $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        } catch (\Throwable) {
            $start = Carbon::now()->startOfMonth();
        }

        return [$start->copy(), $start->copy()->endOfMonth()];
    }

    /**
     * Increment messages sent count.
     */
    public function incrementMessages(Account $account, int $count = 1): void
    {
        $usage = $this->usageRecord($account);
        $usage->increment('messages_sent', $count);
    }

    public function incrementMessageUsage(Account $account, int $count = 1): void
    {
        $this->incrementMessages($account, $count);
    }

    /**
     * Increment template sends count.
     */
    public function incrementTemplateSends(Account $account, int $count = 1): void
    {
        $usage = $this->usageRecord($account);
        $usage->increment('template_sends', $count);
    }

    public function incrementTemplateUsage(Account $account, int $count = 1): void
    {
        $this->incrementTemplateSends($account, $count);
    }

    /**
     * Increment AI credits used.
     */
    public function incrementAiCredits(Account $account, int $count): void
    {
        $usage = $this->usageRecord($account);
        $usage->increment('ai_credits_used', $count);
    }

    public function recordAiRequest(Account $account, int $estimatedTokens = 0, int $estimatedCostMinor = 0, bool $chargeCredits = false): void
    {
        $usage = $this->usageRecord($account);
        $usage->increment('ai_requests');

        if ($estimatedTokens > 0) {
            $usage->increment('ai_estimated_tokens', $estimatedTokens);
        }

        if ($estimatedCostMinor > 0) {
            $usage->increment('ai_estimated_cost_minor', $estimatedCostMinor);
        }

        if ($chargeCredits) {
            $usage->increment('ai_credits_used', max(1, (int) ceil(max(1, $estimatedTokens) / 1000)));
        }
    }

    public function incrementRazorpayPaymentLinksCreated(Account $account, int $count = 1): void
    {
        $this->usageRecord($account)->increment('razorpay_payment_links_created', $count);
    }

    public function incrementRazorpayPaymentLinksPaid(Account $account, int $count = 1): void
    {
        $this->usageRecord($account)->increment('razorpay_payment_links_paid', $count);
    }

    /**
     * Track Meta conversation billing counters (estimated).
     */
    public function incrementMetaConversationUsage(
        Account $account,
        bool $billable,
        ?string $category = null,
        int $estimatedCostMinor = 0
    ): void {
        $usage = $this->usageRecord($account);

        if ($billable) {
            $usage->increment('meta_conversations_paid', 1);
        } else {
            $usage->increment('meta_conversations_free_used', 1);
        }

        $normalizedCategory = strtolower(trim((string) $category));
        $categoryColumn = match ($normalizedCategory) {
            'marketing' => 'meta_conversations_marketing',
            'utility' => 'meta_conversations_utility',
            'authentication' => 'meta_conversations_authentication',
            'service' => 'meta_conversations_service',
            default => null,
        };

        if ($categoryColumn) {
            $usage->increment($categoryColumn, 1);
        }

        if ($estimatedCostMinor > 0) {
            $usage->increment('meta_estimated_cost_minor', $estimatedCostMinor);
        }
    }

    /**
     * Get usage for a specific period.
     */
    public function getUsageForPeriod(Account $account, string $period): ?AccountUsage
    {
        return AccountUsage::where('account_id', $account->id)
            ->where('period', $period)
            ->first();
    }

    /**
     * Get usage history (last N periods).
     */
    public function getUsageHistory(Account $account, int $months = 3): array
    {
        $periods = [];
        $now = Carbon::now();

        for ($i = 0; $i < $months; $i++) {
            $period = $now->copy()->subMonths($i)->format('Y-m');
            $usage = $this->getUsageForPeriod($account, $period);

            $periods[] = [
                'period' => $period,
                'messages_sent' => $usage?->messages_sent ?? 0,
                'template_sends' => $usage?->template_sends ?? 0,
                'ai_credits_used' => $usage?->ai_credits_used ?? 0,
                'ai_requests' => $usage?->ai_requests ?? 0,
                'ai_estimated_tokens' => $usage?->ai_estimated_tokens ?? 0,
                'ai_estimated_cost_minor' => $usage?->ai_estimated_cost_minor ?? 0,
                'razorpay_payment_links_created' => $usage?->razorpay_payment_links_created ?? 0,
                'razorpay_payment_links_paid' => $usage?->razorpay_payment_links_paid ?? 0,
                'meta_conversations_free_used' => $usage?->meta_conversations_free_used ?? 0,
                'meta_conversations_paid' => $usage?->meta_conversations_paid ?? 0,
                'meta_conversations_marketing' => $usage?->meta_conversations_marketing ?? 0,
                'meta_conversations_utility' => $usage?->meta_conversations_utility ?? 0,
                'meta_conversations_authentication' => $usage?->meta_conversations_authentication ?? 0,
                'meta_conversations_service' => $usage?->meta_conversations_service ?? 0,
                'meta_estimated_cost_minor' => $usage?->meta_estimated_cost_minor ?? 0,
            ];
        }

        return $periods;
    }
}
