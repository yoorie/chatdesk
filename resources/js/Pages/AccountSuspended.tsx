import { Head } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import ErrorFrame from './Error/ErrorFrame';

export default function AccountSuspended({
  account,
}: {
  account: { name: string; status: string; disabled_reason: string | null };
}) {
  return (
    <>
      <Head title="Account disabled" />
      <ErrorFrame
        code="Account disabled"
        title="Workspace unavailable"
        description={`The workspace ${account.name} has been disabled. Contact support if you believe this is an error.`}
        icon={AlertCircle}
        accent="amber"
        actions={[
          {
            label: 'Home',
            href: typeof route !== 'undefined' ? route('landing') : '/',
            primary: true,
          },
        ]}
      >
        {account.disabled_reason && (
          <p className="error-detail">
            <strong>Reason:</strong> {account.disabled_reason}
          </p>
        )}
      </ErrorFrame>
    </>
  );
}
