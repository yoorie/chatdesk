<?php

namespace App\Services\Voice;

use App\Models\AiAgent;
use App\Models\WhatsAppCall;
use RuntimeException;

class WhatsAppCallVoiceBridgeService
{
    public function __construct(protected VoiceProviderService $voiceProvider) {}

    public function assertReady(WhatsAppCall $call): void
    {
        $settings = $this->voiceProvider->settings();
        if (! $settings['enabled']) {
            throw new RuntimeException('Voice AI is disabled in Platform Settings -> AI.');
        }

        if (! $call->ai_agent_id) {
            throw new RuntimeException('No AI agent is assigned to this call.');
        }

        if (! AiAgent::where('account_id', $call->account_id)->whereKey($call->ai_agent_id)->where('is_active', true)->exists()) {
            throw new RuntimeException('Assigned AI agent is unavailable.');
        }
    }

    public function diagnostics(WhatsAppCall $call): array
    {
        $settings = $this->voiceProvider->settings();
        $checks = [
            [
                'key' => 'voice_enabled',
                'ok' => $settings['enabled'],
                'message' => $settings['enabled'] ? 'Central voice AI is enabled.' : 'Enable Voice AI in Platform Settings -> AI.',
            ],
            [
                'key' => 'agent_assigned',
                'ok' => (bool) $call->ai_agent_id,
                'message' => $call->ai_agent_id ? 'AI agent is assigned.' : 'No AI agent is assigned to this call.',
            ],
            [
                'key' => 'stt_provider',
                'ok' => $this->providerHasKey($settings['stt_provider'], $settings),
                'message' => 'STT provider: '.$settings['stt_provider'],
            ],
            [
                'key' => 'tts_provider',
                'ok' => $this->providerHasKey($settings['tts_provider'], $settings),
                'message' => 'TTS provider: '.$settings['tts_provider'],
            ],
            [
                'key' => 'fallback_provider',
                'ok' => $settings['fallback_provider'] === 'none' || $this->providerHasKey($settings['fallback_provider'], $settings),
                'message' => 'Fallback provider: '.$settings['fallback_provider'],
            ],
        ];

        return [
            'ready' => collect($checks)->every(fn ($check) => $check['ok']),
            'checks' => $checks,
        ];
    }

    protected function providerHasKey(string $provider, array $settings): bool
    {
        return match ($provider) {
            'elevenlabs' => trim($settings['elevenlabs_api_key']) !== '',
            'openai' => trim($settings['openai_api_key']) !== '',
            'none', '' => true,
            default => false,
        };
    }
}
