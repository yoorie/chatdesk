<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Exceptions\WhatsAppApiException;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TemplateSyncService
{
    protected string $baseUrl;

    protected ?bool $connectionHasSyncColumns = null;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('whatsapp.meta.base_url', 'https://graph.facebook.com'), '/');
    }

    /**
     * Sync templates from Meta for a connection.
     * Uses cache lock to prevent concurrent syncs.
     */
    public function sync(WhatsAppConnection $connection): array
    {
        // Use cache lock to prevent concurrent syncs for the same connection
        $lockKey = "template_sync:connection:{$connection->id}";
        $lock = Cache::lock($lockKey, 300); // 5 minute lock

        if (! $lock->get()) {
            throw new \Exception('Template sync is already in progress for this connection. Please wait.');
        }

        try {
            return $this->performSync($connection);
        } finally {
            $lock->release();
        }
    }

    /**
     * Perform the actual sync operation.
     */
    protected function performSync(WhatsAppConnection $connection): array
    {
        $wabaId = $connection->waba_id;
        if (! $wabaId) {
            throw new \Exception('WABA ID is required to sync templates');
        }

        $url = sprintf(
            '%s/%s/%s/message_templates',
            $this->baseUrl,
            $connection->api_version ?: config('whatsapp.meta.api_version', 'v25.0'),
            $wabaId
        );

        $allTemplates = [];
        $nextPageToken = null;

        do {
            $params = [];

            if ($nextPageToken) {
                $params['after'] = $nextPageToken;
            }

            try {
                // Rate limiting: Check if we need to wait
                $this->rateLimitCheck($connection->id);

                $response = Http::withToken($connection->access_token)->get($url, $params);
                $responseData = $response->json();

                if (! $response->successful()) {
                    $errorMessage = $responseData['error']['message'] ?? 'Unknown error from WhatsApp API';
                    $errorCode = $responseData['error']['code'] ?? $response->status();

                    // Handle rate limiting
                    if ($errorCode === 4 || str_contains($errorMessage, 'rate limit')) {
                        Log::channel('whatsapp')->warning('Rate limit hit during template sync', [
                            'connection_id' => $connection->id]);
                        throw new WhatsAppApiException(
                            'Rate limit exceeded. Please wait before syncing again.',
                            $responseData,
                            $errorCode
                        );
                    }

                    Log::channel('whatsapp')->error('Template sync API error', [
                        'connection_id' => $connection->id,
                        'waba_id' => $wabaId,
                        'error' => $responseData['error'] ?? [],
                        'status' => $response->status()]);

                    throw new WhatsAppApiException(
                        "WhatsApp API error: {$errorMessage}",
                        $responseData,
                        $errorCode
                    );
                }

                $templates = $responseData['data'] ?? [];
                $allTemplates = array_merge($allTemplates, $templates);

                $paging = $responseData['paging'] ?? [];
                $nextPageToken = $paging['cursors']['after'] ?? null;

                // Small delay between pages to avoid rate limits
                if ($nextPageToken) {
                    usleep(500000); // 0.5 second delay
                }
            } catch (WhatsAppApiException $e) {
                throw $e;
            } catch (\Exception $e) {
                Log::channel('whatsapp')->error('Unexpected error syncing templates', [
                    'connection_id' => $connection->id,
                    'error' => $e->getMessage()]);

                throw new WhatsAppApiException(
                    "Failed to sync templates: {$e->getMessage()}",
                    [],
                    0,
                    $e
                );
            }
        } while ($nextPageToken);

        // Process and upsert templates with transaction
        $created = 0;
        $updated = 0;
        $errors = [];

        \DB::transaction(function () use ($connection, $allTemplates, &$created, &$updated, &$errors) {
            foreach ($allTemplates as $templateData) {
                try {
                    $existing = WhatsAppTemplate::where('account_id', $connection->account_id)
                        ->where('whatsapp_connection_id', $connection->id)
                        ->where('meta_template_id', $templateData['id'] ?? null)
                        ->first();

                    $this->upsertTemplate($connection, $templateData);

                    if ($existing) {
                        $updated++;
                    } else {
                        $created++;
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'template' => $templateData['name'] ?? 'Unknown',
                        'error' => $e->getMessage()];
                    Log::channel('whatsapp')->error('Failed to upsert template', [
                        'connection_id' => $connection->id,
                        'template_data' => $templateData,
                        'error' => $e->getMessage()]);
                }
            }
        });

        $this->persistConnectionSyncState($connection, $errors);

        return [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
            'total' => count($allTemplates)];
    }

    /**
     * Check and enforce rate limiting.
     */
    protected function rateLimitCheck(int $connectionId): void
    {
        $rateLimitKey = "template_sync_rate_limit:connection:{$connectionId}";
        $requests = Cache::get($rateLimitKey, 0);

        // Allow max 10 requests per minute per connection
        if ($requests >= 10) {
            $ttl = Cache::get($rateLimitKey.':ttl', 60);
            throw new \Exception("Rate limit: Maximum 10 sync requests per minute. Please wait {$ttl} seconds.");
        }

        // Increment counter
        Cache::put($rateLimitKey, $requests + 1, 60);
        Cache::put($rateLimitKey.':ttl', 60 - (now()->second), 60);
    }

    /**
     * Upsert a template from Meta data.
     */
    protected function upsertTemplate(WhatsAppConnection $connection, array $templateData): WhatsAppTemplate
    {
        $name = $templateData['name'] ?? '';
        $language = $templateData['language'] ?? 'en_US';
        $status = strtolower($templateData['status'] ?? 'unknown');
        $category = $templateData['category'] ?? '';

        // Extract components
        $components = $templateData['components'] ?? [];
        $bodyText = null;
        $headerType = null;
        $headerText = null;
        $footerText = null;
        $buttons = [];

        foreach ($components as $component) {
            $type = strtoupper($component['type'] ?? '');

            if ($type === 'BODY') {
                $bodyText = $component['text'] ?? '';
            } elseif ($type === 'HEADER') {
                $headerType = strtoupper($component['format'] ?? 'TEXT');
                if ($headerType === 'TEXT') {
                    $headerText = $component['text'] ?? '';
                }
            } elseif ($type === 'FOOTER') {
                $footerText = $component['text'] ?? '';
            } elseif ($type === 'BUTTONS') {
                $buttons = $component['buttons'] ?? [];
            }
        }

        // Normalize buttons for UI
        $normalizedButtons = [];
        foreach ($buttons as $button) {
            $normalizedButtons[] = [
                'type' => strtoupper($button['type'] ?? ''),
                'text' => $button['text'] ?? '',
                'url' => $button['url'] ?? null,
                'phone_number' => $button['phone_number'] ?? null];
        }

        $template = WhatsAppTemplate::updateOrCreate(
            [
                'account_id' => $connection->account_id,
                'whatsapp_connection_id' => $connection->id,
                'meta_template_id' => $templateData['id'] ?? null],
            [
                'name' => $name,
                'language' => $language,
                'category' => $category,
                'status' => $status,
                'quality_score' => $templateData['quality_score'] ?? null,
                'body_text' => $bodyText,
                'header_type' => $headerType,
                'header_text' => $headerText,
                'footer_text' => $footerText,
                'buttons' => $normalizedButtons,
                'components' => $components,
                'last_synced_at' => now(),
                'last_meta_error' => null]
        );

        return $template;
    }

    /**
     * Persist sync metadata only when schema supports these columns.
     * Some deployments still run an older whatsapp_connections schema.
     */
    protected function persistConnectionSyncState(WhatsAppConnection $connection, array $errors): void
    {
        if ($this->connectionHasSyncColumns === null) {
            $this->connectionHasSyncColumns = Schema::hasColumn('whatsapp_connections', 'last_synced_at')
                && Schema::hasColumn('whatsapp_connections', 'last_meta_error');
        }

        if (! $this->connectionHasSyncColumns) {
            return;
        }

        $connection->update([
            'last_synced_at' => now(),
            'last_meta_error' => count($errors) > 0 ? json_encode($errors) : null,
        ]);
    }
}
