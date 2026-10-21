<?php

namespace App\Services\AI\Contracts;

interface AiProviderInterface
{
    /**
     * Generate a single text completion from system + user prompt.
     */
    public function generate(string $systemPrompt, string $userPrompt, float $temperature, int $maxTokens): string;
}
