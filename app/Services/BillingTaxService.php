<?php

namespace App\Services;

use App\Models\Account;
use App\Models\PaymentOrder;
use App\Models\PlatformSetting;

class BillingTaxService
{
    /**
     * $creditAmount is the prorated upgrade credit (unused days of the current
     * paid plan). It reduces the taxable value, so GST is charged on the net.
     */
    public function quote(Account $account, int $baseAmount, int $discountAmount = 0, string $currency = 'INR', int $creditAmount = 0): array
    {
        $baseAmount = max(0, $baseAmount);
        $discountAmount = min(max(0, $discountAmount), $baseAmount);
        $creditAmount = min(max(0, $creditAmount), $baseAmount - $discountAmount);
        $taxableAmount = max(0, $baseAmount - $discountAmount - $creditAmount);
        $taxRate = (float) PlatformSetting::get('payment.tax_rate', 0);
        $taxAmount = (int) round($taxableAmount * ($taxRate / 100));
        $supplier = $this->supplierProfile();
        $customer = $this->customerProfile($account);
        $isIndia = strtoupper($currency) === 'INR'
            && strtoupper((string) ($supplier['country'] ?? 'IN')) === 'IN'
            && strtoupper((string) ($customer['country'] ?? 'IN')) === 'IN';
        $sameState = $isIndia
            && $this->normalizeStateCode($supplier['state_code'] ?? null) !== ''
            && $this->normalizeStateCode($supplier['state_code'] ?? null) === $this->normalizeStateCode($customer['state_code'] ?? null);

        $cgst = 0;
        $sgst = 0;
        $igst = 0;

        if ($taxAmount > 0 && $isIndia) {
            if ($sameState) {
                $cgst = intdiv($taxAmount, 2);
                $sgst = $taxAmount - $cgst;
            } else {
                $igst = $taxAmount;
            }
        } else {
            $igst = $taxAmount;
        }

        return [
            'currency' => strtoupper($currency),
            'base_amount' => $baseAmount,
            'discount_amount' => $discountAmount,
            'credit_amount' => $creditAmount,
            'taxable_amount' => $taxableAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'cgst_amount' => $cgst,
            'sgst_amount' => $sgst,
            'igst_amount' => $igst,
            'total_amount' => $taxableAmount + $taxAmount,
            'tax_type' => $cgst || $sgst ? 'CGST_SGST' : ($igst ? 'IGST' : 'NONE'),
            'sac_code' => PlatformSetting::get('payment.sac_code', '998313'),
            'supplier' => $supplier,
            'customer' => $customer,
        ];
    }

    public function nextInvoiceNumber(): string
    {
        $prefix = (string) PlatformSetting::get('payment.invoice_prefix', 'INV-');
        $start = max(1, (int) PlatformSetting::get('payment.invoice_number_start', 1));
        $next = $start + PaymentOrder::whereNotNull('invoice_number')->count();

        do {
            $invoiceNumber = $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
            $next++;
        } while (PaymentOrder::where('invoice_number', $invoiceNumber)->exists());

        return $invoiceNumber;
    }

    public function orderTaxFields(array $quote): array
    {
        return [
            'invoice_number' => $this->nextInvoiceNumber(),
            'amount' => (int) $quote['total_amount'],
            'base_amount' => (int) $quote['base_amount'],
            'discount_amount' => (int) $quote['discount_amount'],
            'credit_amount' => (int) ($quote['credit_amount'] ?? 0),
            'taxable_amount' => (int) $quote['taxable_amount'],
            'tax_amount' => (int) $quote['tax_amount'],
            'tax_rate' => (float) $quote['tax_rate'],
            'cgst_amount' => (int) $quote['cgst_amount'],
            'sgst_amount' => (int) $quote['sgst_amount'],
            'igst_amount' => (int) $quote['igst_amount'],
            'tax_snapshot' => $quote,
        ];
    }

    public function supplierProfile(): array
    {
        return [
            'legal_name' => PlatformSetting::get('payment.legal_name', config('app.name', 'ChatDesk')),
            'gstin' => PlatformSetting::get('payment.gstin'),
            'address_line1' => PlatformSetting::get('payment.address_line1'),
            'address_line2' => PlatformSetting::get('payment.address_line2'),
            'city' => PlatformSetting::get('payment.city'),
            'state' => PlatformSetting::get('payment.state'),
            'state_code' => PlatformSetting::get('payment.state_code'),
            'postal_code' => PlatformSetting::get('payment.postal_code'),
            'country' => PlatformSetting::get('payment.country', 'IN'),
        ];
    }

    public function customerProfile(Account $account): array
    {
        return [
            'legal_name' => $account->billing_name ?: $account->name,
            'email' => $account->billing_email ?: $account->owner?->email,
            'gstin' => $account->billing_gstin,
            'address_line1' => $account->billing_address_line1,
            'address_line2' => $account->billing_address_line2,
            'city' => $account->billing_city,
            'state' => $account->billing_state,
            'state_code' => $account->billing_state_code,
            'postal_code' => $account->billing_postal_code,
            'country' => $account->billing_country ?: 'IN',
        ];
    }

    private function normalizeStateCode(mixed $value): string
    {
        return strtoupper(trim((string) $value));
    }
}
