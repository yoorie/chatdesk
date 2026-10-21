<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a workspace opens a feature its plan does not include.
 * Rendered as a friendly "Available on <plan>" page with an upgrade link.
 */
class FeatureNotOnPlanException extends HttpException
{
    /** @var array<string, string> */
    public const FEATURE_LABELS = [
        'automation.chatbots' => 'Chatbots',
        'ai' => 'AI assistant',
        'analytics' => 'Analytics',
        'floaters' => 'Website widget',
        'whatsapp.calling' => 'WhatsApp calls',
        'broadcasts' => 'Campaigns',
        'templates' => 'Templates',
        'contacts' => 'Contacts',
        'whatsapp.cloud' => 'WhatsApp inbox',
        'billing' => 'Billing',
    ];

    public function __construct(
        public readonly string $moduleKey,
        public readonly ?string $requiredPlan = null,
    ) {
        parent::__construct(403, "Module '{$moduleKey}' is not available on your current plan. Please upgrade to access this feature.");
    }

    public function featureLabel(): string
    {
        return self::FEATURE_LABELS[$this->moduleKey]
            ?? ucfirst(str_replace(['.', '_', '-'], ' ', $this->moduleKey));
    }

    /**
     * Cheapest public plan that includes the module, if any.
     */
    public static function requiredPlanFor(string $moduleKey): ?string
    {
        try {
            return \App\Models\Plan::query()
                ->where('is_active', true)
                ->where('is_public', true)
                ->orderBy('sort_order')
                ->orderBy('price_monthly')
                ->get(['name', 'modules'])
                ->first(fn ($plan) => in_array($moduleKey, $plan->modules ?? [], true))
                ?->name;
        } catch (\Throwable) {
            return null;
        }
    }
}
