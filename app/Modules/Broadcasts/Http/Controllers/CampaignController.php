<?php

namespace App\Modules\Broadcasts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Broadcasts\Jobs\SendScheduledCampaignJob;
use App\Modules\Broadcasts\Models\Campaign;
use App\Modules\Broadcasts\Services\CampaignService;
use App\Modules\Contacts\Models\ContactSegment;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppTemplate;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public const MAX_SELECTED_CONTACTS = 5000;

    public function __construct(
        protected CampaignService $campaignService
    ) {}

    /**
     * Display a listing of campaigns.
     */
    public function index(Request $request): Response
    {
        $account = $request->attributes->get('account') ?? current_account();

        $query = Campaign::where('account_id', $account->id)
            ->with(['connection', 'template', 'creator'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = trim((string) $request->string('search'));
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $campaigns = $query->paginate(20)->withQueryString()->through(function ($campaign) {
            $totalRecipients = max(0, (int) $campaign->total_recipients);
            $sentCount = max(0, (int) $campaign->sent_count);
            $deliveredCount = min($totalRecipients, max(0, (int) $campaign->delivered_count));
            $readCount = min($deliveredCount, max(0, (int) $campaign->read_count));
            $failedCount = max(0, (int) $campaign->failed_count);

            return [
                'id' => $campaign->id,
                'slug' => $campaign->slug,
                'name' => $campaign->name,
                'description' => $campaign->description,
                'status' => $campaign->status,
                'type' => $campaign->type,
                'total_recipients' => $totalRecipients,
                'sent_count' => $sentCount,
                'delivered_count' => $deliveredCount,
                'read_count' => $readCount,
                'failed_count' => $failedCount,
                'completion_percentage' => $campaign->completion_percentage,
                'scheduled_at' => $campaign->scheduled_at?->toIso8601String(),
                'started_at' => $campaign->started_at?->toIso8601String(),
                'completed_at' => $campaign->completed_at?->toIso8601String(),
                'connection' => $campaign->connection ? [
                    'id' => $campaign->connection->id,
                    'name' => $campaign->connection->name] : null,
                'template' => $campaign->template ? [
                    'id' => $campaign->template->id,
                    'name' => $campaign->template->name] : null,
                'created_by' => $campaign->creator ? [
                    'id' => $campaign->creator->id,
                    'name' => $campaign->creator->name] : null,
                'created_at' => $campaign->created_at->toIso8601String()];
        });

        return Inertia::render('Broadcasts/Index', [
            'account' => $account,
            'campaigns' => $campaigns,
            'filters' => [
                'status' => $request->status,
                'search' => (string) $request->string('search'),
            ],
            'createOptions' => array_merge($this->campaignCreateOptions($account), [
                'selectedContacts' => $this->selectedContactsFromQuery($request, $account),
            ]),
            'selectedCampaign' => $this->selectedCampaignPayload($request, $account),
        ]);
    }

    protected function selectedCampaignPayload(Request $request, $account): ?array
    {
        $selected = $request->query('campaign');
        if (! $selected) {
            return null;
        }

        $campaign = Campaign::where('account_id', $account->id)
            ->where(function ($query) use ($selected) {
                $query->where('slug', $selected);
                if (is_numeric($selected)) {
                    $query->orWhere('id', (int) $selected);
                }
            })
            ->with(['connection', 'template', 'creator'])
            ->first();

        if (! $campaign) {
            return null;
        }

        $recipients = $campaign->recipients()
            ->with('contact:id,name,wa_id,phone')
            ->orderByRaw("CASE status WHEN 'failed' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn ($recipient) => [
                'id' => $recipient->id,
                'name' => $recipient->name ?: $recipient->contact?->name,
                'phone_number' => $recipient->phone_number ?: $recipient->contact?->wa_id ?: $recipient->contact?->phone,
                'status' => $recipient->status,
                'sent_at' => $recipient->sent_at?->toIso8601String(),
                'delivered_at' => $recipient->delivered_at?->toIso8601String(),
                'read_at' => $recipient->read_at?->toIso8601String(),
                'failed_at' => $recipient->failed_at?->toIso8601String(),
                'failure_reason' => $recipient->failure_reason,
                'message_id' => $recipient->message_id ?: $recipient->wamid,
                'timeline' => collect([
                    ['label' => 'Prepared', 'at' => $recipient->created_at?->toIso8601String(), 'status' => 'complete'],
                    ['label' => 'Sent', 'at' => $recipient->sent_at?->toIso8601String(), 'status' => $recipient->sent_at ? 'complete' : 'pending'],
                    ['label' => 'Delivered', 'at' => $recipient->delivered_at?->toIso8601String(), 'status' => $recipient->delivered_at ? 'complete' : 'pending'],
                    ['label' => 'Read', 'at' => $recipient->read_at?->toIso8601String(), 'status' => $recipient->read_at ? 'complete' : 'pending'],
                    ['label' => 'Failed', 'at' => $recipient->failed_at?->toIso8601String(), 'status' => $recipient->failed_at ? 'failed' : 'pending'],
                ])->filter(fn ($item) => $item['status'] !== 'pending' || in_array($item['label'], ['Sent', 'Delivered', 'Read'], true))->values(),
            ]);

        try {
            $preflight = $this->campaignService->runPreflightChecks($campaign);
        } catch (\Throwable $e) {
            $preflight = [
                'ok' => false,
                'errors' => [$e->getMessage()],
                'warnings' => [],
            ];
        }

        return [
            'id' => $campaign->id,
            'slug' => $campaign->slug,
            'name' => $campaign->name,
            'description' => $campaign->description,
            'status' => $campaign->status,
            'type' => $campaign->type,
            'recipient_type' => $campaign->recipient_type === 'contacts' && is_array(data_get($campaign->recipient_filters, 'contact_ids'))
                ? 'selected'
                : $campaign->recipient_type,
            'message_text' => $campaign->message_text,
            'media_url' => $campaign->media_url,
            'media_type' => $campaign->media_type,
            'template_params' => $campaign->template_params ?? [],
            'send_delay_seconds' => $campaign->send_delay_seconds,
            'respect_opt_out' => (bool) $campaign->respect_opt_out,
            'dry_run' => (bool) data_get($campaign->metadata, 'dry_run', false),
            'recipient_sample_size' => (int) data_get($campaign->metadata, 'recipient_sample_size', 0),
            'tracking' => [
                'source' => data_get($campaign->metadata, 'tracking.source'),
                'ctwa_ad_id' => data_get($campaign->metadata, 'tracking.ctwa_ad_id'),
                'ctwa_post_id' => data_get($campaign->metadata, 'tracking.ctwa_post_id'),
                'utm_source' => data_get($campaign->metadata, 'tracking.utm_source'),
                'utm_medium' => data_get($campaign->metadata, 'tracking.utm_medium'),
                'utm_campaign' => data_get($campaign->metadata, 'tracking.utm_campaign'),
                'ab_test_enabled' => (bool) data_get($campaign->metadata, 'tracking.ab_test_enabled', false),
                'ab_variant' => data_get($campaign->metadata, 'tracking.ab_variant'),
                'retargeting_basis' => data_get($campaign->metadata, 'tracking.retargeting_basis'),
            ],
            'scheduled_at' => $campaign->scheduled_at?->toIso8601String(),
            'started_at' => $campaign->started_at?->toIso8601String(),
            'completed_at' => $campaign->completed_at?->toIso8601String(),
            'created_at' => $campaign->created_at?->toIso8601String(),
            'connection' => $campaign->connection ? [
                'id' => $campaign->connection->id,
                'name' => $campaign->connection->name,
                'phone_number_id' => $campaign->connection->phone_number_id,
            ] : null,
            'template' => $campaign->template ? [
                'id' => $campaign->template->id,
                'name' => $campaign->template->name,
                'language' => $campaign->template->language,
                'category' => $campaign->template->category,
                'body_text' => $campaign->template->body_text,
            ] : null,
            'created_by' => $campaign->creator ? [
                'id' => $campaign->creator->id,
                'name' => $campaign->creator->name,
            ] : null,
            'stats' => [
                'total_recipients' => $campaign->total_recipients,
                'sent_count' => max(0, (int) $campaign->sent_count),
                'delivered_count' => min(max(0, (int) $campaign->total_recipients), max(0, (int) $campaign->delivered_count)),
                'read_count' => min(min(max(0, (int) $campaign->total_recipients), max(0, (int) $campaign->delivered_count)), max(0, (int) $campaign->read_count)),
                'failed_count' => max(0, (int) $campaign->failed_count),
                'pending_count' => max(0, (int) $campaign->total_recipients - (int) $campaign->sent_count - (int) $campaign->failed_count),
                'completion_percentage' => $campaign->completion_percentage,
                'delivery_rate' => $campaign->delivery_rate,
                'read_rate' => $campaign->read_rate,
            ],
            'diagnostics' => [
                'preflight' => $preflight,
                'queue' => [
                    'pending_recipients' => $campaign->recipients()->where('status', 'pending')->count(),
                    'sending_recipients' => $campaign->recipients()->where('status', 'sending')->count(),
                    'failed_recipients' => $campaign->recipients()->where('status', 'failed')->count(),
                    'oldest_pending_at' => $campaign->recipients()->where('status', 'pending')->min('created_at'),
                ],
                'connection_backoff_until' => data_get($campaign->connection?->metadata, 'campaign_backoff_until'),
            ],
            'recipients' => $recipients,
            'testTargetPhone' => $request->user()?->phone,
        ];
    }

    /**
     * Contacts picked on the Contacts page ("Send campaign" bulk action), passed as
     * ?contacts=1,2,3. Only contacts in this workspace are returned.
     */
    protected function selectedContactsFromQuery(Request $request, $account): array
    {
        $ids = $this->parseContactIds($request->query('contacts'));
        if ($ids === []) {
            return [];
        }

        return WhatsAppContact::where('account_id', $account->id)
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'wa_id', 'phone', 'status'])
            ->map(fn ($contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'wa_id' => $contact->wa_id,
                'phone' => $contact->phone,
                'status' => $contact->status,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    protected function parseContactIds(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($id) => is_numeric($id) ? (int) $id : 0)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->take(self::MAX_SELECTED_CONTACTS)
            ->values()
            ->all();
    }

    protected function campaignCreateOptions($account): array
    {
        $connections = WhatsAppConnection::where('account_id', $account->id)
            ->where('is_active', true)
            ->get(['id', 'name', 'phone_number_id']);

        $templates = WhatsAppTemplate::where('account_id', $account->id)
            ->whereRaw('LOWER(TRIM(status)) = ?', ['approved'])
            ->where(function ($query) {
                $query->where('is_archived', false)
                    ->orWhereNull('is_archived');
            })
            ->get(['id', 'name', 'language', 'category', 'body_text', 'whatsapp_connection_id']);

        $contacts = WhatsAppContact::where('account_id', $account->id)
            ->whereNotIn('status', ['blocked', 'opt_out'])
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'wa_id', 'phone', 'status']);

        $segments = ContactSegment::where('account_id', $account->id)
            ->orderBy('name')
            ->get(['id', 'name', 'contact_count']);

        return [
            'connections' => $connections,
            'templates' => $templates->map(fn ($template) => [
                'id' => $template->id,
                'name' => $template->name,
                'language' => $template->language,
                'category' => $template->category,
                'body_text' => $template->body_text,
                'connection_id' => $template->whatsapp_connection_id,
            ]),
            'contactsCount' => WhatsAppContact::where('account_id', $account->id)->count(),
            'contacts' => $contacts,
            'segments' => $segments,
        ];
    }

    /**
     * Show the form for creating a new campaign.
     */
    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('app.broadcasts.index', ['panel' => 'create']);
    }

    /**
     * Store a newly created campaign.
     */
    public function store(Request $request)
    {
        $account = $request->attributes->get('account') ?? current_account();

        // Normalize optional fields so empty strings don't fail url/exists rules
        $request->merge([
            'whatsapp_template_id' => $request->input('type') === 'template' ? ($request->input('whatsapp_template_id') ?: null) : null,
            'media_url' => $request->input('type') === 'media' ? ($request->input('media_url') ?: null) : null,
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:template,text,media',
            'whatsapp_connection_id' => [
                'required',
                Rule::exists('whatsapp_connections', 'id')->where('account_id', $account->id),
            ],
            'whatsapp_template_id' => [
                'required_if:type,template',
                'nullable',
                Rule::exists('whatsapp_templates', 'id')->where(function (Builder $query) use ($account) {
                    $query->where('account_id', $account->id)
                        ->whereRaw('LOWER(TRIM(status)) = ?', ['approved'])
                        ->where(function ($inner) {
                            $inner->where('is_archived', false)
                                ->orWhereNull('is_archived');
                        });
                }),
            ],
            'template_params' => 'nullable|array',
            'message_text' => 'required_if:type,text|nullable|string',
            'media_url' => ['nullable', 'required_if:type,media', 'url'],
            'media_type' => 'required_if:type,media|nullable|in:image,video,document,audio',
            'recipient_type' => 'required|in:contacts,custom,segment',
            'recipient_filters' => 'nullable|array',
            'recipient_filters.segment_ids' => 'required_if:recipient_type,segment|nullable|array|min:1',
            'recipient_filters.segment_ids.*' => 'integer|exists:contact_segments,id',
            'recipient_filters.contact_ids' => 'nullable|array|max:'.self::MAX_SELECTED_CONTACTS,
            'recipient_filters.contact_ids.*' => 'integer',
            'custom_recipients' => 'required_if:recipient_type,custom|nullable|array',
            'custom_recipients.*.phone' => 'nullable|string',
            'custom_recipients.*.name' => 'nullable|string',
            'scheduled_at' => 'nullable|date|after:now',
            'send_delay_seconds' => 'nullable|integer|min:0|max:3600',
            'respect_opt_out' => 'boolean',
            'dry_run' => 'nullable|boolean',
            'recipient_sample_size' => 'nullable|integer|min:0|max:100000',
            'tracking' => 'nullable|array',
            'tracking.source' => 'nullable|string|max:100',
            'tracking.ctwa_ad_id' => 'nullable|string|max:120',
            'tracking.ctwa_post_id' => 'nullable|string|max:120',
            'tracking.utm_source' => 'nullable|string|max:120',
            'tracking.utm_medium' => 'nullable|string|max:120',
            'tracking.utm_campaign' => 'nullable|string|max:160',
            'tracking.ab_test_enabled' => 'nullable|boolean',
            'tracking.ab_variant' => 'nullable|string|max:80',
            'tracking.retargeting_basis' => 'nullable|string|max:160']);

        // "Selected contacts" audience: send only to the picked contacts in this workspace.
        $filters = $validated['recipient_filters'] ?? null;
        if (is_array($filters) && array_key_exists('contact_ids', $filters)) {
            if ($validated['recipient_type'] !== 'contacts') {
                unset($filters['contact_ids']);
            } else {
                $requested = $this->parseContactIds($filters['contact_ids'] ?? []);
                $owned = $requested === [] ? [] : WhatsAppContact::where('account_id', $account->id)
                    ->whereIn('id', $requested)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values()
                    ->all();

                if ($owned === []) {
                    return back()->withErrors(['recipient_filters' => 'None of the selected contacts were found. Pick contacts again.'])->withInput();
                }

                $filters = ['contact_ids' => $owned];
            }
            $validated['recipient_filters'] = $filters ?: null;
        }

        // When using custom recipients, require at least one with a phone number
        if ($validated['recipient_type'] === 'custom') {
            $withPhone = array_values(array_filter($validated['custom_recipients'] ?? [], function ($r) {
                return ! empty(trim((string) ($r['phone'] ?? '')));
            }));
            if (count($withPhone) === 0) {
                return back()->withErrors(['custom_recipients' => 'Add at least one recipient with a phone number.'])->withInput();
            }
            $validated['custom_recipients'] = $withPhone;
        } else {
            $validated['custom_recipients'] = null;
        }

        // Ensure selected template belongs to selected connection.
        if (($validated['type'] ?? null) === 'template' && ! empty($validated['whatsapp_template_id'])) {
            $templateBelongsToConnection = WhatsAppTemplate::where('id', $validated['whatsapp_template_id'])
                ->where('account_id', $account->id)
                ->whereRaw('LOWER(TRIM(status)) = ?', ['approved'])
                ->where(function ($query) {
                    $query->where('is_archived', false)
                        ->orWhereNull('is_archived');
                })
                ->where(function ($query) use ($validated) {
                    $query->where('whatsapp_connection_id', $validated['whatsapp_connection_id'])
                        // Some synced templates may miss connection_id.
                        ->orWhereNull('whatsapp_connection_id');
                })
                ->exists();

            if (! $templateBelongsToConnection) {
                return back()->withErrors([
                    'whatsapp_template_id' => 'Selected template is not available for the selected connection.',
                ])->withInput();
            }
        }

        try {
            DB::beginTransaction();

            $campaign = Campaign::create([
                'account_id' => $account->id,
                'whatsapp_connection_id' => $validated['whatsapp_connection_id'],
                'whatsapp_template_id' => $validated['whatsapp_template_id'] ?? null,
                'created_by' => $request->user()->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'type' => $validated['type'],
                'status' => ($validated['scheduled_at'] ?? null) ? 'scheduled' : 'draft',
                'template_params' => $validated['template_params'] ?? null,
                'message_text' => $validated['message_text'] ?? null,
                'media_url' => $validated['media_url'] ?? null,
                'media_type' => $validated['media_type'] ?? null,
                'scheduled_at' => ($validated['scheduled_at'] ?? null) ? new \DateTime($validated['scheduled_at']) : null,
                'recipient_type' => $validated['recipient_type'],
                'recipient_filters' => $validated['recipient_filters'] ?? null,
                'custom_recipients' => $validated['custom_recipients'] ?? null,
                'send_delay_seconds' => $validated['send_delay_seconds'] ?? 0,
                'respect_opt_out' => $validated['respect_opt_out'] ?? true,
                'metadata' => [
                    'dry_run' => (bool) ($validated['dry_run'] ?? false),
                    'recipient_sample_size' => max(0, (int) ($validated['recipient_sample_size'] ?? 0)),
                    'tracking' => array_filter([
                        'source' => $validated['tracking']['source'] ?? null,
                        'ctwa_ad_id' => $validated['tracking']['ctwa_ad_id'] ?? null,
                        'ctwa_post_id' => $validated['tracking']['ctwa_post_id'] ?? null,
                        'utm_source' => $validated['tracking']['utm_source'] ?? null,
                        'utm_medium' => $validated['tracking']['utm_medium'] ?? null,
                        'utm_campaign' => $validated['tracking']['utm_campaign'] ?? null,
                        'ab_test_enabled' => (bool) ($validated['tracking']['ab_test_enabled'] ?? false),
                        'ab_variant' => $validated['tracking']['ab_variant'] ?? null,
                        'retargeting_basis' => $validated['tracking']['retargeting_basis'] ?? null,
                    ], fn ($value) => $value !== null && $value !== ''),
                ]]);

            // Prepare recipients
            $this->campaignService->prepareRecipients($campaign);

            // Schedule campaign if scheduled_at is set
            if ($campaign->scheduled_at) {
                SendScheduledCampaignJob::dispatch($campaign->id)
                    ->delay($campaign->scheduled_at);
            }

            DB::commit();

            return redirect()->route('app.broadcasts.index', [
                'campaign' => $campaign->slug])->with('success', 'Campaign created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create campaign', [
                'account_id' => $account->id,
                'error' => $e->getMessage()]);

            return back()->withErrors([
                'error' => 'Failed to create campaign: '.$e->getMessage()])->withInput();
        }
    }

    /**
     * Display the specified campaign.
     */
    public function show(Request $request, Campaign $campaign): RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($campaign->account_id, $account->id)) {
            abort(404);
        }

        return redirect()->route('app.broadcasts.index', ['campaign' => $campaign->slug]);
    }

    public function duplicate(Request $request, Campaign $campaign)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($campaign->account_id, $account->id)) {
            abort(404);
        }

        try {
            $copy = $this->campaignService->duplicateCampaign($campaign, (int) $request->user()->id);

            return redirect()->route('app.broadcasts.index', ['campaign' => $copy->slug])
                ->with('success', 'Campaign duplicated successfully.');
        } catch (\Throwable $e) {
            Log::error('Failed to duplicate campaign', [
                'campaign_id' => $campaign->id,
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Failed to duplicate campaign: '.$e->getMessage(),
            ]);
        }
    }

    public function retryFailed(Request $request, Campaign $campaign)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($campaign->account_id, $account->id)) {
            abort(404);
        }

        if (! in_array($campaign->status, ['sending', 'completed', 'paused', 'cancelled'], true)) {
            return back()->withErrors([
                'error' => 'Failed recipients can only be retried after campaign execution starts.',
            ]);
        }

        try {
            $retried = $this->campaignService->retryFailedRecipients($campaign);
            if ($retried === 0) {
                return back()->with('success', 'No failed recipients to retry.');
            }

            return back()->with('success', "Queued {$retried} failed recipients for retry.");
        } catch (\Throwable $e) {
            Log::error('Failed to retry campaign recipients', [
                'campaign_id' => $campaign->id,
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Failed to retry recipients: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Start a campaign.
     */
    public function start(Request $request, Campaign $campaign)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($campaign->account_id, $account->id)) {
            abort(404);
        }

        if (! $campaign->canStart()) {
            return back()->withErrors([
                'error' => 'Campaign cannot be started in its current state.']);
        }

        try {
            $this->campaignService->startCampaign($campaign);

            return back()->with('success', 'Campaign started successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to start campaign', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage()]);

            return back()->withErrors([
                'error' => 'Failed to start campaign: '.$e->getMessage()]);
        }
    }

    public function sendTest(Request $request, Campaign $campaign)
    {
        $account = $request->attributes->get('account') ?? current_account();
        if (! account_ids_match($campaign->account_id, $account->id)) {
            abort(404);
        }

        $validated = $request->validate([
            'phone' => 'nullable|string|max:30',
        ]);

        $target = trim((string) ($validated['phone'] ?? $request->user()?->phone ?? ''));
        $digits = preg_replace('/\D+/', '', $target);
        if ($digits === '') {
            return back()->withErrors(['error' => 'Add a valid phone number in your profile or send-test form first.']);
        }

        try {
            $response = $this->campaignService->sendTestMessage($campaign, $digits);
            $messageId = $response['messages'][0]['id'] ?? null;

            return back()->with('success', 'Test message sent. Check the phone you sent it to.');
        } catch (\Throwable $e) {
            Log::warning('Campaign test send failed', [
                'campaign_id' => $campaign->id,
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Test send failed: '.$e->getMessage()]);
        }
    }

    /**
     * Pause a campaign.
     */
    public function pause(Request $request, Campaign $campaign)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($campaign->account_id, $account->id)) {
            abort(404);
        }

        if ($campaign->status !== 'sending') {
            return back()->withErrors([
                'error' => 'Only active campaigns can be paused.']);
        }

        $campaign->update(['status' => 'paused']);

        return back()->with('success', 'Campaign paused successfully.');
    }

    /**
     * Cancel a campaign.
     */
    public function cancel(Request $request, Campaign $campaign)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($campaign->account_id, $account->id)) {
            abort(404);
        }

        if (! in_array($campaign->status, ['draft', 'scheduled', 'sending', 'paused'])) {
            return back()->withErrors([
                'error' => 'Campaign cannot be cancelled in its current state.']);
        }

        $campaign->update(['status' => 'cancelled']);

        return back()->with('success', 'Campaign cancelled successfully.');
    }

    public function destroy(Request $request, Campaign $campaign)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($campaign->account_id, $account->id)) {
            abort(404);
        }

        if (! in_array($campaign->status, ['draft', 'cancelled', 'completed'], true)) {
            return back()->withErrors([
                'error' => 'Only draft, cancelled, or completed campaigns can be deleted.',
            ]);
        }

        $recoveryDays = max(1, (int) \App\Models\PlatformSetting::get('compliance.recovery_window_days', 30));
        $campaign->purge_after_at = now()->addDays($recoveryDays);
        $campaign->save();
        $campaign->delete();

        return redirect()->route('app.broadcasts.index')
            ->with('success', "Campaign moved to recovery bin for {$recoveryDays} days.");
    }
}
