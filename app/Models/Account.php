<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $appends = [
        'workspace_type_label',
    ];

    protected $fillable = [
        'name',
        'slug',
        'workspace_type',
        'industry',
        'timezone',
        'logo_path',
        'billing_name',
        'billing_email',
        'billing_gstin',
        'billing_address_line1',
        'billing_address_line2',
        'billing_city',
        'billing_state',
        'billing_state_code',
        'billing_postal_code',
        'billing_country',
        'owner_id',
        'status',
        'disabled_reason',
        'disabled_at',
        'auto_assign_enabled',
        'auto_assign_strategy',
        'welcome_message_enabled',
        'welcome_message_body',
        'auto_close_conversations_enabled',
        'auto_close_after_hours'];

    /**
     * Get the owner of the account.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get all users that belong to this account.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'account_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function roles(): HasMany
    {
        return $this->hasMany(AccountRole::class);
    }

    /**
     * Get all modules for this account.
     */
    public function modules(): HasMany
    {
        return $this->hasMany(AccountModule::class);
    }

    /**
     * Get the account subscription.
     */
    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }

    public function billingEvents(): HasMany
    {
        return $this->hasMany(BillingEvent::class);
    }

    /**
     * Get account addons.
     */
    public function addons()
    {
        return $this->hasMany(AccountAddon::class)->where('status', 'active');
    }

    /**
     * Get account usage records.
     */
    public function usage()
    {
        return $this->hasMany(AccountUsage::class);
    }

    public function wallet()
    {
        return $this->hasOne(AccountWallet::class);
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function integrations()
    {
        return $this->hasMany(AccountIntegration::class);
    }

    public function apiKeys()
    {
        return $this->hasMany(AccountApiKey::class);
    }

    public function apiRequestLogs()
    {
        return $this->hasMany(AccountApiRequestLog::class);
    }

    public function webhookEndpoints()
    {
        return $this->hasMany(AccountWebhookEndpoint::class);
    }

    public function webhookDeliveries()
    {
        return $this->hasMany(AccountWebhookDelivery::class);
    }

    public function catalogProducts()
    {
        return $this->hasMany(AccountCatalogProduct::class);
    }

    public function mediaAssets()
    {
        return $this->hasMany(AccountMediaAsset::class);
    }

    public function ecommerceOrders()
    {
        return $this->hasMany(AccountEcommerceOrder::class);
    }

    public function surveys()
    {
        return $this->hasMany(AccountSurvey::class);
    }

    public function appointments()
    {
        return $this->hasMany(AccountAppointment::class);
    }

    public function metaLeads()
    {
        return $this->hasMany(AccountMetaLead::class);
    }

    public function deals()
    {
        return $this->hasMany(AccountDeal::class);
    }

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'disabled_at' => 'datetime',
            'auto_assign_enabled' => 'boolean',
            'welcome_message_enabled' => 'boolean',
            'auto_close_conversations_enabled' => 'boolean',
            'auto_close_after_hours' => 'integer'];
    }

    public static function workspaceTypes(): array
    {
        return [
            'business' => 'Business',
            'agency' => 'Agency',
            'client' => 'Client',
            'branch' => 'Branch',
            'project' => 'Project',
        ];
    }

    public function workspaceTypeLabel(): string
    {
        return static::workspaceTypes()[$this->workspace_type ?: 'business'] ?? 'Business';
    }

    public function getWorkspaceTypeLabelAttribute(): string
    {
        return $this->workspaceTypeLabel();
    }

    /**
     * Check if account is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if a user is the owner of this account.
     */
    public function isOwnedBy(User $user): bool
    {
        // Primary check: owner_id
        if ($this->owner_id && (int) $this->owner_id === (int) $user->id) {
            return true;
        }

        // Fallback for accounts where ownership is recorded only in the pivot table.
        $accountUser = \App\Models\AccountUser::where('account_id', $this->id)
            ->where('user_id', $user->id)
            ->where('role', 'owner')
            ->first();

        if ($accountUser) {
            // Fix: Set owner_id if it's missing
            if (! $this->owner_id) {
                $this->update(['owner_id' => $user->id]);
            }

            return true;
        }

        return false;
    }

    /**
     * Check if account is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Check if account is disabled.
     */
    public function isDisabled(): bool
    {
        return $this->status === 'disabled';
    }

    /**
     * Disable the account.
     */
    public function disable(?string $reason = null): void
    {
        $this->update([
            'status' => 'disabled',
            'disabled_reason' => $reason,
            'disabled_at' => now()]);
    }

    /**
     * Enable the account.
     */
    public function enable(): void
    {
        $this->update([
            'status' => 'active',
            'disabled_reason' => null,
            'disabled_at' => null]);
    }

    /**
     * Get assignable agents (owner + members with admin/member role) for chat assignment.
     * Returns array of [id, name, email, role] for display in dropdowns.
     */
    public function getAssignableAgents(): \Illuminate\Support\Collection
    {
        $agents = collect();

        if ($this->owner_id && $this->owner && ! $this->owner->isSuperAdmin()) {
            $agents->push([
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
                'role' => 'owner',
            ]);
        }

        $members = $this->users()
            ->where('users.is_platform_admin', false)
            ->get(['users.id', 'users.name', 'users.email', 'account_users.role'])
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot?->role ?? 'member',
            ]);

        return $agents->merge($members)->unique('id')->values();
    }

    /**
     * Get assignable agent user IDs (for auto-assign and validation).
     */
    public function getAssignableAgentIds(): array
    {
        return $this->getAssignableAgents()->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * Generate a unique slug from the name.
     */
    public static function generateSlug(string $name): string
    {
        $slug = \Illuminate\Support\Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
