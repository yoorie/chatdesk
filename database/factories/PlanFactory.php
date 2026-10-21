<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'price_monthly' => $this->faker->numberBetween(0, 100000),
            'price_yearly' => null,
            'currency' => 'INR',
            'is_active' => true,
            'is_public' => true,
            'trial_days' => 0,
            'sort_order' => 0,
            'limits' => [
                'agents' => 1,
                'whatsapp_connections' => 1,
                'messages_monthly' => 500,
                'template_sends_monthly' => 0,
                'ai_credits_monthly' => 0,
            ],
            'modules' => ['whatsapp.cloud'],
            'metadata' => [],
        ];
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => 'free',
            'name' => 'Free',
            'price_monthly' => 0,
            'limits' => [
                'agents' => 1,
                'whatsapp_connections' => 1,
                'messages_monthly' => 500,
                'template_sends_monthly' => 0,
            ],
            'modules' => ['whatsapp.cloud'],
        ]);
    }

    public function starter(): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => 'starter',
            'name' => 'Starter',
            'price_monthly' => 99900,
            'trial_days' => 7,
            'limits' => [
                'agents' => 3,
                'whatsapp_connections' => 1,
                'messages_monthly' => 5000,
                'template_sends_monthly' => 1000,
            ],
            'modules' => ['whatsapp.cloud', 'templates'],
        ]);
    }
}
