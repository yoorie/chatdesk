<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Core\Billing\EntitlementService;
use App\Core\Billing\PlanResolver;
use App\Core\Billing\UsageService;
use App\Http\Controllers\Controller;
use App\Models\AccountCatalogProduct;
use App\Models\AiAgent;
use App\Models\AiSuggestionFeedback;
use App\Models\AiUsageLog;
use App\Models\QuickReply;
use App\Models\User;
use App\Modules\Contacts\Models\ContactTag;
use App\Modules\WhatsApp\Events\Inbox\AuditEventAdded;
use App\Modules\WhatsApp\Events\Inbox\ConversationUpdated;
use App\Modules\WhatsApp\Events\Inbox\InternalNoteAdded;
use App\Modules\WhatsApp\Events\Inbox\MessageCreated;
use App\Modules\WhatsApp\Events\Inbox\MessageUpdated;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppConversationAuditEvent;
use App\Modules\WhatsApp\Models\WhatsAppConversationNote;
use App\Modules\WhatsApp\Models\WhatsAppFlow;
use App\Modules\WhatsApp\Models\WhatsAppList;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Models\WhatsAppTemplate;
use App\Modules\WhatsApp\Models\WhatsAppTemplateSend;
use App\Modules\WhatsApp\Services\TemplateComposer;
use App\Modules\WhatsApp\Services\WhatsAppClient;
use App\Services\AI\ConversationAssistantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function __construct(
        protected \App\Modules\WhatsApp\Services\ConversationInboxService $inboxPresentation,
        protected \App\Modules\WhatsApp\Services\ConversationMediaService $mediaAttachments,
        protected WhatsAppClient $whatsappClient,
        protected TemplateComposer $templateComposer,
        protected EntitlementService $entitlementService,
        protected UsageService $usageService,
        protected ConversationAssistantService $conversationAssistant,
        protected PlanResolver $planResolver
    ) {}

    /**
     * Display a listing of conversations.
     */
    public function index(Request $request): Response
    {
        $account = $request->attributes->get('account') ?? current_account();

        // Optimize query: select only needed columns, use eager loading efficiently
        $conversationSelect = ['id', 'account_id', 'whatsapp_connection_id', 'whatsapp_contact_id', 'status', 'last_message_at', 'last_message_preview', 'metadata'];
        if (Schema::hasColumn('whatsapp_conversations', 'assigned_to')) {
            $conversationSelect[] = 'assigned_to';
        }
        if (Schema::hasColumn('whatsapp_conversations', 'priority')) {
            $conversationSelect[] = 'priority';
        }

        $selectedConversationId = $request->integer('conversation') ?: null;

        $query = WhatsAppConversation::where('account_id', $account->id)
            ->select($conversationSelect)
            ->whereHas('contact')
            ->whereHas('connection')
            ->withMax(['messages as last_inbound_message_at' => function ($query) {
                $query->where('direction', 'inbound');
            }], 'created_at')
            ->with([
                'contact:id,account_id,wa_id,slug,name,email,phone,company,notes,status,source,metadata',
                'contact.tags:id,account_id,name,color',
                'connection:id,account_id,name,slug,calling_status,calling_enabled,calling_webhook_subscribed']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('contact', function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('wa_id', 'like', '%'.$search.'%');
            });
        }

        if (Schema::hasColumn('whatsapp_conversations', 'assigned_to')) {
            $assigneeFilter = $request->input('assignee', 'all');
            if ($assigneeFilter === 'me') {
                $currentUserId = $request->user()?->id;
                if ($currentUserId) {
                    $query->where('assigned_to', $currentUserId);
                }
            } elseif ($assigneeFilter === 'unassigned') {
                $query->whereNull('assigned_to');
            }
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('connection_id') && $request->input('connection_id') !== 'all') {
            $query->where('whatsapp_connection_id', $request->input('connection_id'));
        }

        if ($selectedConversationId) {
            $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$selectedConversationId]);
        }

        $conversations = $query
            ->orderBy('last_message_at', 'desc')
            ->orderBy('id', 'desc') // Secondary sort for consistent ordering
            ->paginate(20)
            ->through(function ($conversation) {
                $contactMeta = $conversation->contact->metadata ?? [];
                $displayPhone = ($conversation->contact->phone ?: $conversation->contact->wa_id);

                return [
                    'id' => $conversation->id,
                    'account_id' => $conversation->account_id,
                    'contact' => [
                        'id' => $conversation->contact->id,
                        'slug' => $conversation->contact->slug,
                        'wa_id' => $conversation->contact->wa_id,
                        'name' => $conversation->contact->name ?? $conversation->contact->wa_id,
                        'display_phone' => $displayPhone,
                        'email' => $conversation->contact->email,
                        'phone' => $conversation->contact->phone,
                        'company' => $conversation->contact->company,
                        'notes' => $conversation->contact->notes,
                        'status' => $conversation->contact->status,
                        'source' => $conversation->contact->source,
                        'ctwa' => $this->inboxPresentation->ctwaForConversation($conversation),
                        'tags' => $conversation->contact->tags->map(fn ($tag) => [
                            'id' => $tag->id,
                            'name' => $tag->name,
                            'color' => $tag->color,
                        ])->values()],
                    'status' => $conversation->status,
                    'last_message_preview' => $conversation->last_message_preview,
                    'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                    'last_inbound_message_at' => $conversation->last_inbound_message_at
                        ? \Illuminate\Support\Carbon::parse($conversation->last_inbound_message_at)->toIso8601String()
                        : null,
                    'connection' => [
                        'id' => $conversation->connection->id,
                        'name' => $conversation->connection->name,
                        'slug' => $conversation->connection->slug,
                        'calling_status' => $conversation->connection->calling_status ?? 'unknown',
                        'calling_enabled' => (bool) ($conversation->connection->calling_enabled ?? false),
                        'calling_webhook_subscribed' => (bool) ($conversation->connection->calling_webhook_subscribed ?? false),
                    ],
                    'assigned_to' => Schema::hasColumn('whatsapp_conversations', 'assigned_to')
                        ? $conversation->assigned_to
                        : null,
                    'priority' => Schema::hasColumn('whatsapp_conversations', 'priority')
                        ? $conversation->priority
                        : null,
                    'automation_state' => $this->inboxPresentation->automationStateForConversation($conversation),
                    'automation_processing' => $this->inboxPresentation->isAutomationProcessing($conversation),
                    'automation_processing_mode' => ($conversation->metadata ?? [])['automation_processing_mode'] ?? null,
                    'bot_paused' => (bool) (($conversation->metadata ?? [])['bot_paused'] ?? false),
                    'bot_paused_reason' => ($conversation->metadata ?? [])['bot_paused_reason'] ?? null,
                    'handoff_status' => ($conversation->metadata ?? [])['handoff_status'] ?? null,
                    'handoff_reason' => ($conversation->metadata ?? [])['handoff_reason'] ?? null,
                ];
            });

        $threads = app(\App\Modules\WhatsApp\Services\ConversationThreadService::class);
        $pageConversations = collect($conversations->items());
        $messageCounts = $threads->messageCounts($pageConversations->pluck('id')->all());
        $conversations->through(function (array $conversation) use ($messageCounts) {
            $conversation['message_counts'] = $messageCounts[$conversation['id']] ?? ['received' => 0, 'sent' => 0, 'total' => 0];

            return $conversation;
        });

        // Ship the open chat's latest messages with the page so the thread renders immediately,
        // instead of showing "Loading messages" while a second request runs after the page loads.
        $initialConversationId = $selectedConversationId && $pageConversations->contains('id', $selectedConversationId)
            ? $selectedConversationId
            : $pageConversations->first()['id'] ?? null;
        $initialConversation = $initialConversationId
            ? WhatsAppConversation::where('account_id', $account->id)->find($initialConversationId)
            : null;
        $initialThread = $initialConversation ? $threads->initialThread($initialConversation) : null;

        $connections = \App\Modules\WhatsApp\Models\WhatsAppConnection::where('account_id', $account->id)
            ->where('is_active', true)
            ->get(['id', 'name']);

        $agents = $account->getAssignableAgents()->all();

        $effectiveModules = $this->planResolver->getEffectiveModules($account);
        $aiAvailable = in_array('ai', $effectiveModules, true);

        $templates = WhatsAppTemplate::where('account_id', $account->id)
            ->whereRaw('LOWER(TRIM(status)) = ?', ['approved'])
            ->where(function ($query) {
                $query->where('is_archived', false)
                    ->orWhereNull('is_archived');
            })
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'language', 'body_text', 'header_text', 'footer_text', 'buttons'])
            ->map(function ($template) {
                $requiredVariables = $this->templateComposer->extractRequiredVariables($template);

                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'language' => $template->language,
                    'body_text' => $template->body_text,
                    'header_text' => $template->header_text,
                    'footer_text' => $template->footer_text,
                    'buttons' => $template->buttons ?? [],
                    'variable_count' => $requiredVariables['total'],
                ];
            });

        return Inertia::render('WhatsApp/Conversations/Index', [
            'account' => $account,
            'conversations' => $conversations,
            'connections' => $connections,
            'agents' => $agents,
            'templates' => $templates,
            'catalog_products' => $this->catalogProductsForInbox($account),
            'saved_buttons' => $this->savedButtonsForInbox($account),
            'saved_lists' => $this->savedListsForInbox($account),
            'saved_forms' => $this->savedFormsForInbox($account),
            'ai_agents' => [], // AI assistants are not part of the open-source edition.
            'ai_available' => $aiAvailable,
            'whatsapp_calling' => $this->inboxPresentation->callingSettingsForInbox($account),
            'recent_calls' => $this->inboxPresentation->recentCallsForInbox($account, collect($conversations->items())->pluck('contact.wa_id')->filter()->values()->all()),
            'filters' => [
                'search' => $request->input('search', ''),
                'assignee' => $request->input('assignee', 'all'),
                'status' => $request->input('status', 'all'),
                'connection_id' => $request->input('connection_id', 'all'),
            ],
            'selected_conversation_id' => $selectedConversationId,
            'initial_thread' => $initialThread,
            'new_chat_open' => $request->boolean('new'),
        ]);
    }

    /**
     * Open or start a conversation with a contact (from Contacts). Gets or creates the conversation and redirects to it.
     * Optional query: connection_id to use a specific connection when account has multiple.
     */
    public function showByContact(Request $request, WhatsAppContact $contact)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($contact->account_id, $account->id)) {
            abort(404);
        }

        $connectionId = $request->query('connection_id');
        $connection = null;

        if ($connectionId) {
            $connection = WhatsAppConnection::where('account_id', $account->id)
                ->where('id', (int) $connectionId)
                ->where('is_active', true)
                ->first();
        }

        if (! $connection) {
            $connection = WhatsAppConnection::where('account_id', $account->id)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();
        }

        if (! $connection) {
            return redirect()->back()->withErrors([
                'connection' => 'No active WhatsApp connection. Please add and connect a WhatsApp connection first.',
            ]);
        }

        $conversation = WhatsAppConversation::firstOrCreate(
            [
                'account_id' => $account->id,
                'whatsapp_connection_id' => $connection->id,
                'whatsapp_contact_id' => $contact->id,
            ],
            ['status' => 'open']
        );

        return redirect()->route('app.whatsapp.conversations.index', ['conversation' => $conversation->id]);
    }

    public function store(Request $request)
    {
        $account = $request->attributes->get('account') ?? current_account();

        $validated = $request->validate([
            'connection_id' => ['required', 'integer', 'exists:whatsapp_connections,id'],
            'wa_id' => ['required', 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $connection = WhatsAppConnection::where('account_id', $account->id)
            ->where('id', (int) $validated['connection_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $waId = preg_replace('/\D+/', '', (string) $validated['wa_id']);
        if ($waId === '') {
            return back()->withErrors(['wa_id' => 'Enter a valid WhatsApp number.']);
        }

        $contact = WhatsAppContact::withTrashed()->firstOrNew([
            'account_id' => $account->id,
            'wa_id' => $waId,
        ]);

        $contact->fill([
            'name' => $validated['name'] ?: ($contact->name ?: $waId),
            'phone' => $contact->phone ?: $waId,
            'status' => $contact->status ?: 'active',
            'source' => $contact->source ?: 'inbox',
        ]);
        $contact->save();

        if (method_exists($contact, 'restore') && $contact->trashed()) {
            $contact->restore();
        }

        $conversation = WhatsAppConversation::firstOrCreate(
            [
                'account_id' => $account->id,
                'whatsapp_connection_id' => $connection->id,
                'whatsapp_contact_id' => $contact->id,
            ],
            [
                'status' => 'open',
                'last_message_at' => now(),
                'last_message_preview' => 'New chat started',
            ]
        );

        return redirect()
            ->route('app.whatsapp.conversations.index', ['conversation' => $conversation->id])
            ->with('success', 'Chat created.');
    }

    /**
     * Load more messages (for infinite scroll).
     */
    public function loadMoreMessages(Request $request, WhatsAppConversation $conversation)
    {
        $account = $request->attributes->get('account') ?? current_account();

        // Ensure conversation belongs to account
        if (! account_ids_match($conversation->account_id, $account->id)) {
            abort(404);
        }

        $validated = $request->validate([
            'before_message_id' => 'required|integer|exists:whatsapp_messages,id']);

        $messages = WhatsAppMessage::where('whatsapp_conversation_id', $conversation->id)
            ->where('id', '<', $validated['before_message_id'])
            ->select(['id', 'direction', 'type', 'text_body', 'payload', 'status', 'meta_message_id', 'created_at', 'sent_at', 'delivered_at', 'read_at'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->reverse()
            ->values()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'direction' => $message->direction,
                    'type' => $message->type,
                    'text_body' => $message->text_body,
                    'payload' => $message->payload,
                    'status' => $message->status,
                    'meta_message_id' => $message->meta_message_id,
                    'created_at' => $message->created_at->toIso8601String(),
                    'updated_at' => $message->updated_at?->toIso8601String(),
                    'sent_at' => $message->sent_at?->toIso8601String(),
                    'delivered_at' => $message->delivered_at?->toIso8601String(),
                    'read_at' => $message->read_at?->toIso8601String(),
                    'error_message' => $message->status === 'failed' ? \App\Support\CustomerMessage::humanize($message->error_message ?: 'This message could not be delivered.', 'This message could not be delivered.') : null];
            });

        return response()->json([
            'messages' => $messages,
            'has_more' => $messages->count() === 50]);
    }

    public function gallery(Request $request, WhatsAppConversation $conversation)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($conversation->account_id, $account->id)) {
            abort(404);
        }

        $messages = WhatsAppMessage::where('account_id', $account->id)
            ->where('whatsapp_conversation_id', $conversation->id)
            ->select(['id', 'direction', 'type', 'text_body', 'payload', 'created_at', 'sent_at', 'received_at'])
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $media = [];
        $documents = [];
        $links = [];
        $seenLinks = [];

        foreach ($messages as $message) {
            $payload = is_array($message->payload) ? $message->payload : [];
            $mediaPayload = is_array($payload['media'] ?? null) ? $payload['media'] : [];
            $typePayload = is_array($payload[$message->type] ?? null) ? $payload[$message->type] : [];
            $url = $mediaPayload['local_url']
                ?? $payload['link']
                ?? $payload['url']
                ?? $typePayload['link']
                ?? $typePayload['url']
                ?? null;
            $filename = $payload['filename']
                ?? $typePayload['filename']
                ?? (is_array($payload['document'] ?? null) ? $payload['document']['filename'] ?? null : null)
                ?? $mediaPayload['filename']
                ?? null;
            $mimeType = $mediaPayload['mime_type'] ?? $payload['mime_type'] ?? $typePayload['mime_type'] ?? null;
            $fileSize = $mediaPayload['file_size'] ?? $payload['file_size'] ?? null;

            $base = [
                'id' => $message->id,
                'message_id' => $message->id,
                'direction' => $message->direction,
                'type' => $message->type,
                'url' => is_string($url) ? $url : null,
                'title' => $filename ?: ucfirst((string) $message->type).' message',
                'filename' => $filename,
                'mime_type' => $mimeType,
                'file_size' => is_numeric($fileSize) ? (int) $fileSize : null,
                'caption' => $message->text_body,
                'created_at' => $message->created_at?->toIso8601String(),
            ];

            if ($message->type === 'document') {
                $documents[] = $base;
            } elseif (in_array($message->type, ['image', 'video', 'audio', 'sticker'], true)) {
                $media[] = $base;
            }

            if (! in_array($message->type, ['image', 'video', 'audio', 'sticker', 'document'], true)) {
                foreach ($this->extractUrlsFromMessage($message->text_body, $payload) as $link) {
                    $key = strtolower($link['url']);
                    if (isset($seenLinks[$key])) {
                        continue;
                    }

                    $seenLinks[$key] = true;
                    $links[] = [
                        'id' => 'link-'.$message->id.'-'.count($links),
                        'message_id' => $message->id,
                        'direction' => $message->direction,
                        'type' => 'link',
                        'url' => $link['url'],
                        'title' => $link['title'] ?: $link['url'],
                        'caption' => $message->text_body,
                        'created_at' => $message->created_at?->toIso8601String(),
                    ];
                }
            }
        }

        return response()->json([
            'media' => array_values($media),
            'documents' => array_values($documents),
            'links' => array_values($links),
            'counts' => [
                'media' => count($media),
                'documents' => count($documents),
                'links' => count($links),
                'total' => count($media) + count($documents) + count($links),
            ],
        ]);
    }

    protected function extractUrlsFromMessage(?string $text, array $payload): array
    {
        $candidates = [];

        if (is_string($text) && trim($text) !== '') {
            $candidates[] = ['text' => $text, 'title' => null];
        }

        foreach (['url', 'link', 'button_url', 'cta_url'] as $key) {
            if (! empty($payload[$key]) && is_string($payload[$key])) {
                $candidates[] = ['text' => $payload[$key], 'title' => $payload['title'] ?? null];
            }
        }

        foreach (['buttons', 'interactive', 'template'] as $key) {
            if (! empty($payload[$key])) {
                $encoded = json_encode($payload[$key]);
                if (is_string($encoded)) {
                    $candidates[] = ['text' => $encoded, 'title' => null];
                }
            }
        }

        $links = [];
        foreach ($candidates as $candidate) {
            preg_match_all('~https?://[^\s<>"\']+~i', $candidate['text'], $matches);
            foreach ($matches[0] ?? [] as $url) {
                $links[] = [
                    'url' => rtrim($url, '.,);]'),
                    'title' => is_string($candidate['title']) ? $candidate['title'] : null,
                ];
            }
        }

        return $links;
    }

    /**
     * Add an internal note to the conversation.
     */
    public function addInternalNote(Request $request, WhatsAppConversation $conversation)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($conversation->account_id, $account->id)) {
            abort(404);
        }

        $validated = $request->validate([
            'note' => 'required|string|max:5000',
        ]);

        $note = WhatsAppConversationNote::create([
            'account_id' => $account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'created_by' => $request->user()?->id,
            'note' => $validated['note'],
        ]);

        $notePayload = [
            'id' => $note->id,
            'note' => $note->note,
            'created_at' => $note->created_at->toIso8601String(),
            'created_by' => $request->user()?->only(['id', 'name', 'email']),
        ];

        event(new InternalNoteAdded($conversation, $notePayload));

        $audit = WhatsAppConversationAuditEvent::create([
            'account_id' => $account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'actor_id' => $request->user()?->id,
            'event_type' => 'note_added',
            'description' => 'Internal note added',
            'meta' => [
                'note_id' => $note->id,
            ],
        ]);

        event(new AuditEventAdded($conversation, [
            'id' => $audit->id,
            'event_type' => $audit->event_type,
            'description' => $audit->description,
            'meta' => $audit->meta,
            'created_at' => $audit->created_at->toIso8601String(),
            'actor' => $request->user()?->only(['id', 'name', 'email']),
        ]));

        return response()->json(['note' => $notePayload]);
    }

    public function markRead(Request $request, WhatsAppConversation $conversation)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($conversation->account_id, $account->id)) {
            abort(404);
        }

        $conversation->load('connection');
        $messages = WhatsAppMessage::where('account_id', $account->id)
            ->where('whatsapp_conversation_id', $conversation->id)
            ->where('direction', 'inbound')
            ->whereNotNull('meta_message_id')
            ->where('meta_message_id', 'like', 'wamid.%')
            ->whereNull('read_at')
            ->orderBy('id')
            ->limit(50)
            ->get();

        $updated = 0;
        foreach ($messages as $message) {
            try {
                if ($conversation->connection && $message->meta_message_id) {
                    $this->whatsappClient->markMessageAsRead($conversation->connection, $message->meta_message_id);
                }
            } catch (\Throwable $e) {
                report($e);

                continue;
            }

            $message->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
            event(new MessageUpdated($message));
            $updated++;
        }

        return response()->json([
            'updated' => $updated,
        ]);
    }

    public function destroy(Request $request, WhatsAppConversation $conversation)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($conversation->account_id, $account->id)) {
            abort(404);
        }
        app(\App\Services\WorkspacePermissionService::class)->assert($request->user(), $account, 'chats.delete');

        $conversationId = $conversation->id;

        $conversation->delete();
        app(\App\Services\AppNotificationService::class)->auditDestructive(
            'chat_deleted',
            "Chat #{$conversationId} deleted",
            $request->user(),
            $account,
            $conversation,
            ['conversation_id' => $conversationId],
            $request
        );

        return redirect()
            ->route('app.whatsapp.conversations.index')
            ->with('success', 'Chat deleted.');
    }

    /**
     * Update conversation meta (status/assignment/priority).
     */
    public function updateMeta(Request $request, WhatsAppConversation $conversation)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($conversation->account_id, $account->id)) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'closed'])],
            'assigned_to' => 'nullable|integer|exists:users,id',
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'tag' => 'nullable|string|max:64',
        ]);

        $updates = [];
        $auditPayloads = [];

        if (array_key_exists('status', $validated) && $validated['status']) {
            $updates['status'] = $validated['status'];
            $auditPayloads[] = [
                'event_type' => 'status_changed',
                'description' => "Status changed to {$validated['status']}",
                'meta' => ['status' => $validated['status']],
            ];
        }

        if (array_key_exists('assigned_to', $validated) && Schema::hasColumn('whatsapp_conversations', 'assigned_to')) {
            $assigneeId = $validated['assigned_to'];
            $previousAssigneeId = $conversation->assigned_to;
            if ($assigneeId) {
                $assignableIds = $account->getAssignableAgentIds();
                if (! in_array((int) $assigneeId, $assignableIds, true)) {
                    return back()->withErrors(['assigned_to' => 'Selected agent is not a team member for this account.']);
                }
            }
            $updates['assigned_to'] = $assigneeId ?: null;

            $actorName = $request->user()?->name ?? 'Someone';
            $assigneeName = $assigneeId ? (User::find($assigneeId)?->name ?? 'Unknown') : null;
            if ($assigneeId && $previousAssigneeId && (int) $previousAssigneeId !== (int) $assigneeId) {
                $description = "Transferred to {$assigneeName} by {$actorName}";
            } elseif ($assigneeId) {
                $description = "Assigned to {$assigneeName} by {$actorName}";
            } else {
                $description = "Unassigned by {$actorName}";
            }
            $auditPayloads[] = [
                'event_type' => 'assigned',
                'description' => $description,
                'meta' => ['assigned_to' => $assigneeId],
            ];
        }

        if (array_key_exists('priority', $validated) && Schema::hasColumn('whatsapp_conversations', 'priority')) {
            $priority = $validated['priority'] ?: 'normal';
            $updates['priority'] = $priority;
            $auditPayloads[] = [
                'event_type' => 'priority_changed',
                'description' => "Priority changed to {$priority}",
                'meta' => ['priority' => $priority],
            ];
        }

        $attachedTag = null;
        if (! empty($validated['tag'])) {
            $tagName = Str::of($validated['tag'])->trim()->limit(64, '')->toString();
            if ($tagName !== '') {
                $tagColors = [
                    'VIP' => '#10B981',
                    'Order' => '#3B82F6',
                    'Support' => '#8B5CF6',
                    'Lead' => '#F59E0B',
                ];
                $tag = ContactTag::firstOrCreate(
                    [
                        'account_id' => $account->id,
                        'name' => $tagName,
                    ],
                    [
                        'color' => $tagColors[$tagName] ?? '#3B82F6',
                    ]
                );
                $conversation->loadMissing('contact');
                $conversation->contact?->tags()->syncWithoutDetaching([$tag->id]);
                $attachedTag = $tag;
                $auditPayloads[] = [
                    'event_type' => 'tag_added',
                    'description' => "Tagged as {$tagName}",
                    'meta' => ['tag' => $tagName, 'tag_id' => $tag->id],
                ];
            }
        }

        if (empty($updates) && ! $attachedTag) {
            return back()->with('success', 'No changes made.');
        }

        if (! empty($updates)) {
            $conversation->update($updates);
        }

        foreach ($auditPayloads as $payload) {
            $audit = WhatsAppConversationAuditEvent::create([
                'account_id' => $account->id,
                'whatsapp_conversation_id' => $conversation->id,
                'actor_id' => $request->user()?->id,
                'event_type' => $payload['event_type'],
                'description' => $payload['description'],
                'meta' => $payload['meta'],
            ]);

            event(new AuditEventAdded($conversation, [
                'id' => $audit->id,
                'event_type' => $audit->event_type,
                'description' => $audit->description,
                'meta' => $audit->meta,
                'created_at' => $audit->created_at->toIso8601String(),
                'actor' => $request->user()?->only(['id', 'name', 'email']),
            ]));
        }

        event(new ConversationUpdated($conversation));

        if ($request->expectsJson()) {
            $conversation->load(['contact.tags:id,account_id,name,color']);

            return response()->json([
                'conversation' => [
                    'id' => $conversation->id,
                    'status' => $conversation->status,
                    'assigned_to' => Schema::hasColumn('whatsapp_conversations', 'assigned_to') ? $conversation->assigned_to : null,
                    'priority' => Schema::hasColumn('whatsapp_conversations', 'priority') ? $conversation->priority : null,
                    'contact' => [
                        'id' => $conversation->contact->id,
                        'wa_id' => $conversation->contact->wa_id,
                        'name' => $conversation->contact->name ?? $conversation->contact->wa_id,
                        'display_phone' => $conversation->contact->phone ?: $conversation->contact->wa_id,
                        'tags' => $conversation->contact->tags->map(fn ($tag) => [
                            'id' => $tag->id,
                            'name' => $tag->name,
                            'color' => $tag->color,
                        ])->values(),
                    ],
                ],
            ]);
        }

        return back()->with('success', 'Conversation updated.');
    }

    public function stopAutomation(Request $request, WhatsAppConversation $conversation)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($conversation->account_id, $account->id)) {
            abort(404);
        }

        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $hadSession = isset($metadata['automation_session']);
        unset($metadata['automation_session']);
        $conversation->forceFill(['metadata' => $metadata])->save();

        if ($hadSession) {
            $audit = WhatsAppConversationAuditEvent::create([
                'account_id' => $account->id,
                'whatsapp_conversation_id' => $conversation->id,
                'actor_id' => $request->user()?->id,
                'event_type' => 'automation_stopped',
                'description' => 'Automation stopped by agent',
                'meta' => [],
            ]);

            event(new AuditEventAdded($conversation, [
                'id' => $audit->id,
                'event_type' => $audit->event_type,
                'description' => $audit->description,
                'meta' => $audit->meta,
                'created_at' => $audit->created_at->toIso8601String(),
                'actor' => $request->user()?->only(['id', 'name', 'email']),
            ]));
        }

        event(new ConversationUpdated($conversation));

        return response()->json([
            'ok' => true,
            'conversation' => [
                'id' => $conversation->id,
                'automation_state' => null,
            ],
        ]);
    }

    public function toggleBot(Request $request, WhatsAppConversation $conversation)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($conversation->account_id, $account->id)) {
            abort(404);
        }

        $validated = $request->validate([
            'paused' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:160'],
            'assign_to_me' => ['nullable', 'boolean'],
        ]);

        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $wasPaused = (bool) ($metadata['bot_paused'] ?? false);
        $requestedPaused = (bool) $validated['paused'];

        if ($validated['paused']) {
            $metadata['bot_paused'] = true;
            $metadata['bot_paused_at'] = now()->toIso8601String();
            $metadata['bot_paused_by'] = $request->user()?->id;
            $metadata['bot_paused_reason'] = $validated['reason'] ?? 'Paused by agent';
            $metadata['handoff_status'] = 'manual';
            $metadata['handoff_reason'] = $metadata['bot_paused_reason'];
            $metadata['handoff_source'] = 'agent';
            $metadata['handoff_at'] = now()->toIso8601String();
            unset($metadata['automation_session']);
            unset(
                $metadata['automation_processing'],
                $metadata['automation_processing_mode'],
                $metadata['automation_processing_started_at'],
                $metadata['automation_processing_expires_at'],
                $metadata['automation_processing_message_id']
            );
        } else {
            unset(
                $metadata['bot_paused'],
                $metadata['bot_paused_at'],
                $metadata['bot_paused_by'],
                $metadata['bot_paused_reason'],
                $metadata['handoff_status'],
                $metadata['handoff_reason'],
                $metadata['handoff_source'],
                $metadata['handoff_at']
            );
        }

        $updates = ['metadata' => $metadata];
        if (($validated['assign_to_me'] ?? false) && Schema::hasColumn('whatsapp_conversations', 'assigned_to')) {
            $updates['assigned_to'] = $request->user()?->id;
        }

        $conversation->forceFill($updates)->save();

        if ($wasPaused !== $requestedPaused) {
            $audit = WhatsAppConversationAuditEvent::create([
                'account_id' => $account->id,
                'whatsapp_conversation_id' => $conversation->id,
                'actor_id' => $request->user()?->id,
                'event_type' => $validated['paused'] ? 'bot_paused' : 'bot_resumed',
                'description' => $validated['paused'] ? 'Bot paused for this chat' : 'Bot resumed for this chat',
                'meta' => ['reason' => $validated['reason'] ?? null],
            ]);

            event(new AuditEventAdded($conversation, [
                'id' => $audit->id,
                'event_type' => $audit->event_type,
                'description' => $audit->description,
                'meta' => $audit->meta,
                'created_at' => $audit->created_at->toIso8601String(),
                'actor' => $request->user()?->only(['id', 'name', 'email']),
            ]));
        }
        event(new ConversationUpdated($conversation));

        return response()->json([
            'ok' => true,
            'conversation' => [
                'id' => $conversation->id,
                'assigned_to' => Schema::hasColumn('whatsapp_conversations', 'assigned_to') ? $conversation->assigned_to : null,
                'automation_state' => $this->inboxPresentation->automationStateForConversation($conversation),
                'bot_paused' => (bool) ($metadata['bot_paused'] ?? false),
                'bot_paused_reason' => $metadata['bot_paused_reason'] ?? null,
                'handoff_status' => $metadata['handoff_status'] ?? null,
                'handoff_reason' => $metadata['handoff_reason'] ?? null,
                'automation_processing' => $this->inboxPresentation->isAutomationProcessing($conversation),
                'automation_processing_mode' => $metadata['automation_processing_mode'] ?? null,
            ],
        ]);
    }

    /**
     * Get an AI-suggested reply for the conversation (requires user preference and AI module).
     */
    public function aiSuggest(Request $request, WhatsAppConversation $conversation)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($conversation->account_id, $account->id)) {
            return response()->json(['error' => 'Conversation not found.'], 404);
        }

        if (! $request->user()?->ai_suggestions_enabled) {
            return response()->json(['error' => 'AI suggestions are disabled in your settings.'], 403);
        }

        $effectiveModules = $this->planResolver->getEffectiveModules($account);
        if (! in_array('ai', $effectiveModules, true)) {
            return response()->json(['error' => 'AI module is not available on your plan.'], 403);
        }

        if (! \App\Models\PlatformSetting::get('ai.enabled', false)) {
            return response()->json(['error' => 'AI is disabled in platform settings.'], 403);
        }

        $validated = $request->validate([
            'agent_id' => ['nullable', 'integer'],
        ]);

        $aiAgent = null;
        if (! empty($validated['agent_id'])) {
            $aiAgent = AiAgent::where('account_id', $account->id)
                ->where('is_active', true)
                ->find($validated['agent_id']);

            if (! $aiAgent) {
                return response()->json(['error' => 'AI agent is unavailable for this workspace.'], 404);
            }
        }

        try {
            $suggestion = $this->conversationAssistant->suggestReply($conversation, 25, null, $aiAgent);
            $suggestion = trim($suggestion);
            $suggestion = $this->applySuggestionGuardrails($suggestion);

            if ($suggestion === '') {
                return response()->json(['error' => 'AI returned an empty suggestion. Please try again.'], 422);
            }

            if (! $this->isUsableSuggestion($suggestion)) {
                return response()->json(['error' => 'AI returned an incomplete suggestion. Please regenerate.'], 422);
            }

            $user = $request->user();
            if ($user && $account && Schema::hasTable('ai_usage_logs')) {
                try {
                    AiUsageLog::create([
                        'user_id' => $user->id,
                        'account_id' => $account->id,
                        'feature' => 'conversation_suggest',
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // Usage logging should never block AI suggestions.
                    \Illuminate\Support\Facades\Log::warning('AI usage log write failed', [
                        'conversation_id' => $conversation->id,
                        'user_id' => $user->id,
                        'account_id' => $account->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'suggestion' => $suggestion,
                'agent' => $aiAgent ? [
                    'id' => $aiAgent->id,
                    'name' => $aiAgent->name,
                    'mode' => $aiAgent->mode,
                ] : null,
            ]);
        } catch (\Throwable $e) {
            [$error, $status] = $this->mapAiSuggestionError($e);
            \Illuminate\Support\Facades\Log::warning('AI suggestion failed', [
                'conversation_id' => $conversation->id,
                'account_id' => $account?->id,
                'user_id' => $request->user()?->id,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => $error,
            ], $status);
        }
    }

    public function aiFeedback(Request $request, WhatsAppConversation $conversation)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($conversation->account_id, $account->id)) {
            return response()->json(['error' => 'Conversation not found.'], 404);
        }

        $validated = $request->validate([
            'suggestion' => 'required|string|max:8000',
            'verdict' => 'required|string|in:up,down',
            'reason' => 'nullable|string|max:1000',
        ]);

        if (Schema::hasTable('ai_suggestion_feedback')) {
            AiSuggestionFeedback::create([
                'account_id' => $account->id,
                'user_id' => $request->user()?->id,
                'whatsapp_conversation_id' => $conversation->id,
                'suggestion' => $validated['suggestion'],
                'verdict' => $validated['verdict'],
                'reason' => $validated['reason'] ?? null,
                'metadata' => [
                    'contact_id' => $conversation->whatsapp_contact_id,
                    'connection_id' => $conversation->whatsapp_connection_id,
                ],
            ]);
        }

        return response()->json(['ok' => true]);
    }

    protected function mapAiSuggestionError(\Throwable $e): array
    {
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
            return [$e->getMessage(), $e->getStatusCode()];
        }

        $message = trim((string) $e->getMessage());
        $lower = Str::lower($message);

        if (Str::contains($lower, ['api key not configured', 'unknown ai provider', 'not configured'])) {
            return ['AI provider is not configured. Please check Platform Settings -> AI.', 422];
        }

        if (Str::contains($lower, ['invalid api key', 'incorrect api key', 'invalid_api_key', 'unauthorized', 'authentication'])) {
            return ['AI provider authentication failed. Please verify API credentials in Platform Settings.', 422];
        }

        if (Str::contains($lower, ['timeout', 'timed out', 'curl error 28', 'connection', 'network'])) {
            return ['AI provider timed out. Please retry in a few seconds.', 503];
        }

        if (Str::contains($lower, ['gemini', 'model']) && Str::contains($lower, ['not found', 'not supported', 'listmodels'])) {
            return ['Selected Gemini model is unavailable for your API key/version. Set AI model to gemini-2.0-flash in Platform Settings -> AI and try again.', 422];
        }

        if ($message !== '') {
            return ['AI suggestion failed: '.$message, 503];
        }

        return ['AI suggestion failed. Please try again.', 503];
    }

    protected function applySuggestionGuardrails(string $suggestion): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($suggestion)) ?? '';
        if ($normalized === '') {
            return '';
        }

        // Basic PII guardrails: redact long numeric strings and card-like patterns.
        $normalized = preg_replace('/\b\d{10,19}\b/', '[REDACTED-NUMBER]', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b(?:\d[ -]*?){13,16}\b/', '[REDACTED-CARD]', $normalized) ?? $normalized;

        return trim($normalized);
    }

    protected function isUsableSuggestion(string $suggestion): bool
    {
        $suggestion = trim($suggestion);

        if (mb_strlen($suggestion) < 24) {
            return false;
        }

        foreach ([
            '/\bto help me understand\s*$/i',
            '/\bplease share\s*$/i',
            '/\bcan you share\s*$/i',
            '/\bcould you share\s*$/i',
            '/\bmay i know\s*$/i',
            '/\btell me more about\s*$/i',
        ] as $pattern) {
            if (preg_match($pattern, $suggestion)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Send a message in a conversation.
     */
    public function sendMessage(Request $request, WhatsAppConversation $conversation)
    {
        $account = $request->attributes->get('account') ?? current_account();

        // Ensure conversation belongs to account
        if (! account_ids_match($conversation->account_id, $account->id)) {
            abort(404);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:4096',
            'reply_message_id' => 'nullable|integer']);

        if ($this->inboxPresentation->isAutomationProcessing($conversation)) {
            return response()->json([
                'message' => 'Bot is replying',
                'message_detail' => 'Wait for the bot reply to finish, or switch this chat to Manual before sending.',
            ], 409);
        }

        $this->ensureCustomerServiceWindowOpen($conversation);

        // Check message limit before sending
        $this->entitlementService->assertWithinLimit($account, 'messages_monthly', 1);

        $conversation->load(['connection', 'contact']);
        $replyContext = $this->replyContext($conversation, $validated['reply_message_id'] ?? null);

        // Use transaction with lock to prevent duplicate message creation
        $message = DB::transaction(function () use ($account, $conversation, $validated, $replyContext) {
            return WhatsAppMessage::lockForUpdate()
                ->create([
                    'account_id' => $account->id,
                    'whatsapp_conversation_id' => $conversation->id,
                    'direction' => 'outbound',
                    'type' => 'text',
                    'text_body' => $validated['message'],
                    'payload' => $replyContext ? ['reply' => $replyContext] : [],
                    'status' => 'queued']);
        });

        // Load relationships for broadcast
        $message->load('conversation.contact');

        // Broadcast optimistic message created
        event(new MessageCreated($message));

        try {
            // Send via WhatsApp API
            $response = $this->whatsappClient->sendTextMessage(
                $conversation->connection,
                $conversation->contact->wa_id,
                $validated['message'],
                $replyContext['meta_message_id'] ?? null
            );

            // Update message with Meta message ID and status
            $metaMessageId = $response['messages'][0]['id'] ?? null;
            $message->update([
                'meta_message_id' => $metaMessageId,
                'status' => 'sent',
                'sent_at' => now(),
                'payload' => array_merge($message->payload ?? [], ['response' => $response])]);

            // Increment usage counter
            $this->usageService->incrementMessages($account, 1);

            // Update conversation
            $conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => substr($validated['message'], 0, 100)]);

            $this->recordHumanReplyCooldown($conversation, $request->user());
            $this->touchContactAfterOutbound($conversation);

            // Broadcast message update and conversation update
            event(new MessageUpdated($message));
            event(new ConversationUpdated($conversation));

            if ($request->expectsJson()) {
                return response()->json([
                    'data' => [
                        'message' => $this->messageResponsePayload($message),
                    ],
                ], 201);
            }

            return redirect()->back()->with('success', 'Message sent successfully.');
        } catch (\Exception $e) {
            $message->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()]);

            // Broadcast failed status
            event(new MessageUpdated($message));

            $msg = $e->getMessage();
            $is24hWindow = $e->getCode() === 131047
                || stripos($msg, '131047') !== false
                || stripos($msg, 'recovery') !== false
                || stripos($msg, 'template') !== false
                || stripos($msg, 'session') !== false
                || stripos($msg, '24 hour') !== false
                || stripos($msg, 'message outside') !== false;

            if ($is24hWindow) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'outside_24h',
                        'message_detail' => 'You can only send a template message to reopen this conversation. Use the template button above.',
                        'data' => [
                            'message' => $this->messageResponsePayload($message),
                        ],
                    ], 422);
                }

                return redirect()->back()->withErrors([
                    'message' => 'outside_24h',
                    'message_detail' => 'You can only send a template message to reopen this conversation. Use the template button above.']);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Failed to send message.',
                    'error' => $e->getMessage(),
                    'data' => [
                        'message' => $this->messageResponsePayload($message),
                    ],
                ], 422);
            }

            return redirect()->back()->withErrors([
                'message' => 'Failed to send message: '.$e->getMessage()]);
        }
    }

    public function retryMessage(Request $request, WhatsAppConversation $conversation, WhatsAppMessage $message)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (
            ! account_ids_match($conversation->account_id, $account->id)
            || ! account_ids_match($message->account_id, $account->id)
            || (string) $message->whatsapp_conversation_id !== (string) $conversation->id
        ) {
            abort(404);
        }

        if ($message->direction !== 'outbound' || $message->status !== 'failed') {
            return response()->json([
                'message' => 'Only failed outbound messages can be retried.',
            ], 422);
        }

        $conversation->load(['connection', 'contact']);

        if ($message->meta_message_id) {
            $message->update([
                'status' => 'sent',
                'error_message' => null,
                'sent_at' => $message->sent_at ?: now(),
            ]);

            event(new MessageUpdated($message));

            return response()->json([
                'data' => [
                    'message' => [
                        'id' => $message->id,
                        'direction' => $message->direction,
                        'type' => $message->type,
                        'text_body' => $message->text_body,
                        'payload' => $message->payload,
                        'status' => $message->status,
                        'meta_message_id' => $message->meta_message_id,
                        'created_at' => $message->created_at->toIso8601String(),
                        'updated_at' => $message->updated_at?->toIso8601String(),
                        'sent_at' => $message->sent_at?->toIso8601String(),
                        'delivered_at' => $message->delivered_at?->toIso8601String(),
                        'read_at' => $message->read_at?->toIso8601String(),
                        'error_message' => $message->status === 'failed' ? \App\Support\CustomerMessage::humanize($message->error_message ?: 'This message could not be delivered.', 'This message could not be delivered.') : null,
                    ],
                ],
            ]);
        }

        $this->entitlementService->assertWithinLimit($account, 'messages_monthly', 1);

        $message->update([
            'status' => 'queued',
            'error_message' => null,
        ]);
        event(new MessageUpdated($message));

        try {
            $payload = $message->payload ?? [];
            $caption = $message->type === 'audio' ? null : ($message->text_body ?? ($payload['caption'] ?? null));
            $filename = $payload['filename'] ?? null;
            $isVoice = $message->type === 'audio' && (bool) ($payload['voice'] ?? false);

            if ($message->type === 'text') {
                $response = $this->whatsappClient->sendTextMessage(
                    $conversation->connection,
                    $conversation->contact->wa_id,
                    (string) $message->text_body
                );
            } elseif (in_array($message->type, ['image', 'video', 'document', 'audio'], true)) {
                $mediaId = $payload['media_id'] ?? $payload['meta_upload']['id'] ?? null;

                if (! $mediaId) {
                    $link = $payload['link'] ?? $payload['url'] ?? null;
                    $path = $this->mediaAttachments->storagePathFromPublicUrl($link);
                    if (! $path || ! Storage::disk('public')->exists($path)) {
                        throw new \RuntimeException('Original media file is no longer available for retry.');
                    }

                    $mimeType = $this->mediaAttachments->metaMediaMimeType(
                        $message->type,
                        $payload['mime_type'] ?? null,
                        $filename,
                        $path
                    );

                    $upload = $this->whatsappClient->uploadMedia(
                        $conversation->connection,
                        Storage::disk('public')->path($path),
                        $filename,
                        $mimeType
                    );
                    $mediaId = $upload['id'];
                    $message->update([
                        'payload' => array_merge($message->payload ?? [], [
                            'media_id' => $mediaId,
                            'meta_upload' => $upload,
                            'mime_type' => $mimeType,
                        ]),
                    ]);
                }

                $response = $this->whatsappClient->sendUploadedMediaMessage(
                    $conversation->connection,
                    $conversation->contact->wa_id,
                    $message->type,
                    (string) $mediaId,
                    $caption,
                    $message->type === 'document' ? $filename : null,
                    $isVoice
                );

            } elseif ($message->type === 'reaction') {
                $reaction = $payload['reaction'] ?? [];
                if (empty($reaction['message_id']) || empty($reaction['emoji'])) {
                    throw new \RuntimeException('Reaction target is missing.');
                }

                $response = $this->whatsappClient->sendReactionMessage(
                    $conversation->connection,
                    $conversation->contact->wa_id,
                    (string) $reaction['message_id'],
                    (string) $reaction['emoji']
                );
            } else {
                throw new \RuntimeException('Retry is not available for this message type.');
            }

            $metaMessageId = $response['messages'][0]['id'] ?? null;
            $message->update([
                'meta_message_id' => $metaMessageId,
                'status' => 'sent',
                'sent_at' => now(),
                'payload' => array_merge($message->payload ?? [], ['retry_response' => $response]),
            ]);

            $this->usageService->incrementMessages($account, 1);

            $conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => $message->text_body ?: strtoupper($message->type).' attachment',
            ]);

            $this->touchContactAfterOutbound($conversation);
            event(new MessageUpdated($message));
            event(new ConversationUpdated($conversation));

            return response()->json([
                'data' => [
                    'message' => [
                        'id' => $message->id,
                        'direction' => $message->direction,
                        'type' => $message->type,
                        'text_body' => $message->text_body,
                        'payload' => $message->payload,
                        'status' => $message->status,
                        'meta_message_id' => $message->meta_message_id,
                        'created_at' => $message->created_at->toIso8601String(),
                        'updated_at' => $message->updated_at?->toIso8601String(),
                        'sent_at' => $message->sent_at?->toIso8601String(),
                        'delivered_at' => $message->delivered_at?->toIso8601String(),
                        'read_at' => $message->read_at?->toIso8601String(),
                        'error_message' => $message->status === 'failed' ? \App\Support\CustomerMessage::humanize($message->error_message ?: 'This message could not be delivered.', 'This message could not be delivered.') : null,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            $message->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            event(new MessageUpdated($message));

            return response()->json([
                'message' => 'Message could not be resent.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function sendTemplateMessage(Request $request, WhatsAppConversation $conversation)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($conversation->account_id, $account->id)) {
            abort(404);
        }

        if (! $request->has('variables') || $request->input('variables') === null || $request->input('variables') === '') {
            $request->merge(['variables' => []]);
        }

        $validated = $request->validate([
            'template_id' => 'required|integer|exists:whatsapp_templates,id',
            'variables' => 'sometimes|array',
            'variables.*' => 'nullable|string|max:1024']);

        $template = WhatsAppTemplate::where('account_id', $account->id)
            ->where('whatsapp_connection_id', $conversation->whatsapp_connection_id)
            ->whereRaw('LOWER(TRIM(status)) = ?', ['approved'])
            ->where(function ($query) {
                $query->where('is_archived', false)
                    ->orWhereNull('is_archived');
            })
            ->findOrFail($validated['template_id']);

        $this->entitlementService->assertWithinLimit($account, 'messages_monthly', 1);

        $conversation->load(['connection', 'contact']);

        try {
            DB::beginTransaction();

            $payload = $this->templateComposer->preparePayload(
                $template,
                $conversation->contact->wa_id,
                $validated['variables'] ?? []
            );

            $preview = $this->templateComposer->renderPreview($template, $validated['variables'] ?? []);

            $message = WhatsAppMessage::lockForUpdate()->create([
                'account_id' => $account->id,
                'whatsapp_conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'type' => 'template',
                'text_body' => $preview['body'],
                'payload' => $payload,
                'status' => 'queued']);

            $templateSend = WhatsAppTemplateSend::create([
                'account_id' => $account->id,
                'whatsapp_template_id' => $template->id,
                'whatsapp_message_id' => $message->id,
                'to_wa_id' => $conversation->contact->wa_id,
                'variables' => $validated['variables'] ?? [],
                'status' => 'queued']);

            $message->load('conversation.contact');
            event(new MessageCreated($message));

            $response = $this->whatsappClient->sendTemplateMessage(
                $conversation->connection,
                $conversation->contact->wa_id,
                $template->name,
                $template->language,
                $payload['template']['components'] ?? []
            );

            $metaMessageId = $response['messages'][0]['id'] ?? null;
            $message->update([
                'meta_message_id' => $metaMessageId,
                'status' => 'sent',
                'sent_at' => now()]);

            $templateSend->update([
                'status' => 'sent',
                'sent_at' => now()]);

            $this->usageService->incrementMessages($account, 1);
            $this->usageService->incrementTemplateSends($account, 1);

            $conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => substr($message->text_body ?? '', 0, 100)]);

            $this->touchContactAfterOutbound($conversation);

            event(new MessageUpdated($message));
            event(new ConversationUpdated($conversation));

            DB::commit();

            return redirect()->back()->with('success', 'Template sent successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            if (isset($message)) {
                $message->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage()]);
                event(new MessageUpdated($message));
            }

            if (isset($templateSend)) {
                $templateSend->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage()]);
            }

            return redirect()->back()->withErrors([
                'template' => 'Failed to send template. Please try again.']);
        }
    }

    public function sendMediaMessage(Request $request, WhatsAppConversation $conversation)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($conversation->account_id, $account->id)) {
            abort(404);
        }

        $validated = $request->validate([
            'type' => 'required|in:image,video,document,audio',
            'caption' => 'nullable|string|max:1024',
            'is_voice' => 'nullable|boolean',
            'reply_message_id' => 'nullable|integer',
            'attachment' => 'required|file|max:102400']);

        $this->ensureCustomerServiceWindowOpen($conversation);

        $this->entitlementService->assertWithinLimit($account, 'messages_monthly', 1);

        $conversation->load(['connection', 'contact']);
        $replyContext = $this->replyContext($conversation, $validated['reply_message_id'] ?? null);

        $file = $request->file('attachment');
        $type = $validated['type'];
        $this->mediaAttachments->validateMediaAttachment($file, $type);

        $path = $file->store('whatsapp-media', 'public');
        $url = Storage::disk('public')->url($path);
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = rtrim(config('app.url'), '/').'/'.ltrim($url, '/');
        }

        $caption = $type === 'audio' ? null : ($validated['caption'] ?? null);
        $filename = $file->getClientOriginalName();
        $isVoice = $type === 'audio' && $request->boolean('is_voice');
        $mimeType = $this->mediaAttachments->metaMediaMimeType(
            $type,
            $file->getClientMimeType() ?: $file->getMimeType(),
            $filename,
            $path
        );

        $message = WhatsAppMessage::lockForUpdate()->create([
            'account_id' => $account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => $type,
            'text_body' => $caption,
            'payload' => [
                'type' => $type,
                'link' => $url,
                'caption' => $caption,
                'filename' => $filename,
                'mime_type' => $mimeType,
                'voice' => $isVoice,
                'reply' => $replyContext,
            ],
            'status' => 'queued']);

        $message->load('conversation.contact');
        event(new MessageCreated($message));

        try {

            $upload = $this->whatsappClient->uploadMedia(
                $conversation->connection,
                Storage::disk('public')->path($path),
                $filename,
                $mimeType
            );

            $metaMediaId = $upload['id'];
            $message->update([
                'payload' => array_merge($message->payload ?? [], [
                    'media_id' => $metaMediaId,
                    'meta_upload' => $upload,
                ]),
            ]);

            $response = $this->whatsappClient->sendUploadedMediaMessage(
                $conversation->connection,
                $conversation->contact->wa_id,
                $type,
                $metaMediaId,
                $caption,
                $type === 'document' ? $filename : null,
                $isVoice,
                $replyContext['meta_message_id'] ?? null
            );

            $metaMessageId = $response['messages'][0]['id'] ?? null;
            $message->update([
                'meta_message_id' => $metaMessageId,
                'status' => 'sent',
                'sent_at' => now(),
                'payload' => array_merge($message->payload ?? [], ['response' => $response])]);

            $this->usageService->incrementMessages($account, 1);

            $conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => $caption ? substr($caption, 0, 100) : strtoupper($type).' attachment']);

            $this->touchContactAfterOutbound($conversation);

            event(new MessageUpdated($message));
            event(new ConversationUpdated($conversation));

            if ($request->expectsJson()) {
                return response()->json([
                    'data' => [
                        'message_id' => $message->id,
                        'meta_message_id' => $message->meta_message_id,
                        'conversation_id' => $conversation->id,
                        'status' => $message->status,
                        'message' => [
                            'id' => $message->id,
                            'direction' => $message->direction,
                            'type' => $message->type,
                            'text_body' => $message->text_body,
                            'payload' => $message->payload,
                            'status' => $message->status,
                            'created_at' => $message->created_at->toIso8601String(),
                            'sent_at' => $message->sent_at?->toIso8601String(),
                            'delivered_at' => $message->delivered_at?->toIso8601String(),
                            'read_at' => $message->read_at?->toIso8601String(),
                            'error_message' => $message->status === 'failed' ? \App\Support\CustomerMessage::humanize($message->error_message ?: 'This message could not be delivered.', 'This message could not be delivered.') : null,
                        ],
                    ],
                ], 201);
            }

            return redirect()->back()->with('success', 'Media sent successfully.');
        } catch (\Exception $e) {
            $message->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()]);
            event(new MessageUpdated($message));

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Failed to send media. Please try again.',
                    'error' => $e->getMessage(),
                    'data' => [
                        'message_id' => $message->id,
                        'conversation_id' => $conversation->id,
                        'status' => 'failed',
                    ],
                ], 422);
            }

            return redirect()->back()->withErrors([
                'media' => 'Failed to send media. Please try again.']);
        }
    }

    public function sendReaction(Request $request, WhatsAppConversation $conversation)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($conversation->account_id, $account->id)) {
            abort(404);
        }

        $validated = $request->validate([
            'message_id' => ['required'],
            'emoji' => ['required', 'string', 'max:16'],
        ]);

        $conversation->load(['connection', 'contact']);

        $target = WhatsAppMessage::where('account_id', $account->id)
            ->where('whatsapp_conversation_id', $conversation->id)
            ->where('id', $validated['message_id'])
            ->first();

        if (! $target || ! $target->meta_message_id) {
            return response()->json([
                'message' => 'This message cannot be reacted to yet because Meta has not returned its message ID.',
            ], 422);
        }

        $message = WhatsAppMessage::create([
            'account_id' => $account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'reaction',
            'text_body' => $validated['emoji'],
            'payload' => [
                'reaction' => [
                    'message_id' => $target->meta_message_id,
                    'emoji' => $validated['emoji'],
                    'target_local_message_id' => $target->id,
                ],
            ],
            'status' => 'queued',
        ]);

        $message->load('conversation.contact');
        event(new MessageCreated($message));

        try {
            $response = $this->whatsappClient->sendReactionMessage(
                $conversation->connection,
                $conversation->contact->wa_id,
                $target->meta_message_id,
                $validated['emoji']
            );

            $metaMessageId = $response['messages'][0]['id'] ?? null;
            $message->update([
                'meta_message_id' => $metaMessageId,
                'status' => 'sent',
                'sent_at' => now(),
                'payload' => array_merge($message->payload ?? [], ['response' => $response]),
            ]);

            $conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => 'Reacted '.$validated['emoji'],
            ]);

            event(new MessageUpdated($message));
            event(new ConversationUpdated($conversation));

            return response()->json([
                'data' => [
                    'message' => [
                        'id' => $message->id,
                        'direction' => $message->direction,
                        'type' => $message->type,
                        'text_body' => $message->text_body,
                        'payload' => $message->payload,
                        'status' => $message->status,
                        'meta_message_id' => $message->meta_message_id,
                        'created_at' => $message->created_at->toIso8601String(),
                        'updated_at' => $message->updated_at?->toIso8601String(),
                        'sent_at' => $message->sent_at?->toIso8601String(),
                        'delivered_at' => $message->delivered_at?->toIso8601String(),
                        'read_at' => $message->read_at?->toIso8601String(),
                        'error_message' => $message->status === 'failed' ? \App\Support\CustomerMessage::humanize($message->error_message ?: 'This message could not be delivered.', 'This message could not be delivered.') : null,
                    ],
                ],
            ], 201);
        } catch (\Exception $e) {
            $message->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            event(new MessageUpdated($message));

            return response()->json([
                'message' => 'Reaction not sent. Please try again.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    protected function replyContext(WhatsAppConversation $conversation, mixed $replyMessageId): ?array
    {
        if (! $replyMessageId) {
            return null;
        }

        $replyMessage = WhatsAppMessage::where('account_id', $conversation->account_id)
            ->where('whatsapp_conversation_id', $conversation->id)
            ->whereKey($replyMessageId)
            ->first();

        if (! $replyMessage || ! $replyMessage->meta_message_id) {
            return null;
        }

        $preview = $replyMessage->text_body;
        if (! $preview) {
            $preview = match ($replyMessage->type) {
                'audio' => ($replyMessage->payload['voice'] ?? false) ? 'Voice message' : 'Audio message',
                'image' => 'Image message',
                'video' => 'Video message',
                'document' => $replyMessage->payload['filename'] ?? 'Document message',
                'location' => 'Location message',
                default => ucfirst((string) $replyMessage->type).' message',
            };
        }

        return [
            'message_id' => $replyMessage->id,
            'meta_message_id' => $replyMessage->meta_message_id,
            'preview' => Str::limit((string) $preview, 160, ''),
        ];
    }

    public function sendLocationMessage(Request $request, WhatsAppConversation $conversation)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($conversation->account_id, $account->id)) {
            abort(404);
        }

        $validated = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'name' => 'nullable|string|max:120',
            'address' => 'nullable|string|max:255']);

        $this->ensureCustomerServiceWindowOpen($conversation);

        $this->entitlementService->assertWithinLimit($account, 'messages_monthly', 1);

        $conversation->load(['connection', 'contact']);

        $message = WhatsAppMessage::lockForUpdate()->create([
            'account_id' => $account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'location',
            'text_body' => $validated['name'] ?? null,
            'payload' => [
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'name' => $validated['name'] ?? null,
                'address' => $validated['address'] ?? null],
            'status' => 'queued']);

        $message->load('conversation.contact');
        event(new MessageCreated($message));

        try {
            $response = $this->whatsappClient->sendLocationMessage(
                $conversation->connection,
                $conversation->contact->wa_id,
                [
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                    'name' => $validated['name'] ?? null,
                    'address' => $validated['address'] ?? null]
            );

            $metaMessageId = $response['messages'][0]['id'] ?? null;
            $message->update([
                'meta_message_id' => $metaMessageId,
                'status' => 'sent',
                'sent_at' => now(),
                'payload' => array_merge($message->payload ?? [], ['response' => $response])]);

            $this->usageService->incrementMessages($account, 1);

            $conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => 'Location shared']);

            $this->touchContactAfterOutbound($conversation);

            event(new MessageUpdated($message));
            event(new ConversationUpdated($conversation));

            return redirect()->back()->with('success', 'Location sent successfully.');
        } catch (\Exception $e) {
            $message->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()]);
            event(new MessageUpdated($message));

            return redirect()->back()->withErrors([
                'location' => 'Failed to send location. Please try again.']);
        }
    }

    /**
     * Send a list message.
     */
    public function sendList(Request $request, WhatsAppConversation $conversation)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($conversation->account_id, $account->id)) {
            abort(404);
        }

        $validated = $request->validate([
            'list_id' => 'nullable|exists:whatsapp_lists,id',
            'body_text' => 'required_without:list_id|nullable|string|max:1024',
            'button_text' => 'required_without:list_id|nullable|string|max:20',
            'header_text' => 'nullable|string|max:60',
            'footer_text' => 'nullable|string|max:60',
            'rows' => 'required_without:list_id|nullable|array|min:1|max:10',
            'rows.*.id' => 'required_with:rows|string|max:200',
            'rows.*.title' => 'required_with:rows|string|max:24',
            'rows.*.description' => 'nullable|string|max:72',
        ]);

        $this->ensureCustomerServiceWindowOpen($conversation);

        $this->entitlementService->assertWithinLimit($account, 'messages_monthly', 1);

        $conversation->load(['connection', 'contact']);

        if (! empty($validated['list_id'])) {
            $list = \App\Modules\WhatsApp\Models\WhatsAppList::where('id', $validated['list_id'])
                ->where('account_id', $account->id)
                ->where('is_active', true)
                ->firstOrFail();

            if ($list->whatsapp_connection_id !== $conversation->whatsapp_connection_id) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'This list belongs to a different connection.'], 422)
                    : redirect()->back()->withErrors(['list_id' => 'This list belongs to a different connection.']);
            }

            $listFormat = $list->toMetaFormat();
            $listName = $list->name;
            $buttonText = $list->button_text;
            $sections = $listFormat['action']['sections'];
            $headerText = $listFormat['header']['text'] ?? null;
            $bodyText = $listFormat['body']['text'] ?? null;
            $footerText = $listFormat['footer']['text'] ?? null;
            $listId = $list->id;
        } else {
            $sections = [[
                'title' => 'Options',
                'rows' => collect($validated['rows'] ?? [])->map(fn ($row) => array_filter([
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'description' => $row['description'] ?? null,
                ]))->values()->all(),
            ]];
            $buttonText = $validated['button_text'];
            $headerText = $validated['header_text'] ?? null;
            $bodyText = $validated['body_text'];
            $footerText = $validated['footer_text'] ?? null;
            $listName = $headerText ?: $bodyText;
            $listId = null;
            $listFormat = [
                'type' => 'list',
                'header' => $headerText ? ['type' => 'text', 'text' => $headerText] : null,
                'body' => ['text' => $bodyText],
                'footer' => $footerText ? ['text' => $footerText] : null,
                'action' => ['button' => $buttonText, 'sections' => $sections],
            ];
        }

        $message = WhatsAppMessage::lockForUpdate()->create([
            'account_id' => $account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'interactive',
            'text_body' => $bodyText ?? $listName,
            'payload' => [
                'interactive_type' => 'list',
                'list_id' => $listId,
                'list_name' => $listName,
                'interactive' => $listFormat,
            ],
            'status' => 'queued']);

        $message->load('conversation.contact');
        event(new MessageCreated($message));

        try {
            $response = $this->whatsappClient->sendListMessage(
                $conversation->connection,
                $conversation->contact->wa_id,
                $buttonText,
                $sections,
                $headerText,
                $bodyText,
                $footerText
            );

            $metaMessageId = $response['messages'][0]['id'] ?? null;
            $message->update([
                'meta_message_id' => $metaMessageId,
                'status' => 'sent',
                'sent_at' => now(),
                'payload' => array_merge($message->payload ?? [], ['response' => $response])]);

            $this->usageService->incrementMessages($account, 1);

            $conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => $listName]);

            $this->touchContactAfterOutbound($conversation);

            event(new MessageUpdated($message));
            event(new ConversationUpdated($conversation));

            if ($request->expectsJson()) {
                return response()->json(['data' => ['message' => $this->messageResponsePayload($message)]], 201);
            }

            return redirect()->back()->with('success', 'List message sent successfully.');
        } catch (\Exception $e) {
            $message->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()]);
            event(new MessageUpdated($message));

            return $request->expectsJson()
                ? response()->json(['message' => 'Failed to send list.', 'error' => $e->getMessage()], 422)
                : redirect()->back()->withErrors(['list' => 'Failed to send list: '.$e->getMessage()]);
        }
    }

    /**
     * Send interactive buttons message.
     */
    public function sendInteractiveButtons(Request $request, WhatsAppConversation $conversation)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($conversation->account_id, $account->id)) {
            abort(404);
        }

        $validated = $request->validate([
            'body_text' => 'required|string|max:1024',
            'buttons' => 'required|array|min:1|max:3',
            'buttons.*.id' => 'required|string|max:256',
            'buttons.*.text' => 'required|string|max:20',
            'header_text' => 'nullable|string|max:60',
            'footer_text' => 'nullable|string|max:60',
        ]);

        $this->ensureCustomerServiceWindowOpen($conversation);

        $this->entitlementService->assertWithinLimit($account, 'messages_monthly', 1);

        $conversation->load(['connection', 'contact']);

        $message = WhatsAppMessage::lockForUpdate()->create([
            'account_id' => $account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'interactive',
            'text_body' => $validated['body_text'],
            'payload' => [
                'interactive_type' => 'button',
                'buttons' => $validated['buttons'],
                'header_text' => $validated['header_text'] ?? null,
                'footer_text' => $validated['footer_text'] ?? null,
            ],
            'status' => 'queued']);

        $message->load('conversation.contact');
        event(new MessageCreated($message));

        try {
            $response = $this->whatsappClient->sendInteractiveButtons(
                $conversation->connection,
                $conversation->contact->wa_id,
                $validated['body_text'],
                $validated['buttons'],
                $validated['header_text'] ?? null,
                $validated['footer_text'] ?? null
            );

            $metaMessageId = $response['messages'][0]['id'] ?? null;
            $message->update([
                'meta_message_id' => $metaMessageId,
                'status' => 'sent',
                'sent_at' => now(),
                'payload' => array_merge($message->payload ?? [], ['response' => $response])]);

            $this->usageService->incrementMessages($account, 1);

            $conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => substr($validated['body_text'], 0, 100)]);

            $this->touchContactAfterOutbound($conversation);

            event(new MessageUpdated($message));
            event(new ConversationUpdated($conversation));

            if ($request->expectsJson()) {
                return response()->json(['data' => ['message' => $this->messageResponsePayload($message)]], 201);
            }

            return redirect()->back()->with('success', 'Interactive buttons sent successfully.');
        } catch (\Exception $e) {
            $message->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()]);
            event(new MessageUpdated($message));

            return $request->expectsJson()
                ? response()->json(['message' => 'Failed to send interactive buttons.', 'error' => $e->getMessage()], 422)
                : redirect()->back()->withErrors(['buttons' => 'Failed to send interactive buttons: '.$e->getMessage()]);
        }
    }

    public function sendFlow(Request $request, WhatsAppConversation $conversation)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($conversation->account_id, $account->id)) {
            abort(404);
        }

        $validated = $request->validate([
            'flow_id' => 'required|string|max:80',
            'body_text' => 'required|string|max:1024',
            'cta' => 'required|string|max:30',
            'header_text' => 'nullable|string|max:60',
            'footer_text' => 'nullable|string|max:60',
            'flow_token' => 'nullable|string|max:256',
            'flow_action' => 'nullable|string|in:navigate,data_exchange',
            'screen' => 'nullable|string|max:80',
        ]);

        $this->ensureCustomerServiceWindowOpen($conversation);

        $this->entitlementService->assertWithinLimit($account, 'messages_monthly', 1);
        $conversation->load(['connection', 'contact']);

        $message = WhatsAppMessage::lockForUpdate()->create([
            'account_id' => $account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'interactive',
            'text_body' => $validated['body_text'],
            'payload' => [
                'interactive_type' => 'flow',
                'flow_id' => $validated['flow_id'],
                'cta' => $validated['cta'],
                'header_text' => $validated['header_text'] ?? null,
                'footer_text' => $validated['footer_text'] ?? null,
            ],
            'status' => 'queued',
        ]);

        $message->load('conversation.contact');
        event(new MessageCreated($message));

        try {
            $response = $this->whatsappClient->sendFlowMessage(
                $conversation->connection,
                $conversation->contact->wa_id,
                $validated['flow_id'],
                $validated['body_text'],
                $validated['cta'],
                $validated['header_text'] ?? null,
                $validated['footer_text'] ?? null,
                $validated['flow_token'] ?? '',
                $validated['flow_action'] ?? 'navigate',
                $validated['screen'] ?? null
            );

            $message->update([
                'meta_message_id' => $response['messages'][0]['id'] ?? null,
                'status' => 'sent',
                'sent_at' => now(),
                'payload' => array_merge($message->payload ?? [], ['response' => $response]),
            ]);

            $this->usageService->incrementMessages($account, 1);
            $conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => substr($validated['body_text'], 0, 100),
            ]);
            $this->touchContactAfterOutbound($conversation);
            event(new MessageUpdated($message));
            event(new ConversationUpdated($conversation));

            return response()->json(['data' => ['message' => $this->messageResponsePayload($message)]], 201);
        } catch (\Exception $e) {
            $message->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            event(new MessageUpdated($message));

            return response()->json(['message' => 'Failed to send form.', 'error' => $e->getMessage()], 422);
        }
    }

    public function sendCtaUrl(Request $request, WhatsAppConversation $conversation)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($conversation->account_id, $account->id)) {
            abort(404);
        }

        $validated = $request->validate([
            'body_text' => 'required|string|max:1024',
            'display_text' => 'required|string|max:20',
            'url' => 'required|url|max:2000',
            'header_text' => 'nullable|string|max:60',
            'footer_text' => 'nullable|string|max:60',
        ]);

        $this->ensureCustomerServiceWindowOpen($conversation);

        $this->entitlementService->assertWithinLimit($account, 'messages_monthly', 1);
        $conversation->load(['connection', 'contact']);

        $message = WhatsAppMessage::lockForUpdate()->create([
            'account_id' => $account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'interactive',
            'text_body' => $validated['body_text'],
            'payload' => [
                'interactive_type' => 'cta_url',
                'display_text' => $validated['display_text'],
                'url' => $validated['url'],
                'header_text' => $validated['header_text'] ?? null,
                'footer_text' => $validated['footer_text'] ?? null,
            ],
            'status' => 'queued',
        ]);

        $message->load('conversation.contact');
        event(new MessageCreated($message));

        try {
            $response = $this->whatsappClient->sendCtaUrlMessage(
                $conversation->connection,
                $conversation->contact->wa_id,
                $validated['body_text'],
                $validated['display_text'],
                $validated['url'],
                $validated['header_text'] ?? null,
                $validated['footer_text'] ?? null
            );

            $message->update([
                'meta_message_id' => $response['messages'][0]['id'] ?? null,
                'status' => 'sent',
                'sent_at' => now(),
                'payload' => array_merge($message->payload ?? [], ['response' => $response]),
            ]);

            $this->usageService->incrementMessages($account, 1);
            $conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => substr($validated['body_text'], 0, 100),
            ]);
            $this->touchContactAfterOutbound($conversation);
            event(new MessageUpdated($message));
            event(new ConversationUpdated($conversation));

            return response()->json(['data' => ['message' => $this->messageResponsePayload($message)]], 201);
        } catch (\Exception $e) {
            $message->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            event(new MessageUpdated($message));

            return response()->json(['message' => 'Failed to send link button.', 'error' => $e->getMessage()], 422);
        }
    }

    public function sendContactCard(Request $request, WhatsAppConversation $conversation)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($conversation->account_id, $account->id)) {
            abort(404);
        }

        $validated = $request->validate([
            'formatted_name' => 'required|string|max:100',
            'phone' => 'required|string|max:32',
            'email' => 'nullable|email|max:128',
        ]);

        $this->ensureCustomerServiceWindowOpen($conversation);

        $this->entitlementService->assertWithinLimit($account, 'messages_monthly', 1);
        $conversation->load(['connection', 'contact']);

        $contactCard = [
            'name' => [
                'formatted_name' => $validated['formatted_name'],
                'first_name' => Str::of($validated['formatted_name'])->before(' ')->toString() ?: $validated['formatted_name'],
            ],
            'phones' => [[
                'phone' => $validated['phone'],
                'type' => 'WORK',
            ]],
        ];

        if (! empty($validated['email'])) {
            $contactCard['emails'] = [[
                'email' => $validated['email'],
                'type' => 'WORK',
            ]];
        }

        $message = WhatsAppMessage::lockForUpdate()->create([
            'account_id' => $account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'contacts',
            'text_body' => 'Contact card: '.$validated['formatted_name'],
            'payload' => ['contacts' => [$contactCard]],
            'status' => 'queued',
        ]);

        $message->load('conversation.contact');
        event(new MessageCreated($message));

        try {
            $response = $this->whatsappClient->sendContactsMessage(
                $conversation->connection,
                $conversation->contact->wa_id,
                [$contactCard]
            );

            $message->update([
                'meta_message_id' => $response['messages'][0]['id'] ?? null,
                'status' => 'sent',
                'sent_at' => now(),
                'payload' => array_merge($message->payload ?? [], ['response' => $response]),
            ]);

            $this->usageService->incrementMessages($account, 1);
            $conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => 'Contact card: '.substr($validated['formatted_name'], 0, 80),
            ]);
            $this->touchContactAfterOutbound($conversation);
            event(new MessageUpdated($message));
            event(new ConversationUpdated($conversation));

            return response()->json(['data' => ['message' => $this->messageResponsePayload($message)]], 201);
        } catch (\Exception $e) {
            $message->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            event(new MessageUpdated($message));

            return response()->json(['message' => 'Failed to send contact card.', 'error' => $e->getMessage()], 422);
        }
    }

    public function sendProduct(Request $request, WhatsAppConversation $conversation)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($conversation->account_id, $account->id)) {
            abort(404);
        }

        $validated = $request->validate([
            'product_id' => 'nullable|integer',
            'mode' => 'nullable|string|in:single,multi',
            'catalog_id' => 'required_without:product_id|nullable|string|max:120',
            'product_retailer_id' => 'required_without:product_id|nullable|string|max:120',
            'body_text' => 'nullable|string|max:1024',
            'header_text' => 'nullable|string|max:60',
            'footer_text' => 'nullable|string|max:60',
            'sections' => 'required_if:mode,multi|nullable|array|min:1|max:10',
            'sections.*.title' => 'nullable|string|max:24',
            'sections.*.product_items' => 'required_with:sections|array|min:1|max:30',
            'sections.*.product_items.*.product_retailer_id' => 'required|string|max:120',
        ]);

        $this->ensureCustomerServiceWindowOpen($conversation);

        $this->entitlementService->assertWithinLimit($account, 'messages_monthly', 1);
        $conversation->load(['connection', 'contact']);

        $mode = $validated['mode'] ?? 'single';
        $selectedProduct = null;
        if (! empty($validated['product_id']) && $mode === 'single') {
            $selectedProduct = AccountCatalogProduct::where('account_id', $account->id)
                ->where('status', 'active')
                ->findOrFail((int) $validated['product_id']);
            $metadata = is_array($selectedProduct->metadata) ? $selectedProduct->metadata : [];
            $catalogId = (string) ($metadata['catalog_id'] ?? '');
            $retailerId = (string) ($metadata['product_retailer_id'] ?? $metadata['retailer_id'] ?? $selectedProduct->sku ?? '');
            if ($catalogId === '' || $retailerId === '') {
                throw ValidationException::withMessages([
                    'product_id' => 'This saved product is missing Meta catalog ID or product retailer ID. Sync it from Meta Catalog again.',
                ]);
            }
            $validated['catalog_id'] = $catalogId;
            $validated['product_retailer_id'] = $retailerId;
        }

        $preview = $validated['body_text'] ?: ($mode === 'single' ? 'Product shared' : 'Products shared');

        $message = WhatsAppMessage::lockForUpdate()->create([
            'account_id' => $account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'interactive',
            'text_body' => $preview,
            'payload' => [
                'interactive_type' => $mode === 'single' ? 'product' : 'product_list',
                'catalog_id' => $validated['catalog_id'],
                'product_retailer_id' => $validated['product_retailer_id'] ?? null,
                'product_id' => $selectedProduct?->id,
                'sections' => $validated['sections'] ?? null,
                'header_text' => $validated['header_text'] ?? null,
                'footer_text' => $validated['footer_text'] ?? null,
            ],
            'status' => 'queued',
        ]);

        $message->load('conversation.contact');
        event(new MessageCreated($message));

        try {
            $response = $mode === 'multi'
                ? $this->whatsappClient->sendProductListMessage(
                    $conversation->connection,
                    $conversation->contact->wa_id,
                    $validated['catalog_id'],
                    $validated['sections'] ?? [],
                    $validated['body_text'] ?: 'Please choose a product.',
                    $validated['header_text'] ?? null,
                    $validated['footer_text'] ?? null
                )
                : $this->whatsappClient->sendProductMessage(
                    $conversation->connection,
                    $conversation->contact->wa_id,
                    $validated['catalog_id'],
                    $validated['product_retailer_id'],
                    $validated['body_text'] ?? null,
                    $validated['footer_text'] ?? null
                );

            $message->update([
                'meta_message_id' => $response['messages'][0]['id'] ?? null,
                'status' => 'sent',
                'sent_at' => now(),
                'payload' => array_merge($message->payload ?? [], ['response' => $response]),
            ]);

            $this->usageService->incrementMessages($account, 1);
            $conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => substr($preview, 0, 100),
            ]);
            $this->touchContactAfterOutbound($conversation);
            event(new MessageUpdated($message));
            event(new ConversationUpdated($conversation));

            return response()->json(['data' => ['message' => $this->messageResponsePayload($message)]], 201);
        } catch (\Exception $e) {
            $message->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            event(new MessageUpdated($message));

            return response()->json(['message' => 'Failed to send product message.', 'error' => $e->getMessage()], 422);
        }
    }

    protected function touchContactAfterOutbound(WhatsAppConversation $conversation): void
    {
        if (! $conversation->relationLoaded('contact')) {
            $conversation->load('contact');
        }

        $contact = $conversation->contact;
        if (! $contact) {
            return;
        }

        // message_count is bumped by the WhatsAppMessage "created" hook, so retries are not double counted.
        $contact->forceFill([
            'last_contacted_at' => now(),
        ])->save();
    }

    protected function recordHumanReplyCooldown(WhatsAppConversation $conversation, ?User $user): void
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $metadata['last_human_reply_at'] = now()->toIso8601String();
        $metadata['last_human_reply_by'] = $user?->id;

        $conversation->forceFill(['metadata' => $metadata])->save();
    }

    protected function catalogProductsForInbox($account): array
    {
        if (! Schema::hasTable('account_catalog_products')) {
            return [];
        }

        return AccountCatalogProduct::where('account_id', $account->id)
            ->where('status', 'active')
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get(['id', 'name', 'sku', 'price', 'currency', 'image_url', 'metadata'])
            ->map(function (AccountCatalogProduct $product) {
                $metadata = is_array($product->metadata) ? $product->metadata : [];
                $payload = is_array($metadata['payload'] ?? null) ? $metadata['payload'] : [];
                $retailerId = $metadata['product_retailer_id']
                    ?? $metadata['retailer_id']
                    ?? $payload['retailer_id']
                    ?? $payload['product_retailer_id']
                    ?? $product->sku;
                $catalogId = $metadata['catalog_id'] ?? $payload['catalog_id'] ?? null;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'catalog_id' => $catalogId,
                    'retailer_id' => $retailerId,
                    'price' => $product->price,
                    'currency' => $product->currency ?: 'INR',
                    'image_url' => $product->image_url,
                    'source' => $metadata['source'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    protected function savedListsForInbox($account): array
    {
        if (! Schema::hasTable('whatsapp_lists')) {
            return [];
        }

        return WhatsAppList::where('account_id', $account->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'whatsapp_connection_id', 'name', 'button_text', 'description', 'footer_text', 'sections'])
            ->map(fn (WhatsAppList $list) => [
                'id' => $list->id,
                'connection_id' => $list->whatsapp_connection_id,
                'name' => $list->name,
                'button_text' => $list->button_text,
                'description' => $list->description,
                'footer_text' => $list->footer_text,
                'sections' => $list->sections ?: [],
            ])
            ->values()
            ->all();
    }

    protected function savedButtonsForInbox($account): array
    {
        if (! Schema::hasTable('quick_replies')) {
            return [];
        }

        return QuickReply::where('account_id', $account->id)
            ->where('is_active', true)
            ->where('type', 'button')
            ->orderByDesc('usage_count')
            ->orderBy('label')
            ->limit(30)
            ->get(['id', 'type', 'label', 'shortcut', 'message'])
            ->map(fn (QuickReply $reply) => [
                'id' => $reply->id,
                'type' => $reply->type ?: 'button',
                'label' => $reply->label,
                'shortcut' => $reply->shortcut,
                'message' => $reply->message,
                'button_text' => Str::limit($reply->label, 20, ''),
            ])
            ->values()
            ->all();
    }

    protected function savedFormsForInbox($account): array
    {
        if (! Schema::hasTable('whatsapp_flows')) {
            return [];
        }

        return WhatsAppFlow::where('account_id', $account->id)
            ->whereNotNull('meta_flow_id')
            ->where('status', 'published')
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'whatsapp_connection_id', 'meta_flow_id', 'name', 'status', 'category'])
            ->map(fn (WhatsAppFlow $flow) => [
                'id' => $flow->id,
                'connection_id' => $flow->whatsapp_connection_id,
                'meta_flow_id' => $flow->meta_flow_id,
                'name' => $flow->name,
                'status' => $flow->status,
                'category' => $flow->category,
            ])
            ->values()
            ->all();
    }

    protected function ensureCustomerServiceWindowOpen(WhatsAppConversation $conversation): void
    {
        $lastInboundAt = WhatsAppMessage::where('account_id', $conversation->account_id)
            ->where('whatsapp_conversation_id', $conversation->id)
            ->where('direction', 'inbound')
            ->latest('created_at')
            ->first()?->created_at;

        if (! $lastInboundAt || $lastInboundAt->lte(now()->subHours(24))) {
            throw ValidationException::withMessages([
                'message' => 'The 24-hour WhatsApp customer service window is closed. Send an approved template to re-open the conversation.',
            ]);
        }
    }

    protected function messageResponsePayload(WhatsAppMessage $message): array
    {
        return [
            'id' => $message->id,
            'direction' => $message->direction,
            'type' => $message->type,
            'text_body' => $message->text_body,
            'payload' => $message->payload,
            'status' => $message->status,
            'meta_message_id' => $message->meta_message_id,
            'created_at' => $message->created_at->toIso8601String(),
            'updated_at' => $message->updated_at?->toIso8601String(),
            'sent_at' => $message->sent_at?->toIso8601String(),
            'delivered_at' => $message->delivered_at?->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
            'error_message' => $message->status === 'failed' ? \App\Support\CustomerMessage::humanize($message->error_message ?: 'This message could not be delivered.', 'This message could not be delivered.') : null,
        ];
    }
}
