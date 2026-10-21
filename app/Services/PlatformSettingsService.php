<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class PlatformSettingsService
{
    /**
     * Get a setting value with fallback to config.
     */
    public function get(string $key, $default = null)
    {
        return PlatformSetting::get($key, $default);
    }

    /**
     * Get a setting value, treating saved blank strings as missing.
     */
    public function getNonBlank(string $key, $default = null)
    {
        $value = $this->get($key);

        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === null || $value === '' ? $default : $value;
    }

    /**
     * Get general settings.
     */
    public function getGeneral(): array
    {
        return [
            'platform_name' => $this->get('general.platform_name', config('app.name')),
            'platform_url' => $this->get('general.platform_url', config('app.url')),
            'support_email' => $this->get('general.support_email', config('mail.from.address')),
            'support_phone' => $this->get('general.support_phone'),
            'timezone' => $this->get('general.timezone', config('app.timezone', 'UTC')),
            'locale' => $this->get('general.locale', config('app.locale', 'en')),
            'date_format' => $this->get('general.date_format', 'Y-m-d'),
            'time_format' => $this->get('general.time_format', '24'),
            'maintenance_mode' => $this->get('general.maintenance_mode', false),
            'maintenance_message' => $this->get('general.maintenance_message', 'We are currently performing scheduled maintenance. Please check back shortly.')];
    }

    /**
     * Get security settings.
     */
    public function getSecurity(): array
    {
        return [
            'password_min_length' => $this->get('security.password_min_length', 8),
            'password_max_length' => $this->get('security.password_max_length', 128),
            'password_require_uppercase' => $this->get('security.password_require_uppercase', false),
            'password_require_lowercase' => $this->get('security.password_require_lowercase', false),
            'password_require_numbers' => $this->get('security.password_require_numbers', false),
            'password_require_symbols' => $this->get('security.password_require_symbols', false),
            'password_expiry_days' => $this->get('security.password_expiry_days', 0),
            'password_history_count' => $this->get('security.password_history_count', 0),
            'require_2fa' => $this->get('security.require_2fa', false),
            'session_timeout' => $this->get('security.session_timeout', 120),
            'max_login_attempts' => $this->get('security.max_login_attempts', 5),
            'lockout_duration' => $this->get('security.lockout_duration', 15),
            'api_rate_limit' => $this->get('security.api_rate_limit', 60),
            'web_rate_limit' => $this->get('security.web_rate_limit', 120),
            'ip_whitelist' => $this->get('security.ip_whitelist')];
    }

    /**
     * Get feature flags.
     */
    public function getFeatures(): array
    {
        return [
            'user_registration' => $this->get('features.user_registration', true),
            'email_verification' => $this->get('features.email_verification', false),
            'account_creation' => $this->get('features.account_creation', true),
            'public_api' => $this->get('features.public_api', false),
            'webhooks' => $this->get('features.webhooks', true),
            'analytics' => $this->get('features.analytics', true)];
    }

    /**
     * Check if a feature is enabled.
     */
    public function isFeatureEnabled(string $feature): bool
    {
        $features = $this->getFeatures();

        return $features[$feature] ?? false;
    }

    /**
     * Check if maintenance mode is enabled.
     */
    public function isMaintenanceMode(): bool
    {
        $general = $this->getGeneral();

        return $general['maintenance_mode'] ?? false;
    }

    /**
     * Get password validation rules based on platform settings.
     */
    public function getPasswordRules(): array
    {
        $security = $this->getSecurity();

        $rules = ['required', 'confirmed'];

        $passwordRule = \Illuminate\Validation\Rules\Password::min($security['password_min_length']);

        if ($security['password_max_length'] < 128) {
            $passwordRule = $passwordRule->max($security['password_max_length']);
        }

        if ($security['password_require_uppercase']) {
            $passwordRule = $passwordRule->letters()->mixedCase();
        } elseif ($security['password_require_lowercase']) {
            $passwordRule = $passwordRule->letters();
        }

        if ($security['password_require_numbers']) {
            $passwordRule = $passwordRule->numbers();
        }

        if ($security['password_require_symbols']) {
            $passwordRule = $passwordRule->symbols();
        }

        $rules[] = $passwordRule;

        return $rules;
    }

    /**
     * Apply mail configuration from platform settings.
     */
    public function applyMailConfig(array $overrides = []): void
    {
        $value = fn (string $key, mixed $default = null) => array_key_exists($key, $overrides)
            ? $overrides[$key]
            : $this->get("mail.{$key}", $default);

        $driver = (string) ($value('driver', config('mail.default', 'smtp')) ?: 'smtp');
        $encryption = $value('encryption', config('mail.mailers.smtp.encryption', 'tls'));
        $encryption = $encryption === 'none' ? null : $encryption;

        config([
            'mail.default' => $driver,
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $value('host', config('mail.mailers.smtp.host')),
            'mail.mailers.smtp.port' => (int) ($value('port', config('mail.mailers.smtp.port', 587)) ?: 587),
            'mail.mailers.smtp.username' => $value('username', config('mail.mailers.smtp.username')),
            'mail.mailers.smtp.password' => $value('password', config('mail.mailers.smtp.password')),
            'mail.mailers.smtp.encryption' => $encryption,
            'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : null,
            'mail.from.address' => $value('from_address', config('mail.from.address')),
            'mail.from.name' => $value('from_name', config('mail.from.name')),
        ]);

        $mailManager = app('mail.manager');
        if (method_exists($mailManager, 'forgetMailers')) {
            $mailManager->forgetMailers();
        }
    }

    /**
     * Apply Pusher configuration from platform settings.
     */
    public function applyPusherConfig(): void
    {
        $appId = $this->getNonBlank('pusher.app_id', config('broadcasting.connections.pusher.app_id'));
        $key = $this->getNonBlank('pusher.key', config('broadcasting.connections.pusher.key'));
        $secret = $this->getNonBlank('pusher.secret', config('broadcasting.connections.pusher.secret'));
        $cluster = $this->getNonBlank('pusher.cluster', config('broadcasting.connections.pusher.options.cluster'));

        if ($appId) {
            config(['broadcasting.connections.pusher.app_id' => $appId]);
        }

        if ($key) {
            config(['broadcasting.connections.pusher.key' => $key]);
        }

        if ($secret) {
            config(['broadcasting.connections.pusher.secret' => $secret]);
        }

        if ($cluster) {
            config(['broadcasting.connections.pusher.options.cluster' => $cluster]);
        }

        if ($appId && $key && $secret) {
            config(['broadcasting.default' => 'pusher']);
        }
    }

    /**
     * Apply WhatsApp Meta configuration from platform settings.
     */
    public function applyWhatsAppConfig(): void
    {
        $appId = $this->get('whatsapp.meta_app_id', config('whatsapp.meta.app_id'));
        $appSecret = $this->get('whatsapp.meta_app_secret', config('whatsapp.meta.app_secret'));
        $systemUserToken = $this->get('whatsapp.system_user_token', config('whatsapp.meta.system_user_token'));
        $embeddedConfigId = $this->get('whatsapp.embedded_signup_config_id', config('whatsapp.meta.embedded_signup_config_id'));
        $apiVersion = $this->get('whatsapp.api_version', config('whatsapp.meta.api_version', 'v25.0'));
        $embeddedEnabled = $this->get('whatsapp.embedded_enabled');

        if ($appId) {
            config(['whatsapp.meta.app_id' => $appId]);
        }
        if ($appSecret) {
            config(['whatsapp.meta.app_secret' => $appSecret]);
        }
        if ($systemUserToken) {
            config(['whatsapp.meta.system_user_token' => $systemUserToken]);
        }
        if ($embeddedConfigId) {
            config(['whatsapp.meta.embedded_signup_config_id' => $embeddedConfigId]);
        }
        if ($apiVersion) {
            config(['whatsapp.meta.api_version' => $apiVersion]);
        }
        if ($embeddedEnabled !== null) {
            config(['whatsapp.meta.embedded_enabled' => (bool) $embeddedEnabled]);
        }
    }

    /**
     * Apply timezone and locale from platform settings.
     */
    public function applyLocalization(): void
    {
        $timezone = $this->get('general.timezone', config('app.timezone', 'UTC'));
        $locale = $this->get('general.locale', config('app.locale', 'en'));

        if ($timezone) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }

        if ($locale) {
            config(['app.locale' => $locale]);
            app()->setLocale($locale);
        }
    }

    /**
     * Apply general configuration from platform settings.
     */
    public function applyGeneralConfig(): void
    {
        $platformUrl = app()->environment('local') && config('app.local_server_url')
            ? config('app.local_server_url')
            : $this->get('general.platform_url', config('app.url'));
        $platformName = $this->get('branding.platform_name', config('app.name'));

        if ($platformUrl) {
            config(['app.url' => $platformUrl]);
            URL::forceRootUrl($platformUrl);
        }

        if ($platformName) {
            config(['app.name' => $platformName]);
        }
    }

    /**
     * Apply storage configuration from platform settings.
     */
    public function applyStorageConfig(): void
    {
        $default = $this->get('storage.default', config('filesystems.default', 'local'));

        if ($default) {
            config(['filesystems.default' => $default]);
        }

        // If using S3, apply S3 configuration
        if ($default === 's3') {
            $s3Key = $this->get('storage.s3_key', config('filesystems.disks.s3.key'));
            $s3Secret = $this->get('storage.s3_secret', config('filesystems.disks.s3.secret'));
            $s3Region = $this->get('storage.s3_region', config('filesystems.disks.s3.region'));
            $s3Bucket = $this->get('storage.s3_bucket', config('filesystems.disks.s3.bucket'));

            if ($s3Key) {
                config(['filesystems.disks.s3.key' => $s3Key]);
            }
            if ($s3Secret) {
                config(['filesystems.disks.s3.secret' => $s3Secret]);
            }
            if ($s3Region) {
                config(['filesystems.disks.s3.region' => $s3Region]);
            }
            if ($s3Bucket) {
                config(['filesystems.disks.s3.bucket' => $s3Bucket]);
            }
        }
    }

    /**
     * Apply performance configuration from platform settings.
     */
    public function applyPerformanceConfig(): void
    {
        $cacheEnabled = (bool) $this->get('performance.cache_enabled', false);
        $cacheDriver = $this->get('performance.cache_driver', config('cache.default', 'file'));
        $cacheTtl = (int) $this->get('performance.cache_ttl', 3600);

        if (! $cacheEnabled) {
            config(['cache.default' => 'array']);
        } elseif ($cacheDriver) {
            config(['cache.default' => $cacheDriver]);
        }

        config(['cache.ttl' => $cacheTtl]);

        $queueConnection = $this->get('performance.queue_connection', config('queue.default', 'database'));
        $queueMaxAttempts = (int) $this->get('performance.queue_max_attempts', 3);
        $queueTimeout = (int) $this->get('performance.queue_timeout', 90);

        if ($queueConnection) {
            config(['queue.default' => $queueConnection]);
            $connectionKey = "queue.connections.{$queueConnection}";
            $existing = config($connectionKey, []);
            if (is_array($existing)) {
                $existing['retry_after'] = $queueTimeout;
                $existing['max_tries'] = $queueMaxAttempts;
                config([$connectionKey => $existing]);
            }
        }

        $queryTimeout = (int) $this->get('performance.query_timeout', 30);
        if ($queryTimeout > 0) {
            $defaultConnection = config('database.default');
            $connectionConfig = config("database.connections.{$defaultConnection}", []);
            if (is_array($connectionConfig)) {
                $options = $connectionConfig['options'] ?? [];
                $options[\PDO::ATTR_TIMEOUT] = $queryTimeout;
                $connectionConfig['options'] = $options;
                config(["database.connections.{$defaultConnection}" => $connectionConfig]);
            }
        }
    }

    /**
     * Apply logging configuration from platform settings.
     */
    public function applyLoggingConfig(): void
    {
        $logLevel = $this->get('analytics.log_level', 'info');
        config(['logging.channels.stack.level' => $logLevel]);
        config(['app.log_level' => $logLevel]);
    }

    /**
     * Apply query logging when enabled.
     */
    public function applyQueryLogging(): void
    {
        $enabled = (bool) $this->get('performance.query_logging_enabled', false);
        if (! $enabled) {
            return;
        }

        $thresholdSeconds = (int) $this->get('performance.query_timeout', 30);
        $thresholdMs = max($thresholdSeconds, 1) * 1000;

        DB::listen(function ($query) use ($thresholdMs) {
            if ($query->time < $thresholdMs) {
                return;
            }
            Log::warning('Slow query detected', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time_ms' => $query->time,
            ]);
        });
    }
}
