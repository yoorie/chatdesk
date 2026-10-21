<?php

namespace App\Services;

use App\Models\PlatformSetting;

class PlatformSettingsValidationService
{
    protected $settingsService;

    public function __construct(PlatformSettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Get all misconfigured settings that are required for platform functionality.
     *
     * @return array Array of misconfigured setting groups with details
     */
    public function getMisconfiguredSettings(): array
    {
        $misconfigured = [];

        // Check Pusher settings (required for real-time features)
        $pusherIssues = $this->validatePusherSettings();
        if (! empty($pusherIssues)) {
            $misconfigured['pusher'] = [
                'group' => 'pusher',
                'name' => 'Real-time Broadcasting (Pusher)',
                'required' => true,
                'issues' => $pusherIssues,
                'impact' => 'Real-time notifications and live updates will not work',
                'route' => 'platform.settings',
                'tab' => 'pusher',
            ];
        }

        // Check Mail settings (required for email notifications)
        $mailIssues = $this->validateMailSettings();
        if (! empty($mailIssues)) {
            $misconfigured['mail'] = [
                'group' => 'mail',
                'name' => 'Email Configuration',
                'required' => true,
                'issues' => $mailIssues,
                'impact' => 'Email notifications and password resets will not work',
                'route' => 'platform.settings',
                'tab' => 'mail',
            ];
        }

        // Check Payment settings (required if Razorpay is enabled)
        $razorpayEnabled = PlatformSetting::get('payment.razorpay_enabled', false);
        if ($razorpayEnabled) {
            $paymentIssues = $this->validatePaymentSettings();
            if (! empty($paymentIssues)) {
                $misconfigured['payment'] = [
                    'group' => 'payment',
                    'name' => 'Payment Gateway (Razorpay)',
                    'required' => true,
                    'issues' => $paymentIssues,
                    'impact' => 'Payment processing will fail',
                    'route' => 'platform.settings',
                    'tab' => 'payment',
                ];
            }
        }

        // Check WhatsApp settings (required for WhatsApp features)
        $whatsappIssues = $this->validateWhatsAppSettings();
        if (! empty($whatsappIssues)) {
            $misconfigured['whatsapp'] = [
                'group' => 'whatsapp',
                'name' => 'WhatsApp Meta Configuration',
                'required' => true,
                'issues' => $whatsappIssues,
                'impact' => 'WhatsApp embedded signup and API features may not work',
                'route' => 'platform.settings',
                'tab' => 'integrations', // WhatsApp settings are in the Integrations tab
            ];
        }

        // Check Storage settings (required if using S3)
        $storageDefault = PlatformSetting::get('storage.default', 'local');
        if ($storageDefault === 's3') {
            $storageIssues = $this->validateStorageSettings();
            if (! empty($storageIssues)) {
                $misconfigured['storage'] = [
                    'group' => 'storage',
                    'name' => 'Storage Configuration (S3)',
                    'required' => true,
                    'issues' => $storageIssues,
                    'impact' => 'File uploads and media storage will fail',
                    'route' => 'platform.settings',
                    'tab' => 'storage',
                ];
            }
        }

        // Check AI settings (required if AI is enabled)
        $aiEnabled = PlatformSetting::get('ai.enabled', false);
        if ($aiEnabled) {
            $aiIssues = $this->validateAISettings();
            if (! empty($aiIssues)) {
                $misconfigured['ai'] = [
                    'group' => 'ai',
                    'name' => 'AI Configuration',
                    'required' => true,
                    'issues' => $aiIssues,
                    'impact' => 'AI features will not work',
                    'route' => 'platform.settings',
                    'tab' => 'ai',
                ];
            }
        }

        // Check Analytics settings (required if enabled)
        $analyticsIssues = $this->validateAnalyticsSettings();
        if (! empty($analyticsIssues)) {
            $misconfigured['analytics'] = [
                'group' => 'analytics',
                'name' => 'Analytics Configuration',
                'required' => false,
                'issues' => $analyticsIssues,
                'impact' => 'Analytics tracking may not work properly',
                'route' => 'platform.settings',
                'tab' => 'analytics',
            ];
        }

        return $misconfigured;
    }

    /**
     * Validate Pusher settings.
     */
    protected function validatePusherSettings(): array
    {
        $issues = [];

        $appId = PlatformSetting::get('pusher.app_id');
        $key = PlatformSetting::get('pusher.key');
        $secret = PlatformSetting::get('pusher.secret');
        $cluster = PlatformSetting::get('pusher.cluster');

        if (empty($appId)) {
            $issues[] = 'App ID is required';
        }
        if (empty($key)) {
            $issues[] = 'Key is required';
        }
        if (empty($secret)) {
            $issues[] = 'Secret is required';
        }
        if (empty($cluster)) {
            $issues[] = 'Cluster is required';
        }

        return $issues;
    }

    /**
     * Validate Mail settings.
     */
    protected function validateMailSettings(): array
    {
        $issues = [];

        $driver = PlatformSetting::get('mail.driver', config('mail.default'));

        if ($driver === 'smtp') {
            $host = PlatformSetting::get('mail.host');
            $port = PlatformSetting::get('mail.port');
            $fromAddress = PlatformSetting::get('mail.from_address');
            $fromName = PlatformSetting::get('mail.from_name');

            if (empty($host)) {
                $issues[] = 'SMTP Host is required';
            }
            if (empty($port)) {
                $issues[] = 'SMTP Port is required';
            }
            if (empty($fromAddress)) {
                $issues[] = 'From Email Address is required';
            }
            if (empty($fromName)) {
                $issues[] = 'From Name is required';
            }
        } elseif ($driver === 'mailgun' || $driver === 'ses' || $driver === 'postmark') {
            // These drivers use API keys from config, but we should still check from_address
            $fromAddress = PlatformSetting::get('mail.from_address');
            if (empty($fromAddress)) {
                $issues[] = 'From Email Address is required';
            }
        }

        return $issues;
    }

    /**
     * Validate Payment settings.
     */
    protected function validatePaymentSettings(): array
    {
        $issues = [];

        $keyId = PlatformSetting::get('payment.razorpay_key_id');
        $keySecret = PlatformSetting::get('payment.razorpay_key_secret');

        if (empty($keyId)) {
            $issues[] = 'Razorpay Key ID is required';
        }
        if (empty($keySecret)) {
            $issues[] = 'Razorpay Key Secret is required';
        }

        return $issues;
    }

    /**
     * Validate WhatsApp settings.
     */
    protected function validateWhatsAppSettings(): array
    {
        $issues = [];

        $embeddedEnabled = PlatformSetting::get('whatsapp.embedded_enabled');

        // If embedded signup is enabled, check for required settings
        if ($embeddedEnabled) {
            $appId = PlatformSetting::get('whatsapp.meta_app_id');
            $appSecret = PlatformSetting::get('whatsapp.meta_app_secret');
            $configId = PlatformSetting::get('whatsapp.embedded_signup_config_id');

            if (empty($appId)) {
                $issues[] = 'Meta App ID is required for embedded signup';
            }
            if (empty($appSecret)) {
                $issues[] = 'Meta App Secret is required for embedded signup';
            }
            if (empty($configId)) {
                $issues[] = 'Embedded Signup Config ID is required';
            }
        }

        return $issues;
    }

    /**
     * Validate Storage settings.
     */
    protected function validateStorageSettings(): array
    {
        $issues = [];

        $s3Key = PlatformSetting::get('storage.s3_key');
        $s3Secret = PlatformSetting::get('storage.s3_secret');
        $s3Region = PlatformSetting::get('storage.s3_region');
        $s3Bucket = PlatformSetting::get('storage.s3_bucket');

        if (empty($s3Key)) {
            $issues[] = 'S3 Access Key is required';
        }
        if (empty($s3Secret)) {
            $issues[] = 'S3 Secret Key is required';
        }
        if (empty($s3Region)) {
            $issues[] = 'S3 Region is required';
        }
        if (empty($s3Bucket)) {
            $issues[] = 'S3 Bucket is required';
        }

        return $issues;
    }

    /**
     * Validate AI settings.
     */
    protected function validateAISettings(): array
    {
        $issues = [];

        $provider = PlatformSetting::get('ai.provider', 'openai');

        if ($provider === 'openai') {
            $apiKey = PlatformSetting::get('ai.openai_api_key');
            if (empty($apiKey)) {
                $issues[] = 'OpenAI API Key is required';
            }
        } elseif ($provider === 'anthropic') {
            $apiKey = PlatformSetting::get('ai.anthropic_api_key');
            if (empty($apiKey)) {
                $issues[] = 'Anthropic API Key is required';
            }
        } elseif ($provider === 'gemini') {
            $apiKey = PlatformSetting::get('ai.gemini_api_key');
            if (empty($apiKey)) {
                $issues[] = 'Gemini API Key is required';
            }
        }

        return $issues;
    }

    /**
     * Validate Analytics settings.
     */
    protected function validateAnalyticsSettings(): array
    {
        $issues = [];

        $gaEnabled = PlatformSetting::get('analytics.google_analytics_enabled', false);
        $mixpanelEnabled = PlatformSetting::get('analytics.mixpanel_enabled', false);

        if ($gaEnabled) {
            $gaId = PlatformSetting::get('analytics.google_analytics_id');
            if (empty($gaId)) {
                $issues[] = 'Google Analytics ID is required when Google Analytics is enabled';
            }
        }

        if ($mixpanelEnabled) {
            $mixpanelToken = PlatformSetting::get('analytics.mixpanel_token');
            if (empty($mixpanelToken)) {
                $issues[] = 'Mixpanel Token is required when Mixpanel is enabled';
            }
        }

        return $issues;
    }

    /**
     * Check if platform has any critical misconfigurations.
     */
    public function hasCriticalMisconfigurations(): bool
    {
        $misconfigured = $this->getMisconfiguredSettings();

        // Filter only required (critical) misconfigurations
        $critical = array_filter($misconfigured, function ($item) {
            return $item['required'] === true;
        });

        return ! empty($critical);
    }

    /**
     * Get count of misconfigured settings.
     */
    public function getMisconfigurationCount(): int
    {
        return count($this->getMisconfiguredSettings());
    }
}
