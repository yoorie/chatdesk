<?php

namespace Database\Factories;

use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\WhatsApp\Models\WhatsAppTemplate>
 */
class WhatsAppTemplateFactory extends Factory
{
    protected $model = WhatsAppTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $bodyText = $this->faker->sentence().' {{1}} '.$this->faker->sentence();

        return [
            'account_id' => \App\Models\Account::factory(),
            'whatsapp_connection_id' => WhatsAppConnection::factory(),
            'meta_template_id' => 'meta_'.$this->faker->uuid(),
            'name' => $this->faker->slug(),
            'language' => 'en_US',
            'category' => $this->faker->randomElement(['MARKETING', 'UTILITY', 'AUTHENTICATION']),
            'status' => $this->faker->randomElement(['approved', 'pending', 'rejected', 'paused']),
            'body_text' => $bodyText,
            'header_type' => null,
            'header_text' => null,
            'footer_text' => $this->faker->optional()->sentence(),
            'buttons' => null,
            'components' => [
                [
                    'type' => 'BODY',
                    'text' => $bodyText,
                ],
            ],
            'last_synced_at' => now(),
            'is_archived' => false,
        ];
    }
}
