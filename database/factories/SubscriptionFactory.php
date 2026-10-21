<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'plan_id' => Plan::factory(),
            'status' => 'active',
            'started_at' => now(),
            'trial_ends_at' => null,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'provider' => 'manual',
            'provider_ref' => null,
            'last_payment_at' => null,
            'last_payment_failed_at' => null,
            'last_error' => null,
        ];
    }

    public function trialing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(7),
            'current_period_end' => now()->addDays(7),
        ]);
    }

    public function pastDue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'past_due',
            'last_payment_failed_at' => now()->subDay(),
            'last_error' => 'Payment failed',
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);
    }
}
