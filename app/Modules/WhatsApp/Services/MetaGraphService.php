<?php

namespace App\Modules\WhatsApp\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaGraphService
{
    protected string $baseUrl;

    protected string $apiVersion;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('whatsapp.meta.base_url', 'https://graph.facebook.com'), '/');
        $this->apiVersion = config('whatsapp.meta.api_version', 'v25.0');
    }

    public function exchangeCodeForToken(string $code, ?string $redirectUri = null): array
    {
        $appId = config('whatsapp.meta.app_id');
        $appSecret = config('whatsapp.meta.app_secret');
        if (empty($appId) || empty($appSecret)) {
            throw new \RuntimeException('Meta App ID/Secret not configured.');
        }

        $payload = [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'code' => $code,
        ];

        if ($redirectUri) {
            $payload['redirect_uri'] = $redirectUri;
        }

        $response = Http::get("{$this->baseUrl}/{$this->apiVersion}/oauth/access_token", $payload);

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->error('Meta OAuth code exchange failed', [
                'status' => $response->status(),
                'error' => $data['error'] ?? $data]);
            throw new \RuntimeException($data['error']['message'] ?? 'Meta OAuth code exchange failed');
        }

        return $data;
    }

    public function exchangeForLongLivedToken(string $shortLivedToken): array
    {
        $appId = config('whatsapp.meta.app_id');
        $appSecret = config('whatsapp.meta.app_secret');
        if (empty($appId) || empty($appSecret)) {
            throw new \RuntimeException('Meta App ID/Secret not configured.');
        }

        $response = Http::get("{$this->baseUrl}/{$this->apiVersion}/oauth/access_token", [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'fb_exchange_token' => $shortLivedToken,
        ]);

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->error('Meta long-lived token exchange failed', [
                'status' => $response->status(),
                'error' => $data['error'] ?? $data,
            ]);
            throw new \RuntimeException($data['error']['message'] ?? 'Meta long-lived token exchange failed');
        }

        return $data;
    }

    public function debugToken(string $inputToken, ?string $accessToken = null): array
    {
        $effectiveToken = $accessToken ?: $this->appAccessToken() ?: $inputToken;

        $response = Http::get("{$this->baseUrl}/{$this->apiVersion}/debug_token", [
            'input_token' => $inputToken,
            'access_token' => $effectiveToken]);

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->error('Meta debug_token failed', [
                'status' => $response->status(),
                'error' => $data['error'] ?? $data]);
            throw new \RuntimeException($data['error']['message'] ?? 'Meta debug_token failed');
        }

        return $data['data'] ?? $data;
    }

    protected function appAccessToken(): ?string
    {
        $appId = config('whatsapp.meta.app_id');
        $appSecret = config('whatsapp.meta.app_secret');

        return $appId && $appSecret ? "{$appId}|{$appSecret}" : null;
    }

    public function subscribeAppToWaba(string $wabaId, string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->post("{$this->baseUrl}/{$this->apiVersion}/{$wabaId}/subscribed_apps");

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->warning('Subscribe app to WABA failed', [
                'waba_id' => $wabaId,
                'status' => $response->status(),
                'error' => $data['error'] ?? $data]);
            throw new \RuntimeException($data['error']['message'] ?? 'Subscribe app to WABA failed');
        }

        return $data;
    }

    public function unsubscribeAppFromWaba(string $wabaId, string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->delete("{$this->baseUrl}/{$this->apiVersion}/{$wabaId}/subscribed_apps");

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->warning('Unsubscribe app from WABA failed', [
                'waba_id' => $wabaId,
                'status' => $response->status(),
                'error' => $data['error'] ?? $data,
            ]);
            throw new \RuntimeException($data['error']['message'] ?? 'Unsubscribe app from WABA failed');
        }

        return $data ?: ['success' => true];
    }

    public function listSubscribedApps(string $wabaId, string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get("{$this->baseUrl}/{$this->apiVersion}/{$wabaId}/subscribed_apps");

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->warning('List WABA subscribed apps failed', [
                'waba_id' => $wabaId,
                'status' => $response->status(),
                'error' => $data['error'] ?? $data,
            ]);
            throw new \RuntimeException($data['error']['message'] ?? 'List subscribed apps failed');
        }

        return $data['data'] ?? [];
    }

    public function subscribeAppToWabaWithCallback(
        string $wabaId,
        string $accessToken,
        ?string $callbackUrl,
        ?string $verifyToken
    ): array {
        $payload = [];
        if ($callbackUrl) {
            $payload['override_callback_uri'] = $callbackUrl;
        }
        if ($verifyToken) {
            $payload['verify_token'] = $verifyToken;
        }

        $response = Http::withToken($accessToken)
            ->post("{$this->baseUrl}/{$this->apiVersion}/{$wabaId}/subscribed_apps", $payload);

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->warning('Subscribe app to WABA with callback failed', [
                'waba_id' => $wabaId,
                'status' => $response->status(),
                'error' => $data['error'] ?? $data,
            ]);
            throw new \RuntimeException($data['error']['message'] ?? 'Subscribe app to WABA failed');
        }

        return $data;
    }

    public function listPhoneNumbers(string $wabaId, string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get("{$this->baseUrl}/{$this->apiVersion}/{$wabaId}/phone_numbers");

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->error('List WABA phone numbers failed', [
                'waba_id' => $wabaId,
                'status' => $response->status(),
                'error' => $data['error'] ?? $data]);
            throw new \RuntimeException($data['error']['message'] ?? 'List phone numbers failed');
        }

        return $data['data'] ?? [];
    }

    public function getWabaDetails(string $wabaId, string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get("{$this->baseUrl}/{$this->apiVersion}/{$wabaId}", [
                'fields' => 'id,name,currency,timezone_id,message_template_namespace,business_verification_status,account_review_status']);

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->warning('Get WABA details failed', [
                'waba_id' => $wabaId,
                'status' => $response->status(),
                'error' => $data['error'] ?? $data,
            ]);
            throw new \RuntimeException($data['error']['message'] ?? 'Get WABA details failed');
        }

        return $data;
    }

    public function listOwnedWhatsAppBusinessAccounts(string $businessId, string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get("{$this->baseUrl}/{$this->apiVersion}/{$businessId}/owned_whatsapp_business_accounts", [
                'fields' => 'id,name,currency,timezone_id,message_template_namespace,business_verification_status,account_review_status']);

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->warning('List owned WABAs failed', [
                'business_id' => $businessId,
                'status' => $response->status(),
                'error' => $data['error'] ?? $data,
            ]);
            throw new \RuntimeException($data['error']['message'] ?? 'List owned WhatsApp Business Accounts failed');
        }

        return $data['data'] ?? [];
    }

    public function listMessageTemplates(string $wabaId, string $accessToken, array $params = []): array
    {
        $response = Http::withToken($accessToken)
            ->get("{$this->baseUrl}/{$this->apiVersion}/{$wabaId}/message_templates", $params + [
                'fields' => 'id,name,language,status,category,components,rejected_reason',
                'limit' => 100,
            ]);

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->warning('List message templates failed', [
                'waba_id' => $wabaId,
                'status' => $response->status(),
                'error' => $data['error'] ?? $data,
            ]);
            throw new \RuntimeException($data['error']['message'] ?? 'List message templates failed');
        }

        return $data;
    }

    public function registerPhoneNumber(string $phoneNumberId, string $pin, string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->post("{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}/register", [
                'messaging_product' => 'whatsapp',
                'pin' => $pin]);

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->warning('Register phone number failed', [
                'phone_number_id' => $phoneNumberId,
                'status' => $response->status(),
                'error' => $data['error'] ?? $data]);
            throw new \RuntimeException($data['error']['message'] ?? 'Register phone number failed');
        }

        return $data;
    }

    public function getPhoneNumberDetails(string $phoneNumberId, string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get("{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}", [
                'fields' => 'display_phone_number,verified_name,status,quality_rating,code_verification_status,name_status,messaging_limit_tier,platform_type,throughput']);

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->warning('Get phone number details failed', [
                'phone_number_id' => $phoneNumberId,
                'status' => $response->status(),
                'error' => $data['error'] ?? $data]);
            throw new \RuntimeException($data['error']['message'] ?? 'Get phone number details failed');
        }

        return $data;
    }

    public function getPhoneNumberSettings(string $phoneNumberId, string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get("{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}/settings");

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->warning('Get phone number settings failed', [
                'phone_number_id' => $phoneNumberId,
                'status' => $response->status(),
                'error' => $data['error'] ?? $data]);
            throw new \RuntimeException($data['error']['message'] ?? 'Get phone number settings failed');
        }

        return $data;
    }

    public function enablePhoneNumberCalling(string $phoneNumberId, string $accessToken, array $options = []): array
    {
        $calling = array_filter([
            'status' => 'ENABLED',
            'call_icon_visibility' => $options['call_icon_visibility'] ?? 'DEFAULT',
            'callback_permission_status' => ($options['callback_permission_status'] ?? true) ? 'ENABLED' : null,
        ], fn ($value) => $value !== null);

        $response = Http::withToken($accessToken)
            ->post("{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}/settings", [
                'calling' => $calling,
            ]);

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->warning('Enable phone number calling failed', [
                'phone_number_id' => $phoneNumberId,
                'status' => $response->status(),
                'error' => $data['error'] ?? $data]);
            throw new \RuntimeException($data['error']['message'] ?? 'Enable phone number calling failed');
        }

        return $data ?: ['success' => true, 'calling' => $calling];
    }

    public function requestPhoneNumberCode(string $phoneNumberId, string $accessToken, string $codeMethod = 'SMS', string $language = 'en'): array
    {
        $response = Http::withToken($accessToken)
            ->post("{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}/request_code", [
                'code_method' => strtoupper($codeMethod),
                'language' => $language,
            ]);

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->warning('Request phone number code failed', [
                'phone_number_id' => $phoneNumberId,
                'status' => $response->status(),
                'error' => $data['error'] ?? $data,
            ]);
            throw new \RuntimeException($data['error']['message'] ?? 'Request phone number code failed');
        }

        return $data ?: ['success' => true];
    }

    public function verifyPhoneNumberCode(string $phoneNumberId, string $accessToken, string $code): array
    {
        $response = Http::withToken($accessToken)
            ->post("{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}/verify_code", [
                'code' => $code,
            ]);

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->warning('Verify phone number code failed', [
                'phone_number_id' => $phoneNumberId,
                'status' => $response->status(),
                'error' => $data['error'] ?? $data,
            ]);
            throw new \RuntimeException($data['error']['message'] ?? 'Verify phone number code failed');
        }

        return $data ?: ['success' => true];
    }

    public function getBusinessProfile(string $phoneNumberId, string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get("{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}/whatsapp_business_profile", [
                'fields' => 'about,address,description,email,websites,vertical,profile_picture_url']);

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->warning('Get business profile failed', [
                'phone_number_id' => $phoneNumberId,
                'status' => $response->status(),
                'error' => $data['error'] ?? $data]);
            throw new \RuntimeException($data['error']['message'] ?? 'Get business profile failed');
        }

        return $data['data'][0] ?? $data;
    }

    public function updateBusinessProfile(string $phoneNumberId, string $accessToken, array $profile): array
    {
        $payload = array_filter([
            'messaging_product' => 'whatsapp',
            'about' => $profile['about'] ?? null,
            'address' => $profile['address'] ?? null,
            'description' => $profile['description'] ?? null,
            'email' => $profile['email'] ?? null,
            'profile_picture_handle' => $profile['profile_picture_handle'] ?? null,
            'vertical' => $profile['vertical'] ?? null,
            'websites' => $profile['websites'] ?? null,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        $response = Http::withToken($accessToken)
            ->post("{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}/whatsapp_business_profile", $payload);

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->warning('Update business profile failed', [
                'phone_number_id' => $phoneNumberId,
                'status' => $response->status(),
                'error' => $data['error'] ?? $data]);
            throw new \RuntimeException($data['error']['message'] ?? 'Update business profile failed');
        }

        return $data;
    }

    public function listFlows(string $wabaId, string $accessToken, array $params = []): array
    {
        $response = Http::withToken($accessToken)
            ->get("{$this->baseUrl}/{$this->apiVersion}/{$wabaId}/flows", $params + [
                'fields' => 'id,name,categories,preview,status,validation_errors,json_version,data_api_version,data_channel_uri,whatsapp_business_account',
                'limit' => 100,
            ]);

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->warning('List WhatsApp flows failed', [
                'waba_id' => $wabaId,
                'status' => $response->status(),
                'error' => $data['error'] ?? $data,
            ]);
            throw new \RuntimeException($data['error']['message'] ?? 'List WhatsApp flows failed');
        }

        return $data['data'] ?? [];
    }

    public function createFlow(string $wabaId, string $accessToken, array $payload): array
    {
        $response = Http::withToken($accessToken)
            ->post("{$this->baseUrl}/{$this->apiVersion}/{$wabaId}/flows", array_filter([
                'name' => $payload['name'] ?? null,
                'categories' => $payload['categories'] ?? null,
                'clone_flow_id' => $payload['clone_flow_id'] ?? null,
                'endpoint_uri' => $payload['data_channel_uri'] ?? $payload['endpoint_uri'] ?? null,
            ], fn ($value) => $value !== null && $value !== '' && $value !== []));

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->warning('Create WhatsApp flow failed', [
                'waba_id' => $wabaId,
                'status' => $response->status(),
                'error' => $data['error'] ?? $data,
            ]);
            throw new \RuntimeException($data['error']['message'] ?? 'Create WhatsApp flow failed');
        }

        return $data;
    }

    public function getFlow(string $flowId, string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get("{$this->baseUrl}/{$this->apiVersion}/{$flowId}", [
                'fields' => 'id,name,categories,preview,status,validation_errors,json_version,data_api_version,data_channel_uri,whatsapp_business_account',
            ]);

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->warning('Get WhatsApp flow failed', [
                'flow_id' => $flowId,
                'status' => $response->status(),
                'error' => $data['error'] ?? $data,
            ]);
            throw new \RuntimeException($data['error']['message'] ?? 'Get WhatsApp flow failed');
        }

        return $data;
    }

    public function updateFlowMetadata(string $flowId, string $accessToken, array $payload): array
    {
        $response = Http::withToken($accessToken)
            ->post("{$this->baseUrl}/{$this->apiVersion}/{$flowId}", array_filter([
                'name' => $payload['name'] ?? null,
                'categories' => $payload['categories'] ?? null,
                'endpoint_uri' => $payload['data_channel_uri'] ?? $payload['endpoint_uri'] ?? null,
            ], fn ($value) => $value !== null && $value !== '' && $value !== []));

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->warning('Update WhatsApp flow failed', [
                'flow_id' => $flowId,
                'status' => $response->status(),
                'error' => $data['error'] ?? $data,
            ]);
            throw new \RuntimeException($data['error']['message'] ?? 'Update WhatsApp flow failed');
        }

        return $data ?: ['success' => true];
    }

    public function updateFlowJson(string $flowId, string $accessToken, array|string $flowJson): array
    {
        $json = is_array($flowJson) ? json_encode($flowJson, JSON_UNESCAPED_SLASHES) : $flowJson;

        $response = Http::withToken($accessToken)
            ->attach('file', $json, 'flow.json', ['Content-Type' => 'application/json'])
            ->post("{$this->baseUrl}/{$this->apiVersion}/{$flowId}/assets", [
                'name' => 'flow.json',
                'asset_type' => 'FLOW_JSON',
            ]);

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->warning('Update WhatsApp flow JSON failed', [
                'flow_id' => $flowId,
                'status' => $response->status(),
                'error' => $data['error'] ?? $data,
            ]);
            throw new \RuntimeException($data['error']['message'] ?? 'Update WhatsApp flow JSON failed');
        }

        return $data ?: ['success' => true];
    }

    public function publishFlow(string $flowId, string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->post("{$this->baseUrl}/{$this->apiVersion}/{$flowId}/publish");

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->warning('Publish WhatsApp flow failed', [
                'flow_id' => $flowId,
                'status' => $response->status(),
                'error' => $data['error'] ?? $data,
            ]);
            throw new \RuntimeException($data['error']['message'] ?? 'Publish WhatsApp flow failed');
        }

        return $data ?: ['success' => true];
    }

    public function deprecateFlow(string $flowId, string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->post("{$this->baseUrl}/{$this->apiVersion}/{$flowId}/deprecate");

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('whatsapp')->warning('Deprecate WhatsApp flow failed', [
                'flow_id' => $flowId,
                'status' => $response->status(),
                'error' => $data['error'] ?? $data,
            ]);
            throw new \RuntimeException($data['error']['message'] ?? 'Deprecate WhatsApp flow failed');
        }

        return $data ?: ['success' => true];
    }

    public function uploadProfilePicture(UploadedFile $file, string $accessToken): string
    {
        $appId = config('whatsapp.meta.app_id');
        if (empty($appId)) {
            throw new \RuntimeException('Meta App ID is required to upload profile pictures.');
        }

        $sessionResponse = Http::post("{$this->baseUrl}/{$this->apiVersion}/{$appId}/uploads", [
            'file_name' => $file->getClientOriginalName() ?: 'profile.jpg',
            'file_length' => $file->getSize(),
            'file_type' => $file->getMimeType() ?: 'image/jpeg',
            'access_token' => $accessToken]);

        $sessionData = $sessionResponse->json();
        if (! $sessionResponse->successful()) {
            Log::channel('whatsapp')->warning('Create profile picture upload session failed', [
                'status' => $sessionResponse->status(),
                'error' => $sessionData['error'] ?? $sessionData]);
            throw new \RuntimeException($sessionData['error']['message'] ?? 'Create profile picture upload session failed');
        }

        $uploadId = $sessionData['id'] ?? null;
        if (! $uploadId) {
            throw new \RuntimeException('Meta did not return a profile picture upload session.');
        }

        $uploadResponse = Http::withHeaders([
            'Authorization' => 'OAuth '.$accessToken,
            'file_offset' => '0',
        ])->withBody(file_get_contents($file->getRealPath()), $file->getMimeType() ?: 'image/jpeg')
            ->post("{$this->baseUrl}/{$this->apiVersion}/{$uploadId}");

        $uploadData = $uploadResponse->json();
        if (! $uploadResponse->successful()) {
            Log::channel('whatsapp')->warning('Upload profile picture data failed', [
                'status' => $uploadResponse->status(),
                'error' => $uploadData['error'] ?? $uploadData]);
            throw new \RuntimeException($uploadData['error']['message'] ?? 'Upload profile picture data failed');
        }

        $handle = $uploadData['h'] ?? $uploadData['handle'] ?? null;
        if (! $handle) {
            throw new \RuntimeException('Meta did not return a profile picture handle.');
        }

        return $handle;
    }

    public function getApiVersion(): string
    {
        return $this->apiVersion;
    }
}
