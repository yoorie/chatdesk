<?php

namespace App\Modules\Contacts\Jobs;

use App\Modules\Contacts\Models\ContactImportBatch;
use App\Modules\Contacts\Services\ContactService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportContactsCsvJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public function __construct(
        public int $batchId
    ) {}

    public function handle(ContactService $contacts): void
    {
        $batch = ContactImportBatch::findOrFail($this->batchId);

        $batch->update([
            'status' => 'processing',
            'started_at' => now(),
            'total_rows' => $contacts->countCsvDataRows(Storage::path($batch->storage_path)),
        ]);

        $result = $contacts->importFromCsv(
            Storage::path($batch->storage_path),
            (int) $batch->account_id,
            $batch->user_id ? (int) $batch->user_id : null,
            $batch->default_tag_ids ?? [],
            function (array $progress) use ($batch): void {
                ContactImportBatch::whereKey($batch->id)->update([
                    'processed_rows' => $progress['processed'] ?? 0,
                    'imported_count' => $progress['imported'] ?? 0,
                    'updated_count' => $progress['updated'] ?? 0,
                    'skipped_count' => $progress['skipped'] ?? 0,
                    'error_count' => $progress['error_count'] ?? 0,
                    'errors' => $progress['errors'] ?? [],
                ]);
            }
        );

        $batch->update([
            'status' => 'completed',
            'processed_rows' => max((int) $batch->total_rows, (int) $batch->fresh()->processed_rows),
            'imported_count' => $result['imported'],
            'updated_count' => $result['updated'],
            'skipped_count' => count($result['errors']),
            'error_count' => count($result['errors']),
            'errors' => array_slice($result['errors'], 0, 25),
            'completed_at' => now(),
        ]);

        Storage::delete($batch->storage_path);
    }

    public function failed(Throwable $exception): void
    {
        $batch = ContactImportBatch::find($this->batchId);
        if (! $batch) {
            return;
        }

        $batch->update([
            'status' => 'failed',
            'failed_at' => now(),
            'errors' => [$exception->getMessage()],
            'error_count' => max(1, (int) $batch->error_count),
        ]);
    }
}
