import { brandName } from '../../lib/brand';
import {
  AuthDivider,
  AuthField,
  AuthInput,
  PasswordField,
  SocialAuthButtons,
} from '@/Components/Auth/AuthParts';
import Button from '@/Components/UI/Button';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, Mail, Sparkles, Star } from 'lucide-react';
import { FormEventHandler } from 'react';

interface SelectedPlan {
  id: number;
  key: string;
  name: string;
  description: string;
  price_monthly: number;
  trial_days: number;
}

interface InviteInfo {
  token: string;
  email: string;
  account_name?: string | null;
  role?: string | null;
}

export default function Register({
  selectedPlan,
  invite,
  googleOAuthEnabled = false,
}: {
  selectedPlan?: SelectedPlan | null;
  invite?: InviteInfo | null;
  googleOAuthEnabled?: boolean;
}) {
  const { data, setData, post, processing, errors, reset } = useForm({
    name: '',
    email: invite?.email || '',
    password: '',
    password_confirmation: '',
    plan_key: selectedPlan?.key || '',
    invite_token: invite?.token || '',
  });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();

    post(route('register'), {
      onFinish: () => reset('password', 'password_confirmation'),
    });
  };

  const formatPrice = (amount: number) => {
    if (amount === 0) return '₹0';
    const major = amount / 100;
    return new Intl.NumberFormat('en-IN', {
      style: 'currency',
      currency: 'INR',
      minimumFractionDigits: 0,
    }).format(major);
  };

  const googleSignupHref = googleOAuthEnabled
    ? route('auth.google.redirect', {
        intent: 'signup',
        ...(selectedPlan?.key ? { plan: selectedPlan.key } : {}),
        ...(invite?.token ? { invite: invite.token } : {}),
      })
    : undefined;

  return (
    <GuestLayout
      headerLabel="Already have an account?"
      headerLinkText="Sign in"
      headerLinkHref={route('login')}
    >
      <Head title="Create Your Account" />

      <div className="mb-6">
        <h1 className="text-3xl sm:text-4xl font-bold tracking-tight text-ui-text dark:text-ui-dark-text">
          {invite?.account_name
            ? `Join ${invite.account_name}`
            : selectedPlan
              ? `Start Your ${selectedPlan.trial_days > 0 ? selectedPlan.trial_days + '-Day ' : ''}Trial`
              : `Start your ${brandName()} trial`}
        </h1>
        <p className="mt-2 text-ui-text-muted dark:text-ui-dark-text-muted">
          {invite?.account_name
            ? `You've been invited as a ${invite.role || 'member'}. Create your account to join the team.`
            : selectedPlan
              ? `Get started with ${selectedPlan.name} plan. ${selectedPlan.trial_days > 0 ? 'No credit card required!' : ''}`
              : 'Choose a paid plan with a trial window. Cancel anytime.'}
        </p>
      </div>

      {/* Selected Plan Badge */}
      {selectedPlan && (
        <div className="mb-6 rounded-xl border border-ui-green/20 bg-ui-green-soft p-4 dark:bg-ui-dark-green-soft">
          <div className="flex items-center justify-between">
            <div>
              <div className="flex items-center gap-2 mb-1">
                <h3 className="font-semibold text-gray-900 dark:text-gray-100">
                  {selectedPlan.name} Plan
                </h3>
                {selectedPlan.trial_days > 0 && (
                  <span className="inline-flex items-center gap-1 px-2 py-0.5 bg-white text-ui-green-dark text-xs font-bold rounded-full">
                    <Star className="h-3 w-3" />
                    {selectedPlan.trial_days}-Day Trial
                  </span>
                )}
              </div>
              <p className="text-sm text-ui-text-muted">{selectedPlan.description}</p>
              <p className="text-sm font-semibold text-ui-text mt-1">
                {formatPrice(selectedPlan.price_monthly)}/month
                {selectedPlan.trial_days > 0 && (
                  <span className="text-ui-green-dark ml-2">
                    - Free for {selectedPlan.trial_days} days
                  </span>
                )}
              </p>
            </div>
            <Link
              href={route('pricing')}
              className="text-xs text-ui-green-dark hover:underline"
            >
              Change Plan
            </Link>
          </div>
        </div>
      )}

      <SocialAuthButtons googleEnabled={googleOAuthEnabled} googleHref={googleSignupHref} />
      <AuthDivider label="Or sign up with email" />

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
          agree to the Terms and Privacy Policy and consent to WhatsApp Business API onboarding.
        </label>

        <Button type="submit" disabled={processing} className="w-full h-11">
          {processing ? (
            'Creating account...'
          ) : (
            <>
              {(selectedPlan?.trial_days ?? 0) > 0 ? (
                <>
                  Start Free Trial
                  <Sparkles className="h-4 w-4" />
                </>
              ) : (
                <>
                  Create Account
                  <ArrowRight className="h-4 w-4" />
                </>
              )}
            </>
          )}
        </Button>
      </form>

      <div className="mt-6 text-center space-y-2">
        <p className="text-sm text-ui-text-muted">
          Already have an account?{' '}
          <Link
            href={route('login')}
            className="font-semibold text-ui-green-dark hover:underline"
          >
            Sign in
          </Link>
        </p>
        {selectedPlan && (
          <p className="text-xs text-ui-text-muted">
            By signing up, you agree to our{' '}
            <Link href={route('terms')} className="underline">
              Terms of Service
            </Link>{' '}
            and{' '}
            <Link href={route('privacy')} className="underline">
              Privacy Policy
            </Link>
          </p>
        )}
      </div>
    </GuestLayout>
  );
}
