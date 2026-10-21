import SettingsNav from '@/Components/Layout/SettingsNav';
import TextInput from '@/Components/TextInput';
import Button from '@/Components/UI/Button';
import { Card, CardHeader, CardTitle } from '@/Components/UI/Card';
import { Avatar, IconButton, PageHeader, StatusBadge, TagPill } from '@/Components/UI/Elements';
import { useNotifications } from '@/hooks/useNotifications';
import AppShell from '@/Layouts/AppShell';
import { Head, router } from '@inertiajs/react';
import {
  Crown,
  Mail,
  MoreHorizontal,
  Plus,
  RefreshCw,
  Save,
  Send,
  Shield,
  Trash2,
  User,
  X,
} from 'lucide-react';
import { useMemo, useState } from 'react';

interface Member {
  id: number;
  name: string;
  email: string;
  role: string;
  joined_at: string;
  is_owner: boolean;
}

interface PendingInvite {
  id: number;
  email: string;
  role: string;
  invited_at?: string | null;
  expires_at?: string | null;
}

interface RoleDefinition {
  id: number | string;
  name: string;
  key: string;
  description?: string | null;
  permissions: string[];
  is_system: boolean;
  is_owner?: boolean;
  members_count?: number;
  pending_invites_count?: number;
}

interface PermissionDefinition {
  key: string;
  label: string;
  group?: string;
  owner_only?: boolean;
  sensitive?: boolean;
}

const roleTone = (role: string, isOwner = false): 'warning' | 'info' | 'muted' | 'success' => {
  if (isOwner || role === 'owner') return 'warning';
  if (role === 'admin') return 'info';
  if (role === 'member') return 'muted';
  return 'success';
};

function formatDate(value?: string | null) {
  if (!value) return 'Recently';
  return new Date(value).toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function roleName(roles: RoleDefinition[], role: string, isOwner = false) {
  if (isOwner) return 'Owner';
  return roles.find((item) => item.key === role)?.name ?? role;
}

function permissionGroup(permission: PermissionDefinition) {
  if (permission.group) return permission.group;
  if (permission.key.includes('.')) return permission.key.split('.')[0].replace(/_/g, ' ');
  return 'core';
}

function permissionTone(permission: string): 'muted' | 'warning' | 'danger' | 'info' {
  if (permission.endsWith('.owner')) return 'warning';
  if (permission === 'chats.delete' || permission.includes('delete')) return 'danger';
  if (permission.includes('export')) return 'info';
  return 'muted';
}

function Drawer({
  title,
  description,
  open,
  onClose,
  children,
}: {
  title: string;
  description?: string;
  open: boolean;
  onClose: () => void;
  children: React.ReactNode;
}) {
  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-[250] flex justify-end bg-black/45 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
    >
      <button type="button" className="absolute inset-0" aria-label="Close" onClick={onClose} />
      <aside className="relative flex h-full w-full max-w-xl flex-col overflow-hidden border-l border-gray-200 bg-white shadow-pop dark:border-ui-dark-border dark:bg-ui-dark-surface">
        <div className="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-5 dark:border-ui-dark-border">
          <div>
            <h2 className="text-lg font-semibold text-ui-text dark:text-ui-dark-text">{title}</h2>
            {description && (
              <p className="mt-1 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                {description}
              </p>
            )}
          </div>
          <IconButton aria-label="Close" onClick={onClose}>
            <X className="h-4 w-4" />
          </IconButton>
        </div>
        <div className="flex-1 overflow-y-auto px-6 py-5">{children}</div>
      </aside>
    </div>
  );
}

