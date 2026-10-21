<?php

namespace Database\Factories;

use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\WhatsApp\Models\WhatsAppMessage>
 */
class WhatsAppMessageFactory extends Factory
{
    protected $model = WhatsAppMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => \App\Models\Account::factory(),
            'whatsapp_conversation_id' => WhatsAppConversation::factory(),
            'direction' => $this->faker->randomElement(['inbound', 'outbound']),
            'meta_message_id' => 'wamid.'.$this->faker->uuid(),
            'type' => 'text',
            'text_body' => $this->faker->sentence(),
            'payload' => null,
            'status' => 'delivered',
            'error_message' => null,
            'sent_at' => now(),
            'delivered_at' => now(),
            'read_at' => null,
            'received_at' => now(),
        ];
    }
}
