import { brandName } from '../../lib/brand';
import {
  AuthDivider,
  AuthField,
  AuthInput,
  PasswordField,
  SocialAuthButtons,
} from '@/Components/Auth/AuthParts';
import Checkbox from '@/Components/Checkbox';
import { Alert } from '@/Components/UI/Alert';
import Button from '@/Components/UI/Button';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, ArrowRight, Mail } from 'lucide-react';
import { FormEventHandler } from 'react';

export default function Login({
  status,
  canResetPassword,
  googleOAuthEnabled = false,
}: {
  status?: string;
  canResetPassword: boolean;
  googleOAuthEnabled?: boolean;
}) {
  const demo = (usePage().props as any).demo as any;
  const { data, setData, post, processing, errors, reset } = useForm({
    email: '',
    password: '',
    remember: false as boolean,
  });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();

    post(route('login'), {
      onFinish: () => reset('password'),
    });
  };

  return (
    <GuestLayout
      headerLabel="New here?"
      headerLinkText="Create account"
      headerLinkHref={route('register')}
    >
      <Head title="Log in" />

      <div className="mb-7">
        <h1 className="text-3xl sm:text-4xl font-bold tracking-tight text-ui-text dark:text-ui-dark-text">
          Welcome back
        </h1>
        <p className="mt-2 text-ui-text-muted dark:text-ui-dark-text-muted">
          Sign in to continue to your {brandName()} workspace.
        </p>
      </div>

      {status && (
        <Alert variant="success" className="mb-6">
          <AlertCircle className="h-4 w-4" />
          {status}
        </Alert>
      )}

      <SocialAuthButtons
        googleEnabled={googleOAuthEnabled}
        googleHref={googleOAuthEnabled ? route('auth.google.redirect') : undefined}
      />
      <AuthDivider label="Or continue with email" />

      {demo?.enabled && (
        <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm dark:border-emerald-900 dark:bg-emerald-950/40">
          <p className="mb-2 font-semibold text-emerald-900 dark:text-emerald-200">Demo logins</p>
          <div className="flex flex-wrap gap-2">
            {([
              ['admin', 'Admin'],
              ['owner', 'Owner'],
              ['agent', 'Agent'],
            ] as const).map(([who, label]) => (
              <button
                key={who}
                type="button"
                title={`${demo[who].email} / ${demo[who].password}`}
                onClick={() => setData((d: any) => ({ ...d, email: demo[who].email, password: demo[who].password }))}
                className="inline-flex w-auto items-center rounded-lg bg-[#047857] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#065f46] focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300"
              >
                Fill {label}
              </button>
            ))}
          </div>
          <p className="mt-2 break-all text-xs text-emerald-900/80 dark:text-emerald-200/80">
            Admin: {demo.admin.email} / {demo.admin.password} · Owner: {demo.owner.email} / {demo.owner.password} · Agent: {demo.agent.email} / {demo.agent.password}
          </p>
        </div>
      )}

      <form onSubmit={submit} className="space-y-4">
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
              autoFocus
              required
              onChange={(e) => setData('email', e.target.value)}
              placeholder="rohan@company.com"
            />
          </div>
        </AuthField>

        <PasswordField
          id="password"
          value={data.password}
          onChange={(e) => setData('password', e.target.value)}
          autoComplete="current-password"
          placeholder="Enter your password"
          forgotHref={canResetPassword ? route('password.request') : undefined}
          error={errors.password}
        />

        <label className="flex items-center gap-2 text-sm text-ui-text cursor-pointer select-none dark:text-ui-dark-text">
          <Checkbox
            name="remember"
            checked={data.remember}
            onChange={(e) => setData('remember', (e.target.checked || false) as false)}
          />
          Keep me signed in for 30 days
        </label>

        <Button type="submit" disabled={processing} className="w-full h-11">
          {processing ? (
            'Signing in...'
          ) : (
            <>
              Sign In
              <ArrowRight className="h-4 w-4" />
            </>
          )}
        </Button>
      </form>

      <div className="mt-6 text-center">
        <p className="text-sm text-ui-text-muted">
          Don't have an account?{' '}
          <Link
            href={route('register')}
            className="font-semibold text-ui-green-dark hover:underline"
          >
            Sign up
          </Link>
        </p>
        <p className="mt-6 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
          By signing in you agree to our{' '}
          <Link href={route('terms')} className="underline">
            Terms
          </Link>{' '}
          and{' '}
          <Link href={route('privacy')} className="underline">
            Privacy Policy
          </Link>
          .
        </p>
      </div>
    </GuestLayout>
  );
}
