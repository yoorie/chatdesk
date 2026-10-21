<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemBackup extends Model
{
    protected $fillable = [
        'type',
        'status',
        'disk',
        'path',
        'file_size_bytes',
        'checksum',
        'started_at',
        'completed_at',
        'restore_drill_at',
        'restore_drill_status',
        'error_message',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'restore_drill_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
