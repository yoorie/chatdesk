<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->company();

        return [
            'name' => $name,
            'slug' => Account::generateSlug($name),
            'workspace_type' => 'business',
            'industry' => $this->faker->randomElement(['Retail', 'Services', 'Healthcare', 'Education', null]),
            'timezone' => 'UTC',
            'owner_id' => \App\Models\User::factory(),
        ];
    }
}
