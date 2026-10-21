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
                'key' => 'ai',
                'name' => 'AI Assistant',
                'description' => 'AI-powered messaging and automation',
                'is_core' => false,
                'is_enabled' => true,
            ],
            [
                'key' => 'whatsapp.calling',
                'name' => 'WhatsApp Calling',
                'description' => 'AI-assisted WhatsApp Business calling workflows',
                'is_core' => false,
                'is_enabled' => true,
            ],
            [
                'key' => 'floaters',
                'name' => 'Floaters & Widgets',
                'description' => 'Floating widgets and chat widgets',
                'is_core' => false,
                'is_enabled' => true,
            ],
            [
                'key' => 'analytics',
                'name' => 'Analytics',
                'description' => 'Analytics and reporting',
                'is_core' => false,
                'is_enabled' => true,
            ],
            [
                'key' => 'billing',
                'name' => 'Billing',
                'description' => 'Billing and subscription management',
                'is_core' => true,
                'is_enabled' => true,
            ],
            [
                'key' => 'support',
                'name' => 'Support',
                'description' => 'Support tickets and customer assistance',
                'is_core' => true,
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
