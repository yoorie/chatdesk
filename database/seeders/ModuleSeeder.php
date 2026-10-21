<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = [
            [
                'key' => 'core.dashboard',
                'name' => 'Dashboard',
                'description' => 'Core dashboard module',
                'is_core' => true,
                'is_enabled' => true,
            ],
            [
                'key' => 'whatsapp.cloud',
                'name' => 'WhatsApp Cloud',
                'description' => 'Connect and manage Meta WhatsApp Cloud API',
                'is_core' => false,
                'is_enabled' => true,
            ],
            [
                'key' => 'templates',
                'name' => 'Templates',
                'description' => 'Manage WhatsApp message templates',
                'is_core' => false,
                'is_enabled' => true,
            ],
            [
                'key' => 'automation.chatbots',
                'name' => 'Chatbots',
                'description' => 'Create and manage automated chatbots',
                'is_core' => false,
                'is_enabled' => true,
            ],
            [
                'key' => 'contacts',
                'name' => 'Contacts & CRM',
                'description' => 'Manage contacts, tags, segments, and customer relationships',
                'is_core' => false,
                'is_enabled' => true,
            ],
            [
                'key' => 'broadcasts',
                'name' => 'Broadcasts & Campaigns',
                'description' => 'Create and manage WhatsApp broadcast campaigns',
                'is_core' => false,
                'is_enabled' => true,
            ],
        ];

        foreach ($modules as $module) {
            Module::updateOrCreate(
                ['key' => $module['key']],
                $module
            );
        }
    }
}
