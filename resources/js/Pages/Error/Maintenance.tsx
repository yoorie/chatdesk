import { Head } from '@inertiajs/react';
import { LogIn, Wrench } from 'lucide-react';
import ErrorFrame from './ErrorFrame';

interface MaintenanceProps {
  message?: string;
}

export default function Maintenance({ message }: MaintenanceProps) {
  const loginHref = typeof route !== 'undefined' ? route('login') : '/login';

  return (
    <>
      <Head title="Maintenance" />
      <ErrorFrame
        code="503"
        title="We'll be back soon"
        description={
          message || 'We are currently performing scheduled maintenance. Please check back shortly.'
        }
        icon={Wrench}
        accent="amber"
        actions={[
          {
            label: 'Admin login',
            href: loginHref,
            icon: LogIn,
            primary: true,
          },
          {
            label: 'Refresh',
            onClick: () => window.location.reload(),
          },
        ]}
      />
    </>
  );
}
