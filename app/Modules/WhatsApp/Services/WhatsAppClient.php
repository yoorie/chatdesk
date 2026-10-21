<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Exceptions\WhatsAppApiException;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class WhatsAppClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('whatsapp.meta.base_url', 'https://graph.facebook.com'), '/');
    }

    /**
     * Check and enforce rate limiting per connection.
     * WhatsApp allows ~1000 messages per second per phone number.
     * We'll be conservative and allow 100 messages per minute per connection.
     */
    protected function checkRateLimit(WhatsAppConnection $connection): void
    {
        // Fixed one-minute window per connection. The previous implementation re-put
        // the counter with a fresh 60s TTL on every call, so under steady traffic the
        // window never expired and the connection stayed blocked indefinitely.
        $maxRequests = max(1, (int) config('whatsapp.client_rate_limit_per_minute', 1000));
        $rateLimitKey = "whatsapp_api_rate_limit:connection:{$connection->id}";

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxRequests)) {
            $ttl = RateLimiter::availableIn($rateLimitKey);
            throw new WhatsAppApiException(
                "Rate limit exceeded: Maximum {$maxRequests} requests per minute for this connection. Please wait {$ttl} seconds.",
                [],
                429
            );
        }

        RateLimiter::hit($rateLimitKey, 60);
    }

    /**
     * Send a text message via WhatsApp Cloud API.
     */
    public function sendTextMessage(
        WhatsAppConnection $connection,
        string $toWaId,
        string $messageText,
        ?string $replyToMessageId = null
    ): array {

        $url = sprintf(
            '%s/%s/%s/messages',
            $this->baseUrl,
            $connection->api_version ?: config('whatsapp.meta.api_version', 'v25.0'),
            $connection->phone_number_id
        );

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toWaId,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $messageText]];

        if ($replyToMessageId) {
            $payload['context'] = ['message_id' => $replyToMessageId];
        }

        try {
            // Check rate limit before making API call
            $this->checkRateLimit($connection);

            $response = Http::withToken($connection->access_token)
                ->post($url, $payload);

            $responseData = $response->json();

            if (! $response->successful()) {
                $errorMessage = $responseData['error']['message'] ?? 'Unknown error from WhatsApp API';
                $errorCode = $responseData['error']['code'] ?? $response->status();

                // Handle rate limiting
                if ($errorCode === 4 || $errorCode === 429 || str_contains($errorMessage, 'rate limit')) {
                    Log::channel('whatsapp')->warning('WhatsApp API rate limit hit', [
                        'connection_id' => $connection->id,
                        'phone_number_id' => $connection->phone_number_id]);
                    throw new WhatsAppApiException(
                        'Rate limit exceeded. Please wait before sending more messages.',
                        $responseData,
                        $errorCode
                    );
                }

                Log::channel('whatsapp')->error('WhatsApp API error', [
                    'connection_id' => $connection->id,
                    'phone_number_id' => $connection->phone_number_id,
                    'error' => $responseData['error'] ?? [],
                    'status' => $response->status()]);

                throw new WhatsAppApiException(
                    "WhatsApp API error: {$errorMessage}",
                    $responseData,
                    $errorCode
                );
            }

            Log::channel('whatsapp')->info('Message sent successfully', [
                'connection_id' => $connection->id,
                'message_id' => $responseData['messages'][0]['id'] ?? null]);

            return $responseData;
        } catch (WhatsAppApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Unexpected error sending message', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage()]);

            throw new WhatsAppApiException(
                "Failed to send message: {$e->getMessage()}",
                [],
                0,
                $e
            );
        }
    }

    public function checkCallPermission(WhatsAppConnection $connection, string $toWaId): array
    {
        $url = sprintf(
            '%s/%s/%s/call_permissions',
            $this->baseUrl,
            $connection->api_version ?: config('whatsapp.meta.api_version', 'v25.0'),
            $connection->phone_number_id
        );

        $this->checkRateLimit($connection);

        $response = Http::withToken($connection->access_token)
            ->get($url, ['user_wa_id' => $toWaId]);

        $data = $response->json();
        if (! $response->successful()) {
            $errorMessage = $data['error']['message'] ?? 'Call permission check failed';
            throw new WhatsAppApiException("WhatsApp Calling API error: {$errorMessage}", $data, $response->status());
        }

        return $data ?: [];
    }

    public function sendCallPermissionRequest(WhatsAppConnection $connection, string $toWaId, string $bodyText): array
    {
        $url = sprintf(
            '%s/%s/%s/messages',
            $this->baseUrl,
            $connection->api_version ?: config('whatsapp.meta.api_version', 'v25.0'),
            $connection->phone_number_id
        );

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toWaId,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'call_permission_request',
                'body' => ['text' => $bodyText],
                'action' => ['name' => 'call_permission_request'],
            ],
        ];

        $this->checkRateLimit($connection);

        $response = Http::withToken($connection->access_token)->post($url, $payload);
        $data = $response->json();
        if (! $response->successful()) {
            $errorMessage = $data['error']['message'] ?? 'Call permission request failed';
            throw new WhatsAppApiException("WhatsApp Calling API error: {$errorMessage}", $data, $response->status());
        }

        return $data ?: [];
    }

    public function manageCall(WhatsAppConnection $connection, array $payload): array
    {
        $url = sprintf(
            '%s/%s/%s/calls',
            $this->baseUrl,
            $connection->api_version ?: config('whatsapp.meta.api_version', 'v25.0'),
            $connection->phone_number_id
        );

        $this->checkRateLimit($connection);

        $response = Http::withToken($connection->access_token)->post($url, $payload);
        $data = $response->json();
        if (! $response->successful()) {
            $errorMessage = $data['error']['message'] ?? 'Call action failed';
            throw new WhatsAppApiException("WhatsApp Calling API error: {$errorMessage}", $data, $response->status());
        }

        return $data ?: ['success' => true];
    }

    /**
     * Send a template message via WhatsApp Cloud API.
     */
    public function sendTemplateMessage(
        WhatsAppConnection $connection,
        string $toWaId,
        string $templateName,
        string $language,
        array $components = []
    ): array {

        $url = sprintf(
            '%s/%s/%s/messages',
            $this->baseUrl,
            $connection->api_version ?: config('whatsapp.meta.api_version', 'v25.0'),
            $connection->phone_number_id
        );

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toWaId,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $language],
                'components' => $components]];

        try {
            // Check rate limit before making API call
            $this->checkRateLimit($connection);

            $response = Http::withToken($connection->access_token)
                ->post($url, $payload);

            $responseData = $response->json();

            if (! $response->successful()) {
                $errorMessage = $responseData['error']['message'] ?? 'Unknown error from WhatsApp API';
                $errorCode = $responseData['error']['code'] ?? $response->status();

                // Handle rate limiting
                if ($errorCode === 4 || $errorCode === 429 || str_contains($errorMessage, 'rate limit')) {
                    Log::channel('whatsapp')->warning('WhatsApp API rate limit hit', [
                        'connection_id' => $connection->id,
                        'phone_number_id' => $connection->phone_number_id,
                        'template_name' => $templateName]);
                    throw new WhatsAppApiException(
                        'Rate limit exceeded. Please wait before sending more messages.',
                        $responseData,
                        $errorCode
                    );
                }

                Log::channel('whatsapp')->error('WhatsApp template API error', [
                    'connection_id' => $connection->id,
                    'phone_number_id' => $connection->phone_number_id,
                    'template_name' => $templateName,
                    'error' => $responseData['error'] ?? [],
                    'status' => $response->status()]);

                throw new WhatsAppApiException(
                    "WhatsApp API error: {$errorMessage}",
                    $responseData,
                    $errorCode
                );
            }

            Log::channel('whatsapp')->info('Template message sent successfully', [
                'connection_id' => $connection->id,
                'template_name' => $templateName,
                'message_id' => $responseData['messages'][0]['id'] ?? null]);

            return $responseData;
        } catch (WhatsAppApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Unexpected error sending template message', [
                'connection_id' => $connection->id,
                'template_name' => $templateName,
                'error' => $e->getMessage()]);

            throw new WhatsAppApiException(
                "Failed to send template message: {$e->getMessage()}",
                [],
                0,
                $e
            );
        }
    }

    /**
     * Send a media message (image/video/document/audio) via WhatsApp Cloud API.
     */
    public function sendMediaMessage(
        WhatsAppConnection $connection,
        string $toWaId,
        string $type,
        string $link,
        ?string $caption = null,
        ?string $filename = null
    ): array {

        $url = sprintf(
            '%s/%s/%s/messages',
            $this->baseUrl,
            $connection->api_version ?: config('whatsapp.meta.api_version', 'v25.0'),
            $connection->phone_number_id
        );

        $mediaPayload = ['link' => $link];
        if ($caption && in_array($type, ['image', 'video', 'document'], true)) {
            $mediaPayload['caption'] = $caption;
        }
        if ($type === 'document' && $filename) {
            $mediaPayload['filename'] = $filename;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toWaId,
            'type' => $type,
            $type => $mediaPayload];

        try {
            $this->checkRateLimit($connection);

            $response = Http::withToken($connection->access_token)
                ->post($url, $payload);

            $responseData = $response->json();

            if (! $response->successful()) {
                $errorMessage = $responseData['error']['message'] ?? 'Unknown error from WhatsApp API';
                $errorCode = $responseData['error']['code'] ?? $response->status();

                Log::channel('whatsapp')->error('WhatsApp media API error', [
                    'connection_id' => $connection->id,
                    'phone_number_id' => $connection->phone_number_id,
                    'media_type' => $type,
                    'error' => $responseData['error'] ?? [],
                    'status' => $response->status()]);

                throw new WhatsAppApiException(
                    "WhatsApp API error: {$errorMessage}",
                    $responseData,
                    $errorCode
                );
            }

            Log::channel('whatsapp')->info('Media message sent successfully', [
                'connection_id' => $connection->id,
                'media_type' => $type,
                'message_id' => $responseData['messages'][0]['id'] ?? null]);

            return $responseData;
        } catch (WhatsAppApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Unexpected error sending media message', [
                'connection_id' => $connection->id,
                'media_type' => $type,
                'error' => $e->getMessage()]);

            throw new WhatsAppApiException(
                "Failed to send media message: {$e->getMessage()}",
                [],
                0,
                $e
            );
        }
    }

    public function uploadMedia(WhatsAppConnection $connection, \SplFileInfo|string $file, ?string $filename = null, ?string $mimeType = null): array
    {
        $url = sprintf(
            '%s/%s/%s/media',
            $this->baseUrl,
            $connection->api_version ?: config('whatsapp.meta.api_version', 'v25.0'),
            $connection->phone_number_id
        );

        $path = $file instanceof \SplFileInfo ? $file->getPathname() : $file;
        if (! is_string($path) || ! is_file($path)) {
            throw new WhatsAppApiException('Invalid media file.', [], 400);
        }

        $filename ??= basename($path);

        $this->checkRateLimit($connection);

        $headers = [];
        if ($mimeType) {
            $headers['Content-Type'] = $mimeType;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new WhatsAppApiException('Unable to read media file for upload.', [], 400);
        }

        try {
            $response = Http::withToken($connection->access_token)
                ->attach('file', $handle, $filename, $headers)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                ]);
        } finally {
            fclose($handle);
        }

        $data = $response->json();
        if (! $response->successful() || empty($data['id'])) {
            throw new WhatsAppApiException(
                $data['error']['message'] ?? $data['error']['error_user_msg'] ?? 'Failed to upload WhatsApp media.',
                $data ?: [],
                $response->status()
            );
        }

        return $data;
    }

    public function sendUploadedMediaMessage(
        WhatsAppConnection $connection,
        string $toWaId,
        string $type,
        string $mediaId,
        ?string $caption = null,
        ?string $filename = null,
        bool $voice = false,
        ?string $replyToMessageId = null
    ): array {
        $mediaPayload = ['id' => $mediaId];
        if ($caption && in_array($type, ['image', 'video', 'document'], true)) {
            $mediaPayload['caption'] = $caption;
        }
        if ($type === 'document' && $filename) {
            $mediaPayload['filename'] = $filename;
        }
        if ($type === 'audio' && $voice) {
            $mediaPayload['voice'] = true;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toWaId,
            'type' => $type,
            $type => $mediaPayload,
        ];

        if ($replyToMessageId) {
            $payload['context'] = ['message_id' => $replyToMessageId];
        }

        return $this->postMessagePayload($connection, $payload, 'uploaded media');
    }

    public function getMediaUrl(WhatsAppConnection $connection, string $mediaId): array
    {
        $url = sprintf(
            '%s/%s/%s',
            $this->baseUrl,
            $connection->api_version ?: config('whatsapp.meta.api_version', 'v25.0'),
            $mediaId
        );

        $this->checkRateLimit($connection);

        $response = Http::withToken($connection->access_token)->get($url);
        $data = $response->json();
        if (! $response->successful() || empty($data['url'])) {
            throw new WhatsAppApiException(
                $data['error']['message'] ?? 'Failed to fetch WhatsApp media URL.',
                $data ?: [],
                $response->status()
            );
        }

        return $data;
    }

    public function downloadMedia(WhatsAppConnection $connection, string $mediaId): array
    {
        $media = $this->getMediaUrl($connection, $mediaId);
        $url = $media['url'] ?? null;
        if (! $url) {
            throw new WhatsAppApiException('Meta did not return a media download URL.', $media, 404);
        }

        $this->checkRateLimit($connection);

        $response = Http::withToken($connection->access_token)->get($url);
        if (! $response->successful()) {
            $data = $response->json();
            throw new WhatsAppApiException(
                $data['error']['message'] ?? 'Failed to download WhatsApp media.',
                is_array($data) ? $data : [],
                $response->status()
            );
        }

        return [
            'body' => $response->body(),
            'mime_type' => $response->header('Content-Type') ?: ($media['mime_type'] ?? 'application/octet-stream'),
            'sha256' => $media['sha256'] ?? null,
            'file_size' => $media['file_size'] ?? strlen($response->body()),
            'url' => $url,
            'meta' => $media,
        ];
    }

    public function markMessageAsRead(WhatsAppConnection $connection, string $metaMessageId): array
    {

        return $this->postMessagePayload($connection, [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $metaMessageId,
            'typing_indicator' => [
                'type' => 'text',
            ],
        ], 'read receipt');
    }

    public function sendReactionMessage(
        WhatsAppConnection $connection,
        string $toWaId,
        string $targetMetaMessageId,
        string $emoji
    ): array {
        return $this->postMessagePayload($connection, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toWaId,
            'type' => 'reaction',
            'reaction' => [
                'message_id' => $targetMetaMessageId,
                'emoji' => $emoji,
            ],
        ], 'reaction message');
    }

    public function sendContactsMessage(
        WhatsAppConnection $connection,
        string $toWaId,
        array $contacts
    ): array {
        return $this->postMessagePayload($connection, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toWaId,
            'type' => 'contacts',
            'contacts' => $contacts,
        ], 'contacts message');
    }

    public function sendCtaUrlMessage(
        WhatsAppConnection $connection,
        string $toWaId,
        string $bodyText,
        string $displayText,
        string $url,
        ?string $headerText = null,
        ?string $footerText = null
    ): array {
        $interactive = [
            'type' => 'cta_url',
            'body' => ['text' => $bodyText],
            'action' => [
                'name' => 'cta_url',
                'parameters' => [
                    'display_text' => $displayText,
                    'url' => $url,
                ],
            ],
        ];

        if ($headerText) {
            $interactive['header'] = ['type' => 'text', 'text' => $headerText];
        }

        if ($footerText) {
            $interactive['footer'] = ['text' => $footerText];
        }

        return $this->postMessagePayload($connection, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toWaId,
            'type' => 'interactive',
            'interactive' => $interactive,
        ], 'CTA URL message');
    }

    public function setUserBlockState(WhatsAppConnection $connection, string $waId, bool $blocked = true): array
    {
        $url = sprintf(
            '%s/%s/%s/block_users',
            $this->baseUrl,
            $connection->api_version ?: config('whatsapp.meta.api_version', 'v25.0'),
            $connection->phone_number_id
        );

        $payload = [
            'messaging_product' => 'whatsapp',
            'block_users' => [
                ['user' => $waId],
            ],
        ];

        $this->checkRateLimit($connection);

        $response = $blocked
            ? Http::withToken($connection->access_token)->post($url, $payload)
            : Http::withToken($connection->access_token)->delete($url, $payload);

        $data = $response->json();
        if (! $response->successful()) {
            throw new WhatsAppApiException(
                $data['error']['message'] ?? ($blocked ? 'Failed to block WhatsApp user.' : 'Failed to unblock WhatsApp user.'),
                $data ?: [],
                $response->status()
            );
        }

        return $data ?: ['success' => true];
    }

    /**
     * Send a location message via WhatsApp Cloud API.
     */
    public function sendLocationMessage(
        WhatsAppConnection $connection,
        string $toWaId,
        array $location
    ): array {
        $url = sprintf(
            '%s/%s/%s/messages',
            $this->baseUrl,
            $connection->api_version ?: config('whatsapp.meta.api_version', 'v25.0'),
            $connection->phone_number_id
        );

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toWaId,
            'type' => 'location',
            'location' => [
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
                'name' => $location['name'] ?? null,
                'address' => $location['address'] ?? null]];

        try {
            $this->checkRateLimit($connection);

            $response = Http::withToken($connection->access_token)
                ->post($url, $payload);

            $responseData = $response->json();

            if (! $response->successful()) {
                $errorMessage = $responseData['error']['message'] ?? 'Unknown error from WhatsApp API';
                $errorCode = $responseData['error']['code'] ?? $response->status();

                Log::channel('whatsapp')->error('WhatsApp location API error', [
                    'connection_id' => $connection->id,
                    'phone_number_id' => $connection->phone_number_id,
                    'error' => $responseData['error'] ?? [],
                    'status' => $response->status()]);

                throw new WhatsAppApiException(
                    "WhatsApp API error: {$errorMessage}",
                    $responseData,
                    $errorCode
                );
            }

            Log::channel('whatsapp')->info('Location message sent successfully', [
                'connection_id' => $connection->id,
                'message_id' => $responseData['messages'][0]['id'] ?? null]);

            return $responseData;
        } catch (WhatsAppApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Unexpected error sending location message', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage()]);

            throw new WhatsAppApiException(
                "Failed to send location message: {$e->getMessage()}",
                [],
                0,
                $e
            );
        }
    }

    /**
     * Send an interactive list message via WhatsApp Cloud API.
     */
    public function sendListMessage(
        WhatsAppConnection $connection,
        string $toWaId,
        string $buttonText,
        array $sections,
        ?string $headerText = null,
        ?string $bodyText = null,
        ?string $footerText = null
    ): array {
        $url = sprintf(
            '%s/%s/%s/messages',
            $this->baseUrl,
            $connection->api_version ?: config('whatsapp.meta.api_version', 'v25.0'),
            $connection->phone_number_id
        );

        $interactive = [
            'type' => 'list',
            'action' => [
                'button' => $buttonText,
                'sections' => $sections,
            ],
        ];

        if ($bodyText) {
            $interactive['body'] = ['text' => $bodyText];
        }

        if ($headerText) {
            $interactive['header'] = ['type' => 'text', 'text' => $headerText];
        }

        if ($footerText) {
            $interactive['footer'] = ['text' => $footerText];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toWaId,
            'type' => 'interactive',
            'interactive' => $interactive,
        ];

        try {
            $this->checkRateLimit($connection);

            $response = Http::withToken($connection->access_token)
                ->post($url, $payload);

            $responseData = $response->json();

            if (! $response->successful()) {
                $errorMessage = $responseData['error']['message'] ?? 'Unknown error from WhatsApp API';
                $errorCode = $responseData['error']['code'] ?? $response->status();

                Log::channel('whatsapp')->error('WhatsApp list message API error', [
                    'connection_id' => $connection->id,
                    'phone_number_id' => $connection->phone_number_id,
                    'error' => $responseData['error'] ?? [],
                    'status' => $response->status()]);

                throw new WhatsAppApiException(
                    "WhatsApp API error: {$errorMessage}",
                    $responseData,
                    $errorCode
                );
            }

            Log::channel('whatsapp')->info('List message sent successfully', [
                'connection_id' => $connection->id,
                'message_id' => $responseData['messages'][0]['id'] ?? null]);

            return $responseData;
        } catch (WhatsAppApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Unexpected error sending list message', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage()]);

            throw new WhatsAppApiException(
                "Failed to send list message: {$e->getMessage()}",
                [],
                0,
                $e
            );
        }
    }

    /**
     * Send an interactive button message (reply buttons) via WhatsApp Cloud API.
     */
    public function sendInteractiveButtons(
        WhatsAppConnection $connection,
        string $toWaId,
        string $bodyText,
        array $buttons,
        ?string $headerText = null,
        ?string $footerText = null
    ): array {
        $url = sprintf(
            '%s/%s/%s/messages',
            $this->baseUrl,
            $connection->api_version ?: config('whatsapp.meta.api_version', 'v25.0'),
            $connection->phone_number_id
        );

        // Validate buttons (max 3 buttons, each button text max 20 chars)
        if (count($buttons) > 3) {
            throw new WhatsAppApiException('Maximum 3 buttons allowed', [], 400);
        }

        $buttonComponents = [];
        foreach ($buttons as $button) {
            if (mb_strlen($button['text']) > 20) {
                throw new WhatsAppApiException('Button text must be 20 characters or less', [], 400);
            }
            $buttonComponents[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => $button['id'] ?? uniqid('btn_'),
                    'title' => $button['text'],
                ],
            ];
        }

        $interactive = [
            'type' => 'button',
            'body' => ['text' => $bodyText],
            'action' => [
                'buttons' => $buttonComponents,
            ],
        ];

        if ($headerText) {
            $interactive['header'] = ['type' => 'text', 'text' => $headerText];
        }

        if ($footerText) {
            $interactive['footer'] = ['text' => $footerText];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toWaId,
            'type' => 'interactive',
            'interactive' => $interactive,
        ];

        try {
            $this->checkRateLimit($connection);

            $response = Http::withToken($connection->access_token)
                ->post($url, $payload);

            $responseData = $response->json();

            if (! $response->successful()) {
                $errorMessage = $responseData['error']['message'] ?? 'Unknown error from WhatsApp API';
                $errorCode = $responseData['error']['code'] ?? $response->status();

                Log::channel('whatsapp')->error('WhatsApp interactive button API error', [
                    'connection_id' => $connection->id,
                    'phone_number_id' => $connection->phone_number_id,
                    'error' => $responseData['error'] ?? [],
                    'status' => $response->status()]);

                throw new WhatsAppApiException(
                    "WhatsApp API error: {$errorMessage}",
                    $responseData,
                    $errorCode
                );
            }

            Log::channel('whatsapp')->info('Interactive button message sent successfully', [
                'connection_id' => $connection->id,
                'message_id' => $responseData['messages'][0]['id'] ?? null]);

            return $responseData;
        } catch (WhatsAppApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Unexpected error sending interactive button message', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage()]);

            throw new WhatsAppApiException(
                "Failed to send interactive button message: {$e->getMessage()}",
                [],
                0,
                $e
            );
        }
    }

    public function sendFlowMessage(
        WhatsAppConnection $connection,
        string $toWaId,
        string $flowId,
        string $bodyText,
        string $cta,
        ?string $headerText = null,
        ?string $footerText = null,
        string $flowToken = '',
        string $flowAction = 'navigate',
        ?string $screen = null
    ): array {
        $interactive = [
            'type' => 'flow',
            'body' => ['text' => $bodyText],
            'action' => [
                'name' => 'flow',
                'parameters' => array_filter([
                    'flow_message_version' => '3',
                    'flow_token' => $flowToken !== '' ? $flowToken : Str::uuid()->toString(),
                    'flow_id' => $flowId,
                    'flow_cta' => $cta,
                    'flow_action' => $flowAction,
                    'flow_action_payload' => $screen ? ['screen' => $screen] : null,
                ], fn ($value) => $value !== null),
            ],
        ];

        if ($headerText) {
            $interactive['header'] = ['type' => 'text', 'text' => $headerText];
        }

        if ($footerText) {
            $interactive['footer'] = ['text' => $footerText];
        }

        return $this->postMessagePayload($connection, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toWaId,
            'type' => 'interactive',
            'interactive' => $interactive,
        ], 'flow message');
    }

    public function sendProductMessage(
        WhatsAppConnection $connection,
        string $toWaId,
        string $catalogId,
        string $productRetailerId,
        ?string $bodyText = null,
        ?string $footerText = null
    ): array {
        $interactive = [
            'type' => 'product',
            'action' => [
                'catalog_id' => $catalogId,
                'product_retailer_id' => $productRetailerId,
            ],
        ];

        if ($bodyText) {
            $interactive['body'] = ['text' => $bodyText];
        }
        if ($footerText) {
            $interactive['footer'] = ['text' => $footerText];
        }

        return $this->postMessagePayload($connection, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toWaId,
            'type' => 'interactive',
            'interactive' => $interactive,
        ], 'product message');
    }

    public function sendProductListMessage(
        WhatsAppConnection $connection,
        string $toWaId,
        string $catalogId,
        array $sections,
        string $bodyText,
        ?string $headerText = null,
        ?string $footerText = null
    ): array {
        $interactive = [
            'type' => 'product_list',
            'body' => ['text' => $bodyText],
            'action' => [
                'catalog_id' => $catalogId,
                'sections' => $sections,
            ],
        ];

        if ($headerText) {
            $interactive['header'] = ['type' => 'text', 'text' => $headerText];
        }
        if ($footerText) {
            $interactive['footer'] = ['text' => $footerText];
        }

        return $this->postMessagePayload($connection, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toWaId,
            'type' => 'interactive',
            'interactive' => $interactive,
        ], 'product list message');
    }

    protected function postMessagePayload(WhatsAppConnection $connection, array $payload, string $operation): array
    {

        $url = sprintf(
            '%s/%s/%s/messages',
            $this->baseUrl,
            $connection->api_version ?: config('whatsapp.meta.api_version', 'v25.0'),
            $connection->phone_number_id
        );

        try {
            $this->checkRateLimit($connection);

            $response = Http::withToken($connection->access_token)->post($url, $payload);
            $responseData = $response->json();

            if (! $response->successful()) {
                throw new WhatsAppApiException(
                    $responseData['error']['message'] ?? "WhatsApp API {$operation} failed.",
                    $responseData ?: [],
                    $response->status()
                );
            }

            return $responseData ?: ['success' => true];
        } catch (WhatsAppApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new WhatsAppApiException(
                "Failed to perform WhatsApp {$operation}: {$e->getMessage()}",
                [],
                0,
                $e
            );
        }
    }

    protected function formatTemplateFallbackText(string $templateName, array $components): string
    {
        $values = [];

        foreach ($components as $component) {
            foreach (($component['parameters'] ?? []) as $parameter) {
                $value = $parameter['text']
                    ?? $parameter['payload']
                    ?? $parameter['image']['link']
                    ?? $parameter['document']['link']
                    ?? $parameter['video']['link']
                    ?? null;

                if (is_scalar($value) && trim((string) $value) !== '') {
                    $values[] = trim((string) $value);
                }
            }
        }

        if ($values === []) {
            return "Template: {$templateName}";
        }

        return "Template: {$templateName}\n".implode("\n", $values);
    }

    /**
     * Get the base URL for API calls.
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
