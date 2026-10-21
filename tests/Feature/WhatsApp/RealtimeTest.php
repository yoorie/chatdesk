<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Account;
use App\Models\Plan;
use App\Models\User;
use App\Modules\Contacts\Models\ContactTag;
use App\Modules\WhatsApp\Events\Inbox\ConversationUpdated;
use App\Modules\WhatsApp\Events\Inbox\MessageCreated;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\WebhookProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RealtimeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $nonMember;

    protected Account $account;

    protected WhatsAppConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'ModuleSeeder']);
        $this->artisan('db:seed', ['--class' => 'PlanSeeder']);
        $this->user = User::factory()->create();
        $this->nonMember = User::factory()->create();
        $this->account = Account::factory()->create([
            'owner_id' => $this->user->id,
        ]);
        $this->account->users()->attach($this->user->id, ['role' => 'member']);
        app(\App\Core\Billing\SubscriptionService::class)->changePlan(
            $this->account,
            Plan::where('key', 'starter')->firstOrFail(),
            $this->user
        );

        $this->connection = WhatsAppConnection::factory()->create([
            'account_id' => $this->account->id,
        ]);

        session(['current_account_id' => $this->account->id]);
    }

    public function test_account_member_can_authorize_inbox_channel(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/broadcasting/auth', [
                'socket_id' => 'test-socket-id',
                'channel_name' => "private-account.{$this->account->id}.whatsapp.inbox",
            ]);

        $response->assertStatus(200);
    }

    public function test_non_member_cannot_authorize_inbox_channel(): void
    {
        $response = $this->actingAs($this->nonMember)
            ->post('/broadcasting/auth', [
                'socket_id' => 'test-socket-id',
                'channel_name' => "private-account.{$this->account->id}.whatsapp.inbox",
            ]);

        $this->assertContains($response->status(), [200, 403]);
    }

    public function test_member_can_authorize_conversation_channel(): void
    {
        $contact = WhatsAppContact::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $conversation = WhatsAppConversation::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_connection_id' => $this->connection->id,
            'whatsapp_contact_id' => $contact->id,
        ]);
        WhatsAppMessage::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'received_at' => now(),
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->post('/broadcasting/auth', [
                'socket_id' => 'test-socket-id',
                'channel_name' => "private-account.{$this->account->id}.whatsapp.conversation.{$conversation->id}",
            ]);

        $response->assertStatus(200);
    }

    public function test_non_member_cannot_authorize_conversation_channel(): void
    {
        $contact = WhatsAppContact::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $conversation = WhatsAppConversation::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_connection_id' => $this->connection->id,
            'whatsapp_contact_id' => $contact->id,
        ]);

        $response = $this->actingAs($this->nonMember)
            ->post('/broadcasting/auth', [
                'socket_id' => 'test-socket-id',
                'channel_name' => "private-account.{$this->account->id}.whatsapp.conversation.{$conversation->id}",
            ]);

        $this->assertContains($response->status(), [200, 403]);
    }

    public function test_webhook_broadcasts_message_created(): void
    {
        Event::fake([MessageCreated::class, ConversationUpdated::class]);

        Http::fake([
            '*' => Http::response(['status' => 'ok'], 200),
        ]);

        $webhookPayload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messages' => [
                                    [
                                        'id' => 'wamid.test123',
                                        'from' => '1234567890',
                                        'type' => 'text',
                                        'text' => [
                                            'body' => 'Hello test',
                                        ],
                                    ],
                                ],
                                'contacts' => [
                                    [
                                        'profile' => [
                                            'name' => 'Test Contact',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $processor = app(WebhookProcessor::class);
        $processor->process($webhookPayload, $this->connection);

        Event::assertDispatched(MessageCreated::class, function ($event) {
            return $event->message->account_id === $this->account->id;
        });

        Event::assertDispatched(ConversationUpdated::class);
    }

    public function test_message_created_broadcast_contains_conversation_snapshot(): void
    {
        $contact = WhatsAppContact::factory()->create([
            'account_id' => $this->account->id,
            'wa_id' => '919988776655',
            'name' => 'Realtime Lead',
        ]);

        $tag = ContactTag::create([
            'account_id' => $this->account->id,
            'name' => 'VIP',
            'color' => '#10B981',
        ]);
        $contact->tags()->attach($tag->id);

        $conversation = WhatsAppConversation::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_connection_id' => $this->connection->id,
            'whatsapp_contact_id' => $contact->id,
            'assigned_to' => $this->user->id,
            'priority' => 'high',
            'last_message_preview' => null,
            'last_message_at' => null,
        ]);

        $message = WhatsAppMessage::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'text_body' => 'Hello from realtime',
            'created_at' => now(),
        ]);

        $payload = (new MessageCreated($message))->broadcastWith();

        $this->assertSame($this->account->id, $payload['account_id']);
        $this->assertSame($conversation->id, $payload['conversation_id']);
        $this->assertSame('Hello from realtime', $payload['message']['text_body']);
        $this->assertSame($conversation->id, $payload['conversation']['id']);
        $this->assertSame($this->account->id, $payload['conversation']['account_id']);
        $this->assertSame($this->connection->id, $payload['conversation']['connection']['id']);
        $this->assertSame('Realtime Lead', $payload['conversation']['contact']['name']);
        $this->assertSame('919988776655', $payload['conversation']['contact']['wa_id']);
        $this->assertSame('Hello from realtime', $payload['conversation']['last_message_preview']);
        $this->assertSame($this->user->id, $payload['conversation']['assignee_id']);
        $this->assertSame($this->user->id, $payload['conversation']['assigned_to']);
        $this->assertSame([
            [
                'id' => $tag->id,
                'name' => 'VIP',
                'color' => '#10B981',
            ],
        ], $payload['conversation']['contact']['tags']);
    }

    public function test_inbox_stream_requires_account_membership(): void
    {
        $response = $this->actingAs($this->nonMember)
            ->withSession(['current_account_id' => $this->account->id])
            ->get(route('app.whatsapp.inbox.stream', ['account' => $this->account->slug]));

        $response->assertForbidden();
    }

    public function test_inbox_stream_returns_updated_conversations(): void
    {
        $contact = WhatsAppContact::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $conversation = WhatsAppConversation::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_connection_id' => $this->connection->id,
            'whatsapp_contact_id' => $contact->id,
            'last_message_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_account_id' => $this->account->id])
            ->get(route('app.whatsapp.inbox.stream', [
                'account' => $this->account->slug,
                'since' => now()->subMinutes(10)->toIso8601String(),
            ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'server_time',
            'updated_conversations',
            'new_message_notifications',
        ]);

        $data = $response->json();
        $this->assertIsArray($data['updated_conversations']);
    }

    public function test_conversation_stream_returns_incremental_messages(): void
    {
        $contact = WhatsAppContact::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $conversation = WhatsAppConversation::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_connection_id' => $this->connection->id,
            'whatsapp_contact_id' => $contact->id,
        ]);

        $message1 = WhatsAppMessage::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'inbound',
        ]);

        $message2 = WhatsAppMessage::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'inbound',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_account_id' => $this->account->id])
            ->get(route('app.whatsapp.inbox.conversation.stream', [
                'account' => $this->account->slug,
                'conversation' => $conversation->id,
                'after_message_id' => $message1->id,
            ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'new_messages',
            'updated_messages',
            'new_notes',
            'new_audit_events',
            'conversation',
        ]);

        $data = $response->json();
        $this->assertCount(1, $data['new_messages']);
        $this->assertEquals($message2->id, $data['new_messages'][0]['id']);
        $this->assertNotEmpty($data['updated_messages']);
        $updatedMessage = collect($data['updated_messages'])->firstWhere('id', $message1->id);
        $this->assertSame($message1->direction, $updatedMessage['direction'] ?? null);
        $this->assertSame($message1->type, $updatedMessage['type'] ?? null);
        $this->assertSame($message1->text_body, $updatedMessage['text_body'] ?? null);
        $this->assertArrayHasKey('created_at', $updatedMessage);
    }

    public function test_conversation_stream_forbids_non_members(): void
    {
        $contact = WhatsAppContact::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $conversation = WhatsAppConversation::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_connection_id' => $this->connection->id,
            'whatsapp_contact_id' => $contact->id,
        ]);

        $response = $this->actingAs($this->nonMember)
            ->withSession(['current_account_id' => $this->account->id])
            ->get(route('app.whatsapp.inbox.conversation.stream', [
                'account' => $this->account->slug,
                'conversation' => $conversation->id,
            ]));

        $response->assertForbidden();
    }

    public function test_audio_attachment_is_uploaded_to_meta_before_send(): void
    {
        Http::fake([
            'https://graph.facebook.com/*/*/media' => Http::response(['id' => 'meta-media-audio-123'], 200),
            'https://graph.facebook.com/*/*/messages' => Http::response([
                'messages' => [
                    ['id' => 'wamid.audio-send-123'],
                ],
            ], 200),
        ]);

        $contact = WhatsAppContact::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $conversation = WhatsAppConversation::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_connection_id' => $this->connection->id,
            'whatsapp_contact_id' => $contact->id,
        ]);
        WhatsAppMessage::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'received_at' => now(),
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_account_id' => $this->account->id])
            ->postJson(route('app.whatsapp.conversations.send-media', ['conversation' => $conversation->id]), [
                'type' => 'audio',
                'is_voice' => true,
                'attachment' => UploadedFile::fake()->create('voice-message.ogg', 64, 'audio/ogg'),
            ]);

        $this->assertSame(201, $response->status(), $response->getContent());
        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'audio',
            'meta_message_id' => 'wamid.audio-send-123',
            'status' => 'sent',
        ]);

        $message = WhatsAppMessage::where('meta_message_id', 'wamid.audio-send-123')->firstOrFail();
        $this->assertSame('meta-media-audio-123', $message->payload['media_id'] ?? null);
        $this->assertTrue($message->payload['voice'] ?? false);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return str_contains($request->url(), '/messages')
                && ($payload['type'] ?? null) === 'audio'
                && ($payload['audio']['id'] ?? null) === 'meta-media-audio-123'
                && ($payload['audio']['voice'] ?? null) === true;
        });
    }

    public function test_reaction_message_is_sent_to_meta(): void
    {
        Http::fake([
            'https://graph.facebook.com/*/*/messages' => Http::response([
                'messages' => [
                    ['id' => 'wamid.reaction-send-123'],
                ],
            ], 200),
        ]);

        $contact = WhatsAppContact::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $conversation = WhatsAppConversation::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_connection_id' => $this->connection->id,
            'whatsapp_contact_id' => $contact->id,
        ]);

        $target = WhatsAppMessage::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'meta_message_id' => 'wamid.inbound-target-123',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_account_id' => $this->account->id])
            ->postJson(route('app.whatsapp.conversations.send-reaction', ['conversation' => $conversation->id]), [
                'message_id' => $target->id,
                'emoji' => '👍',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'reaction',
            'text_body' => '👍',
            'meta_message_id' => 'wamid.reaction-send-123',
            'status' => 'sent',
        ]);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return ($payload['type'] ?? null) === 'reaction'
                && ($payload['reaction']['message_id'] ?? null) === 'wamid.inbound-target-123'
                && ($payload['reaction']['emoji'] ?? null) === '👍';
        });
    }

    public function test_failed_text_message_can_be_retried(): void
    {
        Http::fake([
            'https://graph.facebook.com/*/*/messages' => Http::response([
                'messages' => [
                    ['id' => 'wamid.retry-text-123'],
                ],
            ], 200),
        ]);

        $contact = WhatsAppContact::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $conversation = WhatsAppConversation::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_connection_id' => $this->connection->id,
            'whatsapp_contact_id' => $contact->id,
        ]);

        $message = WhatsAppMessage::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'text',
            'text_body' => 'Retry this',
            'status' => 'failed',
            'meta_message_id' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_account_id' => $this->account->id])
            ->postJson(route('app.whatsapp.conversations.retry-message', [
                'conversation' => $conversation->id,
                'message' => $message->id,
            ]));

        $response->assertOk();
        $this->assertDatabaseHas('whatsapp_messages', [
            'id' => $message->id,
            'status' => 'sent',
            'meta_message_id' => 'wamid.retry-text-123',
        ]);
    }

    public function test_failed_audio_message_retry_uses_meta_accepted_mime_type(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('whatsapp-media/retry-audio.mp4', 'fake audio bytes');

        Http::fake([
            'https://graph.facebook.com/*/*/media' => Http::response([
                'id' => 'media.retry-audio-123',
            ], 200),
            'https://graph.facebook.com/*/*/messages' => Http::response([
                'messages' => [
                    ['id' => 'wamid.retry-audio-123'],
                ],
            ], 200),
        ]);

        $contact = WhatsAppContact::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $conversation = WhatsAppConversation::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_connection_id' => $this->connection->id,
            'whatsapp_contact_id' => $contact->id,
        ]);

        $message = WhatsAppMessage::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'audio',
            'text_body' => null,
            'payload' => [
                'type' => 'audio',
                'link' => config('app.url').'/storage/whatsapp-media/retry-audio.mp4',
                'filename' => 'voice-message.m4a',
            ],
            'status' => 'failed',
            'meta_message_id' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_account_id' => $this->account->id])
            ->postJson(route('app.whatsapp.conversations.retry-message', [
                'conversation' => $conversation->id,
                'message' => $message->id,
            ]));

        $response->assertOk();

        $message->refresh();
        $this->assertSame('sent', $message->status);
        $this->assertSame('wamid.retry-audio-123', $message->meta_message_id);
        $this->assertSame('media.retry-audio-123', $message->payload['media_id'] ?? null);
        $this->assertSame('audio/mp4', $message->payload['mime_type'] ?? null);
    }

    public function test_failed_message_with_meta_id_is_restored_without_duplicate_retry(): void
    {
        Http::fake();

        $contact = WhatsAppContact::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $conversation = WhatsAppConversation::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_connection_id' => $this->connection->id,
            'whatsapp_contact_id' => $contact->id,
        ]);

        $message = WhatsAppMessage::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'audio',
            'status' => 'failed',
            'error_message' => 'Previous retry failed.',
            'meta_message_id' => 'wamid.already-accepted-123',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_account_id' => $this->account->id])
            ->postJson(route('app.whatsapp.conversations.retry-message', [
                'conversation' => $conversation->id,
                'message' => $message->id,
            ]));

        $response->assertOk();

        $message->refresh();
        $this->assertSame('sent', $message->status);
        $this->assertNull($message->error_message);
        $this->assertSame('wamid.already-accepted-123', $message->meta_message_id);
        Http::assertNothingSent();
    }
}
