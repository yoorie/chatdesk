<?php

namespace Database\Factories;

use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\WhatsApp\Models\WhatsAppConversation>
 */
class WhatsAppConversationFactory extends Factory
{
    protected $model = WhatsAppConversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => \App\Models\Account::factory(),
            'whatsapp_connection_id' => WhatsAppConnection::factory(),
            'whatsapp_contact_id' => WhatsAppContact::factory(),
            'status' => 'open',
            'priority' => 'normal',
            'last_message_at' => now(),
            'last_message_preview' => $this->faker->sentence(),
            'metadata' => null,
        ];
    }
}
