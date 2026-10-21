<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountModule extends Model
{
    protected $fillable = [
        'account_id',
        'module_key',
        'enabled',
        'config'];

    protected $casts = [
        'enabled' => 'boolean',
        'config' => 'array'];

    /**
     * Get the account.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the module.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_key', 'key');
    }
}
