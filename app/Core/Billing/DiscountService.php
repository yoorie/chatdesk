<?php

namespace App\Core\Billing;

use App\Models\BillingDiscount;
use App\Models\Account;
use App\Models\PaymentOrder;
use App\Models\Plan;
use App\Models\Subscription;

class DiscountService
{
    public function findRedeemable(?string $code, Plan $plan, string $currency, ?Account $account = null): ?BillingDiscount
    {
        $code = trim((string) $code);
        if ($code === '') {
            return null;
        }

        $discount = BillingDiscount::whereRaw('LOWER(code) = ?', [strtolower($code)])->first();
        if (! $discount || ! $discount->isRedeemableFor($plan, $currency)) {
            return null;
        }

        if ($discount->isNewUserOnly() && (! $account || ! $this->isNewCustomerAccount($account))) {
            return null;
        }

        return $discount;
    }

    public function preview(?string $code, Plan $plan, int $amountMinor, string $currency, ?Account $account = null): ?array
    {
        $discount = $this->findRedeemable($code, $plan, $currency, $account);
        if (! $discount) {
            return null;
        }

        $amountOff = $discount->calculateAmountOff($amountMinor);

        return [
            'code' => $discount->code,
            'name' => $discount->name,
            'discount_type' => $discount->discount_type,
            'percent_off' => $discount->percent_off,
            'amount_off' => $discount->amount_off,
            'duration' => $discount->duration,
            'duration_cycles' => $discount->duration_cycles,
            'new_user_only' => $discount->isNewUserOnly(),
            'amount_off_minor' => $amountOff,
            'amount_due_minor' => max(0, $amountMinor - $amountOff),
        ];
    }

    public function redeem(BillingDiscount $discount): void
    {
        $discount->increment('redemptions');
    }

    protected function isNewCustomerAccount(Account $account): bool
    {
        $ownerEmail = strtolower(trim((string) $account->owner?->email));
        $accountIds = Account::query()
            ->when($ownerEmail !== '', fn ($query) => $query->whereHas('owner', fn ($owner) => $owner->whereRaw('LOWER(email) = ?', [$ownerEmail])))
            ->when($ownerEmail === '', fn ($query) => $query->whereKey($account->id))
            ->pluck('id');

        if ($accountIds->isEmpty()) {
            return true;
        }

        $hasPaidOrder = PaymentOrder::whereIn('account_id', $accountIds)
            ->where('status', 'paid')
            ->exists();

        if ($hasPaidOrder) {
            return false;
        }

        return ! Subscription::whereIn('account_id', $accountIds)
            ->where(function ($query) {
                $query->whereNotNull('last_payment_at')
                    ->orWhereNotNull('provider_ref');
            })
            ->exists();
    }
}
