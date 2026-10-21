import { AuthCard, AuthField, AuthInput, PasswordField } from '@/Components/Auth/AuthParts';
import Button from '@/Components/UI/Button';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { Check, Mail } from 'lucide-react';
import { FormEventHandler } from 'react';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
  const { data, setData, post, processing, errors, reset } = useForm({
    token: token,
    email: email,
    password: '',
    password_confirmation: '',
  });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();

    post(route('password.store'), {
      onFinish: () => reset('password', 'password_confirmation'),
    });
  };

  return (
    <GuestLayout>
      <Head title="Reset Password" />

      <AuthCard>
        <h1 className="text-center text-2xl font-bold text-ui-text dark:text-ui-dark-text">
          Set a new password
        </h1>
        <p className="mt-2 text-center text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
          Must be at least 8 characters with one number.
        </p>

        <form onSubmit={submit} className="mt-7 space-y-4">
          <AuthField htmlFor="email" label="Work email" error={errors.email}>
            <div className="relative">
              <Mail className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
              <AuthInput
                id="email"
                type="email"
                name="email"
                value={data.email}
                className="bg-gray-50 pl-10 dark:bg-ui-dark-surface-2"
                autoComplete="username"
                onChange={(e) => setData('email', e.target.value)}
                disabled
              />
            </div>
          </AuthField>

          <PasswordField
            id="password"
            label="New password"
            value={data.password}
            autoComplete="new-password"
            placeholder="Enter new password"
            onChange={(e) => setData('password', e.target.value)}
            error={errors.password}
          />
          <PasswordField
            id="password_confirmation"
            label="Confirm password"
            value={data.password_confirmation}
            autoComplete="new-password"
            placeholder="Confirm new password"
            onChange={(e) => setData('password_confirmation', e.target.value)}
            error={errors.password_confirmation}
          />

          <Button type="submit" disabled={processing} className="w-full">
            {processing ? (
              'Resetting...'
            ) : (
              <>
                <Check className="h-4 w-4" /> Update password
              </>
            )}
          </Button>
        </form>
      </AuthCard>
    </GuestLayout>
  );
}
