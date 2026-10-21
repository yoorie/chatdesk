<?php

namespace Database\Factories;

use App\Modules\WhatsApp\Models\WhatsAppContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\WhatsApp\Models\WhatsAppContact>
 */
class WhatsAppContactFactory extends Factory
{
    protected $model = WhatsAppContact::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $waId = '91'.$this->faker->numerify('##########');

        return [
            'account_id' => \App\Models\Account::factory(),
            'wa_id' => $waId,
            'name' => $this->faker->name(),
            'email' => $this->faker->optional()->safeEmail(),
            'phone' => '+'.$waId,
            'company' => $this->faker->optional()->company(),
            'notes' => null,
            'status' => 'active',
            'source' => 'manual',
            'last_seen_at' => null,
            'last_contacted_at' => null,
            'message_count' => 0,
            'metadata' => null,
            'custom_fields' => null,
        ];
    }
}
