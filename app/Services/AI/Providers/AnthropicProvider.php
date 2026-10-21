<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AnthropicProvider implements AiProviderInterface
{
    public function __construct(
        protected string $apiKey,
        protected string $model = 'claude-3-5-haiku-20241022',
        protected string $version = '2023-06-01',
        protected int $timeout = 30
    ) {}

    public function generate(string $systemPrompt, string $userPrompt, float $temperature, int $maxTokens): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => $this->version,
            'content-type' => 'application/json',
        ])
            ->timeout($this->timeout)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $maxTokens,
                'system' => $systemPrompt,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $userPrompt,
                    ],
                ],
                'temperature' => $temperature,
            ]);

        if (! $response->successful()) {
            $message = $response->json('error.message') ?? $response->body();
            throw new \RuntimeException('Anthropic request failed: '.$message);
        }

        $content = $response->json('content.0.text', '');

        return Str::of($content)->trim()->toString();
    }
}
