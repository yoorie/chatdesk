<?php

use App\Models\AccountModule;
use App\Models\Module;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Module::where('key', 'ai.voice')->update([
            'name' => 'WhatsApp AI Calling',
            'description' => 'AI-assisted WhatsApp Business calling workflows',
        ]);

        AccountModule::where('module_key', 'ai.voice')
            ->get()
            ->each(function (AccountModule $module) {
                $config = array_merge([
                    'provider' => 'whatsapp',
                    'whatsapp_connection_id' => null,
                    'whatsapp_call_button_enabled' => true,
                    'phone_number_id' => '',
                    'business_phone' => '',
                ], $module->config ?? []);

                unset($config['inbound_number'], $config['outbound_caller_id']);
                $config['provider'] = 'whatsapp';

                $module->forceFill(['config' => $config])->save();
            });
    }

    public function down(): void
    {
        Module::where('key', 'ai.voice')->update([
            'name' => 'AI Voice Calling',
            'description' => 'AI-assisted inbound and outbound voice call workflows',
        ]);
    }
};
