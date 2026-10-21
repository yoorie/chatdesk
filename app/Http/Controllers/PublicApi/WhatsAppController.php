<?php

namespace App\Http\Controllers\PublicApi;

use App\Core\Billing\EntitlementService;
use App\Core\Billing\SubscriptionService;
use App\Core\Billing\UsageService;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppList;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Models\WhatsAppTemplate;
use App\Modules\WhatsApp\Services\TemplateComposer;
use App\Modules\WhatsApp\Services\TemplateSyncService;
use App\Modules\WhatsApp\Services\WhatsAppClient;
use App\Services\WorkspaceWebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WhatsAppController extends Controller
{
    public function __construct(
        protected WhatsAppClient $client,
        protected TemplateComposer $composer,
        protected TemplateSyncService $syncService,
        protected EntitlementService $entitlementService,
        protected UsageService $usageService,
        protected SubscriptionService $subscriptionService,
        protected WorkspaceWebhookDispatcher $webhookDispatcher
    ) {}

    public function connections(Request $request)
    {
        $account = $this->account($request);

        return response()->json([
            'data' => WhatsAppConnection::where('account_id', $account->id)
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(['id', 'name', 'waba_id', 'phone_number_id', 'business_phone', 'api_version', 'webhook_subscribed', 'is_active']),
        ]);
    }

    public function templates(Request $request)
    {
        $account = $this->account($request);

        $query = WhatsAppTemplate::where('account_id', $account->id)
            ->where(function ($q) {
                $q->where('is_archived', false)->orWhereNull('is_archived');
            });

        if ($request->filled('connection_id')) {
            $query->where('whatsapp_connection_id', $request->integer('connection_id'));
        }

        if ($request->filled('status')) {
            $query->whereRaw('LOWER(TRIM(status)) = ?', [strtolower(trim((string) $request->status))]);
        }

        return response()->json([
            'data' => $query->orderBy('name')->paginate((int) $request->integer('per_page', 25)),
        ]);
    }

    public function syncTemplates(Request $request)
    {
        $account = $this->account($request);
        $validated = $request->validate([
            'connection_id' => 'required|integer',
        ]);

        $this->assertActionAllowed($account);
        $connection = $this->connection($account, (int) $validated['connection_id']);

        return response()->json([
            'data' => $this->syncService->sync($connection),
        ]);
    }

    public function conversations(Request $request)
    {
        $account = $this->account($request);

        $conversations = WhatsAppConversation::where('account_id', $account->id)
            ->with(['contact:id,wa_id,name', 'connection:id,name'])
            ->orderByDesc('last_message_at')
            ->paginate((int) $request->integer('per_page', 25));

        return response()->json(['data' => $conversations]);
    }

    public function sendText(Request $request)
    {
        $account = $this->account($request);
        $validated = $request->validate([
            'connection_id' => 'nullable|integer',
            'to' => 'required|string|max:32',
            'message' => 'required|string|max:4096',
        ]);

        $this->assertActionAllowed($account);
        $this->entitlementService->assertWithinLimit($account, 'messages_monthly', 1);
        $connection = $this->connection($account, $validated['connection_id'] ?? null);
        $conversation = $this->conversation($account, $connection, $validated['to']);

        return $this->sendAndPersist($account, $connection, $conversation, 'text', $validated['message'], function () use ($connection, $validated) {
            return $this->client->sendTextMessage($connection, $validated['to'], $validated['message']);
        });
    }

    public function sendTemplate(Request $request)
    {
        $account = $this->account($request);
        $validated = $request->validate([
            'connection_id' => 'nullable|integer',
            'template_id' => 'nullable|integer',
            'template_name' => 'nullable|string',
            'language' => 'nullable|string',
            'to' => 'required|string|max:32',
            'variables' => 'nullable|array',
            'variables.*' => 'nullable|string|max:1024',
        ]);

        $this->assertActionAllowed($account);
        $this->entitlementService->assertWithinLimit($account, 'messages_monthly', 1);
        $this->entitlementService->assertWithinLimit($account, 'template_sends_monthly', 1);
        $connection = $this->connection($account, $validated['connection_id'] ?? null);
        $template = $this->template($account, $connection, $validated);
        $variables = array_values($validated['variables'] ?? []);
        $payload = $this->composer->preparePayload($template, $validated['to'], $variables);
        $preview = $this->composer->renderPreview($template, $variables);
        $conversation = $this->conversation($account, $connection, $validated['to']);

        return $this->sendAndPersist($account, $connection, $conversation, 'template', $preview['body'] ?? $template->name, function () use ($connection, $template, $validated, $payload) {
            return $this->client->sendTemplateMessage(
                $connection,
                $validated['to'],
                $template->name,
                $template->language,
                $payload['template']['components'] ?? []
            );
        }, $payload);
    }

    public function sendMedia(Request $request)
    {
        $account = $this->account($request);
        $validated = $request->validate([
            'connection_id' => 'nullable|integer',
            'to' => 'required|string|max:32',
            'type' => 'required|in:image,video,audio,document',
            'link' => 'required|url',
            'caption' => 'nullable|string|max:1024',
            'filename' => 'nullable|string|max:255',
        ]);

        $this->assertActionAllowed($account);
        $this->entitlementService->assertWithinLimit($account, 'messages_monthly', 1);
        $connection = $this->connection($account, $validated['connection_id'] ?? null);
        $conversation = $this->conversation($account, $connection, $validated['to']);

        return $this->sendAndPersist($account, $connection, $conversation, $validated['type'], $validated['caption'] ?? null, function () use ($connection, $validated) {
            return $this->client->sendMediaMessage(
                $connection,
                $validated['to'],
                $validated['type'],
                $validated['link'],
                $validated['caption'] ?? null,
                $validated['filename'] ?? null
            );
        }, $validated);
    }

    public function sendLocation(Request $request)
    {
        $account = $this->account($request);
        $validated = $request->validate([
            'connection_id' => 'nullable|integer',
            'to' => 'required|string|max:32',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'name' => 'nullable|string|max:120',
            'address' => 'nullable|string|max:255',
        ]);

        $this->assertActionAllowed($account);
        $this->entitlementService->assertWithinLimit($account, 'messages_monthly', 1);
        $connection = $this->connection($account, $validated['connection_id'] ?? null);
        $conversation = $this->conversation($account, $connection, $validated['to']);
        $location = [
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'name' => $validated['name'] ?? null,
            'address' => $validated['address'] ?? null,
        ];

        return $this->sendAndPersist($account, $connection, $conversation, 'location', $validated['name'] ?? 'Location shared', function () use ($connection, $validated, $location) {
            return $this->client->sendLocationMessage($connection, $validated['to'], $location);
        }, $location);
    }

    public function sendList(Request $request)
    {
        $account = $this->account($request);
        $validated = $request->validate([
            'connection_id' => 'nullable|integer',
            'to' => 'required|string|max:32',
            'list_id' => 'required|integer',
        ]);

        $this->assertActionAllowed($account);
        $this->entitlementService->assertWithinLimit($account, 'messages_monthly', 1);
        $connection = $this->connection($account, $validated['connection_id'] ?? null);
        $list = WhatsAppList::where('account_id', $account->id)
            ->where('whatsapp_connection_id', $connection->id)
            ->where('is_active', true)
            ->findOrFail((int) $validated['list_id']);

        $conversation = $this->conversation($account, $connection, $validated['to']);
        $listFormat = $list->toMetaFormat();

        return $this->sendAndPersist($account, $connection, $conversation, 'interactive', $list->description ?: $list->name, function () use ($connection, $validated, $list, $listFormat) {
            return $this->client->sendListMessage(
                $connection,
                $validated['to'],
                $list->button_text,
                $listFormat['action']['sections'],
                $listFormat['header']['text'] ?? null,
                $listFormat['body']['text'] ?? null,
                $listFormat['footer']['text'] ?? null
            );
        }, [
            'interactive_type' => 'list',
            'list_id' => $list->id,
            'list_name' => $list->name,
            'interactive' => $listFormat,
        ]);
    }

    public function sendButtons(Request $request)
    {
        $account = $this->account($request);
        $validated = $request->validate([
            'connection_id' => 'nullable|integer',
            'to' => 'required|string|max:32',
            'body_text' => 'required|string|max:1024',
            'buttons' => 'required|array|min:1|max:3',
            'buttons.*.id' => 'nullable|string|max:256',
            'buttons.*.text' => 'required|string|max:20',
            'header_text' => 'nullable|string|max:60',
            'footer_text' => 'nullable|string|max:60',
        ]);

        $this->assertActionAllowed($account);
        $this->entitlementService->assertWithinLimit($account, 'messages_monthly', 1);
        $connection = $this->connection($account, $validated['connection_id'] ?? null);
        $conversation = $this->conversation($account, $connection, $validated['to']);
        $buttons = collect($validated['buttons'])
            ->values()
            ->map(fn (array $button, int $index) => [
                'id' => $button['id'] ?? 'btn_'.($index + 1),
                'text' => $button['text'],
            ])
            ->all();

        return $this->sendAndPersist($account, $connection, $conversation, 'interactive', $validated['body_text'], function () use ($connection, $validated, $buttons) {
            return $this->client->sendInteractiveButtons(
                $connection,
                $validated['to'],
                $validated['body_text'],
                $buttons,
                $validated['header_text'] ?? null,
                $validated['footer_text'] ?? null
            );
        }, [
            'interactive_type' => 'button',
            'buttons' => $buttons,
            'header_text' => $validated['header_text'] ?? null,
            'footer_text' => $validated['footer_text'] ?? null,
        ]);
    }

    protected function sendAndPersist(Account $account, WhatsAppConnection $connection, WhatsAppConversation $conversation, string $type, ?string $text, callable $sender, array $payload = [])
    {
        return DB::transaction(function () use ($account, $connection, $conversation, $type, $text, $sender, $payload) {
            $message = WhatsAppMessage::create([
                'account_id' => $account->id,
                'whatsapp_conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'type' => $type,
                'text_body' => $text,
                'payload' => $payload,
                'status' => 'queued',
            ]);

            try {
                $response = $sender();
                $message->update([
                    'meta_message_id' => $response['messages'][0]['id'] ?? null,
                    'payload' => array_merge($payload, ['meta_response' => $response]),
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                $this->usageService->incrementMessages($account, 1);
                if ($type === 'template') {
                    $this->usageService->incrementTemplateSends($account, 1);
                }

                $conversation->update([
                    'last_message_at' => now(),
                    'last_message_preview' => $text ? substr($text, 0, 100) : '['.$type.']',
                ]);

                $this->webhookDispatcher->dispatch($account, 'message.sent', [
                    'message' => [
                        'id' => $message->id,
                        'meta_message_id' => $message->meta_message_id,
                        'conversation_id' => $conversation->id,
                        'direction' => $message->direction,
                        'type' => $message->type,
                        'text' => $message->text_body,
                        'status' => $message->status,
                        'sent_at' => $message->sent_at?->toIso8601String(),
                    ],
                    'connection_id' => $connection->id,
                ]);

                return response()->json([
                    'data' => [
                        'message_id' => $message->id,
                        'meta_message_id' => $message->meta_message_id,
                        'conversation_id' => $conversation->id,
                        'status' => $message->status,
                    ],
                ], 201);
            } catch (\Throwable $e) {
                $message->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);

                return response()->json([
                    'message' => 'WhatsApp send failed.',
                    'error' => $e->getMessage(),
                    'data' => [
                        'message_id' => $message->id,
                        'conversation_id' => $conversation->id,
                        'status' => 'failed',
                    ],
                ], 422);
            }
        });
    }

    protected function account(Request $request): Account
    {
        $authenticatedAccount = $request->attributes->get('public_api_account');
        $accountId = $request->headers->get('X-Account-ID') ?: $request->input('account_id');

        if ($authenticatedAccount) {
            if ($accountId && (int) $accountId !== (int) $authenticatedAccount->id) {
                abort(403, 'API key does not have access to this workspace.');
            }

            $request->attributes->set('account', $authenticatedAccount);

            return $authenticatedAccount;
        }

        abort_if(! $accountId, 422, 'X-Account-ID header or account_id is required.');

        $account = Account::findOrFail((int) $accountId);
        $request->attributes->set('account', $account);

        return $account;
    }

    protected function assertActionAllowed(Account $account): void
    {
        if (! $account->isActive()) {
            abort(403, 'Workspace is not active.');
        }

        $subscription = $account->subscription;
        if ($subscription) {
            $subscription = $this->subscriptionService->syncAndNormalize($subscription);
        }

        if (! $subscription) {
            abort(402, 'Please select a plan before using this API action.');
        }

        if ($subscription->isPastDue() || $subscription->isCanceled()) {
            abort(402, $subscription->last_error ?: 'Your plan is not active. Renew your plan before using this API action.');
        }
    }

    protected function connection(Account $account, ?int $connectionId = null): WhatsAppConnection
    {
        $query = WhatsAppConnection::where('account_id', $account->id)->where('is_active', true);
        if ($connectionId) {
            $query->where('id', $connectionId);
        }

        return $query->orderBy('id')->firstOrFail();
    }

    protected function conversation(Account $account, WhatsAppConnection $connection, string $waId): WhatsAppConversation
    {
        $contact = WhatsAppContact::firstOrCreate(
            ['account_id' => $account->id, 'wa_id' => $waId],
            ['source' => 'public_api']
        );

        return WhatsAppConversation::firstOrCreate(
            [
                'account_id' => $account->id,
                'whatsapp_connection_id' => $connection->id,
                'whatsapp_contact_id' => $contact->id,
            ],
            ['status' => 'open']
        );
    }

    protected function template(Account $account, WhatsAppConnection $connection, array $validated): WhatsAppTemplate
    {
        $query = WhatsAppTemplate::where('account_id', $account->id)
            ->where('whatsapp_connection_id', $connection->id)
            ->whereRaw('LOWER(TRIM(status)) = ?', ['approved']);

        if (! empty($validated['template_id'])) {
            return $query->where('id', (int) $validated['template_id'])->firstOrFail();
        }

        abort_if(empty($validated['template_name']) || empty($validated['language']), 422, 'template_id or template_name plus language is required.');

        return $query->where('name', $validated['template_name'])
            ->where('language', $validated['language'])
            ->firstOrFail();
    }
}
