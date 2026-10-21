<?php

namespace App\Modules\Contacts\Services;

use App\Modules\Contacts\Models\ContactActivity;
use App\Modules\Contacts\Models\ContactSegment;
use App\Modules\Contacts\Models\ContactTag;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContactService
{
    /**
     * Create or update a contact.
     */
    public function createOrUpdateContact(array $data, int $accountId, ?int $userId = null): WhatsAppContact
    {
        $contact = WhatsAppContact::updateOrCreate(
            [
                'account_id' => $accountId,
                'wa_id' => $data['wa_id'] ?? $data['phone'] ?? null],
            array_merge($data, [
                'account_id' => $accountId,
                'source' => $data['source'] ?? 'manual'])
        );

        // Log activity
        if ($userId) {
            ContactActivity::create([
                'account_id' => $accountId,
                'contact_id' => $contact->id,
                'user_id' => $userId,
                'type' => $contact->wasRecentlyCreated ? 'contact_created' : 'contact_updated',
                'title' => $contact->wasRecentlyCreated ? 'Contact created' : 'Contact updated',
                'description' => $contact->wasRecentlyCreated
                    ? "Contact {$contact->name} was created"
                    : "Contact {$contact->name} was updated"]);
        }

        return $contact;
    }

    /**
     * Add tags to a contact.
     */
    public function addTags(WhatsAppContact $contact, array $tagIds): void
    {
        $contact->tags()->syncWithoutDetaching($tagIds);

        ContactActivity::create([
            'account_id' => $contact->account_id,
            'contact_id' => $contact->id,
            'type' => 'tag_added',
            'title' => 'Tags added',
            'description' => 'Tags were added to contact']);
    }

    /**
     * Remove tags from a contact.
     */
    public function removeTags(WhatsAppContact $contact, array $tagIds): void
    {
        $contact->tags()->detach($tagIds);

        ContactActivity::create([
            'account_id' => $contact->account_id,
            'contact_id' => $contact->id,
            'type' => 'tag_removed',
            'title' => 'Tags removed',
            'description' => 'Tags were removed from contact']);
    }

    /**
     * Add note to contact.
     */
    public function addNote(WhatsAppContact $contact, string $note, ?int $userId = null): void
    {
        $existingNotes = $contact->notes ?? '';
        $newNotes = $existingNotes ? "{$existingNotes}\n\n{$note}" : $note;

        $contact->update(['notes' => $newNotes]);

        ContactActivity::create([
            'account_id' => $contact->account_id,
            'contact_id' => $contact->id,
            'user_id' => $userId,
            'type' => 'note_added',
            'title' => 'Note added',
            'description' => $note]);
    }

    /**
     * Merge duplicate contacts.
     * Uses lock to prevent concurrent merges.
     */
    public function mergeContacts(WhatsAppContact $primaryContact, array $duplicateIds): WhatsAppContact
    {
        // Use lock to prevent concurrent merges
        $lockKey = "contact_merge:{$primaryContact->id}";
        $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 300); // 5 minute lock

        if (! $lock->get()) {
            throw new \Exception('Contact merge is already in progress.');
        }

        try {
            return $this->performMerge($primaryContact, $duplicateIds);
        } finally {
            $lock->release();
        }
    }

    /**
     * Perform the actual merge operation.
     */
    protected function performMerge(WhatsAppContact $primaryContact, array $duplicateIds): WhatsAppContact
    {
        DB::beginTransaction();

        try {
            // Get all duplicate contacts (with lock)
            $duplicates = WhatsAppContact::whereIn('id', $duplicateIds)
                ->where('account_id', $primaryContact->account_id)
                ->where('id', '!=', $primaryContact->id)
                ->lockForUpdate()
                ->get();

            foreach ($duplicates as $duplicate) {
                // Merge tags
                $duplicate->tags()->each(function ($tag) use ($primaryContact) {
                    $primaryContact->tags()->syncWithoutDetaching([$tag->id]);
                });

                // Merge segments
                $duplicate->segments()->each(function ($segment) use ($primaryContact) {
                    $primaryContact->segments()->syncWithoutDetaching([$segment->id]);
                });

                // Update conversations to point to primary contact
                $duplicate->conversations()->update(['whatsapp_contact_id' => $primaryContact->id]);

                // Merge metadata
                $primaryMetadata = $primaryContact->metadata ?? [];
                $duplicateMetadata = $duplicate->metadata ?? [];
                $primaryContact->update([
                    'metadata' => array_merge($primaryMetadata, $duplicateMetadata)]);

                // Log merge activity
                ContactActivity::create([
                    'account_id' => $primaryContact->account_id,
                    'contact_id' => $primaryContact->id,
                    'type' => 'contact_merged',
                    'title' => 'Contact merged',
                    'description' => "Contact {$duplicate->name} ({$duplicate->wa_id}) was merged into this contact",
                    'metadata' => ['merged_contact_id' => $duplicate->id]]);

                // Delete duplicate
                $duplicate->delete();
            }

            DB::commit();

            return $primaryContact->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to merge contacts', [
                'primary_contact_id' => $primaryContact->id,
                'duplicate_ids' => $duplicateIds,
                'error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Import contacts from CSV.
     * Uses lock to prevent concurrent imports.
     */
    public function importFromCsv(string $filePath, int $accountId, ?int $userId = null, array $defaultTagIds = [], ?callable $progress = null): array
    {
        // Use cache lock to prevent concurrent imports for the same account
        $lockKey = "contact_import:account:{$accountId}";
        $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 600); // 10 minute lock

        if (! $lock->get()) {
            throw new \Exception('Contact import is already in progress for this account. Please wait.');
        }

        try {
            return $this->performImport($filePath, $accountId, $userId, $defaultTagIds, $progress);
        } finally {
            $lock->release();
        }
    }

    /**
     * Perform the actual import operation.
     */
    protected function performImport(string $filePath, int $accountId, ?int $userId = null, array $defaultTagIds = [], ?callable $progress = null): array
    {
        $imported = 0;
        $updated = 0;
        $errors = [];

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            throw new \Exception('Failed to open CSV file');
        }

        // Read header row
        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);
            throw new \Exception('CSV file is empty or invalid');
        }

        $normalizedHeaders = array_map(function ($header) {
            return strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $header)));
        }, $headers);

        // Map headers to contact fields
        $headerMap = [
            'wa_id' => ['wa_id', 'whatsapp_id', 'whatsapp', 'phone', 'phone_number', 'mobile', 'mobile_number'],
            'name' => ['name', 'full_name', 'contact_name'],
            'email' => ['email', 'email_address'],
            'phone' => ['phone', 'phone_number', 'mobile'],
            'company' => ['company', 'organization', 'org'],
            'notes' => ['notes', 'note', 'description'],
            'status' => ['status'],
            'tags' => ['tags', 'tag']];

        $columnIndexes = [];
        foreach ($headerMap as $field => $possibleHeaders) {
            foreach ($possibleHeaders as $possibleHeader) {
                $index = array_search(strtolower($possibleHeader), $normalizedHeaders);
                if ($index !== false) {
                    $columnIndexes[$field] = $index;
                    break;
                }
            }
        }

        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            try {
                $data = [];
                foreach ($columnIndexes as $field => $index) {
                    if (isset($row[$index]) && $row[$index] !== '') {
                        $data[$field] = trim($row[$index]);
                    }
                }

                if (empty($data['wa_id']) && empty($data['phone'])) {
                    $errors[] = "Row {$rowNumber}: Missing phone number or WhatsApp ID";

                    $this->reportImportProgress($progress, $rowNumber - 1, $imported, $updated, $errors);

                    continue;
                }

                $data['wa_id'] = $this->normalizePhoneIdentifier($data['wa_id'] ?? $data['phone'] ?? null);
                $data['phone'] = $this->normalizePhoneIdentifier($data['phone'] ?? $data['wa_id'] ?? null);
                if (! $data['wa_id'] || strlen($data['wa_id']) < 8) {
                    $errors[] = "Row {$rowNumber}: Invalid phone number or WhatsApp ID";

                    $this->reportImportProgress($progress, $rowNumber - 1, $imported, $updated, $errors);

                    continue;
                }

                if (isset($data['status']) && ! in_array($data['status'], ['active', 'inactive', 'blocked', 'opt_out'], true)) {
                    $data['status'] = 'active';
                }

                $tagNames = $this->parseTagNames($data['tags'] ?? null);
                unset($data['tags']);
                $data['source'] = 'csv_import';

                // Use transaction and lock to prevent race conditions
                \DB::transaction(function () use ($accountId, $data, $userId, $tagNames, $defaultTagIds, &$imported, &$updated) {
                    $contact = WhatsAppContact::where('account_id', $accountId)
                        ->where('wa_id', $data['wa_id'])
                        ->lockForUpdate() // Row-level lock
                        ->first();

                    if ($contact) {
                        $contact->update($data);
                        $updated++;
                    } else {
                        $contact = $this->createOrUpdateContact($data, $accountId, $userId);
                        $imported++;
                    }

                    $tagIds = array_merge($defaultTagIds, $this->resolveTagIds($accountId, $tagNames));
                    if (! empty($tagIds)) {
                        $contact->tags()->syncWithoutDetaching(array_values(array_unique($tagIds)));
                    }
                });
            } catch (\Exception $e) {
                $errors[] = "Row {$rowNumber}: {$e->getMessage()}";
            }

            $this->reportImportProgress($progress, $rowNumber - 1, $imported, $updated, $errors);
        }

        fclose($handle);

        return [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors];
    }

    public function countCsvDataRows(string $filePath): int
    {
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return 0;
        }

        $rows = 0;
        $headerRead = false;
        while (($row = fgetcsv($handle)) !== false) {
            if (! $headerRead) {
                $headerRead = true;

                continue;
            }

            if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) > 0) {
                $rows++;
            }
        }

        fclose($handle);

        return $rows;
    }

    protected function reportImportProgress(?callable $progress, int $processed, int $imported, int $updated, array $errors): void
    {
        if (! $progress || $processed % 25 !== 0) {
            return;
        }

        $progress([
            'processed' => $processed,
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => count($errors),
            'error_count' => count($errors),
            'errors' => array_slice($errors, 0, 25),
        ]);
    }

    protected function normalizePhoneIdentifier(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', $value);

        return $normalized !== '' ? $normalized : null;
    }

    protected function parseTagNames(?string $value): array
    {
        if (! $value) {
            return [];
        }

        return collect(preg_split('/[,|;]/', $value))
            ->map(fn ($tag) => trim((string) $tag))
            ->filter()
            ->unique(fn ($tag) => strtolower($tag))
            ->values()
            ->all();
    }

    protected function resolveTagIds(int $accountId, array $tagNames): array
    {
        if (empty($tagNames)) {
            return [];
        }

        return collect($tagNames)->map(function (string $name) use ($accountId) {
            return ContactTag::firstOrCreate(
                ['account_id' => $accountId, 'name' => $name],
                ['color' => '#10B981']
            )->id;
        })->all();
    }

    /**
     * Export contacts to CSV.
     * Uses lock to prevent concurrent exports.
     */
    public function exportToCsv(int $accountId, array $filters = []): string
    {
        // Use lock to prevent concurrent exports
        $lockKey = "contact_export:account:{$accountId}";
        $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 300); // 5 minute lock

        if (! $lock->get()) {
            throw new \Exception('Contact export is already in progress for this account. Please wait.');
        }

        try {
            return $this->performExport($accountId, $filters);
        } finally {
            $lock->release();
        }
    }

    public function streamCsvExport(int $accountId, array $filters = [], mixed $handle = null): void
    {
        $lockKey = "contact_export:account:{$accountId}";
        $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 300);

        if (! $lock->get()) {
            throw new \Exception('Contact export is already in progress for this account. Please wait.');
        }

        $handle ??= fopen('php://output', 'w');

        try {
            fputcsv($handle, ['wa_id', 'name', 'email', 'phone', 'company', 'status', 'tags', 'notes', 'created_at']);

            $this->exportQuery($accountId, $filters)
                ->select(['id', 'wa_id', 'name', 'email', 'phone', 'company', 'status', 'notes', 'created_at'])
                ->orderBy('id')
                ->chunkById(500, function ($contacts) use ($handle) {
                    $contacts->load('tags:id,name');

                    foreach ($contacts as $contact) {
                        fputcsv($handle, [
                            $contact->wa_id,
                            $contact->name ?? '',
                            $contact->email ?? '',
                            $contact->phone ?? '',
                            $contact->company ?? '',
                            $contact->status ?? 'active',
                            $contact->tags->pluck('name')->join(', '),
                            $contact->notes ?? '',
                            $contact->created_at->toDateTimeString(),
                        ]);
                    }

                    if (function_exists('flush')) {
                        flush();
                    }
                });
        } finally {
            $lock->release();
        }
    }

    /**
     * Perform the actual export operation.
     */
    protected function performExport(int $accountId, array $filters = []): string
    {
        $query = $this->exportQuery($accountId, $filters);

        // Use unique filename with account ID and timestamp to prevent conflicts
        $filename = storage_path("app/contacts_export_{$accountId}_".time().'_'.uniqid().'.csv');
        $handle = fopen($filename, 'w');

        // Write header
        fputcsv($handle, ['wa_id', 'name', 'email', 'phone', 'company', 'status', 'tags', 'notes', 'created_at']);

        $query->select(['id', 'wa_id', 'name', 'email', 'phone', 'company', 'status', 'notes', 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function ($contacts) use ($handle) {
                $contacts->load('tags:id,name');

                foreach ($contacts as $contact) {
                    fputcsv($handle, [
                        $contact->wa_id,
                        $contact->name ?? '',
                        $contact->email ?? '',
                        $contact->phone ?? '',
                        $contact->company ?? '',
                        $contact->status ?? 'active',
                        $contact->tags->pluck('name')->join(', '),
                        $contact->notes ?? '',
                        $contact->created_at->toDateTimeString(),
                    ]);
                }
            });

        fclose($handle);

        return $filename;
    }

    protected function exportQuery(int $accountId, array $filters = [])
    {
        $query = WhatsAppContact::where('account_id', $accountId);

        if (isset($filters['tags']) && ! empty($filters['tags'])) {
            $query->whereHas('tags', function ($q) use ($filters) {
                $q->whereIn('contact_tags.id', $filters['tags']);
            });
        }

        if (isset($filters['segments']) && ! empty($filters['segments'])) {
            $query->whereHas('segments', function ($q) use ($filters) {
                $q->whereIn('contact_segments.id', $filters['segments']);
            });
        }

        if (isset($filters['status']) && $filters['status']) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    /**
     * Find duplicate contacts.
     */
    public function findDuplicates(int $accountId, string $field = 'wa_id'): array
    {
        $duplicates = WhatsAppContact::where('account_id', $accountId)
            ->select($field, DB::raw('COUNT(*) as count'))
            ->groupBy($field)
            ->having('count', '>', 1)
            ->get();

        $groups = [];
        foreach ($duplicates as $duplicate) {
            $contacts = WhatsAppContact::where('account_id', $accountId)
                ->where($field, $duplicate->$field)
                ->get();

            $groups[] = $contacts;
        }

        return $groups;
    }

    /**
     * Recalculate segment contact counts.
     * Uses lock to prevent concurrent recalculations.
     */
    public function recalculateSegmentCounts(int $accountId): void
    {
        // Use lock to prevent concurrent recalculations
        $lockKey = "segment_recalculate:account:{$accountId}";
        $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 600); // 10 minute lock

        if (! $lock->get()) {
            throw new \Exception('Segment recalculation is already in progress for this account.');
        }

        try {
            $segments = ContactSegment::where('account_id', $accountId)->get();

            foreach ($segments as $segment) {
                $segment->calculateContactCount();
            }
        } finally {
            $lock->release();
        }
    }
}
