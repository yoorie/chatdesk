<?php

namespace App\Services;

use App\Core\Billing\UsageService;
use App\Models\Account;
use App\Models\AccountIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RazorpayPaymentLinkService
{
    private string $baseUrl = 'https://api.razorpay.com/v1';

    public function isEnabled(?Account $account = null): bool
    {
        if (! $account) {
            return false;
        }

        try {
            $this->credentialsFor($account);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function createForAccount(Account $account, array $data): array
    {
        $credentials = $this->credentialsFor($account);

        $amount = (int) ($data['amount'] ?? 0);
        if ($amount < 100) {
            throw new \RuntimeException('Razorpay payment links require at least INR 1.00.');
        }

        $currency = strtoupper((string) ($data['currency'] ?? $credentials['currency'] ?? 'INR'));
        if ($currency !== 'INR') {
            throw new \RuntimeException('Razorpay payment links currently support INR only.');
        }

        $referenceId = substr((string) ($data['reference_id'] ?? "zyptos_{$account->id}_".time()), 0, 40);
        $description = trim((string) ($data['description'] ?? (brand_name().' payment request')));
        $customerName = trim((string) ($data['customer_name'] ?? 'Customer'));
        $customerPhone = $this->normalizePhone($data['customer_phone'] ?? null);
        $customerEmail = $this->normalizeEmail($data['customer_email'] ?? null);

        $payload = [
            'amount' => $amount,
            'currency' => 'INR',
            'accept_partial' => false,
            'reference_id' => $referenceId,
            'description' => $description,
            'callback_url' => $data['callback_url'] ?? route('landing'),
            'callback_method' => 'get',
            'customer' => array_filter([
                'name' => $customerName ?: 'Customer',
                'contact' => $customerPhone,
                'email' => $customerEmail,
            ]),
            'notify' => [
                'sms' => false,
                'email' => false,
            ],
            'reminder_enable' => false,
            'notes' => array_filter([
                'account_id' => (string) $account->id,
                'account_slug' => $account->slug,
                ...($data['notes'] ?? []),
            ]),
        ];

        if (! empty($data['expire_by'])) {
            $payload['expire_by'] = is_numeric($data['expire_by'])
                ? (int) $data['expire_by']
                : \Illuminate\Support\Carbon::parse($data['expire_by'])->timestamp;
        }

        $response = Http::withBasicAuth($credentials['key_id'], $credentials['key_secret'])
            ->acceptJson()
            ->timeout(20)
            ->post("{$this->baseUrl}/payment_links", $payload);

        $result = $response->json() ?: [];
        if (! $response->successful()) {
            Log::channel('stack')->error('Razorpay payment link creation failed', [
                'account_id' => $account->id,
                'status' => $response->status(),
                'error' => $result,
            ]);

            throw new \RuntimeException($result['error']['description'] ?? 'Unable to create Razorpay payment link.');
        }

        app(UsageService::class)->incrementRazorpayPaymentLinksCreated($account);

        return $result;
    }

    private function credentialsFor(Account $account): array
    {
        $integration = AccountIntegration::query()
            ->where('account_id', $account->id)
            ->where('provider', 'razorpay-payments')
            ->where('status', 'connected')
            ->first();

        if (! $integration) {
            throw new \RuntimeException("Connect Razorpay Payments in the {$account->name} workspace integrations before creating customer payment links.");
        }

        $keyId = trim((string) $integration->configValue('key_id'));
        $keySecret = $integration->secret('key_secret');

        if ($keyId === '' || ! $keySecret) {
            throw new \RuntimeException('Workspace Razorpay key ID or key secret is missing.');
        }

        if (app()->environment('production') && str_starts_with($keyId, 'rzp_test_')) {
            throw new \RuntimeException('Workspace Razorpay is using test keys. Add live Razorpay keys starting with rzp_live_ before sending real customer payment links.');
        }

        return [
            'key_id' => $keyId,
            'key_secret' => $keySecret,
            'currency' => strtoupper((string) ($integration->configValue('currency', 'INR') ?: 'INR')),
        ];
    }

    private function normalizePhone(mixed $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return $digits !== '' ? $digits : null;
    }

    private function normalizeEmail(mixed $email): ?string
    {
        $email = trim((string) $email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
