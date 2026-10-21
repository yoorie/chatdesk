<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AccountRole extends Model
{
    public const PERMISSIONS = [
        'inbox' => 'Inbox',
        'contacts.view' => 'View contacts',
        'contacts.export' => 'Export contacts',
        'contacts' => 'Contacts management',
        'templates' => 'Templates',
        'campaigns' => 'Campaigns',
        'automation' => 'Automation',
        'analytics' => 'Analytics',
        'billing' => 'Billing',
        'billing.owner' => 'Billing owner controls',
        'api_keys.owner' => 'API keys and credentials',
        'payments.approve' => 'Approve payments',
        'chats.delete' => 'Delete chats',
        'settings' => 'Workspace settings',
        'team' => 'Team management',
        'roles' => 'Roles & permissions',
    ];

    protected $fillable = [
        'account_id',
        'name',
        'key',
        'description',
        'permissions',
        'is_system',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_system' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public static function normalizeKey(string $name): string
    {
        $key = Str::slug($name, '_');

        return $key !== '' ? $key : 'custom_role';
    }

    public static function defaultRoles(?Account $account = null): array
    {
        $ownerPermissions = array_keys(self::PERMISSIONS);

        $ownerCount = $account?->owner && ! $account->owner->isSuperAdmin() ? 1 : 0;

        return [
            [
                'id' => 'system-owner',
                'name' => 'Owner',
                'key' => 'owner',
                'description' => 'Full workspace ownership and billing control.',
                'permissions' => $ownerPermissions,
                'is_system' => true,
                'is_owner' => true,
                'members_count' => $ownerCount,
            ],
            [
                'id' => 'system-admin',
                'name' => 'Admin',
                'key' => 'admin',
                'description' => 'Manage workspace operations, settings, team, and roles.',
                'permissions' => $ownerPermissions,
                'is_system' => true,
                'is_owner' => false,
                'members_count' => $account ? $account->users()
                    ->where('users.is_platform_admin', false)
                    ->wherePivot('role', 'admin')
                    ->count() : 0,
            ],
            [
                'id' => 'system-member',
                'name' => 'Agent',
                'key' => 'member',
                'description' => 'Inbox-first access for support and sales agents.',
                'permissions' => ['inbox'],
                'is_system' => true,
                'is_owner' => false,
                'members_count' => $account ? $account->users()
                    ->where('users.is_platform_admin', false)
                    ->wherePivot('role', 'member')
                    ->count() : 0,
            ],
        ];
    }

    public static function permissionLabels(array $permissions): array
    {
        return collect($permissions)
            ->filter(fn ($permission) => isset(self::PERMISSIONS[$permission]))
            ->map(fn ($permission) => [
                'key' => $permission,
                'label' => self::PERMISSIONS[$permission],
            ])
            ->values()
            ->all();
    }
}
