import { Head } from '@inertiajs/react';
import { Clock } from 'lucide-react';
import ErrorFrame from './ErrorFrame';

export default function RateLimited() {
  return (
    <>
      <Head title="Too many requests" />
      <ErrorFrame
        code="429"
        title="Too many requests"
        description="Please wait a moment before trying again."
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
