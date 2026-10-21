<?php

namespace App\Modules\WhatsApp\Services;

use App\Models\Account;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use Illuminate\Validation\ValidationException;

class ConnectionService
{
    /**
     * Create a new WhatsApp connection.
     */
    public function create(Account $account, array $data): WhatsAppConnection
    {
        $data['account_id'] = $account->id;
        $data['webhook_verify_token'] = WhatsAppConnection::generateVerifyToken();
        $data['api_version'] = $data['api_version'] ?? config('whatsapp.meta.api_version', 'v25.0');
        $data = $this->normalizeCampaignSafetySettings($data);

        // Encrypt access token if provided
        if (isset($data['access_token'])) {
            $connection = new WhatsAppConnection;
            $connection->access_token = $data['access_token'];
            unset($data['access_token']);
            $data['access_token_encrypted'] = $connection->access_token_encrypted;
        }

        return WhatsAppConnection::create($data);
    }

    /**
     * Prevent one active WhatsApp phone/Meta phone number from being attached
     * to multiple workspaces. Meta routes webhooks by phone_number_id, so a
     * duplicate active row can misroute inbox messages, calls, and automation.
     */
    public function ensurePhoneAvailable(Account $account, ?string $phoneNumberId, ?string $businessPhone = null, ?WhatsAppConnection $ignore = null): void
    {
        $phoneNumberId = trim((string) $phoneNumberId);
        $normalizedPhone = $this->normalizePhone($businessPhone);

        if ($phoneNumberId === '' && $normalizedPhone === '') {
            return;
        }

        $duplicate = WhatsAppConnection::query()
            ->with('account:id,name')
            ->where('is_active', true)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->where('account_id', '!=', $account->id)
            ->get()
            ->first(function (WhatsAppConnection $connection) use ($phoneNumberId, $normalizedPhone) {
                if ($phoneNumberId !== '' && (string) $connection->phone_number_id === $phoneNumberId) {
                    return true;
                }

                return $normalizedPhone !== '' && $this->normalizePhone($connection->business_phone) === $normalizedPhone;
            });

        if ($duplicate) {
            $workspace = $duplicate->account?->name ?: 'another workspace';
            throw ValidationException::withMessages([
                'phone_number_id' => "This WhatsApp number is already connected in {$workspace}. Disconnect it there before connecting it to another workspace.",
            ]);
        }
    }

    /**
     * Update a WhatsApp connection.
     */
    public function update(WhatsAppConnection $connection, array $data): WhatsAppConnection
    {
        // Handle access token update
        if (isset($data['access_token'])) {
            $connection->access_token = $data['access_token'];
            unset($data['access_token']);
        }

        if (array_key_exists('api_version', $data) && $data['api_version'] === null) {
            unset($data['api_version']);
        }

        $data = $this->normalizeCampaignSafetySettings($data);

        $connection->update($data);

        return $connection->fresh();
    }

    protected function normalizeCampaignSafetySettings(array $data): array
    {
        $start = isset($data['quiet_hours_start']) ? trim((string) $data['quiet_hours_start']) : null;
        $end = isset($data['quiet_hours_end']) ? trim((string) $data['quiet_hours_end']) : null;

        if ($start === '' || $end === '') {
            $start = $start === '' ? null : $start;
            $end = $end === '' ? null : $end;
        }

        if (($start && ! $end) || (! $start && $end)) {
            $data['quiet_hours_start'] = null;
            $data['quiet_hours_end'] = null;
        } else {
            $data['quiet_hours_start'] = $start;
            $data['quiet_hours_end'] = $end;
        }

        $tz = isset($data['quiet_hours_timezone']) ? trim((string) $data['quiet_hours_timezone']) : '';
        if ($tz === '') {
            $data['quiet_hours_timezone'] = config('app.timezone');
        }

        if (array_key_exists('throughput_cap_per_minute', $data) && (int) $data['throughput_cap_per_minute'] <= 0) {
            $data['throughput_cap_per_minute'] = null;
        }

        return $data;
    }

    protected function normalizePhone(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?: '';
    }

    /**
     * Rotate the webhook verify token.
     */
    public function rotateVerifyToken(WhatsAppConnection $connection): WhatsAppConnection
    {
        $connection->update([
            'webhook_verify_token' => WhatsAppConnection::generateVerifyToken(),
            'webhook_subscribed' => false, // Reset subscription status
        ]);

        return $connection->fresh();
    }

    /**
     * Get webhook URL for a connection.
     */
    public function getWebhookUrl(WhatsAppConnection $connection): string
    {
        // Ensure connection has a slug
        if (empty($connection->slug)) {
            $connection->slug = WhatsAppConnection::generateSlug($connection);
            $connection->save();
        }

        // Use slug for webhook URL (more secure and user-friendly)
        // Fallback to ID if slug is still empty (shouldn't happen)
        $identifier = $connection->slug ?? (string) $connection->id;

        // Generate full URL
        $url = route('webhooks.whatsapp.receive', [
            'connection' => $identifier]);

        // Log the generated URL for debugging
        \Log::channel('whatsapp')->debug('Webhook URL generated', [
            'connection_id' => $connection->id,
            'connection_slug' => $connection->slug,
            'identifier_used' => $identifier,
            'url' => $url,
        ]);

        return $url;
    }
}
