<?php

namespace App\Modules\Contacts\Models;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactCustomField extends Model
{
    use HasFactory;

    protected $table = 'contact_custom_fields';

    protected $fillable = [
        'account_id',
        'name',
        'type',
        'options',
        'required',
        'order'];

    protected $casts = [
        'options' => 'array',
        'required' => 'boolean'];

    /**
     * Get the account.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
