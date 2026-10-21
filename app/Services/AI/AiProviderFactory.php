<?php

namespace App\Services\AI;

use App\Models\Account;
use App\Models\AccountIntegration;
use App\Models\PlatformSetting;
use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\OpenAiProvider;
use RuntimeException;

class AiProviderFactory
{
    protected static int $timeout = 30;

    public static function forAccount(?Account $account): AiProviderInterface
    {
        $integration = $account ? self::workspaceIntegration($account) : null;

        if ($integration) {
            $provider = trim((string) $integration->configValue('provider', 'openai')) ?: 'openai';
            $apiKey = self::workspaceApiKey($integration, $provider);

            if ($apiKey) {
                try {
                    return self::makeProvider($provider, [
                        'api_key' => $apiKey,
                        'model' => self::workspaceModel($integration, $provider),
                    ]);
                } catch (RuntimeException $e) {
                    if (! self::allowPlatformFallback($integration)) {
                        throw $e;
                    }
                }
            }

            if (! self::allowPlatformFallback($integration)) {
                throw new RuntimeException('Workspace AI key is missing and platform fallback is disabled.');
            }
        }

        return self::fromPlatformSettings();
    }

    public static function generationSettingsForAccount(?Account $account): array
    {
        $integration = $account ? self::workspaceIntegration($account) : null;
        $config = $integration?->config ?? [];

        return [
            'system_prompt' => filled($config['system_prompt'] ?? null)
                ? (string) $config['system_prompt']
                : PlatformSetting::get('ai.system_prompt'),
            'temperature' => filled($config['temperature'] ?? null)
                ? (float) $config['temperature']
                : (float) PlatformSetting::get('ai.temperature', config('ai.temperature', 0.2)),
            'max_tokens' => filled($config['max_tokens'] ?? null)
                ? (int) $config['max_tokens']
                : (int) PlatformSetting::get('ai.max_tokens', config('ai.max_tokens', 300)),
        ];
    }

    public static function hasWorkspaceProvider(?Account $account): bool
    {
        return $account && self::workspaceIntegration($account) !== null;
    }

    public static function shouldFallbackToPlatform(?Account $account): bool
    {
        $integration = $account ? self::workspaceIntegration($account) : null;

        return $integration ? self::allowPlatformFallback($integration) : true;
    }

    public static function willUsePlatformProvider(?Account $account): bool
    {
        $integration = $account ? self::workspaceIntegration($account) : null;
        if (! $integration) {
            return true;
        }

        $provider = trim((string) $integration->configValue('provider', 'openai')) ?: 'openai';

        return blank(self::workspaceApiKey($integration, $provider));
    }

    public static function fromPlatformSettings(): AiProviderInterface
    {
        $provider = PlatformSetting::get('ai.provider', config('ai.provider', 'openai'));

        return self::makeProvider($provider, [
            'api_key' => match ($provider) {
                'openai' => PlatformSetting::get('ai.openai_api_key') ?: config('ai.openai.api_key'),
                'anthropic' => PlatformSetting::get('ai.anthropic_api_key') ?: config('ai.anthropic.api_key'),
                'gemini' => PlatformSetting::get('ai.gemini_api_key') ?: config('ai.gemini.api_key'),
                default => null,
            },
            'model' => match ($provider) {
                'openai' => PlatformSetting::get('ai.openai_model', config('ai.openai.model', 'gpt-4o-mini')),
                'anthropic' => PlatformSetting::get('ai.anthropic_model', config('ai.anthropic.model', 'claude-3-5-haiku-20241022')),
                'gemini' => PlatformSetting::get('ai.gemini_model', config('ai.gemini.model', 'gemini-2.0-flash')),
                default => null,
            },
        ]);
    }

    protected static function makeProvider(mixed $provider, array $config): AiProviderInterface
    {
        $provider = trim((string) $provider);
        $apiKey = trim((string) ($config['api_key'] ?? ''));

        return match ($provider) {
            'openai' => new OpenAiProvider(
                apiKey: $apiKey ?: throw new RuntimeException('OpenAI API key not configured.'),
                model: trim((string) ($config['model'] ?? '')) ?: 'gpt-4o-mini',
                timeout: self::$timeout
            ),
            'anthropic' => new AnthropicProvider(
                apiKey: $apiKey ?: throw new RuntimeException('Anthropic API key not configured.'),
                model: trim((string) ($config['model'] ?? '')) ?: 'claude-3-5-haiku-20241022',
                version: config('ai.anthropic.version', '2023-06-01'),
                timeout: self::$timeout
            ),
            'gemini' => new GeminiProvider(
                apiKey: $apiKey ?: throw new RuntimeException('Gemini API key not configured.'),
                model: self::normalizeGeminiModel($config['model'] ?? 'gemini-2.0-flash'),
                timeout: self::$timeout
            ),
            default => throw new RuntimeException("Unknown AI provider: {$provider}"),
        };
    }

    protected static function workspaceIntegration(Account $account): ?AccountIntegration
    {
        return AccountIntegration::query()
            ->where('account_id', $account->id)
            ->where('provider', 'workspace-ai')
            ->where('status', 'connected')
            ->first();
    }

    protected static function workspaceApiKey(AccountIntegration $integration, string $provider): ?string
    {
        return match ($provider) {
            'openai' => $integration->secret('openai_api_key'),
            'anthropic' => $integration->secret('anthropic_api_key'),
            'gemini' => $integration->secret('gemini_api_key'),
            default => null,
        };
    }

    protected static function workspaceModel(AccountIntegration $integration, string $provider): ?string
    {
        return match ($provider) {
            'openai' => $integration->configValue('openai_model') ?: config('ai.openai.model', 'gpt-4o-mini'),
            'anthropic' => $integration->configValue('anthropic_model') ?: config('ai.anthropic.model', 'claude-3-5-haiku-20241022'),
            'gemini' => $integration->configValue('gemini_model') ?: config('ai.gemini.model', 'gemini-2.0-flash'),
            default => null,
        };
    }

    protected static function allowPlatformFallback(AccountIntegration $integration): bool
    {
        $mode = (string) $integration->configValue('fallback_mode', '');
        if ($mode !== '') {
            return $mode === 'platform_fallback';
        }

        $value = $integration->configValue('allow_platform_fallback', true);

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return true;
    }

    protected static function normalizeGeminiModel(mixed $configuredModel): string
    {
        $model = trim((string) $configuredModel);
        if ($model === '') {
            return 'gemini-2.0-flash';
        }

        // Keep tenants on a widely available free-tier-friendly model.
        if (in_array(strtolower($model), ['gemini-pro', 'gemini-1.5-flash'], true)) {
            return 'gemini-2.0-flash';
        }

        return $model;
    }
}
