<?php

namespace App\Modules\Contacts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Contacts\Jobs\ImportContactsCsvJob;
use App\Modules\Contacts\Models\ContactActivity;
use App\Modules\Contacts\Models\ContactImportBatch;
use App\Modules\Contacts\Models\ContactSegment;
use App\Modules\Contacts\Models\ContactTag;
use App\Modules\Contacts\Services\ContactService;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function __construct(
        protected ContactService $contactService
    ) {}

    /**
     * Display a listing of contacts.
     */
    public function index(Request $request): Response
    {
        $account = $request->attributes->get('account') ?? current_account();

        $query = WhatsAppContact::where('account_id', $account->id)
            ->with(['tags', 'segments']);

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('wa_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter by tags
        if ($request->has('tags') && ! empty($request->tags)) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->whereIn('contact_tags.id', $request->tags);
            });
        }

        // Filter by segments
        if ($request->has('segments') && ! empty($request->segments)) {
            $query->whereHas('segments', function ($q) use ($request) {
                $q->whereIn('contact_segments.id', $request->segments);
            });
        }

        $contacts = $query->orderBy('created_at', 'desc')
            ->paginate(50)
            ->through(function ($contact) {
                return [
                    'id' => $contact->id,
                    'slug' => $contact->slug ?? $contact->wa_id ?? (string) $contact->id,
                    'wa_id' => $contact->wa_id,
                    'name' => $contact->name,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'company' => $contact->company,
                    'notes' => $contact->notes,
                    'status' => $contact->status ?? 'active',
                    'message_count' => $contact->message_count ?? 0,
                    'last_seen_at' => $contact->last_seen_at?->toIso8601String(),
                    'last_contacted_at' => $contact->last_contacted_at?->toIso8601String(),
                    'tags' => $contact->tags->map(function ($tag) {
                        return [
                            'id' => $tag->id,
                            'name' => $tag->name,
                            'color' => $tag->color];
                    }),
                    'segments' => $contact->segments->map(function ($segment) {
                        return [
                            'id' => $segment->id,
                            'name' => $segment->name];
                    }),
                    'created_at' => $contact->created_at->toIso8601String()];
            });

        // Get available tags and segments for filters
        $tags = ContactTag::where('account_id', $account->id)
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        $segments = ContactSegment::where('account_id', $account->id)
            ->orderBy('name')
            ->get(['id', 'name', 'contact_count']);

        return Inertia::render('Contacts/Index', [
            'account' => $account,
            'contacts' => $contacts,
            'selectedContact' => $this->selectedContactPayload($request, $account),
            'contactStats' => [
                'total' => WhatsAppContact::where('account_id', $account->id)->count(),
                'active' => WhatsAppContact::where('account_id', $account->id)->where('status', 'active')->count(),
                'inactive' => WhatsAppContact::where('account_id', $account->id)->where('status', 'inactive')->count(),
                'blocked' => WhatsAppContact::where('account_id', $account->id)->where('status', 'blocked')->count(),
                'opt_out' => WhatsAppContact::where('account_id', $account->id)->where('status', 'opt_out')->count(),
            ],
            'tags' => $tags,
            'segments' => $segments,
            'importBatches' => ContactImportBatch::where('account_id', $account->id)
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (ContactImportBatch $batch) => $this->importBatchPayload($batch)),
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'tags' => $request->tags ?? [],
                'segments' => $request->segments ?? []]]);
    }

    protected function selectedContactPayload(Request $request, $account): ?array
    {
        $selected = $request->query('contact');
        if (! $selected) {
            return null;
        }

        $contact = WhatsAppContact::where('account_id', $account->id)
            ->where(function ($query) use ($selected) {
                $query->where('slug', $selected)
                    ->orWhere('wa_id', $selected);
                if (is_numeric($selected)) {
                    $query->orWhere('id', (int) $selected);
                }
            })
            ->with(['tags', 'segments'])
            ->first();

        if (! $contact) {
            return null;
        }

        return [
            'id' => $contact->id,
            'slug' => $contact->slug ?? $contact->wa_id ?? (string) $contact->id,
            'wa_id' => $contact->wa_id,
            'name' => $contact->name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'company' => $contact->company,
            'notes' => $contact->notes,
            'status' => $contact->status ?? 'active',
            'message_count' => $contact->message_count ?? 0,
            'last_seen_at' => $contact->last_seen_at?->toIso8601String(),
            'last_contacted_at' => $contact->last_contacted_at?->toIso8601String(),
            'tags' => $contact->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ]),
            'segments' => $contact->segments->map(fn ($segment) => [
                'id' => $segment->id,
                'name' => $segment->name,
            ]),
            'created_at' => $contact->created_at->toIso8601String(),
        ];
    }

    /**
     * Store a newly created contact.
     */
    public function store(Request $request)
    {
        $account = $request->attributes->get('account') ?? current_account();

        $validated = $request->validate([
            'wa_id' => 'required|string',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,blocked,opt_out',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:contact_tags,id']);

        $contact = $this->contactService->createOrUpdateContact(
            $validated,
            $account->id,
            $request->user()->id
        );

        // Add tags if provided
        if (isset($validated['tags']) && ! empty($validated['tags'])) {
            $this->contactService->addTags($contact, $validated['tags']);
        }

        return redirect()->route('app.contacts.index', [
            'contact' => $contact->slug])->with('success', 'Contact created successfully.');
    }

    /**
     * Update the specified contact.
     */
    public function update(Request $request, WhatsAppContact $contact)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($contact->account_id, $account->id)) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,blocked,opt_out',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:contact_tags,id',
            'segments' => 'nullable|array',
            'segments.*' => 'exists:contact_segments,id']);

        $contact->update(\Illuminate\Support\Arr::except($validated, ['tags', 'segments']));

        // Sync tags
        if (array_key_exists('tags', $validated)) {
            $contact->tags()->sync($validated['tags'] ?? []);
        }

        // Sync segments
        if (array_key_exists('segments', $validated)) {
            $contact->segments()->sync($validated['segments'] ?? []);
        }

        ContactActivity::create([
            'account_id' => $account->id,
            'contact_id' => $contact->id,
            'user_id' => $request->user()->id,
            'type' => 'contact_updated',
            'title' => 'Contact updated',
            'description' => 'Contact information was updated']);

        return back()->with('success', 'Contact updated successfully.');
    }

    /**
     * Remove the specified contact.
     */
    public function destroy(Request $request, WhatsAppContact $contact)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($contact->account_id, $account->id)) {
            abort(404);
        }

        $hasConversations = $contact->conversations()
            ->where('account_id', $account->id)
            ->exists();

        if ($hasConversations) {
            return redirect()
                ->route('app.contacts.index', ['contact' => $contact->slug ?? $contact->id])
                ->with('error', 'Contact cannot be deleted because conversation history exists. Archive or clear conversations first.');
        }

        $contactLabel = $contact->name ?: $contact->wa_id;
        $recoveryDays = max(1, (int) \App\Models\PlatformSetting::get('compliance.recovery_window_days', 30));

        \DB::transaction(function () use ($account, $request, $contact, $contactLabel, $recoveryDays): void {
            ContactActivity::create([
                'account_id' => $account->id,
                'contact_id' => $contact->id,
                'user_id' => $request->user()->id,
                'type' => 'contact_deleted',
                'title' => 'Contact deleted',
                'description' => "Contact {$contactLabel} was deleted",
            ]);

            $contact->purge_after_at = now()->addDays($recoveryDays);
            $contact->save();
            $contact->delete();
        });

        return redirect()->route('app.contacts.index')->with('success', "Contact moved to recovery bin for {$recoveryDays} days.");
    }

    /**
     * Move multiple contacts to the recovery bin.
     */
    public function bulkDestroy(Request $request)
    {
        $account = $request->attributes->get('account') ?? current_account();

        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
        ]);

        $contacts = WhatsAppContact::where('account_id', $account->id)
            ->whereIn('id', $validated['ids'])
            ->withExists(['conversations as has_conversations' => function ($query) use ($account) {
                $query->where('account_id', $account->id);
            }])
            ->get();

        $deletable = $contacts->where('has_conversations', false)->values();
        $skipped = $contacts->count() - $deletable->count();
        $recoveryDays = max(1, (int) \App\Models\PlatformSetting::get('compliance.recovery_window_days', 30));

        if ($deletable->isNotEmpty()) {
            \DB::transaction(function () use ($account, $request, $deletable, $recoveryDays): void {
                foreach ($deletable as $contact) {
                    ContactActivity::create([
                        'account_id' => $account->id,
                        'contact_id' => $contact->id,
                        'user_id' => $request->user()->id,
                        'type' => 'contact_deleted',
                        'title' => 'Contact deleted',
                        'description' => 'Contact '.($contact->name ?: $contact->wa_id).' was deleted in bulk',
                    ]);

                    $contact->purge_after_at = now()->addDays($recoveryDays);
                    $contact->save();
                    $contact->delete();
                }
            });
        }

        $message = $deletable->count()." contact(s) moved to recovery bin for {$recoveryDays} days.";
        if ($skipped > 0) {
            $message .= " {$skipped} contact(s) with conversation history were skipped.";
        }

        return redirect()->route('app.contacts.index')->with($deletable->isNotEmpty() ? 'success' : 'error', $message);
    }

    /**
     * Add, replace, or remove tags on multiple contacts.
     */
    public function bulkUpdateTags(Request $request)
    {
        $account = $request->attributes->get('account') ?? current_account();

        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
            'tags' => 'required|array|min:1',
            'tags.*' => 'integer',
            'mode' => 'required|in:add,replace,remove',
        ]);

        $tagIds = ContactTag::where('account_id', $account->id)
            ->whereIn('id', $validated['tags'])
            ->pluck('id')
            ->all();

        if (count($tagIds) !== count(array_unique($validated['tags']))) {
            return back()->withErrors(['tags' => 'One or more selected tags are not available in this workspace.']);
        }

        $contacts = WhatsAppContact::where('account_id', $account->id)
            ->whereIn('id', $validated['ids'])
            ->get();

        if ($contacts->isEmpty()) {
            return back()->withErrors(['ids' => 'No matching contacts were found in this workspace.']);
        }

        foreach ($contacts as $contact) {
            match ($validated['mode']) {
                'replace' => $contact->tags()->sync($tagIds),
                'remove' => $contact->tags()->detach($tagIds),
                default => $contact->tags()->syncWithoutDetaching($tagIds),
            };
        }

        $action = match ($validated['mode']) {
            'replace' => 'replaced on',
            'remove' => 'removed from',
            default => 'added to',
        };

        ContactActivity::create([
            'account_id' => $account->id,
            'contact_id' => $contacts->first()?->id,
            'user_id' => $request->user()->id,
            'type' => 'contacts_bulk_tagged',
            'title' => 'Bulk tags updated',
            'description' => count($tagIds).' tag(s) '.$action.' '.$contacts->count().' contact(s)',
        ]);

        return back()->with('success', 'Tags updated for '.$contacts->count().' contact(s).');
    }

    /**
     * Add note to contact.
     */
    public function addNote(Request $request, WhatsAppContact $contact)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($contact->account_id, $account->id)) {
            abort(404);
        }

        $validated = $request->validate([
            'note' => 'required|string']);

        $this->contactService->addNote($contact, $validated['note'], $request->user()->id);

        return back()->with('success', 'Note added successfully.');
    }

    /**
     * Import contacts from CSV.
     */
    public function import(Request $request)
    {
        $account = $request->attributes->get('account') ?? current_account();

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
            'tags' => 'nullable|array',
            'tags.*' => 'integer|exists:contact_tags,id']);

        $file = $request->file('file');
        $path = $file->store('contact-imports');

        $batch = ContactImportBatch::create([
            'account_id' => $account->id,
            'user_id' => $request->user()?->id,
            'original_filename' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'status' => 'queued',
            'default_tag_ids' => array_values($request->input('tags', [])),
        ]);

        ImportContactsCsvJob::dispatch($batch->id);

        return back()->with('success', 'Contact import queued. Progress will update on this page.');
    }

    public function importStatus(Request $request, ContactImportBatch $batch)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($batch->account_id, $account->id)) {
            abort(404);
        }

        return response()->json([
            'data' => $this->importBatchPayload($batch->fresh()),
        ]);
    }

    public function importErrors(Request $request, ContactImportBatch $batch)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($batch->account_id, $account->id)) {
            abort(404);
        }

        $filename = 'contact-import-errors-'.$batch->id.'.csv';

        return response()->streamDownload(function () use ($batch) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['error']);
            foreach (($batch->errors ?? []) as $error) {
                fputcsv($handle, [$error]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Export contacts to CSV.
     */
    public function export(Request $request)
    {
        $account = $request->attributes->get('account') ?? current_account();
        app(\App\Services\WorkspacePermissionService::class)->assert($request->user(), $account, 'contacts.export');

        $filters = [
            'tags' => $request->tags ?? [],
            'segments' => $request->segments ?? [],
            'status' => $request->status];

        app(\App\Services\AppNotificationService::class)->auditDestructive(
            'contacts_exported',
            'Contacts exported to CSV',
            $request->user(),
            $account,
            null,
            ['filters' => $filters],
            $request
        );

        $filename = 'ui-contacts-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($account, $filters) {
            $this->contactService->streamCsvExport($account->id, $filters);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Merge duplicate contacts.
     */
    public function merge(Request $request, WhatsAppContact $contact)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! account_ids_match($contact->account_id, $account->id)) {
            abort(404);
        }

        $validated = $request->validate([
            'duplicate_ids' => 'required|array',
            'duplicate_ids.*' => 'exists:whatsapp_contacts,id']);

        try {
            $this->contactService->mergeContacts($contact, $validated['duplicate_ids']);

            return redirect()->route('app.contacts.index', [
                'contact' => $contact->slug])->with('success', 'Contacts merged successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to merge contacts: '.$e->getMessage()]);
        }
    }

    protected function importBatchPayload(ContactImportBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'filename' => $batch->original_filename,
            'status' => $batch->status,
            'total_rows' => $batch->total_rows,
            'processed_rows' => $batch->processed_rows,
            'progress' => $batch->progressPercent(),
            'imported_count' => $batch->imported_count,
            'updated_count' => $batch->updated_count,
            'skipped_count' => $batch->skipped_count,
            'error_count' => $batch->error_count,
            'errors' => $batch->errors ?? [],
            'created_at' => $batch->created_at?->toIso8601String(),
            'started_at' => $batch->started_at?->toIso8601String(),
            'completed_at' => $batch->completed_at?->toIso8601String(),
            'failed_at' => $batch->failed_at?->toIso8601String(),
        ];
    }
}
