<?php

namespace App\Modules\Contacts\Models;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactImportBatch extends Model
{
    protected $fillable = [
        'account_id',
        'user_id',
        'original_filename',
        'storage_path',
        'status',
        'total_rows',
        'processed_rows',
        'imported_count',
        'updated_count',
        'skipped_count',
        'error_count',
        'errors',
        'default_tag_ids',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected $casts = [
        'errors' => 'array',
        'default_tag_ids' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function progressPercent(): int
    {
        $total = max(1, (int) $this->total_rows);

        return min(100, (int) floor(((int) $this->processed_rows / $total) * 100));
    }
}
