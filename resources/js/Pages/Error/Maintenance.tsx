import { pathIncludes } from '@/lib/appUrl';
import { Head, usePage } from '@inertiajs/react';
import { LogIn, Settings, Wrench } from 'lucide-react';
import ErrorFrame from './ErrorFrame';

interface MaintenanceProps {
  message?: string;
}

export default function Maintenance({ message }: MaintenanceProps) {
  const isPlatform = pathIncludes(usePage().url, '/platform');
  const platformHref =
    typeof route !== 'undefined' ? route('platform.settings') : '/platform/settings';
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
            label: isPlatform ? 'Platform settings' : 'Admin login',
            href: isPlatform ? platformHref : loginHref,
            icon: isPlatform ? Settings : LogIn,
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
