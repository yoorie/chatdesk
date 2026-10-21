<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Exceptions\WhatsAppApiException;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TemplateManagementService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('whatsapp.meta.base_url', 'https://graph.facebook.com'), '/');
    }

    /**
     * Create a new template via Meta API.
     * Follows Meta's latest template creation guidelines.
     */
    public function createTemplate(WhatsAppConnection $connection, array $templateData): array
    {
        $wabaId = $connection->waba_id;
        if (! $wabaId) {
            throw new \Exception('WABA ID is required to create templates');
        }

        $url = sprintf(
            '%s/%s/%s/message_templates',
            $this->baseUrl,
            $connection->api_version ?: config('whatsapp.meta.api_version', 'v25.0'),
            $wabaId
        );

        // If header is media (IMAGE/VIDEO/DOCUMENT), we must provide an example. Prefer Meta upload handle over URL.
        if (! empty($templateData['header_type']) && in_array($templateData['header_type'], ['IMAGE', 'VIDEO', 'DOCUMENT'])) {
            $mediaUrl = trim((string) ($templateData['header_media_url'] ?? ''));
            if ($mediaUrl !== '') {
                try {
                    $handle = $this->uploadHeaderMediaToMeta($connection, $mediaUrl, $templateData['header_type']);
                    if ($handle) {
                        $templateData['header_media_handle'] = $handle;
                    }
                } catch (\Throwable $e) {
                    Log::channel('whatsapp')->warning('Template header media upload to Meta failed, using URL', [
                        'connection_id' => $connection->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            // Meta requires a valid example for IMAGE/VIDEO/DOCUMENT headers
            $hasExample = ! empty($templateData['header_media_handle']) || ! empty(trim((string) ($templateData['header_media_url'] ?? '')));
            if (! $hasExample) {
                throw new \InvalidArgumentException(
                    'Templates with '.$templateData['header_type'].' header require a sample. Please upload a header image (or provide a public image URL) and try again.'
                );
            }
        }

        // Build payload according to Meta's latest API format
        $payload = $this->buildTemplatePayload($templateData);

        try {
            // Check rate limit
            $this->rateLimitCheck($connection->id);

            $response = Http::withToken($connection->access_token)
                ->post($url, $payload);

            $responseData = $response->json();

            if (! $response->successful()) {
                $err = $responseData['error'] ?? [];
                $errorMessage = $err['message'] ?? 'Unknown error from WhatsApp API';
                $errorCode = $err['code'] ?? $response->status();
                $userMsg = $err['error_user_msg'] ?? $err['error_user_title'] ?? null;
                if ($userMsg && $userMsg !== $errorMessage) {
                    $errorMessage = $errorMessage.' ('.$userMsg.')';
                }
                // When Meta says IMAGE header needs example/valid example: URL may be unreachable by Meta; suggest upload
                if (stripos($errorMessage, 'IMAGE header') !== false && (stripos($errorMessage, 'example') !== false || stripos($errorMessage, 'sample') !== false)) {
                    $errorMessage .= ' Make sure the header image URL is publicly accessible (no login required), or set META_APP_ID in .env so we can upload the image to Meta.';
                }

                Log::channel('whatsapp')->error('Template creation API error', [
                    'connection_id' => $connection->id,
                    'waba_id' => $wabaId,
                    'error' => $err,
                    'payload' => $payload,
                ]);

                throw new WhatsAppApiException(
                    "Failed to create template: {$errorMessage}",
                    $responseData,
                    $errorCode
                );
            }

            // Store template locally. A 200 without an id is not a created template.
            $metaTemplateId = $responseData['id'] ?? null;
            if (! $metaTemplateId) {
                throw new WhatsAppApiException(
                    'Failed to create template: WhatsApp did not return a template id.',
                    is_array($responseData) ? $responseData : [],
                    0
                );
            }
            $this->storeTemplateLocally($connection, (string) $metaTemplateId, $templateData, $responseData);

            return $responseData;
        } catch (WhatsAppApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Unexpected error creating template', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage()]);

            throw new WhatsAppApiException(
                "Failed to create template: {$e->getMessage()}",
                [],
                0,
                $e
            );
        }
    }

    /**
     * Update an existing template (create new version).
     * Meta doesn't allow direct edits - you must create a new version.
     */
    public function updateTemplate(WhatsAppConnection $connection, WhatsAppTemplate $template, array $templateData): array
    {
        // For updates, we create a new version with the same name
        $templateData['name'] = $template->name;

        return $this->createTemplate($connection, $templateData);
    }

    /**
     * Delete a template via Meta API.
     */
    public function deleteTemplate(WhatsAppConnection $connection, string $metaTemplateId): bool
    {
        $wabaId = $connection->waba_id;
        if (! $wabaId) {
            throw new \Exception('WABA ID is required to delete templates');
        }

        $url = sprintf(
            '%s/%s/%s/message_templates?hsm_id=%s',
            $this->baseUrl,
            $connection->api_version ?: config('whatsapp.meta.api_version', 'v25.0'),
            $wabaId,
            $metaTemplateId
        );

        try {
            $this->rateLimitCheck($connection->id);

            $response = Http::withToken($connection->access_token)
                ->delete($url);

            if (! $response->successful()) {
                $responseData = $response->json();
                $errorMessage = $responseData['error']['message'] ?? 'Unknown error from WhatsApp API';

                throw new WhatsAppApiException(
                    "Failed to delete template: {$errorMessage}",
                    $responseData,
                    $response->status()
                );
            }

            return true;
        } catch (WhatsAppApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Unexpected error deleting template', [
                'connection_id' => $connection->id,
                'meta_template_id' => $metaTemplateId,
                'error' => $e->getMessage()]);

            throw new WhatsAppApiException(
                "Failed to delete template: {$e->getMessage()}",
                [],
                0,
                $e
            );
        }
    }

    /**
     * Get template status from Meta API.
     */
    public function getTemplateStatus(WhatsAppConnection $connection, string $metaTemplateId): array
    {
        $wabaId = $connection->waba_id;
        if (! $wabaId) {
            throw new \Exception('WABA ID is required to check template status');
        }

        $url = sprintf(
            '%s/%s/%s/message_templates',
            $this->baseUrl,
            $connection->api_version ?: config('whatsapp.meta.api_version', 'v25.0'),
            $wabaId
        );

        try {
            $this->rateLimitCheck($connection->id);

            $response = Http::withToken($connection->access_token)
                ->get($url, ['limit' => 1000]); // Get all templates

            $responseData = $response->json();

            if (! $response->successful()) {
                $errorMessage = $responseData['error']['message'] ?? 'Unknown error from WhatsApp API';
                throw new WhatsAppApiException(
                    "Failed to get template status: {$errorMessage}",
                    $responseData,
                    $response->status()
                );
            }

            // Find the specific template by ID
            $templates = $responseData['data'] ?? [];
            foreach ($templates as $template) {
                if (($template['id'] ?? '') === $metaTemplateId) {
                    return $template;
                }
            }

            throw new \Exception('Template not found in Meta');
        } catch (WhatsAppApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Unexpected error getting template status', [
                'connection_id' => $connection->id,
                'meta_template_id' => $metaTemplateId,
                'error' => $e->getMessage()]);

            throw new WhatsAppApiException(
                "Failed to get template status: {$e->getMessage()}",
                [],
                0,
                $e
            );
        }
    }

    /**
     * Build template payload according to Meta's latest API format.
     * See: https://developers.facebook.com/docs/whatsapp/business-management-api/message-templates/components
     */
    protected function buildTemplatePayload(array $data): array
    {
        $payload = [
            'name' => $data['name'],
            'language' => $data['language'],
            'category' => strtoupper($data['category']),
            'components' => [],
        ];

        if (strtoupper($data['category']) === 'AUTHENTICATION') {
            $payload['components'][] = [
                'type' => 'BODY',
                'add_security_recommendation' => true,
            ];

            $payload['components'][] = [
                'type' => 'FOOTER',
                'code_expiration_minutes' => 10,
            ];

            $payload['components'][] = [
                'type' => 'BUTTONS',
                'buttons' => [[
                    'type' => 'OTP',
                    'otp_type' => 'COPY_CODE',
                ]],
            ];

            return $payload;
        }

        // Header component
        if (! empty($data['header_type']) && $data['header_type'] !== 'NONE') {
            $header = [
                'type' => 'HEADER',
                'format' => strtoupper($data['header_type']),
            ];

            if ($data['header_type'] === 'TEXT' && ! empty($data['header_text'])) {
                $header['text'] = $data['header_text'];
            } elseif (in_array($data['header_type'], ['IMAGE', 'VIDEO', 'DOCUMENT'])) {
                // Meta requires example for media headers. Use handle from Resumable Upload when available, else public URL.
                $mediaRef = $data['header_media_handle'] ?? $data['header_media_url'] ?? null;
                $mediaRef = is_string($mediaRef) ? trim($mediaRef) : $mediaRef;
                if (empty($mediaRef)) {
                    throw new \InvalidArgumentException(
                        'Templates with '.$data['header_type'].' header need a sample image. Please upload a file or provide a public image URL.'
                    );
                }
                $header['example'] = [
                    'header_handle' => [$mediaRef],
                ];
            }

            $payload['components'][] = $header;
        }

        // Body component (required)
        if (! empty($data['body_text'])) {
            $body = [
                'type' => 'BODY',
                'text' => $data['body_text'],
            ];

            // Only add example when body contains variables ({{1}}, {{2}}, etc.); otherwise Meta returns "Invalid parameter"
            $hasBodyVariables = (bool) preg_match('/\{\{\d+\}\}/', $data['body_text']);
            if ($hasBodyVariables) {
                $examples = array_values(array_filter($data['body_examples'] ?? [], fn ($example) => trim((string) $example) !== ''));
                preg_match_all('/\{\{(\d+)\}\}/', $data['body_text'], $matches);
                $variables = array_values(array_unique(array_map('intval', $matches[1] ?? [])));
                sort($variables);

                if (count($examples) < count($variables)) {
                    throw new \InvalidArgumentException('Add one sample value for each body variable before submitting to Meta.');
                }

                $body['example'] = [
                    'body_text' => [$examples],
                ];
            }

            $payload['components'][] = $body;
        }

        // Buttons (Meta expects BUTTONS before FOOTER)
        if (! empty($data['buttons']) && is_array($data['buttons'])) {
            $buttonComponents = [];
            foreach ($data['buttons'] as $button) {
                $buttonType = strtoupper($button['type'] ?? '');
                $buttonData = [
                    'type' => $buttonType,
                    'text' => $button['text'] ?? '',
                ];

                if ($buttonType === 'URL' && ! empty($button['url'])) {
                    $buttonData['url'] = $button['url'];
                    // Only add example for dynamic URL (Meta requires URL to contain {{1}} when using example)
                    if (! empty($button['url_example']) && preg_match('/\{\{\d+\}\}/', $button['url'])) {
                        $buttonData['example'] = [$button['url_example']];
                    }
                } elseif ($buttonType === 'PHONE_NUMBER' && ! empty($button['phone_number'])) {
                    $buttonData['phone_number'] = $button['phone_number'];
                }

                $buttonComponents[] = $buttonData;
            }

            if (! empty($buttonComponents)) {
                $payload['components'][] = [
                    'type' => 'BUTTONS',
                    'buttons' => $buttonComponents,
                ];
            }
        }

        // Footer component
        if (! empty($data['footer_text'])) {
            $payload['components'][] = [
                'type' => 'FOOTER',
                'text' => $data['footer_text'],
            ];
        }

        return $payload;
    }

    /**
     * Store template locally after creation.
     */
    protected function storeTemplateLocally(
        WhatsAppConnection $connection,
        string $metaTemplateId,
        array $templateData,
        array $metaResponse
    ): WhatsAppTemplate {
        // Meta create response often omits components; use our request payload components as fallback.
        $components = $metaResponse['components'] ?? $this->buildTemplatePayload($templateData)['components'] ?? [];

        $bodyText = null;
        $headerType = null;
        $headerText = null;
        $footerText = null;
        $buttons = [];

        foreach ($components as $component) {
            $type = strtoupper($component['type'] ?? '');

            if ($type === 'BODY') {
                $bodyText = $component['text'] ?? $templateData['body_text'] ?? '';
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

        return WhatsAppTemplate::create([
            'account_id' => $connection->account_id,
            'whatsapp_connection_id' => $connection->id,
            'meta_template_id' => $metaTemplateId,
            'name' => $templateData['name'],
            'language' => $templateData['language'],
            'category' => strtoupper($templateData['category']),
            'status' => strtolower(trim((string) ($metaResponse['status'] ?? 'PENDING'))),
            'quality_score' => $metaResponse['quality_score'] ?? null,
            'body_text' => $bodyText,
            'header_type' => $headerType,
            'header_text' => $headerText,
            'header_media_url' => $templateData['header_media_url'] ?? null,
            'footer_text' => $footerText,
            'buttons' => $buttons,
            'components' => $components,
            'last_synced_at' => now(),
            'last_meta_error' => null]);
    }

    /**
     * Upload header media to Meta via Resumable Upload API and return the file handle.
     * Meta template creation often rejects app-hosted URLs; using a handle avoids "Invalid parameter".
     */
    protected function uploadHeaderMediaToMeta(WhatsAppConnection $connection, string $mediaUrl, string $headerType): ?string
    {
        $appId = config('whatsapp.meta.app_id');
        if (! $appId) {
            return null;
        }

        $response = Http::timeout(30)->get($mediaUrl);
        if (! $response->successful()) {
            throw new \RuntimeException("Could not fetch media: HTTP {$response->status()}");
        }

        $content = $response->body();
        $fileLength = strlen($content);
        if ($fileLength === 0) {
            throw new \RuntimeException('Media file is empty');
        }

        $mimeMap = [
            'IMAGE' => $response->header('Content-Type') ?: 'image/png',
            'VIDEO' => 'video/mp4',
            'DOCUMENT' => $response->header('Content-Type') ?: 'application/pdf',
        ];
        $fileType = $mimeMap[$headerType] ?? 'image/png';
        // Meta only accepts: image/jpeg, image/jpg, image/png, video/mp4, application/pdf
        $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'video/mp4', 'application/pdf'];
        if (! in_array(strtolower(explode(';', $fileType)[0]), $allowed)) {
            $fileType = $headerType === 'VIDEO' ? 'video/mp4' : 'image/png';
        }

        $fileName = 'template_header_'.substr(md5($mediaUrl), 0, 8);
        if (str_starts_with($fileType, 'image/')) {
            $fileName .= '.png';
        } elseif (str_starts_with($fileType, 'video/')) {
            $fileName .= '.mp4';
        } else {
            $fileName .= '.pdf';
        }

        $version = $connection->api_version ?: config('whatsapp.meta.api_version', 'v25.0');
        $createUrl = sprintf('%s/%s/%s/uploads', $this->baseUrl, $version, $appId);

        // Meta Resumable Upload: create session (API accepts query or form params)
        $sessionResponse = Http::withToken($connection->access_token)
            ->post($createUrl.'?'.http_build_query([
                'file_name' => $fileName,
                'file_length' => $fileLength,
                'file_type' => $fileType,
            ]));

        $sessionData = $sessionResponse->json();
        $sessionId = $sessionData['id'] ?? null;
        if (! $sessionId || ! str_starts_with((string) $sessionId, 'upload:')) {
            Log::channel('whatsapp')->warning('Meta upload session failed', [
                'response' => $sessionData,
                'status' => $sessionResponse->status(),
            ]);

            return null;
        }

        $uploadUrl = sprintf('%s/%s/%s', $this->baseUrl, $version, $sessionId);
        $uploadResponse = Http::withHeaders([
            'Authorization' => 'OAuth '.$connection->access_token,
            'file_offset' => '0',
        ])->withBody($content, 'application/octet-stream')
            ->timeout(60)
            ->post($uploadUrl);

        $uploadData = $uploadResponse->json();
        $handle = $uploadData['h'] ?? null;
        if (! $handle) {
            Log::channel('whatsapp')->warning('Meta file upload failed', [
                'response' => $uploadData,
                'status' => $uploadResponse->status(),
            ]);

            return null;
        }

        return $handle;
    }

    /**
     * Check and enforce rate limiting.
     */
    protected function rateLimitCheck(int $connectionId): void
    {
        $rateLimitKey = "template_management_rate_limit:connection:{$connectionId}";
        $requests = Cache::get($rateLimitKey, 0);

        // Allow max 20 requests per minute per connection
        if ($requests >= 20) {
            $ttl = Cache::get($rateLimitKey.':ttl', 60);
            throw new \Exception("Rate limit: Maximum 20 template operations per minute. Please wait {$ttl} seconds.");
        }

        // Increment counter
        Cache::put($rateLimitKey, $requests + 1, 60);
        Cache::put($rateLimitKey.':ttl', 60 - (now()->second), 60);
    }
}
