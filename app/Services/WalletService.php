<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountWallet;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function getOrCreateWallet(Account $account, ?string $currency = null): AccountWallet
    {
        $resolvedCurrency = strtoupper($currency ?: (string) app(PlatformSettingsService::class)->get('payment.default_currency', 'INR'));

        return AccountWallet::firstOrCreate(
            ['account_id' => $account->id],
            [
                'balance_minor' => 0,
                'currency' => $resolvedCurrency,
                'is_active' => true,
            ]
        );
    }

    public function credit(
        Account $account,
        int $amountMinor,
        string $source,
        ?User $actor = null,
        ?string $reference = null,
        ?string $notes = null,
        array $meta = []
    ): WalletTransaction {
        if ($amountMinor <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive.');
        }

        return DB::transaction(function () use ($account, $amountMinor, $source, $actor, $reference, $notes, $meta) {
            $wallet = $this->getOrCreateWallet($account);
            $wallet = AccountWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $wallet->balance_minor += $amountMinor;
            $wallet->save();

            return WalletTransaction::create([
                'account_id' => $account->id,
                'account_wallet_id' => $wallet->id,
                'actor_id' => $actor?->id,
                'direction' => 'credit',
                'amount_minor' => $amountMinor,
                'balance_after_minor' => $wallet->balance_minor,
                'currency' => $wallet->currency,
                'source' => $source,
                'status' => 'success',
                'reference' => $reference,
                'notes' => $notes,
                'meta' => $meta,
            ]);
        });
    }

    public function debit(
        Account $account,
        int $amountMinor,
        string $source,
        ?User $actor = null,
        ?string $reference = null,
        ?string $notes = null,
        array $meta = [],
        bool $allowNegative = false
    ): WalletTransaction {
        if ($amountMinor <= 0) {
            throw new \InvalidArgumentException('Debit amount must be positive.');
        }

        return DB::transaction(function () use ($account, $amountMinor, $source, $actor, $reference, $notes, $meta, $allowNegative) {
            $wallet = $this->getOrCreateWallet($account);
            $wallet = AccountWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            if (! $allowNegative && $wallet->balance_minor < $amountMinor) {
                return WalletTransaction::create([
                    'account_id' => $account->id,
                    'account_wallet_id' => $wallet->id,
                    'actor_id' => $actor?->id,
                    'direction' => 'debit',
                    'amount_minor' => $amountMinor,
                    'balance_after_minor' => $wallet->balance_minor,
                    'currency' => $wallet->currency,
                    'source' => $source,
                    'status' => 'failed',
                    'reference' => $reference,
                    'notes' => $notes ?: 'Insufficient wallet balance.',
                    'meta' => $meta + ['reason' => 'insufficient_balance'],
                ]);
            }

            $wallet->balance_minor -= $amountMinor;
            $wallet->save();

            return WalletTransaction::create([
                'account_id' => $account->id,
                'account_wallet_id' => $wallet->id,
                'actor_id' => $actor?->id,
                'direction' => 'debit',
                'amount_minor' => $amountMinor,
                'balance_after_minor' => $wallet->balance_minor,
                'currency' => $wallet->currency,
                'source' => $source,
                'status' => 'success',
                'reference' => $reference,
                'notes' => $notes,
                'meta' => $meta,
            ]);
        });
    }
}
