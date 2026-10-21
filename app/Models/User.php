<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'google_id',
        'avatar_url',
        'phone',
        'country_code',
        'job_title',
        'locale',
        'timezone',
        'password',
        'is_platform_admin',
        'notify_assignment_enabled',
        'notify_mention_enabled',
        'notify_sound_enabled',
        'notify_billing_enabled',
        'notify_waba_enabled',
        'notify_automation_enabled',
        'notify_leads_enabled',
        'notify_templates_enabled',
        'notify_email_enabled',
        'notify_in_app_enabled',
        'quiet_hours_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
        'ai_suggestions_enabled',
        'two_factor_secret',
        'two_factor_enabled_at',
        'force_password_reset_at',
        'sessions_revoked_at'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'remember_token'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'notify_assignment_enabled' => 'boolean',
            'notify_mention_enabled' => 'boolean',
            'notify_sound_enabled' => 'boolean',
            'notify_billing_enabled' => 'boolean',
            'notify_waba_enabled' => 'boolean',
            'notify_automation_enabled' => 'boolean',
            'notify_leads_enabled' => 'boolean',
            'notify_templates_enabled' => 'boolean',
            'notify_email_enabled' => 'boolean',
            'notify_in_app_enabled' => 'boolean',
            'quiet_hours_enabled' => 'boolean',
            'ai_suggestions_enabled' => 'boolean',
            'two_factor_enabled_at' => 'datetime',
            'force_password_reset_at' => 'datetime',
            'sessions_revoked_at' => 'datetime'];
    }

    /**
     * Get the accounts that the user belongs to.
     */
    public function accounts()
    {
        return $this->belongsToMany(Account::class, 'account_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the accounts that the user owns.
     */
    public function ownedAccounts()
    {
        return $this->hasMany(Account::class, 'owner_id');
    }

    /**
     * Check if user is a super admin (platform owner).
     */
    public function isSuperAdmin(): bool
    {
        return $this->is_platform_admin === true;
    }

    /**
     * Alias for isSuperAdmin (backward compatibility).
     */
    public function isPlatformAdmin(): bool
    {
        return $this->isSuperAdmin();
    }

    public function scopeNotPlatformAdmin(Builder $query): Builder
    {
        return $query->where('is_platform_admin', false);
    }

    /**
     * Check if user can access a account (platform admin or account member/owner).
     */
    public function canAccessAccount(Account $account): bool
    {
        // Platform admins can access all accounts
        if ($this->isPlatformAdmin()) {
            return true;
        }

        // Check if user is owner
        if ((int) $account->owner_id === (int) $this->id) {
            return true;
        }

        // Check if user is a member
        return $account->users()->where('user_id', $this->id)->exists();
    }
}
