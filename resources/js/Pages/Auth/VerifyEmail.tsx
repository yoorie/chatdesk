import { AuthCard } from '@/Components/Auth/AuthParts';
import { Alert } from '@/Components/UI/Alert';
import Button from '@/Components/UI/Button';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { CheckCircle, LogOut, Mail, Send } from 'lucide-react';
import { FormEventHandler } from 'react';

export default function VerifyEmail({ status }: { status?: string }) {
  const { post, processing } = useForm({});

  const submit: FormEventHandler = (e) => {
    e.preventDefault();

    post(route('verification.send'));
  };

  return (
    <GuestLayout>
      <Head title="Email Verification" />

      <AuthCard className="text-center">
        <div className="mb-6">
          <div className="inline-flex items-center justify-center w-20 h-20 rounded-full bg-ui-green-soft mb-4">
            <Mail className="h-10 w-10 text-ui-green-dark" />
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-ui-text dark:text-ui-dark-text mb-2">
            Verify your email
          </h1>
          <p className="text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
            Thanks for signing up! Before getting started, could you verify your email address by
            clicking on the link we just emailed to you?
          </p>
        </div>

        {status === 'verification-link-sent' && (
          <Alert variant="success" className="mb-6">
            <CheckCircle className="h-4 w-4" />
            <div>
              <p className="font-medium">Verification link sent!</p>
              <p className="text-sm mt-1">
                A new verification link has been sent to the email address you provided during
                registration.
              </p>
            </div>
          </Alert>
        )}

        <form onSubmit={submit} className="space-y-4">
          <Button type="submit" disabled={processing} className="w-full h-11">
            {processing ? (
              'Sending...'
            ) : (
              <>
                <Send className="h-4 w-4" />
                Resend Verification Email
              </>
            )}
          </Button>

          <Link
            href={route('logout')}
            method="post"
            as="button"
            className="block w-full text-center text-sm font-medium text-ui-text-muted hover:text-ui-text transition-colors"
          >
            <LogOut className="h-4 w-4 inline mr-1" />
            Log Out
          </Link>
        </form>
      </AuthCard>
    </GuestLayout>
  );
}
