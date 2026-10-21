<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountAddon extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'addon_id',
        'quantity',
        'status',
        'started_at',
        'ends_at'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'started_at' => 'datetime',
            'ends_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(PlanAddon::class, 'addon_id');
    }
}
