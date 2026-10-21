<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seed_is_repeatable_and_does_not_create_sample_accounts(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseHas('plans', ['key' => 'self-hosted']);
        $this->assertDatabaseHas('modules', ['key' => 'automation.chatbots']);
    }
}