export default function TeamIndex({
  members,
  can_manage,
  can_manage_roles,
  current_user_id,
  pending_invites,
  roles,
  permissions,
}: {
  account: any;
  members: Member[];
  can_manage: boolean;
  can_manage_roles: boolean;
  current_user_id: number;
  pending_invites: PendingInvite[];
  roles: RoleDefinition[];
  permissions: PermissionDefinition[];
}) {
  const { confirm, toast } = useNotifications();
  const [inviteOpen, setInviteOpen] = useState(false);
  const [roleOpen, setRoleOpen] = useState(false);
  const [inviteEmail, setInviteEmail] = useState('');
  const [inviteRole, setInviteRole] = useState('member');
  const [processingInvite, setProcessingInvite] = useState(false);
  const [openMenu, setOpenMenu] = useState<string | null>(null);
  const [roleForm, setRoleForm] = useState({
    id: null as number | null,
    name: '',
    description: '',
    permissions: ['inbox'] as string[],
  });

  const assignableRoles = useMemo(() => roles.filter((role) => !role.is_owner), [roles]);
  const groupedPermissions = useMemo(() => {
    return permissions.reduce<Record<string, PermissionDefinition[]>>((groups, permission) => {
      const group = permissionGroup(permission);
      groups[group] = [...(groups[group] || []), permission];
      return groups;
    }, {});
  }, [permissions]);
  const activeMembers = members.length;

  const resetRoleForm = () =>
    setRoleForm({ id: null, name: '', description: '', permissions: ['inbox'] });

  const togglePermission = (permission: string) => {
    setRoleForm((current) => ({
      ...current,
      permissions: current.permissions.includes(permission)
        ? current.permissions.filter((item) => item !== permission)
        : [...current.permissions, permission],
    }));
  };

  const handleInvite = () => {
    if (!inviteEmail.trim()) {
      toast.error('Please enter an email');
      return;
    }

    setProcessingInvite(true);
    router.post(
      route('app.team.invite', {}) as string,
      { email: inviteEmail.trim(), role: inviteRole },
      {
        preserveScroll: true,
        onSuccess: () => {
          setInviteEmail('');
          setInviteRole('member');
          setInviteOpen(false);
        },
        onError: (errors) =>
          toast.error(
            (errors.email || errors.role || errors.error || 'Failed to invite agent') as string
          ),
        onFinish: () => setProcessingInvite(false),
      }
    );
  };

  const updateMemberRole = (member: Member, role: string) => {
    router.post(
      route('app.team.update-role', { user: member.id }) as string,
      { role },
      {
        preserveScroll: true,
        onError: () => toast.error('Failed to update role'),
      }
    );
  };

  const handleRemove = async (member: Member) => {
    const confirmed = await confirm({
      title: 'Remove agent',
      message: `Remove ${member.name} from this workspace?`,
      variant: 'danger',
      confirmText: 'Remove',
    });
    if (!confirmed) return;

    router.delete(route('app.team.remove', { user: member.id }) as string, {
      preserveScroll: true,
      onError: () => toast.error('Failed to remove member'),
    });
  };

  const handleRevokeInvite = async (invite: PendingInvite) => {
    const confirmed = await confirm({
      title: 'Revoke invitation',
      message: `Revoke invitation for ${invite.email}?`,
      variant: 'danger',
      confirmText: 'Revoke',
    });
    if (!confirmed) return;

    router.delete(route('app.team.invites.revoke', { invitation: invite.id }) as string, {
      preserveScroll: true,
      onError: () => toast.error('Failed to revoke invitation'),
    });
  };

  const handleResendInvite = async (invite: PendingInvite) => {
    const confirmed = await confirm({
      title: 'Resend invitation',
      message: `Resend invitation to ${invite.email}?`,
      variant: 'info',
      confirmText: 'Resend',
    });
    if (!confirmed) return;

    router.post(
      route('app.team.invites.resend', { invitation: invite.id }) as string,
      {},
      {
        preserveScroll: true,
        onError: () => toast.error('Failed to resend invitation'),
      }
    );
  };

  const openCreateRole = () => {
    resetRoleForm();
    setRoleOpen(true);
  };

  const openEditRole = (role: RoleDefinition) => {
    if (role.is_system || typeof role.id !== 'number') return;
    setRoleForm({
      id: role.id,
      name: role.name,
      description: role.description ?? '',
      permissions: role.permissions.length ? role.permissions : ['inbox'],
    });
    setRoleOpen(true);
  };

  const saveRole = () => {
    if (!roleForm.name.trim()) {
      toast.error('Role name is required');
      return;
    }

    const payload = {
      name: roleForm.name.trim(),
      description: roleForm.description.trim() || null,
      permissions: roleForm.permissions.length ? roleForm.permissions : ['inbox'],
    };

    const options = {
      preserveScroll: true,
      onSuccess: () => {
        setRoleOpen(false);
        resetRoleForm();
      },
      onError: () => toast.error('Failed to save role'),
    };

    if (roleForm.id) {
      router.patch(
        route('app.team.roles.update', { role: roleForm.id }) as string,
        payload,
        options
      );
    } else {
      router.post(route('app.team.roles.store', {}) as string, payload, options);
    }
  };

  const deleteRole = async (role: RoleDefinition) => {
    if (role.is_system || typeof role.id !== 'number') return;
    const confirmed = await confirm({
      title: 'Delete role',
      message: `Delete ${role.name}? Members must be moved before a role can be deleted.`,
      variant: 'danger',
      confirmText: 'Delete',
    });
    if (!confirmed) return;

    router.delete(route('app.team.roles.delete', { role: role.id }) as string, {
      preserveScroll: true,
      onError: () => toast.error('Failed to delete role'),
    });
  };

  return (
    <AppShell>
      <Head title="Team" />
      <div className="module-page max-w-[1220px]">
        <SettingsNav active="team" className="mb-0" />
        <PageHeader
          title="Team"
          description="Invite people and choose what they can do."
          actions={
            <>
              {can_manage_roles && (
                <Button variant="ghost" onClick={openCreateRole}>
                  <Shield className="h-4 w-4" /> New role
                </Button>
              )}
              {can_manage && (
                <Button onClick={() => setInviteOpen(true)}>
                  <Plus className="h-4 w-4" /> Invite member
                </Button>
              )}
            </>
          }
        />

        <div className="grid gap-3 xl:grid-cols-[minmax(0,1fr)_360px]">
          <Card className="overflow-hidden p-0">
            <CardHeader>
              <CardTitle>
                Members{' '}
                <span className="font-normal text-ui-text-muted dark:text-ui-dark-text-muted">
                  · {activeMembers}
                </span>
              </CardTitle>
            </CardHeader>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-gray-50/60 text-left text-[11px] uppercase tracking-wider text-ui-text-muted dark:bg-ui-dark-surface-2 dark:text-ui-dark-text-muted">
                    <th className="px-4 py-2 font-medium">Member</th>
                    <th className="px-4 py-2 font-medium">Role</th>
                    <th className="px-4 py-2 font-medium">Status</th>
                    <th className="w-12 px-4 py-2 font-medium" />
                  </tr>
                </thead>
                <tbody>
                  {members.map((member) => (
                    <tr
                      key={member.id}
                      className="border-t border-gray-100 transition hover:bg-gray-50/60 dark:border-ui-dark-border dark:hover:bg-ui-dark-surface-2/70"
                    >
                      <td className="px-4 py-2.5">
                        <div className="flex items-center gap-3">
                          <Avatar
                            name={member.name}
                            size="md"
                            status={member.id === current_user_id ? 'online' : undefined}
                          />
                          <div className="min-w-0">
                            <div className="flex items-center gap-2">
                              <p className="font-medium text-ui-text dark:text-ui-dark-text">
                                {member.name}
                              </p>
                              {member.id === current_user_id && (
                                <TagPill tone="success" className="py-0.5 text-[10px]">
                                  You
                                </TagPill>
                              )}
                            </div>
                            <p className="truncate text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                              {member.email}
                            </p>
                          </div>
                        </div>
                      </td>
                      <td className="px-4 py-2.5">
                        {can_manage && !member.is_owner ? (
                          <select
                            value={member.role}
                            onChange={(event) => updateMemberRole(member, event.target.value)}
                            className="h-9 rounded-btn border border-gray-200 bg-white px-3 text-sm text-ui-text outline-none dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
                          >
                            {assignableRoles.map((role) => (
                              <option key={role.key} value={role.key}>
                                {role.name}
                              </option>
                            ))}
                          </select>
                        ) : (
                          <StatusBadge tone={roleTone(member.role, member.is_owner)}>
                            {member.is_owner ? (
                              <Crown className="h-3.5 w-3.5" />
                            ) : member.role === 'admin' ? (
                              <Shield className="h-3.5 w-3.5" />
                            ) : (
                              <User className="h-3.5 w-3.5" />
                            )}
                            {roleName(roles, member.role, member.is_owner)}
                          </StatusBadge>
                        )}
                      </td>
                      <td className="px-4 py-2.5">
                        <StatusBadge tone="success" dot>
                          Active
                        </StatusBadge>
                      </td>
                      <td className="relative px-4 py-2.5 text-right">
                        {can_manage && !member.is_owner && member.id !== current_user_id ? (
                          <>
                            <IconButton
                              size="sm"
                              aria-label="Member actions"
                              onClick={() =>
                                setOpenMenu(
                                  openMenu === `member-${member.id}` ? null : `member-${member.id}`
                                )
                              }
                            >
                              <MoreHorizontal className="h-4 w-4" />
                            </IconButton>
                            {openMenu === `member-${member.id}` && (
                              <div className="absolute right-4 z-20 mt-2 w-40 overflow-hidden rounded-card border border-gray-200 bg-white py-1 text-left shadow-pop dark:border-ui-dark-border dark:bg-ui-dark-surface">
                                <button
                                  type="button"
                                  onClick={() => {
                                    setOpenMenu(null);
                                    void handleRemove(member);
                                  }}
                                  className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-500/10"
                                >
                                  <Trash2 className="h-4 w-4" /> Remove
                                </button>
                              </div>
                            )}
                          </>
                        ) : null}
                      </td>
                    </tr>
                  ))}
                  {can_manage &&
                    pending_invites?.map((invite) => (
                      <tr
                        key={`invite-${invite.id}`}
                        className="border-t border-gray-100 transition hover:bg-gray-50/60 dark:border-ui-dark-border dark:hover:bg-ui-dark-surface-2/70"
                      >
                        <td className="px-4 py-2.5">
                          <div className="flex items-center gap-3">
                            <Avatar name={invite.email} size="md" />
                            <div className="min-w-0">
                              <p className="font-medium text-ui-text dark:text-ui-dark-text">
                                {invite.email}
                              </p>
                              <p className="truncate text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                                Invited {formatDate(invite.invited_at)}
                              </p>
                            </div>
                          </div>
                        </td>
                        <td className="px-4 py-2.5">
                          <TagPill tone="muted">{roleName(roles, invite.role)}</TagPill>
                        </td>
                        <td className="px-4 py-2.5">
                          <StatusBadge tone="warning" dot>
                            Pending
                          </StatusBadge>
                        </td>
                        <td className="relative px-4 py-2.5 text-right">
                          <IconButton
                            size="sm"
                            aria-label="Invite actions"
                            onClick={() =>
                              setOpenMenu(
                                openMenu === `invite-${invite.id}` ? null : `invite-${invite.id}`
                              )
                            }
                          >
                            <MoreHorizontal className="h-4 w-4" />
                          </IconButton>
                          {openMenu === `invite-${invite.id}` && (
                            <div className="absolute right-4 z-20 mt-2 w-40 overflow-hidden rounded-card border border-gray-200 bg-white py-1 text-left shadow-pop dark:border-ui-dark-border dark:bg-ui-dark-surface">
                              <button
                                type="button"
                                onClick={() => {
                                  setOpenMenu(null);
                                  void handleResendInvite(invite);
                                }}
                                className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-ui-text hover:bg-gray-50 dark:text-ui-dark-text dark:hover:bg-ui-dark-surface-2"
                              >
                                <RefreshCw className="h-4 w-4" /> Resend
                              </button>
                              <button
                                type="button"
                                onClick={() => {
                                  setOpenMenu(null);
                                  void handleRevokeInvite(invite);
                                }}
                                className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-500/10"
                              >
                                <Trash2 className="h-4 w-4" /> Revoke
                              </button>
                            </div>
                          )}
                        </td>
                      </tr>
                    ))}
                </tbody>
              </table>
            </div>
          </Card>

          <div className="space-y-3">
            {roles.map((role) => (
              <Card key={role.key} className="p-4">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <div className="flex flex-wrap items-center gap-2">
                      <h3 className="font-semibold text-ui-text dark:text-ui-dark-text">
                        {role.name}
                      </h3>
                      <StatusBadge tone={roleTone(role.key, role.is_owner)}>
                        {role.is_system ? 'System' : 'Custom'}
                      </StatusBadge>
                    </div>
                    <p className="mt-1 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                      {role.description || 'Custom workspace access profile.'}
                    </p>
                  </div>
                  {can_manage_roles && !role.is_system && (
                    <div className="flex gap-1">
                      <Button size="xs" variant="secondary" onClick={() => openEditRole(role)}>
                        Edit
                      </Button>
                      <IconButton
                        size="sm"
                        variant="danger"
                        aria-label="Delete role"
                        onClick={() => void deleteRole(role)}
                      >
                        <Trash2 className="h-4 w-4" />
                      </IconButton>
                    </div>
                  )}
                </div>
                <div className="mt-3 flex flex-wrap gap-1.5">
                  {role.permissions.slice(0, 5).map((permission) => (
                    <TagPill key={permission} tone={permissionTone(permission)}>
                      {permissions.find((item) => item.key === permission)?.label ?? permission}
                    </TagPill>
                  ))}
                  {role.permissions.length > 5 && (
                    <TagPill tone="info">+{role.permissions.length - 5}</TagPill>
                  )}
                </div>
                <div className="mt-3 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                  {role.members_count ?? 0} active · {role.pending_invites_count ?? 0} invited
                </div>
              </Card>
            ))}
          </div>
        </div>
      </div>

      <Drawer
        title="Create agent"
        description="Invite a staff member and assign their workspace role."
        open={inviteOpen}
        onClose={() => setInviteOpen(false)}
      >
        <div className="space-y-4">
          <div>
            <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
              Email address
            </label>
            <div className="relative mt-2">
              <Mail className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ui-text-muted dark:text-ui-dark-text-muted" />
              <TextInput
                type="email"
                value={inviteEmail}
                onChange={(event) => setInviteEmail(event.target.value)}
                onKeyDown={(event) => {
                  if (event.key === 'Enter') handleInvite();
                }}
                placeholder="agent@company.com"
                className="h-11 w-full pl-9"
              />
            </div>
          </div>
          <div>
            <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">Role</label>
            <select
              value={inviteRole}
              onChange={(event) => setInviteRole(event.target.value)}
              className="mt-2 h-11 w-full rounded-btn border border-gray-200 bg-white px-3 text-sm text-ui-text outline-none dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
            >
              {assignableRoles.map((role) => (
                <option key={role.key} value={role.key}>
                  {role.name}
                </option>
              ))}
            </select>
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" onClick={() => setInviteOpen(false)}>
              Cancel
            </Button>
            <Button onClick={handleInvite} disabled={processingInvite}>
              <Send className="h-4 w-4" /> Send invite
            </Button>
          </div>
        </div>
      </Drawer>

      <Drawer
        title={roleForm.id ? 'Edit role' : 'Create role'}
        description="Choose exactly what staff can access in this workspace."
        open={roleOpen}
        onClose={() => setRoleOpen(false)}
      >
        <div className="space-y-4">
          <div>
            <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
              Role name
            </label>
            <TextInput
              value={roleForm.name}
              onChange={(event) => setRoleForm({ ...roleForm, name: event.target.value })}
              placeholder="Sales agent"
              className="mt-2 h-11 w-full"
            />
          </div>
          <div>
            <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
              Description
            </label>
            <textarea
              value={roleForm.description}
              onChange={(event) => setRoleForm({ ...roleForm, description: event.target.value })}
              placeholder="What this role is used for"
              className="mt-2 min-h-24 w-full rounded-btn border border-gray-200 bg-white px-3 py-2 text-sm text-ui-text outline-none dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
            />
          </div>
          <div>
            <p className="text-sm font-medium text-ui-text dark:text-ui-dark-text">Permissions</p>
            <div className="mt-3 space-y-4">
              {Object.entries(groupedPermissions).map(([group, items]) => (
                <div
                  key={group}
                  className="rounded-card border border-gray-100 p-3 dark:border-ui-dark-border"
                >
                  <div className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-ui-text-muted dark:text-ui-dark-text-muted">
                    {group}
                  </div>
                  <div className="grid gap-2 sm:grid-cols-2">
                    {items.map((permission) => (
                      <label
                        key={permission.key}
                        className="flex cursor-pointer items-start gap-2 rounded-card border border-gray-200 px-3 py-2 text-sm text-ui-text dark:border-ui-dark-border dark:text-ui-dark-text"
                      >
                        <input
                          type="checkbox"
                          checked={roleForm.permissions.includes(permission.key)}
                          onChange={() => togglePermission(permission.key)}
                          className="mt-0.5 h-4 w-4 rounded border-gray-300 text-ui-green focus:ring-ui-green"
                        />
                        <span className="min-w-0 flex-1">
                          <span className="block font-medium">{permission.label}</span>
                          <span className="mt-1 flex flex-wrap gap-1">
                            {(permission.owner_only || permission.key.endsWith('.owner')) && (
                              <TagPill tone="warning">Owner only</TagPill>
                            )}
                            {(permission.sensitive ||
                              permissionTone(permission.key) === 'danger') && (
                              <TagPill tone="danger">Sensitive</TagPill>
                            )}
                            {permission.key.includes('export') && (
                              <TagPill tone="info">Export</TagPill>
                            )}
                          </span>
                        </span>
                      </label>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" onClick={() => setRoleOpen(false)}>
              Cancel
            </Button>
            <Button onClick={saveRole}>
              <Save className="h-4 w-4" /> Save role
            </Button>
          </div>
        </div>
      </Drawer>
    </AppShell>
  );
}
