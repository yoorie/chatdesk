import { AuthCard, PasswordField } from '@/Components/Auth/AuthParts';
import Button from '@/Components/UI/Button';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { ArrowRight, Shield } from 'lucide-react';
import { FormEventHandler } from 'react';

export default function ConfirmPassword() {
  const { data, setData, post, processing, errors, reset } = useForm({
    password: '',
  });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();

    post(route('password.confirm'), {
      onFinish: () => reset('password'),
    });
  };

  return (
    <GuestLayout>
      <Head title="Confirm Password" />

      <AuthCard>
        <div className="mb-6 text-center">
          <div className="inline-flex items-center justify-center w-20 h-20 rounded-full bg-ui-green-soft mb-4">
            <Shield className="h-10 w-10 text-ui-green-dark" />
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-ui-text dark:text-ui-dark-text mb-2">
            Confirm password
          </h1>
          <p className="text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
            This is a secure area of the application. Please confirm your password before
            continuing.
          </p>
        </div>

        <form onSubmit={submit} className="space-y-5">
          <PasswordField
            id="password"
            value={data.password}
            onChange={(e) => setData('password', e.target.value)}
            placeholder="Enter your password"
            error={errors.password}
          />

          <Button type="submit" disabled={processing} className="w-full h-11">
            {processing ? (
              'Confirming...'
            ) : (
              <>
                Confirm Password
                <ArrowRight className="h-4 w-4" />
              </>
            )}
          </Button>
        </form>
      </AuthCard>
    </GuestLayout>
  );
}
