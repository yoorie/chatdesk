<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'description',
        'price_monthly',
        'price_yearly',
        'currency',
        'razorpay_plan_id',
        'billing_period',
        'billing_interval',
        'is_active',
        'is_public',
        'trial_days',
        'sort_order',
        'limits',
        'modules',
        'metadata'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'price_monthly' => 'integer',
            'price_yearly' => 'integer',
            'billing_interval' => 'integer',
            'trial_days' => 'integer',
            'sort_order' => 'integer',
            'limits' => 'array',
            'modules' => 'array',
            'metadata' => 'array'];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function requiresAdminApproval(): bool
    {
        return strtolower((string) $this->key) === 'enterprise';
    }

    public function publicFeatures(): array
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $features = $metadata['public_features'] ?? null;

        if (is_array($features) && ! empty($features)) {
            return self::dedupeFeatures(array_map('strval', $features));
        }

        $limits = is_array($this->limits) ? $this->limits : [];
        $fallback = [];

        if (isset($limits['whatsapp_connections'])) {
            $fallback[] = '1 WhatsApp connection per workspace';
        }

        if (isset($limits['agents'])) {
            $agents = (int) $limits['agents'];
            $fallback[] = $agents === -1 ? 'Unlimited agents' : $agents.' agent'.($agents === 1 ? '' : 's');
        }

        if (isset($limits['messages_monthly'])) {
            $messages = (int) $limits['messages_monthly'];
            $fallback[] = $messages === -1 ? 'Unlimited messages/month' : number_format($messages).' messages/month';
        }

        if (isset($limits['template_sends_monthly'])) {
            $templates = (int) $limits['template_sends_monthly'];
            $fallback[] = $templates === -1 ? 'Unlimited template sends/month' : number_format($templates).' template sends/month';
        }

        if (($this->trial_days ?? 0) > 0) {
            $fallback[] = $this->trial_days.'-day free trial';
        }

        return self::dedupeFeatures($fallback);
    }

    /**
     * Feature lines for an in-app plan card: public features without lines that
     * repeat the agents/messages limit tiles the card already shows, and without
     * free-trial lines unless the workspace can still start a trial.
     */
    public function billingFeatures(bool $trialEligible = true): array
    {
        $limits = is_array($this->limits) ? $this->limits : [];
        $seen = [];
        foreach (['agents' => 'agents', 'messages_monthly' => 'messages'] as $limitKey => $noun) {
            if (isset($limits[$limitKey])) {
                $limit = (int) $limits[$limitKey];
                $seen[self::normalizeFeature(($limit === -1 ? 'Unlimited' : number_format($limit)).' '.$noun)] = true;
            }
        }

        $features = [];
        foreach ($this->publicFeatures() as $feature) {
            if (! $trialEligible && self::isTrialFeature($feature)) {
                continue;
            }
            $key = self::normalizeFeature($feature);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $features[] = $feature;
        }

        return $features;
    }

    public static function isTrialFeature(string $feature): bool
    {
        return (bool) preg_match('/\btrial\b/i', $feature);
    }

    /** Remove repeated feature lines (case-insensitive, after normalisation), keeping the first. */
    public static function dedupeFeatures(array $features): array
    {
        $seen = [];
        $result = [];
        foreach ($features as $feature) {
            $feature = trim(preg_replace('/\s+/u', ' ', (string) $feature) ?? '');
            $key = self::normalizeFeature($feature);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $feature;
        }

        return $result;
    }

    /**
     * Comparison key for a feature line: "5,000 Messages / month", "5000 msgs" and
     * "5,000 messages per month" all normalise to "5000 message".
     */
    public static function normalizeFeature(string $feature): string
    {
        $value = mb_strtolower(trim($feature));
        $value = preg_replace('/(?<=\d)[,_](?=\d)/u', '', $value) ?? $value;
        $value = preg_replace('/\b(\d+)\s*k\b/u', '${1}000', $value) ?? $value;
        $value = preg_replace('/\s*(?:\/|per|a|each)\s*(?:month|mo)\b|\bmonthly\b/u', ' ', $value) ?? $value;
        $value = preg_replace('/\bmsgs?\b/u', 'messages', $value) ?? $value;
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
        $words = array_map(
            fn (string $word) => mb_strlen($word) > 3 && str_ends_with($word, 's') && ! str_ends_with($word, 'ss')
                ? mb_substr($word, 0, -1)
                : $word,
            preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: []
        );

        return implode(' ', $words);
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'key';
    }
}
