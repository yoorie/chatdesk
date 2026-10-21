import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import CountryPhoneInput, {
  splitPhoneNumber,
  timezoneForCountryCode,
} from '@/Components/Profile/CountryPhoneInput';
import TwoFactorSetupPanel from '@/Components/Security/TwoFactorSetupPanel';
import TextInput from '@/Components/TextInput';
import Button from '@/Components/UI/Button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/UI/Card';
import PlatformShell from '@/Layouts/PlatformShell';
import { Transition } from '@headlessui/react';
import { Head, useForm, usePage } from '@inertiajs/react';
import {
  Briefcase,
  CheckCircle2,
  Eye,
  EyeOff,
  KeyRound,
  Lock,
  Mail,
  Save,
  ShieldCheck,
  Smartphone,
  UserRound,
} from 'lucide-react';
import { FormEvent, useState } from 'react';
export default function Manage() {
  const { auth, security } = usePage().props as any;
  const user = auth.user;
  const parsedPhone = splitPhoneNumber(user.phone, user.country_code);
  const [visiblePasswords, setVisiblePasswords] = useState<Record<string, boolean>>({});

  const profileForm = useForm({
    name: user.name || '',
    email: user.email || '',
    country_code: parsedPhone.countryCode,
    phone: parsedPhone.localPhone,
    job_title: user.job_title || '',
    locale: user.locale || 'en-IN',
    timezone: user.timezone || timezoneForCountryCode(parsedPhone.countryCode),
  });

  const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
  });
  const twoFactorForm = useForm({ code: '', password: '' });
  const sessionForm = useForm({ password: '' });

  const updateProfile = (event: FormEvent) => {
    event.preventDefault();
    profileForm.patch(route('profile.update'), {
      preserveScroll: true,
    });
  };

  const updatePassword = (event: FormEvent) => {
    event.preventDefault();
    passwordForm.put(route('password.update'), {
      preserveScroll: true,
      onSuccess: () => passwordForm.reset(),
    });
  };

  const passwordType = (field: string) => (visiblePasswords[field] ? 'text' : 'password');

  return (
    <PlatformShell>
      <Head title="Profile" />

      <div className="w-full max-w-5xl space-y-5">
        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
          <Card>
            <CardHeader>
              <div className="flex items-start gap-3">
                <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-card bg-ui-green-soft text-ui-green-dark dark:bg-ui-green/10 dark:text-ui-green">
                  <UserRound className="h-5 w-5" />
                </div>
                <div>
                  <CardTitle>Admin profile</CardTitle>
                  <CardDescription>
                    Update the login identity used for platform administration.
                  </CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <form onSubmit={updateProfile} className="space-y-5">
                <div className="grid gap-5 md:grid-cols-2">
                  <div>
                    <InputLabel
                      htmlFor="name"
                      value="Full name"
                      className="mb-1 text-xs font-medium text-ui-text dark:text-ui-dark-text"
                    />
                    <div className="relative">
                      <UserRound className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                      <TextInput
                        id="name"
                        value={profileForm.data.name}
                        onChange={(event) => profileForm.setData('name', event.target.value)}
                        className="block h-10 w-full rounded-btn border-gray-200 pl-9 text-sm focus:border-ui-green focus:ring-ui-green/15 dark:border-slate-600 dark:bg-slate-900"
                        required
                      />
                    </div>
                    <InputError message={profileForm.errors.name} className="mt-2 text-xs" />
                  </div>

                  <div>
                    <InputLabel
                      htmlFor="email"
                      value="Email address"
                      className="mb-1 text-xs font-medium text-ui-text dark:text-ui-dark-text"
                    />
                    <div className="relative">
                      <Mail className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                      <TextInput
                        id="email"
                        type="email"
                        value={profileForm.data.email}
                        onChange={(event) => profileForm.setData('email', event.target.value)}
                        className="block h-10 w-full rounded-btn border-gray-200 pl-9 text-sm focus:border-ui-green focus:ring-ui-green/15 dark:border-slate-600 dark:bg-slate-900"
                        required
                      />
                    </div>
                    <InputError message={profileForm.errors.email} className="mt-2 text-xs" />
                  </div>
                </div>

                <CountryPhoneInput
                  countryCode={profileForm.data.country_code}
                  phone={profileForm.data.phone}
                  onCountryCodeChange={(value) =>
                    profileForm.setData((current) => ({
                      ...current,
                      country_code: value,
                      timezone: timezoneForCountryCode(value),
                    }))
                  }
                  onPhoneChange={(value) => profileForm.setData('phone', value)}
                  error={profileForm.errors.phone || profileForm.errors.country_code}
                />

                <div className="grid gap-5 md:grid-cols-2">
                  <div>
                    <InputLabel
                      htmlFor="job_title"
                      value="Role / title"
                      className="mb-1 text-xs font-medium text-ui-text dark:text-ui-dark-text"
                    />
                    <div className="relative">
                      <Briefcase className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                      <TextInput
                        id="job_title"
                        value={profileForm.data.job_title}
                        onChange={(event) => profileForm.setData('job_title', event.target.value)}
                        className="block h-10 w-full rounded-btn border-gray-200 pl-9 text-sm focus:border-ui-green focus:ring-ui-green/15 dark:border-slate-600 dark:bg-slate-900"
                        placeholder="Platform owner"
                      />
                    </div>
                    <InputError message={profileForm.errors.job_title} className="mt-2 text-xs" />
                  </div>

                  <div>
                    <InputLabel
                      htmlFor="locale"
                      value="Language"
                      className="mb-1 text-xs font-medium text-ui-text dark:text-ui-dark-text"
                    />
                    <select
                      id="locale"
                      value={profileForm.data.locale}
                      onChange={(event) => profileForm.setData('locale', event.target.value)}
                      className="h-10 w-full rounded-btn border border-gray-200 bg-white px-3 text-sm text-ui-text outline-none transition focus:border-ui-green focus:ring-2 focus:ring-ui-green/15 dark:border-slate-600 dark:bg-slate-900 dark:text-ui-dark-text"
                    >
                      <option value="en-IN">English (India)</option>
                      <option value="en-US">English (US)</option>
                      <option value="hi-IN">Hindi</option>
                      <option value="ta-IN">Tamil</option>
                      <option value="te-IN">Telugu</option>
                      <option value="mr-IN">Marathi</option>
                      <option value="bn-IN">Bengali</option>
                    </select>
                    <InputError message={profileForm.errors.locale} className="mt-2 text-xs" />
                  </div>
                </div>

                <div className="flex items-center justify-end gap-4 border-t border-gray-100 pt-4 dark:border-slate-700">
                  <Transition
                    show={profileForm.recentlySuccessful}
                    enter="transition ease-in-out"
                    enterFrom="opacity-0"
                    leave="transition ease-in-out"
                    leaveTo="opacity-0"
                  >
                    <div className="flex items-center gap-2 text-sm text-emerald-600 dark:text-emerald-300">
                      <CheckCircle2 className="h-4 w-4" />
                      Saved
                    </div>
                  </Transition>
                  <Button type="submit" disabled={profileForm.processing}>
                    {profileForm.processing ? (
                      'Saving...'
                    ) : (
                      <>
                        <Save className="h-4 w-4" />
                        Save profile
                      </>
                    )}
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>

          <div className="space-y-4">
            <Card className="p-5">
              <div className="flex items-start gap-3">
                <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-card bg-ui-green-soft text-ui-green-dark dark:bg-ui-green/10 dark:text-ui-green">
                  <ShieldCheck className="h-5 w-5" />
                </div>
                <div>
                  <div className="text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                    Super admin access
                  </div>
                  <p className="mt-1 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                    This account can manage platform settings, billing, users, plans, and support
                    operations.
                  </p>
                </div>
              </div>
            </Card>

            <Card className="p-5">
              <div className="text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                Login email
              </div>
              <div className="mt-1 break-all text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                {user.email}
              </div>
            </Card>
          </div>
        </div>

        <Card>
          <CardHeader>
            <div className="flex items-start gap-3">
              <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-card bg-ui-green-soft text-ui-green-dark dark:bg-ui-green/10 dark:text-ui-green">
                <KeyRound className="h-5 w-5" />
              </div>
              <div>
                <CardTitle>Password</CardTitle>
                <CardDescription>
                  Change the password used to sign in to the admin panel.
                </CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            <form onSubmit={updatePassword} className="space-y-5">
              <div className="grid gap-5 md:grid-cols-3">
                {[
                  [
                    'current_password',
                    'Current password',
                    'current-password',
                    'Enter current password',
                  ],
                  ['password', 'New password', 'new-password', 'Enter new password'],
                  [
                    'password_confirmation',
                    'Confirm password',
                    'new-password',
                    'Confirm new password',
                  ],
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
                        type={passwordType(field)}
                        value={passwordForm.data[field as keyof typeof passwordForm.data]}
                        onChange={(event) =>
                          passwordForm.setData(
                            field as keyof typeof passwordForm.data,
                            event.target.value
                          )
                        }
                        className="block h-10 w-full rounded-btn border-gray-200 pl-9 pr-10 text-sm focus:border-ui-green focus:ring-ui-green/15 dark:border-slate-600 dark:bg-slate-900"
                        autoComplete={autocomplete}
                        placeholder={placeholder}
                      />
                      <button
                        type="button"
                        onClick={() =>
                          setVisiblePasswords((current) => ({
                            ...current,
                            [field]: !current[field],
                          }))
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
                    <InputError
                      message={passwordForm.errors[field as keyof typeof passwordForm.errors]}
                      className="mt-2 text-xs"
                    />
                  </div>
                ))}
              </div>

              <div className="flex items-center justify-end gap-4 border-t border-gray-100 pt-4 dark:border-slate-700">
                <Transition
                  show={passwordForm.recentlySuccessful}
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
                <Button type="submit" disabled={passwordForm.processing}>
                  {passwordForm.processing ? (
                    'Updating...'
                  ) : (
                    <>
                      <Save className="h-4 w-4" />
                      Update password
                    </>
                  )}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <div className="flex items-start gap-3">
              <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-card bg-ui-green-soft text-ui-green-dark dark:bg-ui-green/10 dark:text-ui-green">
                <ShieldCheck className="h-5 w-5" />
              </div>
              <div>
                <CardTitle>Security</CardTitle>
                <CardDescription>
                  Protect this admin login with 2FA and session controls.
                </CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent className="space-y-5">
            {user.two_factor_enabled ? (
              <div className="space-y-3">
                <div className="rounded-card border border-emerald-100 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200">
                  2FA is enabled.
                </div>
                <div className="flex flex-col gap-2 sm:flex-row">
                  <TextInput
                    type="password"
                    value={twoFactorForm.data.password}
                    onChange={(event) => twoFactorForm.setData('password', event.target.value)}
                    placeholder="Current password"
                    className="h-10 flex-1"
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
                  <TextInput
                    value={twoFactorForm.data.code}
                    onChange={(event) => twoFactorForm.setData('code', event.target.value)}
                    placeholder="6-digit code"
                    className="h-10 flex-1 font-mono"
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

            <div className="border-t border-gray-100 pt-5 dark:border-slate-700">
              <div className="mb-3 text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                Active sessions
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
              </div>
              <div className="mt-4 flex flex-col gap-2 sm:flex-row">
                <TextInput
                  type="password"
                  value={sessionForm.data.password}
                  onChange={(event) => sessionForm.setData('password', event.target.value)}
                  placeholder="Current password"
                  className="h-10 flex-1"
                />
                <Button
                  type="button"
                  variant="secondary"
                  onClick={() =>
                    sessionForm.delete(route('profile.sessions.destroy-others'), {
                      preserveScroll: true,
                    })
                  }
                  disabled={!sessionForm.data.password || sessionForm.processing}
                >
                  Revoke other sessions
                </Button>
              </div>
              <InputError message={sessionForm.errors.password} className="mt-2 text-xs" />
            </div>
          </CardContent>
        </Card>
      </div>
    </PlatformShell>
  );
}
