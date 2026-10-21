<?php

namespace App\Modules\Contacts\Models;

use App\Models\Account;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContactTag extends Model
{
    use HasFactory;

    protected $table = 'contact_tags';

    protected $fillable = [
        'account_id',
        'name',
        'color',
        'description'];

    /**
     * Get the account.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get contacts with this tag.
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(WhatsAppContact::class, 'contact_tag_contact', 'contact_tag_id', 'contact_id')
            ->withTimestamps();
    }

    /**
     * Get contact count.
     */
    public function getContactCountAttribute(): int
    {
        return $this->contacts()->count();
    }
}
