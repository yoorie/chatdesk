import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TwoFactorSetupPanel from '@/Components/Security/TwoFactorSetupPanel';
import TextInput from '@/Components/TextInput';
import { Alert } from '@/Components/UI/Alert';
import Button from '@/Components/UI/Button';
import { Card } from '@/Components/UI/Card';
import { Modal } from '@/Components/UI/Elements';
import { useNotifications } from '@/hooks/useNotifications';
import { Transition } from '@headlessui/react';
import { router, useForm, usePage } from '@inertiajs/react';
import {
  CheckCircle2,
  Eye,
  EyeOff,
  KeyRound,
  Lock,
  Save,
  ShieldCheck,
  Smartphone,
  Trash2,
} from 'lucide-react';
import { useState } from 'react';

export default function SecurityTab() {
  const { auth, security } = usePage().props as any;
  const user = auth?.user;
  const { confirm, toast } = useNotifications();
  const [visiblePasswords, setVisiblePasswords] = useState<Record<string, boolean>>({});
  const [deletePasswordOpen, setDeletePasswordOpen] = useState(false);
  const [deletePassword, setDeletePassword] = useState('');
  const { data, setData, put, processing, errors, reset, recentlySuccessful } = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
  });
  const twoFactorForm = useForm({ code: '', password: '' });
  const sessionForm = useForm({ password: '' });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    put(route('password.update'), {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Password updated successfully');
        reset();
      },
      onError: () => {
        toast.error('Failed to update password');
      },
    });
  };

  const deleteAccount = async () => {
    const confirmed = await confirm({
      title: 'Delete Account',
      message:
        'Are you sure you want to delete your account? This action cannot be undone. All of your data will be permanently deleted.',
      variant: 'danger',
      confirmText: 'Delete Account',
    });

    if (!confirmed) return;

    setDeletePassword('');
    setDeletePasswordOpen(true);
  };

  const confirmDeleteAccount = () => {
    if (!deletePassword) return;

    router.delete(route('profile.destroy'), {
      data: { password: deletePassword },
      onSuccess: () => toast.success('Account deleted successfully'),
      onError: () => toast.error('Failed to delete account'),
    });
  };

  return (
    <div className="space-y-6">
      <Card className="p-5">
        <form onSubmit={submit} className="space-y-5">
          <div className="mb-1 flex items-start gap-3">
            <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-ui-text-muted dark:bg-slate-700 dark:text-ui-dark-text-muted">
              <Lock className="h-4 w-4" />
            </span>
            <div>
              <div className="text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                Password
              </div>
              <p className="mt-0.5 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                Change the password used to sign in to this account.
              </p>
            </div>
          </div>

          {[
            ['current_password', 'Current password', 'current-password', 'Enter current password'],
            ['password', 'New password', 'new-password', 'Enter new password'],
            ['password_confirmation', 'Confirm password', 'new-password', 'Confirm new password'],
          ].map(([field, label, autocomplete, placeholder]) => (
            <div key={field}>
              <InputLabel
                htmlFor={field}
                value={label}
                className="mb-1 text-xs font-medium text-ui-text dark:text-ui-dark-text"
              />
              <div className="relative">
                <Lock className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <TextInput
                  id={field}
                  type={visiblePasswords[field] ? 'text' : 'password'}
                  value={data[field as keyof typeof data]}
                  onChange={(e) => setData(field as keyof typeof data, e.target.value)}
                  className="block h-9 w-full rounded-btn border-gray-200 pl-9 pr-10 text-sm focus:border-ui-green focus:ring-ui-green/15 dark:border-slate-600 dark:bg-slate-900"
                  autoComplete={autocomplete}
                  placeholder={placeholder}
                />
                <button
                  type="button"
                  onClick={() =>
                    setVisiblePasswords((current) => ({ ...current, [field]: !current[field] }))
                  }
                  className="absolute right-2 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-md text-gray-400 transition hover:bg-gray-100 hover:text-ui-text dark:hover:bg-slate-800 dark:hover:text-ui-dark-text"
                  aria-label={visiblePasswords[field] ? `Hide ${label}` : `Show ${label}`}
                >
                  {visiblePasswords[field] ? (
                    <EyeOff className="h-4 w-4" />
                  ) : (
                    <Eye className="h-4 w-4" />
                  )}
                </button>
              </div>
              <InputError message={errors[field as keyof typeof errors]} className="mt-2 text-xs" />
            </div>
          ))}

          <div className="flex items-center justify-end gap-4 border-t border-gray-100 pt-4 dark:border-slate-700">
            <Button type="submit" disabled={processing}>
              {processing ? (
                'Updating...'
              ) : (
                <>
                  <Save className="h-4 w-4" />
                  Update password
                </>
              )}
            </Button>
            <Transition
              show={recentlySuccessful}
              enter="transition ease-in-out"
              enterFrom="opacity-0"
              leave="transition ease-in-out"
              leaveTo="opacity-0"
            >
              <div className="flex items-center gap-2 text-sm text-emerald-600 dark:text-emerald-300">
                <CheckCircle2 className="h-4 w-4" />
                Updated
              </div>
            </Transition>
          </div>
        </form>
      </Card>

      <Card className="p-5">
        <div className="mb-4 flex items-start gap-3">
          <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-ui-green-soft text-ui-green-dark dark:bg-ui-green/10 dark:text-ui-green">
            <ShieldCheck className="h-4 w-4" />
          </span>
          <div>
            <div className="text-sm font-semibold text-ui-text dark:text-ui-dark-text">
              Two-factor authentication
            </div>
            <p className="mt-0.5 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
              Use an authenticator app for owner and admin account protection.
            </p>
          </div>
        </div>
        {user?.two_factor_enabled ? (
          <div className="space-y-3">
            <div className="rounded-card border border-emerald-100 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200">
              2FA is enabled.
            </div>
            <div className="flex flex-col gap-2 sm:flex-row">
              <input
                type="password"
                value={twoFactorForm.data.password}
                onChange={(event) => twoFactorForm.setData('password', event.target.value)}
                placeholder="Current password"
                className="ui-input flex-1"
              />
              <Button
                type="button"
                variant="danger"
                onClick={() =>
                  twoFactorForm.delete(route('two-factor.disable'), { preserveScroll: true })
                }
                disabled={!twoFactorForm.data.password || twoFactorForm.processing}
              >
                Disable 2FA
              </Button>
            </div>
            <InputError message={twoFactorForm.errors.password} className="text-xs" />
          </div>
        ) : security?.two_factor_setup ? (
          <div className="space-y-3">
            <TwoFactorSetupPanel setup={security.two_factor_setup} />
            <div className="flex flex-col gap-2 sm:flex-row">
              <input
                value={twoFactorForm.data.code}
                onChange={(event) => twoFactorForm.setData('code', event.target.value)}
                placeholder="6-digit code"
                className="ui-input flex-1 font-mono"
                inputMode="numeric"
              />
              <Button
                type="button"
                onClick={() =>
                  twoFactorForm.post(route('two-factor.enable'), { preserveScroll: true })
                }
                disabled={twoFactorForm.data.code.length < 6 || twoFactorForm.processing}
              >
                Verify & enable
              </Button>
            </div>
            <InputError message={twoFactorForm.errors.code} className="text-xs" />
          </div>
        ) : (
          <Button
            type="button"
            variant="secondary"
            onClick={() =>
              twoFactorForm.post(route('two-factor.prepare'), { preserveScroll: true })
            }
          >
            <Smartphone className="h-4 w-4" />
            Start 2FA setup
          </Button>
        )}
      </Card>

      <Card className="p-5">
        <div className="mb-4 flex items-start gap-3">
          <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-ui-text-muted dark:bg-slate-700 dark:text-ui-dark-text-muted">
            <KeyRound className="h-4 w-4" />
          </span>
          <div>
            <div className="text-sm font-semibold text-ui-text dark:text-ui-dark-text">
              Sessions
            </div>
            <p className="mt-0.5 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
              Review active browser sessions and revoke old devices.
            </p>
          </div>
        </div>
        <div className="space-y-2">
          {(security?.sessions || []).map((session: any) => (
            <div
              key={session.id}
              className="flex items-center justify-between gap-3 rounded-card border border-gray-100 p-3 text-sm dark:border-ui-dark-border"
            >
              <div className="min-w-0">
                <div className="font-medium text-ui-text dark:text-ui-dark-text">
                  {session.ip_address || 'Unknown IP'} {session.is_current ? '(current)' : ''}
                </div>
                <div className="truncate text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                  {session.user_agent || 'Unknown device'}
                </div>
              </div>
              <div className="shrink-0 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                {new Date(session.last_activity).toLocaleString()}
              </div>
            </div>
          ))}
          {(security?.sessions || []).length === 0 && (
            <div className="rounded-card border border-gray-100 p-3 text-sm text-ui-text-muted dark:border-ui-dark-border dark:text-ui-dark-text-muted">
              No active sessions found.
            </div>
          )}
        </div>
        <div className="mt-4 flex flex-col gap-2 sm:flex-row">
          <input
            type="password"
            value={sessionForm.data.password}
            onChange={(event) => sessionForm.setData('password', event.target.value)}
            placeholder="Current password"
            className="ui-input flex-1"
          />
          <Button
            type="button"
            variant="secondary"
            onClick={() =>
              sessionForm.delete(route('profile.sessions.destroy-others'), { preserveScroll: true })
            }
            disabled={!sessionForm.data.password || sessionForm.processing}
          >
            Revoke other sessions
          </Button>
        </div>
        <InputError message={sessionForm.errors.password} className="mt-2 text-xs" />
      </Card>

      <Card className="border-red-200 p-5 dark:border-red-500/30">
        <Alert variant="error" title="Delete account">
          Once your account is deleted, all of its resources and data will be permanently deleted.
        </Alert>
        <Button variant="danger" onClick={deleteAccount} className="mt-4">
          <Trash2 className="h-4 w-4" />
          Delete account
        </Button>
      </Card>

      <Modal
        open={deletePasswordOpen}
        onClose={() => setDeletePasswordOpen(false)}
        title="Confirm account deletion"
        description="Enter your current password to permanently delete your account."
        footer={
          <>
            <Button type="button" variant="secondary" onClick={() => setDeletePasswordOpen(false)}>
              Cancel
            </Button>
            <Button
              type="button"
              variant="danger"
              onClick={confirmDeleteAccount}
              disabled={!deletePassword}
            >
              <Trash2 className="h-4 w-4" />
              Delete account
            </Button>
          </>
        }
      >
        <InputLabel
          htmlFor="delete-password"
          value="Current password"
          className="mb-1 text-xs font-medium text-ui-text dark:text-ui-dark-text"
        />
        <TextInput
          id="delete-password"
          type="password"
          value={deletePassword}
          onChange={(event) => setDeletePassword(event.target.value)}
          className="block h-9 w-full rounded-btn border-gray-200 text-sm focus:border-ui-green focus:ring-ui-green/15 dark:border-slate-600 dark:bg-slate-900"
          autoComplete="current-password"
          placeholder="Enter current password"
        />
      </Modal>
    </div>
  );
}
