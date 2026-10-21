import { Head } from '@inertiajs/react';
import { Clock } from 'lucide-react';
import ErrorFrame from './ErrorFrame';

export default function SessionExpired() {
  return (
    <>
      <Head title="Session expired" />
      <ErrorFrame
        code="419"
        title="Session expired"
        description="Your session has expired. Refresh the page and try again."
        icon={Clock}
        accent="amber"
        actions={[
          { label: 'Try again', primary: true, onClick: () => window.location.reload() },
          { label: 'Home', href: typeof route !== 'undefined' ? route('landing') : '/' },
        ]}
      />
    </>
  );
}
