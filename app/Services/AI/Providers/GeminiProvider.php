<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GeminiProvider implements AiProviderInterface
{
    public function __construct(
        protected string $apiKey,
        protected string $model = 'gemini-2.0-flash',
        protected int $timeout = 30
    ) {}

    public function generate(string $systemPrompt, string $userPrompt, float $temperature, int $maxTokens): string
    {
        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $systemPrompt."\n\n".$userPrompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxTokens,
            ],
        ];

        $attempts = $this->buildAttempts($this->model);
        $lastError = null;

        foreach ($attempts as [$apiVersion, $model]) {
            $url = "https://generativelanguage.googleapis.com/{$apiVersion}/models/{$model}:generateContent";
            $response = Http::timeout($this->timeout)
                ->withQueryParameters(['key' => $this->apiKey])
                ->post($url, $this->payloadForModel($payload, $model));

            if ($response->successful()) {
                $content = $response->json('candidates.0.content.parts.0.text', '');

                return Str::of($content)->trim()->toString();
            }

            $lastError = $response->json('error.message') ?? $response->body();
        }

        throw new \RuntimeException('Gemini request failed: '.($lastError ?: 'Unknown Gemini error.'));
    }

    protected function payloadForModel(array $payload, string $model): array
    {
        if (Str::contains($model, '2.5-flash')) {
            $payload['generationConfig']['thinkingConfig'] = [
                'thinkingBudget' => 0,
            ];
        }

        return $payload;
    }

    protected function buildAttempts(string $requestedModel): array
    {
        $base = trim($requestedModel) !== '' ? trim($requestedModel) : 'gemini-2.0-flash';

        $models = [$base];
        if ($base === 'gemini-1.5-flash') {
            $models[] = 'gemini-1.5-flash-latest';
            $models[] = 'gemini-2.0-flash';
        }
        if ($base === 'gemini-pro') {
            $models[] = 'gemini-2.0-flash';
        }

        $models = array_values(array_unique($models));
        $attempts = [];
        foreach ($models as $model) {
            $attempts[] = ['v1beta', $model];
            $attempts[] = ['v1', $model];
        }

        return $attempts;
    }
}
