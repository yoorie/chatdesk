import { pathIncludes } from '@/lib/appUrl';
import { Head, usePage } from '@inertiajs/react';
import { RefreshCw, ServerCrash } from 'lucide-react';
import ErrorFrame, { defaultBackAction, defaultDashboardAction } from './ErrorFrame';

export default function ServerError() {
  const isApp = pathIncludes(usePage().url, '/app');
  const actions = [
    { label: 'Retry', icon: RefreshCw, onClick: () => window.location.reload(), primary: true },
    defaultBackAction(),
    ...(isApp ? [defaultDashboardAction()] : []),
  ];

  return (
    <>
      <Head title="Something went wrong" />
      <ErrorFrame
        code="500"
        title="Something went wrong"
        description="We couldn’t complete your request. Try again in a moment."
        icon={ServerCrash}
        accent="red"
        actions={actions}
      />
    </>
  );
}
