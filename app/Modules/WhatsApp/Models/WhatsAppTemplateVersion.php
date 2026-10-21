<?php

namespace App\Modules\WhatsApp\Models;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppTemplateVersion extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_template_versions';

    protected $fillable = [
        'account_id',
        'whatsapp_template_id',
        'version',
        'change_notes',
        'components',
        'created_by'];

    protected $casts = [
        'components' => 'array'];

    /**
     * Get the account.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the template.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whatsapp_template_id');
    }

    /**
     * Get the user who created this version.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
