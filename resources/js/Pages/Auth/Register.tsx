import { brandName } from '../../lib/brand';
import { AuthField, AuthInput, PasswordField } from '@/Components/Auth/AuthParts';
import Button from '@/Components/UI/Button';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, Mail } from 'lucide-react';
import { FormEventHandler } from 'react';

interface InviteInfo {
  token: string;
  email: string;
  account_name?: string | null;
  role?: string | null;
}

export default function Register({ invite }: { invite?: InviteInfo | null }) {
  const { data, setData, post, processing, errors, reset } = useForm({
    name: '',
    email: invite?.email || '',
    password: '',
    password_confirmation: '',
    invite_token: invite?.token || '',
  });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();

    post(route('register'), {
      onFinish: () => reset('password', 'password_confirmation'),
    });
  };

  return (
    <GuestLayout
      headerLabel="Already have an account?"
      headerLinkText="Sign in"
      headerLinkHref={route('login')}
    >
      <Head title="Create Your Account" />

      <div className="mb-6">
        <h1 className="text-3xl sm:text-4xl font-bold tracking-tight text-ui-text dark:text-ui-dark-text">
          {invite?.account_name ? `Join ${invite.account_name}` : `Set up ${brandName()}`}
        </h1>
        <p className="mt-2 text-ui-text-muted dark:text-ui-dark-text-muted">
          {invite?.account_name
            ? `You've been invited as a ${invite.role || 'member'}. Create your account to join the team.`
            : 'Create the owner account for your organisation. Teammates join by invitation.'}
        </p>
      </div>

      <form onSubmit={submit} className="space-y-4">
        <AuthField htmlFor="name" label="Full name" error={errors.name}>
          <AuthInput
            id="name"
            name="name"
            value={data.name}
            autoComplete="name"
            autoFocus
            onChange={(e) => setData('name', e.target.value)}
            placeholder="Rohan Mehta"
            required
          />
        </AuthField>

        <AuthField htmlFor="email" label="Work email" error={errors.email}>
          <div className="relative">
            <Mail className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
            <AuthInput
              id="email"
              type="email"
              name="email"
              value={data.email}
              className="pl-10"
              autoComplete="username"
              onChange={(e) => setData('email', e.target.value)}
              placeholder="rohan@company.com"
              required
              readOnly={Boolean(invite?.email)}
            />
          </div>
        </AuthField>

        <PasswordField
          id="password"
          label="Create password"
          value={data.password}
          onChange={(e) => setData('password', e.target.value)}
          autoComplete="new-password"
          placeholder="At least 8 characters"
          error={errors.password}
        />

        <PasswordField
          id="password_confirmation"
          label="Confirm password"
          value={data.password_confirmation}
          onChange={(e) => setData('password_confirmation', e.target.value)}
          autoComplete="new-password"
          placeholder="Confirm your password"
          error={errors.password_confirmation}
        />

        <label className="flex items-start gap-2 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
          <input type="checkbox" className="mt-0.5 h-4 w-4 rounded accent-ui-green" required />I
          understand this account administers this {brandName()} install.
        </label>

        <Button type="submit" disabled={processing} className="w-full h-11">
          {processing ? (
            'Creating account...'
          ) : (
            <>
              Create Account
              <ArrowRight className="h-4 w-4" />
            </>
          )}
        </Button>
      </form>

      <div className="mt-6 text-center space-y-2">
        <p className="text-sm text-ui-text-muted">
          Already have an account?{' '}
          <Link href={route('login')} className="font-semibold text-ui-green-dark hover:underline">
            Sign in
          </Link>
        </p>
      </div>
    </GuestLayout>
  );
}
