<?php

namespace App\Services\Voice;

use App\Models\PlatformSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class VoiceProviderService
{
    public function transcribe(string|UploadedFile $audio, ?string $mimeType = null, ?string $filename = null): array
    {
        return $this->withFallback('stt', function (string $provider) use ($audio, $mimeType, $filename) {
            return match ($provider) {
                'elevenlabs' => $this->transcribeWithElevenLabs($audio, $mimeType, $filename),
                'openai' => $this->transcribeWithOpenAi($audio, $mimeType, $filename),
                default => throw new RuntimeException("Unknown speech-to-text provider: {$provider}"),
            };
        });
    }

    public function synthesize(string $text, array $options = []): array
    {
        return $this->withFallback('tts', function (string $provider) use ($text, $options) {
            return match ($provider) {
                'elevenlabs' => $this->synthesizeWithElevenLabs($text, $options),
                'openai' => $this->synthesizeWithOpenAi($text, $options),
                default => throw new RuntimeException("Unknown text-to-speech provider: {$provider}"),
            };
        });
    }

    public function settings(): array
    {
        return [
            'enabled' => (bool) PlatformSetting::get('ai.voice_enabled', false),
            'stt_provider' => (string) PlatformSetting::get('ai.voice_stt_provider', 'elevenlabs'),
            'tts_provider' => (string) PlatformSetting::get('ai.voice_tts_provider', 'elevenlabs'),
            'fallback_provider' => (string) PlatformSetting::get('ai.voice_fallback_provider', 'openai'),
            'elevenlabs_api_key' => (string) PlatformSetting::get('ai.elevenlabs_api_key', ''),
            'elevenlabs_stt_model' => (string) PlatformSetting::get('ai.elevenlabs_stt_model', 'scribe_v1'),
            'elevenlabs_tts_model' => (string) PlatformSetting::get('ai.elevenlabs_tts_model', 'eleven_multilingual_v2'),
            'elevenlabs_voice_id' => (string) PlatformSetting::get('ai.elevenlabs_voice_id', '21m00Tcm4TlvDq8ikWAM'),
            'openai_api_key' => (string) (PlatformSetting::get('ai.voice_openai_api_key') ?: PlatformSetting::get('ai.openai_api_key', '')),
            'openai_stt_model' => (string) PlatformSetting::get('ai.openai_stt_model', 'whisper-1'),
            'openai_tts_model' => (string) PlatformSetting::get('ai.openai_tts_model', 'tts-1'),
            'openai_voice' => (string) PlatformSetting::get('ai.openai_voice', 'alloy'),
        ];
    }

    protected function withFallback(string $type, callable $callback): array
    {
        $settings = $this->settings();
        if (! $settings['enabled']) {
            throw new RuntimeException('Voice AI is disabled in platform AI settings.');
        }

        $primary = $type === 'tts' ? $settings['tts_provider'] : $settings['stt_provider'];
        $fallback = $settings['fallback_provider'];
        $errors = [];

        foreach (array_values(array_unique(array_filter([$primary, $fallback], fn ($provider) => $provider && $provider !== 'none'))) as $provider) {
            try {
                $result = $callback($provider);
                $result['provider'] = $provider;
                $result['fallback_used'] = $provider !== $primary;

                return $result;
            } catch (\Throwable $e) {
                $errors[$provider] = $e->getMessage();
            }
        }

        throw new RuntimeException('Voice provider failed: '.json_encode($errors, JSON_UNESCAPED_SLASHES));
    }

    protected function transcribeWithElevenLabs(string|UploadedFile $audio, ?string $mimeType, ?string $filename): array
    {
        $settings = $this->settings();
        $apiKey = trim($settings['elevenlabs_api_key']);
        if ($apiKey === '') {
            throw new RuntimeException('ElevenLabs API key is not configured.');
        }

        $file = $this->audioFile($audio, $mimeType, $filename);
        $response = Http::withHeaders(['xi-api-key' => $apiKey])
            ->timeout(90)
            ->attach('file', $file['contents'], $file['filename'])
            ->post('https://api.elevenlabs.io/v1/speech-to-text', [
                'model_id' => $settings['elevenlabs_stt_model'],
            ]);

        $this->throwIfFailed($response, 'ElevenLabs speech-to-text failed');

        return [
            'text' => trim((string) ($response->json('text') ?? '')),
            'raw' => $response->json(),
        ];
    }

    protected function transcribeWithOpenAi(string|UploadedFile $audio, ?string $mimeType, ?string $filename): array
    {
        $settings = $this->settings();
        $apiKey = trim($settings['openai_api_key']);
        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured for voice fallback.');
        }

        $file = $this->audioFile($audio, $mimeType, $filename);
        $response = Http::withToken($apiKey)
            ->timeout(90)
            ->attach('file', $file['contents'], $file['filename'])
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => $settings['openai_stt_model'],
            ]);

        $this->throwIfFailed($response, 'OpenAI speech-to-text failed');

        return [
            'text' => trim((string) ($response->json('text') ?? '')),
            'raw' => $response->json(),
        ];
    }

    protected function synthesizeWithElevenLabs(string $text, array $options): array
    {
        $settings = $this->settings();
        $apiKey = trim($settings['elevenlabs_api_key']);
        if ($apiKey === '') {
            throw new RuntimeException('ElevenLabs API key is not configured.');
        }

        $voiceId = trim((string) ($options['voice_id'] ?? $settings['elevenlabs_voice_id']));
        if ($voiceId === '') {
            throw new RuntimeException('ElevenLabs voice ID is required.');
        }

        $response = Http::withHeaders([
            'xi-api-key' => $apiKey,
            'Accept' => 'audio/mpeg',
        ])->timeout(90)->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}", [
            'text' => $text,
            'model_id' => $options['model'] ?? $settings['elevenlabs_tts_model'],
            'voice_settings' => [
                'stability' => (float) ($options['stability'] ?? 0.45),
                'similarity_boost' => (float) ($options['similarity_boost'] ?? 0.75),
            ],
        ]);

        $this->throwIfFailed($response, 'ElevenLabs text-to-speech failed');

        return $this->storeAudio($response->body(), 'mpeg', 'audio/mpeg');
    }

    protected function synthesizeWithOpenAi(string $text, array $options): array
    {
        $settings = $this->settings();
        $apiKey = trim($settings['openai_api_key']);
        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured for voice fallback.');
        }

        $response = Http::withToken($apiKey)
            ->timeout(90)
            ->post('https://api.openai.com/v1/audio/speech', [
                'model' => $options['model'] ?? $settings['openai_tts_model'],
                'voice' => $options['voice'] ?? $settings['openai_voice'],
                'input' => $text,
                'response_format' => 'mp3',
            ]);

        $this->throwIfFailed($response, 'OpenAI text-to-speech failed');

        return $this->storeAudio($response->body(), 'mp3', 'audio/mpeg');
    }

    protected function audioFile(string|UploadedFile $audio, ?string $mimeType, ?string $filename): array
    {
        if ($audio instanceof UploadedFile) {
            $path = $audio->getRealPath();
            if (! $path || ! is_file($path)) {
                throw new RuntimeException('Uploaded audio file is not readable.');
            }

            return [
                'contents' => file_get_contents($path),
                'filename' => $audio->getClientOriginalName() ?: 'audio.webm',
            ];
        }

        if (is_file($audio)) {
            $contents = file_get_contents($audio);
            if ($contents === false) {
                throw new RuntimeException('Audio file is not readable.');
            }

            return [
                'contents' => $contents,
                'filename' => $filename ?: basename($audio),
            ];
        }

        return [
            'contents' => $audio,
            'filename' => $filename ?: 'audio.'.match ($mimeType) {
                'audio/mpeg' => 'mp3',
                'audio/wav', 'audio/x-wav' => 'wav',
                'audio/ogg', 'application/ogg' => 'ogg',
                default => 'webm',
            },
        ];
    }

    protected function storeAudio(string $contents, string $extension, string $mimeType): array
    {
        $path = 'voice/generated/'.date('Y/m').'/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $contents);

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'mime_type' => $mimeType,
            'bytes' => strlen($contents),
        ];
    }

    protected function throwIfFailed(Response $response, string $message): void
    {
        if ($response->successful()) {
            return;
        }

        $error = $response->json('error.message')
            ?? $response->json('detail.message')
            ?? $response->json('message')
            ?? Str::limit($response->body(), 500);

        throw new RuntimeException($message.': '.$error);
    }
}
