<?php

namespace Database\Factories;

use App\Modules\WhatsApp\Models\WhatsAppConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\WhatsApp\Models\WhatsAppConnection>
 */
class WhatsAppConnectionFactory extends Factory
{
    protected $model = WhatsAppConnection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => \App\Models\Account::factory(),
            'name' => $this->faker->company().' WhatsApp',
            'phone_number_id' => (string) $this->faker->numerify('##########'),
            'business_phone' => $this->faker->phoneNumber(),
            'access_token_encrypted' => encrypt('test-access-token'),
            'api_version' => 'v20.0',
            'webhook_verify_token' => WhatsAppConnection::generateVerifyToken(),
            'webhook_subscribed' => false,
            'is_active' => true,
        ];
    }
}
