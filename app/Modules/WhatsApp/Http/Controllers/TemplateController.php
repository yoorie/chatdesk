<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppTemplate;
use App\Modules\WhatsApp\Services\TemplateManagementService;
use App\Modules\WhatsApp\Support\PreapprovedTemplateLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    public function __construct(
        protected TemplateManagementService $templateManagementService
    ) {}

    /**
     * Display a listing of templates.
     */
    public function index(Request $request): Response
    {
        $account = $request->attributes->get('account') ?? current_account();

        $query = WhatsAppTemplate::where('account_id', $account->id)
            ->with('connection')
            ->where(function ($q) {
                // Imported rows may have NULL is_archived.
                $q->where('is_archived', false)
                    ->orWhereNull('is_archived');
            });

        // Filters
        if ($request->has('connection') && $request->connection) {
            $query->where('whatsapp_connection_id', $request->connection);
        }

        if ($request->has('status') && $request->status) {
            $query->whereRaw('LOWER(TRIM(status)) = ?', [strtolower(trim((string) $request->status))]);
        }

        if ($request->has('category') && $request->category) {
            $query->where('category', $request->category);
        }

        if ($request->has('language') && $request->language) {
            $query->where('language', $request->language);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('body_text', 'like', "%{$search}%");
            });
        }

        $serializeTemplate = function ($template) {
            $sendStats = $template->sends()
                ->selectRaw("status, count(*) as total")
                ->groupBy('status')
                ->pluck('total', 'status');

            return [
                'id' => $template->id,
                'slug' => $template->slug,
                'name' => $template->name,
                'language' => $template->language,
                'category' => $template->category,
                'status' => $template->status,
                'body_text' => $template->body_text,
                'header_type' => $template->header_type ?? 'NONE',
                'header_text' => $template->header_text,
                'header_media_url' => $template->header_media_url ?? null,
                'footer_text' => $template->footer_text,
                'buttons' => $template->buttons ?? [],
                'has_buttons' => $template->has_buttons,
                'variable_count' => $template->variable_count,
                'quality_score' => $template->quality_score,
                'rejection_reason' => $template->last_meta_error,
                'stats' => [
                    'sent' => (int) ($sendStats['sent'] ?? 0),
                    'delivered' => (int) ($sendStats['delivered'] ?? 0),
                    'read' => (int) ($sendStats['read'] ?? 0),
                    'failed' => (int) ($sendStats['failed'] ?? 0),
                ],
                'connection' => [
                    'id' => $template->connection->id,
                    'name' => $template->connection->name],
                'last_synced_at' => $template->last_synced_at?->toIso8601String()];
        };

        $templates = $query->orderBy('name')
            ->orderBy('language')
            ->paginate(20)
            ->through($serializeTemplate);

        $selectedTemplate = null;
        if ($request->filled('use_template') || $request->filled('template')) {
            $selected = WhatsAppTemplate::where('account_id', $account->id)
                ->where('slug', $request->query('use_template') ?: $request->query('template'))
                ->with('connection')
                ->first();
            $selectedTemplate = $selected ? $serializeTemplate($selected) : null;
        }

        $connectionColumns = ['id', 'name'];
        if (Schema::hasColumn('whatsapp_connections', 'last_synced_at')) {
            $connectionColumns[] = 'last_synced_at';
        }
        if (Schema::hasColumn('whatsapp_connections', 'last_meta_error')) {
            $connectionColumns[] = 'last_meta_error';
        }

        $connections = WhatsAppConnection::where('account_id', $account->id)
            ->where('is_active', true)
            ->get($connectionColumns)
            ->map(function ($connection) {
                return [
                    'id' => $connection->id,
                    'name' => $connection->name,
                    'last_synced_at' => $connection->last_synced_at?->toIso8601String(),
                    'last_sync_error' => $connection->last_meta_error,
                ];
            });

        return Inertia::render('WhatsApp/Templates/Index', [
            'account' => $account,
            'templates' => $templates,
            'connections' => $connections,
            'selected_template' => $selectedTemplate,
            'contacts' => WhatsAppContact::where('account_id', $account->id)
                ->orderBy('name')
                ->limit(250)
                ->get(['id', 'wa_id', 'name'])
                ->map(fn ($contact) => [
                    'id' => $contact->id,
                    'wa_id' => $contact->wa_id,
                    'name' => $contact->name,
                ]),
            'conversations' => WhatsAppConversation::where('account_id', $account->id)
                ->with('contact')
                ->orderByDesc('last_message_at')
                ->limit(50)
                ->get()
                ->map(fn ($conversation) => [
                    'id' => $conversation->id,
                    'contact' => [
                        'wa_id' => $conversation->contact?->wa_id,
                        'name' => $conversation->contact?->name ?? $conversation->contact?->wa_id,
                    ],
                ])
                ->filter(fn ($conversation) => filled($conversation['contact']['wa_id']))
                ->values(),
            'sync_report' => session('sync_report'),
            'library_templates' => collect(PreapprovedTemplateLibrary::all())->values(),
            'filters' => [
                'connection' => $request->connection,
                'status' => $request->status,
                'category' => $request->category,
                'language' => $request->language,
                'search' => $request->search]]);
    }

    public function library(Request $request): RedirectResponse
    {
        return redirect()->route('app.whatsapp.templates.index', ['panel' => 'library']);
    }

    /**
     * Display the specified template.
     */
    public function show(Request $request, WhatsAppTemplate $template): RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($template->account_id, $account->id)) {
            abort(404);
        }

        return redirect()->route('app.whatsapp.templates.index', ['template' => $template->id]);
    }

    /**
     * Archive a template.
     */
    public function archive(Request $request, WhatsAppTemplate $template)
    {
        $account = $request->attributes->get('account') ?? current_account();

        // Ensure template belongs to account
        if (! account_ids_match($template->account_id, $account->id)) {
            abort(404);
        }

        $template->update(['is_archived' => true]);

        return redirect()->route('app.whatsapp.templates.index')->with('success', 'Template archived successfully.');
    }

    /**
     * Restore an archived template.
     */
    public function restore(Request $request, WhatsAppTemplate $template)
    {
        $account = $request->attributes->get('account') ?? current_account();

        // Ensure template belongs to account
        if (! account_ids_match($template->account_id, $account->id)) {
            abort(404);
        }

        $template->update(['is_archived' => false]);

        return redirect()->back()->with('success', 'Template restored successfully.');
    }

    /**
     * Delete a template permanently.
     */
    public function destroy(Request $request, WhatsAppTemplate $template)
    {
        $account = $request->attributes->get('account') ?? current_account();

        // Ensure template belongs to account
        if (! account_ids_match($template->account_id, $account->id)) {
            abort(404);
        }

        if ($template->meta_template_id && $template->connection) {
            try {
                $this->templateManagementService->deleteTemplate($template->connection, $template->meta_template_id);
            } catch (\Throwable $e) {
                Log::channel('whatsapp')->warning('Meta template delete failed; keeping local template', [
                    'template_id' => $template->id,
                    'meta_template_id' => $template->meta_template_id,
                    'error' => $e->getMessage(),
                ]);

                return redirect()->back()->withErrors([
                    'delete' => 'Failed to delete template from Meta: '.$e->getMessage(),
                ]);
            }
        }

        $template->delete();

        return redirect()->route('app.whatsapp.templates.index')->with('success', 'Template deleted successfully.');
    }

    /**
     * Show the form for creating a new template.
     */
    public function create(Request $request): RedirectResponse
    {
        $params = ['panel' => 'create'];
        if ($request->filled('preset')) {
            $params['preset'] = $request->query('preset');
        }

        return redirect()->route('app.whatsapp.templates.index', $params);
    }

    /**
     * Store a newly created template.
     */
    public function store(Request $request)
    {
        $account = $request->attributes->get('account') ?? current_account();

        $validated = $request->validate([
            'whatsapp_connection_id' => 'required|exists:whatsapp_connections,id',
            'name' => 'required|string|max:512|regex:/^[a-zA-Z0-9_]+$/',
            'language' => 'required|string|min:2|max:10|regex:/^[a-z]{2}(_[A-Z]{2})?$/',
            'category' => 'required|in:MARKETING,UTILITY,AUTHENTICATION',
            'header_type' => 'nullable|in:NONE,TEXT,IMAGE,VIDEO,DOCUMENT',
            'header_text' => 'nullable|string|max:60|required_if:header_type,TEXT',
            'header_media_url' => 'nullable|url|required_if:header_type,IMAGE,VIDEO,DOCUMENT',
            'body_text' => 'required|string|max:1024',
            'body_examples' => 'nullable|array',
            'body_examples.*' => 'string|max:100',
            'footer_text' => 'nullable|string|max:60',
            'buttons' => 'nullable|array|max:3',
            'buttons.*.type' => 'required_with:buttons|in:QUICK_REPLY,URL,PHONE_NUMBER',
            'buttons.*.text' => 'required_with:buttons|string|max:20',
            'buttons.*.url' => 'nullable|url|required_if:buttons.*.type,URL',
            'buttons.*.url_example' => 'nullable|string|max:200',
            'buttons.*.phone_number' => 'nullable|string|required_if:buttons.*.type,PHONE_NUMBER']);
        $this->validateTemplateSemanticFields($validated);

        $connection = WhatsAppConnection::where('account_id', $account->id)
            ->findOrFail($validated['whatsapp_connection_id']);

        Gate::authorize('update', $connection);

        if (! $connection->is_active) {
            return back()->withErrors(['create' => 'This WhatsApp connection is disabled. Enable or reconnect it before creating templates.'])->withInput();
        }

        if (! $connection->waba_id) {
            return back()->withErrors(['create' => 'This WhatsApp connection is missing a WABA ID. Reconnect the WABA account and try again.'])->withInput();
        }

        if (! $connection->access_token) {
            return back()->withErrors(['create' => 'This WhatsApp connection is missing or cannot decrypt its access token. Reconnect the WABA account and try again.'])->withInput();
        }

        try {
            Log::channel('whatsapp')->info('Creating template', [
                'account_id' => $account->id,
                'connection_id' => $connection->id,
                'template_name' => $validated['name']]);

            $result = $this->templateManagementService->createTemplate($connection, $validated);

            Log::channel('whatsapp')->info('Template created successfully', [
                'account_id' => $account->id,
                'connection_id' => $connection->id,
                'template_name' => $validated['name'],
                'meta_template_id' => $result['id'] ?? null]);

            return redirect()->route('app.whatsapp.templates.index')
                ->with('success', 'Template saved and sent to WhatsApp for approval. This usually takes a few minutes.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions so they're handled properly
            throw $e;
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Template creation failed', [
                'account_id' => $account->id,
                'connection_id' => $connection->id,
                'template_name' => $validated['name'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()]);

            $errorMessage = $e->getMessage();
            $displayMessage = str_starts_with($errorMessage, 'Failed to create template:')
                ? $errorMessage
                : 'Failed to create template: '.$errorMessage;

            if ($request->header('X-Inertia')) {
                return back()->withErrors(['create' => $displayMessage])->withInput();
            }

            return back()->withErrors(['create' => $displayMessage])->withInput();
        }
    }

    /**
     * Show the form for editing a template.
     */
    public function edit(Request $request, WhatsAppTemplate $template): RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($template->account_id, $account->id)) {
            abort(404);
        }

        return redirect()->route('app.whatsapp.templates.index', [
            'template' => $template->id,
            'panel' => 'edit',
        ]);
    }

    /**
     * Update a template (creates new version).
     */
    public function update(Request $request, WhatsAppTemplate $template)
    {
        $account = $request->attributes->get('account') ?? current_account();

        // Ensure template belongs to account
        if (! account_ids_match($template->account_id, $account->id)) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:512|regex:/^[a-zA-Z0-9_]+$/',
            'language' => 'required|string|min:2|max:10|regex:/^[a-z]{2}(_[A-Z]{2})?$/',
            'category' => 'required|in:MARKETING,UTILITY,AUTHENTICATION',
            'header_type' => 'nullable|in:NONE,TEXT,IMAGE,VIDEO,DOCUMENT',
            'header_text' => 'nullable|string|max:60|required_if:header_type,TEXT',
            'header_media_url' => 'nullable|url|required_if:header_type,IMAGE,VIDEO,DOCUMENT',
            'body_text' => 'required|string|max:1024',
            'body_examples' => 'nullable|array',
            'body_examples.*' => 'string|max:100',
            'footer_text' => 'nullable|string|max:60',
            'buttons' => 'nullable|array|max:3',
            'buttons.*.type' => 'required_with:buttons|in:QUICK_REPLY,URL,PHONE_NUMBER',
            'buttons.*.text' => 'required_with:buttons|string|max:20',
            'buttons.*.url' => 'nullable|url|required_if:buttons.*.type,URL',
            'buttons.*.url_example' => 'nullable|string|max:200',
            'buttons.*.phone_number' => 'nullable|string|required_if:buttons.*.type,PHONE_NUMBER']);
        $this->validateTemplateSemanticFields($validated);

        $connection = $template->connection;
        Gate::authorize('update', $connection);

        try {
            $result = $this->templateManagementService->updateTemplate($connection, $template, $validated);

            return redirect()->route('app.whatsapp.templates.index')->with('success', 'Template updated successfully. A new version has been sent to WhatsApp for approval.');
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Template update failed', [
                'account_id' => $account->id,
                'template_id' => $template->id,
                'error' => $e->getMessage()]);

            return back()->withErrors([
                'update' => 'Failed to update template: '.$e->getMessage()])->withInput();
        }
    }

    /**
     * Upload media file for template header.
     */
    public function uploadMedia(Request $request)
    {
        $account = $request->attributes->get('account') ?? current_account();

        $validated = $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'type' => 'required|in:IMAGE,VIDEO,DOCUMENT']);

        $file = $request->file('file');
        $type = $validated['type'];

        // Validate file type based on header type
        $mimeRules = [
            'IMAGE' => 'mimes:jpg,jpeg,png,gif,webp',
            'VIDEO' => 'mimetypes:video/mp4,video/quicktime,video/3gpp',
            'DOCUMENT' => 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip'];

        $request->validate([
            'file' => $mimeRules[$type] ?? 'file']);

        // Store file
        $path = $file->store('whatsapp-templates', 'public');
        $url = rtrim(config('app.url'), '/').Storage::url($path);

        return response()->json([
            'url' => $url,
            'path' => $path]);

    }

    /**
     * Check template status from Meta.
     */
    public function checkStatus(Request $request, WhatsAppTemplate $template)
    {
        $account = $request->attributes->get('account') ?? current_account();

        // Ensure template belongs to account
        if (! account_ids_match($template->account_id, $account->id)) {
            abort(404);
        }

        if (! $template->meta_template_id) {
            return back()->with('error', 'This template has not been sent to WhatsApp for approval yet.');
        }

        try {
            $statusData = $this->templateManagementService->getTemplateStatus(
                $template->connection,
                $template->meta_template_id
            );

            $metaStatus = strtolower($statusData['status'] ?? $template->status);
            $rejectionReason = $statusData['rejection_reason'] ?? null;

            $template->update([
                'status' => $metaStatus,
                'last_synced_at' => now(),
                'last_meta_error' => $rejectionReason]);

            return back()->with('success', 'Template status refreshed.');
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Template status check failed', [
                'template_id' => $template->id,
                'error' => $e->getMessage()]);

            return back()->with('error', 'We could not check this template right now: '.\App\Support\CustomerMessage::humanize($e->getMessage()));
        }
    }

    private function validateTemplateSemanticFields(array $data): void
    {
        $errors = [];

        if (strtoupper($data['category'] ?? '') !== 'AUTHENTICATION') {
            preg_match_all('/\{\{(\d+)\}\}/', (string) ($data['body_text'] ?? ''), $matches);
            $variables = array_values(array_unique(array_map('intval', $matches[1] ?? [])));
            sort($variables);

            if ($variables) {
                $expected = range(1, count($variables));
                if ($variables !== $expected) {
                    $errors['body_text'] = 'Template variables must be sequential, for example {{1}}, {{2}}, {{3}}.';
                }

                $examples = array_values(array_filter($data['body_examples'] ?? [], fn ($example) => trim((string) $example) !== ''));
                if (count($examples) < count($variables)) {
                    $errors['body_examples'] = 'Add one sample value for each body variable before submitting to Meta.';
                }
            }
        }

        foreach (($data['buttons'] ?? []) as $index => $button) {
            $type = strtoupper((string) ($button['type'] ?? ''));
            if ($type === 'URL') {
                if (empty($button['url'])) {
                    $errors["buttons.{$index}.url"] = 'URL buttons need a destination URL.';
                } elseif (preg_match('/\{\{\d+\}\}/', (string) $button['url']) && empty($button['url_example'])) {
                    $errors["buttons.{$index}.url_example"] = 'Dynamic URL buttons need an example URL.';
                }
            }

            if ($type === 'PHONE_NUMBER' && empty($button['phone_number'])) {
                $errors["buttons.{$index}.phone_number"] = 'Phone buttons need a phone number with country code.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }
}
