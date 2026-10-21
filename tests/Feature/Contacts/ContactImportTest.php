<?php

namespace Tests\Feature\Contacts;

use App\Core\Billing\SubscriptionService;
use App\Models\Account;
use App\Models\Plan;
use App\Models\User;
use App\Modules\Contacts\Models\ContactImportBatch;
use App\Modules\Contacts\Models\ContactTag;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContactImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'ModuleSeeder']);
        $this->artisan('db:seed', ['--class' => 'PlanSeeder']);

        $this->user = User::factory()->create();
        $this->account = Account::factory()->create(['owner_id' => $this->user->id]);
        $this->account->users()->attach($this->user->id, ['role' => 'owner']);

        $plan = Plan::where('key', 'self-hosted')->firstOrFail();
        app(SubscriptionService::class)->changePlan($this->account, $plan, $this->user);
    }

    public function test_owner_can_bulk_import_contacts_from_csv(): void
    {
        Storage::fake('local');

        $defaultTag = ContactTag::create([
            'account_id' => $this->account->id,
            'name' => 'Imported',
            'color' => '#10B981',
        ]);

        $csv = implode("\n", [
            'wa_id,name,email,phone,company,status,tags,notes',
            '919988776655,Aarav Sharma,aarav@example.com,919988776655,Yoorie Retail,active,"VIP,Support",First import',
            '919900001111,Neha Singh,neha@example.com,919900001111,Yoorie Retail,inactive,,Second import',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_account_id' => $this->account->id])
            ->post(route('app.contacts.import'), [
                'file' => UploadedFile::fake()->createWithContent('contacts.csv', $csv),
                'tags' => [$defaultTag->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('whatsapp_contacts', [
            'account_id' => $this->account->id,
            'wa_id' => '919988776655',
            'name' => 'Aarav Sharma',
            'source' => 'csv_import',
        ]);
        $this->assertDatabaseHas('whatsapp_contacts', [
            'account_id' => $this->account->id,
            'wa_id' => '919900001111',
            'status' => 'inactive',
        ]);

        $contact = WhatsAppContact::where('account_id', $this->account->id)
            ->where('wa_id', '919988776655')
            ->firstOrFail();

        $this->assertTrue($contact->tags()->where('name', 'Imported')->exists());
        $this->assertTrue($contact->tags()->where('name', 'VIP')->exists());
        $this->assertTrue($contact->tags()->where('name', 'Support')->exists());

        $batch = ContactImportBatch::where('account_id', $this->account->id)->firstOrFail();
        $this->assertSame('completed', $batch->status);
        $this->assertSame(2, $batch->total_rows);
        $this->assertSame(2, $batch->imported_count);
    }

    public function test_owner_can_stream_contacts_export(): void
    {
        WhatsAppContact::create([
            'account_id' => $this->account->id,
            'wa_id' => '919988776655',
            'name' => 'Aarav Sharma',
            'email' => 'aarav@example.com',
            'phone' => '919988776655',
            'status' => 'active',
            'source' => 'manual',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_account_id' => $this->account->id])
            ->get(route('app.contacts.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('wa_id,name,email,phone,company,status,tags,notes,created_at', $csv);
        $this->assertStringContainsString('919988776655', $csv);
    }
}
